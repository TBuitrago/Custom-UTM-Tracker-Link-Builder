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
            
            // If no URL is entered, use the site URL
            if (!baseUrl) {
                baseUrl = cutm_ajax.home_url;
            } else if (baseUrl.startsWith('http://') || baseUrl.startsWith('https://')) {
                // If full URL is entered, use it as is
                baseUrl = baseUrl;
            } else {
                // If relative URL is entered, append to home URL
                if (!baseUrl.startsWith('/')) {
                    baseUrl = '/' + baseUrl;
                }
                baseUrl = cutm_ajax.home_url + baseUrl;
            }

            // Create URL object
            const url = new URL(baseUrl);

            // Add parameters
            let hasParams = false;
            $('.cutm-param-inputs').each(function() {
                const key = $(this).find('.param-key').val().trim();
                const value = $(this).find('.param-value').val().trim();
                
                if (value) {
                    url.searchParams.append(key, value);
                    hasParams = true;
                }
            });

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
