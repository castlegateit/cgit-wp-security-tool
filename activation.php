<?php

use Cgit\SecurityTool;

/**
 * Create a database table to log login attempts
 */
register_activation_hook($plugin_file, function() {
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

    // Update configuration
    $tool = SecurityTool::getInstance();
    $tool->updateConfig();
});
