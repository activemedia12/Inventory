<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

if (!isset($_GET['id'])) {
    header("Location: insuances.php");
    exit;
}

$id = intval($_GET['id']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $description = trim($_POST['description']);

    $stmt = $inventory->prepare("UPDATE insuances SET item_name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $item_name, $description, $id);

    if ($stmt->execute()) {
        $message = 'Insuance updated successfully';
    } else {
        $message = 'Failed to update insuance';
    }
}

// Fetch current data
$stmt = $inventory->prepare("SELECT item_name, description FROM insuances WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    echo "<div class='alert alert-danger'>Item not found.</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Edit Consumable - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            max-width: 900px;
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

        .header-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .header-title h1 {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark);
        }

        .breadcrumb {
            font-size: 12.5px;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .breadcrumb a {
            color: var(--gray);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary);
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

        /* Info / stock summary card */
        .info-banner {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--primary-bg);
            border-radius: 8px;
            padding: 16px 18px;
            margin-bottom: 20px;
        }

        .info-banner .icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .info-banner .value {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }

        .info-banner .label {
            font-size: 12px;
            color: var(--gray);
        }

        /* Form card */
        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            overflow: hidden;
        }

        .form-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card-header i {
            color: var(--gray);
        }

        .form-card-header h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-card-body {
            padding: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
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

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            background: var(--card-bg);
            color: var(--dark);
            transition: border-color 0.15s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--light-gray);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease;
            border: none;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
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
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li class="active">
                    <a href="papers.php">
                        <i class="fas fa-boxes"></i> <span>Products</span>
                    </a>
                    <ul class="submenu">
                        <li><a href="papers.php">Papers</a></li>
                        <li><a href="insuances.php" class="activate">Consumables</a></li>
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
        <header class="header">
            <div class="header-title">
                <h1>Edit Consumable</h1>
                <div class="breadcrumb">
                    <a href="insuances.php">Consumables</a> <i class="fas fa-chevron-right" style="font-size:9px;"></i> <span>Edit</span>
                </div>
            </div>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
                    <small><?= $_SESSION['role'] ?></small>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'Failed') !== false ? 'alert-danger' : 'alert-success' ?>">
                <i class="fas <?= strpos($message, 'Failed') !== false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-clipboard-list"></i>
                <h3><?= htmlspecialchars($data['item_name']) ?></h3>
            </div>
            <div class="form-card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Item Name</label>
                            <input type="text" name="item_name" class="form-control"
                                value="<?= htmlspecialchars($data['item_name']) ?>" required>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="4" style="resize:vertical;"><?= htmlspecialchars($data['description']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="insuances.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>