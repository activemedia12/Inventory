<?php
$mysqli = new mysqli("localhost", "u382513771_admin", "Amdp@1205", "u382513771_inventory");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $scope = $_POST['scope'] ?? '';
  $value = $_POST['value'] ?? '';

  if ($scope === 'product') {
    $id = intval($value);
    $mysqli->query("DELETE FROM stock_data WHERE id = $id");
    $mysqli->query("DELETE FROM stock_delivery_logs WHERE product_id = $id");
    $mysqli->query("DELETE FROM stock_usage_logs WHERE product_id = $id");
  } elseif ($scope === 'group') {
    $stmt = $mysqli->prepare("DELETE FROM stock_data WHERE section = ?");
    $stmt->bind_param("s", $value);
    $stmt->execute();
  } elseif ($scope === 'type') {
    $stmt = $mysqli->prepare("DELETE FROM stock_data WHERE type = ?");
    $stmt->bind_param("s", $value);
    $stmt->execute();
  }

  echo json_encode(["status" => "success"]);
}
?>
