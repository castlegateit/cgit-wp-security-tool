<?php

namespace Cgit\SecurityTool;

abstract class Tool
{
    /**
     * Default options
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Current options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Options filter name
     *
     * @var string
     */
    protected $filter = 'cgit_security_tool_options';

    /**
     * Constructor
     *
     * Set the initial option values based on the default options and the result
     * of the WP options filter. The range of possible option keys is restricted
     * to those already present in the default options array.
     *
     * @return void
     */
    public function __construct()
    {
        $this->options = apply_filters($this->filter, $this->defaults);
        $this->options = array_intersect_key($this->options, $this->defaults);
    }

    /**
     * Change one or more option values
     *
     * If the first argument is not an associative array of options, the second
     * argument must the value for the option named in the first argument.
     *
     * @param mixed $options
     * @param mixed $value
     * @return void
     */
    public function set($options, $value = null)
    {
        // If the first argument is not an array of options, convert the
        // arguments into an array of options.
        if (!is_array($options)) {

            // If the second argument is missing, leave the options unchanged.
            if (is_null($value)) {
                return;
            }

            $options = [$options => $value];
        }

        // Update the options
        foreach ($options as $key => $value) {
            if (array_key_exists($key, $this->options)) {
                $this->options[$key] = $value;
            }
        }
    }

    /**
     * Perform actions based on options
     *
     * For those options with a value that evaluates to true and that have a
     * corresponding instance method, run the corresponding method.
     *
     * @return void
     */
    public function activate()
    {
        foreach ($this->options as $key => $value) {
            $method = self::camelize($key);

            if (method_exists($this, $method) && $value) {
                $this->$method();
            }
        }
    }

    /**
     * Convert string to camel case
     *
     * @param string $str
     * @return string
     */
    protected static function camelize($str) {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    /**
     * Display warning
     *
     * Display a warning message in the WP dashboard, but only if the current
     * user is an administrator and, therefore, can do something about it.
     *
     * @return void
     */
    protected static function displayWarning($message)
    {
        if (!self::isAdmin()) {
            return;
        }

        add_action('admin_notices', function() use ($message) {
            ?>
            <div class="error">
                <p><strong>Warning:</strong> <?= $message ?></p>
            </div>
            <?php
        });
    }

    /**
     * Is current user an administrator?
     *
     * The Tweak Tool provides a method to check whether the current user in
     * administrator. If that is not available, fall back to the default WP
     * function.
     *
     * @return boolean
     */
    protected static function isAdmin()
    {
        if (class_exists('\Cgit\TweakTool')) {
            $tool = \Cgit\TweakTool::getInstance();
            return $tool->isAdmin();
        }

        return current_user_can('activate_plugins');
    }
}
