<?php
// Get customer ID from GET if passed
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    $customer_id = intval($_POST['customer_id']);
    $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : null;
    $sale_date = sanitize_input($_POST['sale_date']);
    $amount = floatval(str_replace(['$', ','], '', $_POST['amount']));
    $description = sanitize_input($_POST['description']);
    $user_id = $_SESSION['user_id']; // Current user records the sale
    
    // Check for required fields
    if (empty($customer_id)) {
        $error_message = 'Customer is required.';
    } elseif (empty($sale_date)) {
        $error_message = 'Sale date is required.';
    } elseif ($amount <= 0) {
        $error_message = 'Amount must be greater than zero.';
    } else {
        // Insert the sale
        $stmt = $conn->prepare("INSERT INTO sales (customer_id, lead_id, user_id, sale_date, amount, description, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiisds", $customer_id, $lead_id, $user_id, $sale_date, $amount, $description);
        $result = $stmt->execute();
        
        if ($result) {
            $sale_id = $conn->insert_id;
            
            // If sale is from a lead, update lead status to closed_won
            if ($lead_id) {
                $stmt = $conn->prepare("UPDATE leads SET status = 'closed_won' WHERE lead_id = ?");
                $stmt->bind_param("i", $lead_id);
                $stmt->execute();
            }
            
            // Log activity
            log_activity($_SESSION['user_id'], 'added', 'sale', $sale_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Sale has been recorded successfully.'
            ];
            
            // Redirect back to sales list
            header('Location: ' . BASE_URL . '/index.php?page=sales');
            exit;
        } else {
            $error_message = 'Failed to record sale. Please try again.';
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

// If customer is selected, get their leads
$leads = [];
if ($customer_id) {
    $stmt = $conn->prepare("SELECT lead_id, title FROM leads WHERE customer_id = ? AND status NOT IN ('closed_won', 'closed_lost') ORDER BY created_at DESC");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $leads = $stmt->get_result();
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Record New Sale</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=sales" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Sales
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
                <form action="<?php echo BASE_URL; ?>/index.php?page=sales&action=add" method="post" class="needs-validation" novalidate>
                    <!-- CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Customer *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required onchange="loadCustomerLeads(this.value, 'lead_id')">
                                <option value="">-- Select Customer --</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" <?php echo $customer_id == $customer['customer_id'] ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $lead['lead_id']; ?>" <?php echo $lead_id == $lead['lead_id'] ? 'selected' : ''; ?>>
                                            <?php echo $lead['title']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">If this sale is from a lead, select it here. The lead will be marked as won.</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sale_date" class="form-label">Sale Date *</label>
                            <input type="date" class="form-control" id="sale_date" name="sale_date" 
                                   value="<?php echo isset($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Please enter the sale date.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" class="form-control" id="amount" name="amount" 
                                       value="<?php echo isset($_POST['amount']) ? $_POST['amount'] : ''; ?>" 
                                       placeholder="0.00" required>
                            </div>
                            <div class="invalid-feedback">
                                Please enter the sale amount.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Record Sale
                        </button>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=sales" class="btn btn-secondary">Cancel</a>
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