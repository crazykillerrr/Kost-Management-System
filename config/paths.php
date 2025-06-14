<?php
/**
 * Path Configuration
 * Handles relative paths for different directory levels
 */

// Get the current script path
$current_path = $_SERVER['PHP_SELF'];

// Determine base path based on current directory
if (strpos($current_path, '/admin/') !== false) {
    // We're in admin directory
    define('BASE_PATH', '../');
    define('ADMIN_PATH', '');
} else {
    // We're in root directory
    define('BASE_PATH', '');
    define('ADMIN_PATH', 'admin/');
}

// Define common paths
define('CONFIG_PATH', BASE_PATH . 'config/');
define('INCLUDES_PATH', BASE_PATH . 'includes/');
define('UPLOADS_PATH', BASE_PATH . 'uploads/');

// URL paths for links
define('BASE_URL', BASE_PATH);
define('ADMIN_URL', BASE_PATH . 'admin/');
?>
