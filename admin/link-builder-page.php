<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('UTM Link Builder', 'custom-utm-tracker'); ?></h1>
    
    <div class="cutm-builder-container">
        <form id="utm-builder-form" class="cutm-form">
            <?php wp_nonce_field('cutm_nonce', 'cutm_nonce'); ?>
            
            <div class="cutm-form-group">
                <label for="base_url"><?php echo esc_html__('Select or Enter URL:', 'custom-utm-tracker'); ?></label>
                <select id="page_select" class="cutm-select">
                    <option value=""><?php echo esc_html__('-- Select a page --', 'custom-utm-tracker'); ?></option>
                    <option value="<?php echo esc_attr(home_url()); ?>" data-relative="/"><?php echo esc_html__('Site URL', 'custom-utm-tracker'); ?></option>
                    <?php
                    // Get published pages
                    $pages = get_pages(array(
                        'post_status' => 'publish',
                        'sort_column' => 'post_title',
                        'sort_order' => 'ASC'
                    ));
                    
                    foreach ($pages as $page) {
                        $permalink = get_permalink($page->ID);
                        $relative_url = str_replace(home_url(), '', $permalink);
                        printf(
                            '<option value="%s" data-relative="%s">%s</option>',
                            esc_attr($permalink),
                            esc_attr($relative_url),
                            esc_html($page->post_title)
                        );
                    }
                    ?>
                </select>
                <p class="description"><?php echo esc_html__('Or enter a custom URL below:', 'custom-utm-tracker'); ?></p>
                <input type="text" id="base_url" name="base_url" class="regular-text" placeholder="<?php echo esc_attr(home_url()); ?>">
            </div>

            <div class="cutm-params-container">
                <h3><?php echo esc_html__('UTM Parameters', 'custom-utm-tracker'); ?></h3>
                <?php
                // Get custom cookies from options
                $custom_cookies = get_option('cutm_custom_cookies', array());
                
                if (empty($custom_cookies)) {
                    echo '<p class="description">' . esc_html__('No custom parameters defined yet. Please add them in the Custom Cookies section.', 'custom-utm-tracker') . '</p>';
                } else {
                    foreach ($custom_cookies as $key => $cookie) : ?>
                        <div class="cutm-param-group">
                            <label><?php echo esc_html($key); ?>:</label>
                            <div class="cutm-param-inputs">
                                <input type="text" 
                                       class="param-key" 
                                       value="<?php echo esc_attr($key); ?>" 
                                       readonly 
                                       style="background: #f0f0f1;">
                                <input type="text" 
                                       name="param_value_<?php echo esc_attr($key); ?>" 
                                       class="param-value" 
                                       placeholder="<?php echo esc_attr__('Enter value', 'custom-utm-tracker'); ?>">
                            </div>
                            <?php if (!empty($cookie['description'])) : ?>
                                <p class="description"><?php echo esc_html($cookie['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach;
                }
                ?>
            </div>

            <div class="cutm-actions">
                <button type="button" id="generate_url" class="button button-primary">
                    <?php echo esc_html__('Generate URL', 'custom-utm-tracker'); ?>
                </button>
            </div>

            <div class="cutm-result">
                <h3><?php echo esc_html__('Generated URL:', 'custom-utm-tracker'); ?></h3>
                <div class="cutm-url-display">
                    <textarea id="final_url" rows="3" class="large-text code" readonly></textarea>
                    <div class="cutm-url-actions">
                        <button type="button" id="copy_url" class="button">
                            <?php echo esc_html__('Copy to Clipboard', 'custom-utm-tracker'); ?>
                        </button>
                        <button type="button" id="save_url" class="button button-primary">
                            <?php echo esc_html__('Save to History', 'custom-utm-tracker'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Link History Section -->
        <div class="cutm-history">
            <h3><?php echo esc_html__('Link History', 'custom-utm-tracker'); ?></h3>
            <?php
            $link_history = get_option('cutm_link_history', array());
            if (!empty($link_history)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html__('URL', 'custom-utm-tracker'); ?></th>
                            <th scope="col"><?php echo esc_html__('Created', 'custom-utm-tracker'); ?></th>
                            <th scope="col"><?php echo esc_html__('Actions', 'custom-utm-tracker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($link_history as $timestamp => $url) : ?>
                            <tr>
                                <td class="column-url">
                                    <div class="url-text"><?php echo esc_html($url); ?></div>
                                </td>
                                <td class="column-date">
                                    <?php echo esc_html(date('Y-m-d H:i:s', $timestamp)); ?>
                                </td>
                                <td class="column-actions">
                                    <button type="button" 
                                            class="button copy-history-url" 
                                            data-url="<?php echo esc_attr($url); ?>">
                                        <?php echo esc_html__('Copy', 'custom-utm-tracker'); ?>
                                    </button>
                                    <button type="button" 
                                            class="button delete-history-url" 
                                            data-timestamp="<?php echo esc_attr($timestamp); ?>">
                                        <?php echo esc_html__('Delete', 'custom-utm-tracker'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html__('No links in history.', 'custom-utm-tracker'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .cutm-builder-container {
            max-width: 800px;
            margin-top: 20px;
        }
        .cutm-form-group {
            margin-bottom: 20px;
        }
        .cutm-param-group {
            margin-bottom: 15px;
        }
        .cutm-param-inputs {
            display: flex;
            gap: 10px;
        }
        .cutm-param-inputs input {
            flex: 1;
        }
        .cutm-select {
            width: 100%;
            max-width: 400px;
        }
        .cutm-url-display {
            margin-top: 10px;
        }
        .cutm-url-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        .cutm-actions {
            margin: 20px 0;
        }
        .cutm-history {
            margin-top: 40px;
        }
        .column-url {
            width: 60%;
        }
        .column-date {
            width: 20%;
        }
        .column-actions {
            width: 20%;
            text-align: right;
        }
        .url-text {
            word-break: break-all;
        }
        .column-actions .button {
            margin-left: 5px;
        }
    </style>
</div>
