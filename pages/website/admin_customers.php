<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../accounts/login.php");
    exit;
}

// Handle customer actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'update_customer':
            $user_id = $_POST['user_id'];
            $username = trim($_POST['username']);
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $contact_number = trim($_POST['contact_number']);
            $address_line1 = trim($_POST['address_line1']);
            $city = trim($_POST['city']);

            // Check if username already exists (excluding current user)
            $check_query = "SELECT id FROM users WHERE username = ? AND id != ? AND role = 'customer'";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("si", $username, $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Username already exists!";
            } else {
                // Start transaction
                $inventory->begin_transaction();

                try {
                    // Update users table
                    $user_query = "UPDATE users SET username = ? WHERE id = ? AND role = 'customer'";
                    $user_stmt = $inventory->prepare($user_query);
                    $user_stmt->bind_param("si", $username, $user_id);
                    $user_stmt->execute();

                    // Update personal_customers table
                    $customer_query = "UPDATE personal_customers SET 
                                    first_name = ?, last_name = ?, contact_number = ?, 
                                    address_line1 = ?, city = ? 
                                    WHERE user_id = ?";
                    $customer_stmt = $inventory->prepare($customer_query);
                    $customer_stmt->bind_param("sssssi", $first_name, $last_name, $contact_number, $address_line1, $city, $user_id);
                    $customer_stmt->execute();

                    $inventory->commit();
                    $_SESSION['message'] = "Customer updated successfully!";
                } catch (Exception $e) {
                    $inventory->rollback();
                    $_SESSION['error'] = "Failed to update customer!";
                }
            }
            break;

        case 'delete_customer':
            $user_id = $_POST['user_id'];

            // Check if customer has orders
            $check_query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();

            if ($result['order_count'] > 0) {
                $_SESSION['error'] = "Cannot delete customer - they have existing orders!";
            } else {
                // Start transaction
                $inventory->begin_transaction();

                try {
                    // Delete from carts and cart_items
                    $cart_query = "SELECT cart_id FROM carts WHERE user_id = ?";
                    $cart_stmt = $inventory->prepare($cart_query);
                    $cart_stmt->bind_param("i", $user_id);
                    $cart_stmt->execute();
                    $cart_result = $cart_stmt->get_result();

                    while ($cart = $cart_result->fetch_assoc()) {
                        $delete_cart_items = "DELETE FROM cart_items WHERE cart_id = ?";
                        $delete_items_stmt = $inventory->prepare($delete_cart_items);
                        $delete_items_stmt->bind_param("i", $cart['cart_id']);
                        $delete_items_stmt->execute();
                    }

                    // Delete cart
                    $delete_cart = "DELETE FROM carts WHERE user_id = ?";
                    $delete_cart_stmt = $inventory->prepare($delete_cart);
                    $delete_cart_stmt->bind_param("i", $user_id);
                    $delete_cart_stmt->execute();

                    // Delete from personal_customers
                    $delete_personal = "DELETE FROM personal_customers WHERE user_id = ?";
                    $delete_personal_stmt = $inventory->prepare($delete_personal);
                    $delete_personal_stmt->bind_param("i", $user_id);
                    $delete_personal_stmt->execute();

                    // Finally delete user
                    $delete_user = "DELETE FROM users WHERE id = ? AND role = 'customer'";
                    $delete_user_stmt = $inventory->prepare($delete_user);
                    $delete_user_stmt->bind_param("i", $user_id);

                    if ($delete_user_stmt->execute()) {
                        $inventory->commit();
                        $_SESSION['message'] = "Customer deleted successfully!";
                    } else {
                        throw new Exception("Failed to delete customer");
                    }
                } catch (Exception $e) {
                    $inventory->rollback();
                    $_SESSION['error'] = "Failed to delete customer!";
                }
            }
            break;
    }

    header("Location: admin_customers.php");
    exit;
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    switch ($_GET['ajax']) {
        case 'get_customer':
            $user_id = $_GET['user_id'];
            $query = "SELECT u.*, 
                            pc.first_name, pc.last_name, pc.middle_name, pc.age, pc.gender, pc.birthdate, 
                            pc.contact_number, pc.address_line1, pc.city, pc.province, pc.zip_code,
                            cc.company_name, cc.taxpayer_name, cc.contact_person, cc.contact_number AS company_contact, 
                            cc.province AS company_province, cc.city AS company_city, cc.barangay, cc.subd_or_street, 
                            cc.building_or_block, cc.lot_or_room_no, cc.zip_code AS company_zip,
                            CASE 
                                WHEN pc.user_id IS NOT NULL THEN 'personal'
                                WHEN cc.user_id IS NOT NULL THEN 'company'
                                ELSE 'unknown'
                            END AS customer_type
                    FROM users u
                    LEFT JOIN personal_customers pc ON u.id = pc.user_id
                    LEFT JOIN company_customers cc ON u.id = cc.user_id
                    WHERE u.id = ? AND u.role = 'customer'";

            $stmt = $inventory->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $customer = $result->fetch_assoc();
                echo json_encode($customer);
            } else {
                echo json_encode(['error' => 'Customer not found']);
            }
            exit;

        case 'get_customer_stats':
            $user_id = $_GET['user_id'];

            // Get order statistics
            $order_stats_query = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_order_value,
                MAX(created_at) as last_order_date
                FROM orders 
                WHERE user_id = ? AND status IN ('paid', 'processing', 'ready_for_pickup', 'completed')";
            $order_stats_stmt = $inventory->prepare($order_stats_query);
            $order_stats_stmt->bind_param("i", $user_id);
            $order_stats_stmt->execute();
            $order_stats = $order_stats_stmt->get_result()->fetch_assoc();

            // Get recent orders
            $recent_orders_query = "SELECT order_id, total_amount, status, created_at 
                                   FROM orders 
                                   WHERE user_id = ? 
                                   ORDER BY created_at DESC 
                                   LIMIT 5";
            $recent_orders_stmt = $inventory->prepare($recent_orders_query);
            $recent_orders_stmt->bind_param("i", $user_id);
            $recent_orders_stmt->execute();
            $recent_orders_result = $recent_orders_stmt->get_result();
            $recent_orders = [];
            while ($row = $recent_orders_result->fetch_assoc()) {
                $recent_orders[] = $row;
            }

            echo json_encode([
                'order_stats' => $order_stats,
                'recent_orders' => $recent_orders
            ]);
            exit;
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query for customers
$query = "SELECT u.*, 
                 pc.first_name, pc.last_name, pc.contact_number, pc.city,
                 cc.company_name, cc.contact_person, cc.contact_number as company_contact, cc.city as company_city,
                 COUNT(o.order_id) as order_count,
                 COALESCE(SUM(o.total_amount), 0) as total_spent,
                 MAX(o.created_at) as last_order_date
          FROM users u
          LEFT JOIN personal_customers pc ON u.id = pc.user_id
          LEFT JOIN company_customers cc ON u.id = cc.user_id
          LEFT JOIN orders o ON u.id = o.user_id AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
          WHERE u.role = 'customer'";
$params = [];
$types = '';

// Add search filter
if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR pc.first_name LIKE ? OR pc.last_name LIKE ? OR pc.contact_number LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types .= 'ssss';
}

// Add group by and sorting
$query .= " GROUP BY u.id";

switch ($sort) {
    case 'name':
        $query .= " ORDER BY pc.first_name ASC, pc.last_name ASC";
        break;
    case 'orders':
        $query .= " ORDER BY order_count DESC";
        break;
    case 'spent':
        $query .= " ORDER BY total_spent DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY u.id DESC";
        break;
}

// Prepare and execute query
$stmt = $inventory->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers_result = $stmt->get_result();
$customers = [];
while ($row = $customers_result->fetch_assoc()) {
    $customers[] = $row;
}

// Get total statistics for dashboard
$stats_query = "SELECT 
    COUNT(*) as total_customers,
    AVG(order_count) as avg_orders_per_customer
    FROM (
        SELECT u.id, COUNT(o.order_id) as order_count
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.role = 'customer'
        GROUP BY u.id
    ) as customer_stats";
$stats_result = $inventory->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2em;
            margin-bottom: 15px;
        }

        .stat-card.customers i {
            color: #3498db;
        }

        .stat-card.active i {
            color: #27ae60;
        }

        .stat-card.inactive i {
            color: #e74c3c;
        }

        .stat-card.orders i {
            color: #f39c12;
        }

        .stat-number {
            font-size: 2em;
            font-weight: 600;
            margin: 10px 0;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        /* Search and Filter */
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-filter input,
        .search-filter select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .search-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background: #2980b9;
        }

        /* Customers Table */
        .customers-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
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

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        /* Modal */
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
            margin: 2% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
            position: relative;
        }

        .modal-header {
            padding: 25px 30px 20px;
            border-bottom: 1px solid #eef2f7;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            position: relative;
        }

        .modal-header h2 {
            color: #2c3e50;
            font-size: 1.5em;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-header h2 i {
            color: #3498db;
            font-size: 1.2em;
        }

        .modal-body {
            padding: 30px;
            max-height: calc(90vh - 140px);
            overflow-y: auto;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eef2f7;
            background: #f8fafc;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .close {
            position: absolute;
            right: 25px;
            top: 25px;
            color: #94a3b8;
            font-size: 24px;
            font-weight: 300;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            background: transparent;
            border: none;
        }

        .close:hover {
            background: #f1f5f9;
            color: #64748b;
            transform: rotate(90deg);
        }

        /* Modal Animations */
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

        /* Enhanced Form Styles */
        .modal .form-group {
            margin-bottom: 24px;
        }

        .modal .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }

        .modal .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #ffffff;
        }

        .modal .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background: #ffffff;
        }

        /* Enhanced Button Styles */
        .modal .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            min-width: 100px;
            justify-content: center;
        }

        .modal .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
        }

        .modal .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .modal .btn-secondary {
            background: #64748b;
            color: white;
        }

        .modal .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
        }

        .modal .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #219a52 100%);
            color: white;
            box-shadow: 0 2px 4px rgba(39, 174, 96, 0.2);
        }

        .modal .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        /* Customer Stats in Modal */
        .customer-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin: 25px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .stat-box {
            text-align: center;
            padding: 16px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 1.5em;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.85em;
            color: #64748b;
            font-weight: 500;
        }

        /* Enhanced Table in Modal */
        .modal .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .modal .table th {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 16px;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal .table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
        }

        .modal .table tr:last-child td {
            border-bottom: none;
        }

        /* Customer Info Section */
        .customer-info {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .customer-info h3 {
            color: #1e293b;
            margin-bottom: 16px;
            font-size: 1.3em;
            font-weight: 700;
        }

        .customer-info p {
            margin-bottom: 8px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .customer-info strong {
            color: #374151;
            min-width: 120px;
            display: inline-block;
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

        /* Responsive */
        @media (max-width: 768px) {
            .modal-content {
                margin: 5% auto;
                width: 95%;
                max-height: 95vh;
            }

            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .customer-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Customer Management</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: '<?php echo $_SESSION['message']; ?>',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            background: '#d4edda',
                            iconColor: '#155724',
                            color: '#155724'
                        });
                        <?php unset($_SESSION['message']); ?>
                    });
                </script>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: '<?php echo $_SESSION['error']; ?>',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true,
                            background: '#f8d7da',
                            iconColor: '#721c24',
                            color: '#721c24'
                        });
                        <?php unset($_SESSION['error']); ?>
                    });
                </script>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error'];
                                                                unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card customers">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card orders">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-number"><?php echo round($stats['avg_orders_per_customer'], 1); ?></div>
                    <div class="stat-label">Avg Orders per Customer</div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter">
                <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..."
                        value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">

                    <select name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="orders" <?php echo $sort === 'orders' ? 'selected' : ''; ?>>Most Orders</option>
                        <option value="spent" <?php echo $sort === 'spent' ? 'selected' : ''; ?>>Highest Spent</option>
                    </select>

                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>

                    <a href="admin_customers.php" class="btn" style="background: #95a5a6; color: white; text-decoration: none; padding: 10px 15px;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="customers-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Contact Info</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo $customer['id']; ?></td>
                                <td>
                                    <strong>
                                        <?php
                                        if (!empty($customer['first_name'])) {
                                            echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']);
                                        } elseif (!empty($customer['company_name'])) {
                                            echo htmlspecialchars($customer['company_name']);
                                        } else {
                                            echo 'Customer';
                                        }
                                        ?>
                                    </strong>
                                    <br>
                                    <small style="color: #666;">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['username']); ?>
                                    </small>
                                    <?php if (!empty($customer['middle_name'])): ?>
                                        <br>
                                        <small style="color: #888;">Middle: <?php echo htmlspecialchars($customer['middle_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($customer['contact_number']) || !empty($customer['company_contact'])): ?>
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($customer['contact_number'] ?: $customer['company_contact']); ?>
                                        <br>
                                    <?php endif; ?>

                                    <?php if (!empty($customer['city']) || !empty($customer['company_city'])): ?>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($customer['city'] ?: $customer['company_city']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">No location info</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <strong><?php echo $customer['order_count']; ?> orders</strong>
                                </td>
                                <td>
                                    <strong>₱<?php echo number_format($customer['total_spent'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($customer['last_order_date']): ?>
                                        <?php echo date('M j, Y', strtotime($customer['last_order_date'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">No orders yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info" onclick="viewCustomerDetails(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-warning" onclick="editCustomer(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger" onclick="confirmDelete(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['username']); ?>')">
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

    <!-- Customer Details Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-circle"></i> Customer Details</h2>
                <button class="close" onclick="closeModal('customerModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="customerDetails">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Customer</h2>
            <button class="close" onclick="closeModal('editCustomerModal')">&times;</button>
        </div>
        <form id="editCustomerForm" method="post">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_customer">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Username/Email</label>
                        <input type="email" name="username" id="editUsername" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="text" name="contact_number" id="editContactNumber" class="form-control">
                    </div>
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> First Name</label>
                        <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" name="last_name" id="editLastName" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address Line 1</label>
                    <input type="text" name="address_line1" id="editAddressLine1" class="form-control">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-city"></i> City</label>
                    <input type="text" name="city" id="editCity" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCustomerModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Update Customer
                </button>
            </div>
        </form>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function viewCustomerDetails(userId) {
            fetch(`admin_customers.php?ajax=get_customer_stats&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.error);
                        return;
                    }

                    // Fetch customer basic info
                    fetch(`admin_customers.php?ajax=get_customer&user_id=${userId}`)
                        .then(response => response.json())
                        .then(customer => {
                            const stats = data.order_stats;
                            const recentOrders = data.recent_orders;

                            let ordersHtml = '';
                            if (recentOrders.length > 0) {
                                ordersHtml = recentOrders.map(order => `
                            <tr>
                                <td>#${order.order_id}</td>
                                <td>₱${parseFloat(order.total_amount).toFixed(2)}</td>
                                <td><span class="status-badge status-${order.status}">${order.status}</span></td>
                                <td>${new Date(order.created_at).toLocaleDateString()}</td>
                            </tr>
                        `).join('');
                            } else {
                                ordersHtml = '<tr><td colspan="4" style="text-align: center; color: #999;">No orders found</td></tr>';
                            }

                            // --- Build Customer Info depending on type ---
                            let customerInfoHtml = '';

                            if (customer.customer_type === 'personal') {
                                customerInfoHtml = `
                            <h3>${customer.first_name} ${customer.last_name}</h3>
                            <p><strong>Email:</strong> ${customer.username}</p>
                            <p><strong>Full Name:</strong> ${customer.first_name} ${customer.middle_name || ''} ${customer.last_name}</p>
                            <p><strong>Phone:</strong> ${customer.contact_number || 'Not provided'}</p>
                            <p><strong>Address:</strong> ${customer.address_line1 || 'Not provided'} ${customer.city ? ', ' + customer.city : ''} ${customer.province ? ', ' + customer.province : ''} ${customer.zip_code ? ' ' + customer.zip_code : ''}</p>
                            <p><strong>Age/Gender:</strong> ${customer.age || 'Not provided'} / ${customer.gender || 'Not provided'}</p>
                            <p><strong>Birthdate:</strong> ${customer.birthdate ? new Date(customer.birthdate).toLocaleDateString() : 'Not provided'}</p>
                        `;
                            } else if (customer.customer_type === 'company') {
                                customerInfoHtml = `
                            <h3>${customer.company_name}</h3>
                            <p><strong>Email:</strong> ${customer.username}</p>
                            <p><strong>Taxpayer:</strong> ${customer.taxpayer_name || 'Not provided'}</p>
                            <p><strong>Person:</strong> ${customer.contact_person || 'Not provided'}</p>
                            <p><strong>Phone:</strong> ${customer.company_contact || 'Not provided'}</p>
                            <p><strong>Address:</strong> ${customer.building_or_block || ''} ${customer.lot_or_room_no || ''} ${customer.subd_or_street || ''} ${customer.barangay || ''} ${customer.city || ''} ${customer.province || ''} ${customer.zip_code || ''}</p>
                        `;
                            }

                            document.getElementById('customerDetails').innerHTML = `
                        <div class="customer-info">
                            ${customerInfoHtml}
                        </div>
                        
                        <div class="customer-stats">
                            <div class="stat-box">
                                <div class="stat-value">${stats.total_orders || 0}</div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">₱${parseFloat(stats.total_spent || 0).toFixed(2)}</div>
                                <div class="stat-label">Total Spent</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">₱${parseFloat(stats.avg_order_value || 0).toFixed(2)}</div>
                                <div class="stat-label">Avg Order Value</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">${stats.last_order_date ? new Date(stats.last_order_date).toLocaleDateString() : 'Never'}</div>
                                <div class="stat-label">Last Order</div>
                            </div>
                        </div>
                        
                        <h4>Recent Orders</h4>
                        <table class="table" style="width: 100%; margin-top: 15px;">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>${ordersHtml}</tbody>
                        </table>
                    `;

                            document.getElementById('customerModal').style.display = 'block';
                        });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading customer details');
                });
        }


        function editCustomer(userId) {
            fetch(`admin_customers.php?ajax=get_customer&user_id=${userId}`)
                .then(response => response.json())
                .then(customer => {
                    if (customer.error) {
                        alert('Error: ' + customer.error);
                        return;
                    }

                    document.getElementById('editUserId').value = customer.id;
                    document.getElementById('editUsername').value = customer.username;
                    document.getElementById('editFirstName').value = customer.first_name || '';
                    document.getElementById('editLastName').value = customer.last_name || '';
                    document.getElementById('editContactNumber').value = customer.contact_number || '';
                    document.getElementById('editAddressLine1').value = customer.address_line1 || '';
                    document.getElementById('editCity').value = customer.city || '';

                    document.getElementById('editCustomerModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading customer data');
                });
        }

        function confirmDelete(userId, username) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete customer "${username}". This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin_customers.php';

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_customer';
                    form.appendChild(actionInput);

                    const userIdInput = document.createElement('input');
                    userIdInput.type = 'hidden';
                    userIdInput.name = 'user_id';
                    userIdInput.value = userId;
                    form.appendChild(userIdInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
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

        // Auto-hide messages after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.transition = 'opacity 0.5s ease';
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 500);
                }, 3000);
            });
        });
    </script>
</body>

</html>