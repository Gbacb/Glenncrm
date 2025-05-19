<?php
// Handle different actions for lead management
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'list';
$lead_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Determine current page for pagination
$current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$items_per_page = intval(get_setting('items_per_page', 10));
$offset = ($current_page - 1) * $items_per_page;

switch ($action) {
    case 'view':
        // Include view lead page
        include 'leads/view.php';
        break;
        
    case 'add':
        // Include add lead page
        include 'leads/add.php';
        break;
        
    case 'edit':
        // Include edit lead page
        include 'leads/edit.php';
        break;
        
    case 'delete':
        // Check if user has permission to delete
        if (!is_admin()) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to delete leads.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
            exit;
        }
        
        // Verify lead exists
        $stmt = $conn->prepare("SELECT lead_id FROM leads WHERE lead_id = ?");
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Lead not found.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
            exit;
        }
        
        // Use the safe lead deletion function
        $delete_result = delete_lead($lead_id);
        
        if ($delete_result['success']) {
            // Log activity
            log_activity($_SESSION['user_id'], 'deleted', 'lead', $lead_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Lead has been deleted successfully.'
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => $delete_result['message']
            ];
        }
        
        echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
        exit;
        break;
        
    default: // list view
        // Get filter parameters
        $status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
        $customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        
        // Build query conditions
        $conditions = [];
        $params = [];
        $param_types = '';
        
        if (!empty($status_filter)) {
            $conditions[] = "l.status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
        
        if ($customer_filter > 0) {
            $conditions[] = "l.customer_id = ?";
            $params[] = $customer_filter;
            $param_types .= 'i';
        }
        
        if (!empty($search)) {
            $search_term = "%$search%";
            $conditions[] = "(l.title LIKE ? OR l.description LIKE ? OR c.name LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $param_types .= 'sss';
        }
        
        // Only show leads assigned to current user if not admin
        if (!is_admin()) {
            $conditions[] = "l.assigned_to = ?";
            $params[] = $_SESSION['user_id'];
            $param_types .= 'i';
        }
        
        $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
        
        // Count total records for pagination
        $count_query = "SELECT COUNT(*) as total FROM leads l
                       LEFT JOIN customers c ON l.customer_id = c.customer_id" . $where_clause;
        $stmt = $conn->prepare($count_query);
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_items = $row['total'];
        
        // Get leads with pagination
        $query = "SELECT l.*, c.name as customer_name, u.first_name, u.last_name 
                 FROM leads l 
                 LEFT JOIN customers c ON l.customer_id = c.customer_id
                 LEFT JOIN users u ON l.assigned_to = u.user_id" 
                 . $where_clause . 
                 " ORDER BY l.created_at DESC LIMIT ?, ?";
                 
        $stmt = $conn->prepare($query);
        
        // Add pagination parameters
        $param_types .= 'ii';
        $params[] = $offset;
        $params[] = $items_per_page;
        
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $leads = $stmt->get_result();
        
        // Get lead status options for filter
        $status_options = [
            '' => 'All Statuses',
            'new' => 'New',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'proposal' => 'Proposal',
            'negotiation' => 'Negotiation',
            'closed_won' => 'Closed Won',
            'closed_lost' => 'Closed Lost'
        ];
        
        // Get customers for filter
        $customers_query = "SELECT customer_id, name FROM customers ORDER BY name";
        $stmt = $conn->prepare($customers_query);
        $stmt->execute();
        $customers_result = $stmt->get_result();
        
        $customer_options = ['0' => 'All Customers'];
        while ($customer = $customers_result->fetch_assoc()) {
            $customer_options[$customer['customer_id']] = $customer['name'];
        }
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Leads</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add New Lead
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
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel"></i> Filter Leads
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php" method="get" class="row g-3">
                    <input type="hidden" name="page" value="leads">
                    
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Lead title or description..." value="<?php echo $search; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <?php foreach ($status_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($status_filter === $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <?php foreach ($customer_options as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo ($customer_filter == $id) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 align-self-end">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Leads List -->
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul"></i> Leads List
                        </h5>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="text-muted">
                            Showing <?php echo min(($current_page - 1) * $items_per_page + 1, $total_items); ?> to 
                            <?php echo min($current_page * $items_per_page, $total_items); ?> of 
                            <?php echo $total_items; ?> leads
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Value</th>
                                <th>Expected Close</th>
                                <th>Assigned To</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($leads->num_rows > 0): ?>
                                <?php while ($lead = $leads->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $lead['lead_id']; ?>">
                                                <?php echo $lead['title']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($lead['customer_id']): ?>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $lead['customer_id']; ?>">
                                                    <?php echo $lead['customer_name']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">No customer</span>
                                            <?php endif; ?>
                                        </td>
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
                                        <td><?php echo format_currency($lead['value']); ?></td>
                                        <td><?php echo $lead['expected_close_date'] ? format_date($lead['expected_close_date']) : '-'; ?></td>
                                        <td>
                                            <?php 
                                                echo $lead['assigned_to'] ? 
                                                    $lead['first_name'] . ' ' . $lead['last_name'] : 
                                                    '<span class="text-muted">Unassigned</span>'; 
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $lead['lead_id']; ?>" class="btn btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=edit&id=<?php echo $lead['lead_id']; ?>" class="btn btn-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (is_admin()): ?>
                                                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=delete&id=<?php echo $lead['lead_id']; ?>" 
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
                                    <td colspan="7" class="text-center">No leads found</td>
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