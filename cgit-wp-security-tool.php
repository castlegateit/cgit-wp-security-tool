<?php

/*

Plugin Name: Castlegate IT WP Security Tool
Plugin URI: http://github.com/castlegateit/cgit-wp-security-tool
Description: Adds various security enhancements to WordPress.
Version: 1.0
Author: Castlegate IT
Author URI: http://www.castlegateit.co.uk/
License: MIT

*/

add_action('plugins_loaded', function() {

    // Includes
    include dirname(__FILE__) . '/security-tool.php';

    // Initialization
    Cgit\SecurityTool::getInstance();
});
