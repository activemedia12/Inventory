<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Function to fetch data from database
function fetchData($mysqli, $query)
{
    $result = $mysqli->query($query);
    return $result->fetch_assoc()['total'] ?? 0;
}

// Quick Stats
$total_products = fetchData($mysqli, "SELECT COUNT(*) AS total FROM products");
$deliveries_this_week = fetchData($mysqli, "
  SELECT COUNT(*) AS total FROM delivery_logs
  WHERE YEARWEEK(delivery_date, 1) = YEARWEEK(CURDATE(), 1)
");
$out_of_stock = fetchData($mysqli, "
  SELECT COUNT(*) AS total FROM products p
  LEFT JOIN (
    SELECT d.product_id,
          SUM(d.delivered_reams) AS total_reams,
          (SUM(d.delivered_reams) * 500) - IFNULL(SUM(u.used_sheets), 0) AS balance
    FROM delivery_logs d
    LEFT JOIN usage_logs u ON u.product_id = d.product_id
    GROUP BY d.product_id
  ) AS stock ON p.id = stock.product_id
  WHERE IFNULL(balance, 0) <= 0
");

// Recent Data
$recent_deliveries = $mysqli->query("
  SELECT d.product_id, d.delivery_date, p.product_type, p.product_group, p.product_name, d.delivered_reams
  FROM delivery_logs d
  JOIN products p ON d.product_id = p.id
  ORDER BY d.delivery_date DESC
  LIMIT 5
");

$recent_usage = $mysqli->query("
  SELECT u.product_id, u.log_date, p.product_type, p.product_group, p.product_name, u.used_sheets
  FROM usage_logs u
  JOIN products p ON u.product_id = p.id
  ORDER BY u.log_date DESC
  LIMIT 5
");

// Stock Summary Data
$stock_data = $mysqli->query("
  SELECT 
    p.product_type, p.product_group, p.product_name,
    ((SELECT IFNULL(SUM(delivered_reams), 0) FROM delivery_logs WHERE product_id = p.id) * 500 -
    (SELECT IFNULL(SUM(used_sheets), 0) FROM usage_logs WHERE product_id = p.id)) AS available_sheets
  FROM products p
  ORDER BY p.product_type, p.product_name, p.product_group
");

$low_stock = fetchData($mysqli, "
  SELECT COUNT(*) AS total FROM products p
  LEFT JOIN (
    SELECT d.product_id,
          SUM(d.delivered_reams) AS total_reams,
          (SUM(d.delivered_reams) * 500) - IFNULL(SUM(u.used_sheets), 0) AS balance
    FROM delivery_logs d
    LEFT JOIN usage_logs u ON u.product_id = d.product_id
    GROUP BY d.product_id
  ) AS stock ON p.id = stock.product_id
  WHERE IFNULL(balance, 0) >= 0 
  AND IFNULL(balance, 0) < 10000 /* 20 reams * 500 sheets */
");


$grouped = [];
while ($row = $stock_data->fetch_assoc()) {
    $type = $row['product_type'];
    $group = $row['product_group'];
    $name = $row['product_name'];
    $sheets = max(0, $row['available_sheets']);
    $reams = $sheets / 500;

    if (!isset($grouped[$type])) $grouped[$type] = [];
    if (!isset($grouped[$type][$name])) $grouped[$type][$name] = [];
    $grouped[$type][$name][$group] = $reams;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }

        :root {
            --primary: #1877f2;
            --secondary: #166fe5;
            --light: #f0f2f5;
            --dark: #1c1e21;
            --gray: #65676b;
            --light-gray: #e4e6eb;
            --card-bg: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
        }

        .brand {
            padding: 0 20px 40px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 20px;
        }

        .brand img {
            height: 100px;
            width: auto;
            padding-left: 40px;
            transform: rotate(45deg);
        }

        .brand h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            background-color: var(--light-gray);
        }

        .nav-menu li a i {
            margin-right: 10px;
            color: var(--gray);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .user-details h4 {
            font-weight: 500;
            font-size: 16px;
        }

        .user-details small {
            color: var(--gray);
            font-size: 14px;
        }

        .stock-cards {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
        }

        /* Stats Cards */
        .stat-card {
            min-width: 30%;
            max-height: 500px;
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            overflow: auto;
            margin-right: 20px;
            margin-bottom: 20px;
        }

        .stat-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-card .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(24, 119, 242, 0.1);
            color: var(--primary);
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: var(--gray);
            font-size: 14px;
        }

        .out-card {
            min-width: 300px;
            max-height: 120px;
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .out-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .out-card .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(24, 119, 242, 0.1);
            color: var(--primary);
        }

        .out-card h3 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .out-card p {
            color: var(--gray);
            font-size: 14px;
        }

        /* Tables Section */
        .tables-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            width: 100%;
        }

        .recent-tables td {
            cursor: pointer;
            font-size: 14px;
        }

        .table-card {
            width: 100%;
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            /* overflow: scroll; */
        }

        .table-card h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            color: var(--dark);
        }

        .table-card h3 i {
            margin-right: 10px;
            color: var(--primary);
        }

        table {
            min-width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }

        th {
            font-weight: 500;
            color: var(--gray);
            font-size: 14px;
        }

        tr td {
            transition: 0.3s;
        }

        tr:hover td {
            background-color: rgba(24, 119, 242, 0.05);
        }

        .view-all {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .sidebar-con {
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                position: fixed;
            }
            .sidebar {
                position: fixed;
                overflow: hidden;
                height: auto;
                width: auto;
                bottom: 20px;
                padding: 0;
                background-color: rgba(255, 255, 255, 0.3);
                backdrop-filter: blur(2px);
                box-shadow: 1px 1px 10px rgb(190, 190, 190);
                border-radius: 100px;
                cursor: grab;
                transition: left 0.05s ease-in, top 0.05s ease-in;
                touch-action: manipulation;
                z-index: 9999;
                flex-direction: row;
                border: 1px solid white;
                justify-content: center;
            }

            .sidebar .nav-menu {
                display: flex;
                flex-direction: row;
            }

            .sidebar img,
            .sidebar .brand,
            .sidebar .nav-menu li a span {
                display: none;
            }

            .sidebar .nav-menu li a {
                justify-content: center;
                padding: 15px;
            }

            .sidebar .nav-menu li a i {
                margin-right: 0;
            }

            .main-content {
                margin-left: 0;
                overflow: auto;
                margin-bottom: 200px;
            }

            .stat-card {
                min-width: 100%;
            }

            .out-card {
                min-width: 100%;
            }

            .recent-tables {
                overflow: scroll;
            }

            .tables-section {
                grid-template-columns: repeat(auto-fit, minmax(100%, 1fr));
                font-size: 90%;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-info {
                margin-top: 10px;
            }

            .recent-tables table {
                min-width: 500px;
            }
        }

        .stat-card table th,
        .stat-card table td {
            font-size: 14px;
            border-bottom: 1px solid var(--light-gray);
            white-space: nowrap;
        }

        .stat-card table span.low {
            color: red;
            font-weight: 600;
        }

        .stat-card table span.mid {
            color: orange;
            font-weight: 600;
        }

        .stat-card table span.high {
            color: green;
            font-weight: 600;
        }

        /* Stock Summary Styles */
        .stock-summary {
            margin-top: 15px;
        }

        .product-category {
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .category-header {
            padding: 12px 15px;
            background-color: var(--card-bg);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .category-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-title h4 {
            font-weight: 500;
            color: var(--dark);
            margin: 0;
        }

        .toggle-icon {
            color: var(--gray);
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .badge {
            background-color: var(--light-gray);
            color: var(--gray);
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .category-summary {
            display: flex;
            gap: 15px;
        }

        .summary-item {
            font-size: 13px;
            color: var(--gray);
        }

        .summary-item strong {
            color: var(--dark);
            margin-left: 5px;
        }

        .stock-table-container {
            display: none;
            background-color: var(--card-bg);
            max-height: 250px;
            overflow: scroll;
        }

        .stock-table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .stock-table-container th {
            font-weight: 500;
            font-size: 13px;
            color: var(--gray);
            padding: 8px 10px;
            text-align: left;
            position: sticky;
            top: 0;
            background-color: #fff;
            z-index: 2;
            box-shadow: 0 2px 2px rgba(0, 0, 0, 0.05);
        }

        .stock-table-container th.text-center {
            text-align: center;
        }

        .stock-table-container td {
            padding: 10px;
            border-top: 1px solid var(--light-gray);
        }

        .product-name {
            font-weight: 500;
            color: var(--dark);
            min-width: 150px;
        }

        .stock-indicator {
            position: relative;
            min-width: 80px;
        }

        .stock-value {
            font-weight: 600;
            font-size: 14px;
            text-align: center;
        }

        .stock-bar {
            height: 4px;
            background-color: var(--light-gray);
            border-radius: 2px;
            margin: 5px 0;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background-color: var(--primary);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .stock-label {
            font-size: 11px;
            color: var(--gray);
            text-align: center;
            text-transform: uppercase;
        }

        /* Stock level colors */
        .stock-indicator.high .bar-fill {
            background-color: #4CAF50;
        }

        .stock-indicator.mid .bar-fill {
            background-color: #FFC107;
        }

        .stock-indicator.low .bar-fill {
            background-color: #F44336;
        }

        .na {
            color: var(--light-gray);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .category-summary {
                width: 100%;
                justify-content: space-between;
            }

            .stock-table-container {
                overflow-x: auto;
            }
        }

        /* Modal Styles */
        @keyframes centerZoomIn {
            0% {
                transform: translate(-50%, -50%) scale(0.5);
                opacity: 0;
                animation-delay: 1000;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
                animation-delay: 1000;
            }
        }

        /* Modernized modal overlay */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
        }

        /* Enhanced modal content */
        .modal-content {
            background: white;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
            overflow: scroll;
            animation: fadeInUp 0.3s ease-out;
            position: relative;
        }

        /* Improved header section */
        .modal-content h3 {
            margin: 0;
            padding: 18px 20px;
            background: #e74c3c;
            color: white;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Better close button */
        .close {
            position: absolute;
            color: white;
            font-size: 28px;
            font-weight: normal;
            opacity: 0.8;
            transition: opacity 0.2s;
            cursor: pointer;
            right: 20px;
        }

        .close:hover {
            opacity: 1;
            color: white;
        }

        /* Content area improvements */
        #lowStockItems {
            overflow: scroll;
        }

        /* Modern table styling */
        #lowStockItems table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        #lowStockItems th {
            text-align: left;
            padding: 12px 15px;
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
        }

        #lowStockItems td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        #lowStockItems tr:last-child td {
            border-bottom: none;
        }

        #lowStockItems tr:hover {
            background: #f8f9fa;
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading state */
        .loading-message {
            padding: 30px;
            text-align: center;
            color: #666;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="">
            </div>
            <ul class="nav-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php" onclick="goToLastProductPage()"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <h1>Dashboard Overview</h1>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
                    <small><?= $_SESSION['role'] ?></small>
                </div>
            </div>
        </header>

        <div class="stock-cards">
            <!-- Stock Summary Card -->
            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <h3>Stock Summary</h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-boxes"></i></div>
                </div>

                <div class="stock-summary">
                    <?php foreach ($grouped as $type => $products): ?>
                        <div class="product-category">
                            <div class="category-header" onclick="toggleStockTable('<?= md5($type) ?>')">
                                <div class="category-title">
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                    <h4><?= htmlspecialchars($type) ?></h4>
                                    <span class="badge"><?= count($products) ?> items</span>
                                </div>
                                <div class="category-summary">
                                    <?php
                                    // Calculate summary stats for this category
                                    $totalReams = 0;
                                    $totalItems = 0;
                                    foreach ($products as $groupStocks) {
                                        foreach ($groupStocks as $reams) {
                                            if ($reams !== null) {
                                                $totalReams += $reams;
                                                $totalItems++;
                                            }
                                        }
                                    }
                                    $avgReams = $totalItems > 0 ? $totalReams / $totalItems : 0;
                                    ?>
                                    <div class="summary-item">
                                        <span>Total:</span>
                                        <strong><?= number_format($totalReams, 1) ?> reams</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="stock-table-container" id="table-<?= md5($type) ?>">
                                <table>
                                    <thead>
                                        <tr>
                                            <th class="product-name">Product</th>
                                            <?php
                                            $all_groups = [];
                                            foreach ($products as $pname => $groupStocks) {
                                                foreach ($groupStocks as $grp => $_) $all_groups[$grp] = true;
                                            }
                                            $columns = array_keys($all_groups);
                                            foreach ($columns as $grp):
                                            ?>
                                                <th class="text-center"><?= htmlspecialchars($grp) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $pname => $groupStocks): ?>
                                            <tr>
                                                <td class="product-name"><?= htmlspecialchars($pname) ?></td>
                                                <?php foreach ($columns as $grp): ?>
                                                    <?php
                                                    $reams = $groupStocks[$grp] ?? null;
                                                    if ($reams !== null) {
                                                        $class = 'low';
                                                        if ($reams >= 80) $class = 'high';
                                                        else if ($reams >= 20) $class = 'mid';
                                                        $percentage = min(100, ($reams / 100) * 100);
                                                    }
                                                    ?>
                                                    <td class="text-center">
                                                        <?php if ($reams !== null): ?>
                                                            <div class="stock-indicator <?= $class ?>">
                                                                <div class="stock-value"><?= number_format($reams, 1) ?></div>
                                                                <div class="stock-bar">
                                                                    <div class="bar-fill" style="width: <?= $percentage ?>%"></div>
                                                                </div>
                                                                <div class="stock-label">reams</div>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="na">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!--  Stock Card -->
            <div class="out-card" onclick="showLowStockItems()" style="cursor: pointer;">
                <div class="card-header">
                    <div>
                        <p>Low Stock Items</p>
                        <h3><?= $low_stock ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
            </div>

            <div id="lowStockModal" class="modal">
                <div class="modal-content">
                    <h3><i class="fas fa-exclamation-triangle"></i> Low Stock Items<span class="close" onclick="closeLowStockModal()">&times;</span></h3>
                    <div id="lowStockItems"></div>
                </div>
            </div>
        </div>

        <!-- Tables Section -->
        <div class="tables-section">
            <!-- Recent Deliveries Table -->
            <div class="table-card">
                <h3><i class="fas fa-truck"></i> Recent Deliveries</h3>
                <div class="recent-tables">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Reams</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_deliveries->fetch_assoc()): ?>
                                <tr class="clickable-row" data-id="<?= $row['product_id'] ?>">
                                    <td><?= date("M j, Y", strtotime($row['delivery_date'])) ?></td>
                                    <td><?= "{$row['product_type']} - {$row['product_group']} - {$row['product_name']}" ?></td>
                                    <td><?= number_format($row['delivered_reams'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="delivery.php" class="view-all">View All Deliveries →</a>
            </div>

            <!-- Recent Usage Table -->
            <div class="table-card">
                <h3><i class="fas fa-file-alt"></i> Recent Usage</h3>
                <div class="recent-tables">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Sheets Used</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_usage->fetch_assoc()): ?>
                                <tr class="clickable-row" data-id="<?= $row['product_id'] ?>">
                                    <td><?= date("M j, Y", strtotime($row['log_date'])) ?></td>
                                    <td><?= "{$row['product_type']} - {$row['product_group']} - {$row['product_name']}" ?></td>
                                    <td><?= number_format($row['used_sheets'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <a href="products.php" class="view-all">View All Usage →</a>
            </div>
        </div>
    </div>

    <!-- Product Modal -->
    <div id="productModal">
        <div id="productModalBody"></div>
    </div>

    <script>
        function goToLastProductPage() {
            const last = localStorage.getItem('lastProductPage');
            if (last) {
                window.location.href = last;
            } else {
                window.location.href = 'papers.php'; // fallback
            }
        }
        
        function showLowStockItems() {
            const modal = document.getElementById('lowStockModal');
            const content = document.getElementById('lowStockItems');

            modal.style.display = "flex"; // Changed to flex for centering

            fetch('get_low_stock.php')
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                    // Add status colors if not already in PHP response
                    document.querySelectorAll("#lowStockItems td:nth-child(4)").forEach(td => {
                        if (td.textContent.includes("Critical")) {
                            td.style.color = "#e74c3c";
                            td.style.fontWeight = "600";
                        } else if (td.textContent.includes("Low")) {
                            td.style.color = "#f39c12";
                            td.style.fontWeight = "600";
                        }
                    });
                })
                .catch(error => {
                    content.innerHTML = `
                <div class="loading-message" style="color:#e74c3c">
                    <i class="fas fa-exclamation-circle"></i> Error: ${error.message}
                </div>
            `;
                });
        }

        function closeLowStockModal() {
            document.getElementById('lowStockModal').style.display = "none";
        }

        // Toggle stock tables
        function toggleStockTable(id) {
            const container = document.getElementById(`table-${id}`);
            const icon = container.parentElement.querySelector('.toggle-icon');

            if (container.style.display === 'block') {
                container.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                container.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        // Initialize all as collapsed
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.stock-table-container').forEach(container => {
                container.style.display = 'none';
            });
        });

        // Clickable rows for product details
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.clickable-row').forEach(row => {
                row.addEventListener('click', function() {
                    const productId = this.dataset.id;
                    if (!productId) return;

                    fetch(`product_info.php?id=${productId}`)
                        .then(res => {
                            if (!res.ok) throw new Error("Failed to fetch");
                            return res.text();
                        })
                        .then(html => {
                            document.getElementById('productModalBody').innerHTML = html;
                            document.getElementById('productModal').style.display = 'flex';
                        })
                        .catch(err => {
                            document.getElementById('productModalBody').innerHTML = `
                <p style="color:red;">Error loading product info: ${err.message}</p>
                <p>Requested ID: ${productId}</p>
                <p>URL: product_info.php?id=${productId}</p>
              `;
                            document.getElementById('productModal').style.display = 'flex';
                        });
                });
            });
        });

        // Close modal
        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
            document.getElementById('productModalBody').innerHTML = '';
        }

        // Scroll position persistence
        const scrollKey = `scroll-position-/dashboard.php`;
        window.addEventListener('DOMContentLoaded', () => {
            const scrollY = sessionStorage.getItem(scrollKey);
            if (scrollY !== null) {
                window.scrollTo(0, parseInt(scrollY));
            }
        });
        window.addEventListener('scroll', () => {
            sessionStorage.setItem(scrollKey, window.scrollY);
        });

        document.querySelector('.modal').addEventListener('click', () => {
            document.getElementById('lowStockModal').style.display = 'none';
        });
    </script>
</body>

</html>