<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';

// Get user and customer details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.username, u.email_verified,
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

$is_personal = !empty($user_data['first_name']);
$is_company = !empty($user_data['company_name']);

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
    <title>My Profile</title>
    <link rel="icon" type="image/png" href="../../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        /* Profile-specific styles that extend the main style.css */
        .profile-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 80vh;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px;
            background: var(--bg-white);
            box-shadow: var(--shadow);
        }
        
        .profile-header h1 {
            font-size: 2.5em;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .profile-header p {
            font-size: 1.2em;
            color: var(--text-light);
        }
        
        .profile-sections {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .profile-section {
            background: var(--bg-white);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        
        .section-title {
            color: var(--text-dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary-color);
            font-size: 1.5em;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-details p {
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-details strong {
            color: var(--text-dark);
            min-width: 140px;
            font-weight: 600;
        }
        
        .user-details span {
            color: var(--text-light);
            text-align: right;
            flex: 1;
        }
        
        .order-item {
            background: var(--bg-light);
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
            transition: var(--transition);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .order-id {
            font-weight: bold;
            color: var(--text-dark);
            font-size: 1.1em;
        }
        
        .order-status {
            padding: 6px 15px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-paid {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-ready_for_pickup {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 0.95em;
        }
        
        .order-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        .order-detail-label {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .order-detail-value {
            color: var(--text-light);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .success-message i {
            font-size: 1.2em;
        }
        
        .empty-orders {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-light);
        }
        
        .empty-orders i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-orders h3 {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 1.5em;
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
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: var(--bg-white);
            margin: 5% auto;
            padding: 40px;
            width: 85%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .close {
            color: var(--text-light);
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover {
            color: var(--text-dark);
            background: var(--bg-light);
        }
        
        .order-items-list {
            margin-top: 25px;
        }
        
        .order-item-detail {
            background: var(--bg-light);
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
        }
        
        .order-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .order-item-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1em;
            flex: 1;
        }
        
        .order-item-price {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .order-item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 0.95em;
        }
        
        .clickable-order {
            cursor: pointer;
            transition: var(--transition);
        }
        
        .clickable-order:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        
        .profile-actions {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .action-btn {
            padding: 12px 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.3);
        }
        
        .action-btn.secondary {
            background: var(--text-light);
        }
        
        .action-btn.secondary:hover {
            background: #5a6268;
        }
        
        .payment-proof-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }
        
        .payment-proof-link:hover {
            color: var(--primary-dark);
            gap: 8px;
        }
        
        .modal-order-summary {
            background: var(--bg-light);
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1em;
            color: var(--text-dark);
        }
        
        @media (max-width: 992px) {
            .profile-sections {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .modal-content {
                width: 90%;
                padding: 30px;
                margin: 10% auto;
            }
        }
        
        @media (max-width: 768px) {
            .profile-header, .profile-sections {
                font-size: 80%;
                margin: 20px;
            }
            
            .profile-header h1 {
                font-size: 2em;
            }
            
            .profile-section {
                padding: 25px;
            }
            
            .order-details {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .user-details p {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .user-details strong {
                min-width: auto;
            }
            
            .user-details span {
                text-align: left;
            }
            
            .order-item-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .profile-page {
                padding: 20px 0;
            }
            
            .profile-header {
                padding: 25px 15px;
            }
            
            .profile-section {
                padding: 20px;
            }
            
            .modal-content {
                padding: 25px 20px;
                width: 95%;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
            }
        }

        .email-verification {
            display: flex;
            align-items: center;
            justify-content: space-around;
            gap: 10px;
        }
        
        .email-verified {
            color: #28a745;
            font-weight: 600;
        }
        
        .email-not-verified {
            color: #dc3545;
            font-weight: 600;
        }
        
        .verification-btn {
            padding: 6px 12px;
            background: var(--primary-color);
            color: white;
            border: 2px solid var(--primary-color);
            font-size: 0.85em;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .verification-btn:hover {
            background-color: transparent;
            color: black;
        }
        
        .account-type-badge {
            display: inline-block;
            padding: 6px 12px;
            background: var(--bg-light);
            color: var(--text-dark);
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .account-type-personal {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .account-type-company {
            background: #e8f5e9;
            color: #2e7d32;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="#" class="logo">
                    <img src="../../assets/images/plainlogo.png" alt="Active Media" class="logo-image">
                    <span>Active Media Designs & Printing</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="../../website/main.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="../../website/ai_image.php"><i class="fas fa-robot"></i> AI Services</a></li>
                    <li><a href="../../website/about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="../../website/contact.php"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>

                <div class="features">
                    <a href="#" class="chat-icon" id="chatButton">
                        <i class="fas fa-comments"></i>
                        <span class="chat-count" id="chatCount">0</span>
                    </a>
                    <a href="../../website/view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                </div>
                
                <div class="user-info">
                    <a href="../website/profile.php" class="user-profile">
                        <i class="fas fa-user"></i>
                        <span class="user-name">
                            <?php
                            if (!empty($user_data['first_name'])) {
                                echo htmlspecialchars($user_data['first_name']);
                            } elseif (!empty($user_data['company_name'])) {
                                echo htmlspecialchars($user_data['company_name']);
                            } else {
                                echo 'User';
                            }
                            ?>
                        </span>
                    </a>
                    <a href="../../accounts/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
                
                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <!-- Profile Section -->
    <section class="profile-page">
        <div class="container">
            <div class="profile-header">
                <h1><i class="fas fa-user-circle"></i> My Profile</h1>
                <p>Manage your account and view order history</p>
                <div class="email-verification">
                    <?php if ($user_data['email_verified']): ?>
                        <span class="email-verified">
                            <i class="fas fa-check-circle"></i> Email Verified
                        </span>
                    <?php else: ?>
                        <span class="email-not-verified">
                            <i class="fas fa-exclamation-circle"></i> Email Not Verified
                        </span>
                        <a href="../../accounts/email-verification.php" class="verification-btn">
                            <i class="fas fa-envelope"></i> Verify Now
                        </a>
                    <?php endif; ?>
                    <span class="account-type-badge <?php echo $is_personal ? 'account-type-personal' : 'account-type-company'; ?>">
                        <?php echo $is_personal ? 'Personal Account' : 'Company Account'; ?>
                    </span>
                </div>
            </div>

            <?php if (isset($_GET['order_success'])): ?>
                <div class="success-message" id="success-message">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Success!</strong> Your order has been placed and is pending payment verification.
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const message = document.getElementById('success-message');
                        if (message) {
                            setTimeout(() => {
                                message.style.opacity = '0';
                                message.style.transition = 'opacity 0.5s';
                                
                                setTimeout(() => {
                                    message.style.display = 'none';
                                }, 500);
                            }, 3000);
                        }
                    });
                </script>
            <?php endif; ?>

            <div class="profile-sections">
                <!-- Personal Information -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Account Information
                    </h2>
                    <div class="user-details">
                        <?php if (!empty($user_data['first_name'])): ?>
                            <!-- PERSONAL CUSTOMER -->
                            <p>
                                <strong>Name:</strong>
                                <span><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></span>
                            </p>
                            <p>
                                <strong>Username:</strong>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </p>
                            <p>
                                <strong>Contact:</strong>
                                <span><?php echo htmlspecialchars($user_data['personal_contact'] ?? 'N/A'); ?></span>
                            </p>
                            <p>
                                <strong>Address:</strong>
                                <span>
                                    <?php
                                    if (!empty($user_data['address_line1'])) {
                                        echo htmlspecialchars($user_data['address_line1'] . ', ' . $user_data['personal_city'] . ', ' . $user_data['personal_province'] . ' ' . $user_data['personal_zip']);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </span>
                            </p>
                            <p>
                                <strong>Birthdate:</strong>
                                <span><?php echo !empty($user_data['birthdate']) ? htmlspecialchars($user_data['birthdate']) : 'N/A'; ?></span>
                            </p>
                            <p>
                                <strong>Age:</strong>
                                <span><?php echo !empty($user_data['age']) ? htmlspecialchars($user_data['age']) : 'N/A'; ?></span>
                            </p>
                            <p>
                                <strong>Gender:</strong>
                                <span><?php echo !empty($user_data['gender']) ? htmlspecialchars($user_data['gender']) : 'N/A'; ?></span>
                            </p>

                        <?php elseif (!empty($user_data['company_name'])): ?>
                            <!-- COMPANY CUSTOMER -->
                            <p>
                                <strong>Company Name:</strong>
                                <span><?php echo htmlspecialchars($user_data['company_name']); ?></span>
                            </p>
                            <p>
                                <strong>Username:</strong>
                                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            </p>
                            <p>
                                <strong>Taxpayer Name:</strong>
                                <span><?php echo htmlspecialchars($user_data['taxpayer_name'] ?? 'N/A'); ?></span>
                            </p>
                            <p>
                                <strong>Contact Person:</strong>
                                <span><?php echo htmlspecialchars($user_data['contact_person'] ?? 'N/A'); ?></span>
                            </p>
                            <p>
                                <strong>Contact:</strong>
                                <span><?php echo htmlspecialchars($user_data['company_contact'] ?? 'N/A'); ?></span>
                            </p>
                            <p>
                                <strong>Address:</strong>
                                <span>
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
                                </span>
                            </p>
                        <?php else: ?>
                            <p>No customer information available.</p>
                        <?php endif; ?>
                    </div>

                    <div class="profile-actions">
                        <a href="edit_profile.php" class="action-btn">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                    </div>
                </div>

                <!-- Order History Section -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i> Order History
                    </h2>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-item clickable-order" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                <div class="order-header">
                                    <span class="order-id">Order #<?php echo $order['order_id']; ?></span>
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </div>
                                <div class="order-details">
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Amount:</span>
                                        <span class="order-detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Date:</span>
                                        <span class="order-detail-value"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></span>
                                    </div>
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Payment Proof:</span>
                                        <span class="order-detail-value">
                                            <?php if (!empty($order['payment_proof'])): ?>
                                                <a href="../../assets/uploads/payments/user_<?php echo $user_id; ?>/<?php echo $order['payment_proof']; ?>" 
                                                   target="_blank" 
                                                   class="payment-proof-link"
                                                   onclick="event.stopPropagation()">
                                                    <i class="fas fa-external-link-alt"></i> View
                                                </a>
                                            <?php else: ?>
                                                Not uploaded
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-orders">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No orders yet</h3>
                            <p>You haven't placed any orders yet.</p>
                            <a href="../website/main.php" class="action-btn">
                                <i class="fas fa-store"></i> Start Shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalOrderTitle" style="color: var(--text-dark); margin-bottom: 10px;">
                <i class="fas fa-receipt"></i> Order Details
            </h2>
            <p id="modalOrderSubtitle" style="color: var(--text-light); margin-bottom: 25px;">
                Detailed information about your order
            </p>
            <div id="orderModalContent">
                <!-- Order items will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>AMDP</h3>
                    <p>Professional printing services with quality, speed, and precision for all your business needs.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/profile.php?id=100063881538670"><i class="fab fa-facebook-f"></i></a>
                        <a href=""><i class="fab fa-twitter"></i></a>
                        <a href=""><i class="fab fa-instagram"></i></a>
                        <a href=""><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="../../website/main.php #offset">Offset Printing</a></li>
                        <li><a href="../../website/main.php #digital">Digital Printing</a></li>
                        <li><a href="../../website/main.php #riso">RISO Printing</a></li>
                        <li><a href="../../website/main.php #other">Other Services</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="../../website/about.php">About Us</a></li>
                        <li><a href="../../website/about.php">Our Team</a></li>
                        <li><a href="../../website/about.php">Careers</a></li>
                        <li><a href="../../website/about.php">Testimonials</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="../../website/contact.php">Contact Us</a></li>
                        <li><a href="../../website/contact.php">FAQ</a></li>
                        <li><a href="../../website/contact.php">Shipping Info</a></li>
                        <li><a href="../../website/contact.php">Returns</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i>Fausta Rd Lucero St Mabolo, Malolos, Philippines</li>
                        <li><i class="fas fa-phone"></i> (044) 796-4101</li>
                        <li><i class="fas fa-envelope"></i> activemediaprint@gmail.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2025 Active Media Designs & Printing. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="">Privacy Policy</a>
                    <a href="">Terms of Service</a>
                    <a href="">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Chat Widget -->
    <div class="chat-widget" id="chatWidget">
        <div class="chat-header">
            <button class="chat-back-btn" id="chatBackBtn">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 class="chat-title" id="chatTitle">Messages</h3>
            <button class="chat-close" id="chatCloseBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-body">
            <!-- Conversations List -->
            <div class="chat-conversations" id="chatConversations">
                <button class="chat-new-btn" id="newChatBtn">
                    <i class="fas fa-plus"></i> New Conversation
                </button>
                <div id="conversationsList"></div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages" id="chatMessages">
                <div class="messages-list" id="messagesList"></div>
                <div class="chat-input-area" id="chatInputArea">
                    <div class="chat-input-wrapper">
                        <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1"></textarea>
                        <button class="chat-send-btn" id="chatSendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
    // Profile-specific JavaScript that extends the main script.js
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize profile functionality
        setupProfileInteractions();
    });

    function setupProfileInteractions() {
        // Modal functionality
        const modal = document.getElementById('orderModal');
        const closeBtn = document.querySelector('.close');

        if (closeBtn) {
            closeBtn.onclick = function() {
                modal.style.display = 'none';
            }
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Add keyboard support for modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }

    // Function to view order details
    function viewOrderDetails(orderId) {
        // Show loading
        document.getElementById('orderModalContent').innerHTML = `
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary-color); margin-bottom: 15px;"></i>
                <p style="color: var(--text-light);">Loading order details...</p>
            </div>
        `;
        
        const modal = document.getElementById('orderModal');
        modal.style.display = 'block';

        // Update modal title
        document.getElementById('modalOrderTitle').textContent = 'Order #' + orderId + ' Details';
        document.getElementById('modalOrderSubtitle').textContent = 'Detailed information about your order';

        // Fetch order items via AJAX
        fetch('get_order_items.php?order_id=' + orderId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                document.getElementById('orderModalContent').innerHTML = data;
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
                document.getElementById('orderModalContent').innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--accent-color);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2em; margin-bottom: 15px;"></i>
                        <p>Error loading order details. Please try again.</p>
                        <button class="action-btn secondary" onclick="viewOrderDetails(${orderId})" style="margin-top: 15px;">
                            <i class="fas fa-redo"></i> Try Again
                        </button>
                    </div>
                `;
            });
    }

    // Function to format currency
    function formatCurrency(amount) {
        return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    // Function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    </script>
    <script>
        // Chat functionality
        let currentConversationId = null;
        let chatRefreshInterval = null;

        // Initialize chat when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Setup event listeners
            const chatButton = document.getElementById('chatButton');
            const chatCloseBtn = document.getElementById('chatCloseBtn');
            const chatBackBtn = document.getElementById('chatBackBtn');
            const newChatBtn = document.getElementById('newChatBtn');
            const chatSendBtn = document.getElementById('chatSendBtn');
            const chatInput = document.getElementById('chatInput');

            if (chatButton) {
                chatButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleChat();
                });
            }

            if (chatCloseBtn) {
                chatCloseBtn.addEventListener('click', toggleChat);
            }

            if (chatBackBtn) {
                chatBackBtn.addEventListener('click', goBackToConversations);
            }

            if (newChatBtn) {
                newChatBtn.addEventListener('click', startNewConversation);
            }

            if (chatSendBtn) {
                chatSendBtn.addEventListener('click', sendMessage);
            }

            if (chatInput) {
                chatInput.addEventListener('input', function() {
                    autoResize(this);
                });

                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }

            // Load initial unread count
            updateUnreadCount();

            // Check for unread messages every minute
            setInterval(updateUnreadCount, 60000);
        });

        // Toggle chat widget
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            if (widget) {
                widget.classList.toggle('open');

                if (widget.classList.contains('open')) {
                    loadConversations();
                    startChatRefresh();
                } else {
                    stopChatRefresh();
                }
            }
        }

        // Load conversations
        async function loadConversations() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversations');
                const data = await response.json();

                if (data.success) {
                    renderConversations(data.data);
                    updateUnreadCount();

                    // Show conversation count in the UI
                    updateConversationCount(data.data.length);
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
                showChatError('Failed to load conversations. Please try again.');
            }
        }

        // Add this function to check conversation limit
        async function checkConversationLimit() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversation_limit');
                const data = await response.json();

                if (data.success) {
                    return {
                        reached: data.reached || false,
                        count: data.count || 0,
                        limit: data.limit || 3
                    };
                }
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            } catch (error) {
                console.error('Error checking conversation limit:', error);
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            }
        }

        // Render conversations list with delete buttons
        function renderConversations(conversations) {
            const container = document.getElementById('conversationsList');
            if (!container) return;

            if (conversations.length === 0) {
                container.innerHTML = `
                <div class="chat-empty">
                    <i class="fas fa-comments"></i>
                    <p>No conversations yet</p>
                </div>
            `;
                return;
            }

            container.innerHTML = conversations.map(conv => `
            <div class="chat-conversation-item ${currentConversationId === conv.id ? 'active' : ''}" 
                 onclick="openConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation')}')">
                <div class="conversation-header">
                    <div class="conversation-name">${escapeHtml(conv.title || 'Conversation #' + conv.id)}</div>
                    <button class="delete-conversation-btn" onclick="event.stopPropagation(); deleteConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation #' + conv.id)}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="conversation-last-message">${escapeHtml(conv.last_message || 'No messages yet')}</div>
                <div class="conversation-footer">
                    <div class="conversation-time">${formatTime(conv.last_message_time)}</div>
                    ${conv.unread_count > 0 ? `<div class="conversation-unread">${conv.unread_count} new</div>` : ''}
                </div>
            </div>
        `).join('');
        }

        // Delete conversation
        async function deleteConversation(conversationId, conversationTitle) {
            if (!confirm(`Are you sure you want to delete "${conversationTitle}"? This action cannot be undone.`)) {
                return;
            }

            try {
                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_conversation',
                        conversation_id: conversationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // If we're currently viewing this conversation, go back to list
                    if (currentConversationId === conversationId) {
                        goBackToConversations();
                    }

                    // Remove the conversation item from UI
                    const conversationItem = document.querySelector(`.chat-conversation-item[onclick*="${conversationId}"]`);
                    if (conversationItem) {
                        conversationItem.remove();
                    }

                    // Reload conversations list
                    await loadConversations();

                    showChatSuccess('Conversation deleted successfully.');
                } else {
                    showChatError(data.message || 'Failed to delete conversation.');
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
                showChatError('Failed to delete conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Open conversation
        function openConversation(conversationId, title) {
            currentConversationId = conversationId;

            // Update UI
            document.getElementById('chatConversations').style.display = 'none';
            document.getElementById('chatMessages').classList.add('active');
            document.getElementById('chatInputArea').classList.add('active');
            document.getElementById('chatBackBtn').classList.add('visible');
            document.getElementById('chatTitle').textContent = title;

            // Load messages
            loadMessages(conversationId);

            // Mark as read
            markAsRead(conversationId);
        }

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;

            document.getElementById('chatConversations').style.display = 'block';
            document.getElementById('chatMessages').classList.remove('active');
            document.getElementById('chatInputArea').classList.remove('active');
            document.getElementById('chatBackBtn').classList.remove('visible');
            document.getElementById('chatTitle').textContent = 'Messages';

            loadConversations();
        }

        // Load messages
        async function loadMessages(conversationId) {
            try {
                const response = await fetch(`../../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
                const data = await response.json();

                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showChatError('Failed to load messages. Please try again.');
            }
        }

        // Render messages
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!container) return;

            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == userId;
                const isSystem = msg.message_type === 'system';
                const isAdmin = msg.sender_role === 'admin';

                return `
                <div class="message-item ${isSent ? 'sent' : 'received'} ${isSystem ? 'system' : ''}">
                    ${!isSent && !isSystem ? `
                        <div class="message-sender">
                            ${escapeHtml(msg.sender_display_name || msg.sender_username)}
                        </div>
                    ` : ''}
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(msg.message)}</div>
                        <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                    </div>
                </div>
            `;
            }).join('');
        }

        // Send message
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();

            if (!message || !currentConversationId) return;

            // Disable send button
            const sendBtn = document.getElementById('chatSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            try {
                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'send_message',
                        conversation_id: currentConversationId,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    input.value = '';
                    autoResize(input);
                    loadMessages(currentConversationId);
                    updateUnreadCount();
                } else {
                    showChatError(data.message || 'Failed to send message.');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showChatError('Failed to send message. Please try again.');
            } finally {
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        // Start new conversation with online admin
        async function startNewConversation() {
            try {
                // First check if user has reached conversation limit
                const limitCheck = await checkConversationLimit();
                if (limitCheck.reached) {
                    showChatError(`You have reached the maximum limit of 3 active conversations. You currently have ${limitCheck.count} active conversations. Please complete or close existing conversations before starting a new one.`);
                    return;
                }

                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start_conversation',
                        title: 'Support Request',
                        request_online_admin: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const adminInfo = data.admin_name ? ` (Connected with: ${data.admin_name})` : '';
                    openConversation(data.conversation_id, 'Support Request');

                    if (data.admin_name) {
                        showSystemMessage(`You've been connected with administrator ${data.admin_name}. How can we help you?`);
                    }
                } else {
                    // Handle "no admin available" gracefully
                    if (data.message && data.message.includes('No administrators')) {
                        showChatError('No administrators are currently available. Please try again later or contact support via email.');
                    } else if (data.message && data.message.includes('maximum limit')) {
                        showChatError(data.message);
                        // Refresh conversations list to show current count
                        loadConversations();
                    } else {
                        showChatError(data.message || 'Failed to start conversation.');
                    }
                }
            } catch (error) {
                console.error('Error starting conversation:', error);
                showChatError('Failed to start conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Add this function to update conversation count display
        function updateConversationCount(count) {
            // Update the "New Conversation" button text
            const newChatBtn = document.getElementById('newChatBtn');
            if (newChatBtn) {
                const limitReached = count >= 3;
                newChatBtn.innerHTML = `<i class="fas fa-plus"></i> New Conversation (${count}/3)`;
                newChatBtn.disabled = limitReached;
                newChatBtn.title = limitReached ? 'Maximum 3 conversations reached' : 'Start a new conversation';

                if (limitReached) {
                    newChatBtn.classList.add('limit-reached');
                } else {
                    newChatBtn.classList.remove('limit-reached');
                }
            }

            // Also update conversation limit warning in conversations list
            const conversationsList = document.getElementById('conversationsList');
            if (conversationsList && count >= 3) {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (!warningElement) {
                    const warningDiv = document.createElement('div');
                    warningDiv.id = 'conversationLimitWarning';
                    warningDiv.className = 'conversation-limit-warning';
                    warningDiv.innerHTML = `
                    <div class="limit-warning-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Maximum conversations reached</strong>
                            <small>You have ${count} active conversations (maximum: 3). Please close or complete existing conversations to start new ones.</small>
                        </div>
                    </div>
                `;
                    conversationsList.parentNode.insertBefore(warningDiv, conversationsList);
                }
            } else {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (warningElement) {
                    warningElement.remove();
                }
            }
        }

        // Helper function to show system message
        function showSystemMessage(message) {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            const systemMessage = document.createElement('div');
            systemMessage.className = 'message-item system';
            systemMessage.innerHTML = `
            <div class="message-bubble">
                <div class="message-text">${escapeHtml(message)}</div>
                <div class="message-time">${formatMessageTime(new Date().toISOString())}</div>
            </div>
        `;
            messagesList.appendChild(systemMessage);
            messagesList.scrollTop = messagesList.scrollHeight;
        }

        // Add loading indicator
        function showChatLoading(show) {
            let loader = document.getElementById('chatLoader');
            if (!loader && show) {
                loader = document.createElement('div');
                loader.id = 'chatLoader';
                loader.className = 'chat-loader';
                loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                document.getElementById('chatMessages').prepend(loader);
            } else if (loader && !show) {
                loader.remove();
            }
        }

        // Show success message
        function showChatSuccess(message) {
            // Create success notification
            const successDiv = document.createElement('div');
            successDiv.className = 'chat-success-notification';
            successDiv.innerHTML = `
            <div class="success-content">
                <i class="fas fa-check-circle"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;

            // Add to chat widget
            const chatBody = document.querySelector('.chat-body');
            if (chatBody) {
                chatBody.prepend(successDiv);

                // Auto-remove after 3 seconds
                setTimeout(() => {
                    successDiv.remove();
                }, 3000);
            } else {
                alert(message); // Fallback
            }
        }

        // Mark messages as read
        async function markAsRead(conversationId) {
            // This happens automatically when loading messages via the API
            updateUnreadCount();
        }

        // Update unread count
        async function updateUnreadCount() {
            try {
                const response = await fetch('../../api/chat_api.php?action=unread_count');
                const data = await response.json();

                if (data.success) {
                    const count = data.count || 0;
                    const chatCount = document.getElementById('chatCount');
                    if (chatCount) {
                        chatCount.textContent = count;
                        chatCount.style.display = count > 0 ? 'flex' : 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating unread count:', error);
            }
        }

        // Start auto-refresh
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    loadMessages(currentConversationId);
                }
                updateUnreadCount();
            }, 5000); // Refresh every 5 seconds
        }

        // Stop auto-refresh
        function stopChatRefresh() {
            if (chatRefreshInterval) {
                clearInterval(chatRefreshInterval);
                chatRefreshInterval = null;
            }
        }

        // Helper functions
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 86400000) { // Less than 1 day
                return date.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } else if (diff < 604800000) { // Less than 1 week
                return date.toLocaleDateString([], {
                    weekday: 'short'
                });
            } else {
                return date.toLocaleDateString([], {
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function formatMessageTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-resize textarea
        function autoResize(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        // Show chat error
        function showChatError(message) {
            // You can implement a notification system here
            console.error('Chat Error:', message);
            alert(message); // Simple alert for now
        }

        // Make functions available globally
        window.toggleChat = toggleChat;
        window.openConversation = openConversation;
        window.goBackToConversations = goBackToConversations;
        window.sendMessage = sendMessage;
        window.startNewConversation = startNewConversation;
        window.deleteConversation = deleteConversation;
    </script>
</body>
</html>

<?php
// Close connection
$inventory->close();
?>