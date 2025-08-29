<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

// Parse the URL to get puzzle ID/code from path like /puzzle/123 or /puzzle/kx7mp9qr
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($request_uri, '/'));
$puzzle_identifier = null;
$puzzle_data = null;

// Look for identifier after 'puzzle' in the path (supports both numeric IDs and 8-char codes)
if (count($path_parts) >= 2 && $path_parts[0] === 'puzzle') {
    $puzzle_identifier = $path_parts[1];

    // Try to load the puzzle by ID or code
    try {
        $puzzleManager = new PuzzleManager($mla_database);
        $puzzle_data = $puzzleManager->getPuzzleByIdOrCode($puzzle_identifier);
    } catch (\Exception $e) {
        error_log("Error loading puzzle $puzzle_identifier: " . $e->getMessage());
    }
}

$debugLevel = intval($_GET['debug']) ?? 0;
if($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
    if ($puzzle_identifier) {
        echo "<pre>Puzzle Identifier: $puzzle_identifier</pre>";
        echo "<pre>Puzzle found: " . ($puzzle_data ? 'Yes' : 'No') . "</pre>";
    }
}

$page = new \Template(config: $config);
$page->setTemplate("layout/base.tpl.php");
$page->set("page_title", $puzzle_data ? "Puzzle #{$puzzle_data['puzzle_id']} - Slide Practice" : "Slide Practice - Free Puzzle Game");
$page->set("site_version", SENTIMENTAL_VERSION);

// Get the inner content
$inner_page = new \Template(config: $config);
$inner_page->setTemplate("index.tpl.php");
$inner_page->set("site_version", SENTIMENTAL_VERSION);
$inner_page->set("puzzle_id", $puzzle_data['puzzle_id'] ?? null);
$inner_page->set("puzzle_code", $puzzle_data['puzzle_code'] ?? null);
$inner_page->set("puzzle_data", $puzzle_data ? json_encode($puzzle_data) : 'null');

// Get adjacent puzzles for navigation
$prev_puzzle_code = null;
$next_puzzle_code = null;

if ($puzzle_data) {
    try {
        // Get previous puzzle (highest puzzle_id less than current)
        $stmt = $mla_database->prepare("SELECT puzzle_code FROM puzzles WHERE puzzle_id < ? ORDER BY puzzle_id DESC LIMIT 1");
        $stmt->execute([$puzzle_data['puzzle_id']]);
        $prev_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prev_result) {
            $prev_puzzle_code = $prev_result['puzzle_code'];
        }

        // Get next puzzle (lowest puzzle_id greater than current)
        $stmt = $mla_database->prepare("SELECT puzzle_code FROM puzzles WHERE puzzle_id > ? ORDER BY puzzle_id ASC LIMIT 1");
        $stmt->execute([$puzzle_data['puzzle_id']]);
        $next_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($next_result) {
            $next_puzzle_code = $next_result['puzzle_code'];
        }
    } catch (\Exception $e) {
        error_log("Error loading adjacent puzzles: " . $e->getMessage());
    }
}

$inner_page->set("prev_puzzle_code", $prev_puzzle_code);
$inner_page->set("next_puzzle_code", $next_puzzle_code);

if($is_logged_in->isLoggedIn()){
    $page->set("username", $is_logged_in->getLoggedInUsername());
    $inner_page->set("username", $is_logged_in->getLoggedInUsername());
} else {
    $page->set("username", "");
    $inner_page->set("username", "");
}

$page->set("page_content", $inner_page->grabTheGoods());

$page->echoToScreen();
exit;
