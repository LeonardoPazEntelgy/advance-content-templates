<?php
class ACT_Template {
    private static $post_types = array();
    private static $templates = array();

    private static function current_user_can_edit_templates() {
        return current_user_can('edit_posts') || current_user_can('edit_pages');
    }

    public static function init() {
        // Registrar hooks para plantillas
        add_filter('theme_page_templates', [__CLASS__, 'add_template_to_dropdown'], 10, 3);
        add_filter('theme_post_templates', [__CLASS__, 'add_template_to_dropdown'], 10, 3);
        add_filter('template_include', [__CLASS__, 'load_template'], 99);
        
        // Registro dinámico para CPTs
        add_action('registered_post_type', [__CLASS__, 'register_cpt_templates'], 10, 2);
        add_action('current_screen', [__CLASS__, 'register_existing_cpts_late']);
        
        // Integración especial con ACF
        add_action('acf/init', [__CLASS__, 'register_acf_templates']);
        
        // Forzar actualización de campos ACF en el admin
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }

    public static function enqueue_admin_scripts() {
        wp_enqueue_script(
            'act-admin-acf-integration',
            plugins_url('assets/js/acf-integration.js', dirname(__FILE__)),
            ['jquery'],
            ACT_VERSION,
            true
        );
    }

    public static function register_acf_templates() {
        if (!function_exists('acf')) return;
        
        // Asegurar que nuestras plantillas aparezcan en ACF
        add_filter('acf/location/rule_values/page_template', [__CLASS__, 'inject_templates_to_acf']);
        add_filter('acf/location/rule_values/post_template', [__CLASS__, 'inject_templates_to_acf']);
        
        // Manejar la lógica de coincidencia
        add_filter('acf/location/rule_match/page_template', [__CLASS__, 'match_acf_template_rule'], 20, 3);
        add_filter('acf/location/rule_match/post_template', [__CLASS__, 'match_acf_template_rule'], 20, 3);
        
        // Forzar recarga de campos cuando cambia la plantilla
        add_filter('acf/location/current_screen', [__CLASS__, 'update_acf_current_screen']);
    }

    public static function update_acf_current_screen($screen) {
        global $post;
        
        if (isset($post->ID)) {
            $template_id = get_post_meta($post->ID, '_wp_page_template', true);
            if (!empty($template_id) && strpos($template_id, 'act_') === 0) {
                $screen['post_template'] = $template_id;
            }
        }
        
        return $screen;
    }

    public static function inject_templates_to_acf($choices) {
        $templates = self::get_templates();
        
        foreach ($templates as $id => $template) {
            $choices[$id] = $template['name'] . ' (ACT)';
        }
        
        return $choices;
    }

    public static function match_acf_template_rule($match, $rule, $options) {
        $current_template = get_page_template_slug($options['post_id']);
        $rule_template = $rule['value'];
        
        if (strpos($rule_template, 'act_') === 0) {
            return $current_template === $rule_template;
        }
        
        return $match;
    }

    public static function register_existing_cpts_late() {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->base, ['post', 'page'])) return;
        
        $post_type = $screen->post_type;
        $post_type_obj = get_post_type_object($post_type);
        
        if ($post_type_obj && !in_array($post_type, self::$post_types)) {
            self::register_cpt_templates($post_type, $post_type_obj);
        }
    }

    public static function register_cpt_templates($post_type, $args) {
        if (in_array($post_type, ['post', 'page'])) return;
        if (!$args->show_ui || !$args->public) return;
        
        if (!in_array($post_type, self::$post_types)) {
            self::$post_types[] = $post_type;
            add_filter("theme_{$post_type}_templates", [__CLASS__, 'add_template_to_dropdown'], 10, 3);
            add_action("add_meta_boxes_{$post_type}", [__CLASS__, 'force_attributes_metabox']);
        }
    }
    
    public static function force_attributes_metabox() {
        $screen = get_current_screen();
        if (!post_type_supports($screen->post_type, 'page-attributes')) {
            add_post_type_support($screen->post_type, 'page-attributes');
        }
    }

    public static function add_template_to_dropdown($templates, $theme, $post) {
        if (!is_object($post)) {
            global $post;
        }
        
        if (!is_object($post) || !isset($post->post_type)) {
            return $templates;
        }
        
        $stored_templates = self::get_templates();
        
        foreach ($stored_templates as $id => $template) {
            if (in_array($post->post_type, $template['post_types'])) {
                $templates[$id] = $template['name'] . ' (ACT)';
            }
        }
        
        return $templates;
    }

    public static function load_template($template) {
        global $post;
        
        if (!$post || !is_object($post) || !isset($post->ID)) {
            return $template;
        }
        
        $template_id = get_post_meta($post->ID, '_wp_page_template', true);
        
        if (empty($template_id)) {
            return $template;
        }

        $stored_templates = self::get_templates();
        
        if (isset($stored_templates[$template_id])) {
            $selected_template = $stored_templates[$template_id];
            
            // Informar a ACF sobre la plantilla actual en front-end
            add_filter('acf/location/current_screen', function($screen) use ($template_id) {
                $screen['post_template'] = $template_id;
                return $screen;
            });
            
            $file = locate_template(array($template_id));
            if ($file) return $file;
            
            if (!empty($selected_template['content'])) {
                add_filter('the_content', function($content) use ($selected_template) {
                    return $selected_template['content'];
                }, 999);
            }
        }
        
        return $template;
    }

    public static function save_template($template_data) {
        $templates = self::get_templates();
        
        $id = 'act_' . sanitize_title($template_data['name']) . '.php';
        
        $templates[$id] = array(
            'name' => sanitize_text_field($template_data['name']),
            'description' => sanitize_textarea_field($template_data['description']),
            'content' => wp_kses_post($template_data['content']),
            'post_types' => array_map('sanitize_key', $template_data['post_types']),
            'created' => current_time('mysql'),
            'updated' => current_time('mysql')
        );
        
        update_option('act_stored_templates', $templates);
        self::refresh_templates_cache();
        
        return $id;
    }

    public static function get_templates() {
        return get_option('act_stored_templates', array());
    }

    public static function get_template($id) {
        $templates = self::get_templates();
        return isset($templates[$id]) ? $templates[$id] : false;
    }

    public static function delete_template($id) {
        $templates = self::get_templates();
        
        if (isset($templates[$id])) {
            unset($templates[$id]);
            update_option('act_stored_templates', $templates);
            self::refresh_templates_cache();
            return true;
        }
        
        return false;
    }

    public static function refresh_templates_cache() {
        wp_cache_delete('act_stored_templates', 'options');
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        
        do_action('act_refresh_templates_cache');
    }
}