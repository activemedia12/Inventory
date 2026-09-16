<?php
// Returns the current unit price for a single product, e.g.:
//   GET get_product_price.php?id=42  ->  {"id":42,"unit_price":250.00}
// Used by delivery.js to prefill the "Amount per Unit" field for each
// paper item as soon as it's selected in the delivery form.

session_start();
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

require_once '../config/db.php';

header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);
if (!$id) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing or invalid id']);
  exit;
}

$stmt = $inventory->prepare("SELECT unit_price FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
  http_response_code(404);
  echo json_encode(['error' => 'Product not found']);
  exit;
}

echo json_encode([
  'id' => $id,
  'unit_price' => $row['unit_price'] !== null ? floatval($row['unit_price']) : null,
]);