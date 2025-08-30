<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

header("Content-Type: application/json");

// Handle both GET and POST requests
$gridSize = null;
$difficulty = 'medium';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if ($input) {
        $gridSize = $input['grid_size'] ?? null;
        $difficulty = $input['difficulty'] ?? 'medium';
    }
} else {
    $gridSize = (int)($_GET['grid_size'] ?? 0);
    $difficulty = $_GET['difficulty'] ?? 'medium';
}

// Validate grid size
if (!$gridSize || $gridSize < 3 || $gridSize > 10) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid grid_size. Must be between 3 and 10."]);
    exit;
}

// Validate difficulty
if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid difficulty. Must be easy, medium, or hard."]);
    exit;
}

try {
    // Generate the puzzle using PHP
    $generator = new PuzzleGenerator($gridSize);
    $puzzleData = $generator->generatePuzzle($difficulty);

    // Save to database
    $puzzleManager = new PuzzleManager($mla_database);
    $puzzleResult = $puzzleManager->savePuzzle($puzzleData);

    // Return the complete puzzle data along with the saved puzzle info
    echo json_encode([
        "success" => true,
        "puzzle_id" => $puzzleResult['puzzle_id'],
        "puzzle_code" => $puzzleResult['puzzle_code'],
        "puzzle_data" => $puzzleData,
        "generated_by" => "php_server"
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "generated_by" => "php_server"
    ]);
}
