(function($) {
    'use strict';

    $(document).ready(function() {
        const $form = $('#utm-builder-form');
        const $pageSelect = $('#page_select');
        const $baseUrl = $('#base_url');
        const $generateBtn = $('#generate_url');
        const $finalUrl = $('#final_url');
        const $copyBtn = $('#copy_url');

        // Handle page selection
        $pageSelect.on('change', function() {
            const $selected = $(this).find('option:selected');
            if ($selected.val()) {
                // Use the relative URL from the data attribute
                $baseUrl.val($selected.data('relative'));
            } else {
                $baseUrl.val('');
            }
        });

        // Generate URL
        $generateBtn.on('click', function() {
            let baseUrl = $baseUrl.val().trim();
            
            // Validate base URL
            if (!baseUrl) {
                alert('Please enter a base URL');
                return;
            }

            // Ensure base URL starts with /
            if (!baseUrl.startsWith('/')) {
                baseUrl = '/' + baseUrl;
            }

            // Create URL object using home_url + relative path
            const url = new URL(cutm_ajax.home_url + baseUrl);

            // Add parameters
            let hasParams = false;
            for (let i = 1; i <= 5; i++) {
                const key = $(`[name="param_key_${i}"]`).val().trim();
                const value = $(`[name="param_value_${i}"]`).val().trim();

                if (key && value) {
                    url.searchParams.append(key, value);
                    hasParams = true;
                }
            }

            // Update result
            if (hasParams) {
                $finalUrl.val(url.toString());
                $copyBtn.prop('disabled', false);
            } else {
                alert('Please add at least one parameter');
            }
        });

        // Copy to clipboard
        $copyBtn.on('click', function() {
            const urlText = $finalUrl.val();
            if (!urlText) {
                return;
            }

            // Use modern clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(urlText).then(() => {
                    showCopySuccess();
                }).catch(() => {
                    // Fallback to textarea method if clipboard API fails
                    fallbackCopyToClipboard(urlText);
                });
            } else {
                // Use fallback for non-secure contexts
                fallbackCopyToClipboard(urlText);
            }
        });

        // Fallback copy method
        function fallbackCopyToClipboard(text) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                showCopySuccess();
            } catch (err) {
                console.error('Failed to copy URL:', err);
                alert('Failed to copy URL to clipboard');
            }

            $temp.remove();
        }

        // Show success message
        function showCopySuccess() {
            const $btn = $copyBtn;
            const originalText = $btn.text();
            
            $btn.text('Copied!').prop('disabled', true);
            
            setTimeout(() => {
                $btn.text(originalText).prop('disabled', false);
            }, 2000);
        }

        // Initialize
        $copyBtn.prop('disabled', true);
    });
})(jQuery);
