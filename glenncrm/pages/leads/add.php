<?php
// Get customer ID from query string if passed
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

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
    $assigned_to = $_SESSION['user_id']; // Default: assign to current user
    
    // Check for required fields
    if (empty($title)) {
        $error_message = 'Lead title is required.';
    } elseif (empty($customer_id)) {
        $error_message = 'Customer is required.';
    } else {
        // Insert the lead
        $stmt = $conn->prepare("INSERT INTO leads (title, customer_id, status, expected_close_date, value, description, assigned_to, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssdsi", $title, $customer_id, $status, $expected_close_date, $value, $description, $assigned_to);
        $result = $stmt->execute();
        
        if ($result) {
            $lead_id = $conn->insert_id;
            
            // Log activity
            log_activity($_SESSION['user_id'], 'added', 'lead', $lead_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Lead has been added successfully.'
            ];
            
            // Redirect to leads list with JavaScript for consistency
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
            exit;
        } else {
            $error_message = 'Failed to add lead. Please try again.';
        }
    }
}

// Fetch customers for dropdown
$query = is_admin() ? 
    "SELECT customer_id, name FROM customers ORDER BY name" : 
    "SELECT customer_id, name FROM customers WHERE assigned_to = ? ORDER BY name";
$stmt = $conn->prepare($query);
if (!is_admin()) {
    $stmt->bind_param("i", $_SESSION['user_id']);
}
$stmt->execute();
$customers = $stmt->get_result();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">Add New Lead</h1></div>
            <div class="col-sm-6 text-end">
                <a href="<?php echo BASE_URL; ?>/index.php?page=leads" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Leads
                </a>
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
            <div class="card-body">
                <form method="post" action="<?php echo BASE_URL; ?>/index.php?page=leads&action=add" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        <div class="invalid-feedback">Please enter a lead title.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer *</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">-- Select Customer --</option>
                                <?php while ($cust = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $cust['customer_id']; ?>" 
                                        <?php echo ($cust['customer_id'] == $customer_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cust['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">Please select a customer.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="new" <?php echo (isset($_POST['status']) && $_POST['status'] == 'new') ? 'selected' : ''; ?>>New</option>
                                <option value="contacted" <?php echo (isset($_POST['status']) && $_POST['status'] == 'contacted') ? 'selected' : ''; ?>>Contacted</option>
                                <option value="qualified" <?php echo (isset($_POST['status']) && $_POST['status'] == 'qualified') ? 'selected' : ''; ?>>Qualified</option>
                                <option value="proposal" <?php echo (isset($_POST['status']) && $_POST['status'] == 'proposal') ? 'selected' : ''; ?>>Proposal</option>
                                <option value="negotiation" <?php echo (isset($_POST['status']) && $_POST['status'] == 'negotiation') ? 'selected' : ''; ?>>Negotiation</option>
                            </select>
                            <div class="invalid-feedback">Please select a status.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Close Date</label>
                            <input type="date" name="expected_close_date" class="form-control"
                                value="<?php echo isset($_POST['expected_close_date']) ? htmlspecialchars($_POST['expected_close_date']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Value</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" name="value" class="form-control" placeholder="0.00"
                                    value="<?php echo isset($_POST['value']) ? htmlspecialchars($_POST['value']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Lead
                    </button>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</section>
<?php
// ...existing code...
?>