<?php
if (!defined('ABSPATH')) exit;

function wc_email_cart_dashboard() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
    
    // Handle active tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';

    // Add Chart.js to header
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
    
    // Add AJAX support
    wp_enqueue_script('jquery');
    ?>
    <script type="text/javascript">
    /* <![CDATA[ */
    var wcEmailCart = {
        ajaxurl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
        nonce: '<?php echo wp_create_nonce('wc_email_cart_nonce'); ?>',
        sending: 'Sending...',
        success: 'Email sent successfully!',
        error: 'Failed to send email'
    };
    /* ]]> */
    </script>

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
    
    // Enhanced statistics
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total_emails,
            SUM(CASE WHEN reminder_sent = 1 THEN 1 ELSE 0 END) as total_reminders,
            SUM(CASE WHEN status = 'purchased' THEN 1 ELSE 0 END) as total_conversions,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_emails
        FROM $table_name
    ");

    // Calculate conversion rate
    $conversion_rate = $stats->total_emails > 0 
        ? round(($stats->total_conversions / $stats->total_emails) * 100, 2)
        : 0;

    // Get last 7 days data for chart
    $daily_stats = $wpdb->get_results("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'purchased' THEN 1 ELSE 0 END) as conversions
        FROM $table_name
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    ?>
    <!-- Enhanced Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="text-2xl font-bold text-blue-600"><?php echo esc_html($stats->total_emails); ?></div>
            <div class="text-gray-600">Total Emails</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="text-2xl font-bold text-green-600"><?php echo esc_html($stats->total_conversions); ?></div>
            <div class="text-gray-600">Reminder Sent</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="text-2xl font-bold text-yellow-600"><?php echo esc_html($stats->today_emails); ?></div>
            <div class="text-gray-600">Today's Emails</div>
        </div>
    </div>

    <!-- Initialize Charts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Activity Chart
        new Chart(document.getElementById('activityChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_stats, 'date')); ?>,
                datasets: [{
                    label: 'Emails Captured',
                    data: <?php echo json_encode(array_column($daily_stats, 'total')); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    tension: 0.1
                }, {
                    label: 'Conversions',
                    data: <?php echo json_encode(array_column($daily_stats, 'conversions')); ?>,
                    borderColor: 'rgb(34, 197, 94)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Status Distribution Chart
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Purchased', 'Abandoned'],
                datasets: [{
                    data: [
                        <?php 
                        echo $stats->total_emails - $stats->total_conversions . ',';
                        echo $stats->total_conversions . ',';
                        echo $stats->total_reminders;
                        ?>
                    ],
                    backgroundColor: [
                        'rgb(234, 179, 8)',
                        'rgb(34, 197, 94)',
                        'rgb(239, 68, 68)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
    </script>

    <!-- Recent Entries Table with Enhanced Status -->
    <?php
    $recent_entries = $wpdb->get_results("
        SELECT 
            id,
            email, 
            product_name, 
            created_at, 
            status,
            reminder_sent,
            (SELECT COUNT(*) FROM {$wpdb->prefix}wc_email_cart_tracking WHERE email = t1.email) as email_count
        FROM {$table_name} t1
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    ?>
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
                    <tr class="hover:bg-gray-50" data-entry-id="<?php echo esc_attr($entry->id); ?>">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-sm text-gray-900"><?php echo esc_html($entry->email); ?></div>
                                <?php if ($entry->email_count > 1): ?>
                                    <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo esc_html($entry->email_count); ?> carts
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo esc_html($entry->product_name); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo esc_html(human_time_diff(strtotime($entry->created_at), current_time('timestamp'))); ?> ago
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            $status_class = match($entry->status) {
                                'purchased' => 'bg-green-100 text-green-800',
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                default => 'bg-gray-100 text-gray-800'
                            };
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html(ucfirst($entry->status ?: 'pending')); ?>
                            </span>
                            <?php if ($entry->reminder_sent): ?>
                                <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                    Reminded
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('.send-reminder-btn').on('click', function() {
            const btn = $(this);
            const row = btn.closest('tr');
            const email = btn.data('email');
            const id = btn.data('id');
            
            console.log('Sending email...', {
                email: email,
                id: id,
                ajaxurl: wcEmailCart.ajaxurl,
                nonce: wcEmailCart.nonce
            });
            
            if (!confirm(`Send reminder email to ${email}?`)) {
                return;
            }

            btn.prop('disabled', true).text(wcEmailCart.sending);

            // Use jQuery.ajax instead of $.post for better error handling
            $.ajax({
                url: wcEmailCart.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_send_manual_reminder',
                    _ajax_nonce: wcEmailCart.nonce,
                    id: id
                },
                success: function(response) {
                    console.log('Response:', response);
                    
                    if (response.success) {
                        // Update button state
                        btn.closest('td').html('<span class="text-green-600">✓ Sent</span>');
                        
                        // Add reminded badge
                        const statusCell = row.find('td:nth-child(4)');
                        const currentStatus = statusCell.find('.rounded-full').first();
                        
                        if (!statusCell.find('.bg-purple-100').length) {
                            statusCell.append(
                                '<span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">Reminded</span>'
                            );
                        }
                        
                        // Show success notification
                        $('<div>')
                            .addClass('notice notice-success')
                            .html(`<p>${wcEmailCart.success}</p>`)
                            .insertAfter(row)
                            .fadeIn()
                            .delay(3000)
                            .fadeOut();
                    } else {
                        alert(response.data || wcEmailCart.error);
                        btn.prop('disabled', false).text('Send Now');
                    }
                },
                error: function(xhr, textStatus, error) {
                    console.error('AJAX error:', {xhr, textStatus, error});
                    alert(wcEmailCart.error + (error ? ': ' + error : ''));
                    btn.prop('disabled', false).text('Send Now');
                }
            });
        });
    });
    </script>
    <?php
}
