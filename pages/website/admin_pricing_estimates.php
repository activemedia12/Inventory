<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: ../accounts/login.php");
    exit;
}

// CSRF protection: every POST on this page must carry this session's token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

// Handle pricing request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_pricing_status'])) {
        $request_id = $_POST['request_id'];
        $new_status = $_POST['status'];
        $admin_notes = $_POST['admin_notes'];
        // Per-item prices entered by the admin: item_price[item_id] => price.
        $item_prices = isset($_POST['item_price']) && is_array($_POST['item_price']) ? $_POST['item_price'] : [];

        // Get the pricing request details
        $request_query = "SELECT * FROM pricing_requests WHERE id = ?";
        $request_stmt = $inventory->prepare($request_query);
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_data = $request_stmt->get_result()->fetch_assoc();

        // Update pricing_requests_items table for ALL status changes
        $selected_items = json_decode($request_data['selected_items'], true);

        $final_price = 0; // recomputed below as the sum of each item's price
        $returned_item_prices = []; // item_id => price actually saved, sent back to the browser

        if (is_array($selected_items)) {
            $item_count = max(count($selected_items), 1);

            foreach ($selected_items as $item_id) {
                // Check if record already exists in pricing_requests_items, and
                // remember whatever price was already quoted for it (if any).
                $check_query = "SELECT id, quoted_price FROM pricing_requests_items WHERE pricing_request_id = ? AND cart_item_id = ?";
                $check_stmt = $inventory->prepare($check_query);
                $check_stmt->bind_param("ii", $request_id, $item_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $existing_item = $check_result->fetch_assoc(); // null if this item has no row yet

                // Decide the price for THIS item, in order of priority:
                // 1) a price the admin explicitly typed on this save
                // 2) whatever price was already quoted for it previously
                // 3) if it's being quoted for the very first time with no
                //    price at all, fall back to an even split of the
                //    estimated total so it doesn't show as free
                // 4) otherwise (e.g. being cancelled/left pending and never
                //    priced), leave it unpriced rather than inventing a number
                if (isset($item_prices[$item_id]) && $item_prices[$item_id] !== '') {
                    $price_per_item = (float) $item_prices[$item_id];
                } elseif ($existing_item && $existing_item['quoted_price'] !== null) {
                    $price_per_item = (float) $existing_item['quoted_price'];
                } elseif ($new_status === 'quoted') {
                    $price_per_item = $request_data['estimated_total'] / $item_count;
                } else {
                    $price_per_item = null;
                }
                $final_price += $price_per_item ?? 0;
                $returned_item_prices[$item_id] = $price_per_item;

                if ($existing_item) {
                    // Update existing record - ALWAYS update status and admin_notes
                    $update_item_query = "UPDATE pricing_requests_items SET status = ?, admin_notes = ?, quoted_price = ?, updated_at = NOW() WHERE pricing_request_id = ? AND cart_item_id = ?";
                    $update_item_stmt = $inventory->prepare($update_item_query);
                    $update_item_stmt->bind_param("ssdii", $new_status, $admin_notes, $price_per_item, $request_id, $item_id);
                    $update_item_stmt->execute();
                    error_log("DEBUG: Updated pricing_requests_items record for item $item_id with status $new_status and price " . var_export($price_per_item, true));
                } else {
                    // Insert new record
                    $insert_pricing_item = "INSERT INTO pricing_requests_items (pricing_request_id, cart_item_id, admin_notes, quoted_price, status) 
                                            VALUES (?, ?, ?, ?, ?)";
                    $stmt2 = $inventory->prepare($insert_pricing_item);
                    $stmt2->bind_param("iisds", $request_id, $item_id, $admin_notes, $price_per_item, $new_status);
                    $stmt2->execute();
                    error_log("DEBUG: Inserted new pricing_requests_items record for item $item_id with status $new_status and price " . var_export($price_per_item, true));
                }

                // Update cart_items table for quoted status (use estimated price if no final price entered)
                if ($new_status === 'quoted') {
                    $update_cart_query = "UPDATE cart_items SET quoted_price = ?, price_updated_by_admin = 1, price_updated_at = NOW() WHERE item_id = ?";
                    $update_stmt = $inventory->prepare($update_cart_query);
                    $update_stmt->bind_param("di", $price_per_item, $item_id);
                    $update_stmt->execute();
                    error_log("DEBUG: Updated cart item $item_id with price $price_per_item (status: $new_status)");
                } else if ($new_status === 'cancelled') {
                    // For cancelled status, clear the admin price flag but
                    // leave any previously-quoted price untouched - the
                    // cancellation shouldn't rewrite pricing history.
                    $update_cart_query = "UPDATE cart_items SET price_updated_by_admin = 0 WHERE item_id = ?";
                    $update_stmt = $inventory->prepare($update_cart_query);
                    $update_stmt->bind_param("i", $item_id);
                    $update_stmt->execute();
                    error_log("DEBUG: Marked cart item $item_id as not admin priced (cancelled)");
                }
            }
        }

        // Update the main pricing_requests table. final_price is now the
        // sum of the individual item prices above, not a value the admin
        // typed in separately, so it can never drift from what each item
        // actually shows.
        $query = "UPDATE pricing_requests SET status = ?, final_price = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $inventory->prepare($query);
        $stmt->bind_param("sdsi", $new_status, $final_price, $admin_notes, $request_id);
        $success = $stmt->execute();

        // AJAX callers (the inline row form) get JSON back and update the
        // row in place; anything still POSTing the old-fashioned way falls
        // back to the session-flash + redirect behavior below.
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success,
                'message' => $success ? "Pricing request #$request_id updated successfully!" : 'Failed to update pricing request!',
                'request_id' => (int) $request_id,
                'status' => $new_status,
                'status_label' => ucfirst($new_status),
                'final_price' => $final_price,
                'estimated_total' => (float) $request_data['estimated_total'],
                'item_prices' => $returned_item_prices,
            ]);
            exit;
        }

        if ($success) {
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

// Collect every item_id referenced across all requests so we can label
// each item (product name / quantity) and prefill any price it was
// already quoted at, instead of showing one combined price per request.
$all_item_ids = [];
foreach ($pricing_requests as $pr) {
    $decoded = json_decode($pr['selected_items'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $iid) {
            $all_item_ids[(int) $iid] = true;
        }
    }
}
$all_item_ids = array_keys($all_item_ids);

$item_info = [];       // item_id => ['product_name' => ..., 'quantity' => ...]
$existing_item_prices = []; // request_id => [item_id => quoted_price]

if (!empty($all_item_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_item_ids), '?'));
    $types = str_repeat('i', count($all_item_ids));

    $item_query = "SELECT ci.item_id, p.product_name, ci.quantity
                    FROM cart_items ci
                    JOIN products_offered p ON ci.product_id = p.id
                    WHERE ci.item_id IN ($placeholders)";
    $item_stmt = $inventory->prepare($item_query);
    $item_stmt->bind_param($types, ...$all_item_ids);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();
    while ($row = $item_result->fetch_assoc()) {
        $item_info[(int) $row['item_id']] = [
            'product_name' => $row['product_name'],
            'quantity'     => (int) $row['quantity'],
        ];
    }

    $prices_query = "SELECT pricing_request_id, cart_item_id, quoted_price
                      FROM pricing_requests_items
                      WHERE cart_item_id IN ($placeholders)";
    $prices_stmt = $inventory->prepare($prices_query);
    $prices_stmt->bind_param($types, ...$all_item_ids);
    $prices_stmt->execute();
    $prices_result = $prices_stmt->get_result();
    while ($row = $prices_result->fetch_assoc()) {
        $existing_item_prices[(int) $row['pricing_request_id']][(int) $row['cart_item_id']] = $row['quoted_price'];
    }
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
    $request_id = (int) ($_POST['request_id'] ?? 0);
    $is_ajax = isset($_POST['ajax']);

    if ($request_id > 0) {
        $query = "DELETE FROM pricing_requests WHERE id = ?";
        $stmt = $inventory->prepare($query);
        $stmt->bind_param("i", $request_id);
        $success = $stmt->execute();

        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Pricing request deleted successfully.' : ('Error deleting pricing request: ' . $stmt->error),
                'request_id' => $request_id,
            ]);
            exit;
        }

        // NOTE: this used to set $_SESSION['success_message'] / ['error_message'],
        // but the banner below only ever reads 'message' / 'error', so those
        // never actually rendered. Using the same keys as the rest of the
        // page fixes that (moot now that the delete button is AJAX-driven,
        // but kept correct for any non-JS fallback).
        if ($success) {
            $_SESSION['message'] = "Pricing request deleted successfully.";
        } else {
            $_SESSION['error'] = "Error deleting pricing request: " . $stmt->error;
        }

        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Invalid request id.']);
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
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
            vertical-align: top;
        }

        .table tbody tr:hover {
            background: var(--light);
        }

        .table th:nth-child(3),
        .table td:nth-child(3),
        .table th:nth-child(4),
        .table td:nth-child(4) {
            text-align: right;
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
            flex-direction: column;
            gap: 10px;
            min-width: 260px;
        }

        .status-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            background: var(--light);
        }

        .status-form-row {
            display: flex;
            gap: 8px;
        }

        .status-form-row .status-select {
            flex: 1;
        }

        .item-price-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 8px 10px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            background: var(--card-bg);
            max-height: 170px;
            overflow-y: auto;
        }

        .item-price-list-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--gray);
            margin-bottom: 2px;
        }

        .item-price-row {
            display: grid;
            grid-template-columns: 1fr 88px;
            align-items: center;
            gap: 8px;
        }

        .item-price-label {
            font-size: 12px;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-price-input {
            width: 100%;
            text-align: right;
        }

        .item-price-total {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
            color: var(--dark);
            padding: 2px 2px 0;
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

        .notes-input {
            width: 100%;
        }

        .status-form .update-btn {
            align-self: flex-end;
        }

        .secondary-actions {
            display: flex;
            gap: 8px;
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
            color: var(--success);
        }

        .price-decrease {
            color: var(--danger);
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
            background: var(--success-bg);
            color: var(--success);
        }

        .price-difference.decrease {
            background: var(--danger-bg);
            color: var(--danger);
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

        /* Toasts */
        .toast-stack {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10500;
        }

        .toast {
            min-width: 260px;
            max-width: 360px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 6px 20px rgba(20, 23, 31, 0.15);
            display: flex;
            align-items: flex-start;
            gap: 10px;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: var(--success-bg); color: var(--success); }
        .toast.error { background: var(--danger-bg); color: var(--danger); }

        /* Custom confirm dialog (replaces native confirm()) */
        .confirm-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(20, 23, 31, 0.45);
            z-index: 10600;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .confirm-overlay.open { display: flex; }

        .confirm-box {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 22px;
            max-width: 380px;
            width: 100%;
            box-shadow: 0 20px 45px rgba(20, 23, 31, 0.25);
            animation: slideIn 0.2s ease;
        }

        .confirm-box h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--danger);
        }

        .confirm-box p { font-size: 13px; color: var(--gray); margin-bottom: 18px; }

        .confirm-box .confirm-actions { display: flex; justify-content: flex-end; gap: 10px; }

        .confirm-box button {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .confirm-cancel-btn { background: var(--light); color: var(--dark); }
        .confirm-cancel-btn:hover { background: var(--light-gray); }
        .confirm-ok-btn { background: var(--danger); color: #fff; }
        .confirm-ok-btn:hover { opacity: 0.85; }
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
                    <i class="fas fa-check-circle"></i> <?php echo esc_html($_SESSION['message']);
                                                        unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo esc_html($_SESSION['error']);
                                                                unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card total">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <div class="stat-number" id="statTotal"><?php echo $stats['total_requests']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card pending">
                    <i class="fas fa-clock"></i>
                    <div class="stat-number" id="statPending"><?php echo $stats['pending_requests']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card completed">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number" id="statQuoted"><?php echo $stats['quoted_requests']; ?></div>
                    <div class="stat-label">Checked</div>
                </div>
                <div class="stat-card cancelled">
                    <i class="fas fa-times-circle"></i>
                    <div class="stat-number" id="statCancelled"><?php echo $stats['cancelled_requests']; ?></div>
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
                            <tr class="request-row" id="request-row-<?php echo $request['id']; ?>" data-status="<?php echo $request['status']; ?>">
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
                                    <div data-role="final-price-cell">
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
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $request['status']; ?>" data-role="status-badge">
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
                                        <form method="post" class="status-form" onsubmit="return submitPricingUpdate(event, this)" data-estimated-total="<?php echo (float) $request['estimated_total']; ?>">
<?php echo csrf_field(); ?>
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <div class="status-form-row">
                                                <select name="status" class="status-select">
                                                    <option value="pending" <?php echo $request['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="quoted" <?php echo $request['status'] == 'quoted' ? 'selected' : ''; ?>>Checked</option>
                                                    <option value="cancelled" <?php echo $request['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </div>
                                            <div class="item-price-list-label">
                                                Price per item
                                                <button type="button" title="Split the estimated total evenly across every item that doesn't have a price yet"
                                                    style="margin-left:8px;font-size:11px;padding:3px 8px;border-radius:5px;border:1px solid var(--light-gray);background:var(--primary-bg);color:var(--secondary);cursor:pointer;font-weight:600;"
                                                    onclick="fillEvenPrices(this)">
                                                    <i class="fas fa-magic"></i> Split evenly
                                                </button>
                                            </div>
                                            <div class="item-price-list">
                                                <?php foreach ($selected_items as $iid):
                                                    $iid = (int) $iid;
                                                    $info = $item_info[$iid] ?? null;
                                                    $label = $info ? $info['product_name'] . ' ×' . $info['quantity'] : ('Item #' . $iid);
                                                    $prefill = $existing_item_prices[$request['id']][$iid] ?? '';
                                                ?>
                                                    <div class="item-price-row">
                                                        <span class="item-price-label" title="<?php echo htmlspecialchars($label); ?>"><?php echo htmlspecialchars($label); ?></span>
                                                        <input type="number" name="item_price[<?php echo $iid; ?>]" class="price-input item-price-input"
                                                            data-item-id="<?php echo $iid; ?>"
                                                            placeholder="0.00" step="0.01" min="0"
                                                            value="<?php echo $prefill !== '' ? htmlspecialchars($prefill) : ''; ?>">
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="item-price-total">
                                                <span>Total</span>
                                                <span class="computed-total">₱<?php echo number_format(array_sum($existing_item_prices[$request['id']] ?? []), 2); ?></span>
                                            </div>
                                            <input type="text" name="admin_notes" class="notes-input"
                                                placeholder="Admin Notes"
                                                value="<?php echo htmlspecialchars($request['admin_notes'] ?? ''); ?>">
                                            <button type="submit" name="update_pricing_status" class="update-btn" title="Update Pricing">
                                                <i class="fas fa-sync"></i> Update
                                            </button>
                                        </form>
                                        <div class="secondary-actions">
                                            <button type="button" onclick="viewRequestDetails(<?php echo $request['id']; ?>)" class="view-details" title="View Details">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <button type="button" class="delete-btn" title="Delete Request" onclick="confirmDeleteRequest(<?php echo (int) $request['id']; ?>, this)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
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

    <div class="toast-stack" id="toastStack"></div>

    <div class="confirm-overlay" id="confirmOverlay">
        <div class="confirm-box">
            <h3><i class="fas fa-exclamation-triangle"></i> Delete this pricing request?</h3>
            <p>This can't be undone. The request and its item quotes will be permanently removed.</p>
            <div class="confirm-actions">
                <button type="button" class="confirm-cancel-btn" id="confirmCancelBtn">Cancel</button>
                <button type="button" class="confirm-ok-btn" id="confirmOkBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = <?php echo esc_js(csrf_token()); ?>;

        // ---------- Toasts ----------
        function showToast(message, type) {
            const stack = document.getElementById('toastStack');
            const toast = document.createElement('div');
            toast.className = 'toast ' + (type === 'error' ? 'error' : 'success');
            const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
            toast.innerHTML = '<i class="fas ' + icon + '"></i><span></span>';
            toast.querySelector('span').textContent = message;
            stack.appendChild(toast);
            requestAnimationFrame(() => toast.classList.add('show'));
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 250);
            }, 4000);
        }

        // ---------- Custom confirm dialog (Promise-based, replaces confirm()) ----------
        function askConfirm() {
            return new Promise((resolve) => {
                const overlay = document.getElementById('confirmOverlay');
                const okBtn = document.getElementById('confirmOkBtn');
                const cancelBtn = document.getElementById('confirmCancelBtn');
                overlay.classList.add('open');

                function cleanup(result) {
                    overlay.classList.remove('open');
                    okBtn.removeEventListener('click', onOk);
                    cancelBtn.removeEventListener('click', onCancel);
                    overlay.removeEventListener('click', onOverlay);
                    resolve(result);
                }
                function onOk() { cleanup(true); }
                function onCancel() { cleanup(false); }
                function onOverlay(e) { if (e.target === overlay) cleanup(false); }

                okBtn.addEventListener('click', onOk);
                cancelBtn.addEventListener('click', onCancel);
                overlay.addEventListener('click', onOverlay);
            });
        }

        // ---------- Delete (AJAX, no reload) ----------
        async function confirmDeleteRequest(requestId, btn) {
            const ok = await askConfirm();
            if (!ok) return;

            btn.disabled = true;
            try {
                const body = new URLSearchParams({
                    ajax: '1',
                    delete_request: '1',
                    csrf_token: CSRF_TOKEN,
                    request_id: requestId
                });
                const res = await fetch('admin_pricing_estimates.php', { method: 'POST', body });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    const row = document.getElementById('request-row-' + requestId);
                    if (row) {
                        const status = row.dataset.status;
                        row.style.transition = 'opacity 0.2s ease';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 200);
                        adjustStatCounts(status, null);
                    }
                } else {
                    showToast(data.message || 'Failed to delete pricing request.', 'error');
                    btn.disabled = false;
                }
            } catch (e) {
                showToast('Network error while deleting the request.', 'error');
                btn.disabled = false;
            }
        }

        // ---------- Status / pricing update (AJAX, no reload) ----------
        async function submitPricingUpdate(event, form) {
            event.preventDefault();
            const btn = form.querySelector('.update-btn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Updating...';

            const requestId = form.querySelector('[name="request_id"]').value;
            const formData = new FormData(form);
            formData.set('ajax', '1');
            formData.set('update_pricing_status', '1');

            try {
                const res = await fetch('admin_pricing_estimates.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    const row = document.getElementById('request-row-' + requestId);
                    const previousStatus = row ? row.dataset.status : null;
                    applyPricingUpdateToRow(row, form, data);
                    adjustStatCounts(previousStatus, data.status);
                } else {
                    showToast(data.message || 'Failed to update pricing request.', 'error');
                }
            } catch (e) {
                showToast('Network error while updating the request.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
            return false;
        }

        function applyPricingUpdateToRow(row, form, data) {
            if (!row) return;
            row.dataset.status = data.status;

            const badge = row.querySelector('[data-role="status-badge"]');
            if (badge) {
                badge.className = 'status-badge status-' + data.status;
                badge.textContent = data.status_label;
            }

            // Sync each price input to whatever was actually saved (covers the
            // even-split fallback the server applies when a request is first
            // quoted with no explicit per-item price).
            if (data.item_prices) {
                Object.entries(data.item_prices).forEach(([itemId, price]) => {
                    const input = form.querySelector('.item-price-input[data-item-id="' + itemId + '"]');
                    if (input && price !== null && input.value === '') {
                        input.value = Number(price).toFixed(2);
                    }
                });
            }
            const totalEl = form.querySelector('.computed-total');
            if (totalEl) {
                totalEl.textContent = '₱' + Number(data.final_price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            const priceCell = row.querySelector('[data-role="final-price-cell"]');
            if (priceCell) {
                const finalPrice = Number(data.final_price);
                const estimatedTotal = Number(data.estimated_total);
                if (finalPrice > 0) {
                    const difference = finalPrice - estimatedTotal;
                    const percentage = estimatedTotal > 0 ? (difference / estimatedTotal) * 100 : 0;
                    let comparisonHtml;
                    if (difference > 0) {
                        comparisonHtml = '<small class="price-increase">+₱' + Math.abs(difference).toFixed(2) + ' (' + Math.abs(percentage).toFixed(1) + '%)</small>';
                    } else if (difference < 0) {
                        comparisonHtml = '<small class="price-decrease">-₱' + Math.abs(difference).toFixed(2) + ' (' + Math.abs(percentage).toFixed(1) + '%)</small>';
                    } else {
                        comparisonHtml = '<small class="price-same">No change</small>';
                    }
                    priceCell.innerHTML = '<strong>₱' + finalPrice.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong>' +
                        '<div class="price-comparison">' + comparisonHtml + '</div>';
                } else {
                    priceCell.innerHTML = '<span style="color: var(--gray);">Not set</span>';
                }
            }
        }

        // ---------- "Split evenly" quick action ----------
        function fillEvenPrices(btn) {
            const form = btn.closest('form');
            const inputs = Array.from(form.querySelectorAll('.item-price-input'));
            const empty = inputs.filter((i) => i.value.trim() === '');
            const targets = empty.length > 0 ? empty : inputs; // nothing empty? split across all of them
            const estimatedTotal = parseFloat(form.dataset.estimatedTotal) || 0;
            const share = targets.length > 0 ? estimatedTotal / targets.length : 0;

            targets.forEach((input) => {
                input.value = share.toFixed(2);
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        // ---------- Stat card counters (best-effort local sync, matches server on next reload) ----------
        function adjustStatCounts(fromStatus, toStatus) {
            const ids = { pending: 'statPending', quoted: 'statQuoted', cancelled: 'statCancelled' };
            function bump(status, delta) {
                if (!status || !ids[status]) return;
                const el = document.getElementById(ids[status]);
                if (el) el.textContent = Math.max(0, parseInt(el.textContent, 10) + delta);
            }

            if (toStatus === null) {
                // Deletion: one row leaves its current bucket and the total.
                bump(fromStatus, -1);
                const totalEl = document.getElementById('statTotal');
                if (totalEl) totalEl.textContent = Math.max(0, parseInt(totalEl.textContent, 10) - 1);
                return;
            }

            if (fromStatus !== toStatus) {
                bump(fromStatus, -1);
                bump(toStatus, 1);
            }
        }

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

        // As soon as the admin fills in a price for any item, flip that
        // request's status to "Checked" automatically (only while it's
        // still "Pending", so it never overrides a status picked on
        // purpose, e.g. Cancelled). Also keeps the running total in sync.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.status-form').forEach(function (form) {
                const priceInputs = form.querySelectorAll('.item-price-input');
                const statusSelect = form.querySelector('.status-select');
                const totalEl = form.querySelector('.computed-total');

                function refresh() {
                    let total = 0;
                    let hasPrice = false;
                    priceInputs.forEach(function (i) {
                        const val = parseFloat(i.value);
                        if (!isNaN(val) && val > 0) {
                            total += val;
                            hasPrice = true;
                        }
                    });

                    if (totalEl) {
                        totalEl.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }

                    if (hasPrice && statusSelect && statusSelect.value === 'pending') {
                        statusSelect.value = 'quoted';
                    }
                }

                priceInputs.forEach(function (input) {
                    input.addEventListener('input', refresh);
                });
            });
        });
    </script>
</body>

</html>