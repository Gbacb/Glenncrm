<?php
// Get filter parameters
$report_type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'sales';
$date_range = isset($_GET['range']) ? sanitize_input($_GET['range']) : 'month';
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Determine date range based on filter
$end_date = date('Y-m-d');
switch ($date_range) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-1 week'));
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-1 month'));
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-3 months'));
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-1 month'));
}

// Custom date range if provided
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $custom_start = sanitize_input($_GET['start_date']);
    $custom_end = sanitize_input($_GET['end_date']);
    
    if (!empty($custom_start) && !empty($custom_end)) {
        $start_date = $custom_start;
        $end_date = $custom_end;
        $date_range = 'custom';
    }
}

// Initialize variables for report data
$report_data = [];
$chart_data = [];

// Process different report types
switch ($report_type) {
    case 'sales':
        // Sales Report
        $query_params = [];
        $query_types = '';
        $where_clauses = ["sale_date BETWEEN ? AND ?"];
        $query_params[] = $start_date;
        $query_params[] = $end_date;
        $query_types .= 'ss';
        
        // Filter by user if specified and not admin
        if ($user_filter > 0 || !is_admin()) {
            $user_id = $user_filter > 0 ? $user_filter : $_SESSION['user_id'];
            $where_clauses[] = "s.user_id = ?";
            $query_params[] = $user_id;
            $query_types .= 'i';
        }
        
        // Build the query with WHERE conditions
        $where_clause = implode(" AND ", $where_clauses);
        
        // Get total sales amount
        $query = "SELECT SUM(amount) as total_amount, COUNT(*) as total_sales 
                 FROM sales s 
                 WHERE $where_clause";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $totals = $result->fetch_assoc();
        
        // Get sales by day for chart
        $query = "SELECT DATE(sale_date) as date, SUM(amount) as amount 
                 FROM sales s 
                 WHERE $where_clause 
                 GROUP BY DATE(sale_date) 
                 ORDER BY date";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $daily_result = $stmt->get_result();
        
        $chart_labels = [];
        $chart_values = [];
        while ($row = $daily_result->fetch_assoc()) {
            $chart_labels[] = format_date($row['date']);
            $chart_values[] = floatval($row['amount']);
        }
        
        // Get top customers
        $query = "SELECT c.customer_id, c.name, COUNT(s.sale_id) as sales_count, SUM(s.amount) as total_amount 
                 FROM sales s 
                 JOIN customers c ON s.customer_id = c.customer_id 
                 WHERE $where_clause 
                 GROUP BY c.customer_id 
                 ORDER BY total_amount DESC 
                 LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $top_customers = $stmt->get_result();
        
        // Get top performing users if admin
        $top_users = null;
        if (is_admin()) {
            $query = "SELECT u.user_id, u.first_name, u.last_name, COUNT(s.sale_id) as sales_count, SUM(s.amount) as total_amount 
                     FROM sales s 
                     JOIN users u ON s.user_id = u.user_id 
                     WHERE sale_date BETWEEN ? AND ? 
                     GROUP BY u.user_id 
                     ORDER BY total_amount DESC 
                     LIMIT 5";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ss', $start_date, $end_date);
            $stmt->execute();
            $top_users = $stmt->get_result();
        }
        
        $chart_data = [
            'labels' => $chart_labels,
            'values' => $chart_values,
            'title' => 'Sales Trend',
            'type' => 'line'
        ];
        
        $report_data = [
            'totals' => $totals,
            'top_customers' => $top_customers,
            'top_users' => $top_users
        ];
        break;
        
    case 'leads':
        // Leads Report
        $query_params = [];
        $query_types = '';
        $where_clauses = ["created_at BETWEEN ? AND ?"];
        $query_params[] = $start_date . ' 00:00:00';
        $query_params[] = $end_date . ' 23:59:59';
        $query_types .= 'ss';
        
        // Filter by user if specified and not admin
        if ($user_filter > 0 || !is_admin()) {
            $user_id = $user_filter > 0 ? $user_filter : $_SESSION['user_id'];
            $where_clauses[] = "l.assigned_to = ?";
            $query_params[] = $user_id;
            $query_types .= 'i';
        }
        
        // Build the query with WHERE conditions
        $where_clause = implode(" AND ", $where_clauses);
        
        // Get total leads and conversion rate
        $query = "SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'closed_won' THEN 1 ELSE 0 END) as won,
                    SUM(CASE WHEN status = 'closed_lost' THEN 1 ELSE 0 END) as lost
                 FROM leads l 
                 WHERE $where_clause";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $totals = $result->fetch_assoc();
        
        // Calculate conversion rate
        $closed_leads = $totals['won'] + $totals['lost'];
        $conversion_rate = ($closed_leads > 0) ? ($totals['won'] / $closed_leads) * 100 : 0;
        $totals['conversion_rate'] = round($conversion_rate, 2);
        
        // Get leads by status for chart
        $query = "SELECT status, COUNT(*) as count 
                 FROM leads l 
                 WHERE $where_clause 
                 GROUP BY status";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $status_result = $stmt->get_result();
        
        $chart_labels = [];
        $chart_values = [];
        while ($row = $status_result->fetch_assoc()) {
            $chart_labels[] = ucfirst(str_replace('_', ' ', $row['status']));
            $chart_values[] = intval($row['count']);
        }
        
        $chart_data = [
            'labels' => $chart_labels,
            'values' => $chart_values,
            'title' => 'Leads by Status',
            'type' => 'pie'
        ];
        
        // Get leads created per day
        $query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                 FROM leads l 
                 WHERE $where_clause 
                 GROUP BY DATE(created_at) 
                 ORDER BY date";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $daily_result = $stmt->get_result();
        
        $trend_labels = [];
        $trend_values = [];
        while ($row = $daily_result->fetch_assoc()) {
            $trend_labels[] = format_date($row['date']);
            $trend_values[] = intval($row['count']);
        }
        
        $report_data = [
            'totals' => $totals,
            'trend_labels' => $trend_labels,
            'trend_values' => $trend_values
        ];
        break;
        
    case 'activity':
        // Activity Report
        $query_params = [];
        $query_types = '';
        $where_clauses = ["created_at BETWEEN ? AND ?"];
        $query_params[] = $start_date . ' 00:00:00';
        $query_params[] = $end_date . ' 23:59:59';
        $query_types .= 'ss';
        
        // Filter by user if specified and not admin
        if ($user_filter > 0 || !is_admin()) {
            $user_id = $user_filter > 0 ? $user_filter : $_SESSION['user_id'];
            $where_clauses[] = "user_id = ?";
            $query_params[] = $user_id;
            $query_types .= 'i';
        }
        
        // Build the query with WHERE conditions
        $where_clause = implode(" AND ", $where_clauses);
        
        // Get interactions count by type
        $query = "SELECT interaction_type, COUNT(*) as count 
                 FROM interactions 
                 WHERE $where_clause 
                 GROUP BY interaction_type";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $interaction_types = $stmt->get_result();
        
        $chart_labels = [];
        $chart_values = [];
        while ($row = $interaction_types->fetch_assoc()) {
            $chart_labels[] = ucfirst($row['interaction_type']);
            $chart_values[] = intval($row['count']);
        }
        
        // Get total interactions
        $query = "SELECT COUNT(*) as total FROM interactions WHERE $where_clause";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_interactions = $result->fetch_assoc()['total'];
        
        // Get interactions per day
        $query = "SELECT DATE(interaction_date) as date, COUNT(*) as count 
                 FROM interactions 
                 WHERE $where_clause 
                 GROUP BY DATE(interaction_date) 
                 ORDER BY date";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($query_types, ...$query_params);
        $stmt->execute();
        $daily_result = $stmt->get_result();
        
        $trend_labels = [];
        $trend_values = [];
        while ($row = $daily_result->fetch_assoc()) {
            $trend_labels[] = format_date($row['date']);
            $trend_values[] = intval($row['count']);
        }
        
        $chart_data = [
            'labels' => $chart_labels,
            'values' => $chart_values,
            'title' => 'Interactions by Type',
            'type' => 'doughnut'
        ];
        
        $report_data = [
            'total_interactions' => $total_interactions,
            'trend_labels' => $trend_labels,
            'trend_values' => $trend_values
        ];
        break;
}

// Get users for filter dropdown
$users_query = "SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name";
$users_result = $conn->query($users_query);

$user_options = ['0' => 'All Users'];
while ($user = $users_result->fetch_assoc()) {
    $user_options[$user['user_id']] = $user['first_name'] . ' ' . $user['last_name'];
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Reports & Analytics</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-filter"></i> Report Options
                </h5>
            </div>
            <div class="card-body">
                <form action="<?php echo BASE_URL; ?>/index.php" method="get" class="row g-3">
                    <input type="hidden" name="page" value="reports">
                    
                    <div class="col-md-3">
                        <label for="type" class="form-label">Report Type</label>
                        <select name="type" id="type" class="form-select">
                            <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                            <option value="leads" <?php echo $report_type === 'leads' ? 'selected' : ''; ?>>Leads Report</option>
                            <option value="activity" <?php echo $report_type === 'activity' ? 'selected' : ''; ?>>Activity Report</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="range" class="form-label">Date Range</label>
                        <select name="range" id="range" class="form-select" onchange="toggleCustomDateRange(this.value)">
                            <option value="week" <?php echo $date_range === 'week' ? 'selected' : ''; ?>>Last Week</option>
                            <option value="month" <?php echo $date_range === 'month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="quarter" <?php echo $date_range === 'quarter' ? 'selected' : ''; ?>>Last Quarter</option>
                            <option value="year" <?php echo $date_range === 'year' ? 'selected' : ''; ?>>Last Year</option>
                            <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="user_id" class="form-label">User</label>
                        <select name="user_id" id="user_id" class="form-select" <?php echo !is_admin() ? 'disabled' : ''; ?>>
                            <?php foreach ($user_options as $id => $name): ?>
                                <option value="<?php echo $id; ?>" <?php echo $user_filter == $id ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!is_admin()): ?>
                            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-3 align-self-end">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Generate Report
                            </button>
                        </div>
                    </div>
                    
                    <!-- Custom date range inputs (hidden by default) -->
                    <div class="col-md-6 custom-date-range" style="display: <?php echo $date_range === 'custom' ? 'block' : 'none'; ?>;">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Report Content -->
        <div class="report-content">
            <?php if ($report_type === 'sales'): ?>
                <!-- Sales Report -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Sales</h5>
                                <h2 class="display-4"><?php echo $report_data['totals']['total_sales']; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Revenue</h5>
                                <h2 class="display-4"><?php echo format_currency($report_data['totals']['total_amount'] ?: 0); ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Average Sale</h5>
                                <h2 class="display-4">
                                    <?php 
                                        $avg = $report_data['totals']['total_sales'] > 0 ? 
                                            $report_data['totals']['total_amount'] / $report_data['totals']['total_sales'] : 0;
                                        echo format_currency($avg);
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Sales Trend</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="mainChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Top Customers</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($report_data['top_customers']->num_rows > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Sales</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($customer = $report_data['top_customers']->fetch_assoc()): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $customer['customer_id']; ?>">
                                                                <?php echo $customer['name']; ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo $customer['sales_count']; ?></td>
                                                        <td><?php echo format_currency($customer['total_amount']); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No sales data available for this period</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (is_admin() && $report_data['top_users']): ?>
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Top Performing Users</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($report_data['top_users']->num_rows > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>User</th>
                                                        <th>Sales</th>
                                                        <th>Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php while ($user = $report_data['top_users']->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                                            <td><?php echo $user['sales_count']; ?></td>
                                                            <td><?php echo format_currency($user['total_amount']); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No sales data available for this period</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($report_type === 'leads'): ?>
                <!-- Leads Report -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Leads</h5>
                                <h2 class="display-4"><?php echo $report_data['totals']['total_leads']; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Closed Won</h5>
                                <h2 class="display-4"><?php echo $report_data['totals']['won']; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Conversion Rate</h5>
                                <h2 class="display-4"><?php echo $report_data['totals']['conversion_rate']; ?>%</h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Leads Created Over Time</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="leadsTrendChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Leads by Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="mainChart" height="260"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($report_type === 'activity'): ?>
                <!-- Activity Report -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Total Interactions</h5>
                                <h2 class="display-4"><?php echo $report_data['total_interactions']; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">Daily Average</h5>
                                <h2 class="display-4">
                                    <?php 
                                        $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
                                        $days = max(1, $days);
                                        $daily_avg = $report_data['total_interactions'] / $days;
                                        echo number_format($daily_avg, 1);
                                    ?>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Interactions Over Time</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="activityTrendChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Interactions by Type</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="mainChart" height="260"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main chart
    const ctx = document.getElementById('mainChart').getContext('2d');
    const chartType = '<?php echo $chart_data['type']; ?>';
    const mainChart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: <?php echo json_encode($chart_data['labels']); ?>,
            datasets: [{
                label: '<?php echo $chart_data['title']; ?>',
                data: <?php echo json_encode($chart_data['values']); ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(153, 102, 255, 0.5)',
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 159, 64, 0.5)',
                    'rgba(201, 203, 207, 0.5)'
                ],
                borderColor: [
                    'rgb(54, 162, 235)',
                    'rgb(75, 192, 192)',
                    'rgb(255, 206, 86)',
                    'rgb(153, 102, 255)',
                    'rgb(255, 99, 132)',
                    'rgb(255, 159, 64)',
                    'rgb(201, 203, 207)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            legend: {
                position: 'bottom',
            }
        }
    });
    
    <?php if ($report_type === 'leads' && !empty($report_data['trend_labels'])): ?>
    // Leads trend chart
    const leadsCtx = document.getElementById('leadsTrendChart').getContext('2d');
    const leadsTrendChart = new Chart(leadsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($report_data['trend_labels']); ?>,
            datasets: [{
                label: 'Leads Created',
                data: <?php echo json_encode($report_data['trend_values']); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.3)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($report_type === 'activity' && !empty($report_data['trend_labels'])): ?>
    // Activity trend chart
    const activityCtx = document.getElementById('activityTrendChart').getContext('2d');
    const activityTrendChart = new Chart(activityCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($report_data['trend_labels']); ?>,
            datasets: [{
                label: 'Interactions',
                data: <?php echo json_encode($report_data['trend_values']); ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.3)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 2,
                tension: 0.1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
});

function toggleCustomDateRange(value) {
    const customDateRange = document.querySelector('.custom-date-range');
    if (value === 'custom') {
        customDateRange.style.display = 'block';
    } else {
        customDateRange.style.display = 'none';
    }
}

function printReport() {
    window.print();
}

function exportCSV() {
    alert('CSV export functionality would be implemented here');
    // In a real implementation, this would create a CSV file from the report data
}

function exportPDF() {
    alert('PDF export functionality would be implemented here');
    // In a real implementation, this would generate a PDF from the report data
}
</script>