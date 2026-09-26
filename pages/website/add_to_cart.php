<?php
// (service_detail.php includes this file, so only start the session if needed)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/db.php';
require_once '../../config/security.php';

// The cart only changes on a POST request that carries the CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    // Where to send the customer back to (same site only)
    $referrer = safe_referrer('../website/landing.php');

    if (!csrf_valid()) {
        header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "error=csrf");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // service_detail.php always submits the SAME full design JSON blob (front_mockup,
    // back_mockup, front_uploaded_file, back_uploaded_file, upload_type, positions, etc.)
    // in all three of design_image / front_design_image / back_design_image - the actual
    // front/back split lives inside that JSON, not across separate POST fields. Take
    // whichever of the three is populated rather than branching on upload_type (its real
    // values - 'none'/'front_only'/'back_only'/'both_sides' - never match the literal
    // 'single' this used to check for, so that branch never ran and the code below used to
    // run basename() on the whole JSON blob, corrupting it).
    $design_image = null;
    foreach (['design_image', 'front_design_image', 'back_design_image'] as $field) {
        if (!empty($_POST[$field])) {
            $design_image = $_POST[$field];
            break;
        }
    }
    
    $size_option   = $_POST['size_option']   ?? null;
    $custom_size   = $_POST['custom_size']   ?? null;
    $color_option  = $_POST['color_option']  ?? null;
    $custom_color  = $_POST['custom_color']  ?? null;
    $finish_option = $_POST['finish_option'] ?? null;
    $paper_option  = $_POST['paper_option']  ?? null;
    $binding_option= $_POST['binding_option']?? null;
    $layout_option = $_POST['layout_option'] ?? null;
    $layout_details= $_POST['layout_details']?? null;
    $gsm_option    = $_POST['gsm_option']    ?? null;
    $user_layout_files = $_POST['user_layout_files'] ?? null;
    
    // $referrer was set (and checked) at the top of this block
    
    // Validate inputs
    if (!$product_id || $product_id < 1) {
        header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "error=invalid_product");
        exit;
    }
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
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
    }
    // else: no design image supplied at all (front/back were both empty above) - leave as null.
    
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
    
    // Check if product already in cart with same design and options
    $check_item = "SELECT item_id, quantity FROM cart_items 
        WHERE cart_id = ? AND product_id = ? 
        AND COALESCE(design_image, '') = COALESCE(?, '')
        AND COALESCE(size_option, '') = COALESCE(?, '')
        AND COALESCE(custom_size, '') = COALESCE(?, '')
        AND COALESCE(color_option, '') = COALESCE(?, '')
        AND COALESCE(custom_color, '') = COALESCE(?, '')
        AND COALESCE(finish_option, '') = COALESCE(?, '')
        AND COALESCE(paper_option, '') = COALESCE(?, '')
        AND COALESCE(binding_option, '') = COALESCE(?, '')
        AND COALESCE(layout_option, '') = COALESCE(?, '')
        AND COALESCE(layout_details, '') = COALESCE(?, '')
        AND COALESCE(gsm_option, '') = COALESCE(?, '')
        AND COALESCE(user_layout_files, '') = COALESCE(?, '')";

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
            header("Location: ../../website/view_cart.php?success=updated");
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
            header("Location: ../../website/view_cart.php?success=added");
            exit;
        } else {
            error_log("Add to cart failed: " . $inventory->error);
            header("Location: " . $referrer . (strpos($referrer, '?') === false ? '?' : '&') . "error=add_error");
            exit;
        }
    }
} else {
    // Not logged in (or not a POST): send them to login and bring them back to the page they came from.
    $back = safe_referrer('');
    $redirect_uri = '';
    if ($back !== '') {
        $redirect_uri = (string) parse_url($back, PHP_URL_PATH);
        $query = parse_url($back, PHP_URL_QUERY);
        if ($query) {
            $redirect_uri .= '?' . $query;
        }
    }
    header("Location: ../accounts/login.php" . ($redirect_uri !== '' ? "?redirect=" . urlencode($redirect_uri) : ''));
    exit;
}

$inventory->close();
?>