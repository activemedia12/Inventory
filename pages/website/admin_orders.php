<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: ../accounts/login.php");
    exit;
}

// Handle status update
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];

    $query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("si", $new_status, $order_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Order #$order_id status updated to " . ucfirst($new_status) . "!";
    } else {
        $_SESSION['error'] = "Failed to update order status!";
    }

    header("Location: admin_orders.php");
    exit;
}

// Get all orders with customer information
$query = "SELECT o.*, u.username 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          ORDER BY o.created_at DESC";
$orders_result = $inventory->query($query);
$orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        /* Search and Filter */
        .search-filter {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 16px 18px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-filter input,
        .search-filter select {
            padding: 9px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            color: var(--dark);
            background: var(--card-bg);
        }

        .search-filter input:focus,
        .search-filter select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background-color 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background: var(--secondary);
        }

        /* Orders Table */
        .order-table {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
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

        /* Order Actions */
        .order-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .status-select {
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            background: var(--card-bg);
            font-size: 12px;
            color: var(--dark);
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .update-btn {
            background: var(--primary-bg);
            color: var(--secondary);
            border: none;
            padding: 7px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: opacity 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .update-btn:hover {
            opacity: 0.8;
        }

        .view-details {
            background: var(--success-bg);
            color: var(--success);
            padding: 7px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.15s ease;
        }

        .view-details:hover {
            opacity: 0.8;
        }

        .proof-image {
            width: 44px;
            height: 44px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        .proof-image:hover {
            transform: scale(2);
        }

        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-paid {
            background: var(--info-bg);
            color: var(--info);
        }

        .status-processing {
            background: var(--success-bg);
            color: var(--success);
        }

        .status-ready_for_pickup {
            background: var(--primary-bg);
            color: var(--secondary);
        }

        .status-completed {
            background: var(--success-bg);
            color: var(--success);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .order-actions {
                flex-direction: column;
            }

            .status-form {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Order Management</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="message">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message'];
                                                        unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error'];
                                                                unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="search-filter">
                <input type="text" id="searchInput" placeholder="Search by order ID or customer..." style="min-width: 250px;">
                <select id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="processing">Processing</option>
                    <option value="ready_for_pickup">Ready for Pickup</option>
                    <option value="completed">Completed</option>
                </select>
                <button class="search-btn" onclick="filterOrders()">
                    <i class="fas fa-search"></i> Filter
                </button>
                <button class="search-btn" onclick="clearFilters()" style="background: var(--gray);">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>

            <!-- Orders Table -->
            <div class="order-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Proof</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                        <?php foreach ($orders as $order): ?>
                            <tr class="order-row" data-status="<?php echo $order['status']; ?>">
                                <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['username']); ?></strong>
                                        <br>
                                        <small style="color: var(--gray);">User ID: <?php echo $order['user_id']; ?></small>
                                    </div>
                                </td>
                                <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['payment_proof']):
                                        // Use the same path structure as profile.php
                                        $proof_path = "../../assets/uploads/payments/user_" . $order['user_id'] . "/" . $order['payment_proof'];
                                        if (file_exists($proof_path)): ?>
                                            <a href="<?php echo $proof_path; ?>" target="_blank">
                                                <img src="<?php echo $proof_path; ?>" alt="Payment Proof" class="proof-image">
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--gray);">File not found</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--gray);">No proof</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    <br>
                                    <small style="color: var(--gray);"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="order-actions">
                                        <form method="post" class="status-form">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <select name="status" class="status-select">
                                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="paid" <?php echo $order['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="ready_for_pickup" <?php echo $order['status'] == 'ready_for_pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                                                <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                            <button type="submit" name="update_status" class="update-btn" title="Update Status">
                                                <i class="fas fa-sync"></i> Update
                                            </button>
                                        </form>
                                        <a href="admin_order_details.php?id=<?php echo $order['order_id']; ?>" class="view-details" title="View Details">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

        function filterOrders() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.order-row');

            rows.forEach(row => {
                const orderId = row.cells[0].textContent.toLowerCase();
                const customer = row.cells[1].textContent.toLowerCase();
                const status = row.getAttribute('data-status');

                const matchesSearch = orderId.includes(searchTerm) || customer.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;

                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            filterOrders();
        }

        // Initial filter on page load if there are URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            filterOrders();
        });
    </script>
</body>

</html>