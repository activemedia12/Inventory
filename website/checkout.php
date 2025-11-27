<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Get selected items from URL
$selected_items = isset($_GET['selected_items']) ? explode(',', $_GET['selected_items']) : [];

if (empty($selected_items)) {
    header("Location: view_cart.php");
    exit;
}

// Get user and customer details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.username, 
                 pc.first_name, pc.middle_name, pc.last_name, pc.age, pc.gender, 
                 pc.birthdate, pc.contact_number AS personal_contact, pc.address_line1, pc.city AS personal_city, 
                 pc.province AS personal_province, pc.zip_code AS personal_zip,
                 cc.company_name, cc.contact_person, cc.contact_number AS company_contact,
                 cc.province AS company_province, cc.city AS company_city, cc.barangay, 
                 cc.subd_or_street, cc.building_or_block, cc.lot_or_room_no, cc.zip_code AS company_zip,
                 CASE 
                     WHEN pc.user_id IS NOT NULL THEN 'personal'
                     WHEN cc.user_id IS NOT NULL THEN 'company'
                     ELSE 'unknown'
                 END AS customer_type
          FROM users u
          LEFT JOIN personal_customers pc ON u.id = pc.user_id 
          LEFT JOIN company_customers cc ON u.id = cc.user_id 
          WHERE u.id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Get cart items for selected items with all customization options
$placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
$query = "SELECT p.id, p.product_name, p.price as unit_price, p.category as product_group, 
                 ci.quantity, ci.item_id, ci.design_image,
                 ci.quoted_price, ci.price_updated_by_admin,
                 ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                 ci.finish_option, ci.paper_option, ci.binding_option,
                 ci.layout_option, ci.layout_details, ci.gsm_option, ci.user_layout_files,
                 -- Get option names from related tables
                 po.option_name as paper_option_name,
                 fo.option_name as finish_option_name,
                 bo.option_name as binding_option_name,
                 lo.option_name as layout_option_name,
                 -- Get other services option names using CASE statements
                 CASE 
                     WHEN p.category = 'Other Services' AND p.product_name = 'T-Shirts' THEN ts.size_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Tote Bag' THEN tos.size_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Paper Bag' THEN pbs.size_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Mug' THEN ms.size_name
                     ELSE NULL
                 END as size_option_name,
                 CASE 
                     WHEN p.category = 'Other Services' AND p.product_name = 'T-Shirts' THEN tc.color_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Tote Bag' THEN toc.color_name
                     WHEN p.category = 'Other Services' AND p.product_name = 'Mug' THEN mc.color_name
                     ELSE NULL
                 END as color_option_name,
                 pbs.dimensions as paperbag_dimensions
          FROM cart_items ci
          JOIN products_offered p ON ci.product_id = p.id
          JOIN carts c ON ci.cart_id = c.cart_id
          LEFT JOIN paper_options po ON ci.paper_option = po.id
          LEFT JOIN finish_options fo ON ci.finish_option = fo.id
          LEFT JOIN binding_options bo ON ci.binding_option = bo.id
          LEFT JOIN layout_options lo ON ci.layout_option = lo.id
          -- Left join all other services tables with specific conditions
          LEFT JOIN tshirt_sizes ts ON (p.category = 'Other Services' AND p.product_name = 'T-Shirts' AND ci.size_option = ts.id)
          LEFT JOIN tshirt_colors tc ON (p.category = 'Other Services' AND p.product_name = 'T-Shirts' AND ci.color_option = tc.id)
          LEFT JOIN totesize_options tos ON (p.category = 'Other Services' AND p.product_name = 'Tote Bag' AND ci.size_option = tos.id)
          LEFT JOIN totecolor_options toc ON (p.category = 'Other Services' AND p.product_name = 'Tote Bag' AND ci.color_option = toc.id)
          LEFT JOIN paperbag_size_options pbs ON (p.category = 'Other Services' AND p.product_name = 'Paper Bag' AND ci.size_option = pbs.id)
          LEFT JOIN mug_size_options ms ON (p.category = 'Other Services' AND p.product_name = 'Mug' AND ci.size_option = ms.id)
          LEFT JOIN mug_color_options mc ON (p.category = 'Other Services' AND p.product_name = 'Mug' AND ci.color_option = mc.id)
          WHERE c.user_id = ? AND ci.item_id IN ($placeholders)";
$stmt = $inventory->prepare($query);
$types = str_repeat('i', count($selected_items) + 1);
$stmt->bind_param($types, $user_id, ...$selected_items);
$stmt->execute();
$result = $stmt->get_result();

$checkout_items = [];
$subtotal = 0;
while ($row = $result->fetch_assoc()) {
    // Use admin price if available, otherwise use unit price
    $actual_price = $row['price_updated_by_admin'] && $row['quoted_price'] > 0 
        ? $row['quoted_price'] 
        : $row['unit_price'];
    
    $item_total = $actual_price * $row['quantity'];
    $subtotal += $item_total;
    
    // Add the actual price to the row for display
    $row['actual_price'] = $actual_price;
    $row['has_admin_price'] = $row['price_updated_by_admin'] && $row['quoted_price'] > 0;
    
    $checkout_items[] = $row;
}

$shipping = 0;
$tax = $subtotal * 0.03;
$total = $subtotal + $tax;

$cart_count = 0;
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* Checkout-specific styles that extend the main style.css */
        .checkout-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 80vh;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px;
            background: var(--bg-white);
            box-shadow: var(--shadow);
        }

        .checkout-header h1 {
            font-size: 2.5em;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .checkout-header p {
            font-size: 1.2em;
            color: var(--text-light);
        }

        .checkout-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .checkout-section {
            background: var(--bg-white);
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .section-title {
            color: var(--text-dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary-color);
            font-size: 1.4em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .customer-details p {
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
        }

        .customer-details strong {
            color: var(--text-dark);
            min-width: 140px;
            font-weight: 600;
        }

        .customer-details span {
            color: var(--text-light);
            text-align: right;
            flex: 1;
        }

        .order-summary-items {
            margin-bottom: 25px;
        }

        .summary-item {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
        }

        .item-content {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .item-image {
            flex-shrink: 0;
        }

        .item-image img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }

        .item-details {
            flex-grow: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-size: 1.1em;
        }

        .item-category {
            color: var(--text-light);
            margin-bottom: 8px;
            font-size: 0.9em;
        }

        .item-price {
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .item-total {
            text-align: right;
            flex-shrink: 0;
            font-weight: 600;
            color: #27ae60;
            font-size: 1.1em;
        }

        /* Printing Details Styles */
        .printing-details {
            margin-top: 15px;
            padding: 15px;
            background: var(--bg-light);
            font-size: 0.9em;
            border-left: 4px solid var(--primary-color);
            text-transform: uppercase;
        }

        .details-row {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-dark);
            min-width: 140px;
        }

        .detail-value {
            color: var(--text-light);
            flex: 1;
            text-align: right;
        }

        .design-previews {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-top: 15px;
        }

        .design-preview {
            text-align: center;
            flex: 0 0 auto;
        }

        .design-preview img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border: 2px solid var(--primary-color);
            padding: 5px;
            background: white;
            transition: var(--transition);
        }

        .design-preview img:hover {
            transform: scale(1.05);
        }

        .design-label {
            font-size: 0.75em;
            color: var(--text-light);
            margin-top: 8px;
            font-weight: 500;
        }

        .design-preview:has(img[alt*="Original"]) img {
            border-color: #28a745;
        }

        .design-preview:has(img[alt*="Mockup"]) img {
            border-color: var(--primary-color);
        }

        .design-placeholder {
            width: 100px;
            height: 100px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #ccc;
        }

        .custom-design-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed var(--primary-color);
        }

        .custom-design-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-totals {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .total-row {
            font-weight: bold;
            font-size: 1.3em;
            color: #27ae60;
            border-top: 2px solid var(--border-color);
            margin-top: 15px;
            padding-top: 15px;
        }

        /* Payment Section */
        .payment-section {
            background: var(--bg-white);
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            position: sticky;
            top: 20px;
        }

        .qr-code-container {
            text-align: center;
            padding: 25px;
            background: var(--bg-light);
            margin-bottom: 25px;
            border: 2px solid var(--border-color);
        }

        .qr-code-container h3 {
            color: var(--text-dark);
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .qr-code-image {
            max-width: 220px;
            margin: 0 auto 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .qr-code-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        .payment-details {
            margin-bottom: 20px;
        }

        .payment-detail {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .payment-detail:last-child {
            border-bottom: none;
        }

        .payment-detail strong {
            color: var(--text-dark);
        }

        .upload-section {
            margin-top: 25px;
        }

        .file-upload {
            margin: 20px 0;
        }

        .file-upload-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .file-input {
            width: 100%;
            padding: 12px;
            border: 2px dashed var(--border-color);
            background: var(--bg-light);
            transition: var(--transition);
        }

        .file-input:hover {
            border-color: var(--primary-color);
        }

        .file-input:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .instructions {
            margin-top: 25px;
            padding: 20px;
            background: var(--bg-light);
            border-left: 4px solid var(--primary-color);
        }

        .instructions h4 {
            color: var(--text-dark);
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .instructions ul {
            list-style: none;
            padding-left: 0;
        }

        .instructions li {
            margin-bottom: 10px;
            padding-left: 25px;
            position: relative;
            color: var(--text-light);
        }

        .instructions li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-weight: bold;
        }

        .checkout-btn {
            width: 100%;
            padding: 18px;
            background: #28a745;
            color: white;
            border: none;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .checkout-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .checkout-btn:disabled {
            background: var(--text-light);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 992px) {
            .checkout-sections {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .payment-section {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .checkout-header {
                padding: 30px 20px;
            }

            .checkout-header h1 {
                font-size: 2em;
            }

            .checkout-section,
            .payment-section {
                padding: 25px;
            }

            .item-content {
                flex-direction: column;
                text-align: center;
            }

            .item-total {
                text-align: center;
                margin-top: 10px;
            }

            .customer-details p {
                flex-direction: column;
                text-align: center;
                gap: 5px;
            }

            .customer-details strong,
            .customer-details span {
                min-width: auto;
                text-align: center;
            }

            .detail-item {
                flex-direction: column;
                text-align: center;
                gap: 5px;
            }

            .detail-label,
            .detail-value {
                min-width: auto;
                text-align: center;
            }

            .qr-code-image {
                max-width: 180px;
            }
        }

        @media (max-width: 576px) {
            .checkout-page {
                padding: 20px 0;
            }

            .checkout-container {
                padding: 0 15px;
            }

            .checkout-header {
                padding: 25px 15px;
            }

            .checkout-section,
            .payment-section {
                padding: 20px;
            }

            .design-previews {
                justify-content: center;
            }

            .qr-code-container {
                padding: 20px;
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
                    <img src="../assets/images/plainlogo.png" alt="Active Media" class="logo-image">
                    <span>Active Media Designs & Printing</span>
                </a>

                <ul class="nav-links">
                    <li><a href="main.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="ai_image.php"><i class="fas fa-robot"></i> AI Services</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="contact.php" class="active"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>

                <div class="user-info">
                    <a href="view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="../pages/website/profile.php" class="user-profile">
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
                    <a href="../accounts/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>

                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <!-- Checkout Section -->
    <section class="checkout-page">
        <div class="checkout-container">
            <div class="checkout-header">
                <h1><i class="fas fa-shopping-cart"></i> Checkout</h1>
                <p>Review your order and complete your purchase</p>
            </div>

            <div class="checkout-sections">
                <!-- Left Column: Order Details -->
                <div class="checkout-main">
                    <!-- Customer Information -->
                    <div class="checkout-section">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i> Customer Information
                        </h2>
                        <div class="customer-details">
                            <?php if ($user_data['customer_type'] === 'personal'): ?>
                                <p>
                                    <strong>Name:</strong>
                                    <span><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></span>
                                </p>
                                <p>
                                    <strong>Contact:</strong>
                                    <span><?php echo htmlspecialchars($user_data['personal_contact']); ?></span>
                                </p>
                                <p>
                                    <strong>Address:</strong>
                                    <span>
                                        <?php echo htmlspecialchars(
                                            $user_data['address_line1'] . ', ' .
                                                $user_data['personal_city'] . ', ' .
                                                $user_data['personal_province'] . ' ' .
                                                $user_data['personal_zip']
                                        ); ?>
                                    </span>
                                </p>
                            <?php elseif ($user_data['customer_type'] === 'company'): ?>
                                <p>
                                    <strong>Company:</strong>
                                    <span><?php echo htmlspecialchars($user_data['company_name']); ?></span>
                                </p>
                                <p>
                                    <strong>Contact Person:</strong>
                                    <span><?php echo htmlspecialchars($user_data['contact_person']); ?></span>
                                </p>
                                <p>
                                    <strong>Contact Number:</strong>
                                    <span><?php echo htmlspecialchars($user_data['company_contact']); ?></span>
                                </p>
                                <p>
                                    <strong>Address:</strong>
                                    <span>
                                        <?php echo htmlspecialchars(
                                            $user_data['subd_or_street'] . ' ' .
                                                $user_data['building_or_block'] . ' ' .
                                                $user_data['lot_or_room_no'] . ', ' .
                                                $user_data['barangay'] . ', ' .
                                                $user_data['company_city'] . ', ' .
                                                $user_data['company_province'] . ' ' .
                                                $user_data['company_zip']
                                        ); ?>
                                    </span>
                                </p>
                            <?php else: ?>
                                <p>No customer details found.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="checkout-section">
                        <h2 class="section-title">
                            <i class="fas fa-receipt"></i> Order Summary
                        </h2>
                        <div class="order-summary-items">
                            <?php foreach ($checkout_items as $item): ?>
                                <div class="summary-item">
                                    <div class="item-content">
                                        <div class="item-image">
                                            <?php
                                            $image_path = "../assets/images/services/service-" . $item['id'] . ".jpg";
                                            $image_url = file_exists($image_path) ? $image_path : "https://via.placeholder.com/100x100/2c5aa0/ffffff?text=Product";
                                            ?>
                                            <img src="<?php echo $image_url; ?>"
                                                alt="<?php echo $item['product_name']; ?>">
                                        </div>

                                        <div class="item-details">
                                            <p class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                            <p class="item-category"><?php echo htmlspecialchars($item['product_group']); ?></p>
                                            <p class="item-price">
                                                <?php if ($item['has_admin_price']): ?>
                                                    <span style="text-decoration: line-through; color: #999; margin-right: 10px;">
                                                        ₱<?php echo number_format($item['unit_price'], 2); ?>
                                                    </span>
                                                    <span style="color: #e74c3c; font-weight: bold;">
                                                        ₱<?php echo number_format($item['actual_price'], 2); ?>
                                                    </span>
                                                    <br><small style="color: #27ae60;">✓ Price confirmed by admin</small>
                                                <?php else: ?>
                                                    ₱<?php echo number_format($item['actual_price'], 2); ?> × <?php echo $item['quantity']; ?>
                                                <?php endif; ?>
                                            </p>

                                            <!-- Display all customization options -->
                                            <?php if (!empty($item['size_option']) || !empty($item['color_option']) || !empty($item['finish_option_name']) || !empty($item['paper_option_name']) || !empty($item['binding_option_name']) || !empty($item['layout_option_name']) || !empty($item['gsm_option'])): ?>
                                                <div class="printing-details">
                                                    <div class="details-row">
                                                        <?php
                                                        // Determine product type based on category
                                                        $category = strtolower($item['product_group']);
                                                        $isTshirt = strpos($category, 't-shirt') !== false || strpos($category, 'tshirt') !== false;
                                                        $isTote = strpos($category, 'tote') !== false;
                                                        $isPaperBag = strpos($category, 'paper bag') !== false;
                                                        $isMug = strpos($category, 'mug') !== false;
                                                        ?>

                                                        <!-- Size Options -->
                                                        <?php if (!empty($item['size_option'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">
                                                                    <?php if ($isTshirt): ?>
                                                                        T-Shirt Size:
                                                                    <?php elseif ($isTote): ?>
                                                                        Tote Bag Size:
                                                                    <?php elseif ($isPaperBag): ?>
                                                                        Paper Bag Size:
                                                                    <?php elseif ($isMug): ?>
                                                                        Mug Size:
                                                                    <?php else: ?>
                                                                        Size:
                                                                    <?php endif; ?>
                                                                </span>
                                                                <span class="detail-value">
                                                                    <?php
                                                                    if (!empty($item['size_option_name'])) {
                                                                        echo htmlspecialchars($item['size_option_name']);
                                                                        if ($isPaperBag && !empty($item['paperbag_dimensions'])) {
                                                                            echo '<br><small>(' . htmlspecialchars($item['paperbag_dimensions']) . ')</small>';
                                                                        }
                                                                    } else {
                                                                        echo htmlspecialchars($item['size_option']);
                                                                    }
                                                                    ?>
                                                                    <?php if (!empty($item['custom_size'])): ?>
                                                                        <br><small>Custom: <?php echo htmlspecialchars($item['custom_size']); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Color Options -->
                                                        <?php if (!empty($item['color_option'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">
                                                                    <?php if ($isTshirt): ?>
                                                                        T-Shirt Color:
                                                                    <?php elseif ($isTote): ?>
                                                                        Tote Bag Color:
                                                                    <?php elseif ($isMug): ?>
                                                                        Mug Color:
                                                                    <?php else: ?>
                                                                        Color:
                                                                    <?php endif; ?>
                                                                </span>
                                                                <span class="detail-value">
                                                                    <?php
                                                                    if (!empty($item['color_option_name'])) {
                                                                        echo htmlspecialchars($item['color_option_name']);
                                                                    } else {
                                                                        echo htmlspecialchars($item['color_option']);
                                                                    }
                                                                    ?>
                                                                    <?php if (!empty($item['custom_color'])): ?>
                                                                        <br><small>Custom: <?php echo htmlspecialchars($item['custom_color']); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Printing Options (only show for printing products) -->
                                                        <?php if (!$isTshirt && !$isTote && !$isPaperBag && !$isMug): ?>
                                                            <?php if (!empty($item['finish_option_name'])): ?>
                                                                <div class="detail-item">
                                                                    <span class="detail-label">Finish:</span>
                                                                    <span class="detail-value"><?php echo htmlspecialchars($item['finish_option_name']); ?></span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($item['paper_option_name'])): ?>
                                                                <div class="detail-item">
                                                                    <span class="detail-label">Paper:</span>
                                                                    <span class="detail-value"><?php echo htmlspecialchars($item['paper_option_name']); ?></span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($item['binding_option_name'])): ?>
                                                                <div class="detail-item">
                                                                    <span class="detail-label">Binding:</span>
                                                                    <span class="detail-value"><?php echo htmlspecialchars($item['binding_option_name']); ?></span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($item['layout_option_name'])): ?>
                                                                <div class="detail-item">
                                                                    <span class="detail-label">Layout Type:</span>
                                                                    <span class="detail-value">
                                                                        <?php echo htmlspecialchars($item['layout_option_name']); ?>
                                                                        <?php if (!empty($item['layout_details'])): ?>
                                                                            <br><small>Details: <?php echo htmlspecialchars($item['layout_details']); ?></small>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($item['gsm_option'])): ?>
                                                                <div class="detail-item">
                                                                    <span class="detail-label">GSM:</span>
                                                                    <span class="detail-value"><?php echo htmlspecialchars($item['gsm_option']); ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Custom Design Previews -->
                                            <?php
                                            if (!empty($item['design_image'])) {
                                                $designData = $item['design_image'];
                                                $frontMockup = '';
                                                $backMockup = '';
                                                $uploadedFile = '';
                                                $frontUploadedFile = '';
                                                $backUploadedFile = '';
                                                $uploadType = '';

                                                // Check if it's JSON format
                                                $isJson = false;
                                                $designArray = json_decode($designData, true);

                                                if (json_last_error() === JSON_ERROR_NONE && is_array($designArray)) {
                                                    $isJson = true;
                                                    $uploadType = $designArray['upload_type'] ?? 'single';
                                                    
                                                    // Get ALL images - FIXED: Extract all file types
                                                    $frontMockup = $designArray['front_mockup'] ?? '';
                                                    $backMockup = $designArray['back_mockup'] ?? '';
                                                    $uploadedFile = $designArray['uploaded_file'] ?? '';
                                                    $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                                                    $backUploadedFile = $designArray['back_uploaded_file'] ?? '';
                                                    
                                                } else {
                                                    // Try to fix JSON if it's malformed
                                                    if (preg_match('/\{.*\}/', $designData)) {
                                                        $fixedJson = str_replace('\"', '"', $designData);
                                                        $fixedJson = stripslashes($fixedJson);
                                                        
                                                        $designArray = json_decode($fixedJson, true);
                                                        if (json_last_error() === JSON_ERROR_NONE && is_array($designArray)) {
                                                            $isJson = true;
                                                            $uploadType = $designArray['upload_type'] ?? 'single';
                                                            $frontMockup = $designArray['front_mockup'] ?? '';
                                                            $backMockup = $designArray['back_mockup'] ?? '';
                                                            $uploadedFile = $designArray['uploaded_file'] ?? '';
                                                            $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                                                            $backUploadedFile = $designArray['back_uploaded_file'] ?? '';
                                                        }
                                                    } else {
                                                        // Legacy format - single image
                                                        $uploadedFile = $designData;
                                                        $uploadType = 'single';
                                                    }
                                                }

                                                // Display design previews if we have valid images
                                                $hasDesigns = !empty($frontMockup) || !empty($backMockup) || !empty($uploadedFile) || !empty($frontUploadedFile) || !empty($backUploadedFile);

                                                if ($hasDesigns): ?>
                                                    <div class="custom-design-section">
                                                        <div class="custom-design-title">
                                                            <i class="fas fa-palette"></i> Custom Design
                                                            <span style="font-size: 0.8em; color: var(--text-light); margin-left: 10px;">
                                                                (<?php echo $uploadType === 'single' ? 'Same design for both sides' : 'Different designs for front/back'; ?>)
                                                            </span>
                                                        </div>
                                                        <div class="design-previews">
                                                            <?php
                                                            // Show uploaded original files
                                                            if ($uploadType === 'single' && !empty($uploadedFile)):
                                                                $uploadedFilePath = "../assets/uploads/" . $uploadedFile;
                                                                $uploadedFileExists = file_exists($uploadedFilePath);
                                                            ?>
                                                                <div class="design-preview">
                                                                    <?php if ($uploadedFileExists): ?>
                                                                        <img src="<?php echo $uploadedFilePath; ?>"
                                                                            alt="Original Design File">
                                                                    <?php else: ?>
                                                                        <div class="design-placeholder">
                                                                            <i class="fas fa-file-image" style="font-size: 24px; color: #999;"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="design-label">Original File</div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($frontUploadedFile) || !empty($backUploadedFile)): ?>
                                                                <?php if (!empty($frontUploadedFile)):
                                                                    $frontUploadedFilePath = "../assets/uploads/" . $frontUploadedFile;
                                                                    $frontUploadedFileExists = file_exists($frontUploadedFilePath);
                                                                ?>
                                                                    <div class="design-preview">
                                                                        <?php if ($frontUploadedFileExists): ?>
                                                                            <img src="<?php echo $frontUploadedFilePath; ?>"
                                                                                alt="Front Original Design">
                                                                        <?php else: ?>
                                                                            <div class="design-placeholder">
                                                                                <i class="fas fa-file-image" style="font-size: 24px; color: #999;"></i>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <div class="design-label">Front Original</div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($backUploadedFile)):
                                                                    $backUploadedFilePath = "../assets/uploads/" . $backUploadedFile;
                                                                    $backUploadedFileExists = file_exists($backUploadedFilePath);
                                                                ?>
                                                                    <div class="design-preview">
                                                                        <?php if ($backUploadedFileExists): ?>
                                                                            <img src="<?php echo $backUploadedFilePath; ?>"
                                                                                alt="Back Original Design">
                                                                        <?php else: ?>
                                                                            <div class="design-placeholder">
                                                                                <i class="fas fa-file-image" style="font-size: 24px; color: #999;"></i>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        <div class="design-label">Back Original</div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>

                                                            <!-- Mockup Previews -->
                                                            <?php
                                                            // Front mockup
                                                            if (!empty($frontMockup)):
                                                                $frontMockupPath = "../assets/uploads/" . $frontMockup;
                                                                $frontMockupExists = file_exists($frontMockupPath);
                                                            ?>
                                                                <div class="design-preview">
                                                                    <?php if ($frontMockupExists): ?>
                                                                        <img src="<?php echo $frontMockupPath; ?>"
                                                                            alt="Front Mockup">
                                                                    <?php else: ?>
                                                                        <div class="design-placeholder" style="border-color: var(--primary-color);">
                                                                            <i class="fas fa-image" style="font-size: 24px; color: var(--primary-color);"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="design-label">Front Mockup</div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php
                                                            // Back mockup
                                                            if (!empty($backMockup)):
                                                                $backMockupPath = "../assets/uploads/" . $backMockup;
                                                                $backMockupExists = file_exists($backMockupPath);
                                                            ?>
                                                                <div class="design-preview">
                                                                    <?php if ($backMockupExists): ?>
                                                                        <img src="<?php echo $backMockupPath; ?>"
                                                                            alt="Back Mockup">
                                                                    <?php else: ?>
                                                                        <div class="design-placeholder" style="border-color: var(--primary-color);">
                                                                            <i class="fas fa-image" style="font-size: 24px; color: var(--primary-color);"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div class="design-label">Back Mockup</div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                            <?php endif;
                                            }
                                            ?>
                                        </div>

                                        <div class="item-total">
                                            ₱<?php echo number_format($item['actual_price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="summary-totals">
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span>₱<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Tax (3%):</span>
                                <span>₱<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="summary-row total-row">
                                <span>Total Amount:</span>
                                <span>₱<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Payment -->
                <div class="payment-section">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i> Payment Method
                    </h2>

                    <div class="qr-code-container">
                        <h3>GCash Payment</h3>
                        <div class="qr-code-image">
                            <img src="../assets/images/gcash-qr.jpg" alt="GCash QR Code">
                        </div>
                        <div class="payment-details">
                            <div class="payment-detail">
                                <strong>GCash Number:</strong>
                                <span>0998-791-****</span>
                            </div>
                            <div class="payment-detail">
                                <strong>Account Name:</strong>
                                <span>WI******A L.</span>
                            </div>
                            <div class="payment-detail">
                                <strong>Amount to Pay:</strong>
                                <span style="color: #27ae60; font-weight: bold;">₱<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <form action="../pages/website/process_order.php" method="post" enctype="multipart/form-data" class="upload-section">
                        <input type="hidden" name="selected_items" value="<?php echo implode(',', $selected_items); ?>">
                        <input type="hidden" name="total_amount" value="<?php echo $total; ?>">

                        <div class="file-upload">
                            <label for="payment_proof" class="file-upload-label">
                                <i class="fas fa-upload"></i> Upload Payment Proof
                            </label>
                            <input type="file" name="payment_proof" id="payment_proof" accept="image/*,.pdf" class="file-input" required>
                            <small style="display: block; margin-top: 8px; color: var(--text-light);">
                                Upload screenshot of your GCash payment confirmation (JPG, PNG, or PDF)
                            </small>
                        </div>

                        <div class="instructions">
                            <h4>Payment Instructions</h4>
                            <ul>
                                <li>Scan the QR code or send payment to our GCash number</li>
                                <li>Take a screenshot of your payment confirmation</li>
                                <li>Upload the screenshot as proof of payment</li>
                                <li>Your order will be processed within 24 hours</li>
                                <li>You will receive order updates via email/SMS</li>
                            </ul>
                        </div>

                        <button type="submit" class="checkout-btn" id="confirm-order-btn">
                            <i class="fas fa-check"></i> Confirm Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script src="../assets/js/main.js"></script>
    <script>
        // Checkout-specific JavaScript that extends the main script.js
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize checkout functionality
            setupCheckout();

            // Add mobile menu toggle
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

        function setupCheckout() {
            const confirmOrderBtn = document.getElementById('confirm-order-btn');
            const paymentProofInput = document.getElementById('payment_proof');
            const checkoutForm = document.querySelector('form');

            // File upload validation
            if (paymentProofInput) {
                paymentProofInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const fileSize = file.size / 1024 / 1024; // MB
                        const fileType = file.type;

                        // Validate file type
                        if (!fileType.match('image/*') && fileType !== 'application/pdf') {
                            alert('Please upload only image files (JPG, PNG) or PDF files.');
                            this.value = '';
                            return;
                        }

                        // Validate file size (max 5MB)
                        if (fileSize > 5) {
                            alert('File size must be less than 5MB.');
                            this.value = '';
                            return;
                        }

                        // Show file name
                        console.log('File selected:', file.name);
                    }
                });
            }

            // Form submission handling
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    if (!paymentProofInput.value) {
                        e.preventDefault();
                        alert('Please upload your payment proof before confirming the order.');
                        paymentProofInput.focus();
                        return;
                    }

                    // Show loading state
                    if (confirmOrderBtn) {
                        confirmOrderBtn.disabled = true;
                        confirmOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }

                    // Form will submit normally
                });
            }

            // Add smooth scrolling for better UX
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
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

            // Add print functionality for order summary
            const printOrderSummary = () => {
                const orderContent = document.querySelector('.checkout-main').innerHTML;
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Order Summary - Active Media</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .section-title { color: #2c3e50; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
                        .summary-item { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
                        .total-row { font-weight: bold; color: #27ae60; border-top: 2px solid #eee; padding-top: 10px; }
                    </style>
                </head>
                <body>
                    <h1>Order Summary - Active Media</h1>
                    ${orderContent}
                </body>
                </html>
            `);
                printWindow.document.close();
                printWindow.print();
            };

            // Add print button dynamically
            const printBtn = document.createElement('button');
            printBtn.innerHTML = '<i class="fas fa-print"></i> Print Order Summary';
            printBtn.style.cssText = `
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        `;
            printBtn.addEventListener('click', printOrderSummary);

            const orderSummarySection = document.querySelector('.checkout-section:last-child .summary-totals');
            if (orderSummarySection) {
                orderSummarySection.appendChild(printBtn);
            }
        }
    </script>
</body>

</html>