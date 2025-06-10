<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

$is_logged_in->logout();
// We logged out.. yay!
header(header: "Location: /login/");
exit;
