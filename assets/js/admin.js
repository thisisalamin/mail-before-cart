jQuery(document).ready(function($) {
    // Tab switching functionality
    $('.tab-button').on('click', function() {
        $('.tab-button').removeClass('active border-indigo-500 text-indigo-600').addClass('border-transparent text-gray-500');
        $(this).addClass('active border-indigo-500 text-indigo-600').removeClass('border-transparent text-gray-500');
        
        $('.tab-content').addClass('hidden');
        $('#tab-' + $(this).data('tab')).removeClass('hidden');
    });
});
