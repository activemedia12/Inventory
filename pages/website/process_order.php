<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $selected_items = explode(',', $_POST['selected_items']);
    $total_amount = $_POST['total_amount'];
    
    // Handle file upload
    $payment_proof = '';
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "../../assets/uploads/payments/user_$user_id/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $payment_proof = 'payment_' . time() . '.' . $file_extension;
        
        if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_dir . $payment_proof)) {
            echo "Debug: File uploaded successfully to user folder: $payment_proof<br>";
        } else {
            echo "Debug: File upload failed<br>";
        }
    }
    
    // Start transaction
    $inventory->begin_transaction();
    
    try {
        // Create order
        $query = "INSERT INTO orders (user_id, total_amount, payment_proof) VALUES (?, ?, ?)";
        $stmt = $inventory->prepare($query);
        $stmt->bind_param("ids", $user_id, $total_amount, $payment_proof);
        $stmt->execute();
        $order_id = $inventory->insert_id;
        
        // Get cart items and insert into order_items
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        $query = "SELECT p.id, p.product_name, p.category, p.price, 
                        ci.quantity, ci.layout_option, ci.layout_details, 
                        ci.gsm_option, ci.user_layout_files, ci.design_image,
                        ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                        ci.finish_option, ci.paper_option, ci.binding_option
                FROM cart_items ci
                JOIN products_offered p ON ci.product_id = p.id
                JOIN carts c ON ci.cart_id = c.cart_id
                WHERE c.user_id = ? AND ci.item_id IN ($placeholders)";
        $stmt = $inventory->prepare($query);
        $types = str_repeat('i', count($selected_items) + 1);
        $stmt->bind_param($types, $user_id, ...$selected_items);
        $stmt->execute();
        $result = $stmt->get_result();

        // Insert order items with all customization options
        $order_items_query = "INSERT INTO order_items (order_id, product_id, product_name, product_category, unit_price, quantity, layout_option, layout_details, gsm_option, user_layout_files, design_image, size_option, custom_size, color_option, custom_color, finish_option, paper_option, binding_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $order_items_stmt = $inventory->prepare($order_items_query);

        while ($item = $result->fetch_assoc()) {
            $order_items_stmt->bind_param(
                "iissdissssssssssss", 
                $order_id, 
                $item['id'],
                $item['product_name'],
                $item['category'],
                $item['price'],
                $item['quantity'],
                $item['layout_option'],
                $item['layout_details'],
                $item['gsm_option'],
                $item['user_layout_files'],
                $item['design_image'],
                $item['size_option'],
                $item['custom_size'],
                $item['color_option'],
                $item['custom_color'],
                $item['finish_option'],
                $item['paper_option'],
                $item['binding_option']
            );
            $order_items_stmt->execute();
        }
        
        // Remove items from cart
        $delete_query = "DELETE ci FROM cart_items ci 
                        JOIN carts c ON ci.cart_id = c.cart_id 
                        WHERE c.user_id = ? AND ci.item_id IN ($placeholders)";
        $delete_stmt = $inventory->prepare($delete_query);
        $delete_stmt->bind_param($types, $user_id, ...$selected_items);
        $delete_stmt->execute();
        
        // Commit transaction
        $inventory->commit();
        
        // Redirect to profile/orders page
        header("Location: profile.php?order_success=1");
        exit;
        
    } catch (Exception $e) {
        $inventory->rollback();
        echo "Error processing order: " . $e->getMessage();
    }
}