<?php

class Template{
    protected $template_location;

    protected $vars;

    protected $mla_request;      // Encapsulates superglobals e.g. $SESSION, $REQUEST, etc (misspelled in this comment to keep searches clean)
    protected $di_dbase;

    public function __construct(\Config $config) {
        $this->template_location = "{$config->app_path}/templates";

        $this->vars = [];
    }

    public function setTemplate($template_file) {
        $this->template_location = $this->template_location."/".$template_file;
    }

    /**
     * Summary of set
     * @param string $name
     * @param mixed $value mixed so array of file names can be passed in /list/index.php
     * @return void
     */
    public function set(string $name, mixed $value) {
        $this->vars[$name] = $value;
    }

    public function echoToScreen() {
        echo $this->loadTemplate();// Return the contents
    }

     protected function loadTemplate() {
        $charEncode = "UTF-8";
        extract($this->vars);          	// Extract the vars to local namespace

        ob_start();                    	// Start output buffering

        if(!isset($this->template_location)) {
            echo "No template file provided";
        }

        include($this->template_location);	// Include the file

        $ob_result = ob_get_clean();


        return $ob_result;
    }
}
