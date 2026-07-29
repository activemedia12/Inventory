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
        $stmt = $inventory->prepare("INSERT INTO insuances (item_name, description, issued_by, date_issued) VALUES (?, ?, ?, NOW())");
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
$stock_result = $inventory->query("
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
    <title>Consumables Management - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* ==========================================================
           Consumables Management — reskinned to match the shared
           minimal-SaaS design tokens used across dashboard.php
           ========================================================== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbced3;
            border-radius: 8px;
        }

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
            --animate-duration: 300ms;
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
            display: flex;
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            border-right: 1px solid var(--light-gray);
            padding: 20px 0;
            overflow-y: auto;
        }

        .brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .brand img {
            height: 100px;
            width: auto;
            padding-left: 0;
            transform: none;
        }

        .nav-menu {
            list-style: none;
            padding: 0 12px;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .nav-menu li a:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        .nav-menu li.active>a,
        .nav-menu li a.active {
            background-color: var(--primary-bg);
            color: var(--secondary);
        }

        .nav-menu li a i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            color: var(--gray);
        }

        .nav-menu li.active>a i,
        .nav-menu li a:hover i {
            color: inherit;
        }

        .submenu {
            list-style: none;
            margin: 2px 0 6px 14px;
            padding-left: 14px;
            border-left: 2px solid var(--light-gray);
        }

        .submenu li a {
            padding: 7px 10px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--gray);
            border-radius: 6px;
        }

        .submenu li a:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        .submenu li a.activate {
            color: var(--secondary);
            font-weight: 600;
            background-color: var(--primary-bg);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 28px 32px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .user-details h4 {
            font-weight: 600;
            font-size: 14px;
        }

        .user-details small {
            color: var(--gray);
            font-size: 12px;
        }

        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-size: 13px;
            font-weight: 500;
        }

        .alert i {
            margin-right: 10px;
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success);
        }

        .alert-danger {
            background-color: var(--danger-bg);
            color: var(--danger);
        }

        .alert-warning {
            background-color: var(--warning-bg);
            color: var(--warning);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            margin-bottom: 20px;
            gap: 16px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            min-width: 0;
        }

        .stat-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .stat-card .card-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-bg);
            color: var(--primary);
            font-size: 16px;
            flex-shrink: 0;
        }

        .stat-card h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .stat-card p {
            color: var(--gray);
            font-size: 13px;
        }

        .stat-card .stat-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .stat-card .stat-period {
            font-size: 12px;
            color: var(--gray);
            margin-top: 4px;
        }

        /* Forms */
        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
        }

        .form-card h3 {
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-card h3 i {
            margin-right: 8px;
            color: var(--gray);
        }

        .form-note {
            font-size: 12px;
            color: var(--gray);
            margin: -10px 0 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            background: var(--card-bg);
            color: var(--dark);
            transition: border-color 0.15s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            background: var(--card-bg);
            color: var(--dark);
            transition: border-color 0.15s ease;
        }

        .search:focus {
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
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease;
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
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            width: 100%;
            margin-bottom: 20px;
            overflow: auto;
        }

        .table-card h3 {
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
        }

        .table-card h3 i {
            margin-right: 8px;
            color: var(--gray);
        }

        table {
            min-width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
        }

        th {
            font-weight: 600;
            color: var(--gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        tr td {
            transition: background-color 0.15s ease;
        }

        tr:hover td {
            background-color: var(--light);
        }

        .clickable-row {
            cursor: pointer;
        }

        .action-cell a {
            color: var(--gray);
            margin-right: 12px;
            transition: color 0.15s ease;
        }

        .action-cell a:hover {
            color: var(--primary);
        }

        .stock-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-pill.high {
            background: var(--success-bg);
            color: var(--success);
        }

        .stock-pill.mid {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .stock-pill.low {
            background: var(--danger-bg);
            color: var(--danger);
        }

        /* Modal + floating window */
        @keyframes centerZoomIn {
            0% {
                transform: translate(-50%, -50%) scale(0.97);
                opacity: 0;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(20, 23, 31, 0.35);
            backdrop-filter: blur(2px);
            z-index: 999;
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
            border-radius: 10px;
            box-shadow: 0 12px 32px rgba(20, 23, 31, 0.18);
            z-index: 1000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: centerZoomIn 0.18s ease-out forwards;
        }

        .window-header {
            padding: 14px 20px;
            background: var(--dark);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .window-title {
            display: flex;
            align-items: center;
            font-size: 15px;
            font-weight: 600;
        }

        .window-title i {
            margin-right: 10px;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
            padding: 6px;
            opacity: 0.85;
        }

        .close-btn:hover {
            opacity: 1;
        }

        .window-content {
            padding: 22px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .stock-summary-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .stock-card-compact {
            padding: 14px;
            border-radius: 8px;
            background: var(--light);
            text-align: center;
        }

        .stock-card-compact h4 {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .stock-value-compact {
            font-size: 18px;
            font-weight: 700;
        }

        .stock-unit-compact {
            color: var(--gray);
            font-size: 11px;
        }

        .section-header {
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            margin: 20px 0 14px;
        }

        .section-header i {
            margin-right: 8px;
            color: var(--gray);
        }

        .compact-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .compact-table th {
            background: var(--light);
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .compact-table td {
            padding: 8px 10px;
            border-bottom: 1px solid var(--light-gray);
        }

        .compact-table tr:last-child td {
            border-bottom: none;
        }

        .empty-state {
            padding: 16px;
            text-align: center;
            color: var(--gray);
            background: var(--light);
            border-radius: 8px;
            font-size: 13px;
        }

        .empty-state i {
            margin-right: 8px;
        }

        .container {
            overflow: auto;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .floating-window {
                width: 90%;
            }

            .stock-summary-compact {
                grid-template-columns: 1fr;
            }

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
                bottom: 12px;
                left: 50%;
                transform: translateX(-50%);
                padding: 6px;
                background-color: rgba(255, 255, 255, 0.85);
                backdrop-filter: blur(6px);
                box-shadow: 0 4px 16px rgba(20, 23, 31, 0.12);
                border-radius: 100px;
                touch-action: manipulation;
                z-index: 9999;
                flex-direction: row;
                border: 1px solid var(--light-gray);
                justify-content: center;
            }

            .sidebar .nav-menu {
                display: flex;
                flex-direction: row;
                padding: 0;
            }

            .sidebar img,
            .sidebar .brand,
            .sidebar .nav-menu li a span,
            .sidebar .submenu {
                display: none;
            }

            .sidebar .nav-menu li a {
                justify-content: center;
                padding: 12px;
            }

            .sidebar .nav-menu li a i {
                margin-right: 0;
            }

            .main-content {
                margin-left: 0;
                overflow: auto;
                margin-bottom: 90px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .user-info {
                margin-top: 4px;
            }

            .table-card {
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $isProductPage = in_array($currentPage, ['papers.php', 'insuances.php']);

    // Same thresholds used elsewhere: 0 is out of stock, under 10 units is low.
    function insuance_stock_class($qty)
    {
        if ($qty <= 0) return 'low';
        if ($qty < 10) return 'mid';
        return 'high';
    }
    ?>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
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
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div>
                <h1>Consumables Management</h1>
                <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
                    <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                    <small><?php echo $_SESSION['role']; ?></small>
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
                        <p class="stat-label">Total Consumables</p>
                        <h3><?= $total_insuances ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="stat-period">Tracked consumable items</div>
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p class="stat-label">Out of Stock</p>
                        <h3 style="<?= $out_of_stock > 0 ? 'color:var(--danger)' : '' ?>"><?= $out_of_stock ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-period"><?= $out_of_stock > 0 ? '⚠️ Needs restocking' : '✓ All items in stock' ?></div>
            </div>
        </div>

        <!-- Add Insuance Form -->
        <div class="form-card">
            <h3><i class="fas fa-plus-circle"></i> Add New Consumable</h3>
            <p class="form-note"><strong>Note:</strong> don't use the description field to specify the item type.</p>
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
                <button type="submit" class="btn" style="margin-top:16px;"><i class="fas fa-save"></i> Add Consumable</button>
            </form>
        </div>

        <div class="form-card">
            <h3><i class="fas fa-search"></i> Search Consumables</h3>
            <input class="search" type="text" id="searchInput" placeholder="Search item name or description">
        </div>

        <!-- Insuances Table -->
        <div class="table-card">
            <h3><i class="fas fa-list"></i> Consumables Inventory</h3>
            <div class="container">
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
                                <td colspan="8" style="text-align:center; color:var(--gray);">No consumables found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($insuance_stock as $item): ?>
                                <tr onclick="openInsuanceModal(<?= intval($item['item_id']) ?>)" class="clickable-row">
                                    <td><?= htmlspecialchars($item['insuance_name']) ?></td>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td><?= floatval($item['used_quantity']) ?></td>
                                    <td>
                                        <span class="stock-pill <?= insuance_stock_class(floatval($item['current_stock'])) ?>">
                                            <?= floatval($item['current_stock']) ?>
                                        </span>
                                    </td>
                                    <td>₱<?= number_format(floatval($item['latest_amount']), 2) ?></td>
                                    <td><?= $item['latest_used_date'] ? date('M j, Y', strtotime($item['latest_used_date'])) : '-' ?></td>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <td><?= htmlspecialchars($item['latest_used_to'] ?? '-') ?></td>
                                        <td class="action-cell">
                                            <a href="edit_insuance.php?id=<?= $item['item_id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="delete_insuance.php?id=<?= $item['item_id'] ?>" onclick="return confirm('Are you sure you want to delete this item?');" title="Delete"><i class="fas fa-trash"></i></a>
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
                    Consumable Information
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
                    <button type="submit" class="btn" style="margin: 20px 0;">
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

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').addEventListener('keyup', function() {
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