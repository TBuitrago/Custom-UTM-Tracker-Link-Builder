(function() {
    // List of UTM parameters to attach
    var utmParams = [];

    // Function to get cookie by name
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(";").shift());
    }

    // Function to add hidden fields specifically for WPForms
    function addWPFormsFields(form) {
        // Get the form ID from the form element
        var formIdMatch = form.id.match(/wpforms-form-(\d+)/);
        if (!formIdMatch) return;
        
        var formId = formIdMatch[1];
        var lastFieldId = 0;

        // Find the last field ID in the form
        form.querySelectorAll('[data-field-id]').forEach(function(field) {
            var fieldId = parseInt(field.getAttribute('data-field-id'));
            if (!isNaN(fieldId)) {
                lastFieldId = Math.max(lastFieldId, fieldId);
            }
        });

        utmParams.forEach(function(param) {
            var value = getCookie(param);
            if (value) {
                lastFieldId++;
                
                // Check if field already exists
                var fieldId = 'wpforms-' + formId + '-field_' + lastFieldId;
                if (!document.getElementById(fieldId)) {
                    var wrapper = document.createElement('div');
                    wrapper.id = 'wpforms-' + formId + '-field_' + lastFieldId + '-container';
                    wrapper.className = 'wpforms-field wpforms-field-hidden';
                    wrapper.setAttribute('data-field-id', lastFieldId);

                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = fieldId;
                    input.name = 'wpforms[fields][' + lastFieldId + ']';
                    input.value = value;

                    wrapper.appendChild(input);
                    
                    // Insert before the submit button
                    var submit = form.querySelector('.wpforms-submit-container');
                    if (submit) {
                        form.insertBefore(wrapper, submit);
                    } else {
                        form.appendChild(wrapper);
                    }

                    console.log('UTM Tracker: Added field', param, 'with value', value, 'to form', formId);
                }
            }
        });
    }

    // On DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Get the list of UTM parameters from a global variable set by PHP
        if (typeof cutm_utm_params !== 'undefined' && Array.isArray(cutm_utm_params)) {
            utmParams = cutm_utm_params;
        }

        if (utmParams.length === 0) {
            return;
        }

        // Handle existing WPForms
        document.querySelectorAll('.wpforms-form').forEach(addWPFormsFields);

        // Watch for dynamically added forms using MutationObserver
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.classList && node.classList.contains('wpforms-form')) {
                        addWPFormsFields(node);
                    } else if (node.querySelectorAll) {
                        node.querySelectorAll('.wpforms-form').forEach(addWPFormsFields);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        console.log('UTM Tracker: Initialized with parameters:', utmParams);
    });
})();
