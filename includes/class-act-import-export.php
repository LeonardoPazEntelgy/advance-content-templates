<?php
class ACT_Import_Export {
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'handle_export'));
        add_action('admin_init', array(__CLASS__, 'handle_import'));
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
    }

    // Manejar exportación
    public static function handle_export() {
        if (!isset($_GET['act_export']) || !current_user_can('manage_options')) {
            return;
        }
        
        check_admin_referer('act_export');
        
        $template_id = sanitize_key($_GET['act_export']);
        $templates = ACT_Template::get_templates();
        
        if (!isset($templates[$template_id])) {
            wp_die(__('Template not found.', 'advanced-content-templates'));
        }
        
        $template = $templates[$template_id];
        $template['id'] = $template_id;
        
        $filename = sanitize_file_name($template['name']) . '.json';
        $json = wp_json_encode($template);
        
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Content-Type: application/json');
        header('Content-Length: ' . strlen($json));
        
        echo $json;
        exit;
    }

    // Manejar importación
    public static function handle_import() {
        if (!isset($_POST['act_import']) || !current_user_can('manage_options')) {
            return;
        }
        
        check_admin_referer('act_import');
        
        if (empty($_FILES['template_file']['tmp_name'])) {
            wp_redirect(add_query_arg('act_notice', 'import_no_file', wp_get_referer()));
            exit;
        }
        
        $file_content = file_get_contents($_FILES['template_file']['tmp_name']);
        $template = json_decode($file_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($template['name'], $template['content'], $template['post_types'])) {
            wp_redirect(add_query_arg('act_notice', 'import_invalid', wp_get_referer()));
            exit;
        }
        
        // Generar nuevo ID para evitar conflictos
        unset($template['id']);
        $template_id = ACT_Template::save_template($template);
        
        wp_redirect(add_query_arg(array(
            'act_notice' => 'import_success',
            'template_id' => $template_id
        ), wp_get_referer()));
        exit;
    }

    // Mostrar notificaciones
    public static function admin_notices() {
        if (!isset($_GET['act_notice'])) {
            return;
        }
        
        $notice = sanitize_key($_GET['act_notice']);
        
        switch ($notice) {
            case 'import_success':
                $message = __('Template imported successfully!', 'advanced-content-templates');
                $class = 'notice notice-success';
                break;
                
            case 'import_no_file':
                $message = __('Please select a file to import.', 'advanced-content-templates');
                $class = 'notice notice-error';
                break;
                
            case 'import_invalid':
                $message = __('The uploaded file is not a valid template.', 'advanced-content-templates');
                $class = 'notice notice-error';
                break;
                
            default:
                return;
        }
        
        printf('<div class="%s"><p>%s</p></div>', esc_attr($class), esc_html($message));
    }
}