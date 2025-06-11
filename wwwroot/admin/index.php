<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

if ($is_logged_in->isLoggedIn()) {
    // We logged in.. yay!
    $page = new \Template(config: $config);
    $page->setTemplate("admin/index.tpl.php");
    $page->echoToScreen();
    exit;
} else {
    $page = new \Template(config: $config);
    $page->setTemplate("login/index.tpl.php");
    $page->echoToScreen();
    exit;
}
