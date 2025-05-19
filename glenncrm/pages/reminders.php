<?php
// Handle different actions for reminder management
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'list';
$reminder_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Determine current page for pagination
$current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$items_per_page = intval(get_setting('items_per_page', 10));
$offset = ($current_page - 1) * $items_per_page;

// Get current user ID
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

switch ($action) {
    case 'add':
        // Process form submission for adding a new reminder
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Validate CSRF token
            if (!validate_csrf_token($_POST['csrf_token'])) {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => 'Invalid form submission.'
                ];
                echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
                exit;
            }
            
            // Get and sanitize form data
            $title = sanitize_input($_POST['title']);
            $message = sanitize_input($_POST['message']);
            $reminder_date = sanitize_input($_POST['reminder_date']);
            $reminder_time = sanitize_input($_POST['reminder_time']);
            $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
            $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : null;
            $assigned_user_id = !empty($_POST['user_id']) && $is_admin ? intval($_POST['user_id']) : $user_id;
            
            // Validation
            $error = false;
            $error_message = '';
            
            if (empty($title)) {
                $error = true;
                $error_message = 'Reminder title is required.';
            } elseif (empty($reminder_date) || empty($reminder_time)) {
                $error = true;
                $error_message = 'Reminder date and time are required.';
            } else {
                // Combine date and time
                $datetime = $reminder_date . ' ' . $reminder_time . ':00';
                
                // Check if date format is valid
                if (!strtotime($datetime)) {
                    $error = true;
                    $error_message = 'Invalid date or time format.';
                }
            }
            
            if (!$error) {
                // Combine date and time for DB
                $reminder_datetime = date('Y-m-d H:i:s', strtotime($reminder_date . ' ' . $reminder_time));
                
                // Insert new reminder
                $stmt = $conn->prepare("INSERT INTO reminders (user_id, customer_id, lead_id, title, reminder_date, message, is_completed, sent) VALUES (?, ?, ?, ?, ?, ?, 0, 0)");
                $stmt->bind_param("iiisss", $assigned_user_id, $customer_id, $lead_id, $title, $reminder_datetime, $message);
                $result = $stmt->execute();
                
                if ($result) {
                    $new_reminder_id = $conn->insert_id;
                    
                    // Log activity
                    log_activity($_SESSION['user_id'], 'added', 'reminder', $new_reminder_id);
                    
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'Reminder has been added successfully.'
                    ];
                    
                    // Redirect to reminders list
                    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
                    exit;
                } else {
                    $error_message = 'Failed to add reminder. Please try again.';
                }
            }
        }
        
        // Get customer list for dropdown
        $customer_query = $is_admin ? 
            "SELECT customer_id, name FROM customers ORDER BY name ASC" : 
            "SELECT customer_id, name FROM customers WHERE assigned_to = ? ORDER BY name ASC";
        $stmt = $conn->prepare($customer_query);
        
        if (!$is_admin) {
            $stmt->bind_param("i", $user_id);
        }
        
        $stmt->execute();
        $customers = $stmt->get_result();
        
        // Get user list for dropdown (admin only)
        if ($is_admin) {
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name");
            $stmt->execute();
            $users = $stmt->get_result();
        }
        ?>
        
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Add New Reminder</h1>
                    </div>
                    <div class="col-sm-6">
                        <div class="float-end">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Reminders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-bell-plus"></i> Reminder Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add" method="post" class="needs-validation" novalidate>
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Reminder Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($_POST['title']) ? $_POST['title'] : ''; ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter a title for this reminder.
                                    </div>
                                </div>
                                
                                <?php if ($is_admin): ?>
                                <div class="col-md-4 mb-3">
                                    <label for="user_id" class="form-label">Assign To</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <?php while ($user_row = $users->fetch_assoc()): ?>
                                            <option value="<?php echo $user_row['user_id']; ?>" <?php echo $user_row['user_id'] == $user_id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user_row['first_name'] . ' ' . $user_row['last_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="reminder_date" class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="reminder_date" name="reminder_date" value="<?php echo isset($_POST['reminder_date']) ? $_POST['reminder_date'] : date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">
                                        Please select a date.
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="reminder_time" class="form-label">Time *</label>
                                    <input type="time" class="form-control" id="reminder_time" name="reminder_time" value="<?php echo isset($_POST['reminder_time']) ? $_POST['reminder_time'] : date('H:i'); ?>" required>
                                    <div class="invalid-feedback">
                                        Please select a time.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="customer_id" class="form-label">Related Customer</label>
                                    <select class="form-select" id="customer_id" name="customer_id" onchange="loadCustomerLeads(this.value, 'lead_id')">
                                        <option value="">-- Select Customer (Optional) --</option>
                                        <?php while ($customer = $customers->fetch_assoc()): ?>
                                            <option value="<?php echo $customer['customer_id']; ?>" <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['customer_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="lead_id" class="form-label">Related Lead</label>
                                    <select class="form-select" id="lead_id" name="lead_id">
                                        <option value="">-- Select Lead (Optional) --</option>
                                        <!-- Will be populated via JavaScript based on selected customer -->
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Reminder Message</label>
                                <textarea class="form-control" id="message" name="message" rows="3"><?php echo isset($_POST['message']) ? $_POST['message'] : ''; ?></textarea>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Reminder
                                </button>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <?php
        break;
        
    case 'edit':
        // Check if reminder exists and belongs to current user or user is admin
        $stmt = $conn->prepare("SELECT * FROM reminders WHERE reminder_id = ?");
        $stmt->bind_param("i", $reminder_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Reminder not found
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Reminder not found.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
            exit;
        }
        
        $reminder = $result->fetch_assoc();
        
        // Check if user has permission to edit this reminder
        if (!$is_admin && $reminder['user_id'] != $user_id) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to edit this reminder.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
            exit;
        }
        
        // Process form submission for updating reminder
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Validate CSRF token
            if (!validate_csrf_token($_POST['csrf_token'])) {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => 'Invalid form submission.'
                ];
                echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
                exit;
            }
            
            // Get and sanitize form data
            $title = sanitize_input($_POST['title']);
            $message = sanitize_input($_POST['message']);
            $reminder_date = sanitize_input($_POST['reminder_date']);
            $reminder_time = sanitize_input($_POST['reminder_time']);
            $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
            $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : null;
            $is_completed = isset($_POST['is_completed']) ? 1 : 0;
            $assigned_user_id = !empty($_POST['user_id']) && $is_admin ? intval($_POST['user_id']) : $reminder['user_id'];
            
            // Validation
            $error = false;
            $error_message = '';
            
            if (empty($title)) {
                $error = true;
                $error_message = 'Reminder title is required.';
            } elseif (empty($reminder_date) || empty($reminder_time)) {
                $error = true;
                $error_message = 'Reminder date and time are required.';
            } else {
                // Combine date and time
                $datetime = $reminder_date . ' ' . $reminder_time . ':00';
                
                // Check if date format is valid
                if (!strtotime($datetime)) {
                    $error = true;
                    $error_message = 'Invalid date or time format.';
                }
            }
            
            if (!$error) {
                // Combine date and time for DB
                $reminder_datetime = date('Y-m-d H:i:s', strtotime($reminder_date . ' ' . $reminder_time));
                
                // Update reminder
                $stmt = $conn->prepare("UPDATE reminders SET user_id = ?, customer_id = ?, lead_id = ?, title = ?, reminder_date = ?, message = ?, is_completed = ? WHERE reminder_id = ?");
                $stmt->bind_param("iiisssis", $assigned_user_id, $customer_id, $lead_id, $title, $reminder_datetime, $message, $is_completed, $reminder_id);
                $result = $stmt->execute();
                
                if ($result) {
                    // Log activity
                    log_activity($_SESSION['user_id'], 'updated', 'reminder', $reminder_id);
                    
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'Reminder has been updated successfully.'
                    ];
                    
                    // Redirect to reminders list
                    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
                    exit;
                } else {
                    $error_message = 'Failed to update reminder. Please try again.';
                }
            }
        }
        
        // Extract date and time from reminder_date for form
        $reminder_date_formatted = date('Y-m-d', strtotime($reminder['reminder_date']));
        $reminder_time_formatted = date('H:i', strtotime($reminder['reminder_date']));
        
        // Get customer list for dropdown
        $customer_query = $is_admin ? 
            "SELECT customer_id, name FROM customers ORDER BY name ASC" : 
            "SELECT customer_id, name FROM customers WHERE assigned_to = ? ORDER BY name ASC";
        $stmt = $conn->prepare($customer_query);
        
        if (!$is_admin) {
            $stmt->bind_param("i", $user_id);
        }
        
        $stmt->execute();
        $customers = $stmt->get_result();
        
        // Get leads for the selected customer
        if ($reminder['customer_id']) {
            $stmt = $conn->prepare("SELECT lead_id, title FROM leads WHERE customer_id = ? ORDER BY title ASC");
            $stmt->bind_param("i", $reminder['customer_id']);
            $stmt->execute();
            $leads = $stmt->get_result();
        }
        
        // Get user list for dropdown (admin only)
        if ($is_admin) {
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name");
            $stmt->execute();
            $users = $stmt->get_result();
        }
        ?>
        
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Edit Reminder</h1>
                    </div>
                    <div class="col-sm-6">
                        <div class="float-end">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Reminders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-bell"></i> Reminder Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="<?php echo BASE_URL; ?>/index.php?page=reminders&action=edit&id=<?php echo $reminder_id; ?>" method="post" class="needs-validation" novalidate>
                            <!-- CSRF protection -->
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="title" class="form-label">Reminder Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($reminder['title']); ?>" required>
                                    <div class="invalid-feedback">
                                        Please enter a title for this reminder.
                                    </div>
                                </div>
                                
                                <?php if ($is_admin): ?>
                                <div class="col-md-4 mb-3">
                                    <label for="user_id" class="form-label">Assigned To</label>
                                    <select class="form-select" id="user_id" name="user_id">
                                        <?php while ($user_row = $users->fetch_assoc()): ?>
                                            <option value="<?php echo $user_row['user_id']; ?>" <?php echo $user_row['user_id'] == $reminder['user_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user_row['first_name'] . ' ' . $user_row['last_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="reminder_date" class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="reminder_date" name="reminder_date" value="<?php echo $reminder_date_formatted; ?>" required>
                                    <div class="invalid-feedback">
                                        Please select a date.
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="reminder_time" class="form-label">Time *</label>
                                    <input type="time" class="form-control" id="reminder_time" name="reminder_time" value="<?php echo $reminder_time_formatted; ?>" required>
                                    <div class="invalid-feedback">
                                        Please select a time.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="customer_id" class="form-label">Related Customer</label>
                                    <select class="form-select" id="customer_id" name="customer_id" onchange="loadCustomerLeads(this.value, 'lead_id')">
                                        <option value="">-- Select Customer (Optional) --</option>
                                        <?php while ($customer = $customers->fetch_assoc()): ?>
                                            <option value="<?php echo $customer['customer_id']; ?>" <?php echo $customer['customer_id'] == $reminder['customer_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="lead_id" class="form-label">Related Lead</label>
                                    <select class="form-select" id="lead_id" name="lead_id">
                                        <option value="">-- Select Lead (Optional) --</option>
                                        <?php if (isset($leads) && $leads->num_rows > 0): ?>
                                            <?php while ($lead = $leads->fetch_assoc()): ?>
                                                <option value="<?php echo $lead['lead_id']; ?>" <?php echo $lead['lead_id'] == $reminder['lead_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($lead['title']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Reminder Message</label>
                                <textarea class="form-control" id="message" name="message" rows="3"><?php echo htmlspecialchars($reminder['message']); ?></textarea>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_completed" name="is_completed" <?php echo $reminder['is_completed'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_completed">Mark as completed</label>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Reminder
                                </button>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        <?php
        break;
        
    case 'complete':
        // Mark a reminder as completed
        if ($reminder_id > 0) {
            // Check if reminder exists and belongs to current user or user is admin
            $stmt = $conn->prepare("SELECT * FROM reminders WHERE reminder_id = ?");
            $stmt->bind_param("i", $reminder_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                // Reminder not found
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => 'Reminder not found.'
                ];
            } else {
                $reminder = $result->fetch_assoc();
                
                // Check if user has permission to edit this reminder
                if (!$is_admin && $reminder['user_id'] != $user_id) {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'You do not have permission to complete this reminder.'
                    ];
                } else {
                    // Update reminder status
                    $stmt = $conn->prepare("UPDATE reminders SET is_completed = 1 WHERE reminder_id = ?");
                    $stmt->bind_param("i", $reminder_id);
                    $result = $stmt->execute();
                    
                    if ($result) {
                        // Log activity
                        log_activity($user_id, 'completed', 'reminder', $reminder_id);
                        
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'message' => 'Reminder marked as completed.'
                        ];
                    } else {
                        $_SESSION['alert'] = [
                            'type' => 'danger',
                            'message' => 'Failed to update reminder status. Please try again.'
                        ];
                    }
                }
            }
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Invalid reminder ID.'
            ];
        }
        
        echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
        exit;
        break;
        
    case 'delete':
        // Delete a reminder
        if ($reminder_id > 0) {
            // Check if reminder exists and belongs to current user or user is admin
            $stmt = $conn->prepare("SELECT * FROM reminders WHERE reminder_id = ?");
            $stmt->bind_param("i", $reminder_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                // Reminder not found
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => 'Reminder not found.'
                ];
            } else {
                $reminder = $result->fetch_assoc();
                
                // Check if user has permission to delete this reminder
                if (!$is_admin && $reminder['user_id'] != $user_id) {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'You do not have permission to delete this reminder.'
                    ];
                } else {
                    // Delete reminder
                    $stmt = $conn->prepare("DELETE FROM reminders WHERE reminder_id = ?");
                    $stmt->bind_param("i", $reminder_id);
                    $result = $stmt->execute();
                    
                    if ($result) {
                        // Log activity
                        log_activity($user_id, 'deleted', 'reminder', $reminder_id);
                        
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'message' => 'Reminder deleted successfully.'
                        ];
                    } else {
                        $_SESSION['alert'] = [
                            'type' => 'danger',
                            'message' => 'Failed to delete reminder. Please try again.'
                        ];
                    }
                }
            }
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Invalid reminder ID.'
            ];
        }
        
        echo '<script>window.location.href="'.BASE_URL.'/index.php?page=reminders";</script>';
        exit;
        break;
        
    default: // list view
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
        $date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';
        $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        $user_filter = isset($_GET['user_id']) && $is_admin ? intval($_GET['user_id']) : 0;
        
        // Build query conditions
        $conditions = [];
        $params = [];
        $param_types = '';
        
        // If not admin, show only user's reminders
        if (!$is_admin) {
            $conditions[] = "r.user_id = ?";
            $params[] = $user_id;
            $param_types .= 'i';
        } elseif ($user_filter > 0) {
            // If admin and user filter selected
            $conditions[] = "r.user_id = ?";
            $params[] = $user_filter;
            $param_types .= 'i';
        }
        
        // Filter by status
        if ($status_filter === 'active') {
            $conditions[] = "r.is_completed = 0";
        } elseif ($status_filter === 'completed') {
            $conditions[] = "r.is_completed = 1";
        }
        
        // Filter by date
        if ($date_filter === 'today') {
            $conditions[] = "DATE(r.reminder_date) = CURDATE()";
        } elseif ($date_filter === 'tomorrow') {
            $conditions[] = "DATE(r.reminder_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
        } elseif ($date_filter === 'week') {
            $conditions[] = "DATE(r.reminder_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($date_filter === 'overdue') {
            $conditions[] = "r.reminder_date < NOW() AND r.is_completed = 0";
        }
        
        // Search
        if (!empty($search)) {
            $search_term = "%$search%";
            $conditions[] = "(r.title LIKE ? OR r.message LIKE ? OR c.name LIKE ? OR l.title LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $param_types .= 'ssss';
        }
        
        $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
        
        // Count total records for pagination
        $count_query = "SELECT COUNT(*) as total FROM reminders r 
                       LEFT JOIN customers c ON r.customer_id = c.customer_id
                       LEFT JOIN leads l ON r.lead_id = l.lead_id"
                       . $where_clause;
        $stmt = $conn->prepare($count_query);
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_items = $row['total'];
        
        // Get reminders with pagination
        $query = "SELECT r.*, c.name as customer_name, l.title as lead_title, u.first_name, u.last_name 
                 FROM reminders r 
                 LEFT JOIN customers c ON r.customer_id = c.customer_id
                 LEFT JOIN leads l ON r.lead_id = l.lead_id
                 LEFT JOIN users u ON r.user_id = u.user_id" 
                 . $where_clause . 
                 " ORDER BY r.reminder_date ASC LIMIT ?, ?";
                 
        $stmt = $conn->prepare($query);
        
        // Add pagination parameters
        $param_types .= 'ii';
        $params[] = $offset;
        $params[] = $items_per_page;
        
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $reminders = $stmt->get_result();
        
        // Get users for filter dropdown (admin only)
        if ($is_admin) {
            $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name");
            $stmt->execute();
            $users_result = $stmt->get_result();
            
            $user_options = ['0' => 'All Users'];
            while ($user = $users_result->fetch_assoc()) {
                $user_options[$user['user_id']] = $user['first_name'] . ' ' . $user['last_name'];
            }
        }
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Reminders</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add New Reminder
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
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel"></i> Filter Reminders
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php" method="get" class="row g-3">
                    <input type="hidden" name="page" value="reminders">
                    
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search reminders..." value="<?php echo $search; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select name="date" class="form-select">
                            <option value="" <?php echo $date_filter === '' ? 'selected' : ''; ?>>All Dates</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="tomorrow" <?php echo $date_filter === 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Next 7 Days</option>
                            <option value="overdue" <?php echo $date_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    
                    <?php if ($is_admin): ?>
                    <div class="col-md-3">
                        <select name="user_id" class="form-select">
                            <?php foreach ($user_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $user_filter === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Apply Filters
                        </button>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="btn btn-secondary">
                            <i class="bi bi-x-lg"></i> Clear Filters
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bell"></i> Reminders
                        </h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-muted">
                            Showing <?php echo min(($current_page - 1) * $items_per_page + 1, $total_items); ?> to 
                            <?php echo min($current_page * $items_per_page, $total_items); ?> of 
                            <?php echo $total_items; ?> reminders
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($reminders->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date & Time</th>
                                <th>Related To</th>
                                <?php if ($is_admin): ?>
                                <th>Assigned To</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($reminder = $reminders->fetch_assoc()): ?>
                                <?php 
                                    // Determine row styling based on status and date
                                    $row_class = '';
                                    $is_overdue = strtotime($reminder['reminder_date']) < time() && !$reminder['is_completed'];
                                    $is_today = date('Y-m-d') == date('Y-m-d', strtotime($reminder['reminder_date']));
                                    
                                    if ($is_overdue) {
                                        $row_class = 'table-danger';
                                    } elseif ($is_today && !$reminder['is_completed']) {
                                        $row_class = 'table-warning';
                                    } elseif ($reminder['is_completed']) {
                                        $row_class = 'table-success';
                                    }
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td>
                                        <?php echo htmlspecialchars($reminder['title']); ?>
                                        <?php if (!empty($reminder['message'])): ?>
                                            <i class="bi bi-info-circle text-info" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($reminder['message']); ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo format_date($reminder['reminder_date'], 'm/d/Y h:i A'); ?>
                                    </td>
                                    <td>
                                        <?php if ($reminder['customer_id']): ?>
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $reminder['customer_id']; ?>">
                                                <i class="bi bi-person"></i> <?php echo htmlspecialchars($reminder['customer_name']); ?>
                                            </a>
                                            <?php if ($reminder['lead_id']): ?>
                                                <br>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $reminder['lead_id']; ?>">
                                                    <i class="bi bi-funnel"></i> <?php echo htmlspecialchars($reminder['lead_title']); ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($is_admin): ?>
                                    <td>
                                        <?php echo htmlspecialchars($reminder['first_name'] . ' ' . $reminder['last_name']); ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($reminder['is_completed']): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($is_overdue): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php elseif ($is_today): ?>
                                            <span class="badge bg-warning text-dark">Today</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$reminder['is_completed']): ?>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=complete&id=<?php echo $reminder['reminder_id']; ?>" class="btn btn-success" title="Mark as Completed">
                                                    <i class="bi bi-check-lg"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=edit&id=<?php echo $reminder['reminder_id']; ?>" class="btn btn-primary" title="Edit Reminder">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=delete&id=<?php echo $reminder['reminder_id']; ?>" class="btn btn-danger confirm-delete" title="Delete Reminder" onclick="return confirm('Are you sure you want to delete this reminder?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="mt-3">
                    <?php echo pagination($total_items, $items_per_page, $current_page); ?>
                </div>
                
                <?php else: ?>
                    <p class="text-center">No reminders found. <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add">Add a new reminder</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
// Initialize tooltips for reminder messages
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
<?php
        break;
}
?>