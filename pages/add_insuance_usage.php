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
$used_by_name = trim($_POST['used_by'] ?? '');
$date_issued_input = trim($_POST['date_issued'] ?? '');
$issued_by = $_SESSION['user_id'];

// Use provided date if valid, otherwise fallback to NOW()
$date_issued = $date_issued_input !== '' ? $date_issued_input : date('Y-m-d');

if ($item_id > 0 && $quantity_used > 0) {
  $stmt = $inventory->prepare("INSERT INTO insuance_usages (item_id, quantity_used, description, issued_by, used_by_name, date_issued) VALUES (?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("idssss", $item_id, $quantity_used, $description, $issued_by, $used_by_name, $date_issued);

  if ($stmt->execute()) {
    $_SESSION['success_message'] = "Usage recorded successfully.";
  } else {
    $_SESSION['error_message'] = "Database error: " . $stmt->error;
  }
} else {
  $_SESSION['error_message'] = "Invalid input. Please fill in required fields.";
}

header("Location: insuances.php");
exit;
