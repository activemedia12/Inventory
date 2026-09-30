<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../permissions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee', 'super_admin'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// CSRF protection: every POST on this page must carry this session's token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

// Handle pricing request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_pricing_status'])) {
        if (!can('web_pricing')) {
            $msg = permission_denied_message('web_pricing');
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $msg]);
            } else {
                $_SESSION['error'] = $msg;
                header("Location: " . $_SERVER['PHP_SELF']);
            }
            exit;
        }
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

    if (!can('web_delete')) {
        $msg = permission_denied_message('web_delete');
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $msg]);
        } else {
            $_SESSION['error'] = $msg;
            header("Location: " . $_SERVER['PHP_SELF']);
        }
        exit;
    }

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
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-pricing" data-page="pricing">
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Pricing Consultation Management</h1>
            </div>

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
                <button type="button" class="search-btn" onclick="filterRequests()">
                    <i class="fas fa-search"></i> Filter
                </button>
                <button type="button" class="search-btn secondary" onclick="clearFilters()">
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
                                        <small class="text-muted"><?php echo htmlspecialchars($request['username']); ?></small>
                                        <br>
                                    </div>
                                </td>
                                <td>
                                    <strong>₱<?php echo number_format($request['estimated_total'], 2); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo count($selected_items); ?> items</small>
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
                                            <span class="text-muted">Not set</span>
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
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($request['request_date'])); ?></small>
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
        <div class="modal-content modal-xl professional-modal-container">
            <button type="button" class="modal-close" onclick="closeModal('requestModal')" aria-label="Close">&times;</button>
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


    <?php
    ?>
    <script>
        window.WA_CONFIG = {
            csrfToken: <?php echo esc_js(csrf_token()); ?>,
            flash: {
                message: <?php echo isset($_SESSION['message']) ? esc_js($_SESSION['message']) : 'null'; ?>,
                error: <?php echo isset($_SESSION['error']) ? esc_js($_SESSION['error']) : 'null'; ?>
            }
        };
        <?php unset($_SESSION['message'], $_SESSION['error']); ?>
    </script>
    <script src="../../assets/js/website_admin.js"></script>

</body>

</html>