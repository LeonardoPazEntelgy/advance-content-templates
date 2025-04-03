<?php
class ACT_ACF_Integration {
    public static function init() {
        if (!function_exists('acf')) return;

        // 1. Registrar plantillas en las opciones de ACF
        add_filter('acf/location/rule_values/page_template', [__CLASS__, 'inject_templates'], 20);
        add_filter('acf/location/rule_values/post_template', [__CLASS__, 'inject_templates'], 20);
        
        // 2. Manejar la lógica de coincidencia de reglas
        add_filter('acf/location/rule_match/page_template', [__CLASS__, 'match_template_rule'], 20, 3);
        add_filter('acf/location/rule_match/post_template', [__CLASS__, 'match_template_rule'], 20, 3);
        
        // 3. Forzar actualización de campos en el editor
        add_action('current_screen', [__CLASS__, 'refresh_acf_fields_on_edit']);
        
        // 4. Asegurar que ACF conozca la plantilla actual en front-end
        add_filter('acf/location/current_screen', [__CLASS__, 'set_current_template'], 20, 1);
        
        error_log('ACT: Integración con ACF inicializada correctamente');
    }

    public static function inject_templates($choices) {
        $templates = ACT_Template::get_templates();
        
        foreach ($templates as $id => $template) {
            // Mantener el formato act_nombre.php
            $choices[$id] = $template['name'] . ' (ACT)';
            
            // Debug para verificar cada plantilla añadida
            error_log("ACT: Plantilla añadida a ACF - ID: $id, Nombre: {$template['name']}");
        }
        
        return $choices;
    }

    public static function match_template_rule($match, $rule, $options) {
        $current_template = get_page_template_slug($options['post_id']);
        $rule_template = $rule['value'];
        
        // Debug detallado
        error_log("ACT: Evaluando regla - Plantilla actual: $current_template, Regla: $rule_template");
        
        // Solo intervenir si es una de nuestras plantillas
        if (strpos($rule_template, 'act_') === 0) {
            $match = ($current_template === $rule_template);
            error_log("ACT: Resultado coincidencia: " . ($match ? 'TRUE' : 'FALSE'));
        }
        
        return $match;
    }

    public static function refresh_acf_fields_on_edit() {
        $screen = get_current_screen();
        
        if ($screen && $screen->base === 'post') {
            add_action('admin_footer', function() {
                ?>
                <script>
                jQuery(function($) {
                    // 1. Actualizar al cambiar la plantilla
                    $('#page_template').on('change', function() {
                        if (typeof acf !== 'undefined') {
                            acf.doAction('refresh');
                            console.log('ACT: Plantilla cambiada, actualizando campos ACF');
                        }
                    });
                    
                    // 2. Actualizar al cargar la página
                    setTimeout(function() {
                        if (typeof acf !== 'undefined') {
                            acf.doAction('refresh');
                            console.log('ACT: Actualización inicial de campos ACF');
                        }
                    }, 800);
                });
                </script>
                <?php
            });
        }
    }

    public static function set_current_template($screen) {
        if (isset($screen['post_id'])) {
            $template = get_page_template_slug($screen['post_id']);
            if (!empty($template)) {
                $screen['post_template'] = $template;
                error_log("ACT: Plantilla actual establecida para ACF: $template");
            }
        }
        return $screen;
    }
}