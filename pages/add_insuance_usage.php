<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

$item_id = intval($_POST['item_id'] ?? 0);
$quantity_used = floatval($_POST['quantity_used'] ?? 0);
$description = trim($_POST['description'] ?? '');
$issued_by = $_SESSION['user_id'];

if ($item_id > 0 && $quantity_used > 0) {
  $stmt = $mysqli->prepare("INSERT INTO insuance_usages (item_id, quantity_used, description, issued_by, date_issued) VALUES (?, ?, ?, ?, NOW())");
  $stmt->bind_param("idsi", $item_id, $quantity_used, $description, $issued_by);
  if ($stmt->execute()) {
    $_SESSION['success_message'] = "Usage recorded.";
  } else {
    $_SESSION['error_message'] = "Error: " . $stmt->error;
  }
} else {
  $_SESSION['error_message'] = "Invalid input.";
}

header("Location: insuances.php");
exit;
