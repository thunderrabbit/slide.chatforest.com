<?php

/**
 * Bootstrap file for WebDriver tests
 * This file is executed before each WebDriver test
 */

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Include the main bootstrap
require_once __DIR__ . '/../_bootstrap.php';

// Log test start
if (function_exists('codecept_debug')) {
    codecept_debug('WebDriver test bootstrap loaded at ' . date('Y-m-d H:i:s'));
}