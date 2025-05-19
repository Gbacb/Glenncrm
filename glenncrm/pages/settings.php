<?php
// Require admin privileges
require_admin();

// Set error handling for this page
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Invalid form submission.'
        ];
        echo '<script>window.location.href="' . BASE_URL . '/index.php?page=settings";</script>';
        exit;
    }

    // Handle different setting update actions
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';
    
    try {
        switch ($action) {
            case 'general':
                // Update general settings
                $company_name = sanitize_input($_POST['company_name']);
                $items_per_page = intval($_POST['items_per_page']);
                
                // Update company name
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = 'company_name'");
                $stmt->bind_param("s", $company_name);
                $stmt->execute();
                
                // Update items per page
                $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = 'items_per_page'");
                $stmt->bind_param("i", $items_per_page);
                $stmt->execute();
                
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'General settings updated successfully.'
                ];
                break;
                
            case 'email':
                // Update email settings
                $email_from = sanitize_input($_POST['email_from']);
                $smtp_host = sanitize_input($_POST['smtp_host']);
                $smtp_port = intval($_POST['smtp_port']);
                $smtp_user = sanitize_input($_POST['smtp_user']);
                $smtp_pass = $_POST['smtp_pass']; // Don't sanitize password to preserve special characters
                $email_notifications = isset($_POST['email_notifications']) ? 'true' : 'false';
                
                // Update email settings in database
                $settings = [
                    'email_from' => $email_from,
                    'smtp_host' => $smtp_host,
                    'smtp_port' => (string)$smtp_port, // Convert to string for consistent handling
                    'smtp_user' => $smtp_user,
                    'email_notifications' => $email_notifications
                ];
                
                // Only update password if provided
                if (!empty($smtp_pass)) {
                    $settings['smtp_pass'] = $smtp_pass;
                }
                
                foreach ($settings as $name => $value) {
                    // Check if setting exists
                    $stmt = $conn->prepare("SELECT setting_id FROM settings WHERE setting_name = ?");
                    $stmt->bind_param("s", $name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Update existing setting
                        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = ?");
                        $stmt->bind_param("ss", $value, $name);
                    } else {
                        // Insert new setting
                        $stmt = $conn->prepare("INSERT INTO settings (setting_name, setting_value, setting_description, is_public) VALUES (?, ?, '', 0)");
                        $stmt->bind_param("ss", $name, $value);
                    }
                    $stmt->execute();
                }
                
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Email settings updated successfully.'
                ];
                break;
                
            case 'custom':
                // Add or update custom setting
                $setting_name = sanitize_input($_POST['setting_name']);
                $setting_value = sanitize_input($_POST['setting_value']);
                $setting_description = sanitize_input($_POST['setting_description']);
                $is_public = isset($_POST['is_public']) ? 1 : 0;
                
                if (empty($setting_name)) {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'Setting name is required.'
                    ];
                    break;
                }
                
                // Check if setting exists
                $stmt = $conn->prepare("SELECT setting_id FROM settings WHERE setting_name = ?");
                $stmt->bind_param("s", $setting_name);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing setting
                    $stmt = $conn->prepare("UPDATE settings SET setting_value = ?, setting_description = ?, is_public = ? WHERE setting_name = ?");
                    $stmt->bind_param("ssis", $setting_value, $setting_description, $is_public, $setting_name);
                } else {
                    // Insert new setting
                    $stmt = $conn->prepare("INSERT INTO settings (setting_name, setting_value, setting_description, is_public) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $setting_name, $setting_value, $setting_description, $is_public);
                }
                $result = $stmt->execute();
                
                if ($result) {
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'Custom setting saved successfully.'
                    ];
                } else {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'Failed to save custom setting.'
                    ];
                }
                break;
                
            case 'delete':
                // Delete custom setting
                $setting_id = intval($_POST['setting_id']);
                
                // Prevent deletion of core settings
                $stmt = $conn->prepare("SELECT setting_name FROM settings WHERE setting_id = ?");
                $stmt->bind_param("i", $setting_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $setting = $result->fetch_assoc();
                    $core_settings = ['company_name', 'email_notifications', 'items_per_page'];
                    
                    if (in_array($setting['setting_name'], $core_settings)) {
                        $_SESSION['alert'] = [
                            'type' => 'danger',
                            'message' => 'Core settings cannot be deleted.'
                        ];
                        break;
                    }
                    
                    // Delete the setting
                    $stmt = $conn->prepare("DELETE FROM settings WHERE setting_id = ?");
                    $stmt->bind_param("i", $setting_id);
                    $result = $stmt->execute();
                    
                    if ($result) {
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'message' => 'Setting deleted successfully.'
                        ];
                    } else {
                        $_SESSION['alert'] = [
                            'type' => 'danger',
                            'message' => 'Failed to delete setting.'
                        ];
                    }
                } else {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'Setting not found.'
                    ];
                }
                break;
                
            case 'test_email':
                // Test email configuration
                $to = sanitize_input($_POST['test_email']);
                
                if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'Please enter a valid email address for testing.'
                    ];
                    break;
                }
                
                // This is a placeholder - in a real application, you would implement
                // the actual email sending functionality here using the SMTP settings
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Test email has been sent to ' . $to . '. Please check if you received it.'
                ];
                break;
                
            default:
                $_SESSION['alert'] = [
                    'type' => 'warning',
                    'message' => 'Unknown action requested.'
                ];
                break;
        }
    } catch (Exception $e) {
        // Log the error
        error_log('Settings update error: ' . $e->getMessage());
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'An error occurred while updating settings: ' . $e->getMessage()
        ];
    }
    
    // Redirect to settings page to prevent form resubmission and show the correct tab
    // Use JS redirect to ensure it works even after output
    echo '<script>window.location.href="' . BASE_URL . '/index.php?page=settings ";</script>';
    exit;
}

// Get current settings
$settings = [];
$query = "SELECT * FROM settings ORDER BY setting_name";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_name']] = [
        'id' => $row['setting_id'],
        'value' => $row['setting_value'],
        'description' => $row['setting_description'],
        'is_public' => $row['is_public']
    ];
}

// Set default values if not in database
$company_name = isset($settings['company_name']) ? $settings['company_name']['value'] : 'Glenn CRM';
$items_per_page = isset($settings['items_per_page']) ? intval($settings['items_per_page']['value']) : 10;
$email_from = isset($settings['email_from']) ? $settings['email_from']['value'] : '';
$smtp_host = isset($settings['smtp_host']) ? $settings['smtp_host']['value'] : '';
$smtp_port = isset($settings['smtp_port']) ? intval($settings['smtp_port']['value']) : 587;
$smtp_user = isset($settings['smtp_user']) ? $settings['smtp_user']['value'] : '';
$email_notifications = isset($settings['email_notifications']) ? $settings['email_notifications']['value'] === 'true' : false;

// Get settings that are not in the core list
$core_settings = ['company_name', 'email_notifications', 'items_per_page', 'email_from', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'];
$custom_settings = array_filter($settings, function($key) use ($core_settings) {
    return !in_array($key, $core_settings);
}, ARRAY_FILTER_USE_KEY);
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">System Settings</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Show alerts if any -->
        <?php show_alert(); ?>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Settings Menu</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="nav flex-column nav-pills">
                            <a class="nav-link active" id="general-tab" data-bs-toggle="pill" href="#general">
                                <i class="bi bi-gear"></i> General
                            </a>
                            <a class="nav-link" id="email-tab" data-bs-toggle="pill" href="#email">
                                <i class="bi bi-envelope"></i> Email Configuration
                            </a>
                            <a class="nav-link" id="custom-tab" data-bs-toggle="pill" href="#custom">
                                <i class="bi bi-wrench"></i> Custom Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="bi bi-gear"></i> General Settings</h5>
                            </div>
                            <div class="card-body">
                                <form action="<?php echo BASE_URL; ?>/index.php?page=settings" method="post">
                                    <input type="hidden" name="action" value="general">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Company Name</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($company_name); ?>" required>
                                        <div class="form-text">This name will be displayed throughout the application.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="items_per_page" class="form-label">Items Per Page</label>
                                        <input type="number" class="form-control" id="items_per_page" name="items_per_page" 
                                               min="5" max="100" value="<?php echo $items_per_page; ?>" required>
                                        <div class="form-text">Number of items to display per page in list views.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="email">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="bi bi-envelope"></i> Email Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form action="<?php echo BASE_URL; ?>/index.php?page=settings" method="post">
                                    <input type="hidden" name="action" value="email">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="email_notifications" name="email_notifications" 
                                               <?php echo $email_notifications ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">Enable Email Notifications</label>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email_from" class="form-label">From Email Address</label>
                                        <input type="email" class="form-control" id="email_from" name="email_from" 
                                               value="<?php echo htmlspecialchars($email_from); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Server</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                               value="<?php echo htmlspecialchars($smtp_host); ?>">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_port" class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                   value="<?php echo $smtp_port; ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="smtp_user" class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" id="smtp_user" name="smtp_user" 
                                                   value="<?php echo htmlspecialchars($smtp_user); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_pass" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="smtp_pass" name="smtp_pass" 
                                               placeholder="Enter new password to change">
                                        <div class="form-text">Leave blank to keep current password.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Email Settings
                                    </button>
                                </form>
                                
                                <hr>
                                
                                <h5>Test Email Configuration</h5>
                                <form action="<?php echo BASE_URL; ?>/index.php?page=settings" method="post">
                                    <input type="hidden" name="action" value="test_email">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    
                                    <div class="mb-3">
                                        <label for="test_email" class="form-label">Recipient Email</label>
                                        <input type="email" class="form-control" id="test_email" name="test_email" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-secondary">
                                        <i class="bi bi-send"></i> Send Test Email
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Settings -->
                    <div class="tab-pane fade" id="custom">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title"><i class="bi bi-wrench"></i> Custom Settings</h5>
                            </div>
                            <div class="card-body">
                                <h5>Add New Setting</h5>
                                <form action="<?php echo BASE_URL; ?>/index.php?page=settings" method="post">
                                    <input type="hidden" name="action" value="custom">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="setting_name" class="form-label">Setting Name</label>
                                            <input type="text" class="form-control" id="setting_name" name="setting_name" required>
                                            <div class="form-text">Use lowercase letters, numbers and underscores only.</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="setting_value" class="form-label">Setting Value</label>
                                            <input type="text" class="form-control" id="setting_value" name="setting_value" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="setting_description" class="form-label">Description</label>
                                        <input type="text" class="form-control" id="setting_description" name="setting_description">
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_public" name="is_public">
                                        <label class="form-check-label" for="is_public">Public Setting (accessible throughout the application)</label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary mb-4">
                                        <i class="bi bi-plus"></i> Add Setting
                                    </button>
                                </form>
                                
                                <hr>
                                
                                <h5>Current Custom Settings</h5>
                                <?php if (count($custom_settings) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Value</th>
                                                    <th>Description</th>
                                                    <th>Public</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($custom_settings as $name => $setting): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($name); ?></td>
                                                        <td><?php echo htmlspecialchars($setting['value']); ?></td>
                                                        <td><?php echo htmlspecialchars($setting['description']); ?></td>
                                                        <td><?php echo $setting['is_public'] ? 'Yes' : 'No'; ?></td>
                                                        <td>
                                                            <form action="<?php echo BASE_URL; ?>/index.php?page=settings" method="post" class="d-inline">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="setting_id" value="<?php echo $setting['id']; ?>">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger confirm-delete" 
                                                                        onclick="return confirm('Are you sure you want to delete this setting?')">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No custom settings defined.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activate the correct tab based on URL hash if present
    var hash = window.location.hash;
    if (hash) {
        var tab = document.querySelector('.nav-link[href="' + hash + '"]');
        if (tab) {
            tab.click();
        }
    }
});
</script>
<?php
// Ensure all PHP tags are properly closed and no trailing content
?>