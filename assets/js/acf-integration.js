jQuery(document).ready(function($) {
    // Observar cambios en el selector de plantillas
    $(document).on('change', '#page_template', function() {
        if (typeof acf !== 'undefined') {
            // Forzar actualización de campos ACF
            acf.doAction('refresh');
            console.log('ACT: Plantilla cambiada, actualizando campos ACF');
        }
    });

    // Actualización inicial al cargar la página
    if (typeof acf !== 'undefined' && $('#page_template').length) {
        setTimeout(function() {
            acf.doAction('refresh');
            console.log('ACT: Actualización inicial de campos ACF');
        }, 1000);
    }
});