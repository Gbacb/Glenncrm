<?php
// Get pre-filled parameters if passed (for quick adds from customer/lead pages)
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Invalid form submission.'
        ];
        header('Location: ' . BASE_URL . '/index.php?page=reminders');
        exit;
    }

    // Validate input
    $title = sanitize_input($_POST['title']);
    $reminder_date = sanitize_input($_POST['reminder_date']);
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : NULL;
    $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : NULL;
    $message = sanitize_input($_POST['message']);
    
    // For admin users, allow assigning to other users
    if (is_admin() && !empty($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
    } else {
        $user_id = $_SESSION['user_id'];
    }
    
    // Validate required fields
    if (empty($title)) {
        $error_message = 'Reminder title is required.';
    } elseif (empty($reminder_date)) {
        $error_message = 'Reminder date is required.';
    } else {
        // Insert the reminder
        $stmt = $conn->prepare("INSERT INTO reminders (user_id, customer_id, lead_id, title, reminder_date, message) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $user_id, $customer_id, $lead_id, $title, $reminder_date, $message);
        $result = $stmt->execute();
        
        if ($result) {
            $reminder_id = $conn->insert_id;
            
            // Log activity
            log_activity($_SESSION['user_id'], 'added', 'reminder', $reminder_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Reminder has been created successfully.'
            ];
            
            // Redirect back to reminders list
            header('Location: ' . BASE_URL . '/index.php?page=reminders');
            exit;
        } else {
            $error_message = 'Failed to create reminder. Please try again.';
        }
    }
}

// Get customers for dropdown
$query = is_admin() ? 
    "SELECT customer_id, name FROM customers ORDER BY name" : 
    "SELECT customer_id, name FROM customers WHERE assigned_to = ? ORDER BY name";
$stmt = $conn->prepare($query);

if (!is_admin()) {
    $stmt->bind_param("i", $_SESSION['user_id']);
}

$stmt->execute();
$customers = $stmt->get_result();

// Get leads for dropdown if customer is selected
$leads = [];
if ($customer_id > 0) {
    $stmt = $conn->prepare("SELECT lead_id, title FROM leads WHERE customer_id = ? ORDER BY title");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $leads = $stmt->get_result();
}

// Get users for dropdown (admin only)
$users = [];
if (is_admin()) {
    $stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name");
    $stmt->execute();
    $users = $stmt->get_result();
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Create New Reminder</h1>
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
        <!-- Show error if any -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">
                    <i class="bi bi-bell"></i> Reminder Details
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add" method="post" class="needs-validation" novalidate>
                    <!-- CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($_POST['title']) ? $_POST['title'] : ''; ?>" required>
                            <div class="invalid-feedback">
                                Please enter a title for the reminder.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="reminder_date" class="form-label">Due Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="reminder_date" name="reminder_date" 
                                   value="<?php echo isset($_POST['reminder_date']) ? $_POST['reminder_date'] : date('Y-m-d\TH:i'); ?>" required>
                            <div class="invalid-feedback">
                                Please enter the date and time for the reminder.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Related Customer</label>
                            <select class="form-select" id="customer_id" name="customer_id" onchange="loadCustomerLeads(this.value, 'lead_id')">
                                <option value="">-- Select Customer (Optional) --</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" <?php echo $customer_id == $customer['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo $customer['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="lead_id" class="form-label">Related Lead</label>
                            <select class="form-select" id="lead_id" name="lead_id">
                                <option value="">-- Select Lead (Optional) --</option>
                                <?php if ($leads && $leads->num_rows > 0): ?>
                                    <?php while ($lead = $leads->fetch_assoc()): ?>
                                        <option value="<?php echo $lead['lead_id']; ?>" <?php echo $lead_id == $lead['lead_id'] ? 'selected' : ''; ?>>
                                            <?php echo $lead['title']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (is_admin()): ?>
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Assign To</label>
                        <select class="form-select" id="user_id" name="user_id">
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['user_id']; ?>" <?php echo $_SESSION['user_id'] == $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo $user['first_name'] . ' ' . $user['last_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Specify which user should receive this reminder.</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Notes / Details</label>
                        <textarea class="form-control" id="message" name="message" rows="4"><?php echo isset($_POST['message']) ? $_POST['message'] : ''; ?></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Create Reminder
                        </button>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
// Load leads for selected customer via AJAX
function loadCustomerLeads(customerId, targetSelectId) {
    const leadSelect = document.getElementById(targetSelectId);
    
    // Clear existing options
    leadSelect.innerHTML = '<option value="">-- Select Lead (Optional) --</option>';
    
    if (!customerId) return;
    
    // AJAX request to get leads for the selected customer
    fetch('<?php echo BASE_URL; ?>/includes/ajax_handlers.php?action=get_customer_leads&customer_id=' + customerId)
        .then(response => response.json())
        .then(data => {
            data.forEach(lead => {
                const option = document.createElement('option');
                option.value = lead.lead_id;
                option.textContent = lead.title;
                leadSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading leads:', error));
}

// Initialize form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>