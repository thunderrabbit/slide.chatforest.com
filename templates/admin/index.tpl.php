<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Welcome <?= $username ?>!</title>
        <meta name="title" content="Welcome <?= $username ?>!"/>
        <meta name="description" content=""/>
        <link rel="stylesheet" href="/css/styles.css">
    </head>
    <body><!-- Welcome <?= $username ?>! form area -->
        <div class="PageWrapper">
        <div class="PagePanel">
                What's up <?= $username ?>? <br />
            </div>
            <div class="PagePanel">
                <a href="/logout/">Logout</a> <br />
            </div>
        </div>
        <div class="fix">
            <p>Sentimental version: <?= $site_version ?></p>
        </div>
    </body>
</html>
