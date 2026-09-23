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

// These three columns are created on demand elsewhere (Set Total Cost,
// Billing/Invoice shortcut, job creation form) rather than via a migration,
// so guard against reading them before any of those have run yet.
$colCheck = $inventory->query("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_orders'
      AND COLUMN_NAME IN ('price_per_booklet', 'billing_number', 'invoice_number')
");
$existing_cols = [];
while ($c = $colCheck->fetch_assoc()) {
    $existing_cols[] = $c['COLUMN_NAME'];
}
if (!in_array('price_per_booklet', $existing_cols)) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN price_per_booklet DECIMAL(10,2) DEFAULT 0.00");
}
if (!in_array('billing_number', $existing_cols)) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN billing_number VARCHAR(100) DEFAULT NULL");
}
if (!in_array('invoice_number', $existing_cols)) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN invoice_number VARCHAR(100) DEFAULT NULL");
}

$sql = "SELECT grand_total, total_cost, layout_fee, discount_type, discount_value, price_per_booklet, billing_number, invoice_number FROM job_orders WHERE id = ?";
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
        'price_per_booklet' => $job['price_per_booklet'] ?? 0,
        'billing_number'    => $job['billing_number'] ?? '',
        'invoice_number'    => $job['invoice_number'] ?? '',
        'expenses_computed' => (!empty($job['grand_total']) && $job['grand_total'] > 0),
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Job not found']);
}
?>