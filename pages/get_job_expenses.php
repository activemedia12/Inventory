<?php
require_once '../config/db.php';

$job_id = $_GET['id'] ?? 0;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

// Fetch job expenses
$sql = "SELECT grand_total, total_cost FROM job_orders WHERE id = ?";
$stmt = $inventory->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if ($job) {
    // Check if expenses are computed
    $expenses_computed = !empty($job['grand_total']) && $job['grand_total'] > 0;
    
    echo json_encode([
        'success' => true,
        'expenses' => $job['grand_total'] ?? 0,
        'total_cost' => $job['total_cost'] ?? 0,
        'expenses_computed' => $expenses_computed
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Job not found']);
}
?>