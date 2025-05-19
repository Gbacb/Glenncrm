<?php
// Get interaction data
$stmt = $conn->prepare("SELECT * FROM interactions WHERE interaction_id = ?");
$stmt->bind_param("i", $interaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Interaction not found, redirect to list
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Interaction not found.'
    ];
    header('Location: ' . BASE_URL . '/index.php?page=interactions');
    exit;
}

$interaction = $result->fetch_assoc();

// Check if user has permission to edit this interaction
if (!is_admin() && $interaction['user_id'] != $_SESSION['user_id']) {
    $_SESSION['alert'] = [
        'type' => 'warning',
        'message' => 'You do not have permission to edit this interaction.'
    ];
    header('Location: ' . BASE_URL . '/index.php?page=interactions');
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
        header('Location: ' . BASE_URL . '/index.php?page=interactions');
        exit;
    }

    // Validate input
    $interaction_type = sanitize_input($_POST['interaction_type']);
    $interaction_date = sanitize_input($_POST['interaction_date']);
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : NULL;
    $lead_id = !empty($_POST['lead_id']) ? intval($_POST['lead_id']) : NULL;
    $duration = !empty($_POST['duration']) ? intval($_POST['duration']) : NULL;
    $notes = sanitize_input($_POST['notes']);
    
    // Validate required fields
    if (empty($interaction_type)) {
        $error_message = 'Interaction type is required.';
    } elseif (empty($interaction_date)) {
        $error_message = 'Interaction date is required.';
    } elseif (empty($customer_id) && empty($lead_id)) {
        $error_message = 'Either customer or lead must be selected.';
    } else {
        // Update the interaction
        $stmt = $conn->prepare("UPDATE interactions SET customer_id = ?, lead_id = ?, interaction_type = ?, interaction_date = ?, duration = ?, notes = ? WHERE interaction_id = ?");
        $stmt->bind_param("iissssi", $customer_id, $lead_id, $interaction_type, $interaction_date, $duration, $notes, $interaction_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Log activity
            log_activity($_SESSION['user_id'], 'updated', 'interaction', $interaction_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Interaction updated successfully.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=interactions";</script>';
            exit;
        } else {
            $error_message = 'Failed to update interaction. Please try again.';
        }
    }
}

// Format interaction_date for datetime-local input
$interaction_datetime = str_replace(' ', 'T', $interaction['interaction_date']);

// Get customers for dropdown
$query = is_admin() ? 
    "SELECT customer_id, name FROM customers ORDER BY name" : 
    "SELECT customer_id, name FROM customers WHERE assigned_to = ? OR customer_id = ? ORDER BY name";
$stmt = $conn->prepare($query);

if (!is_admin()) {
    $stmt->bind_param("ii", $_SESSION['user_id'], $interaction['customer_id']);
}

$stmt->execute();
$customers = $stmt->get_result();

// Get leads for the selected customer
$leads = [];
if ($interaction['customer_id']) {
    $stmt = $conn->prepare("SELECT lead_id, title FROM leads WHERE customer_id = ? ORDER BY title");
    $stmt->bind_param("i", $interaction['customer_id']);
    $stmt->execute();
    $leads = $stmt->get_result();
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Edit Interaction</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=view&id=<?php echo $interaction_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Interaction
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
                    <i class="bi bi-chat-dots"></i> Edit Interaction Details
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php?page=interactions&action=edit&id=<?php echo $interaction_id; ?>" method="post" class="needs-validation" novalidate>
                    <!-- CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="interaction_type" class="form-label">Interaction Type *</label>
                            <select class="form-select" id="interaction_type" name="interaction_type" required>
                                <option value="" disabled>-- Select Type --</option>
                                <option value="call" <?php echo $interaction['interaction_type'] == 'call' ? 'selected' : ''; ?>>Call</option>
                                <option value="email" <?php echo $interaction['interaction_type'] == 'email' ? 'selected' : ''; ?>>Email</option>
                                <option value="meeting" <?php echo $interaction['interaction_type'] == 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                <option value="other" <?php echo $interaction['interaction_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select an interaction type.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="interaction_date" class="form-label">Date & Time *</label>
                            <input type="datetime-local" class="form-control" id="interaction_date" name="interaction_date" 
                                   value="<?php echo $interaction_datetime; ?>" required>
                            <div class="invalid-feedback">
                                Please enter the date and time of the interaction.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Customer *</label>
                            <select class="form-select" id="customer_id" name="customer_id" onchange="loadCustomerLeads(this.value, 'lead_id')" required>
                                <option value="">-- Select Customer --</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" <?php echo $interaction['customer_id'] == $customer['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo $customer['name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select a customer.
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="lead_id" class="form-label">Related Lead</label>
                            <select class="form-select" id="lead_id" name="lead_id">
                                <option value="">-- Select Lead (Optional) --</option>
                                <?php if ($leads && $leads->num_rows > 0): ?>
                                    <?php while ($lead = $leads->fetch_assoc()): ?>
                                        <option value="<?php echo $lead['lead_id']; ?>" <?php echo $interaction['lead_id'] == $lead['lead_id'] ? 'selected' : ''; ?>>
                                            <?php echo $lead['title']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="duration" class="form-label">Duration (minutes)</label>
                        <input type="number" class="form-control" id="duration" name="duration" min="1" 
                               value="<?php echo $interaction['duration']; ?>" placeholder="Duration in minutes">
                        <div class="form-text">Enter the duration of the interaction in minutes, if applicable.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes / Summary *</label>
                        <textarea class="form-control" id="notes" name="notes" rows="5" required><?php echo htmlspecialchars($interaction['notes']); ?></textarea>
                        <div class="invalid-feedback">
                            Please provide notes or a summary of the interaction.
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Interaction
                        </button>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=view&id=<?php echo $interaction_id; ?>" class="btn btn-secondary">Cancel</a>
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