<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Advanced Content Templates', 'advanced-content-templates'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=act-add-new'); ?>" class="page-title-action"><?php _e('Add New', 'advanced-content-templates'); ?></a>
    
    <hr class="wp-header-end">
    
    <!-- Barra de búsqueda y filtros -->
    <div class="act-search-filters">
        <div class="act-search-box">
            <input type="text" id="act-search-input" placeholder="<?php _e('Search templates...', 'advanced-content-templates'); ?>" class="regular-text">
        </div>
        
        <div class="act-filters">
            <select id="act-filter-post-type" class="postform">
                <option value="all"><?php _e('All Post Types', 'advanced-content-templates'); ?></option>
                <?php
                $post_types = get_post_types(['public' => true], 'objects');
                foreach ($post_types as $post_type => $post_type_obj) {
                    if (!in_array($post_type, ['attachment', 'revision', 'nav_menu_item'])) {
                        echo '<option value="' . esc_attr($post_type) . '">' . esc_html($post_type_obj->labels->singular_name) . '</option>';
                    }
                }
                ?>
            </select>
            
            <select id="act-filter-date" class="postform">
                <option value="all"><?php _e('Any Date', 'advanced-content-templates'); ?></option>
                <option value="today"><?php _e('Today', 'advanced-content-templates'); ?></option>
                <option value="week"><?php _e('Last 7 Days', 'advanced-content-templates'); ?></option>
                <option value="month"><?php _e('Last 30 Days', 'advanced-content-templates'); ?></option>
            </select>
        </div>
    </div>
    
    <div id="act-no-results" style="display:none;" class="notice notice-warning">
        <p><?php _e('No templates found matching your criteria.', 'advanced-content-templates'); ?></p>
    </div>
    
    <?php if (empty($templates)) : ?>
        <div class="notice notice-info">
            <p><?php _e('No templates found. Create your first template to get started!', 'advanced-content-templates'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="act-sortable" data-column="name" data-order="asc">
                        <?php _e('Name', 'advanced-content-templates'); ?>
                        <span class="sorting-indicator"></span>
                    </th>
                    <th class="act-sortable" data-column="desc" data-order="asc">
                        <?php _e('Description', 'advanced-content-templates'); ?>
                        <span class="sorting-indicator"></span>
                    </th>
                    <th class="act-sortable" data-column="post-types" data-order="asc">
                        <?php _e('Post Types', 'advanced-content-templates'); ?>
                        <span class="sorting-indicator"></span>
                    </th>
                    <th class="act-sortable" data-column="created" data-order="desc">
                        <?php _e('Created', 'advanced-content-templates'); ?>
                        <span class="sorting-indicator"></span>
                    </th>
                    <th><?php _e('Actions', 'advanced-content-templates'); ?></th>
                </tr>
            </thead>            
            <tbody>
                <?php foreach ($templates as $id => $template) : 
                    $post_type_names = [];
                    foreach ($template['post_types'] as $post_type) {
                        $post_type_obj = get_post_type_object($post_type);
                        if ($post_type_obj) {
                            $post_type_names[] = $post_type_obj->labels->singular_name;
                        }
                    }
                    ?>
                    <tr class="act-template-row" 
                        data-post-types="<?php echo esc_attr(implode(',', $template['post_types'])); ?>"
                        data-created-date="<?php echo esc_attr($template['created']); ?>">
                        <td class="template-name"><?php echo esc_html($template['name']); ?></td>
                        <td class="template-desc"><?php echo esc_html($template['description']); ?></td>
                        <td><?php echo esc_html(implode(', ', $post_type_names)); ?></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($template['created'])); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=act-add-new&template=' . urlencode($id)); ?>" class="button"><?php _e('Edit', 'advanced-content-templates'); ?></a>
                            <button class="button act-delete-template" data-template-id="<?php echo esc_attr($id); ?>"><?php _e('Delete', 'advanced-content-templates'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="act-import-form">
        <h2><?php _e('Import Template', 'advanced-content-templates'); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('act_import'); ?>
            <input type="file" name="template_file" accept=".json">
            <input type="submit" name="act_import" class="button button-primary" value="<?php esc_attr_e('Import', 'advanced-content-templates'); ?>">
        </form>
    </div>
</div>