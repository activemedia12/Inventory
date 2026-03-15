<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$job_id         = intval($_POST['job_id']        ?? 0);
$total_cost     = floatval($_POST['total_cost']   ?? -1);
$layout_fee     = isset($_POST['layout_fee'])     ? floatval($_POST['layout_fee'])     : null;
$discount_type  = isset($_POST['discount_type'])  && in_array($_POST['discount_type'], ['amount','percent'])
                    ? $_POST['discount_type'] : null;
$discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : null;

if ($job_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID']);
    exit;
}

if ($total_cost < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid total cost']);
    exit;
}

// If layout fee / discount were included, save them too
if ($layout_fee !== null) {
    $stmt = $inventory->prepare(
        "UPDATE job_orders
         SET total_cost = ?, layout_fee = ?, discount_type = ?, discount_value = ?
         WHERE id = ?"
    );
    $stmt->bind_param("ddsdi", $total_cost, $layout_fee, $discount_type, $discount_value, $job_id);
} else {
    $stmt = $inventory->prepare("UPDATE job_orders SET total_cost = ? WHERE id = ?");
    $stmt->bind_param("di", $total_cost, $job_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Total cost saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}
$stmt->close();
?>