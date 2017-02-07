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
        'automatic_update_warning' => true,
        'disable_author_archives' => true,
        'disable_author_names' => true,
        'disable_file_mods' => false,
        'disable_file_edit' => true,
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
     * Prevents modifications to theme, plugin and core files. Also prevents
     * automatic from within WordPress.
     *
     * @return void
     */
    protected function disableFileMods()
    {
        $key = 'DISALLOW_FILE_MODS';
        $default = true;

        if (!defined($key)) {
            add_action(
                'admin_init',
                function () use ($key, $default) {
                    define($key, $default);
                }
            );

            return;
        }

        self::displayWarning(
            'For security reasons, please define the following constants in
            <code>wp-config.php</code>: ' . $key . '.'
        );
    }

    /**
     * Disable file editor
     *
     * Prevents access to the theme & plugin editor in the WordPress dashboard
     * to stop anyone editing the theme or plugin files from within WordPress.
     *
     * @return void
     */
    protected function disableFileEdit()
    {
        $key = 'DISALLOW_FILE_EDIT';
        $default = true;

        if (!defined($key)) {
            add_action(
                'admin_init',
                function () use ($key, $default) {
                    define($key, $default);
                }
            );

            self::displayWarning(
                'For security reasons, please define the following constants in
                <code>wp-config.php</code>: ' . $key . '.'
            );
        }
    }

    /**
     * Automatic update warning
     *
     * Warns the administrator if automatic updates are not set to 'minor'.
     *
     * @return void
     */
    protected function automaticUpdateWarning()
    {
        // Check if DISALLOW_FILE_MODS is preventing automatic updates.
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) {
            self::displayWarning(
                'Automatic updates are disabled due to the <code>'
                .'DISALLOW_FILE_MODS</code> constant being set to <code>true'
                .'</code>. WordPress is currently unable to apply automatic '
                .'security updates'
            );

            return;
        }

        if (defined('WP_AUTO_UPDATE_CORE')) {
            if (WP_AUTO_UPDATE_CORE === false) {
                // Check if automatic updates are disabled.
                self::displayWarning(
                    'WordPress automatic updates have been disabled due to '
                    .'the <code>WP_AUTO_UPDATE_CORE</code> constant being set '
                    .'to <code>false</code>. For security reasons, please set '
                    .'<code>WP_AUTO_UPDATE_CORE</code> to <code>\'minor\''
                    .'</code>.'
                );
            } elseif (WP_AUTO_UPDATE_CORE === true) {
                // Check if automatic updates include major versions.
                self::displayWarning(
                    'WordPress automatic updates have been set to include major'
                    .' & development updates due to the <code>'
                    .'WP_AUTO_UPDATE_CORE</code> constant being set to <code>'
                    .'true</code>. Consider changing this constant\'s value to '
                    .'<code>\'minor\'</code> to avoid site-breaking updates.'
                );
            } elseif (WP_AUTO_UPDATE_CORE != 'minor') {
                // Check if automatic updates are disabled due to a bad value.
                self::displayWarning(
                    'WordPress automatic updates have been disabled due to '
                    .'an invalid configuration value (<code>'
                    .htmlentities(WP_AUTO_UPDATE_CORE).'</code>). for the '
                    .'<code>WP_AUTO_UPDATE_CORE</code> constant.'
                    .' For security reasons, please set <code>'
                    .'WP_AUTO_UPDATE_CORE</code> to <code>\'minor\'</code>.'
                );
            }
        } else {
            // Set the default and desired update option.
            add_action(
                'admin_init',
                function () {
                    define('WP_AUTO_UPDATE_CORE', 'minor');
                }
            );
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
