<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: products.php?error=Access denied.");
    exit;
}

$product_id = $_GET['id'] ?? null;

if (!$product_id || !is_numeric($product_id)) {
    header("Location: products.php?error=Invalid product ID.");
    exit;
}

// 1. Check current stock level
$stock_stmt = $mysqli->prepare("
    SELECT 
        (IFNULL(SUM(d.delivered_reams), 0) * 500 -
         IFNULL(SUM(u.used_sheets), 0)) AS remaining_sheets
    FROM products p
    LEFT JOIN delivery_logs d ON p.id = d.product_id
    LEFT JOIN usage_logs u ON p.id = u.product_id
    WHERE p.id = ?
");
$stock_stmt->bind_param("i", $product_id);
$stock_stmt->execute();
$stock_result = $stock_stmt->get_result();
$stock = $stock_result->fetch_assoc();
$remaining = intval($stock['remaining_sheets'] ?? 0);
$stock_stmt->close();

if ($remaining > 0) {
    header("Location: products.php?error=Cannot delete product, there are orders and deliveries recorded with this product.");
    exit;
}

// 2. Check if product is referenced in logs
$check_logs = $mysqli->prepare("
    SELECT COUNT(*) AS total_refs
    FROM (
        SELECT product_id FROM delivery_logs WHERE product_id = ?
        UNION ALL
        SELECT product_id FROM usage_logs WHERE product_id = ?
    ) AS refs
");
$check_logs->bind_param("ii", $product_id, $product_id);
$check_logs->execute();
$logs_result = $check_logs->get_result();
$total_refs = intval($logs_result->fetch_assoc()['total_refs'] ?? 0);
$check_logs->close();

if ($total_refs > 0) {
    header("Location: products.php?error=Cannot delete product: It is used in delivery or usage logs.");
    exit;
}

// 3. Safe to delete
$stmt = $mysqli->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);

if ($stmt->execute()) {
    header("Location: products.php?msg=✅ Product deleted successfully");
    exit;
} else {
    header("Location: products.php?error=Failed to delete product. Please try again.");
    exit;
}
