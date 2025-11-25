<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #3498db;
            font-size: 1.3em;
            margin-bottom: 5px;
        }

        .sidebar-header small {
            font-size: 0.85em;
            color: #bdc3c7;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f0f2f5;
            padding-bottom: 130px;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1c1e21;
            font-size: 1.8em;
            margin: 0;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        /* Messages */
        .message {
            padding: 15px;
            background: #d4edda;
            color: #155724;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .error {
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* Search and Filter */
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-filter input, .search-filter select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .search-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background: #2980b9;
        }

        /* Orders Table */
        .order-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Order Actions */
        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .status-select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: white;
            font-size: 14px;
        }

        .update-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .update-btn:hover {
            background: #2980b9;
        }

        .view-details {
            background: #27ae60;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }

        .view-details:hover {
            background: #219a52;
        }

        .proof-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .proof-image:hover {
            transform: scale(2);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d1ecf1; color: #0c5460; }
        .status-processing { background: #d4edda; color: #155724; }
        .status-ready_for_pickup { background: #cce7ff; color: #004085; }
        .status-completed { background: #d1f7c4; color: #0f5132; }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
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
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
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
                <button class="search-btn" onclick="clearFilters()" style="background: #95a5a6;">
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
                                    <small style="color: #666;">User ID: <?php echo $order['user_id']; ?></small>
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
                                        <span style="color: #999;">File not found</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">No proof</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                <br>
                                <small style="color: #666;"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
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