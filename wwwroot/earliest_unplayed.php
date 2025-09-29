<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

try {
    if ($is_logged_in->isLoggedIn()) {
        // For logged-in users: find earliest puzzle they haven't solved
        $user_id = $is_logged_in->loggedInID();

        // Get grid size from URL parameter to maintain consistency
        $grid_size = intval($_GET['grid_size'] ?? 0);

        if ($grid_size >= 5 && $grid_size <= 8) {
            $query = "SELECT p.puzzle_id, p.puzzle_code, p.grid_size, p.difficulty, p.created_date
                      FROM puzzles p
                      LEFT JOIN solve_times st ON p.puzzle_id = st.puzzle_id AND st.user_id = ?
                      WHERE st.puzzle_id IS NULL AND p.grid_size = ?
                      ORDER BY p.puzzle_id ASC
                      LIMIT 1";
            $stmt = $mla_database->prepare($query);
            $stmt->execute([$user_id, $grid_size]);
        } else {
            // Fallback to original behavior if no valid grid size specified
            $query = "SELECT p.puzzle_id, p.puzzle_code, p.grid_size, p.difficulty, p.created_date
                      FROM puzzles p
                      LEFT JOIN solve_times st ON p.puzzle_id = st.puzzle_id AND st.user_id = ?
                      WHERE st.puzzle_id IS NULL
                      ORDER BY p.puzzle_id ASC
                      LIMIT 1";
            $stmt = $mla_database->prepare($query);
            $stmt->execute([$user_id]);
        }

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
        // For anonymous users: find earliest puzzle not in localStorage (same grid size)
        // Get grid size from URL parameter to maintain consistency
        $grid_size = intval($_GET['grid_size'] ?? 0);

        if ($grid_size >= 5 && $grid_size <= 8) {
            $query = "SELECT puzzle_id, puzzle_code, grid_size, difficulty, created_date
                      FROM puzzles
                      WHERE grid_size = ?
                      ORDER BY puzzle_id ASC
                      LIMIT 1";
            $stmt = $mla_database->prepare($query);
            $stmt->execute([$grid_size]);
        } else {
            // Fallback to original behavior if no valid grid size specified
            $query = "SELECT puzzle_id, puzzle_code, grid_size, difficulty, created_date
                      FROM puzzles
                      ORDER BY puzzle_id ASC
                      LIMIT 1";
            $stmt = $mla_database->prepare($query);
            $stmt->execute();
        }

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