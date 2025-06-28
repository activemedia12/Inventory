<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$product_id = intval($_GET['id'] ?? 0);
if ($product_id <= 0) {
    echo "Invalid product ID.";
    exit;
}

// Fetch basic product info and stock
$query = "
    SELECT 
        p.product_type,
        p.product_group,
        p.product_name,
        p.unit_price,
        COALESCE(d.total_delivered, 0) AS total_delivered,
        COALESCE(u.total_used, 0) AS total_used,
        COALESCE(d.total_delivered, 0) - COALESCE(u.total_used, 0) AS stock_balance
    FROM products p
    LEFT JOIN (
        SELECT product_id, SUM(delivered_reams * 500) AS total_delivered
        FROM delivery_logs
        GROUP BY product_id
    ) d ON p.id = d.product_id
    LEFT JOIN (
        SELECT product_id, SUM(used_sheets) AS total_used
        FROM usage_logs
        GROUP BY product_id
    ) u ON p.id = u.product_id
    WHERE p.id = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "Product not found.";
    exit;
}

// Fetch usage history
$usage_history = $mysqli->query("
    SELECT 
        ul.log_date, 
        jo.client_name, 
        jo.project_name,
        ul.used_sheets
    FROM usage_logs ul
    LEFT JOIN job_orders jo ON ul.job_order_id = jo.id
    WHERE ul.product_id = $product_id
    ORDER BY ul.log_date DESC
");

// Fetch delivery history
$delivery_history = $mysqli->query("
    SELECT delivery_date, delivered_reams, supplier_name, amount_per_ream
    FROM delivery_logs
    WHERE product_id = $product_id
    ORDER BY delivery_date DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Product Info</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h2>Product Info</h2>
        <p><strong>Type:</strong> <?= htmlspecialchars($product['product_type']) ?></p>
        <p><strong>Group:</strong> <?= htmlspecialchars($product['product_group']) ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($product['product_name']) ?></p>
        <p><strong>Unit Price:</strong> ₱<?= number_format($product['unit_price'], 2) ?></p>

        <h3>Stock Summary</h3>
        <p><strong>Total Delivered:</strong> <?= number_format($product['total_delivered']) ?> sheets (<?= number_format($product['total_delivered'] / 500, 2) ?> reams)</p>
        <p><strong>Total Used:</strong> <?= number_format($product['total_used']) ?> sheets (<?= number_format($product['total_used'] / 500, 2) ?> reams)</p>
        <p><strong>Current Stock:</strong> <?= number_format($product['stock_balance']) ?> sheets (<?= number_format($product['stock_balance'] / 500, 2) ?> reams)</p>

        <h3>Usage History</h3>
        <?php if ($usage_history->num_rows > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Client Name</th>
                    <th>Project Name</th>
                    <th>Used Reams</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $usage_history->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['log_date']) ?></td>
                    <td><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['project_name'] ?? 'N/A') ?></td>
                    <td><?= number_format($row['used_sheets'] / 500, 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No usage history found.</p>
        <?php endif; ?>

        <h3>Delivery History</h3>
        <?php if ($delivery_history->num_rows > 0): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Quantity (Reams)</th>
                    <th>Supplier</th>
                    <th>Amount per Ream</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $delivery_history->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['delivery_date']) ?></td>
                    <td><?= number_format($row['delivered_reams'], 2) ?></td>
                    <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                    <td>₱<?= number_format($row['amount_per_ream'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No delivery history found.</p>
        <?php endif; ?>

        <p><a href="products.php">← Back to Stock Summary</a></p>
    </div>
</body>
</html>
