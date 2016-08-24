<?php

namespace Cgit;

class SecurityTool
{
    /**
     * Reference to the singleton instance of the class
     */
    private static $instance;

    /**
     * Default options
     */
    private $defaultOptions = [
        'disable_author_archives' => true,
        'disable_author_names' => true,
        'disable_php_in_uploads' => true,
        'disable_xmlrpc' => true,
        'disable_file_mods' => true,
        'disable_readme_files' => true,
        'default_table_prefix_warning' => true,
        'default_user_warning' => true,
        'default_user_prevent' => true,
        'login_log' => true,
        'login_lock' => true,
        'login_max_attempts' => 5,
        'login_retry_interval' => 60, // 60 seconds
        'login_lock_duration' => 60, // 60 seconds
        'enable_google_recaptcha' => true,
    ];

    /**
     * Configuration file options
     *
     * These options involve writing configuration files, so should not run on
     * every page load.
     */
    private $configOptions = [
        'disable_php_in_uploads',
        'disable_xmlrpc',
        'disable_readme_files',
    ];

    /**
     * Site options
     */
    private $options = [];

    /**
     * Configuration file indent
     */
    private $indent;

    /**
     * Constructor
     *
     * Private constructor ...
     */
    private function __construct($options = [])
    {
        $this->options = $this->defaultOptions;
        $this->indent = str_repeat(' ', 4);
        $this->set($options);
    }

    /**
     * Return the singleton instance of the class
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Set options
     */
    public function set($options, $value = null)
    {
        // If single option, convert to array
        if (is_string($options)) {
            if ($value == null) {
                return false;
            }

            $options = [
                $options => $value,
            ];
        }

        // Set options
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $this->defaultOptions)) {
                $this->options[$key] = $value;
            }
        }

        // Apply changes using actions and filters
        $this->update();
    }

    /**
     * Update actions and filters
     */
    private function update()
    {
        // Filter options
        $this->options = apply_filters(
            'cgit_security_tool_options',
            $this->options
        );

        // Run methods
        foreach ($this->options as $key => $value) {
            $method = $this->camelize($key);

            // Skip options that affect configuration files
            if (in_array($key, $this->configOptions)) {
                continue;
            }

            // Run if value is true
            if (method_exists($this, $method) && $value) {
                $this->$method();
            }
        }
    }

    /**
     * Update configuration files
     */
    public function updateConfig()
    {
        foreach ($this->configOptions as $option) {
            $method = $this->camelize($option);

            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
    }

    /**
     * Convert string to camel case
     */
    private function camelize($str)
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /**
     * Join strings to create path
     */
    private function joinPath($items)
    {
        if (!is_array($items)) {
            return false;
        }

        $path = [];
        $sub = false;

        foreach ($items as $item) {
            $item = rtrim($item, '/');

            if ($sub) {
                $item = ltrim($item, '/');
            }

            $path[] = $item;
            $sub = true;
        }

        return implode('/', $path);
    }

    /**
     * Write to .htaccess
     */
    private function writeConfig($file, $marker, $content)
    {
        require_once(ABSPATH . 'wp-admin/includes/misc.php');

        if (is_array($file)) {
            $file = $this->joinPath($file);
        }

        insert_with_markers($file, $marker, $content);
    }

    /**
     * Display warning in Dashboard
     */
    private function displayWarning($message)
    {
        if (!$this->isAdmin()) {
            return;
        }

        add_action('admin_notices', function() use ($message) {
            echo '<div class="error"><p><strong>Warning:</strong> ' . $message
                . '</p></div>';
        });
    }

    /**
     * Is current user an administrator?
     *
     * If the Tweak Tool is available, use its isAdmin() method. Otherwise,
     * check for the ability to edit users.
     */
    private function isAdmin()
    {
        if (class_exists('\Cgit\TweakTool')) {
            $tool = \Cgit\TweakTool::getInstance();
            return $tool->isAdmin();
        }

        return current_user_can('edit_users');
    }

    /**
     * Disable author archives
     *
     * Author archives include the author's real username in the query string or
     * URL. This completely disables all author archives and returns a 404
     * response for any author query.
     */
    private function disableAuthorArchives()
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
     */
    private function disableAuthorNames()
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
     * Disable PHP in the uploads directory
     *
     * Writes .htaccess to prevent PHP execution for any files within the
     * uploads directory.
     */
    private function disablePhpInUploads()
    {
        $marker = 'Security Tool: disable PHP in uploads';
        $file = [wp_upload_dir()['basedir'], '.htaccess'];
        $content = [];

        if ($this->options['disable_php_in_uploads']) {
            $content = [
                '<Files "*.php">',
                $this->indent . 'Order Deny,Allow',
                $this->indent . 'Deny from all',
                '</Files>',
            ];
        }

        $this->writeConfig($file, $marker, $content);
    }

    /**
     * Disable XML RPC
     *
     * Blocks access to xmlrpc.php using .htaccess.
     */
    private function disableXmlrpc()
    {
        $marker = 'Security Tool: disable XML RPC';
        $file = [ABSPATH, '.htaccess'];
        $content = [];

        if ($this->options['disable_xmlrpc']) {
            $content = [
                '<Files "xmlrpc.php">',
                $this->indent . 'Order Deny,Allow',
                $this->indent . 'Deny from all',
                $this->indent . 'Allow from 127.0.0.1',
                '</Files>',
            ];
        }

        $this->writeConfig($file, $marker, $content);
    }

    /**
     * Disable file modifications
     *
     * Prevents access to the theme editor in the WordPress dashboard to stop
     * anyone editing the theme files from within WordPress. Also prevents
     * automatic updates and other file modifications from within WordPress.
     */
    private function disableFileMods()
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
            $this->displayWarning('For security reasons, please define the'
                . ' following constants in <code>wp-config.php</code>: '
                . implode(', ', $missing) . '.');
        }
    }

    /**
     * Disable README files
     *
     * Block access to README and LICENSE files in the document root common to
     * WordPress installations and Git repositories.
     */
    private function disableReadmeFiles()
    {
        $marker = 'Security Tool: disable README files';
        $file = [ABSPATH, '.htaccess'];
        $content = [];

        if ($this->options['disable_readme_files']) {
            $content = [
                'RewriteEngine on',
                'RewriteBase /',
                'RewriteRule ^license.*$ - [R=404,NC,L]',
                'RewriteRule ^readme.*$ - [R=404,NC,L]',
            ];
        }

        $this->writeConfig($file, $marker, $content);
    }

    /**
     * Default table prefix warning
     */
    private function defaultTablePrefixWarning()
    {
        global $table_prefix;

        if ($table_prefix == 'wp_') {
            $this->displayWarning('For security reasons, you should change the'
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
     */
    private function defaultUserWarning()
    {
        if (username_exists('admin')) {
            $this->displayWarning('For security reasons, you should delete, or'
                . ' change the username of, the default <code>admin</code> user'
                . ' account. &#x1f620;');
        }
    }

    /**
     * Default admin user prevention
     *
     * Prevent a user account with the username "admin" from being created for
     * this site.
     */
    private function defaultUserPrevent()
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

    /**
     * Log login attempts
     *
     * Saves a record of all login attempts in the database, using the table
     * created with the plugin activation hook.
     */
    private function loginLog()
    {
        add_filter('authenticate', function($user, $name, $password) {
            global $wpdb;

            // If no username or password submitted, do not consider this a
            // real login attempt.
            if (!$name && !$password) {
                return $user;
            }

            $table = $wpdb->prefix . 'cgit_security_logins';
            $address = $_SERVER['REMOTE_ADDR'];
            $user_id = null;
            $success = 0;

            if (is_a($user, 'WP_User')) {
                $user_id = $user->ID;
                $success = 1;
            }

            $wpdb->insert($table, [
                'ip' => $address,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'date' => date('Y-m-d H:i:s'),
                'user_id' => $user_id,
                'user_name' => $name,
                'success' => $success,
            ]);

            return $user;
        }, 40, 3);
    }

    /**
     * Limit login attempts
     *
     * Repeated login attempts from the same IP are blocked for a given
     * duration.
     */
    private function loginLock()
    {
        add_filter('authenticate', function($user, $name, $password) {
            global $wpdb;

            // Lock message
            $message = 'You have exceeded the maximum number of login'
                . ' attempts. Please try again later.';

            // Maximum number of attempts, retry interval, and lock duration
            $max_attempts = $this->options['login_max_attempts'];
            $interval = $this->options['login_retry_interval'];
            $duration = $this->options['login_lock_duration'];

            // IP address and database table
            $address = $_SERVER['REMOTE_ADDR'];
            $table = $wpdb->prefix . 'cgit_security_logins';

            // List failed login attempts from this IP in the last $duration
            // seconds.
            $recent_fails = $wpdb->get_results('
                SELECT UNIX_TIMESTAMP(date) AS time
                FROM ' . $table. '
                WHERE ip = "' . $address . '"
                AND success = 0
                AND date >= DATE_SUB(NOW(), INTERVAL ' . $duration . ' SECOND)
            ');

            // If the number of failed attempts is greater than the maximum
            // number of attempts permitted, check if $max_attempts attempts
            // fell within $interval seconds.
            if (count($recent_fails) > $max_attempts) {
                if ($max_attempts == 1) {
                    wp_die($message);
                }

                $delta = $max_attempts - 1;

                for ($i = $delta; $i < count($recent_fails); $i++) {
                    $time = intval($recent_fails[$i]->time);
                    $prev = intval($recent_fails[$i - $delta]->time);
                    $diff = $time - $prev;

                    if ($diff < $interval) {
                        wp_die($message);
                    }
                }
            }

            return $user;
        }, 50, 3);
    }

    private function enableGoogleRecaptcha()
    {

        $site_key = '6Ldw8icTAAAAAIiEjNKXGVuFtz9ZBGibmDxoKNfq';

        $secret_key = '6Ldw8icTAAAAAJR0_DOLCSAurULlP9fRdO-ZjcMP';

        add_action(
            'login_enqueue_scripts',
            function () {
                wp_enqueue_script(
                    'google-recaptcha',
                    'https://www.google.com/recaptcha/api.js',
                    false
                );
            }
        );
    }
}
