<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

if ($is_logged_in->isLoggedIn()) {
    // We logged in.. yay! Check for last played puzzle
    $lastPuzzleCode = $_GET['return_to_puzzle'] ?? $_POST['return_to_puzzle'] ?? null;

    if ($lastPuzzleCode) {
        // Redirect to specific puzzle if provided
        header("Location: /puzzle/$lastPuzzleCode?returning=1");
        exit;
    }

    // Check if there's a referrer that might indicate the current puzzle
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (preg_match('#/puzzle/([a-zA-Z0-9]+)#', $referrer, $matches)) {
        $puzzleCode = $matches[1];
        header("Location: /puzzle/$puzzleCode?returning=1");
        exit;
    }

    // Fallback: redirect to main page
    header("Location: /?returning=1");
    exit;
} else {
    if(!$is_logged_in->isLoggedIn()){
        $page = new \Template(config: $config);
        $page->setTemplate("login/index.tpl.php");
        $page->echoToScreen();
        exit;
    }
}
