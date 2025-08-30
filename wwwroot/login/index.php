<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

if ($is_logged_in->isLoggedIn()) {
    // We logged in.. yay! Check for last played puzzle
    echo "<script>
        const lastPuzzle = localStorage.getItem('lastPlayedPuzzle');
        if (lastPuzzle) {
            window.location.href = '/puzzle/' + lastPuzzle + '?returning=1';
        } else {
            window.location.href = '/?returning=1';
        }
    </script>";
    exit;
} else {
    if(!$is_logged_in->isLoggedIn()){
        $page = new \Template(config: $config);
        $page->setTemplate("login/index.tpl.php");
        $page->echoToScreen();
        exit;
    }
}
