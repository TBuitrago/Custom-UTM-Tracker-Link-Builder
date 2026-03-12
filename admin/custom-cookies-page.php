<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get saved custom cookies
$custom_cookies = get_option('cutm_custom_cookies', array());
?>

<div class="wrap">
    <h1><?php echo esc_html__('Custom Cookies Manager', 'custom-utm-tracker'); ?></h1>
    
    <div class="cutm-cookies-container">
        <!-- Add New Cookie Form -->
        <div class="cutm-add-cookie-form">
            <h2><?php echo esc_html__('Add New Cookie', 'custom-utm-tracker'); ?></h2>
            <form id="add-cookie-form" method="post" action="">
                <?php wp_nonce_field('cutm_add_cookie_nonce', 'cutm_add_cookie_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cookie_key"><?php echo esc_html__('Cookie Key:', 'custom-utm-tracker'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="cookie_key" 
                                   name="cookie_key" 
                                   class="regular-text" 
                                   required 
                                   placeholder="<?php echo esc_attr__('e.g., custom_source', 'custom-utm-tracker'); ?>">
                            <p class="description">
                                <?php echo esc_html__('Enter a unique identifier for your cookie. Use only letters, numbers, and underscores.', 'custom-utm-tracker'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cookie_description"><?php echo esc_html__('Description:', 'custom-utm-tracker'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="cookie_description" 
                                   name="cookie_description" 
                                   class="regular-text" 
                                   placeholder="<?php echo esc_attr__('e.g., Tracks traffic source', 'custom-utm-tracker'); ?>">
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" name="add_cookie">
                        <?php echo esc_html__('Add Cookie', 'custom-utm-tracker'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- Existing Cookies List -->
        <div class="cutm-cookies-list">
            <h2><?php echo esc_html__('Existing Cookies', 'custom-utm-tracker'); ?></h2>
            <?php if (!empty($custom_cookies)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc_html__('Cookie Key', 'custom-utm-tracker'); ?></th>
                            <th scope="col"><?php echo esc_html__('Description', 'custom-utm-tracker'); ?></th>
                            <th scope="col"><?php echo esc_html__('WPForms Value', 'custom-utm-tracker'); ?></th>
                            <th scope="col"><?php echo esc_html__('Actions', 'custom-utm-tracker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($custom_cookies as $key => $cookie) : ?>
                            <tr>
                                <td><?php echo esc_html($key); ?></td>
                                <td><?php echo esc_html($cookie['description']); ?></td>
                                <td>
                                    <div class="wpforms-value-container">
                                        <code class="wpforms-value">{query_var key="<?php echo esc_attr($key); ?>"}</code>
                                        <button type="button" 
                                                class="button copy-value" 
                                                data-value="{query_var key=&quot;<?php echo esc_attr($key); ?>&quot;}">
                                            <?php echo esc_html__('Copy', 'custom-utm-tracker'); ?>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" 
                                            class="button delete-cookie" 
                                            data-cookie-key="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html__('Delete', 'custom-utm-tracker'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo esc_html__('No custom cookies defined yet.', 'custom-utm-tracker'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .cutm-cookies-container {
            max-width: 1200px;
            margin-top: 20px;
        }
        .cutm-add-cookie-form {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .cutm-cookies-list {
            margin-top: 30px;
        }
        .cookie-value-input {
            width: 200px;
            margin-right: 10px;
        }
        .wp-list-table td {
            vertical-align: middle;
        }
        .wpforms-value-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .wpforms-value {
            background: #f0f0f1;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 13px;
            user-select: all;
        }
        .copy-value {
            padding: 2px 8px !important;
            height: auto !important;
            min-height: 26px;
        }
    </style>
</div>
