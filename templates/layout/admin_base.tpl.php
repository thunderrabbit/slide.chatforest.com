<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content=""/>
    <title><?= $page_title ?? 'MarbleTrack3 Admin' ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <div class="NavBar">
        <a href="/">View Site</a> |
        <a href="/admin/">Admin Site</a> |
        <a href="/admin/workers">Workers</a>
    </div>
    <div class="PageWrapper">
        <?= $page_content ?>
    </div>
</body>
</html>
