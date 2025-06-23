<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$product_id = intval($_GET['id'] ?? 0);
if (!$product_id) {
    echo "Invalid product ID.";
    exit;
}

// Fetch product info
$product = $mysqli->query("
    SELECT 
        p.*,
        IFNULL(del.total_delivered, 0) AS total_delivered_reams,
        IFNULL(used.total_used, 0) AS total_used_sheets,
        (IFNULL(del.total_delivered, 0) * 500 - IFNULL(used.total_used, 0)) AS current_stock
    FROM products p
    LEFT JOIN (
        SELECT product_id, SUM(delivered_reams) AS total_delivered
        FROM delivery_logs
        WHERE product_id = $product_id
        GROUP BY product_id
    ) del ON del.product_id = p.id
    LEFT JOIN (
        SELECT product_id, SUM(used_sheets) AS total_used
        FROM usage_logs
        WHERE product_id = $product_id
        GROUP BY product_id
    ) used ON used.product_id = p.id
    WHERE p.id = $product_id
")->fetch_assoc();

if (!$product) {
    echo "Product not found.";
    exit;
}

// Fetch usage history grouped by date and client
$usage_history = $mysqli->query("
    SELECT 
        u.log_date,
        (
            SELECT j.client_name
            FROM job_orders j
            WHERE j.log_date = u.log_date
              AND j.paper_type = p.product_type
              AND j.product_size = p.product_group
              AND j.paper_sequence LIKE CONCAT('%', p.product_name, '%')
            LIMIT 1
        ) AS client_name,
        ROUND(SUM(u.used_sheets) / 500, 2) AS used_reams
    FROM usage_logs u
    JOIN products p ON p.id = u.product_id
    WHERE u.product_id = $product_id
    GROUP BY u.log_date
    ORDER BY u.log_date DESC
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
    <h2>Product Information</h2>

    <p><strong>Type:</strong> <?= htmlspecialchars($product['product_type']) ?></p>
    <p><strong>Group (Size):</strong> <?= htmlspecialchars($product['product_group']) ?></p>
    <p><strong>Name:</strong> <?= htmlspecialchars($product['product_name']) ?></p>
    <p><strong>Unit Price:</strong> ₱<?= number_format($product['unit_price'], 2) ?></p>
    <p><strong>Total Delivered:</strong> <?= number_format($product['total_delivered_reams'], 2) ?> reams</p>
    <p><strong>Total Used:</strong> <?= number_format($product['total_used_sheets'] / 500, 2) ?> reams</p>
    <p><strong>Current Stock:</strong> <?= number_format($product['current_stock'] / 500, 2) ?> reams</p>

    <h3>Usage History</h3>
    <table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr><th>Date</th><th>Client Name</th><th>Used Reams</th></tr>
    </thead>
    <tbody>
        <?php while ($row = $usage_history->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['log_date']) ?></td>
            <td><?= htmlspecialchars($row['client_name'] ?? 'Unknown') ?></td>
            <td><?= number_format($row['used_reams'], 2) ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
    </table>

    <h3>Delivery History</h3>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Quantity (Reams)</th>
                <th>Supplier</th>
                <th>Price per Ream</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($delivery_history->num_rows > 0): ?>
                <?php while ($row = $delivery_history->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['delivery_date']) ?></td>
                        <td><?= number_format($row['delivered_reams'], 2) ?></td>
                        <td><?= htmlspecialchars($row['supplier_name'] ?: '-') ?></td>
                        <td>₱<?= number_format($row['amount_per_ream'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No delivery history found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p><a href="usage.php">&larr; Back to Usage Page</a></p>
</div>
</body>
</html>
