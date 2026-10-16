<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../permissions.php';
require_once 'admin_helpers.php';

// Check that the user is logged in and is staff (admin, employee or super admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee', 'super_admin'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// CSRF protection: every POST on this page must carry this session's token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

$VALID_STATUSES = ['pending', 'processing', 'ready_for_pickup', 'completed', 'cancelled'];
$STATUS_LABELS = [
    'pending' => 'Pending',
    'processing' => 'Processing',
    'ready_for_pickup' => 'Ready for Pickup',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

// Which status each order may move to. Cancelled is final; a Completed order can only be reopened to
// Ready for Pickup (to undo a mistake). Nothing goes back to Pending, so a payment can't be verified twice.
$ALLOWED_TRANSITIONS = [
    'pending' => ['processing', 'ready_for_pickup', 'completed', 'cancelled'],
    'processing' => ['ready_for_pickup', 'completed', 'cancelled'],
    'ready_for_pickup' => ['processing', 'completed', 'cancelled'],
    'completed' => ['ready_for_pickup'],
    'cancelled' => [],
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

/** "Match" / "Short" / "Over" badge comparing the amount on a payment proof with the order total. */
function qv_proof_badge($proof, float $total): string
{
    if ($proof === null || $proof === '') {
        return '<span class="text-muted">&mdash;</span>';
    }
    $diff = round((float) $proof - $total, 2);
    if (abs($diff) < 0.005) {
        return '<span class="status-badge status-completed">Match</span>';
    }
    $label = $diff < 0 ? 'Short' : 'Over';
    return '<span class="status-badge status-cancelled">' . $label . ' &#8369;' . number_format(abs($diff), 2) . '</span>';
}

/**
 * Balance still owed on a downpayment order (needs status, total_amount, amount_paid,
 * is_downpayment). Zero for full-payment orders, for orders whose downpayment is not
 * verified yet (Pending) and for cancelled orders. Once the downpayment is verified,
 * amount_paid holds it, so a balance is owed until amount_paid reaches the total,
 * whatever status the order has moved on to.
 */
function order_balance_owed(array $row): float
{
    if (empty($row['is_downpayment']) || in_array($row['status'] ?? '', ['pending', 'cancelled'], true)) {
        return 0.0;
    }
    $owed = round((float) $row['total_amount'] - (float) ($row['amount_paid'] ?? 0), 2);
    return $owed > 0.004 ? $owed : 0.0;
}

/**
 * Payment label (not an order status): Unpaid, Partially paid or Paid, worked out from how much of
 * the total has been verified. Returns [label, css class of an existing badge colour].
 */
function payment_label(array $row): array
{
    $total = (float) ($row['total_amount'] ?? 0);
    $paid = (float) ($row['amount_paid'] ?? 0);
    // A cancelled order is never "Paid": show the money that was received (and may need returning) instead.
    if (($row['status'] ?? '') === 'cancelled') {
        return $paid > 0.004
            ? ['₱' . number_format($paid, 2) . ' paid (cancelled)', 'status-cancelled']
            : ['Unpaid', 'status-cancelled'];
    }
    if ($total > 0 && $paid >= $total - 0.004) {
        return ['Paid', 'status-completed'];
    }
    if ($paid > 0.004) {
        return ['Partially paid', 'status-processing'];
    }
    return ['Unpaid', 'status-pending'];
}

/**
 * True when the customer has uploaded a proof that still waits for the admin: the checkout proof on a
 * Pending order, or the remaining balance proof while a balance is owed. Cancelled orders never need review.
 */
function payment_needs_review(array $row): bool
{
    if (($row['status'] ?? '') === 'cancelled') {
        return false;
    }
    if (order_balance_owed($row) > 0) {
        return !empty($row['balance_proof']);
    }
    return ($row['status'] ?? '') === 'pending' && !empty($row['payment_proof']);
}

/** Fresh payment label + balance for an order, sent back with AJAX responses so the page can update without a reload. */
function payment_snapshot($db, int $order_id): array
{
    try {
        $q = $db->prepare("SELECT status, total_amount, amount_paid, is_downpayment, payment_proof, balance_proof FROM orders WHERE order_id = ?");
        $q->bind_param("i", $order_id);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        if (!$r) {
            return [];
        }
        [$text, $class] = payment_label($r);
        return ['payment_label' => $text, 'payment_class' => $class, 'balance_owed' => order_balance_owed($r), 'needs_review' => payment_needs_review($r)];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Write one row to the payment history (order_payments, see payment_audit.sql). Throws when the table is
 * missing, so callers inside a transaction roll the payment back instead of recording it without a trace.
 */
function log_order_payment(mysqli $db, int $order_id, string $kind, string $method, float $expected, float $received, ?string $proof_file, ?string $note): void
{
    $admin_id = (int) ($_SESSION['user_id'] ?? 0);
    $stmt = $db->prepare("INSERT INTO order_payments (order_id, kind, method, amount_expected, amount_received, proof_file, note, verified_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddssi", $order_id, $kind, $method, $expected, $received, $proof_file, $note, $admin_id);
    $stmt->execute();
}

/** Payment history rows for the order panel (display only: an empty list if the table is not there yet). */
function order_payment_history(mysqli $db, int $order_id): array
{
    try {
        $stmt = $db->prepare("SELECT p.*, u.username FROM order_payments p LEFT JOIN users u ON p.verified_by = u.id WHERE p.order_id = ? ORDER BY p.id");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function payments_rollback(mysqli $db): void
{
    try {
        $db->rollback();
    } catch (Throwable $e) {
    }
}

/** Amount the admin is verifying right now: the balance if one is owed, otherwise the amount due at checkout. */
function payment_expected_now(array $row): float
{
    $owed = order_balance_owed($row);
    if ($owed > 0) {
        return $owed;
    }
    return isset($row['amount_due']) && $row['amount_due'] !== null ? (float) $row['amount_due'] : (float) $row['total_amount'];
}

/** Which saved proof amount belongs to the payment being verified (fixed whitelist, safe to put in SQL). */
function payment_proof_column(array $row): string
{
    return order_balance_owed($row) > 0 ? 'balance_proof_amount' : 'proof_amount';
}

/** True when a saved proof amount equals the expected amount. */
function proof_matches($proof, float $expected): bool
{
    return $proof !== null && $proof !== '' && abs((float) $proof - $expected) < 0.005;
}

/**
 * Render the customization tiles for a single order item (same rules as the
 * old admin_order_details.php, just laid out as labelled tiles).
 */
function render_item_customization(array $item): void
{
    $category = $item['product_category'];
    $product_name = $item['product_name'];

    if ($item['size_option']) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts':
                    cust_row('T-shirt size', $item['tshirt_size_name'] ?? $item['size_option']);
                    break;
                case 'Tote Bag':
                    cust_row('Tote bag size', $item['tote_size_name'] ?? $item['size_option']);
                    break;
                case 'Paper Bag':
                    $dimensions = $item['paperbag_dimensions'] ?? '';
                    cust_row('Paper bag size', ($item['paperbag_size_name'] ?? $item['size_option']) . ($dimensions ? ' (' . $dimensions . ')' : ''));
                    break;
                case 'Mug':
                    cust_row('Mug size', $item['mug_size_name'] ?? $item['size_option']);
                    break;
                default:
                    cust_row('Size', $item['size_option']);
            }
        } else {
            cust_row('Size', $item['size_option']);
        }
        if ($item['custom_size']) {
            cust_row('Custom size', $item['custom_size']);
        }
    }

    if ($item['color_option']) {
        if ($category === 'Other Services') {
            switch ($product_name) {
                case 'T-Shirts':
                    cust_row('T-shirt color', $item['tshirt_color_name'] ?? $item['color_option']);
                    break;
                case 'Tote Bag':
                    cust_row('Tote bag color', $item['tote_color_name'] ?? $item['color_option']);
                    break;
                case 'Mug':
                    cust_row('Mug color', $item['mug_color_name'] ?? $item['color_option']);
                    break;
                case 'Paper Bag':
                    cust_row('Color', 'Brown');
                    break;
                default:
                    cust_row('Color', $item['color_option']);
            }
        } else {
            cust_row('Color', $item['color_option']);
        }
        if ($item['custom_color']) {
            cust_row('Custom color', $item['custom_color']);
        }
    }

    if ($category !== 'Other Services') {
        if ($item['finish_option_name']) cust_row('Finish', $item['finish_option_name']);
        if ($item['paper_option_name']) cust_row('Paper', $item['paper_option_name']);
        if ($item['binding_option_name']) cust_row('Binding', $item['binding_option_name']);
        if ($item['layout_option_name']) cust_row('Layout', $item['layout_option_name']);
        if ($item['gsm_option']) cust_row('GSM', $item['gsm_option']);
        if ($item['layout_details']) cust_row('Layout details', $item['layout_details'], true);
    }
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

    // Customer profile (read-only, same tables admin_customers.php uses)
    $cust = [];
    try {
        $cstmt = $inventory->prepare("SELECT pc.first_name, pc.last_name, pc.contact_number AS personal_contact, pc.city AS personal_city,
                    cc.company_name, cc.contact_person, cc.contact_number AS company_contact, cc.city AS company_city
                FROM users u
                LEFT JOIN personal_customers pc ON u.id = pc.user_id
                LEFT JOIN company_customers cc ON u.id = cc.user_id
                WHERE u.id = ?");
        $cust_uid = (int) $order['user_id'];
        $cstmt->bind_param("i", $cust_uid);
        $cstmt->execute();
        $cust = $cstmt->get_result()->fetch_assoc() ?: [];
    } catch (Throwable $e) {
        $cust = [];
    }
    $is_company = !empty($cust['company_name']) && empty($cust['first_name']);
    $display_name = $is_company ? $cust['company_name']
        : trim(($cust['first_name'] ?? '') . ' ' . ($cust['last_name'] ?? ''));
    if ($display_name === '') {
        $display_name = $order['username'];
    }
    $phone = $is_company ? ($cust['company_contact'] ?? '') : ($cust['personal_contact'] ?? '');
    $city = $is_company ? ($cust['company_city'] ?? '') : ($cust['personal_city'] ?? '');
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $display_name) ?: '?', 0, 2));

    $has_proof = !empty($order['payment_proof']);
    $proof_ok = $has_proof && payment_proof_path((int) $order['user_id'], (string) $order['payment_proof']);
    $proof_url = $has_proof ? payment_proof_url((int) $order['order_id']) : '';
    $item_units = 0;
    foreach ($order_items as $it) {
        $item_units += (int) $it['quantity'];
    }

    // Payment verification figures. amount_due / is_downpayment / proof_amount only exist
    // after downpayment.sql has been run; older orders fall back to the full total.
    $is_down = !empty($order['is_downpayment']);
    $amount_paid = isset($order['amount_paid']) && $order['amount_paid'] !== null ? (float) $order['amount_paid'] : 0.0;
    $can_edit_proof = can('web_orders');
    // Balance owed after the downpayment was verified. Stays true even if the order has moved
    // on to Processing, so the balance can still be verified from here.
    $owed = order_balance_owed($order);
    $awaiting_balance = $owed > 0;
    $due_amount = payment_expected_now($order);
    $proof_col = payment_proof_column($order);
    $proof_amount = isset($order[$proof_col]) && $order[$proof_col] !== null ? (float) $order[$proof_col] : null;
    $has_balance_proof = !empty($order['balance_proof']);
    $balance_proof_ok = $has_balance_proof && payment_proof_path((int) $order['user_id'], (string) $order['balance_proof']);
    // payment_proof.php takes &type=balance to stream the remaining balance proof instead.
    $balance_proof_url = $has_balance_proof ? payment_proof_url((int) $order['order_id']) . '&type=balance' : '';
    // The proof for the stage being verified (remaining balance proof while a balance is owed, otherwise the checkout proof).
    $stage_proof_ok = $awaiting_balance ? (bool) $balance_proof_ok : (bool) $proof_ok;
    $can_decide = $can_edit_proof && ($order['status'] === 'pending' || $awaiting_balance);
    $down_pct = rtrim(rtrim(number_format((defined('ORDER_DOWNPAYMENT_RATE') ? (float) ORDER_DOWNPAYMENT_RATE : 0.5) * 100, 2), '0'), '.');

    ob_start();
    ?>
    <?php [$pay_text, $pay_class] = payment_label($order); ?>
    <section class="od-section od-summary" aria-labelledby="odOverview">
        <?php if (payment_needs_review($order)): ?>
            <div class="notice notice--warning" role="status">
                <span class="notice-icon" aria-hidden="true"><i class="fas fa-file-invoice-dollar"></i></span>
                <div class="notice-body">
                    <strong><?php echo $awaiting_balance ? 'Remaining balance proof to verify' : 'Payment proof to verify'; ?></strong>
                    <span>Check the uploaded proof against the <?php echo $awaiting_balance ? 'balance' : 'amount'; ?> due of <b>&#8369;<?php echo number_format($due_amount, 2); ?></b>, then verify it under Payment.</span>
                </div>
                <button type="button" class="btn btn-primary btn-sm notice-action" data-scroll-to="odPayment">Review proof</button>
            </div>
        <?php endif; ?>
        <div class="od-summary-head">
            <div>
                <h3 class="od-summary-no" id="odOverview">Order #<?php echo (int) $order['order_id']; ?></h3>
                <p class="od-summary-meta">Placed <?php echo date('M j, Y, g:i A', strtotime($order['created_at'])); ?> &middot; <?php echo count($order_items); ?> line<?php echo count($order_items) === 1 ? '' : 's'; ?>, <?php echo $item_units; ?> unit<?php echo $item_units === 1 ? '' : 's'; ?></p>
            </div>
            <div class="od-summary-total"><span>Total</span><strong>&#8369;<?php echo number_format($order['total_amount'], 2); ?></strong></div>
        </div>
        <div class="od-statuses">
            <div class="od-status"><span class="od-status-label">Order</span><span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>"><?php echo htmlspecialchars($STATUS_LABELS[$order['status']] ?? ucfirst($order['status'])); ?></span></div>
            <div class="od-status"><span class="od-status-label">Payment</span><span class="status-badge <?php echo $pay_class; ?>"><?php echo esc_html($pay_text); ?></span></div>
        </div>
    </section>

    <?php
    // One proof block per payment stage (downpayment / balance), same markup for both.
    $proof_stage = function (string $name, bool $has, bool $ok, string $url, string $alt, string $none_text, string $missing_text, bool $is_pdf = false): void {
        echo '<div class="pv-stage"><span class="pv-stage-name">' . esc_html($name) . '</span>';
        if ($ok) {
            echo '<div class="proof"><a href="' . esc_html($url) . '" target="_blank" rel="noopener" title="' . esc_html($alt) . '">'
                . ($is_pdf ? pdf_thumb_html($url, 'pdf-thumb--proof') : '<img class="proof-thumb" src="' . esc_html($url) . '" alt="' . esc_html($alt) . '">') . '</a>'
                . '<div class="proof-body"><span class="proof-state is-ok"><i class="fas fa-circle-check" aria-hidden="true"></i> Uploaded by the customer</span>'
                . '<a class="btn btn-secondary btn-sm" href="' . esc_html($url) . '" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square" aria-hidden="true"></i> Open full size</a></div></div>';
        } elseif ($has) {
            echo '<p class="proof-state is-missing"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> ' . esc_html($missing_text) . '</p>';
        } else {
            echo '<p class="proof-state is-none"><i class="fas fa-circle-minus" aria-hidden="true"></i> ' . esc_html($none_text) . '</p>';
        }
        echo '</div>';
    };
    $verify_what = $awaiting_balance ? 'balance' : ($is_down ? 'downpayment' : 'payment');
    // Nothing left to verify once the order is out of Pending and no balance is owed.
    $needs_verify = $order['status'] === 'pending' || $awaiting_balance;
    ?>
    <section class="od-section" aria-labelledby="odPayment">
        <h3 class="od-title" id="odPayment">Payment <small><?php echo $is_down ? 'Downpayment (' . esc_html($down_pct) . '%)' : 'Full payment'; ?></small></h3>

        <dl class="od-money">
            <div class="od-money-tile"><dt>Order total</dt><dd>&#8369;<?php echo number_format((float) $order['total_amount'], 2); ?></dd></div>
            <?php if ($is_down && $order['status'] === 'pending'): ?>
                <div class="od-money-tile is-due"><dt>Due now</dt><dd>&#8369;<?php echo number_format($due_amount, 2); ?></dd></div>
                <div class="od-money-tile"><dt>Balance later</dt><dd>&#8369;<?php echo number_format((float) $order['total_amount'] - $due_amount, 2); ?></dd></div>
            <?php elseif ($is_down): ?>
                <div class="od-money-tile"><dt>Paid so far</dt><dd>&#8369;<?php echo number_format($amount_paid, 2); ?></dd></div>
                <?php if ($awaiting_balance): ?>
                    <div class="od-money-tile is-owed"><dt>Balance owed</dt><dd>&#8369;<?php echo number_format($owed, 2); ?></dd></div>
                <?php else: ?>
                    <div class="od-money-tile is-clear"><dt>Balance</dt><dd>Settled</dd></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="od-money-tile is-due"><dt><?php echo $order['status'] === 'pending' ? 'Due now' : 'Amount due'; ?></dt><dd>&#8369;<?php echo number_format($due_amount, 2); ?></dd></div>
            <?php endif; ?>
        </dl>

        <div class="pv-stages">
            <?php
            $proof_stage($is_down ? 'Downpayment proof' : 'Payment proof', $has_proof, (bool) $proof_ok, $proof_url,
                'Payment proof for order #' . (int) $order['order_id'],
                "The customer hasn't uploaded a payment proof yet.", 'A proof is on record but the file could not be found.',
                is_pdf_file($order['payment_proof'] ?? ''));
            if ($is_down && ($has_balance_proof || $awaiting_balance)) {
                $proof_stage('Remaining balance proof', $has_balance_proof, (bool) $balance_proof_ok, $balance_proof_url,
                    'Balance payment proof for order #' . (int) $order['order_id'],
                    "The customer hasn't uploaded the remaining balance proof yet.", 'A remaining balance proof is on record but the file could not be found.',
                    is_pdf_file($order['balance_proof'] ?? ''));
            }
            ?>
        </div>

        <?php if ($needs_verify): ?>
            <div class="pv pv-box">
                <h4 class="pv-box-title" id="odVerify">Verify <?php echo esc_html($verify_what); ?></h4>
                <div class="qv-proof">
                    <label class="qv-proof-label" for="qvProofAmt"><?php echo $awaiting_balance ? 'Amount on remaining balance proof' : 'Amount on payment proof'; ?></label>
                    <div class="qv-proof-row">
                        <div class="price-input-wrap">
                            <input type="number" id="qvProofAmt" class="price-input" data-role="proof-amount" step="0.01" min="0" placeholder="0.00"
                                data-order-id="<?php echo (int) $order['order_id']; ?>" data-due="<?php echo esc_html(number_format($due_amount, 2, '.', '')); ?>"
                                value="<?php echo $proof_amount !== null ? esc_html(number_format($proof_amount, 2, '.', '')) : ''; ?>"<?php echo $can_edit_proof ? '' : ' disabled'; ?>>
                        </div>
                        <span class="qv-saved" data-role="proof-saved" aria-live="polite"></span>
                    </div>
                    <dl class="od-grid">
                        <div class="od-kv"><dt><?php echo $awaiting_balance ? 'Balance due' : 'Amount due'; ?></dt><dd>&#8369;<?php echo number_format($due_amount, 2); ?></dd></div>
                        <div class="od-kv"><dt><?php echo $awaiting_balance ? 'Proof vs balance due' : 'Proof vs amount due'; ?></dt><dd data-role="proof-diff"><?php echo qv_proof_badge($proof_amount, $due_amount); ?></dd></div>
                    </dl>
                </div>

                <?php if ($can_decide): ?>
                    <div class="pv-actions" data-role="pv-actions">
                        <button type="button" class="btn btn-primary btn-sm" data-role="verify-payment" data-order-id="<?php echo (int) $order['order_id']; ?>" data-has-proof="<?php echo $stage_proof_ok ? '1' : '0'; ?>"<?php echo $stage_proof_ok ? '' : ' disabled'; ?>><i class="fas fa-circle-check" aria-hidden="true"></i> <?php echo $awaiting_balance ? 'Verify balance' : ($is_down ? 'Verify downpayment' : 'Verify payment'); ?></button>
                        <?php if ($awaiting_balance): ?>
                            <label class="pv-cash-toggle"><input type="checkbox" data-role="cash-toggle"> Paid in cash</label>
                            <button type="button" class="btn btn-secondary btn-sm" data-role="cash-balance" data-order-id="<?php echo (int) $order['order_id']; ?>" data-balance="<?php echo esc_html(number_format($owed, 2)); ?>" hidden><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Confirm cash received</button>
                        <?php endif; ?>
                        <?php if (!$awaiting_balance): ?>
                        <div class="pv-reject">
                            <input type="text" class="cancel-reason-input" data-role="reject-reason" placeholder="Reason for rejecting" aria-label="Reason for rejecting this payment">
                            <button type="button" class="btn btn-secondary btn-sm" data-role="reject-payment" data-order-id="<?php echo (int) $order['order_id']; ?>"><i class="fas fa-ban" aria-hidden="true"></i> Reject payment</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$stage_proof_ok): ?>
                        <div class="notice notice--warning notice--compact" data-role="pv-hint" role="status"><span class="notice-icon" aria-hidden="true"><i class="fas fa-triangle-exclamation"></i></span><div class="notice-body"><span><?php echo $awaiting_balance
                            ? "The customer hasn't uploaded the remaining balance proof yet, so it can't be verified. Tick Paid in cash if they paid the balance at the shop."
                            : "The customer hasn't uploaded a payment proof yet, so this payment can't be verified. You can still reject it."; ?></span></div></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="notice notice--success notice--compact" role="status"><span class="notice-icon" aria-hidden="true"><i class="fas fa-circle-check"></i></span><div class="notice-body"><strong>Payment settled</strong><span>Nothing left to verify.</span></div></div>
        <?php endif; ?>

        <?php $pay_history = order_payment_history($inventory, (int) $order['order_id']);
        if ($pay_history):
            $kind_names = ['downpayment' => 'Downpayment', 'full' => 'Payment', 'balance' => 'Remaining balance']; ?>
            <div>
                <div class="od-sub-title">Payment history</div>
                <dl class="od-grid">
                    <?php foreach ($pay_history as $ph): ?>
                        <div class="od-kv">
                            <dt><?php echo esc_html(date('M j, g:i A', strtotime($ph['verified_at']))); ?> &middot; <?php echo esc_html(($kind_names[$ph['kind']] ?? $ph['kind']) . ($ph['method'] === 'cash' ? ' (cash)' : '')); ?></dt>
                            <dd>&#8369;<?php echo number_format((float) $ph['amount_received'], 2); ?><?php echo !empty($ph['username']) ? ' &middot; by ' . esc_html($ph['username']) : ''; ?><?php echo !empty($ph['note']) ? '<br><span class="text-muted">' . esc_html($ph['note']) . '</span>' : ''; ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endif; ?>
    </section>

    <section class="od-section" aria-labelledby="odCustomer">
        <h3 class="od-title" id="odCustomer">Customer</h3>
        <div class="od-customer">
            <span class="avatar<?php echo $is_company ? ' avatar--company' : ''; ?>" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></span>
            <div class="cell-stack">
                <span class="od-customer-name"><?php echo htmlspecialchars($display_name); ?></span>
                <span><span class="type-badge type-badge--<?php echo $is_company ? 'company' : 'personal'; ?>"><?php echo $is_company ? 'Company' : 'Personal'; ?></span></span>
            </div>
        </div>
        <dl class="od-grid">
            <div class="od-kv"><dt>Username</dt><dd><?php echo htmlspecialchars($order['username']); ?></dd></div>
            <div class="od-kv"><dt>User ID</dt><dd><?php echo (int) $order['user_id']; ?></dd></div>
            <?php if ($is_company && !empty($cust['contact_person'])): ?>
                <div class="od-kv"><dt>Contact person</dt><dd><?php echo htmlspecialchars($cust['contact_person']); ?></dd></div>
            <?php endif; ?>
            <div class="od-kv"><dt>Phone</dt><dd><?php echo $phone !== '' ? htmlspecialchars($phone) : '<span class="text-muted">Not provided</span>'; ?></dd></div>
            <div class="od-kv"><dt>City</dt><dd><?php echo $city !== '' ? htmlspecialchars($city) : '<span class="text-muted">Not provided</span>'; ?></dd></div>
        </dl>
    </section>

    <section class="od-section" aria-labelledby="odItems">
        <h3 class="od-title" id="odItems">Items <small><?php echo count($order_items); ?> in this order</small></h3>
        <?php if (empty($order_items)): ?>
            <div class="empty-state"><i class="fas fa-box-open" aria-hidden="true"></i><h3>No items on this order</h3></div>
        <?php endif; ?>
        <?php foreach ($order_items as $item): ?>
            <article class="od-item">
                <header class="od-item-head">
                    <div>
                        <div class="od-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="od-item-sub"><?php echo htmlspecialchars($item['product_category']); ?> &middot; <?php echo (int) $item['quantity']; ?> &times; &#8369;<?php echo number_format($item['unit_price'], 2); ?></div>
                    </div>
                    <div class="od-item-total">&#8369;<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></div>
                </header>
                <div class="od-item-body">
                    <?php if ($item['size_option'] || $item['color_option'] || $item['finish_option'] || $item['paper_option'] || $item['binding_option'] || $item['layout_option'] || $item['gsm_option']): ?>
                        <div>
                            <div class="od-sub-title">Customizations</div>
                            <div class="cust-grid"><?php render_item_customization($item); ?></div>
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

if (isset($_POST['ajax']) && $_POST['ajax'] === 'save_proof_amount' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('web_orders')) {
        echo json_encode(['success' => false, 'message' => permission_denied_message('web_orders')]);
        exit;
    }
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $raw = trim((string) ($_POST['amount'] ?? ''));
    if ($raw === '') {
        $amount = null;
    } elseif (is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= 99999999.99) {
        $amount = round((float) $raw, 2);
    } else {
        echo json_encode(['success' => false, 'message' => 'Enter a valid amount, for example 1250.00.']);
        exit;
    }

    try {
        $stmt = $inventory->prepare("SELECT status, total_amount, amount_due, amount_paid, is_downpayment FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$order_id || !$row) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        // Nothing to record once the payment is settled (or the order is cancelled).
        if ($row['status'] !== 'pending' && order_balance_owed($row) <= 0) {
            echo json_encode(['success' => false, 'message' => 'This order has no payment waiting to be verified, so the proof amount can no longer be changed.']);
            exit;
        }
        // The amount belongs to the remaining balance proof when a balance is owed, otherwise to the checkout proof.
        $col = payment_proof_column($row);
        $stmt = $inventory->prepare("UPDATE orders SET $col = ? WHERE order_id = ?");
        $stmt->bind_param("di", $amount, $order_id);
        $stmt->execute();
        echo json_encode([
            'success' => true,
            'message' => $amount === null ? "Cleared the proof amount for order #$order_id." : "Saved the proof amount for order #$order_id.",
            'diff_html' => qv_proof_badge($amount, payment_expected_now($row)),
        ]);
    } catch (Throwable $e) {
        error_log('save_proof_amount failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not save the amount. Have proof_amount.sql and payment_balance.sql been run?']);
    }
    exit;
}

// Remaining balance paid in cash at the shop: no proof to check, the admin just confirms the cash was received.
if (isset($_POST['ajax']) && $_POST['ajax'] === 'cash_balance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('web_orders')) {
        echo json_encode(['success' => false, 'message' => permission_denied_message('web_orders')]);
        exit;
    }
    $order_id = (int) ($_POST['order_id'] ?? 0);

    try {
        $stmt = $inventory->prepare("SELECT status, total_amount, amount_paid, is_downpayment FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$order_id || !$row) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        $owed = order_balance_owed($row);
        if ($owed <= 0) {
            echo json_encode(['success' => false, 'message' => 'This order has no balance owed.']);
            exit;
        }
        // Same effect as verifying a remaining balance proof: the order status stays, the payment label becomes Paid.
        // COALESCE: an empty amount_paid counts as 0 instead of making the comparison fail.
        $inventory->begin_transaction();
        $stmt = $inventory->prepare("UPDATE orders SET amount_paid = total_amount, updated_at = NOW() WHERE order_id = ? AND status = ? AND COALESCE(amount_paid, 0) < total_amount");
        $stmt->bind_param("is", $order_id, $row['status']);
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            payments_rollback($inventory);
            echo json_encode(['success' => false, 'message' => 'This order was changed by someone else. Reopen it and try again.']);
            exit;
        }
        log_order_payment($inventory, $order_id, 'balance', 'cash', $owed, $owed, null, 'Paid in cash at the shop');
        $inventory->commit();
        echo json_encode([
            'success' => true,
            'message' => "Cash received. Order #$order_id balance of ₱" . number_format($owed, 2) . " is now Paid in full.",
            'order_id' => $order_id,
            'status' => $row['status'],
            'status_label' => $STATUS_LABELS[$row['status']] ?? $row['status'],
            'cancellation_reason' => null,
        ] + payment_snapshot($inventory, $order_id));
    } catch (Throwable $e) {
        payments_rollback($inventory);
        error_log('cash_balance failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not complete this action. Have downpayment.sql, payment_balance.sql and payment_audit.sql been run?']);
    }
    exit;
}

if (isset($_POST['ajax']) && in_array($_POST['ajax'], ['verify_payment', 'reject_payment'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!can('web_orders')) {
        echo json_encode(['success' => false, 'message' => permission_denied_message('web_orders')]);
        exit;
    }
    $action = $_POST['ajax'];
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? ''));

    try {
        $stmt = $inventory->prepare("SELECT user_id, payment_proof, balance_proof, status, total_amount, amount_due, proof_amount, balance_proof_amount, amount_paid, is_downpayment FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$order_id || !$row) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }
        $balance_stage = order_balance_owed($row) > 0;
        $status_name = $STATUS_LABELS[$row['status']] ?? $row['status'];
        if ($action === 'reject_payment' && $row['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Only pending orders can be rejected. This order is ' . $status_name . '.']);
            exit;
        }
        if ($action === 'verify_payment') {
            // Never verify without the customer's proof for this stage (checkout proof, or remaining balance proof while a balance is owed).
            $stage_file = $balance_stage ? (string) ($row['balance_proof'] ?? '') : (string) ($row['payment_proof'] ?? '');
            if (($row['status'] === 'pending' || $balance_stage) && ($stage_file === '' || !payment_proof_path((int) $row['user_id'], $stage_file))) {
                echo json_encode(['success' => false, 'message' => $balance_stage
                    ? "The customer hasn't uploaded the remaining balance proof yet, so it can't be verified."
                    : "The customer hasn't uploaded a payment proof yet, so it can't be verified."]);
                exit;
            }
        }
        if ($action === 'verify_payment' && $row['status'] !== 'pending' && !$balance_stage) {
            echo json_encode(['success' => false, 'message' => 'There is no payment waiting to be verified. This order is ' . $status_name . '.']);
            exit;
        }

        $pending_log = null; // payment history row, written in the same transaction as the update
        if ($action === 'verify_payment') {
            $due = payment_expected_now($row);
            $col = payment_proof_column($row);
            // The Verify button sends the amount typed in the panel, so there is no separate Save step.
            $typed_raw = trim((string) ($_POST['amount'] ?? ''));
            if ($typed_raw !== '') {
                if (!is_numeric($typed_raw) || (float) $typed_raw < 0 || (float) $typed_raw > 99999999.99) {
                    echo json_encode(['success' => false, 'message' => 'Enter a valid amount, for example 1250.00.']);
                    exit;
                }
                $typed = round((float) $typed_raw, 2);
                $save = $inventory->prepare("UPDATE orders SET $col = ? WHERE order_id = ?");
                $save->bind_param("di", $typed, $order_id);
                $save->execute();
                $row[$col] = $typed;
            }
            $proof = $row[$col] ?? null;
            if ($proof === null) {
                echo json_encode(['success' => false, 'message' => $balance_stage ? 'Enter the amount shown on the remaining balance proof first.' : 'Enter the amount shown on the payment proof first.']);
                exit;
            }
            if ((float) $proof <= 0) {
                echo json_encode(['success' => false, 'message' => 'Enter an amount greater than zero.']);
                exit;
            }
            $received = round((float) $proof, 2);
            $mismatch = !proof_matches($proof, $due);
            $gap = round($received - $due, 2);
            if ($mismatch) {
                if (!$balance_stage) {
                    echo json_encode(['success' => false, 'message' => 'The proof amount (₱' . number_format($received, 2) . ') does not match the amount due (₱' . number_format($due, 2) . '). Correct the amount or reject the payment.']);
                    exit;
                }
                if (($_POST['confirm_mismatch'] ?? '') !== '1') {
                    // Remaining balance proofs may be accepted at a different amount, but only after the admin confirms.
                    $gap_text = '₱' . number_format(abs($gap), 2);
                    $confirm_msg = $gap < 0
                        ? 'The amount on the remaining balance proof (₱' . number_format($received, 2) . ') is ' . $gap_text . ' short of the balance due (₱' . number_format($due, 2) . '). If you verify it anyway, only ₱' . number_format($received, 2) . ' is recorded and ' . $gap_text . ' stays owed.'
                        : 'The amount on the remaining balance proof (₱' . number_format($received, 2) . ') is ' . $gap_text . ' over the balance due (₱' . number_format($due, 2) . '). If you verify it anyway, the balance is marked Paid in full and the extra ' . $gap_text . ' is noted in the payment history.';
                    echo json_encode(['success' => false, 'needs_confirm' => true, 'message' => $confirm_msg]);
                    exit;
                }
            }
            $proof_file_name = $balance_stage ? (string) ($row['balance_proof'] ?? '') : (string) ($row['payment_proof'] ?? '');
            $total = (float) $row['total_amount'];
            if ($balance_stage) {
                // The order status stays where it is. What was actually received is added to amount_paid
                // (never above the total), so a short payment leaves the rest owed instead of marking it Paid.
                $new_status = $row['status'];
                $new_paid = min($total, round((float) ($row['amount_paid'] ?? 0) + $received, 2));
                $still_owed = round($total - $new_paid, 2);
                $note = null;
                if ($mismatch) {
                    $note = $gap < 0
                        ? 'Short by ₱' . number_format(abs($gap), 2) . ', verified after the admin confirmed'
                        : 'Over by ₱' . number_format(abs($gap), 2) . ', counted up to the order total';
                }
                if ($still_owed > 0.004) {
                    // Part of the balance is still owed: clear this proof so the customer can send the rest
                    // (the file name is kept in the payment history).
                    $stmt = $inventory->prepare("UPDATE orders SET amount_paid = ?, balance_proof = NULL, balance_proof_amount = NULL, updated_at = NOW() WHERE order_id = ? AND status = ? AND COALESCE(amount_paid, 0) < total_amount");
                    $message = "Balance partly verified. ₱" . number_format($received, 2) . " recorded on order #$order_id; ₱" . number_format($still_owed, 2) . " is still owed.";
                } else {
                    $stmt = $inventory->prepare("UPDATE orders SET amount_paid = ?, updated_at = NOW() WHERE order_id = ? AND status = ? AND COALESCE(amount_paid, 0) < total_amount");
                    $message = "Balance verified. Order #$order_id is now Paid in full." . ($mismatch ? ' The proof amount differed from the balance due.' : '');
                }
                $stmt->bind_param("dis", $new_paid, $order_id, $row['status']);
                $pending_log = [$order_id, 'balance', 'proof', (float) $due, (float) $received, $proof_file_name, $note];
            } elseif (!empty($row['is_downpayment'])) {
                $new_status = 'processing';
                $stmt = $inventory->prepare("UPDATE orders SET status = 'processing', amount_paid = ?, updated_at = NOW() WHERE order_id = ? AND status = 'pending'");
                $stmt->bind_param("di", $due, $order_id);
                $message = "Downpayment verified. Order #$order_id is now Processing and Partially paid; the balance is still due.";
                $pending_log = [$order_id, 'downpayment', 'proof', (float) $due, (float) $received, $proof_file_name, null];
            } else {
                $new_status = 'processing';
                $stmt = $inventory->prepare("UPDATE orders SET status = 'processing', amount_paid = ?, updated_at = NOW() WHERE order_id = ? AND status = 'pending'");
                $stmt->bind_param("di", $total, $order_id);
                $message = "Payment verified. Order #$order_id is now Processing and Paid.";
                $pending_log = [$order_id, 'full', 'proof', (float) $due, (float) $received, $proof_file_name, null];
            }
            $saved_reason = null;
        } else {
            if ($reason === '') {
                echo json_encode(['success' => false, 'message' => 'Please enter the reason for rejecting this payment.']);
                exit;
            }
            // Rejecting only cancels the order. Nothing goes back to the cart or the pricing estimates.
            $new_status = 'cancelled';
            $saved_reason = 'Payment rejected: ' . $reason;
            $stmt = $inventory->prepare("UPDATE orders SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW() WHERE order_id = ? AND status = 'pending'");
            $stmt->bind_param("si", $saved_reason, $order_id);
            $message = "Payment rejected. Order #$order_id was cancelled.";
        }
        $inventory->begin_transaction();
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            payments_rollback($inventory);
            echo json_encode(['success' => false, 'message' => 'This order was changed by someone else. Reopen it and try again.']);
            exit;
        }
        if ($pending_log !== null) {
            log_order_payment($inventory, ...$pending_log);
        }
        $inventory->commit();
        echo json_encode([
            'success' => true,
            'message' => $message,
            'order_id' => $order_id,
            'status' => $new_status,
            'status_label' => $STATUS_LABELS[$new_status],
            'cancellation_reason' => $saved_reason,
        ] + payment_snapshot($inventory, $order_id));
    } catch (Throwable $e) {
        payments_rollback($inventory);
        error_log($action . ' failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not complete this action. Have downpayment.sql, payment_balance.sql and payment_audit.sql been run?']);
    }
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

    // Payment guards. $new_amount_paid is set when this change is the one that takes a payment.
    // Any error here stops the change: a failed check must never let a status through unchecked.
    $new_amount_paid = null;
    $pay_log = null;
    $current = '';
    try {
        $gs = $inventory->prepare("SELECT user_id, status, total_amount, amount_due, payment_proof, proof_amount, balance_proof_amount, amount_paid, is_downpayment FROM orders WHERE order_id = ?");
        $gs->bind_param("i", $order_id);
        $gs->execute();
        $g = $gs->get_result()->fetch_assoc();
        if (!$g) {
            echo json_encode(['success' => false, 'message' => "Order #$order_id was not found."]);
            exit;
        }
        $current = $g['status'];
        $is_down_order = !empty($g['is_downpayment']);
        $owed = order_balance_owed($g);
        $due_now = payment_expected_now($g);
        $fail = null;

        if ($current === $new_status) {
            $fail = "Order #$order_id is already " . $STATUS_LABELS[$current] . ".";
        } elseif (!in_array($new_status, $ALLOWED_TRANSITIONS[$current] ?? [], true)) {
            $fail = "Order #$order_id can't go from " . ($STATUS_LABELS[$current] ?? $current) . " to " . $STATUS_LABELS[$new_status] . ".";
        } else {
            $leaves_pending = $current === 'pending' && in_array($new_status, ['processing', 'ready_for_pickup', 'completed'], true);
            if ($new_status === 'completed' && ($owed > 0 || ($is_down_order && $current === 'pending'))) {
                $left = $owed > 0 ? $owed : round((float) $g['total_amount'] - (float) $due_now, 2);
                $fail = "Order #$order_id still has a balance of ₱" . number_format($left, 2) . ". Verify the balance before completing it.";
            } elseif ($leaves_pending) {
                // Moving on from Pending is the payment verification, so it follows the same rules as the Verify button:
                // the customer's proof file must exist and the amount on it must match what was due.
                $proof_file = (string) ($g['payment_proof'] ?? '');
                if ($proof_file === '' || !payment_proof_path((int) $g['user_id'], $proof_file)) {
                    $fail = "Order #$order_id has no payment proof from the customer, so its payment can't be verified. Ask the customer to upload one.";
                } elseif (!proof_matches($g['proof_amount'], $due_now)) {
                    $fail = "Order #$order_id can only leave Pending after the amount on the payment proof is saved and matches the amount due (₱" . number_format($due_now, 2) . ").";
                } else {
                    $new_amount_paid = $is_down_order ? $due_now : (float) $g['total_amount'];
                    $pay_log = [$order_id, $is_down_order ? 'downpayment' : 'full', 'proof', (float) $due_now, (float) $g['proof_amount'], $proof_file, 'Verified by changing the order status'];
                }
            }
        }
        if ($fail !== null) {
            echo json_encode(['success' => false, 'message' => $fail]);
            exit;
        }
    } catch (Throwable $e) {
        error_log('update_status guard failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not check this order\'s payment, so nothing was changed. Have downpayment.sql, payment_balance.sql and payment_audit.sql been run?']);
        exit;
    }

    try {
        $inventory->begin_transaction();
        // AND status = <what we just checked>: if someone else changed the order meanwhile, nothing is written.
        if ($new_status === 'cancelled') {
            $stmt = $inventory->prepare("UPDATE orders SET status = ?, cancellation_reason = ?, updated_at = NOW() WHERE order_id = ? AND status = ?");
            $stmt->bind_param("ssis", $new_status, $cancel_reason, $order_id, $current);
        } elseif ($new_amount_paid !== null) {
            $stmt = $inventory->prepare("UPDATE orders SET status = ?, amount_paid = ?, updated_at = NOW() WHERE order_id = ? AND status = ?");
            $stmt->bind_param("sdis", $new_status, $new_amount_paid, $order_id, $current);
        } else {
            $stmt = $inventory->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ? AND status = ?");
            $stmt->bind_param("sis", $new_status, $order_id, $current);
        }
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            payments_rollback($inventory);
            echo json_encode(['success' => false, 'message' => 'This order was changed by someone else. Reopen it and try again.']);
            exit;
        }
        if ($pay_log !== null) {
            log_order_payment($inventory, ...$pay_log);
        }
        $inventory->commit();
    } catch (Throwable $e) {
        payments_rollback($inventory);
        error_log('update_status failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update order status. Have downpayment.sql, payment_balance.sql and payment_audit.sql been run?']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => "Order #$order_id status updated to " . $STATUS_LABELS[$new_status] . ".",
        'order_id' => $order_id,
        'status' => $new_status,
        'status_label' => $STATUS_LABELS[$new_status],
        'cancellation_reason' => $new_status === 'cancelled' ? $cancel_reason : null,
    ] + payment_snapshot($inventory, $order_id));
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
$per_page = 10;
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

$list_query = "SELECT o.*, u.username,
                      (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
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
$tab_counts = ['all' => 0] + array_fill_keys($VALID_STATUSES, 0);
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

<?php
require_once 'admin_layout.php';

$filters_active = ($status !== '' || $search !== '');
$subtitle = $total_orders . ' ' . ($total_orders === 1 ? 'order' : 'orders');
if ($status !== '') {
    $subtitle .= ' · ' . $STATUS_LABELS[$status];
}
if ($search !== '') {
    $subtitle .= ' matching "' . $search . '"';
}
admin_layout_start([
    'page' => 'orders',
    'title' => 'Orders',
    'subtitle' => $subtitle,
]);

$tabs = ['' => 'All'] + array_map(function ($label) { return $label; }, $STATUS_LABELS);
?>

<!-- Status tabs -->
<nav class="tabs" aria-label="Filter orders by status">
    <?php foreach ($tabs as $val => $label):
        $count_key = $val === '' ? 'all' : $val; ?>
        <a href="<?php echo build_query_url(['status' => $val, 'page' => '']); ?>" class="tab-link <?php echo $status === $val ? 'active' : ''; ?>"
            <?php echo $status === $val ? 'aria-current="page"' : ''; ?>>
            <?php echo htmlspecialchars($label); ?>
            <span class="tab-count" data-count-status="<?php echo htmlspecialchars($count_key); ?>"><?php echo (int) ($tab_counts[$count_key] ?? 0); ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<!-- Search -->
<div class="toolbar">
    <form method="get" action="admin_orders.php" role="search">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>"><?php endif; ?>
        <div class="search-field">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by order ID or customer" aria-label="Search orders by order ID or customer">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($filters_active): ?>
            <a href="admin_orders.php" class="btn btn-secondary"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset filters</a>
        <?php endif; ?>
    </form>
</div>

<!-- Orders -->
<div class="order-table">
    <table class="table">
        <caption class="sr-only">Orders<?php echo $status !== '' ? ', ' . htmlspecialchars($STATUS_LABELS[$status]) : ''; ?>, page <?php echo (int) $page; ?> of <?php echo (int) $total_pages; ?></caption>
        <thead>
            <tr>
                <th scope="col">Order</th>
                <th scope="col">Customer</th>
                <th scope="col">Date</th>
                <th scope="col" class="num">Total</th>
                <th scope="col" class="col-center">Proof</th>
                <th scope="col">Payment</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody id="ordersTable">
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" class="empty-cell">
                        <div class="empty-state">
                            <i class="fas fa-receipt" aria-hidden="true"></i>
                            <h3><?php echo $filters_active ? 'No orders match these filters' : 'No orders yet'; ?></h3>
                            <p><?php echo $filters_active ? 'Try a different search or status, or reset the filters.' : 'Orders appear here as soon as customers check out.'; ?></p>
                            <?php if ($filters_active): ?><a class="btn btn-secondary btn-sm" href="admin_orders.php">Reset filters</a><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($orders as $order): ?>
                <tr class="order-row" id="order-row-<?php echo (int) $order['order_id']; ?>" data-status="<?php echo htmlspecialchars($order['status']); ?>">
                    <td>
                        <div class="cell-stack">
                            <button type="button" class="link-btn" data-open-order="<?php echo (int) $order['order_id']; ?>" aria-label="Open order <?php echo (int) $order['order_id']; ?>">#<?php echo (int) $order['order_id']; ?></button>
                            <span class="cell-sub"><?php echo (int) $order['item_count']; ?> item<?php echo (int) $order['item_count'] === 1 ? '' : 's'; ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="cell-stack">
                            <span class="cell-main"><?php echo htmlspecialchars($order['username']); ?></span>
                            <span class="cell-sub">User ID <?php echo (int) $order['user_id']; ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="cell-stack">
                            <span class="cell-main"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                            <span class="cell-sub"><?php echo date('g:i A', strtotime($order['created_at'])); ?></span>
                        </div>
                    </td>
                    <td class="num">
                        <div class="cell-stack">
                            <strong>&#8369;<?php echo number_format($order['total_amount'], 2); ?></strong>
                            <span class="cell-sub"><?php echo !empty($order['is_downpayment']) ? 'Downpayment' : 'Full payment'; ?></span>
                        </div>
                    </td>
                    <td class="col-center">
                        <?php if ($order['payment_proof']):
                            $proof_path = payment_proof_url((int) $order['order_id']);
                            if (payment_proof_path((int) $order['user_id'], (string) $order['payment_proof'])): ?>
                                <a href="<?php echo esc_html($proof_path); ?>" target="_blank" rel="noopener" title="Open payment proof">
                                    <?php if (is_pdf_file((string) $order['payment_proof'])): ?>
                                        <span class="proof-image proof-image--pdf" aria-label="PDF payment proof for order <?php echo (int) $order['order_id']; ?>"><i class="fas fa-file-pdf" aria-hidden="true"></i></span>
                                    <?php else: ?>
                                        <img src="<?php echo esc_html($proof_path); ?>" alt="Payment proof for order <?php echo (int) $order['order_id']; ?>" class="proof-image" loading="lazy">
                                    <?php endif; ?>
                                </a>
                            <?php else: ?>
                                <span class="customization-badge is-danger">File missing</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">No proof</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php [$pl_text, $pl_class] = payment_label($order); $row_owed = order_balance_owed($order); ?>
                        <div class="cell-stack pay-cell">
                            <div class="pay-line">
                                <span class="status-badge <?php echo $pl_class; ?>" data-role="payment-badge"><?php echo esc_html($pl_text); ?></span>
                                <span class="proof-flag" data-role="proof-flag" title="<?php echo order_balance_owed($order) > 0 ? 'The customer uploaded a remaining balance proof' : 'The customer uploaded a payment proof'; ?>"<?php echo payment_needs_review($order) ? '' : ' hidden'; ?>><span class="proof-flag-dot" aria-hidden="true"></span>To verify</span>
                            </div>
                            <?php if ($row_owed > 0): ?>
                                <span class="cell-sub pay-balance">Balance &#8369;<?php echo number_format($row_owed, 2); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>" data-role="status-badge"
                            <?php if ($order['status'] === 'cancelled' && !empty($order['cancellation_reason'])): ?>
                                title="Reason: <?php echo esc_html($order['cancellation_reason']); ?>"
                            <?php endif; ?>>
                            <?php echo htmlspecialchars($STATUS_LABELS[$order['status']] ?? ucfirst($order['status'])); ?>
                        </span>
                    </td>
                    <td class="col-actions">
                        <div class="order-actions" data-order-id="<?php echo (int) $order['order_id']; ?>">
                            <label class="sr-only" for="status-<?php echo (int) $order['order_id']; ?>">Status for order <?php echo (int) $order['order_id']; ?></label>
                            <?php $row_next = $ALLOWED_TRANSITIONS[$order['status']] ?? []; ?>
                            <select id="status-<?php echo (int) $order['order_id']; ?>" class="status-select" data-role="status-select"<?php echo $row_next ? '' : ' disabled'; ?>>
                                <?php foreach (array_merge([$order['status']], $row_next) as $val): ?>
                                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $order['status'] === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($STATUS_LABELS[$val] ?? ucfirst($val)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="update-btn" data-role="update-btn" data-order-id="<?php echo (int) $order['order_id']; ?>"<?php echo $row_next ? '' : ' disabled'; ?>>Update</button>
                            <input type="text" class="cancel-reason-input" data-role="cancel-reason"
                                placeholder="Reason for cancellation" aria-label="Reason for cancelling order <?php echo (int) $order['order_id']; ?>"
                                value="<?php echo $order['status'] === 'cancelled' ? esc_html($order['cancellation_reason'] ?? '') : ''; ?>"
                                <?php echo $order['status'] === 'cancelled' ? '' : 'hidden'; ?>>
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
            <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page - 1]); ?>"><i class="fas fa-chevron-left" aria-hidden="true"></i> Prev</a>
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <a class="page-link <?php echo $p === $page ? 'active' : ''; ?>" href="<?php echo build_query_url(['page' => $p]); ?>" <?php echo $p === $page ? 'aria-current="page"' : ''; ?>><?php echo $p; ?></a>
            <?php endfor; ?>
            <a class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page + 1]); ?>">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
        </div>
    <?php endif; ?>
</div>

<?php admin_layout_end(); ?>

<!-- Order details drawer -->
<div class="panel-overlay" id="panelOverlay"></div>
<aside class="slide-panel" id="slidePanel" role="dialog" aria-modal="true" aria-labelledby="panelTitle" aria-hidden="true">
    <header class="panel-header">
        <div class="panel-heading">
            <h2 id="panelTitle">Order details</h2>
            <span class="status-badge" id="panelStatusBadge" hidden></span>
        </div>
        <button type="button" class="panel-close" id="panelClose" aria-label="Close order details" autofocus><i class="fas fa-xmark" aria-hidden="true"></i></button>
    </header>
    <div class="panel-status-bar" id="panelStatusBar"></div>
    <div class="panel-body" id="panelBody">
        <div class="panel-loading" role="status"><i class="fas fa-circle-notch" aria-hidden="true"></i> Loading order...</div>
    </div>
</aside>

<?php
$wa_data = [
    'statusLabels' => $STATUS_LABELS,
    'transitions' => $ALLOWED_TRANSITIONS,
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