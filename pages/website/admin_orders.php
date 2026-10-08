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
 * Small presentation helpers (UI only - no business logic).
 */
function od_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $chars = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
    return function_exists('mb_strtoupper') ? mb_strtoupper($chars) : strtoupper($chars);
}

function od_status_badge(string $status, array $STATUS_LABELS, string $role = ''): string
{
    $label = $STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="wa-badge tone-' . htmlspecialchars($status) . '"'
        . ($role !== '' ? ' data-role="' . htmlspecialchars($role) . '"' : '')
        . '>' . htmlspecialchars($label) . '</span>';
}

function od_file_extension(string $path): string
{
    return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

function od_is_image(string $path): bool
{
    return in_array(od_file_extension($path), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
}

/**
 * One label/value tile in the customization grid.
 */
function render_spec(string $label, $value): void
{
    echo '<div class="od-spec"><dt>' . htmlspecialchars($label) . '</dt><dd>' . htmlspecialchars((string) $value) . '</dd></div>';
}

/**
 * Render the customization detail tiles for a single order item (ported from
 * admin_order_details.php) into the currently-open output buffer. The
 * conditions and name lookups are unchanged; only the markup is new.
 */
function render_item_customization(array $item): void
{
    $category = $item['product_category'];
    $product_name = $item['product_name'];

    if ($item['size_option']) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts':
                    render_spec('T-shirt size', $item['tshirt_size_name'] ?? $item['size_option']);
                    break;
                case 'Tote Bag':
                    render_spec('Tote bag size', $item['tote_size_name'] ?? $item['size_option']);
                    break;
                case 'Paper Bag':
                    $dimensions = $item['paperbag_dimensions'] ?? '';
                    $value = ($item['paperbag_size_name'] ?? $item['size_option']) . ($dimensions ? ' (' . $dimensions . ')' : '');
                    render_spec('Paper bag size', $value);
                    break;
                case 'Mug':
                    render_spec('Mug size', $item['mug_size_name'] ?? $item['size_option']);
                    break;
                default:
                    render_spec('Size', $item['size_option']);
            }
        } else {
            render_spec('Size', $item['size_option']);
        }
        if ($item['custom_size']) {
            render_spec('Custom size', $item['custom_size']);
        }
    }

    if ($item['color_option']) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts':
                    render_spec('T-shirt color', $item['tshirt_color_name'] ?? $item['color_option']);
                    break;
                case 'Tote Bag':
                    render_spec('Tote bag color', $item['tote_color_name'] ?? $item['color_option']);
                    break;
                case 'Mug':
                    render_spec('Mug color', $item['mug_color_name'] ?? $item['color_option']);
                    break;
                case 'Paper Bag':
                    render_spec('Color', 'Brown');
                    break;
                default:
                    render_spec('Color', $item['color_option']);
            }
        } else {
            render_spec('Color', $item['color_option']);
        }
        if ($item['custom_color']) {
            render_spec('Custom color', $item['custom_color']);
        }
    }

    if ($category !== 'Other Services') {
        if ($item['finish_option_name']) render_spec('Finish', $item['finish_option_name']);
        if ($item['paper_option_name']) render_spec('Paper', $item['paper_option_name']);
        if ($item['binding_option_name']) render_spec('Binding', $item['binding_option_name']);
        if ($item['layout_option_name']) render_spec('Layout', $item['layout_option_name']);
        if ($item['layout_details']) render_spec('Layout details', $item['layout_details']);
        if ($item['gsm_option']) render_spec('GSM', $item['gsm_option']);
    }
}

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

/**
 * Render one design-file preview card, or a "file not found" placeholder.
 * $kind is 'original' or 'mockup' (used only for styling).
 */
function render_design_preview(string $file, string $label, string $kind = 'original'): void
{
    $path = "../../assets/uploads/" . $file;
    $exists = file_exists($path);
    $safe_path = htmlspecialchars($path);
    $safe_label = htmlspecialchars($label);
    $name = htmlspecialchars(basename($file));

    echo '<figure class="od-file od-file--' . htmlspecialchars($kind) . ($exists ? '' : ' is-missing') . '">';
    if ($exists) {
        echo '<a class="od-file-thumb" href="' . $safe_path . '" target="_blank" rel="noopener" title="Open ' . $safe_label . ' in a new tab">';
        echo '<img src="' . $safe_path . '" alt="' . $safe_label . '" loading="lazy" onerror="this.closest(\'.od-file\').classList.add(\'is-broken\')">';
        echo '<i class="fas fa-file" aria-hidden="true"></i>';
        echo '</a>';
        echo '<a class="od-file-dl" href="' . $safe_path . '" download="' . $name . '" aria-label="Download ' . $safe_label . '" title="Download"><i class="fas fa-download" aria-hidden="true"></i></a>';
    } else {
        echo '<div class="od-file-thumb"><i class="fas fa-file-circle-xmark" aria-hidden="true"></i></div>';
    }
    echo '<figcaption class="od-file-meta">';
    echo '<span class="od-file-label">' . $safe_label . '</span>';
    echo '<span class="od-file-name" title="' . htmlspecialchars($file) . '">' . $name . '</span>';
    if (!$exists) {
        echo '<span class="od-file-flag">File not found</span>';
    }
    echo '</figcaption>';
    echo '</figure>';
}

/**
 * Render one customer-supplied layout file as a compact row with a download
 * button. The path handling matches the original panel code.
 */
function render_layout_file(string $file_path): void
{
    $clean_path = str_replace('../../', '', $file_path);
    $full_path = "../../" . $clean_path;
    $file_exists = file_exists($full_path);
    $name = basename($clean_path);
    $ext = od_file_extension($name);

    if ($ext === 'pdf') {
        $icon = 'fa-file-pdf';
    } elseif (in_array($ext, ['doc', 'docx', 'txt', 'rtf'], true)) {
        $icon = 'fa-file-lines';
    } elseif (in_array($ext, ['zip', 'rar', '7z'], true)) {
        $icon = 'fa-file-zipper';
    } else {
        $icon = 'fa-file';
    }

    echo '<div class="od-layout-file' . ($file_exists ? '' : ' is-missing') . '">';
    echo '<div class="od-layout-icon">';
    if ($file_exists && od_is_image($name)) {
        echo '<img src="' . htmlspecialchars($full_path) . '" alt="" loading="lazy">';
    } else {
        echo '<i class="fas ' . $icon . '" aria-hidden="true"></i>';
    }
    echo '</div>';
    echo '<div class="od-layout-info">';
    echo '<div class="od-layout-name" title="' . htmlspecialchars($name) . '">' . htmlspecialchars($name) . '</div>';
    echo '<div class="od-layout-path">' . htmlspecialchars($clean_path) . '</div>';
    if (!$file_exists) {
        echo '<div class="od-layout-flag"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> File not found</div>';
    }
    echo '</div>';
    if ($file_exists) {
        echo '<a class="btn btn-sm btn-outline" href="' . htmlspecialchars($full_path) . '" download="' . htmlspecialchars($name) . '" target="_blank" rel="noopener"><i class="fas fa-download" aria-hidden="true"></i> Download</a>';
    }
    echo '</div>';
}

/**
 * Build the full slide-over panel body HTML for one order (ported from
 * admin_order_details.php, condensed for the panel context). Queries are
 * unchanged; the markup is organised into overview / customer / payment /
 * items sections.
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
    <section class="od-section">
        <header class="od-section-head">
            <h3 class="od-section-title"><i class="fas fa-receipt" aria-hidden="true"></i> Order overview</h3>
        </header>
        <div class="od-section-body">
            <dl class="od-kpis">
                <div class="od-kpi">
                    <dt>Order ID</dt>
                    <dd>#<?php echo (int) $order['order_id']; ?></dd>
                </div>
                <div class="od-kpi">
                    <dt>Status</dt>
                    <dd><?php echo od_status_badge((string) $order['status'], $STATUS_LABELS, 'panel-status-badge'); ?></dd>
                </div>
                <div class="od-kpi">
                    <dt>Order date</dt>
                    <dd>
                        <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                        <span class="od-kpi-sub"><?php echo date('g:i A', strtotime($order['created_at'])); ?></span>
                    </dd>
                </div>
                <div class="od-kpi od-kpi--total">
                    <dt>Total amount</dt>
                    <dd>&#8369;<?php echo number_format($order['total_amount'], 2); ?></dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="od-section">
        <header class="od-section-head">
            <h3 class="od-section-title"><i class="fas fa-user" aria-hidden="true"></i> Customer</h3>
        </header>
        <div class="od-section-body">
            <div class="od-person">
                <span class="wa-avatar" aria-hidden="true"><?php echo htmlspecialchars(od_initials((string) $order['username'])); ?></span>
                <div>
                    <div class="od-person-name"><?php echo htmlspecialchars($order['username']); ?></div>
                    <div class="od-person-meta">User ID <?php echo (int) $order['user_id']; ?></div>
                </div>
            </div>
        </div>
    </section>

    <section class="od-section">
        <header class="od-section-head">
            <h3 class="od-section-title"><i class="fas fa-money-check-dollar" aria-hidden="true"></i> Payment</h3>
        </header>
        <div class="od-section-body">
            <?php
            $proof_state = 'none';
            $proof_path = '';
            if ($order['payment_proof']) {
                $proof_path = payment_proof_url((int) $order['order_id']);
                $proof_state = payment_proof_path((int) $order['user_id'], (string) $order['payment_proof']) ? 'ok' : 'missing';
            }
            ?>
            <div class="od-proof">
                <?php if ($proof_state === 'ok'): ?>
                    <a class="od-proof-thumb" href="<?php echo esc_html($proof_path); ?>" target="_blank" rel="noopener" title="Open payment proof in a new tab">
                        <img src="<?php echo esc_html($proof_path); ?>" alt="Payment proof for order #<?php echo (int) $order['order_id']; ?>" loading="lazy" onerror="this.closest('.od-proof').classList.add('is-broken')">
                        <i class="fas fa-file-invoice" aria-hidden="true"></i>
                    </a>
                    <div class="od-proof-info">
                        <span class="od-proof-state is-ok"><i class="fas fa-circle-check" aria-hidden="true"></i> Payment proof uploaded</span>
                        <a class="btn btn-sm btn-outline" href="<?php echo esc_html($proof_path); ?>" target="_blank" rel="noopener">
                            <i class="fas fa-up-right-from-square" aria-hidden="true"></i> View payment proof
                        </a>
                    </div>
                <?php elseif ($proof_state === 'missing'): ?>
                    <div class="od-proof-thumb"><i class="fas fa-file-circle-xmark" aria-hidden="true"></i></div>
                    <div class="od-proof-info">
                        <span class="od-proof-state is-missing"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> File not found</span>
                        <span class="od-proof-hint">A proof was recorded for this order, but the file is missing on the server.</span>
                    </div>
                <?php else: ?>
                    <div class="od-proof-thumb"><i class="fas fa-file-circle-minus" aria-hidden="true"></i></div>
                    <div class="od-proof-info">
                        <span class="od-proof-state is-none"><i class="fas fa-circle-minus" aria-hidden="true"></i> No payment proof uploaded</span>
                        <span class="od-proof-hint">The customer has not uploaded proof of payment yet.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="od-section">
        <header class="od-section-head">
            <h3 class="od-section-title"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Order items</h3>
            <span class="od-count"><?php echo count($order_items); ?></span>
        </header>
        <div class="od-section-body">
            <?php if (empty($order_items)): ?>
                <p class="od-empty-note">No items were found for this order.</p>
            <?php endif; ?>
            <?php foreach ($order_items as $item): ?>
                <article class="od-item">
                    <header class="od-item-head">
                        <div>
                            <h4 class="od-item-name"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                            <span class="wa-chip"><?php echo htmlspecialchars($item['product_category']); ?></span>
                        </div>
                        <span class="od-item-qty">&times; <?php echo (int) $item['quantity']; ?></span>
                    </header>

                    <dl class="od-money">
                        <div>
                            <dt>Unit price</dt>
                            <dd>&#8369;<?php echo number_format($item['unit_price'], 2); ?></dd>
                        </div>
                        <div>
                            <dt>Quantity</dt>
                            <dd><?php echo (int) $item['quantity']; ?></dd>
                        </div>
                        <div class="is-total">
                            <dt>Subtotal</dt>
                            <dd>&#8369;<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></dd>
                        </div>
                    </dl>

                    <?php if ($item['size_option'] || $item['color_option'] || $item['finish_option'] || $item['paper_option'] || $item['binding_option'] || $item['layout_option'] || $item['gsm_option']):
                        ob_start();
                        render_item_customization($item);
                        $specs_html = ob_get_clean();
                        if ($specs_html !== ''): ?>
                            <div class="od-block">
                                <h5 class="od-block-title"><i class="fas fa-sliders" aria-hidden="true"></i> Customization</h5>
                                <dl class="od-specs"><?php echo $specs_html; ?></dl>
                            </div>
                        <?php endif;
                    endif; ?>

                    <?php if (!empty($item['user_layout_files'])):
                        $layout_files = json_decode($item['user_layout_files'], true); ?>
                        <div class="od-block">
                            <h5 class="od-block-title"><i class="fas fa-file-arrow-up" aria-hidden="true"></i> Customer layout files</h5>
                            <?php if (is_array($layout_files)): ?>
                                <div class="od-layout-list">
                                    <?php foreach ($layout_files as $file_path) {
                                        render_layout_file((string) $file_path);
                                    } ?>
                                </div>
                            <?php else: ?>
                                <p class="od-empty-note">No valid layout files found.</p>
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
                            <div class="od-block">
                                <h5 class="od-block-title"><i class="fas fa-palette" aria-hidden="true"></i> Custom design files</h5>
                                <div class="od-files">
                                    <?php
                                    if ($uploadedFile) render_design_preview($uploadedFile, 'Original file', 'original');
                                    foreach ($frontUploadedFiles as $i => $file) {
                                        $label = count($frontUploadedFiles) > 1 ? 'Front original ' . ($i + 1) : 'Front original';
                                        render_design_preview($file, $label, 'original');
                                    }
                                    foreach ($backUploadedFiles as $i => $file) {
                                        $label = count($backUploadedFiles) > 1 ? 'Back original ' . ($i + 1) : 'Back original';
                                        render_design_preview($file, $label, 'original');
                                    }
                                    if ($frontMockup) render_design_preview($frontMockup, 'Front', 'mockup');
                                    if ($backMockup) render_design_preview($backMockup, 'Back', 'mockup');
                                    ?>
                                </div>
                            </div>
                        <?php endif;
                    endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
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
$tab_counts = ['all' => 0, 'pending' => 0, 'paid' => 0, 'processing' => 0, 'ready_for_pickup' => 0, 'completed' => 0, 'cancelled' => 0];
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

// Display-only values for the header, summary tiles and result range.
$in_progress_total = $tab_counts['paid'] + $tab_counts['processing'] + $tab_counts['ready_for_pickup'];
$range_from = $total_orders > 0 ? $offset + 1 : 0;
$range_to = $offset + count($orders);
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
        <main class="main-content">
            <div class="wa-page">

                <!-- 1. Page header -->
                <header class="wa-page-head">
                    <div>
                        <h1 class="wa-page-title">Order Management</h1>
                        <p class="wa-page-desc">Review payments, track production and update the status of customer orders.</p>
                    </div>
                    <div class="wa-page-actions">
                        <?php if ($tab_counts['pending'] > 0): ?>
                            <a class="wa-chip tone-pending" href="admin_orders.php?status=pending">
                                <span class="wa-dot" aria-hidden="true"></span>
                                <span data-count="pending"><?php echo $tab_counts['pending']; ?></span>&nbsp;pending
                            </a>
                        <?php endif; ?>
                        <a class="btn btn-secondary" href="<?php echo build_query_url(); ?>">
                            <i class="fas fa-rotate" aria-hidden="true"></i> Refresh
                        </a>
                    </div>
                </header>

                <!-- 2. Summary -->
                <section class="wa-stats" aria-label="Order summary">
                    <a class="wa-stat tone-total <?php echo ($status === '' && $search === '') ? 'is-active' : ''; ?>" href="admin_orders.php">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-receipt"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Total orders</span>
                            <span class="wa-stat-value" data-count="__all"><?php echo $tab_counts['all']; ?></span>
                            <span class="wa-stat-sub">All statuses</span>
                        </span>
                    </a>
                    <a class="wa-stat tone-pending <?php echo $status === 'pending' ? 'is-active' : ''; ?>" href="admin_orders.php?status=pending">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Pending</span>
                            <span class="wa-stat-value" data-count="pending"><?php echo $tab_counts['pending']; ?></span>
                            <span class="wa-stat-sub">Waiting to be actioned</span>
                        </span>
                    </a>
                    <div class="wa-stat tone-processing">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-gears"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">In progress</span>
                            <span class="wa-stat-value" data-count="paid processing ready_for_pickup"><?php echo $in_progress_total; ?></span>
                            <span class="wa-stat-sub">
                                <span data-count="paid"><?php echo $tab_counts['paid']; ?></span> paid &middot;
                                <span data-count="processing"><?php echo $tab_counts['processing']; ?></span> processing &middot;
                                <span data-count="ready_for_pickup"><?php echo $tab_counts['ready_for_pickup']; ?></span> ready
                            </span>
                        </span>
                    </div>
                    <a class="wa-stat tone-completed <?php echo $status === 'completed' ? 'is-active' : ''; ?>" href="admin_orders.php?status=completed">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-circle-check"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Completed</span>
                            <span class="wa-stat-value" data-count="completed"><?php echo $tab_counts['completed']; ?></span>
                            <span class="wa-stat-sub">Fulfilled orders</span>
                        </span>
                    </a>
                    <a class="wa-stat tone-cancelled <?php echo $status === 'cancelled' ? 'is-active' : ''; ?>" href="admin_orders.php?status=cancelled">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-ban"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Cancelled</span>
                            <span class="wa-stat-value" data-count="cancelled"><?php echo $tab_counts['cancelled']; ?></span>
                            <span class="wa-stat-sub">Closed without fulfilment</span>
                        </span>
                    </a>
                </section>

                <!-- 3 + 4. Search, filter and status navigation (one control) -->
                <section class="wa-filterbar" aria-label="Search and filter orders">
                    <form class="wa-filterbar-form" method="get" action="admin_orders.php" role="search">
                        <div class="wa-search">
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" name="search" id="orderSearch" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by order ID or customer" aria-label="Search orders by order ID or customer" autocomplete="off">
                            <?php if ($search !== ''): ?>
                                <a class="wa-search-clear" href="<?php echo build_query_url(['search' => '', 'page' => '']); ?>" aria-label="Clear search" title="Clear search">
                                    <i class="fas fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="wa-select">
                            <label class="sr-only" for="orderStatusFilter">Filter by status</label>
                            <select name="status" id="orderStatusFilter">
                                <option value="">All statuses</option>
                                <?php foreach ($STATUS_LABELS as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $status === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                        <?php if ($status !== '' || $search !== ''): ?>
                            <a href="admin_orders.php" class="btn btn-ghost"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <nav class="wa-tabs" aria-label="Filter orders by status">
                        <?php foreach (array_merge(['' => 'All'], $STATUS_LABELS) as $val => $label):
                            $val = (string) $val;
                            $count_key = $val === '' ? 'all' : $val;
                            $is_active = $status === $val;
                        ?>
                            <a class="wa-tab <?php echo $is_active ? 'is-active' : ''; ?>"
                                href="<?php echo build_query_url(['status' => $val, 'page' => '']); ?>"
                                <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                <?php if ($val !== ''): ?><span class="wa-dot tone-<?php echo $val; ?>" aria-hidden="true"></span><?php endif; ?>
                                <?php echo htmlspecialchars($label); ?>
                                <span class="wa-tab-count" data-count="<?php echo $val === '' ? '__all' : $val; ?>"><?php echo $tab_counts[$count_key]; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </section>

                <!-- 5 + 6. Orders data and pagination -->
                <section class="wa-datacard" aria-labelledby="ordersHeading">
                    <div class="wa-datacard-head">
                        <div>
                            <h2 class="wa-datacard-title" id="ordersHeading"><?php echo $status !== '' ? htmlspecialchars($STATUS_LABELS[$status]) . ' orders' : 'All orders'; ?></h2>
                            <p class="wa-datacard-sub">
                                <?php if ($total_orders > 0): ?>
                                    Showing <?php echo $range_from; ?>&ndash;<?php echo $range_to; ?> of <?php echo $total_orders; ?> &middot; newest first
                                <?php else: ?>
                                    Nothing to show
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($status !== '' || $search !== ''): ?>
                            <div class="wa-filter-chips" aria-label="Active filters">
                                <?php if ($status !== ''): ?>
                                    <span class="wa-filter-chip">
                                        <span>Status: <?php echo htmlspecialchars($STATUS_LABELS[$status]); ?></span>
                                        <a href="<?php echo build_query_url(['status' => '', 'page' => '']); ?>" aria-label="Remove status filter" title="Remove status filter"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($search !== ''): ?>
                                    <span class="wa-filter-chip">
                                        <span>Search: &ldquo;<?php echo htmlspecialchars($search); ?>&rdquo;</span>
                                        <a href="<?php echo build_query_url(['search' => '', 'page' => '']); ?>" aria-label="Remove search filter" title="Remove search filter"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wa-table-wrap">
                        <table class="ord-table">
                            <caption class="sr-only">Customer orders</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Order</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Payment</th>
                                    <th scope="col" class="is-num">Total</th>
                                    <th scope="col">Status</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody id="ordersTable">
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-row">
                                            <div class="wa-empty">
                                                <span class="wa-empty-icon" aria-hidden="true"><i class="fas fa-inbox"></i></span>
                                                <div class="wa-empty-title">No orders found</div>
                                                <p class="wa-empty-text">
                                                    <?php echo ($status !== '' || $search !== '') ? 'Nothing matches the current search or status. Try a different term or clear the filters.' : 'Orders will appear here as customers place them.'; ?>
                                                </p>
                                                <?php if ($status !== '' || $search !== ''): ?>
                                                    <a href="admin_orders.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left" aria-hidden="true"></i> Clear filters</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($orders as $order):
                                    $created_ts = strtotime($order['created_at']);
                                    $cancel_reason_text = ($order['status'] === 'cancelled' && !empty($order['cancellation_reason'])) ? $order['cancellation_reason'] : '';
                                    $proof_state = 'none';
                                    $proof_path = '';
                                    if ($order['payment_proof']) {
                                        $proof_path = payment_proof_url((int) $order['order_id']);
                                        $proof_state = payment_proof_path((int) $order['user_id'], (string) $order['payment_proof']) ? 'ok' : 'missing';
                                    }
                                ?>
                                    <tr class="ord-row" id="order-row-<?php echo (int) $order['order_id']; ?>"
                                        data-status="<?php echo htmlspecialchars($order['status']); ?>"
                                        data-customer="<?php echo htmlspecialchars($order['username']); ?>"
                                        data-date="<?php echo date('M j, Y · g:i A', $created_ts); ?>"
                                        data-cancel-reason="<?php echo htmlspecialchars($cancel_reason_text); ?>">
                                        <td class="ord-col-order">
                                            <button type="button" class="ord-id" onclick="openOrderPanel(<?php echo (int) $order['order_id']; ?>)"
                                                aria-label="View order #<?php echo (int) $order['order_id']; ?>">#<?php echo (int) $order['order_id']; ?></button>
                                            <div class="ord-meta">
                                                <time datetime="<?php echo date('c', $created_ts); ?>"><?php echo date('M j, Y', $created_ts); ?> &middot; <?php echo date('g:i A', $created_ts); ?></time>
                                            </div>
                                        </td>
                                        <td class="ord-col-customer">
                                            <div class="ord-customer">
                                                <span class="wa-avatar" aria-hidden="true"><?php echo htmlspecialchars(od_initials((string) $order['username'])); ?></span>
                                                <div>
                                                    <div class="ord-customer-name"><?php echo htmlspecialchars($order['username']); ?></div>
                                                    <div class="ord-meta">User ID <?php echo (int) $order['user_id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="ord-col-payment">
                                            <?php if ($proof_state === 'ok'): ?>
                                                <a class="ord-proof" href="<?php echo esc_html($proof_path); ?>" target="_blank" rel="noopener"
                                                    title="Open payment proof for order #<?php echo (int) $order['order_id']; ?>">
                                                    <span class="ord-proof-thumb">
                                                        <img src="<?php echo esc_html($proof_path); ?>" alt="Payment proof for order #<?php echo (int) $order['order_id']; ?>" loading="lazy"
                                                            onerror="this.closest('.ord-proof').classList.add('is-broken')">
                                                        <i class="fas fa-file-invoice" aria-hidden="true"></i>
                                                    </span>
                                                    <span class="ord-proof-text">Proof uploaded</span>
                                                </a>
                                            <?php elseif ($proof_state === 'missing'): ?>
                                                <span class="ord-proof-state is-missing"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> File not found</span>
                                            <?php else: ?>
                                                <span class="ord-proof-state"><i class="fas fa-circle-minus" aria-hidden="true"></i> No proof</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="ord-col-total is-num">
                                            <span class="ord-total">&#8369;<?php echo number_format($order['total_amount'], 2); ?></span>
                                        </td>
                                        <td class="ord-col-status">
                                            <span class="wa-badge tone-<?php echo htmlspecialchars($order['status']); ?>" data-role="status-badge"
                                                <?php if ($cancel_reason_text !== ''): ?>title="Reason: <?php echo esc_html($cancel_reason_text); ?>" <?php endif; ?>>
                                                <?php echo htmlspecialchars($STATUS_LABELS[$order['status']] ?? ucfirst(str_replace('_', ' ', $order['status']))); ?>
                                            </span>
                                            <div class="ord-reason" data-role="status-reason" <?php echo $cancel_reason_text === '' ? 'hidden' : ''; ?>>
                                                <i class="fas fa-comment-dots" aria-hidden="true"></i>
                                                <span data-role="status-reason-text"><?php echo htmlspecialchars($cancel_reason_text); ?></span>
                                            </div>
                                        </td>
                                        <td class="ord-col-actions">
                                            <div class="ord-actions" data-order-id="<?php echo (int) $order['order_id']; ?>">
                                                <button type="button" class="btn btn-sm btn-outline" onclick="openOrderPanel(<?php echo (int) $order['order_id']; ?>)">
                                                    <i class="fas fa-eye" aria-hidden="true"></i> View
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline btn-icon" onclick="openStatusDialog(<?php echo (int) $order['order_id']; ?>)"
                                                    aria-label="Update status of order #<?php echo (int) $order['order_id']; ?>" title="Update status">
                                                    <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="wa-datacard-foot">
                        <span class="wa-datacard-foot-text">Showing <?php echo count($orders); ?> of <?php echo $total_orders; ?> orders</span>
                        <?php if ($total_pages > 1): ?>
                            <nav class="wa-pager" aria-label="Pagination">
                                <a class="wa-pager-link <?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page - 1]); ?>"
                                    <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>><i class="fas fa-chevron-left" aria-hidden="true"></i> Prev</a>
                                <?php
                                $window = 2;
                                for ($p = 1; $p <= $total_pages; $p++) {
                                    $show = $p === 1 || $p === $total_pages || abs($p - $page) <= $window;
                                    if (!$show) {
                                        if ($p === 2 || $p === $total_pages - 1) {
                                            echo '<span class="wa-pager-gap" aria-hidden="true">&hellip;</span>';
                                        }
                                        continue;
                                    }
                                    $active = $p === $page;
                                    echo '<a class="wa-pager-link' . ($active ? ' is-active' : '') . '" href="' . build_query_url(['page' => $p]) . '"'
                                        . ($active ? ' aria-current="page"' : '') . ' aria-label="Page ' . $p . '">' . $p . '</a>';
                                }
                                ?>
                                <a class="wa-pager-link <?php echo $page >= $total_pages ? 'is-disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page + 1]); ?>"
                                    <?php echo $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                            </nav>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Order detail slide-over panel -->
    <div class="panel-overlay" id="panelOverlay" onclick="closeOrderPanel()"></div>
    <aside class="slide-panel" id="slidePanel" role="dialog" aria-modal="true" aria-labelledby="panelTitle" aria-hidden="true">
        <div class="panel-header">
            <div class="panel-heading">
                <div class="panel-title-row">
                    <h2 id="panelTitle">Order Details</h2>
                    <span id="panelHeadBadge"></span>
                </div>
                <p class="panel-subtitle" id="panelSubtitle"></p>
            </div>
            <button type="button" class="panel-close" id="panelClose" onclick="closeOrderPanel()" aria-label="Close order details"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="panel-status-bar" id="panelStatusBar"></div>
        <div class="panel-body" id="panelBody" aria-live="polite">
            <div class="od-skeleton" aria-hidden="true">
                <div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:40%"></span><span class="wa-skel"></span><span class="wa-skel" style="width:70%"></span></div></div>
            </div>
        </div>
    </aside>

    <!-- Status dialog (opened from a row's "Update status" button) -->
    <div class="modal" id="statusModal" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <div>
                    <h2 id="statusModalTitle">Update order status</h2>
                    <p class="ord-dialog-sub" id="statusModalSub"></p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('statusModal')" aria-label="Close">&times;</button>
            </div>
            <form id="statusForm" novalidate>
                <div class="modal-body">
                    <fieldset class="ord-options">
                        <legend class="sr-only">New status</legend>
                        <?php foreach ($STATUS_LABELS as $val => $label): ?>
                            <label class="ord-option tone-<?php echo $val; ?>">
                                <input type="radio" class="sr-only" name="dialog_status" value="<?php echo $val; ?>">
                                <span class="wa-dot" aria-hidden="true"></span>
                                <span class="ord-option-label"><?php echo htmlspecialchars($label); ?></span>
                                <i class="fas fa-check ord-option-check" aria-hidden="true"></i>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <div class="ord-reason-field" id="statusReasonField" hidden>
                        <label for="statusReason">Reason for cancellation <span>Required</span></label>
                        <textarea class="od-textarea" id="statusReason" rows="3" placeholder="Why is this order being cancelled?"></textarea>
                        <p class="ord-field-hint">Saved with the order and shown in the orders list.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Close</button>
                    <button type="submit" class="btn btn-primary" id="statusSubmit"><span id="statusSubmitLabel">Update status</span></button>
                </div>
            </form>
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