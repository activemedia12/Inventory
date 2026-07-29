<?php
// =============================
// 1. clients.php
// =============================
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}
require_once '../config/db.php';

$search = $_GET['search_client'] ?? '';
$clients = [];

if (!empty($search)) {
    $stmt = $inventory->prepare("SELECT * FROM clients WHERE client_name LIKE ? ORDER BY client_name ASC");
    $like = '%' . $search . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $inventory->query("SELECT * FROM clients ORDER BY client_name ASC");
}

while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}

// Fetch all saved clients
// $result = $inventory->query("SELECT * FROM clients ORDER BY created_at DESC");
// $clients = $result->fetch_all(inventory_ASSOC);
$provinces = [];
$result = $inventory->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($row = $result->fetch_assoc()) {
    $provinces[] = $row['province'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Client Information - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
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
            --primary-light: #eef1ff;
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
            background-color: var(--primary-light);
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
            background-color: var(--primary-light);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 28px 32px;
            overflow: auto;
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

        /* Alert */
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
            border: none;
            color: var(--success);
        }

        .alert-danger {
            background-color: var(--danger-bg);
            border: none;
            color: var(--danger);
        }

        .alert-warning {
            background-color: var(--warning-bg);
            border: none;
            color: var(--warning);
        }

        .alert-info {
            background-color: var(--info-bg);
            border: none;
            color: var(--info);
        }

        /* Add Client form card (collapsible) */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-toggle {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }

        .card-toggle:hover {
            background-color: var(--light);
        }

        .card-toggle h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-toggle h3 i.fa-user-plus {
            color: var(--gray);
        }

        .card-toggle .chevron {
            color: var(--gray);
            transition: transform 0.15s ease;
            font-size: 13px;
        }

        .card-body-inner {
            padding: 0 20px 20px;
            border-top: 1px solid var(--light-gray);
            padding-top: 20px;
        }

        .section-label {
            grid-column: 1 / -1;
            font-size: 12px;
            font-weight: 700;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 8px 0 -2px;
            padding-top: 14px;
            border-top: 1px solid var(--light-gray);
        }

        .section-label:first-child {
            padding-top: 0;
            border-top: none;
            margin-top: 0;
        }

        .section-label span {
            display: block;
            font-size: 12px;
            font-weight: 400;
            text-transform: none;
            letter-spacing: normal;
            color: var(--gray);
            margin-top: 3px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .vat-group {
            display: flex;
            flex-direction: column;
        }

        .vat-group>label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .vatlabels {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 4px 18px;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
        }

        .vatlabels label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--dark);
            font-weight: 400;
            cursor: pointer;
        }

        .vatlabels input[type="radio"] {
            accent-color: var(--primary);
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 4px;
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
        .form-group select,
        .form-group textarea,
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            background: var(--card-bg);
            color: var(--dark);
            transition: border-color 0.15s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus,
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease;
            text-decoration: none;
        }

        .btn:hover {
            background-color: var(--secondary);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--gray);
        }

        .btn-outline:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        .btn-edit {
            background-color: var(--primary-light);
            color: var(--secondary);
        }

        .btn-edit:hover {
            background-color: var(--primary);
            color: white;
        }

        .btn-delete {
            background-color: var(--danger-bg);
            color: var(--danger);
        }

        .btn-delete:hover {
            background-color: var(--danger);
            color: white;
        }

        fieldset {
            border: 0;
        }

        input::placeholder {
            color: var(--gray);
            opacity: 0.5;
        }

        /* Saved Clients card */
        .client-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--light-gray);
            padding: 16px 20px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i {
            color: var(--gray);
        }

        .card-body {
            padding: 16px 20px 20px;
        }

        .search {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: start;
        }

        .search input {
            min-height: 38px;
            width: 100%;
            max-width: 320px;
            margin: 0 0 14px;
            padding: 9px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            transition: border-color 0.15s ease;
        }

        .search input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .client-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .client-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 14px;
            transition: background-color 0.15s ease;
            background-color: var(--light);
            margin-bottom: 8px;
            border-radius: 6px;
            cursor: pointer;
        }

        .client-item:last-child {
            margin-bottom: 0;
        }

        .client-item:hover {
            background: var(--primary-light);
        }

        .client-name {
            font-weight: 600;
            font-size: 13px;
            color: var(--dark);
        }

        .client-item .btn {
            padding: 8px 14px;
            font-size: 12.5px;
            flex-shrink: 0;
        }

        .empty-state {
            padding: 30px 20px;
            text-align: center;
            color: var(--gray);
            font-size: 13px;
        }

        .empty-state i {
            font-size: 28px;
            margin-bottom: 10px;
            opacity: 0.5;
            display: block;
        }

        .empty-state p {
            margin: 0;
        }

        /* Modal */
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

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(20, 23, 31, 0.35);
            backdrop-filter: blur(2px);
        }

        .modal-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 900px;
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

        .modal-header {
            padding: 14px 20px;
            background: var(--dark);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.15s ease;
            opacity: 0.85;
            background: none;
            border: none;
            color: white;
            padding: 6px;
        }

        .close-btn:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
        }

        .client-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 16px;
        }

        @media (max-width: 600px) {
            .client-details-grid {
                grid-template-columns: 1fr;
            }
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-label i {
            width: 16px;
            text-align: center;
            color: var(--gray);
        }

        .detail-value {
            font-size: 13px;
            font-weight: 500;
            color: var(--dark);
            margin-top: 3px;
            padding-left: 24px;
        }

        .divider {
            height: 1px;
            background: var(--light-gray);
            margin: 18px 0;
        }

        .stats-section {
            margin-bottom: 18px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: var(--light);
            border-radius: 8px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }

        .recent-orders h4 {
            font-size: 13px;
            font-weight: 600;
            margin: 0 0 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .recent-orders h4 i {
            color: var(--gray);
        }

        .order-list {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            overflow: hidden;
        }

        .order-list li {
            padding: 10px 14px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .order-list li:last-child {
            border-bottom: none;
        }

        .order-list .order-project {
            color: var(--dark);
            font-weight: 500;
        }

        .order-list .order-date {
            color: var(--gray);
            font-size: 12px;
            flex-shrink: 0;
        }

        .order-list .no-orders,
        .order-list .error {
            justify-content: center;
            color: var(--gray);
        }

        .modal-footer {
            padding: 14px 20px;
            background: var(--light);
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
                margin-bottom: 90px;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-container {
                width: 95%;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .user-info {
                margin-top: 4px;
            }

            .search input {
                max-width: 100%;
            }

            .client-item .eyo {
                margin-right: 0;
            }

            .client-item span.cjo-label {
                display: none;
            }
        }
    </style>
</head>

<body>

    <body>
        <div class="sidebar-con">
            <div class="sidebar">
                <div class="brand">
                    <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
                </div>
                <ul class="nav-menu">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li>
                        <a href="papers.php">
                            <i class="fas fa-boxes"></i> <span>Products</span>
                        </a>
                    </li>
                    <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                    <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                    <li class="active"><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                    <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                    <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div>
                    <h1>Client Information</h1>
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

            <div class="card">
                <div class="card-toggle" id="addClientToggle" onclick="toggleAddClientForm()">
                    <h3><i class="fa-solid fa-user-plus"></i> Add Client</h3>
                    <i class="fas fa-chevron-down chevron" id="addClientChevron"></i>
                </div>
                <div class="card-body-inner" id="addClientBody" style="display:none;">
                    <form id="clientForm" action="save_client.php" method="post">
                        <fieldset>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="client_name">Company / Trade Name *</label>
                                    <input type="text" id="client_name" name="client_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="taxpayer_name">Taxpayer Name *</label>
                                    <input type="text" id="taxpayer_name" name="taxpayer_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="tin">TIN</label>
                                    <input type="text" name="tin" id="tin" class="form-control" placeholder="e.g. 123-456-789-0000">
                                </div>
                                <div class="vat-group">
                                    <label>Tax Type *</label>
                                    <div class="vatlabels">
                                        <label><input type="radio" name="tax_type" value="VAT" required> VAT</label>
                                        <label><input type="radio" name="tax_type" value="NONVAT"> NONVAT</label>
                                        <label><input type="radio" name="tax_type" value="VAT-EXEMPT"> VAT-EXEMPT</label>
                                        <label><input type="radio" name="tax_type" value="NON-VAT EXEMPT"> NON-VAT EXEMPT</label>
                                        <label><input type="radio" name="tax_type" value="EXEMPT"> EXEMPT</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="rdo_code">BIR RDO Code</label>
                                    <input list="rdo_list" id="rdo_code" name="rdo_code" placeholder="Enter or select RDO code">
                                    <datalist id="rdo_list">
                                        <option value="001 - Laoag City, Ilocos Norte">
                                        <option value="002 - Vigan, Ilocos Sur">
                                        <option value="003 - San Fernando, La Union">
                                        <option value="004 - Calasiao, West Pangasinan">
                                        <option value="005 - Alaminos, Pangasinan">
                                        <option value="006 - Urdaneta, Pangasinan">
                                        <option value="007 - Bangued, Abra">
                                        <option value="008 - Baguio City">
                                        <option value="009 - La Trinidad, Benguet">
                                        <option value="010 - Bontoc, Mt. Province">
                                        <option value="011 - Tabuk City, Kalinga">
                                        <option value="012 - Lagawe, Ifugao">
                                        <option value="013 - Tuguegarao, Cagayan">
                                        <option value="014 - Bayombong, Nueva Vizcaya">
                                        <option value="015 - Naguilian, Isabela">
                                        <option value="016 - Cabarroguis, Quirino">
                                        <option value="17A - Tarlac City, Tarlac">
                                        <option value="17B - Paniqui, Tarlac">
                                        <option value="018 - Olongapo City">
                                        <option value="019 - Subic Bay Freeport Zone">
                                        <option value="020 - Balanga, Bataan">
                                        <option value="21A - North Pampanga">
                                        <option value="21B - South Pampanga">
                                        <option value="21C - Clark Freeport Zone">
                                        <option value="022 - Baler, Aurora">
                                        <option value="23A - North Nueva Ecija">
                                        <option value="23B - South Nueva Ecija">
                                        <option value="024 - Valenzuela City">
                                        <option value="25A - Plaridel, Bulacan (now RDO West Bulacan)">
                                        <option value="25B - Sta. Maria, Bulacan (now RDO East Bulacan)">
                                        <option value="026 - Malabon-Navotas">
                                        <option value="027 - Caloocan City">
                                        <option value="028 - Novaliches">
                                        <option value="029 - Tondo – San Nicolas">
                                        <option value="030 - Binondo">
                                        <option value="031 - Sta. Cruz">
                                        <option value="032 - Quiapo-Sampaloc-San Miguel-Sta. Mesa">
                                        <option value="033 - Intramuros-Ermita-Malate">
                                        <option value="034 - Paco-Pandacan-Sta. Ana-San Andres">
                                        <option value="035 - Romblon">
                                        <option value="036 - Puerto Princesa">
                                        <option value="037 - San Jose, Occidental Mindoro">
                                        <option value="038 - North Quezon City">
                                        <option value="039 - South Quezon City">
                                        <option value="040 - Cubao">
                                        <option value="041 - Mandaluyong City">
                                        <option value="042 - San Juan">
                                        <option value="043 - Pasig">
                                        <option value="044 - Taguig-Pateros">
                                        <option value="045 - Marikina">
                                        <option value="046 - Cainta-Taytay">
                                        <option value="047 - East Makati">
                                        <option value="048 - West Makati">
                                        <option value="049 - North Makati">
                                        <option value="050 - South Makati">
                                        <option value="051 - Pasay City">
                                        <option value="052 - Parañaque">
                                        <option value="53A - Las Piñas City">
                                        <option value="53B - Muntinlupa City">
                                        <option value="54A - Trece Martirez City, East Cavite">
                                        <option value="54B - Kawit, West Cavite">
                                        <option value="055 - San Pablo City">
                                        <option value="056 - Calamba, Laguna">
                                        <option value="057 - Biñan, Laguna">
                                        <option value="058 - Batangas City">
                                        <option value="059 - Lipa City">
                                        <option value="060 - Lucena City">
                                        <option value="061 - Gumaca, Quezon">
                                        <option value="062 - Boac, Marinduque">
                                        <option value="063 - Calapan, Oriental Mindoro">
                                        <option value="064 - Talisay, Camarines Norte">
                                        <option value="065 - Naga City">
                                        <option value="066 - Iriga City">
                                        <option value="067 - Legazpi City, Albay">
                                        <option value="068 - Sorsogon, Sorsogon">
                                        <option value="069 - Virac, Catanduanes">
                                        <option value="070 - Masbate, Masbate">
                                        <option value="071 - Kalibo, Aklan">
                                        <option value="072 - Roxas City">
                                        <option value="073 - San Jose, Antique">
                                        <option value="074 - Iloilo City">
                                        <option value="075 - Zarraga, Iloilo City">
                                        <option value="076 - Victorias City, Negros Occidental">
                                        <option value="077 - Bacolod City">
                                        <option value="078 - Binalbagan, Negros Occidental">
                                        <option value="079 - Dumaguete City">
                                        <option value="080 - Mandaue City">
                                        <option value="081 - Cebu City North">
                                        <option value="082 - Cebu City South">
                                        <option value="083 - Talisay City, Cebu">
                                        <option value="084 - Tagbilaran City">
                                        <option value="085 - Catarman, Northern Samar">
                                        <option value="086 - Borongan, Eastern Samar">
                                        <option value="087 - Calbayog City, Samar">
                                        <option value="088 - Tacloban City">
                                        <option value="089 - Ormoc City">
                                        <option value="090 - Maasin, Southern Leyte">
                                        <option value="091 - Dipolog City">
                                        <option value="092 - Pagadian City, Zamboanga del Sur">
                                        <option value="093A - Zamboanga City, Zamboanga del Sur">
                                        <option value="093B - Ipil, Zamboanga Sibugay">
                                        <option value="094 - Isabela, Basilan">
                                        <option value="095 - Jolo, Sulu">
                                        <option value="096 - Bongao, Tawi-Tawi">
                                        <option value="097 - Gingoog City">
                                        <option value="098 - Cagayan de Oro City">
                                        <option value="099 - Malaybalay City, Bukidnon">
                                        <option value="100 - Ozamis City">
                                        <option value="101 - Iligan City">
                                        <option value="102 - Marawi City">
                                        <option value="103 - Butuan City">
                                        <option value="104 - Bayugan City, Agusan del Sur">
                                        <option value="105 - Surigao City">
                                        <option value="106 - Tandag, Surigao del Sur">
                                        <option value="107 - Cotabato City">
                                        <option value="108 - Kidapawan, North Cotabato">
                                        <option value="109 - Tacurong, Sultan Kudarat">
                                        <option value="110 - General Santos City">
                                        <option value="111 - Koronadal City, South Cotabato">
                                        <option value="112 - Tagum, Davao del Norte">
                                        <option value="113A - West Davao City">
                                        <option value="113B - East Davao City">
                                        <option value="114 - Mati, Davao Oriental">
                                        <option value="115 - Digos, Davao del Sur">
                                    </datalist>
                                </div>
                                <input type="hidden" name="client_address" id="client_address" oninput="suggestRDO()" required>

                                <div class="section-label">Address <span>Feeds the address used when creating job orders for this client.</span></div>

                                <div class="form-group">
                                    <label for="province">Province *</label>
                                    <select id="province" name="province" required>
                                        <option value="">Select Province</option>
                                        <?php foreach ($provinces as $prov): ?>
                                            <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="city">City / Municipality *</label>
                                    <select id="city" name="city" required>
                                        <option value="">Select City</option>
                                    </select>
                                </div>
                                <div class="form-group" style="position: relative;">
                                    <label for="barangay">Barangay</label>
                                    <span style="
                                position: absolute;
                                top: 60%;
                                left: 12px;
                                transform: translateY(-50%);
                                color: var(--gray);
                                pointer-events: none;
                                font-size: 13px;
                                ">
                                        Brgy.
                                    </span>
                                    <input type="text"
                                        id="barangay"
                                        name="barangay"
                                        class="form-control"
                                        placeholder="e.g. San Isidro"
                                        style="padding-left: 52px;" pattern="[^,]*" title="Commas are not allowed" />
                                </div>
                                <div class="form-group">
                                    <label for="street">Subdivision / Street</label>
                                    <input type="text" id="street" name="street" placeholder="e.g. Rizal St." pattern="[^,]*" title="Commas are not allowed">
                                </div>
                                <div class="form-group">
                                    <label for="building_no">Building / House No.</label>
                                    <input type="text" id="building_no" name="building_no" placeholder="e.g. Bldg 4, Lot 6" pattern="[^,]*" title="Commas are not allowed">
                                </div>
                                <div class="form-group">
                                    <label for="floor_no">Floor / Room No.</label>
                                    <input type="text" id="floor_no" name="floor_no" placeholder="e.g. 2F, Room 201" pattern="[^,]*" title="Commas are not allowed">
                                </div>
                                <div class="form-group">
                                    <label for="zip_code">ZIP Code</label>
                                    <input type="text" id="zip_code" name="zip_code" placeholder="e.g. 3020" pattern="[^,]*" title="Commas are not allowed">
                                </div>

                                <div class="section-label">Contact</div>

                                <div class="form-group">
                                    <label for="contact_person">Contact Person *</label>
                                    <input type="text" id="contact_person" name="contact_person" required>
                                </div>
                                <div class="form-group">
                                    <label for="contact_number">Contact Number *</label>
                                    <input type="text" id="contact_number" name="contact_number" required>
                                </div>
                                <div class="form-group">
                                    <label for="client_by">Client By *</label>
                                    <input type="text" name="client_by" id="client_by" class="form-control" required>
                                </div>
                            </div>
                        </fieldset>
                        <button type="submit" class="btn"><i class="fas fa-save"></i>Save Client</button>
                    </form>
                </div>
            </div>

            <div class="client-card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Saved Clients</h3>
                </div>
                <div class="card-body">
                    <div class="search">
                        <input type="text" id="clientSearchInput" placeholder="Search clients..." class="form-control">
                    </div>
                    <ul class="client-list">
                        <?php foreach ($clients as $client): ?>
                            <li class="client-item" data-client='<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>'>
                                <div class="client-info">
                                    <span class="client-name"><?= htmlspecialchars($client['client_name']) ?></span>
                                </div>
                                <a href="job_orders.php?client_id=<?= $client['id'] ?>" class="btn cjo" onclick="event.stopPropagation()">
                                    <i class="fas fa-file-alt eyo"></i><span class="cjo-label">Create Job Order</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php if (count($clients) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No saved clients found</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="clientModal" class="modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-container animate__animated animate__fadeInUp">
                    <div class="modal-header">
                        <h3>
                            <i class="fas fa-building"></i>
                            <span id="modalClientName"></span>
                        </h3>
                        <button class="close-btn"><i class="fas fa-times"></i></button>
                    </div>

                    <div class="modal-body">
                        <div class="client-details-grid">
                            <!-- Column 1 -->
                            <div class="detail-group">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-id-card"></i> Taxpayer</span>
                                    <span id="modalTaxpayer" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-hashtag"></i> TIN</span>
                                    <span id="modalTIN" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-map-marker-alt"></i> RDO Code</span>
                                    <span id="modalRDO" class="detail-value"></span>
                                </div>
                            </div>

                            <!-- Column 2 -->
                            <div class="detail-group">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-phone"></i> Contact</span>
                                    <span id="modalContact" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user-plus"></i> Client By</span>
                                    <span id="modalClientBy" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-map-marked-alt"></i> Address</span>
                                    <span id="modalAddress" class="detail-value"></span>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="stats-section">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Total Job Orders</span>
                                    <span id="modalTotalOrders" class="stat-value">...</span>
                                </div>
                            </div>
                        </div>

                        <div class="recent-orders">
                            <h4><i class="fas fa-clock"></i> Recent Orders</h4>
                            <ul id="modalRecentOrders" class="order-list">
                                <!-- Dynamically populated -->
                            </ul>
                        </div>
                    </div>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="modal-footer">
                            <button id="editClientBtn" class="btn btn-edit">
                                <i class="fas fa-edit"></i> Edit Client
                            </button>
                            <button id="deleteClientBtn" class="btn btn-delete">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                // Collapsible "Add Client" form, remembers state across visits like the other pages
                function toggleAddClientForm(forceOpen) {
                    const body = document.getElementById('addClientBody');
                    const chevron = document.getElementById('addClientChevron');
                    const isOpen = body.style.display !== 'none';
                    const open = forceOpen !== undefined ? forceOpen : !isOpen;

                    body.style.display = open ? 'block' : 'none';
                    chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
                    sessionStorage.setItem('clientFormOpen', open ? '1' : '0');
                }

                document.addEventListener('DOMContentLoaded', () => {
                    toggleAddClientForm(sessionStorage.getItem('clientFormOpen') === '1');
                });

                function goToLastProductPage() {
                    const last = localStorage.getItem('lastProductPage');
                    if (last) {
                        window.location.href = last;
                    } else {
                        window.location.href = 'papers.php'; // fallback
                    }
                }

                document.querySelectorAll('.client-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const client = JSON.parse(item.dataset.client);

                        // Populate modal fields (matches new HTML structure)
                        document.getElementById('modalClientName').textContent = client.client_name;
                        document.getElementById('modalTaxpayer').textContent = client.taxpayer_name || '-';
                        document.getElementById('modalTIN').textContent = client.tin || '-';
                        document.getElementById('modalRDO').textContent = client.rdo_code || '-';
                        document.getElementById('modalContact').textContent = `${client.contact_person || ''} ${client.contact_number ? `(${client.contact_number})` : ''}`.trim() || '-';
                        document.getElementById('modalClientBy').textContent = client.client_by || '-';
                        document.getElementById('modalAddress').textContent = client.client_address || '-';

                        // Set up edit button (now matches class "btn-edit")
                        const editBtn = document.getElementById('editClientBtn');
                        if (editBtn) {
                            editBtn.onclick = (e) => {
                                e.preventDefault();
                                window.location.href = `edit_client.php?id=${client.id}`;
                            };

                            // Alternative: If using <a> tag
                            editBtn.href = `edit_client.php?id=${client.id}`;
                        }

                        // Set up delete button (now matches class "btn-delete")
                        const deleteBtn = document.getElementById('deleteClientBtn');
                        if (deleteBtn) {
                            deleteBtn.onclick = () => {
                                if (confirm('Are you sure you want to delete this client?')) {
                                    fetch(`delete_client.php?id=${client.id}`, {
                                            method: 'POST'
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            alert(data.message || 'Client deleted successfully');
                                            location.reload();
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            alert('Failed to delete client');
                                        });
                                }
                            };
                        }

                        // Fetch order data (updated to match new list structure)
                        fetch(`get_client_orders.php?client_id=${client.id}`)
                            .then(res => res.json())
                            .then(data => {
                                document.getElementById('modalTotalOrders').textContent = data.total_orders || '0';

                                const ordersList = document.getElementById('modalRecentOrders');
                                ordersList.innerHTML = '';

                                if (!data.recent_orders || data.recent_orders.length === 0) {
                                    ordersList.innerHTML = '<li class="no-orders">No recent orders found</li>';
                                } else {
                                    data.recent_orders.forEach(order => {
                                        const li = document.createElement('li');
                                        li.innerHTML = `
                                        <span class="order-project">${order.project_name || 'Untitled Project'}</span>
                                        <span class="order-date">${formatDate(order.log_date)}</span>
                                    `;
                                        ordersList.appendChild(li);
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching orders:', error);
                                document.getElementById('modalRecentOrders').innerHTML =
                                    '<li class="error">Failed to load order history</li>';
                            });

                        // Show modal (matches new modal structure)
                        document.getElementById('clientModal').style.display = 'flex';
                    });
                });

                // Close modal (updated for new close button class)
                document.querySelector('#clientModal .close-btn').onclick = () => {
                    document.getElementById('clientModal').style.display = 'none';
                };

                // Close when clicking overlay
                document.querySelector('.modal-overlay').addEventListener('click', () => {
                    document.getElementById('clientModal').style.display = 'none';
                });

                // Helper function to format dates
                function formatDate(dateString) {
                    if (!dateString) return '';
                    const options = {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    };
                    return new Date(dateString).toLocaleDateString(undefined, options);
                }

                document.getElementById('clientSearchInput').addEventListener('input', function() {
                    const query = this.value.toLowerCase();
                    const items = document.querySelectorAll('.client-item');

                    items.forEach(item => {
                        const name = item.querySelector('.client-name').textContent.toLowerCase();
                        item.style.display = name.includes(query) ? 'flex' : 'none';
                    });

                    const hasVisible = Array.from(items).some(item => item.style.display !== 'none');
                    document.querySelector('.empty-state').style.display = hasVisible ? 'none' : 'block';
                });

                document.addEventListener('DOMContentLoaded', () => {
                    // Block commas from being typed or pasted
                    document.querySelectorAll('#clientForm input[type="text"], #clientForm textarea').forEach(input => {
                        input.addEventListener('keydown', e => {
                            if (e.key === ',') e.preventDefault();
                        });
                        input.addEventListener('input', () => {
                            input.value = input.value.replace(/,/g, '');
                        });
                    });

                    // Update address when any field changes
                    ["floor_no", "building_no", "street", "barangay", "city", "province", "zip_code"].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.addEventListener("input", updateClientAddress);
                    });

                    // Province → City dropdown
                    const province = document.getElementById("province");
                    const city = document.getElementById("city");

                    if (province && city) {
                        province.addEventListener("change", function() {
                            const selectedProvince = this.value;
                            city.innerHTML = '<option value="">Select City</option>';
                            updateClientAddress();

                            if (!selectedProvince) return;

                            fetch(`get_cities.php?province=${encodeURIComponent(selectedProvince)}`)
                                .then(res => res.json())
                                .then(cities => {
                                    cities.forEach(cityName => {
                                        const option = document.createElement("option");
                                        option.value = cityName;
                                        option.textContent = cityName;
                                        city.appendChild(option);
                                    });
                                });
                        });

                        city.addEventListener("change", () => {
                            suggestRDO();
                            updateClientAddress();
                        });
                    }

                    const scrollPos = sessionStorage.getItem("clientsScrollY");
                    if (scrollPos !== null) {
                        window.scrollTo(0, parseInt(scrollPos));
                    }
                });

                window.addEventListener("beforeunload", function() {
                    sessionStorage.setItem("clientsScrollY", window.scrollY);
                });

                // Construct full client address string
                function updateClientAddress() {
                    const floor = document.getElementById("floor_no").value.trim();
                    const building = document.getElementById("building_no").value.trim();
                    const street = document.getElementById("street").value.trim();
                    const barangayEl = document.getElementById("barangay");
                    const barangay = barangayEl.value.trim().replace(/\b\w/g, c => c.toUpperCase());
                    barangayEl.value = barangay;

                    const city = document.getElementById("city").value.trim();
                    const province = document.getElementById("province").value.trim();
                    const zip = document.getElementById("zip_code").value.trim();

                    const parts = [];
                    if (floor) parts.push(floor);
                    if (building) parts.push(building);
                    if (street) parts.push(street);
                    if (barangay) parts.push("Brgy. " + barangay);
                    if (city) parts.push(city);
                    if (province) parts.push(province);
                    if (zip) parts.push(zip);

                    document.getElementById("client_address").value = parts.join(", ");
                }

                // Suggest RDO code based on city value
                function suggestRDO() {
                    const city = document.getElementById("city").value.trim();
                    const rdoInput = document.getElementById("rdo_code");

                    const matchedCity = Object.keys(rdoMapping).find(key =>
                        city.toLowerCase().includes(key.toLowerCase())
                    );

                    if (matchedCity) {
                        rdoInput.value = `${rdoMapping[matchedCity]} - ${matchedCity}`;
                    }
                }

                const rdoMapping = {
                    "Laoag City, Ilocos Norte": "001",
                    "Vigan, Ilocos Sur": "002",
                    "San Fernando, La Union": "003",
                    "Calasiao, West Pangasinan": "004",
                    "Alaminos, Pangasinan": "005",
                    "Urdaneta, Pangasinan": "006",
                    "Bangued, Abra": "007",
                    "Baguio City": "008",
                    "La Trinidad, Benguet": "009",
                    "Bontoc, Mt. Province": "010",
                    "Tabuk City, Kalinga": "011",
                    "Lagawe, Ifugao": "012",
                    "Tuguegarao, Cagayan": "013",
                    "Bayombong, Nueva Vizcaya": "014",
                    "Naguilian, Isabela": "015",
                    "Cabarroguis, Quirino": "016",
                    "Tarlac City, Tarlac": "17A",
                    "Paniqui, Tarlac": "17B",
                    "Olongapo City": "018",
                    "Subic Bay Freeport Zone": "019",
                    "Balanga, Bataan": "020",
                    "North Pampanga": "21A",
                    "South Pampanga": "21B",
                    "Clark Freeport Zone": "21C",
                    "Baler, Aurora": "022",
                    "North Nueva Ecija": "23A",
                    "South Nueva Ecija": "23B",
                    "Valenzuela City": "024",
                    "Plaridel, Bulacan": "25A (now RDO West Bulacan)",
                    "Sta. Maria, Bulacan": "25B (now RDO East Bulacan)",
                    "Malabon-Navotas": "026",
                    "Caloocan City": "027",
                    "Novaliches": "028",
                    "Tondo – San Nicolas": "029",
                    "Binondo": "030",
                    "Sta. Cruz": "031",
                    "Quiapo-Sampaloc-San Miguel-Sta. Mesa": "032",
                    "Intramuros-Ermita-Malate": "033",
                    "Paco-Pandacan-Sta. Ana-San Andres": "034",
                    "Romblon": "035",
                    "Puerto Princesa": "036",
                    "San Jose, Occidental Mindoro": "037",
                    "North Quezon City": "038",
                    "South Quezon City": "039",
                    "Cubao": "040",
                    "Mandaluyong City": "041",
                    "San Juan": "042",
                    "Pasig": "043",
                    "Taguig-Pateros": "044",
                    "Marikina": "045",
                    "Cainta-Taytay": "046",
                    "East Makati": "047",
                    "West Makati": "048",
                    "North Makati": "049",
                    "South Makati": "050",
                    "Pasay City": "051",
                    "Parañaque": "052",
                    "Las Piñas City": "53A",
                    "Muntinlupa City": "53B",
                    "Trece Martirez City, East Cavite": "54A",
                    "Kawit, West Cavite": "54B",
                    "San Pablo City": "055",
                    "Calamba, Laguna": "056",
                    "Biñan, Laguna": "057",
                    "Batangas City": "058",
                    "Lipa City": "059",
                    "Lucena City": "060",
                    "Gumaca, Quezon": "061",
                    "Boac, Marinduque": "062",
                    "Calapan, Oriental Mindoro": "063",
                    "Talisay, Camarines Norte": "064",
                    "Naga City": "065",
                    "Iriga City": "066",
                    "Legazpi City, Albay": "067",
                    "Sorsogon, Sorsogon": "068",
                    "Virac, Catanduanes": "069",
                    "Masbate, Masbate": "070",
                    "Kalibo, Aklan": "071",
                    "Roxas City": "072",
                    "San Jose, Antique": "073",
                    "Iloilo City": "074",
                    "Zarraga, Iloilo City": "075",
                    "Victorias City, Negros Occidental": "076",
                    "Bacolod City": "077",
                    "Binalbagan, Negros Occidental": "078",
                    "Dumaguete City": "079",
                    "Mandaue City": "080",
                    "Cebu City North": "081",
                    "Cebu City South": "082",
                    "Talisay City, Cebu": "083",
                    "Tagbilaran City": "084",
                    "Catarman, Northern Samar": "085",
                    "Borongan, Eastern Samar": "086",
                    "Calbayog City, Samar": "087",
                    "Tacloban City": "088",
                    "Ormoc City": "089",
                    "Maasin, Southern Leyte": "090",
                    "Dipolog City": "091",
                    "Pagadian City, Zamboanga del Sur": "092",
                    "Zamboanga City, Zamboanga del Sur": "093A",
                    "Ipil, Zamboanga Sibugay": "093B",
                    "Isabela, Basilan": "094",
                    "Jolo, Sulu": "095",
                    "Bongao, Tawi-Tawi": "096",
                    "Gingoog City": "097",
                    "Cagayan de Oro City": "098",
                    "Malaybalay City, Bukidnon": "099",
                    "Ozamis City": "100",
                    "Iligan City": "101",
                    "Marawi City": "102",
                    "Butuan City": "103",
                    "Bayugan City, Agusan del Sur": "104",
                    "Surigao City": "105",
                    "Tandag, Surigao del Sur": "106",
                    "Cotabato City": "107",
                    "Kidapawan, North Cotabato": "108",
                    "Tacurong, Sultan Kudarat": "109",
                    "General Santos City": "110",
                    "Koronadal City, South Cotabato": "111",
                    "Tagum, Davao del Norte": "112",
                    "West Davao City": "113A",
                    "East Davao City": "113B",
                    "Mati, Davao Oriental": "114",
                    "Digos, Davao del Sur": "115"
                };
            </script>
        </div>
    </body>

</html>