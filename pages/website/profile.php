<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';
require_once '../../config/security.php';

// Get user and customer details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.username, u.email_verified,
                 p.first_name, p.middle_name, p.last_name, p.age, p.gender, 
                 p.birthdate, p.contact_number AS personal_contact, 
                 p.address_line1, p.city AS personal_city, 
                 p.province AS personal_province, p.zip_code AS personal_zip,
                 c.company_name, c.taxpayer_name, c.contact_person, 
                 c.contact_number AS company_contact, 
                 c.city AS company_city, c.province AS company_province, 
                 c.barangay, c.subd_or_street, c.building_or_block, 
                 c.lot_or_room_no, c.zip_code AS company_zip
          FROM users u
          LEFT JOIN personal_customers p ON u.id = p.user_id
          LEFT JOIN company_customers c ON u.id = c.user_id
          WHERE u.id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Get order history
// NOTE: the `orders` table has no title/name column, so we derive a display
// title from the order's first line item (order_items.product_name).
// Adjust "oi.order_item_id" below to whatever your order_items primary key
// is actually called if it isn't that.
$order_query = "SELECT o.order_id, o.total_amount, o.status, o.payment_proof, o.created_at, o.cancellation_reason,
                 %s
                 (SELECT oi.product_name
                    FROM order_items oi
                   WHERE oi.order_id = o.order_id
                   ORDER BY oi.order_item_id ASC
                   LIMIT 1) AS first_item_name,
                 (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
          FROM orders o
          WHERE o.user_id = ? 
          ORDER BY o.created_at DESC";
// Payment columns exist only after downpayment.sql and payment_balance.sql have been run;
// without them the page still loads, just without the balance features.
$result = null;
try {
    $stmt = $inventory->prepare(sprintf($order_query, "o.is_downpayment, o.amount_due, o.amount_paid, o.balance_proof,"));
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Throwable $e) {
    $result = null;
}
if (!$result) {
    $stmt = $inventory->prepare(sprintf($order_query, ""));
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
$orders = [];
while ($row = $result->fetch_assoc()) {
    // Build the title: first item's product name, plus "+N more" when the
    // order has additional line items. Falls back to the order number if
    // an order somehow has no items yet.
    $title = $row['first_item_name'] !== null && $row['first_item_name'] !== ''
        ? $row['first_item_name']
        : ('Order #' . $row['order_id']);
    if ((int) $row['item_count'] > 1) {
        $title .= ' + ' . ((int) $row['item_count'] - 1) . ' more';
    }
    $row['title'] = $title;

    // Balance still owed on a downpayment order: only after the downpayment is verified
    // (status past Pending) and until the balance is verified. Same rule as admin_orders.php.
    $row['is_down'] = !empty($row['is_downpayment']);
    $row['paid_so_far'] = (float) ($row['amount_paid'] ?? 0);
    $owed = round((float) $row['total_amount'] - $row['paid_so_far'], 2);
    if ((float) $row['total_amount'] > 0 && $row['paid_so_far'] >= (float) $row['total_amount'] - 0.004) {
        $row['pay_label'] = 'Paid';
        $row['pay_class'] = 'paid';
    } elseif ($row['paid_so_far'] > 0.004) {
        $row['pay_label'] = 'Partially paid';
        $row['pay_class'] = 'partial';
    } else {
        $row['pay_label'] = 'Unpaid';
        $row['pay_class'] = 'unpaid';
    }
    $row['balance_owed'] = ($row['is_down'] && !in_array($row['status'], ['pending', 'cancelled'], true) && $owed > 0.004) ? $owed : 0.0;
    $orders[] = $row;
}

$is_personal = !empty($user_data['first_name']);
$is_company = !empty($user_data['company_name']);

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $query = "SELECT SUM(ci.quantity) as total_items 
              FROM cart_items ci 
              JOIN carts c ON ci.cart_id = c.cart_id 
              WHERE c.user_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_cart = $stmt->get_result();
    $row = $result_cart->fetch_assoc();

    $cart_count = $row['total_items'] ? $row['total_items'] : 0;
}

// ---------------------------------------------------------------
// Presentation helpers (display only — no data is changed here)
// ---------------------------------------------------------------
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';

$slice = function ($text, $start, $length) {
    $text = (string) $text;
    return function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($text, $start, $length, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($text, $start, $length));
};

if ($is_personal) {
    $display_name = trim($user_data['first_name']);
    $full_name    = trim($user_data['first_name'] . ' ' . ($user_data['last_name'] ?? ''));
    $initials     = $slice(trim($user_data['first_name']), 0, 1) . $slice(trim($user_data['last_name'] ?? ''), 0, 1);
} elseif ($is_company) {
    $display_name = trim($user_data['company_name']);
    $full_name    = $display_name;
    $words        = preg_split('/\s+/', $display_name);
    $initials     = count($words) > 1
        ? $slice($words[0], 0, 1) . $slice($words[1], 0, 1)
        : $slice($words[0], 0, 2);
} else {
    $display_name = 'friend';
    $full_name    = 'Your account';
    $initials     = '?';
}
$username = $user_data['username'] ?? ($_SESSION['username'] ?? '');

// <dd> with a soft "Not provided" fallback
$dd = function ($value) {
    $value = trim((string) $value);
    return $value !== ''
        ? '<dd>' . htmlspecialchars($value) . '</dd>'
        : '<dd class="is-empty">Not provided</dd>';
};

// One readable address line
if ($is_personal) {
    $address_parts = [
        $user_data['address_line1'] ?? '',
        $user_data['personal_city'] ?? '',
        trim(($user_data['personal_province'] ?? '') . ' ' . ($user_data['personal_zip'] ?? '')),
    ];
} else {
    $address_parts = [
        $user_data['barangay'] ?? '',
        $user_data['subd_or_street'] ?? '',
        $user_data['building_or_block'] ?? '',
        $user_data['lot_or_room_no'] ?? '',
        $user_data['company_city'] ?? '',
        trim(($user_data['company_province'] ?? '') . ' ' . ($user_data['company_zip'] ?? '')),
    ];
}
$address_text = implode(', ', array_filter(array_map('trim', $address_parts), 'strlen'));

// Birthdate + live age (the stored age goes stale)
$birthdate_text = '';
if (!empty($user_data['birthdate']) && $user_data['birthdate'] !== '0000-00-00') {
    try {
        $birth          = new DateTime($user_data['birthdate']);
        $birthdate_text = $birth->format('F j, Y') . ' (' . $birth->diff(new DateTime())->y . ' years old)';
    } catch (Exception $e) {
        $birthdate_text = $user_data['birthdate'];
    }
}

// Order summary tiles
$order_total     = count($orders);
$order_completed = 0;
$order_cancelled = 0;
foreach ($orders as $o) {
    if ($o['status'] === 'completed') {
        $order_completed++;
    } elseif ($o['status'] === 'cancelled') {
        $order_cancelled++;
    }
}
$order_active = $order_total - $order_completed - $order_cancelled;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        .order-card__cancel-note {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 8px 0;
            padding: 8px 12px;
            background: #fbe9e7;
            color: #d9463c;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1.4;
        }

        .order-card__cancel-note i {
            margin-top: 2px;
        }

        /* Short date is only used in the compact phone layout */
        .order-card__date-short {
            display: none;
        }

        /* =========================================================
           Phones: compact 2-column order grid.
           Each card becomes a small tile (title, ref, status, amount,
           date, proof, details). Tablet/desktop layout is unchanged.
        ========================================================= */
        @media (max-width: 640px) {
            .acct-page .order-list {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .acct-page .order-list .order-card {
                display: flex;
                flex-direction: column;
                gap: 8px;
                min-width: 0;
                padding: 12px 12px 10px 11px;
                border-left-width: 3px;
            }

            .acct-page .order-list .order-card:hover {
                transform: none;
                box-shadow: none;
            }

            .acct-page .order-list .order-card__top {
                flex-direction: column;
                align-items: flex-start;
                flex-wrap: nowrap;
                gap: 6px;
            }

            .acct-page .order-list .order-card__id {
                flex-direction: column;
                align-items: flex-start;
                flex-wrap: nowrap;
                gap: 4px;
                width: 100%;
                min-width: 0;
            }

            /* Long product names stop at two lines */
            .acct-page .order-list .order-card__id h3 {
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                overflow: hidden;
                font-size: 0.92rem;
                line-height: 1.25;
                overflow-wrap: anywhere;
            }

            .acct-page .order-list .order-card__number {
                font-size: 11.5px;
            }

            .acct-page .order-list .order-status {
                gap: 5px;
                padding: 2px 8px;
                font-size: 10px;
                letter-spacing: 0.02em;
            }

            .acct-page .order-list .order-status::before {
                width: 6px;
                height: 6px;
            }

            .acct-page .order-list .order-card__amount {
                font-size: 1.05rem;
            }

            .acct-page .order-list .order-card__cancel-note {
                margin: 0;
                padding: 6px 8px;
                gap: 6px;
                font-size: 11.5px;
                line-height: 1.35;
                overflow-wrap: anywhere;
            }

            /* Footer stacks and sits at the bottom so tiles in a row line up */
            .acct-page .order-list .order-card__foot {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
                margin-top: auto;
                padding-top: 8px;
                font-size: 11.5px;
            }

            .acct-page .order-list .order-card__foot>span {
                align-items: flex-start;
                gap: 6px;
            }

            .acct-page .order-list .order-card__open {
                margin-left: 0;
                margin-top: 2px;
                font-size: 12.5px;
            }

            .order-card__date-full {
                display: none;
            }

            .order-card__date-short {
                display: inline;
            }
        }

        /* =========================================================
           Order details dialog: phone bottom sheet.
           Compact header, dots-only tracker with a one-line caption,
           small fact tiles, a swipe-down grabber, and a one-row footer
           with 44px touch targets. Tablet/desktop keep the centred dialog.
        ========================================================= */
        .acct-tracker__now,
        .acct-modal__chat i {
            display: none;
        }

        @media (max-width: 640px) {
            .acct-modal {
                overscroll-behavior: contain;
            }

            .acct-modal__panel {
                max-height: 94vh;
                max-height: 94dvh;
            }

            /* header: grabber, no eyebrow, two-line title */
            .acct-modal__head {
                padding: 26px 64px 14px 16px;
            }

            .acct-modal__head::after {
                content: "";
                position: absolute;
                top: 8px;
                left: 50%;
                width: 40px;
                height: 4px;
                margin-left: -20px;
                border-radius: 2px;
                background: rgba(250, 247, 241, 0.4);
            }

            .acct-modal__eyebrow {
                display: none;
            }

            .acct-modal__head h2 {
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                overflow: hidden;
                margin-bottom: 8px;
                font-size: 1.15rem;
                line-height: 1.25;
            }

            .acct-modal__ref {
                font-size: 13px;
            }

            .acct-modal__close {
                top: 14px;
                right: 10px;
                width: 44px;
                height: 44px;
            }

            .acct-modal__scroll {
                gap: 12px;
                padding: 12px 12px 16px;
                overscroll-behavior: contain;
                -webkit-overflow-scrolling: touch;
            }

            /* tracker: numbered dots only, current step spelled out underneath */
            .acct-tracker {
                padding: 14px 6px 8px;
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                border-bottom: 0;
            }

            .acct-tracker__step>span:last-child {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip: rect(0 0 0 0);
                white-space: nowrap;
            }

            .acct-tracker__dot {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }

            .acct-tracker__step+.acct-tracker__step::before {
                top: 13px;
            }

            .acct-tracker__now {
                display: block;
                margin: 0;
                padding: 0 12px 12px;
                background: var(--paper-white);
                border: 1px solid var(--line);
                border-top: 0;
                border-radius: 0 0 var(--r-md) var(--r-md);
                text-align: center;
                font-size: 13px;
                color: var(--ink-soft);
            }

            .acct-tracker__now strong {
                font-family: var(--font-display);
                color: var(--ink);
            }

            .acct-modal__alert {
                gap: 10px;
                padding: 12px 14px;
                font-size: 13.5px;
            }

            .acct-modal__alert>i {
                width: 30px;
                height: 30px;
            }

            /* key facts: small tiles */
            .acct-facts {
                gap: 8px;
            }

            .acct-fact {
                padding: 9px 12px;
            }

            .acct-fact dt {
                margin-bottom: 2px;
                font-size: 10.5px;
                letter-spacing: 0.05em;
            }

            .acct-fact dd {
                font-size: 13.5px;
            }

            .acct-fact--total dd {
                font-size: 1.15rem;
            }

            .acct-modal__section {
                margin: 2px 0 -4px;
            }

            .acct-modal__section h3 {
                font-size: 1rem;
            }

            /* footer: Chat + Close side by side, thumb-sized */
            .acct-modal__foot {
                flex-wrap: nowrap;
                gap: 10px;
                padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px));
            }

            .acct-modal__foot p {
                flex: 1;
                min-width: 0;
                margin: 0;
            }

            .acct-modal__foot-q {
                display: none;
            }

            .acct-modal__chat {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                min-height: 44px;
                padding: 0 12px;
                border: 1.5px solid var(--ink);
                border-radius: var(--r-sm);
                font-size: 14px;
                color: var(--ink);
            }

            .acct-modal__chat i {
                display: inline;
            }

            .acct-modal__foot .btn {
                flex: 1;
                width: auto;
                min-height: 44px;
                padding: 0 12px;
            }
        }

        /* Status pills row */
        .acct-page .order-card__badges {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 8px;
        }

        .acct-page .order-pay {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .acct-page .order-pay--unpaid {
            background: #eceff1;
            color: #455a64;
        }

        .acct-page .order-pay--partial {
            background: #fff4d6;
            color: #8a5a00;
        }

        .acct-page .order-pay--paid {
            background: #e3f6e8;
            color: #1b6e3a;
        }

        /* Balance due: clean card with a proof-of-payment uploader */
        .acct-page .order-card__balance {
            display: grid;
            gap: 16px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            cursor: default;
        }

        .acct-page .bal-head {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px 16px;
        }

        .acct-page .bal-label {
            display: block;
            margin-bottom: 2px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--ink-faint);
        }

        .acct-page .bal-amount {
            display: block;
            font-size: 1.6rem;
            line-height: 1.15;
            letter-spacing: -0.01em;
            color: var(--ink);
        }

        .acct-page .bal-paid {
            margin: 0;
            font-size: 13px;
            color: var(--ink-soft);
            text-align: right;
        }

        .acct-page .bal-state {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            width: fit-content;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 600;
        }

        .acct-page .bal-state.is-action {
            background: #f3f4f6;
            color: #374151;
        }

        .acct-page .bal-state.is-review {
            background: #e8f1fb;
            color: #1d4f8c;
        }

        .acct-page .bal-section {
            display: grid;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        .acct-page .bal-section-title {
            margin: 0;
            font-size: 13.5px;
            font-weight: 700;
            color: var(--ink);
        }

        .acct-page .bal-help {
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
            color: var(--ink-soft);
        }

        /* current proof on file */
        .acct-page .bal-current {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fafafa;
        }

        .acct-page .bal-thumb {
            flex: 0 0 auto;
            width: 56px;
            height: 56px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff center / cover no-repeat;
            display: grid;
            place-items: center;
            color: var(--ink-faint);
            font-size: 1.2rem;
            overflow: hidden;
        }

        .acct-page .bal-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .acct-page .bal-current-body {
            min-width: 0;
            display: grid;
            gap: 2px;
            font-size: 13px;
        }

        .acct-page .bal-current-body strong {
            font-size: 13.5px;
        }

        /* uploader */
        .acct-page .bal-form {
            display: grid;
            gap: 10px;
            margin: 0;
        }

        .acct-page .bal-form[hidden],
        .acct-page .bal-drop[hidden],
        .acct-page .bal-replace[hidden] {
            display: none;
        }

        .acct-page .bal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .acct-page .bal-replace {
            width: fit-content;
        }

        .acct-page .bal-file {
            position: absolute;
            width: 1px;
            height: 1px;
            margin: -1px;
            padding: 0;
            border: 0;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
        }

        .acct-page .bal-drop {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 20px 14px;
            text-align: center;
            cursor: pointer;
            border: 1.5px dashed #c5cad3;
            border-radius: 10px;
            background: #fafbfc;
            color: var(--ink-soft);
            transition: border-color .15s, background .15s;
        }

        .acct-page .bal-drop:hover,
        .acct-page .bal-drop.is-over {
            border-color: var(--riso-blue);
            background: #f3f8fd;
        }

        .acct-page .bal-file:focus-visible+.bal-drop {
            outline: 2px solid var(--riso-blue);
            outline-offset: 2px;
        }

        .acct-page .bal-drop i {
            font-size: 1.4rem;
            color: var(--riso-blue);
        }

        .acct-page .bal-drop strong {
            font-size: 14px;
            color: var(--ink);
        }

        .acct-page .bal-drop span {
            font-size: 12.5px;
        }

        .acct-page .bal-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
        }

        .acct-page .bal-preview[hidden] {
            display: none;
        }

        .acct-page .bal-preview-meta {
            min-width: 0;
            flex: 1;
            display: grid;
            gap: 2px;
            font-size: 12.5px;
            color: var(--ink-soft);
        }

        .acct-page .bal-preview-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--ink);
        }

        .acct-page .bal-remove {
            flex: 0 0 auto;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--ink-soft);
            cursor: pointer;
        }

        .acct-page .bal-remove:hover {
            color: #b32d18;
            border-color: #e6b4ab;
        }

        .acct-page .bal-form .btn[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        .acct-page .bal-link {
            font-size: 13px;
        }

        /* Phones: tidy pills; a card with a balance gets the full row instead of a half-width tile */
        @media (max-width: 640px) {
            .acct-page .order-list .order-card__badges {
                gap: 4px 6px;
            }

            .acct-page .order-list .order-pay {
                padding: 2px 8px;
                font-size: 10px;
                letter-spacing: 0.02em;
            }

            .acct-page .order-list .order-card.order-card--balance {
                grid-column: 1 / -1;
                gap: 12px;
            }

            .acct-page .order-list .order-card--balance .order-card__top {
                flex-direction: row;
                align-items: flex-start;
                justify-content: space-between;
                gap: 12px;
            }

            .acct-page .order-list .order-card--balance .order-card__id {
                width: auto;
                flex: 1;
                min-width: 0;
            }

            .acct-page .order-list .order-card--balance .order-card__amount {
                flex-shrink: 0;
            }

            .acct-page .order-list .order-card--balance .order-card__foot {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
                gap: 6px 16px;
            }

            .acct-page .order-list .order-card--balance .order-card__open {
                margin-left: auto;
                margin-top: 0;
            }

            .acct-page .order-card--balance .bal-head {
                align-items: flex-start;
                flex-direction: column;
                gap: 2px;
            }

            .acct-page .order-card--balance .bal-paid {
                width: auto;
            }
        }

        /* Modal: balance tile */
        .acct-page .acct-fact--balance {
            grid-column: span 2;
        }

        .acct-page .acct-fact--balance dd {
            display: grid;
            gap: 4px;
        }

        .acct-page .acct-balance__amount {
            font-family: var(--font-display);
            font-size: 1.35rem;
            letter-spacing: -0.01em;
        }

        .acct-page .acct-balance__meta {
            font-size: 12.5px;
            font-weight: 400;
            color: var(--ink-soft);
        }

        .acct-page .acct-balance__proof {
            font-size: 12.5px;
            font-weight: 400;
        }

        .acct-page .acct-balance__proof.is-empty {
            font-style: italic;
            color: var(--ink-faint);
        }

        @media (max-width: 640px) {
            .acct-page .acct-fact--balance {
                grid-column: 1 / -1;
            }

            .acct-page .order-card__balance {
                padding: 14px;
                gap: 14px;
            }

            .acct-page .bal-amount {
                font-size: 1.35rem;
            }

            .acct-page .bal-paid {
                text-align: left;
                width: 100%;
            }

            .acct-page .bal-drop {
                padding: 16px 12px;
            }

            .acct-page .bal-form .btn,
            .acct-page .bal-replace {
                width: 100%;
                min-height: 44px;
            }

            .acct-page .bal-actions .btn {
                flex: 1;
            }
        }


        /* =========================================================
           Order dialog layout (phone, tablet and desktop).
           Header, summary and footer are fixed; only the items list
           scrolls. Total + balance are always visible; the rest of the
           summary lives in "Details", which opens and closes smoothly
           (grid-rows + opacity, so closing animates too).
        ========================================================= */
        .acct-modal__more {
            display: none;
        }

        .acct-page .acct-modal__panel {
            display: flex;
            flex-direction: column;
            height: min(88vh, 820px);
            height: min(88dvh, 820px);
            max-height: none;
        }

        .acct-page .acct-modal__head,
        .acct-page .acct-modal__foot {
            flex: none;
        }

        .acct-page .acct-modal__scroll {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            gap: 10px;
            overflow: hidden;
        }

        .acct-page .acct-modal__summary {
            flex: none;
            display: grid;
            gap: 8px;
        }

        .acct-page .acct-facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin: 0;
        }

        .acct-page .acct-facts--key {
            grid-template-columns: minmax(0, 1fr);
        }

        .acct-page .acct-facts--key:empty {
            display: none;
        }

        /* key row: balance as one compact line, amount left, paid / proof right */
        .acct-page .acct-facts--has-balance .acct-fact--balance {
            grid-column: 1 / -1;
            padding: 8px 12px;
            border-left: 3px solid var(--riso-blue);
            background: #f3f8fd;
        }

        .acct-page .acct-facts--has-balance .acct-fact--balance dt {
            margin-bottom: 2px;
        }

        .acct-page .acct-facts--has-balance .acct-fact--balance dd {
            grid-template-columns: auto minmax(0, 1fr);
            grid-template-areas:
                "amt meta"
                "amt proof";
            align-items: center;
            column-gap: 14px;
            row-gap: 0;
        }

        .acct-page .acct-balance__amount {
            grid-area: amt;
            font-size: 1.25rem;
            line-height: 1.1;
        }

        .acct-page .acct-balance__meta {
            grid-area: meta;
            font-size: 12px;
        }

        .acct-page .acct-balance__proof {
            grid-area: proof;
            font-size: 12px;
        }

        /* the pinned summary already shows the order total */
        .acct-page .acct-modal.has-pinned .acct-fact--total {
            display: none;
        }

        /* details: animate height with grid rows, fade with opacity */
        .acct-page .acct-modal__details {
            display: grid;
            grid-template-rows: 0fr;
            opacity: 0;
            transition: grid-template-rows .3s cubic-bezier(.2, .8, .2, 1), opacity .22s ease;
        }

        .acct-page .acct-modal.is-details .acct-modal__details {
            grid-template-rows: 1fr;
            opacity: 1;
        }

        .acct-page .acct-modal__details-inner {
            min-height: 0;
            max-height: 34vh;
            max-height: 34dvh;
            overflow: hidden;
            display: grid;
            gap: 8px;
        }

        .acct-page .acct-modal.is-details .acct-modal__details-inner {
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .acct-page .acct-modal.is-details .acct-tracker {
            padding-top: 10px;
            padding-bottom: 6px;
        }

        /* items bar */
        .acct-page .acct-modal__section {
            flex: none;
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 8px;
            min-width: 0;
            min-height: 40px;
            margin: 0;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .acct-page .acct-modal__section h3 {
            min-width: 0;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .acct-page .acct-modal__section .acct-count {
            flex: none;
            white-space: nowrap;
        }

        .acct-page .acct-modal__more {
            display: inline-flex;
            flex: none;
            align-items: center;
            gap: 6px;
            margin-left: auto;
            padding: 6px 12px;
            border: 1px solid var(--line);
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
            white-space: nowrap;
            color: var(--ink-soft);
        }

        .acct-page .acct-modal__chev {
            font-size: 10px;
            transition: transform .3s;
        }

        .acct-page .acct-modal__section[aria-expanded="true"] .acct-modal__chev {
            transform: rotate(180deg);
        }

        /* items list: the only scroller */
        .acct-page .acct-modal__body {
            flex: 1 1 0;
            min-height: 120px;
            padding-bottom: 4px;
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }

        /* pinned order summary / order total (moved here by JS) */
        .acct-page .acct-modal__fixed {
            flex: none;
            max-height: 22%;
            overflow-y: auto;
            margin: 0;
            border: 1px solid var(--line);
            border-radius: var(--r-md, 12px);
            background: var(--paper-white, #fff);
            box-shadow: 0 -4px 10px -6px rgba(0, 0, 0, 0.16);
        }

        .acct-page .acct-modal__fixed:empty {
            display: none;
        }

        /* the summary and the total are the same element (.modal-order-summary.order-total):
           drop main.css's boxed look here (the pinned slot supplies the frame) and tighten its rows */
        .acct-page .acct-modal__fixed .modal-order-summary {
            margin: 0;
            padding: 0 12px 6px;
            background: none;
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }

        .acct-page .acct-modal__fixed .modal-order-summary>* {
            margin: 0;
            padding-top: 4px;
            padding-bottom: 4px;
            min-height: 0;
        }

        /* the total reads as the bottom line of a receipt */
        .acct-page .acct-modal__fixed .order-total {
            padding-top: 3px;
            color: var(--ink);
            font-weight: 700;
        }

        .acct-page .acct-modal__fixed .order-total,
        .acct-page .acct-modal__fixed .order-total * {
            font-size: 13px !important;
            line-height: 1.5 !important;
        }

        /* slim scrollbars inside the dialog */
        .acct-page .acct-modal * {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.25) transparent;
        }

        .acct-page .acct-modal ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .acct-page .acct-modal ::-webkit-scrollbar-track {
            background: transparent;
        }

        .acct-page .acct-modal ::-webkit-scrollbar-thumb {
            border-radius: 3px;
            background: rgba(0, 0, 0, 0.25);
        }

        @media (min-width: 641px) {
            .acct-page .acct-modal__details-inner #modalFacts {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .acct-page .acct-modal__panel {
                height: 94vh;
                height: 94dvh;
            }

            .acct-page .acct-modal__head {
                padding: 22px 56px 10px 14px;
            }

            .acct-page .acct-modal__head h2 {
                margin-bottom: 6px;
                font-size: 1.05rem;
            }

            .acct-page .acct-modal__scroll {
                padding: 8px 12px 10px;
            }

            .acct-page .acct-modal__details-inner {
                max-height: 32dvh;
            }

            .acct-page .acct-modal__foot {
                padding-top: 8px;
                padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .acct-page .acct-modal__details,
            .acct-page .acct-modal__chev {
                transition: none;
            }
        }
    </style>
</head>

<body class="acct-page">
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li class="has-sub">
                <a href="../../website/main.php"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a>
                <ul class="side-nav-sub" aria-label="Printing services">
                    <li><a href="../../website/main.php#offset" data-service="offset"><span class="side-nav-dot" data-ink="black" aria-hidden="true"></span><span class="side-nav-label">Offset Printing</span></a></li>
                    <li><a href="../../website/main.php#digital" data-service="digital"><span class="side-nav-dot" data-ink="cyan" aria-hidden="true"></span><span class="side-nav-label">Digital Printing</span></a></li>
                    <li><a href="../../website/main.php#riso" data-service="riso"><span class="side-nav-dot" data-ink="magenta" aria-hidden="true"></span><span class="side-nav-label">RISO Printing</span></a></li>
                    <li><a href="../../website/main.php#other" data-service="other"><span class="side-nav-dot" data-ink="yellow" aria-hidden="true"></span><span class="side-nav-label">Other Services</span></a></li>
                </ul>
            </li>
            <li><a href="../../website/ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="../../website/about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="../../website/contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="#" class="chat-icon" id="chatButton">
                    <span class="side-nav-icon">
                        <i class="fas fa-comments"></i>
                        <span class="chat-count" id="chatCount">0</span>
                    </span>
                    <span class="side-nav-label">Chat</span>
                </a>
            </li>
            <li>
                <a href="../../website/view_cart.php" class="cart-icon">
                    <span class="side-nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </span>
                    <span class="side-nav-label">Cart</span>
                </a>
            </li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="profile.php" class="user-profile active">
                    <i class="fas fa-user"></i>
                    <span class="side-nav-label user-name"><?php echo htmlspecialchars($display_name === 'friend' ? 'User' : $display_name); ?></span>
                </a>
            </li>
            <li>
                <a href="../../accounts/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="side-nav-label">Log Out</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Account Hero -->
    <section class="acct-hero hide">
        <div class="container">
            <div class="acct-hero__texture halftone"></div>
            <div class="acct-hero-inner">
                <span class="section-eyebrow"><span class="reg-mark"></span> My account</span>
                <h1 class="acct-hero-title">Welcome back, <span class="registered" data-text="<?php echo htmlspecialchars($display_name); ?>."><?php echo htmlspecialchars($display_name); ?>.</span></h1>
                <p class="acct-hero-sub">
                    Keep your details up to date and follow every order from proof to pickup.
                </p>
            </div>
        </div>
    </section>

    <!-- Account Main -->
    <section class="acct-main hide">
        <div class="container">

            <?php if (isset($_GET['order_success'])): ?>
                <div class="acct-notice acct-notice--success" id="success-message" role="status">
                    <span class="acct-notice__icon"><i class="fas fa-check"></i></span>
                    <div class="acct-notice__body">
                        <strong>Success!</strong> Your order has been placed and is pending payment verification.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['balance_uploaded'])): ?>
                <div class="acct-notice acct-notice--success" id="balance-message" role="status">
                    <span class="acct-notice__icon"><i class="fas fa-check"></i></span>
                    <div class="acct-notice__body">
                        <strong>Balance proof uploaded.</strong> We'll check it and update your order once the balance is verified.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($user_data['email_verified'])): ?>
                <div class="acct-notice acct-notice--warn" role="alert">
                    <span class="acct-notice__icon"><i class="fas fa-exclamation"></i></span>
                    <div class="acct-notice__body">
                        <strong>Your email isn't verified yet.</strong> Verify it to keep your account secure.
                    </div>
                    <a href="../../accounts/email-verification.php" class="btn btn-secondary acct-notice__action">
                        <i class="fas fa-envelope"></i> Verify now
                    </a>
                </div>
            <?php endif; ?>

            <div class="acct-layout">

                <!-- Identity card -->
                <aside class="acct-aside">
                    <div class="acct-card profile-card">
                        <div class="profile-card__top">
                            <div class="acct-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                            <div class="profile-card__id">
                                <h2><?php echo htmlspecialchars($full_name); ?></h2>
                                <?php if ($username !== ''): ?>
                                    <span class="profile-card__handle">@<?php echo htmlspecialchars($username); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="acct-badges">
                            <span class="acct-badge <?php echo $is_personal ? 'acct-badge--personal' : 'acct-badge--company'; ?>">
                                <i class="fas <?php echo $is_personal ? 'fa-user' : 'fa-building'; ?>"></i>
                                <?php echo $is_personal ? 'Personal account' : 'Company account'; ?>
                            </span>
                            <?php if (!empty($user_data['email_verified'])): ?>
                                <span class="acct-badge acct-badge--ok"><i class="fas fa-check-circle"></i> Email verified</span>
                            <?php else: ?>
                                <span class="acct-badge acct-badge--alert"><i class="fas fa-exclamation-circle"></i> Email not verified</span>
                            <?php endif; ?>
                        </div>

                        <dl class="detail-list">
                            <?php if ($is_personal): ?>
                                <div class="detail-row">
                                    <dt><i class="fas fa-phone"></i> Contact</dt>
                                    <?php echo $dd($user_data['personal_contact'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-map-marker-alt"></i> Address</dt>
                                    <?php echo $dd($address_text); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-birthday-cake"></i> Birthdate</dt>
                                    <?php echo $dd($birthdate_text); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-venus-mars"></i> Gender</dt>
                                    <?php echo $dd($user_data['gender'] ?? ''); ?>
                                </div>
                            <?php elseif ($is_company): ?>
                                <div class="detail-row">
                                    <dt><i class="fas fa-file-invoice"></i> Taxpayer name</dt>
                                    <?php echo $dd($user_data['taxpayer_name'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-user-tie"></i> Contact person</dt>
                                    <?php echo $dd($user_data['contact_person'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-phone"></i> Contact</dt>
                                    <?php echo $dd($user_data['company_contact'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-map-marker-alt"></i> Address</dt>
                                    <?php echo $dd($address_text); ?>
                                </div>
                            <?php else: ?>
                                <div class="detail-row">
                                    <dt><i class="fas fa-info-circle"></i> Details</dt>
                                    <dd class="is-empty">No customer information available.</dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <div class="profile-card__actions">
                            <a href="edit_profile.php" class="btn btn-primary">
                                <i class="fas fa-pen"></i> Edit profile
                            </a>
                            <a href="edit_profile.php#sec-password" class="btn btn-secondary">
                                <i class="fas fa-lock"></i> Change password
                            </a>
                        </div>
                    </div>
                </aside>

                <!-- Orders -->
                <div class="acct-content">
                    <div class="acct-section-head">
                        <div>
                            <span class="section-eyebrow"><span class="reg-mark"></span> Order history</span>
                            <h2 class="section-title">Your orders</h2>
                            <p class="section-subtitle">Select an order to see its items, quantities and pricing.</p>
                        </div>
                        <a href="../../website/main.php#services" class="view-all">
                            Browse services <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                    <?php if (!empty($orders)): ?>
                        <div class="stat-strip">
                            <div class="stat-tile">
                                <span class="stat-tile__value"><?php echo $order_total; ?></span>
                                <span class="stat-tile__label">Total orders</span>
                            </div>
                            <div class="stat-tile" data-ink="cyan">
                                <span class="stat-tile__value"><?php echo $order_active; ?></span>
                                <span class="stat-tile__label">In progress</span>
                            </div>
                            <div class="stat-tile" data-ink="ok">
                                <span class="stat-tile__value"><?php echo $order_completed; ?></span>
                                <span class="stat-tile__label">Completed</span>
                            </div>
                        </div>

                        <div class="order-list">
                            <?php foreach ($orders as $order):
                                $status_class = preg_replace('/[^a-z0-9_-]/i', '', (string) $order['status']);
                                $status_label = ucfirst(str_replace('_', ' ', $order['status']));
                                $placed_text  = date('M j, Y · g:i A', strtotime($order['created_at']));
                                $placed_short = date('M j, Y', strtotime($order['created_at']));
                                $proof_url    = !empty($order['payment_proof'])
                                    ? 'payment_proof.php?order_id=' . (int) $order['order_id']
                                    : '';
                                $balance_proof_url = !empty($order['balance_proof'])
                                    ? 'payment_proof.php?order_id=' . (int) $order['order_id'] . '&type=balance'
                                    : '';
                            ?>
                                <article class="order-card status-<?php echo $status_class; ?><?php echo $order['balance_owed'] > 0 ? ' order-card--balance' : ''; ?>"
                                    data-order-id="<?php echo (int) $order['order_id']; ?>"
                                    data-title="<?php echo htmlspecialchars($order['title']); ?>"
                                    data-status="<?php echo $status_class; ?>"
                                    data-status-label="<?php echo htmlspecialchars($status_label); ?>"
                                    data-date="<?php echo htmlspecialchars($placed_text); ?>"
                                    data-total="₱<?php echo number_format($order['total_amount'], 2); ?>"
                                    data-proof="<?php echo htmlspecialchars($proof_url); ?>"
                                    data-down="<?php echo $order['is_down'] ? '1' : '0'; ?>"
                                    data-payment="<?php echo htmlspecialchars($order['pay_label']); ?>"
                                    data-paid="<?php echo $order['is_down'] ? '₱' . number_format($order['paid_so_far'], 2) : ''; ?>"
                                    data-balance="<?php echo $order['balance_owed'] > 0 ? '₱' . number_format($order['balance_owed'], 2) : ''; ?>"
                                    data-balance-proof="<?php echo htmlspecialchars($balance_proof_url); ?>"
                                    data-items="<?php echo (int) $order['item_count']; ?>"
                                    data-cancel-reason="<?php echo htmlspecialchars($order['cancellation_reason'] ?? ''); ?>">
                                    <div class="order-card__top">
                                        <div class="order-card__id">
                                            <h3><?php echo htmlspecialchars($order['title']); ?></h3>
                                            <span class="order-card__number">Ref No. <?php echo (int) $order['order_id']; ?></span>
                                        </div>
                                        <div class="order-card__amount">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                                    </div>
                                    <div class="order-card__badges">
                                        <span class="order-status status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label); ?></span>
                                        <?php if ($status_class !== 'cancelled'): ?>
                                            <span class="order-pay order-pay--<?php echo $order['pay_class']; ?>"><?php echo htmlspecialchars($order['pay_label']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($status_class === 'cancelled' && !empty($order['cancellation_reason'])): ?>
                                        <div class="order-card__cancel-note">
                                            <i class="fas fa-ban"></i>
                                            <span><strong>Reason:</strong> <?php echo htmlspecialchars($order['cancellation_reason']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($order['balance_owed'] > 0): ?>
                                        <div class="order-card__balance">
                                            <div class="bal-head">
                                                <div>
                                                    <span class="bal-label">Balance due</span>
                                                    <strong class="bal-amount">₱<?php echo number_format($order['balance_owed'], 2); ?></strong>
                                                </div>
                                                <p class="bal-paid">₱<?php echo number_format($order['paid_so_far'], 2); ?> paid of ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                                            </div>
                                            <?php if ($balance_proof_url !== ''): ?>
                                                <span class="bal-state is-review"><i class="fas fa-hourglass-half" aria-hidden="true"></i> Proof received, under review</span>
                                            <?php else: ?>
                                                <span class="bal-state is-action"><i class="fas fa-circle-info" aria-hidden="true"></i> Payment needed</span>
                                            <?php endif; ?>

                                            <div class="bal-section">
                                                <h4 class="bal-section-title">Proof of payment</h4>
                                                <?php if ($balance_proof_url !== ''): ?>
                                                    <div class="bal-current">
                                                        <a class="bal-thumb" href="<?php echo htmlspecialchars($balance_proof_url); ?>" target="_blank" rel="noopener" aria-label="Open current balance proof">
                                                            <img src="<?php echo htmlspecialchars($balance_proof_url); ?>" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'fas fa-file-lines'}))">
                                                        </a>
                                                        <div class="bal-current-body">
                                                            <strong>Current proof</strong>
                                                            <a href="<?php echo htmlspecialchars($balance_proof_url); ?>" target="_blank" rel="noopener" class="payment-proof-link bal-link">View full size <i class="fas fa-external-link-alt"></i></a>
                                                        </div>
                                                    </div>
                                                    <p class="bal-help">We'll verify it soon. Replace it only if you uploaded the wrong file.</p>
                                                    <button type="button" class="btn btn-secondary btn-sm bal-replace" data-bal-replace>Replace proof</button>
                                                <?php else: ?>
                                                    <p class="bal-help">Pay the remaining balance, then upload your proof of payment.</p>
                                                <?php endif; ?>

                                                <form action="upload_balance_proof.php" method="post" enctype="multipart/form-data" class="bal-form" data-bal-form<?php echo $balance_proof_url !== '' ? ' hidden' : ''; ?>>
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
                                                    <input type="file" name="balance_proof" id="balFile<?php echo (int) $order['order_id']; ?>" class="bal-file" accept="image/*,.pdf" required>
                                                    <label class="bal-drop" for="balFile<?php echo (int) $order['order_id']; ?>">
                                                        <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                                                        <strong>Choose a file</strong>
                                                        <span>or drag and drop &middot; JPG, PNG or PDF</span>
                                                    </label>
                                                    <div class="bal-preview" data-bal-preview hidden>
                                                        <div class="bal-thumb" data-bal-thumb></div>
                                                        <div class="bal-preview-meta">
                                                            <span class="bal-preview-name" data-bal-name></span>
                                                            <span data-bal-size></span>
                                                        </div>
                                                        <button type="button" class="bal-remove" data-bal-remove aria-label="Remove selected file"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                                                    </div>
                                                    <div class="bal-actions">
                                                        <button type="submit" class="btn btn-primary btn-sm" data-bal-submit disabled><?php echo $balance_proof_url !== '' ? 'Upload new proof' : 'Upload proof'; ?></button>
                                                        <?php if ($balance_proof_url !== ''): ?>
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bal-cancel>Cancel</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="order-card__foot">
                                        <span>
                                            <i class="far fa-calendar"></i>
                                            <span class="order-card__date-full"><?php echo htmlspecialchars($placed_text); ?></span>
                                            <span class="order-card__date-short"><?php echo htmlspecialchars($placed_short); ?></span>
                                        </span>
                                        <span>
                                            <i class="fas fa-receipt"></i>
                                            <?php if (!empty($order['payment_proof'])): ?>
                                                <a href="<?php echo htmlspecialchars($proof_url); ?>"
                                                    target="_blank" rel="noopener" class="payment-proof-link">
                                                    Payment proof <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                Proof not uploaded
                                            <?php endif; ?>
                                        </span>
                                        <button type="button" class="order-card__open" onclick="viewOrderDetails(<?php echo (int) $order['order_id']; ?>, <?php echo htmlspecialchars(json_encode($order['title']), ENT_QUOTES); ?>)">
                                            View details <i class="fas fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="acct-empty">
                            <div class="acct-empty__icon"><i class="fas fa-shopping-bag"></i></div>
                            <h3>No orders yet</h3>
                            <p>You haven't placed any orders yet. Browse our services and your first order will show up here.</p>
                            <a href="../../website/main.php#services" class="btn btn-primary">
                                <i class="fas fa-store"></i> Start shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </section>

    <!-- Order Details Dialog -->
    <div class="acct-modal" id="orderModal" role="dialog" aria-modal="true" aria-labelledby="modalOrderTitle" aria-hidden="true">
        <div class="acct-modal__panel">
            <div class="acct-modal__head">
                <span class="acct-modal__eyebrow"><span class="reg-mark"></span> Order details</span>
                <h2 id="modalOrderTitle">Order</h2>
                <div class="acct-modal__meta">
                    <span class="acct-modal__ref" id="modalOrderRef"></span>
                    <span class="order-status" id="modalOrderStatus" hidden></span>
                </div>
                <button type="button" class="acct-modal__close" data-close aria-label="Close order details">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="acct-modal__scroll" id="modalScroll">
                <div class="acct-modal__summary" id="modalSummary">
                    <dl class="acct-facts acct-facts--key" id="modalKey"></dl>
                    <div class="acct-modal__details" id="modalDetails">
                        <div class="acct-modal__details-inner">
                            <div id="modalProgress"></div>
                            <dl class="acct-facts" id="modalFacts"></dl>
                        </div>
                    </div>
                </div>

                <div class="acct-modal__section" id="modalSummaryToggle" role="button" tabindex="0" aria-expanded="false" aria-controls="modalSummary">
                    <h3>Items in this order</h3>
                    <span class="acct-count" id="modalItemCount"></span>
                    <span class="acct-modal__more">Details <i class="fas fa-chevron-down acct-modal__chev" aria-hidden="true"></i></span>
                </div>
                <div class="acct-modal__body" id="orderModalContent" aria-live="polite">
                    <!-- Order items are loaded here via AJAX -->
                </div>
                <div class="acct-modal__fixed" id="modalFixed"><!-- order summary / total is pinned here --></div>
            </div>

            <div class="acct-modal__foot">
                <p><span class="acct-modal__foot-q">Questions about this order?</span> <button type="button" class="acct-modal__chat" id="modalChatBtn"><i class="fas fa-comments" aria-hidden="true"></i> Chat with us</button></p>
                <button type="button" class="btn btn-secondary" data-close>Close</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>AMDP</h3>
                    <p>Professional printing services with quality, speed, and precision for all your business needs.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/profile.php?id=100063881538670"><i class="fab fa-facebook-f"></i></a>
                        <a href=""><i class="fab fa-twitter"></i></a>
                        <a href=""><i class="fab fa-instagram"></i></a>
                        <a href=""><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="../../website/main.php#offset">Offset Printing</a></li>
                        <li><a href="../../website/main.php#digital">Digital Printing</a></li>
                        <li><a href="../../website/main.php#riso">RISO Printing</a></li>
                        <li><a href="../../website/main.php#other">Other Services</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="../../website/about.php">About Us</a></li>
                        <li><a href="../../website/about.php">Our Team</a></li>
                        <li><a href="../../website/about.php">Careers</a></li>
                        <li><a href="../../website/about.php">Testimonials</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="../../website/contact.php">Contact Us</a></li>
                        <li><a href="../../website/contact.php">FAQ</a></li>
                        <li><a href="../../website/contact.php">Shipping Info</a></li>
                        <li><a href="../../website/contact.php">Returns</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i>Fausta Rd Lucero St Mabolo, Malolos, Philippines</li>
                        <li><i class="fas fa-phone"></i> (044) 796-4101</li>
                        <li><i class="fas fa-envelope"></i> activemediaprint@gmail.com</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2025 Active Media Designs & Printing. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="">Privacy Policy</a>
                    <a href="">Terms of Service</a>
                    <a href="">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Chat Widget -->
    <div class="chat-widget" id="chatWidget">
        <div class="chat-header">
            <button class="chat-back-btn" id="chatBackBtn">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 class="chat-title" id="chatTitle">Messages</h3>
            <button class="chat-close" id="chatCloseBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-body">
            <!-- Conversations List -->
            <div class="chat-conversations" id="chatConversations">
                <button class="chat-new-btn" id="newChatBtn">
                    <i class="fas fa-plus"></i> New Conversation
                </button>
                <div id="conversationsList"></div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages" id="chatMessages">
                <div class="messages-list" id="messagesList"></div>
                <div class="chat-input-area" id="chatInputArea">
                    <div class="chat-input-wrapper">
                        <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1"></textarea>
                        <button class="chat-send-btn" id="chatSendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        // Profile page: order details dialog + order-success banner
        (function() {
            'use strict';

            var modal = document.getElementById('orderModal');
            var panel = modal.querySelector('.acct-modal__panel');
            var scrollEl = document.getElementById('modalScroll');
            var titleEl = document.getElementById('modalOrderTitle');
            var refEl = document.getElementById('modalOrderRef');
            var statusEl = document.getElementById('modalOrderStatus');
            var progressEl = document.getElementById('modalProgress');
            var factsEl = document.getElementById('modalFacts');
            var keyEl = document.getElementById('modalKey');
            var countEl = document.getElementById('modalItemCount');
            var contentEl = document.getElementById('orderModalContent');
            var fixedEl = document.getElementById('modalFixed');
            var closeBtn = modal.querySelector('[data-close]');
            var chatBtn = document.getElementById('modalChatBtn');
            var toggleEl = document.getElementById('modalSummaryToggle');
            var lastFocus = null;
            var requestId = 0;

            // Order of a normal order's life; "cancelled" is handled separately
            var STEPS = [
                ['pending', 'Pending'],
                ['processing', 'Processing'],
                ['ready_for_pickup', 'Ready for pickup'],
                ['completed', 'Completed']
            ];

            // Details open/close is pure CSS (grid-rows + opacity transition)
            function setDetails(on) {
                modal.classList.toggle('is-details', on);
                toggleEl.setAttribute('aria-expanded', on ? 'true' : 'false');
            }

            function detailsOpen() {
                return toggleEl.getAttribute('aria-expanded') === 'true';
            }

            toggleEl.addEventListener('click', function() {
                setDetails(!detailsOpen());
            });
            toggleEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setDetails(!detailsOpen());
                }
            });

            function openModal() {
                setDetails(false);
                lastFocus = document.activeElement;
                modal.classList.add('is-open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('acct-modal-open');
                closeBtn.focus();
                if (document.activeElement !== closeBtn) {
                    requestAnimationFrame(function() {
                        closeBtn.focus();
                    });
                }
            }

            function closeModal() {
                modal.classList.remove('is-open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('acct-modal-open');
                requestId++; // ignore any response still in flight
                if (lastFocus && lastFocus.focus) lastFocus.focus();
            }

            modal.addEventListener('click', function(e) {
                if (e.target === modal || e.target.closest('[data-close]')) closeModal();
            });

            // Phones: drag the header down to dismiss the bottom sheet
            (function() {
                var head = modal.querySelector('.acct-modal__head');
                var phone = window.matchMedia ? window.matchMedia('(max-width: 640px)') : null;
                var startY = null;
                var dy = 0;

                head.addEventListener('touchstart', function(e) {
                    if (!phone || !phone.matches || e.touches.length !== 1 || e.target.closest('[data-close]')) return;
                    startY = e.touches[0].clientY;
                    dy = 0;
                    panel.style.transition = 'none';
                }, {
                    passive: true
                });

                head.addEventListener('touchmove', function(e) {
                    if (startY === null) return;
                    dy = Math.max(0, e.touches[0].clientY - startY);
                    panel.style.transform = 'translateY(' + dy + 'px)';
                }, {
                    passive: true
                });

                function release() {
                    if (startY === null) return;
                    var dismiss = dy > 90;
                    startY = null;
                    dy = 0;
                    // hand control back to the stylesheet: it animates from where the finger let go
                    panel.style.transition = '';
                    panel.style.transform = '';
                    if (dismiss) closeModal();
                }

                head.addEventListener('touchend', release);
                head.addEventListener('touchcancel', release);
            })();

            // "Chat with us": close the dialog, then open the chat widget
            chatBtn.addEventListener('click', function() {
                closeModal();
                setTimeout(function() {
                    var chatButton = document.getElementById('chatButton');
                    if (chatButton) chatButton.click();
                }, 320);
            });

            document.addEventListener('keydown', function(e) {
                if (!modal.classList.contains('is-open')) return;

                if (e.key === 'Escape') {
                    closeModal();
                    return;
                }

                // Keep Tab inside the dialog while it is open
                if (e.key === 'Tab') {
                    var focusable = panel.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])');
                    if (!focusable.length) return;
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });

            // ---- small DOM helper ----
            function make(tag, className, html) {
                var node = document.createElement(tag);
                if (className) node.className = className;
                if (html !== undefined) node.innerHTML = html;
                return node;
            }

            // ---- header, progress tracker and key facts (from the order card) ----
            function renderSummary(orderId, card, orderTitle) {
                var d = card ? card.dataset : {};

                titleEl.textContent = orderTitle || d.title || ('Order #' + orderId);
                refEl.textContent = 'Ref No. ' + orderId;

                if (d.status) {
                    statusEl.className = 'order-status status-' + d.status;
                    statusEl.textContent = d.statusLabel || d.status;
                    statusEl.hidden = false;
                } else {
                    statusEl.hidden = true;
                }

                // Progress
                var steps = STEPS;
                progressEl.innerHTML = '';
                var current = -1;
                steps.forEach(function(step, i) {
                    if (step[0] === d.status) current = i;
                });
                if (d.status === 'completed') current = steps.length; // every step done

                if (d.status === 'cancelled') {
                    var reasonHtml = d.cancelReason ?
                        '<strong>This order was cancelled.</strong> Reason: ' + escapeHtml(d.cancelReason) :
                        '<strong>This order was cancelled.</strong> Chat with us if you have any questions.';
                    progressEl.appendChild(make('p', 'acct-modal__alert',
                        '<i class="fas fa-ban" aria-hidden="true"></i>' +
                        '<span>' + reasonHtml + '</span>'));
                } else if (current > -1) {
                    var list = make('ol', 'acct-tracker');
                    list.setAttribute('aria-label', 'Order progress');
                    steps.forEach(function(step, i) {
                        var state = i < current ? 'is-done' : (i === current ? 'is-current' : '');
                        var item = make('li', 'acct-tracker__step ' + state,
                            '<span class="acct-tracker__dot">' + (i < current ? '<i class="fas fa-check"></i>' : (i + 1)) + '</span>' +
                            '<span>' + step[1] + '</span>');
                        if (i === current) item.setAttribute('aria-current', 'step');
                        list.appendChild(item);
                    });
                    progressEl.appendChild(list);

                    // Phones show only the dots; this line says where the order is
                    var nowIdx = Math.min(current, steps.length - 1);
                    progressEl.appendChild(make('p', 'acct-tracker__now',
                        'Step ' + (nowIdx + 1) + ' of ' + steps.length + ' &middot; <strong>' + steps[nowIdx][1] + '</strong>'));
                }

                // Key facts
                factsEl.innerHTML = '';
                keyEl.innerHTML = '';
                keyEl.classList.remove('acct-facts--has-balance');

                function fact(icon, label, valueNode, extra) {
                    var wrap = make('div', 'acct-fact' + (extra ? ' ' + extra : ''));
                    wrap.appendChild(make('dt', '', '<i class="' + icon + '" aria-hidden="true"></i> ' + label));
                    wrap.appendChild(valueNode);
                    // total + balance stay in the always-visible row; the rest live in Details
                    var isKey = extra && (extra.indexOf('acct-fact--balance') > -1 || extra.indexOf('acct-fact--total') > -1);
                    (isKey ? keyEl : factsEl).appendChild(wrap);
                }

                var placed = make('dd');
                placed.textContent = d.date || '—';
                fact('far fa-calendar', 'Placed', placed);

                var total = make('dd');
                total.textContent = d.total || '—';
                fact('fas fa-wallet', 'Order total', total, 'acct-fact--total');

                if (d.payment) {
                    var pay = make('dd');
                    pay.textContent = d.payment;
                    fact('fas fa-circle-check', 'Payment', pay);
                }

                var proof = make('dd');
                if (d.proof) {
                    var link = make('a', '', 'View proof <i class="fas fa-external-link-alt" aria-hidden="true"></i>');
                    link.href = d.proof;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    proof.appendChild(link);
                } else {
                    proof.className = 'is-empty';
                    proof.textContent = 'Not uploaded';
                }
                fact('fas fa-receipt', d.down === '1' ? 'Downpayment proof' : 'Payment proof', proof);

                if (d.down === '1') {
                    if (d.balance) {
                        // Everything about the balance in one tile: amount, how much is paid, proof status
                        var bal = make('dd');
                        bal.appendChild(make('span', 'acct-balance__amount', escapeHtml(d.balance)));
                        bal.appendChild(make('span', 'acct-balance__meta', escapeHtml(d.paid || '') + ' paid of ' + escapeHtml(d.total || '')));
                        if (d.balanceProof) {
                            var bl = make('span', 'acct-balance__proof', 'Balance proof sent &middot; ');
                            var blink = make('a', '', 'View proof <i class="fas fa-external-link-alt" aria-hidden="true"></i>');
                            blink.href = d.balanceProof;
                            blink.target = '_blank';
                            blink.rel = 'noopener';
                            bl.appendChild(blink);
                            bal.appendChild(bl);
                        } else {
                            bal.appendChild(make('span', 'acct-balance__proof is-empty', 'Balance proof not uploaded yet'));
                        }
                        fact('fas fa-coins', 'Balance due', bal, 'acct-fact--balance');
                        keyEl.classList.add('acct-facts--has-balance');
                    } else if (d.paid) {
                        var paid = make('dd');
                        paid.textContent = d.paid;
                        fact('fas fa-circle-check', 'Paid so far', paid);
                    }
                }

                var items = parseInt(d.items, 10);
                countEl.textContent = items > 0 ? items + (items === 1 ? ' item' : ' items') : '';
            }

            function showLoading() {
                fixedEl.innerHTML = '';
                modal.classList.remove('has-pinned');
                contentEl.innerHTML =
                    '<div class="acct-skel" aria-hidden="true">' +
                    '<div class="acct-skel__card"></div>' +
                    '<div class="acct-skel__card"></div>' +
                    '</div>' +
                    '<span class="acct-sr">Loading order details...</span>';
            }

            function showError(orderId, orderTitle) {
                fixedEl.innerHTML = '';
                modal.classList.remove('has-pinned');
                contentEl.innerHTML =
                    '<div class="acct-modal__state acct-modal__state--error">' +
                    '<i class="fas fa-exclamation-triangle"></i>' +
                    '<p>Error loading order details. Please try again.</p>' +
                    '<button type="button" class="btn btn-secondary" data-retry>' +
                    '<i class="fas fa-redo"></i> Try again' +
                    '</button>' +
                    '</div>';
                contentEl.querySelector('[data-retry]').addEventListener('click', function() {
                    window.viewOrderDetails(orderId, orderTitle);
                });
            }

            // Order items are tap-to-open cards. The markup arrives by AJAX (scripts in it
            // would not run), so the clicks are handled here, once, on the container.
            function setItemOpen(item, open) {
                item.classList.toggle('is-open', open);
                var btn = item.querySelector('.oi-toggle');
                if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            function syncExpandAll() {
                var btn = contentEl.querySelector('.oi-expand');
                if (!btn) return;
                var items = contentEl.querySelectorAll('.order-item-detail');
                var all = items.length > 0 && Array.prototype.every.call(items, function(i) {
                    return i.classList.contains('is-open');
                });
                btn.setAttribute('aria-pressed', all ? 'true' : 'false');
                btn.querySelector('span').textContent = all ? 'Collapse all' : 'Expand all';
            }

            contentEl.addEventListener('click', function(e) {
                var toggle = e.target.closest('.oi-toggle');
                if (toggle) {
                    var item = toggle.closest('.order-item-detail');
                    var opening = !item.classList.contains('is-open');
                    setItemOpen(item, opening);
                    syncExpandAll();
                    // once it has finished growing, bring the rest of the card into view
                    if (opening) {
                        setTimeout(function() {
                            item.scrollIntoView({
                                behavior: 'smooth',
                                block: 'nearest'
                            });
                        }, 340);
                    }
                    return;
                }

                var expandAll = e.target.closest('.oi-expand');
                if (expandAll) {
                    var openAll = expandAll.getAttribute('aria-pressed') !== 'true';
                    contentEl.querySelectorAll('.order-item-detail').forEach(function(i) {
                        setItemOpen(i, openAll);
                    });
                    syncExpandAll();
                }
            });

            // Called from each order card
            window.viewOrderDetails = function(orderId, orderTitle) {
                var thisRequest = ++requestId;
                var card = document.querySelector('.order-card[data-order-id="' + parseInt(orderId, 10) + '"]');

                renderSummary(orderId, card, orderTitle);
                showLoading();
                scrollEl.scrollTop = 0;
                if (!modal.classList.contains('is-open')) openModal();

                fetch('get_order_items.php?order_id=' + encodeURIComponent(orderId))
                    .then(function(response) {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.text();
                    })
                    .then(function(html) {
                        if (thisRequest !== requestId) return;
                        contentEl.innerHTML = html;
                        // Pin the order summary / order total so it never scrolls with the items
                        fixedEl.innerHTML = '';
                        var sum = contentEl.querySelector('.modal-order-summary') || contentEl.querySelector('.order-total');
                        if (sum) fixedEl.appendChild(sum);
                        modal.classList.toggle('has-pinned', !!sum);
                    })
                    .catch(function(error) {
                        console.error('Error fetching order details:', error);
                        if (thisRequest !== requestId) return;
                        showError(orderId, orderTitle);
                    });
            };

            // Clicking anywhere on a card (except its links/buttons) opens it too
            document.querySelectorAll('.order-card').forEach(function(card) {
                card.addEventListener('click', function(e) {
                    if (e.target.closest('a, button, input, label, form')) return;
                    window.viewOrderDetails(card.dataset.orderId, card.dataset.title);
                });
            });

            // Balance proof uploader: image/PDF preview, drag and drop, remove
            document.querySelectorAll('[data-bal-form]').forEach(function(form) {
                var input = form.querySelector('input[type="file"]');
                var drop = form.querySelector('.bal-drop');
                var preview = form.querySelector('[data-bal-preview]');
                var thumb = form.querySelector('[data-bal-thumb]');
                var nameEl = form.querySelector('[data-bal-name]');
                var sizeEl = form.querySelector('[data-bal-size]');
                var submit = form.querySelector('[data-bal-submit]');
                var url = null;
                var section = form.closest('.bal-section');
                var replaceBtn = section ? section.querySelector('[data-bal-replace]') : null;
                var cancelBtn = form.querySelector('[data-bal-cancel]');

                if (replaceBtn) {
                    replaceBtn.addEventListener('click', function() {
                        form.hidden = false;
                        replaceBtn.hidden = true;
                        input.focus();
                    });
                }
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        clear();
                        form.hidden = true;
                        if (replaceBtn) {
                            replaceBtn.hidden = false;
                            replaceBtn.focus();
                        }
                    });
                }

                function clear() {
                    if (url) {
                        URL.revokeObjectURL(url);
                        url = null;
                    }
                    input.value = '';
                    thumb.innerHTML = '';
                    preview.hidden = true;
                    drop.hidden = false;
                    submit.disabled = true;
                }

                function show(file) {
                    if (url) {
                        URL.revokeObjectURL(url);
                        url = null;
                    }
                    thumb.innerHTML = '';
                    if (file.type.indexOf('image/') === 0) {
                        url = URL.createObjectURL(file);
                        var img = document.createElement('img');
                        img.src = url;
                        img.alt = 'Preview of the selected proof';
                        thumb.appendChild(img);
                    } else {
                        thumb.innerHTML = '<i class="fas fa-file-pdf" aria-hidden="true"></i>';
                    }
                    nameEl.textContent = file.name;
                    sizeEl.textContent = file.size >= 1048576 ?
                        (file.size / 1048576).toFixed(1) + ' MB' :
                        Math.max(1, Math.round(file.size / 1024)) + ' KB';
                    preview.hidden = false;
                    drop.hidden = true;
                    submit.disabled = false;
                }

                input.addEventListener('change', function() {
                    if (input.files && input.files[0]) show(input.files[0]);
                    else clear();
                });
                form.querySelector('[data-bal-remove]').addEventListener('click', clear);

                ['dragenter', 'dragover'].forEach(function(ev) {
                    drop.addEventListener(ev, function(e) {
                        e.preventDefault();
                        drop.classList.add('is-over');
                    });
                });
                ['dragleave', 'drop'].forEach(function(ev) {
                    drop.addEventListener(ev, function(e) {
                        e.preventDefault();
                        drop.classList.remove('is-over');
                    });
                });
                drop.addEventListener('drop', function(e) {
                    var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                    if (!f) return;
                    if (f.type.indexOf('image/') !== 0 && f.type !== 'application/pdf') return;
                    var dt = new DataTransfer();
                    dt.items.add(f);
                    input.files = dt.files;
                    show(f);
                });
            });

            // Success banners (order placed, balance proof uploaded) fade away on their own
            ['success-message', 'balance-message'].forEach(function(id) {
                var banner = document.getElementById(id);
                if (banner) {
                    setTimeout(function() {
                        banner.classList.add('is-leaving');
                        setTimeout(function() {
                            banner.remove();
                        }, 600);
                    }, 5000);
                }
            });
            // Drop the one-time flags from the address bar so a refresh doesn't bring the banner back
            if (window.history && history.replaceState) {
                var url = new URL(window.location.href);
                if (url.searchParams.has('order_success') || url.searchParams.has('balance_uploaded')) {
                    url.searchParams.delete('order_success');
                    url.searchParams.delete('balance_uploaded');
                    history.replaceState(null, '', url.pathname + url.search + url.hash);
                }
            }
        })();
    </script>
    <script>
        // Chat functionality
        let currentConversationId = null;
        let chatRefreshInterval = null;

        // Initialize chat when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Setup event listeners
            const chatButton = document.getElementById('chatButton');
            const chatCloseBtn = document.getElementById('chatCloseBtn');
            const chatBackBtn = document.getElementById('chatBackBtn');
            const newChatBtn = document.getElementById('newChatBtn');
            const chatSendBtn = document.getElementById('chatSendBtn');
            const chatInput = document.getElementById('chatInput');

            if (chatButton) {
                chatButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleChat();
                });
            }

            if (chatCloseBtn) {
                chatCloseBtn.addEventListener('click', toggleChat);
            }

            if (chatBackBtn) {
                chatBackBtn.addEventListener('click', goBackToConversations);
            }

            if (newChatBtn) {
                newChatBtn.addEventListener('click', startNewConversation);
            }

            if (chatSendBtn) {
                chatSendBtn.addEventListener('click', sendMessage);
            }

            if (chatInput) {
                chatInput.addEventListener('input', function() {
                    autoResize(this);
                });

                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }

            // Load initial unread count
            updateUnreadCount();

            // Check for unread messages every minute
            setInterval(updateUnreadCount, 60000);
        });

        // Toggle chat widget
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            if (widget) {
                widget.classList.toggle('open');

                if (widget.classList.contains('open')) {
                    loadConversations();
                    startChatRefresh();
                } else {
                    stopChatRefresh();
                }
            }
        }

        // Load conversations
        async function loadConversations() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversations');
                const data = await response.json();

                if (data.success) {
                    renderConversations(data.data);
                    updateUnreadCount();

                    // Show conversation count in the UI
                    updateConversationCount(data.data.length);
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
                showChatError('Failed to load conversations. Please try again.');
            }
        }

        // Add this function to check conversation limit
        async function checkConversationLimit() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversation_limit');
                const data = await response.json();

                if (data.success) {
                    return {
                        reached: data.reached || false,
                        count: data.count || 0,
                        limit: data.limit || 3
                    };
                }
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            } catch (error) {
                console.error('Error checking conversation limit:', error);
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            }
        }

        // Render conversations list with delete buttons
        function renderConversations(conversations) {
            const container = document.getElementById('conversationsList');
            if (!container) return;

            if (conversations.length === 0) {
                container.innerHTML = `
                <div class="chat-empty">
                    <i class="fas fa-comments"></i>
                    <p>No conversations yet</p>
                </div>
            `;
                return;
            }

            container.innerHTML = conversations.map(conv => `
            <div class="chat-conversation-item ${currentConversationId === conv.id ? 'active' : ''}" 
                 onclick="openConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation')}')">
                <div class="conversation-header">
                    <div class="conversation-name">${escapeHtml(conv.title || 'Conversation #' + conv.id)}</div>
                    <button class="delete-conversation-btn" onclick="event.stopPropagation(); deleteConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation #' + conv.id)}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="conversation-last-message">${escapeHtml(conv.last_message || 'No messages yet')}</div>
                <div class="conversation-footer">
                    <div class="conversation-time">${formatTime(conv.last_message_time)}</div>
                    ${conv.unread_count > 0 ? `<div class="conversation-unread">${conv.unread_count} new</div>` : ''}
                </div>
            </div>
        `).join('');
        }

        // Delete conversation
        async function deleteConversation(conversationId, conversationTitle) {
            if (!confirm(`Are you sure you want to delete "${conversationTitle}"? This action cannot be undone.`)) {
                return;
            }

            try {
                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_conversation',
                        conversation_id: conversationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // If we're currently viewing this conversation, go back to list
                    if (currentConversationId === conversationId) {
                        goBackToConversations();
                    }

                    // Remove the conversation item from UI
                    const conversationItem = document.querySelector(`.chat-conversation-item[onclick*="${conversationId}"]`);
                    if (conversationItem) {
                        conversationItem.remove();
                    }

                    // Reload conversations list
                    await loadConversations();

                    showChatSuccess('Conversation deleted successfully.');
                } else {
                    showChatError(data.message || 'Failed to delete conversation.');
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
                showChatError('Failed to delete conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Open conversation
        function openConversation(conversationId, title) {
            currentConversationId = conversationId;

            // Update UI
            document.getElementById('chatConversations').style.display = 'none';
            document.getElementById('chatMessages').classList.add('active');
            document.getElementById('chatInputArea').classList.add('active');
            document.getElementById('chatBackBtn').classList.add('visible');
            document.getElementById('chatTitle').textContent = title;

            // Load messages
            loadMessages(conversationId);

            // Mark as read
            markAsRead(conversationId);
        }

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;

            document.getElementById('chatConversations').style.display = 'block';
            document.getElementById('chatMessages').classList.remove('active');
            document.getElementById('chatInputArea').classList.remove('active');
            document.getElementById('chatBackBtn').classList.remove('visible');
            document.getElementById('chatTitle').textContent = 'Messages';

            loadConversations();
        }

        // Load messages
        async function loadMessages(conversationId) {
            try {
                const response = await fetch(`../../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
                const data = await response.json();

                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showChatError('Failed to load messages. Please try again.');
            }
        }

        // Render messages
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!container) return;

            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == userId;
                const isSystem = msg.message_type === 'system';
                const isAdmin = msg.sender_role === 'admin';

                return `
                <div class="message-item ${isSent ? 'sent' : 'received'} ${isSystem ? 'system' : ''}">
                    ${!isSent && !isSystem ? `
                        <div class="message-sender">
                            ${escapeHtml(msg.sender_display_name || msg.sender_username)}
                        </div>
                    ` : ''}
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(msg.message)}</div>
                        <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                    </div>
                </div>
            `;
            }).join('');
        }

        // Send message
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();

            if (!message || !currentConversationId) return;

            // Disable send button
            const sendBtn = document.getElementById('chatSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            try {
                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'send_message',
                        conversation_id: currentConversationId,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    input.value = '';
                    autoResize(input);
                    loadMessages(currentConversationId);
                    updateUnreadCount();
                } else {
                    showChatError(data.message || 'Failed to send message.');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showChatError('Failed to send message. Please try again.');
            } finally {
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        // Start new conversation with online admin
        async function startNewConversation() {
            try {
                // First check if user has reached conversation limit
                const limitCheck = await checkConversationLimit();
                if (limitCheck.reached) {
                    showChatError(`You have reached the maximum limit of 3 active conversations. You currently have ${limitCheck.count} active conversations. Please complete or close existing conversations before starting a new one.`);
                    return;
                }

                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start_conversation',
                        title: 'Support Request',
                        request_online_admin: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const adminInfo = data.admin_name ? ` (Connected with: ${data.admin_name})` : '';
                    openConversation(data.conversation_id, 'Support Request');

                    if (data.admin_name) {
                        showSystemMessage(`You've been connected with administrator ${data.admin_name}. How can we help you?`);
                    }
                } else {
                    // Handle "no admin available" gracefully
                    if (data.message && data.message.includes('No administrators')) {
                        showChatError('No administrators are currently available. Please try again later or contact support via email.');
                    } else if (data.message && data.message.includes('maximum limit')) {
                        showChatError(data.message);
                        // Refresh conversations list to show current count
                        loadConversations();
                    } else {
                        showChatError(data.message || 'Failed to start conversation.');
                    }
                }
            } catch (error) {
                console.error('Error starting conversation:', error);
                showChatError('Failed to start conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Add this function to update conversation count display
        function updateConversationCount(count) {
            // Update the "New Conversation" button text
            const newChatBtn = document.getElementById('newChatBtn');
            if (newChatBtn) {
                const limitReached = count >= 3;
                newChatBtn.innerHTML = `<i class="fas fa-plus"></i> New Conversation (${count}/3)`;
                newChatBtn.disabled = limitReached;
                newChatBtn.title = limitReached ? 'Maximum 3 conversations reached' : 'Start a new conversation';

                if (limitReached) {
                    newChatBtn.classList.add('limit-reached');
                } else {
                    newChatBtn.classList.remove('limit-reached');
                }
            }

            // Also update conversation limit warning in conversations list
            const conversationsList = document.getElementById('conversationsList');
            if (conversationsList && count >= 3) {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (!warningElement) {
                    const warningDiv = document.createElement('div');
                    warningDiv.id = 'conversationLimitWarning';
                    warningDiv.className = 'conversation-limit-warning';
                    warningDiv.innerHTML = `
                    <div class="limit-warning-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Maximum conversations reached</strong>
                            <small>You have ${count} active conversations (maximum: 3). Please close or complete existing conversations to start new ones.</small>
                        </div>
                    </div>
                `;
                    conversationsList.parentNode.insertBefore(warningDiv, conversationsList);
                }
            } else {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (warningElement) {
                    warningElement.remove();
                }
            }
        }

        // Helper function to show system message
        function showSystemMessage(message) {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            const systemMessage = document.createElement('div');
            systemMessage.className = 'message-item system';
            systemMessage.innerHTML = `
            <div class="message-bubble">
                <div class="message-text">${escapeHtml(message)}</div>
                <div class="message-time">${formatMessageTime(new Date().toISOString())}</div>
            </div>
        `;
            messagesList.appendChild(systemMessage);
            messagesList.scrollTop = messagesList.scrollHeight;
        }

        // Add loading indicator
        function showChatLoading(show) {
            let loader = document.getElementById('chatLoader');
            if (!loader && show) {
                loader = document.createElement('div');
                loader.id = 'chatLoader';
                loader.className = 'chat-loader';
                loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                document.getElementById('chatMessages').prepend(loader);
            } else if (loader && !show) {
                loader.remove();
            }
        }

        // Show success message
        function showChatSuccess(message) {
            // Create success notification
            const successDiv = document.createElement('div');
            successDiv.className = 'chat-success-notification';
            successDiv.innerHTML = `
            <div class="success-content">
                <i class="fas fa-check-circle"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;

            // Add to chat widget
            const chatBody = document.querySelector('.chat-body');
            if (chatBody) {
                chatBody.prepend(successDiv);

                // Auto-remove after 3 seconds
                setTimeout(() => {
                    successDiv.remove();
                }, 3000);
            } else {
                alert(message); // Fallback
            }
        }

        // Mark messages as read
        async function markAsRead(conversationId) {
            // This happens automatically when loading messages via the API
            updateUnreadCount();
        }

        // Update unread count
        async function updateUnreadCount() {
            try {
                const response = await fetch('../../api/chat_api.php?action=unread_count');
                const data = await response.json();

                if (data.success) {
                    const count = data.count || 0;
                    const chatCount = document.getElementById('chatCount');
                    if (chatCount) {
                        chatCount.textContent = count;
                        chatCount.style.display = count > 0 ? 'flex' : 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating unread count:', error);
            }
        }

        // Start auto-refresh
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    loadMessages(currentConversationId);
                }
                updateUnreadCount();
            }, 5000); // Refresh every 5 seconds
        }

        // Stop auto-refresh
        function stopChatRefresh() {
            if (chatRefreshInterval) {
                clearInterval(chatRefreshInterval);
                chatRefreshInterval = null;
            }
        }

        // Helper functions
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 86400000) { // Less than 1 day
                return date.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } else if (diff < 604800000) { // Less than 1 week
                return date.toLocaleDateString([], {
                    weekday: 'short'
                });
            } else {
                return date.toLocaleDateString([], {
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function formatMessageTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-resize textarea
        function autoResize(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        // Show chat error
        function showChatError(message) {
            // You can implement a notification system here
            console.error('Chat Error:', message);
            alert(message); // Simple alert for now
        }

        // Make functions available globally
        window.toggleChat = toggleChat;
        window.openConversation = openConversation;
        window.goBackToConversations = goBackToConversations;
        window.sendMessage = sendMessage;
        window.startNewConversation = startNewConversation;
        window.deleteConversation = deleteConversation;
    </script>
</body>

</html>

<?php
// Close connection
$inventory->close();
?>