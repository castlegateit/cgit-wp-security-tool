<?php

namespace Cgit\SecurityTool\Tools;

use Cgit\SecurityTool\Tool;

/**
 * Provide the facility to log login attempts and to block access to the login
 * form following repeated failed login attempts.
 */
class Login extends Tool
{
    /**
     * Default options
     *
     * @var array
     */
    protected $defaults = [
        'login_log' => true,
        'login_lock' => true,
        'login_max_attempts' => 5,
        'login_retry_interval' => 60, // 60 seconds
        'login_lock_duration' => 60, // 60 seconds
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
     * Run methods on activation
     *
     * Most of the options for this tool do not correspond to method names, so
     * this is used to override the activate method from the parent Tool class.
     *
     * @return void
     */
    public function activate()
    {
        add_filter('authenticate', [$this, 'log'], 40, 3);
        add_filter('authenticate', [$this, 'lock'], 50, 3);
    }

    /**
     * Log login attempts
     *
     * Saves a record of all login attempts in the database, using the table
     * created with the plugin activation hook.
     */
    public function log($user, $name, $password)
    {
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
    }

    /**
     * Limit login attempts
     *
     * Repeated login attempts from the same IP are blocked for a given
     * duration.
     */
    public function lock($user, $name, $password)
    {
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
    }
}
