<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

try {
    if ($is_logged_in->isLoggedIn()) {
        // For logged-in users: find earliest puzzle they haven't solved
        $user_id = $is_logged_in->loggedInID();
        
        $query = "SELECT p.puzzle_id, p.puzzle_code, p.grid_size, p.difficulty, p.created_date
                  FROM puzzles p
                  LEFT JOIN solve_times st ON p.puzzle_id = st.puzzle_id AND st.user_id = ?
                  WHERE st.puzzle_id IS NULL
                  ORDER BY p.puzzle_id ASC
                  LIMIT 1";
        
        $stmt = $mla_database->prepare($query);
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                "success" => true,
                "puzzle_code" => $result['puzzle_code'],
                "puzzle_id" => $result['puzzle_id'],
                "grid_size" => $result['grid_size'],
                "difficulty" => $result['difficulty'],
                "created_date" => $result['created_date']
            ]);
        } else {
            // All puzzles have been played
            echo json_encode([
                "success" => false,
                "message" => "You've solved all available puzzles!"
            ]);
        }
    } else {
        // For anonymous users: find earliest puzzle not in localStorage
        // We'll handle this via JavaScript since we can't access localStorage server-side
        // Just return the earliest puzzle by ID
        $query = "SELECT puzzle_id, puzzle_code, grid_size, difficulty, created_date
                  FROM puzzles
                  ORDER BY puzzle_id ASC
                  LIMIT 1";
        
        $stmt = $mla_database->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo json_encode([
                "success" => true,
                "puzzle_code" => $result['puzzle_code'],
                "puzzle_id" => $result['puzzle_id'],
                "grid_size" => $result['grid_size'],
                "difficulty" => $result['difficulty'],
                "created_date" => $result['created_date'],
                "anonymous" => true
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "No puzzles available"
            ]);
        }
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}