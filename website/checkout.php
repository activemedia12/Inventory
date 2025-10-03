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
    $item_total = $row['unit_price'] * $row['quantity'];
    $subtotal += $item_total;
    $checkout_items[] = $row;
}

$shipping = 0;
$tax = $subtotal * 0.1;
$total = $subtotal + $tax;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Active Media</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkout-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        .checkout-section {
            background: white;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        .order-summary {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        .qr-code {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .qr-code img {
            max-width: 200px;
            margin-bottom: 15px;
        }
        .upload-section {
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .total-row {
            font-weight: bold;
            font-size: 1.2em;
            color: #27ae60;
            border-top: 2px solid #eee;
            margin-top: 10px;
        }
        .summary-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
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
        }
        
        /* Printing Details Styles */
        .printing-details {
            margin-top: 10px;
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
        .design-previews {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-top: 10px;
        }
        .design-preview {
            text-align: center;
            flex: 0 0 auto;
        }
        .design-preview img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 8px;
            border: 2px solid #007bff;
            padding: 5px;
            background: white;
        }
        .design-label {
            font-size: 0.7em;
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
    </style>
</head>
<body>
    <div class="checkout-container">
        <h1><i class="fas fa-shopping-cart"></i> Checkout</h1>
        
        <!-- Customer Information -->
        <div class="checkout-section">
            <h2 class="section-title">Customer Information</h2>
            <div class="customer-details">
                <?php if ($user_data['customer_type'] === 'personal'): ?>
                    <p><strong>Name:</strong> 
                        <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                    </p>
                    <p><strong>Contact:</strong> 
                        <?php echo htmlspecialchars($user_data['personal_contact']); ?>
                    </p>
                    <p><strong>Address:</strong> 
                        <?php echo htmlspecialchars($user_data['address_line1'] . ', ' . $user_data['personal_city'] . ', ' . $user_data['personal_province'] . ' ' . $user_data['personal_zip']); ?>
                    </p>
                <?php elseif ($user_data['customer_type'] === 'company'): ?>
                    <p><strong>Company:</strong> 
                        <?php echo htmlspecialchars($user_data['company_name']); ?>
                    </p>
                    <p><strong>Contact Person:</strong> 
                        <?php echo htmlspecialchars($user_data['contact_person']); ?>
                    </p>
                    <p><strong>Contact Number:</strong> 
                        <?php echo htmlspecialchars($user_data['company_contact']); ?>
                    </p>
                    <p><strong>Address:</strong> 
                        <?php echo htmlspecialchars(
                            $user_data['subd_or_street'] . ' ' .
                            $user_data['building_or_block'] . ' ' .
                            $user_data['lot_or_room_no'] . ', ' .
                            $user_data['barangay'] . ', ' .
                            $user_data['company_city'] . ', ' .
                            $user_data['company_province'] . ' ' .
                            $user_data['company_zip']
                        ); ?>
                    </p>
                <?php else: ?>
                    <p>No customer details found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="checkout-section">
            <h2 class="section-title">Order Summary</h2>
            <?php foreach ($checkout_items as $item): ?>
                <div class="summary-item">
                    <div style="display: flex; gap: 20px; align-items: flex-start; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                        <div style="flex-shrink: 0;">
                            <?php
                            $image_path = "../assets/images/services/service-" . $item['id'] . ".jpg";
                            $image_url = file_exists($image_path) ? $image_path : "https://via.placeholder.com/80x80/007bff/ffffff?text=Product";
                            ?>
                            <img src="<?php echo $image_url; ?>" 
                                alt="<?php echo $item['product_name']; ?>"
                                style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #f8f9fa;">
                        </div>
                        
                        <div style="flex-grow: 1;">
                            <p style="font-weight: bold; margin-bottom: 5px; font-size: 1.1em;">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </p>
                            <p style="color: #666; margin-bottom: 8px; font-size: 0.9em;">
                                <?php echo htmlspecialchars($item['product_group']); ?>
                            </p>
                            <p style="color: #e74c3c; font-weight: bold; margin-bottom: 8px;">
                                ₱<?php echo number_format($item['unit_price'], 2); ?> × <?php echo $item['quantity']; ?>
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
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 2px dashed #007bff;">
                                        <div style="font-weight: bold; margin-bottom: 10px; color: #007bff; font-size: 1em;">
                                            <i class="fas fa-palette"></i> Custom Design
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
                                                        <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
                                                            <i class="fas fa-file-image" style="font-size: 20px; color: #999;"></i>
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
                                                            <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
                                                                <i class="fas fa-file-image" style="font-size: 20px; color: #999;"></i>
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
                                                            <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #ccc;">
                                                                <i class="fas fa-file-image" style="font-size: 20px; color: #999;"></i>
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
                                                        <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #007bff;">
                                                            <i class="fas fa-image" style="font-size: 20px; color: #007bff;"></i>
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
                                                        <div style="width: 80px; height: 80px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 2px dashed #007bff;">
                                                            <i class="fas fa-image" style="font-size: 20px; color: #007bff;"></i>
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
                        
                        <div style="text-align: right; flex-shrink: 0;">
                            <p style="font-weight: bold; color: #27ae60;">
                                ₱<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="summary-row">
                <span>Subtotal:</span>
                <span>₱<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Tax (10%):</span>
                <span>₱<?php echo number_format($tax, 2); ?></span>
            </div>
            <div class="summary-row total-row">
                <span>Total Amount:</span>
                <span>₱<?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <!-- Payment Section -->
        <div class="checkout-section">
            <h2 class="section-title">Payment Method</h2>
            <div class="order-summary">
                <div class="qr-code">
                    <h3>GCash Payment</h3>
                    <img src="../assets/images/gcash-qr.jpg" alt="GCash QR Code">
                    <p><strong>GCash Number:</strong> 0998-791-****</p>
                    <p><strong>Account Name:</strong> WI******A L.</p>
                    <p><strong>Amount to Pay:</strong> ₱<?php echo number_format($total, 2); ?></p>
                    
                    <form action="../pages/website/process_order.php" method="post" enctype="multipart/form-data" class="upload-section">
                        <input type="hidden" name="selected_items" value="<?php echo implode(',', $selected_items); ?>">
                        <input type="hidden" name="total_amount" value="<?php echo $total; ?>">
                        
                        <div style="margin: 20px 0;">
                            <label for="payment_proof"><strong>Upload Payment Proof:</strong></label>
                            <input type="file" name="payment_proof" id="payment_proof" accept="image/*" required style="margin-top: 10px;">
                        </div>
                        
                        <button type="submit" class="cart-btn checkout-btn" style="width: 100%;">
                            <i class="fas fa-check"></i> Confirm Order
                        </button>
                    </form>
                </div>
                
                <div>
                    <h3>Order Instructions</h3>
                    <ul style="text-align: left;">
                        <li>Scan the QR code or send payment to our GCash number</li>
                        <li>Take a screenshot of your payment confirmation</li>
                        <li>Upload the screenshot as proof of payment</li>
                        <li>Your order will be processed within 24 hours</li>
                        <li>You will receive updates via email/SMS</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>