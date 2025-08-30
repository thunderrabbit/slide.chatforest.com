<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

// Require user to be logged in
if (!$is_logged_in->isLoggedIn()) {
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
        'puzzle_code' => $input['puzzle_code'] ?? null,
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
    $query = "INSERT INTO solve_times (puzzle_id, puzzle_code, solve_time_ms, user_id)
              VALUES (?, ?, ?, ?)";

    $stmt = $mla_database->prepare($query);
    $result = $stmt->execute([
        $solve_data['puzzle_id'],
        $solve_data['puzzle_code'],
        $solve_data['solve_time_ms'],
        $solve_data['user_id']
    ]);

    if ($result) {
        echo json_encode([
            "success" => true,
            "message" => "Solve time recorded successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to save solve time"]);
    }

} catch (\PDOException $e) {
    // Check if it's a duplicate key constraint violation (MySQL error code 23000)
    if ($e->getCode() == '23000' && strpos($e->getMessage(), 'unique_user_puzzle_solve') !== false) {
        http_response_code(409); // Conflict
        echo json_encode([
            "error" => "You have already solved this puzzle",
            "already_solved" => true
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
