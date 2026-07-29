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

$job_id      = intval($_POST['job_id'] ?? 0);
$grand_total = floatval($_POST['grand_total'] ?? 0);

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

if ($grand_total < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid expense amount']);
    exit;
}

$stmt = $inventory->prepare("UPDATE job_orders SET grand_total = ? WHERE id = ?");
$stmt->bind_param("di", $grand_total, $job_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Expenses saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}
$stmt->close();
