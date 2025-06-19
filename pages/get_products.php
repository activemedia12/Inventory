<?php
require_once '../config/db.php';

$paper_type = $_GET['type'] ?? '';
$paper_size = $_GET['size'] ?? '';

$response = [];

if ($paper_type && $paper_size) {
    $stmt = $mysqli->prepare("
        SELECT DISTINCT product_name
        FROM products
        WHERE product_type = ? AND product_group = ?
        AND (
            SELECT COALESCE(SUM(d.delivered_reams * 500), 0) - COALESCE(SUM(u.used_sheets), 0)
            FROM delivery_logs d
            LEFT JOIN usage_logs u ON d.product_id = u.product_id
            WHERE d.product_id = products.id
        ) > 0
        ORDER BY product_name
    ");
    $stmt->bind_param("ss", $paper_type, $paper_size);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $response[] = $row['product_name'];
    }

    $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($response);
