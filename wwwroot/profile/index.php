<?php

# Must include here because DH runs FastCGI https://www.phind.com/search?cache=zfj8o8igbqvaj8cm91wp1b7k
include_once "/home/dh_fbrdk3/db.marbletrack3.com/prepend.php";

// Check if user is logged in
if (!$is_logged_in->isLoggedIn()) {
    header("Location: /login/");
    exit;
}

$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate input
    $errors = [];
    if (empty($current_password)) {
        $errors[] = "Current password is required.";
    }
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    }
    if (empty($confirm_password)) {
        $errors[] = "Password confirmation is required.";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    
    // If no validation errors, proceed with password change
    if (empty($errors)) {
        try {
            // Get current user's username to verify current password
            $username = $is_logged_in->getLoggedInUsername();
            $user_id = $is_logged_in->loggedInID();
            
            // Get current password hash from database
            $user_result = $mla_database->fetchResults(
                "SELECT `password_hash` FROM `users` WHERE `user_id` = ? LIMIT 1", 
                "i", 
                $user_id
            );
            
            if ($user_result->numRows() > 0) {
                $user_data = $user_result->toArray()[0];
                
                // Verify current password
                if (password_verify($current_password, $user_data['password_hash'])) {
                    // Hash new password
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password in database
                    $mla_database->executeSQL(
                        "UPDATE `users` SET `password_hash` = ? WHERE `user_id` = ?",
                        "si",
                        $new_password_hash,
                        $user_id
                    );
                    
                    $success_message = "Password changed successfully!";
                } else {
                    $errors[] = "Current password is incorrect.";
                }
            } else {
                $errors[] = "User not found.";
            }
        } catch (\Exception $e) {
            $errors[] = "An error occurred while changing password: " . $e->getMessage();
        }
    }
    
    // Set error message if there are errors
    if (!empty($errors)) {
        $error_message = implode("<br>", $errors);
    }
}

// Display the form
$page = new \Template(config: $config);
$page->setTemplate("profile/index.tpl.php");
$page->set("username", $is_logged_in->getLoggedInUsername());
$page->set("error_message", $error_message);
$page->set("success_message", $success_message);

$inner = $page->grabTheGoods();

$layout = new \Template(config: $config);
$layout->setTemplate("layout/admin_base.tpl.php");
$layout->set("page_title", "Change Password");
$layout->set("page_content", $inner);
$layout->echoToScreen();