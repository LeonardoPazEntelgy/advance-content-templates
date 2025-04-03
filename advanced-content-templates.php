<?php
/**
 * Plugin Name: Advanced Content Templates
 * Description: Crea y gestiona plantillas avanzadas de contenido para entradas, páginas y CPTs. Integración con ACF.
 * Version: 1.0.0
 * Author: Leonardo
 * License: GPL-2.0+
 * Text Domain: advanced-content-templates
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

// Definir constantes
define('ACT_VERSION', '1.0.0');
define('ACT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ACT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Cargar archivos necesarios
require_once ACT_PLUGIN_DIR . 'includes/class-act-template.php';
require_once ACT_PLUGIN_DIR . 'includes/class-act-admin.php';
require_once ACT_PLUGIN_DIR . 'includes/class-act-import-export.php';
require_once ACT_PLUGIN_DIR . 'includes/class-act-acf-integration.php';

// Inicialización del plugin
add_action('plugins_loaded', function() {
    load_plugin_textdomain('advanced-content-templates', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    
    ACT_Template::init();
    ACT_Admin::init();
    ACT_Import_Export::init();
    
    // Inicializar integración con ACF si está activo
    if (class_exists('ACF')) {
        ACT_ACF_Integration::init();
    }
});

// Forzar la integración con ACF incluso si se carga tarde
add_action('acf/include_location_rules', function() {
    if (class_exists('ACT_ACF_Integration')) {
        ACT_ACF_Integration::init();
    }
});