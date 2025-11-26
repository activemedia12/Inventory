<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

require_once '../../config/db.php';

// Fetch user info (personal or company)
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
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user_data = $userResult->fetch_assoc();

// Set display name for session if not set
if (!empty($user_data['first_name'])) {
    $_SESSION['username'] = $user_data['first_name'];
} elseif (!empty($user_data['company_name'])) {
    $_SESSION['username'] = $user_data['company_name'];
} else {
    $_SESSION['username'] = 'User';
}


// Display success/error messages
if (isset($_GET['success'])) {
    $message = '';
    $type = 'success';

    switch ($_GET['success']) {
        case 'added':
            $message = '✅ Product added to cart successfully!';
            break;
        case 'updated':
            $message = '✅ Cart quantity updated successfully!';
            break;
    }

    if ($message) {
        echo '<div style="position: fixed; top: 20px; right: 20px; background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border: 1px solid #c3e6cb; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';
        echo $message;
        echo '</div>';

        // Add JavaScript to auto-hide the message after 3 seconds
        echo '<script>
            setTimeout(function() {
                const message = document.querySelector("div[style*=\"position: fixed\"]");
                if (message) message.remove();
            }, 3000);
        </script>';
    }
}

if (isset($_GET['error'])) {
    $message = '';
    $type = 'error';

    switch ($_GET['error']) {
        case 'invalid_product':
            $message = '❌ Invalid product!';
            break;
        case 'cart_error':
            $message = '❌ Error creating cart!';
            break;
        case 'update_error':
            $message = '❌ Error updating cart!';
            break;
        case 'add_error':
            $message = '❌ Error adding to cart!';
            break;
    }

    if ($message) {
        echo '<div style="position: fixed; top: 20px; right: 20px; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border: 1px solid #f5c6cb; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">';
        echo $message;
        echo '</div>';

        // Add JavaScript to auto-hide the message after 5 seconds
        echo '<script>
            setTimeout(function() {
                const message = document.querySelector("div[style*=\"position: fixed\"]");
                if (message) message.remove();
            }, 5000);
        </script>';
    }
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id === 0) {
    header("Location: ../../website/main.php");
    exit;
}

// Check if product has customization options
$customization_query = "SELECT * FROM product_customization WHERE product_id = ?";
$customization_stmt = $inventory->prepare($customization_query);
$customization_stmt->bind_param("i", $product_id);
$customization_stmt->execute();
$customization_result = $customization_stmt->get_result();
$customization = $customization_result->fetch_assoc();

// Fetch available options for this product
$paper_options = [];
$finish_options = [];
$binding_options = [];
$layout_options = [];

if ($customization) {
    // Paper options
    if ($customization['has_paper_option']) {
        $paper_query = "SELECT po.* FROM paper_options po 
                       JOIN product_paper_options ppo ON po.id = ppo.paper_option_id 
                       WHERE ppo.product_id = ?";
        $paper_stmt = $inventory->prepare($paper_query);
        $paper_stmt->bind_param("i", $product_id);
        $paper_stmt->execute();
        $paper_result = $paper_stmt->get_result();
        while ($paper = $paper_result->fetch_assoc()) {
            $paper_options[] = $paper;
        }
    }

    // Repeat similar queries for finish, binding, and layout options
    // Finish options
    if ($customization['has_finish_option']) {
        $finish_query = "SELECT fo.* FROM finish_options fo 
                        JOIN product_finish_options pfo ON fo.id = pfo.finish_option_id 
                        WHERE pfo.product_id = ?";
        $finish_stmt = $inventory->prepare($finish_query);
        $finish_stmt->bind_param("i", $product_id);
        $finish_stmt->execute();
        $finish_result = $finish_stmt->get_result();
        while ($finish = $finish_result->fetch_assoc()) {
            $finish_options[] = $finish;
        }
    }

    // Binding options
    if ($customization['has_binding_option']) {
        $binding_query = "SELECT bo.* FROM binding_options bo 
                         JOIN product_binding_options pbo ON bo.id = pbo.binding_option_id 
                         WHERE pbo.product_id = ?";
        $binding_stmt = $inventory->prepare($binding_query);
        $binding_stmt->bind_param("i", $product_id);
        $binding_stmt->execute();
        $binding_result = $binding_stmt->get_result();
        while ($binding = $binding_result->fetch_assoc()) {
            $binding_options[] = $binding;
        }
    }

    // Layout options
    if ($customization['has_layout_option']) {
        $layout_query = "SELECT lo.* FROM layout_options lo 
                        JOIN product_layout_options plo ON lo.id = plo.layout_option_id 
                        WHERE plo.product_id = ?";
        $layout_stmt = $inventory->prepare($layout_query);
        $layout_stmt->bind_param("i", $product_id);
        $layout_stmt->execute();
        $layout_result = $layout_stmt->get_result();
        while ($layout = $layout_result->fetch_assoc()) {
            $layout_options[] = $layout;
        }
    }
}


// Fetch product details
$query = "SELECT id, product_name, category, price FROM products_offered WHERE id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: ../../website/main.php");
    exit;
}

// Fetch dynamic size and color options for Other Services (IDs 18-21)
$size_options = [];
$color_options = [];
$label = '';
if ($customization && in_array($product_id, [18, 19, 20, 21])) {
    // Size options
    switch ($product_id) {
        case 18: // T-Shirts
            $size_query = "SELECT ts.* FROM tshirt_sizes ts 
                          JOIN product_tshirt_sizes pts ON ts.id = pts.tshirt_size_id 
                          WHERE pts.product_id = ?";
            break;
        case 19: // Tote Bag
            $size_query = "SELECT tos.* FROM totesize_options tos 
                          JOIN product_totesizes ptos ON tos.id = ptos.totesize_id 
                          WHERE ptos.product_id = ?";
            break;
        case 20: // Paper Bag
            $size_query = "SELECT pbs.* FROM paperbag_size_options pbs 
                          JOIN product_paperbag_sizes ppbs ON pbs.id = ppbs.paperbag_size_id 
                          WHERE ppbs.product_id = ?";
            break;
        case 21: // Mug
            $size_query = "SELECT ms.* FROM mug_size_options ms 
                          JOIN product_mug_sizes pms ON ms.id = pms.mug_size_id 
                          WHERE pms.product_id = ?";
            break;
        default:
            $size_query = "";
    }
    if (!empty($size_query)) {
        $size_stmt = $inventory->prepare($size_query);
        $size_stmt->bind_param("i", $product_id);
        $size_stmt->execute();
        $size_result = $size_stmt->get_result();
        while ($size = $size_result->fetch_assoc()) {
            $size_options[] = $size;
        }
    }

    // Color options
    switch ($product_id) {
        case 18: // T-Shirts
            $color_query = "SELECT tc.* FROM tshirt_colors tc 
                           JOIN product_tshirt_colors ptc ON tc.id = ptc.tshirt_color_id 
                           WHERE ptc.product_id = ?";
            $label = "Color:";
            break;
        case 19: // Tote Bag
            $color_query = "SELECT toc.* FROM totecolor_options toc 
                           JOIN product_totecolors ptoc ON toc.id = ptoc.totecolor_id 
                           WHERE ptoc.product_id = ?";
            $label = "Color:";
            break;
        case 21: // Mug
            $color_query = "SELECT mc.* FROM mug_color_options mc 
                           JOIN product_mug_colors pmc ON mc.id = pmc.mug_color_id 
                           WHERE pmc.product_id = ?";
            $label = "Color:";
            break;
        default:
            $color_query = "";
    }
    if (!empty($color_query)) {
        $color_stmt = $inventory->prepare($color_query);
        $color_stmt->bind_param("i", $product_id);
        $color_stmt->execute();
        $color_result = $color_stmt->get_result();
        while ($color = $color_result->fetch_assoc()) {
            $color_options[] = $color;
        }
    }
}

// Check if product is in Other Services category (should show image customization)
$show_image_customization = ($product['category'] === 'Other Services');

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $design_image = isset($_POST['design_image']) ? $_POST['design_image'] : '';
    $front_design_image = isset($_POST['front_design_image']) ? $_POST['front_design_image'] : '';
    $back_design_image = isset($_POST['back_design_image']) ? $_POST['back_design_image'] : '';
    $upload_type = isset($_POST['upload_type']) ? $_POST['upload_type'] : 'single';
    $layout_option = isset($_POST['layout_option']) ? $_POST['layout_option'] : '';
    $layout_details = isset($_POST['layout_details']) ? $_POST['layout_details'] : '';

    // New: Get size/color options
    $size_option = isset($_POST['size_option']) ? $_POST['size_option'] : '';
    $custom_size = isset($_POST['custom_size']) ? $_POST['custom_size'] : '';
    $color_option = isset($_POST['color_option']) ? $_POST['color_option'] : '';
    $custom_color = isset($_POST['custom_color']) ? $_POST['custom_color'] : '';

    // Handle user layout file uploads
    $user_layout_files = [];
    if (isset($_FILES['user_layout_upload']) && $_FILES['user_layout_upload']['error'][0] == 0) {
        $user_id = $_SESSION['user_id'];
        $upload_dir = '../../assets/uploads/user_layouts/' . $user_id . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        foreach ($_FILES['user_layout_upload']['tmp_name'] as $key => $tmp_name) {
            $original_name = basename($_FILES['user_layout_upload']['name'][$key]);
            $target_file = $upload_dir . time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $original_name);
            if (move_uploaded_file($tmp_name, $target_file)) {
                $user_layout_files[] = $target_file;
            }
        }
    }
    $user_layout_files_json = !empty($user_layout_files) ? json_encode($user_layout_files) : '';

    $url = "add_to_cart.php?product_id=" . $product_id . "&quantity=" . $quantity;
    $url .= "&upload_type=" . urlencode($upload_type);

    // Include all design image fields
    $url .= "&design_image=" . urlencode($design_image);
    $url .= "&front_design_image=" . urlencode($front_design_image);
    $url .= "&back_design_image=" . urlencode($back_design_image);

    $url .= "&layout_option=" . urlencode($layout_option);
    $url .= "&layout_details=" . urlencode($layout_details);
    $url .= "&size_option=" . urlencode($size_option);
    $url .= "&custom_size=" . urlencode($custom_size);
    $url .= "&color_option=" . urlencode($color_option);
    $url .= "&custom_color=" . urlencode($custom_color);
    $url .= "&finish_option=" . urlencode($_POST['finish_option'] ?? '');
    $url .= "&paper_option=" . urlencode($_POST['paper_option'] ?? '');
    $url .= "&binding_option=" . urlencode($_POST['binding_option'] ?? '');
    $url .= "&gsm_option=" . urlencode($_POST['gsm_option'] ?? '');
    $url .= "&user_layout_files=" . urlencode($user_layout_files_json);

    header("Location: " . $url);
    exit;
}

// Get cart count for navigation
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $count_query = "SELECT SUM(ci.quantity) as total_items 
                  FROM cart_items ci 
                  JOIN carts c ON ci.cart_id = c.cart_id 
                  WHERE c.user_id = ?";
    $count_stmt = $inventory->prepare($count_query);
    $count_stmt->bind_param("i", $_SESSION['user_id']);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $cart_count = $count_row['total_items'] ? $count_row['total_items'] : 0;
}

// Get base image paths instead of product images
$base_image_path = "../../assets/images/base/base-" . $product['id'] . ".jpg";
$base_image_url = file_exists($base_image_path) ? $base_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Base+Image";
$back_base_image_path = "../../assets/images/base/base-" . $product['id'] . "-1.jpg";
$back_base_image_url = file_exists($back_base_image_path) ? $back_base_image_path : "";

// Get product images for gallery display
$product_image_path = "../../assets/images/services/service-" . $product['id'] . ".jpg";
$product_image_url = file_exists($product_image_path) ? $product_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Product+Image";
$product_back_image_path = "../../assets/images/services/service-" . $product['id'] . "-1.jpg";
$product_back_image_url = file_exists($product_back_image_path) ? $product_back_image_path : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['product_name']; ?> - Product Details</title>
    <link rel="icon" type="image/png" href="../../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        /* Product Detail specific styles that extend the main style.css */
        .product-detail-page {
            padding: 40px 0;
            background-color: var(--bg-light);
        }
        
        .product-detail-container {
            background: var(--bg-white);
            padding: 40px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            border: 1px solid var(--border-color);
        }
        
        .product-detail {
            display: flex;
            gap: 50px;
            align-items: flex-start;
        }
        
        .product-gallery {
            flex: 1;
            max-width: 500px;
        }
        
        .main-image {
            width: 100%;
            height: 450px;
            object-fit: contain;
            margin-bottom: 20px;
            background: var(--bg-light);
            border: 2px solid var(--border-color);
            padding: 20px;
            transition: opacity 0.5s ease-in-out;
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .thumbnail {
            width: 70px;
            height: 70px;
            object-fit: cover;
            cursor: pointer;
            border: 3px solid transparent;
            transition: var(--transition);
            background: var(--bg-light);
            padding: 2px;
            transition: all 0.3s ease;
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-title {
            font-size: 2.2em;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-weight: 700;
        }
        
        .product-category {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 8px 16px;
            font-size: 0.95em;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .product-price {
            font-size: 2em;
            color: var(--accent-color);
            margin-bottom: 25px;
            font-weight: bold;
        }
        
        .customization-section {
            margin-bottom: 30px;
            padding: 25px;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.3em;
            margin-bottom: 20px;
            color: var(--text-dark);
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary-color);
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .quantity-btn {
            width: 45px;
            height: 45px;
            background: var(--bg-white);
            border: 2px solid var(--border-color);
            font-size: 1.3em;
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
        
        .quantity-input {
            width: 80px;
            height: 45px;
            text-align: center;
            border: 2px solid var(--border-color);
            font-size: 1.2em;
            font-weight: 600;
            background: var(--bg-white);
        }
        
        .image-upload-section {
            margin-bottom: 25px;
        }
        
        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            background: var(--text-light);
            color: white;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .upload-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .upload-preview {
            margin-top: 20px;
            display: none;
            text-align: center;
            padding: 15px;
            background: var(--bg-white);
            border: 2px dashed var(--border-color);
        }
        
        .uploaded-image {
            max-width: 220px;
            max-height: 180px;
            border: 2px solid var(--primary-color);
            margin-bottom: 15px;
        }
        
        .preview-section {
            margin-bottom: 30px;
            padding: 25px;
            background: var(--bg-white);
            border: 1px solid var(--border-color);
        }
        
        .preview-container {
            width: 100%;
            height: 220px;
            background: var(--bg-light);
            border: 2px dashed var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .mockup-preview {
            max-width: 100%;
            max-height: 200px;
            display: none;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }
        
        /* Draggable Design Styles */
        .positioning-tools {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .tool-btn {
            padding: 10px 15px;
            background: var(--text-light);
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
        }
        
        .tool-btn:hover {
            background: #5a6268;
        }
        
        .positioning-container {
            width: 100%;
            height: 400px;
            border: 2px dashed var(--border-color);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .product-base-image {
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        #baseImage {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .design-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .draggable-design {
            position: absolute;
            cursor: move;
            border: 2px dashed var(--primary-color);
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            box-sizing: border-box;
        }
        
        .resize-handle {
            position: absolute;
            width: 12px;
            height: 12px;
            background: var(--primary-color);
            bottom: -6px;
            right: -6px;
            cursor: nwse-resize;
        }
        
        /* Visual boundary indicator */
        .design-boundary {
            position: absolute;
            border: 2px dashed rgba(44, 90, 160, 0.3);
            background-color: rgba(44, 90, 160, 0.1);
            pointer-events: none;
            display: none;
        }
        
        /* Mockup view selector */
        .view-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .view-btn {
            padding: 10px 15px;
            background: var(--text-light);
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .view-btn.active {
            background: var(--primary-color);
        }
        
        .view-btn:hover {
            background: var(--primary-dark);
        }
        
        /* Mockup Popup Styles */
        .mockup-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .mockup-container {
            background: var(--bg-white);
            padding: 40px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .close-popup {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 28px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            transition: var(--transition);
        }
        
        .close-popup:hover {
            color: #000;
        }
        
        .mockup-images {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            justify-content: center;
            margin: 30px 0;
        }
        
        .mockup-image {
            text-align: center;
            display: none;
        }
        
        .mockup-image img {
            max-width: 320px;
            max-height: 420px;
            border: 2px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .download-btn {
            margin-top: 15px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .download-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .form-control:invalid {
            border-color: var(--accent-color) !important;
        }
        
        .required-field::after {
            content: " *";
            color: var(--accent-color);
        }
        
        .validation-error {
            color: var(--accent-color);
            font-size: 0.875em;
            margin-top: 5px;
            display: none;
        }
        
        .section-with-error {
            border: 2px solid var(--accent-color) !important;
            background-color: #f8d7da !important;
        }
        
        /* Button-based option styles */
        .option-button {
            padding: 12px 20px;
            background: var(--bg-light);
            border: 2px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            color: var(--text-dark);
            flex: 1;
            min-width: 120px;
            text-align: center;
            text-transform: uppercase;
            font-family: 'Poppins';
        }
        
        .option-button:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            transform: translateY(-2px);
        }
        
        .option-button.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.3);
        }
        
        .option-button.custom-option {
            border: 2px dashed var(--text-light);
        }
        
        .option-button.custom-option.selected {
            border: 2px solid var(--primary-color);
        }
        
        .button-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .upload-type-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .upload-type-buttons label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px 15px;
            background: var(--bg-light);
            transition: var(--transition);
        }

        .upload-type-buttons label:hover {
            background: #e9ecef;
        }

        .upload-type-buttons input[type="radio"] {
            accent-color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .product-detail {
                flex-direction: column;
            }
            
            .product-gallery {
                max-width: 100%;
            }
            
            .thumbnail {
                width: 60px;
                height: 60px;
            }
            
            .main-image {
                height: 350px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .mockup-container {
                padding: 25px;
                width: 95%;
            }
            
            .mockup-images {
                flex-direction: column;
                gap: 20px;
            }
            
            .positioning-container {
                height: 300px;
            }
            
            .option-button {
                min-width: 100px;
                padding: 10px 15px;
                font-size: 0.9em;
            }
            
            .button-options {
                gap: 8px;
            }

            .upload-type-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .design-areas {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .design-area {
                padding: 20px;
            }
            
            .upload-zone {
                padding: 20px;
            }

            .auth-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 576px) {
            .product-detail-container {
                padding: 25px;
            }
            
            .product-title {
                font-size: 1.8em;
            }
            
            .product-price {
                font-size: 1.6em;
            }
            
            .customization-section {
                padding: 20px;
            }
            
            .thumbnail {
                width: 50px;
                height: 50px;
            }
            
            .main-image {
                height: 280px;
            }

            .thumbnail-container {
                gap: 8px;
            }

            .login-prompt {
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
                    <a href="../website/profile.php" class="user-profile">
                        <i class="fas fa-user"></i>
                        <span class="user-name">
                            <?php echo $_SESSION['username'] ?? 'User'; ?>
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

    <!-- Product Detail Section -->
    <section class="product-detail-page">
        <div class="container">
            <div class="product-detail-container">
                <div class="product-detail">
                    <div class="product-gallery">
                        <!-- Show product images in gallery -->
                        <?php
                        // Array to store all product images
                        $product_images = [];
                        
                        // Check for up to 5 product images
                        for ($i = 0; $i < 5; $i++) {
                            $suffix = $i > 0 ? '-' . $i : '';
                            $image_path = "../../assets/images/services/service-" . $product['id'] . $suffix . ".jpg";
                            
                            if (file_exists($image_path)) {
                                $product_images[] = [
                                    'path' => $image_path,
                                    'alt' => $product['product_name'] . ($i > 0 ? ' - View ' . ($i + 1) : ''),
                                    'index' => $i
                                ];
                            }
                        }
                        
                        // If no images found, use placeholder
                        if (empty($product_images)) {
                            $product_images[] = [
                                'path' => "https://via.placeholder.com/500x500/2c5aa0/ffffff?text=Product+Image",
                                'alt' => $product['product_name'],
                                'index' => 0
                            ];
                        }
                        
                        // Main image (first one)
                        $main_image = $product_images[0];
                        ?>
                        
                        <img src="<?php echo $main_image['path']; ?>" alt="<?php echo $main_image['alt']; ?>" class="main-image" id="mainImage">

                        <div class="thumbnail-container">
                            <?php foreach ($product_images as $index => $image): ?>
                                <img src="<?php echo $image['path']; ?>"
                                    alt="Thumbnail <?php echo $index + 1; ?>" 
                                    class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                    onclick="changeImage(this, <?php echo $index; ?>)"
                                    data-image-index="<?php echo $index; ?>">
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="product-info">
                        <h1 class="product-title"><?php echo $product['product_name']; ?></h1>
                        <span class="product-category"><?php echo $product['category']; ?></span>

                        <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>

                        <form method="post" id="cartForm" action="" enctype="multipart/form-data">
                            <input type="hidden" name="add_to_cart" value="1">

                            <?php if ($customization && in_array($product_id, [18, 19, 20, 21])): ?>
                                <div class="customization-section">
                                    <h3 class="section-title required-field"><i class="fas fa-ruler-combined"></i> Size</h3>
                                    <?php if (!empty($size_options)): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <div class="button-options">
                                                <?php foreach ($size_options as $size): 
                                                    $display_name = isset($size['dimensions']) ? $size['size_name'] . ' (' . $size['dimensions'] . ')' : $size['size_name'];
                                                ?>
                                                    <button type="button" 
                                                            class="option-button <?php echo $size['is_custom'] ? 'custom-option' : ''; ?>" 
                                                            data-value="<?php echo $size['id']; ?>" 
                                                            data-custom="<?php echo $size['is_custom']; ?>"
                                                            onclick="selectOption(this, 'size')">
                                                        <?php echo $display_name; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="size_option" id="sizeOption" value="">
                                            <div id="customSizeContainer" style="margin-top: 10px; display: none;">
                                                <input type="text" name="custom_size" placeholder="Please specify your custom size" 
                                                       style="padding: 10px; border-radius: 5px; border: 1px solid var(--border-color); width: 100%; background: var(--bg-white);">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="customization-section">
                                    <h3 class="section-title required-field"><i class="fas fa-palette"></i> Color</h3>
                                    <?php if (!empty($color_options)): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <div class="button-options">
                                                <?php foreach ($color_options as $color): ?>
                                                    <button type="button" 
                                                            class="option-button <?php echo $color['is_custom'] ? 'custom-option' : ''; ?>" 
                                                            data-value="<?php echo $color['id']; ?>" 
                                                            data-custom="<?php echo $color['is_custom']; ?>"
                                                            onclick="selectOption(this, 'color')">
                                                        <?php echo $color['color_name']; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="color_option" id="colorOption" value="">
                                            <div id="customColorContainer" style="margin-top: 10px; display: none;">
                                                <input type="text" name="custom_color" placeholder="Please specify your custom color" 
                                                       style="padding: 10px; border-radius: 5px; border: 1px solid var(--border-color); width: 100%; background: var(--bg-white);">
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($product_id == 20): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <button type="button" class="option-button selected" data-value="brown" onclick="selectOption(this, 'color')">
                                                Brown (Standard)
                                            </button>
                                            <input type="hidden" name="color_option" value="brown">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php
                            if (
                                $customization &&
                                !in_array($product_id, [18, 19, 20, 21]) &&
                                in_array($product['category'], ['RISO Printing', 'Offset Printing', 'Digital Printing'])
                            ):
                            ?>
                                <div class="customization-section">
                                    <h3 class="section-title"><i class="fas fa-cog"></i> Printing Options</h3>

                                    <?php if ($customization['has_paper_option'] && !empty($paper_options)): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Paper Type:</label>
                                            <div class="button-options">
                                                <?php foreach ($paper_options as $paper): ?>
                                                    <button type="button" class="option-button" data-value="<?php echo $paper['id']; ?>" onclick="selectOption(this, 'paper')">
                                                        <?php echo $paper['option_name']; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="paper_option" id="paperOption" value="">
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    // Only show for printing categories, not for Other Services (IDs 18-21)
                                    if (
                                        $customization['has_size_option'] &&
                                        !in_array($product_id, [18, 19, 20, 21]) &&
                                        in_array($product['category'], ['Riso Printing', 'Offset Printing', 'Digital Printing'])
                                    ): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Size (in inches):</label>
                                            <input type="text" name="size_option" placeholder="e.g., 8.5 x 11" style="padding: 10px; border-radius: 5px; border: 1px solid var(--border-color); width: 100%; background: var(--bg-white);">
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($customization['has_finish_option'] && !empty($finish_options)): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Finish:</label>
                                            <div class="button-options">
                                                <?php foreach ($finish_options as $finish): ?>
                                                    <button type="button" class="option-button" data-value="<?php echo $finish['id']; ?>" onclick="selectOption(this, 'finish')">
                                                        <?php echo $finish['option_name']; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="finish_option" id="finishOption" value="">
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($customization['has_layout_option'] && !empty($layout_options)): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Layout Option:</label>
                                            <div class="button-options">
                                                <?php foreach ($layout_options as $layout): ?>
                                                    <button type="button" class="option-button" data-value="<?php echo $layout['id']; ?>" onclick="selectLayoutOption(this)">
                                                        <?php echo $layout['option_name']; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="layout_option" id="layoutOption" value="">
                                            
                                            <div id="layoutInputContainer" style="margin-top: 10px; display: none;">
                                                <!-- Content will be populated by JavaScript based on selection -->
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($customization['has_binding_option'] && !empty($binding_options)): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Binding:</label>
                                            <div class="button-options">
                                                <?php foreach ($binding_options as $binding): ?>
                                                    <button type="button" class="option-button" data-value="<?php echo $binding['id']; ?>" onclick="selectOption(this, 'binding')">
                                                        <?php echo $binding['option_name']; ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="binding_option" id="bindingOption" value="">
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($customization['has_gsm_option']): ?>
                                        <div class="option-group" style="margin-bottom: 20px;">
                                            <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Paper Weight (GSM):</label>
                                            <input type="number" name="gsm_option" placeholder="e.g., 120" min="0" style="padding: 10px; border-radius: 5px; border: 1px solid var(--border-color); width: 100%; background: var(--bg-white);">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="customization-section">
                                <h3 class="section-title"><i class="fas fa-shopping-cart"></i> Quantity</h3>
                                <div class="quantity-selector">
                                    <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                                    <input type="number" name="quantity" class="quantity-input" id="quantity" value="1" min="1">
                                    <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                                </div>
                            </div>

                            <?php if ($show_image_customization): ?>
                                <div class="customization-section">
                                    <h3 class="section-title"><i class="fas fa-paint-brush"></i> Customize Your Product</h3>

                                    <!-- Design Areas - Always show both front and back areas -->
                                    <div class="design-areas">
                                        <!-- Front Design Area -->
                                        <div class="design-area front-design" id="frontDesignArea">
                                            <h4 class="design-area-title">
                                                <i class="fas fa-tshirt"></i> Front Design
                                                <span class="design-status" id="frontDesignStatus">(Not uploaded)</span>
                                            </h4>
                                            
                                            <div class="design-upload-container">
                                                <label class="upload-zone" id="frontUploadZone">
                                                    <input type="file" id="frontDesignUpload" name="front_design_upload" accept="image/*" hidden 
                                                        onchange="handleDesignUpload(this, 'front')">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span class="upload-text">Upload Front Design</span>
                                                    <small class="upload-hint">JPG, PNG, GIF (Max 5MB)</small>
                                                </label>
                                                
                                                <div class="design-preview" id="frontDesignPreview">
                                                    <img src="" alt="Front Design Preview" id="frontPreviewImage">
                                                    <div class="design-actions">
                                                        <button type="button" class="btn-remove-design" onclick="removeDesign('front')">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Back Design Area -->
                                        <div class="design-area back-design" id="backDesignArea" 
                                            style="<?php echo empty($back_base_image_url) ? 'display: none;' : ''; ?>">
                                            <h4 class="design-area-title">
                                                <i class="fas fa-tshirt"></i> Back Design
                                                <span class="design-status" id="backDesignStatus">(Not uploaded)</span>
                                            </h4>
                                            
                                            <div class="design-upload-container">
                                                <label class="upload-zone" id="backUploadZone">
                                                    <input type="file" id="backDesignUpload" name="back_design_upload" accept="image/*" hidden 
                                                        onchange="handleDesignUpload(this, 'back')">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span class="upload-text">Upload Back Design</span>
                                                    <small class="upload-hint">JPG, PNG, GIF (Max 5MB)</small>
                                                </label>
                                                
                                                <div class="design-preview" id="backDesignPreview">
                                                    <img src="" alt="Back Design Preview" id="backPreviewImage">
                                                    <div class="design-actions">
                                                        <button type="button" class="btn-remove-design" onclick="removeDesign('back')">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Auto-determined design type indicator -->
                                    <div class="design-type-indicator" id="designTypeIndicator">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="designTypeText">Upload designs to see customization type</span>
                                    </div>

                                    <!-- Positioning Section -->
                                    <div class="positioning-section">
                                        <h4 class="section-title"><i class="fas fa-arrows-alt"></i> Position Your Design</h4>

                                        <div class="view-selector">
                                            <button type="button" class="view-btn active" id="frontViewBtn" onclick="switchView('front')">
                                                <i class="fas fa-tshirt"></i> Front View
                                            </button>
                                            <?php if (!empty($back_base_image_url)): ?>
                                                <button type="button" class="view-btn" id="backViewBtn" onclick="switchView('back')">
                                                    <i class="fas fa-tshirt"></i> Back View
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <div class="positioning-tools">
                                            <button type="button" class="tool-btn" onclick="enableDragging()" id="dragBtn">
                                                <i class="fas fa-arrows-alt"></i> Move Design
                                            </button>
                                            <button type="button" class="tool-btn" onclick="resizeDesign(1.1)">
                                                <i class="fas fa-search-plus"></i> Enlarge
                                            </button>
                                            <button type="button" class="tool-btn" onclick="resizeDesign(0.9)">
                                                <i class="fas fa-search-minus"></i> Shrink
                                            </button>
                                            <button type="button" class="tool-btn" onclick="resetDesignPosition()">
                                                <i class="fas fa-redo"></i> Reset
                                            </button>
                                            <button type="button" class="tool-btn" onclick="toggleBoundary()" id="boundaryBtn">
                                                <i class="fas fa-border-all"></i> Show Boundaries
                                            </button>
                                        </div>

                                        <div class="positioning-container">
                                            <div class="product-base-image">
                                                <img src="<?php echo $base_image_url; ?>" alt="Product Base" id="baseImage">
                                                <div id="designOverlay" class="design-overlay"></div>
                                                <div id="designBoundary" class="design-boundary"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="preview-section">
                                        <h4 class="section-title"><i class="fas fa-eye"></i> Design Preview</h4>
                                        <div class="preview-container">
                                            <img src="" alt="Mockup Preview" class="mockup-preview" id="mockupPreview">
                                            <p id="previewText" style="color: var(--text-light); font-style: italic;">Upload an image to generate preview</p>
                                        </div>
                                        <button type="button" class="btn btn-secondary" onclick="generateMockup()">
                                            <i class="fas fa-image"></i> Generate Mockup
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="action-buttons">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </div>

                            <input type="hidden" name="design_image" id="designImageInput" value="">
                            <input type="hidden" name="front_design_image" id="frontDesignImageInput" value="">
                            <input type="hidden" name="back_design_image" id="backDesignImageInput" value="">
                            <input type="hidden" name="upload_type" id="uploadTypeInput" value="single">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mockup Popup -->
    <div class="mockup-popup" id="mockupPopup">
        <div class="mockup-container">
            <button class="close-popup" onclick="closeModal()">&times;</button>
            <h2 style="text-align: center; color: var(--text-dark); margin-bottom: 10px;">
                <i class="fas fa-palette"></i> Your <?php echo $product['product_name']; ?> Mockup
            </h2>
            <p style="text-align: center; color: var(--text-light); margin-bottom: 30px;">Preview your custom design</p>

            <div class="mockup-images">
                <div class="mockup-image" id="frontMockupContainer">
                    <img src="" alt="Front View" id="mockupFront">
                    <p style="margin: 15px 0; font-weight: 600; color: var(--text-dark);">Front View</p>
                    <button class="download-btn" onclick="downloadMockup('mockupFront', 'front-design.png')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <div class="mockup-image" id="backMockupContainer">
                    <img src="" alt="Back View" id="mockupBack">
                    <p style="margin: 15px 0; font-weight: 600; color: var(--text-dark);">Back View</p>
                    <button class="download-btn" onclick="downloadMockup('mockupBack', 'back-design.png')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>

            <div class="action-buttons" style="margin-top: 30px; justify-content: center;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()" style="flex: none; padding: 12px 25px;">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" onclick="useThisDesign()" style="flex: none; padding: 12px 25px;">
                    <i class="fas fa-check"></i> Use This Design
                </button>
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

    <script src="../../assets/js/main.js"></script>
    <script>
        // Global variables for the new design system
        let frontDesign = null;
        let backDesign = null;
        let currentMockup = null;
        let backMockup = null;
        let isDraggingEnabled = false;
        let currentDesign = null;
        let currentView = 'front';
        let currentUploadType = 'none'; // 'front_only', 'back_only', 'both_sides'
        let frontDesignPosition = {
            x: 100,
            y: 100,
            width: 200,
            height: 200
        };
        let backDesignPosition = {
            x: 100,
            y: 100,
            width: 200,
            height: 200
        };
        let isDragging = false;
        let isResizing = false;
        let startX, startY;
        let initialDesignPosition;
        let showBoundary = false;
        let imageBoundary = {
            x: 0,
            y: 0,
            width: 0,
            height: 0
        };
        let frontImageUrl = "<?php echo $base_image_url; ?>";
        let backImageUrl = "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>";

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate the image boundary for the initial product image
            const baseImage = document.getElementById('baseImage');
            baseImage.onload = function() {
                calculateImageBoundary();
            };

            // Initialize upload type
            document.getElementById('uploadTypeInput').value = currentUploadType;

            // Auto-select first option for each button group
            setTimeout(() => {
                autoSelectFirstOptions();
            }, 100);

            // Close popup when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === document.getElementById('mockupPopup')) {
                    closeModal();
                }
            });

            // Setup canvas quality
            if (window.HTMLCanvasElement) {
                const originalGetContext = HTMLCanvasElement.prototype.getContext;
                HTMLCanvasElement.prototype.getContext = function() {
                    const context = originalGetContext.apply(this, arguments);
                    if (context && context.imageSmoothingEnabled !== undefined) {
                        context.imageSmoothingEnabled = true;
                        context.imageSmoothingQuality = 'high';
                    }
                    return context;
                };
            }

            // Check for AI-generated design on page load
            checkForAIDesign();
        });

        // Check for AI-generated design on page load
        function checkForAIDesign() {
            const aiDesign = sessionStorage.getItem('aiGeneratedDesign');
            const aiProductId = sessionStorage.getItem('aiDesignProductId');
            const aiPlacement = sessionStorage.getItem('aiDesignPlacement');
            const currentProductId = <?php echo $product_id; ?>;
            const urlParams = new URLSearchParams(window.location.search);
            const aiDesignParam = urlParams.get('ai_design');
            
            console.log('Checking for AI design:', {
                hasDesign: !!aiDesign,
                aiProductId: aiProductId,
                currentProductId: currentProductId,
                aiDesignParam: aiDesignParam
            });
            
            if (aiDesign && aiProductId && aiProductId == currentProductId && aiDesignParam === '1') {
                console.log('Auto-populating AI design for product:', currentProductId);
                autoPopulateAIDesign(aiDesign);
                
                // Clear the session storage
                sessionStorage.removeItem('aiGeneratedDesign');
                sessionStorage.removeItem('aiDesignProductId');
                sessionStorage.removeItem('aiDesignPlacement');
                sessionStorage.removeItem('aiDesignTimestamp');
                
                // Remove the ai_design parameter from URL without reloading
                const newUrl = window.location.pathname + '?id=' + currentProductId;
                window.history.replaceState({}, '', newUrl);
            }
        }

        // Handle design upload for both front and back
        function handleDesignUpload(input, side) {
            const file = input.files[0];
            if (!file) return;

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size too large. Maximum size is 5MB.');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                const previewId = side + 'PreviewImage';
                const previewContainer = side + 'DesignPreview';
                const statusId = side + 'DesignStatus';
                const designArea = side + 'DesignArea';

                // Update preview
                document.getElementById(previewId).src = event.target.result;
                document.getElementById(previewContainer).style.display = 'block';
                
                // Update status and area styling
                document.getElementById(statusId).textContent = '(Uploaded)';
                document.getElementById(statusId).style.color = '#28a745';
                document.getElementById(designArea).classList.add('has-design');

                // Hide upload zone
                document.getElementById(side + 'UploadZone').style.display = 'none';

                // Store design
                if (side === 'front') {
                    frontDesign = new Image();
                    frontDesign.src = event.target.result;
                    frontDesign.onload = function() {
                        if (currentView === 'front') {
                            initDesignOverlay(event.target.result);
                        }
                        calculateImageBoundary();
                        updateDesignType();
                        updatePreview();
                    };
                } else {
                    backDesign = new Image();
                    backDesign.src = event.target.result;
                    backDesign.onload = function() {
                        if (currentView === 'back') {
                            initDesignOverlay(event.target.result);
                        }
                        calculateImageBoundary();
                        updateDesignType();
                        updatePreview();
                    };
                }
            };
            reader.readAsDataURL(file);
        }

        // Remove design
        function removeDesign(side) {
            const inputId = side + 'DesignUpload';
            const previewContainer = side + 'DesignPreview';
            const statusId = side + 'DesignStatus';
            const designArea = side + 'DesignArea';
            const uploadZone = side + 'UploadZone';

            // Reset input
            document.getElementById(inputId).value = '';
            
            // Hide preview and show upload zone
            document.getElementById(previewContainer).style.display = 'none';
            document.getElementById(uploadZone).style.display = 'flex';
            
            // Update status and styling
            document.getElementById(statusId).textContent = '(Not uploaded)';
            document.getElementById(statusId).style.color = '';
            document.getElementById(designArea).classList.remove('has-design');

            // Clear design data
            if (side === 'front') {
                frontDesign = null;
                if (currentView === 'front') {
                    resetDesignOverlay();
                }
            } else {
                backDesign = null;
                if (currentView === 'back') {
                    resetDesignOverlay();
                }
            }

            updateDesignType();
            updatePreview();
        }

        // Automatically determine design type based on uploaded designs
        function updateDesignType() {
            const hasFront = frontDesign !== null;
            const hasBack = backDesign !== null;
            const hasBackTemplate = backImageUrl !== '';

            let designType = 'none';
            let designTypeText = '';

            if (hasFront && !hasBack) {
                designType = 'front_only';
                designTypeText = 'Front Only Design - The back will be plain';
            } else if (!hasFront && hasBack) {
                designType = 'back_only';
                designTypeText = 'Back Only Design - The front will be plain';
            } else if (hasFront && hasBack) {
                designType = 'both_sides';
                designTypeText = 'Both Sides Design - Front and back will have different designs';
            } else {
                designTypeText = 'Upload designs to see customization type';
            }

            // Add note about mockup preview
            if (hasFront || hasBack) {
                designTypeText += ' (Both sides will be shown in mockup preview)';
            }

            currentUploadType = designType;
            document.getElementById('designTypeText').textContent = designTypeText;
            document.getElementById('uploadTypeInput').value = designType;
        }

        // Update preview based on current view and available designs
        function updatePreview() {
            const previewText = document.getElementById('previewText');
            const mockupPreview = document.getElementById('mockupPreview');

            if (currentView === 'front' && frontDesign) {
                previewText.style.display = 'none';
                mockupPreview.style.display = 'block';
                mockupPreview.src = frontDesign.src;
            } else if (currentView === 'back' && backDesign) {
                previewText.style.display = 'none';
                mockupPreview.style.display = 'block';
                mockupPreview.src = backDesign.src;
            } else {
                previewText.style.display = 'block';
                mockupPreview.style.display = 'none';
            }
        }

        // Auto-select first option for each button group
        function autoSelectFirstOptions() {
            // Size options
            const sizeButtons = document.querySelectorAll('.option-button[data-value][onclick*="size"]');
            if (sizeButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="size"]')) {
                selectOption(sizeButtons[0], 'size');
            }

            // Color options
            const colorButtons = document.querySelectorAll('.option-button[data-value][onclick*="color"]');
            if (colorButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="color"]')) {
                selectOption(colorButtons[0], 'color');
            }

            // Paper options
            const paperButtons = document.querySelectorAll('.option-button[data-value][onclick*="paper"]');
            if (paperButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="paper"]')) {
                selectOption(paperButtons[0], 'paper');
            }

            // Finish options
            const finishButtons = document.querySelectorAll('.option-button[data-value][onclick*="finish"]');
            if (finishButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="finish"]')) {
                selectOption(finishButtons[0], 'finish');
            }

            // Layout options
            const layoutButtons = document.querySelectorAll('.option-button[data-value][onclick*="selectLayoutOption"]');
            if (layoutButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="selectLayoutOption"]')) {
                selectLayoutOption(layoutButtons[0]);
            }

            // Binding options
            const bindingButtons = document.querySelectorAll('.option-button[data-value][onclick*="binding"]');
            if (bindingButtons.length > 0 && !document.querySelector('.option-button.selected[onclick*="binding"]')) {
                selectOption(bindingButtons[0], 'binding');
            }
        }

        // Handle option selection for buttons
        function selectOption(button, type) {
            // Remove selected class from all buttons in the same group
            const buttonGroup = button.closest('.option-group');
            if (buttonGroup) {
                buttonGroup.querySelectorAll('.option-button').forEach(btn => {
                    btn.classList.remove('selected');
                });
            }
            
            // Add selected class to clicked button
            button.classList.add('selected');
            
            // Update the hidden input value
            const value = button.getAttribute('data-value');
            const hiddenInput = document.getElementById(type + 'Option');
            if (hiddenInput) {
                hiddenInput.value = value;
            }
            
            // Handle custom options
            const isCustom = button.getAttribute('data-custom') === '1';
            if (type === 'size') {
                document.getElementById('customSizeContainer').style.display = isCustom ? 'block' : 'none';
            } else if (type === 'color') {
                document.getElementById('customColorContainer').style.display = isCustom ? 'block' : 'none';
            }
        }

        // Handle layout option selection
        function selectLayoutOption(button) {
            // Remove selected class from all layout buttons
            const buttonGroup = button.closest('.option-group');
            if (buttonGroup) {
                buttonGroup.querySelectorAll('.option-button').forEach(btn => {
                    btn.classList.remove('selected');
                });
            }
            
            // Add selected class to clicked button
            button.classList.add('selected');
            
            // Update the hidden input value
            const value = button.getAttribute('data-value');
            document.getElementById('layoutOption').value = value;
            
            // Handle layout-specific inputs
            handleLayoutOptionChange();
        }

        // Switch between front and back views
        function switchView(view) {
            // Only allow switching to back view if back image exists
            if (view === 'back' && !backImageUrl) {
                alert('Back view is not available for this product');
                return;
            }

            currentView = view;

            // Update button states
            document.getElementById('frontViewBtn').classList.toggle('active', view === 'front');
            document.getElementById('backViewBtn').classList.toggle('active', view === 'back');

            // Change the base image
            const baseImage = document.getElementById('baseImage');
            if (view === 'front') {
                baseImage.src = frontImageUrl;
            } else {
                baseImage.src = backImageUrl;
            }

            // Reinitialize design overlay for the current view
            if (view === 'front' && frontDesign) {
                initDesignOverlay(frontDesign.src);
            } else if (view === 'back' && backDesign) {
                initDesignOverlay(backDesign.src);
            } else {
                resetDesignOverlay();
            }

            updatePreview();
            setTimeout(calculateImageBoundary, 100);
        }

        // Calculate the actual image boundary within the container with better precision
        function calculateImageBoundary() {
            const container = document.querySelector('.positioning-container');
            const img = document.getElementById('baseImage');
            
            if (!img.complete) {
                // If image isn't loaded yet, wait for it
                img.onload = calculateImageBoundary;
                return;
            }
            
            // Get the natural dimensions of the image
            const naturalWidth = img.naturalWidth;
            const naturalHeight = img.naturalHeight;
            
            // Get the displayed dimensions
            const displayedWidth = img.offsetWidth;
            const displayedHeight = img.offsetHeight;
            
            // Calculate the aspect ratios
            const containerAspect = container.offsetWidth / container.offsetHeight;
            const imageAspect = naturalWidth / naturalHeight;
            
            // Calculate the actual displayed image area (not including any whitespace)
            if (imageAspect > containerAspect) {
                // Image is wider than container - image fills width, centered vertically
                imageBoundary.width = container.offsetWidth;
                imageBoundary.height = container.offsetWidth / imageAspect;
                imageBoundary.x = 0;
                imageBoundary.y = (container.offsetHeight - imageBoundary.height) / 2;
            } else {
                // Image is taller than container - image fills height, centered horizontally
                imageBoundary.height = container.offsetHeight;
                imageBoundary.width = container.offsetHeight * imageAspect;
                imageBoundary.y = 0;
                imageBoundary.x = (container.offsetWidth - imageBoundary.width) / 2;
            }
            
            console.log('Image Boundary:', imageBoundary); // Debug info
            
            // Update boundary indicator if shown
            if (showBoundary) {
                updateBoundaryIndicator();
            }
        }

        // Update the boundary indicator
        function updateBoundaryIndicator() {
            const boundary = document.getElementById('designBoundary');
            boundary.style.display = 'block';
            boundary.style.left = `${imageBoundary.x}px`;
            boundary.style.top = `${imageBoundary.y}px`;
            boundary.style.width = `${imageBoundary.width}px`;
            boundary.style.height = `${imageBoundary.height}px`;
        }

        // Toggle boundary visibility
        function toggleBoundary() {
            showBoundary = !showBoundary;
            const btn = document.getElementById('boundaryBtn');

            if (showBoundary) {
                btn.style.background = '#007bff';
                btn.innerHTML = '<i class="fas fa-border-all"></i> Hide Boundaries';
                updateBoundaryIndicator();
            } else {
                btn.style.background = '#6c757d';
                btn.innerHTML = '<i class="fas fa-border-all"></i> Show Boundaries';
                document.getElementById('designBoundary').style.display = 'none';
            }
        }

        function changeImage(element, imageIndex) {
            // Update main image
            document.getElementById('mainImage').src = element.src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
            
            // Don't change the base image - it should stay as the template
        }

        let slideIndex = 0;
        const slideInterval = setInterval(() => {
            const thumbs = document.querySelectorAll('.thumbnail');
            if (thumbs.length > 1) {
                slideIndex = (slideIndex + 1) % thumbs.length;
                const nextThumb = thumbs[slideIndex];
                document.getElementById('mainImage').src = nextThumb.src;
                document.querySelector('.thumbnail.active')?.classList.remove('active');
                nextThumb.classList.add('active');
            }
        }, 3000);

        // Quantity controls
        function increaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            quantityInput.value = parseInt(quantityInput.value) + 1;
        }

        function decreaseQuantity() {
            const quantityInput = document.getElementById('quantity');
            if (parseInt(quantityInput.value) > 1) {
                quantityInput.value = parseInt(quantityInput.value) - 1;
            }
        }

        // Enable/disable dragging
        function enableDragging() {
            const hasDesign = (currentView === 'front' && frontDesign) || (currentView === 'back' && backDesign);
            
            if (!hasDesign) {
                alert(`Please upload a ${currentView} design image first!`);
                return;
            }

            isDraggingEnabled = !isDraggingEnabled;
            const btn = document.getElementById('dragBtn');

            if (isDraggingEnabled) {
                btn.style.background = '#007bff';
                btn.innerHTML = '<i class="fas fa-hand-paper"></i> Dragging Enabled';

                if (currentDesign) {
                    currentDesign.style.cursor = 'move';
                    currentDesign.style.pointerEvents = 'auto';
                }
            } else {
                btn.style.background = '#6c757d';
                btn.innerHTML = '<i class="fas fa-arrows-alt"></i> Move Design';

                if (currentDesign) {
                    currentDesign.style.cursor = 'default';
                    currentDesign.style.pointerEvents = 'none';
                }
            }
        }

        // Initialize design overlay with better positioning
        function initDesignOverlay(imageSrc) {
            const overlay = document.getElementById('designOverlay');
            overlay.innerHTML = '';
            
            // Get the current design position for the active view
            const designPosition = currentView === 'front' ? frontDesignPosition : backDesignPosition;
            
            // Create design element with high-quality rendering
            const designElement = document.createElement('div');
            designElement.className = 'draggable-design';
            designElement.style.backgroundImage = `url(${imageSrc})`;
            designElement.style.backgroundSize = 'contain';
            designElement.style.backgroundRepeat = 'no-repeat';
            designElement.style.backgroundPosition = 'center';
            designElement.style.width = `${designPosition.width}px`;
            designElement.style.height = `${designPosition.height}px`;
            designElement.style.left = `${designPosition.x}px`;
            designElement.style.top = `${designPosition.y}px`;
            
            // Add resize handle
            const resizeHandle = document.createElement('div');
            resizeHandle.className = 'resize-handle';
            designElement.appendChild(resizeHandle);
            
            // Add event listeners for dragging
            designElement.addEventListener('mousedown', startDrag);
            resizeHandle.addEventListener('mousedown', startResize);
            
            overlay.appendChild(designElement);
            currentDesign = designElement;
            
            // Enable dragging by default when design is loaded
            isDraggingEnabled = true;
            document.getElementById('dragBtn').style.background = '#007bff';
            document.getElementById('dragBtn').innerHTML = '<i class="fas fa-hand-paper"></i> Dragging Enabled';
            designElement.style.cursor = 'move';
            designElement.style.pointerEvents = 'auto';
        }

        // Helper function to reset design overlay
        function resetDesignOverlay() {
            const overlay = document.getElementById('designOverlay');
            overlay.innerHTML = '';
            currentDesign = null;
            isDraggingEnabled = false;
            document.getElementById('dragBtn').innerHTML = '<i class="fas fa-arrows-alt"></i> Move Design';
            document.getElementById('dragBtn').style.background = '#6c757d';
        }

        // Start dragging
        function startDrag(e) {
            if (!isDraggingEnabled) return;
            if (e.target.classList.contains('resize-handle')) return;

            e.preventDefault();
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            initialDesignPosition = {
                x: parseInt(currentDesign.style.left),
                y: parseInt(currentDesign.style.top)
            };

            document.addEventListener('mousemove', doDrag);
            document.addEventListener('mouseup', stopDrag);
        }

        // Perform dragging with boundary constraints
        function doDrag(e) {
            if (!isDragging) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            let newX = initialDesignPosition.x + dx;
            let newY = initialDesignPosition.y + dy;

            // Apply boundary constraints
            const designWidth = parseInt(currentDesign.style.width);
            const designHeight = parseInt(currentDesign.style.height);

            // Ensure design stays within image boundaries
            newX = Math.max(imageBoundary.x, newX);
            newY = Math.max(imageBoundary.y, newY);
            newX = Math.min(imageBoundary.x + imageBoundary.width - designWidth, newX);
            newY = Math.min(imageBoundary.y + imageBoundary.height - designHeight, newY);

            currentDesign.style.left = `${newX}px`;
            currentDesign.style.top = `${newY}px`;
        }

        // Stop dragging
        function stopDrag() {
            isDragging = false;

            // Save the position for the current view
            if (currentView === 'front') {
                frontDesignPosition.x = parseInt(currentDesign.style.left);
                frontDesignPosition.y = parseInt(currentDesign.style.top);
            } else {
                backDesignPosition.x = parseInt(currentDesign.style.left);
                backDesignPosition.y = parseInt(currentDesign.style.top);
            }

            document.removeEventListener('mousemove', doDrag);
            document.removeEventListener('mouseup', stopDrag);
        }

        // Start resizing
        function startResize(e) {
            e.stopPropagation();
            isResizing = true;
            startX = e.clientX;
            startY = e.clientY;
            initialDesignPosition = {
                width: parseInt(currentDesign.style.width),
                height: parseInt(currentDesign.style.height),
                x: parseInt(currentDesign.style.left),
                y: parseInt(currentDesign.style.top)
            };

            document.addEventListener('mousemove', doResize);
            document.addEventListener('mouseup', stopResize);
        }

        // Perform resizing with boundary constraints
        function doResize(e) {
            if (!isResizing) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            let newWidth = Math.max(50, initialDesignPosition.width + dx);
            let newHeight = Math.max(50, initialDesignPosition.height + dy);

            // Apply boundary constraints during resizing
            const maxWidth = imageBoundary.x + imageBoundary.width - initialDesignPosition.x;
            const maxHeight = imageBoundary.y + imageBoundary.height - initialDesignPosition.y;

            newWidth = Math.min(newWidth, maxWidth);
            newHeight = Math.min(newHeight, maxHeight);

            currentDesign.style.width = `${newWidth}px`;
            currentDesign.style.height = `${newHeight}px`;
        }

        // Stop resizing
        function stopResize() {
            isResizing = false;

            // Save the size for the current view
            if (currentView === 'front') {
                frontDesignPosition.width = parseInt(currentDesign.style.width);
                frontDesignPosition.height = parseInt(currentDesign.style.height);
            } else {
                backDesignPosition.width = parseInt(currentDesign.style.width);
                backDesignPosition.height = parseInt(currentDesign.style.height);
            }

            document.removeEventListener('mousemove', doResize);
            document.removeEventListener('mouseup', stopResize);
        }

        // Resize design with buttons
        function resizeDesign(factor) {
            if (!currentDesign) {
                alert('Please upload a design image for the current view first!');
                return;
            }

            const currentWidth = parseInt(currentDesign.style.width);
            const currentHeight = parseInt(currentDesign.style.height);
            const currentX = parseInt(currentDesign.style.left);
            const currentY = parseInt(currentDesign.style.top);

            let newWidth = Math.max(50, currentWidth * factor);
            let newHeight = Math.max(50, currentHeight * factor);

            // Apply boundary constraints
            const maxWidth = imageBoundary.x + imageBoundary.width - currentX;
            const maxHeight = imageBoundary.y + imageBoundary.height - currentY;

            newWidth = Math.min(newWidth, maxWidth);
            newHeight = Math.min(newHeight, maxHeight);

            currentDesign.style.width = `${newWidth}px`;
            currentDesign.style.height = `${newHeight}px`;

            // Save the size for the current view
            if (currentView === 'front') {
                frontDesignPosition.width = newWidth;
                frontDesignPosition.height = newHeight;
            } else {
                backDesignPosition.width = newWidth;
                backDesignPosition.height = newHeight;
            }
        }

        // Reset design position
        function resetDesignPosition() {
            if (!currentDesign) {
                alert('Please upload a design image for the current view first!');
                return;
            }

            // Center the design within the image boundary
            const newPosition = {
                x: imageBoundary.x + (imageBoundary.width - 200) / 2,
                y: imageBoundary.y + (imageBoundary.height - 200) / 2,
                width: 200,
                height: 200
            };

            currentDesign.style.width = `${newPosition.width}px`;
            currentDesign.style.height = `${newPosition.height}px`;
            currentDesign.style.left = `${newPosition.x}px`;
            currentDesign.style.top = `${newPosition.y}px`;

            // Save the position for the current view
            if (currentView === 'front') {
                frontDesignPosition = { ...newPosition };
            } else {
                backDesignPosition = { ...newPosition };
            }
        }

        // Generate mockup preview - ALWAYS show both sides if templates exist
        function generateMockup() {
            const hasFrontDesign = frontDesign !== null;
            const hasBackDesign = backDesign !== null;

            if (!hasFrontDesign && !hasBackDesign) {
                alert('Please upload at least one design image!');
                return;
            }

            // Use base images as templates
            const frontTemplate = "<?php echo $base_image_url; ?>";
            const backTemplate = "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>";

            // Show loading state
            document.querySelectorAll('.mockup-image').forEach(el => el.style.display = 'none');

            // Reset mockups
            currentMockup = '';
            backMockup = '';

            // ALWAYS generate front mockup if template exists
            if (frontTemplate) {
                if (hasFrontDesign) {
                    // Use uploaded front design
                    generateSingleMockup(
                        frontDesign,
                        frontTemplate,
                        'mockupFront',
                        'frontMockupContainer',
                        frontDesignPosition,
                        'Front View'
                    );
                } else {
                    // Show front template only (no design)
                    generateTemplateOnly(
                        frontTemplate,
                        'mockupFront',
                        'frontMockupContainer',
                        'Front View (No Design)'
                    );
                }
            } else {
                document.getElementById('frontMockupContainer').style.display = 'none';
            }

            // ALWAYS generate back mockup if template exists
            if (backTemplate) {
                if (hasBackDesign) {
                    // Use uploaded back design
                    generateSingleMockup(
                        backDesign,
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        backDesignPosition,
                        'Back View'
                    );
                } else {
                    // Show back template only (no design)
                    generateTemplateOnly(
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        'Back View (No Design)'
                    );
                }
            } else {
                document.getElementById('backMockupContainer').style.display = 'none';
            }

            document.getElementById('mockupPopup').style.display = 'flex';
        }

        // Generate template-only view (when no design is uploaded for that side)
        function generateTemplateOnly(templatePath, outputId, containerId, labelText) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const productTemplate = new Image();

            productTemplate.src = templatePath;
            productTemplate.onload = function() {
                // Set canvas to high resolution
                canvas.width = productTemplate.width;
                canvas.height = productTemplate.height;
                
                // Use high-quality image rendering
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                // Draw product template only (no design overlay)
                ctx.drawImage(productTemplate, 0, 0, canvas.width, canvas.height);

                // Output the template image
                const finalImage = canvas.toDataURL('image/png', 1.0);
                document.getElementById(outputId).src = finalImage;
                document.getElementById(containerId).style.display = 'block';
                
                // Update the label to indicate no design
                const labelElement = document.querySelector(`#${containerId} p`);
                if (labelElement) {
                    labelElement.textContent = labelText;
                }
                
                // Store mockup (empty for this side)
                if (outputId === 'mockupFront') {
                    currentMockup = '';
                } else if (outputId === 'mockupBack') {
                    backMockup = '';
                }
            };
        }

        // Generate single mockup by embedding design onto product template
        function generateSingleMockup(designImage, templatePath, outputId, containerId, position, label) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const productTemplate = new Image();

            productTemplate.src = templatePath;
            productTemplate.onload = function() {
                // Set canvas to high resolution
                canvas.width = productTemplate.width;
                canvas.height = productTemplate.height;
                
                // Use high-quality image rendering
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                // Draw product template first (as background)
                ctx.drawImage(productTemplate, 0, 0, canvas.width, canvas.height);

                // Calculate scale factors based on actual image dimensions, not container
                const scaleX = productTemplate.width / imageBoundary.width;
                const scaleY = productTemplate.height / imageBoundary.height;
                
                // Calculate position relative to the actual image boundary
                const x = (position.x - imageBoundary.x) * scaleX;
                const y = (position.y - imageBoundary.y) * scaleY;
                const width = position.width * scaleX;
                const height = position.height * scaleY;

                // Draw user's design on top of the product template with high quality
                ctx.save();
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(designImage, x, y, width, height);
                ctx.restore();

                // Output the combined image to the mockup preview
                const finalImage = canvas.toDataURL('image/png', 1.0); // Maximum quality
                document.getElementById(outputId).src = finalImage;
                document.getElementById(containerId).style.display = 'block';
                
                // Update the label
                const labelElement = document.querySelector(`#${containerId} p`);
                if (labelElement) {
                    labelElement.textContent = label;
                }
                
                // Store mockup
                if (outputId === 'mockupFront') {
                    currentMockup = finalImage;
                } else if (outputId === 'mockupBack') {
                    backMockup = finalImage;
                }
            };
        }

        // Download mockup
        function downloadMockup(imageId, fileName) {
            const link = document.createElement('a');
            link.href = document.getElementById(imageId).src;
            link.download = fileName;
            link.click();
        }

        // Use this design - ALWAYS save both sides
        function useThisDesign() {
            const hasFrontDesign = frontDesign !== null;
            const hasBackDesign = backDesign !== null;

            if (!hasFrontDesign && !hasBackDesign) {
                alert('Please generate mockups for your designs first!');
                return;
            }

            // ALWAYS save both mockups, even if one is empty
            const frontMockupToSave = currentMockup || '';
            const backMockupToSave = backMockup || '';

            saveBothDesigns(frontMockupToSave, backMockupToSave).then(designData => {
                console.log('Design save response:', designData);

                const completeDesignData = {
                    front_mockup: designData.front_mockup || '',
                    back_mockup: designData.back_mockup || '',
                    front_uploaded_file: designData.front_uploaded_file || '',
                    back_uploaded_file: designData.back_uploaded_file || '',
                    upload_type: currentUploadType,
                    has_front_design: hasFrontDesign ? '1' : '0',
                    has_back_design: hasBackDesign ? '1' : '0',
                    front_design_position: frontDesignPosition,
                    back_design_position: backDesignPosition
                };

                const designDataString = JSON.stringify(completeDesignData);
                
                // Store in hidden inputs
                document.getElementById('designImageInput').value = designDataString;
                document.getElementById('frontDesignImageInput').value = designDataString;
                document.getElementById('backDesignImageInput').value = designDataString;

                alert('Designs applied successfully! You can now add to cart.');
                closeModal();
            });
        }

        // Helper function to save designs and uploaded files - ALWAYS save both mockups
        async function saveBothDesigns(frontImageData, backImageData) {
            try {
                const formData = new FormData();

                // ALWAYS send both images, even if one is empty
                // For empty sides, we'll send a flag to create a plain mockup
                formData.append('front_image', frontImageData || '');
                formData.append('back_image', backImageData || '');
                formData.append('has_front_design', frontDesign !== null ? '1' : '0');
                formData.append('has_back_design', backDesign !== null ? '1' : '0');

                // Add the actual uploaded files (only if they exist)
                const frontUploadInput = document.getElementById('frontDesignUpload');
                if (frontUploadInput && frontUploadInput.files[0]) {
                    formData.append('front_design_file', frontUploadInput.files[0]);
                }

                const backUploadInput = document.getElementById('backDesignUpload');
                if (backUploadInput && backUploadInput.files[0]) {
                    formData.append('back_design_file', backUploadInput.files[0]);
                }

                // Add design configuration
                formData.append('upload_type', currentUploadType);
                formData.append('user_id', '<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>');
                formData.append('product_id', '<?php echo $product_id; ?>');

                // Add template paths so server can generate plain mockups
                formData.append('front_template', "<?php echo $base_image_url; ?>");
                formData.append('back_template', "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>");

                console.log('Saving design data with configuration:', {
                    upload_type: currentUploadType,
                    has_front: frontDesign !== null,
                    has_back: backDesign !== null
                });

                const response = await fetch('save_design.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Save design result:', result);
                return result;
            } catch (error) {
                console.error('Error saving designs:', error);
                return {
                    front_mockup: 'error',
                    back_mockup: 'error',
                    front_uploaded_file: 'error',
                    back_uploaded_file: 'error'
                };
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('mockupPopup').style.display = 'none';
        }

        function handleLayoutOptionChange() {
            const layoutValue = document.getElementById('layoutOption').value;
            const layoutInputContainer = document.getElementById('layoutInputContainer');

            // Clear previous content
            layoutInputContainer.innerHTML = '';

            if (layoutValue == 1) { // Assuming 1 is the ID for "User Layout"
                layoutInputContainer.innerHTML = `
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Upload Your Design Files:</label>
                    <input type="file" id="userLayoutUpload" name="user_layout_upload[]" multiple accept="image/*,.pdf,.ai,.psd" style="margin-bottom: 10px;">
                    <small style="display: block; color: #6c757d; margin-bottom: 10px;">You can upload multiple files (images, PDF, AI, PSD)</small>
                    <div id="userLayoutPreview" style="margin-top: 10px;"></div>
                `;

                // Add event listener for file upload
                setTimeout(() => {
                    const uploadInput = document.getElementById('userLayoutUpload');
                    if (uploadInput) {
                        uploadInput.addEventListener('change', function(e) {
                            const files = e.target.files;
                            const previewContainer = document.getElementById('userLayoutPreview');
                            previewContainer.innerHTML = '';

                            if (files.length > 0) {
                                previewContainer.innerHTML = '<p style="font-weight: 600; margin-bottom: 10px;">Uploaded Files:</p>';

                                for (let i = 0; i < files.length; i++) {
                                    const file = files[i];
                                    const fileElement = document.createElement('div');
                                    fileElement.style.marginBottom = '5px';
                                    fileElement.innerHTML = `<i class="fas fa-file"></i> ${file.name} (${formatFileSize(file.size)})`;
                                    previewContainer.appendChild(fileElement);
                                }
                            }
                        });
                    }
                }, 100);

            } else if (layoutValue == 2) { // Assuming 2 is the ID for "Store Layout"
                layoutInputContainer.innerHTML = `
                    <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Design Specifications:</label>
                    <textarea name="layout_details" placeholder="Please describe your design preferences, colors, text, images, and any specific requirements..." 
                            style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ddd; min-height: 100px;"></textarea>
                    <small style="display: block; color: #6c757d; margin-top: 5px;">Please be as detailed as possible to help us create your design</small>
                `;
            }

            layoutInputContainer.style.display = 'block';
        }

        // Helper function to format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form validation before submitting to cart
        document.getElementById('cartForm').addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault(); // Stop form submission
                return false;
            }
        });

        function validateForm() {
            const productId = <?php echo $product_id; ?>;
            let isValid = true;
            let errorMessage = '';

            // Check if product requires customization options
            <?php if ($customization): ?>

                // Validate size options for Other Services (IDs 18-21)
                <?php if (in_array($product_id, [18, 19, 20, 21])): ?>
                    const sizeOption = document.getElementById('sizeOption');
                    if (sizeOption && !sizeOption.value) {
                        isValid = false;
                        errorMessage += '• Please select a size option\n';
                    } else if (sizeOption && sizeOption.value) {
                        const selectedButton = document.querySelector('.option-button.selected[onclick*="size"]');
                        if (selectedButton) {
                            const isCustomSize = selectedButton.getAttribute('data-custom') === '1';
                            const customSizeInput = document.querySelector('input[name="custom_size"]');
                            
                            if (isCustomSize && (!customSizeInput || !customSizeInput.value.trim())) {
                                isValid = false;
                                errorMessage += '• Please specify your custom size\n';
                            }
                        }
                    }
                <?php endif; ?>

                // Validate color options for Other Services (IDs 18-21)
                <?php if (in_array($product_id, [18, 19, 21])): ?>
                    const colorOption = document.getElementById('colorOption');
                    if (colorOption && !colorOption.value) {
                        isValid = false;
                        errorMessage += '• Please select a color option\n';
                    } else if (colorOption && colorOption.value) {
                        const selectedButton = document.querySelector('.option-button.selected[onclick*="color"]');
                        if (selectedButton) {
                            const isCustomColor = selectedButton.getAttribute('data-custom') === '1';
                            const customColorInput = document.querySelector('input[name="custom_color"]');
                            
                            if (isCustomColor && (!customColorInput || !customColorInput.value.trim())) {
                                isValid = false;
                                errorMessage += '• Please specify your custom color\n';
                            }
                        }
                    }
                <?php endif; ?>

                // Validate printing options for printing categories
                <?php if ($customization && !in_array($product_id, [18, 19, 20, 21]) && in_array($product['category'], ['RISO Printing', 'Offset Printing', 'Digital Printing'])): ?>

                    // Validate paper option
                    <?php if ($customization['has_paper_option'] && !empty($paper_options)): ?>
                        const paperOption = document.getElementById('paperOption');
                        if (paperOption && !paperOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a paper type\n';
                        }
                    <?php endif; ?>

                    // Validate size input for printing
                    <?php if ($customization['has_size_option'] && !in_array($product_id, [18, 19, 20, 21]) && in_array($product['category'], ['RISO Printing', 'Offset Printing', 'Digital Printing'])): ?>
                        const sizeInput = document.querySelector('input[name="size_option"]');
                        if (sizeInput && !sizeInput.value.trim()) {
                            isValid = false;
                            errorMessage += '• Please specify the size (e.g., 8.5 x 11)\n';
                        }
                    <?php endif; ?>

                    // Validate finish option
                    <?php if ($customization['has_finish_option'] && !empty($finish_options)): ?>
                        const finishOption = document.getElementById('finishOption');
                        if (finishOption && !finishOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a finish option\n';
                        }
                    <?php endif; ?>

                    // Validate layout option
                    <?php if ($customization['has_layout_option'] && !empty($layout_options)): ?>
                        const layoutOption = document.getElementById('layoutOption');
                        if (layoutOption && !layoutOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a layout option\n';
                        }

                        // Validate layout details based on selection
                        if (layoutOption && layoutOption.value) {
                            const selectedButton = document.querySelector('.option-button.selected[onclick*="selectLayoutOption"]');
                            if (selectedButton) {
                                const selectedText = selectedButton.textContent;
                                
                                if (selectedText.includes('User Layout')) {
                                    const userLayoutUpload = document.getElementById('userLayoutUpload');
                                    if (!userLayoutUpload || !userLayoutUpload.files.length) {
                                        isValid = false;
                                        errorMessage += '• Please upload your design files for User Layout\n';
                                    }
                                } else if (selectedText.includes('Store Layout')) {
                                    const layoutDetails = document.querySelector('textarea[name="layout_details"]');
                                    if (!layoutDetails || !layoutDetails.value.trim()) {
                                        isValid = false;
                                        errorMessage += '• Please provide design specifications for Store Layout\n';
                                    }
                                }
                            }
                        }
                    <?php endif; ?>

                    // Validate binding option
                    <?php if ($customization['has_binding_option'] && !empty($binding_options)): ?>
                        const bindingOption = document.getElementById('bindingOption');
                        if (bindingOption && !bindingOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a binding option\n';
                        }
                    <?php endif; ?>

                    // Validate GSM option
                    <?php if ($customization['has_gsm_option']): ?>
                        const gsmOption = document.querySelector('input[name="gsm_option"]');
                        if (gsmOption && (!gsmOption.value || gsmOption.value <= 0)) {
                            isValid = false;
                            errorMessage += '• Please enter a valid paper weight (GSM)\n';
                        }
                    <?php endif; ?>

                <?php endif; // End printing categories validation 
                ?>

            <?php endif; // End customization validation 
            ?>

            // Validate image customization for Other Services
            <?php if ($show_image_customization): ?>
                const hasFrontDesign = frontDesign !== null;
                const hasBackDesign = backDesign !== null;

                if (!hasFrontDesign && !hasBackDesign) {
                    isValid = false;
                    errorMessage += '• Please upload at least one design image\n';
                }

                // Check if design was applied
                const designImageInput = document.getElementById('designImageInput');
                if ((hasFrontDesign || hasBackDesign) && (!designImageInput || !designImageInput.value)) {
                    isValid = false;
                    errorMessage += '• Please generate and apply your design using the "Generate Mockup" and "Use This Design" buttons\n';
                }
            <?php endif; ?>

            // Validate quantity
            const quantityInput = document.getElementById('quantity');
            if (!quantityInput || quantityInput.value < 1) {
                isValid = false;
                errorMessage += '• Please enter a valid quantity\n';
            }

            // Show error message if validation fails
            if (!isValid) {
                alert('Please complete the following required fields:\n\n' + errorMessage);

                // Scroll to the first error (optional)
                if (errorMessage.includes('size')) {
                    document.querySelector('.customization-section').scrollIntoView({
                        behavior: 'smooth'
                    });
                } else if (errorMessage.includes('design') || errorMessage.includes('mockup')) {
                    document.querySelector('.design-areas').scrollIntoView({
                        behavior: 'smooth'
                    });
                }

                return false;
            }

            return true;
        }

        function autoPopulateAIDesign(imageData) {
            // Get placement from session storage
            const placement = sessionStorage.getItem('aiDesignPlacement') || 'front';
            const currentProductId = <?php echo $product_id; ?>;
            
            console.log('AI Design Placement:', placement, 'Product ID:', currentProductId);

            // Convert base64 to blob and create file
            fetch(imageData)
                .then(res => res.blob())
                .then(blob => {
                    const file = new File([blob], `ai-design-${Date.now()}.png`, { type: 'image/png' });
                    
                    // Determine which design area to populate based on placement
                    if (placement === 'front' || placement === 'both') {
                        populateDesignArea('front', file, imageData);
                    }
                    
                    if (placement === 'back' || placement === 'both') {
                        // Only populate back if back design area exists and is visible
                        const backDesignArea = document.getElementById('backDesignArea');
                        if (backDesignArea && backDesignArea.style.display !== 'none') {
                            populateDesignArea('back', file, imageData);
                        } else if (placement === 'back') {
                            // If back was requested but not available, use front instead
                            populateDesignArea('front', file, imageData);
                        }
                    }
                    
                    // Show success message
                    setTimeout(() => {
                        let message = 'AI-generated design loaded successfully! ';
                        if (placement === 'front') message += 'Design applied to front.';
                        else if (placement === 'back') message += 'Design applied to back.';
                        else if (placement === 'both') message += 'Design applied to both sides.';
                        
                        alert(message);
                        
                        // Switch to appropriate view
                        if (placement === 'back') {
                            switchView('back');
                        } else {
                            switchView('front');
                        }
                    }, 500);
                })
                .catch(error => {
                    console.error('Error loading AI design:', error);
                    alert('Error loading AI design. Please upload manually.');
                });
        }

        // Helper function to populate a specific design area
        function populateDesignArea(side, file, imageData) {
            const inputId = side + 'DesignUpload';
            const previewId = side + 'PreviewImage';
            const previewContainer = side + 'DesignPreview';
            const statusId = side + 'DesignStatus';
            const designArea = side + 'DesignArea';
            const uploadZone = side + 'UploadZone';

            const input = document.getElementById(inputId);
            const previewImg = document.getElementById(previewId);
            const previewDiv = document.getElementById(previewContainer);
            const statusSpan = document.getElementById(statusId);
            const areaDiv = document.getElementById(designArea);
            const zoneDiv = document.getElementById(uploadZone);

            if (input && previewImg && previewDiv) {
                // Create a new FileList-like object
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                input.files = dataTransfer.files;

                // Update UI
                previewImg.src = imageData;
                previewDiv.style.display = 'block';
                statusSpan.textContent = '(AI Generated)';
                statusSpan.style.color = '#28a745';
                areaDiv.classList.add('has-design');
                zoneDiv.style.display = 'none';

                // Store design data
                if (side === 'front') {
                    frontDesign = new Image();
                    frontDesign.src = imageData;
                    frontDesign.onload = function() {
                        if (currentView === 'front') {
                            initDesignOverlay(imageData);
                        }
                        calculateImageBoundary();
                        updateDesignType();
                        updatePreview();
                    };
                } else {
                    backDesign = new Image();
                    backDesign.src = imageData;
                    backDesign.onload = function() {
                        if (currentView === 'back') {
                            initDesignOverlay(imageData);
                        }
                        calculateImageBoundary();
                        updateDesignType();
                        updatePreview();
                    };
                }
            }
        }
    </script>
</body>

</html>