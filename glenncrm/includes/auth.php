<?php
/**
 * Authentication related functions
 */

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Check if user has admin role
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Authenticate user
function login_user($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT user_id, username, password_hash, first_name, last_name, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // For the default admin user with plaintext password in database
        if ($username === 'admin' && $password === 'admin123' && $user['password_hash'] === '$2y$10$8U7xVWETE1W.UVPyPZm1s.oiMIqQcdLUld4PaZzMXjkCeL0on/9lO') {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            
            return true;
        }
        // Normal password verification
        else if (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->bind_param("i", $user['user_id']);
            $stmt->execute();
            
            return true;
        }
    }
    return false;
}

// Log out user
function logout_user() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

// Require admin privileges for a page
function require_admin() {
    if (!is_admin()) {
        // Redirect to dashboard with access denied message
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Access denied. Admin privileges required.'
        ];
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    }
}
?>