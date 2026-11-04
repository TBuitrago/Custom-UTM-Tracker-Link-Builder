# Custom UTM Tracker & Link Builder

A WordPress plugin that captures specific URL parameters (e.g., utm_source, utm_campaign, etc.) and stores them in first-party cookies for up to 30 days, passes them to WPForms submissions, and includes an admin interface for generating shareable links by appending custom parameters to any internal URL.

## Features

- **Parameter Tracking**: Captures the parameters you define (utm_source, utm_campaign, gclid, ...) and stores each one in a first-party cookie for 30 days
- **Page-cache compatible**: Parameters are captured in the browser as well as in PHP, so tracking keeps working on sites using LiteSpeed Cache, WP Rocket, Cloudflare, etc. (those caches serve pages without running PHP and usually ignore `utm_*` parameters)
- **WPForms attribution**: Hidden fields with a `{query_var key="..."}` default value receive the stored cookie value on submission, even if the visitor landed on another page first
- **Admin Link Builder**: Easy-to-use interface for generating custom parameterized URLs
- **Link History**: Save generated URLs to history, copy, and delete them from the admin interface
- **Custom Cookies Management**: Admin interface to add, copy, and delete tracked parameters
- **DateTime Field Population**: Populates a hidden WPForms field with the submission date/time
- **Automatic updates from GitHub**: Every site running the plugin updates itself when a new release is published
- **WordPress Integration**: Seamlessly integrates with WordPress admin under the Tools menu

## Installation

1. Download `custom-utm-tracker.zip` from the [latest release](https://github.com/TBuitrago/Custom-UTM-Tracker-Link-Builder/releases/latest)
2. In WordPress go to **Plugins > Add New > Upload Plugin**, upload the ZIP and activate it
3. Navigate to **Tools > Custom Cookies** to define the parameters to track
4. Navigate to **Tools > UTM Link Builder** to start creating custom links

From then on the plugin keeps itself up to date (see [Automatic updates](#automatic-updates)).

## Usage

### Automatic Parameter Tracking

1. Add the parameters to track under **Tools > Custom Cookies** (e.g. `utm_source`, `utm_medium`, `utm_campaign`)
2. When a visitor arrives with those parameters (e.g., `yoursite.com/page?utm_source=google&utm_campaign=summer2024`), each value is stored in a cookie with the same name for 30 days
3. The URL is never modified and the visitor is never redirected
4. A new visit with a different value overwrites the stored one (last touch)

Cookies are first-party, `SameSite=Lax`, `Secure` on HTTPS and readable from JavaScript (so Google Tag Manager can use them). Values are sanitized and limited to 200 characters.

### WPForms

1. Copy the **WPForms Value** of a parameter from **Tools > Custom Cookies** (e.g. `{query_var key="utm_source"}`)
2. Add a **Hidden** field to the form and paste it as its **Default Value**
3. On submission the field receives the value stored in the visitor's cookie. If there is no cookie, the value from the URL (if any) is kept

The value is saved in the entry and sent to notifications and integrations (HubSpot, Zapier, ...).

#### Submission date/time

The submission date/time (visitor's local time, `MM-DD-YYYY HH:mm`) is written into:

- the **hidden** field with ID `16` in any form (change the ID with the `cutm_wpforms_datetime_field_id` filter, or return `''` to disable it), and
- any field whose **CSS Classes** setting contains `cutm-datetime`.

### Link Builder Admin Interface

1. Go to **WordPress Admin > Tools > UTM Link Builder**
2. Select a page from the dropdown or enter a custom URL path (selecting a page fills in its path, which can then be edited)
3. Fill in the values of the parameters
4. Click "Generate URL" to create the final link
5. Copy the generated URL to your clipboard
6. Save generated URLs to history for later use (the 200 most recent are kept)
7. Copy or delete URLs from the history list

### Custom Cookies Admin Interface

1. Go to **WordPress Admin > Tools > Custom Cookies**
2. Add new parameters with a key (lowercase letters, numbers and underscores) and a description
3. Copy the WPForms value of each parameter
4. Delete parameters as needed

## Automatic updates

The plugin updates itself from the GitHub releases of this repository through the native WordPress update system:

- The header `Update URI: https://github.com/TBuitrago/Custom-UTM-Tracker-Link-Builder` tells WordPress (5.8+) to never look for this plugin on WordPress.org, so a plugin with the same name there can never replace it.
- WordPress checks for updates twice a day; the plugin answers with the latest GitHub release (cached for 4 hours, *Dashboard > Updates > Check again* forces a fresh check).
- Auto-updates are **enabled by default** on every site, so WordPress installs new releases in the background (WP-Cron). Since WordPress 6.6 an auto-update that causes a fatal error is rolled back automatically.
- The plugin keeps its folder name and stays active after updating, whatever folder it was installed in.

Auto-updates need WordPress to be able to write files (no `DISALLOW_FILE_MODS`, no `AUTOMATIC_UPDATER_DISABLED`, file system access without FTP credentials), and WordPress skips them when `wp-content/plugins` or a folder above it is under version control (Git/SVN).

### Publishing an update

1. Bump the version in **both** places in `custom-utm-tracker.php`: the `Version:` header and the `CUTM_VERSION` constant
2. Add a changelog entry below
3. Merge/push to `main`

The [Release workflow](.github/workflows/release.yml) then lints the code, builds `custom-utm-tracker.zip` and publishes a GitHub release tagged with the version (e.g. `2.1.0`, without a `v` prefix). Pushes that don't change the version publish nothing; the workflow refuses a version lower than the latest release. Sites pick the release up within about 12–24 hours.

### Updating sites still on 1.0.1 (one time only)

Version 1.0.1 shipped an updater that only works inside wp-admin and cannot see `v`-prefixed tags, so it never detected any release. It does detect `2.1.0`, but cannot install it in the background. On each site, once:

1. Go to **Dashboard > Updates** and click **Check again**
2. Update **Custom UTM Tracker & Link Builder** to 2.1.0

From 2.1.0 on, updates are fully automatic.

### Configuration (wp-config.php)

```php
// Stop forcing auto-updates on this site (the regular "Enable auto-updates" link then applies).
define('CUTM_DISABLE_AUTO_UPDATE', true);

// Only needed if the repository is ever made private: a fine-grained token with read access to its contents.
define('CUTM_GITHUB_TOKEN', 'github_pat_...');
```

## Filters

| Filter | Default | Description |
| --- | --- | --- |
| `cutm_cookie_lifetime` | `30 * DAY_IN_SECONDS` | Cookie lifetime in seconds |
| `cutm_cookie_max_length` | `200` | Max characters stored per value (up to 1000) |
| `cutm_wpforms_datetime_field_id` | `'16'` | ID of the hidden WPForms field that receives the date/time (`''` disables it) |
| `cutm_enable_auto_update` | `true` | Return `false` to stop forcing auto-updates |

Theme or plugin code can read a stored value with `CustomUTMTracker::get_value('utm_source')`.

## File Structure

```
custom-utm-tracker/
├── custom-utm-tracker.php        # Main plugin file
├── uninstall.php                 # Removes the plugin options on delete
├── includes/
│   └── class-cutm-updater.php    # Automatic updates from GitHub releases
├── js/
│   ├── utm-capture.js            # Captures tracked parameters in the browser
│   ├── form-datetime.js          # WPForms datetime field population
│   ├── link-builder.js           # Link builder admin page
│   └── custom-cookies.js         # Custom cookies admin page
├── admin/
│   ├── link-builder-page.php     # Admin page template for link builder
│   └── custom-cookies-page.php   # Admin page template for custom cookies
└── .github/workflows/release.yml # Publishes a release when the version changes
```

## Security Features

- Nonce and capability (`manage_options`) checks on every admin action
- Input sanitization and validation; output escaping
- Stored values limited in length, so crafted links cannot plant oversized cookies that break the site for a visitor
- Cookie names used by WordPress or PHP cannot be tracked
- Updates only from this repository's releases; the update package is checked before installing

## Requirements

- WordPress 5.8 or higher
- PHP 7.2 or higher

## License

GPL v2 or later

## Changelog

### Version 2.1.0
- New automatic updater: uses the WordPress `Update URI` mechanism, works in background auto-updates (WP-Cron), auto-updates enabled by default, keeps the install folder, caches GitHub requests
- Fixed: the previous updater never detected releases (`v`-prefixed tags), only ran inside wp-admin, returned `false` to every other `plugins_api` request (overriding other plugins' results) and hooked into the installation of every plugin
- Added: release workflow that publishes a GitHub release when the version changes on `main`
- Added: browser-side parameter capture, so tracking works on cached pages (LiteSpeed Cache, WP Rocket, Cloudflare...)
- Added: WPForms hidden fields using `{query_var key="..."}` receive the stored cookie value on submission
- Fixed: the WPForms datetime field was never filled (the script listened to an event WPForms does not fire); it now only writes into hidden fields or fields with the `cutm-datetime` class, never into visible fields
- Fixed: cookies were re-sent on every page view, which added backslashes to values containing quotes on each visit and made pages harder to cache; cookies now last 30 days from the last tagged visit
- Security: values limited to 200 characters, reserved cookie names blocked, `SameSite=Lax` and `Secure` cookie flags, removed an unused AJAX endpoint that could set arbitrary cookies
- Link history: entries saved in the same second no longer overwrite each other, limited to the 200 most recent, not autoloaded on every page, dates shown in the site timezone
- Link builder: edits made to the path after selecting a page are no longer ignored
- Descriptions containing quotes are no longer saved with backslashes
- Uninstall also cleans up multisite installs and the update cache

### Version 1.0.1
- Added link history feature to save, copy, and delete generated URLs
- Added custom cookies admin interface with add, update, copy, and delete functionality
- Fixed copy button functionality using modern clipboard API with fallback
- Fixed WPForms hidden datetime field population on form submission
- Improved event handling with delegated event listeners for dynamic elements

### Version 1.0.0
- Initial release
- UTM parameter tracking and storage
- Admin link builder interface
- Automatic parameter appending
- Clean URL redirects
