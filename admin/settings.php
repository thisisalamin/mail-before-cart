<?php
if (!defined('ABSPATH')) exit;

function wc_email_cart_get_default_template() {
    return '
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2>Hello {customer_name},</h2>
        <p>We noticed you left something in your cart at {site_name}.</p>
        <p>Your cart contains: {cart_items}</p>
        <p>Click the link below to complete your purchase:</p>
        <p><a href="{cart_link}" style="background-color: #4CAF50; color: white; padding: 12px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">Complete Purchase</a></p>
        <p>Best regards,<br>{site_name}</p>
    </div>';
}

function wc_email_cart_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings
    if (isset($_POST['wc_email_cart_settings_nonce']) && wp_verify_nonce($_POST['wc_email_cart_settings_nonce'], 'wc_email_cart_settings')) {
        update_option('wc_email_cart_label', sanitize_text_field($_POST['wc_email_cart_label']));
        update_option('wc_email_cart_placeholder', sanitize_text_field($_POST['wc_email_cart_placeholder']));
        update_option('wc_email_cart_reminder_days', absint($_POST['wc_email_cart_reminder_days']));
        update_option('wc_email_cart_subject', sanitize_text_field($_POST['wc_email_cart_subject']));
        update_option('wc_email_cart_reminder_subject', sanitize_text_field($_POST['wc_email_cart_reminder_subject']));
        
        add_settings_error(
            'wc_email_cart_messages',
            'wc_email_cart_message',
            __('Settings Saved', 'wc-email-before-cart'),
            'updated'
        );
    }

    if (isset($_POST['wc_email_reminder_settings_nonce'])) {
        check_admin_referer('wc_email_reminder_settings', 'wc_email_reminder_settings_nonce');
        
        // Save settings
        update_option('wc_email_reminder_subject', sanitize_text_field($_POST['reminder_subject']));
        update_option('wc_email_reminder_template', wp_kses_post($_POST['reminder_template']));
        update_option('wc_email_reminder_delay', absint($_POST['reminder_delay']));
        
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }

    // Get current values
    $label = get_option('wc_email_cart_label', 'Enter Your Email to Add to Cart:');
    $placeholder = get_option('wc_email_cart_placeholder', 'your@email.com');
    $reminder_days = get_option('wc_email_cart_reminder_days', 1);
    $email_subject = get_option('wc_email_cart_subject', 'Product Added to Cart');
    $reminder_subject = get_option('wc_email_cart_reminder_subject', 'Complete Your Purchase');
    $subject = get_option('wc_email_reminder_subject', 'Complete Your Purchase!');
    $template = get_option('wc_email_reminder_template', wc_email_cart_get_default_template());
    $delay = get_option('wc_email_reminder_delay', 24);
    ?>
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <form method="post" action="" class="space-y-6 p-6">
            <?php wp_nonce_field('wc_email_cart_settings', 'wc_email_cart_settings_nonce'); ?>
            
            <!-- Form Fields -->
            <div class="space-y-4">
                <div>
                    <label for="wc_email_cart_label" class="block text-sm font-medium text-gray-700">
                        Email Field Label
                    </label>
                    <input type="text" 
                           name="wc_email_cart_label" 
                           id="wc_email_cart_label"
                           value="<?php echo esc_attr($label); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="wc_email_cart_placeholder" class="block text-sm font-medium text-gray-700">
                        Email Field Placeholder
                    </label>
                    <input type="text" 
                           name="wc_email_cart_placeholder" 
                           id="wc_email_cart_placeholder"
                           value="<?php echo esc_attr($placeholder); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="wc_email_cart_reminder_days" class="block text-sm font-medium text-gray-700">
                        Send Reminder After (days)
                    </label>
                    <input type="number" 
                           name="wc_email_cart_reminder_days" 
                           id="wc_email_cart_reminder_days"
                           value="<?php echo esc_attr($reminder_days); ?>"
                           min="1"
                           class="mt-1 block w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="wc_email_cart_subject" class="block text-sm font-medium text-gray-700">
                        Initial Email Subject
                    </label>
                    <input type="text" 
                           name="wc_email_cart_subject" 
                           id="wc_email_cart_subject"
                           value="<?php echo esc_attr($email_subject); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="wc_email_cart_reminder_subject" class="block text-sm font-medium text-gray-700">
                        Reminder Email Subject
                    </label>
                    <input type="text" 
                           name="wc_email_cart_reminder_subject" 
                           id="wc_email_cart_reminder_subject"
                           value="<?php echo esc_attr($reminder_subject); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-5">
                <button type="submit" 
                        class="inline-flex justify-center rounded-md border border-transparent bg-blue-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    <div class="wrap">
        <form method="post" action="">
            <?php wp_nonce_field('wc_email_reminder_settings', 'wc_email_reminder_settings_nonce'); ?>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">
                    Reminder Email Subject
                    <input type="text" 
                           name="reminder_subject" 
                           value="<?php echo esc_attr($subject); ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">
                    Send Reminder After (hours)
                    <input type="number" 
                           name="reminder_delay" 
                           value="<?php echo esc_attr($delay); ?>"
                           min="1" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </label>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700">
                    Email Template
                    <textarea name="reminder_template" 
                              rows="10" 
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><?php echo esc_textarea($template); ?></textarea>
                </label>
                <p class="mt-2 text-sm text-gray-500">
                    Available variables: {site_name}, {customer_name}, {cart_items}, {cart_link}, {email}
                </p>
            </div>

            <div>
                <button type="submit" 
                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
    <?php
}
