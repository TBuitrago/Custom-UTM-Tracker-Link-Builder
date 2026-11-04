/**
 * Writes the submission date/time (visitor's local time, MM-DD-YYYY HH:mm)
 * into WPForms fields right before the form is submitted.
 *
 * Targets:
 * - Hidden fields whose field ID matches the `cutm_wpforms_datetime_field_id`
 *   filter (default 16). Only hidden inputs are touched, so a visible field
 *   that happens to have the same ID in another form is never overwritten.
 * - Any field whose "CSS Classes" setting in WPForms includes `cutm-datetime`.
 *
 * Configuration is printed by PHP as window.cutmForms.
 */
(function () {
    'use strict';

    var config = window.cutmForms || {};
    var fieldId = /^\d+$/.test(String(config.datetimeFieldId || '')) ? String(config.datetimeFieldId) : '';

    function pad(number) {
        return ('0' + number).slice(-2);
    }

    function getCurrentDateTime() {
        var now = new Date();

        // MM-DD-YYYY HH:mm (24-hour clock)
        return pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + '-' + now.getFullYear() +
            ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    }

    function fillDateTime(form) {
        if (!form || !form.classList || !form.classList.contains('wpforms-form')) {
            return;
        }

        var selectors = ['.cutm-datetime input'];
        if (fieldId) {
            selectors.push('input[type="hidden"][id^="wpforms-"][id$="-field_' + fieldId + '"]');
        }

        var inputs = form.querySelectorAll(selectors.join(','));
        var value = getCurrentDateTime();

        for (var i = 0; i < inputs.length; i++) {
            inputs[i].value = value;
        }
    }

    // Native submit (capture phase runs before WPForms' own handlers).
    document.addEventListener('submit', function (event) {
        fillDateTime(event.target);
    }, true);

    // WPForms event, also fired for submissions started from script (CAPTCHA callbacks, etc.).
    if (window.jQuery) {
        window.jQuery(document).on('wpformsBeforeFormSubmit', '.wpforms-form', function () {
            fillDateTime(this);
        });
    }
})();
