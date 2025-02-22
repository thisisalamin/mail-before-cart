<?php
if (!defined('ABSPATH')) {
    exit;
}

function wc_email_cart_get_stats_cards() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    
    $total_emails = $wpdb->get_var("SELECT COUNT(DISTINCT email) FROM $table_name");
    $total_products = $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM $table_name");
    $reminder_sent = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE reminder_sent = 1");
    
    ob_start();
    ?>
    <div class="bg-white overflow-hidden shadow rounded-lg">
        <div class="p-5">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div class="ml-5 w-0 flex-1">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Unique Emails</dt>
                    <dd class="text-lg font-medium text-gray-900"><?php echo $total_emails; ?></dd>
                </div>
            </div>
        </div>
    </div>
    <!-- Add more stat cards here -->
    <?php
    return ob_get_clean();
}

function wc_email_cart_get_emails_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    
    ob_start();
    ?>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
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
                            <?php foreach ($results as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo esc_html($row->email); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo esc_html($row->product_name); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo esc_html($row->created_at); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $row->reminder_sent ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $row->reminder_sent ? 'Reminded' : 'Pending'; ?>
                                    </span>
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
    return ob_get_clean();
}

function wc_email_cart_get_settings_form() {
    ob_start();
    ?>
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Email Cart Configuration</h3>
            <form action="options.php" method="post" class="space-y-6">
                <?php settings_fields('wc_email_cart'); ?>
                
                <div class="grid grid-cols-1 gap-6">
                    <!-- Email Field Label -->
                    <div>
                        <label for="wc_email_cart_label" class="block text-sm font-medium text-gray-700">
                            Email Field Label
                        </label>
                        <input type="text" 
                               name="wc_email_cart_label" 
                               id="wc_email_cart_label"
                               value="<?php echo esc_attr(get_option('wc_email_cart_label', 'Enter Your Email to Add to Cart:')); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    <!-- Email Field Placeholder -->
                    <div>
                        <label for="wc_email_cart_placeholder" class="block text-sm font-medium text-gray-700">
                            Email Field Placeholder
                        </label>
                        <input type="text" 
                               name="wc_email_cart_placeholder" 
                               id="wc_email_cart_placeholder"
                               value="<?php echo esc_attr(get_option('wc_email_cart_placeholder', 'your@email.com')); ?>"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    <!-- Reminder Days -->
                    <div>
                        <label for="wc_email_cart_reminder_days" class="block text-sm font-medium text-gray-700">
                            Send Reminder After (days)
                        </label>
                        <input type="number" 
                               name="wc_email_cart_reminder_days" 
                               id="wc_email_cart_reminder_days"
                               value="<?php echo esc_attr(get_option('wc_email_cart_reminder_days', '1')); ?>"
                               min="1"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div class="pt-5">
                    <button type="submit" 
                            class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function wc_email_cart_get_export_options() {
    ob_start();
    ?>
    <div class="bg-white shadow sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">Export Data</h3>
            <div class="mt-5">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wc_export_emails_to_csv">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Export to CSV
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
