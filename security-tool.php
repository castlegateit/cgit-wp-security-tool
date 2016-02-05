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
        'disable_theme_editor' => true,
        'default_user_warning' => true,
        'login_log' => true,
        'login_lock' => true,
        'login_max_attempts' => 5,
        'login_retry_interval' => 60, // 60 seconds
        'login_lock_duration' => 60, // 60 seconds
    ];

    /**
     * Site options
     */
    private $options = [];

    /**
     * Constructor
     *
     * Private constructor ...
     */
    private function __construct($options = [])
    {
        $this->options = $this->defaultOptions;
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

            if ($value && method_exists($this, $method)) {
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
                array_key_exists('author_name', $wp->query_vars)
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
     * uploads directory. If .htaccess already exists, this does nothing.
     */
    private function disablePhpInUploads()
    {
        $uploads = wp_upload_dir()['basedir'];
        $file = $this->joinPath([$uploads, '.htaccess']);
        $config = '<Files *.php>Deny from all</Files>';

        if (file_exists($file)) {
            return;
        }

        file_put_contents($file, $config);
    }

    /**
     * Disable theme editor
     *
     * Prevents access to the theme editor in the WordPress dashboard to stop
     * anyone editing the theme files from within WordPress.
     */
    private function disableThemeEditor()
    {
        add_action('init', function() {
            define('DISALLOW_FILE_EDIT', true);
        });
    }

    /**
     * Default user warning
     *
     * If the default administrative username "admin" exists, site
     * administrators will receive an stern rebuke.
     */
    private function defaultUserWarning()
    {
        if (username_exists('admin') && current_user_can('edit_users')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p><strong>Warning:</strong>'
                    . ' For security reasons, you should delete, or change the'
                    . ' username of, the default <code>admin</code> user'
                    . ' account. &#x1f620;</p></div>';
            });
        }
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
}
