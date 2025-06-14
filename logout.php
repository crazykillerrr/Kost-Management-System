<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Log logout activity if user is logged in
if (isLoggedIn()) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                     VALUES (?, 'LOGOUT', 'User logged out', ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([getUserId(), $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Ignore logging errors during logout
    }
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: login.php?message=logout');
exit();
?>
