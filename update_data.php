<?php
$conn = new mysqli("localhost", "root", "", "inventory");

$data = json_decode(file_get_contents("php://input"), true);

foreach ($data as $section => $items) {
    foreach ($items as $item) {
        $stmt = $conn->prepare("UPDATE stock_data SET stock_balance=?, stock_level=?, amount=? WHERE id=?");
        $stmt->bind_param("dddi", $item['stock_balance'], $item['stock_level'], $item['amount'], $item['id']);
        $stmt->execute();
    }
}
$conn->close();
?>