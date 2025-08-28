<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Free online Slide Practice puzzle game - solve path puzzles by connecting numbered cells in sequence"/>
    <title><?= $page_title ?? 'Slide Practice - Free Puzzle Game' ?></title>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/menu.css">
    <link rel="stylesheet" href="/css/slide-practice.css">
</head>
<body>
    <div class="NavBar">
        <a href="/">Slide Practice</a> |
<?php if(empty($username)): ?>
        <a href="/login/register.php">Sign Up</a> |
        <a href="/login/">Login</a>
<?php else: // if(empty($username)): ?>
        <a href="/profile/"><?= $username ?></a> |
        <a href="/logout/">Logout</a>
<?php endif; // if(empty($username)): ?>
    </div>
    <div class="PageWrapper">
        <?= $page_content ?>
    </div>
</body>
</html>
