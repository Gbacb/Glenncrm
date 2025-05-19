<?php
// Get interaction details
$stmt = $conn->prepare("SELECT i.*, c.name as customer_name, c.customer_id, l.title as lead_title, l.lead_id, 
                        u.first_name, u.last_name 
                       FROM interactions i 
                       LEFT JOIN customers c ON i.customer_id = c.customer_id 
                       LEFT JOIN leads l ON i.lead_id = l.lead_id 
                       LEFT JOIN users u ON i.user_id = u.user_id
                       WHERE i.interaction_id = ?");
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

// Check if user has permission to view this interaction
if (!is_admin() && $interaction['user_id'] != $_SESSION['user_id']) {
    $_SESSION['alert'] = [
        'type' => 'warning',
        'message' => 'You do not have permission to view this interaction.'
    ];
    header('Location: ' . BASE_URL . '/index.php?page=interactions');
    exit;
}

// Get other recent interactions with this customer
$other_interactions = [];
if ($interaction['customer_id']) {
    $stmt = $conn->prepare("SELECT i.*, u.first_name, u.last_name 
                           FROM interactions i 
                           LEFT JOIN users u ON i.user_id = u.user_id
                           WHERE i.customer_id = ? AND i.interaction_id != ?
                           ORDER BY i.interaction_date DESC LIMIT 5");
    $stmt->bind_param("ii", $interaction['customer_id'], $interaction_id);
    $stmt->execute();
    $other_interactions = $stmt->get_result();
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">View Interaction</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=interactions" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Interactions
                    </a>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=edit&id=<?php echo $interaction_id; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <?php if (is_admin()): ?>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=delete&id=<?php echo $interaction_id; ?>" class="btn btn-danger confirm-delete">
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
                <!-- Interaction Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-chat-dots"></i> Interaction Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Type:</strong>
                                <span class="badge bg-<?php 
                                    echo $interaction['interaction_type'] == 'call' ? 'primary' : 
                                    ($interaction['interaction_type'] == 'email' ? 'info' : 
                                    ($interaction['interaction_type'] == 'meeting' ? 'success' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($interaction['interaction_type']); ?>
                                </span>
                            </div>
                            
                            <div class="col-md-4">
                                <strong>Date & Time:</strong>
                                <?php echo format_date($interaction['interaction_date'], 'm/d/Y h:i A'); ?>
                            </div>
                            
                            <div class="col-md-4">
                                <strong>Duration:</strong>
                                <?php echo $interaction['duration'] ? $interaction['duration'] . ' minutes' : 'Not specified'; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Recorded By:</strong>
                                <?php echo $interaction['first_name'] . ' ' . $interaction['last_name']; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <strong>Customer:</strong>
                                <?php if ($interaction['customer_id']): ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $interaction['customer_id']; ?>">
                                        <?php echo $interaction['customer_name']; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not specified</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <strong>Related Lead:</strong>
                                <?php if ($interaction['lead_id']): ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $interaction['lead_id']; ?>">
                                        <?php echo $interaction['lead_title']; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Created:</strong>
                            <?php echo format_date($interaction['created_at']); ?>
                        </div>
                        
                        <hr>
                        
                        <h5>Notes / Summary</h5>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($interaction['notes'])); ?>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=add<?php echo $interaction['customer_id'] ? '&customer_id=' . $interaction['customer_id'] : ''; ?><?php echo $interaction['lead_id'] ? '&lead_id=' . $interaction['lead_id'] : ''; ?>" class="btn btn-outline-warning">
                                <i class="bi bi-bell"></i> Create Follow-Up Reminder
                            </a>
                            
                            <?php if ($interaction['customer_id']): ?>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=add&customer_id=<?php echo $interaction['customer_id']; ?><?php echo $interaction['lead_id'] ? '&lead_id=' . $interaction['lead_id'] : ''; ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-plus-circle"></i> Record Another Interaction
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <?php if ($interaction['customer_id']): ?>
                    <!-- Customer Overview Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-person"></i> Customer Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                                // Get customer information
                                $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
                                $stmt->bind_param("i", $interaction['customer_id']);
                                $stmt->execute();
                                $customer = $stmt->get_result()->fetch_assoc();
                            ?>
                            
                            <h5><a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer['customer_id']; ?>"><?php echo $customer['name']; ?></a></h5>
                            
                            <?php if ($customer['email']): ?>
                                <div class="mb-2">
                                    <i class="bi bi-envelope"></i> 
                                    <a href="mailto:<?php echo $customer['email']; ?>"><?php echo $customer['email']; ?></a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($customer['phone']): ?>
                                <div class="mb-2">
                                    <i class="bi bi-telephone"></i> 
                                    <a href="tel:<?php echo $customer['phone']; ?>"><?php echo $customer['phone']; ?></a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View Full Customer Profile
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($other_interactions && $other_interactions->num_rows > 0): ?>
                    <!-- Other Interactions Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">
                                <i class="bi bi-chat-square-text"></i> Other Interactions with this Customer
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php while($other = $other_interactions->fetch_assoc()): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <span class="badge bg-<?php 
                                                    echo $other['interaction_type'] == 'call' ? 'primary' : 
                                                    ($other['interaction_type'] == 'email' ? 'info' : 
                                                    ($other['interaction_type'] == 'meeting' ? 'success' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($other['interaction_type']); ?>
                                                </span>
                                            </h6>
                                            <small><?php echo format_date($other['interaction_date']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo strlen($other['notes']) > 50 ? substr($other['notes'], 0, 50) . '...' : $other['notes']; ?></p>
                                        <small>by <?php echo $other['first_name'] . ' ' . $other['last_name']; ?></small>
                                        <div class="mt-2">
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=view&id=<?php echo $other['interaction_id']; ?>" class="btn btn-sm btn-outline-info">View</a>
                                        </div>
                                    </li>
                                <?php endwhile; ?>
                            </ul>
                        </div>
                        <div class="card-footer">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&customer_id=<?php echo $interaction['customer_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-list"></i> View All Interactions for this Customer
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>