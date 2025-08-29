<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

// Only logged-in users can check solve status
if (!$is_logged_in->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(["error" => "Login required"]);
    exit;
}

$puzzle_id = intval($_GET['puzzle_id'] ?? 0);

if (!$puzzle_id) {
    http_response_code(400);
    echo json_encode(["error" => "puzzle_id parameter required"]);
    exit;
}

try {
    $user_id = $is_logged_in->loggedInID();

    // Check if user has already solved this puzzle
    $query = "SELECT solve_time_ms, completed_at FROM solve_times
              WHERE user_id = ? AND puzzle_id = ?
              LIMIT 1";

    $stmt = $mla_database->prepare($query);
    $stmt->execute([$user_id, $puzzle_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            "solved" => true,
            "solve_time_ms" => $result['solve_time_ms'],
            "completed_at" => $result['completed_at']
        ]);
    } else {
        echo json_encode([
            "solved" => false
        ]);
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
