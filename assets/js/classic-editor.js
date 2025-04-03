jQuery(function($) {
    // Esperar a que el DOM esté completamente cargado
    $(window).on('load', function() {
        // Verificar si el metabox existe
        if ($('#page_template').length) {
            // Disparar evento para recargar plantillas
            setTimeout(function() {
                $('#page_template').trigger('change');
                console.log('ACT: Selector de plantillas forzado');
            }, 1000);
        }
    });
});