<?php
// Handle different actions for interaction management
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : 'list';
$interaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Determine current page for pagination
$current_page = isset($_GET['p']) ? intval($_GET['p']) : 1;
$items_per_page = intval(get_setting('items_per_page', 10));
$offset = ($current_page - 1) * $items_per_page;

switch ($action) {
    case 'view':
        include 'interactions/view.php';
        break;
        
    case 'add':
        include 'interactions/add.php';
        break;
        
    case 'edit':
        include 'interactions/edit.php';
        break;
        
    case 'delete':
        // Check if user has permission to delete
        if (!is_admin()) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'You do not have permission to delete interactions.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=interactions";</script>';
            exit;
        }
        
        // Verify interaction exists
        $stmt = $conn->prepare("SELECT interaction_id FROM interactions WHERE interaction_id = ?");
        $stmt->bind_param("i", $interaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Interaction not found.'
            ];
            echo '<script>window.location.href="'.BASE_URL.'/index.php?page=interactions";</script>';
            exit;
        }
        
        // Delete interaction
        $stmt = $conn->prepare("DELETE FROM interactions WHERE interaction_id = ?");
        $stmt->bind_param("i", $interaction_id);
        $result = $stmt->execute();
        
        if ($result) {
            // Log activity
            log_activity($_SESSION['user_id'], 'deleted', 'interaction', $interaction_id);
            
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Interaction has been deleted successfully.'
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Failed to delete interaction.'
            ];
        }
        
        echo '<script>window.location.href="'.BASE_URL.'/index.php?page=interactions";</script>';
        exit;
        
    default: // list view
        // Get filter parameters
        $type_filter = isset($_GET['type']) ? sanitize_input($_GET['type']) : '';
        $customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        $lead_filter = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
        $date_range = isset($_GET['date_range']) ? sanitize_input($_GET['date_range']) : 'all';
        $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
        
        // Build query conditions
        $conditions = [];
        $params = [];
        $param_types = '';
        
        if (!empty($type_filter)) {
            $conditions[] = "i.interaction_type = ?";
            $params[] = $type_filter;
            $param_types .= 's';
        }
        
        if ($customer_filter > 0) {
            $conditions[] = "i.customer_id = ?";
            $params[] = $customer_filter;
            $param_types .= 'i';
        }
        
        if ($lead_filter > 0) {
            $conditions[] = "i.lead_id = ?";
            $params[] = $lead_filter;
            $param_types .= 'i';
        }
        
        // Date range filter
        $today = date('Y-m-d');
        switch ($date_range) {
            case 'today':
                $conditions[] = "DATE(i.interaction_date) = ?";
                $params[] = $today;
                $param_types .= 's';
                break;
            case 'yesterday':
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $conditions[] = "DATE(i.interaction_date) = ?";
                $params[] = $yesterday;
                $param_types .= 's';
                break;
            case 'week':
                $week_ago = date('Y-m-d', strtotime('-7 days'));
                $conditions[] = "i.interaction_date BETWEEN ? AND ?";
                $params[] = $week_ago . ' 00:00:00';
                $params[] = $today . ' 23:59:59';
                $param_types .= 'ss';
                break;
            case 'month':
                $month_ago = date('Y-m-d', strtotime('-30 days'));
                $conditions[] = "i.interaction_date BETWEEN ? AND ?";
                $params[] = $month_ago . ' 00:00:00';
                $params[] = $today . ' 23:59:59';
                $param_types .= 'ss';
                break;
            case 'custom':
                if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                    $start_date = sanitize_input($_GET['start_date']);
                    $end_date = sanitize_input($_GET['end_date']);
                    if (!empty($start_date) && !empty($end_date)) {
                        $conditions[] = "DATE(i.interaction_date) BETWEEN ? AND ?";
                        $params[] = $start_date;
                        $params[] = $end_date;
                        $param_types .= 'ss';
                    }
                }
                break;
        }
        
        if (!empty($search)) {
            $search_term = "%$search%";
            $conditions[] = "(i.notes LIKE ? OR c.name LIKE ? OR l.title LIKE ?)";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $param_types .= 'sss';
        }
        
        // Only show interactions for current user if not admin
        if (!is_admin()) {
            $conditions[] = "i.user_id = ?";
            $params[] = $_SESSION['user_id'];
            $param_types .= 'i';
        }
        
        $where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
        
        // Count total records for pagination
        $count_query = "SELECT COUNT(*) as total FROM interactions i 
                       LEFT JOIN customers c ON i.customer_id = c.customer_id 
                       LEFT JOIN leads l ON i.lead_id = l.lead_id" . $where_clause;
        $stmt = $conn->prepare($count_query);
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_items = $row['total'];
        
        // Get interactions with pagination
        $query = "SELECT i.*, c.name as customer_name, l.title as lead_title, u.first_name, u.last_name 
                 FROM interactions i 
                 LEFT JOIN customers c ON i.customer_id = c.customer_id
                 LEFT JOIN leads l ON i.lead_id = l.lead_id
                 LEFT JOIN users u ON i.user_id = u.user_id" 
                 . $where_clause . 
                 " ORDER BY i.interaction_date DESC LIMIT ?, ?";
                 
        $stmt = $conn->prepare($query);
        
        // Add pagination parameters
        $param_types .= 'ii';
        $params[] = $offset;
        $params[] = $items_per_page;
        
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $interactions = $stmt->get_result();
        
        // Get interaction type options for filter
        $type_options = [
            '' => 'All Types',
            'call' => 'Call',
            'email' => 'Email',
            'meeting' => 'Meeting',
            'other' => 'Other'
        ];
        
        // Get date range options for filter
        $date_range_options = [
            'all' => 'All Time',
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            'week' => 'Last 7 Days',
            'month' => 'Last 30 Days',
            'custom' => 'Custom Range'
        ];
        
        // Get customers for filter dropdown
        $query = is_admin() ? 
            "SELECT customer_id, name FROM customers ORDER BY name" : 
            "SELECT customer_id, name FROM customers WHERE assigned_to = ? ORDER BY name";
        $stmt = $conn->prepare($query);
        
        if (!is_admin()) {
            $stmt->bind_param("i", $_SESSION['user_id']);
        }
        
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
                <h1 class="m-0">Customer Interactions</h1>
            </div>
            <div class="col-sm-6">
                <div class="float-end">
                    <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=add" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add New Interaction
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
                    <i class="bi bi-funnel"></i> Filter Interactions
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php" method="get" class="row g-3">
                    <input type="hidden" name="page" value="interactions">
                    
                    <div class="col-md-3">
                        <label for="type" class="form-label">Interaction Type</label>
                        <select name="type" id="type" class="form-select">
                            <?php foreach ($type_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $type_filter === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                    
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" name="search" id="search" class="form-control" placeholder="Search interactions..." value="<?php echo $search; ?>">
                    </div>
                    
                    <!-- Custom date range fields (hidden by default) -->
                    <div class="col-md-6 custom-date-range" <?php echo $date_range !== 'custom' ? 'style="display:none"' : ''; ?>>
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
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($search) || $customer_filter > 0 || $lead_filter > 0 || $type_filter != '' || $date_range != 'all'): ?>
                    <div class="col-md-3">
                        <div class="d-grid mt-4">
                            <a href="<?php echo BASE_URL; ?>/index.php?page=interactions" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Clear Filters
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <!-- Interactions List -->
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-chat-dots"></i> Interactions List
                        </h5>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="text-muted">
                            Showing <?php echo min(($current_page - 1) * $items_per_page + 1, $total_items); ?> to 
                            <?php echo min($current_page * $items_per_page, $total_items); ?> of 
                            <?php echo $total_items; ?> interactions
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Lead</th>
                                <th>Duration</th>
                                <th>Notes</th>
                                <th>Recorded By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($interactions->num_rows > 0): ?>
                                <?php while ($interaction = $interactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo format_date($interaction['interaction_date']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $interaction['interaction_type'] == 'call' ? 'primary' : 
                                                ($interaction['interaction_type'] == 'email' ? 'info' : 
                                                ($interaction['interaction_type'] == 'meeting' ? 'success' : 'secondary')); 
                                            ?>">
                                                <?php echo ucfirst($interaction['interaction_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($interaction['customer_id']): ?>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $interaction['customer_id']; ?>">
                                                    <?php echo $interaction['customer_name']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($interaction['lead_id']): ?>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $interaction['lead_id']; ?>">
                                                    <?php echo $interaction['lead_title']; ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($interaction['duration']): ?>
                                                <?php echo $interaction['duration']; ?> min
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $notes = $interaction['notes'];
                                                echo strlen($notes) > 50 ? substr($notes, 0, 50) . '...' : $notes;
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo $interaction['first_name'] . ' ' . $interaction['last_name']; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=view&id=<?php echo $interaction['interaction_id']; ?>" class="btn btn-info" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=edit&id=<?php echo $interaction['interaction_id']; ?>" class="btn btn-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if (is_admin()): ?>
                                                    <a href="<?php echo BASE_URL; ?>/index.php?page=interactions&action=delete&id=<?php echo $interaction['interaction_id']; ?>" class="btn btn-danger confirm-delete" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No interactions found</td>
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