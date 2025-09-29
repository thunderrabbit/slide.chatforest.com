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
$page->set("firefox_cache_buster", $firefox_cache_buster);

// Get the inner content
$inner_page = new \Template(config: $config);
$inner_page->setTemplate("index.tpl.php");
$inner_page->set("site_version", SENTIMENTAL_VERSION);
$inner_page->set("firefox_cache_buster", $firefox_cache_buster);
$inner_page->set("puzzle_id", $puzzle_data['puzzle_id'] ?? null);
$inner_page->set("puzzle_code", $puzzle_data['puzzle_code'] ?? null);
$inner_page->set("puzzle_data", $puzzle_data ? json_encode($puzzle_data) : 'null');

// Get adjacent puzzles for navigation (same grid size only)
$prev_puzzle_code = null;
$next_puzzle_code = null;

if ($puzzle_data) {
    try {
        $current_grid_size = $puzzle_data['grid_size'];

        // Get previous puzzle (highest puzzle_id less than current, same grid size)
        $stmt = $mla_database->prepare("SELECT puzzle_code FROM puzzles WHERE puzzle_id < ? AND grid_size = ? ORDER BY puzzle_id DESC LIMIT 1");
        $stmt->execute([$puzzle_data['puzzle_id'], $current_grid_size]);
        $prev_result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prev_result) {
            $prev_puzzle_code = $prev_result['puzzle_code'];
        }

        // Get next puzzle (lowest puzzle_id greater than current, same grid size)
        $stmt = $mla_database->prepare("SELECT puzzle_code FROM puzzles WHERE puzzle_id > ? AND grid_size = ? ORDER BY puzzle_id ASC LIMIT 1");
        $stmt->execute([$puzzle_data['puzzle_id'], $current_grid_size]);
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

// Handle grid size parameter - use puzzle's actual size or URL parameter
$selected_grid_size = null;
if ($puzzle_data) {
    $selected_grid_size = $puzzle_data['grid_size']; // Use actual puzzle size
} else {
    $selected_grid_size = intval($_GET['grid_size'] ?? 6); // Fallback to URL param or default
}
if ($selected_grid_size < 5 || $selected_grid_size > 8) {
    $selected_grid_size = 6; // Default to 6x6
}
$inner_page->set("selected_grid_size", $selected_grid_size);

if($is_logged_in->isLoggedIn()){
    $page->set("username", $is_logged_in->getLoggedInUsername());
    $inner_page->set("username", $is_logged_in->getLoggedInUsername());
    $inner_page->set("is_admin", $is_logged_in->isAdmin());

    // Check if user is experienced (3+ solved puzzles) for auto-hide UI feature
    $experienceChecker = new AreYouExperienced($mla_database);
    $is_experienced = $experienceChecker->DesuKa($is_logged_in->loggedInID());
    $inner_page->set("is_experienced", $is_experienced);
} else {
    $page->set("username", "");
    $inner_page->set("username", "");
    $inner_page->set("is_admin", false);
    $inner_page->set("is_experienced", false);
}

$page->set("page_content", $inner_page->grabTheGoods());

$page->echoToScreen();
exit;
