<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// Get dashboard statistics
$stats = [];

// Get current month dates
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');

// Total Orders - Current Month
$query = "SELECT COUNT(*) as total_orders FROM orders 
          WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'
          AND status IN ('completed')";
$result = $inventory->query($query);
$stats['total_orders'] = $result->fetch_assoc()['total_orders'];

// Total Revenue - Current Month  
$query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders 
          WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'
          AND status IN ('completed')";
$result = $inventory->query($query);
$stats['total_revenue'] = $result->fetch_assoc()['total_revenue'];

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
          AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
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
          AND status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month";
$cumulative_revenue_result = $inventory->query($query);
$cumulative_revenue = [];
while ($row = $cumulative_revenue_result->fetch_assoc()) {
    $cumulative_revenue[] = $row;
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
    <style>
        :root {
            --primary: #4f5eff;
            --secondary: #4048e0;
            --primary-bg: #eef1ff;
            --light: #f6f6f7;
            --dark: #14171f;
            --gray: #6b7280;
            --light-gray: #e2e4e7;
            --card-bg: #ffffff;
            --success: #1a9c6b;
            --success-bg: #e3f6ee;
            --danger: #d9463c;
            --danger-bg: #fbe9e7;
            --warning: #b6790a;
            --warning-bg: #fdf2df;
            --info: #2a7ade;
            --info-bg: #e8f1fc;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbced3;
            border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 28px 32px;
            background: var(--light);
            padding-bottom: 90px;
        }

        .header {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 18px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--dark);
            font-size: 22px;
            margin: 0;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: var(--danger-bg);
            color: var(--danger);
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: opacity 0.15s ease;
        }

        .logout-btn:hover {
            opacity: 0.8;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 18px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            text-align: center;
            transition: box-shadow 0.15s ease;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(20, 23, 31, 0.08);
        }

        .stat-card i {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .stat-card.orders i {
            color: var(--primary);
        }

        .stat-card.revenue i {
            color: var(--success);
        }

        .stat-card.pending i {
            color: var(--warning);
        }

        .stat-card.completed i {
            color: var(--success);
        }

        .stat-card.customers i {
            color: var(--secondary);
        }

        .stat-number {
            font-size: 22px;
            font-weight: 700;
            margin: 6px 0;
        }

        .stat-label {
            color: var(--gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .chart-container {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 18px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
        }

        .chart-title {
            margin-bottom: 16px;
            color: var(--dark);
            font-size: 15px;
            font-weight: 600;
        }

        /* Recent Orders */
        .recent-orders {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 18px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
        }

        .table th {
            background: var(--light);
            font-weight: 600;
            color: var(--gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-paid {
            background: var(--info-bg);
            color: var(--info);
        }

        .status-processing {
            background: var(--success-bg);
            color: var(--success);
        }

        .status-ready_for_pickup {
            background: var(--primary-bg);
            color: var(--secondary);
        }

        .status-completed {
            background: var(--success-bg);
            color: var(--success);
        }

        .view-all {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        @media (max-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 20px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Monthly Website Overview - <?php echo date('F Y'); ?></h1>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card orders">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card revenue">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-number">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card pending">
                    <i class="fas fa-clock"></i>
                    <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card completed">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number"><?php echo $stats['completed_orders']; ?></div>
                    <div class="stat-label">Completed Orders</div>
                </div>
                <div class="stat-card customers">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
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
                                <th>Amount</th>
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
                                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
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

    <script>
        // Revenue Chart - Monthly Cumulative data
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($cumulative_revenue, 'month')); ?>,
                datasets: [{
                    label: 'Cumulative Revenue (₱)',
                    data: <?php echo json_encode(array_column($cumulative_revenue, 'cumulative_revenue')); ?>,
                    borderColor: '#4f5eff',
                    backgroundColor: 'rgba(79, 94, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Revenue (₱)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function ($status) {
                            return ucfirst(str_replace('_', ' ', $status['status']));
                        }, $status_distribution)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($status_distribution, 'count')); ?>,
                    backgroundColor: [
                        '#fdf2df', // pending
                        '#e8f1fc', // paid
                        '#e3f6ee', // processing
                        '#eef1ff', // ready_for_pickup
                        '#e3f6ee' // completed
                    ],
                    borderColor: [
                        '#b6790a',
                        '#2a7ade',
                        '#1a9c6b',
                        '#4048e0',
                        '#1a9c6b'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true
            }
        });
    </script>
</body>

</html>