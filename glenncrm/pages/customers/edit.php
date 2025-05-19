<?php
// Get customer data
$stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Customer not found, redirect to list
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Customer not found.'
    ];
    header('Location: ' . BASE_URL . '/index.php?page=customers');
    exit;
}

$customer = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Invalid form submission.'
        ];
        header('Location: ' . BASE_URL . '/index.php?page=customers');
        exit;
    }

    // Validate input
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);
    $notes = sanitize_input($_POST['notes']);
    $status = sanitize_input($_POST['status']);
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : NULL;
    
    // Check for required fields
    if (empty($name)) {
        $error_message = 'Customer name is required.';
    }
    // Validate email if provided
    elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    }
    else {
        // Only check duplicates if user changed the email
        if (!empty($email) && $email !== $customer['email']) {
            $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? AND customer_id != ?");
            $stmt->bind_param("si", $email, $customer_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error_message = 'Email address already exists for another customer.';
            }
        }
        
        // If no errors, update the customer
        if (!isset($error_message)) {
            $stmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, address = ?, notes = ?, status = ?, assigned_to = ? WHERE customer_id = ?");
            $stmt->bind_param("ssssssii", $name, $email, $phone, $address, $notes, $status, $assigned_to, $customer_id);
            $result = $stmt->execute();
            
            if ($result) {
                // Log activity
                log_activity($_SESSION['user_id'], 'updated', 'customer', $customer_id);
                
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Customer has been updated successfully.'
                ];
                
                // Redirect to customers list
                echo '<script>window.location.href="'.BASE_URL.'/index.php?page=customers";</script>';
                exit;
            } else {
                $error_message = 'Failed to update customer. Please try again.';
            }
        }
    }
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Edit Customer</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Customer
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
                    <i class="bi bi-person-gear"></i> Edit Customer Information
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php?page=customers&action=edit&id=<?php echo $customer_id; ?>" method="post" class="needs-validation" novalidate>
                    <!-- CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Customer Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter the customer name.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo ($customer['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($customer['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="prospect" <?php echo ($customer['status'] == 'prospect') ? 'selected' : ''; ?>>Prospect</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Assigned To</label>
                        <?php echo get_users_dropdown('assigned_to', $customer['assigned_to']); ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Customer
                        </button>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>