<?php
/**
 * General utility functions for the CRM
 */

// Display alert messages
function show_alert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show" role="alert">';
        echo $alert['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['alert']);
    }
}

// Format date for display
function format_date($date, $format = DATE_FORMAT_SHORT) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '';
    }
    
    try {
        $date_obj = new DateTime($date);
        return $date_obj->format($format);
    } catch (Exception $e) {
        return '';
    }
}

// Generate pagination links
function pagination($total_items, $items_per_page = 10, $current_page = 1) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $output = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $output .= '<li class="page-item"><a class="page-link" href="?page=' . $_GET['page'] . '&p=' . ($current_page - 1) . '">&laquo;</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo;</a></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            $output .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $output .= '<li class="page-item"><a class="page-link" href="?page=' . $_GET['page'] . '&p=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $output .= '<li class="page-item"><a class="page-link" href="?page=' . $_GET['page'] . '&p=' . ($current_page + 1) . '">&raquo;</a></li>';
    } else {
        $output .= '<li class="page-item disabled"><a class="page-link" href="#">&raquo;</a></li>';
    }
    
    $output .= '</ul></nav>';
    
    return $output;
}

// Get user's full name
function get_user_name($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        return $user['first_name'] . ' ' . $user['last_name'];
    }
    
    return 'Unknown User';
}

// Get customer name
function get_customer_name($customer_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT name FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        return $customer['name'];
    }
    
    return 'Unknown Customer';
}

// Format currency value
function format_currency($amount) {
    return '$' . number_format($amount, 2);
}

// Generate a select dropdown with options
function generate_dropdown($name, $options, $selected = '', $class = 'form-select', $id = '') {
    $id = empty($id) ? $name : $id;
    $output = '<select name="' . $name . '" id="' . $id . '" class="' . $class . '">';
    
    foreach ($options as $value => $label) {
        $selected_attr = ($value == $selected) ? ' selected' : '';
        $output .= '<option value="' . $value . '"' . $selected_attr . '>' . $label . '</option>';
    }
    
    $output .= '</select>';
    return $output;
}

// Generate user dropdown
function get_users_dropdown($name = 'user_id', $selected = '', $include_empty = true) {
    global $conn;
    
    $options = [];
    if ($include_empty) {
        $options[''] = '-- Select User --';
    }
    
    $query = "SELECT user_id, first_name, last_name FROM users ORDER BY first_name, last_name";
    $result = $conn->query($query);
    
    while ($row = $result->fetch_assoc()) {
        $options[$row['user_id']] = $row['first_name'] . ' ' . $row['last_name'];
    }
    
    return generate_dropdown($name, $options, $selected);
}

// Generate a token for CSRF protection
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Log activity
function log_activity($user_id, $action, $entity_type, $entity_id = null) {
    global $conn;
    
    // Check if user_id is valid
    if (!$user_id || !is_numeric($user_id)) {
        return false;
    }
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issis", $user_id, $action, $entity_type, $entity_id, $ip_address);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        // Silently fail if there's an issue with logging
        return false;
    }
}

// Safe lead deletion - checks for constraints and returns result
function delete_lead($lead_id) {
    global $conn;
    
    // First check for any related records that would prevent deletion
    try {
        // Check interactions
        $sql = "SELECT COUNT(*) as count FROM interactions WHERE lead_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $has_interactions = $stmt->get_result()->fetch_assoc()['count'] > 0;
        
        // Check reminders
        $sql = "SELECT COUNT(*) as count FROM reminders WHERE lead_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $has_reminders = $stmt->get_result()->fetch_assoc()['count'] > 0;
        
        // Check sales
        $sql = "SELECT COUNT(*) as count FROM sales WHERE lead_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $has_sales = $stmt->get_result()->fetch_assoc()['count'] > 0;
        
        if ($has_interactions || $has_reminders || $has_sales) {
            $error_message = 'Cannot delete lead because it has related ';
            $related_items = [];
            
            if ($has_interactions) {
                $related_items[] = 'interactions';
            }
            if ($has_reminders) {
                $related_items[] = 'reminders';
            }
            if ($has_sales) {
                $related_items[] = 'sales';
            }
            
            $error_message .= implode(', ', $related_items);
            
            return [
                'success' => false,
                'message' => $error_message
            ];
        }
        
        // If no related records, proceed with deletion
        $sql = "DELETE FROM leads WHERE lead_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Lead deleted successfully.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error deleting lead: ' . $e->getMessage()
        ];
    }
}
?>