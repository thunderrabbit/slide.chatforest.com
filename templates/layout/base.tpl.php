<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Free online Slide Practice puzzle game - solve path puzzles by connecting numbered cells in sequence"/>
    <title><?= $page_title ?? 'Slide Practice - Free Puzzle Game' ?></title>
    <link rel="stylesheet" href="/css/styles.css?v=<?= $firefox_cache_buster ?? 1 ?>">
    <link rel="stylesheet" href="/css/menu.css?v=<?= $firefox_cache_buster ?? 1 ?>">
    <link rel="stylesheet" href="/css/slide-practice.css?v=<?= $firefox_cache_buster ?? 1 ?>">
</head>
<body>
    <div class="NavBar">
        <a href="/">Slide Practice</a> |
<?php if(empty($username)): ?>
        <a href="/login/register.php" id="signup-link">Sign Up</a> |
        <a href="/login/" id="login-link">Login</a>
<?php else: // if(empty($username)): ?>
        <a href="/profile/"><?= $username ?></a> |
        <a href="/logout/">Logout</a>
<?php endif; // if(empty($username)): ?>
    </div>
    
    <script>
    // Preserve current puzzle when clicking login/signup links
    document.addEventListener('DOMContentLoaded', function() {
        const loginLink = document.getElementById('login-link');
        const signupLink = document.getElementById('signup-link');
        const currentPuzzle = localStorage.getItem('lastPlayedPuzzle');
        
        if (currentPuzzle && loginLink) {
            loginLink.href = '/login/?return_to_puzzle=' + currentPuzzle;
        }
        
        if (currentPuzzle && signupLink) {
            signupLink.href = '/login/register.php?return_to_puzzle=' + currentPuzzle;
        }
    });
    </script>
    <div class="PageWrapper">
        <?= $page_content ?>
    </div>
</body>
</html>
