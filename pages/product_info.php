<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

const HISTORY_PAGE_SIZE = 5;

$product_id = intval($_GET['id'] ?? 0);
if ($product_id <= 0) {
    echo "<div class='alert alert-danger'>Invalid product ID.</div>";
    exit;
}

$mode = $_GET['mode'] ?? 'full'; // full | usage | delivery
if (!in_array($mode, ['full', 'usage', 'delivery'], true)) {
    $mode = 'full';
}

/**
 * Fetch one page of usage history rows (LIMIT/OFFSET at the SQL level).
 * Requests one extra row over the page size so we can tell if there's
 * a next page without a separate COUNT(*) query.
 */
function fetch_usage_page(mysqli $inventory, int $product_id, int $page): array
{
    $page = max(1, $page);
    $offset = ($page - 1) * HISTORY_PAGE_SIZE;
    $limit = HISTORY_PAGE_SIZE + 1;

    $query = "
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
        LIMIT ? OFFSET ?
    ";
    $stmt = $inventory->prepare($query);
    if (!$stmt) {
        die("Error in usage history query: " . $inventory->error);
    }
    $stmt->bind_param("iii", $product_id, $limit, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $has_more = count($rows) > HISTORY_PAGE_SIZE;
    if ($has_more) {
        array_pop($rows); // drop the lookahead row
    }

    return [$rows, $has_more];
}

/**
 * Fetch one page of delivery history rows (LIMIT/OFFSET at the SQL level).
 */
function fetch_delivery_page(mysqli $inventory, int $product_id, int $page): array
{
    $page = max(1, $page);
    $offset = ($page - 1) * HISTORY_PAGE_SIZE;
    $limit = HISTORY_PAGE_SIZE + 1;

    $query = "
        SELECT delivery_date, delivered_reams, supplier_name, amount_per_ream
        FROM delivery_logs
        WHERE product_id = ?
        ORDER BY delivery_date DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $inventory->prepare($query);
    if (!$stmt) {
        die("Error in delivery history query: " . $inventory->error);
    }
    $stmt->bind_param("iii", $product_id, $limit, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $has_more = count($rows) > HISTORY_PAGE_SIZE;
    if ($has_more) {
        array_pop($rows);
    }

    return [$rows, $has_more];
}

function render_usage_row(array $row): string
{
    ob_start();
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
    <?php
    return ob_get_clean();
}

function render_delivery_row(array $row): string
{
    ob_start();
    ?>
    <tr>
        <td><?= date("M j, Y", strtotime($row['delivery_date'])) ?></td>
        <td><?= htmlspecialchars($row['supplier_name']) ?></td>
        <td><?= number_format($row['delivered_reams'], 2) ?></td>
        <td>₱<?= number_format($row['amount_per_ream'], 2) ?></td>
        <td><?= number_format($row['delivered_reams'] * 500) ?></td>
    </tr>
    <?php
    return ob_get_clean();
}

// === AJAX "Show more" endpoints: return just the next page as JSON ===
if ($mode === 'usage') {
    $usage_page = max(1, intval($_GET['usage_page'] ?? 1));
    [$rows, $has_more] = fetch_usage_page($inventory, $product_id, $usage_page);

    $rows_html = implode('', array_map('render_usage_row', $rows));

    header('Content-Type: application/json');
    echo json_encode(['rows_html' => $rows_html, 'has_more' => $has_more]);
    $inventory->close();
    exit;
}

if ($mode === 'delivery') {
    $delivery_page = max(1, intval($_GET['delivery_page'] ?? 1));
    [$rows, $has_more] = fetch_delivery_page($inventory, $product_id, $delivery_page);

    $rows_html = implode('', array_map('render_delivery_row', $rows));

    header('Content-Type: application/json');
    echo json_encode(['rows_html' => $rows_html, 'has_more' => $has_more]);
    $inventory->close();
    exit;
}

// === Full modal render (first page of each history table) ===

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

[$usage_rows, $usage_has_more] = fetch_usage_page($inventory, $product_id, 1);
[$delivery_rows, $delivery_has_more] = fetch_delivery_page($inventory, $product_id, 1);
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
        <?php if (!empty($usage_rows)): ?>
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
                <tbody id="usage-table-body">
                    <?php foreach ($usage_rows as $row): ?>
                        <?= render_usage_row($row) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($usage_has_more): ?>
                <button type="button" class="show-more-btn" id="usage-show-more-btn"
                    onclick="loadMoreProductUsage(<?= $product_id ?>)">
                    <i class="fas fa-chevron-down"></i> Show more
                </button>
            <?php endif; ?>
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
        <?php if (!empty($delivery_rows)): ?>
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
                <tbody id="delivery-table-body">
                    <?php foreach ($delivery_rows as $row): ?>
                        <?= render_delivery_row($row) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($delivery_has_more): ?>
                <button type="button" class="show-more-btn" id="delivery-show-more-btn"
                    onclick="loadMoreProductDelivery(<?= $product_id ?>)">
                    <i class="fas fa-chevron-down"></i> Show more
                </button>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <p><i class="fas fa-info-circle"></i> No delivery history found for this product</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$inventory->close();