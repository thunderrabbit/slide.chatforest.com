<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

$current_puzzle_id = intval($_GET['current_puzzle_id'] ?? 0);

if (!$current_puzzle_id) {
    http_response_code(400);
    echo json_encode(["error" => "current_puzzle_id parameter required"]);
    exit;
}

try {
    if ($is_logged_in->isLoggedIn()) {
        // For logged-in users: find next unplayed puzzle with higher ID than current
        $user_id = $is_logged_in->loggedInID();
        
        $query = "SELECT p.puzzle_id, p.puzzle_code, p.grid_size, p.difficulty, p.created_date
                  FROM puzzles p
                  LEFT JOIN solve_times st ON p.puzzle_id = st.puzzle_id AND st.user_id = ?
                  WHERE st.puzzle_id IS NULL AND p.puzzle_id > ?
                  ORDER BY p.puzzle_id ASC
                  LIMIT 1";
        
        $stmt = $mla_database->prepare($query);
        $stmt->execute([$user_id, $current_puzzle_id]);
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
            // No more unplayed puzzles with higher ID - redirect to main page for new puzzle
            echo json_encode([
                "success" => false,
                "redirect_to_new" => true,
                "message" => "No more unplayed puzzles - generating new puzzle"
            ]);
        }
    } else {
        // For anonymous users: find next puzzle with higher ID
        // JavaScript will need to check localStorage for completions
        $query = "SELECT puzzle_id, puzzle_code, grid_size, difficulty, created_date
                  FROM puzzles
                  WHERE puzzle_id > ?
                  ORDER BY puzzle_id ASC
                  LIMIT 1";
        
        $stmt = $mla_database->prepare($query);
        $stmt->execute([$current_puzzle_id]);
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
            // No more puzzles with higher ID - redirect to main page for new puzzle
            echo json_encode([
                "success" => false,
                "redirect_to_new" => true,
                "message" => "No more puzzles available - generating new puzzle"
            ]);
        }
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}