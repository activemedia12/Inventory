<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$product_id = $_GET['id'] ?? null;

if (!$product_id || !is_numeric($product_id)) {
    die("Invalid product ID.");
}

// Delete product
$stmt = $mysqli->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);

if ($stmt->execute()) {
    header("Location: products.php?msg=Product deleted successfully");
    exit;
} else {
    die("Failed to delete product: " . $stmt->error);
}
