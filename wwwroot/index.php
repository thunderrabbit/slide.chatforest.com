<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/prepend.php';

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
