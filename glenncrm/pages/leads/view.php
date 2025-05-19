<?php
// Get lead details
$stmt = $conn->prepare("SELECT l.*, c.name as customer_name, u.first_name, u.last_name 
                       FROM leads l 
                       LEFT JOIN customers c ON l.customer_id = c.customer_id
                       LEFT JOIN users u ON l.assigned_to = u.user_id
                       WHERE l.lead_id = ?");
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

// Check if user has access to this lead (admin or owner)
if (!is_admin() && $lead['assigned_to'] != $_SESSION['user_id']) {
    $_SESSION['alert'] = [
        'type' => 'warning',
        'message' => 'You do not have permission to view this lead.'
    ];
    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
    exit;
}

// Get interactions related to this lead
$stmt = $conn->prepare("SELECT i.*, u.first_name, u.last_name 
                       FROM interactions i 
                       INNER JOIN users u ON i.user_id = u.user_id
                       WHERE i.lead_id = ? 
                       ORDER BY i.interaction_date DESC 
                       LIMIT 10");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$interactions = $stmt->get_result();

// Get reminders related to this lead
$stmt = $conn->prepare("SELECT r.*, u.first_name, u.last_name 
                       FROM reminders r 
                       INNER JOIN users u ON r.user_id = u.user_id
                       WHERE r.lead_id = ? AND r.is_completed = 0
                       ORDER BY r.reminder_date ASC 
                       LIMIT 5");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$reminders = $stmt->get_result();

// Get sales related to this lead
$stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name 
                       FROM sales s 
                       INNER JOIN users u ON s.user_id = u.user_id
                       WHERE s.lead_id = ? 
                       ORDER BY s.sale_date DESC");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$sales = $stmt->get_result();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Lead Details</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Leads
                    </a>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=edit&id=<?php echo $lead_id; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Lead
                    </a>
                    <?php if (is_admin()): ?>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=delete&id=<?php echo $lead_id; ?>" 
                           class="btn btn-danger confirm-delete">
                            <i class="bi bi-trash"></i> Delete
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Show alerts if any -->
        <?php show_alert(); ?>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Lead Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-funnel"></i> Lead Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <h4><?php echo htmlspecialchars($lead['title']); ?></h4>
                        <p>
                            <span class="badge bg-<?php 
                                echo ($lead['status'] == 'closed_won') ? 'success' : 
                                    (($lead['status'] == 'closed_lost') ? 'danger' : 'primary'); 
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $lead['status'])); ?>
                            </span>
                        </p>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong><i class="bi bi-calendar"></i> Created:</strong>
                                <p><?php echo format_date($lead['created_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong><i class="bi bi-calendar-check"></i> Expected Close Date:</strong>
                                <p><?php echo !empty($lead['expected_close_date']) ? format_date($lead['expected_close_date']) : '<span class="text-muted">Not specified</span>'; ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong><i class="bi bi-person"></i> Assigned To:</strong>
                                <p><?php echo $lead['first_name'] . ' ' . $lead['last_name']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong><i class="bi bi-currency-dollar"></i> Value:</strong>
                                <p class="text-success fw-bold"><?php echo format_currency($lead['value']); ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="bi bi-building"></i> Customer:</strong>
                            <p>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $lead['customer_id']; ?>">
                                    <?php echo htmlspecialchars($lead['customer_name']); ?>
                                </a>
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <strong><i class="bi bi-info-circle"></i> Description:</strong>
                            <div class="p-3 bg-light rounded mt-2">
                                <?php echo !empty($lead['description']) ? nl2br(htmlspecialchars($lead['description'])) : '<span class="text-muted">No description provided</span>'; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=add&lead_id=<?php echo $lead_id; ?>&customer_id=<?php echo $lead['customer_id']; ?>" class="btn btn-outline-primary me-2">
                                <i class="bi bi-chat-dots"></i> Add Interaction
                            </a>
                            
                            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add&lead_id=<?php echo $lead_id; ?>&customer_id=<?php echo $lead['customer_id']; ?>" class="btn btn-outline-warning me-2">
                                <i class="bi bi-bell"></i> Set Reminder
                            </a>
                            
                            <?php if ($lead['status'] != 'closed_won' && $lead['status'] != 'closed_lost'): ?>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=add&lead_id=<?php echo $lead_id; ?>&customer_id=<?php echo $lead['customer_id']; ?>" class="btn btn-outline-success">
                                    <i class="bi bi-cash-coin"></i> Record Sale
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Related Sales -->
                <?php if ($sales->num_rows > 0): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-currency-dollar"></i> Related Sales
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Recorded By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sale = $sales->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo format_date($sale['sale_date']); ?></td>
                                        <td><?php echo format_currency($sale['amount']); ?></td>
                                        <td><?php echo $sale['first_name'] . ' ' . $sale['last_name']; ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=view&id=<?php echo $sale['sale_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <!-- Interactions -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-chat-dots"></i> Recent Interactions
                            </h5>
                            <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=add&lead_id=<?php echo $lead_id; ?>&customer_id=<?php echo $lead['customer_id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus"></i> Add
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($interactions->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($interaction = $interactions->fetch_assoc()): ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=view&id=<?php echo $interaction['interaction_id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php 
                                                    echo ($interaction['interaction_type'] == 'call') ? 'primary' : 
                                                         (($interaction['interaction_type'] == 'email') ? 'info' : 
                                                         (($interaction['interaction_type'] == 'meeting') ? 'success' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($interaction['interaction_type']); ?>
                                                </span>
                                            </h6>
                                            <small><?php echo format_date($interaction['interaction_date']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo substr(htmlspecialchars($interaction['notes']), 0, 100) . (strlen($interaction['notes']) > 100 ? '...' : ''); ?></p>
                                        <small>By: <?php echo $interaction['first_name'] . ' ' . $interaction['last_name']; ?></small>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                            <div class="card-footer">
                                <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&lead_id=<?php echo $lead_id; ?>" class="btn btn-sm btn-outline-secondary w-100">
                                    View All Interactions
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="card-body">
                                <p class="text-muted">No interactions recorded for this lead.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Reminders -->
                <div class="card mt-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bell"></i> Upcoming Reminders
                            </h5>
                            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add&lead_id=<?php echo $lead_id; ?>&customer_id=<?php echo $lead['customer_id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus"></i> Add
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($reminders->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($reminder = $reminders->fetch_assoc()): ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=edit&id=<?php echo $reminder['reminder_id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($reminder['title']); ?></h6>
                                            <small><?php echo format_date($reminder['reminder_date']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo !empty($reminder['message']) ? substr(htmlspecialchars($reminder['message']), 0, 100) . (strlen($reminder['message']) > 100 ? '...' : '') : '<span class="text-muted">No details</span>'; ?></p>
                                        <small>For: <?php echo $reminder['first_name'] . ' ' . $reminder['last_name']; ?></small>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="card-body">
                                <p class="text-muted">No upcoming reminders for this lead.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>