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
function wc_email_cart_create_table()
{
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
        status varchar(50) DEFAULT 'pending',
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Enqueue Tailwind CSS for frontend
function wc_enqueue_tailwind_css()
{
    wp_enqueue_style('tailwind-css', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
}
add_action('wp_enqueue_scripts', 'wc_enqueue_tailwind_css');

// Enqueue Tailwind CSS for admin
function wc_enqueue_admin_tailwind_css($hook)
{
    if ('woocommerce_page_wc-abandoned-emails' !== $hook) {
        return;
    }
    wp_enqueue_style('tailwind-css-admin', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
}
add_action('admin_enqueue_scripts', 'wc_enqueue_admin_tailwind_css');

// Add Settings Page
function wc_email_cart_settings_init()
{
    add_settings_section(
        'wc_email_cart_settings',
        'Email Cart Settings',
        null,
        'wc_email_cart'
    );

    // Only keep email field settings
    add_settings_field(
        'wc_email_cart_label',
        'Email Field Label',
        'wc_email_cart_label_cb',
        'wc_email_cart',
        'wc_email_cart_settings'
    );

    add_settings_field(
        'wc_email_cart_placeholder',
        'Email Field Placeholder',
        'wc_email_cart_placeholder_cb',
        'wc_email_cart',
        'wc_email_cart_settings'
    );

    register_setting('wc_email_cart', 'wc_email_cart_label');
    register_setting('wc_email_cart', 'wc_email_cart_placeholder');
}
add_action('admin_init', 'wc_email_cart_settings_init');

// Settings callbacks
function wc_email_cart_label_cb()
{
    $label = get_option('wc_email_cart_label', 'Enter Your Email to Add to Cart:');
    echo "<input type='text' class='regular-text' name='wc_email_cart_label' value='" . esc_attr($label) . "'>";
}

function wc_email_cart_placeholder_cb()
{
    $placeholder = get_option('wc_email_cart_placeholder', 'your@email.com');
    echo "<input type='text' class='regular-text' name='wc_email_cart_placeholder' value='" . esc_attr($placeholder) . "'>";
}

function wc_email_cart_reminder_days_cb()
{
    $days = get_option('wc_email_cart_reminder_days', 1);
    echo "<input type='number' class='small-text' name='wc_email_cart_reminder_days' value='" . esc_attr($days) . "'>";
}

function wc_email_cart_reminder_value_cb()
{
    $value = get_option('wc_email_cart_reminder_value', 1);
    echo "<input type='number' min='1' class='small-text' name='wc_email_cart_reminder_value' value='" . esc_attr($value) . "'>";
}

function wc_email_cart_reminder_unit_cb()
{
    $unit = get_option('wc_email_cart_reminder_unit', 'minutes');
?>
    <select name="wc_email_cart_reminder_unit">
        <option value="minutes" <?php selected($unit, 'minutes'); ?>>Minutes</option>
        <option value="hours" <?php selected($unit, 'hours'); ?>>Hours</option>
        <option value="days" <?php selected($unit, 'days'); ?>>Days</option>
    </select>
<?php
}

// Email validation to ensure it's not already in the database
// Modified email input field display function
function wc_email_before_add_to_cart()
{
    global $product;

    // Don't show email field if:
    // 1. Product is out of stock
    // 2. Product is not purchasable
    // 3. Product type doesn't support add to cart
    if (
        !$product ||
        !$product->is_purchasable() ||
        !$product->is_in_stock() ||
        $product->is_type('external') ||
        ($product->is_type('variable') && !$product->has_child())
    ) {
        return;
    }

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
function wc_check_email_exists()
{
    global $wpdb;
    $email = sanitize_email($_POST['email']);
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE email = %s", $email));
    wp_send_json(array('exists' => $exists > 0));
}
add_action('wp_ajax_wc_check_email_exists', 'wc_check_email_exists');
add_action('wp_ajax_nopriv_wc_check_email_exists', 'wc_check_email_exists');

// Store the email in database and WooCommerce session
function wc_store_email_in_session($cart_item_data, $product_id)
{
    if (isset($_POST['customer_email']) && function_exists('WC')) {
        global $wpdb;
        $email = sanitize_email($_POST['customer_email']);
        $product = wc_get_product($product_id);

        // Save to custom table
        $wpdb->insert(
            $wpdb->prefix . 'wc_email_cart_tracking',
            array(
                'email' => $email,
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%s')
        );

        // Set WooCommerce session and CartFlows cookie
        WC()->session->set('wcf-customer-email', $email);
        WC()->session->set('ab_cart_user_email', $email);
        WC()->session->set('cart_user_id', 'guest_' . md5($email));
        WC()->session->set('customer_email', $email); // Ensure compatibility with other plugins

        setcookie('wcf_ac_email_set', $email, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        $_COOKIE['wcf_ac_email_set'] = $email;

        // Debugging: log when email is set
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Abandoned cart email set: ' . $email);
        }

        // Trigger CartFlows hook
        do_action('wcf_ac_save_abandoned_cart', $email);
    }

    return $cart_item_data;
}


add_filter('woocommerce_add_cart_item_data', 'wc_store_email_in_session', 10, 2);

// Retrieve stored email for abandoned cart tracking
function wc_get_abandoned_cart_email()
{
    if (!function_exists('WC') || !WC()->session) {
        return false;
    }
    return WC()->session->get('customer_email');
}

// Add Admin Menu for viewing abandoned emails
function wc_abandoned_cart_menu()
{
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

// Modify the wc_display_abandoned_emails function
function wc_display_abandoned_emails()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle clear data action
    if (isset($_POST['action']) && $_POST['action'] === 'wc_clear_all_data' && check_admin_referer('wc_clear_all_data_nonce')) {
        wc_clear_all_email_data();
        echo '<div class="notice notice-success"><p>All email tracking data has been cleared successfully!</p></div>';
    }

    if (!function_exists('WC')) {
        echo '<div class="error"><p>WooCommerce must be installed and activated to use this feature.</p></div>';
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    echo '<div class="wrap">';
    echo '<h1>Abandoned Cart Emails</h1>';

    // Add Clear Data button
    echo '<div style="margin: 20px 0;">';
    echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to clear all email tracking data? This action cannot be undone.\');">';
    wp_nonce_field('wc_clear_all_data_nonce');
    echo '<input type="hidden" name="action" value="wc_clear_all_data">';
    echo '<input type="submit" value="Clear All Data" class="button button-secondary" style="background-color: #dc3545; color: white; border-color: #dc3545;">';
    echo '</form>';

    // Add Export to CSV button (existing)
    echo '<form method="post" action="' . admin_url('admin-post.php') . '" style="display:inline; margin-left: 10px;">';
    echo '<input type="hidden" name="action" value="wc_export_emails_to_csv">';
    echo '<input type="submit" value="Export to CSV" class="button button-primary">';
    echo '</form>';
    echo '</div>';

    // Rest of the table display code
    echo '<table class="wp-list-table widefat fixed striped">
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

// Add new function to clear all data
function wc_clear_all_email_data()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';

    // Truncate the table
    $wpdb->query("TRUNCATE TABLE $table_name");

    // Clear any related sessions
    if (function_exists('WC') && WC()->session) {
        WC()->session->__unset('customer_email');
    }

    // Clear any scheduled reminders
    wp_clear_scheduled_hook('wc_email_cart_send_reminders');

    // Reschedule reminders
    wc_email_cart_activate();
}

// Export emails to CSV
function wc_export_emails_to_csv()
{
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

// Update database table to include reminder_sent column
function wc_email_cart_update_db()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';

    // Check for reminder_sent column
    $reminder_column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'reminder_sent'",
        DB_NAME,
        $table_name
    ));

    if (empty($reminder_column)) {
        $wpdb->query("ALTER TABLE $table_name ADD reminder_sent TINYINT(1) DEFAULT 0");
    }

    // Check for status column
    $status_column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
        DB_NAME,
        $table_name
    ));

    if (empty($status_column)) {
        $wpdb->query("ALTER TABLE $table_name ADD status varchar(50) DEFAULT 'pending'");
        $wpdb->query("UPDATE $table_name SET status = 'pending' WHERE status IS NULL");
    }

    // Check for last_reminder_sent column
    $last_reminder_column = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'last_reminder_sent'",
        DB_NAME,
        $table_name
    ));

    if (empty($last_reminder_column)) {
        $wpdb->query("ALTER TABLE $table_name ADD last_reminder_sent datetime DEFAULT NULL");
    }
}
register_activation_hook(__FILE__, 'wc_email_cart_update_db');

// Schedule Cron Job to clear old session data
function wc_clear_abandoned_cart_emails()
{
    if (function_exists('WC') && WC()->session) {
        WC()->session->__unset('customer_email');
    }
}
add_action('wp_scheduled_delete', 'wc_clear_abandoned_cart_emails');

// Schedule a daily event to send reminders and clear old session data
if (!wp_next_scheduled('wp_scheduled_delete')) {
    wp_schedule_event(time(), 'daily', 'wp_scheduled_delete');
}

// Include core files
require_once plugin_dir_path(__FILE__) . 'includes/class-email-reminder.php';
require_once plugin_dir_path(__FILE__) . 'admin/components.php';
require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings.php';

// Enqueue admin scripts
function wc_email_cart_admin_scripts($hook)
{
    if ('woocommerce_page_wc-abandoned-emails' !== $hook) {
        return;
    }
    wp_enqueue_script('wc-email-cart-admin', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0', true);
}
add_action('admin_enqueue_scripts', 'wc_email_cart_admin_scripts');

// Add these new functions for order status tracking
function wc_track_order_status($order_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';

    $order = wc_get_order($order_id);
    $email = $order->get_billing_email();

    // Update all entries for this email
    $wpdb->update(
        $table_name,
        array('status' => 'purchased'),
        array('email' => $email)
    );
}
add_action('woocommerce_order_status_completed', 'wc_track_order_status');
add_action('woocommerce_order_status_processing', 'wc_track_order_status');

// Unregister cron job on plugin deactivation
register_deactivation_hook(__FILE__, 'wc_email_cart_deactivate');
function wc_email_cart_deactivate()
{
    wp_clear_scheduled_hook('wc_email_cart_send_reminders');
}

// Hook the reminder sending function to the cron event
add_action('wc_email_cart_send_reminders', 'wc_process_abandoned_cart_reminders');

// Add this new function to help with debugging
function wc_email_cart_debug_cron()
{
    if (!current_user_can('manage_options')) return;

    $next_scheduled = wp_next_scheduled('wc_email_cart_send_reminders');
    error_log('Next scheduled reminder check: ' . ($next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled'));

    // Force process reminders if debug parameter is present
    if (isset($_GET['process_reminders'])) {
        wc_process_abandoned_cart_reminders();
        error_log('Manually triggered reminder processing');
    }
}
add_action('init', 'wc_email_cart_debug_cron');

// Add this new function to force reschedule
function wc_force_reschedule_reminders()
{
    if (!current_user_can('manage_options')) return;

    if (isset($_GET['reschedule_reminders'])) {
        wp_clear_scheduled_hook('wc_email_cart_send_reminders');

        $timestamp = time();
        wp_schedule_event($timestamp, 'reminder_interval', 'wc_email_cart_send_reminders');

        wp_redirect(admin_url('admin.php?page=wc-abandoned-emails&rescheduled=1'));
        exit;
    }
}
add_action('admin_init', 'wc_force_reschedule_reminders');

// Add admin notice for reschedule success
function wc_reminder_admin_notices()
{
    if (isset($_GET['rescheduled'])) {
    ?>
        <div class="notice notice-success is-dismissible">
            <p>Reminder schedule has been reset. Next reminder will run in 1 minute.</p>
        </div>
<?php
    }
}
add_action('admin_notices', 'wc_reminder_admin_notices');

// Add a function to manually reset the schedule
function wc_reset_reminder_schedule()
{
    // Clear existing schedule
    wp_clear_scheduled_hook('wc_email_cart_send_reminders');

    // Set next run time to next minute
    $next_minute = strtotime(date('Y-m-d H:i:00', time() + 60));

    // Schedule new event
    wp_schedule_event($next_minute, 'five_minutes', 'wc_email_cart_send_reminders');

    return true;
}

// Add this near other add_action calls
add_action('wp_ajax_wc_send_manual_reminder', 'wc_send_manual_reminder');

function wc_send_manual_reminder()
{
    check_ajax_referer('wc_email_cart_nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Permission denied');
    }

    $id = intval($_POST['id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';

    $cart = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $id
    ));

    if (!$cart) {
        wp_send_json_error('Cart not found');
    }

    $sent = wc_email_reminder()->send_reminder_email($cart);

    if ($sent) {
        // Update reminder_sent even if it was already sent (for tracking multiple sends)
        $wpdb->update(
            $table_name,
            array(
                'reminder_sent' => 1,
                'last_reminder_sent' => current_time('mysql')
            ),
            array('id' => $id)
        );
        wp_send_json_success(array(
            'message' => 'Email sent successfully',
            'buttonText' => 'Send Again'
        ));
    } else {
        wp_send_json_error('Failed to send email');
    }
}

?>
