<?php
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    exit;
}

// Get user info from auth system (use existing instance from prepend.php)
$username = null;
if (isset($is_logged_in) && $is_logged_in->isLoggedIn()) {
    $username = $is_logged_in->getLoggedInUsername();
}

// Add username to the incoming data and output as-is
$data['username'] = $username;

// Ensure logs directory exists
$logsDir = dirname(__DIR__) . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Write to log file (one JSON object per line)
$logFile = $logsDir . '/cell_clicks.log';
$logLine = json_encode($data) . "\n";
file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

// Return success
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>
