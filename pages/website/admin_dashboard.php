<?php
session_start();
require_once '../../config/db.php';
require_once '../permissions.php';
require_once 'admin_helpers.php';

// Check that the user is logged in and is staff (admin, employee or super admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee', 'super_admin'])) {
    header("Location: ../../accounts/login.php");
    exit;
}
// Revenue figures are shown only to users the super admin has switched on
$can_web_finance = can('web_finance');

// Get dashboard statistics
$stats = [];

// Get current month dates. Include the full last day (BETWEEN against a bare
// date compares against midnight, which silently excludes every order placed
// after 00:00:00 on the last day of the month).
$current_month_start = date('Y-m-01') . ' 00:00:00';
$current_month_end   = date('Y-m-t') . ' 23:59:59';
$month_range = "created_at BETWEEN '$current_month_start' AND '$current_month_end'";

// Orders in these statuses are treated as "counts toward revenue" everywhere
// in the admin panel (dashboard, reports, export) so the numbers agree.
// This is a fixed list, so it is written straight into the SQL.
$REVENUE_STATUSES = ['paid', 'processing', 'ready_for_pickup', 'completed'];
$revenue_status_list = "'" . implode("','", $REVENUE_STATUSES) . "'";

// Every query below goes through wa_fetch_one / wa_fetch_all: if one fails the
// card shows 0 / an empty state and the reason is logged, the page still loads.

// Orders, pending and completed counts - current month
$counts = wa_fetch_one($inventory, "SELECT
        SUM(CASE WHEN status IN ($revenue_status_list) THEN 1 ELSE 0 END) AS total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders
    FROM orders
    WHERE $month_range");
$stats['total_orders'] = (int) ($counts['total_orders'] ?? 0);
$stats['pending_orders'] = (int) ($counts['pending_orders'] ?? 0);
$stats['completed_orders'] = (int) ($counts['completed_orders'] ?? 0);

// Total Revenue - Current Month
$stats['total_revenue'] = 0;
if ($can_web_finance) {
    $row = wa_fetch_one($inventory, "SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM orders
        WHERE $month_range AND status IN ($revenue_status_list)");
    $stats['total_revenue'] = (float) ($row['total_revenue'] ?? 0);
}

// Total Customers
$row = wa_fetch_one($inventory, "SELECT COUNT(*) AS total_customers FROM users WHERE role = 'customer'");
$stats['total_customers'] = (int) ($row['total_customers'] ?? 0);

// Top Products - Current Month. Grouped by product id so a renamed product
// stays one row (the current name is used when the product still exists).
$top_products = wa_fetch_all($inventory, "SELECT
        COALESCE(MAX(p.product_name), MAX(oi.product_name)) AS product_name,
        SUM(oi.quantity) AS total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    LEFT JOIN products_offered p ON oi.product_id = p.id
    WHERE o.created_at BETWEEN '$current_month_start' AND '$current_month_end'
      AND o.status IN ($revenue_status_list)
    GROUP BY COALESCE(CAST(oi.product_id AS CHAR), oi.product_name)
    ORDER BY total_sold DESC
    LIMIT 5");

// Cumulative revenue by month. The running total is added up here in PHP
// instead of SQL window functions, so it works on any MySQL / MariaDB version.
$current_year_start = date('Y-01-01') . ' 00:00:00';
$cumulative_revenue = [];
if ($can_web_finance) {
    $monthly = wa_fetch_all($inventory, "SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month,
            SUM(total_amount) AS monthly_revenue
        FROM orders
        WHERE created_at >= '$current_year_start'
          AND status IN ($revenue_status_list)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month");
    $running = 0.0;
    foreach ($monthly as $m) {
        $running += (float) $m['monthly_revenue'];
        $cumulative_revenue[] = ['month' => $m['month'], 'cumulative_revenue' => $running];
    }
}

// Order Status Distribution (every status, unlike the Orders card which only counts paid or later)
$status_distribution = wa_fetch_all($inventory, "SELECT status, COUNT(*) AS count
    FROM orders
    WHERE $month_range
    GROUP BY status");
?>

<?php
require_once 'admin_layout.php';

// Recent orders. LEFT JOIN so an order from a deleted account still shows up.
$recent_orders = wa_fetch_all($inventory, "SELECT o.*, u.username
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 5");

$top_max = 0;
foreach ($top_products as $p) {
    $top_max = max($top_max, (int) $p['total_sold']);
}

$actions = '<a class="btn btn-secondary" href="admin_orders.php"><i class="fas fa-receipt" aria-hidden="true"></i> All orders</a>';
if ($can_web_finance) {
    $actions .= '<a class="btn btn-primary" href="admin_reports.php"><i class="fas fa-chart-line" aria-hidden="true"></i> View reports</a>';
}
admin_layout_start([
    'page' => 'dashboard',
    'title' => 'Dashboard',
    'subtitle' => 'Month to date: ' . date('F Y'),
    'actions' => $actions,
    'chart' => true,
]);
?>

<!-- KPIs -->
<div class="kpi-grid">
    <a href="admin_orders.php" class="kpi">
        <span class="kpi-label"><i class="fas fa-receipt" aria-hidden="true"></i> Orders</span>
        <span class="kpi-value"><?php echo (int) $stats['total_orders']; ?></span>
        <span class="kpi-meta">Paid or later (excludes pending and cancelled)</span>
    </a>
    <?php if ($can_web_finance): ?>
    <a href="admin_reports.php" class="kpi">
        <span class="kpi-label"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Revenue</span>
        <span class="kpi-value">₱<?php echo number_format($stats['total_revenue'], 2); ?></span>
        <span class="kpi-meta">Open reports</span>
    </a>
    <?php endif; ?>
    <a href="admin_orders.php?status=pending" class="kpi">
        <span class="kpi-label"><i class="fas fa-clock" aria-hidden="true"></i> Pending</span>
        <span class="kpi-value"><?php echo (int) $stats['pending_orders']; ?></span>
        <span class="kpi-meta">Waiting for payment or review</span>
    </a>
    <a href="admin_orders.php?status=completed" class="kpi">
        <span class="kpi-label"><i class="fas fa-circle-check" aria-hidden="true"></i> Completed</span>
        <span class="kpi-value"><?php echo (int) $stats['completed_orders']; ?></span>
        <span class="kpi-meta">Finished this month</span>
    </a>
    <a href="admin_customers.php" class="kpi">
        <span class="kpi-label"><i class="fas fa-users" aria-hidden="true"></i> Customers</span>
        <span class="kpi-value"><?php echo (int) $stats['total_customers']; ?></span>
        <span class="kpi-meta">All registered accounts</span>
    </a>
</div>

<?php
// Reusable blocks so the grid can adapt when revenue is hidden by permission
ob_start(); ?>
<section class="card chart-card" aria-labelledby="statusTitle">
    <div class="card-head">
        <h2 class="card-title" id="statusTitle">Order status</h2>
        <span class="card-sub">All statuses, this month</span>
    </div>
    <?php if (empty($status_distribution)): ?>
        <div class="empty-state"><i class="fas fa-chart-pie" aria-hidden="true"></i><h3>No orders this month</h3><p>The status mix appears once orders come in.</p></div>
    <?php else: ?>
        <div class="chart-box chart-box--short"><canvas id="statusChart" role="img" aria-label="Order status distribution this month"></canvas></div>
    <?php endif; ?>
</section>
<?php $status_card = ob_get_clean();

ob_start(); ?>
<section class="card chart-card" aria-labelledby="topTitle">
    <div class="card-head">
        <h2 class="card-title" id="topTitle">Top products</h2>
        <span class="card-sub">Units sold this month</span>
    </div>
    <?php if (empty($top_products)): ?>
        <div class="empty-state"><i class="fas fa-box-open" aria-hidden="true"></i><h3>No sales yet</h3><p>Top sellers appear once orders are paid.</p></div>
    <?php else: ?>
        <ol class="rank-list">
            <?php foreach ($top_products as $i => $product):
                $pct = $top_max > 0 ? round(((int) $product['total_sold'] / $top_max) * 100) : 0; ?>
                <li class="rank-row">
                    <span class="rank-no"><?php echo $i + 1; ?></span>
                    <span class="rank-name" title="<?php echo htmlspecialchars($product['product_name']); ?>"><?php echo htmlspecialchars($product['product_name']); ?></span>
                    <span class="rank-value"><?php echo (int) $product['total_sold']; ?> units</span>
                    <span class="rank-bar" aria-hidden="true"><span style="width: <?php echo $pct; ?>%"></span></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
<?php $top_card = ob_get_clean();

ob_start(); ?>
<section class="card chart-card" aria-labelledby="recentTitle">
    <div class="card-head">
        <h2 class="card-title" id="recentTitle">Recent orders</h2>
        <a class="view-all" href="admin_orders.php">View all orders</a>
    </div>
    <?php if (empty($recent_orders)): ?>
        <div class="empty-state"><i class="fas fa-receipt" aria-hidden="true"></i><h3>No orders yet</h3><p>New orders will show up here.</p></div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <?php if ($can_web_finance): ?><th class="num">Amount</th><?php endif; ?>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td><a class="cell-main" href="admin_orders.php?open=<?php echo (int) $order['order_id']; ?>">#<?php echo (int) $order['order_id']; ?></a></td>
                        <td><?php echo htmlspecialchars($order['username'] ?? 'Deleted account'); ?></td>
                        <?php if ($can_web_finance): ?><td class="num">₱<?php echo number_format($order['total_amount'], 2); ?></td><?php endif; ?>
                        <td>
                            <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php $recent_card = ob_get_clean();
?>

<?php if ($can_web_finance): ?>
    <div class="dash-grid">
        <section class="card chart-card" aria-labelledby="revTitle">
            <div class="card-head">
                <h2 class="card-title" id="revTitle">Cumulative revenue, <?php echo date('Y'); ?></h2>
                <span class="card-sub">By month</span>
            </div>
            <?php if (empty($cumulative_revenue)): ?>
                <div class="empty-state"><i class="fas fa-chart-line" aria-hidden="true"></i><h3>No revenue recorded this year</h3><p>The trend appears once orders are paid.</p></div>
            <?php else: ?>
                <div class="chart-box chart-box--short"><canvas id="revenueChart" role="img" aria-label="Cumulative revenue by month"></canvas></div>
            <?php endif; ?>
        </section>
        <?php echo $status_card; ?>
    </div>
    <div class="dash-grid">
        <?php echo $recent_card; ?>
        <?php echo $top_card; ?>
    </div>
<?php else: ?>
    <div class="dash-grid dash-grid--single">
        <?php echo $status_card; ?>
        <?php echo $top_card; ?>
    </div>
    <?php echo $recent_card; ?>
<?php endif; ?>

<?php admin_layout_end(); ?>

<?php
$wa_data = [
    'revenue' => [
        'labels' => array_column($cumulative_revenue, 'month'),
        'values' => array_map('floatval', array_column($cumulative_revenue, 'cumulative_revenue')),
    ],
    'status' => [
        'labels' => array_map(function ($status) {
            return ucfirst(str_replace('_', ' ', $status['status']));
        }, $status_distribution),
        'values' => array_map('intval', array_column($status_distribution, 'count')),
    ],
];
?>
<script>
    window.WA_CONFIG = {
        data: <?php echo json_encode($wa_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    };
</script>
<script src="../../assets/js/website_admin.js"></script>
</body>

</html>