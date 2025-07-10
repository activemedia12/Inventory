<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$product_id = intval($_GET['id'] ?? 0);
if ($product_id <= 0) {
    echo "<div class='alert alert-danger'>Invalid product ID.</div>";
    exit;
}

// Fetch basic product info and stock
$query = "
    SELECT 
        p.product_type,
        p.product_group,
        p.product_name,
        p.unit_price,
        COALESCE(d.total_delivered, 0) AS total_delivered,
        COALESCE(u.total_used, 0) AS total_used,
        COALESCE(d.total_delivered, 0) - COALESCE(u.total_used, 0) AS stock_balance
    FROM products p
    LEFT JOIN (
        SELECT product_id, SUM(delivered_reams * 500) AS total_delivered
        FROM delivery_logs
        GROUP BY product_id
    ) d ON p.id = d.product_id
    LEFT JOIN (
        SELECT product_id, SUM(used_sheets) AS total_used
        FROM usage_logs
        GROUP BY product_id
    ) u ON p.id = u.product_id
    WHERE p.id = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    die("Error in product query: " . $mysqli->error);
}
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "<div class='alert alert-danger'>Product not found.</div>";
    exit;
}

// Fetch usage history with prepared statement
$usage_query = "
    SELECT 
        ul.log_date, 
        jo.client_name, 
        jo.project_name,
        ul.used_sheets
    FROM usage_logs ul
    LEFT JOIN job_orders jo ON ul.job_order_id = jo.id
    WHERE ul.product_id = ?
    ORDER BY ul.log_date DESC
";
$usage_stmt = $mysqli->prepare($usage_query);
if (!$usage_stmt) {
    die("Error in usage history query: " . $mysqli->error);
}
$usage_stmt->bind_param("i", $product_id);
$usage_stmt->execute();
$usage_history = $usage_stmt->get_result();

// Fetch delivery history with prepared statement
$delivery_query = "
    SELECT delivery_date, delivered_reams, supplier_name, amount_per_ream
    FROM delivery_logs
    WHERE product_id = ?
    ORDER BY delivery_date DESC
";
$delivery_stmt = $mysqli->prepare($delivery_query);
if (!$delivery_stmt) {
    die("Error in delivery history query: " . $mysqli->error);
}
$delivery_stmt->bind_param("i", $product_id);
$delivery_stmt->execute();
$delivery_history = $delivery_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Info</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
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

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgb(140, 140, 140);
            border-radius: 10px;
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
        }
    </style>
</head>

<body>
    <!-- Overlay -->
    <div id='productModal' class="overlay animate__animated animate__fadeIn"></div>

    <!-- Floating Window -->
    <div id='productModalBody' class="floating-window">
        <div class="window-header">
            <div class="window-title">
                <i class="fas fa-box"></i>
                Product Information
            </div>
            <button class="close-btn" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="window-content">
            <!-- Basic Product Info -->
            <div class="product-info-compact">
                <div class="info-item-compact">
                    <strong>Product Type</strong>
                    <span><?= htmlspecialchars($product['product_type']) ?></span>
                </div>

                <div class="info-item-compact">
                    <strong>Product Group</strong>
                    <span><?= htmlspecialchars($product['product_group']) ?></span>
                </div>

                <div class="info-item-compact">
                    <strong>Product Name</strong>
                    <span><?= htmlspecialchars($product['product_name']) ?></span>
                </div>

                <div class="info-item-compact">
                    <strong>Unit Price</strong>
                    <span>₱<?= number_format($product['unit_price'], 2) ?></span>
                </div>
            </div>

            <!-- Stock Summary -->
            <div class="stock-summary-compact">
                <div class="stock-card-compact">
                    <h4>Total Delivered</h4>
                    <div class="stock-value-compact"><?= number_format($product['total_delivered']) ?></div>
                    <div class="stock-unit-compact">sheets (<?= number_format($product['total_delivered'] / 500, 2) ?> reams)</div>
                </div>

                <div class="stock-card-compact">
                    <h4>Total Used</h4>
                    <div class="stock-value-compact"><?= number_format($product['total_used']) ?></div>
                    <div class="stock-unit-compact">sheets (<?= number_format($product['total_used'] / 500, 2) ?> reams)</div>
                </div>

                <div class="stock-card-compact">
                    <h4>Current Stock</h4>
                    <div class="stock-value-compact"><?= number_format($product['stock_balance']) ?></div>
                    <div class="stock-unit-compact">sheets (<?= number_format($product['stock_balance'] / 500, 2) ?> reams)</div>
                </div>
            </div>

            <!-- Usage History Section -->
            <div class="section-header">
                <i class="fas fa-history"></i>
                Usage History
            </div>
            <div class="container">
            <?php if ($usage_history->num_rows > 0): ?>
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th>Sheets</th>
                            <th>Reams</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $usage_history->fetch_assoc()): ?>
                            <tr>
                                <td><?= date("M j, Y", strtotime($row['log_date'])) ?></td>
                                <td><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($row['project_name'] ?? 'N/A') ?></td>
                                <td><?= number_format($row['used_sheets']) ?></td>
                                <td><?= number_format($row['used_sheets'] / 500, 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p><i class="fas fa-info-circle"></i> No usage history found for this product</p>
                </div>
            <?php endif; ?>
            </div>
            <!-- Delivery History Section -->
            <div class="section-header">
                <i class="fas fa-truck"></i>
                Delivery History
            </div>
            <div class="container">
            <?php if ($delivery_history->num_rows > 0): ?>
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Reams</th>
                            <th>Price/Ream</th>
                            <th>Sheets</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $delivery_history->fetch_assoc()): ?>
                            <tr>
                                <td><?= date("M j, Y", strtotime($row['delivery_date'])) ?></td>
                                <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                                <td><?= number_format($row['delivered_reams'], 2) ?></td>
                                <td>₱<?= number_format($row['amount_per_ream'], 2) ?></td>
                                <td><?= number_format($row['delivered_reams'] * 500) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p><i class="fas fa-info-circle"></i> No delivery history found for this product</p>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>
<?php
// Close statements if they're still open
if (isset($usage_stmt)) $usage_stmt->close();
if (isset($delivery_stmt)) $delivery_stmt->close();
$mysqli->close();
?>