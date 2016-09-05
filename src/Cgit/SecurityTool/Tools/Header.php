<?php

namespace Cgit\SecurityTool\Tools;

use Cgit\SecurityTool\Tool;

/**
 * Sends additional headers to improve general security and protect against
 * specific attacks.
 */
class Header extends Tool
{
    /**
     * Default options
     *
     * @var array
     */
    protected $defaults = [
        'enable_frame_origin' => true,
        'enable_xss_protection' => true,
        'enable_no_sniff' => true,
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
     * Sends the X-Frame-Origin header to limit rendering of pages to frames on
     * the same domain/origin. Provides XSS protection.
     *
     * @return void
     */
    public function enableFrameOrigin()
    {
        if (!headers_sent()) {
            send_frame_options_header();
        }
    }

    /**
     * Sends the X-XSS-Protection header to block detected XSS attempts when
     * detected by supported browsers. Support is included in IE8+ & Webkit.
     */
    public function enableXssProtection()
    {
        if (!headers_sent()) {
            header("X-XSS-Protection: 1; mode=block;");
        }
    }

    /**
     * Send a HTTP header to disable content type sniffing in browsers which
     * support it. This prevents browsers from attempting to determine a mime
     * type automatically, and forces rendering in the mime type provided by
     * the server.
     *
     * @return void
     */
    public function enableNoSniff()
    {
        if (!headers_sent()) {
            send_nosniff_header();
        }
    }
}
