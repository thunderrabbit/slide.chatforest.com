<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

$debugLevel = intval(value: $_GET['debug']) ?? 0;
if($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
}

if($is_logged_in->isLoggedIn()){

    $page = new \Template(config: $config);

    $page->setTemplate(template_file: "poster/index.tpl.php");
    $page->echoToScreen();
} else {
    $page = new \Template(config: $config);
    $page->setTemplate(template_file: "login/index.tpl.php");
    $page->echoToScreen();
}
