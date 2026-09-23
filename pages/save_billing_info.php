<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$job_id          = intval($_POST['job_id'] ?? 0);
$billing_number  = trim($_POST['billing_number'] ?? '');
$invoice_number  = trim($_POST['invoice_number'] ?? '');

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

// Ensure job_orders has the billing/invoice number columns (same pattern
// used elsewhere in this app — created on demand rather than via a
// separate migration file).
$colCheck = $inventory->query("
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_orders'
      AND COLUMN_NAME IN ('billing_number', 'invoice_number')
");
$existing_cols = [];
while ($c = $colCheck->fetch_assoc()) {
    $existing_cols[] = $c['COLUMN_NAME'];
}
if (!in_array('billing_number', $existing_cols)) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN billing_number VARCHAR(100) DEFAULT NULL");
}
if (!in_array('invoice_number', $existing_cols)) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN invoice_number VARCHAR(100) DEFAULT NULL");
}

$stmt = $inventory->prepare("UPDATE job_orders SET billing_number = ?, invoice_number = ? WHERE id = ?");
$stmt->bind_param("ssi", $billing_number, $invoice_number, $job_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Billing/invoice numbers saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}
$stmt->close();