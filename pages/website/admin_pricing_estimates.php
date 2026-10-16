<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../permissions.php';
require_once 'admin_helpers.php';

/**
 * Customer's estimated price for each item of a request: item_id => amount or null.
 * The cart row's price column is read, and it is only trusted when it adds up to the
 * request's estimated total (a line total, or a unit price x quantity). A request with a
 * single item simply uses the request estimate. When nothing can be trusted the item
 * shows no estimate instead of a wrong one.
 */
function pricing_item_estimates(array $rows, float $request_total, int $expected_count): array
{
    $out = array_fill_keys(array_keys($rows), null);
    if (!$rows) {
        return $out;
    }
    if ($expected_count === 1 && count($rows) === 1) {
        return [array_key_first($rows) => round($request_total, 2)];
    }

    $candidates = [];
    $sets = [
        'line' => ['estimated_price', 'estimated_total', 'total_price', 'line_total', 'subtotal', 'total', 'price'],
        'unit' => ['estimated_unit_price', 'unit_price', 'price'],
    ];
    foreach ($sets as $kind => $cols) {
        foreach ($cols as $col) {
            $vals = [];
            foreach ($rows as $id => $r) {
                if (!isset($r[$col]) || !is_numeric($r[$col])) {
                    continue 2;
                }
                $v = (float) $r[$col];
                if ($kind === 'unit') {
                    $v *= max((int) ($r['quantity'] ?? 1), 1);
                }
                $vals[$id] = round($v, 2);
            }
            // A plain 'price' column could be a unit price or a line total, so it is only trusted when it adds up.
            $candidates[] = ['vals' => $vals, 'sure' => $col !== 'price'];
        }
    }

    $complete = count($rows) === $expected_count;
    foreach ($candidates as $c) {
        if ($complete && abs(array_sum($c['vals']) - $request_total) < 0.01) {
            return $c['vals'];
        }
    }
    // Some items of the request are gone (already ordered or removed), so the sum can't be checked.
    if (!$complete) {
        foreach ($candidates as $c) {
            if ($c['sure']) {
                return $c['vals'];
            }
        }
    }
    // Last resort: the product's catalog price (products_offered.price, the same "From" price
    // sub-main.php shows) x quantity. This is a base price, so it ignores size/finish/paper options.
    foreach ($rows as $id => $r) {
        if (isset($r['product_price']) && is_numeric($r['product_price'])) {
            $out[$id] = round((float) $r['product_price'] * max((int) ($r['quantity'] ?? 1), 1), 2);
        }
    }
    return $out;
}

// Check that the user is logged in and is staff (admin, employee or super admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee', 'super_admin'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// CSRF protection: every POST on this page must carry this session's token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

// ---------------------------------------------------------------------
// Request details drawer: same-file AJAX endpoint, mirrors admin_orders.php
// (?ajax=get_order_details) so both pages share the .slide-panel / .od-* styles.
// ---------------------------------------------------------------------
function render_item_customization(array $item): void
{
    $category = $item['product_category'] ?? '';
    $product_name = $item['product_name'] ?? '';
    $size = $item['size_option'] ?? null;
    $color = $item['color_option'] ?? null;

    if ($size) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts': cust_row('T-shirt size', $item['tshirt_size_name'] ?? $size); break;
                case 'Tote Bag': cust_row('Tote bag size', $item['tote_size_name'] ?? $size); break;
                case 'Paper Bag':
                    $dimensions = $item['paperbag_dimensions'] ?? '';
                    cust_row('Paper bag size', ($item['paperbag_size_name'] ?? $size) . ($dimensions ? ' (' . $dimensions . ')' : ''));
                    break;
                case 'Mug': cust_row('Mug size', $item['mug_size_name'] ?? $size); break;
                default: cust_row('Size', $size);
            }
        } else {
            cust_row('Size', $size);
        }
        if (!empty($item['custom_size'])) cust_row('Custom size', $item['custom_size']);
    }

    if ($color) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts': cust_row('T-shirt color', $item['tshirt_color_name'] ?? $color); break;
                case 'Tote Bag': cust_row('Tote bag color', $item['tote_color_name'] ?? $color); break;
                case 'Mug': cust_row('Mug color', $item['mug_color_name'] ?? $color); break;
                case 'Paper Bag': cust_row('Color', 'Brown'); break;
                default: cust_row('Color', $color);
            }
        } else {
            cust_row('Color', $color);
        }
        if (!empty($item['custom_color'])) cust_row('Custom color', $item['custom_color']);
    }

    if ($category !== 'Other Services') {
        if (!empty($item['finish_option_name'])) cust_row('Finish', $item['finish_option_name']);
        if (!empty($item['paper_option_name'])) cust_row('Paper', $item['paper_option_name']);
        if (!empty($item['binding_option_name'])) cust_row('Binding', $item['binding_option_name']);
        if (!empty($item['layout_option_name'])) cust_row('Layout', $item['layout_option_name']);
        if (!empty($item['gsm_option'])) cust_row('GSM', $item['gsm_option']);
        if (!empty($item['layout_details'])) cust_row('Layout details', $item['layout_details'], true);
    }
}

function render_request_panel_html(mysqli $inventory, int $request_id): string
{
    $stmt = $inventory->prepare("SELECT pr.*, u.username FROM pricing_requests pr JOIN users u ON pr.user_id = u.id WHERE pr.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    if (!$req) {
        return '';
    }

    $item_ids = json_decode($req['selected_items'], true);
    $item_ids = is_array($item_ids) ? array_values(array_unique(array_map('intval', $item_ids))) : [];

    // Cart items behind this request. Same option joins as the order drawer;
    // falls back to a plain query if this database lacks any of those tables.
    $items = [];
    if ($item_ids) {
        $ph = implode(',', array_fill(0, count($item_ids), '?'));
        $types = str_repeat('i', count($item_ids));
        $full_sql = "SELECT ci.*, p.product_name, p.price AS product_price, p.category AS product_category,
                            po.option_name AS paper_option_name, fo.option_name AS finish_option_name,
                            bo.option_name AS binding_option_name, lo.option_name AS layout_option_name,
                            ts.size_name AS tshirt_size_name, tc.color_name AS tshirt_color_name,
                            tos.size_name AS tote_size_name, toc.color_name AS tote_color_name,
                            pbs.size_name AS paperbag_size_name, pbs.dimensions AS paperbag_dimensions,
                            ms.size_name AS mug_size_name, mc.color_name AS mug_color_name
                     FROM cart_items ci
                     JOIN products_offered p ON ci.product_id = p.id
                     LEFT JOIN paper_options po ON ci.paper_option = po.id
                     LEFT JOIN finish_options fo ON ci.finish_option = fo.id
                     LEFT JOIN binding_options bo ON ci.binding_option = bo.id
                     LEFT JOIN layout_options lo ON ci.layout_option = lo.id
                     LEFT JOIN tshirt_sizes ts ON (p.category = 'Other Services' AND p.product_name = 'T-Shirts' AND ci.size_option = ts.id)
                     LEFT JOIN tshirt_colors tc ON (p.category = 'Other Services' AND p.product_name = 'T-Shirts' AND ci.color_option = tc.id)
                     LEFT JOIN totesize_options tos ON (p.category = 'Other Services' AND p.product_name = 'Tote Bag' AND ci.size_option = tos.id)
                     LEFT JOIN totecolor_options toc ON (p.category = 'Other Services' AND p.product_name = 'Tote Bag' AND ci.color_option = toc.id)
                     LEFT JOIN paperbag_size_options pbs ON (p.category = 'Other Services' AND p.product_name = 'Paper Bag' AND ci.size_option = pbs.id)
                     LEFT JOIN mug_size_options ms ON (p.category = 'Other Services' AND p.product_name = 'Mug' AND ci.size_option = ms.id)
                     LEFT JOIN mug_color_options mc ON (p.category = 'Other Services' AND p.product_name = 'Mug' AND ci.color_option = mc.id)
                     WHERE ci.item_id IN ($ph)";
        $plain_sql = "SELECT ci.*, p.product_name, p.price AS product_price FROM cart_items ci JOIN products_offered p ON ci.product_id = p.id WHERE ci.item_id IN ($ph)";
        foreach ([$full_sql, $plain_sql] as $sql) {
            try {
                $istmt = $inventory->prepare($sql);
                $istmt->bind_param($types, ...$item_ids);
                $istmt->execute();
                $items = $istmt->get_result()->fetch_all(MYSQLI_ASSOC);
                break;
            } catch (Throwable $e) {
                $items = [];
            }
        }
    }

    // Prices already quoted per item.
    $quoted = [];
    $qstmt = $inventory->prepare("SELECT cart_item_id, quoted_price FROM pricing_requests_items WHERE pricing_request_id = ?");
    $qstmt->bind_param("i", $request_id);
    $qstmt->execute();
    foreach ($qstmt->get_result()->fetch_all(MYSQLI_ASSOC) as $q) {
        $quoted[(int) $q['cart_item_id']] = $q['quoted_price'];
    }

    // Customer profile (same tables the order drawer uses)
    $cust = [];
    try {
        $cstmt = $inventory->prepare("SELECT pc.first_name, pc.last_name, pc.contact_number AS personal_contact, pc.city AS personal_city,
                    cc.company_name, cc.contact_person, cc.contact_number AS company_contact, cc.city AS company_city
                FROM users u
                LEFT JOIN personal_customers pc ON u.id = pc.user_id
                LEFT JOIN company_customers cc ON u.id = cc.user_id
                WHERE u.id = ?");
        $uid = (int) $req['user_id'];
        $cstmt->bind_param("i", $uid);
        $cstmt->execute();
        $cust = $cstmt->get_result()->fetch_assoc() ?: [];
    } catch (Throwable $e) {
        $cust = [];
    }
    $is_company = !empty($cust['company_name']) && empty($cust['first_name']);
    $display_name = $is_company ? $cust['company_name'] : trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? ''));
    if ($display_name === '') {
        $display_name = $req['username'];
    }
    $phone = $is_company ? ($cust['company_contact'] ?? '') : ($cust['personal_contact'] ?? '');
    $city = $is_company ? ($cust['company_city'] ?? '') : ($cust['personal_city'] ?? '');
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $display_name) ?: '?', 0, 2));

    $status_label = $req['status'] === 'quoted' ? 'Checked' : ucfirst($req['status']);
    $estimated = (float) $req['estimated_total'];
    $has_final = $req['final_price'] !== null && (float) $req['final_price'] > 0;
    $final = (float) $req['final_price'];
    $diff = $final - $estimated;
    $units = 0;
    $est_rows = [];
    foreach ($items as $it) {
        $units += (int) ($it['quantity'] ?? 0);
        $est_rows[(int) $it['item_id']] = $it;
    }
    $item_estimates = pricing_item_estimates($est_rows, $estimated, count($items));

    ob_start();
    ?>
    <section class="od-section od-summary" aria-labelledby="odOverview">
        <div class="od-summary-head">
            <div>
                <h3 class="od-summary-no" id="odOverview">Request #<?php echo (int) $req['id']; ?></h3>
                <p class="od-summary-meta">Requested <?php echo date('M j, Y, g:i A', strtotime($req['request_date'])); ?> &middot; <?php echo count($items); ?> line<?php echo count($items) === 1 ? '' : 's'; ?>, <?php echo $units; ?> unit<?php echo $units === 1 ? '' : 's'; ?></p>
            </div>
            <div class="od-summary-total"><span>Estimated</span><strong>&#8369;<?php echo number_format($estimated, 2); ?></strong></div>
        </div>
        <div class="od-statuses">
            <div class="od-status"><span class="od-status-label">Request</span><span class="status-badge status-<?php echo htmlspecialchars($req['status']); ?>"><?php echo htmlspecialchars($status_label); ?></span></div>
        </div>
    </section>

    <section class="od-section" aria-labelledby="odQuote">
        <h3 class="od-title" id="odQuote">Quote</h3>
        <dl class="od-money">
            <div class="od-money-tile"><dt>Customer's price expectation</dt><dd>&#8369;<?php echo number_format($estimated, 2); ?></dd></div>
            <div class="od-money-tile<?php echo $has_final ? ' is-due' : ''; ?>"><dt>Final quote</dt><dd><?php echo $has_final ? '&#8369;' . number_format($final, 2) : 'Not set'; ?></dd></div>
            <?php if ($has_final): ?>
                <div class="od-money-tile"><dt>Difference</dt><dd><?php echo $diff == 0 ? 'No change' : ($diff > 0 ? '+' : '-') . '&#8369;' . number_format(abs($diff), 2); ?></dd></div>
            <?php endif; ?>
        </dl>
        <?php if (!empty($req['admin_notes'])): ?>
            <div class="od-sub-title">Admin notes</div>
            <p><?php echo nl2br(htmlspecialchars($req['admin_notes'])); ?></p>
        <?php endif; ?>
    </section>

    <section class="od-section" aria-labelledby="odCustomer">
        <h3 class="od-title" id="odCustomer">Customer</h3>
        <div class="od-customer">
            <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></span>
            <div>
                <span class="od-customer-name"><?php echo htmlspecialchars($display_name); ?></span>
            </div>
        </div>
        <dl class="od-grid">
            <div class="od-kv"><dt>Username</dt><dd><?php echo htmlspecialchars($req['username']); ?></dd></div>
            <div class="od-kv"><dt>User ID</dt><dd><?php echo (int) $req['user_id']; ?></dd></div>
            <?php if ($is_company && !empty($cust['contact_person'])): ?>
                <div class="od-kv"><dt>Contact person</dt><dd><?php echo htmlspecialchars($cust['contact_person']); ?></dd></div>
            <?php endif; ?>
            <div class="od-kv"><dt>Phone</dt><dd><?php echo $phone !== '' ? htmlspecialchars($phone) : '<span class="text-muted">Not provided</span>'; ?></dd></div>
            <div class="od-kv"><dt>City</dt><dd><?php echo $city !== '' ? htmlspecialchars($city) : '<span class="text-muted">Not provided</span>'; ?></dd></div>
        </dl>
    </section>

    <section class="od-section" aria-labelledby="odItems">
        <h3 class="od-title" id="odItems">Items <small><?php echo count($items); ?> in this request</small></h3>
        <?php if (empty($items)): ?>
            <div class="empty-state"><i class="fas fa-box-open" aria-hidden="true"></i><h3>No items on this request</h3></div>
        <?php endif; ?>
        <?php foreach ($items as $item):
            $iid = (int) $item['item_id'];
            $line_price = $quoted[$iid] ?? null;
        ?>
            <?php
            $est = $item_estimates[$iid] ?? null;
            $qty = (int) $item['quantity'];
            ?>
            <article class="od-item">
                <header class="od-item-head">
                    <div>
                        <div class="od-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="od-item-sub"><?php echo !empty($item['product_category']) ? htmlspecialchars($item['product_category']) : ''; ?></div>
                        <div class="od-item-qty"><span class="qty-chip qty-chip--lg"><span class="qty-x">&times;</span><?php echo $qty; ?></span> <span class="od-item-qty-label">unit<?php echo $qty === 1 ? '' : 's'; ?> ordered</span></div>
                    </div>
                    <div class="od-item-prices">
                        <div class="od-price-line"><span>Base Price</span><strong><?php echo $est !== null ? '&#8369;' . number_format($est, 2) : '<span class="text-muted">&mdash;</span>'; ?></strong></div>
                        <div class="od-price-line is-quote"><span>Your quote</span><strong><?php echo $line_price !== null ? '&#8369;' . number_format((float) $line_price, 2) : '<span class="text-muted">Not priced</span>'; ?></strong></div>
                        <?php if ($est !== null && $line_price !== null && abs((float) $line_price - $est) >= 0.005): $d = (float) $line_price - $est; ?>
                            <div class="od-price-diff <?php echo $d > 0 ? 'is-up' : 'is-down'; ?>"><?php echo $d > 0 ? '+' : '-'; ?>&#8369;<?php echo number_format(abs($d), 2); ?> vs estimate</div>
                        <?php endif; ?>
                    </div>
                </header>
                <div class="od-item-body">
                    <?php
                    ob_start();
                    render_item_customization($item);
                    $cust_html = ob_get_clean();
                    if ($cust_html !== ''): ?>
                        <div>
                            <div class="od-sub-title">Customizations</div>
                            <div class="cust-grid"><?php echo $cust_html; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item['user_layout_files'])):
                        $layout_files = json_decode($item['user_layout_files'], true); ?>
                        <div>
                            <div class="od-sub-title">Customer layout files</div>
                            <?php if (is_array($layout_files) && $layout_files): ?>
                                <div class="file-tiles">
                                    <?php foreach (array_values($layout_files) as $n => $file_path) {
                                        // Stored as ../../assets/uploads/user_layouts/<id>/file; the helper resolves
                                        // inside assets/uploads and shows "file not found" for anything outside it.
                                        $clean_path = preg_replace('#^(\.\./)+#', '', (string) $file_path);
                                        $clean_path = preg_replace('#^assets/#', '', $clean_path);
                                        $clean_path = preg_replace('#^uploads/#', '', $clean_path);
                                        render_design_preview($clean_path, count($layout_files) > 1 ? 'Layout file ' . ($n + 1) : 'Layout file', '');
                                    } ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No valid layout files found.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item['design_image'])):
                        $designData = $item['design_image'];
                        $frontMockup = $backMockup = $uploadedFile = '';
                        $frontUploadedFiles = $backUploadedFiles = [];
                        $designArray = json_decode($designData, true);
                        if (!(json_last_error() === JSON_ERROR_NONE && is_array($designArray)) && preg_match('/\{.*\}/', $designData)) {
                            $designArray = json_decode(stripslashes(str_replace('\"', '"', $designData)), true);
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
                        if ($frontMockup || $backMockup || $uploadedFile || $frontUploadedFiles || $backUploadedFiles): ?>
                            <div>
                                <div class="od-sub-title">Design files</div>
                                <div class="file-tiles">
                                    <?php
                                    if ($uploadedFile) render_design_preview($uploadedFile, 'Original file', '');
                                    foreach ($frontUploadedFiles as $n => $file) {
                                        render_design_preview($file, count($frontUploadedFiles) > 1 ? 'Front original ' . ($n + 1) : 'Front original', '');
                                    }
                                    foreach ($backUploadedFiles as $n => $file) {
                                        render_design_preview($file, count($backUploadedFiles) > 1 ? 'Back original ' . ($n + 1) : 'Back original', '');
                                    }
                                    if ($frontMockup) render_design_preview($frontMockup, 'Front mockup', 'var(--primary)');
                                    if ($backMockup) render_design_preview($backMockup, 'Back mockup', 'var(--primary)');
                                    ?>
                                </div>
                            </div>
                        <?php endif;
                    endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}

if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['ajax'] === 'get_request_details') {
    header('Content-Type: application/json; charset=utf-8');
    $rid = (int) ($_GET['id'] ?? 0);
    $html = $rid ? render_request_panel_html($inventory, $rid) : '';
    if ($html === '') {
        echo json_encode(['success' => false, 'message' => 'Pricing request not found.']);
        exit;
    }
    $sstmt = $inventory->prepare("SELECT status FROM pricing_requests WHERE id = ?");
    $sstmt->bind_param("i", $rid);
    $sstmt->execute();
    $srow = $sstmt->get_result()->fetch_assoc();
    echo json_encode([
        'success' => true,
        'request_id' => $rid,
        'status' => $srow['status'],
        'status_label' => $srow['status'] === 'quoted' ? 'Checked' : ucfirst($srow['status']),
        'html' => $html,
    ]);
    exit;
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
        $is_ajax = isset($_POST['ajax']);
        $fail = function (string $msg) use ($is_ajax) {
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $msg]);
            } else {
                $_SESSION['error'] = $msg;
                header("Location: admin_pricing_estimates.php");
            }
            exit;
        };

        $request_id = (int) ($_POST['request_id'] ?? 0);
        $new_status = (string) ($_POST['status'] ?? '');
        $admin_notes = trim((string) ($_POST['admin_notes'] ?? ''));
        // Per-item prices entered by the admin: item_price[item_id] => price.
        $item_prices = isset($_POST['item_price']) && is_array($_POST['item_price']) ? $_POST['item_price'] : [];

        // Only the statuses the form offers are accepted.
        if (!in_array($new_status, ['pending', 'quoted', 'cancelled'], true)) {
            $fail('Choose a valid status.');
        }

        // The request must exist.
        $request_stmt = $inventory->prepare("SELECT * FROM pricing_requests WHERE id = ?");
        $request_stmt->bind_param("i", $request_id);
        $request_stmt->execute();
        $request_data = $request_stmt->get_result()->fetch_assoc();
        if ($request_id < 1 || !$request_data) {
            $fail('Pricing request not found.');
        }

        // Every typed price must be a real, non-negative amount.
        foreach ($item_prices as $typed) {
            $typed = trim((string) $typed);
            if ($typed === '') {
                continue;
            }
            if (!is_numeric($typed) || (float) $typed < 0 || (float) $typed > 99999999.99) {
                $fail('Enter valid prices, for example 1250.00. Prices cannot be negative.');
            }
        }

        $selected_items = json_decode((string) $request_data['selected_items'], true);
        $final_price = 0; // recomputed below as the sum of each item's price
        $returned_item_prices = []; // item_id => price actually saved, sent back to the browser

        // Everything below is saved together or not at all, so a failure
        // half-way never leaves some items quoted and the request not.
        try {
            $inventory->begin_transaction();

            if (is_array($selected_items)) {
                $item_count = max(count($selected_items), 1);

                foreach ($selected_items as $item_id) {
                    $item_id = (int) $item_id;

                    // Check if record already exists in pricing_requests_items, and
                    // remember whatever price was already quoted for it (if any).
                    $check_stmt = $inventory->prepare("SELECT id, quoted_price FROM pricing_requests_items WHERE pricing_request_id = ? AND cart_item_id = ?");
                    $check_stmt->bind_param("ii", $request_id, $item_id);
                    $check_stmt->execute();
                    $existing_item = $check_stmt->get_result()->fetch_assoc(); // null if this item has no row yet

                    // Decide the price for THIS item, in order of priority:
                    // 1) a price the admin explicitly typed on this save
                    // 2) whatever price was already quoted for it previously
                    // 3) if it's being quoted for the very first time with no
                    //    price at all, fall back to an even split of the
                    //    estimated total so it doesn't show as free
                    // 4) otherwise (e.g. being cancelled/left pending and never
                    //    priced), leave it unpriced rather than inventing a number
                    if (isset($item_prices[$item_id]) && trim((string) $item_prices[$item_id]) !== '') {
                        $price_per_item = round((float) $item_prices[$item_id], 2);
                    } elseif ($existing_item && $existing_item['quoted_price'] !== null) {
                        $price_per_item = (float) $existing_item['quoted_price'];
                    } elseif ($new_status === 'quoted') {
                        $price_per_item = round((float) $request_data['estimated_total'] / $item_count, 2);
                    } else {
                        $price_per_item = null;
                    }
                    $final_price += $price_per_item ?? 0;
                    $returned_item_prices[$item_id] = $price_per_item;

                    if ($existing_item) {
                        $item_stmt = $inventory->prepare("UPDATE pricing_requests_items SET status = ?, admin_notes = ?, quoted_price = ?, updated_at = NOW() WHERE pricing_request_id = ? AND cart_item_id = ?");
                        $item_stmt->bind_param("ssdii", $new_status, $admin_notes, $price_per_item, $request_id, $item_id);
                    } else {
                        $item_stmt = $inventory->prepare("INSERT INTO pricing_requests_items (pricing_request_id, cart_item_id, admin_notes, quoted_price, status) VALUES (?, ?, ?, ?, ?)");
                        $item_stmt->bind_param("iisds", $request_id, $item_id, $admin_notes, $price_per_item, $new_status);
                    }
                    $item_stmt->execute();

                    // Update cart_items table for quoted status
                    if ($new_status === 'quoted') {
                        $cart_stmt = $inventory->prepare("UPDATE cart_items SET quoted_price = ?, price_updated_by_admin = 1, price_updated_at = NOW() WHERE item_id = ?");
                        $cart_stmt->bind_param("di", $price_per_item, $item_id);
                        $cart_stmt->execute();
                    } elseif ($new_status === 'cancelled') {
                        // Clear the admin price flag but leave any previously
                        // quoted price untouched: cancelling shouldn't rewrite
                        // pricing history.
                        $cart_stmt = $inventory->prepare("UPDATE cart_items SET price_updated_by_admin = 0 WHERE item_id = ?");
                        $cart_stmt->bind_param("i", $item_id);
                        $cart_stmt->execute();
                    }
                }
            }

            // final_price is the sum of the individual item prices above, so it
            // can never drift from what each item actually shows.
            $stmt = $inventory->prepare("UPDATE pricing_requests SET status = ?, final_price = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sdsi", $new_status, $final_price, $admin_notes, $request_id);
            $stmt->execute();
            $inventory->commit();
            $success = true;
        } catch (Throwable $e) {
            try {
                $inventory->rollback();
            } catch (Throwable $ignored) {
            }
            error_log('update_pricing_status failed: ' . $e->getMessage());
            $success = false;
        }

        // AJAX callers (the inline row form) get JSON back and update the
        // row in place; anything still POSTing the old-fashioned way falls
        // back to the session-flash + redirect behavior below.
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => $success,
                'message' => $success ? "Pricing request #$request_id updated successfully!" : 'Failed to update pricing request. Nothing was changed.',
                'request_id' => $request_id,
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
            $_SESSION['error'] = "Failed to update pricing request. Nothing was changed.";
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
          WHERE pr.status <> 'completed'
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

    $item_query = "SELECT ci.*, p.product_name, p.price AS product_price
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
            'row'          => $row,
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

<?php
require_once 'admin_layout.php';

admin_layout_start([
    'page' => 'pricing',
    'title' => 'Pricing estimates',
    'subtitle' => 'Review customer requests, quote each item, and send back a final price.',
]);
?>

<div class="stats-cards">
    <div class="stat-card total">
        <span class="stat-label">Total requests</span>
        <span class="stat-number" id="statTotal"><?php echo (int) $stats['total_requests']; ?></span>
    </div>
    <div class="stat-card pending">
        <span class="stat-label">Pending</span>
        <span class="stat-number" id="statPending"><?php echo (int) $stats['pending_requests']; ?></span>
    </div>
    <div class="stat-card completed">
        <span class="stat-label">Checked</span>
        <span class="stat-number" id="statQuoted"><?php echo (int) $stats['quoted_requests']; ?></span>
    </div>
    <div class="stat-card cancelled">
        <span class="stat-label">Cancelled</span>
        <span class="stat-number" id="statCancelled"><?php echo (int) $stats['cancelled_requests']; ?></span>
    </div>
</div>

<div class="toolbar">
    <div class="search-field">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="text" id="searchInput" placeholder="Search by request ID, customer or email" aria-label="Search pricing requests" autocomplete="off">
    </div>
    <label class="sr-only" for="statusFilter">Status</label>
    <select id="statusFilter">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="quoted">Checked</option>
        <option value="cancelled">Cancelled</option>
    </select>
    <button type="button" class="btn btn-secondary" id="clearRequestFilters"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset</button>
    <span class="toolbar-spacer"></span>
    <span class="cell-sub" aria-live="polite"><strong id="requestCount"><?php echo count($pricing_requests); ?></strong> shown</span>
</div>

<div class="pricing-table">
    <?php if (empty($pricing_requests)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice-dollar" aria-hidden="true"></i>
            <h3>No pricing requests yet</h3>
            <p>When a customer asks for a quote from their cart, it will appear here.</p>
        </div>
    <?php else: ?>
    <table class="table">
        <caption class="sr-only">Pricing requests</caption>
        <thead>
            <tr>
                <th scope="col">Request</th>
                <th scope="col">Customer</th>
                <th scope="col" class="num">Estimated</th>
                <th scope="col" class="num">Final quote</th>
                <th scope="col">Status</th>
                <th scope="col">Requested</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody id="requestsTable">
            <?php foreach ($pricing_requests as $request):
                $customer_name = !empty($request['company_name']) ? $request['company_name'] : (!empty($request['first_name']) ? $request['first_name'] . ' ' . $request['last_name'] :
                    $request['username']);
                $selected_items = json_decode($request['selected_items'], true);
                $selected_items = is_array($selected_items) ? $selected_items : [];
                $rid = (int) $request['id'];
            ?>
                <tr class="request-row" id="request-row-<?php echo $rid; ?>" data-status="<?php echo htmlspecialchars($request['status']); ?>">
                    <td><button type="button" class="link-btn" data-pricing-action="details" data-id="<?php echo $rid; ?>" aria-label="Open request <?php echo $rid; ?>">#<?php echo $rid; ?></button></td>
                    <td>
                        <div class="cell-stack">
                            <span class="cell-main"><?php echo htmlspecialchars($customer_name); ?></span>
                            <span class="cell-sub"><?php echo htmlspecialchars($request['username']); ?></span>
                        </div>
                    </td>
                    <td class="num">
                        <div class="cell-stack">
                            <strong>&#8369;<?php echo number_format($request['estimated_total'], 2); ?></strong>
                            <span class="cell-sub"><?php echo count($selected_items); ?> item<?php echo count($selected_items) === 1 ? '' : 's'; ?></span>
                        </div>
                    </td>
                    <td class="num">
                        <div class="cell-stack" data-role="final-price-cell">
                            <?php if ($request['final_price']): ?>
                                <strong>&#8369;<?php echo number_format($request['final_price'], 2); ?></strong>
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
                        <span class="status-badge status-<?php echo htmlspecialchars($request['status']); ?>" data-role="status-badge">
                            <?php echo $request['status'] === 'quoted' ? 'Checked' : ucfirst($request['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="cell-stack">
                            <span><?php echo date('M j, Y', strtotime($request['request_date'])); ?></span>
                            <span class="cell-sub"><?php echo date('g:i A', strtotime($request['request_date'])); ?></span>
                        </div>
                    </td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <button type="button" class="btn btn-primary btn-sm" data-pricing-action="toggle" data-id="<?php echo $rid; ?>"
                                aria-expanded="false" aria-controls="request-edit-<?php echo $rid; ?>">
                                <i class="fas fa-calculator" aria-hidden="true"></i> <span data-label>Quote</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <tr class="request-edit" id="request-edit-<?php echo $rid; ?>" hidden>
                    <td colspan="7">
                        <form method="post" class="status-form" data-estimated-total="<?php echo (float) $request['estimated_total']; ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="request_id" value="<?php echo $rid; ?>">
                            <div class="quote-panel">
                                <div>
                                    <div class="quote-col-head">
                                        <h3 class="quote-col-title">Price per item</h3>
                                        <button type="button" class="split-btn" data-pricing-action="split" title="Split the estimated total evenly across every item that doesn't have a price yet">
                                            <i class="fas fa-divide" aria-hidden="true"></i> Split evenly
                                        </button>
                                    </div>
                                    <?php
                                    $req_rows = [];
                                    foreach ($selected_items as $sel_id) {
                                        if (isset($item_info[(int) $sel_id])) {
                                            $req_rows[(int) $sel_id] = $item_info[(int) $sel_id]['row'];
                                        }
                                    }
                                    $item_estimates = pricing_item_estimates($req_rows, (float) $request['estimated_total'], count($selected_items));
                                    ?>
                                    <div class="item-price-list">
                                        <?php if (!$selected_items): ?>
                                            <p class="text-muted">This request has no items.</p>
                                        <?php else: ?>
                                            <div class="item-price-head" aria-hidden="true">
                                                <span>Item</span><span>Qty</span><span>Base Price</span><span>Your price</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php foreach ($selected_items as $iid):
                                            $iid = (int) $iid;
                                            if (!isset($item_info[$iid])) { continue; } // item already ordered/removed
                                            $info = $item_info[$iid];
                                            $prefill = $existing_item_prices[$rid][$iid] ?? '';
                                            $est = $item_estimates[$iid] ?? null;
                                        ?>
                                            <div class="item-price-row">
                                                <label class="item-price-label" for="price-<?php echo $rid; ?>-<?php echo $iid; ?>" title="<?php echo htmlspecialchars($info['product_name']); ?>"><?php echo htmlspecialchars($info['product_name']); ?></label>
                                                <span class="qty-chip" title="Quantity ordered"><span class="qty-x">&times;</span><?php echo (int) $info['quantity']; ?></span>
                                                <?php if ($est !== null): ?>
                                                    <button type="button" class="est-btn" data-pricing-action="use-estimate" data-amount="<?php echo htmlspecialchars(number_format($est, 2, '.', '')); ?>" title="Click to use this estimate as your price">&#8369;<?php echo number_format($est, 2); ?></button>
                                                <?php else: ?>
                                                    <span class="text-muted est-none" title="No per-item estimate is stored for this request">&mdash;</span>
                                                <?php endif; ?>
                                                <input type="number" id="price-<?php echo $rid; ?>-<?php echo $iid; ?>" name="item_price[<?php echo $iid; ?>]" class="price-input item-price-input"
                                                    data-item-id="<?php echo $iid; ?>"
                                                    placeholder="0.00" step="0.01" min="0"
                                                    aria-label="Your price for <?php echo htmlspecialchars($info['product_name']); ?>, quantity <?php echo (int) $info['quantity']; ?>"
                                                    value="<?php echo $prefill !== '' ? htmlspecialchars($prefill) : ''; ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="quote-col-title">Quote summary</h3>
                                    <div class="quote-summary">
                                        <div class="quote-line"><span>Customer's price expectation</span><strong>&#8369;<?php echo number_format($request['estimated_total'], 2); ?></strong></div>
                                        <div class="quote-line is-total"><span>Quoted total</span><span class="computed-total">&#8369;<?php echo number_format(array_sum($existing_item_prices[$rid] ?? []), 2); ?></span></div>
                                    </div>
                                    <p class="quote-diff" aria-live="polite"></p>

                                    <div class="quote-fields">
                                        <div class="form-group">
                                            <label for="status-<?php echo $rid; ?>">Status</label>
                                            <select name="status" id="status-<?php echo $rid; ?>" class="status-select">
                                                <option value="pending" <?php echo $request['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="quoted" <?php echo $request['status'] == 'quoted' ? 'selected' : ''; ?>>Checked</option>
                                                <option value="cancelled" <?php echo $request['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="notes-<?php echo $rid; ?>">Admin notes</label>
                                            <textarea name="admin_notes" id="notes-<?php echo $rid; ?>" class="notes-input" placeholder="Notes for this quote"><?php echo htmlspecialchars($request['admin_notes'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="quote-buttons">
                                            <button type="submit" name="update_pricing_status" class="update-btn" title="Save status, prices and notes">
                                                <i class="fas fa-floppy-disk" aria-hidden="true"></i> Save quote
                                            </button>
                                            <span class="spacer"></span>
                                            <button type="button" class="delete-btn" data-pricing-action="delete" data-id="<?php echo $rid; ?>" title="Delete request">
                                                <i class="fas fa-trash" aria-hidden="true"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="empty-state" id="requestsEmpty" hidden>
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
        <h3>No requests match</h3>
        <p>Try a different search or status.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Pagination: outside the table card, like every other admin page -->
<div id="requestsPager" hidden></div>

<?php admin_layout_end(); ?>

<!-- Request details drawer (same shell as the order details drawer in admin_orders.php) -->
<div class="panel-overlay" id="panelOverlay"></div>
<aside class="slide-panel" id="slidePanel" role="dialog" aria-modal="true" aria-labelledby="panelTitle" aria-hidden="true">
    <header class="panel-header">
        <div class="panel-heading">
            <h2 id="panelTitle">Request details</h2>
            <span class="status-badge" id="panelStatusBadge" hidden></span>
        </div>
        <button type="button" class="panel-close" id="panelClose" aria-label="Close request details" autofocus><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="panel-status-bar" id="panelStatusBar"></div>
    <div class="panel-body" id="panelBody">
        <div class="panel-loading" role="status"><i class="fas fa-circle-notch" aria-hidden="true"></i> Loading request...</div>
    </div>
</aside>

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