<?php
session_start();
require_once '../../config/db.php';

// Debug: Log received data
error_log("Add to Cart - POST: " . print_r($_POST, true));
error_log("Add to Cart - GET: " . print_r($_GET, true));

if (($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['product_id'])) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : (isset($_GET['product_id']) ? intval($_GET['product_id']) : null);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : (isset($_GET['quantity']) ? intval($_GET['quantity']) : 1);
    
    // Get upload type to determine which design field to use
    $upload_type = isset($_POST['upload_type']) ? $_POST['upload_type'] : (isset($_GET['upload_type']) ? $_GET['upload_type'] : 'single');
    
    // Handle design data based on upload type
    if ($upload_type === 'single') {
        $design_image = isset($_POST['design_image']) ? $_POST['design_image'] : (isset($_GET['design_image']) ? $_GET['design_image'] : null);
    } else {
        // For separate uploads, use front_design_image as the main design_image
        $design_image = isset($_POST['front_design_image']) ? $_POST['front_design_image'] : (isset($_GET['front_design_image']) ? $_GET['front_design_image'] : null);
        
        // Debug: Check if we're getting the separate design data
        error_log("Separate upload detected - Front design: " . ($_POST['front_design_image'] ?? $_GET['front_design_image'] ?? 'NOT SET'));
        error_log("Separate upload detected - Back design: " . ($_POST['back_design_image'] ?? $_GET['back_design_image'] ?? 'NOT SET'));
    }
    
    $size_option   = $_POST['size_option']   ?? ($_GET['size_option']   ?? null);
    $custom_size   = $_POST['custom_size']   ?? ($_GET['custom_size']   ?? null);
    $color_option  = $_POST['color_option']  ?? ($_GET['color_option']  ?? null);
    $custom_color  = $_POST['custom_color']  ?? ($_GET['custom_color']  ?? null);
    $finish_option = $_POST['finish_option'] ?? ($_GET['finish_option'] ?? null);
    $paper_option  = $_POST['paper_option']  ?? ($_GET['paper_option']  ?? null);
    $binding_option= $_POST['binding_option']?? ($_GET['binding_option']?? null);
    $layout_option = $_POST['layout_option'] ?? ($_GET['layout_option'] ?? null);
    $layout_details= $_POST['layout_details']?? ($_GET['layout_details']?? null);
    $gsm_option    = $_POST['gsm_option']    ?? ($_GET['gsm_option']    ?? null);
    $user_layout_files = $_POST['user_layout_files'] ?? ($_GET['user_layout_files'] ?? null);
    
    // Store the referring URL to redirect back
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../website/landing.php';
    
    // Validate inputs
    if (!$product_id || $product_id < 1) {
        header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "error=invalid_product");
        exit;
    }
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    // Debug: Log design image data
    error_log("Upload Type: " . $upload_type);
    error_log("Design Image: " . $design_image);
    
    // Handle design_image (could be JSON or single filename)
    if ($design_image) {
        // Check if it's JSON format
        $design_image_data = json_decode($design_image, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // It's valid JSON, keep it as is
            $design_image = $inventory->real_escape_string($design_image);
        } else {
            // It's a single filename or invalid JSON, sanitize it
            $design_image = $inventory->real_escape_string(basename($design_image));
        }
    } else {
        // If no design image but we have separate designs, try to use front design
        $front_design_image = isset($_POST['front_design_image']) ? $_POST['front_design_image'] : (isset($_GET['front_design_image']) ? $_GET['front_design_image'] : null);
        if ($front_design_image) {
            $design_image = $inventory->real_escape_string($front_design_image);
            error_log("Using front_design_image as fallback: " . $design_image);
        }
    }
    
    // Check if user has an active cart
    $cart_query = "SELECT cart_id FROM carts WHERE user_id = ?";
    $stmt = $inventory->prepare($cart_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_result = $stmt->get_result();
    
    if ($cart_result->num_rows == 0) {
        // Create new cart
        $insert_cart = "INSERT INTO carts (user_id) VALUES (?)";
        $stmt = $inventory->prepare($insert_cart);
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $cart_id = $inventory->insert_id;
        } else {
            error_log("Cart creation failed: " . $inventory->error);
            header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "error=cart_error");
            exit;
        }
    } else {
        $cart_row = $cart_result->fetch_assoc();
        $cart_id = $cart_row['cart_id'];
    }
    
    // Check if product already in cart with same design
    $check_item = "SELECT item_id, quantity FROM cart_items 
        WHERE cart_id = ? AND product_id = ? 
        AND IFNULL(design_image, '') = IFNULL(?, '') 
        AND IFNULL(size_option, '') = IFNULL(?, '')
        AND IFNULL(custom_size, '') = IFNULL(?, '')
        AND IFNULL(color_option, '') = IFNULL(?, '')
        AND IFNULL(custom_color, '') = IFNULL(?, '')
        AND IFNULL(finish_option, '') = IFNULL(?, '')
        AND IFNULL(paper_option, '') = IFNULL(?, '')
        AND IFNULL(binding_option, '') = IFNULL(?, '')
        AND IFNULL(layout_option, '') = IFNULL(?, '')
        AND IFNULL(layout_details, '') = IFNULL(?, '')
        AND IFNULL(gsm_option, '') = IFNULL(?, '')
        AND IFNULL(user_layout_files, '') = IFNULL(?, '')";

    $stmt = $inventory->prepare($check_item);
    $stmt->bind_param(
        "iissssssssssss",
        $cart_id,
        $product_id,
        $design_image,
        $size_option,
        $custom_size,
        $color_option,
        $custom_color,
        $finish_option,
        $paper_option,
        $binding_option,
        $layout_option,
        $layout_details,
        $gsm_option,
        $user_layout_files
    );
    
    $stmt->execute();
    $item_result = $stmt->get_result();
    
    if ($item_result->num_rows > 0) {
        // Update quantity
        $item_row = $item_result->fetch_assoc();
        $new_quantity = $item_row['quantity'] + $quantity;
        
        $update_item = "UPDATE cart_items SET quantity = ? WHERE item_id = ?";
        $stmt = $inventory->prepare($update_item);
        $stmt->bind_param("ii", $new_quantity, $item_row['item_id']);
        
        if ($stmt->execute()) {
            header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "success=updated");
            exit;
        } else {
            error_log("Update cart item failed: " . $inventory->error);
            header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "error=update_error");
            exit;
        }
    } else {
        // Add new item
        $insert_item = "INSERT INTO cart_items 
            (cart_id, product_id, quantity, design_image, size_option, custom_size, color_option, custom_color, finish_option, paper_option, binding_option, layout_option, layout_details, gsm_option, user_layout_files) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $inventory->prepare($insert_item);
        $stmt->bind_param(
            "iiissssssssssss",
            $cart_id,
            $product_id,
            $quantity,
            $design_image,
            $size_option,
            $custom_size,
            $color_option,
            $custom_color,
            $finish_option,
            $paper_option,
            $binding_option,
            $layout_option,
            $layout_details,
            $gsm_option,
            $user_layout_files
        );
        
        if ($stmt->execute()) {
            error_log("Cart item added successfully. Design image: " . $design_image);
            header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "success=added");
            exit;
        } else {
            error_log("Add to cart failed: " . $inventory->error);
            header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "error=add_error");
            exit;
        }
    }
} else {
    // If user is not logged in, redirect to login page
    $redirect_uri = $_SERVER['REQUEST_URI'];
    if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['product_id'])) {
        $redirect_uri .= "?product_id=" . $_GET['product_id'] . "&quantity=" . ($_GET['quantity'] ?? 1);
        if (isset($_GET['design_image'])) {
            $redirect_uri .= "&design_image=" . urlencode($_GET['design_image']);
        }
        if (isset($_GET['upload_type'])) {
            $redirect_uri .= "&upload_type=" . urlencode($_GET['upload_type']);
        }
        if (isset($_GET['front_design_image'])) {
            $redirect_uri .= "&front_design_image=" . urlencode($_GET['front_design_image']);
        }
        if (isset($_GET['back_design_image'])) {
            $redirect_uri .= "&back_design_image=" . urlencode($_GET['back_design_image']);
        }
    }
    header("Location: ../accounts/login.php?redirect=" . urlencode($redirect_uri));
    exit;
}

$inventory->close();
?>