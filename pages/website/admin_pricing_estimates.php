<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f5eff;
            --secondary: #4048e0;
            --primary-bg: #eef1ff;
            --light: #f6f6f7;
            --dark: #14171f;
            --gray: #6b7280;
            --light-gray: #e2e4e7;
            --card-bg: #ffffff;
            --success: #1a9c6b;
            --success-bg: #e3f6ee;
            --danger: #d9463c;
            --danger-bg: #fbe9e7;
            --warning: #b6790a;
            --warning-bg: #fdf2df;
            --info: #2a7ade;
            --info-bg: #e8f1fc;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbced3;
            border-radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 28px 32px;
            background: var(--light);
            padding-bottom: 90px;
        }

        .header {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 18px 20px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--dark);
            font-size: 22px;
            margin: 0;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: var(--danger-bg);
            color: var(--danger);
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: opacity 0.15s ease;
        }

        .logout-btn:hover {
            opacity: 0.8;
        }

        /* Statistics Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 18px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            text-align: center;
        }

        .stat-card i {
            font-size: 22px;
            margin-bottom: 10px;
        }

        .stat-card.pending i {
            color: var(--warning);
        }

        .stat-card.reviewed i {
            color: var(--primary);
        }

        .stat-card.quoted i {
            color: var(--secondary);
        }

        .stat-card.completed i {
            color: var(--success);
        }

        .stat-card.cancelled i {
            color: var(--danger);
        }

        .stat-card.total i {
            color: var(--dark);
        }

        .stat-number {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
        }

        /* Messages */
        .message {
            padding: 12px 15px;
            background: var(--success-bg);
            color: var(--success);
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .error {
            padding: 12px 15px;
            background: var(--danger-bg);
            color: var(--danger);
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        /* Search and Filter */
        .search-filter {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            padding: 16px 18px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-filter input,
        .search-filter select {
            padding: 9px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            color: var(--dark);
            background: var(--card-bg);
        }

        .search-filter input:focus,
        .search-filter select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: background-color 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background: var(--secondary);
        }

        /* Pricing Requests Table */
        .pricing-table {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 10px 14px;
            text-align: center;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
        }

        .table th {
            background: var(--light);
            font-weight: 600;
            color: var(--gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* Pricing Actions */
        .pricing-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .status-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .status-select,
        .price-input,
        .notes-input {
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            background: var(--card-bg);
            font-size: 12px;
            color: var(--dark);
        }

        .status-select:focus,
        .price-input:focus,
        .notes-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .price-input {
            width: 110px;
        }

        .notes-input {
            width: 180px;
        }

        .update-btn {
            background: var(--primary-bg);
            color: var(--secondary);
            border: none;
            padding: 7px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: opacity 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .update-btn:hover {
            opacity: 0.8;
        }

        .view-details {
            background: var(--success-bg);
            color: var(--success);
            padding: 7px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.15s ease;
            border: none;
            cursor: pointer;
        }

        .view-details:hover {
            opacity: 0.8;
        }

        .delete-btn {
            background: var(--danger-bg);
            color: var(--danger);
            border: none;
            padding: 7px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: opacity 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .delete-btn:hover {
            opacity: 0.8;
        }

        .status-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }

        .status-pending {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-reviewed {
            background: var(--info-bg);
            color: var(--info);
        }

        .status-quoted {
            background: var(--success-bg);
            color: var(--success);
        }

        .status-completed {
            background: var(--success-bg);
            color: var(--success);
        }

        .status-cancelled {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .price-comparison {
            font-size: 12px;
        }

        .price-increase {
            color: var(--danger);
        }

        .price-decrease {
            color: var(--success);
        }

        .price-same {
            color: var(--gray);
        }

        .request-details {
            background: var(--light);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--light-gray);
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: var(--gray);
            min-width: 150px;
        }

        .detail-value {
            color: var(--dark);
            flex: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
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
            backdrop-filter: blur(2px);
            animation: fadeIn 0.2s ease;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .modal-content {
            position: relative;
            animation: slideIn 0.2s ease;
            background: var(--card-bg);
            width: 100%;
            max-width: 920px;
            max-height: 820px;
            overflow-y: auto;
            border-radius: 12px;
            box-shadow: 0 20px 45px rgba(20, 23, 31, 0.25);
            flex-shrink: 0;
        }

        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: var(--light-gray);
            border-radius: 8px;
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
                transform: translateY(-20px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.15s ease;
            z-index: 10001;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            border-radius: 12px;
        }

        /* Request Details Styles (legacy / simple modal) */
        .request-details {
            font-size: 13px;
        }

        .details-section {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }

        .details-section:last-child {
            border-bottom: none;
        }

        .details-section h3 {
            color: var(--primary);
            margin-bottom: 12px;
            font-size: 15px;
            font-weight: 600;
        }

        .request-item {
            background: var(--light);
            padding: 14px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 3px solid var(--primary);
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .item-header h4 {
            margin: 0;
            color: var(--dark);
            flex-grow: 1;
            font-size: 14px;
            font-weight: 600;
        }

        .item-category {
            background: var(--primary);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }

        .item-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 10px;
        }

        .customization-details {
            background: var(--card-bg);
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            border: 1px solid var(--light-gray);
        }

        .customization-details p {
            margin: 4px 0;
            font-size: 12px;
        }

        .design-preview {
            margin-top: 10px;
            text-align: center;
        }

        .design-preview img {
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            padding: 4px;
        }

        .status-approved {
            color: var(--success);
            background: var(--success-bg);
            padding: 3px 8px;
            border-radius: 4px;
        }

        .status-rejected {
            color: var(--danger);
            background: var(--danger-bg);
            padding: 3px 8px;
            border-radius: 4px;
        }

        .error-message {
            text-align: center;
            padding: 60px 32px;
            color: var(--danger);
        }

        .error-message i {
            font-size: 32px;
            margin-bottom: 16px;
        }

        .error-message h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 6px;
        }

        .error-message p {
            font-size: 13px;
            color: var(--gray);
        }

        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            padding: 90px 32px;
            color: var(--gray);
            font-size: 13px;
            font-weight: 500;
        }

        .loading-spinner i {
            font-size: 28px;
            color: var(--primary);
        }

        .btn {
            padding: 9px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--light-gray);
        }

        .btn-secondary:hover {
            background: var(--light-gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modal {
                padding: 12px;
            }

            .modal-content {
                width: 100%;
                height: 94vh;
                max-height: none;
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
            font-family: 'Inter', sans-serif;
            color: var(--dark);
        }

        .modal-header-section {
            background: var(--dark);
            color: white;
            padding: 22px 52px 22px 22px;
            border-radius: 12px 12px 0 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            row-gap: 8px;
            margin-bottom: 12px;
        }

        .header-content h2 {
            margin: 0;
            font-weight: 600;
            font-size: 18px;
        }

        .header-badges {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .request-id {
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            white-space: nowrap;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .header-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            opacity: 0.85;
        }

        .modal-body-section {
            padding: 22px;
            background: var(--light);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 20px;
            min-width: 0;
        }

        .left-column,
        .right-column {
            min-width: 0;
        }

        /* Info Cards */
        .info-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 16px;
            border: 1px solid var(--light-gray);
            display: flex;
            gap: 14px;
            min-width: 0;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }

        .info-content {
            min-width: 0;
            flex: 1;
        }

        .info-content h4 {
            margin: 0 0 12px 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
        }

        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray);
            min-width: 110px;
            flex-shrink: 0;
            font-size: 13px;
        }

        .info-value {
            color: var(--dark);
            font-weight: 500;
            font-size: 13px;
            flex: 1;
            min-width: 0;
            text-align: right;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        /* Pricing Summary */
        .pricing-summary {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
        }

        .price-row.final {
            border-bottom: none;
            font-weight: 600;
            font-size: 15px;
            color: var(--dark);
        }

        .price-label {
            color: var(--gray);
        }

        .price-value {
            font-weight: 600;
            color: var(--dark);
        }

        .price-difference {
            display: flex;
            justify-content: space-between;
            padding: 10px 12px;
            border-radius: 6px;
            margin-top: 8px;
            gap: 12px;
            font-size: 13px;
        }

        .price-difference.increase {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .price-difference.decrease {
            background: var(--success-bg);
            color: var(--success);
        }

        .difference-label {
            font-weight: 600;
        }

        .difference-value {
            font-weight: 600;
        }

        /* Items Section */
        .items-section {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            border: 1px solid var(--light-gray);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--light-gray);
        }

        .section-header h3 {
            margin: 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 15px;
        }

        .items-count {
            background: var(--light);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: var(--gray);
        }

        .items-container {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        /* Request Items */
        .request-item {
            background: var(--light);
            border-radius: 8px;
            padding: 16px;
            border-left: 3px solid var(--primary);
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }

        .item-title {
            min-width: 0;
            flex: 1;
        }

        .item-title h4 {
            margin: 0 0 4px 0;
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .item-category {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            max-width: 100%;
            word-break: break-word;
        }

        .item-price {
            flex-shrink: 0;
            text-align: right;
        }

        .item-price .subtotal {
            font-weight: 700;
            color: var(--dark);
            font-size: 15px;
            white-space: nowrap;
        }

        .item-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .detail-group label {
            font-size: 11px;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .detail-group span {
            font-weight: 500;
            color: var(--dark);
            font-size: 13px;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .detail-group.quoted-price span {
            color: var(--success);
            font-weight: 600;
        }

        /* Customization Section */
        .customization-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--light-gray);
        }

        .customization-section h5 {
            margin: 0 0 10px 0;
            color: var(--gray);
            font-weight: 600;
            font-size: 12px;
        }

        .customization-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
        }

        .customization-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 12px;
            background: var(--card-bg);
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            min-width: 0;
        }

        .customization-item.full-width {
            grid-column: 1 / -1;
            flex-direction: column;
            align-items: flex-start;
        }

        .layout-details-value {
            width: 100%;
            max-height: 140px;
            overflow-y: auto;
            text-align: left;
            white-space: pre-wrap;
            margin-top: 4px;
        }

        .customization {
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--card-bg);
            border-radius: 6px;
            border: 1px solid var(--light-gray);
        }

        .custom-label {
            font-weight: 600;
            color: var(--gray);
            font-size: 12px;
            flex-shrink: 0;
        }

        .custom-value {
            color: var(--dark);
            font-weight: 500;
            font-size: 12px;
            text-align: right;
            min-width: 0;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        /* Design Preview Section */
        .design-preview-section {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed var(--light-gray);
        }

        .design-preview-section h5 {
            margin: 0 0 10px 0;
            color: var(--primary);
            font-weight: 600;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .design-preview-section h5 i {
            font-size: 11px;
        }

        .design-previews {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .design-preview {
            text-align: center;
            flex: 0 0 auto;
            position: relative;
        }

        .design-image {
            width: 84px;
            height: 84px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            padding: 4px;
            background: var(--card-bg);
            transition: transform 0.15s ease;
        }

        .design-preview a:hover .design-image {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .design-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(20, 23, 31, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.15s ease;
            color: white;
            font-size: 15px;
            border-radius: 6px;
        }

        .design-preview a:hover .design-overlay {
            opacity: 1;
        }

        .design-label {
            font-size: 10px;
            color: var(--gray);
            margin-top: 6px;
            font-weight: 500;
            max-width: 84px;
            word-wrap: break-word;
        }

        .design-file-missing {
            width: 84px;
            height: 84px;
            background: var(--light);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: 1px dashed var(--light-gray);
            color: var(--gray);
        }

        .design-file-missing i {
            font-size: 18px;
            margin-bottom: 4px;
        }

        /* Color coding for different file types */
        .design-preview:has(img[alt*="Original"]) .design-image {
            border-color: var(--success);
        }

        .design-preview:has(img[alt*="Mockup"]) .design-image {
            border-color: var(--primary);
        }

        .design-preview:has(.design-file-missing) .design-label {
            color: var(--danger);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .design-previews {
                justify-content: center;
                gap: 10px;
            }

            .design-image {
                width: 70px;
                height: 70px;
            }

            .design-file-missing {
                width: 70px;
                height: 70px;
            }

            .design-label {
                max-width: 70px;
                font-size: 10px;
            }
        }

        /* Total Section */
        .total-section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--light-gray);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            background: var(--dark);
            border-radius: 8px;
            color: white;
        }

        .total-label {
            font-weight: 600;
            font-size: 14px;
        }

        .total-amount {
            font-weight: 700;
            font-size: 18px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
                gap: 16px;
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
            font-size: 12px;
            color: var(--gray);
            margin-top: 2px;
        }

        .layout-files {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed var(--light-gray);
        }

        .files-label {
            font-size: 12px;
            color: var(--warning);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .layout-file a:hover {
            text-decoration: underline;
        }

        .layout-images {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 5px;
        }

        .layout-image-preview {
            position: relative;
            width: 100px;
            text-align: center;
        }

        .layout-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
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
            background: rgba(20, 23, 31, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.15s ease;
            border-radius: 6px;
        }

        .layout-image-preview:hover .image-overlay {
            opacity: 1;
        }

        .image-overlay i {
            color: white;
            font-size: 15px;
        }

        .image-actions {
            margin-top: 6px;
        }

        .download-btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 11px;
            transition: background-color 0.15s ease;
        }

        .download-btn:hover {
            background: var(--secondary);
            color: white;
            text-decoration: none;
        }

        .image-filename {
            font-size: 10px;
            margin-top: 5px;
            word-break: break-all;
            color: var(--gray);
            line-height: 1.3;
        }

        .layout-file {
            font-size: 11px;
            margin: 2px 0;
        }

        .layout-file a {
            color: var(--primary);
            text-decoration: none;
        }

        .file-missing {
            color: var(--danger);
            font-size: 11px;
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Pricing Consultation Management</h1>
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
                <button class="search-btn" onclick="clearFilters()" style="background: var(--gray);">
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
                                        <small style="color: var(--gray);"><?php echo htmlspecialchars($request['username']); ?></small>
                                        <br>
                                    </div>
                                </td>
                                <td>
                                    <strong>₱<?php echo number_format($request['estimated_total'], 2); ?></strong>
                                    <br>
                                    <small style="color: var(--gray);"><?php echo count($selected_items); ?> items</small>
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
                                        <span style="color: var(--gray);">Not set</span>
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
                                    <small style="color: var(--gray);"><?php echo date('g:i A', strtotime($request['request_date'])); ?></small>
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

            modal.style.display = 'flex';

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