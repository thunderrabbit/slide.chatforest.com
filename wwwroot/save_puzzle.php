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

$required_fields = ['grid_size', 'barriers', 'numbered_positions', 'solution_path', 'difficulty'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

try {
    $puzzleManager = new PuzzleManager($mla_database);
    $puzzleId = $puzzleManager->savePuzzle($input);

    echo json_encode([
        "success" => true,
        "puzzle_id" => $puzzleId
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
