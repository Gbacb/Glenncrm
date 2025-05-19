<?php
// Handle different actions for sales management
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'list';
$sale_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Determine current page for pagination
$current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$items_per_page = intval(get_setting('items_per_page', 10));
$offset = ($current_page - 1) * $items_per_page;

switch ($action) {
    case 'view':
        // Include view sale page
        include 'sales/view.php';
        break;
        
    case 'add':
        // Include add sale page
        include 'sales/add.php';
        break;
        
    case 'edit':
        // Include edit sale page
        include 'sales/edit.php';
        break;
        
    case 'delete':
        // Check if user has permission to delete
        if (!is_admin()) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to delete sales.'
            ];
            header('Location: ' . BASE_URL . '/index.php?page=sales');
            exit;
        }
        
        // Verify sale exists
        $stmt = $conn->prepare("SELECT sale_id FROM sales WHERE sale_id = ?");
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Sale not found.'
            ];
            header('Location: ' . BASE_URL . '/index.php?page=sales');
            exit;
        }
        
        // Delete sale
        $stmt = $conn->prepare("DELETE FROM sales WHERE sale_id = ?");
        $stmt->bind_param("i", $sale_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Log activity
            log_activity($_SESSION['user_id'], 'deleted', 'sale', $sale_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Sale has been deleted successfully.'
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Failed to delete sale. There might be related records.'
            ];
        }
        
        // Redirect back to sales list
        echo '<script>window.location.href="'.BASE_URL.'/index.php?page=sales";</script>';
        exit;
        break;
        
    default: // list view
        // Get filter parameters
        $customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        $user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $date_range = isset($_GET['date_range']) ? sanitize_input($_GET['date_range']) : 'all';
        $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        
        // Build query conditions
        $conditions = [];
        $params = [];
        $param_types = '';
        
        // Date range filter
        $today = date('Y-m-d');
        switch ($date_range) {
            case 'today':
                $conditions[] = "s.sale_date = ?";
                $params[] = $today;
                $param_types .= 's';
                break;
            case 'week':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                $conditions[] = "s.sale_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $today;
                $param_types .= 'ss';
                break;
            case 'month':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                $conditions[] = "s.sale_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $today;
                $param_types .= 'ss';
                break;
            case 'quarter':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                $conditions[] = "s.sale_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $today;
                $param_types .= 'ss';
                break;
            case 'year':
                $start_date = date('Y-m-d', strtotime('-1 year'));
                $conditions[] = "s.sale_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $today;
                $param_types .= 'ss';
                break;
            case 'custom':
                if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                    $start_date = sanitize_input($_GET['start_date']);
                    $end_date = sanitize_input($_GET['end_date']);
                    if (!empty($start_date) && !empty($end_date)) {
                        $conditions[] = "s.sale_date BETWEEN ? AND ?";
                        $params[] = $start_date;
                        $params[] = $end_date;
                        $param_types .= 'ss';
                    }
                }
                break;
        }
        
        if ($customer_filter > 0) {
            $conditions[] = "s.customer_id = ?";
            $params[] = $customer_filter;
            $param_types .= 'i';
        }
        
        if ($user_filter > 0) {
            $conditions[] = "s.user_id = ?";
            $params[] = $user_filter;
            $param_types .= 'i';
        }
        
        if (!empty($search)) {
            $search_term = "%$search%";
            $conditions[] = "(s.description LIKE ? OR c.name LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $param_types .= 'ss';
        }
        
        // Only show sales by current user if not admin
        if (!is_admin() && $user_filter <= 0) {
            $conditions[] = "s.user_id = ?";
            $params[] = $_SESSION['user_id'];
            $param_types .= 'i';
        }
        
        $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
        
        // Count total records for pagination
        $count_query = "SELECT COUNT(*) as total FROM sales s
                       LEFT JOIN customers c ON s.customer_id = c.customer_id
                       LEFT JOIN leads l ON s.lead_id = l.lead_id" . $where_clause;
        $stmt = $conn->prepare($count_query);
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_items = $row['total'];
        
        // Get sales with pagination
        $query = "SELECT s.*, c.name as customer_name, l.title as lead_title, u.first_name, u.last_name 
                 FROM sales s 
                 LEFT JOIN customers c ON s.customer_id = c.customer_id
                 LEFT JOIN leads l ON s.lead_id = l.lead_id
                 LEFT JOIN users u ON s.user_id = u.user_id" 
                 . $where_clause . 
                 " ORDER BY s.sale_date DESC, s.sale_id DESC LIMIT ?, ?";
                 
        $stmt = $conn->prepare($query);
        
        // Add pagination parameters
        $param_types .= 'ii';
        $params[] = $offset;
        $params[] = $items_per_page;
        
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $sales = $stmt->get_result();
        
        // Get date range options for filter
        $date_range_options = [
            'all' => 'All Time',
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
            'quarter' => 'This Quarter',
            'year' => 'This Year',
            'custom' => 'Custom Range'
        ];
        
        // Get customers for filter
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
        $customers_result = $stmt->get_result();
        
        $customer_options = ['0' => 'All Customers'];
        while ($customer = $customers_result->fetch_assoc()) {
            $customer_options[$customer['customer_id']] = $customer['name'];
        }
        
        // Get users for filter (for admin only)
        $user_options = ['0' => 'All Users'];
        if (is_admin()) {
            $users_query = "SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name";
            $users_result = $conn->query($users_query);
            
            while ($user = $users_result->fetch_assoc()) {
                $user_options[$user['user_id']] = $user['first_name'] . ' ' . $user['last_name'];
            }
        }
          // Calculate total sales amount
        $total_query = "SELECT SUM(amount) as total_amount FROM sales s" . $where_clause;
        $stmt = $conn->prepare($total_query);
        
        if (!empty($params) && count($params) > 2) { // Exclude pagination params
            $param_types_trimmed = substr($param_types, 0, -2);
            $params_trimmed = array_slice($params, 0, -2);
            $stmt->bind_param($param_types_trimmed, ...$params_trimmed);
        }
        
        $stmt->execute();
        $total_result = $stmt->get_result();
        $total_amount = $total_result->fetch_assoc()['total_amount'] ?: 0;
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Sales</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Record New Sale
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
        
        <!-- Sales Summary Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up"></i> Sales Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <h6 class="text-muted">Total Sales</h6>
                        <h3><?php echo $total_items; ?></h3>
                    </div>
                    <div class="col-md-4 text-center">
                        <h6 class="text-muted">Total Revenue</h6>
                        <h3><?php echo format_currency($total_amount); ?></h3>
                    </div>
                    <div class="col-md-4 text-center">
                        <h6 class="text-muted">Average Sale</h6>
                        <h3><?php echo format_currency($total_items > 0 ? $total_amount / $total_items : 0); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel"></i> Filter Sales
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php" method="get" class="row g-3">
                    <input type="hidden" name="page" value="sales">
                    
                    <div class="col-md-3">
                        <label for="date_range" class="form-label">Date Range</label>
                        <select name="date_range" id="date_range" class="form-select" onchange="toggleCustomDateRange(this.value)">
                            <?php foreach ($date_range_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $date_range === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Custom date range fields (hidden by default) -->
                    <div class="col-md-3 custom-date-range" <?php echo $date_range !== 'custom' ? 'style="display:none"' : ''; ?>>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">From</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" 
                                       value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">To</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                       value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <?php foreach ($customer_options as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $customer_filter == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (is_admin()): ?>
                    <div class="col-md-3">
                        <label for="user_id" class="form-label">Sales Agent</label>
                        <select name="user_id" id="user_id" class="form-select">
                            <?php foreach ($user_options as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $user_filter == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Description or customer..." 
                               value="<?php echo $search; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3 align-self-end">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($search) || $customer_filter > 0 || $user_filter > 0 || $date_range != 'all'): ?>
                    <div class="col-md-12">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=sales" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Clear Filters
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Sales List -->
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul"></i> Sales List
                        </h5>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="text-muted">
                            Showing <?php echo min(($current_page - 1) * $items_per_page + 1, $total_items); ?> to 
                            <?php echo min($current_page * $items_per_page, $total_items); ?> of 
                            <?php echo $total_items; ?> sales
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="10%">Date</th>
                                <th width="20%">Customer</th>
                                <th width="15%">Amount</th>
                                <th>Description</th>
                                <th width="15%">Recorded By</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sales->num_rows > 0): ?>
                                <?php while ($sale = $sales->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo format_date($sale['sale_date']); ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $sale['customer_id']; ?>">
                                                <?php echo $sale['customer_name']; ?>
                                            </a>
                                        </td>
                                        <td class="font-weight-bold">
                                            <?php echo format_currency($sale['amount']); ?>
                                        </td>
                                        <td>
                                            <?php if ($sale['lead_id']): ?>
                                                <strong>Lead:</strong> 
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $sale['lead_id']; ?>">
                                                    <?php echo $sale['lead_title']; ?>
                                                </a>
                                                <br>
                                            <?php endif; ?>
                                            <?php echo $sale['description']; ?>
                                        </td>
                                        <td>
                                            <?php echo $sale['first_name'] . ' ' . $sale['last_name']; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=view&id=<?php echo $sale['sale_id']; ?>" class="btn btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=edit&id=<?php echo $sale['sale_id']; ?>" class="btn btn-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (is_admin()): ?>
                                                    <a href="<?php echo BASE_URL; ?>/index.php?page=sales&action=delete&id=<?php echo $sale['sale_id']; ?>" 
                                                       class="btn btn-danger confirm-delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No sales found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="mt-3">
                    <?php echo pagination($total_items, $items_per_page, $current_page); ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function toggleCustomDateRange(value) {
    const customDateRange = document.querySelector('.custom-date-range');
    if (value === 'custom') {
        customDateRange.style.display = 'block';
    } else {
        customDateRange.style.display = 'none';
    }
}
</script>
<?php
        break;
}
?>