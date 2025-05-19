<?php
// Main application entry point
// Set error handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Custom error handler
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    if (strpos($errstr, 'log_activity') !== false) {
        // Silently ignore errors related to activity logging
        return true;
    }
    
    // Log the error to a file
    $error_message = date('Y-m-d H:i:s') . " - Error: [$errno] $errstr in $errfile on line $errline\n";
    error_log($error_message, 3, __DIR__ . '/logs/error.log');
    
    // For critical errors, display a user-friendly message
    if ($errno == E_ERROR || $errno == E_USER_ERROR) {
        echo '<div style="padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 20px;">
              <h3>System Error</h3>
              <p>Sorry, an error occurred. Please try again later or contact the administrator.</p>
              </div>';
    }
    
    // Don't execute PHP's internal error handler
    return true;
}

// Set custom error handler
set_error_handler("custom_error_handler");

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0775, true);
}

// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// The BASE_URL is already defined in database.php, we'll use that one
// If not defined, define it here as a fallback
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/glenncrm');
}

// Handle routing
$page = isset($_GET['page']) ? sanitize_input($_GET['page']) : 'dashboard';
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'view';

// Authentication check - redirect to login if not logged in
if (!is_logged_in() && $page != 'login') {
    header('Location: ' . BASE_URL . '/index.php?page=login');
    exit;
}

// Define allowed pages
$allowed_pages = [
    'dashboard', 'customers', 'leads', 'sales', 'interactions', 
    'reminders', 'reports', 'users', 'settings', 'login', 'logout'
];

// Validate requested page
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';  // Default to dashboard if invalid page
}

// Define page title
$page_titles = [
    'dashboard' => 'Dashboard',
    'customers' => 'Customer Management',
    'leads' => 'Lead Tracking',
    'sales' => 'Sales',
    'interactions' => 'Interactions',
    'reminders' => 'Reminders & Tasks',
    'reports' => 'Reports & Analytics',
    'users' => 'User Management',
    'settings' => 'System Settings',
    'login' => 'Login',
    'logout' => 'Logout'
];

$page_title = isset($page_titles[$page]) ? $page_titles[$page] : 'Glenn CRM';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_setting('company_name', 'Glenn CRM') . ' - ' . $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php if (is_logged_in() && $page != 'login'): ?>
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; ?>
    <?php endif; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <?php if (is_logged_in() && $page != 'login'): ?>
                <!-- Sidebar -->
                <div class="col-md-2">
                    <?php include 'includes/sidebar.php'; ?>
                </div>
                <!-- Main Content -->
                <div class="col-md-10">
                    <?php include 'pages/' . $page . '.php'; ?>
                </div>
            <?php else: ?>
                <!-- Login page uses full width -->
                <div class="col-12">
                    <?php include 'pages/' . $page . '.php'; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>