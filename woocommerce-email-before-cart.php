<?php
/**
 * Plugin Name: WooCommerce Email Before Add to Cart
 * Description: Requires customers to enter their email before adding a product to the cart and stores it for abandoned cart recovery.
 * Version: 1.0
 * Author: Mohamed Alamin
 * Author URI: https://crafely.com
 * License: GPL2
 * Text Domain: wc-email-before-cart
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Create database table on plugin activation
register_activation_hook(__FILE__, 'wc_email_cart_create_table');
function wc_email_cart_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(100) NOT NULL,
        product_id bigint(20) NOT NULL,
        product_name varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        reminder_sent TINYINT(1) DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue Tailwind CSS for frontend
function wc_enqueue_tailwind_css() {
    wp_enqueue_style('tailwind-css', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
}
add_action('wp_enqueue_scripts', 'wc_enqueue_tailwind_css');

// Enqueue Tailwind CSS for admin
function wc_enqueue_admin_tailwind_css() {
    wp_enqueue_style('tailwind-css-admin', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
}
add_action('admin_enqueue_scripts', 'wc_enqueue_admin_tailwind_css');

// Add Settings Page
function wc_email_cart_settings_init() {
    add_settings_section(
        'wc_email_cart_settings',
        'Email Cart Settings',
        null,
        'wc_email_cart'
    );

    // Email Field Label
    add_settings_field(
        'wc_email_cart_label',
        'Email Field Label',
        'wc_email_cart_label_cb',
        'wc_email_cart',
        'wc_email_cart_settings'
    );

    // Email Field Placeholder
    add_settings_field(
        'wc_email_cart_placeholder',
        'Email Field Placeholder',
        'wc_email_cart_placeholder_cb',
        'wc_email_cart',
        'wc_email_cart_settings'
    );

    // Reminder Email Timing
    add_settings_field(
        'wc_email_cart_reminder_days',
        'Send Reminder After (days)',
        'wc_email_cart_reminder_days_cb',
        'wc_email_cart',
        'wc_email_cart_settings'
    );

    register_setting('wc_email_cart', 'wc_email_cart_label');
    register_setting('wc_email_cart', 'wc_email_cart_placeholder');
    register_setting('wc_email_cart', 'wc_email_cart_reminder_days');
}
add_action('admin_init', 'wc_email_cart_settings_init');

// Settings callbacks
function wc_email_cart_label_cb() {
    $label = get_option('wc_email_cart_label', 'Enter Your Email to Add to Cart:');
    echo "<input type='text' class='regular-text' name='wc_email_cart_label' value='" . esc_attr($label) . "'>";
}

function wc_email_cart_placeholder_cb() {
    $placeholder = get_option('wc_email_cart_placeholder', 'your@email.com');
    echo "<input type='text' class='regular-text' name='wc_email_cart_placeholder' value='" . esc_attr($placeholder) . "'>";
}

function wc_email_cart_reminder_days_cb() {
    $days = get_option('wc_email_cart_reminder_days', 1);
    echo "<input type='number' class='small-text' name='wc_email_cart_reminder_days' value='" . esc_attr($days) . "'>";
}

// Email validation to ensure it's not already in the database
function wc_email_before_add_to_cart() {
    $label = get_option('wc_email_cart_label', 'Enter Your Email to Add to Cart:');
    $placeholder = get_option('wc_email_cart_placeholder', 'your@email.com');
    ?>
    <p class="email-before-add-to-cart">
        <label for="customer_email" class="text-gray-700 font-medium"><?php echo esc_html($label); ?></label>
        <input type="email" 
               id="customer_email" 
               name="customer_email" 
               required 
               placeholder="<?php echo esc_attr($placeholder); ?>"
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </p>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('form.cart').on('submit', function(event) {
                var email = $('#customer_email').val();
                if (email === '') {
                    alert('Please enter your email before adding this item to the cart.');
                    event.preventDefault();
                    return false;
                } else {
                    $.ajax({
                        url: wc_email_before_cart.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wc_check_email_exists',
                            email: email
                        },
                        success: function(response) {
                            if (response.exists) {
                                alert('This email is already in use. Please use a different email.');
                                event.preventDefault();
                                return false;
                            } else {
                                localStorage.setItem('customer_email', email);
                            }
                        }
                    });
                }
            });
        });
    </script>
    <?php
}
add_action('woocommerce_before_add_to_cart_button', 'wc_email_before_add_to_cart');

// AJAX handler to check if email exists
function wc_check_email_exists() {
    global $wpdb;
    $email = sanitize_email($_POST['email']);
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
    wp_send_json(array('exists' => $exists > 0));
}
add_action('wp_ajax_wc_check_email_exists', 'wc_check_email_exists');
add_action('wp_ajax_nopriv_wc_check_email_exists', 'wc_check_email_exists');

// Enhanced email notification with HTML template
function wc_send_email_notification($email, $product_name) {
    $subject = 'Product Added to Cart';
    
    $message = '
    <html>
    <body style="font-family: Arial, sans-serif;">
        <h2>Thank you for your interest!</h2>
        <p>You have added <strong>' . esc_html($product_name) . '</strong> to your cart.</p>
        <p>Complete your purchase now to secure your item.</p>
        <p><a href="' . wc_get_cart_url() . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Cart</a></p>
    </body>
    </html>';

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($email, $subject, $message, $headers);
}

// Store the email in database and WooCommerce session, and send notification
function wc_store_email_in_session($cart_item_data, $product_id) {
    if (isset($_POST['customer_email']) && function_exists('WC')) {
        global $wpdb;
        $email = sanitize_email($_POST['customer_email']);
        $product = wc_get_product($product_id);
        
        // Store in database
        $wpdb->insert(
            $wpdb->prefix . 'wc_email_cart_tracking',
            array(
                'email' => $email,
                'product_id' => $product_id,
                'product_name' => $product->get_name()
            ),
            array('%s', '%d', '%s')
        );
        
        // Store in session
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }
        WC()->session->set('customer_email', $email);

        // Send email notification
        wc_send_email_notification($email, $product->get_name());
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'wc_store_email_in_session', 10, 2);

// Retrieve stored email for abandoned cart tracking
function wc_get_abandoned_cart_email() {
    if (!function_exists('WC') || !WC()->session) {
        return false;
    }
    return WC()->session->get('customer_email');
}

// Add Admin Menu for viewing abandoned emails
function wc_abandoned_cart_menu() {
    add_submenu_page(
        'woocommerce',
        'Email Cart',
        'WC Abandoned Emails',
        'manage_woocommerce',
        'wc-abandoned-emails',
        'wc_email_cart_dashboard'
    );
}
add_action('admin_menu', 'wc_abandoned_cart_menu', 99);

// Display stored emails in the admin panel
function wc_display_abandoned_emails() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!function_exists('WC')) {
        echo '<div class="error"><p>WooCommerce must be installed and activated to use this feature.</p></div>';
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    echo '<div class="wrap">
        <h1>Abandoned Cart Emails</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Product</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>';
    
    if ($results) {
        foreach ($results as $row) {
            echo '<tr>
                <td>' . esc_html($row->email) . '</td>
                <td>' . esc_html($row->product_name) . ' (ID: ' . esc_html($row->product_id) . ')</td>
                <td>' . esc_html($row->created_at) . '</td>
            </tr>';
        }
    } else {
        echo '<tr><td colspan="3">No abandoned emails recorded yet.</td></tr>';
    }
    
    echo '</tbody></table>';

    echo '<form method="post" action="' . admin_url('admin-post.php') . '">
        <input type="hidden" name="action" value="wc_export_emails_to_csv">
        <input type="submit" value="Export to CSV" class="button button-primary">
    </form>';

    echo '</div>';
}

// Export emails to CSV
function wc_export_emails_to_csv() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);

    if ($results) {
        $filename = 'abandoned_cart_emails_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, array('Email', 'Product ID', 'Product Name', 'Date'));

        foreach ($results as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
add_action('admin_post_wc_export_emails_to_csv', 'wc_export_emails_to_csv');

// Enhanced reminder email system with customizable timing
function wc_send_abandoned_cart_reminders() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    $reminder_days = get_option('wc_email_cart_reminder_days', 1);
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE created_at < NOW() - INTERVAL %d DAY AND reminder_sent = 0",
        $reminder_days
    ));

    foreach ($results as $row) {
        $subject = 'Complete Your Purchase';
        
        $message = '
        <html>
        <body style="font-family: Arial, sans-serif;">
            <h2>Don\'t forget about your cart!</h2>
            <p>We noticed you left <strong>' . esc_html($row->product_name) . '</strong> in your cart.</p>
            <p>Complete your purchase now before it\'s gone!</p>
            <p><a href="' . wc_get_cart_url() . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Complete Purchase</a></p>
        </body>
        </html>';

        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        if (wp_mail($row->email, $subject, $message, $headers)) {
            $wpdb->update(
                $table_name,
                array('reminder_sent' => 1),
                array('id' => $row->id)
            );
        }
    }
}

// Update database table to include reminder_sent column
function wc_email_cart_update_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    
    $column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reminder_sent'",
        DB_NAME,
        $table_name
    ));

    if (empty($column)) {
        $wpdb->query("ALTER TABLE $table_name ADD reminder_sent TINYINT(1) DEFAULT 0");
    }
}
register_activation_hook(__FILE__, 'wc_email_cart_update_db');

// Schedule Cron Job to clear old session data
function wc_clear_abandoned_cart_emails() {
    if (function_exists('WC') && WC()->session) {
        WC()->session->__unset('customer_email');
    }
}
add_action('wp_scheduled_delete', 'wc_clear_abandoned_cart_emails');

// Schedule a daily event to send reminders and clear old session data
if (!wp_next_scheduled('wp_scheduled_delete')) {
    wp_schedule_event(time(), 'daily', 'wp_scheduled_delete');
}

// Include admin files
require_once plugin_dir_path(__FILE__) . 'admin/components.php';
require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings.php';

// Enqueue admin scripts
function wc_email_cart_admin_scripts($hook) {
    if ('woocommerce_page_wc-abandoned-emails' !== $hook) {
        return;
    }
    wp_enqueue_script('wc-email-cart-admin', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0', true);
}
add_action('admin_enqueue_scripts', 'wc_email_cart_admin_scripts');

?>
