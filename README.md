# Castlegate IT WP Security Tool #

**Requires PHP 5.5.16 or greater**

Provides some WordPress and general security enhancements:

*   Warn administrators if automatic updates are disabled.
*   Warn administrators if the site email address is not set to a desired value.
*   Warn administrators if no administrator users exist with a desired email address.
*   Prevent exposure of usernames in author archives.
*   Prevent exposure of usernames in XML feeds.
*   Prevent PHP execution in the uploads directory.
*   Block access to `xmlrpc.php`.
*   Disable theme, plugin and core file modifications (includes automatic updates).
*   Disable the theme & plugin editor in the dashboard.
*   Warn administrators if the default table prefix `wp_` is in use.
*   Warn administrators if the default user account still exists.
*   Prevent a user with the default "admin" username from being created.
*   Block access to common README and LICENSE files in document root.
*   Log login attempts.
*   Lock repeated failed login attempts from the same IP address.
*   Enables Google reCAPTCHA on `wp-login.php`.
*   Sends the `X-Frame-Origin` HTTP header.
*   Sends the `X-XSS-Protection` HTTP header.
*   Sends the `X-Content-Type-Options` HTTP header.

## Options ##

The options are stored and set as an associative array, with the following default values:

~~~ php
$options = [
    'automatic_update_warning' => true,
    'site_email_warning' => true,
    'admin_email_warning' => true,
    'disable_author_archives' => true,
    'disable_author_names' => true,
    'disable_php_in_uploads' => true, // config option
    'disable_xmlrpc' => true, // config option
    'disable_readme_files' => true,
    'disable_file_mods' => false,
    'disable_file_edit' => true,
    'default_table_prefix_warning' => true,
    'default_user_warning' => true,
    'default_user_prevent' => true,
    'login_log' => true,
    'login_lock' => true,
    'login_max_attempts' => 5,
    'login_retry_interval' => 60, // 60 seconds
    'login_lock_duration' => 60, // 60 seconds
    'enable_google_recaptcha' => true,
    'enable_frame_options' => true,
    'enable_xss_protection' => true,
    'enable_no_sniff' => true,
];
~~~

By default, all security settings are enabled. If you really want to disable something (e.g. to allow author archives), you can edit the options as follows:

~~~ php
add_filter(
    'cgit_security_tool_options',
    function ($options) {
        $options['disable_author_archives'] => false,
        $options['disable_author_names'] => false,

        return $options;
    }
);
~~~

## Configuration options ##

Some options require the plugin to edit configuration files, including `.htaccess` files. The plugin will only do this on activation and deactivation. If you need to change these options, you will need to reactivate the plugin or use the `FileTool` class directly:

~~~ php
use Cgit\SecurityTool;

$tool = new FileTool();

$tool->set('disable_php_in_uploads', false);
$tool->update();
~~~

## Security enhancements ##

### Automatic update warnings ###

Option: `automatic_update_warning` - Default: `true`

This option warns administrators if automatic updates are disabled or the configuration constant contains an invalid value, preventing updates from running. A warning will also be displayed if automatic updates are configured to include development and major updates, which may include site-breaking changes.

It's recommended to use the default automatic update settings, which can be manually defined using the constant below. The `'minor'` configuration value offers the best balance of security updates without risking major changes which may break the website.

~~~ php
define('WP_AUTO_UPDATE_CORE', 'minor');
~~~

### Site email warning ###

Option: `site_email_warning` - Default: `true`

This option warns the administrators if the site's email address does not match a predefined value. It allows developers to ensure important emails such as update notifications are sent to a preferred location.

The desired email address can be set by defining the `CGIT_SEC_SITE_EMAIL` constant as shown below:

~~~ php
define('CGIT_SEC_SITE_EMAIL', 'example@domain.com');
~~~

### Admin email warning ###

Option: `admin_email_warning` - Default: `true`

This option warns administrators if the none of the site's admin users have an email address matching a predefined value. It allows developers to ensure that at least one user account is accessible if a feature such as two factor authentication is enabled.

The desired administrator email address can be set by defining the `CGIT_SEC_ADMIN_EMAIL` constant as shown below:

~~~ php
define('CGIT_SEC_ADMIN_EMAIL', 'example@domain.com');
~~~


### Disable author archives ###

Option: `disable_author_archives` - Default: `true` 

This option disables all author archives and returns a HTTP `404` response for any author archive page. Out of the box, WordPress will generate per-user archives using the following URL structure:

http://www.example.co.uk/author/administrator/

More often than not, these are not required and provide an endpoint for username exposure. If the site's users do not have their display name set, the username is exposed in the URL.

### Disable author names ###

Option: `disable_author_names` - Default: `true` 

If a user's display name is not set, the author's username is used instead. This is shown in links, archives, XML feeds and results in username exposure. 

This option prevents username exposure by replacing the author's display name with an anonymous string, only if the display name is equal to the login name.

### Disable PHP in uploads ###

Option: `disable_php_in_uploads` - Default: `true` 

Any files uploaded to the `uploads` directory are publicly accessible and therefore executable. This poses a security risk if a PHP file is uploaded.

This option writes to `.htaccess` to disable execution of any PHP files within the `uploads` directory.

### Disable XMLRPC ###

Option: `disable_xmlrpc` - Default: `true` 

The XMLRPC endpoint becomes a target for brute force login attempts. If the feature is not it use it should be disabled.

This option blocks any access to `xmlrpc.php` using `.htaccess`.

### Disable README files ###

Option: `disable_readme_files` - Default: `true` 

WordPress ships with `license.txt` and developers often include a `README.md` file with their projects. License files need not be publicly accessible and any readme file can contain potentially sensitive information.

This option blocks access to `license.txt` and `README.md` using `.htaccess`

### Disable file modifications ###

Option: `disable_file_mods` - Default: `false` 

WordPress core files, themes and plugins can be manipulated via the WordPress. When such features are not required they should be disabled to prevent any malicous user from escalating their privileges and gaining file system access.

This option disables editing of theme files, plugin files if the following constant is defined and set to `true`. 

'Note:' This option will also disable automatic updates.

~~~ php
define('DISALLOW_FILE_MODS', true);
~~~

### Disable file edits ###

Option: `disable_file_edit` - Default: `true` 

WordPress themes and plugins can be manipulated via the WordPress admin. When such features are not required they should be disabled to prevent any malicous user from escalating their privileges and gaining file system access.

This option disables editing of theme files, plugin files if the following constant is defined and set to `true`

~~~ php
define('DISALLOW_FILE_EDIT', true);
~~~

### Default table prefix warning ###

Option: `default_table_prefix_warning` - Default: `true` 

WordPress uses a default database table name prefix of `wp_`. Many simple SQL injection attacks assume the use of the default value. Changing the prefix helps protect against some basic attacks.

This option displays a warning to administrators if the default prefix has been used for the WordPress installation.

### Default user warning ###

Option: `default_user_warning` - Default: `true` 

WordPress installs with a default administrator account named `admin`. Many brute force attacks assume this username exists and its presence reduces the effort required to guess login credentials.

The `admin` account should be deleted or renamed. This option warns administrators if the username is still present.

### Default user prevention ###

Option: `default_user_prevent` - Default: `true` 

This option prevents the creation of a user with the username `admin`

### Login log ###

Option: `login_log` - Default: `true` 

This option enables logging of any login attempt, successful or otherwise.

### Login lock ###

Option: `login_lock` - Default: `true` 

Most WordPress websites receive a steady stream of brute force login attempts.

This option locks `wp-login.php` when a number of failed login attempts is exceeded, preventing further attempts for a period of time. 

The default values are suitable for most websites and are designed to slow down brute force attacks, without erroneously blocking genuine users for prolonged periods of time. 

### Login maximum attempts ###

Option: `login_max_attempts` - Default: `5` 

This option sets the maximum number of failed login attempts before `wp-login.php` is locked.

### Login retry interval ###

Option: `login_retry_interval` - Default: `60` 

This option sets the time period in which a maximum number of failed logins attempts can occur before `wp-login.php` is locked.

### Login lock duration ###

Option: `login_lock_duration` - Default: `60` 

This option sets the duration of a login lock.

### Enable Google reCAPTCHA ###

Option: `enable_google_recaptcha` - Default: `true` 

This option displays Google reCAPTCHA on `wp-login.php` to prevent automated login attempts.

A Google reCAPTCHA API key is required and the following constants must be set:

~~~ php
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');
~~~

### Enable frame options header ###

Option: `enable_frame_options` - Default: `true` 

This option provides additional cross-site scripting protection by sending the `X-Frame-Options` HTTP header in all requests. The `X-Frame-Options` header limits the rendering of pages to HTML frames which exist on the same domain name.

Sends: `X-Frame-Options: SAMEORIGIN`

### Enable XSS protection header ###

Option: `enable_xss_protection` - Default: `true` 

This option sends the `X-XSS-Protection` header in all requests to help block cross-site scripting attempts when detected by a supported browser. Support is included in IE8+ & Webkit.

Sends: `X-XSS-Protection: 1; mode=block;`

### Enable no sniff header ###

Option: `enable_no_sniff` - Default: `true` 

The options sends the `X-Content-Type-Options` header set to a value of `nosniff` which disables content type sniffing in browsers which support it.

This prevents browsers from attempting to determine a file's mime type automatically, and forces rendering in the mime type provided by the server.

Sends: `X-Content-Type-Options: nosniff`


