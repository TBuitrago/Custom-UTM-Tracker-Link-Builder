# Custom UTM Tracker & Link Builder

A WordPress plugin that captures specific URL parameters (e.g., utm_source, utm_campaign, etc.) and stores them in cookies for up to 30 days. It also includes an admin interface for generating shareable links by appending up to 5 custom parameters to any internal URL.

## Features

- **Parameter Tracking**: Automatically captures and stores up to 6 UTM parameters in cookies
- **Cookie Persistence**: Parameters are stored for 30 days and persist across sessions
- **Automatic Appending**: Stored parameters are automatically appended to internal navigation when missing
- **Clean URL Redirects**: Users are redirected to clean URLs that include saved parameters
- **Admin Link Builder**: Easy-to-use interface for generating custom parameterized URLs
- **WordPress Integration**: Seamlessly integrates with WordPress admin under Tools menu

## Tracked Parameters

The plugin tracks these URL parameters by default:
- `utm_source`
- `utm_campaign`
- `utm_medium`
- `utm_term`
- `utm_adgroup`
- `utm_content`

## Installation

1. Upload the `custom-utm-tracker` folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Tools > UTM Link Builder** to start creating custom links

## Usage

### Automatic Parameter Tracking

The plugin automatically works in the background:

1. When a user visits your site with UTM parameters (e.g., `yoursite.com/page?utm_source=google&utm_campaign=summer2024`)
2. These parameters are stored in cookies for 30 days
3. When the user navigates to other pages without UTM parameters, they are automatically appended from the stored cookies
4. Users are redirected to clean URLs that include the saved parameters

### Link Builder Admin Interface

1. Go to **WordPress Admin > Tools > UTM Link Builder**
2. Select a page from the dropdown or enter a custom URL path
3. Add up to 5 custom parameters with their values
4. Click "Generate URL" to create the final link
5. Copy the generated URL to your clipboard

### Example Usage

**Original URL**: `https://yoursite.com/services`

**With UTM Parameters**: `https://yoursite.com/services?utm_source=facebook&utm_campaign=spring2024&utm_medium=social`

## File Structure

```
custom-utm-tracker/
├── custom-utm-tracker.php      # Main plugin file
├── js/
│   └── link-builder.js         # JavaScript for admin interface
├── admin/
│   └── link-builder-page.php   # Admin page template
└── README.md                   # This file
```

## Technical Details

### Cookie Management
- Cookies are set with a 30-day expiration
- Cookie path is set to '/' for site-wide availability
- All values are sanitized using `sanitize_text_field()`

### Security Features
- Nonce verification for admin forms
- Input sanitization and validation
- Proper WordPress hooks and actions
- No database storage (cookies only)

### Compatibility
- Works with existing UTM/GA tracking
- Compatible with GA4 and Google Tag Manager
- No conflicts with other tracking plugins

## Requirements

- WordPress 4.0 or higher
- PHP 7.0 or higher

## Support

This plugin is designed to be lightweight and conflict-free. It uses standard WordPress APIs and follows WordPress coding standards.

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- Initial release
- UTM parameter tracking and storage
- Admin link builder interface
- Automatic parameter appending
- Clean URL redirects
