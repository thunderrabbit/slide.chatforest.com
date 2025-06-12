<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

if ($is_logged_in->isLoggedIn()) {
    $page = new \Template($config);
    $page->setTemplate("admin/workers/index.tpl.php");
    $inner = $page->grabTheGoods();

    $layout = new \Template($config);
    $layout->setTemplate("layout/admin_base.tpl.php");
    $layout->set("page_title", "Workers");
    $layout->set("page_content", $inner);
    $layout->echoToScreen();
    exit;
} else {
    header("Location: /login/");
    exit;
}
