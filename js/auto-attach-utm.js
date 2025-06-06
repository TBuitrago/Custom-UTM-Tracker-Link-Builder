(function() {
    // List of UTM parameters to attach
    var utmParams = [];

    // Function to get cookie by name
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length === 2) return parts.pop().split(";").shift();
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

        // For each form on the page
        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            utmParams.forEach(function(param) {
                var value = getCookie(param);
                if (value) {
                    // Check if hidden input already exists
                    if (!form.querySelector('input[name="' + param + '"]')) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = param;
                        input.value = value;
                        form.appendChild(input);
                    }
                }
            });
        });
    });
})();
