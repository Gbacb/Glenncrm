<?php
// Get customer details
$stmt = $conn->prepare("SELECT c.*, u.first_name, u.last_name 
                       FROM customers c 
                       LEFT JOIN users u ON c.assigned_to = u.user_id
                       WHERE c.customer_id = ?");
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

// Get customer's leads
$stmt = $conn->prepare("SELECT lead_id, title, status, expected_close_date, value FROM leads WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$leads = $stmt->get_result();

// Get customer's interactions
$stmt = $conn->prepare("SELECT i.*, u.first_name, u.last_name 
                       FROM interactions i 
                       INNER JOIN users u ON i.user_id = u.user_id
                       WHERE i.customer_id = ? 
                       ORDER BY i.interaction_date DESC 
                       LIMIT 10");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$interactions = $stmt->get_result();

// Get customer's sales
$stmt = $conn->prepare("SELECT s.*, u.first_name, u.last_name 
                       FROM sales s 
                       INNER JOIN users u ON s.user_id = u.user_id
                       WHERE s.customer_id = ? 
                       ORDER BY s.sale_date DESC 
                       LIMIT 10");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$sales = $stmt->get_result();

// Count total sales for this customer
$stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM sales WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Customer Details</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=customers" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Customers
                    </a>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=edit&id=<?php echo $customer_id; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Customer
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
        
        <div class="row">
            <div class="col-md-4">
                <!-- Customer Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-person"></i> Customer Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <h4><?php echo $customer['name']; ?></h4>
                        <p class="mb-2">
                            <span class="badge bg-<?php 
                                echo $customer['status'] == 'active' ? 'success' : 
                                    ($customer['status'] == 'inactive' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($customer['status']); ?>
                            </span>
                        </p>
                        
                        <div class="mb-3">
                            <strong><i class="bi bi-envelope"></i> Email:</strong>
                            <?php if ($customer['email']): ?>
                                <a href="mailto:<?php echo $customer['email']; ?>"><?php echo $customer['email']; ?></a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="bi bi-telephone"></i> Phone:</strong>
                            <?php if ($customer['phone']): ?>
                                <a href="tel:<?php echo $customer['phone']; ?>"><?php echo $customer['phone']; ?></a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="bi bi-geo-alt"></i> Address:</strong>
                            <?php if ($customer['address']): ?>
                                <p><?php echo nl2br($customer['address']); ?></p>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="bi bi-person-badge"></i> Assigned To:</strong>
                            <?php if ($customer['assigned_to']): ?>
                                <?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?>
                            <?php else: ?>
                                <span class="text-muted">Unassigned</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong><i class="bi bi-calendar"></i> Added:</strong>
                            <?php echo format_date($customer['created_at']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Notes Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-sticky"></i> Notes
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($customer['notes']): ?>
                            <?php echo nl2br($customer['notes']); ?>
                        <?php else: ?>
                            <p class="text-muted">No notes available for this customer.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <!-- Quick Stats -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Leads</h5>
                                <h3 class="mb-0"><?php echo $leads->num_rows; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales</h5>
                                <h3 class="mb-0"><?php echo $sales_summary['count']; ?></h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Revenue</h5>
                                <h3 class="mb-0"><?php echo format_currency($sales_summary['total'] ? $sales_summary['total'] : 0); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Leads Tab -->
                <div class="card mt-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-funnel"></i> Leads
                            </h5>
                            <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=add&customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus"></i> New Lead
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($leads->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Expected Close</th>
                                            <th>Value</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($lead = $leads->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $lead['title']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        $status_colors = [
                                                            'new' => 'primary',
                                                            'contacted' => 'info',
                                                            'qualified' => 'warning',
                                                            'proposal' => 'secondary',
                                                            'negotiation' => 'dark',
                                                            'closed_won' => 'success',
                                                            'closed_lost' => 'danger'
                                                        ];
                                                        echo $status_colors[$lead['status']];
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $lead['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $lead['expected_close_date'] ? format_date($lead['expected_close_date']) : 'N/A'; ?></td>
                                                <td><?php echo format_currency($lead['value']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $lead['lead_id']; ?>" class="btn btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No leads associated with this customer.</p>
                            <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=add&customer_id=<?php echo $customer_id; ?>" class="btn btn-outline-primary">
                                <i class="bi bi-plus"></i> Create First Lead
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Interactions Tab -->
                <div class="card mt-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-chat-dots"></i> Recent Interactions
                            </h5>
                            <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=add&customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus"></i> New Interaction
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($interactions->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>User</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($interaction = $interactions->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo format_date($interaction['interaction_date']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        $type_colors = [
                                                            'call' => 'primary',
                                                            'email' => 'info',
                                                            'meeting' => 'success',
                                                            'other' => 'secondary'
                                                        ];
                                                        echo $type_colors[$interaction['interaction_type']];
                                                    ?>">
                                                        <?php echo ucfirst($interaction['interaction_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $interaction['first_name'] . ' ' . $interaction['last_name']; ?></td>
                                                <td>
                                                    <?php 
                                                        echo strlen($interaction['notes']) > 100 ? 
                                                            substr($interaction['notes'], 0, 100) . '...' : 
                                                            $interaction['notes']; 
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No interactions recorded for this customer.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>