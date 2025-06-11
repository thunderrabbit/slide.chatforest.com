<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

$debugLevel = intval(value: $_GET['debug']) ?? 0;
if($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
}

if($is_logged_in->isLoggedIn()){

    echo "<h1>You're logged in</h1>";
    exit;
    // $page = new \Template(config: $config);

    // $page->setTemplate(template_file: "admin/index.tpl.php");
    // $page->echoToScreen();
} else {
    echo "<h1>Welcome to Marble Track 3</h1>";
    echo "<p><a href='/login/'>Click here to log in</a></p>";
    exit;
}
