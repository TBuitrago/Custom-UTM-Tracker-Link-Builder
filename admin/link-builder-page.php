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
                <input type="text" id="base_url" name="base_url" class="regular-text" placeholder="/your-page">
            </div>

            <div class="cutm-params-container">
                <h3><?php echo esc_html__('UTM Parameters', 'custom-utm-tracker'); ?></h3>
                <?php for ($i = 1; $i <= 5; $i++) : ?>
                    <div class="cutm-param-group">
                        <label><?php echo sprintf(esc_html__('Parameter %d:', 'custom-utm-tracker'), $i); ?></label>
                        <div class="cutm-param-inputs">
                            <input type="text" 
                                   name="param_key_<?php echo $i; ?>" 
                                   class="param-key" 
                                   placeholder="<?php echo esc_attr__('Key (e.g., utm_source)', 'custom-utm-tracker'); ?>">
                            <input type="text" 
                                   name="param_value_<?php echo $i; ?>" 
                                   class="param-value" 
                                   placeholder="<?php echo esc_attr__('Value (e.g., google)', 'custom-utm-tracker'); ?>">
                        </div>
                    </div>
                <?php endfor; ?>
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
                    <button type="button" id="copy_url" class="button">
                        <?php echo esc_html__('Copy to Clipboard', 'custom-utm-tracker'); ?>
                    </button>
                </div>
            </div>
        </form>
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
        .cutm-actions {
            margin: 20px 0;
        }
        #copy_url {
            margin-top: 10px;
        }
    </style>
</div>
