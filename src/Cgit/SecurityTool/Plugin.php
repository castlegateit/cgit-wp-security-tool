<?php

namespace Cgit\SecurityTool;

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
        'FeatureTool',
        'FileTool',
        'LoginTool',
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

        $table = $wpdb->prefix . 'cgit_security_logins';
        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table . ' (
            login_id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(16),
            user_agent VARCHAR(512),
            date DATETIME,
            user_id INT,
            user_name VARCHAR(64),
            success TINYINT
        )';

        // Create necessary database tables
        $wpdb->query($sql);

        // Update configuration files
        $tool = new FileTool();
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
        $tool = new FileTool();

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
        add_action('init', function() {
            foreach ($this->tools as $tool) {
                $name = '\\Cgit\\SecurityTool\\' . $tool;

                if (class_exists($name)) {
                    $obj = new $name();
                    $obj->activate();
                }
            }
        });
    }
}
