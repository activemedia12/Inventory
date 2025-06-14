<?php
$mysqli = new mysqli("localhost", "root", "", "inventory");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $section = $_POST['section'] ?? ''; // this acts as group header
    $product = $_POST['product'] ?? '';
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $initial_stock = floatval($_POST['initial_stock'] ?? 0);

    if (!$section || !$product) {
        die("Missing required fields.");
    }

    // Insert product
    $stmt = $mysqli->prepare("INSERT INTO stock_data (type, section, product, unit_price) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $type, $section, $product, $unit_price);
    $stmt->execute();
    $product_id = $stmt->insert_id;
    $stmt->close();

    // If initial stock provided, log it
    if ($initial_stock > 0) {
        $log_date = date('Y-m-d');
        $note = "Initial stock";

        $dl = $mysqli->prepare("INSERT INTO stock_delivery_logs (product_id, log_date, delivery_total, delivery_note) VALUES (?, ?, ?, ?)");
        $dl->bind_param("isds", $product_id, $log_date, $initial_stock, $note);
        $dl->execute();
        $dl->close();
    }

    header("Location: index.html");
    exit;
}
?>
