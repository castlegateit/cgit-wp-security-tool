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
        'site_email' => 'dev@castlegateit.co.uk',
        'site_email_warning' => true,
        'admin_email' => 'dev@castlegateit.co.uk',
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
        // Admin email.
        $current_email = get_option('admin_email');
        $desired_email = $this->options['site_email'];

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
        // Administrator users.
        $administrators = get_users(['role' => 'administrator']);

        // Desired administrator email.
        $desired_email = $this->options['admin_email'];

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
