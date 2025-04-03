<?php
// Verificar si estamos editando una plantilla existente
$editing = isset($_GET['template']);
$template = $editing ? ACT_Template::get_template(sanitize_key($_GET['template'])) : false;

// Datos por defecto
$defaults = array(
    'name' => '',
    'description' => '',
    'content' => '',
    'post_types' => array('page')
);

$template_data = $template ? $template : $defaults;
$template_id = $editing ? sanitize_key($_GET['template']) : '';
?>

<div class="wrap">
    <h1>
        <?php echo $editing ? __('Edit Content Template', 'advanced-content-templates') : __('Add New Content Template', 'advanced-content-templates'); ?>
    </h1>
    
    <div id="act-message-container"></div>
    
    <form id="act-template-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
        <?php if ($editing) : ?>
            <input type="hidden" name="template_id" value="<?php echo esc_attr($template_id); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="action" value="act_save_template">
        <?php wp_nonce_field('act_admin_nonce', 'nonce'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="act-template-name"><?php _e('Template Name', 'advanced-content-templates'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="act-template-name" name="name" class="regular-text" value="<?php echo esc_attr($template_data['name']); ?>" required>
                        <p class="description"><?php _e('A unique name for this template', 'advanced-content-templates'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="act-template-description"><?php _e('Description', 'advanced-content-templates'); ?></label>
                    </th>
                    <td>
                        <textarea id="act-template-description" name="description" class="large-text" rows="3"><?php echo esc_textarea($template_data['description']); ?></textarea>
                        <p class="description"><?php _e('A brief description of this template (optional)', 'advanced-content-templates'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e('Post Types', 'advanced-content-templates'); ?></label>
                    </th>
                    <td>
                        <?php foreach ($post_types as $post_type => $post_type_obj) : ?>
                            <label>
                                <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type); ?>" <?php checked(in_array($post_type, $template_data['post_types'])); ?>>
                                <?php echo esc_html($post_type_obj->labels->singular_name); ?>
                            </label><br>
                        <?php endforeach; ?>
                        <p class="description"><?php _e('Select which post types this template will be available for', 'advanced-content-templates'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="act-template-content"><?php _e('Template Content', 'advanced-content-templates'); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor(
                            $template_data['content'],
                            'act-template-content',
                            array(
                                'textarea_name' => 'content',
                                'textarea_rows' => 15,
                                'media_buttons' => true,
                                'teeny' => false
                            )
                        );
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary" id="act-save-template"><?php _e('Save Template', 'advanced-content-templates'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=advanced-content-templates'); ?>" class="button"><?php _e('Cancel', 'advanced-content-templates'); ?></a>
            <span id="act-loading" style="display:none;"><?php _e('Saving...', 'advanced-content-templates'); ?></span>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#act-template-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $('#act-save-template');
        var $loading = $('#act-loading');
        var $messageContainer = $('#act-message-container');
        
        // Mostrar indicador de carga
        $submitButton.prop('disabled', true);
        $loading.show();
        
        // Obtener contenido del editor
        var content = '';
        if (typeof tinymce !== 'undefined' && tinymce.get('act-template-content')) {
            content = tinymce.get('act-template-content').getContent();
        } else {
            content = $('#act-template-content').val();
        }
        
        // Preparar datos
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
        
        // Añadir template_id si existe
        var templateId = $form.find('input[name="template_id"]').val();
        if (templateId) {
            formData.template_id = templateId;
        }
        
        // Enviar via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            dataType: 'json'
        }).done(function(response) {
            if (response.success) {
                $messageContainer.html(
                    '<div class="notice notice-success is-dismissible"><p>' + 
                    response.data.message + 
                    '</p></div>'
                );
                
                // Si es nueva plantilla, redirigir
                if (!templateId && response.data.id) {
                    window.location.href = 'admin.php?page=act-add-new&template=' + response.data.id;
                }
            } else {
                $messageContainer.html(
                    '<div class="notice notice-error is-dismissible"><p>' + 
                    response.data + 
                    '</p></div>'
                );
            }
        }).fail(function(xhr, status, error) {
            $messageContainer.html(
                '<div class="notice notice-error is-dismissible"><p>' + 
                'Error: ' + error + 
                '</p></div>'
            );
        }).always(function() {
            $submitButton.prop('disabled', false);
            $loading.hide();
            
            // Desplazarse al mensaje
            $('html, body').animate({
                scrollTop: $messageContainer.offset().top - 100
            }, 500);
        });
    });
});
</script>