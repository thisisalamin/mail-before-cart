(function($) {
    'use strict';

    const EmailCartAdmin = {
        init: function() {
            this.bindEvents();
            console.log('EmailCartAdmin initialized with:', wcEmailCart);
        },

        bindEvents: function() {
            $(document).on('click', '.send-reminder-btn', this.handleReminderSend);
            this.initializeTestEmail();
        },

        initializeTestEmail: function() {
            $('#send-test-email').on('click', function(e) {
                e.preventDefault();
                const testEmail = $('#test-email').val();
                
                if (!testEmail) {
                    alert('Please enter a test email address');
                    return;
                }

                $(this).prop('disabled', true).text('Sending...');

                $.ajax({
                    url: wcEmailCart.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_send_test_reminder',
                        email: testEmail,
                        _ajax_nonce: wcEmailCart.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Test email sent successfully!');
                        } else {
                            alert(response.data.message || 'Failed to send test email');
                        }
                    },
                    error: function() {
                        alert('Failed to send test email');
                    },
                    complete: function() {
                        $('#send-test-email').prop('disabled', false).text('Send Test Email');
                    }
                });
            });
        },

        // ... rest of existing code ...
    };

    $(document).ready(function() {
        EmailCartAdmin.init();
    });

})(jQuery);
