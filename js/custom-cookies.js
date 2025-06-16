jQuery(document).ready(function($) {
    'use strict';

    // Handle adding new cookie
    $('#add-cookie-form').on('submit', function(e) {
        e.preventDefault();

        const $form = $(this);
        const key = $('#cookie_key').val().trim();
        const description = $('#cookie_description').val().trim();

        if (!key) {
            alert('Please enter a cookie key');
            return;
        }

        // Validate cookie key format (letters, numbers, underscores only)
        if (!/^[a-zA-Z0-9_]+$/.test(key)) {
            alert('Cookie key can only contain letters, numbers, and underscores');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cutm_add_cookie',
                nonce: $('#cutm_add_cookie_nonce').val(),
                cookie_key: key,
                cookie_description: description
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to show new cookie
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to add cookie');
                }
            },
            error: function() {
                alert('Failed to add cookie. Please try again.');
            }
        });
    });

    // Handle saving cookie value
    $('.save-cookie-value').on('click', function() {
        const $btn = $(this);
        const key = $btn.data('cookie-key');
        const value = $btn.prev('.cookie-value-input').val().trim();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cutm_update_cookie_value',
                nonce: $('#cutm_add_cookie_nonce').val(),
                cookie_key: key,
                cookie_value: value
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const $msg = $('<span class="success-msg" style="color: green; margin-left: 10px;">✓ Saved</span>');
                    $btn.after($msg);
                    setTimeout(() => $msg.fadeOut('slow', function() { $(this).remove(); }), 2000);
                } else {
                    alert(response.data.message || 'Failed to save cookie value');
                }
            },
            error: function() {
                alert('Failed to save cookie value. Please try again.');
            }
        });
    });

    // Handle copying WPForms value
    $(document).on('click', '.copy-value', async function() {
        const $btn = $(this);
        const value = $btn.data('value');
        
        try {
            // Try using modern clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(value);
            } else {
                // Fallback to older method
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(value).select();
                document.execCommand('copy');
                $temp.remove();
            }
            
            // Show success message
            const $msg = $('<span class="success-msg" style="color: green; margin-left: 10px;">✓ Copied</span>');
            $btn.after($msg);
            setTimeout(() => $msg.fadeOut('slow', function() { $(this).remove(); }), 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
            alert('Failed to copy value. Please try again.');
        }
    });

    // Handle deleting cookie
    $('.delete-cookie').on('click', function() {
        if (!confirm('Are you sure you want to delete this cookie?')) {
            return;
        }

        const $btn = $(this);
        const key = $btn.data('cookie-key');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cutm_delete_cookie',
                nonce: $('#cutm_add_cookie_nonce').val(),
                cookie_key: key
            },
            success: function(response) {
                if (response.success) {
                    // Remove the row from the table
                    $btn.closest('tr').fadeOut('slow', function() {
                        $(this).remove();
                        // If no more cookies, show "no cookies" message
                        if ($('.wp-list-table tbody tr').length === 0) {
                            $('.wp-list-table').replaceWith(
                                '<p>' + cutm_cookies.no_cookies_text + '</p>'
                            );
                        }
                    });
                } else {
                    alert(response.data.message || 'Failed to delete cookie');
                }
            },
            error: function() {
                alert('Failed to delete cookie. Please try again.');
            }
        });
    });
});
