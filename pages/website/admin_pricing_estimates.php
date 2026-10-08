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

$VALID_STATUSES = ['pending', 'quoted', 'cancelled'];
$STATUS_LABELS = [
    'pending' => 'Pending',
    'quoted' => 'Checked',
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

function pr_status_label(string $status, array $STATUS_LABELS): string
{
    return $STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function od_status_badge(string $status, array $STATUS_LABELS, string $role = ''): string
{
    return '<span class="wa-badge tone-' . htmlspecialchars($status) . '"'
        . ($role !== '' ? ' data-role="' . htmlspecialchars($role) . '"' : '')
        . '>' . htmlspecialchars(pr_status_label($status, $STATUS_LABELS)) . '</span>';
}

function pr_customer_name(array $row): string
{
    if (!empty($row['company_name'])) {
        return $row['company_name'];
    }
    if (!empty($row['first_name'])) {
        return trim($row['first_name'] . ' ' . ($row['last_name'] ?? ''));
    }
    return (string) $row['username'];
}

function pr_selected_item_ids($raw): array
{
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_values(array_map('intval', $decoded));
}

/**
 * Final price cell: the quoted total plus how far it is from the estimate.
 * (Same maths the old page used; only the markup changed.)
 */
function pr_final_price_html($final, $estimated): string
{
    if (!$final) {
        return '<span class="ord-meta">Not set</span>';
    }
    $difference = $final - $estimated;
    $percentage = $estimated > 0 ? ($difference / $estimated) * 100 : 0;
    if ($difference > 0) {
        $diff = '<small class="price-increase">+&#8369;' . number_format(abs($difference), 2) . ' (' . number_format(abs($percentage), 1) . '%)</small>';
    } elseif ($difference < 0) {
        $diff = '<small class="price-decrease">-&#8369;' . number_format(abs($difference), 2) . ' (' . number_format(abs($percentage), 1) . '%)</small>';
    } else {
        $diff = '<small class="price-same">No change</small>';
    }
    return '<span class="ord-total">&#8369;' . number_format($final, 2) . '</span>'
        . '<div class="ord-meta price-comparison">' . $diff . '</div>';
}

function od_file_extension(string $path): string
{
    return strtolower(pathinfo($path, PATHINFO_EXTENSION));
}

function od_is_image(string $path): bool
{
    return in_array(od_file_extension($path), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
}

function render_spec(string $label, $value): void
{
    echo '<div class="od-spec"><dt>' . htmlspecialchars($label) . '</dt><dd>' . htmlspecialchars((string) $value) . '</dd></div>';
}

/**
 * Customization tiles for one cart item (same fields the old details modal showed).
 */
function pr_render_item_specs(array $item): void
{
    if (!empty($item['size_option'])) {
        render_spec('Size', $item['size_option'] . (!empty($item['custom_size']) ? ' (Custom: ' . $item['custom_size'] . ')' : ''));
    }
    if (!empty($item['color_option'])) {
        render_spec('Color', $item['color_option'] . (!empty($item['custom_color']) ? ' (Custom: ' . $item['custom_color'] . ')' : ''));
    }
    if (!empty($item['finish_option'])) render_spec('Finish', $item['finish_option_name']);
    if (!empty($item['paper_option'])) render_spec('Paper', $item['paper_option_name']);
    if (!empty($item['binding_option'])) render_spec('Binding', $item['binding_option_name']);
    if (!empty($item['layout_option'])) render_spec('Layout', $item['layout_option_name']);
    if (!empty($item['layout_details'])) render_spec('Layout details', $item['layout_details']);
    if (!empty($item['gsm_option'])) render_spec('GSM', $item['gsm_option']);
}

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
 * Build the slide-over panel body for one pricing request: overview,
 * customer, admin notes and the requested items.
 */
function render_request_panel_html(mysqli $inventory, int $request_id, array $STATUS_LABELS): string
{
    $stmt = $inventory->prepare("SELECT pr.*, u.username, pc.first_name, pc.last_name, pc.contact_number AS personal_contact,
                                        cc.company_name, cc.contact_person, cc.contact_number AS company_contact
                                 FROM pricing_requests pr
                                 JOIN users u ON pr.user_id = u.id
                                 LEFT JOIN personal_customers pc ON u.id = pc.user_id
                                 LEFT JOIN company_customers cc ON u.id = cc.user_id
                                 WHERE pr.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    if (!$request) {
        return '';
    }

    $item_ids = pr_selected_item_ids($request['selected_items']);

    $cart_rows = [];
    if (!empty($item_ids)) {
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $stmt = $inventory->prepare("SELECT ci.*, p.product_name, p.category, p.price AS unit_price,
                                            po.option_name AS paper_option_name,
                                            fo.option_name AS finish_option_name,
                                            bo.option_name AS binding_option_name,
                                            lo.option_name AS layout_option_name
                                     FROM cart_items ci
                                     JOIN products_offered p ON ci.product_id = p.id
                                     LEFT JOIN paper_options po ON ci.paper_option = po.id
                                     LEFT JOIN finish_options fo ON ci.finish_option = fo.id
                                     LEFT JOIN binding_options bo ON ci.binding_option = bo.id
                                     LEFT JOIN layout_options lo ON ci.layout_option = lo.id
                                     WHERE ci.item_id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($item_ids)), ...$item_ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $cart_rows[(int) $row['item_id']] = $row;
        }
    }

    $customer_name = pr_customer_name($request);
    $requested_ts = strtotime($request['request_date']);

    $total_quoted = 0;

    ob_start();
    ?>
    <section class="od-section">
        <header class="od-section-head">
            <h3 class="od-section-title"><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Request overview</h3>
        </header>
        <div class="od-section-body">
            <dl class="od-kpis">
                <div class="od-kpi">
                    <dt>Request ID</dt>
                    <dd>#<?php echo (int) $request['id']; ?></dd>
                </div>
                <div class="od-kpi">
                    <dt>Status</dt>
                    <dd><?php echo od_status_badge((string) $request['status'], $STATUS_LABELS, 'panel-status-badge'); ?></dd>
                </div>
                <div class="od-kpi">
                    <dt>Request date</dt>
                    <dd>
                        <?php echo date('M j, Y', $requested_ts); ?>
                        <span class="od-kpi-sub"><?php echo date('g:i A', $requested_ts); ?></span>
                    </dd>
                </div>
                <div class="od-kpi od-kpi--total">
                    <dt>Final price</dt>
                    <dd>
                        <?php echo $request['final_price'] ? '&#8369;' . number_format($request['final_price'], 2) : 'Not set'; ?>
                        <span class="od-kpi-sub">Estimated &#8369;<?php echo number_format($request['estimated_total'], 2); ?></span>
                    </dd>
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
                <span class="wa-avatar" aria-hidden="true"><?php echo htmlspecialchars(od_initials($customer_name)); ?></span>
                <div>
                    <div class="od-person-name"><?php echo htmlspecialchars($customer_name); ?></div>
                    <div class="od-person-meta"><?php echo !empty($request['company_name']) ? 'Company customer' : (!empty($request['first_name']) ? 'Personal customer' : 'Customer'); ?> &middot; User ID <?php echo (int) $request['user_id']; ?></div>
                </div>
            </div>
            <dl class="od-specs pq-contact">
                <?php
                render_spec('Username', $request['username']);
                if (!empty($request['company_name'])) {
                    if (!empty($request['contact_person'])) render_spec('Contact person', $request['contact_person']);
                    if (!empty($request['company_contact'])) render_spec('Contact no.', $request['company_contact']);
                } elseif (!empty($request['personal_contact'])) {
                    render_spec('Contact no.', $request['personal_contact']);
                }
                ?>
            </dl>
        </div>
    </section>

    <?php if (!empty($request['admin_notes'])): ?>
        <section class="od-section">
            <header class="od-section-head">
                <h3 class="od-section-title"><i class="fas fa-comment-dots" aria-hidden="true"></i> Admin notes</h3>
            </header>
            <div class="od-section-body">
                <p class="pq-notes"><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="od-section">
        <header class="od-section-head">
            <h3 class="od-section-title"><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Requested items</h3>
            <span class="od-count"><?php echo count($item_ids); ?></span>
        </header>
        <div class="od-section-body">
            <?php if (empty($item_ids)): ?>
                <p class="od-empty-note">No items were found for this request.</p>
            <?php endif; ?>
            <?php foreach ($item_ids as $iid):
                $item = $cart_rows[$iid] ?? null;
                $admin_priced = $item && !empty($item['price_updated_by_admin']);
                $actual_price = 0;
                if ($item) {
                    $actual_price = ($admin_priced && $item['quoted_price'] > 0) ? $item['quoted_price'] : $item['unit_price'];
                    $subtotal = $actual_price * $item['quantity'];
                    $total_quoted += $subtotal;
                }
            ?>
                <article class="od-item">
                    <header class="od-item-head">
                        <div>
                            <h4 class="od-item-name"><?php echo $item ? htmlspecialchars($item['product_name']) : 'Item #' . $iid; ?></h4>
                            <?php if ($item && !empty($item['category'])): ?><span class="wa-chip"><?php echo htmlspecialchars($item['category']); ?></span><?php endif; ?>
                            <?php if ($admin_priced): ?><span class="wa-chip tone-quoted">Quoted &#8369;<?php echo number_format($item['quoted_price'], 2); ?></span><?php endif; ?>
                        </div>
                        <?php if ($item): ?><span class="od-item-qty">&times; <?php echo (int) $item['quantity']; ?></span><?php endif; ?>
                    </header>

                    <?php if ($item): ?>
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
                            <dd>&#8369;<?php echo number_format($subtotal, 2); ?></dd>
                        </div>
                    </dl>
                    <?php else: ?>
                        <p class="od-empty-note pq-empty">This item is no longer available.</p>
                    <?php endif; ?>

                    <?php if ($item):
                        ob_start();
                        pr_render_item_specs($item);
                        $specs_html = ob_get_clean();
                        if ($specs_html !== ''): ?>
                            <div class="od-block">
                                <h5 class="od-block-title"><i class="fas fa-sliders" aria-hidden="true"></i> Customization</h5>
                                <dl class="od-specs"><?php echo $specs_html; ?></dl>
                            </div>
                        <?php endif;
                    endif; ?>

                    <?php if ($item && !empty($item['user_layout_files'])):
                        $layout_files = json_decode($item['user_layout_files'], true);
                        if (is_array($layout_files) && !empty($layout_files)): ?>
                            <div class="od-block">
                                <h5 class="od-block-title"><i class="fas fa-file-arrow-up" aria-hidden="true"></i> Customer layout files</h5>
                                <div class="od-layout-list">
                                    <?php foreach ($layout_files as $file_path) {
                                        render_layout_file((string) $file_path);
                                    } ?>
                                </div>
                            </div>
                        <?php endif;
                    endif; ?>

                    <?php if ($item && !empty($item['design_image'])):
                        $designData = $item['design_image'];
                        $frontMockup = $backMockup = $uploadedFile = '';
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
            <?php if ($total_quoted > 0): ?>
                <div class="pq-total">
                    <span>Total quoted amount</span>
                    <strong>&#8369;<?php echo number_format($total_quoted, 2); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------
// POST actions (business logic unchanged from the previous version).
// ---------------------------------------------------------------------
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
        $request_id = (int) ($_POST['request_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        $admin_notes = $_POST['admin_notes'] ?? '';
        // Per-item prices entered by the admin: item_price[item_id] => price.
        $item_prices = isset($_POST['item_price']) && is_array($_POST['item_price']) ? $_POST['item_price'] : [];

        // Get the pricing request details
        $request_query = "SELECT * FROM pricing_requests WHERE id = ?";
        $request_stmt = $inventory->prepare($request_query);
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_data = $request_stmt->get_result()->fetch_assoc();

        if (!$request_data || !in_array($new_status, $VALID_STATUSES, true)) {
            $msg = 'Invalid request.';
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $msg]);
            } else {
                $_SESSION['error'] = $msg;
                header("Location: admin_pricing_estimates.php");
            }
            exit;
        }

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

        // AJAX callers (the quote dialog) get JSON back and update the row
        // in place; anything still POSTing the old-fashioned way falls back
        // to the session-flash + redirect behavior below.
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success,
                'message' => $success ? "Pricing request #$request_id updated successfully!" : 'Failed to update pricing request!',
                'request_id' => (int) $request_id,
                'status' => $new_status,
                'status_label' => $STATUS_LABELS[$new_status],
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

    // Handle delete request
    if (isset($_POST['delete_request'])) {
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
}

// ---------------------------------------------------------------------
// AJAX endpoint for the slide-over panel (same-file `?ajax=` dispatch,
// matching admin_orders.php).
// ---------------------------------------------------------------------
if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['ajax'] === 'get_request_details') {
    header('Content-Type: application/json; charset=utf-8');
    $request_id = (int) ($_GET['id'] ?? 0);
    $html = $request_id ? render_request_panel_html($inventory, $request_id, $STATUS_LABELS) : '';
    if ($html === '') {
        echo json_encode(['success' => false, 'message' => 'Pricing request not found.']);
        exit;
    }

    $stmt = $inventory->prepare("SELECT status FROM pricing_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'request_id' => $request_id,
        'status' => $row['status'],
        'html' => $html,
    ]);
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
    $where[] = "pr.status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($search !== '') {
    $where[] = "(pr.id LIKE ? OR u.username LIKE ? OR pc.first_name LIKE ? OR pc.last_name LIKE ? OR CONCAT_WS(' ', pc.first_name, pc.last_name) LIKE ? OR cc.company_name LIKE ?)";
    $like = "%$search%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $like;
    }
    $types .= 'ssssss';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$from_sql = "FROM pricing_requests pr
             JOIN users u ON pr.user_id = u.id
             LEFT JOIN personal_customers pc ON u.id = pc.user_id
             LEFT JOIN company_customers cc ON u.id = cc.user_id";

$stmt = $inventory->prepare("SELECT COUNT(*) AS total $from_sql $where_sql");
if ($types !== '') bind_dynamic($stmt, $types, $params);
$stmt->execute();
$total_requests = (int) $stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int) ceil($total_requests / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$list_query = "SELECT pr.*, u.username, pc.first_name, pc.last_name, cc.company_name
               $from_sql
               $where_sql
               ORDER BY pr.request_date DESC
               LIMIT ? OFFSET ?";
$list_params = $params;
$list_types = $types . 'ii';
$list_params[] = $per_page;
$list_params[] = $offset;
$stmt = $inventory->prepare($list_query);
bind_dynamic($stmt, $list_types, $list_params);
$stmt->execute();
$pricing_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Item labels and already-quoted prices for the requests on this page, used
// to prefill the quote dialog.
$page_request_ids = array_map(static function ($r) {
    return (int) $r['id'];
}, $pricing_requests);

$all_item_ids = [];
foreach ($pricing_requests as $pr) {
    foreach (pr_selected_item_ids($pr['selected_items']) as $iid) {
        $all_item_ids[$iid] = true;
    }
}
$all_item_ids = array_keys($all_item_ids);

$item_info = [];            // item_id => ['product_name' => ..., 'quantity' => ...]
$existing_item_prices = []; // request_id => [item_id => quoted_price]

if (!empty($all_item_ids)) {
    $placeholders = implode(',', array_fill(0, count($all_item_ids), '?'));
    $item_types = str_repeat('i', count($all_item_ids));

    $item_stmt = $inventory->prepare("SELECT ci.item_id, p.product_name, ci.quantity
                                      FROM cart_items ci
                                      JOIN products_offered p ON ci.product_id = p.id
                                      WHERE ci.item_id IN ($placeholders)");
    $item_stmt->bind_param($item_types, ...$all_item_ids);
    $item_stmt->execute();
    $item_result = $item_stmt->get_result();
    while ($row = $item_result->fetch_assoc()) {
        $item_info[(int) $row['item_id']] = [
            'product_name' => $row['product_name'],
            'quantity' => (int) $row['quantity'],
        ];
    }

    $req_placeholders = implode(',', array_fill(0, count($page_request_ids), '?'));
    $prices_stmt = $inventory->prepare("SELECT pricing_request_id, cart_item_id, quoted_price
                                        FROM pricing_requests_items
                                        WHERE pricing_request_id IN ($req_placeholders)");
    $prices_stmt->bind_param(str_repeat('i', count($page_request_ids)), ...$page_request_ids);
    $prices_stmt->execute();
    $prices_result = $prices_stmt->get_result();
    while ($row = $prices_result->fetch_assoc()) {
        $existing_item_prices[(int) $row['pricing_request_id']][(int) $row['cart_item_id']] = $row['quoted_price'];
    }
}

// Status tab counts (unfiltered by search, so counts stay stable while typing).
$tab_counts = ['all' => 0, 'pending' => 0, 'quoted' => 0, 'cancelled' => 0];
$count_by_status = $inventory->query("SELECT status, COUNT(*) AS c FROM pricing_requests GROUP BY status");
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
    return 'admin_pricing_estimates.php' . ($merged ? ('?' . http_build_query($merged)) : '');
}

$open_id = isset($_GET['open']) ? (int) $_GET['open'] : 0;

// Display-only values for the result range.
$range_from = $total_requests > 0 ? $offset + 1 : 0;
$range_to = $offset + count($pricing_requests);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Consultation Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-pricing" data-page="pricing">
    <div class="admin-container">
        <main class="main-content">
            <div class="wa-page">

                <!-- 1. Page header -->
                <header class="wa-page-head">
                    <div>
                        <h1 class="wa-page-title">Pricing Consultation Management</h1>
                        <p class="wa-page-desc">Review customer pricing requests, set a price for each item and track the status of every quote.</p>
                    </div>
                    <div class="wa-page-actions">
                        <?php if ($tab_counts['pending'] > 0): ?>
                            <a class="wa-chip tone-pending" href="admin_pricing_estimates.php?status=pending">
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
                <section class="wa-stats" aria-label="Pricing request summary">
                    <a class="wa-stat tone-total <?php echo ($status === '' && $search === '') ? 'is-active' : ''; ?>" href="admin_pricing_estimates.php">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Total requests</span>
                            <span class="wa-stat-value" data-count="__all"><?php echo $tab_counts['all']; ?></span>
                            <span class="wa-stat-sub">All statuses</span>
                        </span>
                    </a>
                    <a class="wa-stat tone-pending <?php echo $status === 'pending' ? 'is-active' : ''; ?>" href="admin_pricing_estimates.php?status=pending">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Pending</span>
                            <span class="wa-stat-value" data-count="pending"><?php echo $tab_counts['pending']; ?></span>
                            <span class="wa-stat-sub">Waiting to be priced</span>
                        </span>
                    </a>
                    <a class="wa-stat tone-quoted <?php echo $status === 'quoted' ? 'is-active' : ''; ?>" href="admin_pricing_estimates.php?status=quoted">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-circle-check"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Checked</span>
                            <span class="wa-stat-value" data-count="quoted"><?php echo $tab_counts['quoted']; ?></span>
                            <span class="wa-stat-sub">Priced and sent back</span>
                        </span>
                    </a>
                    <a class="wa-stat tone-cancelled <?php echo $status === 'cancelled' ? 'is-active' : ''; ?>" href="admin_pricing_estimates.php?status=cancelled">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-ban"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Cancelled</span>
                            <span class="wa-stat-value" data-count="cancelled"><?php echo $tab_counts['cancelled']; ?></span>
                            <span class="wa-stat-sub">Closed without a quote</span>
                        </span>
                    </a>
                </section>

                <!-- 3 + 4. Search, filter and status navigation (one control) -->
                <section class="wa-filterbar" aria-label="Search and filter pricing requests">
                    <form class="wa-filterbar-form" method="get" action="admin_pricing_estimates.php" role="search">
                        <div class="wa-search">
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" name="search" id="requestSearch" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by request ID or customer" aria-label="Search pricing requests by request ID or customer" autocomplete="off">
                            <?php if ($search !== ''): ?>
                                <a class="wa-search-clear" href="<?php echo build_query_url(['search' => '', 'page' => '']); ?>" aria-label="Clear search" title="Clear search">
                                    <i class="fas fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="wa-select">
                            <label class="sr-only" for="requestStatusFilter">Filter by status</label>
                            <select name="status" id="requestStatusFilter">
                                <option value="">All statuses</option>
                                <?php foreach ($STATUS_LABELS as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $status === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                        <?php if ($status !== '' || $search !== ''): ?>
                            <a href="admin_pricing_estimates.php" class="btn btn-ghost"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <nav class="wa-tabs" aria-label="Filter pricing requests by status">
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

                <!-- 5 + 6. Requests data and pagination -->
                <section class="wa-datacard" aria-labelledby="requestsHeading">
                    <div class="wa-datacard-head">
                        <div>
                            <h2 class="wa-datacard-title" id="requestsHeading"><?php echo $status !== '' ? htmlspecialchars($STATUS_LABELS[$status]) . ' requests' : 'All pricing requests'; ?></h2>
                            <p class="wa-datacard-sub">
                                <?php if ($total_requests > 0): ?>
                                    Showing <?php echo $range_from; ?>&ndash;<?php echo $range_to; ?> of <?php echo $total_requests; ?> &middot; newest first
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
                            <caption class="sr-only">Pricing requests</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Request</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col" class="is-num">Estimated</th>
                                    <th scope="col" class="is-num">Final price</th>
                                    <th scope="col">Status</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody id="requestsTable">
                                <?php if (empty($pricing_requests)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-row">
                                            <div class="wa-empty">
                                                <span class="wa-empty-icon" aria-hidden="true"><i class="fas fa-inbox"></i></span>
                                                <div class="wa-empty-title">No pricing requests found</div>
                                                <p class="wa-empty-text">
                                                    <?php echo ($status !== '' || $search !== '') ? 'Nothing matches the current search or status. Try a different term or clear the filters.' : 'Pricing requests will appear here as customers submit them.'; ?>
                                                </p>
                                                <?php if ($status !== '' || $search !== ''): ?>
                                                    <a href="admin_pricing_estimates.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left" aria-hidden="true"></i> Clear filters</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($pricing_requests as $request):
                                    $rid = (int) $request['id'];
                                    $customer_name = pr_customer_name($request);
                                    $item_ids = pr_selected_item_ids($request['selected_items']);
                                    $requested_ts = strtotime($request['request_date']);
                                    $notes_text = trim((string) ($request['admin_notes'] ?? ''));

                                    $quote_items = [];
                                    foreach ($item_ids as $iid) {
                                        $info = $item_info[$iid] ?? null;
                                        $prefill = $existing_item_prices[$rid][$iid] ?? null;
                                        $quote_items[] = [
                                            'id' => $iid,
                                            'label' => $info ? $info['product_name'] . ' ×' . $info['quantity'] : ('Item #' . $iid),
                                            'price' => $prefill !== null ? (float) $prefill : null,
                                        ];
                                    }
                                    $quote_data = [
                                        'items' => $quote_items,
                                        'notes' => (string) ($request['admin_notes'] ?? ''),
                                        'estimated' => (float) $request['estimated_total'],
                                    ];
                                ?>
                                    <tr class="ord-row request-row" id="request-row-<?php echo $rid; ?>"
                                        data-status="<?php echo htmlspecialchars($request['status']); ?>"
                                        data-customer="<?php echo htmlspecialchars($customer_name); ?>"
                                        data-date="<?php echo date('M j, Y · g:i A', $requested_ts); ?>"
                                        data-quote="<?php echo htmlspecialchars(json_encode($quote_data), ENT_QUOTES); ?>">
                                        <td class="ord-col-order">
                                            <button type="button" class="ord-id" onclick="openRequestPanel(<?php echo $rid; ?>)"
                                                aria-label="View pricing request #<?php echo $rid; ?>">#<?php echo $rid; ?></button>
                                            <div class="ord-meta">
                                                <time datetime="<?php echo date('c', $requested_ts); ?>"><?php echo date('M j, Y', $requested_ts); ?> &middot; <?php echo date('g:i A', $requested_ts); ?></time>
                                            </div>
                                        </td>
                                        <td class="ord-col-customer">
                                            <div class="ord-customer">
                                                <span class="wa-avatar" aria-hidden="true"><?php echo htmlspecialchars(od_initials($customer_name)); ?></span>
                                                <div>
                                                    <div class="ord-customer-name"><?php echo htmlspecialchars($customer_name); ?></div>
                                                    <div class="ord-meta"><?php echo htmlspecialchars($request['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="ord-col-payment is-num">
                                            <span class="ord-total">&#8369;<?php echo number_format($request['estimated_total'], 2); ?></span>
                                            <div class="ord-meta"><?php echo count($item_ids); ?> item<?php echo count($item_ids) === 1 ? '' : 's'; ?></div>
                                        </td>
                                        <td class="ord-col-total is-num" data-role="final-price-cell">
                                            <?php echo pr_final_price_html($request['final_price'], $request['estimated_total']); ?>
                                        </td>
                                        <td class="ord-col-status">
                                            <span class="wa-badge tone-<?php echo htmlspecialchars($request['status']); ?>" data-role="status-badge">
                                                <?php echo htmlspecialchars(pr_status_label((string) $request['status'], $STATUS_LABELS)); ?>
                                            </span>
                                            <div class="ord-reason" data-role="status-reason" <?php echo $notes_text === '' ? 'hidden' : ''; ?>>
                                                <i class="fas fa-comment-dots" aria-hidden="true"></i>
                                                <span data-role="status-reason-text"><?php echo htmlspecialchars($notes_text); ?></span>
                                            </div>
                                        </td>
                                        <td class="ord-col-actions">
                                            <div class="ord-actions">
                                                <button type="button" class="btn btn-sm btn-outline" onclick="openRequestPanel(<?php echo $rid; ?>)">
                                                    <i class="fas fa-eye" aria-hidden="true"></i> View
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline btn-icon" onclick="openQuoteDialog(<?php echo $rid; ?>)"
                                                    aria-label="Edit quote for request #<?php echo $rid; ?>" title="Edit quote">
                                                    <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline btn-icon pq-delete" onclick="confirmDeleteRequest(<?php echo $rid; ?>, this)"
                                                    aria-label="Delete request #<?php echo $rid; ?>" title="Delete request">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="wa-datacard-foot">
                        <span class="wa-datacard-foot-text">Showing <?php echo count($pricing_requests); ?> of <?php echo $total_requests; ?> requests</span>
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

    <!-- Request detail slide-over panel -->
    <div class="panel-overlay" id="panelOverlay" onclick="closeRequestPanel()"></div>
    <aside class="slide-panel" id="slidePanel" role="dialog" aria-modal="true" aria-labelledby="panelTitle" aria-hidden="true">
        <div class="panel-header">
            <div class="panel-heading">
                <div class="panel-title-row">
                    <h2 id="panelTitle">Pricing Request</h2>
                    <span id="panelHeadBadge"></span>
                </div>
                <p class="panel-subtitle" id="panelSubtitle"></p>
            </div>
            <button type="button" class="panel-close" id="panelClose" onclick="closeRequestPanel()" aria-label="Close request details"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="panel-status-bar" id="panelStatusBar"></div>
        <div class="panel-body" id="panelBody" aria-live="polite">
            <div class="od-skeleton" aria-hidden="true">
                <div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:40%"></span><span class="wa-skel"></span><span class="wa-skel" style="width:70%"></span></div></div>
            </div>
        </div>
    </aside>

    <!-- Quote dialog (opened from a row's "Edit quote" button) -->
    <div class="modal" id="quoteModal" role="dialog" aria-modal="true" aria-labelledby="quoteModalTitle">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <div>
                    <h2 id="quoteModalTitle">Edit quote</h2>
                    <p class="ord-dialog-sub" id="quoteModalSub"></p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('quoteModal')" aria-label="Close">&times;</button>
            </div>
            <form id="quoteForm" novalidate>
                <div class="modal-body">
                    <fieldset class="ord-options">
                        <legend class="sr-only">Status</legend>
                        <?php foreach ($STATUS_LABELS as $val => $label): ?>
                            <label class="ord-option tone-<?php echo $val; ?>">
                                <input type="radio" class="sr-only" name="dialog_status" value="<?php echo $val; ?>">
                                <span class="wa-dot" aria-hidden="true"></span>
                                <span class="ord-option-label"><?php echo htmlspecialchars($label); ?></span>
                                <i class="fas fa-check ord-option-check" aria-hidden="true"></i>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <div class="pq-field">
                        <div class="pq-field-head">
                            <span class="pq-label">Price per item</span>
                            <button type="button" class="btn btn-sm btn-ghost" onclick="fillEvenPrices()"
                                title="Split the estimated total evenly across every item that doesn't have a price yet">
                                <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Split evenly
                            </button>
                        </div>
                        <div class="pq-items" id="quoteItems"></div>
                        <div class="pq-total">
                            <span>Total</span>
                            <strong id="quoteTotal">&#8369;0.00</strong>
                        </div>
                    </div>

                    <div class="pq-field">
                        <label class="pq-label" for="quoteNotes">Admin notes</label>
                        <textarea class="pq-input" id="quoteNotes" rows="3" placeholder="Add a note for this request"></textarea>
                        <p class="ord-field-hint">Saved with the request and shown in the requests list.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('quoteModal')">Close</button>
                    <button type="submit" class="btn btn-primary" id="quoteSubmit"><span id="quoteSubmitLabel">Save quote</span></button>
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
            flash: {
                message: <?php echo isset($_SESSION['message']) ? esc_js($_SESSION['message']) : 'null'; ?>,
                error: <?php echo isset($_SESSION['error']) ? esc_js($_SESSION['error']) : 'null'; ?>
            },
            data: <?php echo json_encode($wa_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        };
        <?php unset($_SESSION['message'], $_SESSION['error']); ?>
    </script>
    <script src="../../assets/js/website_admin.js"></script>

</body>

</html>