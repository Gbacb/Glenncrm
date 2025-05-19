<?php
// Get sale details
$stmt = $conn->prepare("SELECT s.*, c.name AS customer_name, l.title AS lead_title, l.lead_id, 
                       u.first_name, u.last_name
                       FROM sales s
                       LEFT JOIN customers c ON s.customer_id = c.customer_id
                       LEFT JOIN leads l ON s.lead_id = l.lead_id
                       LEFT JOIN users u ON s.user_id = u.user_id
                       WHERE s.sale_id = ?");
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

// Check if user has access to this sale (admin or owner)
if (!is_admin() && $sale['user_id'] != $_SESSION['user_id']) {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'You do not have permission to view this sale.'
    ];
    header('Location: ' . BASE_URL . '/index.php?page=sales');
    exit;
}

// Get customer details
$stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $sale['customer_id']);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Sale Details</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=sales" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Sales
                    </a>
                    <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=edit&id=<?php echo $sale_id; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Sale
                    </a>
                    <?php if (is_admin()): ?>
                        <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=delete&id=<?php echo $sale_id; ?>" 
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
                <!-- Sale Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-cash-coin"></i> Sale Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Sale Date:</strong>
                                <p><?php echo format_date($sale['sale_date']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Amount:</strong>
                                <p class="text-success fw-bold"><?php echo format_currency($sale['amount']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Recorded By:</strong>
                                <p><?php echo $sale['first_name'] . ' ' . $sale['last_name']; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Customer:</strong>
                            <p>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $sale['customer_id']; ?>">
                                    <?php echo $sale['customer_name']; ?>
                                </a>
                            </p>
                        </div>
                        
                        <?php if ($sale['lead_id']): ?>
                        <div class="mb-3">
                            <strong>Related Lead:</strong>
                            <p>
                                <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $sale['lead_id']; ?>">
                                    <?php echo $sale['lead_title']; ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <strong>Description:</strong>
                            <p>
                                <?php if (!empty($sale['description'])): ?>
                                    <?php echo nl2br($sale['description']); ?>
                                <?php else: ?>
                                    <em class="text-muted">No description provided</em>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Created At:</strong>
                                <p><?php echo format_date($sale['created_at'], DATE_FORMAT); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Customer Information Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-person"></i> Customer Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <h5>
                            <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer['customer_id']; ?>">
                                <?php echo $customer['name']; ?>
                            </a>
                        </h5>
                        
                        <?php if (!empty($customer['email'])): ?>
                            <div class="mb-2">
                                <strong><i class="bi bi-envelope"></i> Email:</strong>
                                <a href="mailto:<?php echo $customer['email']; ?>"><?php echo $customer['email']; ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($customer['phone'])): ?>
                            <div class="mb-2">
                                <strong><i class="bi bi-telephone"></i> Phone:</strong>
                                <a href="tel:<?php echo $customer['phone']; ?>"><?php echo $customer['phone']; ?></a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer['customer_id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View Customer Details
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Other Sales Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-graph-up"></i> Customer's Recent Sales
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $stmt = $conn->prepare("SELECT sale_id, sale_date, amount FROM sales 
                                              WHERE customer_id = ? AND sale_id != ? 
                                              ORDER BY sale_date DESC LIMIT 5");
                        $stmt->bind_param("ii", $sale['customer_id'], $sale_id);
                        $stmt->execute();
                        $other_sales = $stmt->get_result();
                        
                        if ($other_sales->num_rows > 0): 
                        ?>
                            <div class="list-group">
                                <?php while ($other_sale = $other_sales->fetch_assoc()): ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=view&id=<?php echo $other_sale['sale_id']; ?>" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <span><?php echo format_date($other_sale['sale_date']); ?></span>
                                        <span class="badge bg-primary rounded-pill"><?php echo format_currency($other_sale['amount']); ?></span>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                            <div class="mt-3">
                                <a href="<?php echo BASE_URL; ?>/index.php?page=sales&customer_id=<?php echo $sale['customer_id']; ?>" 
                                   class="btn btn-sm btn-outline-secondary">
                                    View All Sales
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No other sales recorded for this customer.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>