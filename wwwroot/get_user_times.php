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

// Allow both logged-in and anonymous users to see global leaderboard

try {
    $puzzle_id = intval($_GET['puzzle_id']);
    $current_user_id = $is_logged_in->isLoggedIn() ? $is_logged_in->loggedInID() : null;

    // Get global leaderboard for this puzzle with usernames
    $query = "SELECT st.solve_time_ms, st.completed_at, st.user_id, u.username
              FROM solve_times st
              JOIN users u ON st.user_id = u.user_id
              WHERE st.puzzle_id = ?
              ORDER BY st.solve_time_ms ASC
              LIMIT 10";

    $stmt = $mla_database->prepare($query);
    $stmt->execute([$puzzle_id]);
    $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "times" => $times,
        "current_user_id" => $current_user_id
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
