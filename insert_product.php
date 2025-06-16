<?php
$mysqli = new mysqli("localhost", "root", "", "inventory");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $section = $_POST['section'] ?? '';
    $product = $_POST['product'] ?? '';
    $unit_price = floatval($_POST['unit_price'] ?? 0);
    $initial_stock = floatval($_POST['initial_stock'] ?? 0);

    if (!$section || !$product) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO stock_data (type, section, product, unit_price) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $type, $section, $product, $unit_price);
    $stmt->execute();
    $product_id = $stmt->insert_id;
    $stmt->close();

    if ($initial_stock > 0) {
        $log_date = date('Y-m-d');
        $note = "Initial stock";
        $dl = $mysqli->prepare("INSERT INTO stock_delivery_logs (product_id, log_date, delivery_total, delivery_note) VALUES (?, ?, ?, ?)");
        $dl->bind_param("isds", $product_id, $log_date, $initial_stock, $note);
        $dl->execute();
        $dl->close();
    }

    // Check if it's an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    } else {
        header("Location: index.html");
        exit;
    }
}
?>
