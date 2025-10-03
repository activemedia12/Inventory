<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Function to fetch data from database
function fetchData($inventory, $query)
{
    $result = $inventory->query($query);
    return $result->fetch_assoc()['total'] ?? 0;
}

// Quick Stats
$total_products = fetchData($inventory, "SELECT COUNT(*) AS total FROM products");
$deliveries_this_week = fetchData($inventory, "
  SELECT COUNT(*) AS total FROM delivery_logs
  WHERE YEARWEEK(delivery_date, 1) = YEARWEEK(CURDATE(), 1)
");
$out_of_stock = fetchData($inventory, "
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
$recent_deliveries = $inventory->query("
  SELECT d.product_id, d.delivery_date, p.product_type, p.product_group, p.product_name, d.delivered_reams
  FROM delivery_logs d
  JOIN products p ON d.product_id = p.id
  ORDER BY d.delivery_date DESC
  LIMIT 5
");

$recent_usage = $inventory->query("
  SELECT u.product_id, u.log_date, p.product_type, p.product_group, p.product_name, u.used_sheets
  FROM usage_logs u
  JOIN products p ON u.product_id = p.id
  ORDER BY u.log_date DESC
  LIMIT 5
");

// Stock Summary Data
$stock_data = $inventory->query("
  SELECT 
    p.product_type, p.product_group, p.product_name,
    ((SELECT IFNULL(SUM(delivered_reams), 0) FROM delivery_logs WHERE product_id = p.id) * 500 -
    (SELECT IFNULL(SUM(used_sheets), 0) FROM usage_logs WHERE product_id = p.id)) AS available_sheets
  FROM products p
  ORDER BY p.product_type, p.product_name, p.product_group
");

$low_stock = fetchData($inventory, "
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

$sql = "SELECT jo.*, u.username 
        FROM job_orders jo
        LEFT JOIN users u ON u.id = jo.created_by
        ORDER BY jo.created_at DESC 
        LIMIT 10";
$result = $inventory->query($sql);

$recent_orders = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

$greetings = [
    "Welcome back",
    "Good to see you again",
    "Glad you're here",
    "Hoping you have a great day",
    "Nice to have you back",
    "Your presence makes today better",
    "Let’s make today productive",
    "Ready to achieve great things today",
    "Another day, another opportunity to excel",
    "Let’s make progress together",
    "Your effort makes a difference",
    "Small steps today, big results tomorrow",
    "Every challenge is a chance to grow",
    "Let’s turn ideas into action",
    "Teamwork makes the dream work",
    "Your contribution matters",
    "Stay positive, stay focused",
    "Let’s make this day count",
    "Consistency builds success",
    "Strive for progress, not perfection"
];



// pick greeting based on day of the year
$dayOfYear = date("z"); // 0 to 365
$greeting = $greetings[$dayOfYear % count($greetings)];

// format username
$username = ucfirst(strtolower(htmlspecialchars($_SESSION['username'])));
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
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
            min-width: 500px;
            max-height: 500px;
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            overflow: auto;
            margin-right: 20px;
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
        .recent-tables td {
            cursor: pointer;
            font-size: 14px;
        }

        .table-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            width: 100%;
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

        @media (max-width: 1200px) {
            .flex {
                flex-direction: column;
                gap: 20px;
            }
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
            .welcome {
                font-size: 200% !important;
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

        #jobModal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(3px);
            z-index: 999;
            display: none;
        }

        .floating-window {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 1000px;
            max-height: 80vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: centerZoomIn 0.3s ease-in-out forwards;
        }

        .window-header {
            padding: 0.5rem 1.5rem;
            background: var(--primary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .window-title {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .window-title i {
            margin-right: 0.8rem;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        .window-content {
            padding: 1.5rem;
            overflow-y: auto;
            flex-grow: 1;
        }

        /* Product Info Compact Grid */
        .product-info-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .info-item-compact {
            margin-bottom: 0.5rem;
        }

        .info-item-compact strong {
            display: block;
            color: var(--gray);
            font-size: 100%;
            margin-bottom: 0.2rem;
        }

        .info-item-compact span {
            font-size: 85%;
        }

        /* Stock Summary Cards */
        .stock-summary-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stock-card-compact {
            padding: 0.8rem;
            border-radius: 8px;
            background: rgba(67, 97, 238, 0.05);
            text-align: center;
        }

        .stock-card-compact h4 {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .stock-value-compact {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .stock-unit-compact {
            color: var(--gray);
            font-size: 0.75rem;
        }

        /* Section Headers */
        .section-header {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary);
            margin: 1.5rem 0 0.5rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .section-header i {
            margin-right: 0.5rem;
        }

        /* Special Instructions */
        .special-instructions {
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 8px;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            margin-top: 1rem;
        }

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-active {
            background: var(--success);
        }

        .status-completed {
            background: var(--primary);
        }

        .status-pending {
            background: var(--danger);
        }

        .status-toggle-form {
            display: flex;
        }

        .status-select:focus {
            outline: none;
        }

        .status-select {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            background: rgba(67, 97, 238, 0.1);
            color: white;
            border: 1px solid var(--primary);
            display: inline-flex;
            text-align: center;
            gap: 0.5rem;
            transition: all 0.2s;
            margin: 6px 6px;
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border-color: #ff9800;
        }

        .status-unpaid {
            background: rgba(255, 0, 0, 0.1);
            color: #ff0000ff;
            border-color: #ff0000ff;
        }

        .status-for_delivery {
            background: rgba(0, 38, 255, 0.1);
            color: var(--primary);
            border-color: var(--primary);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-color: #28a745;
        }

        .btn-status {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            background: rgba(67, 238, 76, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            margin: 6px 6px;
            gap: 6px;
        }

        .btn-edit,
        .btn-delete {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            margin: 6px 6px;
        }

        .btn-edit {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-delete {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }

        .btn-status:hover {
            background: rgba(40, 167, 69, 0.2);
        }

        .btn-status.pending:hover {
            background: rgba(255, 152, 0, 0.2);
        }

        .btn-status.completed:hover {
            background: rgba(40, 167, 69, 0.2);
        }

        .btn-edit:hover {
            background: rgba(67, 97, 238, 0.2);
        }

        .btn-delete:hover {
            background: rgba(244, 67, 54, 0.2);
        }

        /* Empty State */
        .empty-state {
            padding: 1rem;
            text-align: center;
            color: var(--gray);
            background: #f9f9f9;
            border-radius: 8px;
        }

        .empty-state i {
            margin-right: 0.5rem;
        }

        /* Form Elements */
        .status-form {
            display: inline;
        }

        .flex {
            display: flex;
            flex-wrap: nowrap;
            margin-bottom: 20px;
        }

        .welcome{
            font-style: italic; 
            font-size: 300%; 
            font-weight:600; 
            margin: 50px;
            color: #1c1c1cca; /* base color so text is always visible */
            position: relative;
            display: inline-block;
            overflow: hidden;
            padding: 0 20px;
        }

        .welcome::before {
            content: attr(data-text); /* duplicate the text */
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                120deg,
                rgba(255, 255, 255, 0) 40%,
                rgba(255, 255, 255, 0.8) 50%,
                rgba(255, 255, 255, 0) 60%
            );
            background-size: 200% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shine 5s linear infinite;
            padding: 0 20px;
        }
        @keyframes shine {
            0% { background-position: 100%; }
            100% { background-position: -100%; }
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
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
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

        <div class="welcome animate__animated animate__fadeInUp" 
            data-text="<?= $greeting ?>, <?= $username ?>!">
            <?= $greeting ?>, <?= $username ?>!
        </div>


        <div class="flex">
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
            </div>

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
            </div>
        </div>

        <div class="table-card">
            <h3><i class="fas fa-history"></i> Recent Job Orders</h3>
            <?php if (empty($recent_orders)): ?>
                <div class="empty-message">
                    <p>No recent job orders</p>
                </div>
            <?php else: ?>
                <div class="recent-tables table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr class="clickable-row"
                                    data-order='<?= htmlspecialchars(json_encode($order), ENT_QUOTES, "UTF-8") ?>'
                                    data-role="<?= htmlspecialchars($_SESSION['role']) ?>">
                                    <td><?= htmlspecialchars($order['client_name']) ?></td>
                                    <td><?= htmlspecialchars($order['project_name']) ?></td>
                                    <td><span class="badge bg-info"><?= ucfirst($order['status']) ?></span></td>
                                    <td><?= htmlspecialchars($order['username'] ?? 'Unknown') ?></td>
                                    <td><?= date("M d, Y g:i A", strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Modal -->
    <div id="productModal">
        <div id="productModalBody"></div>
    </div>

    <div id="jobModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div id="modal-body">
            </div>
        </div>
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
            document.getElementById('jobModal').style.display = 'none';
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
        document.querySelectorAll('.clickable-row').forEach(row => {
            row.addEventListener('click', function() {
                const orderData = JSON.parse(this.dataset.order);
                const userRole = this.dataset.role;
                console.log(userRole);
                openModal(orderData, userRole);
            });
        });

        function openModal(order, userRole) {
            function applyStatusColor(selectEl) {
                const status = selectEl.value;
                selectEl.classList.remove('status-pending', 'status-unpaid', 'status-for_delivery', 'status-completed');
                selectEl.classList.add(`status-${status}`);
            }

            // Run for all dropdowns (on initial load or after modal opens)
            document.querySelectorAll('.status-select').forEach(select => {
                applyStatusColor(select);
                select.addEventListener('change', () => applyStatusColor(select));
            });

            const modal = document.getElementById('jobModal');
            const modalBody = document.getElementById('modal-body');

            let html = `
    <div class="floating-window">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-file-invoice"></i>
          Job Order ${order.id}
        </div>
        <button class="close-btn" onclick="closeModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="window-content">
        <!-- Client Information Section -->
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Company</strong>
            <span>${order.client_name || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Tax Payer Name</strong>
            <span>${order.taxpayer_name || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>TIN</strong>
            <span>${order.tin || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Tax Type</strong>
            <span>${order.tax_type || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>RDO Code</strong>
            <span>${order.rdo_code || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Client Address</strong>
            <span>${order.client_address || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Person</strong>
            <span>${order.contact_person || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Number</strong>
            <span>${order.contact_number || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Client By</strong>
            <span>${order.client_by || 'None'}</span>
          </div>
        </div>

        <!-- Project Details Section -->
        <div class="section-header">
          <i class="fas fa-clipboard-list"></i>
          Project Details
        </div>
        <div class="stock-summary-compact">
          <div class="stock-card-compact">
            <h4>Order Quantity</h4>
            <div class="stock-value-compact">${order.quantity}</div>
            <div class="stock-unit-compact">pieces</div>
          </div>
          <div class="stock-card-compact">
            <h4>Sets per Bind</h4>
            <div class="stock-value-compact">${order.number_of_sets}</div>
            <div class="stock-unit-compact">sets</div>
          </div>
          <div class="stock-card-compact">
            <h4>Copies per Set</h4>
            <div class="stock-value-compact">${order.copies_per_set}</div>
            <div class="stock-unit-compact">copies</div>
          </div>
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Project Name</strong>
            <span>${order.project_name || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Serial Range</strong>
            <span>${order.serial_range}</span>
          </div>
          <div class="info-item-compact">
            <strong>OCN Number</strong>
            <span>${order.ocn_number || 'Pending'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Date Issued</strong>
            <span>
              ${order.date_issued
                ? new Date(order.date_issued).toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                  })
                : 'Pending'}
            </span>
          </div>
        </div>

        <!-- Specifications Section -->
        <div class="section-header">
          <i class="fas fa-tools"></i>
          Specifications
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Paper Size</strong>
            <span>${order.paper_size === 'custom' ? order.custom_paper_size : order.paper_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Paper Type</strong>
            <span>${order.paper_type}</span>
          </div>
          <div class="info-item-compact">
            <strong>Cut Size</strong>
            <span>${order.product_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Binding</strong>
            <span>${order.binding_type === 'Custom' ? order.custom_binding : order.binding_type}</span>
          </div>
          <div class="info-item-compact">
            <strong>Color Sequence</strong>
            <span>${order.paper_sequence}</span>
          </div>
        </div>

        <!-- Special Instructions -->
        <div class="section-header">
          <i class="fas fa-comment-alt"></i>
          Special Instructions
        </div>
        <div class="special-instructions">
          ${order.special_instructions ? order.special_instructions.replace(/\n/g, '<br>') : '<div class="empty-state"><p><i class="fas fa-info-circle"></i> No special instructions provided</p></div>'}
        </div>
  `;

            if (userRole === 'admin') {
                const statuses = ['pending', 'unpaid', 'for_delivery', 'completed'];
                const currentStatus = order.status;
                const options = statuses.map(status => {
                    const selected = status === currentStatus ? 'selected' : '';
                    const label = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()); // Capitalize
                    return `<option value="${status}" ${selected}>${label}</option>`;
                }).join('');

                html += `
          <div class="section-header">
            <i class="fas fa-cog"></i>
            Actions
          </div>
          <div class="action-buttons">
            <form class="status-toggle-form" data-job-id="${order.id}">
              <select name="new_status" class="status-select">
                ${options}
              </select>
              <button type="submit" class="btn-status">
                <i class="fas fa-sync-alt"></i> Update Status
              </button>
            </form>
            <a href="edit_job.php?id=${order.id}" class="btn-edit">
              <i class="fas fa-edit"></i> Edit
            </a>
            <a href="delete_job.php?id=${order.id}" class="btn-delete" onclick="return confirm('Delete this job order?')">
              <i class="fas fa-trash-alt"></i> Delete
            </a>
          </div>
        `;
            }

            html += `
            </div>
          </div>
        `;

            modalBody.innerHTML = html;
            modal.style.display = 'flex';

            // Attach the event listener to the new form
            const statusForm = modalBody.querySelector('.status-toggle-form');
            if (statusForm) {
                statusForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const jobId = this.dataset.jobId;
                    const newStatus = this.querySelector('select[name="new_status"]').value;

                    fetch('update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `job_id=${encodeURIComponent(jobId)}&new_status=${encodeURIComponent(newStatus)}`
                        })
                        .then(response => response.text())
                        .then(data => {
                            console.log(data);
                            location.reload();
                        })
                        .catch(err => {
                            alert('Status update failed.');
                            console.error(err);
                        });
                });
            }

            // ✅ Apply status color now that the select is in the DOM
            const select = modalBody.querySelector('.status-select');
            if (select) {
                applyStatusColor(select); // Initial color
                select.addEventListener('change', () => applyStatusColor(select)); // Update on change
            }
        }
    </script>
</body>

</html>