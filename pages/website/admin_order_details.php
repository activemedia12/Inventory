<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admin_orders.php");
    exit;
}

$order_id = $_GET['id'];

// Get order details
$query = "SELECT o.*, u.username 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.order_id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: admin_orders.php");
    exit;
}

// Get order items with proper option names for Other Services
$query = "SELECT oi.*, 
                 po.option_name as paper_option_name,
                 fo.option_name as finish_option_name,
                 bo.option_name as binding_option_name,
                 lo.option_name as layout_option_name,
                 
                 -- T-Shirt options
                 ts.size_name as tshirt_size_name,
                 tc.color_name as tshirt_color_name,
                 
                 -- Tote Bag options
                 tos.size_name as tote_size_name,
                 toc.color_name as tote_color_name,
                 
                 -- Paper Bag options
                 pbs.size_name as paperbag_size_name,
                 pbs.dimensions as paperbag_dimensions,
                 
                 -- Mug options
                 ms.size_name as mug_size_name,
                 mc.color_name as mug_color_name
                 
          FROM order_items oi
          LEFT JOIN paper_options po ON oi.paper_option = po.id
          LEFT JOIN finish_options fo ON oi.finish_option = fo.id
          LEFT JOIN binding_options bo ON oi.binding_option = bo.id
          LEFT JOIN layout_options lo ON oi.layout_option = lo.id
          
          -- Other Services joins
          LEFT JOIN tshirt_sizes ts ON (oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' AND oi.size_option = ts.id)
          LEFT JOIN tshirt_colors tc ON (oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' AND oi.color_option = tc.id)
          
          LEFT JOIN totesize_options tos ON (oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' AND oi.size_option = tos.id)
          LEFT JOIN totecolor_options toc ON (oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' AND oi.color_option = toc.id)
          
          LEFT JOIN paperbag_size_options pbs ON (oi.product_category = 'Other Services' AND oi.product_name = 'Paper Bag' AND oi.size_option = pbs.id)
          
          LEFT JOIN mug_size_options ms ON (oi.product_category = 'Other Services' AND oi.product_name = 'Mug' AND oi.size_option = ms.id)
          LEFT JOIN mug_color_options mc ON (oi.product_category = 'Other Services' AND oi.product_name = 'Mug' AND oi.color_option = mc.id)
          
          WHERE oi.order_id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Active Media</title>
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

        .user-info span {
            color: var(--gray);
            font-size: 13px;
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

        .back-btn {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            color: var(--dark);
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: background-color 0.15s ease;
            font-size: 13px;
            font-weight: 600;
        }

        .back-btn:hover {
            background: var(--light);
            text-decoration: none;
            color: var(--dark);
        }

        .order-details-container {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
        }

        .detail-section {
            margin-bottom: 24px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--light-gray);
        }

        .detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-section h3 {
            color: var(--dark);
            margin-bottom: 16px;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-section h3 i {
            color: var(--gray);
        }

        .detail-row {
            display: flex;
            margin-bottom: 10px;
            align-items: flex-start;
            font-size: 13px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--gray);
            min-width: 160px;
            flex-shrink: 0;
        }

        .detail-value {
            color: var(--dark);
            flex: 1;
        }

        .item-details {
            background: var(--light);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        /* Design Files Styles */
        .design-previews {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-top: 10px;
        }

        .design-preview {
            text-align: center;
            flex: 0 0 auto;
        }

        .design-preview img {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            padding: 4px;
            background: var(--card-bg);
            transition: transform 0.15s ease;
        }

        .design-preview a:hover img {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .design-label {
            font-size: 10px;
            color: var(--gray);
            margin-top: 5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .design-preview:has(img[alt*="Original"]) img {
            border-color: var(--success);
        }

        .design-preview:has(img[alt*="Mockup"]) img {
            border-color: var(--primary);
        }

        /* File path display */
        .design-preview small {
            display: block;
            margin-top: 2px;
            color: var(--gray);
            font-size: 10px;
            max-width: 80px;
            word-break: break-all;
        }

        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
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

        .status-select {
            padding: 9px 12px;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            background: var(--card-bg);
            font-size: 13px;
            color: var(--dark);
            min-width: 180px;
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .update-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background-color 0.15s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .update-btn:hover {
            background: var(--secondary);
        }

        /* User Layout Files Styles */
        .user-layout-file {
            background: var(--warning-bg);
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
        }

        .user-layout-file a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .user-layout-file a:hover {
            text-decoration: underline;
        }

        .file-path {
            font-family: monospace;
            font-size: 12px;
            color: var(--gray);
            background: var(--light);
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid var(--light-gray);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .header {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .detail-row {
                flex-direction: column;
                gap: 4px;
            }

            .detail-label {
                min-width: auto;
            }

            .status-form {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .status-select {
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .order-details-container {
                padding: 18px;
            }

            .header {
                padding: 16px;
            }

            .header h1 {
                font-size: 18px;
            }

            .design-preview img {
                width: 64px;
                height: 64px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <div class="main-content">
            <a href="admin_orders.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>

            <div class="header">
                <h1>Order Details - #<?php echo $order['order_id']; ?></h1>
                <div class="user-info">
                    <span>Order Status:
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </span>
                </div>
            </div>

            <!-- Order Information -->
            <div class="order-details-container">
                <div class="detail-section">
                    <h3><i class="fas fa-user"></i> Customer Information</h3>
                    <div class="detail-row">
                        <span class="detail-label">Username:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($order['username']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">User ID:</span>
                        <span class="detail-value"><?php echo $order['user_id']; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Order Date:</span>
                        <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                </div>

                <div class="detail-section">
                    <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                    <div class="detail-row">
                        <span class="detail-label">Total Amount:</span>
                        <span class="detail-value" style="font-weight: bold; font-size: 1.2em; color: var(--success);">
                            ₱<?php echo number_format($order['total_amount'], 2); ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Proof:</span>
                        <span class="detail-value">
                            <?php if ($order['payment_proof']):
                                // Use the same path structure as profile.php
                                $proof_path = "../../assets/uploads/payments/user_" . $order['user_id'] . "/" . $order['payment_proof'];
                                if (file_exists($proof_path)): ?>
                                    <a href="<?php echo $proof_path; ?>" target="_blank" style="color: var(--primary);">
                                        <i class="fas fa-external-link-alt"></i> View Payment Proof
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--danger);">File not found</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--gray);">No payment proof uploaded</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Status Update Form -->
                <div class="detail-section">
                    <h3><i class="fas fa-sync"></i> Update Order Status</h3>
                    <form method="post" action="admin_orders.php" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                        <select name="status" class="status-select" style="padding: 10px; border-radius: 5px; border: 1px solid var(--light-gray);">
                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="paid" <?php echo $order['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="ready_for_pickup" <?php echo $order['status'] == 'ready_for_pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                            <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                        <button type="submit" name="update_status" class="update-btn" style="padding: 10px 15px;">
                            <i class="fas fa-sync"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order Items -->
            <div class="order-details-container">
                <h3><i class="fas fa-boxes"></i> Order Items</h3>
                <?php foreach ($order_items as $item): ?>
                    <div class="item-details">
                        <div class="detail-row">
                            <span class="detail-label">Product:</span>
                            <span class="detail-value" style="font-weight: bold;"><?php echo htmlspecialchars($item['product_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Category:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($item['product_category']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Quantity:</span>
                            <span class="detail-value"><?php echo $item['quantity']; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Unit Price:</span>
                            <span class="detail-value">₱<?php echo number_format($item['unit_price'], 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Subtotal:</span>
                            <span class="detail-value" style="font-weight: bold;">₱<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></span>
                        </div>

                        <!-- Customization Details -->
                        <?php if ($item['size_option'] || $item['color_option'] || $item['finish_option'] || $item['paper_option'] || $item['binding_option'] || $item['layout_option'] || $item['gsm_option']): ?>
                            <div style="margin-top: 10px;">
                                <strong>Customization:</strong>
                                <div style="margin-left: 20px; margin-top: 5px;">
                                    <?php
                                    // Determine product type and display appropriate labels
                                    $category = $item['product_category'];
                                    $product_name = $item['product_name'];

                                    // Size Options
                                    if ($item['size_option']):
                                        $size_display = '';

                                        if ($category === 'Other Services') {
                                            switch ($product_name) {
                                                case 'T-Shirts':
                                                    $size_display = $item['tshirt_size_name'] ?? $item['size_option'];
                                                    echo '<div>T-Shirt Size: ' . htmlspecialchars($size_display) . '</div>';
                                                    break;
                                                case 'Tote Bag':
                                                    $size_display = $item['tote_size_name'] ?? $item['size_option'];
                                                    echo '<div>Tote Bag Size: ' . htmlspecialchars($size_display) . '</div>';
                                                    break;
                                                case 'Paper Bag':
                                                    $size_display = $item['paperbag_size_name'] ?? $item['size_option'];
                                                    $dimensions = $item['paperbag_dimensions'] ?? '';
                                                    echo '<div>Paper Bag Size: ' . htmlspecialchars($size_display);
                                                    if ($dimensions) echo ' (' . htmlspecialchars($dimensions) . ')';
                                                    echo '</div>';
                                                    break;
                                                case 'Mug':
                                                    $size_display = $item['mug_size_name'] ?? $item['size_option'];
                                                    echo '<div>Mug Size: ' . htmlspecialchars($size_display) . '</div>';
                                                    break;
                                                default:
                                                    echo '<div>Size: ' . htmlspecialchars($item['size_option']) . '</div>';
                                            }
                                        } else {
                                            // Printing products
                                            echo '<div>Size: ' . htmlspecialchars($item['size_option']) . '</div>';
                                        }

                                        // Custom size
                                        if ($item['custom_size']):
                                            echo '<div>Custom Size: ' . htmlspecialchars($item['custom_size']) . '</div>';
                                        endif;
                                    endif;

                                    // Color Options
                                    if ($item['color_option']):
                                        $color_display = '';

                                        if ($category === 'Other Services') {
                                            switch ($product_name) {
                                                case 'T-Shirts':
                                                    $color_display = $item['tshirt_color_name'] ?? $item['color_option'];
                                                    echo '<div>T-Shirt Color: ' . htmlspecialchars($color_display) . '</div>';
                                                    break;
                                                case 'Tote Bag':
                                                    $color_display = $item['tote_color_name'] ?? $item['color_option'];
                                                    echo '<div>Tote Bag Color: ' . htmlspecialchars($color_display) . '</div>';
                                                    break;
                                                case 'Mug':
                                                    $color_display = $item['mug_color_name'] ?? $item['color_option'];
                                                    echo '<div>Mug Color: ' . htmlspecialchars($color_display) . '</div>';
                                                    break;
                                                case 'Paper Bag':
                                                    // Paper Bag only has brown color
                                                    echo '<div>Color: Brown</div>';
                                                    break;
                                                default:
                                                    echo '<div>Color: ' . htmlspecialchars($item['color_option']) . '</div>';
                                            }
                                        } else {
                                            // Printing products
                                            echo '<div>Color: ' . htmlspecialchars($item['color_option']) . '</div>';
                                        }

                                        // Custom color
                                        if ($item['custom_color']):
                                            echo '<div>Custom Color: ' . htmlspecialchars($item['custom_color']) . '</div>';
                                        endif;
                                    endif;

                                    // Printing Options (only for non-Other Services)
                                    if ($category !== 'Other Services'):
                                        if ($item['finish_option_name']):
                                            echo '<div>Finish: ' . htmlspecialchars($item['finish_option_name']) . '</div>';
                                        endif;

                                        if ($item['paper_option_name']):
                                            echo '<div>Paper: ' . htmlspecialchars($item['paper_option_name']) . '</div>';
                                        endif;

                                        if ($item['binding_option_name']):
                                            echo '<div>Binding: ' . htmlspecialchars($item['binding_option_name']) . '</div>';
                                        endif;

                                        if ($item['layout_option_name']):
                                            echo '<div>Layout: ' . htmlspecialchars($item['layout_option_name']) . '</div>';
                                        endif;

                                        if ($item['layout_details']):
                                            echo '<div>Layout Details: ' . htmlspecialchars($item['layout_details']) . '</div>';
                                        endif;

                                        if ($item['gsm_option']):
                                            echo '<div>GSM: ' . htmlspecialchars($item['gsm_option']) . '</div>';
                                        endif;
                                    endif;
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- User Layout Files -->
                        <?php if (!empty($item['user_layout_files'])): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 2px dashed var(--warning);">
                                <div style="font-weight: bold; margin-bottom: 10px; color: var(--warning); font-size: 1em;">
                                    <i class="fas fa-file-upload"></i> User Layout Files
                                </div>
                                <?php
                                $layout_files = json_decode($item['user_layout_files'], true);
                                if (is_array($layout_files)) {
                                    foreach ($layout_files as $file_path) {
                                        // Clean up the file path (remove ../ if present)
                                        $clean_path = str_replace('../../', '', $file_path);
                                        $full_path = "../../" . $clean_path;
                                        $file_exists = file_exists($full_path);
                                ?>
                                        <div class="user-layout-file">
                                            <div>
                                                <?php if ($file_exists): ?>
                                                    <a href="<?php echo $full_path; ?>" download="<?php echo basename($clean_path); ?>" target="_blank">
                                                        <i class="fas fa-download"></i> <?php echo basename($clean_path); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: var(--danger);">
                                                        <i class="fas fa-exclamation-triangle"></i> <?php echo basename($clean_path); ?> (File not found)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="file-path"><?php echo htmlspecialchars($clean_path); ?></div>
                                        </div>
                                <?php
                                    }
                                } else {
                                    echo '<div style="color: var(--gray); font-style: italic;">No valid layout files found</div>';
                                }
                                ?>
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
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px dashed var(--primary);">
                                    <div style="font-weight: bold; margin-bottom: 10px; color: var(--primary); font-size: 1em;">
                                        <i class="fas fa-palette"></i> Custom Design Files
                                    </div>
                                    <div class="design-previews">
                                        <?php
                                        // Show uploaded original files
                                        if ($uploadType === 'single' && !empty($uploadedFile)):
                                            $uploadedFilePath = "../../assets/uploads/" . $uploadedFile;
                                            $uploadedFileExists = file_exists($uploadedFilePath);
                                        ?>
                                            <div class="design-preview">
                                                <?php if ($uploadedFileExists): ?>
                                                    <a href="<?php echo $uploadedFilePath; ?>" download="<?php echo basename($uploadedFile); ?>" style="text-decoration: none;">
                                                        <img src="<?php echo $uploadedFilePath; ?>"
                                                            alt="Original Design File">
                                                        <div class="design-label">Original File</div>
                                                    </a>
                                                <?php else: ?>
                                                    <div style="width: 80px; height: 80px; background: var(--light-gray); display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed var(--light-gray);">
                                                        <i class="fas fa-file-image" style="font-size: 20px; color: var(--gray);"></i>
                                                    </div>
                                                    <div class="design-label">File not found</div>
                                                    <small style="color: var(--gray); font-size: 0.6em;"><?php echo htmlspecialchars($uploadedFile); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($frontUploadedFile) || !empty($backUploadedFile)): ?>
                                            <?php if (!empty($frontUploadedFile)):
                                                $frontUploadedFilePath = "../../assets/uploads/" . $frontUploadedFile;
                                                $frontUploadedFileExists = file_exists($frontUploadedFilePath);
                                            ?>
                                                <div class="design-preview">
                                                    <?php if ($frontUploadedFileExists): ?>
                                                        <a href="<?php echo $frontUploadedFilePath; ?>" download="<?php echo basename($frontUploadedFile); ?>" style="text-decoration: none;">
                                                            <img src="<?php echo $frontUploadedFilePath; ?>"
                                                                alt="Front Original Design">
                                                            <div class="design-label">Front Original</div>
                                                        </a>
                                                    <?php else: ?>
                                                        <div style="width: 80px; height: 80px; background: var(--light-gray); display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed var(--light-gray);">
                                                            <i class="fas fa-file-image" style="font-size: 20px; color: var(--gray);"></i>
                                                        </div>
                                                        <div class="design-label">File not found</div>
                                                        <small style="color: var(--gray); font-size: 0.6em;"><?php echo htmlspecialchars($frontUploadedFile); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($backUploadedFile)):
                                                $backUploadedFilePath = "../../assets/uploads/" . $backUploadedFile;
                                                $backUploadedFileExists = file_exists($backUploadedFilePath);
                                            ?>
                                                <div class="design-preview">
                                                    <?php if ($backUploadedFileExists): ?>
                                                        <a href="<?php echo $backUploadedFilePath; ?>" download="<?php echo basename($backUploadedFile); ?>" style="text-decoration: none;">
                                                            <img src="<?php echo $backUploadedFilePath; ?>"
                                                                alt="Back Original Design">
                                                            <div class="design-label">Back Original</div>
                                                        </a>
                                                    <?php else: ?>
                                                        <div style="width: 80px; height: 80px; background: var(--light-gray); display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed var(--light-gray);">
                                                            <i class="fas fa-file-image" style="font-size: 20px; color: var(--gray);"></i>
                                                        </div>
                                                        <div class="design-label">File not found</div>
                                                        <small style="color: var(--gray); font-size: 0.6em;"><?php echo htmlspecialchars($backUploadedFile); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Mockup Previews -->
                                        <?php
                                        // Front mockup
                                        if (!empty($frontMockup)):
                                            $frontMockupPath = "../../assets/uploads/" . $frontMockup;
                                            $frontMockupExists = file_exists($frontMockupPath);
                                        ?>
                                            <div class="design-preview">
                                                <?php if ($frontMockupExists): ?>
                                                    <a href="<?php echo $frontMockupPath; ?>" download="<?php echo basename($frontMockup); ?>" style="text-decoration: none;">
                                                        <img src="<?php echo $frontMockupPath; ?>"
                                                            alt="Front Mockup">
                                                        <div class="design-label">Front Mockup</div>
                                                    </a>
                                                <?php else: ?>
                                                    <div style="width: 80px; height: 80px; background: var(--light-gray); display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed var(--primary);">
                                                        <i class="fas fa-image" style="font-size: 20px; color: var(--primary);"></i>
                                                    </div>
                                                    <div class="design-label">File not found</div>
                                                    <small style="color: var(--gray); font-size: 0.6em;"><?php echo htmlspecialchars($frontMockup); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php
                                        // Back mockup
                                        if (!empty($backMockup)):
                                            $backMockupPath = "../../assets/uploads/" . $backMockup;
                                            $backMockupExists = file_exists($backMockupPath);
                                        ?>
                                            <div class="design-preview">
                                                <?php if ($backMockupExists): ?>
                                                    <a href="<?php echo $backMockupPath; ?>" download="<?php echo basename($backMockup); ?>" style="text-decoration: none;">
                                                        <img src="<?php echo $backMockupPath; ?>"
                                                            alt="Back Mockup">
                                                        <div class="design-label">Back Mockup</div>
                                                    </a>
                                                <?php else: ?>
                                                    <div style="width: 80px; height: 80px; background: var(--light-gray); display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed var(--primary);">
                                                        <i class="fas fa-image" style="font-size: 20px; color: var(--primary);"></i>
                                                    </div>
                                                    <div class="design-label">File not found</div>
                                                    <small style="color: var(--gray); font-size: 0.6em;"><?php echo htmlspecialchars($backMockup); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                        <?php endif;
                        }
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>

</html>