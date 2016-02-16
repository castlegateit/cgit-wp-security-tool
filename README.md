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
        'disable_php_in_uploads' => true,
        'disable_xmlrpc' => true,
        'disable_theme_editor' => true,
        'default_user_warning' => true,
        'default_user_prevent' => true,
        'login_log' => true,
        'login_lock' => true,
        'login_max_attempts' => 3,
        'login_retry_interval' => 300, // 300 seconds = 5 minutes
        'login_lock_duration' => 3600, // 3600 seconds = 1 hour
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
