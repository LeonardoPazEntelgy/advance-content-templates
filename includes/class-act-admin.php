<?php
class ACT_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('admin_init', array(__CLASS__, 'save_user_filter_preferences')); // Añadido
    }

    // Añadir menú de administración
    public static function add_admin_menu() {
        add_menu_page(
            __('Advanced Content Templates', 'advanced-content-templates'),
            __('Content Templates', 'advanced-content-templates'),
            'manage_options',
            'advanced-content-templates',
            array(__CLASS__, 'render_admin_page'),
            'dashicons-media-document',
            30
        );
        
        // Subpáginas
        add_submenu_page(
            'advanced-content-templates',
            __('Add New Template', 'advanced-content-templates'),
            __('Add New', 'advanced-content-templates'),
            'manage_options',
            'act-add-new',
            array(__CLASS__, 'render_add_new_page')
        );
    }

    // Cargar scripts y estilos
    public static function enqueue_scripts($hook) {
        // Solo cargar en nuestras páginas admin
        $valid_pages = [
            'toplevel_page_advanced-content-templates',
            'advanced-content-templates_page_act-add-new'
        ];
        
        if (!in_array($hook, $valid_pages)) {
            return;
        }
    
        // Cargar dashicons para los indicadores de ordenación
        wp_enqueue_style('dashicons');
        
        // CSS principal
        wp_enqueue_style(
            'act-admin-css',
            ACT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ACT_VERSION
        );
        
        // Registrar y cargar el script principal con dependencias
        wp_register_script(
            'act-admin-js',
            ACT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-util', 'wp-api-fetch'],
            ACT_VERSION,
            true
        );
    
        wp_enqueue_script('act-admin-js');
        
        // Datos para JavaScript - Actualizado con nuevas traducciones
        wp_localize_script('act-admin-js', 'act_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('act_admin_nonce'),
            'version' => ACT_VERSION,
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this template?', 'advanced-content-templates'),
                'delete' => __('Delete', 'advanced-content-templates'),
                'deleting' => __('Deleting...', 'advanced-content-templates'),
                'saving' => __('Saving...', 'advanced-content-templates'),
                'ajax_error' => __('AJAX error: %s', 'advanced-content-templates'),
                'delete_error' => __('Delete error: %s', 'advanced-content-templates'),
                'search_placeholder' => __('Search templates...', 'advanced-content-templates'),
                'no_results' => __('No templates found matching your criteria.', 'advanced-content-templates')
            ]
        ]);
        
        // Debug: Verificar carga del script
        if (WP_DEBUG) {
            error_log('ACT: Script admin.js cargado para hook: ' . $hook);
        }
    }

    // Renderizar página principal
    public static function render_admin_page() {
        $templates = ACT_Template::get_templates();
        $post_types = self::get_available_post_types();
        
        // Preparar datos para la vista
        $data = [
            'templates' => $templates,
            'post_types' => $post_types,
            'has_templates' => !empty($templates),
            'current_filters' => [
                'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
                'post_type' => isset($_GET['post_type_filter']) ? sanitize_text_field($_GET['post_type_filter']) : '',
                'date' => isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : ''
            ]
        ];
        
        // Cargar vista con todos los datos
        include ACT_PLUGIN_DIR . 'views/admin-page.php';
    }

    // Renderizar página de añadir nueva plantilla
    public static function render_add_new_page() {
        $post_types = get_post_types(array(
            'public' => true
        ), 'objects');
        
        // Eliminar tipos de contenido no deseados
        unset($post_types['attachment'], $post_types['revision'], $post_types['nav_menu_item']);
        
        include ACT_PLUGIN_DIR . 'views/add-new-template.php';
    }

    // Procesar formulario de guardado
    public static function handle_save_template() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'act_admin_nonce')) {
            wp_send_json_error(__('Security check failed', 'advanced-content-templates'));
        }
    
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'advanced-content-templates'));
        }
    
        // Validar datos requeridos
        if (empty($_POST['name']) || empty($_POST['content']) || empty($_POST['post_types'])) {
            wp_send_json_error(__('Please fill all required fields', 'advanced-content-templates'));
        }
    
        // Preparar datos de la plantilla
        $template_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'content' => wp_kses_post($_POST['content']),
            'post_types' => array_map('sanitize_key', (array)$_POST['post_types'])
        );
    
        $templates = get_option('act_stored_templates', array());
    
        try {
            // Si estamos editando
            if (!empty($_POST['template_id']) && isset($templates[$_POST['template_id']])) {
                $template_id = sanitize_key($_POST['template_id']);
                $templates[$template_id] = array_merge($templates[$template_id], array(
                    'name' => $template_data['name'],
                    'description' => $template_data['description'],
                    'content' => $template_data['content'],
                    'post_types' => $template_data['post_types'],
                    'updated' => current_time('mysql')
                ));
            } else {
                // Nueva plantilla
                $template_id = sanitize_title($template_data['name']) . '-' . uniqid();
                $template_data['created'] = current_time('mysql');
                $template_data['updated'] = current_time('mysql');
                $templates[$template_id] = $template_data;
            }
    
            // Guardar plantillas
            $updated = update_option('act_stored_templates', $templates, false);
    
            if (!$updated) {
                throw new Exception(__('Failed to save template', 'advanced-content-templates'));
            }
    
            wp_send_json_success(array(
                'id' => $template_id,
                'message' => __('Template saved successfully!', 'advanced-content-templates'),
                'redirect' => empty($_POST['template_id']) // Redirigir solo para nuevas plantillas
            ));
    
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    // Procesar eliminación de plantilla
    public static function handle_delete_template() {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'act_admin_nonce')) {
            wp_send_json_error(__('Invalid security token', 'advanced-content-templates'));
        }
    
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'advanced-content-templates'));
        }
    
        // Verificar ID de plantilla
        if (empty($_POST['template_id'])) {
            wp_send_json_error(__('Template ID is required', 'advanced-content-templates'));
        }
    
        $template_id = sanitize_key($_POST['template_id']);
        $templates = get_option('act_stored_templates', array());
    
        if (!isset($templates[$template_id])) {
            wp_send_json_error(__('Template not found', 'advanced-content-templates'));
        }
    
        // Eliminar plantilla
        unset($templates[$template_id]);
        update_option('act_stored_templates', $templates);
    
        wp_send_json_success(array(
            'message' => __('Template deleted successfully', 'advanced-content-templates'),
            'template_id' => $template_id
        ));
    }
    /**
     * Obtener tipos de post públicos para filtros
     */
    public static function get_available_post_types() {
        $post_types = get_post_types(['public' => true], 'objects');
        $excluded = ['attachment', 'revision', 'nav_menu_item'];
        
        $available = [];
        foreach ($post_types as $post_type => $post_type_obj) {
            if (!in_array($post_type, $excluded)) {
                $available[$post_type] = $post_type_obj->labels->singular_name;
            }
        }
        
        return $available;
    }    
    /**
     * Filtrar plantillas según parámetros
     */
    public static function filter_templates($templates, $filters) {
        if (empty($filters)) {
            return $templates;
        }
        
        return array_filter($templates, function($template) use ($filters) {
            $matches = true;
            
            // Filtro por búsqueda
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $name = strtolower($template['name']);
                $desc = strtolower($template['description']);
                
                if (strpos($name, $search) === false && strpos($desc, $search) === false) {
                    $matches = false;
                }
            }
            
            // Filtro por tipo de post
            if (!empty($filters['post_type'])) {
                if (!in_array($filters['post_type'], $template['post_types'])) {
                    $matches = false;
                }
            }
            
            // Filtro por fecha
            if (!empty($filters['date'])) {
                $created = strtotime($template['created']);
                $now = current_time('timestamp');
                $diff = $now - $created;
                
                switch ($filters['date']) {
                    case 'today':
                        if (date('Y-m-d', $created) !== date('Y-m-d', $now)) {
                            $matches = false;
                        }
                        break;
                    case 'week':
                        if ($diff > WEEK_IN_SECONDS) {
                            $matches = false;
                        }
                        break;
                    case 'month':
                        if ($diff > MONTH_IN_SECONDS) {
                            $matches = false;
                        }
                        break;
                }
            }
            
            return $matches;
        });
    }

    // Nuevo método para manejar filtrado AJAX (opcional)
    public static function handle_filter_templates() {
        check_ajax_referer('act_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action', 'advanced-content-templates'));
        }
        
        $filters = [
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'post_type' => isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '',
            'date' => isset($_POST['date']) ? sanitize_text_field($_POST['date']) : ''
        ];
        
        $templates = ACT_Template::get_templates();
        $filtered = self::filter_templates($templates, $filters);
        
        ob_start();
        include ACT_PLUGIN_DIR . 'views/partials/template-list.php';
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'count' => count($filtered)
        ]);
    }

    /**
     * Guardar preferencias de filtro del usuario
     */
    public static function save_user_filter_preferences() {
        if (!empty($_POST['act_save_filters']) && check_admin_referer('act_save_filters', 'act_filter_nonce')) {
            $user_id = get_current_user_id();
            $filters = [
                'post_type' => isset($_POST['post_type_filter']) ? sanitize_text_field($_POST['post_type_filter']) : '',
                'date' => isset($_POST['date_filter']) ? sanitize_text_field($_POST['date_filter']) : ''
            ];
            
            update_user_meta($user_id, 'act_template_filters', $filters);
            
            // Redireccionar para evitar reenvío del formulario
            wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
            exit;
        }
    }

}

// Registrar handlers AJAX
add_action('wp_ajax_act_save_template', ['ACT_Admin', 'handle_save_template']);
add_action('wp_ajax_act_delete_template', ['ACT_Admin', 'handle_delete_template']);
add_action('wp_ajax_act_filter_templates', ['ACT_Admin', 'handle_filter_templates']);
// No necesitamos el nopriv ya que es solo para admin