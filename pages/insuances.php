<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Handle form submission: add a usage record (issuance)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $description = trim($_POST['description']);
    $issued_by = $_SESSION['user_id'];

    if ($item_name !== '') {
        $stmt = $mysqli->prepare("INSERT INTO insuances (item_name, description, issued_by, date_issued) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $item_name, $description, $issued_by);
        if ($stmt->execute()) {
            header("Location: insuances.php?msg=Insuance+added+successfully");
            exit;
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to add insuance.</div>';
        }
    }
}

// Fetch current stock = total delivered - total used
$stock_result = $mysqli->query("
    SELECT
        ins.id AS item_id,
        ins.item_name AS insuance_name,
        ins.description,
        COALESCE(d.unit, '-') AS unit,
        COALESCE(SUM(d.delivered_quantity), 0) AS delivered_quantity,
        (
            SELECT COALESCE(SUM(u.quantity_used), 0)
            FROM insuance_usages u
            WHERE u.item_id = ins.id
        ) AS used_quantity,
        COALESCE(SUM(d.delivered_quantity), 0) - (
            SELECT COALESCE(SUM(u.quantity_used), 0)
            FROM insuance_usages u
            WHERE u.item_id = ins.id
        ) AS current_stock,
        (
            SELECT idl.amount_per_unit
            FROM insuance_delivery_logs idl
            WHERE idl.insuance_name = ins.item_name
            ORDER BY delivery_date DESC, id DESC
            LIMIT 1
        ) AS latest_amount,
        (
            SELECT MAX(u.date_issued)
            FROM insuance_usages u
            WHERE u.item_id = ins.id
        ) AS latest_used_date,
        (
            SELECT u.used_by_name
            FROM insuance_usages u
            WHERE u.item_id = ins.id AND u.used_by_name IS NOT NULL AND u.used_by_name != ''
            ORDER BY u.date_issued DESC, u.id DESC
            LIMIT 1
        ) AS latest_used_to,
        (
            SELECT usr.username
            FROM insuance_usages u
            LEFT JOIN users usr ON u.issued_by = usr.id
            WHERE u.item_id = ins.id
            ORDER BY u.date_issued DESC, u.id DESC
            LIMIT 1
        ) AS latest_issued_by
    FROM insuances ins
    LEFT JOIN insuance_delivery_logs d ON d.insuance_name = ins.item_name
    GROUP BY ins.id
");

$insuance_stock = $stock_result->fetch_all(MYSQLI_ASSOC);

// Count totals
$total_insuances = count($insuance_stock);
$out_of_stock = count(array_filter($insuance_stock, fn($i) => $i['current_stock'] <= 0));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Consumables Management</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            --success: #42b72a;
            --danger: #ff4d4f;
            --warning: #faad14;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poppins", sans-serif;
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

        .nav-menu li a:hover {
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
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

        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        /* Empty State */
        .empty-message {
            padding: 30px;
            text-align: center;
            color: var(--gray);
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
        }

        /* Forms */
        .form-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .form-card h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            color: var(--dark);
        }

        .form-card h3 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .form-card button {
            margin-top: 15px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--gray);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: var(--secondary);
        }

        .btn i {
            margin-right: 8px;
        }

        /* Tables */
        .table-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            overflow: scroll;
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
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
            font-size: 14px;
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

        .clickable-row {
            cursor: pointer;
        }

        .action-cell a {
            color: var(--gray);
            margin-right: 10px;
            transition: color 0.3s;
            z-index: 1000;
        }

        .action-cell a:hover {
            color: var(--primary);
        }

        /* Category headers */
        .category-header {
            background-color: var(--light-gray);
            font-weight: 600;
        }

        .subcategory-header {
            background-color: rgba(233, 236, 239, 0.5);
            font-style: italic;
        }

        /* Stock toggle */
        .stock-toggle {
            display: inline-flex;
            align-items: center;
            background: var(--light-gray);
            border-radius: 20px;
            padding: 2px;
            margin-left: 10px;
        }

        .stock-toggle select {
            border: none;
            background: transparent;
            padding: 4px 8px;
            font-size: 13px;
            cursor: pointer;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .modal-header .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
        }

        /* Responsive */
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

            .product-content {
                font-size: 13px;
            }

            .product-content th {
                font-size: 13px;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-info {
                margin-top: 10px;
            }
        }

        .collapsible-header {
            cursor: pointer;
            padding: 10px;
            background: #f2f2f2;
            border: 1px solid #ccc;
            margin-top: 10px;
            font-weight: bold;
        }

        .collapsible-header i {
            margin-right: 8px;
            transition: transform 0.2s;
        }

        .product-content {
            padding: 10px;
            overflow: scroll;
        }

        .table-card table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-card table td,
        .table-card table th {
            padding: 8px;
            border: 1px solid #ddd;
        }

        .nav-menu li.active>a {
            background-color: var(--light-gray);
        }

        .submenu {
            font-size: 90%;
            list-style-type: none;
            margin-left: 30px;
            border-left: 2px solid #1c1c1c1a;
        }

        .submenu li a {
            padding-left: 30px;
        }

        .submenu li a.activate {
            font-weight: 600;
            background-color: #1c1c1c10;
        }


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

        :root {
            --animate-duration: 300ms;
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

        /* Compact product info styles */
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
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }

        .info-item-compact span {
            font-size: 0.95rem;
        }

        /* Stock summary compact */
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

        /* Section headers */
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

        /* Compact tables */
        .compact-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .compact-table th {
            background: #f5f5f5;
            padding: 0.5rem;
            text-align: left;
            font-weight: 500;
        }

        .compact-table td {
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .compact-table tr:last-child td {
            border-bottom: none;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(3px);
            z-index: 999;
        }

        /* Empty state */
        .empty-state {
            padding: 2rem;
            text-align: center;
            color: var(--gray);
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .container {
            overflow: scroll;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .floating-window {
                width: 90%;
            }

            .product-info-compact {
                grid-template-columns: 1fr 1fr;
            }

            .stock-summary-compact {
                grid-template-columns: 1fr;
            }

      .sidebar {
        padding-top: 30px;
        border-radius: 20px;
      }

      .submenu {
        width: 100%;
        font-size: 70%;
        list-style-type: none;
        margin-left: 0;
        border: none;
        position: absolute;
        display: flex;
        height: 20px;
        top: 0;
        left: 23%;
      }

      .submenu li a {
        padding-left: 0;
        height: 1px;
      }

      .submenu li a.activate {
        font-weight: 600;
        background-color: #1c1c1c10;
      }
        }
    </style>
</head>

<body>
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $isProductPage = in_array($currentPage, ['papers.php', 'insuances.php']);
    ?>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li class="<?= $isProductPage ? 'active' : '' ?>">
                    <a href="papers.php">
                        <i class="fas fa-boxes"></i> <span>Products</span>
                    </a>
                    <ul class="submenu">
                        <li><a href="papers.php" class="<?= $currentPage == 'papers.php' ? 'activate' : '' ?>">Papers</a></li>
                        <li><a href="insuances.php" class="<?= $currentPage == 'insuances.php' ? 'activate' : '' ?>">Consumables</a></li>
                    </ul>
                </li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    <div class="main-content">
        <header class="header">
            <h1>Consumables Management (BETA)</h1>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
                    <small><?= $_SESSION['role'] ?></small>
                </div>
            </div>
        </header>

        <?php if (!empty($message)) echo $message; ?>
        <?php if (isset($_GET['msg'])): ?>
            <div id="flash-message" class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p>Total Consumables</p>
                        <h3><?= $total_insuances ?></h3>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p>Out of Stock</p>
                        <h3><?= $out_of_stock ?></h3>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Insuance Form -->
        <div class="form-card">
            <h3><i class="fas fa-plus-circle"></i>Add New Consumable</h3>
            <p style="font-size: 80%; color: lightgray; margin-bottom: 10px;"><strong>DO NOT</strong> USE <strong>DESCRIPTION</strong> TO SPECIFY THE TYPE OF ITEM. *</p>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="item_name">Item Name</label>
                        <input type="text" id="item_name" name="item_name" required placeholder="e.g., Staples - 10.65mm">
                    </div>
                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <input type="text" id="description" name="description">
                    </div>
                </div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Add Insuance</button>
            </form>
        </div>

        <div class="form-card">
            <div class="form-group">
                <h3><i class="fas fa-search"></i>Search Consumables</h3>
                <input type="text" id="searchInput" placeholder="Search item name or description">
            </div>
        </div>

        <!-- Insuances Table -->
        <div class="table-card">
            <h3><i class="fas fa-list"></i>Consumables Inventory</h3>
            <div class="product-content">
                <table id="insuanceTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Used</th>
                            <th>Current Stock</th>
                            <th>Latest Amount (₱)</th>
                            <th>Last Issued</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Issued To</th>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($insuance_stock)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; color:gray;">No insuances found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($insuance_stock as $item): ?>
                                <tr onclick="openInsuanceModal(<?= intval($item['item_id']) ?>)" class="clickable-row">
                                    <td><?= htmlspecialchars($item['insuance_name']) ?></td>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td><?= floatval($item['used_quantity']) ?></td>
                                    <td><?= floatval($item['current_stock']) ?></td>
                                    <td>₱<?= number_format(floatval($item['latest_amount']), 2) ?></td>
                                    <td><?= $item['latest_used_date'] ? date('M j, Y', strtotime($item['latest_used_date'])) : '-' ?></td>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <td><?= htmlspecialchars($item['latest_used_to'] ?? '-') ?></td>
                                        <td class="action-cell">
                                            <a href="edit_insuance.php?id=<?= $item['item_id'] ?>" class="fas fa-edit"></a>
                                            <a href="delete_insuance.php?id=<?= $item['item_id'] ?>" class="fas fa-trash" onclick="return confirm('Are you sure you want to delete this item?');"></a>
                                        </td>
                                    <?php endif; ?>                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Usage Modal -->
    <div id="insuanceModal" class="overlay animate__animated animate__fadeIn" style="display: none;">
        <!-- Floating Window -->
        <div id="insuanceModalBody" class="floating-window">
            <div class="window-header">
                <div class="window-title">
                    <i class="fas fa-clipboard-list"></i>
                    Insuance Information
                </div>
                <button class="close-btn" onclick="closeInsuanceModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="window-content">
                <!-- Usage Form -->
                <form id="usageForm" method="post" action="add_insuance_usage.php">
                    <input type="hidden" name="item_id" id="modal_item_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="quantity_used">Quantity Used</label>
                            <input type="number" name="quantity_used" id="quantity_used" placeholder="e.g. 1, 2, 3" min="0" step="0" required>
                        </div>
                        <div class="form-group">
                            <label for="used_by">Issued To</label>
                            <input type="text" name="used_by" id="used_by" placeholder="e.g. Tolits" required>
                        </div>
                        <div class="form-group">
                            <label for="date_issued">Date Issued</label>
                            <input type="date" name="date_issued" id="date_issued" 
                                max="<?= date('Y-m-d') ?>" 
                                value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="form-group">
                            <label for="description">Usage Notes</label>
                            <input name="description" id="description" placeholder="Optional notes...">
                        </div>
                    </div>
                    <button type="submit" class="btn" style="margin: 20px 0 20px 0;">
                        <i class="fas fa-save"></i> Submit Usage
                    </button>
                </form>

                <!-- Stock Summary -->
                <div class="stock-summary-compact">
                    <div class="stock-card-compact">
                        <h4>Total Delivered</h4>
                        <div class="stock-value-compact" id="delivered_quantity"></div>
                        <div class="stock-unit-compact">(items)</div>
                    </div>

                    <div class="stock-card-compact">
                        <h4>Total Used</h4>
                        <div class="stock-value-compact" id="used_quantity"></div>
                        <div class="stock-unit-compact">(items)</div>
                    </div>

                    <div class="stock-card-compact">
                        <h4>Current Stock</h4>
                        <div class="stock-value-compact" id="current_stock"></div>
                        <div class="stock-unit-compact">(items)</div>
                    </div>
                </div>

                <!-- Usage History Section -->
                <div class="section-header">
                    <i class="fas fa-history"></i>
                    Usage History
                </div>
                <div class="container" id="usage_history_container">
                    <!-- JS will inject a table or empty message here -->
                </div>

                <!-- Delivery History Section -->
                <div class="section-header">
                    <i class="fas fa-truck"></i>
                    Delivery History
                </div>
                <div class="container" id="delivery_history_container">
                    <!-- JS will inject a table or empty message here -->
                </div>
            </div>
        </div>
    </div>


    <script>
        const scrollKey = `scroll-position-/insuances.php`;
        window.addEventListener('DOMContentLoaded', () => {
            const scrollY = sessionStorage.getItem(scrollKey);
            if (scrollY !== null) {
                window.scrollTo(0, parseInt(scrollY));
            }
        });
        window.addEventListener('scroll', () => {
            sessionStorage.setItem(scrollKey, window.scrollY);
        });
        
        function formatDate(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function openInsuanceModal(itemId) {
            fetch(`get_insuance_details.php?item_id=${itemId}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('modal_item_id').value = itemId;
                    document.getElementById('delivered_quantity').textContent = data.delivered_quantity;
                    document.getElementById('used_quantity').textContent = data.used_quantity;
                    document.getElementById('current_stock').textContent = data.current_stock;

                    // Usage history
                    const usageContainer = document.getElementById('usage_history_container');
                    if (data.usage_history.length > 0) {
                        let html = '<table class="compact-table"><thead><tr><th>Date</th><th>Issued By</th><th>Issued To</th><th>Quantity</th><th>Notes</th></tr></thead><tbody>';
                        data.usage_history.forEach(row => {
                            html += `<tr>
                                <td>${formatDate(row.date_issued)}</td>
                                <td>${row.issued_by ?? 'N/A'}</td>
                                <td>${row.issued_to || '-'}</td>
                                <td>${parseFloat(row.quantity_used).toFixed(2)}</td>
                                <td>${row.description ?? '-'}</td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                        usageContainer.innerHTML = html;
                    } else {
                        usageContainer.innerHTML = `<div class="empty-state"><p><i class="fas fa-info-circle"></i> No usage history found</p></div>`;
                    }

                    // Delivery history
                    const deliveryContainer = document.getElementById('delivery_history_container');
                    if (data.delivery_history.length > 0) {
                        let html = 
                        '<table class="compact-table"><thead><tr><th>Date</th><th>Supplier</th><th>Quantity</th><th>Unit</th><th>Price/Unit</th></tr></thead><tbody>';
                        data.delivery_history.forEach(row => {
                            html += `<tr>
                                <td>${formatDate(row.delivery_date)}</td>
                                <td>${row.supplier_name ?? '-'}</td>
                                <td>${parseFloat(row.delivered_quantity).toFixed(2)}</td>
                                <td>${row.unit ?? '-'}</td>
                                <td>₱${parseFloat(row.amount_per_unit).toFixed(2)}</td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                        deliveryContainer.innerHTML = html;
                    } else {
                        deliveryContainer.innerHTML = `<div class="empty-state"><p><i class="fas fa-info-circle"></i> No delivery history found</p></div>`;
                    }

                    // Show modal
                    document.getElementById('insuanceModal').style.display = 'block';
                });
        }

        function closeInsuanceModal() {
            document.getElementById('insuanceModal').style.display = 'none';
        }

        document.cookie = "lastProductPage=" + window.location.pathname + "; path=/";

        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('searchInput').addEventListener('keyup', function () {
                const filter = this.value.toLowerCase();
                const rows = document.querySelectorAll('#insuanceTable tbody tr');

                rows.forEach(row => {
                    const itemName = row.cells[0].textContent.toLowerCase();
                    const description = row.cells[1].textContent.toLowerCase();
                    const match = itemName.includes(filter) || description.includes(filter);
                    row.style.display = match ? '' : 'none';
                });
            });

            const flash = document.getElementById('flash-message');
            if (flash) {
            setTimeout(() => {
                flash.style.transition = 'opacity 0.5s ease';
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 500);
            }, 3000);
            }
        });
    </script>
</body>

</html>