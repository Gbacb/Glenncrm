<?php
// Define database connection constants
define('DB_HOST', 'localhost'); // Changed from 'glenncrm_db' to 'localhost'
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'glenncrm_db');

// Error reporting settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Connect to the database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// Enable exceptions for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Define site URLs and paths
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/glenncrm');
define('BASE_PATH', dirname(__FILE__, 2));

// Date format for consistent display throughout the application
define('DATE_FORMAT', 'Y-m-d H:i:s');
define('DATE_FORMAT_SHORT', 'm/d/Y');

// Function to get settings from database
function get_setting($setting_name, $default = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_name = ?");
    $stmt->bind_param("s", $setting_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    return $default;
}

// Function to sanitize user input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>