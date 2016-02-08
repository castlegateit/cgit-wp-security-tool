<?php

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

    $wpdb->query($sql);
});
