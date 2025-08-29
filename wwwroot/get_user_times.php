<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

if (!isset($_GET['puzzle_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing puzzle_id parameter"]);
    exit;
}

if (!$is_logged_in->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(["error" => "User not logged in"]);
    exit;
}

try {
    $puzzle_id = intval($_GET['puzzle_id']);
    $user_id = $is_logged_in->getLoggedInUserId();

    // Get user's best times for this puzzle, ordered by solve time (fastest first)
    $query = "SELECT solve_time_ms, completed_at
              FROM solve_times
              WHERE puzzle_id = ? AND user_id = ?
              ORDER BY solve_time_ms ASC
              LIMIT 10";

    $stmt = $mla_database->prepare($query);
    $stmt->execute([$puzzle_id, $user_id]);
    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "times" => $times
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
