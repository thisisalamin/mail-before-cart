<?php
/**
 * Core plugin bootstrap class (refactor scaffold).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! class_exists( 'MBCart_Plugin' ) ) {
	class MBCart_Plugin {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->define_constants();
			$this->includes();
			$this->hooks();
		}

		private function define_constants() {
			if ( ! defined( 'MBCART_VERSION' ) ) {
				define( 'MBCART_VERSION', '1.0.0' );
			}
			if ( ! defined( 'MBCART_PATH' ) ) {
				define( 'MBCART_PATH', plugin_dir_path( __DIR__ ) );
			}
			if ( ! defined( 'MBCART_URL' ) ) {
				define( 'MBCART_URL', plugin_dir_url( __DIR__ ) );
			}
		}

		private function includes() {
			// Ensure reminder class loads early.
			require_once MBCART_PATH . 'includes/class-email-reminder.php';
			// Admin class already required directly in main file after refactor.
		}

		private function hooks() {
			register_activation_hook( MBCART_PATH . 'mail-before-cart.php', array( __CLASS__, 'activate' ) );
			register_deactivation_hook( MBCART_PATH . 'mail-before-cart.php', array( __CLASS__, 'deactivate' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'capture_email_cart_item' ), 10, 2 );
			add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_email_field' ) );
			add_action( 'wp_ajax_wc_check_email_exists', array( $this, 'ajax_check_email' ) );
			add_action( 'wp_ajax_nopriv_wc_check_email_exists', array( $this, 'ajax_check_email' ) );
		}

		public static function activate() {
			self::create_tables();
			self::update_schema();
		}

		public static function deactivate() {
			wp_clear_scheduled_hook( 'mbcart_send_reminders' );
		}

		public static function create_tables() {
			global $wpdb;
			$table   = $wpdb->prefix . 'mbcart_tracking';
			$charset = $wpdb->get_charset_collate();
			$sql     = "CREATE TABLE IF NOT EXISTS $table (id mediumint(9) NOT NULL AUTO_INCREMENT,email varchar(100) NOT NULL,product_id bigint(20) NOT NULL,product_name varchar(255) NOT NULL,created_at datetime DEFAULT CURRENT_TIMESTAMP,reminder_sent TINYINT(1) DEFAULT 0,status varchar(50) DEFAULT 'pending',last_reminder_sent datetime DEFAULT NULL,PRIMARY KEY  (id)) $charset;";
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		public static function update_schema() {
			// keeps earlier updater logic minimal
			self::create_tables();
		}

		public function frontend_assets() {
			wp_enqueue_style( 'mbcart-tailwind', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), '2.2.19' ); }
		public function admin_assets( $hook ) {
			if ( 'woocommerce_page_wc-abandoned-emails' === $hook ) {
				wp_enqueue_style( 'mbcart-tailwind-admin', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), '2.2.19' );
			}
		}

		public function render_email_field() {
			if ( ! function_exists( 'wc_get_product' ) ) {
				return; }
			global $product;
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || $product->is_type( 'external' ) ) {
				return; }
			$label       = get_option( 'mbcart_label', 'Enter Your Email to Add to Cart:' );
			$placeholder = get_option( 'mbcart_placeholder', 'your@email.com' );
			?>
			<p class="email-before-add-to-cart">
				<label for="customer_email" class="text-gray-700 font-medium"><?php echo esc_html( $label ); ?></label>
				<input type="email" id="customer_email" name="customer_email" required placeholder="<?php echo esc_attr( $placeholder ); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
			</p>
			<script>
			(function($){$('form.cart').on('submit',function(e){var email=$('#customer_email').val();if(!email){alert('<?php echo esc_js( __( 'Please enter your email before adding this item to the cart.', 'mail-before-cart' ) ); ?>');e.preventDefault();return false;}$.post('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',{action:'wc_check_email_exists',email:email},function(r){if(r.exists){alert('<?php echo esc_js( __( 'This email is already in use. Please use a different email.', 'mail-before-cart' ) ); ?>');e.preventDefault();return false;}else{localStorage.setItem('customer_email',email);}});});})(jQuery);
			</script>
			<?php
		}

		public function ajax_check_email() {
			global $wpdb;
			$email  = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$table  = $wpdb->prefix . 'mbcart_tracking';
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE email=%s", $email ) );
			wp_send_json( array( 'exists' => $exists > 0 ) ); }

		public function capture_email_cart_item( $cart_item_data, $product_id ) {
			if ( isset( $_POST['customer_email'] ) && function_exists( 'WC' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				global $wpdb;
				$email   = sanitize_email( wp_unslash( $_POST['customer_email'] ) );
				$product = wc_get_product( $product_id );
				$wpdb->insert(
					$wpdb->prefix . 'mbcart_tracking',
					array(
						'email'        => $email,
						'product_id'   => $product_id,
						'product_name' => $product ? $product->get_name() : '',
						'status'       => 'pending',
						'created_at'   => current_time( 'mysql' ),
					),
					array( '%s', '%d', '%s', '%s', '%s' )
				);
				WC()->session->set( 'customer_email', $email );
			}
			return $cart_item_data;
		}
	}
}

// Bootstrap
MBCart_Plugin::instance();
