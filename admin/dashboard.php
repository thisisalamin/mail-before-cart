<?php
if (!defined('ABSPATH')) exit;

function wc_email_cart_dashboard() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    
    // Get statistics
    $total_emails = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_reminders = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE reminder_sent = 1");
    $today_emails = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s", date('Y-m-d')));
    
    // Get recent entries
    $recent_entries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");
    ?>
    <div class="wrap bg-gray-50 min-h-screen p-6">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Email Cart Dashboard</h1>
            
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
                                    <?php if ($entry->reminder_sent): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Reminder Sent
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}
