<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

// Handle pricing request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_pricing_status'])) {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['status'];
        $final_price = $_POST['final_price'];
        $admin_notes = $_POST['admin_notes'];

        // Get the pricing request details
        $request_query = "SELECT * FROM pricing_requests WHERE id = ?";
        $request_stmt = $inventory->prepare($request_query);
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_data = $request_stmt->get_result()->fetch_assoc();

        // Use estimated total as final price if admin didn't enter one
        if (empty($final_price) || $final_price <= 0) {
            $final_price = $request_data['estimated_total'];
        }

        // Update pricing_requests_items table for ALL status changes
        $selected_items = json_decode($request_data['selected_items'], true);

        if (is_array($selected_items)) {
            foreach ($selected_items as $item_id) {
                // Check if record already exists in pricing_requests_items
                $check_query = "SELECT id FROM pricing_requests_items WHERE pricing_request_id = ? AND cart_item_id = ?";
                $check_stmt = $inventory->prepare($check_query);
                $check_stmt->bind_param("ii", $request_id, $item_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                // Calculate price per item
                $price_per_item = $final_price / count($selected_items);

                if ($check_result->num_rows > 0) {
                    // Update existing record - ALWAYS update status and admin_notes
                    $update_item_query = "UPDATE pricing_requests_items SET status = ?, admin_notes = ?, quoted_price = ?, updated_at = NOW() WHERE pricing_request_id = ? AND cart_item_id = ?";
                    $update_item_stmt = $inventory->prepare($update_item_query);
                    $update_item_stmt->bind_param("ssdii", $new_status, $admin_notes, $price_per_item, $request_id, $item_id);
                    $update_item_stmt->execute();
                    error_log("DEBUG: Updated pricing_requests_items record for item $item_id with status $new_status and price $price_per_item");
                } else {
                    // Insert new record
                    $insert_pricing_item = "INSERT INTO pricing_requests_items (pricing_request_id, cart_item_id, admin_notes, quoted_price, status) 
                                            VALUES (?, ?, ?, ?, ?)";
                    $stmt2 = $inventory->prepare($insert_pricing_item);
                    $stmt2->bind_param("iisds", $request_id, $item_id, $admin_notes, $price_per_item, $new_status);
                    $stmt2->execute();
                    error_log("DEBUG: Inserted new pricing_requests_items record for item $item_id with status $new_status and price $price_per_item");
                }

                // Update cart_items table for quoted status (use estimated price if no final price entered)
                if ($new_status === 'quoted') {
                    $update_cart_query = "UPDATE cart_items SET quoted_price = ?, price_updated_by_admin = 1, price_updated_at = NOW() WHERE item_id = ?";
                    $update_stmt = $inventory->prepare($update_cart_query);
                    $update_stmt->bind_param("di", $price_per_item, $item_id);
                    $update_stmt->execute();
                    error_log("DEBUG: Updated cart item $item_id with price $price_per_item (status: $new_status)");
                } else if ($new_status === 'cancelled') {
                    // For cancelled status, clear the admin price flag
                    $update_cart_query = "UPDATE cart_items SET price_updated_by_admin = 0 WHERE item_id = ?";
                    $update_stmt = $inventory->prepare($update_cart_query);
                    $update_stmt->bind_param("i", $item_id);
                    $update_stmt->execute();
                    error_log("DEBUG: Marked cart item $item_id as not admin priced (cancelled)");
                }
            }
        }

        // Update the main pricing_requests table
        $query = "UPDATE pricing_requests SET status = ?, final_price = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $inventory->prepare($query);
        $stmt->bind_param("sdsi", $new_status, $final_price, $admin_notes, $request_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Pricing request #$request_id updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update pricing request!";
        }

        header("Location: admin_pricing_estimates.php");
        exit;
    }
}

// Get all pricing requests with user information
$query = "SELECT pr.*, 
                 u.username, 
                 pc.first_name, 
                 pc.last_name,
                 cc.company_name
          FROM pricing_requests pr 
          JOIN users u ON pr.user_id = u.id 
          LEFT JOIN personal_customers pc ON u.id = pc.user_id
          LEFT JOIN company_customers cc ON u.id = cc.user_id
          ORDER BY pr.request_date DESC";
$requests_result = $inventory->query($query);
$pricing_requests = [];
while ($row = $requests_result->fetch_assoc()) {
    $pricing_requests[] = $row;
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed_requests,
    SUM(CASE WHEN status = 'quoted' THEN 1 ELSE 0 END) as quoted_requests,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_requests
    FROM pricing_requests";
$stats_result = $inventory->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    $request_id = $_POST['request_id'] ?? 0;

    if ($request_id > 0) {
        $query = "DELETE FROM pricing_requests WHERE id = ?";
        $stmt = $inventory->prepare($query);
        $stmt->bind_param("i", $request_id);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Pricing request deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting pricing request: " . $stmt->error;
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Estimates Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            padding-bottom: 130px;
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

        /* Statistics Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card i {
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .stat-card.pending i {
            color: #f39c12;
        }

        .stat-card.reviewed i {
            color: #3498db;
        }

        .stat-card.quoted i {
            color: #9b59b6;
        }

        .stat-card.completed i {
            color: #27ae60;
        }

        .stat-card.cancelled i {
            color: #e74c3c;
        }

        .stat-card.total i {
            color: #2c3e50;
        }

        .stat-number {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        /* Search and Filter */
        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
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

        /* Pricing Requests Table */
        .pricing-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        /* Pricing Actions */
        .pricing-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .status-select,
        .price-input,
        .notes-input {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background: white;
            font-size: 14px;
        }

        .price-input {
            width: 120px;
        }

        .notes-input {
            width: 200px;
        }

        .update-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .update-btn:hover {
            background: #2980b9;
        }

        .view-details {
            background: #27ae60;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .view-details:hover {
            background: #219a52;
        }

        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .delete-btn:hover {
            background: #c0392b;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-align: center;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-reviewed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-quoted {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1f7c4;
            color: #0f5132;
        }

        .status-cancelled {
            background: #f7c4c4ff;
            color: #e74c3c;
        }

        .price-comparison {
            font-size: 0.9em;
        }

        .price-increase {
            color: #e74c3c;
        }

        .price-decrease {
            color: #27ae60;
        }

        .price-same {
            color: #7f8c8d;
        }

        .request-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 150px;
        }

        .detail-value {
            color: #5a6c7d;
            flex: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
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

            .pricing-actions {
                flex-direction: column;
            }

            .status-form {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(3px);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            animation: slideIn 0.3s ease;
            overflow-y: scroll;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 0, 0, 0.6);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2em;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            z-index: 10001;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            max-height: 90vh;
            overflow-y: auto;
            scale: 0.9;
            border-radius: 28px;
        }

        /* Request Details Styles */
        .request-details {
            font-size: 14px;
        }

        .details-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .details-section:last-child {
            border-bottom: none;
        }

        .details-section h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .request-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
        }

        .item-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }

        .item-header h4 {
            margin: 0;
            color: var(--text-dark);
            flex-grow: 1;
        }

        .item-category {
            background: var(--primary-color);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }

        .item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }

        .customization-details {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            border: 1px solid #dee2e6;
        }

        .customization-details p {
            margin: 5px 0;
            font-size: 0.9em;
        }

        .design-preview {
            margin-top: 10px;
            text-align: center;
        }

        .design-preview img {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }

        .status-pending {
            color: #856404;
            background: #fff3cd;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .status-approved {
            color: #155724;
            background: #d4edda;
            padding: 4px 8px;
            border-radius: 4px;
        }

        -status-rejected {
            color: #721c24;
            background: #f8d7da;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .error-message {
            text-align: center;
            padding: 40px;
            color: #dc3545;
        }

        .error-message i {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }

            .item-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .item-category {
                margin-top: 5px;
            }

            .item-details {
                grid-template-columns: 1fr;
            }
        }

        /* Professional Modal Styles */
        .professional-modal {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #2c3e50;
        }

        .modal-header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px 12px 0 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .header-content h2 {
            margin: 0;
            font-weight: 600;
            font-size: 1.8em;
        }

        .header-badges {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .request-id {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-reviewed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-quoted {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d1f7c4;
            color: #0f5132;
        }

        .header-meta {
            display: flex;
            gap: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            opacity: 0.9;
        }

        .modal-body-section {
            padding: 30px;
            background: #f8f9fa;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
        }

        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            border: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
        }

        .info-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2em;
            flex-shrink: 0;
        }

        .info-content h4 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-item {
            display: flex;
            justify-content: between;
        }

        .info-label {
            font-weight: 600;
            color: #6c757d;
            min-width: 200px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        /* Pricing Summary */
        .pricing-summary {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .price-row {
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .price-row.final {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1em;
            color: #2c3e50;
        }

        .price-label {
            color: #6c757d;
        }

        .price-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .price-difference {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            gap: 12px;
        }

        .price-difference.increase {
            background: #f8d7da;
            color: #721c24;
        }

        .price-difference.decrease {
            background: #d1f7c4;
            color: #0f5132;
        }

        .difference-label {
            font-weight: 600;
        }

        .difference-value {
            font-weight: 600;
        }

        /* Items Section */
        .items-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f4;
        }

        .section-header h3 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .items-count {
            background: #e9ecef;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 600;
            color: #6c757d;
        }

        .items-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            max-height: 500px;
            overflow-y: auto;
            padding-right: 10px;
        }

        /* Request Items */
        .request-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .item-title h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .item-category {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 600;
        }

        .item-price .subtotal {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1em;
        }

        .item-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 15px;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-group label {
            font-size: 0.8em;
            color: #6c757d;
            font-weight: 600;
        }

        .detail-group span {
            font-weight: 500;
            color: #2c3e50;
        }

        .detail-group.quoted-price span {
            color: #28a745;
            font-weight: 600;
        }

        /* Customization Section */
        .customization-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .customization-section h5 {
            margin: 0 0 12px 0;
            color: #495057;
            font-weight: 600;
            font-size: 0.9em;
        }

        .customization-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .customization-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            text-transform: uppercase
        }

        .customization {
            justify-content: space-between;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }

        .custom-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.85em;
        }

        .custom-value {
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.85em;
        }

        /* Design Preview Section */
        .design-preview-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed #007bff;
        }

        .design-preview-section h5 {
            margin: 0 0 12px 0;
            color: #007bff;
            font-weight: 600;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .design-preview-section h5 i {
            font-size: 0.9em;
        }

        .design-previews {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .design-preview {
            text-align: center;
            flex: 0 0 auto;
            position: relative;
        }

        .design-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 8px;
            border: 2px solid #007bff;
            padding: 5px;
            background: white;
            transition: transform 0.3s ease;
        }

        .design-preview a:hover .design-image {
            transform: scale(1.05);
            border-color: #0056b3;
        }

        .design-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            color: white;
            font-size: 1.2em;
            border-radius: 6px;
        }

        .design-preview a:hover .design-overlay {
            opacity: 1;
        }

        .design-label {
            font-size: 0.75em;
            color: #666;
            margin-top: 8px;
            font-weight: 500;
            max-width: 100px;
            word-wrap: break-word;
        }

        .design-file-missing {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
            color: #6c757d;
        }

        .design-file-missing i {
            font-size: 1.5em;
            margin-bottom: 5px;
        }

        /* Color coding for different file types */
        .design-preview:has(img[alt*="Original"]) .design-image {
            border-color: #28a745;
        }

        .design-preview:has(img[alt*="Mockup"]) .design-image {
            border-color: #007bff;
        }

        .design-preview:has(.design-file-missing) .design-label {
            color: #dc3545;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .design-previews {
                justify-content: center;
                gap: 12px;
            }

            .design-image {
                width: 80px;
                height: 80px;
            }

            .design-file-missing {
                width: 80px;
                height: 80px;
            }

            .design-label {
                max-width: 80px;
                font-size: 0.7em;
            }
        }

        /* Total Section */
        .total-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            color: white;
        }

        .total-label {
            font-weight: 600;
            font-size: 1.1em;
        }

        .total-amount {
            font-weight: 700;
            font-size: 1.3em;
        }

        /* Scrollbar Styling */
        .items-container::-webkit-scrollbar {
            width: 6px;
        }

        .items-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .items-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .items-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .item-header {
                flex-direction: column;
                gap: 10px;
            }

            .item-details-grid {
                grid-template-columns: 1fr;
            }

            .customization-grid {
                grid-template-columns: 1fr;
            }

            .info-card {
                flex-direction: column;
                text-align: center;
            }

            .info-icon {
                align-self: center;
            }
        }

        .layout-details {
            font-size: 0.9em;
            color: #666;
            margin-top: 2px;
        }

        .layout-files {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #ddd;
        }

        .files-label {
            font-size: 0.85em;
            color: #ffc107;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .layout-file a:hover {
            text-decoration: underline;
        }

        .layout-images {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 5px;
        }

        .layout-image-preview {
            position: relative;
            width: 120px;
            text-align: center;
        }

        .layout-thumbnail {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .image-link {
            display: block;
            position: relative;
            text-decoration: none;
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: 8px;
        }

        .layout-image-preview:hover .image-overlay {
            opacity: 1;
        }

        .image-overlay i {
            color: white;
            font-size: 1.5em;
        }

        .image-actions {
            margin-top: 8px;
        }

        .download-btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8em;
            transition: background 0.3s;
        }

        .download-btn:hover {
            background: #0056b3;
            color: white;
            text-decoration: none;
        }

        .image-filename {
            font-size: 0.75em;
            margin-top: 5px;
            word-break: break-all;
            color: #666;
            line-height: 1.2;
        }

        .layout-file {
            font-size: 0.8em;
            margin: 2px 0;
        }

        .layout-file a {
            color: #007bff;
            text-decoration: none;
        }

        .file-missing {
            color: #e74c3c;
            font-size: 0.8em;
        }


    </style>
</head>

<body>
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Pricing Estimates Management</h1>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="message">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message'];
                                                        unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error'];
                                                                unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card total">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <div class="stat-number"><?php echo $stats['total_requests']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card pending">
                    <i class="fas fa-clock"></i>
                    <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card completed">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number"><?php echo $stats['quoted_requests']; ?></div>
                    <div class="stat-label">Checked</div>
                </div>
                <div class="stat-card cancelled">
                    <i class="fas fa-times-circle"></i>
                    <div class="stat-number"><?php echo $stats['cancelled_requests']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter">
                <input type="text" id="searchInput" placeholder="Search by request ID, customer, or email..." style="min-width: 300px;">
                <select id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="quoted">Checked</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button class="search-btn" onclick="filterRequests()">
                    <i class="fas fa-search"></i> Filter
                </button>
                <button class="search-btn" onclick="clearFilters()" style="background: #95a5a6;">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>

            <!-- Pricing Requests Table -->
            <div class="pricing-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Customer</th>
                            <th>Estimated Total</th>
                            <th>Final Price</th>
                            <th>Status</th>
                            <th>Request Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTable">
                        <?php foreach ($pricing_requests as $request):
                            $customer_name = !empty($request['company_name']) ? $request['company_name'] : (!empty($request['first_name']) ? $request['first_name'] . ' ' . $request['last_name'] :
                                $request['username']);
                            $selected_items = json_decode($request['selected_items'], true);
                        ?>
                            <tr class="request-row" data-status="<?php echo $request['status']; ?>">
                                <td><strong>#<?php echo $request['id']; ?></strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($customer_name); ?></strong>
                                        <br>
                                        <small style="color: #666;"><?php echo htmlspecialchars($request['username']); ?></small>
                                        <br>
                                    </div>
                                </td>
                                <td>
                                    <strong>₱<?php echo number_format($request['estimated_total'], 2); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo count($selected_items); ?> items</small>
                                </td>
                                <td>
                                    <?php if ($request['final_price']): ?>
                                        <strong>₱<?php echo number_format($request['final_price'], 2); ?></strong>
                                        <div class="price-comparison">
                                            <?php
                                            $difference = $request['final_price'] - $request['estimated_total'];
                                            $percentage = $request['estimated_total'] > 0 ? ($difference / $request['estimated_total']) * 100 : 0;
                                            if ($difference > 0): ?>
                                                <small class="price-increase">+₱<?php echo number_format(abs($difference), 2); ?> (<?php echo number_format(abs($percentage), 1); ?>%)</small>
                                            <?php elseif ($difference < 0): ?>
                                                <small class="price-decrease">-₱<?php echo number_format(abs($difference), 2); ?> (<?php echo number_format(abs($percentage), 1); ?>%)</small>
                                            <?php else: ?>
                                                <small class="price-same">No change</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #999;">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                                    <br>
                                    <small style="color: #666;"><?php echo date('g:i A', strtotime($request['request_date'])); ?></small>
                                </td>
                                <td>
                                    <div class="pricing-actions">
                                        <form method="post" class="status-form">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <select name="status" class="status-select">
                                                <option value="pending" <?php echo $request['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="quoted" <?php echo $request['status'] == 'quoted' ? 'selected' : ''; ?>>Checked</option>
                                                <option value="cancelled" <?php echo $request['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <input type="number" name="final_price" class="price-input"
                                                placeholder="Final Price" step="0.01" min="0"
                                                value="<?php echo $request['final_price'] ? $request['final_price'] : ''; ?>">
                                            <input type="text" name="admin_notes" class="notes-input"
                                                placeholder="Admin Notes"
                                                value="<?php echo htmlspecialchars($request['admin_notes'] ?? ''); ?>">
                                            <button type="submit" name="update_pricing_status" class="update-btn" title="Update Pricing">
                                                <i class="fas fa-sync"></i> Update
                                            </button>
                                        </form>
                                        <button type="button" onclick="viewRequestDetails(<?php echo $request['id']; ?>)" class="view-details" title="View Details">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="delete_request" class="delete-btn"
                                                onclick="return confirm('Are you sure you want to delete this pricing request?')" title="Delete Request">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="requestModal" class="modal">
        <div class="modal-content professional-modal-container">
            <div class="modal-body" id="modalBody">
                <div class="loading-state">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <p>Loading request details...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterRequests() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('.request-row');

            rows.forEach(row => {
                const requestId = row.cells[0].textContent.toLowerCase();
                const customer = row.cells[1].textContent.toLowerCase();
                const status = row.getAttribute('data-status');

                const matchesSearch = requestId.includes(searchTerm) || customer.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;

                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            filterRequests();
        }

        function viewRequestDetails(requestId) {
            // Show loading state
            document.getElementById('modalBody').innerHTML = `
                <div class="loading-state">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <p>Loading request details...</p>
                </div>
            `;

            // Add close button to modal
            const modal = document.getElementById('requestModal');
            if (!document.querySelector('.modal-close')) {
                const closeBtn = document.createElement('button');
                closeBtn.className = 'modal-close';
                closeBtn.innerHTML = '×';
                closeBtn.onclick = closeModal;
                modal.querySelector('.modal-content').prepend(closeBtn);
            }

            modal.style.display = 'block';

            // Fetch request details via AJAX
            fetch(`get_pricing_request_details.php?id=${requestId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        document.getElementById('modalBody').innerHTML = `
                            <div class="error-message">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3>Error Loading Details</h3>
                                <p>${data.error}</p>
                            </div>
                        `;
                    } else {
                        document.getElementById('modalBody').innerHTML = data.html;
                    }
                })
                .catch(error => {
                    console.error('Error fetching request details:', error);
                    document.getElementById('modalBody').innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Network Error</h3>
                            <p>Failed to load request details. Please try again.</p>
                            <p><small>Error: ${error.message}</small></p>
                        </div>
                    `;
                });
        }

        // Close modal function
        function closeModal() {
            document.getElementById('requestModal').style.display = 'none';
        }

        // Close modal when clicking outside or pressing ESC
        window.onclick = function(event) {
            const modal = document.getElementById('requestModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });

        // Initial filter on page load
        document.addEventListener('DOMContentLoaded', function() {
            filterRequests();
        });

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