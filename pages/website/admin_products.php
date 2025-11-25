<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../accounts/login.php");
    exit;
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
            
        case 'update_product_options':
            $product_id = $_POST['product_id'];
            $option_type = $_POST['option_type'];
            $selected_options = isset($_POST['options']) ? $_POST['options'] : [];
            
            updateSpecificProductOptions($inventory, $product_id, $option_type, $selected_options);
            $_SESSION['message'] = "Product options updated successfully!";
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

// Function to update specific product options
function updateSpecificProductOptions($tshirtprint, $product_id, $option_type, $selected_options) {
    $table_map = [
        'paper' => ['table' => 'product_paper_options', 'column' => 'paper_option_id'],
        'finish' => ['table' => 'product_finish_options', 'column' => 'finish_option_id'],
        'binding' => ['table' => 'product_binding_options', 'column' => 'binding_option_id'],
        'layout' => ['table' => 'product_layout_options', 'column' => 'layout_option_id']
    ];
    
    if (!isset($table_map[$option_type])) return;
    
    $table_info = $table_map[$option_type];
    
    // Clear existing options
    $clear_query = "DELETE FROM {$table_info['table']} WHERE product_id = ?";
    $clear_stmt = $tshirtprint->prepare($clear_query);
    $clear_stmt->bind_param("i", $product_id);
    $clear_stmt->execute();
    
    // Add selected options
    if (!empty($selected_options)) {
        $insert_query = "INSERT INTO {$table_info['table']} (product_id, {$table_info['column']}) VALUES (?, ?)";
        $insert_stmt = $tshirtprint->prepare($insert_query);
        
        foreach ($selected_options as $option_id) {
            $insert_stmt->bind_param("ii", $product_id, $option_id);
            $insert_stmt->execute();
        }
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
            
        case 'get_product_options':
            $product_id = $_GET['product_id'];
            $option_type = $_GET['option_type'];
            
            $table_map = [
                'paper' => ['table' => 'product_paper_options', 'column' => 'paper_option_id', 'options_table' => 'paper_options'],
                'finish' => ['table' => 'product_finish_options', 'column' => 'finish_option_id', 'options_table' => 'finish_options'],
                'binding' => ['table' => 'product_binding_options', 'column' => 'binding_option_id', 'options_table' => 'binding_options'],
                'layout' => ['table' => 'product_layout_options', 'column' => 'layout_option_id', 'options_table' => 'layout_options']
            ];
            
            if (!isset($table_map[$option_type])) {
                echo json_encode(['error' => 'Invalid option type']);
                exit;
            }
            
            $table_info = $table_map[$option_type];
            
            // Get all available options
            $all_options_query = "SELECT * FROM {$table_info['options_table']}";
            $all_options_result = $inventory->query($all_options_query);
            $all_options = [];
            while ($row = $all_options_result->fetch_assoc()) {
                $all_options[] = $row;
            }
            
            // Get selected options for this product
            $selected_query = "SELECT {$table_info['column']} FROM {$table_info['table']} WHERE product_id = ?";
            $selected_stmt = $inventory->prepare($selected_query);
            $selected_stmt->bind_param("i", $product_id);
            $selected_stmt->execute();
            $selected_result = $selected_stmt->get_result();
            $selected_options = [];
            while ($row = $selected_result->fetch_assoc()) {
                $selected_options[] = $row[$table_info['column']];
            }
            
            echo json_encode([
                'all_options' => $all_options,
                'selected_options' => $selected_options
            ]);
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
    $products[] = $row;
}

// Get unique categories for dropdown
$categories_query = "SELECT DISTINCT category FROM products_offered ORDER BY category";
$categories_result = $inventory->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category'];
}

// Get all available options for the option management modal
$paper_options_query = "SELECT * FROM paper_options ORDER BY option_name";
$paper_options_result = $inventory->query($paper_options_query);
$all_paper_options = [];
while ($row = $paper_options_result->fetch_assoc()) {
    $all_paper_options[] = $row;
}

$finish_options_query = "SELECT * FROM finish_options ORDER BY option_name";
$finish_options_result = $inventory->query($finish_options_query);
$all_finish_options = [];
while ($row = $finish_options_result->fetch_assoc()) {
    $all_finish_options[] = $row;
}

$binding_options_query = "SELECT * FROM binding_options ORDER BY option_name";
$binding_options_result = $inventory->query($binding_options_query);
$all_binding_options = [];
while ($row = $binding_options_result->fetch_assoc()) {
    $all_binding_options[] = $row;
}

$layout_options_query = "SELECT * FROM layout_options ORDER BY option_name";
$layout_options_result = $inventory->query($layout_options_query);
$all_layout_options = [];
while ($row = $layout_options_result->fetch_assoc()) {
    $all_layout_options[] = $row;
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
            color: #2c3e50;
            font-size: 1.8em;
            margin: 0;
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
            background-color: rgba(0,0,0,0.5);
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
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
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

        .options-management {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .option-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: white;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .option-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .option-checkbox label {
            margin: 0;
            cursor: pointer;
        }
        
        .option-type-section {
            margin-bottom: 25px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .option-type-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .option-type-title {
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .manage-options-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .manage-options-btn:hover {
            background: #138496;
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
                                    
                                    <?php if ($base_image_exists): ?>
                                        <span class="customization-badge active" title="Base Template Exists">
                                            <i class="fas fa-vector-square"></i> Base
                                        </span>
                                    <?php else: ?>
                                        <span class="customization-badge" style="background: #fff3cd; color: #856404;" title="Base Template Missing">
                                            <i class="fas fa-exclamation-circle"></i> Base
                                        </span>
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
                                    <button class="btn btn-info" onclick="openOptionsModal(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-list"></i> Manage
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
            <form id="productForm" method="post">
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
                
                <div class="image-setup-info" id="imageSetupInfo" style="display: none;">
                    <div class="message" style="margin-bottom: 15px;">
                        <h4><i class="fas fa-info-circle"></i> Image Setup Required</h4>
                        <p>After creating this product, you'll need to add these images:</p>
                        <ul>
                            <li><strong>Product Image:</strong> <code>assets/images/services/service-{id}.jpg</code></li>
                            <li><strong>Base Template:</strong> <code>assets/images/base/base-{id}.jpg</code></li>
                        </ul>
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

    <!-- Options Management Modal -->
    <div id="optionsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" onclick="closeModal('optionsModal')">&times;</span>
            <h2>Manage Product Options</h2>
            <p id="optionsProductInfo" style="color: #666; margin-bottom: 20px;"></p>
            
            <form id="optionsForm" method="post">
                <input type="hidden" name="action" value="update_product_options">
                <input type="hidden" name="product_id" id="optionsProductId">
                <input type="hidden" name="option_type" id="optionType">
                
                <div class="option-type-section">
                    <div class="option-type-header">
                        <h3 class="option-type-title">Paper Options</h3>
                        <button type="button" class="manage-options-btn" onclick="loadOptions('paper')">Manage Paper Options</button>
                    </div>
                    <div class="options-grid" id="paperOptionsGrid">
                        <!-- Options will be loaded dynamically -->
                    </div>
                </div>
                
                <div class="option-type-section">
                    <div class="option-type-header">
                        <h3 class="option-type-title">Finish Options</h3>
                        <button type="button" class="manage-options-btn" onclick="loadOptions('finish')">Manage Finish Options</button>
                    </div>
                    <div class="options-grid" id="finishOptionsGrid">
                        <!-- Options will be loaded dynamically -->
                    </div>
                </div>
                
                <div class="option-type-section">
                    <div class="option-type-header">
                        <h3 class="option-type-title">Binding Options</h3>
                        <button type="button" class="manage-options-btn" onclick="loadOptions('binding')">Manage Binding Options</button>
                    </div>
                    <div class="options-grid" id="bindingOptionsGrid">
                        <!-- Options will be loaded dynamically -->
                    </div>
                </div>
                
                <div class="option-type-section">
                    <div class="option-type-header">
                        <h3 class="option-type-title">Layout Options</h3>
                        <button type="button" class="manage-options-btn" onclick="loadOptions('layout')">Manage Layout Options</button>
                    </div>
                    <div class="options-grid" id="layoutOptionsGrid">
                        <!-- Options will be loaded dynamically -->
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeModal('optionsModal')" style="background: #95a5a6;">Cancel</button>
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
            document.getElementById('imageSetupInfo').style.display = 'none';
            document.getElementById('productModal').style.display = 'block';
        }

        function openEditModal(productId) {
            // Show loading state
            document.getElementById('saveBtnText').style.display = 'none';
            document.getElementById('saveBtnLoading').style.display = 'inline-block';
            
            // Fetch product data via AJAX
            fetch(`admin_products.php?ajax=get_product&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
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
                    
                    // Show image setup info
                    document.getElementById('imageSetupInfo').style.display = 'block';
                    
                    document.getElementById('productModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading product data');
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

        function openOptionsModal(productId) {
            currentProductId = productId;
            document.getElementById('optionsProductId').value = productId;
            
            // Fetch product info for display
            fetch(`admin_products.php?ajax=get_product&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }
                    
                    currentProductName = data.product_name;
                    document.getElementById('optionsProductInfo').textContent = 
                        `Managing options for: ${data.product_name} (${data.category})`;
                    
                    // Load all option types
                    loadOptions('paper');
                    loadOptions('finish');
                    loadOptions('binding');
                    loadOptions('layout');
                    
                    document.getElementById('optionsModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading product information');
                });
        }

        function loadOptions(optionType) {
            document.getElementById('optionType').value = optionType;
            
            fetch(`admin_products.php?ajax=get_product_options&product_id=${currentProductId}&option_type=${optionType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error:', data.error);
                        return;
                    }
                    
                    const grid = document.getElementById(`${optionType}OptionsGrid`);
                    grid.innerHTML = '';
                    
                    data.all_options.forEach(option => {
                        const isSelected = data.selected_options.includes(parseInt(option.id));
                        const optionElement = document.createElement('div');
                        optionElement.className = 'option-checkbox';
                        optionElement.innerHTML = `
                            <input type="checkbox" name="options[]" value="${option.id}" id="opt_${optionType}_${option.id}" ${isSelected ? 'checked' : ''}>
                            <label for="opt_${optionType}_${option.id}">${option.option_name}</label>
                        `;
                        grid.appendChild(optionElement);
                    });
                })
                .catch(error => {
                    console.error('Error loading options:', error);
                });
        }

        function handleCategoryChange() {
            const category = document.getElementById('category').value;
            const imageInfo = document.getElementById('imageSetupInfo');
            
            if (category) {
                imageInfo.style.display = 'block';
            } else {
                imageInfo.style.display = 'none';
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