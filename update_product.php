<?php
$mysqli = new mysqli("localhost", "u382513771_admin", "Amdp@1205", "u382513771_inventory");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $product = trim($_POST['product']);
    $section = trim($_POST['section']);
    $type = trim($_POST['type']);
    $unit_price = floatval($_POST['unit_price']);

    $stmt = $mysqli->prepare("UPDATE stock_data SET product = ?, section = ?, type = ?, unit_price = ? WHERE id = ?");
    $stmt->bind_param("sssdi", $product, $section, $type, $unit_price, $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
