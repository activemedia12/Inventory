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
            border-radius: 15px;
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
            border-radius: 12px;
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
            border-radius: 8px;
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
            border-radius: 20px;
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
            border-radius: 8px;
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
            border-radius: 15px;
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
            border-radius: 8px;
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
            border-radius: 8px;
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
            border-radius: 8px;
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
            .profile-header {
                padding: 30px 20px;
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
                    <li><a href="#"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="#"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>
                
                <div class="user-info">
                    <a href="../../website/view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="../../website/profile.php" class="user-profile">
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
            </div>

            <?php if (isset($_GET['order_success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Success!</strong> Your order has been placed and is pending payment verification.
                    </div>
                </div>
            <?php endif; ?>

            <div class="profile-sections">
                <!-- Personal Information -->
                <div class="profile-section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Personal Information
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
                    <h3>Active Media</h3>
                    <p>Professional printing services with quality, speed, and precision for all your business needs.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="#offset">Offset Printing</a></li>
                        <li><a href="#digital">Digital Printing</a></li>
                        <li><a href="#riso">RISO Printing</a></li>
                        <li><a href="#other">Other Services</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Our Team</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Testimonials</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Shipping Info</a></li>
                        <li><a href="#">Returns</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i> 123 Print Street, City, State 12345</li>
                        <li><i class="fas fa-phone"></i> (123) 456-7890</li>
                        <li><i class="fas fa-envelope"></i> info@activemedia.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2023 Active Media. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="../../assets/js/main.js"></script>
    <script>
    // Profile-specific JavaScript that extends the main script.js
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize profile functionality
        setupProfileInteractions();
        
        // Add mobile menu toggle if needed
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const navLinks = document.querySelector('.nav-links');
        
        if (mobileMenuToggle && navLinks) {
            mobileMenuToggle.addEventListener('click', function() {
                navLinks.classList.toggle('active');
                this.querySelector('i').classList.toggle('fa-bars');
                this.querySelector('i').classList.toggle('fa-times');
            });
        }
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
</body>
</html>

<?php
// Close connection
$inventory->close();
?>