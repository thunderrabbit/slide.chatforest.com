<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

$raw_input = file_get_contents("php://input");
$input = json_decode($raw_input, true);

// Function to log debugging info to file
function logSaveAttempt($data) {
    $logsDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    $logFile = $logsDir . '/save_solve_time.log';
    $logLine = json_encode($data) . "\n";
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// Log API call for debugging migration issues
logSaveAttempt([
    'endpoint' => 'save_solve_time.php',
    'method' => $_SERVER['REQUEST_METHOD'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
    'input_received' => $input,
    'raw_input_length' => strlen($raw_input),
    'timestamp' => date('Y-m-d H:i:s')
]);

if (!$input) {
    logSaveAttempt(['error' => 'Invalid JSON input', 'raw_input' => $raw_input, 'timestamp' => date('Y-m-d H:i:s')]);
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

// Require user to be logged in
if (!$is_logged_in->isLoggedIn()) {
    logSaveAttempt(['error' => 'User not logged in during save_solve_time API call', 'session_data' => $_SESSION ?? [], 'timestamp' => date('Y-m-d H:i:s')]);
    http_response_code(401);
    echo json_encode(["error" => "Login required to save solve times"]);
    exit;
}

$required_fields = ['puzzle_id', 'solve_time_ms'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

try {
    $user_id = $is_logged_in->loggedInID();

    // Prepare the solve time data
    $solve_data = [
        'puzzle_id' => intval($input['puzzle_id']),
        'solve_time_ms' => intval($input['solve_time_ms']),
        'user_id' => $user_id
    ];

    // Validate solve time is reasonable (minimum 2.5 seconds to prevent bots)
    if ($solve_data['solve_time_ms'] < 2500) {
        http_response_code(400);
        echo json_encode(["error" => "Wow that's fast"]);
        exit;
    }

    // Insert the solve time
    $query = "INSERT INTO solve_times (puzzle_id, solve_time_ms, user_id)
              VALUES (?, ?, ?)";

    $stmt = $mla_database->prepare($query);
    $result = $stmt->execute([
        $solve_data['puzzle_id'],
        $solve_data['solve_time_ms'],
        $solve_data['user_id']
    ]);

    if ($result) {
        logSaveAttempt(['success' => 'Solve time saved successfully', 'user_id' => $user_id, 'puzzle_id' => $solve_data['puzzle_id'], 'solve_time_ms' => $solve_data['solve_time_ms'], 'timestamp' => date('Y-m-d H:i:s')]);
        echo json_encode([
            "success" => true,
            "message" => "Solve time recorded successfully"
        ]);
    } else {
        logSaveAttempt(['error' => 'Database insert failed', 'user_id' => $user_id, 'puzzle_id' => $solve_data['puzzle_id'], 'timestamp' => date('Y-m-d H:i:s')]);
        http_response_code(500);
        echo json_encode(["error" => "Failed to save solve time"]);
    }

} catch (\PDOException $e) {
    // Check if it's a duplicate key constraint violation (MySQL error code 23000)
    if ($e->getCode() == '23000' && strpos($e->getMessage(), 'unique_user_puzzle_solve') !== false) {
        logSaveAttempt(['error' => 'Duplicate solve attempt blocked', 'user_id' => $user_id ?? null, 'puzzle_id' => $solve_data['puzzle_id'] ?? null, 'exception_code' => $e->getCode(), 'timestamp' => date('Y-m-d H:i:s')]);
        http_response_code(409); // Conflict
        echo json_encode([
            "error" => "You have already solved this puzzle",
            "already_solved" => true
        ]);
    } else {
        logSaveAttempt(['error' => 'PDO Exception during solve save', 'user_id' => $user_id ?? null, 'puzzle_id' => $solve_data['puzzle_id'] ?? null, 'exception_message' => $e->getMessage(), 'exception_code' => $e->getCode(), 'timestamp' => date('Y-m-d H:i:s')]);
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} catch (\Exception $e) {
    logSaveAttempt(['error' => 'General exception during solve save', 'user_id' => $user_id ?? null, 'exception_message' => $e->getMessage(), 'timestamp' => date('Y-m-d H:i:s')]);
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
