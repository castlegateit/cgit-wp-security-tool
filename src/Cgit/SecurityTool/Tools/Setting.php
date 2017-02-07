<?php

namespace Cgit\SecurityTool\Tools;

use Cgit\SecurityTool\Tool;

/**
 * Disable various default WP settings and provide warnings about those that
 * cannot be changed here.
 */
class Setting extends Tool
{
    /**
     * Default options
     *
     * @var array
     */
    protected $defaults = [
        'site_email_warning' => true,
        'admin_email_warning' => true,
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
     * Site email warning
     *
     * Warns the administrator if the site email address is not set to a
     * predefined value.
     *
     * @return void
     */
    protected function siteEmailWarning()
    {
        $docs = 'https://github.com/castlegateit/cgit-wp-security-tool';
        $docs.= '#site-email-warning';

        if (!defined('CGIT_SEC_SITE_EMAIL')) {
            self::displayWarning(
                'The required constant, <code>CGIT_SEC_SITE_EMAIL</code> is '
                .'not set in your configuration file. This constant is used to '
                .'check that at your WordPress site email address matches a '
                .'predefined value. Please set this constant\'s value to your '
                .'preferred email address. <br />See the <a href="'
                .$docs.'" target="_blank">documentation</a> for more '
                .'information.'
            );

            return;
        }

        // Admin email.
        $current_email = get_option('admin_email');
        $desired_email = CGIT_SEC_SITE_EMAIL;

        // Settings URL
        $settings_url = admin_url('options-general.php');

        if ($current_email !== $desired_email) {
            self::displayWarning(
                'The site email address is not set to the desired value of '
                .'<code>'.$desired_email.'</code>. Please update the site\'s '
                .'email address from the <a href="'.$settings_url
                .'">General Settings</a> page.'
            );
        }
    }

    /**
     * Admin email warning
     *
     * Warns the administrator if the site does not have an administrator
     * with a desired email address.
     *
     * @return void
     */
    protected function adminEmailWarning()
    {
        $docs = 'https://github.com/castlegateit/cgit-wp-security-tool';
        $docs.= '#admin-email-warning';

        if (!defined('CGIT_SEC_ADMIN_EMAIL')) {
            self::displayWarning(
                'The required constant, <code>CGIT_SEC_ADMIN_EMAIL</code> is '
                .'not set in your configuration file. This constant is used to '
                .'check that at least one administrator user exists with an '
                .'email address which matches a predefined value. Please set '
                .'this constant\'s value to your preferred email address. '
                .'<br />See the <a href="'.$docs.'" target="_blank">'
                .'documentation</a> for more information.'
            );

            return;
        }

        // Administrator users.
        $administrators = get_users(['role' => 'administrator']);

        // Desired administrator email.
        $desired_email = CGIT_SEC_ADMIN_EMAIL;

        foreach ($administrators as $user) {
            if ($user->user_email == $desired_email) {
                return;
            }
        }

        self::displayWarning(
            'None of the administrator users have the email address '
            .'<code>'.$desired_email.'</code>. Please update one '
            .'administrator user to use this email address.'
        );
    }
}
