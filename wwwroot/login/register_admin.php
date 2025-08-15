<?php

// This is only run if users table is empty
// We do *not* include prepend.php because
// it would cause a circular dependency

# Extract DreamHost project root: /home/username/domain.com
preg_match('#^(/home/[^/]+/[^/]+)#', __DIR__, $matches);
include_once $matches[1] . '/classes/Mlaphp/Autoloader.php';
// create autoloader instance and register the method with SPL
$autoloader = new \Mlaphp\Autoloader();
spl_autoload_register(array($autoloader, 'load'));

$mla_request = new \Mlaphp\Request();
$config = new \Config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // handle form submission...
    $mla_database = \Database\Base::getPDO($config);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['pass'] ?? '';
    $password_confirm = $_POST['pass_verify'] ?? '';

    // Validate input
    $errors = [];
    if (empty($username))
        $errors[] = "Username is required.";
    if (empty($password))
        $errors[] = "Password is required.";
    if ($password !== $password_confirm)
        $errors[] = "Passwords do not match.";

    // If errors, redisplay form with errors
    if (!empty($errors)) {
        echo "<h1>Registration Errors</h1><ul>";
        foreach ($errors as $e)
            echo "<li>" . htmlspecialchars($e) . "</li>";
        echo "</ul><a href=\"/\">Go back</a>";
        exit;
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        echo "<h1>Creating Admin User...</h1>";
        $stmt = $mla_database->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hash, "admin"]);

        echo "<h1>Admin Created</h1>";
        echo "<p>You can now <a href='/login'>log in</a> with your admin credentials.</p>";
    } catch (\PDOException $e) {
        if ($e->getCode() == '23000') { // Duplicate key error
            echo "<h1>Error</h1><p>User already exists. Try a different username.</p>";
        } else {
            echo "<h1>Unexpected Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        }
    }

    exit;

} else {
    header(header: "Location: /login/");
    exit;
}



