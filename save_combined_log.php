<?php
$mysqli = new mysqli("localhost", "u382513771_admin", "Amdp@1205", "u382513771_inventory");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $product_id = intval($_POST['product_id']);
  $log_date = $_POST['log_date'];

  $begin = floatval($_POST['beginning_inventory'] ?? 0);
  $d1 = floatval($_POST['delivery_1'] ?? 0);
  $d2 = floatval($_POST['delivery_2'] ?? 0);
  $d3 = floatval($_POST['delivery_3'] ?? 0);
  $d4 = floatval($_POST['delivery_4'] ?? 0);
  $d5 = floatval($_POST['delivery_5'] ?? 0);
  $delivery_total = $begin + $d1 + $d2 + $d3 + $d4 + $d5;
  $delivery_note = $_POST['delivery_note'] ?? '';

  $used_for = $_POST['used_for'] ?? '';
  $u1 = floatval($_POST['used_1'] ?? 0);
  $u2 = floatval($_POST['used_2'] ?? 0);
  $u3 = floatval($_POST['used_3'] ?? 0);
  $u4 = floatval($_POST['used_4'] ?? 0);
  $u5 = floatval($_POST['used_5'] ?? 0);
  $u6 = floatval($_POST['used_6'] ?? 0);
  $used_total = $u1 + $u2 + $u3 + $u4 + $u5 + $u6;
  $usage_note = $_POST['usage_note'] ?? '';

  $check_del = $mysqli->query("SELECT id FROM stock_delivery_logs WHERE product_id = $product_id AND log_date = '$log_date'");
  if ($check_del->num_rows > 0) {
    $stmt = $mysqli->prepare("UPDATE stock_delivery_logs 
      SET beginning_inventory=?, delivery_1=?, delivery_2=?, delivery_3=?, delivery_4=?, delivery_5=?, delivery_total=?, delivery_note=?
      WHERE product_id=? AND log_date=?");
    $stmt->bind_param("dddddddsis", $begin, $d1, $d2, $d3, $d4, $d5, $delivery_total, $delivery_note, $product_id, $log_date);
  } else {
    $stmt = $mysqli->prepare("INSERT INTO stock_delivery_logs 
      (product_id, log_date, beginning_inventory, delivery_1, delivery_2, delivery_3, delivery_4, delivery_5, delivery_total, delivery_note)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdddddds", $product_id, $log_date, $begin, $d1, $d2, $d3, $d4, $d5, $delivery_total, $delivery_note);
  }
  $stmt->execute();
  $stmt->close();

  $check_usage = $mysqli->query("SELECT id FROM stock_usage_logs WHERE product_id = $product_id AND log_date = '$log_date'");
  if ($check_usage->num_rows > 0) {
    $stmt2 = $mysqli->prepare("UPDATE stock_usage_logs 
      SET used_for=?, used_1=?, used_2=?, used_3=?, used_4=?, used_5=?, used_6=?, used_total=?, usage_note=?
      WHERE product_id=? AND log_date=?");
    $stmt2->bind_param("sdddddddsis", $used_for, $u1, $u2, $u3, $u4, $u5, $u6, $used_total, $usage_note, $product_id, $log_date);
  } else {
    $stmt2 = $mysqli->prepare("INSERT INTO stock_usage_logs 
      (product_id, log_date, used_for, used_1, used_2, used_3, used_4, used_5, used_6, used_total, usage_note)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("issddddddds", $product_id, $log_date, $used_for, $u1, $u2, $u3, $u4, $u5, $u6, $used_total, $usage_note);
  }
  $stmt2->execute();
  $stmt2->close();

  header("Location: usage_log.php?product_id=$product_id");
  exit;
}
?>
