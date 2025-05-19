<?php
/**
 * AJAX Handler for dynamic data loading
 */
require_once '../config/database.php';
require_once 'functions.php';
require_once 'auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Get the requested action
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';

// Process different AJAX requests
switch ($action) {
    case 'get_customer_leads':
        // Get leads for a specific customer
        $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        
        if ($customer_id <= 0) {
            echo json_encode([]);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT lead_id, title FROM leads WHERE customer_id = ? ORDER BY title ASC");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $leads = [];
        while ($row = $result->fetch_assoc()) {
            $leads[] = $row;
        }
        
        echo json_encode($leads);
        break;
        
    case 'get_lead_data':
        // Get details for a specific lead
        $lead_id = isset($_GET['lead_id']) ? intval($_GET['lead_id']) : 0;
        
        if ($lead_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid lead ID']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT * FROM leads WHERE lead_id = ?");
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo json_encode(['status' => 'error', 'message' => 'Lead not found']);
            exit;
        }
        
        $lead = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'data' => $lead]);
        break;
        
    case 'complete_reminder':
        // Mark a reminder as completed
        $reminder_id = isset($_GET['reminder_id']) ? intval($_GET['reminder_id']) : 0;
        
        if ($reminder_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid reminder ID']);
            exit;
        }
        
        // Check if reminder belongs to current user or user is admin
        $stmt = $conn->prepare("SELECT user_id FROM reminders WHERE reminder_id = ?");
        $stmt->bind_param("i", $reminder_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo json_encode(['status' => 'error', 'message' => 'Reminder not found']);
            exit;
        }
        
        $reminder = $result->fetch_assoc();
        
        if ($reminder['user_id'] != $_SESSION['user_id'] && !is_admin()) {
            echo json_encode(['status' => 'error', 'message' => 'You do not have permission to complete this reminder']);
            exit;
        }
        
        // Update reminder status
        $stmt = $conn->prepare("UPDATE reminders SET is_completed = 1 WHERE reminder_id = ?");
        $stmt->bind_param("i", $reminder_id);
        $result = $stmt->execute();
        
        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Reminder marked as completed']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update reminder']);
        }
        break;
        
    case 'get_lead_status_counts':
        // Get lead count by status for charts
        $user_id = $_SESSION['user_id'];
        $is_admin = is_admin();
        
        $query = $is_admin ? 
            "SELECT status, COUNT(*) as count FROM leads GROUP BY status" : 
            "SELECT status, COUNT(*) as count FROM leads WHERE assigned_to = ? GROUP BY status";
        
        $stmt = $conn->prepare($query);
        if (!$is_admin) {
            $stmt->bind_param("i", $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $status_counts = [];
        while ($row = $result->fetch_assoc()) {
            $status_counts[] = [
                'status' => ucfirst(str_replace('_', ' ', $row['status'])),
                'count' => intval($row['count'])
            ];
        }
        
        echo json_encode(['status' => 'success', 'data' => $status_counts]);
        break;
        
    case 'get_monthly_sales':
        // Get monthly sales data for charts
        $user_id = $_SESSION['user_id'];
        $is_admin = is_admin();
        $months = 6; // Last 6 months
        
        $sales_data = [];
        $labels = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $labels[] = date('M Y', strtotime("-$i months"));
            
            $start_date = $month . '-01';
            $end_date = date('Y-m-t', strtotime($start_date));
            
            $query = $is_admin ? 
                "SELECT SUM(amount) as total FROM sales WHERE sale_date BETWEEN ? AND ?" : 
                "SELECT SUM(amount) as total FROM sales WHERE user_id = ? AND sale_date BETWEEN ? AND ?";
            
            $stmt = $conn->prepare($query);
            
            if ($is_admin) {
                $stmt->bind_param("ss", $start_date, $end_date);
            } else {
                $stmt->bind_param("iss", $user_id, $start_date, $end_date);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $sales_data[] = $row['total'] ? floatval($row['total']) : 0;
        }
        
        echo json_encode([
            'status' => 'success', 
            'data' => [
                'labels' => $labels,
                'values' => $sales_data
            ]
        ]);
        break;
        
    default:
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        break;
}
?>