<?php
// Database setup check script
require_once 'config/database.php';

// Check if activity_log table exists
$check_table = $conn->query("SHOW TABLES LIKE 'activity_log'");
if ($check_table->num_rows == 0) {
    // Create the table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS activity_log (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id)
    )";
    
    if ($conn->query($create_table)) {
        echo "Activity log table created successfully.<br>";
    } else {
        echo "Error creating activity log table: " . $conn->error . "<br>";
    }
} else {
    // Check if the ip_address column exists
    $check_column = $conn->query("SHOW COLUMNS FROM activity_log LIKE 'ip_address'");
    if ($check_column->num_rows == 0) {
        // Add the ip_address column if it doesn't exist
        $add_column = "ALTER TABLE activity_log ADD COLUMN ip_address VARCHAR(45) NULL";
        if ($conn->query($add_column)) {
            echo "IP Address column added to activity log table.<br>";
        } else {
            echo "Error adding IP Address column: " . $conn->error . "<br>";
        }
    }
    
    // Check if the created_at column exists
    $check_column = $conn->query("SHOW COLUMNS FROM activity_log LIKE 'created_at'");
    if ($check_column->num_rows == 0) {
        // Add the created_at column if it doesn't exist
        $add_column = "ALTER TABLE activity_log ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        if ($conn->query($add_column)) {
            echo "Created At column added to activity log table.<br>";
        } else {
            echo "Error adding Created At column: " . $conn->error . "<br>";
        }
    }
    
    echo "Activity log table exists and has been checked for required columns.<br>";
}

// Check users table and default admin user
$check_admin = $conn->query("SELECT * FROM users WHERE username = 'admin'");
if ($check_admin->num_rows == 0) {
    // Insert admin user if it doesn't exist
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO users (username, password_hash, email, first_name, last_name, role) 
                     VALUES ('admin', '$password_hash', 'admin@example.com', 'Admin', 'User', 'admin')";
    if ($conn->query($insert_admin)) {
        echo "Default admin user created successfully.<br>";
    } else {
        echo "Error creating default admin user: " . $conn->error . "<br>";
    }
} else {
    echo "Default admin user exists.<br>";
}

echo "<br>Setup check complete. <a href='index.php'>Return to CRM</a>";
?>