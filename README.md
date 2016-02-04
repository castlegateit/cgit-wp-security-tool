# Castlegate IT WP Security Tool #

Provides some basic WordPress security enhancements:

*   Prevent exposure of usernames in author archives.
*   Prevent exposure of usernames in XML feeds.
*   Prevent PHP execution in the uploads directory.
*   Disable the theme editor in the dashboard.
*   Warn administrators if the default user account still exists.

## Options ##

The options are stored and set as an associative array, with the following default values:

    $options = [
        'disable_author_archives' => true,
        'disable_author_names' => true,
        'disable_php_in_uploads' => true,
        'disable_theme_editor' => true,
        'default_user_warning' => true,
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
