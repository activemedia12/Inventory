<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../accounts/login.php");
    exit;
}

// Function to handle product image uploads (supports single and multiple files)
function handleProductImageUpload($product_id, $file_input_name, $directory, $prefix, $suffix = '', $index = null) {
    if (isset($_FILES[$file_input_name])) {
        // Handle both single file and multiple files
        if ($index !== null && is_array($_FILES[$file_input_name]['name'])) {
            // Multiple files - specific index
            $file = [
                'name' => $_FILES[$file_input_name]['name'][$index],
                'type' => $_FILES[$file_input_name]['type'][$index],
                'tmp_name' => $_FILES[$file_input_name]['tmp_name'][$index],
                'error' => $_FILES[$file_input_name]['error'][$index],
                'size' => $_FILES[$file_input_name]['size'][$index]
            ];
        } else {
            // Single file
            $file = $_FILES[$file_input_name];
        }
        
        if ($file['error'] === 0) {
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
                return false;
            }
            
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $_SESSION['error'] = "File size too large. Maximum size is 5MB.";
                return false;
            }
            
            // Create directory if it doesn't exist
            $upload_dir = "../../assets/images/{$directory}/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate filename
            $filename = $prefix . '-' . $product_id . $suffix . '.jpg';
            $file_path = $upload_dir . $filename;
            
            // Convert and save image as JPG
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Clear file cache
                clearstatcache(true, $file_path);
                return true;
            }
        }
    }
    return false;
}

// Function to delete product images
function deleteProductImages($product_id, $image_type) {
    $success = true;
    
    switch($image_type) {
        case 'product_images':
            // Delete up to 5 product images (indices 0-4)
            $files = [];
            for ($i = 0; $i < 5; $i++) {
                $suffix = $i > 0 ? '-' . $i : '';
                $files[] = "../../assets/images/services/service-{$product_id}{$suffix}.jpg";
            }
            break;
        case 'base_templates':
            $files = [
                "../../assets/images/base/base-{$product_id}.jpg",
                "../../assets/images/base/base-{$product_id}-1.jpg"
            ];
            break;
        case 'all_images':
            // Delete all product images (up to 5) AND base templates
            $files = [];
            // Product images (indices 0-4)
            for ($i = 0; $i < 5; $i++) {
                $suffix = $i > 0 ? '-' . $i : '';
                $files[] = "../../assets/images/services/service-{$product_id}{$suffix}.jpg";
            }
            // Base templates
            $files[] = "../../assets/images/base/base-{$product_id}.jpg";
            $files[] = "../../assets/images/base/base-{$product_id}-1.jpg";
            break;
        default:
            return false;
    }
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            if (!unlink($file)) {
                $success = false;
                error_log("Failed to delete file: $file");
            }
        }
    }
    
    return $success;
}

// Handle product actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'add_product':
            $product_name = trim($_POST['product_name']);
            $category = $_POST['category'];
            $price = $_POST['price'];
            
            // Check if product already exists
            $check_query = "SELECT id FROM products_offered WHERE product_name = ? AND category = ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("ss", $product_name, $category);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Product '$product_name' already exists in this category!";
            } else {
                $query = "INSERT INTO products_offered (product_name, category, price) VALUES (?, ?, ?)";
                $stmt = $inventory->prepare($query);
                $stmt->bind_param("ssd", $product_name, $category, $price);
                
                if ($stmt->execute()) {
                    $product_id = $inventory->insert_id;
                    $_SESSION['message'] = "Product '$product_name' added successfully!";
                    
                    // Handle image uploads
                    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                        $uploaded_count = 0;
                        $file_count = min(count($_FILES['product_images']['name']), 5); // Limit to 5 files
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['product_images']['error'][$i] === 0) {
                                $suffix = $i > 0 ? '-' . $i : '';
                                if (handleProductImageUpload($product_id, 'product_images', 'services', 'service', $suffix, $i)) {
                                    $uploaded_count++;
                                }
                            }
                        }
                    }
                    
                    // Handle base template uploads for Other Services
                    if ($category === 'Other Services') {
                        handleProductImageUpload($product_id, 'base_image', 'base', 'base');
                        handleProductImageUpload($product_id, 'base_back_image', 'base', 'base', '-1');
                    }
                    
                    // Add default customization settings
                    $custom_query = "INSERT INTO product_customization (product_id, has_paper_option, has_size_option, has_finish_option, has_layout_option, has_binding_option, has_gsm_option) VALUES (?, 0, 0, 0, 0, 0, 0)";
                    $custom_stmt = $inventory->prepare($custom_query);
                    $custom_stmt->bind_param("i", $product_id);
                    $custom_stmt->execute();
                    
                    // For Other Services, enable size option by default
                    if ($category === 'Other Services') {
                        $update_custom_query = "UPDATE product_customization SET has_size_option = 1 WHERE product_id = ?";
                        $update_custom_stmt = $inventory->prepare($update_custom_query);
                        $update_custom_stmt->bind_param("i", $product_id);
                        $update_custom_stmt->execute();
                    }
                } else {
                    $_SESSION['error'] = "Failed to add product!";
                }
            }
            break;
            
        case 'update_product':
            $product_id = $_POST['product_id'];
            $product_name = trim($_POST['product_name']);
            $category = $_POST['category'];
            $price = $_POST['price'];
            
            // Check if product already exists (excluding current product)
            $check_query = "SELECT id FROM products_offered WHERE product_name = ? AND category = ? AND id != ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("ssi", $product_name, $category, $product_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Product '$product_name' already exists in this category!";
            } else {
                $query = "UPDATE products_offered SET product_name = ?, category = ?, price = ? WHERE id = ?";
                $stmt = $inventory->prepare($query);
                $stmt->bind_param("ssdi", $product_name, $category, $price, $product_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Product updated successfully!";
                    
                    // Handle image uploads for updates
                    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                        $uploaded_count = 0;
                        $file_count = min(count($_FILES['product_images']['name']), 5); // Limit to 5 files
                        
                        for ($i = 0; $i < $file_count; $i++) {
                            if ($_FILES['product_images']['error'][$i] === 0) {
                                $suffix = $i > 0 ? '-' . $i : '';
                                if (handleProductImageUpload($product_id, 'product_images', 'services', 'service', $suffix, $i)) {
                                    $uploaded_count++;
                                }
                            }
                        }
                    }
                    
                    // Handle base template uploads for Other Services
                    if ($category === 'Other Services') {
                        if (isset($_FILES['base_image']) && $_FILES['base_image']['error'] === 0) {
                            handleProductImageUpload($product_id, 'base_image', 'base', 'base');
                        }
                        if (isset($_FILES['base_back_image']) && $_FILES['base_back_image']['error'] === 0) {
                            handleProductImageUpload($product_id, 'base_back_image', 'base', 'base', '-1');
                        }
                    }
                } else {
                    $_SESSION['error'] = "Failed to update product!";
                }
            }
            break;
            
        case 'delete_product':
            $product_id = $_POST['product_id'];
            
            // Check if product has orders
            $check_query = "SELECT COUNT(*) as order_count FROM order_items WHERE product_id = ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("i", $product_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();
            
            if ($result['order_count'] > 0) {
                $_SESSION['error'] = "Cannot delete product - it has existing orders!";
            } else {
                // Delete product images first
                deleteProductImages($product_id, 'all_images');
                
                // Delete from all option tables first
                $option_tables = [
                    'product_paper_options',
                    'product_finish_options', 
                    'product_binding_options',
                    'product_layout_options',
                    'product_tshirt_sizes',
                    'product_tshirt_colors',
                    'product_totesizes',
                    'product_totecolors',
                    'product_paperbag_sizes',
                    'product_mug_sizes',
                    'product_mug_colors'
                ];
                
                foreach ($option_tables as $table) {
                    $delete_query = "DELETE FROM $table WHERE product_id = ?";
                    $delete_stmt = $inventory->prepare($delete_query);
                    $delete_stmt->bind_param("i", $product_id);
                    $delete_stmt->execute();
                }
                
                // Delete from product_customization
                $delete_custom_query = "DELETE FROM product_customization WHERE product_id = ?";
                $delete_custom_stmt = $inventory->prepare($delete_custom_query);
                $delete_custom_stmt->bind_param("i", $product_id);
                $delete_custom_stmt->execute();
                
                // Then delete the product
                $delete_query = "DELETE FROM products_offered WHERE id = ?";
                $delete_stmt = $inventory->prepare($delete_query);
                $delete_stmt->bind_param("i", $product_id);
                
                if ($delete_stmt->execute()) {
                    $_SESSION['message'] = "Product deleted successfully!";
                } else {
                    $_SESSION['error'] = "Failed to delete product!";
                }
            }
            break;

        case 'delete_product_images':
            $product_id = $_POST['product_id'];
            $image_type = $_POST['image_type'];
            
            if (deleteProductImages($product_id, $image_type)) {
                $_SESSION['message'] = "Images deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete images!";
            }
            break;
            
        case 'delete_single_image':
            $product_id = $_POST['product_id'];
            $image_index = $_POST['image_index'];
            
            $success = false;
            
            if ($image_index === 'front') {
                // Delete front base template
                $file = "../../assets/images/base/base-{$product_id}.jpg";
                if (file_exists($file)) {
                    $success = unlink($file);
                }
            } elseif ($image_index === 'back') {
                // Delete back base template  
                $file = "../../assets/images/base/base-{$product_id}-1.jpg";
                if (file_exists($file)) {
                    $success = unlink($file);
                }
            } else {
                // Delete product image (0-4 index)
                $suffix = $image_index > 0 ? '-' . $image_index : '';
                $file = "../../assets/images/services/service-{$product_id}{$suffix}.jpg";
                if (file_exists($file)) {
                    $success = unlink($file);
                }
            }
            
            if ($success) {
                $_SESSION['message'] = "Image deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete image!";
            }
            break;

        case 'update_customization':
            $product_id = $_POST['product_id'];
            $has_paper = isset($_POST['has_paper_option']) ? 1 : 0;
            $has_size = isset($_POST['has_size_option']) ? 1 : 0;
            $has_finish = isset($_POST['has_finish_option']) ? 1 : 0;
            $has_layout = isset($_POST['has_layout_option']) ? 1 : 0;
            $has_binding = isset($_POST['has_binding_option']) ? 1 : 0;
            $has_gsm = isset($_POST['has_gsm_option']) ? 1 : 0;
            
            $query = "UPDATE product_customization SET 
                    has_paper_option = ?, has_size_option = ?, has_finish_option = ?, 
                    has_layout_option = ?, has_binding_option = ?, has_gsm_option = ? 
                    WHERE product_id = ?";
            $stmt = $inventory->prepare($query);
            $stmt->bind_param("iiiiiii", $has_paper, $has_size, $has_finish, $has_layout, $has_binding, $has_gsm, $product_id);
            
            if ($stmt->execute()) {
                // Update available options based on customization settings
                updateProductOptions($inventory, $product_id, $has_paper, $has_finish, $has_binding, $has_layout);
                $_SESSION['message'] = "Customization settings updated!";
            } else {
                $_SESSION['error'] = "Failed to update customization settings!";
            }
            break;
    }
    
    header("Location: admin_products.php");
    exit;
}

// Function to update product options based on customization
function updateProductOptions($tshirtprint, $product_id, $has_paper, $has_finish, $has_binding, $has_layout) {
    // Clear existing options
    $option_tables = [
        'product_paper_options',
        'product_finish_options',
        'product_binding_options', 
        'product_layout_options'
    ];
    
    foreach ($option_tables as $table) {
        $clear_query = "DELETE FROM $table WHERE product_id = ?";
        $clear_stmt = $tshirtprint->prepare($clear_query);
        $clear_stmt->bind_param("i", $product_id);
        $clear_stmt->execute();
    }
    
    // Add all available options if customization is enabled
    if ($has_paper) {
        $paper_query = "INSERT INTO product_paper_options (product_id, paper_option_id) 
                       SELECT ?, id FROM paper_options";
        $paper_stmt = $tshirtprint->prepare($paper_query);
        $paper_stmt->bind_param("i", $product_id);
        $paper_stmt->execute();
    }
    
    if ($has_finish) {
        $finish_query = "INSERT INTO product_finish_options (product_id, finish_option_id) 
                        SELECT ?, id FROM finish_options";
        $finish_stmt = $tshirtprint->prepare($finish_query);
        $finish_stmt->bind_param("i", $product_id);
        $finish_stmt->execute();
    }
    
    if ($has_binding) {
        $binding_query = "INSERT INTO product_binding_options (product_id, binding_option_id) 
                         SELECT ?, id FROM binding_options";
        $binding_stmt = $tshirtprint->prepare($binding_query);
        $binding_stmt->bind_param("i", $product_id);
        $binding_stmt->execute();
    }
    
    if ($has_layout) {
        $layout_query = "INSERT INTO product_layout_options (product_id, layout_option_id) 
                        SELECT ?, id FROM layout_options";
        $layout_stmt = $tshirtprint->prepare($layout_query);
        $layout_stmt->bind_param("i", $product_id);
        $layout_stmt->execute();
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    switch ($_GET['ajax']) {
        case 'get_product':
            $product_id = $_GET['product_id'];
            $query = "SELECT * FROM products_offered WHERE id = ?";
            $stmt = $inventory->prepare($query);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $product = $result->fetch_assoc();
                
                // Check for up to 5 product images
                for ($i = 0; $i < 5; $i++) {
                    $suffix = $i > 0 ? '-' . $i : '';
                    $product_image_path = "../../assets/images/services/service-" . $product_id . $suffix . ".jpg";
                    $product['product_image_exists_' . $i] = file_exists($product_image_path);
                }
                
                // Check base templates
                $base_image_path = "../../assets/images/base/base-" . $product_id . ".jpg";
                $base_back_image_path = "../../assets/images/base/base-" . $product_id . "-1.jpg";
                
                $product['base_image_exists'] = file_exists($base_image_path);
                $product['base_back_image_exists'] = file_exists($base_back_image_path);
                
                echo json_encode($product);
            } else {
                echo json_encode(['error' => 'Product not found']);
            }
            exit;
            
        case 'get_customization':
            $product_id = $_GET['product_id'];
            $query = "SELECT * FROM product_customization WHERE product_id = ?";
            $stmt = $inventory->prepare($query);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $customization = $result->fetch_assoc();
                echo json_encode($customization);
            } else {
                echo json_encode(['error' => 'Customization settings not found']);
            }
            exit;
    }
}

// Get all products with customization settings
$query = "SELECT p.*, 
                 pc.has_paper_option, pc.has_size_option, pc.has_finish_option,
                 pc.has_layout_option, pc.has_binding_option, pc.has_gsm_option
          FROM products_offered p
          LEFT JOIN product_customization pc ON p.id = pc.product_id
          ORDER BY p.category, p.product_name";
$products_result = $inventory->query($query);
$products = [];
while ($row = $products_result->fetch_assoc()) {
    // Check image existence for each product
    $product_id = $row['id'];
    $product_image_path = "../../assets/images/services/service-" . $product_id . ".jpg";
    $product_back_image_path = "../../assets/images/services/service-" . $product_id . "-1.jpg";
    $base_image_path = "../../assets/images/base/base-" . $product_id . ".jpg";
    $base_back_image_path = "../../assets/images/base/base-" . $product_id . "-1.jpg";
    
    $row['product_image_exists'] = file_exists($product_image_path);
    $row['product_back_image_exists'] = file_exists($product_back_image_path);
    $row['base_image_exists'] = file_exists($base_image_path);
    $row['base_back_image_exists'] = file_exists($base_back_image_path);
    
    $products[] = $row;
}

// Get unique categories for dropdown
$categories_query = "SELECT DISTINCT category FROM products_offered ORDER BY category";
$categories_result = $inventory->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #3498db;
            font-size: 1.3em;
            margin-bottom: 5px;
        }

        .sidebar-header small {
            font-size: 0.85em;
            color: #bdc3c7;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f0f2f5;
            padding-bottom: 110px;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1c1e21;
            font-size: 1.8em;
            margin: 0;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        /* Products Table */
        .products-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .category-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .category-offset { background: #e7f3ff; color: #0066cc; }
        .category-digital { background: #e7f6ec; color: #0f5132; }
        .category-riso { background: #fff3cd; color: #856404; }
        .category-other { background: #f8d7da; color: #721c24; }

        .customization-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .customization-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.7em;
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
        }

        .customization-badge.active {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        /* Forms */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(3px);
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        /* Messages */
        .message {
            padding: 15px;
            background: #d4edda;
            color: #155724;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .error {
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* Image Upload Styles */
        .image-upload-section {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .image-upload-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .image-preview-container {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .image-preview {
            width: 100px;
            height: 100px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-preview .no-image {
            width: 100%;
            height: 100%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 12px;
            text-align: center;
        }

        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: #6c757d;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .file-input-label:hover {
            background: #5a6268;
        }

        .file-input {
            display: none;
        }

        .current-images-section {
            margin-top: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .image-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 5px 0;
            font-size: 14px;
            padding: 5px;
            border-radius: 4px;
        }

        .image-status:hover {
            background: #f8f9fa;
        }

        .btn-delete-small {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
            transition: all 0.3s;
        }

        .btn-delete-small:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .btn-delete-all {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 15px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-delete-all:hover {
            background: #c82333;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .status-present {
            background: #28a745;
        }

        .status-missing {
            background: #dc3545;
        }

        .image-preview-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 5px;
            font-size: 10px;
            text-align: center;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Product Management</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="message">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
                <button class="btn btn-success" onclick="exportProducts()">
                    <i class="fas fa-file-export"></i> Export Products
                </button>
                <button class="btn btn-info" onclick="openOptionsManagement()">
                    <i class="fas fa-cogs"></i> Manage Global Options
                </button>
            </div>

            <!-- Products Table -->
            <div class="products-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Customization</th>
                            <th>Images</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): 
                            $category_class = 'category-' . strtolower(str_replace(' ', '-', $product['category']));
                            
                            // Check if product images exist
                            $product_image_path = "../../assets/images/services/service-" . $product['id'] . ".jpg";
                            $product_image_exists = file_exists($product_image_path);
                            $base_image_path = "../../assets/images/base/base-" . $product['id'] . ".jpg";
                            $base_image_exists = file_exists($base_image_path);
                        ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                            </td>
                            <td>
                                <span class="category-badge <?php echo $category_class; ?>">
                                    <?php echo htmlspecialchars($product['category']); ?>
                                </span>
                            </td>
                            <td><strong>₱<?php echo number_format($product['price'], 2); ?></strong></td>
                            <td>
                                <div class="customization-badges">
                                    <?php if ($product['has_paper_option']): ?>
                                        <span class="customization-badge active">Paper</span>
                                    <?php endif; ?>
                                    <?php if ($product['has_size_option']): ?>
                                        <span class="customization-badge active">Size</span>
                                    <?php endif; ?>
                                    <?php if ($product['has_finish_option']): ?>
                                        <span class="customization-badge active">Finish</span>
                                    <?php endif; ?>
                                    <?php if ($product['has_layout_option']): ?>
                                        <span class="customization-badge active">Layout</span>
                                    <?php endif; ?>
                                    <?php if ($product['has_binding_option']): ?>
                                        <span class="customization-badge active">Binding</span>
                                    <?php endif; ?>
                                    <?php if ($product['has_gsm_option']): ?>
                                        <span class="customization-badge active">GSM</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="customization-badges">
                                    <?php if ($product_image_exists): ?>
                                        <span class="customization-badge active" title="Product Image Exists">
                                            <i class="fas fa-image"></i> Product
                                        </span>
                                    <?php else: ?>
                                        <span class="customization-badge" style="background: #f8d7da; color: #721c24;" title="Product Image Missing">
                                            <i class="fas fa-exclamation-triangle"></i> Product
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Only show base badge for products that need base templates
                                    $needs_base = ($product['category'] === 'Other Services');
                                    if ($needs_base): 
                                    ?>
                                        <?php if ($base_image_exists): ?>
                                            <span class="customization-badge active" title="Base Template Exists">
                                                <i class="fas fa-vector-square"></i> Base
                                            </span>
                                        <?php else: ?>
                                            <span class="customization-badge" style="background: #fff3cd; color: #856404;" title="Base Template Missing">
                                                <i class="fas fa-exclamation-circle"></i> Base
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button class="btn btn-warning" onclick="openEditModal(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-primary" onclick="openCustomizationModal(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-cog"></i> Options
                                    </button>
                                    <button class="btn btn-danger" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('productModal')">&times;</span>
            <h2 id="modalTitle">Add New Product</h2>
            <form id="productForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add_product">
                <input type="hidden" name="product_id" id="productId">
                
                <div class="form-group">
                    <label for="product_name">Product Name</label>
                    <input type="text" id="product_name" name="product_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control" required onchange="handleCategoryChange()">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="price">Price (₱)</label>
                    <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <!-- Product Images -->
                <div class="image-upload-section">
                    <h4 class="image-upload-title"><i class="fas fa-images"></i> Product Images (Up to 5 images)</h4>
                    
                    <div class="form-group">
                        <label>Product Images:</label>
                        <label class="file-input-label">
                            <i class="fas fa-upload"></i> Choose Product Images
                            <input type="file" class="file-input" name="product_images[]" accept="image/*" multiple onchange="showFileNames(this, 'productImagesFile')">
                        </label>
                        <small id="productImagesFile" style="color: #6c757d; display: block; margin-top: 5px;">No files chosen</small>
                        <small style="color: #6c757d;">Will be saved as: service-{id}.jpg, service-{id}-1.jpg, service-{id}-2.jpg, etc.</small>
                        
                        <!-- Image Previews Container -->
                        <div class="image-preview-container" id="productImagesPreview" style="display: none; margin-top: 10px;">
                            <!-- Previews will be added here dynamically -->
                        </div>
                    </div>
                    
                    <!-- Current Images Status (for edit mode) -->
                    <div class="current-images-section" id="currentImagesSection" style="display: none;">
                        <h5>Current Images Status:</h5>
                        <div id="currentImagesList">
                            <!-- Current images will be listed here -->
                        </div>
                    </div>
                </div>

                <!-- Base Templates (Only for Other Services) -->
                <div class="image-upload-section" id="baseTemplatesSection" style="display: none;">
                    <h4 class="image-upload-title"><i class="fas fa-vector-square"></i> Base Templates</h4>
                    
                    <div class="form-group">
                        <label>Front Base Template:</label>
                        <label class="file-input-label">
                            <i class="fas fa-upload"></i> Choose Front Base
                            <input type="file" class="file-input" name="base_image" accept="image/*" onchange="showFileName(this, 'frontBaseFile')">
                        </label>
                        <small id="frontBaseFile" style="color: #6c757d; display: block; margin-top: 5px;">No file chosen</small>
                        <small style="color: #6c757d;">Will be saved as: base-{id}.jpg</small>
                        
                        <!-- Image Preview -->
                        <div class="image-preview-container" id="frontBasePreview" style="display: none; margin-top: 10px;">
                            <div class="image-preview">
                                <img id="frontBasePreviewImg" src="" alt="Preview">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Back Base Template (Optional):</label>
                        <label class="file-input-label">
                            <i class="fas fa-upload"></i> Choose Back Base
                            <input type="file" class="file-input" name="base_back_image" accept="image/*" onchange="showFileName(this, 'backBaseFile')">
                        </label>
                        <small id="backBaseFile" style="color: #6c757d; display: block; margin-top: 5px;">No file chosen</small>
                        <small style="color: #6c757d;">Will be saved as: base-{id}-1.jpg</small>
                        
                        <!-- Image Preview -->
                        <div class="image-preview-container" id="backBasePreview" style="display: none; margin-top: 10px;">
                            <div class="image-preview">
                                <img id="backBasePreviewImg" src="" alt="Preview">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current Base Templates Status (for edit mode) -->
                    <div class="current-images-section" id="currentBaseSection" style="display: none;">
                        <h5>Current Base Templates Status:</h5>
                        <div class="image-status">
                            <span class="status-indicator" id="frontBaseStatus"></span>
                            <span>Front Base Template: <span id="frontBaseText">Checking...</span></span>
                        </div>
                        <div class="image-status">
                            <span class="status-indicator" id="backBaseStatus"></span>
                            <span>Back Base Template: <span id="backBaseText">Checking...</span></span>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('productModal')" style="background: #95a5a6;">Cancel</button>
                    <button type="submit" class="btn btn-success" id="saveProductBtn">
                        <span id="saveBtnText">Save Product</span>
                        <span id="saveBtnLoading" class="loading" style="display: none;"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customization Modal -->
    <div id="customizationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('customizationModal')">&times;</span>
            <h2>Customization Options</h2>
            <form id="customizationForm" method="post">
                <input type="hidden" name="action" value="update_customization">
                <input type="hidden" name="product_id" id="customizationProductId">
                
                <div class="form-group">
                    <label>Enable Customization Options:</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="has_paper_option" name="has_paper_option" value="1">
                            <label for="has_paper_option">Paper Options</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="has_size_option" name="has_size_option" value="1">
                            <label for="has_size_option">Size Options</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="has_finish_option" name="has_finish_option" value="1">
                            <label for="has_finish_option">Finish Options</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="has_layout_option" name="has_layout_option" value="1">
                            <label for="has_layout_option">Layout Options</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="has_binding_option" name="has_binding_option" value="1">
                            <label for="has_binding_option">Binding Options</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="has_gsm_option" name="has_gsm_option" value="1">
                            <label for="has_gsm_option">GSM Options</label>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('customizationModal')" style="background: #95a5a6;">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Options</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global variables
        let currentProductId = null;
        let currentProductName = null;

        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Product';
            document.getElementById('formAction').value = 'add_product';
            document.getElementById('productId').value = '';
            document.getElementById('productForm').reset();
            document.getElementById('currentImagesSection').style.display = 'none';
            document.getElementById('currentBaseSection').style.display = 'none';
            document.getElementById('baseTemplatesSection').style.display = 'none';
            
            // Reset file inputs
            resetFileInputs();
            
            document.getElementById('productModal').style.display = 'block';
        }

        function openEditModal(productId) {
            // Show loading state
            document.getElementById('saveBtnText').style.display = 'none';
            document.getElementById('saveBtnLoading').style.display = 'inline-block';
            
            // Reset file inputs first
            resetFileInputs();
            
            console.log('Fetching product data for ID:', productId);
            
            // Fetch product data via AJAX
            fetch(`admin_products.php?ajax=get_product&product_id=${productId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data);
                    
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    // Populate form with product data
                    document.getElementById('modalTitle').textContent = 'Edit Product';
                    document.getElementById('formAction').value = 'update_product';
                    document.getElementById('productId').value = data.id;
                    document.getElementById('product_name').value = data.product_name;
                    document.getElementById('category').value = data.category;
                    document.getElementById('price').value = data.price;
                    
                    // Update image status
                    updateImageStatus(data);
                    
                    // Handle category-specific visibility
                    handleCategoryChange();
                    
                    document.getElementById('productModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    alert('Error loading product data: ' + error.message);
                })
                .finally(() => {
                    // Hide loading state
                    document.getElementById('saveBtnText').style.display = 'inline';
                    document.getElementById('saveBtnLoading').style.display = 'none';
                });
        }

        function openCustomizationModal(productId) {
            document.getElementById('customizationProductId').value = productId;
            
            // Fetch current customization settings
            fetch(`admin_products.php?ajax=get_customization&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    // Set checkbox states
                    document.getElementById('has_paper_option').checked = data.has_paper_option == 1;
                    document.getElementById('has_size_option').checked = data.has_size_option == 1;
                    document.getElementById('has_finish_option').checked = data.has_finish_option == 1;
                    document.getElementById('has_layout_option').checked = data.has_layout_option == 1;
                    document.getElementById('has_binding_option').checked = data.has_binding_option == 1;
                    document.getElementById('has_gsm_option').checked = data.has_gsm_option == 1;
                    
                    document.getElementById('customizationModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading customization settings');
                });
        }

        function refreshPageAfterUpload() {
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Handle category change for base templates visibility
        function handleCategoryChange() {
            const category = document.getElementById('category').value;
            const baseSection = document.getElementById('baseTemplatesSection');
            
            if (category) {
                // Show/hide base templates section
                if (category === 'Other Services') {
                    baseSection.style.display = 'block';
                } else {
                    baseSection.style.display = 'none';
                }
            } else {
                baseSection.style.display = 'none';
            }
        }

        // Show multiple file names when files are selected
        function showFileNames(input, displayElementId) {
            const displayElement = document.getElementById(displayElementId);
            const previewContainer = document.getElementById('productImagesPreview');
            
            if (input.files && input.files.length > 0) {
                const fileNames = Array.from(input.files).slice(0, 5).map(file => file.name).join(', ');
                const fileCount = Math.min(input.files.length, 5);
                
                displayElement.textContent = `Selected ${fileCount} file(s): ${fileNames}`;
                displayElement.style.color = '#28a745';
                displayElement.style.fontWeight = '600';
                
                // Show image previews
                showMultipleImagePreviews(input);
            } else {
                displayElement.textContent = 'No files chosen';
                displayElement.style.color = '#6c757d';
                displayElement.style.fontWeight = 'normal';
                
                // Hide image previews
                previewContainer.style.display = 'none';
                previewContainer.innerHTML = '';
            }
        }

        // Show multiple image previews
        function showMultipleImagePreviews(input) {
            const previewContainer = document.getElementById('productImagesPreview');
            previewContainer.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                const fileCount = Math.min(input.files.length, 5);
                
                for (let i = 0; i < fileCount; i++) {
                    const file = input.files[i];
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'image-preview';
                        previewDiv.innerHTML = `
                            <img src="${e.target.result}" alt="Preview ${i + 1}">
                            <div class="image-preview-label">Image ${i + 1}</div>
                        `;
                        previewContainer.appendChild(previewDiv);
                    };
                    
                    reader.readAsDataURL(file);
                }
                
                previewContainer.style.display = 'flex';
            }
        }

        // Show image preview
        function showImagePreview(input, previewId) {
            const previewContainer = document.getElementById(previewId + 'Preview');
            const previewImg = document.getElementById(previewId + 'PreviewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Hide image preview
        function hideImagePreview(previewId) {
            const previewContainer = document.getElementById(previewId + 'Preview');
            if (previewContainer) {
                previewContainer.style.display = 'none';
            }
        }

        // Get preview ID based on input name
        function getPreviewId(inputName) {
            const previewMap = {
                'product_image': 'mainProduct',
                'product_back_image': 'backProduct',
                'base_image': 'frontBase',
                'base_back_image': 'backBase'
            };
            
            return previewMap[inputName] || inputName;
        }

        function resetFileInputs() {
            // Reset file name displays
            const fileDisplays = ['productImagesFile', 'frontBaseFile', 'backBaseFile'];
            fileDisplays.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = id === 'productImagesFile' ? 'No files chosen' : 'No file chosen';
                    element.style.color = '#6c757d';
                    element.style.fontWeight = 'normal';
                }
            });
            
            // Hide all previews
            const previews = ['productImagesPreview', 'frontBasePreview', 'backBasePreview'];
            previews.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.style.display = 'none';
                    if (id === 'productImagesPreview') {
                        element.innerHTML = '';
                    }
                }
            });
            
            // Reset file inputs
            const fileInputs = document.querySelectorAll('.file-input');
            fileInputs.forEach(input => {
                input.value = '';
            });
        }

        // Update image status in edit mode with delete buttons
        function updateImageStatus(productData) {
            const currentImagesSection = document.getElementById('currentImagesSection');
            const currentImagesList = document.getElementById('currentImagesList');
            const currentBaseSection = document.getElementById('currentBaseSection');
            
            if (productData.id) {
                // Show current images section
                currentImagesSection.style.display = 'block';
                currentImagesList.innerHTML = '<h5>Current Images Status:</h5>';
                
                let hasAnyImages = false;
                
                // Check for up to 5 product images
                for (let i = 0; i < 5; i++) {
                    const imageExists = productData[`product_image_exists_${i}`] || false;
                    if (imageExists) hasAnyImages = true;
                    
                    const imageStatus = document.createElement('div');
                    imageStatus.className = 'image-status';
                    imageStatus.innerHTML = `
                        <span class="status-indicator ${imageExists ? 'status-present' : 'status-missing'}"></span>
                        <span style="flex: 1;">Product Image ${i + 1}: <strong>${imageExists ? '✓ Present' : '✗ Missing'}</strong></span>
                        ${imageExists ? `
                            <button type="button" class="btn-delete-small" onclick="deleteProductImage(${productData.id}, ${i})" title="Delete this image">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        ` : ''}
                    `;
                    currentImagesList.appendChild(imageStatus);
                }
                
                // Add "Delete All Images" button if any images exist
                if (hasAnyImages) {
                    const deleteAllContainer = document.createElement('div');
                    deleteAllContainer.style.marginTop = '15px';
                    deleteAllContainer.style.paddingTop = '15px';
                    deleteAllContainer.style.borderTop = '1px solid #dee2e6';
                    deleteAllContainer.innerHTML = `
                        <button type="button" class="btn-delete-all" onclick="deleteAllProductImages(${productData.id})">
                            <i class="fas fa-trash"></i> Delete All Product Images
                        </button>
                    `;
                    currentImagesList.appendChild(deleteAllContainer);
                }
                
                // Update base templates status if Other Services
                if (productData.category === 'Other Services') {
                    currentBaseSection.style.display = 'block';
                    currentBaseSection.innerHTML = '<h5>Current Base Templates Status:</h5>';
                    
                    // Front base template
                    const frontBaseStatus = document.createElement('div');
                    frontBaseStatus.className = 'image-status';
                    frontBaseStatus.innerHTML = `
                        <span class="status-indicator ${productData.base_image_exists ? 'status-present' : 'status-missing'}"></span>
                        <span style="flex: 1;">Front Base Template: <strong>${productData.base_image_exists ? '✓ Present' : '✗ Missing'}</strong></span>
                        ${productData.base_image_exists ? `
                            <button type="button" class="btn-delete-small" onclick="deleteBaseTemplate(${productData.id}, 'front')" title="Delete front base template">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        ` : ''}
                    `;
                    currentBaseSection.appendChild(frontBaseStatus);
                    
                    // Back base template
                    const backBaseStatus = document.createElement('div');
                    backBaseStatus.className = 'image-status';
                    backBaseStatus.innerHTML = `
                        <span class="status-indicator ${productData.base_back_image_exists ? 'status-present' : 'status-missing'}"></span>
                        <span style="flex: 1;">Back Base Template: <strong>${productData.base_back_image_exists ? '✓ Present' : '✗ Missing'}</strong></span>
                        ${productData.base_back_image_exists ? `
                            <button type="button" class="btn-delete-small" onclick="deleteBaseTemplate(${productData.id}, 'back')" title="Delete back base template">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        ` : ''}
                    `;
                    currentBaseSection.appendChild(backBaseStatus);
                } else {
                    currentBaseSection.style.display = 'none';
                }
            } else {
                currentImagesSection.style.display = 'none';
                currentBaseSection.style.display = 'none';
            }
        }

        // Delete a specific product image
        function deleteProductImage(productId, imageIndex) {
            const imageNumber = imageIndex + 1;
            Swal.fire({
                title: 'Delete Image?',
                text: `Are you sure you want to delete Product Image ${imageNumber}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin_products.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_single_image';
                    form.appendChild(actionInput);
                    
                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    form.appendChild(productIdInput);
                    
                    const imageIndexInput = document.createElement('input');
                    imageIndexInput.type = 'hidden';
                    imageIndexInput.name = 'image_index';
                    imageIndexInput.value = imageIndex;
                    form.appendChild(imageIndexInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Delete all product images
        function deleteAllProductImages(productId) {
            Swal.fire({
                title: 'Delete All Images?',
                text: 'Are you sure you want to delete ALL product images? This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete all!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin_products.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_product_images';
                    form.appendChild(actionInput);
                    
                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    form.appendChild(productIdInput);
                    
                    const imageTypeInput = document.createElement('input');
                    imageTypeInput.type = 'hidden';
                    imageTypeInput.name = 'image_type';
                    imageTypeInput.value = 'product_images';
                    form.appendChild(imageTypeInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Delete base template
        function deleteBaseTemplate(productId, templateType) {
            const templateName = templateType === 'front' ? 'Front Base Template' : 'Back Base Template';
            
            Swal.fire({
                title: 'Delete Base Template?',
                text: `Are you sure you want to delete the ${templateName}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin_products.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_single_image';
                    form.appendChild(actionInput);
                    
                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    form.appendChild(productIdInput);
                    
                    const imageIndexInput = document.createElement('input');
                    imageIndexInput.type = 'hidden';
                    imageIndexInput.name = 'image_index';
                    imageIndexInput.value = templateType;
                    form.appendChild(imageIndexInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        // Helper function to update status indicators
        function updateStatusIndicator(statusId, textId, exists, label) {
            const statusElement = document.getElementById(statusId);
            const textElement = document.getElementById(textId);
            
            if (exists) {
                statusElement.className = 'status-indicator status-present';
                textElement.textContent = `${label} ✓`;
            } else {
                statusElement.className = 'status-indicator status-missing';
                textElement.textContent = `${label} ✗ (Missing)`;
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmDelete(productId, productName) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete "${productName}". This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin_products.php';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_product';
                    form.appendChild(actionInput);
                    
                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    form.appendChild(productIdInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function exportProducts() {
            // Simple CSV export
            const rows = [
                ['ID', 'Product Name', 'Category', 'Price']
            ];
            
            <?php foreach ($products as $product): ?>
            rows.push([
                '<?php echo $product['id']; ?>',
                '<?php echo addslashes($product['product_name']); ?>',
                '<?php echo addslashes($product['category']); ?>',
                '<?php echo $product['price']; ?>'
            ]);
            <?php endforeach; ?>
            
            let csvContent = "data:text/csv;charset=utf-8,";
            rows.forEach(function(rowArray) {
                let row = rowArray.map(field => `"${field}"`).join(",");
                csvContent += row + "\r\n";
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "products_export.csv");
            document.body.appendChild(link);
            link.click();
        }

        function openOptionsManagement() {
            alert('Global options management would open here - this would allow managing paper_types, finish_options, etc. across all products');
            // You can implement a separate modal for global option management
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        }

        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const productName = document.getElementById('product_name').value.trim();
            const category = document.getElementById('category').value;
            const price = document.getElementById('price').value;
            
            if (!productName) {
                e.preventDefault();
                alert('Please enter a product name');
                return;
            }
            
            if (!category) {
                e.preventDefault();
                alert('Please select a category');
                return;
            }
            
            if (!price || price <= 0) {
                e.preventDefault();
                alert('Please enter a valid price');
                return;
            }
        });
    </script>
</body>
</html>