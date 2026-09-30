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

$VALID_STATUSES = ['pending', 'paid', 'processing', 'ready_for_pickup', 'completed', 'cancelled'];
$STATUS_LABELS = [
    'pending' => 'Pending',
    'paid' => 'Paid',
    'processing' => 'Processing',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

/**
 * Bind a dynamic list of params to a mysqli statement (bind_param needs refs).
 */
function bind_dynamic(mysqli_stmt $stmt, string $types, array $params): void
{
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

/**
 * Render the customization detail lines for a single order item (ported from
 * admin_order_details.php) into the currently-open output buffer.
 */
function render_item_customization(array $item): void
{
    $category = $item['product_category'];
    $product_name = $item['product_name'];

    if ($item['size_option']) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts':
                    echo '<div>T-Shirt Size: ' . htmlspecialchars($item['tshirt_size_name'] ?? $item['size_option']) . '</div>';
                    break;
                case 'Tote Bag':
                    echo '<div>Tote Bag Size: ' . htmlspecialchars($item['tote_size_name'] ?? $item['size_option']) . '</div>';
                    break;
                case 'Paper Bag':
                    $dimensions = $item['paperbag_dimensions'] ?? '';
                    echo '<div>Paper Bag Size: ' . htmlspecialchars($item['paperbag_size_name'] ?? $item['size_option']);
                    if ($dimensions) echo ' (' . htmlspecialchars($dimensions) . ')';
                    echo '</div>';
                    break;
                case 'Mug':
                    echo '<div>Mug Size: ' . htmlspecialchars($item['mug_size_name'] ?? $item['size_option']) . '</div>';
                    break;
                default:
                    echo '<div>Size: ' . htmlspecialchars($item['size_option']) . '</div>';
            }
        } else {
            echo '<div>Size: ' . htmlspecialchars($item['size_option']) . '</div>';
        }
        if ($item['custom_size']) {
            echo '<div>Custom Size: ' . htmlspecialchars($item['custom_size']) . '</div>';
        }
    }

    if ($item['color_option']) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts':
                    echo '<div>T-Shirt Color: ' . htmlspecialchars($item['tshirt_color_name'] ?? $item['color_option']) . '</div>';
                    break;
                case 'Tote Bag':
                    echo '<div>Tote Bag Color: ' . htmlspecialchars($item['tote_color_name'] ?? $item['color_option']) . '</div>';
                    break;
                case 'Mug':
                    echo '<div>Mug Color: ' . htmlspecialchars($item['mug_color_name'] ?? $item['color_option']) . '</div>';
                    break;
                case 'Paper Bag':
                    echo '<div>Color: Brown</div>';
                    break;
                default:
                    echo '<div>Color: ' . htmlspecialchars($item['color_option']) . '</div>';
            }
        } else {
            echo '<div>Color: ' . htmlspecialchars($item['color_option']) . '</div>';
        }
        if ($item['custom_color']) {
            echo '<div>Custom Color: ' . htmlspecialchars($item['custom_color']) . '</div>';
        }
    }

    if ($category !== 'Other Services') {
        if ($item['finish_option_name']) echo '<div>Finish: ' . htmlspecialchars($item['finish_option_name']) . '</div>';
        if ($item['paper_option_name']) echo '<div>Paper: ' . htmlspecialchars($item['paper_option_name']) . '</div>';
        if ($item['binding_option_name']) echo '<div>Binding: ' . htmlspecialchars($item['binding_option_name']) . '</div>';
        if ($item['layout_option_name']) echo '<div>Layout: ' . htmlspecialchars($item['layout_option_name']) . '</div>';
        if ($item['layout_details']) echo '<div>Layout Details: ' . htmlspecialchars($item['layout_details']) . '</div>';
        if ($item['gsm_option']) echo '<div>GSM: ' . htmlspecialchars($item['gsm_option']) . '</div>';
    }
}

/**
 * Render one design-file preview <div>, or a "file not found" placeholder.
 */
/**
 * Normalizes the "original design file(s)" value for one side into a plain
 * list of path strings. Accepts the new array shape (front_uploaded_files /
 * back_uploaded_files, written by save_design.php going forward) as well as
 * the old single-string shape (front_uploaded_file / back_uploaded_file)
 * still sitting in orders placed before this change. Anything else (null,
 * empty string, unexpected type) becomes an empty list.
 */
function normalize_design_file_list($value): array
{
    if (is_array($value)) {
        return array_values(array_filter($value, static function ($item) {
            return is_string($item) && $item !== '';
        }));
    }
    if (is_string($value) && $value !== '') {
        return [$value];
    }
    return [];
}

function render_design_preview(string $file, string $label, string $accentVar): void
{
    $path = "../../assets/uploads/" . $file;
    $exists = file_exists($path);
    echo '<div class="design-preview">';
    if ($exists) {
        echo '<a href="' . htmlspecialchars($path) . '" download="' . htmlspecialchars(basename($file)) . '" style="text-decoration:none;">';
        echo '<img src="' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($label) . '">';
        echo '<div class="design-label">' . htmlspecialchars($label) . '</div>';
        echo '</a>';
    } else {
        echo '<div style="width:80px;height:80px;background:var(--light-gray);display:flex;align-items:center;justify-content:center;border-radius:8px;border:2px dashed ' . $accentVar . ';">';
        echo '<i class="fas fa-file-image" style="font-size:20px;color:var(--gray);"></i></div>';
        echo '<div class="design-label">File not found</div>';
        echo '<small style="color:var(--gray);font-size:0.6em;">' . htmlspecialchars($file) . '</small>';
    }
    echo '</div>';
}

/**
 * Build the full slide-over panel body HTML for one order (ported from
 * admin_order_details.php, condensed for the panel context).
 */
function render_order_panel_html(mysqli $inventory, int $order_id, array $STATUS_LABELS): string
{
    $query = "SELECT o.*, u.username
              FROM orders o
              JOIN users u ON o.user_id = u.id
              WHERE o.order_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        return '';
    }

    $query = "SELECT oi.*,
                     po.option_name as paper_option_name,
                     fo.option_name as finish_option_name,
                     bo.option_name as binding_option_name,
                     lo.option_name as layout_option_name,
                     ts.size_name as tshirt_size_name,
                     tc.color_name as tshirt_color_name,
                     tos.size_name as tote_size_name,
                     toc.color_name as tote_color_name,
                     pbs.size_name as paperbag_size_name,
                     pbs.dimensions as paperbag_dimensions,
                     ms.size_name as mug_size_name,
                     mc.color_name as mug_color_name
              FROM order_items oi
              LEFT JOIN paper_options po ON oi.paper_option = po.id
              LEFT JOIN finish_options fo ON oi.finish_option = fo.id
              LEFT JOIN binding_options bo ON oi.binding_option = bo.id
              LEFT JOIN layout_options lo ON oi.layout_option = lo.id
              LEFT JOIN tshirt_sizes ts ON (oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' AND oi.size_option = ts.id)
              LEFT JOIN tshirt_colors tc ON (oi.product_category = 'Other Services' AND oi.product_name = 'T-Shirts' AND oi.color_option = tc.id)
              LEFT JOIN totesize_options tos ON (oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' AND oi.size_option = tos.id)
              LEFT JOIN totecolor_options toc ON (oi.product_category = 'Other Services' AND oi.product_name = 'Tote Bag' AND oi.color_option = toc.id)
              LEFT JOIN paperbag_size_options pbs ON (oi.product_category = 'Other Services' AND oi.product_name = 'Paper Bag' AND oi.size_option = pbs.id)
              LEFT JOIN mug_size_options ms ON (oi.product_category = 'Other Services' AND oi.product_name = 'Mug' AND oi.size_option = ms.id)
              LEFT JOIN mug_color_options mc ON (oi.product_category = 'Other Services' AND oi.product_name = 'Mug' AND oi.color_option = mc.id)
              WHERE oi.order_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    ob_start();
    ?>
    <div class="panel-section">
        <h3><i class="fas fa-user"></i> Customer Information</h3>
        <div class="detail-row">
            <span class="detail-label">Username:</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['username']); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">User ID:</span>
            <span class="detail-value"><?php echo (int) $order['user_id']; ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Order Date:</span>
            <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></span>
        </div>
    </div>

    <div class="panel-section">
        <h3><i class="fas fa-receipt"></i> Order Summary</h3>
        <div class="detail-row">
            <span class="detail-label">Total Amount:</span>
            <span class="detail-value" style="font-weight:bold;font-size:1.15em;color:var(--success);">
                &#8369;<?php echo number_format($order['total_amount'], 2); ?>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Proof:</span>
            <span class="detail-value">
                <?php if ($order['payment_proof']):
                    $proof_path = payment_proof_url((int) $order['order_id']);
                    if (payment_proof_path((int) $order['user_id'], (string) $order['payment_proof'])): ?>
                        <a href="<?php echo esc_html($proof_path); ?>" target="_blank" style="color:var(--primary);">
                            <i class="fas fa-external-link-alt"></i> View Payment Proof
                        </a>
                    <?php else: ?>
                        <span style="color:var(--danger);">File not found</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:var(--gray);">No payment proof uploaded</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <div class="panel-section">
        <h3><i class="fas fa-boxes"></i> Order Items</h3>
        <?php foreach ($order_items as $item): ?>
            <div class="item-details">
                <div class="detail-row">
                    <span class="detail-label">Product:</span>
                    <span class="detail-value" style="font-weight:bold;"><?php echo htmlspecialchars($item['product_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Category:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($item['product_category']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Quantity:</span>
                    <span class="detail-value"><?php echo (int) $item['quantity']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Unit Price:</span>
                    <span class="detail-value">&#8369;<?php echo number_format($item['unit_price'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Subtotal:</span>
                    <span class="detail-value" style="font-weight:bold;">&#8369;<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></span>
                </div>

                <?php if ($item['size_option'] || $item['color_option'] || $item['finish_option'] || $item['paper_option'] || $item['binding_option'] || $item['layout_option'] || $item['gsm_option']): ?>
                    <div style="margin-top:10px;">
                        <strong>Customization:</strong>
                        <div style="margin-left:20px;margin-top:5px;">
                            <?php render_item_customization($item); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($item['user_layout_files'])):
                    $layout_files = json_decode($item['user_layout_files'], true); ?>
                    <div style="margin-top:15px;padding-top:15px;border-top:2px dashed var(--warning);">
                        <div style="font-weight:bold;margin-bottom:10px;color:var(--warning);font-size:1em;">
                            <i class="fas fa-file-upload"></i> User Layout Files
                        </div>
                        <?php if (is_array($layout_files)):
                            foreach ($layout_files as $file_path):
                                $clean_path = str_replace('../../', '', $file_path);
                                $full_path = "../../" . $clean_path;
                                $file_exists = file_exists($full_path);
                        ?>
                            <div class="user-layout-file">
                                <div>
                                    <?php if ($file_exists): ?>
                                        <a href="<?php echo htmlspecialchars($full_path); ?>" download="<?php echo htmlspecialchars(basename($clean_path)); ?>" target="_blank">
                                            <i class="fas fa-download"></i> <?php echo htmlspecialchars(basename($clean_path)); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--danger);">
                                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars(basename($clean_path)); ?> (File not found)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="file-path"><?php echo htmlspecialchars($clean_path); ?></div>
                            </div>
                        <?php endforeach;
                        else: ?>
                            <div style="color:var(--gray);font-style:italic;">No valid layout files found</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($item['design_image'])):
                    $designData = $item['design_image'];
                    $frontMockup = $backMockup = $uploadedFile = '';
                    // Originals are a list per side now, but orders placed before this
                    // change stored a single string under front_uploaded_file /
                    // back_uploaded_file - normalize both shapes into a plain array.
                    $frontUploadedFiles = $backUploadedFiles = [];
                    $designArray = json_decode($designData, true);

                    if (!(json_last_error() === JSON_ERROR_NONE && is_array($designArray)) && preg_match('/\{.*\}/', $designData)) {
                        $fixedJson = stripslashes(str_replace('\"', '"', $designData));
                        $designArray = json_decode($fixedJson, true);
                    }

                    if (is_array($designArray)) {
                        $frontMockup = $designArray['front_mockup'] ?? '';
                        $backMockup = $designArray['back_mockup'] ?? '';
                        $uploadedFile = $designArray['uploaded_file'] ?? '';
                        $frontUploadedFiles = normalize_design_file_list($designArray['front_uploaded_files'] ?? ($designArray['front_uploaded_file'] ?? null));
                        $backUploadedFiles = normalize_design_file_list($designArray['back_uploaded_files'] ?? ($designArray['back_uploaded_file'] ?? null));
                    } else {
                        $uploadedFile = $designData;
                    }

                    $hasDesigns = $frontMockup || $backMockup || $uploadedFile || $frontUploadedFiles || $backUploadedFiles;
                    if ($hasDesigns): ?>
                        <div style="margin-top:15px;padding-top:15px;border-top:2px dashed var(--primary);">
                            <div style="font-weight:bold;margin-bottom:10px;color:var(--primary);font-size:1em;">
                                <i class="fas fa-palette"></i> Custom Design Files
                            </div>
                            <div class="design-previews">
                                <?php
                                if ($uploadedFile) render_design_preview($uploadedFile, 'Original File', 'var(--light-gray)');
                                foreach ($frontUploadedFiles as $i => $file) {
                                    $label = count($frontUploadedFiles) > 1 ? 'Front Original ' . ($i + 1) : 'Front Original';
                                    render_design_preview($file, $label, 'var(--light-gray)');
                                }
                                foreach ($backUploadedFiles as $i => $file) {
                                    $label = count($backUploadedFiles) > 1 ? 'Back Original ' . ($i + 1) : 'Back Original';
                                    render_design_preview($file, $label, 'var(--light-gray)');
                                }
                                if ($frontMockup) render_design_preview($frontMockup, 'Front Mockup', 'var(--primary)');
                                if ($backMockup) render_design_preview($backMockup, 'Back Mockup', 'var(--primary)');
                                ?>
                            </div>
                        </div>
                    <?php endif;
                endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------
// AJAX endpoints (same-file `?ajax=` dispatch, matching admin_customers.php
// / admin_products.php conventions elsewhere in this app).
// ---------------------------------------------------------------------
if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['ajax'] === 'get_order_details') {
    header('Content-Type: application/json; charset=utf-8');
    $order_id = (int) ($_GET['id'] ?? 0);
    $html = $order_id ? render_order_panel_html($inventory, $order_id, $STATUS_LABELS) : '';
    if ($html === '') {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    $stmt = $inventory->prepare("SELECT status, cancellation_reason FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'status' => $row['status'],
        'cancellation_reason' => $row['cancellation_reason'],
        'html' => $html,
    ]);
    exit;
}

if (isset($_POST['ajax']) && $_POST['ajax'] === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('web_orders')) {
        echo json_encode(['success' => false, 'message' => permission_denied_message('web_orders')]);
        exit;
    }
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $cancel_reason = trim($_POST['cancel_reason'] ?? '');

    if (!$order_id || !in_array($new_status, $VALID_STATUSES, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    if ($new_status === 'cancelled' && $cancel_reason === '') {
        echo json_encode(['success' => false, 'message' => "Please provide a reason for cancelling order #$order_id."]);
        exit;
    }

    if ($new_status === 'cancelled') {
        $stmt = $inventory->prepare("UPDATE orders SET status = ?, cancellation_reason = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->bind_param("ssi", $new_status, $cancel_reason, $order_id);
    } else {
        $stmt = $inventory->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => "Order #$order_id status updated to " . $STATUS_LABELS[$new_status] . ".",
            'order_id' => $order_id,
            'status' => $new_status,
            'status_label' => $STATUS_LABELS[$new_status],
            'cancellation_reason' => $new_status === 'cancelled' ? $cancel_reason : null,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order status.']);
    }
    exit;
}

// ---------------------------------------------------------------------
// Normal page load: filters, pagination, listing query.
// ---------------------------------------------------------------------
$status = $_GET['status'] ?? '';
if (!in_array($status, $VALID_STATUSES, true)) {
    $status = '';
}
$search = trim($_GET['search'] ?? '');
$per_page = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = [];
$params = [];
$types = '';

if ($status !== '') {
    $where[] = "o.status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($search !== '') {
    $where[] = "(o.order_id LIKE ? OR u.username LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$count_query = "SELECT COUNT(*) AS total FROM orders o JOIN users u ON o.user_id = u.id $where_sql";
$stmt = $inventory->prepare($count_query);
if ($types !== '') bind_dynamic($stmt, $types, $params);
$stmt->execute();
$total_orders = (int) $stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int) ceil($total_orders / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$list_query = "SELECT o.*, u.username
               FROM orders o
               JOIN users u ON o.user_id = u.id
               $where_sql
               ORDER BY o.created_at DESC
               LIMIT ? OFFSET ?";
$list_params = $params;
$list_types = $types . 'ii';
$list_params[] = $per_page;
$list_params[] = $offset;
$stmt = $inventory->prepare($list_query);
bind_dynamic($stmt, $list_types, $list_params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Status tab counts (unfiltered by search, so counts stay stable while typing).
$tab_counts = ['all' => 0, 'pending' => 0, 'processing' => 0, 'completed' => 0];
$count_by_status = $inventory->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
while ($row = $count_by_status->fetch_assoc()) {
    $tab_counts['all'] += (int) $row['c'];
    if (isset($tab_counts[$row['status']])) {
        $tab_counts[$row['status']] = (int) $row['c'];
    }
}

function build_query_url(array $overrides = []): string
{
    $current = ['status' => $_GET['status'] ?? '', 'search' => $_GET['search'] ?? '', 'page' => $_GET['page'] ?? ''];
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return 'admin_orders.php' . ($merged ? ('?' . http_build_query($merged)) : '');
}

$open_id = isset($_GET['open']) ? (int) $_GET['open'] : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-orders" data-page="orders">
    <div class="admin-container">
        <div class="main-content">
            <div class="header">
                <h1>Order Management</h1>
            </div>

            <!-- Status Tabs -->
            <div class="tab-bar">
                <a href="<?php echo build_query_url(['status' => '', 'page' => '']); ?>" class="tab-link <?php echo $status === '' ? 'active' : ''; ?>">
                    All <span class="tab-count"><?php echo $tab_counts['all']; ?></span>
                </a>
                <a href="<?php echo build_query_url(['status' => 'pending', 'page' => '']); ?>" class="tab-link <?php echo $status === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="tab-count"><?php echo $tab_counts['pending']; ?></span>
                </a>
                <a href="<?php echo build_query_url(['status' => 'processing', 'page' => '']); ?>" class="tab-link <?php echo $status === 'processing' ? 'active' : ''; ?>">
                    Processing <span class="tab-count"><?php echo $tab_counts['processing']; ?></span>
                </a>
                <a href="<?php echo build_query_url(['status' => 'completed', 'page' => '']); ?>" class="tab-link <?php echo $status === 'completed' ? 'active' : ''; ?>">
                    Completed <span class="tab-count"><?php echo $tab_counts['completed']; ?></span>
                </a>
            </div>

            <!-- Search and Filter -->
            <form class="search-filter" method="get" action="admin_orders.php">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by order ID or customer..." style="min-width: 250px;">
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($STATUS_LABELS as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $status === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> Filter</button>
                <a href="admin_orders.php" class="search-btn secondary"><i class="fas fa-times"></i> Clear</a>
            </form>

            <!-- Orders Table -->
            <div class="order-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Proof</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTable">
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7" class="empty-cell">No orders match this view.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <tr class="order-row" id="order-row-<?php echo $order['order_id']; ?>" data-status="<?php echo $order['status']; ?>">
                                <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['username']); ?></strong>
                                        <br><small class="text-muted">User ID: <?php echo $order['user_id']; ?></small>
                                    </div>
                                </td>
                                <td><strong>&#8369;<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>" data-role="status-badge"
                                        <?php if ($order['status'] === 'cancelled' && !empty($order['cancellation_reason'])): ?>
                                            title="Reason: <?php echo esc_html($order['cancellation_reason']); ?>"
                                        <?php endif; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['payment_proof']):
                                        $proof_path = payment_proof_url((int) $order['order_id']);
                                        if (payment_proof_path((int) $order['user_id'], (string) $order['payment_proof'])): ?>
                                            <a href="<?php echo esc_html($proof_path); ?>" target="_blank">
                                                <img src="<?php echo esc_html($proof_path); ?>" alt="Payment Proof" class="proof-image">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">File not found</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No proof</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    <br><small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="order-actions" data-order-id="<?php echo $order['order_id']; ?>">
                                        <select class="status-select" data-role="status-select" onchange="onStatusSelectChange(this)">
                                            <?php foreach ($STATUS_LABELS as $val => $label): ?>
                                                <option value="<?php echo $val; ?>" <?php echo $order['status'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" class="cancel-reason-input" data-role="cancel-reason"
                                            placeholder="Reason for cancellation"
                                            value="<?php echo $order['status'] === 'cancelled' ? esc_html($order['cancellation_reason'] ?? '') : ''; ?>"
                                            style="display: <?php echo $order['status'] === 'cancelled' ? 'inline-block' : 'none'; ?>;">
                                        <button type="button" class="update-btn" data-role="update-btn" onclick="submitStatusUpdate(<?php echo (int) $order['order_id']; ?>, this)">
                                            <i class="fas fa-sync"></i> Update
                                        </button>
                                        <button type="button" class="view-details" onclick="openOrderPanel(<?php echo (int) $order['order_id']; ?>)">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <span class="pagination-summary">Showing <?php echo count($orders); ?> of <?php echo $total_orders; ?> orders</span>
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-controls">
                        <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page - 1]); ?>"><i class="fas fa-chevron-left"></i> Prev</a>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <a class="page-link <?php echo $p === $page ? 'active' : ''; ?>" href="<?php echo build_query_url(['page' => $p]); ?>"><?php echo $p; ?></a>
                        <?php endfor; ?>
                        <a class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page + 1]); ?>">Next <i class="fas fa-chevron-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Slide-over panel -->
    <div class="panel-overlay" id="panelOverlay" onclick="closeOrderPanel()"></div>
    <div class="slide-panel" id="slidePanel">
        <div class="panel-header">
            <h2 id="panelTitle">Order Details</h2>
            <button type="button" class="panel-close" onclick="closeOrderPanel()" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="panel-status-bar" id="panelStatusBar"></div>
        <div class="panel-body" id="panelBody">
            <div class="panel-loading"><i class="fas fa-circle-notch"></i> Loading order...</div>
        </div>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite"></div>

    <?php
    $wa_data = [
        'statusLabels' => $STATUS_LABELS,
        'openId' => (int) $open_id,
    ];
    ?>
    <script>
        window.WA_CONFIG = {
            csrfToken: <?php echo esc_js(csrf_token()); ?>,
            data: <?php echo json_encode($wa_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        };
    </script>
    <script src="../../assets/js/website_admin.js"></script>

</body>

</html>