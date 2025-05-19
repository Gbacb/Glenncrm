<?php
// Require admin privileges
require_admin();

// Handle different actions for user management
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'list';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process different actions
switch ($action) {
    case 'add':
        // Handle adding a new user
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Validate CSRF token
            if (!validate_csrf_token($_POST['csrf_token'])) {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => 'Invalid form submission.'
                ];
                echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
                exit;
            }
            
            // Get form data
            $username = sanitize_input($_POST['username']);
            $email = sanitize_input($_POST['email']);
            $first_name = sanitize_input($_POST['first_name']);
            $last_name = sanitize_input($_POST['last_name']);
            $password = $_POST['password']; // Don't sanitize password
            $confirm_password = $_POST['confirm_password']; // Don't sanitize password
            $role = sanitize_input($_POST['role']);
            
            // Validate input
            $errors = [];
            
            if (empty($username)) {
                $errors[] = 'Username is required.';
            }
            
            if (empty($email)) {
                $errors[] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }
            
            if (empty($first_name)) {
                $errors[] = 'First name is required.';
            }
            
            if (empty($last_name)) {
                $errors[] = 'Last name is required.';
            }
            
            if (empty($password)) {
                $errors[] = 'Password is required.';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long.';
            }
            
            if ($password !== $confirm_password) {
                $errors[] = 'Passwords do not match.';
            }
            
            if (!in_array($role, ['admin', 'user'])) {
                $errors[] = 'Invalid role selected.';
            }
            
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = 'Username already exists. Please choose a different username.';
            }
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = 'Email address already in use. Please use a different email.';
            }
            
            // If no errors, add the user
            if (empty($errors)) {
                // Hash the password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert the user
                $stmt = $conn->prepare("INSERT INTO users (username, password_hash, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $username, $password_hash, $email, $first_name, $last_name, $role);
                $result = $stmt->execute();
                
                if ($result) {
                    // Log activity
                    $new_user_id = $conn->insert_id;
                    log_activity($_SESSION['user_id'], 'added', 'user', $new_user_id);
                    
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'User has been added successfully.'
                    ];
                    
                    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
                    exit;
                } else {
                    $errors[] = 'Failed to add user. Please try again.';
                }
            }
            
            // If there are errors, store them in session
            if (!empty($errors)) {
                $_SESSION['form_errors'] = $errors;
                $_SESSION['form_data'] = [
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'role' => $role
                ];
            }
        }
        
        // Get form data from session if available
        $form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [
            'username' => '',
            'email' => '',
            'first_name' => '',
            'last_name' => '',
            'role' => 'user'
        ];
        
        // Get form errors if any
        $form_errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
        
        // Clear session data
        unset($_SESSION['form_data']);
        unset($_SESSION['form_errors']);
        
        // Display the add user form
        ?>
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Add New User</h1>
                    </div>
                    <div class="col-sm-6">
                        <div class="float-end">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=users" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <!-- Display errors if any -->
                <?php if (!empty($form_errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle"></i> Please fix the following errors:</h5>
                        <ul>
                            <?php foreach ($form_errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-person-plus"></i> User Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo BASE_URL; ?>/index.php?page=users&action=add" method="post" class="needs-validation" novalidate>
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($form_data['username']); ?>" required>
                                    <div class="form-text">Username must be unique and will be used for login.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($form_data['first_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($form_data['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">User Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo ($form_data['role'] == 'user') ? 'selected' : ''; ?>>Regular User</option>
                                    <option value="admin" <?php echo ($form_data['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                                <div class="form-text">Administrators have full access to all system features.</div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Create User
                                </button>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=users" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <?php
        break;
        
    case 'edit':
        // Handle editing an existing user
        if ($user_id <= 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Invalid user ID.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
            exit;
        }
        
        // Get user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'User not found.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Validate CSRF token
            if (!validate_csrf_token($_POST['csrf_token'])) {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => 'Invalid form submission.'
                ];
                echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
                exit;
            }
            
            // Get form data
            $email = sanitize_input($_POST['email']);
            $first_name = sanitize_input($_POST['first_name']);
            $last_name = sanitize_input($_POST['last_name']);
            $role = sanitize_input($_POST['role']);
            $password = $_POST['password']; // Don't sanitize password
            $confirm_password = $_POST['confirm_password']; // Don't sanitize password
            
            // Validate input
            $errors = [];
            
            if (empty($email)) {
                $errors[] = 'Email is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }
            
            if (empty($first_name)) {
                $errors[] = 'First name is required.';
            }
            
            if (empty($last_name)) {
                $errors[] = 'Last name is required.';
            }
            
            if (!in_array($role, ['admin', 'user'])) {
                $errors[] = 'Invalid role selected.';
            }
            
            // Check if email already exists for different user
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = 'Email address already in use. Please use a different email.';
            }
            
            // If changing password, validate it
            $update_password = false;
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $errors[] = 'Password must be at least 8 characters long.';
                } elseif ($password !== $confirm_password) {
                    $errors[] = 'Passwords do not match.';
                } else {
                    $update_password = true;
                }
            }
            
            // If no errors, update the user
            if (empty($errors)) {
                // Start with basic user data
                $query = "UPDATE users SET email = ?, first_name = ?, last_name = ?, role = ? WHERE user_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssi", $email, $first_name, $last_name, $role, $user_id);
                $result = $stmt->execute();
                
                // If password is being updated, do it in a separate query
                if ($update_password) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $password_hash, $user_id);
                    $stmt->execute();
                }
                
                if ($result) {
                    // Log activity
                    log_activity($_SESSION['user_id'], 'updated', 'user', $user_id);
                    
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'User has been updated successfully.'
                    ];
                    
                    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
                    exit;
                } else {
                    $errors[] = 'Failed to update user. Please try again.';
                }
            }
            
            // If there are errors, store form data for redisplay
            if (!empty($errors)) {
                $_SESSION['form_errors'] = $errors;
            }
        }
        
        // Get form errors if any
        $form_errors = isset($_SESSION['form_errors']) ? $_SESSION['form_errors'] : [];
        
        // Clear session data
        unset($_SESSION['form_errors']);
        
        // Display the edit user form
        ?>
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Edit User</h1>
                    </div>
                    <div class="col-sm-6">
                        <div class="float-end">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=users" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <!-- Display errors if any -->
                <?php if (!empty($form_errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle"></i> Please fix the following errors:</h5>
                        <ul>
                            <?php foreach ($form_errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-person-gear"></i> User Information</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo BASE_URL; ?>/index.php?page=users&action=edit&id=<?php echo $user_id; ?>" method="post" class="needs-validation" novalidate>
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                                    <div class="form-text">Username cannot be changed.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <div class="form-text">Leave blank to keep current password. Password must be at least 8 characters long.</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">User Role *</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>Regular User</option>
                                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                                <div class="form-text">Administrators have full access to all system features.</div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update User
                                </button>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=users" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <?php
        break;
        
    case 'delete':
        // Handle user deletion
        if ($user_id <= 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Invalid user ID.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
            exit;
        }
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'User not found.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Prevent deleting own account
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'You cannot delete your own account.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
            exit;
        }
        
        // Prevent deleting default admin user
        if ($user['username'] == 'admin') {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'The default admin user cannot be deleted.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
            exit;
        }
        
        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Log activity
            log_activity($_SESSION['user_id'], 'deleted', 'user', $user_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'User has been deleted successfully.'
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Failed to delete user. The user might have associated records.'
            ];
        }
        
        echo '<script>window.location.href="'.BASE_URL.'/index.php?page=users";</script>';
        exit;
        
    default:
        // List all users
        $query = "SELECT * FROM users ORDER BY role DESC, first_name ASC, last_name ASC";
        $result = $conn->query($query);
        
        // Get user counts by role
        $counts = [
            'total' => 0,
            'admin' => 0,
            'user' => 0
        ];
        
        $count_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
        $count_result = $conn->query($count_query);
        
        while ($row = $count_result->fetch_assoc()) {
            $counts[$row['role']] = $row['count'];
            $counts['total'] += $row['count'];
        }
        ?>
        
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">User Management</h1>
                    </div>
                    <div class="col-sm-6">
                        <div class="float-end">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=users&action=add" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Add New User
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <!-- Show alerts if any -->
                <?php show_alert(); ?>
                
                <!-- Role summary cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white stats-card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>Total Users</h6>
                                    <h2><?php echo $counts['total']; ?></h2>
                                </div>
                                <div class="stats-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-success text-white stats-card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>Administrators</h6>
                                    <h2><?php echo $counts['admin']; ?></h2>
                                </div>
                                <div class="stats-icon">
                                    <i class="bi bi-shield-lock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-info text-white stats-card">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <h6>Regular Users</h6>
                                    <h2><?php echo $counts['user']; ?></h2>
                                </div>
                                <div class="stats-icon">
                                    <i class="bi bi-person"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title"><i class="bi bi-people"></i> System Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['role'] == 'admin'): ?>
                                                    <span class="badge bg-danger">Administrator</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">Regular User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo !empty($user['last_login']) ? format_date($user['last_login']) : 'Never'; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="<?php echo BASE_URL; ?>/index.php?page=users&action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <?php if ($user['user_id'] != $_SESSION['user_id'] && $user['username'] != 'admin'): ?>
                                                        <a href="<?php echo BASE_URL; ?>/index.php?page=users&action=delete&id=<?php echo $user['user_id']; ?>" 
                                                           class="btn btn-sm btn-danger confirm-delete"
                                                           onclick="return confirm('Are you sure you want to delete this user?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                    
                                    <?php if ($result->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No users found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php
}
?>