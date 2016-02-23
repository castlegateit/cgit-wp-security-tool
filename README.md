# Castlegate IT WP Security Tool #

Provides some basic WordPress security enhancements:

*   Prevent exposure of usernames in author archives.
*   Prevent exposure of usernames in XML feeds.
*   Prevent PHP execution in the uploads directory.
*   Block access to `xmlrpc.php`.
*   Disable the theme editor in the dashboard.
*   Warn administrators if the default user account still exists.
*   Prevent a user with the default "admin" username from being created.
*   Log login attempts.
*   Lock repeated failed login attempts from the same IP address.

## Options ##

The options are stored and set as an associative array, with the following default values:

    $options = [
        'disable_author_archives' => true,
        'disable_author_names' => true,
        'disable_php_in_uploads' => true, // config option
        'disable_xmlrpc' => true, // config option
        'disable_theme_editor' => true,
        'default_user_warning' => true,
        'default_user_prevent' => true,
        'login_log' => true,
        'login_lock' => true,
        'login_max_attempts' => 5,
        'login_retry_interval' => 60, // 60 seconds
        'login_lock_duration' => 60, // 60 seconds
    ];

By default, all security settings are enabled. If you really want to disable something (e.g. to allow author archives), you can edit the options as follows:

    $tool = Cgit\SecurityTool::getInstance();

    // Change multiple options
    $tool->set([
        'disable_author_archives' => false,
        'disable_author_names' => false,
    ]);

    // Change options individually
    $tool->set('disable_author_archives', false);
    $tool->set('disable_author_names', false);

## Configuration options ##

Some options require the plugin to edit configuration files, including `.htaccess` files. The plugin will only do this on activation and deactivation. If you need to change these options, you will need to reactivate the plugin or call the `updateConfig()` method in your own plugin or theme:

    use Cgit\SecurityTool;

    // Plugin activation
    register_activation_hook($plugin_file, function() {
        $tool = SecurityTool::getInstance();
        $tool->set('disable_php_in_uploads', false);
        $tool->updateConfig();
    });

    // Theme activation
    add_action('after_switch_theme', function() {
        $tool = SecurityTool::getInstance();
        $tool->set('disable_php_in_uploads', false);
        $tool->updateConfig();
    });
