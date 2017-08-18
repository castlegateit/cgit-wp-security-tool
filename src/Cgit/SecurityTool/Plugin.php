<?php

namespace Cgit\SecurityTool;

use Cgit\SecurityTool\Tools\File;

class Plugin
{
    /**
     * Tools
     *
     * List of security tool classes, each of which should be extended from the
     * abstract Tool class.
     *
     * @var array
     */
    private $tools = [
        'Feature',
        'File',
        'Login',
        'Recaptcha',
        'Header',
    ];

    /**
     * Singleton class instance
     *
     * @var Plugin
     */
    private static $instance;

    /**
     * Private constructor
     *
     * Registers the activation and deactivation methods. Checks for all
     * security tool classes, i.e. those classes that extend the abstract Tool
     * class, and initializes them.
     *
     * @return void
     */
    private function __construct()
    {
        register_activation_hook(
            CGIT_SECURITY_TOOL_PLUGIN_FILE,
            [$this, 'activate']
        );

        register_deactivation_hook(
            CGIT_SECURITY_TOOL_PLUGIN_FILE,
            [$this, 'deactivate']
        );

        // Activate the registered security tools
        $this->activateTools();

        // Check for multisite network compatibility
        $this->checkNetworkCompatible();
    }

    /**
     * Return the singleton class instance
     *
     * @return Plugin
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Activate plugin
     *
     * Creates the database required for login logs and creates or updates the
     * configuration files.
     *
     * @return void
     */
    public function activate()
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'cgit_security_logins';
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
            login_id INT AUTO_INCREMENT PRIMARY KEY,
            blog_id INT,
            ip VARCHAR(16),
            user_agent VARCHAR(512),
            date DATETIME,
            user_id INT,
            user_name VARCHAR(64),
            success TINYINT
        )';

        // Create necessary database tables
        $wpdb->query($sql);

        // Update the database if it is not compatible with network sites
        if (!self::networkCompatible()) {
            $wpdb->query('ALTER TABLE ' . $table
                . ' ADD blog_id INT AFTER login_id');
        }

        // Update configuration files
        $tool = new File();
        $tool->update();
    }

    /**
     * Deactivate plugin
     *
     * Removes the changes made to the configuration files when the plugin is
     * deactivated to restore the default WP behaviour.
     *
     * @return void
     */
    public function deactivate()
    {
        $tool = new File();

        $tool->set([
            'disable_php_in_uploads' => false,
            'disable_xmlrpc' => false,
            'disable_readme_files' => false,
        ]);

        $tool->update();
    }

    /**
     * Activate security tools
     *
     * Security tools are any classes that extend the abstract Tool class in
     * this plugin. This method loops through them all and activates each of
     * them.
     *
     * @return void
     */
    private function activateTools()
    {
        add_action('init', function () {
            foreach ($this->tools as $tool) {
                $name = '\\Cgit\\SecurityTool\\Tools\\' . $tool;

                if (class_exists($name)) {
                    $obj = new $name();
                    $obj->activate();
                }
            }
        });
    }

    /**
     * Check for and notify of network compatibility issues
     *
     * @return void
     */
    private function checkNetworkCompatible()
    {
        if (self::networkCompatible() || !is_multisite()) {
            return;
        }

        add_action('admin_notices', function () {
            ?>
            <div class="error">
                <p><strong>Warning:</strong> Please reactivate the Security Tool
                plugin to update the database for compatibility with the latest
                version of the plugin.</p>
            </div>
            <?php
        });
    }

    /**
     * Is the database table compatible with network sites?
     *
     * @return boolean
     */
    public static function networkCompatible()
    {
        global $wpdb;

        $database = DB_NAME;
        $table = $wpdb->base_prefix . 'cgit_security_logins';
        $result = $wpdb->query("
            SELECT * FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '$database'
            AND TABLE_NAME = '$table'
            AND COLUMN_NAME = 'blog_id'
        ");

        if ($result) {
            return true;
        }

        return false;
    }
}
