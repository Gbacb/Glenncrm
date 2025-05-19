<?php
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

// Check for related records
$has_related_records = false;
$related_types = [];

// Check for interactions
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM interactions WHERE lead_id = ?");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->fetch_assoc()['count'] > 0) {
    $has_related_records = true;
    $related_types[] = 'interactions';
}

// Check for reminders
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM reminders WHERE lead_id = ?");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->fetch_assoc()['count'] > 0) {
    $has_related_records = true;
    $related_types[] = 'reminders';
}

// Check for sales
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE lead_id = ?");
$stmt->bind_param("i", $lead_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->fetch_assoc()['count'] > 0) {
    $has_related_records = true;
    $related_types[] = 'sales';
}

// If related records exist, return error
if ($has_related_records) {
    $related_types_str = implode(', ', $related_types);
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Cannot delete lead because it has related ' . $related_types_str . '. Please delete these records first.'
    ];
    echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
    exit;
}

// If no related records, try to delete the lead
try {
    $stmt = $conn->prepare("DELETE FROM leads WHERE lead_id = ?");
    $stmt->bind_param("i", $lead_id);
    $stmt->execute();
    
    // Log the deletion
    log_activity($_SESSION['user_id'], 'deleted', 'lead', $lead_id);
    
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => 'Lead has been deleted successfully.'
    ];
} catch (Exception $e) {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Failed to delete lead: ' . $e->getMessage()
    ];
}

echo '<script>window.location.href="'.BASE_URL.'/index.php?page=leads";</script>';
exit;