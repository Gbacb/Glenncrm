<?php
// Process login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password to preserve special characters
    
    if (login_user($username, $password)) {
        // Log the login activity - only after successful login when user_id is set
        if (isset($_SESSION['user_id'])) {
            log_activity($_SESSION['user_id'], 'logged in', 'system');
        }
        
        // Successful login
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    } else {
        // Login failed
        $error_message = 'Invalid username or password';
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card login-card">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h3><?php echo get_setting('company_name', 'Glenn CRM'); ?></h3>
                        <p class="text-muted">Sign in to your account</p>
                    </div>
                    
                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?php echo BASE_URL; ?>/index.php?page=login">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                        </div>
                    </form>
                </div>
            </div>
              <div class="text-center mt-3">
                <p class="text-muted">
                    Default login: admin / admin123
                </p>
                <p class="text-muted small">
                    Having trouble? <a href="<?php echo BASE_URL; ?>/setup-check.php">Run setup check</a>
                </p>
            </div>
        </div>
    </div>
</div>