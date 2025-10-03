<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';

// Get user and customer details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.username,
                 p.first_name, p.middle_name, p.last_name, p.age, p.gender, 
                 p.birthdate, p.contact_number AS personal_contact, 
                 p.address_line1, p.city AS personal_city, 
                 p.province AS personal_province, p.zip_code AS personal_zip,
                 c.company_name, c.taxpayer_name, c.contact_person, 
                 c.contact_number AS company_contact, 
                 c.city AS company_city, c.province AS company_province, 
                 c.barangay, c.subd_or_street, c.building_or_block, 
                 c.lot_or_room_no, c.zip_code AS company_zip
          FROM users u
          LEFT JOIN personal_customers p ON u.id = p.user_id
          LEFT JOIN company_customers c ON u.id = c.user_id
          WHERE u.id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Get order history
$query = "SELECT order_id, total_amount, status, payment_proof, created_at 
          FROM orders 
          WHERE user_id = ? 
          ORDER BY created_at DESC";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $query = "SELECT SUM(ci.quantity) as total_items 
              FROM cart_items ci 
              JOIN carts c ON ci.cart_id = c.cart_id 
              WHERE c.user_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_cart = $stmt->get_result();
    $row = $result_cart->fetch_assoc();

    $cart_count = $row['total_items'] ? $row['total_items'] : 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Active Media</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        header {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            margin-bottom: 30px;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4a4a4a;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo i {
            margin-right: 10px;
            color: #007bff;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info a {
            color: #4a4a4a;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .user-info a:hover {
            color: #007bff;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .profile-header h1 {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .profile-sections {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .profile-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
            font-size: 1.4em;
        }

        .user-details p {
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .user-details strong {
            color: #2c3e50;
            min-width: 120px;
            display: inline-block;
        }

        .order-item {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .order-id {
            font-weight: bold;
            color: #2c3e50;
        }

        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.95em;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }

        .empty-orders {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-orders i {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 80%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .order-items-list {
            margin-top: 20px;
        }

        .order-item-detail {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .clickable-order {
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .clickable-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .cart-btn {
            padding: 12px 25px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .cart-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .cart-icon {
            position: relative;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .status-ready_for_pickup {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="../../website/main.php" class="logo">
                    <i class="fas fa-print"></i>
                    Active Media
                </a>

                <div class="user-info">
                    <a href="../../website/view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="#">
                        <i class="fas fa-user"></i>
                        <?php 
                            if (!empty($user_data['first_name'])) {
                                echo htmlspecialchars($user_data['first_name']);
                            } elseif (!empty($user_data['company_name'])) {
                                echo htmlspecialchars($user_data['company_name']);
                            } else {
                                echo 'User';
                            }
                        ?>
                    </a>
                    <a href="../../accounts/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <p>Manage your account and view order history</p>
        </div>

        <?php if (isset($_GET['order_success'])): ?>
            <div class="success-message">
                <strong>Success!</strong> Your order has been placed and is pending payment verification.
            </div>
        <?php endif; ?>

        <div class="profile-sections">
            <!-- Personal Information -->
            <div class="profile-section">
                <h2 class="section-title">Personal Information</h2>
                <div class="user-details">
                    <?php if (!empty($user_data['first_name'])): ?>
                        <!-- PERSONAL CUSTOMER -->
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($user_data['personal_contact'] ?? 'N/A'); ?></p>
                        <p><strong>Address:</strong>
                            <?php
                            if (!empty($user_data['address_line1'])) {
                                echo htmlspecialchars($user_data['address_line1'] . ', ' . $user_data['personal_city'] . ', ' . $user_data['personal_province'] . ' ' . $user_data['personal_zip']);
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </p>
                        <p><strong>Birthdate:</strong> <?php echo !empty($user_data['birthdate']) ? htmlspecialchars($user_data['birthdate']) : 'N/A'; ?></p>
                        <p><strong>Age:</strong> <?php echo !empty($user_data['age']) ? htmlspecialchars($user_data['age']) : 'N/A'; ?></p>
                        <p><strong>Gender:</strong> <?php echo !empty($user_data['gender']) ? htmlspecialchars($user_data['gender']) : 'N/A'; ?></p>

                    <?php elseif (!empty($user_data['company_name'])): ?>
                        <!-- COMPANY CUSTOMER -->
                        <p><strong>Company Name:</strong> <?php echo htmlspecialchars($user_data['company_name']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        <p><strong>Taxpayer Name:</strong> <?php echo htmlspecialchars($user_data['taxpayer_name'] ?? 'N/A'); ?></p>
                        <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($user_data['contact_person'] ?? 'N/A'); ?></p>
                        <p><strong>Contact:</strong> <?php echo htmlspecialchars($user_data['company_contact'] ?? 'N/A'); ?></p>
                        <p><strong>Address:</strong>
                            <?php
                            $address_parts = [];
                            if (!empty($user_data['barangay'])) $address_parts[] = $user_data['barangay'];
                            if (!empty($user_data['subd_or_street'])) $address_parts[] = $user_data['subd_or_street'];
                            if (!empty($user_data['building_or_block'])) $address_parts[] = $user_data['building_or_block'];
                            if (!empty($user_data['lot_or_room_no'])) $address_parts[] = $user_data['lot_or_room_no'];
                            if (!empty($user_data['company_city'])) $address_parts[] = $user_data['company_city'];
                            if (!empty($user_data['company_province'])) $address_parts[] = $user_data['company_province'];
                            if (!empty($user_data['company_zip'])) $address_parts[] = $user_data['company_zip'];

                            echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : 'N/A';
                            ?>
                        </p>
                    <?php else: ?>
                        <p>No customer information available.</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Order History Section -->
            <div class="profile-section">
                <h2 class="section-title">Order History</h2>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-item clickable-order" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                            <div class="order-header">
                                <span class="order-id">Order #<?php echo $order['order_id']; ?></span>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <div class="order-details">
                                <div>
                                    <strong>Amount:</strong> ₱<?php echo number_format($order['total_amount'], 2); ?>
                                </div>
                                <div>
                                    <strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                </div>
                                <div>
                                    <strong>Payment Proof:</strong>
                                    <?php if (!empty($order['payment_proof'])): ?>
                                        <a href="../../assets/uploads/payments/user_<?php echo $user_id; ?>/<?php echo $order['payment_proof']; ?>" target="_blank" onclick="event.stopPropagation()">View</a>
                                    <?php else: ?>
                                        Not uploaded
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-orders">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>No orders yet</h3>
                        <p>You haven't placed any orders yet.</p>
                        <a href="../website/main.php" class="cart-btn" style="display: inline-block; margin-top: 15px;">
                            <i class="fas fa-store"></i> Start Shopping
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalOrderTitle">Order Details</h2>
            <div id="orderModalContent">
                <!-- Order items will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('orderModal');
        const closeBtn = document.querySelector('.close');

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Function to view order details
        function viewOrderDetails(orderId) {
            // Show loading
            document.getElementById('orderModalContent').innerHTML = '<p>Loading order details...</p>';
            modal.style.display = 'block';

            // Fetch order items via AJAX
            fetch('get_order_items.php?order_id=' + orderId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalOrderTitle').textContent = 'Order #' + orderId + ' Details';
                    document.getElementById('orderModalContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('orderModalContent').innerHTML = '<p>Error loading order details.</p>';
                });
        }
    </script>
</body>

</html>

<?php
// Close connection
$inventory->close();
?>