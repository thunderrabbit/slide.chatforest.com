<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

$debugLevel = intval(value: $_GET['debug']) ?? 0;
if($debugLevel > 0) {
    echo "<pre>Debug Level: $debugLevel</pre>";
}

if($is_logged_in->isLoggedIn()){

    echo "<h1>You're logged in</h1>";
    echo "<p><a href='/admin/'>Click here to admire admin page</a></p>";
    exit;
} else {
    echo "<h1>Welcome to This Here Brand New Web Site</h1>";
    echo "<p><a href='/login/'>Click here to log in</a></p>";
    exit;
}
