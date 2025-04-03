jQuery(document).ready(function($) {
    // Debug: Verificar que el script se carga correctamente
    console.log('ACT: Script admin.js cargado - Versión ' + act_admin.version);

    // Cargar preferencias guardadas
    var savedPostTypeFilter = localStorage.getItem('act_filter_post_type');
    var savedDateFilter = localStorage.getItem('act_filter_date');
    
    if (savedPostTypeFilter) {
        $('#act-filter-post-type').val(savedPostTypeFilter);
    }
    if (savedDateFilter) {
        $('#act-filter-date').val(savedDateFilter);
    }
    
    // Aplicar filtros al cargar si hay preferencias
    if (savedPostTypeFilter || savedDateFilter) {
        setTimeout(filterTemplates, 100);
    }

    // 1. Manejar el envío del formulario de plantilla
    $('#act-template-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        var originalText = $submitButton.text();
        
        // Mostrar indicador de carga
        $submitButton.prop('disabled', true).text(act_admin.i18n.saving);
        
        // Obtener el contenido del editor
        var content = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('act-template-content')) {
            content = tinymce.get('act-template-content').getContent();
        } else {
            content = $('#act-template-content').val();
        }
        
        // Preparar datos del formulario
        var formData = {
            action: 'act_save_template',
            nonce: $form.find('#nonce').val(),
            name: $form.find('#act-template-name').val(),
            description: $form.find('#act-template-description').val(),
            content: content,
            post_types: $form.find('input[name="post_types[]"]:checked').map(function() {
                return this.value;
            }).get()
        };
        
        // Si estamos editando, añadir el ID de la plantilla
        var templateId = $form.find('input[name="template_id"]').val();
        if (templateId) {
            formData.template_id = templateId;
        }
        
        // Enviar datos via AJAX
        $.ajax({
            url: act_admin.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Mostrar mensaje de éxito
                    $form.prepend(
                        '<div id="act-message" class="notice notice-success is-dismissible">' +
                        '<p>' + response.data.message + '</p>' +
                        '</div>'
                    );
                    
                    // Si es nueva plantilla, redirigir
                    if (!templateId && response.data.id) {
                        window.location.href = 'admin.php?page=act-add-new&template=' + response.data.id + '&saved=1';
                    } else {
                        // Forzar recarga de ACF si estamos editando
                        if (typeof acf !== 'undefined') {
                            acf.doAction('refresh');
                        }
                    }
                } else {
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                showError(act_admin.i18n.ajax_error.replace('%s', error));
            },
            complete: function() {
                $submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // 2. Manejar eliminación de plantillas
    $(document).on('click', '.act-delete-template', function(e) {
        e.preventDefault();
        console.log('ACT: Intento de eliminar plantilla');
        
        if (!confirm(act_admin.i18n.confirm_delete)) {
            return;
        }

        var $button = $(this);
        var templateId = $button.data('template-id');
        
        $button.prop('disabled', true).text(act_admin.i18n.deleting);
        
        $.ajax({
            url: act_admin.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'act_delete_template',
                template_id: templateId,
                nonce: act_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        showNotice(response.data.message, 'success');
                    });
                } else {
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                showError(act_admin.i18n.delete_error.replace('%s', error));
            },
            complete: function() {
                $button.prop('disabled', false).text(act_admin.i18n.delete);
            }
        });
    });
    
    // 3. Integración con ACF - Cambios de plantilla
    $(document).on('change', '#page_template', function() {
        console.log('ACT: Cambio de plantilla detectado');
        
        if (typeof acf !== 'undefined') {
            // Forzar actualización completa de ACF
            acf.doAction('unload', $('#post'));
            acf.doAction('load', $('#post'));
            acf.doAction('append', $('#post'));
            
            console.log('ACT: Campos ACF actualizados');
        }
    });
    
    // 4. Actualización inicial al cargar la página
    if ($('#page_template').length && typeof acf !== 'undefined') {
        setTimeout(function() {
            console.log('ACT: Actualización inicial de campos ACF');
            acf.doAction('load', $('#post'));
        }, 800);
    }
    
    // Funciones auxiliares
    function showError(message) {
        $('.wrap').prepend(
            '<div class="notice notice-error is-dismissible">' +
            '<p>' + message + '</p>' +
            '</div>'
        );
    }
    
    function showNotice(message, type) {
        $('.wrap').prepend(
            '<div class="notice notice-' + type + ' is-dismissible">' +
            '<p>' + message + '</p>' +
            '</div>'
        );
    }

    // 5. Sistema de búsqueda y filtrado
    $('#act-search-input').on('keyup', function() {
        filterTemplates();
    });

    $('#act-filter-post-type, #act-filter-date').on('change', function() {
        filterTemplates();
    });

    function filterTemplates() {
        var searchTerm = $('#act-search-input').val().toLowerCase();
        var postTypeFilter = $('#act-filter-post-type').val();
        var dateFilter = $('#act-filter-date').val();
        
        $('.act-template-row').each(function() {
            var $row = $(this);
            var name = $row.find('.template-name').text().toLowerCase();
            var postTypes = $row.data('post-types').toString().toLowerCase();
            var createdDate = $row.data('created-date');
            var showRow = true;
            
            // Filtro por nombre O descripción (cambio en la condición)
            if (searchTerm && name.indexOf(searchTerm) === -1 && description.indexOf(searchTerm) === -1) {
                showRow = false;
            }
            
            // Filtro por tipo de post
            if (postTypeFilter && postTypeFilter !== 'all' && postTypes.indexOf(postTypeFilter) === -1) {
                showRow = false;
            }
            
            // Filtro por fecha
            if (dateFilter && dateFilter !== 'all') {
                var templateDate = new Date(createdDate);
                var now = new Date();
                var diffTime = now - templateDate;
                var diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                if (dateFilter === 'today' && diffDays !== 0) {
                    showRow = false;
                } else if (dateFilter === 'week' && diffDays > 7) {
                    showRow = false;
                } else if (dateFilter === 'month' && diffDays > 30) {
                    showRow = false;
                }
            }
            
            $row.toggle(showRow);
        });
        
        // Mostrar mensaje si no hay resultados
        var visibleRows = $('.act-template-row:visible').length;
        if (visibleRows === 0) {
            $('#act-no-results').show();
        } else {
            $('#act-no-results').hide();
        }
    }

    // Guardar preferencias al cambiar filtros
    $('#act-filter-post-type, #act-filter-date').on('change', function() {
        localStorage.setItem('act_filter_post_type', $('#act-filter-post-type').val());
        localStorage.setItem('act_filter_date', $('#act-filter-date').val());
        filterTemplates();
    });

    // 6. Ordenación por columnas
    $('.act-sortable').on('click', function() {
        var $header = $(this);
        var column = $header.data('column');
        var order = $header.data('order');
        var $table = $header.closest('table');
        var $rows = $table.find('tbody tr').get();
        
        // Cambiar indicador visual
        $('.act-sortable').removeClass('asc desc').find('.sorting-indicator').removeClass('dashicons-arrow-up dashicons-arrow-down');
        $header.data('order', order === 'asc' ? 'desc' : 'asc')
            .addClass(order === 'asc' ? 'desc' : 'asc')
            .find('.sorting-indicator')
            .addClass('dashicons dashicons-arrow-' + (order === 'asc' ? 'down' : 'up'));
        
        // Ordenar las filas
        $rows.sort(function(a, b) {
            var aVal = $(a).find('td[data-column="' + column + '"]').text().toLowerCase();
            var bVal = $(b).find('td[data-column="' + column + '"]').text().toLowerCase();
            
            if (column === 'created') {
                aVal = new Date($(a).data('created-date'));
                bVal = new Date($(b).data('created-date'));
            }
            
            if (order === 'asc') {
                return aVal > bVal ? 1 : -1;
            } else {
                return aVal < bVal ? 1 : -1;
            }
        });
        
        // Reinsertar filas ordenadas
        $.each($rows, function(index, row) {
            $table.find('tbody').append(row);
        });
    });    
    
});