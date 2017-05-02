<?php

namespace Cgit\SecurityTool\Tools;

use Cgit\SecurityTool\Tool;

/**
 * Adds Google reCAPTCHA protection to wp-login.php
 */
class Recaptcha extends Tool
{
    /**
     * Default options
     *
     * @var array
     */
    protected $defaults = [
        'enable_google_recaptcha' => true,
    ];

    /**
     * Google reCAPTCHA disabled
     *
     * Used to indicate if Google reCAPTCHA should be disabled.
     */
    private $disabled = false;

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
     * Enable Google reCAPTCHA.
     *
     * @return void
     */
    public function enableGoogleRecaptcha()
    {
        // Configure
        $site_key = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
        $secret_key = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';

        // Check the configuration constants exist
        if (empty($site_key) || empty($secret_key)) {
            $this->displayWarning(
                'Google reCAPTCHA configuration keys have not been set.'
            );

            return;
        }

        /**
         * Add a hook to check if reCATPCHA should be disabled. This runs before
         * any method which will display reCAPTCHA code.
         */
        add_action(
            'wp_loaded',
            [$this, 'disable'],
            1
        );

        // Display the reCAPTCHA
        add_action(
            'wp_loaded',
            [$this, 'display'],
            2
        );

        // Display reCAPTCHA error message.
        add_action(
            'wp_loaded',
            [$this, 'error'],
            2
        );

        // Handle the reCAPTCHA request on authentication
        add_action(
            'wp_authenticate',
            [$this, 'process']
        );
    }

    /**
     * Checks if Google reCAPTCHA should be disabled and sets a variable.
     * It should be disabled in the presence of 2FA authentication.
     *
     * @return void
     */
    public function disable()
    {
        if (defined('TFA_MAIN_PLUGIN_PATH')) {
            $this->disabled = true;
        }
    }

    /**
     * Displays a Google reCAPTCHA on wp-login.php.
     *
     * @return void
     */
    public function display()
    {
        if ($this->disabled) {
            return;
        }

        // Enqueue the Google ReCAPTCHA API on the login page.
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

        // Add the reCAPTCHA markup.
        $styles = '
            margin: 0 auto 1em auto;
           transform:scale(.90);
           -webkit-transform: scale(.90);
           transform-origin:0 0;
           -webkit-transform-origin:0 0;';

        $style = preg_replace("/\r|\n/", "", $styles);

        add_action(
            'login_form',
            function () use ($style) {
                echo '<div style="'.$style.'" class="g-recaptcha" data-sitekey="'.RECAPTCHA_SITE_KEY.'"></div>';
            }
        );
    }

    /**
     * Displays an error message if reCAPTCHA failed.
     *
     * @return void
     */
    public function error()
    {
        if ($this->disabled) {
            return;
        }

        add_filter(
            'authenticate',
            function ($user, $username = '', $password = '') {
                // Only if required.
                if (isset($_GET['recaptcha-failure'])) {
                    $error = new \WP_Error;
                    $error->add('cgit-recaptcha', '<strong>ERROR:</strong> Please confirm you\'re not a robot.');
                    return $error;
                }
                return $user;
            }
        );
    }

    /**
     * Called on action 'wp_authenticate', this runs on both GET and POST of
     * wp-login.php to handle Google reCAPTCHA checks.
     *
     * @return void
     */
    public function process()
    {
        if ($this->disabled) {
            return;
        }

        // Process only when a login has been submitted.
        if (empty($_POST)) {
            return true;
        }

        // Fetch the reCAPTCHA submission response.
        $response = json_decode($this->googleRecaptchaRequest(), true);

        // Assume valid.
        $valid = true;

        // Validate hostname. reCAPTCHA expects hostname validation when shared
        // keys are in use.
        $hostname = parse_url(get_home_url(), PHP_URL_HOST);
        if (!isset($response['hostname']) || $response['hostname'] !== $hostname) {
            $valid = false;
        }

        // Validate response
        if (!isset($response['success']) || $response['success'] !== true) {
            $valid = false;
        }

        // If validation failed, logout and redirect.
        if (!$valid) {
            wp_logout();
            header('Location: wp-login.php?recaptcha-failure');
            exit();
        }
    }

    /**
     * Sends a request to Google to validate the reCAPTCHA entry.
     *
     * @return string
     */
    public function googleRecaptchaRequest()
    {
        // Get the posted reCAPTCHA response
        $response = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

        // Build the request parameters
        $parameters = http_build_query([
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]);

        // Google endpoint.
        $url = 'https://www.google.com/recaptcha/api/siteverify?'.$parameters;

        if (function_exists('curl_init')
            && function_exists('curl_setopt')
            && function_exists('curl_exec')
        ) {
            // Make the request with cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
        } else {
            // Make the request with file_get_contents
            $response = file_get_contents($url);
        }

        return trim($response);
    }
}
