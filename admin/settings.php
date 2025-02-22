<?php
if (!defined('ABSPATH')) exit;

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

    // Get current values
    $label = get_option('wc_email_cart_label', 'Enter Your Email to Add to Cart:');
    $placeholder = get_option('wc_email_cart_placeholder', 'your@email.com');
    $reminder_days = get_option('wc_email_cart_reminder_days', 1);
    $email_subject = get_option('wc_email_cart_subject', 'Product Added to Cart');
    $reminder_subject = get_option('wc_email_cart_reminder_subject', 'Complete Your Purchase');
    
    // Settings page HTML
    ?>
    <div class="wrap bg-gray-50 min-h-screen p-6">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Email Cart Settings</h1>
            
            <?php settings_errors('wc_email_cart_messages'); ?>

            <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
                <form method="post" action="" class="space-y-6 p-6">
                    <?php wp_nonce_field('wc_email_cart_settings', 'wc_email_cart_settings_nonce'); ?>
                    
                    <!-- Form Fields -->
                    <div class="space-y-4">
                        <!-- Email Field Label -->
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

                        <!-- Email Field Placeholder -->
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

                        <!-- Reminder Days -->
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

                        <!-- Email Subject -->
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

                        <!-- Reminder Email Subject -->
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
        </div>
    </div>
    <?php
}

// Add settings page to menu
function wc_email_cart_add_settings_page() {
    add_submenu_page(
        'woocommerce',
        'Email Cart Settings',
        'Email Cart Settings',
        'manage_options',
        'wc-email-cart-settings',
        'wc_email_cart_settings_page'
    );
}
add_action('admin_menu', 'wc_email_cart_add_settings_page', 99);
