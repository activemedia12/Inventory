<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$delivery_id = intval($_GET['id'] ?? 0);

if ($delivery_id <= 0) {
    die("Invalid delivery ID.");
}

// Fetch the delivery log
$stmt = $inventory->prepare("SELECT dl.*, p.product_type, p.product_group, p.product_name 
                         FROM delivery_logs dl
                         JOIN products p ON dl.product_id = p.id
                         WHERE dl.id = ?");
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Delivery not found.");
}

$delivery = $result->fetch_assoc();
$product_id = $delivery['product_id'];

// If confirmed via POST, proceed with validation and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Calculate stock before deletion
    $reams_to_delete = floatval($delivery['delivered_reams']);

    $delivered_result = $inventory->query("
        SELECT SUM(delivered_reams) AS total_delivered 
        FROM delivery_logs 
        WHERE product_id = $product_id AND id != $delivery_id
    ");
    $total_delivered = floatval($delivered_result->fetch_assoc()['total_delivered'] ?? 0);

    $used_result = $inventory->query("
        SELECT SUM(used_sheets) AS total_used_sheets 
        FROM usage_logs 
        WHERE product_id = $product_id
    ");
    $total_used_sheets = floatval($used_result->fetch_assoc()['total_used_sheets'] ?? 0);

    $total_delivered_sheets = $total_delivered * 500;

    if ($total_delivered_sheets < $total_used_sheets) {
        echo "<script>
            alert('❌ Cannot delete this delivery. It would cause negative stock.');
            window.location.href = 'delivery.php?id=$product_id&tab=delivery';
        </script>";
        exit;
    }

    // Proceed with deletion
    $delete_stmt = $inventory->prepare("DELETE FROM delivery_logs WHERE id = ?");
    $delete_stmt->bind_param("i", $delivery_id);
    $delete_stmt->execute();

    header("Location: delivery.php?id=$product_id&tab=delivery");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Delivery</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgb(140, 140, 140);
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
            min-height: 100vh;
            padding-left: 70px;
        }

        /* Floating Mobile Navigation */
        .sidebar {
            width: 70px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: width 0.3s ease;
            overflow: hidden;
        }

        .sidebar.expanded {
            width: 250px;
        }

        .brand {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand img {
            height: 40px;
            width: auto;
            transform: rotate(45deg);
        }

        .brand h2 {
            display: none;
        }

        .sidebar.expanded .brand {
            justify-content: flex-start;
        }

        .sidebar.expanded .brand img {
            margin-right: 15px;
        }

        .sidebar.expanded .brand h2 {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }

        .nav-menu {
            list-style: none;
            padding-top: 15px;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            transition: background-color 0.3s;
            white-space: nowrap;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            background-color: var(--light-gray);
        }

        .nav-menu li a i {
            min-width: 40px;
            text-align: center;
            color: var(--gray);
            font-size: 18px;
        }

        .nav-menu li a span {
            display: none;
        }

        .sidebar.expanded .nav-menu li a span {
            display: inline;
        }

        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 18px;
            cursor: pointer;
        }

        /* Main Content */
        .main-content {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Confirmation Card */
        .confirmation-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .confirmation-icon {
            font-size: 48px;
            color: var(--danger);
            margin-bottom: 20px;
        }

        .confirmation-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .confirmation-message {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.6;
        }

        /* Delivery Details */
        .delivery-details {
            background: rgba(255, 77, 79, 0.05);
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
            border-left: 3px solid var(--danger);
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }

        .detail-label {
            font-weight: 500;
            color: var(--dark);
            min-width: 150px;
        }

        .detail-value {
            color: var(--gray);
            flex: 1;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background-color: #e63946;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--dark);
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 250px;
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }

            .sidebar.expanded {
                transform: translateX(0);
            }

            .menu-toggle {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .detail-row {
                flex-direction: column;
            }

            .detail-label {
                margin-bottom: 5px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="main-content">
        <div class="confirmation-card">
            <div class="confirmation-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="confirmation-title">Delete Delivery Record</h1>
            <p class="confirmation-message">Are you sure you want to permanently delete this delivery record?</p>

            <div class="delivery-details">
                <div class="detail-row">
                    <span class="detail-label">Product:</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($delivery['product_type']) ?> -
                        <?= htmlspecialchars($delivery['product_group']) ?> -
                        <?= htmlspecialchars($delivery['product_name']) ?>
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Delivery Date:</span>
                    <span class="detail-value"><?= date('M j, Y', strtotime($delivery['delivery_date'])) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Supplier:</span>
                    <span class="detail-value"><?= htmlspecialchars($delivery['supplier_name']) ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Quantity:</span>
                    <span class="detail-value">
                        <?= $delivery['delivered_reams'] ?> reams (<?= $delivery['delivered_reams'] * 500 ?> sheets)
                    </span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">Price per Ream:</span>
                    <span class="detail-value">₱<?= number_format($delivery['amount_per_ream'], 2) ?></span>
                </div>

                <?php if (!empty($delivery['delivery_note'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Notes:</span>
                        <span class="detail-value"><?= nl2br(htmlspecialchars($delivery['delivery_note'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST">
                <div class="btn-group">
                    <button type="submit" class="btn btn-danger">
                        Confirm Delete
                    </button>
                    <a href="delivery.php?id=<?= $product_id ?>&tab=delivery" class="btn btn-outline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Enhanced mobile navigation toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('expanded');

            // Toggle menu icon
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('expanded')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Close menu when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                event.target !== menuToggle &&
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('expanded');
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    </script>
</body>

</html>