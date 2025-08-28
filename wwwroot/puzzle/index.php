<?php

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

// Parse the URL to get puzzle ID from path like /puzzle/123
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim($request_uri, '/'));
$puzzle_id = null;
$puzzle_data = null;

// Look for numeric ID after 'puzzle' in the path
if (count($path_parts) >= 2 && $path_parts[0] === 'puzzle' && is_numeric($path_parts[1])) {
    $puzzle_id = (int)$path_parts[1];

    // Try to load the puzzle
    try {
        $puzzleManager = new PuzzleManager($mla_database);
        $puzzle_data = $puzzleManager->getPuzzle($puzzle_id);
    } catch (\Exception $e) {
        error_log("Error loading puzzle $puzzle_id: " . $e->getMessage());
    }
}

$debugLevel = intval($_GET['debug']) ?? 0;
if($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
    if ($puzzle_id) {
        echo "<pre>Puzzle ID: $puzzle_id</pre>";
        echo "<pre>Puzzle found: " . ($puzzle_data ? 'Yes' : 'No') . "</pre>";
    }
}

$page = new \Template(config: $config);
$page->setTemplate("layout/base.tpl.php");
$page->set("page_title", $puzzle_data ? "Puzzle #{$puzzle_id} - Slide Practice" : "Slide Practice - Free Puzzle Game");
$page->set("site_version", SENTIMENTAL_VERSION);

// Get the inner content
$inner_page = new \Template(config: $config);
$inner_page->setTemplate("index.tpl.php");
$inner_page->set("site_version", SENTIMENTAL_VERSION);
$inner_page->set("puzzle_id", $puzzle_id);
$inner_page->set("puzzle_data", $puzzle_data ? json_encode($puzzle_data) : 'null');

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
