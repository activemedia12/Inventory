<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// Get date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'sales';

// Validate dates
if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Build date condition for queries
$date_condition = "o.created_at BETWEEN ? AND ?";
$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$types = 'ss';

// Get sales report data
// Get sales report data
if ($report_type === 'sales') {
    // Debug: Check what dates are being used
    error_log("Date range: $start_date to $end_date");

    // Total sales with COALESCE to handle NULL values
    $sales_query = "SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_order_value,
        COUNT(DISTINCT user_id) as unique_customers
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ? 
        AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed', 'pending')";

    $sales_stmt = $inventory->prepare($sales_query);

    // Format dates properly for MySQL
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';

    $sales_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();

    if ($sales_result) {
        $sales_data = $sales_result->fetch_assoc();

        // Debug output (remove in production)
        echo "<!-- Sales Data: " . print_r($sales_data, true) . " -->";

        // Ensure we have values even if NULL
        $sales_data = [
            'total_orders' => $sales_data['total_orders'] ?? 0,
            'total_revenue' => $sales_data['total_revenue'] ?? 0,
            'avg_order_value' => $sales_data['avg_order_value'] ?? 0,
            'unique_customers' => $sales_data['unique_customers'] ?? 0
        ];
    } else {
        // Handle query error
        $sales_data = [
            'total_orders' => 0,
            'total_revenue' => 0,
            'avg_order_value' => 0,
            'unique_customers' => 0
        ];
        echo "<!-- Query Error: " . $sales_stmt->error . " -->";
    }

    // Daily sales trend
    $daily_sales_query = "SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as order_count,
        COALESCE(SUM(o.total_amount), 0) as daily_revenue
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ? 
        AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed', 'pending')
        GROUP BY DATE(o.created_at)
        ORDER BY date";

    $daily_sales_stmt = $inventory->prepare($daily_sales_query);
    $daily_sales_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $daily_sales_stmt->execute();
    $daily_sales_result = $daily_sales_stmt->get_result();
    $daily_sales = $daily_sales_result ? $daily_sales_result->fetch_all(MYSQLI_ASSOC) : [];

    // Top products
    $top_products_query = "SELECT 
        oi.product_name,
        COALESCE(SUM(oi.quantity), 0) as total_sold,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.created_at BETWEEN ? AND ? 
        AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed', 'pending')
        GROUP BY oi.product_name
        ORDER BY total_sold DESC
        LIMIT 10";

    $top_products_stmt = $inventory->prepare($top_products_query);
    $top_products_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $top_products_stmt->execute();
    $top_products_result = $top_products_stmt->get_result();
    $top_products = $top_products_result ? $top_products_result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get customer report data
if ($report_type === 'customers') {
    // Customer statistics
    $customer_stats_query = "SELECT 
        COUNT(DISTINCT co.user_id) as active_customers,
        COALESCE(AVG(co.order_count), 0) as avg_orders_per_customer,
        COALESCE(AVG(co.total_spent), 0) as avg_customer_value
        FROM (
            SELECT user_id, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as total_spent
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ? 
            AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed', 'pending')
            GROUP BY user_id
        ) as co";

    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';

    $customer_stats_stmt = $inventory->prepare($customer_stats_query);
    $customer_stats_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $customer_stats_stmt->execute();
    $customer_stats_result = $customer_stats_stmt->get_result();
    $customer_stats = $customer_stats_result ? $customer_stats_result->fetch_assoc() : [
        'active_customers' => 0,
        'avg_orders_per_customer' => 0,
        'avg_customer_value' => 0
    ];

    // Top customers
    $top_customers_query = "SELECT 
        u.username,
        pc.first_name,
        pc.last_name,
        COUNT(o.order_id) as order_count,
        SUM(o.total_amount) as total_spent
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN personal_customers pc ON u.id = pc.user_id
        WHERE $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY o.user_id
        ORDER BY total_spent DESC
        LIMIT 10";

    $top_customers_stmt = $inventory->prepare($top_customers_query);
    $top_customers_stmt->bind_param($types, ...$params);
    $top_customers_stmt->execute();
    $top_customers = $top_customers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get product report data
if ($report_type === 'products') {
    // Product performance
    $product_performance_query = "SELECT 
        p.product_name,
        p.category,
        p.price,
        COUNT(oi.order_item_id) as times_ordered,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM products_offered p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY p.id, p.product_name, p.category, p.price
        ORDER BY total_revenue DESC";

    $product_performance_stmt = $inventory->prepare($product_performance_query);
    $product_performance_stmt->bind_param($types, ...$params);
    $product_performance_stmt->execute();
    $product_performance = $product_performance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Category performance
    $category_performance_query = "SELECT 
        p.category,
        COUNT(oi.order_item_id) as total_orders,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM products_offered p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY p.category
        ORDER BY total_revenue DESC";

    $category_performance_stmt = $inventory->prepare($category_performance_query);
    $category_performance_stmt->bind_param($types, ...$params);
    $category_performance_stmt->execute();
    $category_performance = $category_performance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get order status distribution
$status_distribution_query = "SELECT 
    o.status,
    COUNT(*) as order_count,
    SUM(o.total_amount) as total_amount
    FROM orders o
    WHERE $date_condition
    GROUP BY o.status
    ORDER BY order_count DESC";

$status_distribution_stmt = $inventory->prepare($status_distribution_query);
$status_distribution_stmt->bind_param($types, ...$params);
$status_distribution_stmt->execute();
$status_distribution = $status_distribution_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* Report Tabs */
        .report-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 9px 16px;
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: var(--gray);
            transition: background-color 0.15s ease, color 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background: var(--light);
            color: var(--dark);
        }

        /* Report Filters */
        .report-filters {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 18px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
        }

        .filter-row {
            display: flex;
            gap: 16px;
            align-items: end;
            flex-wrap: wrap;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .form-control {
            padding: 9px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            color: var(--dark);
            background: var(--card-bg);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-bg);
        }

        .btn {
            padding: 9px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.15s ease, opacity 0.15s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            opacity: 0.85;
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--light-gray);
        }

        .btn-secondary:hover {
            background: var(--light-gray);
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

        .stat-card.avg-order i {
            color: var(--warning);
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

        /* Tables */
        .data-table {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
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

        .category-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .category-offset {
            background: var(--primary-bg);
            color: var(--secondary);
        }

        .category-digital {
            background: var(--success-bg);
            color: var(--success);
        }

        .category-riso {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .category-other {
            background: var(--danger-bg);
            color: var(--danger);
        }

        /* Messages */
        .message {
            padding: 12px 15px;
            background: var(--success-bg);
            color: var(--success);
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .error {
            padding: 12px 15px;
            background: var(--danger-bg);
            color: var(--danger);
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .charts-section {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .report-tabs {
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Reports & Analytics</h1>
            </div>

            <!-- Report Tabs -->
            <div class="report-tabs">
                <button class="tab-btn <?php echo $report_type === 'sales' ? 'active' : ''; ?>"
                    onclick="changeReportType('sales')">
                    <i class="fas fa-chart-line"></i> Sales Report
                </button>
                <button class="tab-btn <?php echo $report_type === 'customers' ? 'active' : ''; ?>"
                    onclick="changeReportType('customers')">
                    <i class="fas fa-users"></i> Customer Report
                </button>
                <button class="tab-btn <?php echo $report_type === 'products' ? 'active' : ''; ?>"
                    onclick="changeReportType('products')">
                    <i class="fas fa-box"></i> Product Report
                </button>
            </div>

            <!-- Report Filters -->
            <div class="report-filters">
                <form method="GET" id="reportForm">
                    <input type="hidden" name="report_type" id="reportType" value="<?php echo $report_type; ?>">

                    <div class="filter-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                value="<?php echo $start_date; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                value="<?php echo $end_date; ?>" required>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportReport()">
                                <i class="fas fa-download"></i> Export Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Sales Report -->
            <?php if ($report_type === 'sales'): ?>
                <!-- Sales Statistics -->
                <div class="stats-grid">
                    <div class="stat-card orders">
                        <i class="fas fa-shopping-cart"></i>
                        <div class="stat-number"><?php echo $sales_data['total_orders'] ?? 0; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card revenue">
                        <i class="fas fa-money-bill-wave"></i>
                        <div class="stat-number">₱ <?php echo number_format($sales_data['total_revenue'] ?? 0, 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card avg-order">
                        <i class="fas fa-chart-pie"></i>
                        <div class="stat-number">₱ <?php echo number_format($sales_data['avg_order_value'] ?? 0, 2); ?></div>
                        <div class="stat-label">Average Order Value</div>
                    </div>
                    <div class="stat-card customers">
                        <i class="fas fa-users"></i>
                        <div class="stat-number"><?php echo $sales_data['unique_customers'] ?? 0; ?></div>
                        <div class="stat-label">Unique Customers</div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-section">
                    <div class="chart-container">
                        <h3 class="chart-title">Sales Trend</h3>
                        <canvas id="salesTrendChart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h3 class="chart-title">Order Status Distribution</h3>
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="data-table">
                    <h3 style="padding: 20px 20px 0; margin: 0;">Top Selling Products</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity Sold</th>
                                <th>Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo $product['total_sold']; ?></td>
                                    <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Customer Report -->
            <?php if ($report_type === 'customers'): ?>
                <!-- Customer Statistics -->
                <div class="stats-grid">
                    <div class="stat-card customers">
                        <i class="fas fa-users"></i>
                        <div class="stat-number"><?php echo $customer_stats['active_customers'] ?? 0; ?></div>
                        <div class="stat-label">Active Customers</div>
                    </div>
                    <div class="stat-card orders">
                        <i class="fas fa-shopping-cart"></i>
                        <div class="stat-number"><?php echo round($customer_stats['avg_orders_per_customer'] ?? 0, 1); ?></div>
                        <div class="stat-label">Avg Orders per Customer</div>
                    </div>
                    <div class="stat-card revenue">
                        <i class="fas fa-money-bill-wave"></i>
                        <div class="stat-number">₱<?php echo number_format($customer_stats['avg_customer_value'] ?? 0, 2); ?></div>
                        <div class="stat-label">Avg Customer Value</div>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="data-table">
                    <h3 style="padding: 20px 20px 0; margin: 0;">Top Customers</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_customers as $customer): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        <br>
                                        <small style="color: var(--gray);"><?php echo htmlspecialchars($customer['username']); ?></small>
                                    </td>
                                    <td><?php echo $customer['order_count']; ?></td>
                                    <td>₱<?php echo number_format($customer['total_spent'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Product Report -->
            <?php if ($report_type === 'products'): ?>
                <!-- Product Performance -->
                <div class="data-table">
                    <h3 style="padding: 20px 20px 0; margin: 0;">Product Performance</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Times Ordered</th>
                                <th>Total Quantity</th>
                                <th>Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($product_performance as $product):
                                $category_class = 'category-' . strtolower(str_replace(' ', '-', $product['category']));
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td>
                                        <span class="category-badge <?php echo $category_class; ?>">
                                            <?php echo htmlspecialchars($product['category']); ?>
                                        </span>
                                    </td>
                                    <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo $product['times_ordered']; ?></td>
                                    <td><?php echo $product['total_quantity']; ?></td>
                                    <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Category Performance -->
                <div class="data-table">
                    <h3 style="padding: 20px 20px 0; margin: 0;">Category Performance</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Orders</th>
                                <th>Total Quantity</th>
                                <th>Total Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_performance as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category']); ?></td>
                                    <td><?php echo $category['total_orders']; ?></td>
                                    <td><?php echo $category['total_quantity']; ?></td>
                                    <td>₱<?php echo number_format($category['total_revenue'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide messages after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.transition = 'opacity 0.5s ease';
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 500);
                }, 3000);
            });
        });

        // Change report type
        function changeReportType(type) {
            document.getElementById('reportType').value = type;
            document.getElementById('reportForm').submit();
        }

        // Reset filters
        function resetFilters() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            document.getElementById('start_date').value = formatDate(firstDay);
            document.getElementById('end_date').value = formatDate(lastDay);
            document.getElementById('reportForm').submit();
        }

        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }

        // Export report
        function exportReport() {
            Swal.fire({
                title: 'Export Report',
                text: 'This feature will export the current report as CSV file.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Export CSV',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Add export functionality here
                    const form = document.getElementById('reportForm');
                    form.target = '_blank';
                    form.action = 'export_report.php';
                    form.submit();
                    form.target = '';
                    form.action = '';
                }
            });
        }

        // Charts
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($report_type === 'sales' && !empty($daily_sales)): ?>
                // Sales Trend Chart
                const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
                const salesTrendChart = new Chart(salesTrendCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
                        datasets: [{
                            label: 'Daily Revenue (₱)',
                            data: <?php echo json_encode(array_column($daily_sales, 'daily_revenue')); ?>,
                            borderColor: '#4f5eff',
                            backgroundColor: 'rgba(79, 94, 255, 0.1)',
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

                // Status Distribution Chart
                const statusCtx = document.getElementById('statusChart').getContext('2d');
                const statusChart = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_map(function ($status) {
                                    return ucfirst(str_replace('_', ' ', $status['status']));
                                }, $status_distribution)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($status_distribution, 'order_count')); ?>,
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
            <?php endif; ?>
        });
    </script>

    <!-- SweetAlert Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo $_SESSION['message']; ?>',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    background: 'var(--success-bg)',
                    iconColor: 'var(--success)',
                    color: 'var(--success)'
                });
                <?php unset($_SESSION['message']); ?>
            });
        </script>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?php echo $_SESSION['error']; ?>',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    background: 'var(--danger-bg)',
                    iconColor: 'var(--danger)',
                    color: 'var(--danger)'
                });
                <?php unset($_SESSION['error']); ?>
            });
        </script>
    <?php endif; ?>
</body>

</html>