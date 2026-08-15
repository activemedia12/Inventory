<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$product_id = intval($_GET['id'] ?? 0);
if ($product_id <= 0) {
    echo "<div class='alert alert-danger'>Invalid product ID.</div>";
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
        SELECT product_id, SUM(used_sheets + COALESCE(spoilage_sheets, 0)) AS total_used
        FROM usage_logs
        GROUP BY product_id
    ) u ON p.id = u.product_id
    WHERE p.id = ?
    LIMIT 1
";

$stmt = $inventory->prepare($query);
if (!$stmt) {
    die("Error in product query: " . $inventory->error);
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "<div class='alert alert-danger'>Product not found.</div>";
    exit;
}

// Fetch usage history with prepared statement
$usage_query = "
    SELECT 
        ul.log_date, 
        jo.client_name, 
        jo.project_name,
        ul.used_sheets,
        jo.product_type_id,
        pt.name AS print_type_name,
        pt.icon AS print_type_icon
    FROM usage_logs ul
    LEFT JOIN job_orders jo ON ul.job_order_id = jo.id
    LEFT JOIN product_types pt ON jo.product_type_id = pt.id
    WHERE ul.product_id = ?
    ORDER BY ul.log_date DESC
";
$usage_stmt = $inventory->prepare($usage_query);
if (!$usage_stmt) {
    die("Error in usage history query: " . $inventory->error);
}
$usage_stmt->bind_param("i", $product_id);
$usage_stmt->execute();
$usage_history = $usage_stmt->get_result();

// Fetch delivery history with prepared statement
$delivery_query = "
    SELECT delivery_date, delivered_reams, supplier_name, amount_per_ream
    FROM delivery_logs
    WHERE product_id = ?
    ORDER BY delivery_date DESC
";
$delivery_stmt = $inventory->prepare($delivery_query);
if (!$delivery_stmt) {
    die("Error in delivery history query: " . $inventory->error);
}
$delivery_stmt->bind_param("i", $product_id);
$delivery_stmt->execute();
$delivery_history = $delivery_stmt->get_result();
?>

<div class="window-header">
    <div class="window-title">
        <i class="fas fa-box"></i>
        Paper Information
    </div>
    <button class="close-btn" onclick="closeModal()">
        <i class="fas fa-times"></i>
    </button>
</div>

<div class="window-content">
    <!-- Basic Product Info -->
    <div class="product-info-compact">
        <div class="info-item-compact">
            <strong>Paper Type</strong>
            <span><?= htmlspecialchars($product['product_type']) ?></span>
        </div>

        <div class="info-item-compact">
            <strong>Paper Group</strong>
            <span><?= htmlspecialchars($product['product_group']) ?></span>
        </div>

        <div class="info-item-compact">
            <strong>Paper Name</strong>
            <span><?= htmlspecialchars($product['product_name']) ?></span>
        </div>

        <div class="info-item-compact">
            <strong>Unit Price</strong>
            <span>₱<?= number_format($product['unit_price'], 2) ?></span>
        </div>
    </div>

    <!-- Stock Summary -->
    <p class="stock-unit-compact">Displayed per ream*</p>
    <div class="stock-summary-compact">
        <div class="stock-card-compact">
            <h4>Total Delivered</h4>
            <div class="stock-value-compact"><?= number_format($product['total_delivered'] / 500, 2) ?></div>
            <div class="stock-unit-compact">(<?= number_format($product['total_delivered']) ?> sheets)</div>
        </div>

        <div class="stock-card-compact">
            <h4>Total Used</h4>
            <div class="stock-value-compact"><?= number_format($product['total_used'] / 500, 2) ?></div>
            <div class="stock-unit-compact">(<?= number_format($product['total_used']) ?> sheets)</div>
        </div>

        <div class="stock-card-compact">
            <h4>Current Stock</h4>
            <div class="stock-value-compact"><?= number_format($product['stock_balance'] / 500, 2) ?></div>
            <div class="stock-unit-compact">(<?= number_format($product['stock_balance']) ?> sheets)</div>
        </div>
    </div>

    <!-- Usage History Section -->
    <div class="section-header">
        <i class="fas fa-history"></i>
        Usage History
    </div>
    <div class="container">
        <?php if ($usage_history->num_rows > 0): ?>
            <table class="compact-table" id="usage-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Project</th>
                        <th>Print Type</th>
                        <th>Sheets</th>
                        <th>Reams</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $usage_rows = $usage_history->fetch_all(MYSQLI_ASSOC);
                    $usage_count = count($usage_rows);
                    $display_usage = min(10, $usage_count);

                    for ($i = 0; $i < $display_usage; $i++):
                        $row = $usage_rows[$i];
                    ?>
                        <tr>
                            <td><?= date("M j, Y", strtotime($row['log_date'])) ?></td>
                            <td><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['project_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if (!empty($row['product_type_id']) && $row['print_type_name']): ?>
                                    <span class="badge badge-success" style="white-space:nowrap;">
                                        <i class="fas <?= htmlspecialchars($row['print_type_icon'] ?? 'fa-print') ?>"></i>
                                        <?= htmlspecialchars($row['print_type_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">
                                        <i class="fas fa-file-alt"></i> Paper
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($row['used_sheets']) ?></td>
                            <td><?= number_format($row['used_sheets'] / 500, 2) ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p><i class="fas fa-info-circle"></i> No usage history found for this product</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delivery History Section -->
    <div class="section-header">
        <i class="fas fa-truck"></i>
        Delivery History
    </div>
    <div class="container">
        <?php if ($delivery_history->num_rows > 0): ?>
            <table class="compact-table" id="delivery-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Reams</th>
                        <th>Price/Ream</th>
                        <th>Sheets</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $delivery_rows = $delivery_history->fetch_all(MYSQLI_ASSOC);
                    $delivery_count = count($delivery_rows);
                    $display_delivery = min(10, $delivery_count);

                    for ($i = 0; $i < $display_delivery; $i++):
                        $row = $delivery_rows[$i];
                    ?>
                        <tr>
                            <td><?= date("M j, Y", strtotime($row['delivery_date'])) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                            <td><?= number_format($row['delivered_reams'], 2) ?></td>
                            <td>₱<?= number_format($row['amount_per_ream'], 2) ?></td>
                            <td><?= number_format($row['delivered_reams'] * 500) ?></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p><i class="fas fa-info-circle"></i> No delivery history found for this product</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
if (isset($usage_stmt)) $usage_stmt->close();
if (isset($delivery_stmt)) $delivery_stmt->close();
$inventory->close();
?>