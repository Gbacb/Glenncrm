<?php
// Get lead details
$stmt = $conn->prepare("SELECT * FROM leads WHERE lead_id = ?");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Lead not found, redirect to list
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Lead not found.'
    ];
    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
    exit;
}

$lead = $result->fetch_assoc();

// Check if user has permission to edit this lead (admin or owner)
if (!is_admin() && $lead['assigned_to'] != $_SESSION['user_id']) {
    $_SESSION['alert'] = [
        'type' => 'warning',
        'message' => 'You do not have permission to edit this lead.'
    ];
    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Invalid form submission.'
        ];
        echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
        exit;
    }

    // Validate input
    $title = sanitize_input($_POST['title']);
    $customer_id = intval($_POST['customer_id']);
    $status = sanitize_input($_POST['status']);
    $expected_close_date = !empty($_POST['expected_close_date']) ? sanitize_input($_POST['expected_close_date']) : null;
    $value = !empty($_POST['value']) ? floatval(str_replace(['$', ','], '', $_POST['value'])) : 0;
    $description = sanitize_input($_POST['description']);
    
    // For admin users, allow reassigning
    if (is_admin() && isset($_POST['assigned_to'])) {
        $assigned_to = intval($_POST['assigned_to']);
    } else {
        $assigned_to = $lead['assigned_to']; // Keep the original assigned user
    }
    
    // Check for required fields
    if (empty($title)) {
        $error_message = 'Lead title is required.';
    } elseif (empty($customer_id)) {
        $error_message = 'Customer is required.';
    } else {
        // Update the lead
        $stmt = $conn->prepare("UPDATE leads SET title = ?, customer_id = ?, status = ?, expected_close_date = ?, value = ?, description = ?, assigned_to = ? WHERE lead_id = ?");
        $stmt->bind_param("sissdsis", $title, $customer_id, $status, $expected_close_date, $value, $description, $assigned_to, $lead_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Log activity
            log_activity($_SESSION['user_id'], 'updated', 'lead', $lead_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Lead has been updated successfully.'
            ];
            
            // Redirect to lead view with JavaScript for consistency
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads&action=view&id='.$lead_id.'";</script>';
            exit;
        } else {
            $error_message = 'Failed to update lead. Please try again.';
        }
    }
}

// Get customers for dropdown
$query = is_admin() ? 
    "SELECT customer_id, name FROM customers ORDER BY name" : 
    "SELECT customer_id, name FROM customers WHERE assigned_to = ? OR customer_id = ? ORDER BY name";
$stmt = $conn->prepare($query);

if (!is_admin()) {
    $stmt->bind_param("ii", $_SESSION['user_id'], $lead['customer_id']);
}

$stmt->execute();
$customers = $stmt->get_result();

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
                <h1 class="m-0">Edit Lead</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $lead_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Lead
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
                    <i class="bi bi-funnel"></i> Edit Lead Details
                </h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo BASE_URL; ?>/index.php?page=leads&action=edit&id=<?php echo $lead_id; ?>" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($lead['title']); ?>">
                        <div class="invalid-feedback">Please enter a lead title.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer *</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Select Customer --</option>
                                <?php while ($cust = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $cust['customer_id']; ?>" 
                                        <?php echo ($cust['customer_id'] == $lead['customer_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cust['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">Please select a customer.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="new" <?php echo ($lead['status'] == 'new') ? 'selected' : ''; ?>>New</option>
                                <option value="contacted" <?php echo ($lead['status'] == 'contacted') ? 'selected' : ''; ?>>Contacted</option>
                                <option value="qualified" <?php echo ($lead['status'] == 'qualified') ? 'selected' : ''; ?>>Qualified</option>
                                <option value="proposal" <?php echo ($lead['status'] == 'proposal') ? 'selected' : ''; ?>>Proposal</option>
                                <option value="negotiation" <?php echo ($lead['status'] == 'negotiation') ? 'selected' : ''; ?>>Negotiation</option>
                                <option value="closed_won" <?php echo ($lead['status'] == 'closed_won') ? 'selected' : ''; ?>>Closed Won</option>
                                <option value="closed_lost" <?php echo ($lead['status'] == 'closed_lost') ? 'selected' : ''; ?>>Closed Lost</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Close Date</label>
                            <input type="date" name="expected_close_date" class="form-control"
                                value="<?php echo !empty($lead['expected_close_date']) ? htmlspecialchars($lead['expected_close_date']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Value</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" name="value" class="form-control" placeholder="0.00"
                                    value="<?php echo !empty($lead['value']) ? number_format($lead['value'], 2, '.', '') : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <?php if (is_admin()): ?>
                    <div class="mb-3">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select">
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['user_id']; ?>" 
                                    <?php echo ($user['user_id'] == $lead['assigned_to']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($lead['description']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Update Lead
                    </button>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $lead_id; ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</section>