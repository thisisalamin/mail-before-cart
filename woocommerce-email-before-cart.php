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
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add email input field before the Add to Cart button
function wc_email_before_add_to_cart() {
    echo '<p class="email-before-add-to-cart">
        <label for="customer_email">Enter Your Email to Add to Cart:</label>
        <input type="email" id="customer_email" name="customer_email" required>
    </p>';
}
add_action('woocommerce_before_add_to_cart_button', 'wc_email_before_add_to_cart');

// Enqueue JavaScript to prevent adding to cart without email
function wc_email_validation_script() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('form.cart').on('submit', function(event) {
                var email = $('#customer_email').val();
                if (email === '') {
                    alert('Please enter your email before adding this item to the cart.');
                    event.preventDefault();
                    return false;
                } else {
                    localStorage.setItem('customer_email', email);
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'wc_email_validation_script');

// Store the email in database and WooCommerce session
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
        'woocommerce', // Parent slug
        'Abandoned Cart Emails', // Page title
        'Abandoned Emails', // Menu title
        'manage_woocommerce', // Capability
        'wc-abandoned-emails', // Menu slug
        'wc_display_abandoned_emails' // Callback function
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
    
    echo '</tbody></table></div>';
}

// Schedule Cron Job to clear old session data
function wc_clear_abandoned_cart_emails() {
    if (function_exists('WC') && WC()->session) {
        WC()->session->__unset('customer_email');
    }
}
add_action('wp_scheduled_delete', 'wc_clear_abandoned_cart_emails');

// Schedule a cleanup event every day
if (!wp_next_scheduled('wp_scheduled_delete')) {
    wp_schedule_event(time(), 'daily', 'wp_scheduled_delete');
}

?>
