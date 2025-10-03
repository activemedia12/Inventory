<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';

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
    header("Location: main.php");
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
    header("Location: main.php");
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
        $upload_dir = '../assets/uploads/user_layouts/' . $user_id . '/';
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
$base_image_path = "../assets/images/base/base-" . $product['id'] . ".jpg";
$base_image_url = file_exists($base_image_path) ? $base_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Base+Image";
$back_base_image_path = "../assets/images/base/base-" . $product['id'] . "-1.jpg";
$back_base_image_url = file_exists($back_base_image_path) ? $back_base_image_path : "";

// Get product images for gallery display
$product_image_path = "../assets/images/services/service-" . $product['id'] . ".jpg";
$product_image_url = file_exists($product_image_path) ? $product_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Product+Image";
$product_back_image_path = "../assets/images/services/service-" . $product['id'] . "-1.jpg";
$product_back_image_url = file_exists($product_back_image_path) ? $product_back_image_path : "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product['product_name']; ?> - Product Details</title>
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

        .product-detail-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
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
            border-radius: 12px;
            margin-bottom: 20px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            padding: 20px;
        }

        .thumbnail-container {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.3s ease;
            background: #f8f9fa;
            padding: 5px;
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: #007bff;
            transform: scale(1.05);
        }

        .product-info {
            flex: 1;
        }

        .product-title {
            font-size: 2.2em;
            margin-bottom: 15px;
            color: #2c3e50;
            font-weight: 700;
        }

        .product-category {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.95em;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .product-price {
            font-size: 2em;
            color: #e74c3c;
            margin-bottom: 25px;
            font-weight: bold;
        }

        .customization-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }

        .section-title {
            font-size: 1.3em;
            margin-bottom: 20px;
            color: #2c3e50;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #007bff;
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
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1.3em;
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

        .quantity-input {
            width: 80px;
            height: 45px;
            text-align: center;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1.2em;
            font-weight: 600;
        }

        .image-upload-section {
            margin-bottom: 25px;
        }

        .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            background: #6c757d;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
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
            background: white;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        .uploaded-image {
            max-width: 220px;
            max-height: 180px;
            border-radius: 8px;
            border: 2px solid #007bff;
            margin-bottom: 15px;
        }

        .preview-section {
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }

        .preview-container {
            width: 100%;
            height: 220px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
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
            border-radius: 6px;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }

        .btn {
            padding: 18px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
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
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
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
            border: 2px dashed #dee2e6;
            border-radius: 10px;
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
            border: 2px dashed #007bff;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            box-sizing: border-box;
        }

        .resize-handle {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #007bff;
            border-radius: 50%;
            bottom: -6px;
            right: -6px;
            cursor: nwse-resize;
        }

        /* Visual boundary indicator */
        .design-boundary {
            position: absolute;
            border: 2px dashed rgba(0, 123, 255, 0.3);
            background-color: rgba(0, 123, 255, 0.1);
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
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-btn.active {
            background: #007bff;
        }

        .view-btn:hover {
            background: #0056b3;
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
            background: white;
            padding: 40px;
            border-radius: 15px;
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
            color: #6c757d;
            transition: color 0.3s ease;
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
            border: 2px solid #e9ecef;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .download-btn {
            margin-top: 15px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .download-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .form-control:invalid {
            border-color: #dc3545 !important;
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        .validation-error {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
            display: none;
        }

        .section-with-error {
            border: 2px solid #dc3545 !important;
            background-color: #f8d7da !important;
        }

        @media (max-width: 768px) {
            .product-detail {
                flex-direction: column;
            }

            .product-gallery {
                max-width: 100%;
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

            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .positioning-container {
                height: 300px;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="../website/main.php" class="logo">
                    <i class="fas fa-print"></i>
                    Active Media
                </a>

                <div class="user-info">
                    <a href="../../website/view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <?php echo $_SESSION['username'] ?? 'User'; ?>
                    </a>
                    <a href="../accounts/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="product-detail-container">
            <div class="product-detail">
                <div class="product-gallery">
                    <!-- Show product images in gallery -->
                    <?php
                    $product_image_path = "../../assets/images/services/service-" . $product['id'] . ".jpg";
                    $product_image_url = file_exists($product_image_path) ? $product_image_path : "https://via.placeholder.com/500x500/007bff/ffffff?text=Product+Image";
                    $product_back_image_path = "../../assets/images/services/service-" . $product['id'] . "-1.jpg";
                    $product_back_image_url = file_exists($product_back_image_path) ? $product_back_image_path : "";
                    ?>
                    <img src="<?php echo $product_image_url; ?>" alt="<?php echo $product['product_name']; ?>" class="main-image" id="mainImage">

                    <div class="thumbnail-container">
                        <img src="<?php echo $product_image_url; ?>"
                            alt="Thumbnail 1" class="thumbnail active" onclick="changeImage(this, 'front')">
                        <?php if (!empty($product_back_image_url)): ?>
                            <img src="<?php echo $product_back_image_url; ?>"
                                alt="Thumbnail 2" class="thumbnail" onclick="changeImage(this, 'back')">
                        <?php endif; ?>
                        <img src="../assets/images/services/service-<?php echo $product['id']; ?>-2.jpg"
                            alt="Thumbnail 3" class="thumbnail" onclick="changeImage(this, 'front')">
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
                                        <select name="size_option" id="sizeOption" class="form-control" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;" onchange="handleSizeOptionChange()">
                                            <?php foreach ($size_options as $size):
                                                $display_name = isset($size['dimensions']) ? $size['size_name'] . ' (' . $size['dimensions'] . ')' : $size['size_name'];
                                            ?>
                                                <option value="<?php echo $size['id']; ?>" data-custom="<?php echo $size['is_custom']; ?>">
                                                    <?php echo $display_name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="customSizeContainer" style="margin-top: 10px; display: none;">
                                            <input type="text" name="custom_size" placeholder="Please specify your custom size"
                                                style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="customization-section">
                                <h3 class="section-title required-field"><i class="fas fa-palette"></i> Color</h3>
                                <?php if (!empty($color_options)): ?>
                                    <div class="option-group" style="margin-bottom: 20px;">
                                        <select name="color_option" id="colorOption" class="form-control" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;" onchange="handleColorOptionChange()">
                                            <?php foreach ($color_options as $color): ?>
                                                <option value="<?php echo $color['id']; ?>" data-custom="<?php echo $color['is_custom']; ?>">
                                                    <?php echo $color['color_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div id="customColorContainer" style="margin-top: 10px; display: none;">
                                            <input type="text" name="custom_color" placeholder="Please specify your custom color"
                                                style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;">
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($product_id == 20): ?>
                                    <div class="option-group" style="margin-bottom: 20px;">
                                        <input type="text" value="Brown (Standard)" readonly
                                            style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%; background: #f8f9fa;">
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
                                        <select name="paper_option" class="form-control" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;">
                                            <?php foreach ($paper_options as $paper): ?>
                                                <option value="<?php echo $paper['id']; ?>"><?php echo $paper['option_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
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
                                        <input type="text" name="size_option" placeholder="e.g., 8.5 x 11" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;">
                                    </div>
                                <?php endif; ?>

                                <?php if ($customization['has_finish_option'] && !empty($finish_options)): ?>
                                    <div class="option-group" style="margin-bottom: 20px;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Finish:</label>
                                        <select name="finish_option" class="form-control" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;">
                                            <?php foreach ($finish_options as $finish): ?>
                                                <option value="<?php echo $finish['id']; ?>"><?php echo $finish['option_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($customization['has_layout_option'] && !empty($layout_options)): ?>
                                    <div class="option-group" style="margin-bottom: 20px;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Layout Option:</label>
                                        <select name="layout_option" id="layoutOption" class="form-control" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;" onchange="handleLayoutOptionChange()">
                                            <?php foreach ($layout_options as $layout): ?>
                                                <option value="<?php echo $layout['id']; ?>"><?php echo $layout['option_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <div id="layoutInputContainer" style="margin-top: 10px; display: none;">
                                            <!-- Content will be populated by JavaScript based on selection -->
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($customization['has_binding_option'] && !empty($binding_options)): ?>
                                    <div class="option-group" style="margin-bottom: 20px;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Binding:</label>
                                        <select name="binding_option" class="form-control" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;">
                                            <?php foreach ($binding_options as $binding): ?>
                                                <option value="<?php echo $binding['id']; ?>"><?php echo $binding['option_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($customization['has_gsm_option']): ?>
                                    <div class="option-group" style="margin-bottom: 20px;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600;" class="required-field">Paper Weight (GSM):</label>
                                        <input type="number" name="gsm_option" placeholder="e.g., 120" min="0" style="padding: 10px; border-radius: 5px; border: 1px solid #ddd; width: 100%;">
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

                                <!-- Upload Type Selection -->
                                <div class="upload-type-section" style="margin-bottom: 25px;">
                                    <h4 class="section-title" style="font-size: 1.1em;"><i class="fas fa-upload"></i> Upload Type</h4>
                                    <div class="upload-type-buttons" style="display: flex; gap: 15px; flex-wrap: wrap;">
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="radio" name="upload_type" value="single" checked onchange="handleUploadTypeChange()">
                                            <span>Same Design for Front & Back</span>
                                        </label>
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="radio" name="upload_type" value="separate" onchange="handleUploadTypeChange()">
                                            <span>Different Designs for Front & Back</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Single Image Upload (Default) -->
                                <div class="image-upload-section" id="singleUploadSection">
                                    <label for="designUpload" class="upload-btn required-field">
                                        <i class="fas fa-upload"></i> Choose Design Image (for both sides)
                                    </label>
                                    <input type="file" id="designUpload" name="design_upload" accept="image/*" style="display: none;">
                                    <div class="upload-preview" id="uploadPreview">
                                        <img src="" alt="Uploaded design" class="uploaded-image" id="uploadedImage">
                                        <button type="button" class="btn btn-secondary" onclick="removeUploadedImage()">
                                            <i class="fas fa-times"></i> Remove Image
                                        </button>
                                    </div>
                                </div>

                                <!-- Separate Image Upload (Hidden by default) -->
                                <div class="separate-upload-section" id="separateUploadSection" style="display: none;">
                                    <div class="front-upload" style="margin-bottom: 20px;">
                                        <h5 style="margin-bottom: 10px; color: #2c3e50;">
                                            <i class="fas fa-tshirt"></i> Front Design
                                        </h5>
                                        <label for="frontDesignUpload" class="upload-btn required-field">
                                            <i class="fas fa-upload"></i> Choose Front Design
                                        </label>
                                        <input type="file" id="frontDesignUpload" name="front_design_upload" accept="image/*" style="display: none;">
                                        <div class="upload-preview" id="frontUploadPreview">
                                            <img src="" alt="Front design" class="uploaded-image" id="frontUploadedImage">
                                            <button type="button" class="btn btn-secondary" onclick="removeFrontUploadedImage()">
                                                <i class="fas fa-times"></i> Remove Front Image
                                            </button>
                                        </div>
                                    </div>

                                    <?php if (!empty($back_base_image_url)): ?>
                                        <div class="back-upload">
                                            <h5 style="margin-bottom: 10px; color: #2c3e50;">
                                                <i class="fas fa-tshirt"></i> Back Design
                                            </h5>
                                            <label for="backDesignUpload" class="upload-btn required-field">
                                                <i class="fas fa-upload"></i> Choose Back Design
                                            </label>
                                            <input type="file" id="backDesignUpload" name="back_design_upload" accept="image/*" style="display: none;">
                                            <div class="upload-preview" id="backUploadPreview">
                                                <img src="" alt="Back design" class="uploaded-image" id="backUploadedImage">
                                                <button type="button" class="btn btn-secondary" onclick="removeBackUploadedImage()">
                                                    <i class="fas fa-times"></i> Remove Back Image
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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
                                        <p id="previewText" style="color: #6c757d; font-style: italic;">Upload an image to generate preview</p>
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

    <!-- Mockup Popup -->
    <div class="mockup-popup" id="mockupPopup">
        <div class="mockup-container">
            <button class="close-popup" onclick="closeModal()">&times;</button>
            <h2 style="text-align: center; color: #2c3e50; margin-bottom: 10px;">
                <i class="fas fa-palette"></i> Your <?php echo $product['product_name']; ?> Mockup
            </h2>
            <p style="text-align: center; color: #6c757d; margin-bottom: 30px;">Preview your custom design</p>

            <div class="mockup-images">
                <div class="mockup-image" id="frontMockupContainer">
                    <img src="" alt="Front View" id="mockupFront">
                    <p style="margin: 15px 0; font-weight: 600; color: #2c3e50;">Front View</p>
                    <button class="download-btn" onclick="downloadMockup('mockupFront', 'front-design.png')">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <div class="mockup-image" id="backMockupContainer">
                    <img src="" alt="Back View" id="mockupBack">
                    <p style="margin: 15px 0; font-weight: 600; color: #2c3e50;">Back View</p>
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

    <script>
        // Global variables
        let userDesign = null;
        let frontUserDesign = null;
        let backUserDesign = null;
        let currentMockup = null;
        let backMockup = null;
        let isDraggingEnabled = false;
        let currentDesign = null;
        let currentView = 'front';
        let currentUploadType = 'single';
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
            // Setup design upload listeners
            document.getElementById('designUpload').addEventListener('change', function(e) {
                handleSingleDesignUpload(e);
            });

            // Front design upload
            document.getElementById('frontDesignUpload').addEventListener('change', function(e) {
                handleSeparateDesignUpload(e, 'front');
            });

            // Back design upload
            document.getElementById('backDesignUpload').addEventListener('change', function(e) {
                handleSeparateDesignUpload(e, 'back');
            });

            // Close popup when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === document.getElementById('mockupPopup')) {
                    closeModal();
                }
            });

            // Calculate the image boundary for the initial product image
            const baseImage = document.getElementById('baseImage');
            baseImage.onload = function() {
                calculateImageBoundary();
            };

            // Initialize upload type
            document.getElementById('uploadTypeInput').value = currentUploadType;
        });

        // Handle upload type change
        function handleUploadTypeChange() {
            const uploadType = document.querySelector('input[name="upload_type"]:checked').value;
            currentUploadType = uploadType;
            document.getElementById('uploadTypeInput').value = uploadType;

            if (uploadType === 'single') {
                document.getElementById('singleUploadSection').style.display = 'block';
                document.getElementById('separateUploadSection').style.display = 'none';
                // Reset separate uploads when switching to single
                removeFrontUploadedImage();
                removeBackUploadedImage();
                // Switch to front view
                switchView('front');
            } else {
                document.getElementById('singleUploadSection').style.display = 'none';
                document.getElementById('separateUploadSection').style.display = 'block';
                // Reset single upload when switching to separate
                removeUploadedImage();
            }
        }

        // Handle single design upload
        function handleSingleDesignUpload(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                document.getElementById('uploadedImage').src = event.target.result;
                document.getElementById('uploadPreview').style.display = 'block';

                // Initialize the design overlay with the uploaded image
                initDesignOverlay(event.target.result);

                userDesign = new Image();
                userDesign.src = event.target.result;
                userDesign.onload = function() {
                    document.getElementById('previewText').style.display = 'none';
                    document.getElementById('mockupPreview').style.display = 'block';
                    document.getElementById('mockupPreview').src = event.target.result;

                    // Calculate the image boundary after the image is loaded
                    calculateImageBoundary();
                };
            };
            reader.readAsDataURL(file);
        }

        // Handle separate design upload
        function handleSeparateDesignUpload(e, side) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                const previewId = side + 'UploadPreview';
                const imageId = side + 'UploadedImage';

                document.getElementById(imageId).src = event.target.result;
                document.getElementById(previewId).style.display = 'block';

                // Store the design
                if (side === 'front') {
                    frontUserDesign = new Image();
                    frontUserDesign.src = event.target.result;
                    frontUserDesign.onload = function() {
                        // If we're currently viewing front, update the overlay
                        if (currentView === 'front') {
                            initDesignOverlay(event.target.result);
                        }
                        calculateImageBoundary();
                        updatePreviewForSeparateDesigns();
                    };
                } else {
                    backUserDesign = new Image();
                    backUserDesign.src = event.target.result;
                    backUserDesign.onload = function() {
                        // If we're currently viewing back, update the overlay
                        if (currentView === 'back') {
                            initDesignOverlay(event.target.result);
                        }
                        calculateImageBoundary();
                        updatePreviewForSeparateDesigns();
                    };
                }
            };
            reader.readAsDataURL(file);
        }

        // Update preview for separate designs
        function updatePreviewForSeparateDesigns() {
            const previewText = document.getElementById('previewText');
            const mockupPreview = document.getElementById('mockupPreview');

            if (currentView === 'front' && frontUserDesign) {
                previewText.style.display = 'none';
                mockupPreview.style.display = 'block';
                mockupPreview.src = frontUserDesign.src;
            } else if (currentView === 'back' && backUserDesign) {
                previewText.style.display = 'none';
                mockupPreview.style.display = 'block';
                mockupPreview.src = backUserDesign.src;
            } else {
                previewText.style.display = 'block';
                mockupPreview.style.display = 'none';
            }
        }

        // Remove functions for separate designs
        function removeFrontUploadedImage() {
            document.getElementById('frontDesignUpload').value = '';
            document.getElementById('frontUploadPreview').style.display = 'none';
            frontUserDesign = null;
            resetDesignOverlay();
            updatePreviewForSeparateDesigns();
        }

        function removeBackUploadedImage() {
            document.getElementById('backDesignUpload').value = '';
            document.getElementById('backUploadPreview').style.display = 'none';
            backUserDesign = null;
            resetDesignOverlay();
            updatePreviewForSeparateDesigns();
        }

        // Update the existing remove function
        function removeUploadedImage() {
            document.getElementById('designUpload').value = '';
            document.getElementById('uploadPreview').style.display = 'none';
            document.getElementById('previewText').style.display = 'block';
            document.getElementById('mockupPreview').style.display = 'none';
            userDesign = null;
            resetDesignOverlay();
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

            // Change the base image to use base template, not product image
            const baseImage = document.getElementById('baseImage');
            if (view === 'front') {
                baseImage.src = frontImageUrl;
            } else {
                baseImage.src = backImageUrl;
            }

            // Reinitialize design overlay for the current view based on upload type
            if (currentUploadType === 'single' && userDesign) {
                initDesignOverlay(userDesign.src);
            } else if (currentUploadType === 'separate') {
                if (view === 'front' && frontUserDesign) {
                    initDesignOverlay(frontUserDesign.src);
                } else if (view === 'back' && backUserDesign) {
                    initDesignOverlay(backUserDesign.src);
                } else {
                    resetDesignOverlay();
                }
            } else {
                resetDesignOverlay();
            }

            // Update preview
            updatePreviewForSeparateDesigns();

            // Recalculate boundaries
            setTimeout(calculateImageBoundary, 100);
        }

        // Calculate the actual image boundary within the container
        function calculateImageBoundary() {
            const container = document.querySelector('.product-base-image');
            const img = document.getElementById('baseImage');

            // Get the natural dimensions of the image
            const naturalWidth = img.naturalWidth;
            const naturalHeight = img.naturalHeight;

            // Get the displayed dimensions
            const displayedWidth = img.width;
            const displayedHeight = img.height;

            // Calculate the aspect ratios
            const containerAspect = container.offsetWidth / container.offsetHeight;
            const imageAspect = naturalWidth / naturalHeight;

            // Calculate the actual displayed image area (not including any whitespace)
            if (imageAspect > containerAspect) {
                // Image is wider than container
                imageBoundary.width = container.offsetWidth;
                imageBoundary.height = container.offsetWidth / imageAspect;
                imageBoundary.x = 0;
                imageBoundary.y = (container.offsetHeight - imageBoundary.height) / 2;
            } else {
                // Image is taller than container
                imageBoundary.height = container.offsetHeight;
                imageBoundary.width = container.offsetHeight * imageAspect;
                imageBoundary.y = 0;
                imageBoundary.x = (container.offsetWidth - imageBoundary.width) / 2;
            }

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

        // Change main image when thumbnail is clicked (gallery only)
        function changeImage(element, view) {
            // Only update the gallery image, NOT the base image
            document.getElementById('mainImage').src = element.src;
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');

            // Don't change the base image - it should stay as the template
            // The base image should only show the template for positioning
        }

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
            if (currentUploadType === 'single' && !userDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate') {
                if (currentView === 'front' && !frontUserDesign) {
                    alert('Please upload a front design image first!');
                    return;
                }
                if (currentView === 'back' && !backUserDesign) {
                    alert('Please upload a back design image first!');
                    return;
                }
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

        // Initialize design overlay
        function initDesignOverlay(imageSrc) {
            const overlay = document.getElementById('designOverlay');
            overlay.innerHTML = '';

            // Get the current design position for the active view
            const designPosition = currentView === 'front' ? frontDesignPosition : backDesignPosition;

            const designElement = document.createElement('div');
            designElement.className = 'draggable-design';
            designElement.style.backgroundImage = `url(${imageSrc})`;
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
            if (currentUploadType === 'single' && !currentDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate' && !currentDesign) {
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
            if (currentUploadType === 'single' && !currentDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate' && !currentDesign) {
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
                frontDesignPosition = {
                    ...newPosition
                };
            } else {
                backDesignPosition = {
                    ...newPosition
                };
            }
        }

        // Generate mockup preview
        function generateMockup() {
            if (currentUploadType === 'single' && !userDesign) {
                alert('Please upload a design image first!');
                return;
            }

            if (currentUploadType === 'separate') {
                if (!frontUserDesign && !backUserDesign) {
                    alert('Please upload at least one design image!');
                    return;
                }
            }

            // Use base images as templates, not product images
            const frontTemplate = "<?php echo $base_image_url; ?>";
            const backTemplate = "<?php echo !empty($back_base_image_url) ? $back_base_image_url : ''; ?>";

            // Show loading state
            document.querySelectorAll('.mockup-image').forEach(el => el.style.display = 'none');

            // Generate front mockup if design exists
            if (currentUploadType === 'single' && userDesign) {
                generateSingleMockup(
                    userDesign,
                    frontTemplate,
                    'mockupFront',
                    'frontMockupContainer',
                    frontDesignPosition
                );
            } else if (currentUploadType === 'separate' && frontUserDesign) {
                generateSingleMockup(
                    frontUserDesign,
                    frontTemplate,
                    'mockupFront',
                    'frontMockupContainer',
                    frontDesignPosition
                );
            } else {
                document.getElementById('frontMockupContainer').style.display = 'none';
            }

            // Generate back mockup if template and design exist
            if (backTemplate) {
                if (currentUploadType === 'single' && userDesign) {
                    generateSingleMockup(
                        userDesign,
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        backDesignPosition
                    );
                } else if (currentUploadType === 'separate' && backUserDesign) {
                    generateSingleMockup(
                        backUserDesign,
                        backTemplate,
                        'mockupBack',
                        'backMockupContainer',
                        backDesignPosition
                    );
                } else {
                    document.getElementById('backMockupContainer').style.display = 'none';
                }
            } else {
                document.getElementById('backMockupContainer').style.display = 'none';
            }

            document.getElementById('mockupPopup').style.display = 'flex';
        }

        // Generate single mockup by embedding design onto product template
        function generateSingleMockup(designImage, templatePath, outputId, containerId, position) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const productTemplate = new Image();

            productTemplate.src = templatePath;
            productTemplate.onload = function() {
                canvas.width = productTemplate.width;
                canvas.height = productTemplate.height;

                // Draw product template first (as background)
                ctx.drawImage(productTemplate, 0, 0);

                // Calculate position and size relative to canvas
                const baseImg = document.getElementById('baseImage');
                const scaleX = productTemplate.width / baseImg.offsetWidth;
                const scaleY = productTemplate.height / baseImg.offsetHeight;

                const x = position.x * scaleX;
                const y = position.y * scaleY;
                const width = position.width * scaleX;
                const height = position.height * scaleY;

                // Draw user's design on top of the product template
                ctx.drawImage(designImage, x, y, width, height);

                // Output the combined image to the mockup preview
                document.getElementById(outputId).src = canvas.toDataURL('image/png');
                document.getElementById(containerId).style.display = 'block';

                // Store mockup
                if (outputId === 'mockupFront') {
                    currentMockup = canvas.toDataURL('image/png');
                } else if (outputId === 'mockupBack') {
                    backMockup = canvas.toDataURL('image/png');
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

        // Use this design
        function useThisDesign() {
            if (currentUploadType === 'single' && currentMockup) {
                saveBothDesigns(currentMockup, currentMockup).then(designData => {
                    if (designData.front_mockup && designData.front_mockup !== 'error') {
                        const completeDesignData = {
                            front_mockup: designData.front_mockup || '',
                            back_mockup: designData.back_mockup || '',
                            uploaded_file: designData.uploaded_file || '',
                            upload_type: 'single'
                        };

                        // Validate the JSON before storing
                        const designDataString = JSON.stringify(completeDesignData);
                        console.log('Single design data to save:', designDataString); // Debug

                        // Store as JSON string
                        document.getElementById('designImageInput').value = designDataString;
                        alert('Design applied successfully! You can now add to cart.');
                        closeModal();
                    } else {
                        alert('Error saving design. Please try again.');
                    }
                });
            } else if (currentUploadType === 'separate') {
                // For separate uploads, we need to ensure both mockups exist
                if (!currentMockup && !backMockup) {
                    alert('Please generate mockups for both front and back designs first!');
                    return;
                }

                saveBothDesigns(
                    currentMockup || '',
                    backMockup || ''
                ).then(designData => {
                    console.log('Separate design save response:', designData); // Debug

                    if ((designData.front_mockup && designData.front_mockup !== 'error') ||
                        (designData.back_mockup && designData.back_mockup !== 'error')) {

                        const completeDesignData = {
                            front_mockup: designData.front_mockup || '',
                            back_mockup: designData.back_mockup || '',
                            front_uploaded_file: designData.front_uploaded_file || '',
                            back_uploaded_file: designData.back_uploaded_file || '',
                            upload_type: 'separate'
                        };

                        // Validate the JSON before storing
                        const designDataString = JSON.stringify(completeDesignData);
                        console.log('Separate design data to save:', designDataString); // Debug
                        console.log('JSON length:', designDataString.length); // Debug

                        // Store as JSON string in BOTH hidden inputs for safety
                        document.getElementById('frontDesignImageInput').value = designDataString;
                        document.getElementById('backDesignImageInput').value = designDataString;

                        alert('Designs applied successfully! You can now add to cart.');
                        closeModal();
                    } else {
                        alert('Error saving designs. Please try again.');
                    }
                });
            }
        }

        // Helper function to save designs and uploaded files
        async function saveBothDesigns(frontImageData, backImageData) {
            try {
                const formData = new FormData();

                // Add mockup images
                if (frontImageData) {
                    formData.append('front_image', frontImageData);
                }
                if (backImageData) {
                    formData.append('back_image', backImageData);
                }

                // Add the actual uploaded files based on upload type
                if (currentUploadType === 'single') {
                    const singleUploadInput = document.getElementById('designUpload');
                    if (singleUploadInput && singleUploadInput.files[0]) {
                        formData.append('design_file', singleUploadInput.files[0]);
                    }
                } else {
                    // For separate uploads, save both front and back files
                    const frontUploadInput = document.getElementById('frontDesignUpload');
                    if (frontUploadInput && frontUploadInput.files[0]) {
                        formData.append('front_design_file', frontUploadInput.files[0]);
                    }

                    const backUploadInput = document.getElementById('backDesignUpload');
                    if (backUploadInput && backUploadInput.files[0]) {
                        formData.append('back_design_file', backUploadInput.files[0]);
                    }
                }

                formData.append('user_id', '<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0; ?>');

                console.log('Saving design data...'); // Debug
                const response = await fetch('save_design.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                console.log('Save design result:', result); // Debug
                return result;
            } catch (error) {
                console.error('Error saving designs:', error);
                return {
                    front_mockup: 'error',
                    back_mockup: 'error'
                };
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('mockupPopup').style.display = 'none';
        }

        function handleLayoutOptionChange() {
            const layoutOption = document.getElementById('layoutOption');
            const layoutInputContainer = document.getElementById('layoutInputContainer');

            // Clear previous content
            layoutInputContainer.innerHTML = '';

            if (layoutOption.value == 1) { // Assuming 1 is the ID for "User Layout"
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

            } else if (layoutOption.value == 2) { // Assuming 2 is the ID for "Store Layout"
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

        function handleUserLayoutUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // You can process the uploaded image here
                    console.log("User layout image uploaded:", e.target.result);
                    // You might want to display a preview or store the image data
                };
                reader.readAsDataURL(file);
            }
        }

        function handleSizeOptionChange() {
            const sizeSelect = document.getElementById('sizeOption');
            const customContainer = document.getElementById('customSizeContainer');
            if (sizeSelect) {
                const selectedOption = sizeSelect.options[sizeSelect.selectedIndex];
                const isCustom = selectedOption.getAttribute('data-custom') === '1';
                customContainer.style.display = isCustom ? 'block' : 'none';
            }
        }

        function handleColorOptionChange() {
            const colorSelect = document.getElementById('colorOption');
            const customContainer = document.getElementById('customColorContainer');
            if (colorSelect) {
                const selectedOption = colorSelect.options[colorSelect.selectedIndex];
                const isCustom = selectedOption.getAttribute('data-custom') === '1';
                customContainer.style.display = isCustom ? 'block' : 'none';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('sizeOption')) {
                handleSizeOptionChange();
            }
            if (document.getElementById('colorOption')) {
                handleColorOptionChange();
            }
            if (document.getElementById('layoutOption')) {
                handleLayoutOptionChange();
            }
        });


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
                    const sizeOption = document.querySelector('select[name="size_option"]');
                    if (sizeOption) {
                        const selectedSize = sizeOption.options[sizeOption.selectedIndex];
                        const isCustomSize = selectedSize.getAttribute('data-custom') === '1';
                        const customSizeInput = document.querySelector('input[name="custom_size"]');

                        if (isCustomSize && (!customSizeInput || !customSizeInput.value.trim())) {
                            isValid = false;
                            errorMessage += '• Please specify your custom size\n';
                        }
                    }
                <?php endif; ?>

                // Validate color options for Other Services (IDs 18-21)
                <?php if (in_array($product_id, [18, 19, 21])): ?>
                    const colorOption = document.querySelector('select[name="color_option"]');
                    if (colorOption) {
                        const selectedColor = colorOption.options[colorOption.selectedIndex];
                        const isCustomColor = selectedColor.getAttribute('data-custom') === '1';
                        const customColorInput = document.querySelector('input[name="custom_color"]');

                        if (isCustomColor && (!customColorInput || !customColorInput.value.trim())) {
                            isValid = false;
                            errorMessage += '• Please specify your custom color\n';
                        }
                    }
                <?php endif; ?>

                // Validate printing options for printing categories
                <?php if ($customization && !in_array($product_id, [18, 19, 20, 21]) && in_array($product['category'], ['RISO Printing', 'Offset Printing', 'Digital Printing'])): ?>

                    // Validate paper option
                    <?php if ($customization['has_paper_option'] && !empty($paper_options)): ?>
                        const paperOption = document.querySelector('select[name="paper_option"]');
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
                        const finishOption = document.querySelector('select[name="finish_option"]');
                        if (finishOption && !finishOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a finish option\n';
                        }
                    <?php endif; ?>

                    // Validate layout option
                    <?php if ($customization['has_layout_option'] && !empty($layout_options)): ?>
                        const layoutOption = document.querySelector('select[name="layout_option"]');
                        if (layoutOption && !layoutOption.value) {
                            isValid = false;
                            errorMessage += '• Please select a layout option\n';
                        }

                        // Validate layout details based on selection
                        if (layoutOption && layoutOption.value) {
                            const selectedLayout = layoutOption.options[layoutOption.selectedIndex].text;

                            if (selectedLayout.includes('User Layout')) {
                                const userLayoutUpload = document.getElementById('userLayoutUpload');
                                if (!userLayoutUpload || !userLayoutUpload.files.length) {
                                    isValid = false;
                                    errorMessage += '• Please upload your design files for User Layout\n';
                                }
                            } else if (selectedLayout.includes('Store Layout')) {
                                const layoutDetails = document.querySelector('textarea[name="layout_details"]');
                                if (!layoutDetails || !layoutDetails.value.trim()) {
                                    isValid = false;
                                    errorMessage += '• Please provide design specifications for Store Layout\n';
                                }
                            }
                        }
                    <?php endif; ?>

                    // Validate binding option
                    <?php if ($customization['has_binding_option'] && !empty($binding_options)): ?>
                        const bindingOption = document.querySelector('select[name="binding_option"]');
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
                const uploadType = document.querySelector('input[name="upload_type"]:checked').value;

                if (uploadType === 'single') {
                    const designUpload = document.getElementById('designUpload');
                    if (!designUpload || !designUpload.files.length) {
                        isValid = false;
                        errorMessage += '• Please upload a design image\n';
                    }
                } else if (uploadType === 'separate') {
                    const frontDesignUpload = document.getElementById('frontDesignUpload');
                    const backDesignUpload = document.getElementById('backDesignUpload');

                    if (!frontDesignUpload || !frontDesignUpload.files.length) {
                        isValid = false;
                        errorMessage += '• Please upload a front design image\n';
                    }

                    <?php if (!empty($back_base_image_url)): ?>
                        if (!backDesignUpload || !backDesignUpload.files.length) {
                            isValid = false;
                            errorMessage += '• Please upload a back design image\n';
                        }
                    <?php endif; ?>
                }

                // Check if design was applied (mockup generated and used)
                const designImageInput = document.getElementById('designImageInput');
                const frontDesignImageInput = document.getElementById('frontDesignImageInput');

                if (uploadType === 'single') {
                    if (!designImageInput || !designImageInput.value) {
                        isValid = false;
                        errorMessage += '• Please generate and apply your design using the "Generate Mockup" and "Use This Design" buttons\n';
                    }
                } else if (uploadType === 'separate') {
                    if (!frontDesignImageInput || !frontDesignImageInput.value) {
                        isValid = false;
                        errorMessage += '• Please generate and apply your designs using the "Generate Mockup" and "Use This Design" buttons\n';
                    }
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
                    document.querySelector('.image-upload-section').scrollIntoView({
                        behavior: 'smooth'
                    });
                }

                return false;
            }

            return true;
        }
    </script>
</body>

</html>