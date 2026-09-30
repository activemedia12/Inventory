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
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Monthly Website Overview - <?php echo date('F Y'); ?></h1>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <a href="admin_orders.php" class="stat-card orders">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-card-hint">View all orders →</div>
                </a>
                <?php if ($can_web_finance): ?>
                <a href="admin_reports.php" class="stat-card revenue">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-number">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-card-hint">View reports →</div>
                </a>
                <?php endif; ?>
                <a href="admin_orders.php?status=pending" class="stat-card pending">
                    <i class="fas fa-clock"></i>
                    <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
                    <div class="stat-label">Pending Orders</div>
                    <div class="stat-card-hint">View pending →</div>
                </a>
                <a href="admin_orders.php?status=completed" class="stat-card completed">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number"><?php echo $stats['completed_orders']; ?></div>
                    <div class="stat-label">Completed Orders</div>
                    <div class="stat-card-hint">View completed →</div>
                </a>
                <a href="admin_customers.php" class="stat-card customers">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Total Customers</div>
                    <div class="stat-card-hint">View customers →</div>
                </a>
            </div>

            <!-- Charts Section -->
            <div class="charts-section<?php echo $can_web_finance ? '' : ' charts-section--single'; ?>">
                <div class="chart-container"<?php if (!$can_web_finance) echo ' style="display:none"'; ?>>
                    <h3 class="chart-title">Cumulative Revenue - <?php echo date('Y'); ?> (Monthly)</h3>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3 class="chart-title">Order Status Distribution</h3>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Recent Orders & Top Products -->
            <div class="charts-section">
                <div class="chart-container">
                    <h3 class="chart-title">Recent Orders</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <?php if ($can_web_finance): ?><th>Amount</th><?php endif; ?>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT o.*, u.username 
                                      FROM orders o 
                                      JOIN users u ON o.user_id = u.id 
                                      ORDER BY o.created_at DESC 
                                      LIMIT 5";
                            $result = $inventory->query($query);
                            while ($order = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    <?php if ($can_web_finance): ?><td>₱<?php echo number_format($order['total_amount'], 2); ?></td><?php endif; ?>
                                    <td>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <a href="admin_orders.php" class="view-all">View All Orders →</a>
                </div>

                <div class="chart-container">
                    <h3 class="chart-title">Top Products</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo $product['total_sold']; ?> units</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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