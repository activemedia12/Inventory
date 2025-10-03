<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../accounts/login.php");
    exit;
}

// Get dashboard statistics
$stats = [];

// Total Orders
$query = "SELECT COUNT(*) as total_orders FROM orders";
$result = $inventory->query($query);
$stats['total_orders'] = $result->fetch_assoc()['total_orders'];

// Total Revenue
$query = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders WHERE status IN ('paid', 'processing', 'ready_for_pickup', 'completed')";
$result = $inventory->query($query);
$stats['total_revenue'] = $result->fetch_assoc()['total_revenue'];

// Pending Orders
$query = "SELECT COUNT(*) as pending_orders FROM orders WHERE status = 'pending'";
$result = $inventory->query($query);
$stats['pending_orders'] = $result->fetch_assoc()['pending_orders'];

// Completed Orders
$query = "SELECT COUNT(*) as completed_orders FROM orders WHERE status = 'completed'";
$result = $inventory->query($query);
$stats['completed_orders'] = $result->fetch_assoc()['completed_orders'];

// Recent Orders (last 7 days)
$query = "SELECT COUNT(*) as recent_orders FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$result = $inventory->query($query);
$stats['recent_orders'] = $result->fetch_assoc()['recent_orders'];

// Total Customers
$query = "SELECT COUNT(*) as total_customers FROM users WHERE role = 'customer'";
$result = $inventory->query($query);
$stats['total_customers'] = $result->fetch_assoc()['total_customers'];

// Top Products
$query = "SELECT product_name, SUM(quantity) as total_sold 
          FROM order_items 
          GROUP BY product_name 
          ORDER BY total_sold DESC 
          LIMIT 5";
$top_products_result = $inventory->query($query);
$top_products = [];
while ($row = $top_products_result->fetch_assoc()) {
    $top_products[] = $row;
}

// Monthly Revenue (for chart)
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(total_amount) as monthly_revenue
          FROM orders 
          WHERE status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month DESC 
          LIMIT 6";
$monthly_revenue_result = $inventory->query($query);
$monthly_revenue = [];
while ($row = $monthly_revenue_result->fetch_assoc()) {
    $monthly_revenue[] = $row;
}

// Order Status Distribution
$query = "SELECT status, COUNT(*) as count 
          FROM orders 
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #3498db;
            font-size: 1.3em;
            margin-bottom: 5px;
        }

        .sidebar-header small {
            font-size: 0.85em;
            color: #bdc3c7;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f8f9fa;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 1.8em;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2em;
            margin-bottom: 15px;
        }

        .stat-card.orders i { color: #3498db; }
        .stat-card.revenue i { color: #27ae60; }
        .stat-card.pending i { color: #f39c12; }
        .stat-card.completed i { color: #2ecc71; }
        .stat-card.customers i { color: #9b59b6; }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .chart-title {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 1.2em;
            font-weight: 600;
        }

        /* Recent Orders */
        .recent-orders {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-processing { background: #d4edda; color: #155724; }
        .status-ready_for_pickup { background: #cce7ff; color: #004085; }
        .status-completed { background: #d1f7c4; color: #0f5132; }

        .view-all {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }

        .view-all:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Dashboard Overview</h1>
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
                    <h3 class="chart-title">Revenue Overview (Last 6 Months)</h3>
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
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_revenue, 'month')); ?>,
                datasets: [{
                    label: 'Monthly Revenue (₱)',
                    data: <?php echo json_encode(array_column($monthly_revenue, 'monthly_revenue')); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function($status) { 
                    return ucfirst(str_replace('_', ' ', $status['status'])); 
                }, $status_distribution)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($status_distribution, 'count')); ?>,
                    backgroundColor: [
                        '#fff3cd', // pending
                        '#d1ecf1', // paid
                        '#d4edda', // processing
                        '#cce7ff', // ready_for_pickup
                        '#d1f7c4'  // completed
                    ],
                    borderColor: [
                        '#856404',
                        '#0c5460',
                        '#155724',
                        '#004085',
                        '#0f5132'
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