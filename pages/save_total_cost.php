<?php
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $job_id = intval($_POST['job_id'] ?? 0);
    $total_cost = floatval($_POST['total_cost'] ?? 0);
    
    if ($job_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
        exit;
    }
    
    if ($total_cost <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid total cost']);
        exit;
    }
    
    // Update total cost in database
    $stmt = $inventory->prepare("UPDATE job_orders SET total_cost = ? WHERE id = ?");
    $stmt->bind_param("di", $total_cost, $job_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Total cost saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>