<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

$job_id = intval($_GET['id'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

$sql = "SELECT grand_total, total_cost, layout_fee, discount_type, discount_value FROM job_orders WHERE id = ?";
$stmt = $inventory->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($job) {
    echo json_encode([
        'success'           => true,
        'expenses'          => $job['grand_total'] ?? 0,
        'total_cost'        => $job['total_cost'] ?? 0,
        'layout_fee'        => $job['layout_fee'] ?? 0,
        'discount_type'     => $job['discount_type'] ?? 'amount',
        'discount_value'    => $job['discount_value'] ?? 0,
        'expenses_computed' => (!empty($job['grand_total']) && $job['grand_total'] > 0),
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Job not found']);
}
?>