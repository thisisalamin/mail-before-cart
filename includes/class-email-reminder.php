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
    }

    public function init() {
        // Initialize the reminder system
    }

    public function send_reminder($cart) {
        $subject = get_option('wc_email_reminder_subject', 'Complete Your Purchase!');
        $template = get_option('wc_email_reminder_template', $this->get_default_template());
        
        // Process template variables
        $body = $this->process_template($template, $cart);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        $result = wp_mail($cart->email, $subject, $body, $headers);
        remove_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        
        return $result;
    }

    private function send_reminder_email($cart) {
        // Get email template settings
        $subject = get_option('wc_email_reminder_subject', 'Complete Your Purchase!');
        $template = get_option('wc_email_reminder_template', $this->get_default_template());
        
        // Process template variables
        $body = $this->process_template($template, $cart);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        error_log('Sending reminder email to: ' . $cart->email);
        
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        $result = wp_mail($cart->email, $subject, $body, $headers);
        remove_filter('wp_mail_content_type', array($this, 'set_html_content_type'));

        return $result;
    }

    public function set_html_content_type() {
        return 'text/html';
    }

    public function get_default_template() {
        return wc_email_cart_get_default_template();
    }

    private function process_template($template, $cart) {
        $replacements = array(
            '{site_name}' => get_bloginfo('name'),
            '{customer_name}' => $this->get_customer_name($cart->email),
            '{cart_items}' => $cart->product_name,
            '{cart_link}' => $this->get_cart_recovery_link($cart),
            '{email}' => $cart->email
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function get_cart_recovery_link($cart) {
        $token = wp_create_nonce('recover_cart_' . $cart->id);
        return add_query_arg(array(
            'recover_cart' => $cart->id,
            'token' => $token
        ), wc_get_cart_url());
    }

    private function get_customer_name($email) {
        $user = get_user_by('email', $email);
        return $user ? $user->display_name : 'Valued Customer';
    }
}

endif;

// Initialize the class
add_action('plugins_loaded', array('WC_Email_Cart_Reminder', 'get_instance'));
