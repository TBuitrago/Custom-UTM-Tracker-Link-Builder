(function() {
    // List of UTM parameters to attach
    var utmParams = [];

    // Function to get cookie by name
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(";").shift());
    }

    // Function to add hidden fields to a form
    function addHiddenFields(form) {
        utmParams.forEach(function(param) {
            var value = getCookie(param);
            if (value) {
                // Check if hidden input already exists
                var existingInput = form.querySelector('input[name="' + param + '"]');
                if (!existingInput) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = param;
                    input.value = value;
                    
                    // Special handling for WPForms
                    if (form.classList.contains('wpforms-form')) {
                        // Create a wrapper div as WPForms expects
                        var wrapper = document.createElement('div');
                        wrapper.classList.add('wpforms-field', 'wpforms-field-hidden');
                        wrapper.style.display = 'none';
                        wrapper.appendChild(input);
                        form.appendChild(wrapper);
                    } else {
                        // For other forms, add input directly
                        form.appendChild(input);
                    }
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

        // Handle existing forms
        var forms = document.querySelectorAll('form');
        forms.forEach(addHiddenFields);

        // Watch for dynamically added forms using MutationObserver
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeName === 'FORM') {
                        addHiddenFields(node);
                    } else if (node.querySelectorAll) {
                        var forms = node.querySelectorAll('form');
                        forms.forEach(addHiddenFields);
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
})();
