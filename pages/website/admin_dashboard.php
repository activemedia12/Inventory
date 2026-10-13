<?php
session_start();
require_once '../../config/db.php';
require_once '../permissions.php';

// Check if user is logged in and is admin
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

// Orders in these statuses are treated as "counts toward revenue" everywhere
// in the admin panel (dashboard, reports, export) so the numbers agree.
$REVENUE_STATUSES = ['paid', 'processing', 'ready_for_pickup', 'completed'];
$revenue_status_list = "'" . implode("','", array_map([$inventory, 'real_escape_string'], $REVENUE_STATUSES)) . "'";

// Total Orders - Current Month
$query = "SELECT COUNT(*) as total_orders FROM orders 
          WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'
          AND status IN ($revenue_status_list)";
$result = $inventory->query($query);
$stats['total_orders'] = $result->fetch_assoc()['total_orders'];

// Total Revenue - Current Month  
$stats['total_revenue'] = 0;
if ($can_web_finance) {
    $query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders 
              WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'
              AND status IN ($revenue_status_list)";
    $result = $inventory->query($query);
    $stats['total_revenue'] = $result->fetch_assoc()['total_revenue'];
}

// Pending Orders - Current Month
$query = "SELECT COUNT(*) as pending_orders FROM orders 
          WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'
          AND status = 'pending'";
$result = $inventory->query($query);
$stats['pending_orders'] = $result->fetch_assoc()['pending_orders'];

// Completed Orders - Current Month
$query = "SELECT COUNT(*) as completed_orders FROM orders 
          WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'
          AND status = 'completed'";
$result = $inventory->query($query);
$stats['completed_orders'] = $result->fetch_assoc()['completed_orders'];

// Recent Orders - Current Month
$query = "SELECT COUNT(*) as recent_orders FROM orders 
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND created_at BETWEEN '$current_month_start' AND '$current_month_end'";
$result = $inventory->query($query);
$stats['recent_orders'] = $result->fetch_assoc()['recent_orders'];

// Total Customers
$query = "SELECT COUNT(*) as total_customers FROM users WHERE role = 'customer'";
$result = $inventory->query($query);
$stats['total_customers'] = $result->fetch_assoc()['total_customers'];

// Top Products - Current Month
$query = "SELECT oi.product_name, SUM(oi.quantity) as total_sold 
          FROM order_items oi
          JOIN orders o ON oi.order_id = o.order_id
          WHERE o.created_at BETWEEN '$current_month_start' AND '$current_month_end'
          AND o.status IN ($revenue_status_list)
          GROUP BY oi.product_name 
          ORDER BY total_sold DESC 
          LIMIT 5";
$top_products_result = $inventory->query($query);
$top_products = [];
while ($row = $top_products_result->fetch_assoc()) {
    $top_products[] = $row;
}

$current_year_start = date('Y-01-01');
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(SUM(total_amount)) OVER (ORDER BY DATE_FORMAT(created_at, '%Y-%m')) as cumulative_revenue
          FROM orders 
          WHERE created_at >= '$current_year_start'
          AND status IN ($revenue_status_list)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$cumulative_revenue = [];
if ($can_web_finance) {
    $cumulative_revenue_result = $inventory->query($query);
    while ($row = $cumulative_revenue_result->fetch_assoc()) {
        $cumulative_revenue[] = $row;
    }
}

// Order Status Distribution
$query = "SELECT status, COUNT(*) as count 
          FROM orders 
          WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'
          GROUP BY status";
$status_distribution_result = $inventory->query($query);
$status_distribution = [];
while ($row = $status_distribution_result->fetch_assoc()) {
    $status_distribution[] = $row;
}
?>

<?php
require_once 'admin_layout.php';

// Recent orders (same query as before, fetched up front so the markup can show an empty state)
$recent_orders = [];
$query = "SELECT o.*, u.username 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          ORDER BY o.created_at DESC 
          LIMIT 5";
$result = $inventory->query($query);
while ($result && ($order = $result->fetch_assoc())) {
    $recent_orders[] = $order;
}

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
        <span class="kpi-meta">Paid or later this month</span>
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
        <span class="card-sub">This month</span>
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
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
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