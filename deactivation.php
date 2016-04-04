<?php

use Cgit\SecurityTool;

/**
 * Remove .htaccess rules on deactivation
 */
register_deactivation_hook(CGIT_SECURITY_TOOL_FILE, function() {
    $tool = SecurityTool::getInstance();

    $tool->set([
        'disable_php_in_uploads' => false,
        'disable_xmlrpc' => false,
    ]);

    $tool->updateConfig();
});
