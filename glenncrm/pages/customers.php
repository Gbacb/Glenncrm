<?php
// Handle different actions for customer management
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'list';
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Determine current page for pagination
$current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$items_per_page = intval(get_setting('items_per_page', 10));
$offset = ($current_page - 1) * $items_per_page;

switch ($action) {
    case 'view':
        include 'customers/view.php';
        break;
        
    case 'add':
        include 'customers/add.php';
        break;
        
    case 'edit':
        include 'customers/edit.php';
        break;
        
    case 'delete':
        // Check if user has permission to delete
        if (!is_admin()) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to delete customers.'
            ];
            header('Location: ' . BASE_URL . '/index.php?page=customers');
            exit;
        }
        
        // Verify customer exists
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Customer not found.'
            ];
            header('Location: ' . BASE_URL . '/index.php?page=customers');
            exit;
        }
        
        // Delete customer
        $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customer_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Log activity
            log_activity($_SESSION['user_id'], 'deleted', 'customer', $customer_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Customer has been deleted successfully.'
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Failed to delete customer. The customer might have associated records.'
            ];
        }
        
        header('Location: ' . BASE_URL . '/index.php?page=customers');
        exit;
        break;
        
    default: // list view
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
        $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        
        // Build query conditions
        $conditions = [];
        $params = [];
        $param_types = '';
        
        if (!empty($status_filter)) {
            $conditions[] = "status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
          if (!empty($search)) {
            $search_term = "%$search%";
            $conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $param_types .= 'sss';
        }
        
        // Only show customers assigned to current user if not admin
        if (!is_admin()) {
            $conditions[] = "assigned_to = ?";
            $params[] = $_SESSION['user_id'];
            $param_types .= 'i';
        }
        
        $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
          // Count total records for pagination
        $count_query = "SELECT COUNT(*) as total FROM customers c" . $where_clause;
        $stmt = $conn->prepare($count_query);
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_items = $row['total'];
        
        // Get customers with pagination
        $query = "SELECT c.*, u.first_name, u.last_name 
                 FROM customers c 
                 LEFT JOIN users u ON c.assigned_to = u.user_id" 
                 . $where_clause . 
                 " ORDER BY c.name ASC LIMIT ?, ?";
                 
        $stmt = $conn->prepare($query);
        
        // Add pagination parameters
        $param_types .= 'ii';
        $params[] = $offset;
        $params[] = $items_per_page;
        
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $customers = $stmt->get_result();
        
        // Get status options for filter
        $status_options = [
            '' => 'All Statuses',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'prospect' => 'Prospect'
        ];
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Customers</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add New Customer
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
        
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-8">
                        <form action="<?php echo BASE_URL; ?>/index.php" method="get" class="form-inline">
                            <input type="hidden" name="page" value="customers">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search customers..." value="<?php echo $search; ?>">
                                <select name="status" class="form-select">
                                    <?php foreach ($status_options as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                    <a href="<?php echo BASE_URL; ?>/index.php?page=customers" class="btn btn-secondary">
                                        <i class="bi bi-x-lg"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="text-muted">
                            Showing <?php echo min(($current_page - 1) * $items_per_page + 1, $total_items); ?> to 
                            <?php echo min($current_page * $items_per_page, $total_items); ?> of 
                            <?php echo $total_items; ?> customers
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($customers->num_rows > 0): ?>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $customer['customer_id']; ?></td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer['customer_id']; ?>">
                                                <?php echo $customer['name']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo $customer['email']; ?></td>
                                        <td><?php echo $customer['phone']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $customer['status'] == 'active' ? 'success' : 
                                                    ($customer['status'] == 'inactive' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                echo $customer['assigned_to'] ? 
                                                    $customer['first_name'] . ' ' . $customer['last_name'] : 
                                                    'Unassigned'; 
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer['customer_id']; ?>" class="btn btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=edit&id=<?php echo $customer['customer_id']; ?>" class="btn btn-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (is_admin()): ?>
                                                    <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=delete&id=<?php echo $customer['customer_id']; ?>" 
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
                                    <td colspan="7" class="text-center">No customers found</td>
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
<?php
        break;
}
?>