<?php

namespace Cgit\SecurityTool\Tools;

use Cgit\SecurityTool\Tool;

/**
 * Disable various default WP features and provide warnings about those that
 * cannot be changed here.
 */
class Feature extends Tool
{
    /**
     * Default options
     *
     * @var array
     */
    protected $defaults = [
        'disable_author_archives' => true,
        'disable_author_names' => true,
        'disable_file_mods' => true,
        'default_table_prefix_warning' => true,
        'default_user_warning' => true,
        'default_user_prevent' => true,
    ];

    /**
     * Constructor
     *
     * Call the parent constructor to assign default and custom values to the
     * array of active options.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Disable author archives
     *
     * Author archives include the author's real username in the query string or
     * URL. This completely disables all author archives and returns a 404
     * response for any author query.
     *
     * @return void
     */
    protected function disableAuthorArchives()
    {
        // Return 404 for all author queries
        add_action('wp', function($wp) {
            if (
                count($wp->query_vars) == 1 &&
                (
                    array_key_exists('author', $wp->query_vars) ||
                    array_key_exists('author_name', $wp->query_vars)
                )
            ) {
                global $wp_query;

                $wp_query->set_404();
                status_header(404);
            }
        });

        // Never return author URLs
        add_filter('author_link', function($link) {
            return get_bloginfo('url');
        }, 10, 1);

        // Prevent the_author_posts_link() from returning a link
        add_filter('the_author_posts_link', function($link) {
            return strip_tags($link);
        });
    }

    /**
     * Disable author names
     *
     * If no display name is set, the author's real username is used instead in
     * links, archives, and XML feeds. This replaces the author's display name
     * with an anonymous string if the display name is the same as the login
     * name.
     *
     * @return void
     */
    protected function disableAuthorNames()
    {
        add_filter('the_author', function($name) {
            $user = get_user_by('login', $name);

            if (!$user) {
                return $name;
            }

            return 'anonymous';
        });
    }

    /**
     * Disable file modifications
     *
     * Prevents access to the theme editor in the WordPress dashboard to stop
     * anyone editing the theme files from within WordPress. Also prevents
     * automatic updates and other file modifications from within WordPress.
     *
     * @return void
     */
    protected function disableFileMods()
    {
        $constants = [
            'DISALLOW_FILE_EDIT' => true,
            'DISALLOW_FILE_MODS' => true,
            'WP_AUTO_UPDATE_CORE' => false,
        ];
        $missing = [];

        foreach ($constants as $key => $value) {
            if (!defined($key)) {
                $str_value = $value ? 'true' : 'false';
                $missing[] = "<code>$key</code>";

                add_action('admin_init', function() use ($key, $value) {
                    define($key, $value);
                });
            }
        }

        if ($missing) {
            self::displayWarning('For security reasons, please define the'
                . ' following constants in <code>wp-config.php</code>: '
                . implode(', ', $missing) . '.');
        }
    }

    /**
     * Default table prefix warning
     *
     * @return void
     */
    protected function defaultTablePrefixWarning()
    {
        global $table_prefix;

        if ($table_prefix == 'wp_') {
            self::displayWarning('For security reasons, you should change the'
                . ' database table prefix in <code>wp-config.php</code>. You'
                . ' will need to update your database after you have done'
                . ' this.');
        }
    }

    /**
     * Default user warning
     *
     * If the default administrative username "admin" exists, site
     * administrators will receive an stern rebuke.
     *
     * @return void
     */
    protected function defaultUserWarning()
    {
        if (username_exists('admin')) {
            self::displayWarning('For security reasons, you should delete, or'
                . ' change the username of, the default <code>admin</code> user'
                . ' account. &#x1f620;');
        }
    }

    /**
     * Default admin user prevention
     *
     * Prevent a user account with the username "admin" from being created for
     * this site.
     *
     * @return void
     */
    protected function defaultUserPrevent()
    {
        $admin = 'admin';
        $message = '<strong>Error:</strong> For security reasons, you cannot'
            . ' register a user called <code>' . $admin . '</code>.';
        $id = 'cgit_security_default_user_prevent';

        // Prevent "admin" from being created using wp_insert_user(). There is
        // no elegant error handling here, so this simply dies on error.
        add_filter('pre_user_login', function($login) use ($admin, $message) {
            if ($login == $admin) {
                wp_die($message);
            }

            return $login;
        });

        // Prevent "admin" from being created from the WordPress dashboard,
        // with a friendly error message.
        add_action(
            'user_profile_update_errors',
            function($errors, $update, $user) use ($admin, $id, $message) {
                if ($user->user_login == $admin) {
                    $errors->add($id, $message);
                }
            },
            10,
            3
        );

        // Prevent "admin" from being created from the WordPress login screen
        // (when user registrations are permitted by the site settings) with a
        // friendly error message.
        add_filter(
            'registration_errors',
            function($errors, $login, $email) use ($admin, $id, $message) {
                if ($login == $admin) {
                    $errors->add($id, $message);
                }

                return $errors;
            },
            10,
            3
        );
    }
}
