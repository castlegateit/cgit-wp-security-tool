<?php

/*

Plugin Name: Castlegate IT WP Security Tool
Plugin URI: http://github.com/castlegateit/cgit-wp-security-tool
Description: Adds various security enhancements to WordPress.
Version: 2.6.1
Author: Castlegate IT
Author URI: http://www.castlegateit.co.uk/
License: MIT

*/

use Cgit\SecurityTool\Plugin;

// Constants
define('CGIT_SECURITY_TOOL_PLUGIN_FILE', __FILE__);

// Load plugin
require __DIR__ . '/src/autoload.php';

// Initialization
Plugin::getInstance();
