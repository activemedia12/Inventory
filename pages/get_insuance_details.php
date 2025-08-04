<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

$item_id = intval($_GET['item_id'] ?? 0);

if (!$item_id) {
    echo json_encode(['error' => 'Missing item ID']);
    exit;
}

// Get item name
$item_stmt = $mysqli->prepare("SELECT item_name FROM insuances WHERE id = ?");
$item_stmt->bind_param("i", $item_id);
$item_stmt->execute();
$item_result = $item_stmt->get_result();
if ($item_result->num_rows === 0) {
    echo json_encode(['error' => 'Item not found']);
    exit;
}
$item = $item_result->fetch_assoc();
$item_name = $item['item_name'];

// Fetch usage history
$usage_stmt = $mysqli->prepare("
    SELECT u.date_issued, u.quantity_used, u.description, usr.username
    FROM insuance_usages u
    LEFT JOIN users usr ON u.issued_by = usr.id
    WHERE u.item_id = ?
    ORDER BY u.date_issued DESC
");
$usage_stmt->bind_param("i", $item_id);
$usage_stmt->execute();
$usage_result = $usage_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch delivery history
$delivery_stmt = $mysqli->prepare("
    SELECT delivery_date, supplier_name, delivered_quantity, unit, amount_per_unit
    FROM insuance_delivery_logs
    WHERE insuance_name = ?
    ORDER BY delivery_date DESC
");
$delivery_stmt->bind_param("s", $item_name);
$delivery_stmt->execute();
$delivery_result = $delivery_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stock summary
$summary_stmt = $mysqli->prepare("
    SELECT
        COALESCE(SUM(d.delivered_quantity), 0) AS delivered_quantity,
        (
            SELECT COALESCE(SUM(u.quantity_used), 0)
            FROM insuance_usages u
            WHERE u.item_id = ?
        ) AS used_quantity
    FROM insuance_delivery_logs d
    WHERE d.insuance_name = ?
");
$summary_stmt->bind_param("is", $item_id, $item_name);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary['current_stock'] = $summary['delivered_quantity'] - $summary['used_quantity'];

echo json_encode([
    'item_id' => $item_id,
    'item_name' => $item_name,
    'usage_history' => $usage_result,
    'delivery_history' => $delivery_result,
    'delivered_quantity' => $summary['delivered_quantity'],
    'used_quantity' => $summary['used_quantity'],
    'current_stock' => $summary['current_stock']
]);
