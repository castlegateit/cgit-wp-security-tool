<?php

namespace Cgit\SecurityTool\Tools;

class TwoFactorAuth extends \Cgit\SecurityTool\Tool
{
    /**
     * Default settings
     *
     * By default, a warning should be displayed if 2FA has been disabled for
     * any user role. If the `two_factor_auth_warning` option is false and the
     * `two_factor_auth_admin_warning` is true, the warning will only be
     * displayed if 2FA is disabled for administrator users.
     *
     * @var array
     */
    protected $defaults = [
        'two_factor_auth_warning' => true,
        'two_factor_auth_admin_warning' => true,
    ];

    /**
     * Roles
     *
     * A complete list of the user roles available on the site, which will
     * correspond to 2FA options.
     *
     * @var array
     */
    private $roles = [];

    /**
     * Has a 2FA warning already been issued?
     *
     * @var boolean
     */
    private $warningIssued = false;

    /**
     * Constructor
     *
     * Call the parent constructor so that the settings can be filtered and
     * populate the list of user roles available on this site.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->populateRolesList();
    }

    /**
     * Display a warning if 2FA is disabled for any user roles
     *
     * If no previous 2FA warning has been issued, check the WordPress options
     * to make sure 2FA is enabled for all users.
     *
     * @return void
     */
    protected function twoFactorAuthWarning()
    {
        if ($this->warningIssued) {
            return;
        }

        $this->checkAuthEnabled();
    }

    /**
     * Display a warning if 2FA is disabled for administrator user roles
     *
     * If no previous 2FA warning has been issued, check the WordPress options
     * to make sure that 2FA is enabled for administrator users.
     *
     * @return void
     */
    protected function twoFactorAuthAdminWarning()
    {
        if ($this->warningIssued) {
            return;
        }

        $this->checkAuthEnabled(['administrator']);
    }

    /**
     * Populate roles list
     *
     * List all the user roles available on the site and assign them to the user
     * roles property. Each role will have a corresponding 2FA option.
     *
     * @return void
     */
    private function populateRolesList()
    {
        global $wp_roles;

        if (!isset($wp_roles)) {
            $wp_roles = new WP_Roles();
        }

        foreach ($wp_roles->role_names as $key => $name) {
            $this->roles[$key] = $name;
        }
    }

    /**
     * Is 2FA enabled?
     *
     * Check that 2FA is enabled for one or more user roles and display a
     * warning on the WordPress dashboard if it is not.
     *
     * @param array $roles
     * @return void
     */
    private function checkAuthEnabled($roles = null)
    {
        // List of roles to check. If no roles are specified, use the list of
        // all available user roles.
        $roles = is_null($roles) ? array_keys($this->roles) : $roles;

        // List of roles checked by the plugin that have 2FA disabled and,
        // therefore, need to appear in the warning notification.
        $disabled = [];

        // Check the options table to identify any user roles for which 2FA has
        // been disabled.
        foreach ($roles as $role) {
            if (!get_option('tfa_' . $role)) {
                $disabled[] = $this->roles[$role];
            }
        }

        // If 2FA is enabled for all the roles checked, do nothing
        if (!$disabled) {
            return;
        }

        // Singular or plural?
        $suffix = count($disabled) > 1 ? 's' : '';

        // Display a warning, listing the user roles that should have 2FA
        // enabled but currently do not.
        self::displayWarning('Please activate the <a href="https://wordpress.org/plugins/two-factor-auth/">Two Factor Auth</a> plugin and enable authentication for the ' . self::formatList($disabled) . ' user role' . $suffix . '.');

        // Prevent any more 2FA warnings from being displayed
        $this->warningIssued = true;
    }

    /**
     * Format an array as text
     *
     * @param array $items
     * @return string
     */
    private function formatList($items)
    {
        // Make sure that we are not dealing with an associative array or non-
        // sequential array keys.
        $items = array_values($items);

        switch (count($items)) {
            case 1:
                return $items[0];
            case 2:
                return implode(' and ', $items);
            default:
                $last = array_pop($items);
                return implode(', ', $items) . ', and ' . $last;
        }
    }
}
