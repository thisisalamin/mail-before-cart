<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('WC_Email_Cart_Reminder')):

class WC_Email_Cart_Reminder {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wc_email_cart_send_reminders', array($this, 'process_abandoned_cart_reminders'));
        add_filter('cron_schedules', array($this, 'add_cron_schedule'));
    }

    public function init() {
        $this->setup_cron_job();
    }

    public function add_cron_schedule($schedules) {
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display' => 'Every 5 Minutes'
        );
        return $schedules;
    }

    public function setup_cron_job() {
        if (!wp_next_scheduled('wc_email_cart_send_reminders')) {
            wp_schedule_event(time(), 'five_minutes', 'wc_email_cart_send_reminders');
        }
    }

    public function process_abandoned_cart_reminders() {
        error_log('=== Starting Reminder Check ===');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
        
        // Fixed 24-hour reminder
        $query = "
            SELECT * FROM {$table_name} 
            WHERE status = 'pending' 
            AND reminder_sent = 0 
            AND created_at <= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ";
        
        $pending_reminders = $wpdb->get_results($query);
        
        foreach ($pending_reminders as $cart) {
            $this->send_reminder_email($cart);
            $this->update_reminder_status($cart->id);
        }
        
        error_log('=== Finished Reminder Check ===');
    }

    public function send_reminder_email($cart) {
        $subject = get_option('wc_email_cart_reminder_subject', 'Complete Your Purchase');
        $template = get_option('wc_email_reminder_template', $this->get_default_template());
        
        // Replace template variables
        $message = $this->parse_template_variables($template, $cart);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
        );
        
        $sent = wp_mail($cart->email, $subject, $message, $headers);
        error_log("Reminder email sent to {$cart->email}: " . ($sent ? 'Success' : 'Failed'));
        
        return $sent;
    }

    private function parse_template_variables($template, $cart) {
        $replacements = array(
            '{site_name}' => get_option('blogname'),
            '{customer_name}' => $this->get_customer_name($cart->email),
            '{cart_items}' => esc_html($cart->product_name),
            '{cart_link}' => wc_get_cart_url(),
            '{email}' => $cart->email
        );
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    private function get_customer_name($email) {
        $user = get_user_by('email', $email);
        if ($user) {
            return $user->display_name;
        }
        return 'Valued Customer';
    }

    private function get_default_template() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif;">
            <h2>Don\'t forget about your cart!</h2>
            <p>We noticed you left some items in your cart.</p>
            <p>Complete your purchase now before it\'s gone!</p>
            <p><a href="{cart_link}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Complete Purchase</a></p>
        </body>
        </html>';
    }

    public function send_initial_notification($email, $product_name) {
        $subject = get_option('wc_email_cart_subject', 'Product Added to Cart');
        $template = get_option('wc_email_initial_template', $this->get_default_initial_template());
        
        $message = $this->parse_template_variables($template, (object)[
            'email' => $email,
            'product_name' => $product_name
        ]);

        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($email, $subject, $message, $headers);
    }

    private function get_default_initial_template() {
        return '
        <html>
        <body style="font-family: Arial, sans-serif;">
            <h2>Thank you for your interest!</h2>
            <p>You have added <strong>{cart_items}</strong> to your cart.</p>
            <p>Complete your purchase now to secure your item.</p>
            <p><a href="{cart_link}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Cart</a></p>
        </body>
        </html>';
    }

    public function reset_schedule() {
        wp_clear_scheduled_hook('wc_email_cart_send_reminders');
        $next_minute = strtotime(date('Y-m-d H:i:00', time() + 60));
        wp_schedule_event($next_minute, 'five_minutes', 'wc_email_cart_send_reminders');
        return true;
    }
}

endif;

// Initialize the class
function wc_email_reminder() {
    return WC_Email_Cart_Reminder::get_instance();
}

add_action('plugins_loaded', 'wc_email_reminder');
