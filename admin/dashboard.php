<?php
if (!defined('ABSPATH')) exit;

function wc_email_cart_dashboard() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    
    // Handle active tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
    ?>
    <div class="wrap bg-gray-50 min-h-screen p-6">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Email Cart Dashboard</h1>
            </div>

            <!-- Navigation Tabs -->
            <div class="mb-8 border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <a href="?page=wc-abandoned-emails&tab=dashboard" 
                       class="<?php echo $active_tab === 'dashboard' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Dashboard
                    </a>
                    <a href="?page=wc-abandoned-emails&tab=settings" 
                       class="<?php echo $active_tab === 'settings' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Settings
                    </a>
                </nav>
            </div>

            <?php
            if ($active_tab === 'settings') {
                // Display settings form
                wc_email_cart_settings_page();
            } else {
                // Display dashboard content
                wc_email_cart_display_dashboard();
            }
            ?>
        </div>
    </div>
    <?php
}

function wc_email_cart_display_dashboard() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    
    // Get statistics with status counts
    $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_reminders = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE reminder_sent = 1");
    $today_emails = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s", date('Y-m-d')));
    
    // Get recent entries with explicit column selection
    $recent_entries = $wpdb->get_results("
        SELECT email, product_name, created_at, 
               COALESCE(status, 'pending') as status 
        FROM $table_name 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    ?>
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="text-2xl font-bold text-blue-600"><?php echo esc_html($total_emails); ?></div>
            <div class="text-gray-600">Total Captured Emails</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="text-2xl font-bold text-green-600"><?php echo esc_html($today_emails); ?></div>
            <div class="text-gray-600">Today's Emails</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="text-2xl font-bold text-purple-600"><?php echo esc_html($total_reminders); ?></div>
            <div class="text-gray-600">Reminders Sent</div>
        </div>
    </div>

    <!-- Export Button -->
    <div class="mb-6">
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="wc_export_emails_to_csv">
            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export to CSV
            </button>
        </form>
    </div>

    <!-- Recent Entries Table -->
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Recent Entries</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($recent_entries as $entry): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php echo esc_html($entry->email); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo esc_html($entry->product_name); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?php echo esc_html(date('M j, Y', strtotime($entry->created_at))); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $status = $entry->status ? $entry->status : 'pending';
                            $status_class = '';
                            $status_text = ucfirst($status);
                            
                            switch($status) {
                                case 'purchased':
                                    $status_class = 'bg-green-100 text-green-800';
                                    break;
                                case 'pending':
                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                    break;
                                default:
                                    $status_class = 'bg-gray-100 text-gray-800';
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
