<?php

/*

Plugin Name: Castlegate IT WP Security Tool
Plugin URI: http://github.com/castlegateit/cgit-wp-security-tool
Description: Adds various security enhancements to WordPress.
Version: 1.1
Author: Castlegate IT
Author URI: http://www.castlegateit.co.uk/
License: MIT

*/

use Cgit\SecurityTool;

$plugin_file = __FILE__;

require __DIR__ . '/src/autoload.php';
require __DIR__ . '/activation.php';
require __DIR__ . '/deactivation.php';

/**
 * Initialization
 */
add_action('init', function() {
    $tool = SecurityTool::getInstance();
});
