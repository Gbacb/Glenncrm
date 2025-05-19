<?php
// Get sale data
$stmt = $conn->prepare("SELECT * FROM sales WHERE sale_id = ?");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Sale not found, redirect to list
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Sale not found.'
    ];
    header('Location: ' . BASE_URL . '/index.php?page=sales');
    exit;
}

$sale = $result->fetch_assoc();

// Check if user has permission to edit this sale (admin or owner)
if (!is_admin() && $sale['user_id'] != $_SESSION['user_id']) {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'You do not have permission to edit this sale.'
    ];
    header('Location: ' . BASE_URL . '/index.php?page=sales');
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
        header('Location: ' . BASE_URL . '/index.php?page=sales');
        exit;
    }

    // Validate input
    $customer_id = intval($_POST['customer_id']);
    $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : null;
    $sale_date = sanitize_input($_POST['sale_date']);
    $amount = floatval(str_replace(['$', ','], '', $_POST['amount']));
    $description = sanitize_input($_POST['description']);
    
    // Check for required fields
    if (empty($customer_id)) {
        $error_message = 'Customer is required.';
    } elseif (empty($sale_date)) {
        $error_message = 'Sale date is required.';
    } elseif ($amount <= 0) {
        $error_message = 'Amount must be greater than zero.';
    } else {
        // Update the sale
        $stmt = $conn->prepare("UPDATE sales SET customer_id = ?, lead_id = ?, sale_date = ?, amount = ?, description = ? WHERE sale_id = ?");
        $stmt->bind_param("iisdsi", $customer_id, $lead_id, $sale_date, $amount, $description, $sale_id);
        $result = $stmt->execute();
        
        if ($result) {
            // If lead is changed, update previous and new lead statuses
            if ($lead_id != $sale['lead_id']) {
                // If there was a previous lead, reset its status if no other sales are linked
                if ($sale['lead_id']) {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE lead_id = ? AND sale_id != ?");
                    $stmt->bind_param("ii", $sale['lead_id'], $sale_id);
                    $stmt->execute();
                    $previous_lead_sales = $stmt->get_result()->fetch_assoc()['count'];
                    
                    if ($previous_lead_sales == 0) {
                        $stmt = $conn->prepare("UPDATE leads SET status = 'negotiation' WHERE lead_id = ?");
                        $stmt->bind_param("i", $sale['lead_id']);
                        $stmt->execute();
                    }
                }
                
                // If there's a new lead, update its status to won
                if ($lead_id) {
                    $stmt = $conn->prepare("UPDATE leads SET status = 'closed_won' WHERE lead_id = ?");
                    $stmt->bind_param("i", $lead_id);
                    $stmt->execute();
                }
            }
            
            // Log activity
            log_activity($_SESSION['user_id'], 'updated', 'sale', $sale_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Sale has been updated successfully.'
            ];
            
            // Redirect to the sales list
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Sale updated successfully.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=sales";</script>';
            exit;
        } else {
            $error_message = 'Failed to update sale. Please try again.';
        }
    }
}

// Get customers list
$query = is_admin() ? 
    "SELECT customer_id, name FROM customers ORDER BY name" : 
    "SELECT c.customer_id, c.name FROM customers c 
     LEFT JOIN leads l ON c.customer_id = l.customer_id 
     WHERE c.assigned_to = ? OR l.assigned_to = ? 
     GROUP BY c.customer_id ORDER BY c.name";
     
$stmt = $conn->prepare($query);

if (!is_admin()) {
    $stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
}

$stmt->execute();
$customers = $stmt->get_result();

// Get leads for the selected customer
$stmt = $conn->prepare("SELECT lead_id, title, status FROM leads WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $sale['customer_id']);
$stmt->execute();
$leads = $stmt->get_result();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Edit Sale</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=view&id=<?php echo $sale_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Sale
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
                    <i class="bi bi-cash-coin"></i> Sale Information
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php?page=sales&action=edit&id=<?php echo $sale_id; ?>" method="post" class="needs-validation" novalidate>
                    <!-- CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Customer *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required onchange="loadCustomerLeads(this.value, 'lead_id')">
                                <option value="">-- Select Customer --</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" <?php echo $sale['customer_id'] == $customer['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo $customer['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a customer.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="lead_id" class="form-label">Related Lead (optional)</label>
                            <select class="form-select" id="lead_id" name="lead_id">
                                <option value="">-- Select Lead --</option>
                                <?php if ($leads && $leads->num_rows > 0): ?>
                                    <?php while ($lead = $leads->fetch_assoc()): ?>
                                        <option value="<?php echo $lead['lead_id']; ?>" <?php echo $sale['lead_id'] == $lead['lead_id'] ? 'selected' : ''; ?>>
                                            <?php echo $lead['title']; ?>
                                            <?php if ($lead['status'] == 'closed_won'): ?> (Won)<?php endif; ?>
                                            <?php if ($lead['status'] == 'closed_lost'): ?> (Lost)<?php endif; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">Changing the lead association will update the status of both the previous and new leads.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sale_date" class="form-label">Sale Date *</label>
                            <input type="date" class="form-control" id="sale_date" name="sale_date" 
                                   value="<?php echo $sale['sale_date']; ?>" required>
                            <div class="invalid-feedback">
                                Please enter the sale date.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control" id="amount" name="amount" 
                                       value="<?php echo number_format($sale['amount'], 2); ?>" 
                                       placeholder="0.00" required>
                            </div>
                            <div class="invalid-feedback">
                                Please enter the sale amount.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $sale['description']; ?></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Sale
                        </button>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=view&id=<?php echo $sale_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
// Format amount input as currency
document.getElementById('amount').addEventListener('blur', function(e) {
    const value = parseFloat(this.value.replace(/[^\d.]/g, ''));
    if (!isNaN(value)) {
        this.value = value.toFixed(2);
    }
});

// Load leads for selected customer via AJAX
function loadCustomerLeads(customerId, targetSelectId) {
    const leadSelect = document.getElementById(targetSelectId);
    
    // Clear existing options
    leadSelect.innerHTML = '<option value="">-- Select Lead --</option>';
    
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