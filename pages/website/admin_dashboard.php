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
// Recent orders (presentation query only - moved out of the markup)
$recent_orders = [];
$result = $inventory->query("SELECT o.*, u.username
                             FROM orders o
                             JOIN users u ON o.user_id = u.id
                             ORDER BY o.created_at DESC
                             LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recent_orders[] = $row;
}

function dash_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $chars = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
    return function_exists('mb_strtoupper') ? mb_strtoupper($chars) : strtoupper($chars);
}

$dash_status_labels = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'processing' => 'Processing',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-dashboard" data-page="dashboard">
    <div class="admin-container">
        <main class="main-content">
            <div class="wa-page">

                <!-- 1. Page header -->
                <header class="wa-page-head">
                    <div>
                        <h1 class="wa-page-title">Monthly Website Overview</h1>
                        <p class="wa-page-desc"><?php echo date('F Y'); ?> &middot; orders, customers and top-selling products at a glance.</p>
                    </div>
                    <div class="wa-page-actions">
                        <?php if ($stats['pending_orders'] > 0): ?>
                            <a class="wa-chip tone-pending" href="admin_orders.php?status=pending">
                                <span class="wa-dot" aria-hidden="true"></span>
                                <?php echo (int) $stats['pending_orders']; ?>&nbsp;pending
                            </a>
                        <?php endif; ?>
                        <?php if ($can_web_finance): ?>
                            <a class="btn btn-secondary" href="admin_reports.php"><i class="fas fa-chart-line" aria-hidden="true"></i> Reports</a>
                        <?php endif; ?>
                        <a class="btn btn-primary" href="admin_orders.php"><i class="fas fa-receipt" aria-hidden="true"></i> All orders</a>
                    </div>
                </header>

                <!-- 2. Summary -->
                <section class="wa-stats" aria-label="Monthly summary">
                    <a class="wa-stat tone-paid" href="admin_orders.php">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-shopping-cart"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Total orders</span>
                            <span class="wa-stat-value"><?php echo (int) $stats['total_orders']; ?></span>
                            <span class="wa-stat-sub">View all orders</span>
                        </span>
                    </a>
                    <?php if ($can_web_finance): ?>
                        <a class="wa-stat tone-completed" href="admin_reports.php">
                            <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-money-bill-wave"></i></span>
                            <span class="wa-stat-body">
                                <span class="wa-stat-label">Total revenue</span>
                                <span class="wa-stat-value">&#8369;<?php echo number_format($stats['total_revenue'], 2); ?></span>
                                <span class="wa-stat-sub">View reports</span>
                            </span>
                        </a>
                    <?php endif; ?>
                    <a class="wa-stat tone-pending" href="admin_orders.php?status=pending">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Pending orders</span>
                            <span class="wa-stat-value"><?php echo (int) $stats['pending_orders']; ?></span>
                            <span class="wa-stat-sub">View pending</span>
                        </span>
                    </a>
                    <a class="wa-stat tone-ready_for_pickup" href="admin_orders.php?status=completed">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-circle-check"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Completed orders</span>
                            <span class="wa-stat-value"><?php echo (int) $stats['completed_orders']; ?></span>
                            <span class="wa-stat-sub">View completed</span>
                        </span>
                    </a>
                    <a class="wa-stat tone-processing" href="admin_customers.php">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Total customers</span>
                            <span class="wa-stat-value"><?php echo (int) $stats['total_customers']; ?></span>
                            <span class="wa-stat-sub">View customers</span>
                        </span>
                    </a>
                </section>

                <!-- 3. Charts -->
                <div class="wa-dash-grid<?php echo $can_web_finance ? '' : ' wa-dash-grid--single'; ?>">
                    <?php if ($can_web_finance): ?>
                        <section class="wa-datacard" aria-labelledby="revenueHeading">
                            <div class="wa-datacard-head">
                                <div>
                                    <h2 class="wa-datacard-title" id="revenueHeading">Cumulative revenue</h2>
                                    <p class="wa-datacard-sub"><?php echo date('Y'); ?> &middot; month by month</p>
                                </div>
                            </div>
                            <div class="dash-chart">
                                <canvas id="revenueChart" aria-label="Cumulative revenue by month"></canvas>
                            </div>
                        </section>
                    <?php endif; ?>
                    <section class="wa-datacard" aria-labelledby="statusHeading">
                        <div class="wa-datacard-head">
                            <div>
                                <h2 class="wa-datacard-title" id="statusHeading">Order status distribution</h2>
                                <p class="wa-datacard-sub"><?php echo date('F Y'); ?></p>
                            </div>
                        </div>
                        <div class="dash-chart dash-chart--doughnut">
                            <canvas id="statusChart" aria-label="Orders by status"></canvas>
                        </div>
                    </section>
                </div>

                <!-- 4. Recent orders -->
                <section class="wa-datacard" aria-labelledby="recentHeading">
                    <div class="wa-datacard-head">
                        <div>
                            <h2 class="wa-datacard-title" id="recentHeading">Recent orders</h2>
                            <p class="wa-datacard-sub">Latest 5 &middot; newest first</p>
                        </div>
                        <a class="btn btn-outline btn-sm" href="admin_orders.php">View all orders <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </div>

                    <div class="wa-table-wrap">
                        <table class="ord-table dash-table">
                            <caption class="sr-only">Recent orders</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Order</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Date</th>
                                    <?php if ($can_web_finance): ?><th scope="col" class="is-num">Amount</th><?php endif; ?>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="<?php echo $can_web_finance ? 5 : 4; ?>" class="empty-row">
                                            <div class="wa-empty">
                                                <span class="wa-empty-icon" aria-hidden="true"><i class="fas fa-inbox"></i></span>
                                                <div class="wa-empty-title">No orders yet</div>
                                                <p class="wa-empty-text">Orders will appear here as customers place them.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($recent_orders as $order):
                                    $created_ts = strtotime($order['created_at']);
                                    $status_key = (string) $order['status'];
                                ?>
                                    <tr class="ord-row" data-href="admin_orders.php?open=<?php echo (int) $order['order_id']; ?>">
                                        <td class="ord-col-order">
                                            <a class="ord-id" href="admin_orders.php?open=<?php echo (int) $order['order_id']; ?>" aria-label="View order #<?php echo (int) $order['order_id']; ?>">#<?php echo (int) $order['order_id']; ?></a>
                                        </td>
                                        <td class="ord-col-customer">
                                            <div class="ord-customer">
                                                <span class="wa-avatar" aria-hidden="true"><?php echo htmlspecialchars(dash_initials((string) $order['username'])); ?></span>
                                                <div class="ord-customer-name"><?php echo htmlspecialchars($order['username']); ?></div>
                                            </div>
                                        </td>
                                        <td class="ord-col-payment">
                                            <div class="ord-meta"><time datetime="<?php echo date('c', $created_ts); ?>"><?php echo date('M j, Y', $created_ts); ?></time></div>
                                        </td>
                                        <?php if ($can_web_finance): ?>
                                            <td class="ord-col-total is-num"><span class="ord-total">&#8369;<?php echo number_format($order['total_amount'], 2); ?></span></td>
                                        <?php endif; ?>
                                        <td class="ord-col-status">
                                            <span class="wa-badge tone-<?php echo htmlspecialchars($status_key); ?>"><?php echo htmlspecialchars($dash_status_labels[$status_key] ?? ucfirst(str_replace('_', ' ', $status_key))); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- 5. Top products -->
                <section class="wa-datacard" aria-labelledby="topHeading">
                    <div class="wa-datacard-head">
                        <div>
                            <h2 class="wa-datacard-title" id="topHeading">Top products</h2>
                            <p class="wa-datacard-sub"><?php echo date('F Y'); ?> &middot; by units sold</p>
                        </div>
                    </div>

                    <div class="wa-table-wrap">
                        <table class="ord-table dash-table">
                            <caption class="sr-only">Top products this month</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="is-num">Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_products)): ?>
                                    <tr>
                                        <td colspan="2" class="empty-row">
                                            <div class="wa-empty">
                                                <span class="wa-empty-icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                                                <div class="wa-empty-title">No sales this month</div>
                                                <p class="wa-empty-text">Top-selling products will show up once orders are paid.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($top_products as $i => $product): ?>
                                    <tr class="dash-row">
                                        <td>
                                            <div class="ord-customer">
                                                <span class="wa-avatar" aria-hidden="true"><?php echo $i + 1; ?></span>
                                                <div class="ord-customer-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                            </div>
                                        </td>
                                        <td class="is-num"><span class="ord-total"><?php echo (int) $product['total_sold']; ?> units</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <?php
    $wa_data = [
        'revenue' => [
            'labels' => array_column($cumulative_revenue, 'month'),
            'values' => array_column($cumulative_revenue, 'cumulative_revenue'),
        ],
        'status' => [
            'labels' => array_map(function ($status) {
                return ucfirst(str_replace('_', ' ', $status['status']));
            }, $status_distribution),
            'values' => array_column($status_distribution, 'count'),
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