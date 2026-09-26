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

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #cbced3; border-radius: 8px; }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        body { background-color: var(--light); color: var(--dark); line-height: 1.5; -webkit-font-smoothing: antialiased; }

        .admin-container { display: flex; min-height: 100vh; }

        .main-content { flex: 1; padding: 28px 32px; background: var(--light); padding-bottom: 90px; }

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

        .header h1 { color: var(--dark); font-size: 22px; margin: 0; font-weight: 600; }

        /* Status tabs */
        .tab-bar {
            display: flex;
            gap: 4px;
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 6px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            flex-wrap: wrap;
        }

        .tab-link {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--gray);
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .tab-link:hover { background: var(--light); color: var(--dark); }

        .tab-link.active { background: var(--primary); color: #fff; }

        .tab-count {
            font-size: 11px;
            font-weight: 700;
            background: rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 1px 7px;
        }

        .tab-link.active .tab-count { background: rgba(255, 255, 255, 0.25); }

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

        .search-filter input, .search-filter select {
            padding: 9px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            color: var(--dark);
            background: var(--card-bg);
        }

        .search-filter input:focus, .search-filter select:focus { outline: none; border-color: var(--primary); }

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
            text-decoration: none;
        }

        .search-btn:hover { background: var(--secondary); }
        .search-btn.secondary { background: var(--gray); }

        /* Orders Table */
        .order-table {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
        }

        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid var(--light-gray); font-size: 13px; }
        .table th { background: var(--light); font-weight: 600; color: var(--gray); font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
        .table tbody tr:last-child td { border-bottom: none; }

        .order-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        .status-select {
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            background: var(--card-bg);
            font-size: 12px;
            color: var(--dark);
        }

        .status-select:focus { outline: none; border-color: var(--primary); }

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

        .update-btn:hover { opacity: 0.8; }
        .update-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .view-details {
            background: var(--success-bg);
            color: var(--success);
            padding: 7px 12px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: opacity 0.15s ease;
            cursor: pointer;
        }

        .view-details:hover { opacity: 0.8; }

        .proof-image {
            width: 44px; height: 44px; object-fit: cover; border-radius: 6px;
            border: 1px solid var(--light-gray); cursor: pointer; transition: transform 0.15s ease;
        }
        .proof-image:hover { transform: scale(2); }

        .status-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pending { background: var(--warning-bg); color: var(--warning); }
        .status-paid { background: var(--info-bg); color: var(--info); }
        .status-processing { background: var(--success-bg); color: var(--success); }
        .status-ready_for_pickup { background: var(--primary-bg); color: var(--secondary); }
        .status-completed { background: var(--success-bg); color: var(--success); }
        .status-cancelled { background: var(--danger-bg); color: var(--danger); }

        .cancel-reason-input {
            padding: 7px 10px; border-radius: 6px; border: 1px solid var(--light-gray);
            background: var(--card-bg); font-size: 12px; color: var(--dark); min-width: 160px;
        }
        .cancel-reason-input:focus { outline: none; border-color: var(--danger); }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .page-link {
            min-width: 34px;
            text-align: center;
            padding: 7px 10px;
            border-radius: 6px;
            border: 1px solid var(--light-gray);
            background: var(--card-bg);
            color: var(--dark);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .page-link:hover { background: var(--light); }
        .page-link.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .page-link.disabled { opacity: 0.4; pointer-events: none; }
        .page-info { font-size: 12px; color: var(--gray); margin-top: 10px; text-align: center; }

        /* Slide-over panel */
        .panel-overlay {
            position: fixed; inset: 0; background: rgba(20, 23, 31, 0.45);
            opacity: 0; pointer-events: none; transition: opacity 0.2s ease; z-index: 100;
        }
        .panel-overlay.open { opacity: 1; pointer-events: auto; }

        .slide-panel {
            position: fixed; top: 0; right: 0; height: 100%;
            width: min(560px, 100%);
            background: var(--card-bg);
            box-shadow: -4px 0 24px rgba(20, 23, 31, 0.15);
            transform: translateX(100%);
            transition: transform 0.25s ease;
            z-index: 101;
            display: flex;
            flex-direction: column;
        }
        .slide-panel.open { transform: translateX(0); }

        .panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .panel-header h2 { font-size: 17px; font-weight: 600; }
        .panel-close {
            background: var(--light); border: none; width: 32px; height: 32px; border-radius: 6px;
            cursor: pointer; color: var(--gray); font-size: 14px;
        }
        .panel-close:hover { background: var(--light-gray); }

        .panel-status-bar {
            padding: 14px 22px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            flex-shrink: 0;
            background: var(--light);
        }

        .panel-body { padding: 22px; overflow-y: auto; flex: 1; }
        .panel-body .panel-section { margin-bottom: 22px; padding-bottom: 18px; border-bottom: 1px solid var(--light-gray); }
        .panel-body .panel-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .panel-body .panel-section h3 { font-size: 14px; font-weight: 600; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; }
        .panel-body .panel-section h3 i { color: var(--gray); }

        .detail-row { display: flex; margin-bottom: 10px; align-items: flex-start; font-size: 13px; }
        .detail-label { font-weight: 600; color: var(--gray); min-width: 140px; flex-shrink: 0; }
        .detail-value { color: var(--dark); flex: 1; }

        .item-details { background: var(--light); padding: 16px; border-radius: 8px; margin-bottom: 14px; }

        .design-previews { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 10px; }
        .design-preview { text-align: center; flex: 0 0 auto; }
        .design-preview img {
            width: 64px; height: 64px; object-fit: contain; border-radius: 6px;
            border: 1px solid var(--light-gray); padding: 4px; background: var(--card-bg);
        }
        .design-label { font-size: 10px; color: var(--gray); margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
        .design-preview small { display: block; margin-top: 2px; color: var(--gray); font-size: 10px; max-width: 72px; word-break: break-all; }

        .user-layout-file {
            background: var(--warning-bg); padding: 10px 12px; border-radius: 6px; margin-bottom: 8px;
            display: flex; justify-content: space-between; align-items: center; font-size: 12px; gap: 10px;
        }
        .user-layout-file a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .user-layout-file a:hover { text-decoration: underline; }
        .file-path { font-family: monospace; font-size: 11px; color: var(--gray); background: var(--light); padding: 3px 7px; border-radius: 4px; border: 1px solid var(--light-gray); }

        .panel-loading { display: flex; align-items: center; justify-content: center; height: 200px; color: var(--gray); font-size: 13px; gap: 10px; }
        .panel-loading i { animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Toasts */
        .toast-stack {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 200;
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

        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .search-filter { flex-direction: column; align-items: stretch; }
            .order-actions { flex-direction: column; align-items: stretch; }
            .slide-panel { width: 100%; }
        }
    </style>
</head>

<body>
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
                                <td colspan="7" style="text-align:center;color:var(--gray);padding:30px;">No orders match this view.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <tr class="order-row" id="order-row-<?php echo $order['order_id']; ?>" data-status="<?php echo $order['status']; ?>">
                                <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['username']); ?></strong>
                                        <br><small style="color: var(--gray);">User ID: <?php echo $order['user_id']; ?></small>
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
                                            <span style="color: var(--gray);">File not found</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--gray);">No proof</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                    <br><small style="color: var(--gray);"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
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
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page - 1]); ?>">&laquo; Prev</a>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a class="page-link <?php echo $p === $page ? 'active' : ''; ?>" href="<?php echo build_query_url(['page' => $p]); ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                    <a class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page + 1]); ?>">Next &raquo;</a>
                </div>
            <?php endif; ?>
            <div class="page-info">Showing <?php echo count($orders); ?> of <?php echo $total_orders; ?> orders</div>
        </div>
    </div>

    <!-- Slide-over panel -->
    <div class="panel-overlay" id="panelOverlay" onclick="closeOrderPanel()"></div>
    <div class="slide-panel" id="slidePanel">
        <div class="panel-header">
            <h2 id="panelTitle">Order Details</h2>
            <button class="panel-close" onclick="closeOrderPanel()"><i class="fas fa-times"></i></button>
        </div>
        <div class="panel-status-bar" id="panelStatusBar"></div>
        <div class="panel-body" id="panelBody">
            <div class="panel-loading"><i class="fas fa-circle-notch"></i> Loading order...</div>
        </div>
    </div>

    <div class="toast-stack" id="toastStack"></div>

    <script>
        const CSRF_TOKEN = <?php echo esc_js(csrf_token()); ?>;
        const STATUS_LABELS = <?php echo json_encode($STATUS_LABELS); ?>;

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

        // ---------- Inline status update (row controls) ----------
        function onStatusSelectChange(select) {
            const container = select.closest('.order-actions');
            const reasonInput = container.querySelector('[data-role="cancel-reason"]');
            if (select.value === 'cancelled') {
                reasonInput.style.display = 'inline-block';
                reasonInput.focus();
            } else {
                reasonInput.style.display = 'none';
            }
        }

        async function submitStatusUpdate(orderId, btn) {
            const container = btn.closest('.order-actions');
            const status = container.querySelector('[data-role="status-select"]').value;
            const reasonInput = container.querySelector('[data-role="cancel-reason"]');
            const reason = reasonInput.value.trim();

            if (status === 'cancelled' && reason === '') {
                showToast('Please enter a reason for cancelling this order.', 'error');
                reasonInput.focus();
                return;
            }

            btn.disabled = true;
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Updating...';

            try {
                const body = new URLSearchParams({
                    ajax: 'update_status',
                    csrf_token: CSRF_TOKEN,
                    order_id: orderId,
                    status: status,
                    cancel_reason: reason
                });
                const res = await fetch('admin_orders.php', { method: 'POST', body });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    applyStatusToRow(orderId, data.status, data.status_label, data.cancellation_reason);
                    if (panelOrderId === orderId) applyStatusToPanel(data.status, data.status_label, data.cancellation_reason);
                } else {
                    showToast(data.message || 'Failed to update order status.', 'error');
                }
            } catch (e) {
                showToast('Network error while updating the order.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        function applyStatusToRow(orderId, status, statusLabel, cancellationReason) {
            const row = document.getElementById('order-row-' + orderId);
            if (!row) return;
            row.dataset.status = status;
            const badge = row.querySelector('[data-role="status-badge"]');
            badge.className = 'status-badge status-' + status;
            badge.textContent = statusLabel;
            if (status === 'cancelled' && cancellationReason) {
                badge.title = 'Reason: ' + cancellationReason;
            } else {
                badge.removeAttribute('title');
            }
        }

        // ---------- Slide-over panel ----------
        let panelOrderId = null;

        function openOrderPanel(orderId) {
            panelOrderId = orderId;
            document.getElementById('panelOverlay').classList.add('open');
            document.getElementById('slidePanel').classList.add('open');
            document.getElementById('panelTitle').textContent = 'Order #' + orderId;
            document.getElementById('panelBody').innerHTML = '<div class="panel-loading"><i class="fas fa-circle-notch"></i> Loading order...</div>';
            document.getElementById('panelStatusBar').innerHTML = '';

            const url = new URL(window.location);
            url.searchParams.set('open', orderId);
            history.replaceState(null, '', url);

            fetch('admin_orders.php?ajax=get_order_details&id=' + orderId)
                .then(res => res.json())
                .then(data => {
                    if (panelOrderId !== orderId) return; // stale response, user moved on
                    if (!data.success) {
                        document.getElementById('panelBody').innerHTML = '<div class="panel-loading">' + (data.message || 'Order not found.') + '</div>';
                        return;
                    }
                    document.getElementById('panelBody').innerHTML = data.html;
                    renderPanelStatusBar(orderId, data.status, data.cancellation_reason);
                })
                .catch(() => {
                    document.getElementById('panelBody').innerHTML = '<div class="panel-loading">Couldn\'t load this order. Please try again.</div>';
                });
        }

        function closeOrderPanel() {
            panelOrderId = null;
            document.getElementById('panelOverlay').classList.remove('open');
            document.getElementById('slidePanel').classList.remove('open');
            const url = new URL(window.location);
            url.searchParams.delete('open');
            history.replaceState(null, '', url);
        }

        function renderPanelStatusBar(orderId, status, cancellationReason) {
            const bar = document.getElementById('panelStatusBar');
            let options = '';
            for (const [val, label] of Object.entries(STATUS_LABELS)) {
                options += '<option value="' + val + '"' + (val === status ? ' selected' : '') + '>' + label + '</option>';
            }
            bar.innerHTML =
                '<select class="status-select" data-role="panel-status-select" onchange="onPanelStatusChange(this)">' + options + '</select>' +
                '<input type="text" class="cancel-reason-input" data-role="panel-cancel-reason" placeholder="Reason for cancellation" ' +
                    'value="' + (status === 'cancelled' && cancellationReason ? cancellationReason.replace(/"/g, '&quot;') : '') + '" ' +
                    'style="display:' + (status === 'cancelled' ? 'inline-block' : 'none') + ';">' +
                '<button type="button" class="update-btn" onclick="submitPanelStatusUpdate(' + orderId + ', this)"><i class="fas fa-sync"></i> Update</button>';
        }

        function onPanelStatusChange(select) {
            const reasonInput = document.querySelector('[data-role="panel-cancel-reason"]');
            reasonInput.style.display = select.value === 'cancelled' ? 'inline-block' : 'none';
        }

        async function submitPanelStatusUpdate(orderId, btn) {
            const status = document.querySelector('[data-role="panel-status-select"]').value;
            const reasonInput = document.querySelector('[data-role="panel-cancel-reason"]');
            const reason = reasonInput.value.trim();

            if (status === 'cancelled' && reason === '') {
                showToast('Please enter a reason for cancelling this order.', 'error');
                reasonInput.focus();
                return;
            }

            btn.disabled = true;
            try {
                const body = new URLSearchParams({
                    ajax: 'update_status',
                    csrf_token: CSRF_TOKEN,
                    order_id: orderId,
                    status: status,
                    cancel_reason: reason
                });
                const res = await fetch('admin_orders.php', { method: 'POST', body });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, 'success');
                    applyStatusToRow(orderId, data.status, data.status_label, data.cancellation_reason);
                    applyStatusToPanel(data.status, data.status_label, data.cancellation_reason);
                } else {
                    showToast(data.message || 'Failed to update order status.', 'error');
                }
            } catch (e) {
                showToast('Network error while updating the order.', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function applyStatusToPanel(status, statusLabel, cancellationReason) {
            const select = document.querySelector('[data-role="panel-status-select"]');
            if (select) select.value = status;
            const reasonInput = document.querySelector('[data-role="panel-cancel-reason"]');
            if (reasonInput) {
                reasonInput.style.display = status === 'cancelled' ? 'inline-block' : 'none';
                if (cancellationReason) reasonInput.value = cancellationReason;
            }
        }

        // Auto-open panel if URL already has ?open=ID (e.g. deep link from the dashboard, or an old order-details bookmark).
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($open_id > 0): ?>
                openOrderPanel(<?php echo $open_id; ?>);
            <?php endif; ?>
        });
    </script>
</body>

</html>