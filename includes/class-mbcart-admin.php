<?php
/**
 * Unified Admin class: menu, dashboard, settings, exports, manual reminders.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! class_exists( 'MBCart_Admin' ) ) {
	class MBCart_Admin {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self(); }
			return self::$instance;
		}

		private function __construct() {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 60 );
			add_action( 'admin_init', array( $this, 'maybe_clear_stats' ) );
			add_action( 'admin_notices', array( $this, 'stats_cleared_notice' ) );
			add_action( 'admin_init', array( $this, 'maybe_export_filtered' ) );
			add_action( 'admin_post_wc_export_emails_to_csv', array( $this, 'export_all_to_csv' ) );
			add_action( 'wp_ajax_wc_send_manual_reminder', array( $this, 'ajax_send_manual_reminder' ) );
		}

		public function register_menu() {
			add_submenu_page(
				'woocommerce',
				__( 'Mail Before Cart', 'mail-before-cart' ),
				__( 'Mail Before Cart', 'mail-before-cart' ),
				'manage_woocommerce',
				'wc-abandoned-emails',
				array( $this, 'render_page' )
			);
		}

		public function render_page() {
			$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true );
			wp_enqueue_script( 'jquery' );
			echo '<div class="wrap mbc-wrap"><div class="mbc-container">';
			echo '<h1 class="mbc-page-title">' . esc_html__( 'Mail Before Cart', 'mail-before-cart' ) . '</h1>';
			echo '<nav class="mbc-tabs-nav">';
			$tabs = array(
				'dashboard' => __( 'Dashboard', 'mail-before-cart' ),
				'settings'  => __( 'Settings', 'mail-before-cart' ),
			);
			foreach ( $tabs as $slug => $label ) {
				$cls = $active_tab === $slug ? 'mbc-tab-active' : 'mbc-tab';
				echo '<a href="?page=wc-abandoned-emails&tab=' . esc_attr( $slug ) . '" class="' . esc_attr( $cls ) . '">' . esc_html( $label ) . '</a>';
			}
			echo '</nav>';
			if ( 'settings' === $active_tab ) {
				$this->render_settings();
			} else {
				$this->render_dashboard(); }
			echo '</div></div>';
			$this->inline_js_object();
		}

		private function inline_js_object() {
			echo '<script>var wcEmailCart=' . wp_json_encode(
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'mbcart_nonce' ),
					'sending' => __( 'Sending...', 'mail-before-cart' ),
					'success' => __( 'Email sent successfully!', 'mail-before-cart' ),
					'error'   => __( 'Failed to send email', 'mail-before-cart' ),
				)
			) . ';</script>';
		}

		/* Dashboard */
		private function get_stats() {
			global $wpdb;
			$t = $wpdb->prefix . 'mbcart_tracking';
			return $wpdb->get_row( "SELECT COUNT(*) total_emails, SUM(CASE WHEN reminder_sent=1 THEN 1 ELSE 0 END) total_reminders, SUM(CASE WHEN status='purchased' THEN 1 ELSE 0 END) total_conversions, SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) today_emails FROM {$t}" );
		}
		private function get_daily_stats() {
			global $wpdb;
			$t = $wpdb->prefix . 'mbcart_tracking';
			return $wpdb->get_results( "SELECT DATE(created_at) date, COUNT(*) total, SUM(CASE WHEN status='purchased' THEN 1 ELSE 0 END) conversions FROM {$t} WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date ASC" );
		}
		private function build_filters( &$wc, &$params ) {
			$where  = array( '1=1' );
			$p      = array();
			$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$from   = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
			$to     = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
			if ( $status ) {
				$where[] = 'status = %s';
				$p[]     = $status; }
			if ( $from ) {
				$where[] = 'created_at >= %s';
				$p[]     = $from . ' 00:00:00'; }
			if ( $to ) {
				$where[] = 'created_at <= %s';
				$p[]     = $to . ' 23:59:59'; }
			$wc     = implode( ' AND ', $where );
			$params = $p;
		}
		private function get_entries( $wc, $params, $per_page, $offset, &$total ) {
			global $wpdb;
			$t = $wpdb->prefix . 'mbcart_tracking';
			if ( $params ) {
				$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$wc}", $params ) ); } else {
				$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$wc}" ); }
				$query   = "SELECT id,email,product_name,product_id,created_at,status,reminder_sent,last_reminder_sent FROM {$t} WHERE {$wc} ORDER BY created_at DESC LIMIT %d OFFSET %d";
				$qparams = array_merge( $params, array( $per_page, $offset ) );
				return $params ? $wpdb->get_results( $wpdb->prepare( $query, $qparams ) ) : $wpdb->get_results( $wpdb->prepare( $query, $per_page, $offset ) );
		}
		private function render_dashboard() {
			$stats    = $this->get_stats();
			$daily    = $this->get_daily_stats();
			$per_page = 10;
			$current  = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$offset   = ( $current - 1 ) * $per_page;
			$wc       = '';
			$params   = array();
			$this->build_filters( $wc, $params );
			$total   = 0;
			$entries = $this->get_entries( $wc, $params, $per_page, $offset, $total );
			$pages   = (int) ceil( $total / $per_page );
			$this->dashboard_filters();
			$this->stat_cards( $stats );
			$this->charts( $stats, $daily );
			$this->entries_table( $entries );
			$this->pagination( $current, $per_page, $total, $pages );
		}
		private function dashboard_filters() {
			echo '<form method="get" class="mbc-filters-form">';
			echo '<input type="hidden" name="page" value="wc-abandoned-emails" />';
			$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$from   = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
			$to     = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
			echo '<div class="mbc-filter"><label class="mbc-label">' . esc_html__( 'Status', 'mail-before-cart' ) . '</label><select name="status" class="mbc-select">';
			echo '<option value="">' . esc_html__( 'All', 'mail-before-cart' ) . '</option>';
			foreach ( array(
				'pending'   => __( 'Pending', 'mail-before-cart' ),
				'purchased' => __( 'Purchased', 'mail-before-cart' ),
			) as $val => $label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $val ), selected( $status, $val, false ), esc_html( $label ) ); }
			echo '</select></div>';
			echo '<div class="mbc-filter"><label class="mbc-label">' . esc_html__( 'From', 'mail-before-cart' ) . '</label><input type="date" name="date_from" value="' . esc_attr( $from ) . '" class="mbc-input" /></div>';
			echo '<div class="mbc-filter"><label class="mbc-label">' . esc_html__( 'To', 'mail-before-cart' ) . '</label><input type="date" name="date_to" value="' . esc_attr( $to ) . '" class="mbc-input" /></div>';
			echo '<div class="mbc-filter-btns"><button class="mbc-btn mbc-btn-primary" type="submit">' . esc_html__( 'Filter', 'mail-before-cart' ) . '</button>';
			echo '<a class="mbc-btn mbc-btn-success" href="' . esc_url(
				add_query_arg(
					array(
						'action' => 'wc_export_filtered_emails',
						'nonce'  => wp_create_nonce( 'export_filtered_emails' ),
					)
				)
			) . '">' . esc_html__( 'Export Filtered', 'mail-before-cart' ) . '</a></div>';
			echo '</form>';
		}
		private function stat_cards( $s ) {
			echo '<div class="mbc-stats-grid">';
			$cards = array(
				array(
					'value' => (int) $s->total_emails,
					'label' => __( 'Total Emails', 'mail-before-cart' ),
					'color' => 'text-blue-600',
				),
				array(
					'value' => (int) $s->total_reminders,
					'label' => __( 'Reminders Sent', 'mail-before-cart' ),
					'color' => 'text-green-600',
				),
				array(
					'value' => (int) $s->today_emails,
					'label' => __( "Today's Emails", 'mail-before-cart' ),
					'color' => 'text-yellow-600',
				),
			);
			foreach ( $cards as $c ) {
				echo '<div class="mbc-stat-card"><div class="mbc-stat-value ' . esc_attr( $c['color'] ) . '">' . esc_html( $c['value'] ) . '</div><div class="mbc-stat-label">' . esc_html( $c['label'] ) . '</div></div>'; }
			echo '</div>';
			echo '<form method="post" onsubmit="return confirm(\'' . esc_js( __( 'Clear all statistics and emails?', 'mail-before-cart' ) ) . '\')" class="mbc-clear-form">';
			wp_nonce_field( 'clear_stats_nonce', 'clear_stats_security' );
			echo '<input type="hidden" name="action" value="clear_all_stats" />';
			echo '<button class="mbc-btn mbc-btn-danger" type="submit">' . esc_html__( 'Clear All Statistics', 'mail-before-cart' ) . '</button></form>';
		}
		private function charts( $stats, $daily ) {
			echo '<div class="mbc-charts"><canvas id="activityChart" height="140"></canvas><canvas id="statusChart" height="140"></canvas></div>';
			echo '<script>(function(){const dL=' . wp_json_encode( wp_list_pluck( $daily, 'date' ) ) . ',dT=' . wp_json_encode( wp_list_pluck( $daily, 'total' ) ) . ',dC=' . wp_json_encode( wp_list_pluck( $daily, 'conversions' ) ) . ';window.addEventListener("load",function(){if(window.Chart){new Chart(document.getElementById("activityChart"),{type:"line",data:{labels:dL,datasets:[{label:"' . esc_js( __( 'Emails Captured', 'mail-before-cart' ) ) . '",data:dT,borderColor:"#3b82f6",tension:.1},{label:"' . esc_js( __( 'Conversions', 'mail-before-cart' ) ) . '",data:dC,borderColor:"#22c55e",tension:.1}]}});new Chart(document.getElementById("statusChart"),{type:"doughnut",data:{labels:[' . wp_json_encode( array( __( 'Pending', 'mail-before-cart' ), __( 'Purchased', 'mail-before-cart' ), __( 'Reminded', 'mail-before-cart' ) ) ) . '],datasets:[{data:[' . ( (int) $stats->total_emails - (int) $stats->total_conversions ) . ',' . (int) $stats->total_conversions . ',' . (int) $stats->total_reminders . '],backgroundColor:["#eab308","#22c55e","#9333ea"]}]}});}})();</script>';
		}
		private function entries_table( $entries ) {
			if ( ! $entries ) {
				echo '<div class="mbc-empty">' . esc_html__( 'No emails captured yet.', 'mail-before-cart' ) . '</div>';
				return; }
			echo '<div class="mbc-table-wrap"><table class="mbc-table"><thead><tr>';
			foreach ( array( 'Email', 'Product', 'Date', 'Status', 'Actions' ) as $h ) {
				echo '<th class="mbc-th">' . esc_html( $h ) . '</th>'; }
			echo '</tr></thead><tbody>';
			foreach ( $entries as $e ) {
				$status_class = 'mbc-status-pending';
				if ( 'purchased' === $e->status ) {
					$status_class = 'mbc-status-purchased'; }
				echo '<tr class="mbc-row"><td class="mbc-td">' . esc_html( $e->email ) . '</td>';
				echo '<td class="mbc-td">' . esc_html( $e->product_name ) . '</td>';
				echo '<td class="mbc-td">' . esc_html( human_time_diff( strtotime( $e->created_at ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'mail-before-cart' ) . '</td>';
				echo '<td class="mbc-td"><span class="mbc-status ' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $e->status ) ) . '</span>' . ( $e->reminder_sent ? '<span class="mbc-status mbc-status-reminded">' . esc_html__( 'Reminded', 'mail-before-cart' ) . '</span>' : '' ) . '</td>';
				echo '<td class="mbc-td"><button class="send-reminder-btn mbc-btn mbc-btn-small mbc-btn-primary" data-id="' . esc_attr( $e->id ) . '" data-email="' . esc_attr( $e->email ) . '">' . ( $e->reminder_sent ? esc_html__( 'Send Again', 'mail-before-cart' ) : esc_html__( 'Send Now', 'mail-before-cart' ) ) . '</button></td></tr>';
			}
			echo '</tbody></table></div>';
				echo <<<'JS'
<script>
jQuery(function($){
    $(document).on("click",".send-reminder-btn",function(){
        const b=$(this),id=b.data("id"),email=b.data("email");
        if(!confirm("Send reminder to "+email+"?")) return;
        b.prop("disabled",true).text(wcEmailCart.sending);
        $.post(wcEmailCart.ajaxurl,{action:"wc_send_manual_reminder",_ajax_nonce:wcEmailCart.nonce,id:id},function(r){
            if(r.success){
                b.html("Send Again <span class=\"text-xs\">(just now)</span>");
                if(!b.closest("tr").find(".bg-purple-100").length){
                    b.closest("tr").find("td:nth-child(4)").append("<span class=\"ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800\">Reminded</span>");
                }
            } else {
                alert(r.data||wcEmailCart.error);
                b.prop("disabled",false).text("Send Now");
            }
        }).fail(function(){
            alert(wcEmailCart.error);
            b.prop("disabled",false).text("Send Now");
        });
    });
});
</script>
JS;
		}
		private function pagination( $current, $per_page, $total, $pages ) {
			if ( $pages <= 1 ) {
				return; }
			$start = ( ( $current - 1 ) * $per_page ) + 1;
			$end   = min( $current * $per_page, $total );
			echo '<div class="mbc-pagination"><div class="mbc-pagination-info">' . sprintf( esc_html__( 'Showing %1$d to %2$d of %3$d entries', 'mail-before-cart' ), $start, $end, $total ) . '</div><div class="mbc-pagination-links">';
			if ( $current > 1 ) {
				echo '<a class="mbc-page-link" href="' . esc_url( add_query_arg( 'paged', $current - 1 ) ) . '">&laquo; ' . esc_html__( 'Previous', 'mail-before-cart' ) . '</a>'; }
			if ( $current < $pages ) {
				echo '<a class="mbc-page-link" href="' . esc_url( add_query_arg( 'paged', $current + 1 ) ) . '">' . esc_html__( 'Next', 'mail-before-cart' ) . ' &raquo;</a>'; }
			echo '</div></div>';
		}

		/* Settings */
		private function render_settings() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return; }
			if ( isset( $_POST['mbcart_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mbcart_settings_nonce'] ) ), 'mbcart_settings' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				update_option( 'mbcart_label', sanitize_text_field( wp_unslash( $_POST['mbcart_label'] ) ) );
				update_option( 'mbcart_placeholder', sanitize_text_field( wp_unslash( $_POST['mbcart_placeholder'] ) ) );
				update_option( 'mbcart_subject', sanitize_text_field( wp_unslash( $_POST['mbcart_subject'] ) ) );
				update_option( 'mbcart_reminder_subject', sanitize_text_field( wp_unslash( $_POST['mbcart_reminder_subject'] ) ) );
				update_option( 'wc_email_reminder_template', wp_kses_post( wp_unslash( $_POST['reminder_template'] ) ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'mail-before-cart' ) . '</p></div>';
			}
			$label = get_option( 'mbcart_label', 'Enter Your Email to Add to Cart:' );
			$ph    = get_option( 'mbcart_placeholder', 'your@email.com' );
			$subj  = get_option( 'mbcart_subject', 'Product Added to Cart' );
			$rsubj = get_option( 'mbcart_reminder_subject', 'Complete Your Purchase' );
			$tmpl  = get_option( 'wc_email_reminder_template', $this->default_template() );
			echo '<form method="post" class="mbc-settings-form">';
			wp_nonce_field( 'mbcart_settings', 'mbcart_settings_nonce' );
			echo '<div class="mbc-section"><h2 class="mbc-section-title">' . esc_html__( 'Email Field', 'mail-before-cart' ) . '</h2>';
			$this->input_field( 'mbcart_label', __( 'Email Field Label', 'mail-before-cart' ), $label );
			$this->input_field( 'mbcart_placeholder', __( 'Email Field Placeholder', 'mail-before-cart' ), $ph );
			echo '</div><div class="mbc-section"><h2 class="mbc-section-title">' . esc_html__( 'Email Content', 'mail-before-cart' ) . '</h2>';
			$this->input_field( 'mbcart_subject', __( 'Initial Email Subject', 'mail-before-cart' ), $subj );
			$this->input_field( 'mbcart_reminder_subject', __( 'Reminder Email Subject', 'mail-before-cart' ), $rsubj );
			echo '<label class="mbc-label" for="reminder_template">' . esc_html__( 'Reminder Email Template', 'mail-before-cart' ) . '</label>';
			echo '<textarea class="mbc-textarea" name="reminder_template" id="reminder_template" rows="8">' . esc_textarea( $tmpl ) . '</textarea>';
			echo '<p class="mbc-help">' . esc_html__( 'Available: {site_name}, {customer_name}, {cart_items}, {cart_link}, {email}', 'mail-before-cart' ) . '</p>';
			echo '</div><div><button type="submit" class="mbc-btn mbc-btn-primary">' . esc_html__( 'Save Settings', 'mail-before-cart' ) . '</button></div></form>';
		}
		private function input_field( $name, $label, $value ) {
			echo '<div class="mbc-field"><label for="' . esc_attr( $name ) . '" class="mbc-label">' . esc_html( $label ) . '</label><input class="mbc-input" type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" /></div>'; }
		private function default_template() {
			return '<div><h2>Hello {customer_name},</h2><p>' . esc_html__( 'You left something in your cart.', 'mail-before-cart' ) . '</p><p>{cart_items}</p><p><a href="{cart_link}">' . esc_html__( 'Complete Purchase', 'mail-before-cart' ) . '</a></p></div>'; }

		/* Stats clearing */
		public function maybe_clear_stats() {
			if ( isset( $_POST['action'], $_POST['clear_stats_security'] ) && 'clear_all_stats' === $_POST['action'] && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['clear_stats_security'] ) ), 'clear_stats_nonce' ) ) {
				global $wpdb;
				$t = $wpdb->prefix . 'mbcart_tracking';
				$wpdb->query( "TRUNCATE TABLE {$t}" );
				set_transient( 'mbcart_cleared', true, 30 );
				wp_safe_redirect( admin_url( 'admin.php?page=wc-abandoned-emails' ) );
				exit; } }
		public function stats_cleared_notice() {
			if ( get_transient( 'mbcart_cleared' ) ) {
				delete_transient( 'mbcart_cleared' );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All statistics cleared.', 'mail-before-cart' ) . '</p></div>'; } }

		/* CSV Export */
		public function maybe_export_filtered() {
			if ( isset( $_GET['action'] ) && 'wc_export_filtered_emails' === $_GET['action'] ) {
				$this->export_filtered(); } } // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		private function export_filtered() {
			if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'export_filtered_emails' ) ) {
				wp_die( esc_html__( 'Unauthorized', 'mail-before-cart' ) ); }
			$wc     = '';
			$params = array();
			$this->build_filters( $wc, $params );
			global $wpdb;
			$t    = $wpdb->prefix . 'mbcart_tracking';
			$q    = "SELECT * FROM {$t} WHERE {$wc} ORDER BY created_at DESC";
			$rows = $params ? $wpdb->get_results( $wpdb->prepare( $q, $params ), ARRAY_A ) : $wpdb->get_results( $q, ARRAY_A );
			if ( $rows ) {
				$this->stream_csv( 'abandoned_cart_emails_filtered_', $rows ); }
		}
		public function export_all_to_csv() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			} global $wpdb;
			$t    = $wpdb->prefix . 'mbcart_tracking';
			$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY created_at DESC", ARRAY_A );
			if ( $rows ) {
				$this->stream_csv( 'abandoned_cart_emails_', $rows ); } }
		private function stream_csv( $prefix, $rows ) {
			$filename = $prefix . date( 'Y-m-d' ) . '.csv';
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment;filename=' . $filename );
			$out = fopen( 'php://output', 'w' );
			fputcsv( $out, array( 'Email', 'Product ID', 'Product Name', 'Date Created', 'Status', 'Reminder Sent' ) );
			foreach ( $rows as $r ) {
				fputcsv( $out, array( $r['email'], $r['product_id'], $r['product_name'], $r['created_at'], $r['status'], ! empty( $r['reminder_sent'] ) ? 'Yes' : 'No' ) );
			} fclose( $out );
			exit; }

		/* Manual reminder */
		public function ajax_send_manual_reminder() {
			check_ajax_referer( 'mbcart_nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( __( 'Permission denied', 'mail-before-cart' ) ); }
			$id = isset( $_POST['id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $id ) {
				wp_send_json_error( __( 'Invalid ID', 'mail-before-cart' ) ); }
			global $wpdb;
			$t    = $wpdb->prefix . 'mbcart_tracking';
			$cart = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id=%d", $id ) );
			if ( ! $cart ) {
				wp_send_json_error( __( 'Not found', 'mail-before-cart' ) ); }
			if ( class_exists( 'MBCart_Reminder' ) ) {
				$sent = MBCart_Reminder::instance()->send_reminder_email( $cart );
			} else {
				$sent = false; }
			if ( $sent ) {
				$wpdb->update(
					$t,
					array(
						'reminder_sent'      => 1,
						'last_reminder_sent' => current_time( 'mysql' ),
					),
					array( 'id' => $id )
				);
				wp_send_json_success(
					array(
						'message'    => __( 'Email sent', 'mail-before-cart' ),
						'buttonText' => __( 'Send Again', 'mail-before-cart' ),
					)
				); }
			wp_send_json_error( __( 'Failed to send email', 'mail-before-cart' ) );
		}
	}
}

MBCart_Admin::instance();
