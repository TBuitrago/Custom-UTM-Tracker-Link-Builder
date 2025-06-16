(function($) {
    'use strict';

    function getCurrentDateTime() {
        const now = new Date();
        
        // Format date as MM-DD-YYYY
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const year = now.getFullYear();
        
        // Format time in 24-hour format (military time)
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        return `${month}-${day}-${year} ${hours}:${minutes}`;
    }

    $(document).ready(function() {
        // Handle form submissions
        $('.wpforms-form').on('wpformsBeforeSubmit', function() {
        // Find datetime hidden field by its ID
        const $dateField = $(this).find('input[id^="wpforms-"][id$="-field_16"]');
        if ($dateField.length) {
            $dateField.val(getCurrentDateTime());
        }
        });
    });
})(jQuery);
