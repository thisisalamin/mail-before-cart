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
            <div class="text-2xl font-bold text-green-600"><?php echo esc_html($stats->total_reminders); ?></div>
            <div class="text-gray-600">Reminder Sent</div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div class="text-2xl font-bold text-yellow-600"><?php echo esc_html($stats->today_emails); ?></div>
            <div class="text-gray-600">Today's Emails</div>
        </div>
    </div>

    <!-- Add Clear Stats button after the statistics cards -->
    <div class="mb-8 flex justify-end">
        <form method="post" onsubmit="return confirm('Are you sure you want to clear all statistics and emails? This cannot be undone.');">
            <?php wp_nonce_field('clear_stats_nonce', 'clear_stats_security'); ?>
            <input type="hidden" name="action" value="clear_all_stats">
            <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                Clear All Statistics
            </button>
        </form>
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
            product_id, 
            created_at, 
            status,
            reminder_sent,
            last_reminder_sent,
            (SELECT COUNT(*) FROM {$wpdb->prefix}wc_email_cart_tracking WHERE email = t1.email) as email_count
        FROM {$table_name} t1
        ORDER BY created_at DESC 
        LIMIT 10
    ");

    if (!$recent_entries) {
        echo '<div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 text-center text-gray-500">
            No emails have been captured yet.
        </div>';
        return;
    }
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($recent_entries as $entry): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo esc_html($entry->email); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo esc_html($entry->product_name); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo esc_html(human_time_diff(strtotime($entry->created_at), current_time('timestamp'))); ?> ago
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php 
                            $status_class = 'bg-yellow-100 text-yellow-800';
                            if ($entry->status === 'purchased') {
                                $status_class = 'bg-green-100 text-green-800';
                            }
                            ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                <?php echo esc_html(ucfirst($entry->status)); ?>
                            </span>
                            <?php if ($entry->reminder_sent): ?>
                                <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                    Reminded
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button class="send-reminder-btn bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm"
                                    data-id="<?php echo esc_attr($entry->id); ?>"
                                    data-email="<?php echo esc_attr($entry->email); ?>">
                                <?php echo $entry->reminder_sent ? 'Send Again' : 'Send Now'; ?>
                            </button>
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
                        // Update button text with timestamp
                        const now = new Date();
                        const timeAgo = 'just now';
                        btn.html(`Send Again <span class="text-xs">(${timeAgo})</span>`);
                        
                        // Add or update last sent timestamp
                        const timestampDiv = btn.siblings('.text-gray-500');
                        if (timestampDiv.length) {
                            timestampDiv.text('Last sent: just now');
                        } else {
                            btn.after('<div class="text-xs text-gray-500 mt-1">Last sent: just now</div>');
                        }
                        
                        // Add reminded badge if not exists
                        const statusCell = row.find('td:nth-child(4)');
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

// Add this at the start of the dashboard.php file
add_action('admin_init', 'handle_stats_clear');
function handle_stats_clear() {
    if (
        isset($_POST['action']) && 
        $_POST['action'] === 'clear_all_stats' && 
        check_admin_referer('clear_stats_nonce', 'clear_stats_security')
    ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_email_cart_tracking';
        
        // Clear the table
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        // Redirect with success message
        wp_redirect(add_query_arg('cleared', 'true', admin_url('admin.php?page=wc-abandoned-emails')));
        exit;
    }
}

// Add success message
add_action('admin_notices', 'show_stats_cleared_message');
function show_stats_cleared_message() {
    if (isset($_GET['cleared']) && $_GET['cleared'] === 'true') {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>All statistics and emails have been cleared successfully!</p>
        </div>
        <?php
    }
}
