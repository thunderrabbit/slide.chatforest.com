<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

$debugLevel = intval(value: $_GET['debug']) ?? 0;
if($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
}

$page = new \Template(config: $config);
$page->setTemplate("layout/base.tpl.php");
$page->set("page_title", "Slide Practice - Free Puzzle Game");
$page->set("site_version", SENTIMENTAL_VERSION);
$page->set("firefox_cache_buster", $firefox_cache_buster);


// Get the inner content
$inner_page = new \Template(config: $config);
$inner_page->setTemplate("index.tpl.php");
$inner_page->set("site_version", SENTIMENTAL_VERSION);

// Cache busting for static files
$inner_page->set("firefox_cache_buster", $firefox_cache_buster);

// Handle grid size parameter
$selected_grid_size = intval($_GET['grid_size'] ?? 6);
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
