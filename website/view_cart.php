<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

/* ------------------------------
   1. Get USER info (personal or company)
--------------------------------*/
$userQuery = "SELECT 
                u.id,
                pc.first_name, pc.last_name,
                cc.company_name
              FROM users u
              LEFT JOIN personal_customers pc ON u.id = pc.user_id
              LEFT JOIN company_customers cc ON u.id = cc.user_id
              WHERE u.id = ?
              LIMIT 1";

$userStmt = $inventory->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user_data = $userResult->fetch_assoc();

/* ------------------------------
   2. Get CART items
--------------------------------*/
$query = "SELECT p.id, p.product_name, p.price AS unit_price, p.category AS product_group, 
                 ci.quantity, ci.item_id, ci.design_image, ci.added_at,
                 ci.layout_option, ci.layout_details, ci.gsm_option, ci.user_layout_files,
                 ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                 ci.finish_option, ci.paper_option, ci.binding_option,
                 po.option_name AS paper_option_name,
                 fo.option_name AS finish_option_name,
                 bo.option_name AS binding_option_name,
                 lo.option_name AS layout_option_name
          FROM cart_items ci
          JOIN products_offered p ON ci.product_id = p.id
          JOIN carts c ON ci.cart_id = c.cart_id
          LEFT JOIN paper_options po ON ci.paper_option = po.id
          LEFT JOIN finish_options fo ON ci.finish_option = fo.id
          LEFT JOIN binding_options bo ON ci.binding_option = bo.id
          LEFT JOIN layout_options lo ON ci.layout_option = lo.id
          WHERE c.user_id = ?
          ORDER BY ci.added_at DESC";

$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}

/* ------------------------------
   3. Handle selected items
--------------------------------*/
$selected_items = isset($_POST['selected_items']) && is_array($_POST['selected_items']) ? $_POST['selected_items'] : [];

$selected_total = 0;
foreach ($cart_items as $item) {
    if (in_array($item['item_id'], $selected_items)) {
        $selected_total += $item['unit_price'] * $item['quantity'];
    }
}

/* ------------------------------
   4. Handle checkout form submission
--------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_checkout'])) {
    if (empty($selected_items)) {
        echo "<script>alert('Please select at least one item to checkout.');</script>";
    } else {
        header("Location: checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Active Media</title>
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
        
        .cart-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .cart-header h1 {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .cart-header p {
            font-size: 1.2em;
            color: #666;
        }
        
        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .select-all {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .select-all input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .select-all label {
            font-weight: 500;
            cursor: pointer;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
        }
        
        .bulk-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .remove-selected-btn {
            background: #dc3545;
            color: white;
        }
        
        .remove-selected-btn:hover {
            background: #c82333;
        }
        
        .cart-items {
            margin-bottom: 40px;
        }
        
        .cart-item {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            align-items: flex-start;
            position: relative;
        }
        
        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
        }
        
        .item-checkbox {
            margin-right: 20px;
            margin-top: 10px;
            z-index: 2;
        }
        
        .item-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .cart-item-content {
            display: flex;
            flex-grow: 1;
            gap: 25px;
            position: relative;
        }
        
        .product-link-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            cursor: pointer;
            z-index: 1;
        }
        
        .cart-item-image {
            flex-shrink: 0;
            z-index: 2;
            position: relative;
        }
        
        .cart-item-image img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #f8f9fa;
            transition: transform 0.3s ease;
        }
        
        .cart-item:hover .cart-item-image img {
            transform: scale(1.05);
        }
        
        .cart-item-info {
            flex-grow: 1;
            z-index: 2;
            position: relative;
        }
        
        .cart-item-info h3 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1.3em;
        }
        
        .product-group {
            background: #f1f8ff;
            color: #007bff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .price {
            color: #e74c3c;
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .custom-design {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed #007bff;
        }
        
        .custom-design-title {
            font-weight: bold;
            margin-bottom: 12px;
            color: #007bff;
            font-size: 1.1em;
        }
        
        .design-previews {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .design-preview {
            text-align: center;
            flex: 0 0 auto;
        }

        .design-preview img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 8px;
            border: 2px solid #007bff;
            padding: 5px;
            background: white;
            transition: transform 0.3s ease;
        }

        .design-preview img:hover {
            transform: scale(1.05);
        }

        .design-label {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
            font-weight: 500;
        }

        .design-preview:has(img[alt*="Original"]) img {
            border-color: #28a745;
        }

        .design-preview:has(img[alt*="Mockup"]) img {
            border-color: #007bff;
        }
        
        .design-label {
            font-size: 0.8em;
            color: #666;
            margin-top: 5px;
        }
        
        .subtotal {
            color: #27ae60;
            font-weight: bold;
            font-size: 1.2em;
            margin-top: 15px;
        }
        
        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            min-width: 180px;
            justify-content: center;
            z-index: 2;
            position: relative;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .quantity-input {
            width: 80px;
            padding: 10px;
            text-align: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1.2em;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .quantity-btn:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .update-btn, .remove-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .update-btn {
            background: #007bff;
            color: white;
        }
        
        .update-btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .remove-btn {
            background: #dc3545;
            color: white;
        }
        
        .remove-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .cart-summary {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .summary-title {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            font-size: 1.1em;
        }
        
        .summary-row.total {
            font-size: 1.4em;
            font-weight: bold;
            color: #27ae60;
            border-bottom: none;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        
        .cart-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }
        
        .cart-btn {
            padding: 18px 35px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1em;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .continue-shopping {
            background: #6c757d;
            color: white;
        }
        
        .continue-shopping:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }
        
        .checkout-btn {
            background: #28a745;
            color: white;
        }
        
        .checkout-btn:hover {
            background: #218838;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }
        
        .empty-cart {
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .empty-cart i {
            font-size: 5em;
            color: #6c757d;
            margin-bottom: 25px;
            opacity: 0.7;
        }
        
        .empty-cart h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 2em;
        }
        
        .empty-cart p {
            color: #666;
            font-size: 1.2em;
            margin-bottom: 30px;
        }
        
        .start-shopping {
            background: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .start-shopping:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        }

        .printing-details {
        margin-top: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        font-size: 0.9em;
    }

    .details-row {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .printing-details {
        margin-top: 15px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        font-size: 0.9em;
    }
    .details-row {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .detail-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
    }
    .detail-label {
        font-weight: 600;
        color: #2c3e50;
        min-width: 120px;
    }
    .detail-value {
        color: #495057;
        flex: 1;
    }

        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .cart-item-content {
                flex-direction: column;
                text-align: center;
            }
            
            .item-checkbox {
                margin-right: 0;
                margin-bottom: 15px;
                align-self: center;
            }
            
            .cart-item-image {
                margin-right: 0;
            }
            
            .cart-item-actions {
                flex-direction: row;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .cart-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .cart-buttons {
                flex-direction: column;
            }
            
            .design-previews {
                justify-content: center;
            }
            
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="main.php" class="logo">
                    <i class="fas fa-print"></i>
                    Active Media
                </a>
                
                <div class="user-info">
                    <a href="view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo array_sum(array_column($cart_items, 'quantity')); ?></span>
                    </a>
                    <a href="../pages/website/profile.php">
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
                    <a href="../accounts/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="cart-header">
            <h1><i class="fas fa-shopping-cart"></i> Your Shopping Cart</h1>
            <p>Review and manage your items before checkout</p>
        </div>

        <?php if (!empty($cart_items)): ?>
            <form method="post" id="cartForm">
                <div class="cart-actions">
                    <div class="select-all">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        <label for="selectAll">Select All Items</label>
                    </div>
                    
                    <div class="bulk-actions">
                        <button type="submit" name="remove_selected" class="bulk-btn remove-selected-btn" onclick="return confirm('Are you sure you want to remove the selected items?')">
                            <i class="fas fa-trash"></i> Remove Selected
                        </button>
                    </div>
                </div>

                <div class="cart-items">
                    <?php foreach ($cart_items as $row): 
                        $item_total = $row['unit_price'] * $row['quantity'];
                        $is_selected = in_array($row['item_id'], $selected_items);
                    ?>
                        <div class="cart-item">
                            <div class="item-checkbox">
                                <input type="checkbox" name="selected_items[]" value="<?php echo $row['item_id']; ?>" 
                                    <?php echo $is_selected ? 'checked' : ''; ?>
                                    onchange="updateCartTotal()">
                            </div>
                            
                            <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>" class="product-link-overlay" title="View product details"></a>
                            
                            <div class="cart-item-content">
                                <div class="cart-item-image">
                                    <?php
                                    $image_path = "../assets/images/services/service-" . $row['id'] . ".jpg";
                                    $image_url = file_exists($image_path) ? $image_path : "https://via.placeholder.com/120x120/007bff/ffffff?text=Product";
                                    ?>
                                    <img src="<?php echo $image_url; ?>" 
                                         alt="<?php echo $row['product_name']; ?>">
                                </div>

                                <div class="cart-item-info">
                                    <h3><?php echo $row['product_name']; ?></h3>
                                    <span class="product-group"><?php echo $row['product_group']; ?></span>
                                    <p class="price">₱<?php echo number_format($row['unit_price'], 2); ?></p>

                                    <!-- Product Details for RISO, Offset, and Digital Printing -->
                                    <?php
                                    $productGroup = strtolower($row['product_group']);
                                    $isPrintingProduct = in_array($productGroup, ['riso', 'offset', 'digital', 'riso printing', 'offset printing', 'digital printing']);

                                    if ($isPrintingProduct && (!empty($row['layout_option']) || !empty($row['gsm_option']) || !empty($row['finish_option']) || !empty($row['paper_option']) || !empty($row['binding_option']) || !empty($row['size_option']))):
                                    ?>
                                        <div class="printing-details">
                                            <div class="details-row">
                                                <?php if (!empty($row['size_option'])): ?>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Size:</span>
                                                        <span class="detail-value">
                                                            <?php echo htmlspecialchars($row['size_option']); ?>
                                                            <?php if (!empty($row['custom_size'])): ?>
                                                                <br><small>Custom: <?php echo htmlspecialchars($row['custom_size']); ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($row['finish_option_name'])): ?>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Finish:</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($row['finish_option_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($row['paper_option_name'])): ?>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Paper:</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($row['paper_option_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($row['binding_option_name'])): ?>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Binding:</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($row['binding_option_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($row['layout_option_name'])): ?>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Layout Type:</span>
                                                        <span class="detail-value">
                                                            <?php echo htmlspecialchars($row['layout_option_name']); ?>
                                                            <?php if (!empty($row['layout_details'])): ?>
                                                                <br><small>Details: <?php echo htmlspecialchars($row['layout_details']); ?></small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($row['gsm_option'])): ?>
                                                    <div class="detail-item">
                                                        <span class="detail-label">GSM:</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($row['gsm_option']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Custom Design Section -->
                                    <?php
                                    if (!empty($row['design_image'])) {
                                        $designData = $row['design_image'];
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
                                            
                                            // Get mockup images
                                            $frontMockup = $designArray['front_mockup'] ?? '';
                                            $backMockup = $designArray['back_mockup'] ?? '';
                                            
                                            // Get uploaded files based on upload type
                                            if ($uploadType === 'single') {
                                                $uploadedFile = $designArray['uploaded_file'] ?? '';
                                            } else {
                                                $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                                                $backUploadedFile = $designArray['back_uploaded_file'] ?? '';
                                            }
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
                                                    
                                                    if ($uploadType === 'single') {
                                                        $uploadedFile = $designArray['uploaded_file'] ?? '';
                                                    } else {
                                                        $frontUploadedFile = $designArray['front_uploaded_file'] ?? '';
                                                        $backUploadedFile = $designArray['back_uploaded_file'] ?? '';
                                                    }
                                                }
                                            } else {
                                                // Legacy format - single image
                                                $frontMockup = $designData;
                                                $uploadType = 'single';
                                            }
                                        }
                                        
                                        // Display design previews if we have valid images
                                        $hasDesigns = !empty($frontMockup) || !empty($backMockup) || !empty($uploadedFile) || !empty($frontUploadedFile) || !empty($backUploadedFile);
                                        
                                        if ($hasDesigns): ?>
                                            <div class="custom-design">
                                                <div class="custom-design-title">
                                                    <i class="fas fa-palette"></i> Your Custom Design 
                                                    <span style="font-size: 0.8em; color: #666; margin-left: 10px;">
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
                                                                <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
                                                                    <i class="fas fa-file-image" style="font-size: 24px; color: #999;"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="design-label">Original File</div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($uploadType === 'separate'): ?>
                                                        <?php if (!empty($frontUploadedFile)): 
                                                            $frontUploadedFilePath = "../assets/uploads/" . $frontUploadedFile;
                                                            $frontUploadedFileExists = file_exists($frontUploadedFilePath);
                                                            ?>
                                                            <div class="design-preview">
                                                                <?php if ($frontUploadedFileExists): ?>
                                                                    <img src="<?php echo $frontUploadedFilePath; ?>" 
                                                                        alt="Front Original Design">
                                                                <?php else: ?>
                                                                    <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
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
                                                                    <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
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
                                                                <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #007bff;">
                                                                    <i class="fas fa-image" style="font-size: 24px; color: #007bff;"></i>
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
                                                                <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #007bff;">
                                                                    <i class="fas fa-image" style="font-size: 24px; color: #007bff;"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="design-label">Back Mockup</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Design Information -->
                                                <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px; font-size: 0.9em;">
                                                    <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                                        <span><strong>Upload Type:</strong> <?php echo ucfirst($uploadType); ?></span>
                                                        <?php if ($uploadType === 'single' && !empty($uploadedFile)): ?>
                                                            <span><strong>Original File:</strong> <?php echo basename($uploadedFile); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($uploadType === 'separate'): ?>
                                                            <?php if (!empty($frontUploadedFile)): ?>
                                                                <span><strong>Front File:</strong> <?php echo basename($frontUploadedFile); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($backUploadedFile)): ?>
                                                                <span><strong>Back File:</strong> <?php echo basename($backUploadedFile); ?></span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif;
                                    }
                                    ?>
                                    
                                    <p class="subtotal">Subtotal: ₱<?php echo number_format($item_total, 2); ?></p>
                                </div>
                            </div>

                            <div class="cart-item-actions">
                                <div class="quantity-controls">
                                    <button type="button" class="quantity-btn decrease">-</button>
                                    <input type="number" 
                                        name="quantity" 
                                        class="quantity-input"
                                        value="<?php echo $row['quantity']; ?>" 
                                        min="1">
                                    <button type="button" class="quantity-btn increase">+</button>
                                </div>

                                <button type="button" class="remove-btn">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                                <input type="hidden" name="item_id" value="<?php echo $row['item_id']; ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h2 class="summary-title">Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Subtotal (<span id="selected-count"><?php echo count($selected_items); ?></span> items):</span>
                        <span id="subtotal-amount">₱<?php echo number_format($selected_total, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (10%):</span>
                        <span id="tax-amount">₱<?php echo number_format($selected_total * 0.1, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total-amount">₱<?php echo number_format($selected_total + ($selected_total * 0.1), 2); ?></span>
                    </div>
                </div>

                <div class="cart-buttons">
                    <a href="main.php" class="cart-btn continue-shopping">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                    <button type="submit" name="proceed_to_checkout" class="cart-btn checkout-btn">
                        <i class="fas fa-lock"></i> Proceed to Checkout
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="landing.php" class="start-shopping">
                    <i class="fas fa-store"></i> Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Store product prices and quantities for JavaScript calculations
    const productData = {
        <?php foreach ($cart_items as $item): ?>
            <?php echo $item['item_id']; ?>: {
                price: <?php echo $item['unit_price']; ?>,
                quantity: <?php echo $item['quantity']; ?>,
                subtotal: <?php echo $item['unit_price'] * $item['quantity']; ?>
            },
        <?php endforeach; ?>
    };

    // Enhanced JavaScript for better UX with AJAX
    document.addEventListener('DOMContentLoaded', function() {
        setupQuantityControls();
        setupRemoveButtons();
        setupBulkRemove();
        
        // Real-time quantity validation
        const quantityInputs = document.querySelectorAll('.quantity-input');
        quantityInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 1) {
                    this.value = 1;
                }
            });
        });
        
        // Prevent overlay click from affecting checkboxes and buttons
        const overlays = document.querySelectorAll('.product-link-overlay');
        overlays.forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                // Don't trigger if clicking on interactive elements
                if (e.target.closest('.item-checkbox') || 
                    e.target.closest('.cart-item-actions') ||
                    e.target.closest('.quantity-controls') ||
                    e.target.tagName === 'INPUT' ||
                    e.target.tagName === 'BUTTON') {
                    e.preventDefault();
                    return;
                }
                
                // Allow the link to work normally
                window.location = this.href;
            });
        });
        
        updateSelectAll();
        updateCartTotal(); // Initialize the total display
    });

    // Setup quantity controls with AJAX
    function setupQuantityControls() {
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const controls = this.closest('.quantity-controls');
                const input = controls.querySelector('.quantity-input');
                const itemId = this.closest('.cart-item').querySelector('input[name="item_id"]').value;
                
                if (this.textContent.includes('+') || this.classList.contains('increase')) {
                    input.stepUp();
                } else {
                    input.stepDown();
                    if (input.value < 1) input.value = 1;
                }
                
                updateCartItem(itemId, input.value);
            });
        });
        
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 1) {
                    this.value = 1;
                }
                
                const itemId = this.closest('.cart-item').querySelector('input[name="item_id"]').value;
                updateCartItem(itemId, this.value);
            });
        });
    }

    function setupRemoveButtons() {
        document.querySelectorAll('.remove-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to remove this item from your cart?')) {
                    return;
                }
                
                const itemId = this.closest('.cart-item').querySelector('input[name="item_id"]').value;
                removeCartItem(itemId);
            });
        });
    }

    // Handle bulk remove selected items
    function setupBulkRemove() {
        const bulkRemoveBtn = document.querySelector('.remove-selected-btn');
        if (bulkRemoveBtn) {
            bulkRemoveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
                if (selectedItems.length === 0) {
                    alert('Please select at least one item to remove.');
                    return;
                }
                
                if (!confirm(`Are you sure you want to remove ${selectedItems.length} selected item(s) from your cart?`)) {
                    return;
                }
                
                removeSelectedItems(selectedItems);
            });
        }
    }

    // Remove selected items via AJAX
    function removeSelectedItems(selectedItems) {
        const itemIds = Array.from(selectedItems).map(item => item.value);
        
        const formData = new FormData();
        formData.append('action', 'remove_selected');
        itemIds.forEach(id => {
            formData.append('selected_items[]', id);
        });
        
        fetch('../pages/website/update_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Bulk remove response:', data);
            if (data.status === 'success' || data.status === 'partial') {
                alert(data.message);
                location.reload(); // Reload to reflect changes
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error removing selected items');
        });
    }

    // Update cart item via AJAX
    function updateCartItem(itemId, quantity) {
        showLoading(itemId);
        
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('item_id', itemId);
        formData.append('quantity', quantity);
        
        // Make sure this path is correct
        fetch('../pages/website/update_cart.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Update response:', data); // For debugging
            if (data.status === 'success') {
                // Update the product data
                if (productData[itemId]) {
                    productData[itemId].quantity = parseInt(quantity);
                    productData[itemId].subtotal = productData[itemId].price * parseInt(quantity);
                }
                
                // Update the UI
                updateItemSubtotal(itemId);
                updateCartTotal();
                updateCartCount();
                
                showSuccess(itemId, 'Quantity updated');
            } else {
                showError(itemId, 'Error: ' + data.message);
                // Revert the input value
                const input = document.querySelector(`input[name="item_id"][value="${itemId}"]`)
                    .closest('.cart-item')
                    .querySelector('.quantity-input');
                input.value = productData[itemId].quantity;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError(itemId, 'Network error updating quantity');
            // Revert the input value
            const input = document.querySelector(`input[name="item_id"][value="${itemId}"]`)
                .closest('.cart-item')
                .querySelector('.quantity-input');
            input.value = productData[itemId].quantity;
        });
    }

    // Remove cart item via AJAX
    function removeCartItem(itemId) {
        showLoading(itemId);
        
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('item_id', itemId);
        
        // Make sure this path is correct - same as update
        fetch('../pages/website/update_cart.php', { // Changed from cart_count.php
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Remove response:', data); // For debugging
            if (data.status === 'success') {
                // Remove the item from the DOM
                const itemElement = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
                itemElement.style.opacity = '0.5';
                
                setTimeout(() => {
                    itemElement.remove();
                    
                    // Remove from productData
                    delete productData[itemId];
                    
                    // Update the UI
                    updateCartTotal();
                    updateCartCount();
                    updateSelectAll();
                    
                    // Check if cart is empty
                    if (document.querySelectorAll('.cart-item').length === 0) {
                        location.reload(); // Reload to show empty cart message
                    }
                }, 500);
            } else {
                showError(itemId, 'Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError(itemId, 'Network error removing item');
        });
    }

    // Show loading state
    function showLoading(itemId) {
        const itemElement = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
        itemElement.style.opacity = '0.7';
        itemElement.style.pointerEvents = 'none';
    }

    // Show success message
    function showSuccess(itemId, message) {
        const itemElement = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
        itemElement.style.opacity = '1';
        itemElement.style.pointerEvents = 'auto';
        
        // Optional: Show a temporary success message
        const successMsg = document.createElement('div');
        successMsg.textContent = message;
        successMsg.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #4CAF50; color: white; padding: 5px 10px; border-radius: 4px; font-size: 12px; z-index: 10;';
        itemElement.appendChild(successMsg);
        
        setTimeout(() => {
            successMsg.remove();
        }, 2000);
    }

    // Show error message
    function showError(itemId, message) {
        const itemElement = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
        itemElement.style.opacity = '1';
        itemElement.style.pointerEvents = 'auto';
        
        alert(message); // Or show a more elegant error message
    }

    // Update item subtotal - FIXED: Changed $ to ₱
    function updateItemSubtotal(itemId) {
        const itemElement = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
        const subtotalElement = itemElement.querySelector('.subtotal');
        
        if (subtotalElement && productData[itemId]) {
            subtotalElement.textContent = `Subtotal: ₱${productData[itemId].subtotal.toFixed(2)}`; // FIXED: Changed $ to ₱
        }
    }

    // Update cart count in navigation
    function updateCartCount() {
        let totalItems = 0;
        Object.values(productData).forEach(item => {
            totalItems += item.quantity;
        });
        
        const cartCountElement = document.querySelector('.cart-count');
        if (cartCountElement) {
            cartCountElement.textContent = totalItems;
        }
    }

    // Toggle select all checkboxes
    function toggleSelectAll(checkbox) {
        const itemCheckboxes = document.querySelectorAll('input[name="selected_items[]"]');
        itemCheckboxes.forEach(item => {
            item.checked = checkbox.checked;
        });
        updateCartTotal();
    }

    // Update select all checkbox based on individual selections
    function updateSelectAll() {
        const itemCheckboxes = document.querySelectorAll('input[name="selected_items[]"]');
        const selectAll = document.getElementById('selectAll');
        
        if (itemCheckboxes.length > 0) {
            const allChecked = Array.from(itemCheckboxes).every(checkbox => checkbox.checked);
            const someChecked = Array.from(itemCheckboxes).some(checkbox => checkbox.checked);
            
            selectAll.checked = allChecked;
            selectAll.indeterminate = someChecked && !allChecked;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
    }

    // Update cart total based on selected items - FIXED: Changed $ to ₱ and shipping to 40.00
    function updateCartTotal() {
        const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
        let subtotal = 0;
        
        selectedItems.forEach(item => {
            const itemId = item.value;
            if (productData[itemId]) {
                subtotal += productData[itemId].subtotal;
            }
        });
        
        const tax = subtotal * 0.1;
        const total = subtotal + tax;
        
        // Update the display
        document.getElementById('selected-count').textContent = selectedItems.length;
        document.getElementById('subtotal-amount').textContent = '₱' + subtotal.toFixed(2);
        document.getElementById('tax-amount').textContent = '₱' + tax.toFixed(2);
        document.getElementById('total-amount').textContent = '₱' + total.toFixed(2);
        
        updateSelectAll();
    }

    // Validate checkout - ensure at least one item is selected
    function validateCheckout() {
        const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
        if (selectedItems.length === 0) {
            alert('Please select at least one item to checkout.');
            return false;
        }
        alert('Proceeding to checkout with ' + selectedItems.length + ' selected items.');
        // Here you would typically redirect to checkout page with selected items
        return true;
    }

    </script>
</body>
</html>

<?php
// Close connection
$inventory->close();
?>