/**
 * Stores tracked URL parameters (utm_source, utm_campaign, ...) in first-party
 * cookies from the browser.
 *
 * PHP does the same on uncached requests, but pages served from a page cache
 * (LiteSpeed Cache, WP Rocket, Cloudflare...) never reach PHP — and those
 * caches usually ignore utm_* parameters on purpose — so without this script
 * the parameters would silently not be stored for most visitors.
 *
 * Configuration is printed by PHP as window.cutmTracker.
 */
(function () {
    'use strict';

    var config = window.cutmTracker;
    if (!config || !config.params || !config.params.length || !window.location.search) {
        return;
    }

    var query;
    try {
        query = new URLSearchParams(window.location.search);
    } catch (e) {
        return;
    }

    var maxAge = parseInt(config.lifetime, 10) || 2592000; // 30 days.
    var maxLength = parseInt(config.maxLength, 10) || 200;
    var secure = window.location.protocol === 'https:' ? '; Secure' : '';

    for (var i = 0; i < config.params.length; i++) {
        var name = config.params[i];

        // Names are validated server-side ([a-z0-9_]); never trust anything else.
        if (!/^[a-z0-9_]+$/.test(name)) {
            continue;
        }

        var value = query.get(name);
        if (value === null) {
            continue;
        }

        // Rough equivalent of sanitize_text_field(): no tags, no control characters.
        value = value.replace(/<[^>]*>?/g, '').replace(/[\u0000-\u001F\u007F]+/g, ' ').replace(/\s+/g, ' ').trim();
        if (value.length > maxLength) {
            value = value.slice(0, maxLength).trim();
        }
        if (!value) {
            continue;
        }

        document.cookie = name + '=' + encodeURIComponent(value) + '; Max-Age=' + maxAge + '; Path=/; SameSite=Lax' + secure;
    }
})();
