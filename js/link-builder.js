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
            try {
                // Get base URL from select or input
                let baseUrl = '';
                const selectedUrl = $pageSelect.val();
                
                if (selectedUrl) {
                    // Use selected page URL
                    baseUrl = selectedUrl;
                } else {
                    // Get URL from input
                    baseUrl = $baseUrl.val().trim();
                    
                    // If no URL is entered, use home URL
                    if (!baseUrl) {
                        baseUrl = cutm_ajax.home_url;
                    } else if (!baseUrl.startsWith('http://') && !baseUrl.startsWith('https://')) {
                        // If relative URL is entered, append to home URL
                        if (!baseUrl.startsWith('/')) {
                            baseUrl = '/' + baseUrl;
                        }
                        baseUrl = cutm_ajax.home_url + baseUrl;
                    }
                }

                // Create URL object
                const url = new URL(baseUrl);

                // Add parameters
                let hasParams = false;
                $('.param-value').each(function() {
                    const value = $(this).val();
                    if (value && value.trim()) {
                        const key = $(this).closest('.cutm-param-inputs').find('.param-key').val();
                        if (key) {
                            url.searchParams.append(key.trim(), value.trim());
                            hasParams = true;
                        }
                    }
                });

                // Update result
                if (hasParams) {
                    $finalUrl.val(url.toString());
                    $copyBtn.prop('disabled', false);
                } else {
                    alert('Please add at least one parameter');
                }
            } catch (error) {
                console.error('Error generating URL:', error);
                alert('An error occurred while generating the URL. Please check the console for details.');
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

        // Save URL to history
        $('#save_url').on('click', function() {
            const url = $finalUrl.val();
            if (!url) {
                alert('No URL to save');
                return;
            }

            $.ajax({
                url: cutm_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cutm_save_link_history',
                    nonce: cutm_ajax.nonce,
                    url: url
                },
                success: function(response) {
                    if (response.success) {
                        alert('URL saved to history');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to save URL');
                    }
                },
                error: function() {
                    alert('Failed to save URL. Please try again.');
                }
            });
        });

        // Copy link from history
        $(document).on('click', '.copy-history-url', function() {
            const url = $(this).data('url');
            if (!url) return;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Copied to clipboard');
                }).catch(() => {
                    fallbackCopyToClipboard(url);
                });
            } else {
                fallbackCopyToClipboard(url);
            }
        });

        // Delete link from history
        $(document).on('click', '.delete-history-url', function() {
            if (!confirm('Are you sure you want to delete this link?')) {
                return;
            }

            const timestamp = $(this).data('timestamp');
            if (!timestamp) return;

            $.ajax({
                url: cutm_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'cutm_delete_link_history',
                    nonce: cutm_ajax.nonce,
                    timestamp: timestamp
                },
                success: function(response) {
                    if (response.success) {
                        alert('Link deleted');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Failed to delete link');
                    }
                },
                error: function() {
                    alert('Failed to delete link. Please try again.');
                }
            });
        });

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
