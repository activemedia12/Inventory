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
   2. Get CART items with individual pricing data
--------------------------------*/
// Modified query to get pricing data per cart item
$query = "SELECT p.id, p.product_name, p.price AS unit_price, p.category AS product_group, 
                 ci.quantity, ci.item_id, ci.design_image, ci.added_at,
                 ci.quoted_price, ci.price_updated_by_admin,
                 ci.layout_option, ci.layout_details, ci.gsm_option, ci.user_layout_files,
                 ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                 ci.finish_option, ci.paper_option, ci.binding_option,
                 po.option_name AS paper_option_name,
                 fo.option_name AS finish_option_name,
                 bo.option_name AS binding_option_name,
                 lo.option_name AS layout_option_name,
                 c.cart_id,
                 -- Get individual pricing request data for each cart item
                 pri.admin_notes, pri.status AS pricing_status
          FROM cart_items ci
          JOIN products_offered p ON ci.product_id = p.id
          JOIN carts c ON ci.cart_id = c.cart_id
          LEFT JOIN paper_options po ON ci.paper_option = po.id
          LEFT JOIN finish_options fo ON ci.finish_option = fo.id
          LEFT JOIN binding_options bo ON ci.binding_option = bo.id
          LEFT JOIN layout_options lo ON ci.layout_option = lo.id
          -- Join with pricing_requests_items to get per-item pricing data
          LEFT JOIN pricing_requests_items pri ON ci.item_id = pri.cart_item_id
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
// Initialize selected items from session or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items'])) {
    // Update selected items from POST
    $_SESSION['selected_cart_items'] = $_POST['selected_items'];
    $selected_items = $_SESSION['selected_cart_items'];
} else {
    // Get selected items from session
    $selected_items = isset($_SESSION['selected_cart_items']) ? $_SESSION['selected_cart_items'] : [];
}

$selected_total = 0;
foreach ($cart_items as $item) {
    if (in_array($item['item_id'], $selected_items)) {
        // Use admin price if available, otherwise use unit price
        $actual_price = $item['price_updated_by_admin'] && $item['quoted_price'] > 0 
            ? $item['quoted_price'] 
            : $item['unit_price'];
        $selected_total += $actual_price * $item['quantity'];
    }
}

/* ------------------------------
   4. Handle price request submission
--------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_pricing'])) {
    if (empty($selected_items)) {
        echo "<script>alert('Please select at least one item to request pricing.');</script>";
    } else {
        error_log("=== PRICING REQUEST SUBMISSION START ===");
        error_log("User ID: $user_id");
        error_log("Selected Items Count: " . count($selected_items));
        error_log("Selected Total: $selected_total");

        // Store the selected items in session for the pricing request
        $_SESSION['pricing_request_items'] = $selected_items;

        // Send notification to admin and save to database
        $request_id = sendPricingRequestNotification($user_id, $selected_items, $selected_total);

        if ($request_id) {
            error_log("SUCCESS: Pricing request created with ID: $request_id");
            unset($_SESSION['selected_cart_items']);
            echo "<script>
                alert('Your pricing request #$request_id has been sent to our team. We will contact you shortly with the final pricing.');
                window.location.href = 'view_cart.php';
            </script>";
        } else {
            error_log("FAILED: Pricing request creation failed");
            // Get the last database error for more details
            global $inventory;
            $db_error = $inventory ? $inventory->error : "No database connection";
            error_log("Database Error: $db_error");

            echo "<script>
                alert('There was an error submitting your pricing request. Please try again. If the problem persists, contact support.');
                console.error('Pricing request error: $db_error');
            </script>";
        }
        error_log("=== PRICING REQUEST SUBMISSION END ===");
        exit;
    }
}

/* ------------------------------
   5. Function to send pricing request notification
--------------------------------*/
function sendPricingRequestNotification($user_id, $selected_items, $total_estimate) {
    global $inventory;

    error_log("=== PRICING REQUEST DEBUG ===");
    error_log("User ID: $user_id");
    error_log("Selected Items: " . print_r($selected_items, true));
    error_log("Total Estimate: $total_estimate");

    // Check database connection
    if (!$inventory) {
        error_log("ERROR: No database connection object");
        return false;
    }

    if ($inventory->connect_error) {
        error_log("ERROR: Database connection error: " . $inventory->connect_error);
        return false;
    }

    error_log("Database connection OK");

    try {
        // Convert selected items array to JSON
        $items_json = json_encode($selected_items);
        if ($items_json === false) {
            error_log("ERROR: JSON encoding failed");
            return false;
        }
        error_log("Items JSON: $items_json");

        // Get the user's cart_id
        $cart_query = "SELECT cart_id FROM carts WHERE user_id = ?";
        $cart_stmt = $inventory->prepare($cart_query);
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        $cart_data = $cart_result->fetch_assoc();
        $cart_id = $cart_data['cart_id'];

        // Check if a pricing request already exists for this cart
        $check_query = "SELECT id FROM pricing_requests WHERE cart_id = ? AND status = 'pending'";
        $check_stmt = $inventory->prepare($check_query);
        $check_stmt->bind_param("i", $cart_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update existing pricing request instead of creating new one
            $update_query = "UPDATE pricing_requests SET selected_items = ?, estimated_total = ?, request_date = NOW() WHERE cart_id = ? AND status = 'pending'";
            $update_stmt = $inventory->prepare($update_query);
            $update_stmt->bind_param("sdi", $items_json, $total_estimate, $cart_id);
            $execute_result = $update_stmt->execute();
            
            if (!$execute_result) {
                error_log("ERROR: Update failed: " . $update_stmt->error);
                return false;
            }
            
            $request_id = $check_result->fetch_assoc()['id'];
            error_log("SUCCESS: Existing pricing request updated with ID: $request_id");
        } else {
            // Create new pricing request
            $query = "INSERT INTO pricing_requests (user_id, cart_id, selected_items, estimated_total) VALUES (?, ?, ?, ?)";
            error_log("Query: $query");
                    
            $stmt = $inventory->prepare($query);
            if (!$stmt) {
                error_log("ERROR: Prepare failed: " . $inventory->error);
                return false;
            }
            error_log("Statement prepared successfully");

            // Bind parameters
            $bind_result = $stmt->bind_param("iisd", $user_id, $cart_id, $items_json, $total_estimate);
            if (!$bind_result) {
                error_log("ERROR: Bind failed: " . $stmt->error);
                return false;
            }
            error_log("Parameters bound successfully");

            // Execute
            $execute_result = $stmt->execute();
            if (!$execute_result) {
                error_log("ERROR: Execute failed: " . $stmt->error);
                return false;
            }
            error_log("Execute successful");

            $request_id = $inventory->insert_id;
            error_log("SUCCESS: New pricing request created with ID: $request_id");
        }
        
        return $request_id;
        
    } catch (Exception $e) {
        error_log("EXCEPTION: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_checkout'])) {
    if (empty($selected_items)) {
        echo "<script>alert('There was an error checking out.');</script>";
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
    <title>Shopping Cart</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* Your existing CSS styles remain the same */
        .cart-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 70vh;
        }
        .cart-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px;
            background: var(--bg-white);
            box-shadow: var(--shadow);
        }

        .cart-header h1 {
            font-size: 2.5em;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .cart-header p {
            font-size: 1.2em;
            color: var(--text-light);
        }

        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--bg-white);
            box-shadow: var(--shadow);
        }

        .select-all {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .select-all input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .select-all label {
            font-weight: 600;
            cursor: pointer;
            color: var(--text-dark);
        }

        .bulk-actions {
            display: flex;
            gap: 15px;
        }

        .bulk-btn {
            padding: 12px 24px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .remove-selected-btn {
            background: var(--accent-color);
            color: white;
        }

        .remove-selected-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .cart-items {
            margin-bottom: 40px;
        }

        .cart-item {
            display: flex;
            background: var(--bg-white);
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            align-items: flex-start;
            position: relative;
            border: 1px solid var(--border-color);
        }

        .cart-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
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
            accent-color: var(--primary-color);
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
            width: 140px;
            height: 140px;
            object-fit: cover;
            border: 2px solid var(--border-color);
            transition: var(--transition);
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
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 1.3em;
        }

        .product-group {
            background: var(--primary-color);
            color: white;
            padding: 6px 14px;
            font-size: 0.9em;
            display: inline-block;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .price {
            color: var(--accent-color);
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 10px;
        }

        .custom-design {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed var(--primary-color);
        }

        .custom-design-title {
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .design-previews {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .design-preview {
            text-align: center;
            flex: 0 0 auto;
        }

        .design-preview img {
            width: 120px;
            height: 120px;
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
            font-size: 0.85em;
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
            padding: 12px;
            text-align: center;
            border: 2px solid var(--border-color);
            font-size: 1.1em;
            font-weight: bold;
            background: var(--bg-white);
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            background: var(--bg-light);
            border: 2px solid var(--border-color);
            font-size: 1.2em;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .update-btn,
        .remove-btn {
            padding: 12px 20px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .update-btn {
            background: var(--primary-color);
            color: white;
        }

        .update-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .remove-btn {
            background: var(--accent-color);
            color: white;
        }

        .remove-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .cart-summary {
            background: var(--bg-white);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .summary-title {
            font-size: 1.8em;
            color: var(--text-dark);
            margin-bottom: 20px;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 1.1em;
        }

        .summary-row.total {
            font-size: 1.4em;
            font-weight: bold;
            color: #27ae60;
            border-bottom: none;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }

        .cart-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .cart-btn {
            padding: 18px 35px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1em;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .continue-shopping {
            background: var(--text-light);
            color: white;
            cursor: pointer;
        }

        .continue-shopping:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }

        .checkout-btn {
            cursor: pointer;
            background: #28a745;
            color: white;
            border: none;
        }

        .checkout-btn:hover {
            background: #218838;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .empty-cart {
            text-align: center;
            padding: 80px 30px;
            background: var(--bg-white);
            box-shadow: var(--shadow);
        }

        .empty-cart i {
            font-size: 5em;
            color: var(--text-light);
            margin-bottom: 25px;
            opacity: 0.7;
        }

        .empty-cart h2 {
            color: var(--text-dark);
            margin-bottom: 20px;
            font-size: 2em;
        }

        .empty-cart p {
            color: var(--text-light);
            font-size: 1.2em;
            margin-bottom: 30px;
        }

        .start-shopping {
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            padding: 25px 20px 0 20px;
            gap: 8px;
        }

        .start-shopping:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        }

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
            padding: 5px 0;
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

        .design-info {
            margin-top: 15px;
            padding: 12px;
            background: var(--bg-light);
            font-size: 0.85em;
        }

        .design-info-row {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 5px;
        }

        .design-info-row:last-child {
            margin-bottom: 0;
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
        }

        @media (max-width: 576px) {
            .cart-header {
                padding: 30px 20px;
            }

            .cart-header h1 {
                font-size: 2em;
            }

            .cart-item {
                padding: 20px;
            }

            .cart-item-image img {
                width: 100px;
                height: 100px;
            }

            .design-previews {
                gap: 10px;
            }

            .design-preview img {
                width: 80px;
                height: 80px;
            }
        }

        .pricing-info {
            background: #e8f4fd;
            border-left: 4px solid #2196F3;
            padding: 40px 40px 20px 40px;
            margin-bottom: 20px;
        }

        .pricing-info ul {
            margin-left: 40px;
        }

        .pricing-info h3 {
            color: #1976D2;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pricing-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 0.95em;
        }

        .request-pricing-btn {
            background: #2196F3 !important;
            color: white !important;
            border: none;
        }

        .request-pricing-btn:hover {
            background: #1976D2 !important;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.3);
        }

        .estimated-total {
            font-size: 1.1em;
            color: #1976D2;
            font-weight: 600;
            margin: 10px 0;
        }

        .admin-price-notice {
            padding: 8px 0;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .pricing-status-alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .pricing-status-alert p {
            margin: 0;
        }

        .pricing-status-alert.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .pricing-status-alert.warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .price-display {
            margin-bottom: 10px;
        }

        .admin-notes-section {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .admin-notes-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-weight: bold;
            color: #007bff;
        }

        .admin-notes-content {
            color: #495057;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .pricing-status.badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .pricing-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .pricing-status.completed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .pricing-status.approved {
            background: #d4edda;
            color: #155724;
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
                    <li><a href="contact.php"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>

                <div class="user-info">
                    <a href="view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo array_sum(array_column($cart_items, 'quantity')); ?></span>
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

    <!-- Cart Section -->
    <section class="cart-page">
        <div class="container">
            <div class="cart-header">
                <h1><i class="fas fa-shopping-cart"></i> Your Shopping Cart</h1>
                <p>Review your items and request pricing from our team</p>
            </div>

            <?php if (!empty($cart_items)): ?>
                <form method="post" id="cartForm" action="view_cart.php">
                    <div class="pricing-info">
                        <h3><i class="fas fa-info-circle"></i> Pricing Information</h3>
                        <p>This is an <strong>estimated price</strong> based on standard rates. Final pricing may vary based on:</p>
                        <ul>
                            <li>Complexity of your custom design</li>
                            <li>Special material requirements</li>
                            <li>Urgency of the order</li>
                            <li>Quantity adjustments</li>
                        </ul>
                        <div class="pricing-note">
                            <strong><i class="fas fa-exclamation-triangle"></i> Note:</strong>
                            Click "Request Final Pricing" to send your selected items to our team. We will review your requirements and provide you with the exact pricing.
                        </div>
                    </div>

                    <div class="cart-actions">
                        <div class="select-all">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            <label for="selectAll">Select All Items</label>
                        </div>

                        <div class="bulk-actions">
                            <button type="button" class="bulk-btn remove-selected-btn" onclick="handleBulkRemove()">
                                <i class="fas fa-trash"></i> Remove Selected
                            </button>
                        </div>
                    </div>

                    <div class="cart-items">
                        <?php foreach ($cart_items as $row):
                            // Determine the actual price for this specific item
                            $has_admin_price = $row['price_updated_by_admin'];
                            $quoted_price = $row['quoted_price'];
                            $actual_price = $has_admin_price && $quoted_price > 0 ? $quoted_price : $row['unit_price'];
                            $item_total = $actual_price * $row['quantity'];
                            $is_selected = in_array($row['item_id'], $selected_items);
                        ?>
                            <div class="cart-item">
                                <div class="item-checkbox">
                                    <input type="checkbox" name="selected_items[]" value="<?php echo $row['item_id']; ?>"
                                        <?php echo $is_selected ? 'checked' : ''; ?>
                                        onchange="updateCartTotal()">
                                </div>

                                <div class="cart-item-content">
                                    <div class="cart-item-image">
                                        <?php
                                        $image_path = "../assets/images/services/service-" . $row['id'] . ".jpg";
                                        $image_url = file_exists($image_path) ? $image_path : "https://via.placeholder.com/140x140/2c5aa0/ffffff?text=Product";
                                        ?>
                                        <img src="<?php echo $image_url; ?>"
                                            alt="<?php echo $row['product_name']; ?>">
                                    </div>

                                    <div class="cart-item-info">
                                        <h3><?php echo $row['product_name']; ?></h3>
                                        <span class="product-group"><?php echo $row['product_group']; ?></span>
                                        
                                        <!-- Price Display with Admin Update Indicator -->
                                        <div class="price-display">
                                            <?php if ($has_admin_price): ?>
                                                <div class="admin-price-notice">
                                                    <i class="fas fa-check-circle" style="color: #27ae60;"></i>
                                                    <span style="color: #27ae60; font-weight: bold;">PRICE UPDATED</span>
                                                </div>
                                                <p class="price">
                                                    <span style="text-decoration: line-through; color: #999; margin-right: 10px;">
                                                        ₱<?php echo number_format($row['unit_price'], 2); ?>
                                                    </span>
                                                    <span style="color: #e74c3c; font-size: 1.3em;">
                                                        ₱<?php echo number_format($actual_price, 2); ?>
                                                    </span>
                                                </p>
                                            <?php else: ?>
                                                <p class="price">₱<?php echo number_format($actual_price, 2); ?></p>
                                            <?php endif; ?>

                                            <!-- Individual Admin Notes for this cart item -->
                                            <?php if (!empty($row['admin_notes'])): ?>
                                                <div class='admin-notes-section'>
                                                    <div class='admin-notes-header'>
                                                        <i class='fas fa-sticky-note'></i>
                                                        <strong>Admin Notes</strong>
                                                        <?php if (!empty($row['pricing_status'])): ?>
                                                            <span class='pricing-status badge <?php echo $row['pricing_status']; ?>'>
                                                                <?php echo ucfirst($row['pricing_status']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class='admin-notes-content'>
                                                        <?php echo htmlspecialchars($row['admin_notes']); ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

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
                                                <div class="custom-design">
                                                    <div class="custom-design-title">
                                                        <i class="fas fa-palette"></i> Your Custom Design
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
                                                                    <div style="width: 120px; height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 2px dashed #ccc;">
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
                                                                        <div style="width: 120px; height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 2px dashed #ccc;">
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
                                                                        <div style="width: 120px; height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 2px dashed #ccc;">
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
                                                                    <div style="width: 120px; height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 2px dashed var(--primary-color);">
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
                                                                    <div style="width: 120px; height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border: 2px dashed var(--primary-color);">
                                                                        <i class="fas fa-image" style="font-size: 24px; color: var(--primary-color);"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="design-label">Back Mockup</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Design Information -->
                                                    <div class="design-info">
                                                        <div class="design-info-row">
                                                            <span><strong>Upload Type:</strong> <?php echo ucfirst($uploadType); ?></span>
                                                            <?php if ($uploadType === 'single' && !empty($uploadedFile)): ?>
                                                                <span><strong>Original File:</strong> <?php echo basename($uploadedFile); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($uploadType === 'separate'): ?>
                                                            <div class="design-info-row">
                                                                <?php if (!empty($frontUploadedFile)): ?>
                                                                    <span><strong>Front File:</strong> <?php echo basename($frontUploadedFile); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($backUploadedFile)): ?>
                                                                    <span><strong>Back File:</strong> <?php echo basename($backUploadedFile); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                        <?php endif;
                                        }
                                        ?>

                                        <p class="subtotal">
                                            Subtotal: ₱<?php echo number_format($item_total, 2); ?>
                                            <?php if ($has_admin_price): ?>
                                                <br><small style="color: #999; font-weight: 300;">* final prices are reviewed and confirmed by the store (not automatically)</small>
                                            <?php endif; ?>
                                        </p>
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

                        <?php
                        $items_with_admin_prices = 0;
                        $total_selected_items = count($selected_items);

                        foreach ($cart_items as $item) {
                            if (in_array($item['item_id'], $selected_items)) {
                                if ($item['price_updated_by_admin'] && $item['quoted_price'] > 0) {
                                    $items_with_admin_prices++;
                                }
                            }
                        }

                        $all_prices_updated = ($items_with_admin_prices === $total_selected_items && $total_selected_items > 0);
                        ?>

                        <div id="debug-info" style="background: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 5px; font-size: 0.9em; display: none;">
                            <strong>Debug Info:</strong>
                            Selected: <span id="debug-selected-count"><?php echo $total_selected_items; ?></span> items |
                            With Admin Prices: <span id="debug-admin-prices"><?php echo $items_with_admin_prices; ?></span> |
                            Can Checkout: <span id="debug-can-checkout"><?php echo $all_prices_updated ? 'Yes' : 'No'; ?></span>
                        </div>

                        <?php if ($items_with_admin_prices > 0): ?>
                            <div class="pricing-status-alert success">
                                <i class="fas fa-check-circle"></i>
                                <strong><?php echo $items_with_admin_prices; ?> item(s) have confirmed pricing</strong>
                                <p>These items are ready for checkout with final store approved prices.</p>
                            </div>
                        <?php endif; ?>

                        <?php if (!$all_prices_updated && $total_selected_items > 0): ?>
                            <div class="pricing-status-alert warning">
                                <i class="fas fa-clock"></i>
                                <strong>Waiting for price confirmation</strong>
                                <p>
                                    <?php if ($items_with_admin_prices > 0): ?>
                                        <?php echo ($total_selected_items - $items_with_admin_prices); ?> of your <?php echo $total_selected_items; ?> selected items are still waiting for store pricing confirmation.
                                    <?php else: ?>
                                        All <?php echo $total_selected_items; ?> selected items are waiting for store pricing confirmation.
                                    <?php endif; ?>
                                    The checkout button will appear once all prices are confirmed.
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="summary-row">
                            <span>Subtotal (<span id="selected-count"><?php echo $total_selected_items; ?></span> items):</span>
                            <span id="subtotal-amount">₱<?php echo number_format($selected_total, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (3%):</span>
                            <span id="tax-amount">₱<?php echo number_format($selected_total * 0.03, 2); ?></span>
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

                        <?php
                        // Improved checkout logic
                        $can_checkout = false;
                        $checkout_message = "";

                        if (!empty($selected_items)) {
                            $items_with_admin_prices = 0;
                            $total_selected_items = count($selected_items);

                            foreach ($cart_items as $item) {
                                if (in_array($item['item_id'], $selected_items)) {
                                    if ($item['price_updated_by_admin'] && $item['quoted_price'] > 0) {
                                        $items_with_admin_prices++;
                                    }
                                }
                            }

                            $all_prices_updated = ($items_with_admin_prices === $total_selected_items && $total_selected_items > 0);

                            // Allow checkout if ALL selected items have admin prices
                            if ($items_with_admin_prices === $total_selected_items) {
                                $can_checkout = true;
                                $checkout_message = "All selected items have confirmed pricing";
                            } else if ($items_with_admin_prices > 0) {
                                $checkout_message = "$items_with_admin_prices of $total_selected_items selected items have confirmed pricing";
                            } else {
                                $checkout_message = "No selected items have confirmed pricing yet";
                            }

                            error_log("DEBUG Checkout: can_checkout=$can_checkout, items_with_admin_prices=$items_with_admin_prices, total_selected_items=$total_selected_items");
                        }
                        ?>

                        <?php if ($can_checkout): ?>
                            <button type="submit" name="proceed_to_checkout" class="cart-btn checkout-btn"
                                style="<?php echo empty($selected_items) ? 'display: none;' : ''; ?>">
                                <i class="fas fa-lock"></i> Proceed to Checkout
                            </button>
                        <?php elseif (!empty($selected_items)): ?>
                            <button type="button" class="cart-btn checkout-btn"
                                style="background: #6c757d !important; cursor: not-allowed; <?php echo empty($selected_items) ? 'display: none;' : ''; ?>"
                                title="<?php echo $checkout_message; ?>">
                                <i class="fas fa-clock"></i> Waiting for Price Confirmation
                            </button>
                        <?php endif; ?>

                        <button type="submit" name="request_pricing" class="cart-btn request-pricing-btn">
                            <i class="fas fa-envelope"></i> Request Price Confirmation
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Your cart is empty</h2>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="main.php" class="start-shopping">
                        <i class="fas fa-store" style="color: white; font-size: 30px;"></i> Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="../assets/js/main.js"></script>
    <script>
        // Store product prices and quantities for JavaScript calculations
        const productData = {
            <?php foreach ($cart_items as $item): ?>
                <?php 
                // Use admin price if available, otherwise use unit price
                $actual_price = $item['price_updated_by_admin'] && $item['quoted_price'] > 0 
                    ? $item['quoted_price'] 
                    : $item['unit_price'];
                ?>
                <?php echo $item['item_id']; ?>: {
                    price: <?php echo $actual_price; ?>,
                    quantity: <?php echo $item['quantity']; ?>,
                    subtotal: <?php echo $actual_price * $item['quantity']; ?>,
                    hasAdminPrice: <?php echo $item['price_updated_by_admin'] ? 'true' : 'false'; ?>,
                    originalPrice: <?php echo $item['unit_price']; ?>
                },
            <?php endforeach; ?>
        };

        // Enhanced JavaScript for better UX with AJAX
        document.addEventListener('DOMContentLoaded', function() {
            setupQuantityControls();
            setupRemoveButtons();
            setupBulkRemove();

            // Add event listeners for checkboxes to update debug info
            document.querySelectorAll('input[name="selected_items[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateCartTotal();
                    updateDebugInfo();
                });
            });

            // Real-time quantity validation
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value < 1) {
                        this.value = 1;
                    }
                });
            });

            updateSelectAll();
            updateCartTotal(); // Initialize the total display
            updateDebugInfo(); // Initialize debug info
        });

        // Handle form submission with AJAX to avoid page reload
        function handleFormSubmission(event) {
            event.preventDefault();

            const form = event.target;
            const submitter = event.submitter;

            if (submitter.name === 'request_pricing') {
                handlePricingRequest(new FormData(form));
            } else if (submitter.name === 'proceed_to_checkout') {
                handleCheckout(new FormData(form));
            }
        }

        // Handle pricing request via AJAX
        function handlePricingRequest(formData) {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (selectedItems.length === 0) {
                alert('Please select at least one item to request pricing.');
                return;
            }

            // Show loading state
            showLoadingState(true);

            fetch('view_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Check if the response contains a success message
                    if (html.includes('pricing request') && html.includes('sent to our team')) {
                        // Extract and show success message
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        const scripts = tempDiv.getElementsByTagName('script');

                        // Execute any alert scripts from the response
                        for (let script of scripts) {
                            if (script.textContent.includes('alert')) {
                                eval(script.textContent);
                            }
                        }

                        // Reload the page to reflect changes
                        setTimeout(() => {
                            window.location.href = 'view_cart.php';
                        }, 2000);

                    } else {
                        alert('There was an error submitting your request. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error submitting request');
                })
                .finally(() => {
                    showLoadingState(false);
                });
        }

        // Handle checkout process
        function handleCheckout(formData) {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            
            if (selectedItems.length === 0) {
                alert('Please select at least one item to checkout.');
                return;
            }

            // Show loading state
            showLoadingState(true);

            // Get selected item IDs
            const selectedItemIds = Array.from(selectedItems).map(item => item.value);
            
            // Save selected items to session first (optional, but good for persistence)
            const saveFormData = new FormData();
            saveFormData.append('action', 'save_selected_items');
            selectedItemIds.forEach(id => {
                saveFormData.append('selected_items[]', id);
            });

            fetch('../pages/website/update_cart.php', {
                method: 'POST',
                body: saveFormData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Selected items saved:', data);
                
                // Redirect to checkout page with selected items as URL parameters
                const queryString = selectedItemIds.join(',');
                window.location.href = `checkout.php?selected_items=${queryString}`;
            })
            .catch(error => {
                console.error('Error:', error);
                // Fallback: redirect without saving to session
                const queryString = selectedItemIds.join(',');
                window.location.href = `checkout.php?selected_items=${queryString}`;
            })
            .finally(() => {
                showLoadingState(false);
            });
        }

        // Handle bulk remove
        function handleBulkRemove() {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (selectedItems.length === 0) {
                alert('Please select at least one item to remove.');
                return;
            }

            if (!confirm(`Are you sure you want to remove ${selectedItems.length} selected item(s) from your cart?`)) {
                return;
            }

            removeSelectedItems(selectedItems);
        }

        // Show/hide loading state
        function showLoadingState(show) {
            const overlay = document.getElementById('loading-overlay') || createLoadingOverlay();
            overlay.style.display = show ? 'flex' : 'none';

            if (show) {
                document.body.style.pointerEvents = 'none';
                document.body.style.opacity = '0.7';
            } else {
                document.body.style.pointerEvents = 'auto';
                document.body.style.opacity = '1';
            }
        }

        // Create loading overlay
        function createLoadingOverlay() {
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
            font-size: 1.5em;
        `;
            overlay.innerHTML = `
            <div style="text-align: center;">
                <i class="fas fa-spinner fa-spin" style="font-size: 3em; margin-bottom: 20px;"></i>
                <p>Processing your request...</p>
            </div>
        `;
            document.body.appendChild(overlay);
            return overlay;
        }

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
                    handleBulkRemove();
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

            fetch('../pages/website/update_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Update response:', data);
                    if (data.status === 'success') {
                        if (productData[itemId]) {
                            productData[itemId].quantity = parseInt(quantity);
                            productData[itemId].subtotal = productData[itemId].price * parseInt(quantity);
                        }

                        updateItemSubtotal(itemId);
                        updateCartTotal();
                        updateCartCount();

                        showSuccess(itemId, 'Quantity updated');
                    } else {
                        showError(itemId, 'Error: ' + data.message);
                        const input = document.querySelector(`input[name="item_id"][value="${itemId}"]`)
                            .closest('.cart-item')
                            .querySelector('.quantity-input');
                        input.value = productData[itemId].quantity;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError(itemId, 'Network error updating quantity');
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

            fetch('../pages/website/update_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Remove response:', data);
                    if (data.status === 'success') {
                        const itemElement = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
                        itemElement.style.opacity = '0.5';

                        setTimeout(() => {
                            itemElement.remove();

                            delete productData[itemId];

                            updateCartTotal();
                            updateCartCount();
                            updateSelectAll();

                            if (document.querySelectorAll('.cart-item').length === 0) {
                                location.reload();
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

            const successMsg = document.createElement('div');
            successMsg.textContent = message;
            successMsg.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #4CAF50; color: white; padding: 5px 10px; font-size: 12px; z-index: 10;';
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

            alert(message);
        }

        // Update item subtotal
        function updateItemSubtotal(itemId) {
            const itemElement = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
            const subtotalElement = itemElement.querySelector('.subtotal');

            if (subtotalElement && productData[itemId]) {
                subtotalElement.textContent = `Subtotal: ₱${productData[itemId].subtotal.toFixed(2)}`;
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

        // Update cart total based on selected items
        function updateCartTotal() {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            let subtotal = 0;

            selectedItems.forEach(item => {
                const itemId = item.value;
                if (productData[itemId]) {
                    subtotal += productData[itemId].subtotal;
                }
            });

            const tax = subtotal * 0.03;
            const total = subtotal + tax;

            // Update the display
            document.getElementById('selected-count').textContent = selectedItems.length;
            document.getElementById('subtotal-amount').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('tax-amount').textContent = '₱' + tax.toFixed(2);
            document.getElementById('total-amount').textContent = '₱' + total.toFixed(2);

            updateSelectAll();
            updateDebugInfo();
            saveSelectedItems();
        }

        // Save selected items to session via AJAX
        function saveSelectedItems() {
            const selectedItems = Array.from(document.querySelectorAll('input[name="selected_items[]"]:checked')).map(item => item.value);

            const formData = new FormData();
            formData.append('action', 'save_selected_items');
            selectedItems.forEach(id => {
                formData.append('selected_items[]', id);
            });

            fetch('../pages/website/update_cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Selected items saved:', data);
                })
                .catch(error => {
                    console.error('Error saving selected items:', error);
                });
        }

        // Update debug information in real-time
        function updateDebugInfo() {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            const selectedCount = selectedItems.length;

            let itemsWithAdminPrices = 0;
            let canCheckout = false;

            selectedItems.forEach(item => {
                const itemId = item.value;
                const cartItem = document.querySelector(`input[name="item_id"][value="${itemId}"]`).closest('.cart-item');
                const hasAdminPrice = cartItem.querySelector('.admin-price-notice') !== null;

                if (hasAdminPrice) {
                    itemsWithAdminPrices++;
                }
            });

            canCheckout = (selectedCount > 0 && itemsWithAdminPrices === selectedCount);

            document.getElementById('debug-selected-count').textContent = selectedCount;
            document.getElementById('debug-admin-prices').textContent = itemsWithAdminPrices;
            document.getElementById('debug-can-checkout').textContent = canCheckout ? 'Yes' : 'No';

            updateCheckoutButtonState(selectedCount, itemsWithAdminPrices, canCheckout);
        }

        // Update checkout button state dynamically
        function updateCheckoutButtonState(selectedCount, itemsWithAdminPrices, canCheckout) {
            const checkoutBtn = document.querySelector('button[name="proceed_to_checkout"]');
            const waitingBtn = document.querySelector('.cart-btn[style*="cursor: not-allowed"]');

            if (selectedCount === 0) {
                if (checkoutBtn) checkoutBtn.style.display = 'none';
                if (waitingBtn) waitingBtn.style.display = 'none';
                return;
            }

            if (canCheckout) {
                if (checkoutBtn) checkoutBtn.style.display = 'flex';
                if (waitingBtn) waitingBtn.style.display = 'none';
            } else {
                if (checkoutBtn) checkoutBtn.style.display = 'none';
                if (waitingBtn) waitingBtn.style.display = 'flex';

                if (waitingBtn) {
                    let message = "No selected items have confirmed pricing yet";
                    if (itemsWithAdminPrices > 0) {
                        message = `${itemsWithAdminPrices} of ${selectedCount} selected items have confirmed pricing`;
                    }
                    waitingBtn.title = message;
                }
            }
        }
    </script>
</body>

</html>

<?php
// Close connection
$inventory->close();
?>