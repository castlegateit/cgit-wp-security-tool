<?php

namespace Cgit\SecurityTool\Tools;

use Cgit\SecurityTool\Tool;

/**
 * Block access to various potentially sensitive files. Because this involves
 * writing to files, this should not run every time that WP loads.
 */
class File extends Tool
{
    /**
     * Default options
     *
     * @var array
     */
    protected $defaults = [
        'disable_php_in_uploads' => true,
        'disable_xmlrpc' => true,
        'disable_readme_files' => true,
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
     * Avoid doing anything on activation
     *
     * The actions performed by this tool involve writing to files. This should
     * be done on activation and deactivation, not on every page load.
     *
     * @return void
     */
    public function activate()
    {
        // do nothing
    }

    /**
     * Update all files based on options
     *
     * Loops through the options and runs the corresponding methods. In contrast
     * the parent class activate method, this runs the methods regardless of
     * whether they are true or not.
     *
     * @return void
     */
    public function update()
    {
        foreach ($this->options as $key => $value) {
            $method = self::camelize($key);

            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
    }

    /**
     * Disable PHP in the uploads directory
     *
     * Writes .htaccess to prevent PHP execution for any files within the
     * uploads directory.
     */
    private function disablePhpInUploads()
    {
        $marker = 'Security Tool: disable PHP in uploads';
        $file = [wp_upload_dir()['basedir'], '.htaccess'];
        $content = [];

        if ($this->options['disable_php_in_uploads']) {
            $content = [
                '<Files "*.php">',
                '    Order Deny,Allow',
                '    Deny from all',
                '</Files>',
            ];
        }

        $this->writeConf($file, $marker, $content);
    }

    /**
     * Disable XML RPC
     *
     * Blocks access to xmlrpc.php using .htaccess.
     */
    private function disableXmlrpc()
    {
        $marker = 'Security Tool: disable XML RPC';
        $file = [ABSPATH, '.htaccess'];
        $content = [];

        if ($this->options['disable_xmlrpc']) {
            $content = [
                '<Files "xmlrpc.php">',
                '    Order Deny,Allow',
                '    Deny from all',
                '    Allow from 127.0.0.1',
                '</Files>',
            ];
        }

        $this->writeConf($file, $marker, $content);
    }

    /**
     * Disable README files
     *
     * Block access to README and LICENSE files in the document root common to
     * WordPress installations and Git repositories.
     */
    private function disableReadmeFiles()
    {
        $marker = 'Security Tool: disable README files';
        $file = [ABSPATH, '.htaccess'];
        $content = [];

        if ($this->options['disable_readme_files']) {
            $content = [
                'RewriteEngine on',
                'RewriteBase /',
                'RewriteRule ^license.*$ - [R=404,NC,L]',
                'RewriteRule ^readme.*$ - [R=404,NC,L]',
            ];
        }

        $this->writeConf($file, $marker, $content);
    }

    /**
     * Write to an Apache configuration file
     *
     * @return void
     */
    private function writeConf($file, $marker, $content)
    {
        require_once(ABSPATH . 'wp-admin/includes/misc.php');

        if (is_array($file)) {
            $file = $this->path($file);
        }

        insert_with_markers($file, $marker, $content);
    }

    /**
     * Join strings to create a file system path
     *
     * If the first element in the array starts with a forward slash, this will
     * return an absolute path. Otherwise, this will return a relative path.
     *
     * @return string
     */
    private function path($parts)
    {
        if (!is_array($parts)) {
            return false;
        }

        $start = array_values($parts)[0][0] == '/' ? '/' : '';
        $parts = array_map(function($part) {
            return trim($part, '/');
        }, $parts);

        return $start . implode('/', $parts);
    }
}
