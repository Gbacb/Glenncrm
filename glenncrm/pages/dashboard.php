<?php
// Get dashboard stats
$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// Count total customers
$customer_query = $is_admin ? 
    "SELECT COUNT(*) as total FROM customers" : 
    "SELECT COUNT(*) as total FROM customers WHERE assigned_to = ?";
$stmt = $conn->prepare($customer_query);
if (!$is_admin) {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
$customers_count = $result->fetch_assoc()['total'];

// Count total leads
$lead_query = $is_admin ? 
    "SELECT COUNT(*) as total FROM leads" : 
    "SELECT COUNT(*) as total FROM leads WHERE assigned_to = ?";
$stmt = $conn->prepare($lead_query);
if (!$is_admin) {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
$leads_count = $result->fetch_assoc()['total'];

// Count total sales
$sales_query = $is_admin ? 
    "SELECT SUM(amount) as total FROM sales" : 
    "SELECT SUM(amount) as total FROM sales WHERE user_id = ?";
$stmt = $conn->prepare($sales_query);
if (!$is_admin) {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
$sales_total = $result->fetch_assoc()['total'];
$sales_total = $sales_total ? $sales_total : 0;

// Count reminders
$reminder_query = "SELECT COUNT(*) as total FROM reminders WHERE user_id = ? AND is_completed = 0";
$stmt = $conn->prepare($reminder_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reminders_count = $result->fetch_assoc()['total'];

// Get upcoming reminders
$reminder_query = "SELECT r.*, c.name as customer_name, l.title as lead_title 
                  FROM reminders r 
                  LEFT JOIN customers c ON r.customer_id = c.customer_id
                  LEFT JOIN leads l ON r.lead_id = l.lead_id
                  WHERE r.user_id = ? AND r.is_completed = 0 AND r.reminder_date > NOW()
                  ORDER BY r.reminder_date ASC
                  LIMIT 5";
$stmt = $conn->prepare($reminder_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_reminders = $stmt->get_result();

// Get recent leads
$lead_query = $is_admin ? 
    "SELECT l.*, c.name as customer_name FROM leads l 
     LEFT JOIN customers c ON l.customer_id = c.customer_id
     ORDER BY l.created_at DESC LIMIT 5" : 
    "SELECT l.*, c.name as customer_name FROM leads l 
     LEFT JOIN customers c ON l.customer_id = c.customer_id
     WHERE l.assigned_to = ? ORDER BY l.created_at DESC LIMIT 5";
$stmt = $conn->prepare($lead_query);
if (!$is_admin) {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$recent_leads = $stmt->get_result();

// Get chart data for sales by month (last 6 months)
$months = [];
$sales_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime("-$i months"));
    
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $sales_query = $is_admin ? 
        "SELECT SUM(amount) as total FROM sales WHERE sale_date BETWEEN ? AND ?" : 
        "SELECT SUM(amount) as total FROM sales WHERE user_id = ? AND sale_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sales_query);
    
    if ($is_admin) {
        $stmt->bind_param("ss", $start_date, $end_date);
    } else {
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $monthly_total = $result->fetch_assoc()['total'];
    $sales_data[] = $monthly_total ? $monthly_total : 0;
}

// Get lead status distribution
$status_query = $is_admin ? 
    "SELECT status, COUNT(*) as count FROM leads GROUP BY status" : 
    "SELECT status, COUNT(*) as count FROM leads WHERE assigned_to = ? GROUP BY status";
$stmt = $conn->prepare($status_query);
if (!$is_admin) {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

$lead_status_labels = [];
$lead_status_counts = [];
while ($row = $result->fetch_assoc()) {
    $lead_status_labels[] = ucfirst(str_replace('_', ' ', $row['status']));
    $lead_status_counts[] = $row['count'];
}
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Dashboard</h1>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Show alerts if any -->
        <?php show_alert(); ?>
        
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card stats-card bg-primary text-white mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Customers</h5>
                            <h2 class="mt-2 mb-0"><?php echo $customers_count; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=customers" class="small text-white stretched-link">View Details</a>
                        <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card stats-card bg-success text-white mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Leads</h5>
                            <h2 class="mt-2 mb-0"><?php echo $leads_count; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-funnel"></i>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=leads" class="small text-white stretched-link">View Details</a>
                        <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card stats-card bg-warning text-white mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Sales</h5>
                            <h2 class="mt-2 mb-0"><?php echo format_currency($sales_total); ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=sales" class="small text-white stretched-link">View Details</a>
                        <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="card stats-card bg-danger text-white mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">Reminders</h5>
                            <h2 class="mt-2 mb-0"><?php echo $reminders_count; ?></h2>
                        </div>
                        <div class="stats-icon">
                            <i class="bi bi-bell"></i>
                        </div>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a href="<?php echo BASE_URL; ?>/index.php?page=reminders" class="small text-white stretched-link">View Details</a>
                        <div class="small text-white"><i class="bi bi-arrow-right"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-bar-chart"></i>
                        Sales Last 6 Months
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" width="100%" height="40"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-pie-chart"></i>
                        Lead Status Distribution
                    </div>
                    <div class="card-body">
                        <canvas id="leadsChart" width="100%" height="40"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Sections -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i>
                        Recent Leads
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Lead</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Value</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_leads->num_rows > 0): ?>
                                        <?php while($lead = $recent_leads->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $lead['title']; ?></td>
                                                <td><?php echo $lead['customer_name']; ?></td>
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
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $lead['lead_id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No recent leads found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-bell"></i>
                        Upcoming Reminders
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Reminder</th>
                                        <th>Related To</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($upcoming_reminders->num_rows > 0): ?>
                                        <?php while($reminder = $upcoming_reminders->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $reminder['title']; ?></td>
                                                <td>
                                                    <?php if ($reminder['customer_id'] && $reminder['customer_name']): ?>
                                                        <a href="<?php echo BASE_URL; ?>/index.php?page=customers&action=view&id=<?php echo $reminder['customer_id']; ?>">
                                                            <?php echo $reminder['customer_name']; ?>
                                                        </a>
                                                    <?php elseif ($reminder['lead_id'] && $reminder['lead_title']): ?>
                                                        <a href="<?php echo BASE_URL; ?>/index.php?page=leads&action=view&id=<?php echo $reminder['lead_id']; ?>">
                                                            <?php echo $reminder['lead_title']; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        N/A
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo format_date($reminder['reminder_date']); ?></td>
                                                <td>
                                                    <a href="<?php echo BASE_URL; ?>/index.php?page=reminders&action=complete&id=<?php echo $reminder['reminder_id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No upcoming reminders</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts with PHP data
    initDashboardCharts(
        {
            labels: <?php echo json_encode($months); ?>,
            values: <?php echo json_encode($sales_data); ?>
        },
        {
            labels: <?php echo json_encode($lead_status_labels); ?>,
            values: <?php echo json_encode($lead_status_counts); ?>
        }
    );
});
</script>