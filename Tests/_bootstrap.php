<?php

/**
 * Bootstrap file for Codeception tests
 * This file is executed before each test
 */

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Include any test-specific setup here
// For example, database connections, test data, etc.

// Log test start
if (function_exists('codecept_debug')) {
    codecept_debug('Test bootstrap loaded at ' . date('Y-m-d H:i:s'));
}