<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

/* ------------------------------
   1. Get USER info (personal or company)
--------------------------------*/
$userQuery = "SELECT 
                u.id,
                pc.first_name, pc.last_name,
                cc.company_name
              FROM users u
              LEFT JOIN personal_customers pc ON u.id = pc.user_id
              LEFT JOIN company_customers cc ON u.id = cc.user_id
              WHERE u.id = ?
              LIMIT 1";

$userStmt = $inventory->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user_data = $userResult->fetch_assoc();

/* ------------------------------
   2. Get CART items with individual pricing data
--------------------------------*/
// Modified query to get pricing data per cart item
$query = "SELECT p.id, p.product_name, p.price AS unit_price, p.category AS product_group, 
                 ci.quantity, ci.item_id, ci.design_image, ci.added_at,
                 ci.quoted_price, ci.price_updated_by_admin,
                 ci.layout_option, ci.layout_details, ci.gsm_option, ci.user_layout_files,
                 ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                 ci.finish_option, ci.paper_option, ci.binding_option,
                 po.option_name AS paper_option_name,
                 fo.option_name AS finish_option_name,
                 bo.option_name AS binding_option_name,
                 lo.option_name AS layout_option_name,
                 c.cart_id,
                pri.admin_notes, pri.status AS pricing_status,
                pr2.request_date AS quote_request_date
          FROM cart_items ci
          JOIN products_offered p ON ci.product_id = p.id
          JOIN carts c ON ci.cart_id = c.cart_id
          LEFT JOIN paper_options po ON ci.paper_option = po.id
          LEFT JOIN finish_options fo ON ci.finish_option = fo.id
          LEFT JOIN binding_options bo ON ci.binding_option = bo.id
          LEFT JOIN layout_options lo ON ci.layout_option = lo.id
          LEFT JOIN pricing_requests_items pri ON ci.item_id = pri.cart_item_id
          LEFT JOIN pricing_requests pr2 ON pr2.cart_id = c.cart_id AND pr2.status = 'pending'
          WHERE c.user_id = ?
          ORDER BY ci.added_at DESC";

$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}

/* ------------------------------
   3. Handle selected items
--------------------------------*/
// Initialize selected items from session or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_items'])) {
    // Update selected items from POST
    $_SESSION['selected_cart_items'] = $_POST['selected_items'];
    $selected_items = $_SESSION['selected_cart_items'];
} else {
    // Get selected items from session
    $selected_items = isset($_SESSION['selected_cart_items']) ? $_SESSION['selected_cart_items'] : [];
}

$selected_total = 0;
$selected_confirmed_count = 0;
$unconfirmed_selected_items = [];
$unconfirmed_selected_total = 0;
foreach ($cart_items as $item) {
    if (in_array($item['item_id'], $selected_items)) {
        // Use admin price if available, otherwise use unit price
        $item_is_confirmed = $item['price_updated_by_admin'] && $item['quoted_price'] > 0;
        $actual_price = $item_is_confirmed
            ? $item['quoted_price']
            : $item['unit_price'];
        $selected_total += $actual_price * $item['quantity'];
        if ($item_is_confirmed) {
            $selected_confirmed_count++;
        } else {
            // Only items still awaiting a store-confirmed price should go into a new request
            $unconfirmed_selected_items[] = $item['item_id'];
            $unconfirmed_selected_total += $item['unit_price'] * $item['quantity'];
        }
    }
}
// True when every currently-selected item already has a store-confirmed
// price, i.e. there's nothing left to request pricing for.
$selected_all_confirmed = (count($selected_items) > 0 && $selected_confirmed_count === count($selected_items));

/* ------------------------------
   4. Handle price request submission
--------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_pricing'])) {
    if (empty($selected_items)) {
        echo "<script>alert('Please select at least one item to request pricing.');</script>";
    } elseif ($selected_all_confirmed) {
        echo "<script>alert('All of your selected items already have a confirmed price. There\'s nothing left to request.');</script>";
    } else {
        error_log("=== PRICING REQUEST SUBMISSION START ===");
        error_log("User ID: $user_id");
        error_log("Selected Items Count: " . count($selected_items));
        error_log("Selected Total: $selected_total");

        // Store the selected items in session for the pricing request
        $_SESSION['pricing_request_items'] = $unconfirmed_selected_items;

        // Send notification to admin and save to database
        // Only items still awaiting a confirmed price go into the request —
        // already-quoted items in the same selection are excluded so they
        // don't get re-sent to the admin.
        $request_id = sendPricingRequestNotification($user_id, $unconfirmed_selected_items, $unconfirmed_selected_total);

        if ($request_id) {
            error_log("SUCCESS: Pricing request created with ID: $request_id");
            unset($_SESSION['selected_cart_items']);
            $quote_deadline = cart_quote_deadline_text();
            echo "<script>
                alert('Your pricing request #$request_id has been sent to our team.');
                window.location.href = 'view_cart.php';
            </script>";
        } else {
            error_log("FAILED: Pricing request creation failed");
            // Get the last database error for more details
            global $inventory;
            $db_error = $inventory ? $inventory->error : "No database connection";
            error_log("Database Error: $db_error");

            echo "<script>
                alert('There was an error submitting your pricing request. Please try again. If the problem persists, contact support.');
                console.error('Pricing request error: $db_error');
            </script>";
        }
        error_log("=== PRICING REQUEST SUBMISSION END ===");
        exit;
    }
}

/* ------------------------------
   5. Function to send pricing request notification
--------------------------------*/
function sendPricingRequestNotification($user_id, $selected_items, $total_estimate)
{
    global $inventory;

    error_log("=== PRICING REQUEST DEBUG ===");
    error_log("User ID: $user_id");
    error_log("Selected Items: " . print_r($selected_items, true));
    error_log("Total Estimate: $total_estimate");

    // Check database connection
    if (!$inventory) {
        error_log("ERROR: No database connection object");
        return false;
    }

    if ($inventory->connect_error) {
        error_log("ERROR: Database connection error: " . $inventory->connect_error);
        return false;
    }

    error_log("Database connection OK");

    try {
        // Convert selected items array to JSON
        $items_json = json_encode($selected_items);
        if ($items_json === false) {
            error_log("ERROR: JSON encoding failed");
            return false;
        }
        error_log("Items JSON: $items_json");

        // Get the user's cart_id
        $cart_query = "SELECT cart_id FROM carts WHERE user_id = ?";
        $cart_stmt = $inventory->prepare($cart_query);
        $cart_stmt->bind_param("i", $user_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        $cart_data = $cart_result->fetch_assoc();
        $cart_id = $cart_data['cart_id'];

        // Check if a pricing request already exists for this cart
        $check_query = "SELECT id FROM pricing_requests WHERE cart_id = ? AND status = 'pending'";
        $check_stmt = $inventory->prepare($check_query);
        $check_stmt->bind_param("i", $cart_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // Update existing pricing request instead of creating new one
            $update_query = "UPDATE pricing_requests SET selected_items = ?, estimated_total = ?, request_date = NOW() WHERE cart_id = ? AND status = 'pending'";
            $update_stmt = $inventory->prepare($update_query);
            $update_stmt->bind_param("sdi", $items_json, $total_estimate, $cart_id);
            $execute_result = $update_stmt->execute();

            if (!$execute_result) {
                error_log("ERROR: Update failed: " . $update_stmt->error);
                return false;
            }

            $request_id = $check_result->fetch_assoc()['id'];
            error_log("SUCCESS: Existing pricing request updated with ID: $request_id");
        } else {
            // Create new pricing request
            $query = "INSERT INTO pricing_requests (user_id, cart_id, selected_items, estimated_total) VALUES (?, ?, ?, ?)";
            error_log("Query: $query");

            $stmt = $inventory->prepare($query);
            if (!$stmt) {
                error_log("ERROR: Prepare failed: " . $inventory->error);
                return false;
            }
            error_log("Statement prepared successfully");

            // Bind parameters
            $bind_result = $stmt->bind_param("iisd", $user_id, $cart_id, $items_json, $total_estimate);
            if (!$bind_result) {
                error_log("ERROR: Bind failed: " . $stmt->error);
                return false;
            }
            error_log("Parameters bound successfully");

            // Execute
            $execute_result = $stmt->execute();
            if (!$execute_result) {
                error_log("ERROR: Execute failed: " . $stmt->error);
                return false;
            }
            error_log("Execute successful");

            $request_id = $inventory->insert_id;
            error_log("SUCCESS: New pricing request created with ID: $request_id");
        }

        // Make sure every selected item has a pricing_requests_items row as
        // soon as the request is submitted, rather than only getting one the
        // first time an admin opens the pricing screen for this request.
        foreach ($selected_items as $item_id) {
            $item_id = (int) $item_id;
            if ($item_id < 1) {
                continue;
            }

            $check_item_query = "SELECT id FROM pricing_requests_items WHERE pricing_request_id = ? AND cart_item_id = ?";
            $check_item_stmt = $inventory->prepare($check_item_query);
            $check_item_stmt->bind_param("ii", $request_id, $item_id);
            $check_item_stmt->execute();
            $check_item_result = $check_item_stmt->get_result();

            if ($check_item_result->num_rows === 0) {
                $insert_item_query = "INSERT INTO pricing_requests_items (pricing_request_id, cart_item_id, admin_notes, quoted_price, status) VALUES (?, ?, NULL, NULL, 'pending')";
                $insert_item_stmt = $inventory->prepare($insert_item_query);
                $insert_item_stmt->bind_param("ii", $request_id, $item_id);
                if (!$insert_item_stmt->execute()) {
                    error_log("ERROR: Failed to create pending pricing_requests_items row for item $item_id: " . $insert_item_stmt->error);
                }
            }
            // If a row already exists (e.g. the admin already started pricing
            // this item, or the cart was re-submitted), leave it as-is -
            // don't reset an in-progress quote back to 'pending'.
        }

        return $request_id;
    } catch (Exception $e) {
        error_log("EXCEPTION: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_checkout'])) {
    if (empty($selected_items)) {
        echo "<script>alert('There was an error checking out.');</script>";
    } else {
        header("Location: checkout.php?selected_items=" . urlencode(implode(',', $selected_items)));
        exit;
    }
}

/* ------------------------------
   6. Presentation helpers (layout only — no data changes)
--------------------------------*/
$navOpen  = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';
$tax_rate = 0.03;

function cart_h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Office hours the shop actually staffs quotes during: Mon-Sat, 8 AM-6 PM
// (closed Sunday). Given any moment, returns the next time the shop is
// open - itself, if it's already within business hours; otherwise the
// next opening (later today if it's simply before 8 AM, or the next
// business day's 8 AM if it's past close / a Sunday, skipping Sunday).
function cart_next_business_open(DateTime $from)
{
    $open = clone $from;
    $open->setTime(8, 0, 0);

    $day  = (int) $from->format('N'); // 1 = Monday ... 7 = Sunday
    $hour = (int) $from->format('G');

    if ($day === 7) {
        // Sunday - closed all day.
        $open->modify('next monday');
    } elseif ($hour >= 18) {
        // Already past today's close - roll to the next business day.
        $open->modify('+1 day');
        if ((int) $open->format('N') === 7) {
            $open->modify('+1 day'); // skip Sunday
        }
    }
    // Otherwise (before 8 AM, or already within hours, on a business day):
    // today's 8 AM stays as the reference open time.

    return $open;
}

// Per-item ETA once a quote request has been sent: 3 hours of business
// time from the request, honoring Mon-Sat 8 AM-6 PM office hours. A
// request made outside those hours (evenings, before opening doesn't
// count here - see below, or Sundays) starts its 3-hour clock at the
// next time the shop opens, so an after-hours or Sunday requote reads
// as tomorrow (or Monday) instead of a literal same-day clock time. If
// the 3 hours would run past closing, the remainder carries over to the
// next business day's opening rather than landing after-hours.
function cart_item_quote_eta_text($request_date)
{
    if (empty($request_date)) {
        return null;
    }
    try {
        $requestedAt = new DateTime($request_date);
    } catch (Exception $e) {
        return null;
    }

    $day  = (int) $requestedAt->format('N');
    $hour = (int) $requestedAt->format('G');
    $withinOfficeHours = $day !== 7 && $hour >= 8 && $hour < 18;

    $startAt = $withinOfficeHours ? clone $requestedAt : cart_next_business_open($requestedAt);

    $expectBy = (clone $startAt)->modify('+3 hours');

    // 3 hours from a late-afternoon start can spill past 6 PM close -
    // carry the leftover time into the next business day's opening.
    $closeSameDay = (clone $startAt)->setTime(18, 0, 0);
    if ($expectBy > $closeSameDay) {
        $overflowSeconds = $expectBy->getTimestamp() - $closeSameDay->getTimestamp();
        $expectBy = cart_next_business_open((clone $closeSameDay)->modify('+1 minute'));
        $expectBy->modify("+{$overflowSeconds} seconds");
    }

    $now = new DateTime('now');

    if ($expectBy->format('Y-m-d') === $now->format('Y-m-d')) {
        return $expectBy->format('g:i A') . ' today';
    }
    if ($expectBy->format('Y-m-d') === (clone $now)->modify('+1 day')->format('Y-m-d')) {
        return $expectBy->format('g:i A') . ' tomorrow';
    }
    return $expectBy->format('l, M j \a\t g:i A');
}
// is the goal whenever there's still time left in today's business hours.
function cart_quote_deadline_text()
{
    $now = new DateTime('now');
    $dayOfWeek = (int) $now->format('N'); // 1 = Monday ... 7 = Sunday
    $hour = (int) $now->format('G');

    $isBusinessDay = $dayOfWeek >= 1 && $dayOfWeek <= 6; // Mon-Sat
    $stillOpenToday = $isBusinessDay && $hour < 18;      // before 6 PM

    if ($stillOpenToday) {
        // There's still time today — quote today, no need to wait for tomorrow.
        return 'today by 6 PM';
    }

    // Shop is closed for the day (or it's Sunday) — push to the next business day.
    $deadline = clone $now;
    if ($dayOfWeek === 7) {
        $deadline->modify('next monday');
    } elseif ($dayOfWeek === 6) {
        // After hours Saturday -> shop closed Sunday -> next open day is Monday
        $deadline->modify('next monday');
    } else {
        $deadline->modify('+1 day');
        if ((int) $deadline->format('N') === 7) {
            $deadline->modify('+1 day'); // skip Sunday
        }
    }

    // "tomorrow" reads better than a weekday name when it genuinely is tomorrow
    $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
    if ($deadline->format('Y-m-d') === $tomorrow) {
        return 'tomorrow by 6 PM';
    }
    return $deadline->format('l, M j') . ' by 6 PM';
}

function cart_str($v)
{
    return is_scalar($v) ? (string)$v : '';
}

// Maps a product group to one of the four brand inks (same mapping the catalog tabs use)
function cart_ink_for_group($group)
{
    $g = strtolower((string)$group);
    if (strpos($g, 'offset') !== false)  return 'black';
    if (strpos($g, 'digital') !== false) return 'cyan';
    if (strpos($g, 'riso') !== false)    return 'magenta';
    return 'yellow';
}

// Same parsing rules as before: JSON, then "repaired" JSON, then a legacy single filename
function cart_parse_design($raw)
{
    $out = [
        'upload_type'         => 'single',
        'front_mockup'        => '',
        'back_mockup'         => '',
        'uploaded_file'       => '',
        'front_uploaded_file' => '',
        'back_uploaded_file'  => '',
    ];

    $arr = json_decode($raw, true);
    if (!(json_last_error() === JSON_ERROR_NONE && is_array($arr))) {
        if (preg_match('/\{.*\}/', $raw)) {
            $fixed = stripslashes(str_replace('\"', '"', $raw));
            $arr = json_decode($fixed, true);
            if (!(json_last_error() === JSON_ERROR_NONE && is_array($arr))) {
                return $out;
            }
        } else {
            $out['uploaded_file'] = $raw; // legacy: a bare filename
            return $out;
        }
    }

    $out['upload_type'] = cart_str($arr['upload_type'] ?? 'single') ?: 'single';
    foreach (['front_mockup', 'back_mockup', 'uploaded_file', 'front_uploaded_file', 'back_uploaded_file'] as $k) {
        $out[$k] = cart_str($arr[$k] ?? '');
    }
    return $out;
}

function cart_design_tile($label, $file, $icon = 'fa-file-image')
{
    $rel = "../assets/uploads/" . $file;
    echo '<figure class="design-tile">';
    if (file_exists($rel)) {
        echo '<a href="' . cart_h($rel) . '" target="_blank" rel="noopener"><img src="' . cart_h($rel) . '" alt="' . cart_h($label) . '" loading="lazy"></a>';
    } else {
        echo '<div class="design-missing" title="Preview not available"><i class="fas ' . cart_h($icon) . '"></i></div>';
    }
    echo '<figcaption>' . cart_h($label) . '</figcaption></figure>';
}

// Layout files are saved by service_detail.php with its own relative prefix baked in
// (e.g. "../../assets/uploads/user_layouts/5/layout_xyz.jpg"). Normalize to a path
// that's correct relative to *this* page regardless of how many "../" segments were stored.
function cart_layout_file_url($stored_path)
{
    $pos = strpos($stored_path, 'assets/uploads/');
    if ($pos === false) {
        return null;
    }
    return '../' . substr($stored_path, $pos);
}

function cart_layout_files_list($raw)
{
    $files = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($files)) {
        return [];
    }
    return $files;
}

function cart_step_class($n, $current)
{
    if ($n < $current) return 'is-done';
    if ($n === $current) return 'is-current';
    return '';
}

/* Selection / pricing state — computed once, used by the markup and the JS */
$total_units             = 0;
$confirmed_in_cart       = 0;
$total_selected_items    = 0;
$items_with_admin_prices = 0;
$product_data            = [];

foreach ($cart_items as $ci) {
    $is_confirmed = !empty($ci['price_updated_by_admin']) && $ci['quoted_price'] > 0;
    $price        = $is_confirmed ? $ci['quoted_price'] : $ci['unit_price'];

    $total_units += (int)$ci['quantity'];
    if ($is_confirmed) $confirmed_in_cart++;

    if (in_array($ci['item_id'], $selected_items)) {
        $total_selected_items++;
        if ($is_confirmed) $items_with_admin_prices++;
    }

    $product_data[$ci['item_id']] = [
        'price'         => (float)$price,
        'quantity'      => (int)$ci['quantity'],
        'subtotal'      => (float)$price * (int)$ci['quantity'],
        'hasAdminPrice' => $is_confirmed,
        'originalPrice' => (float)$ci['unit_price'],
    ];
}

$all_prices_updated = ($total_selected_items > 0 && $items_with_admin_prices === $total_selected_items);
$can_checkout       = $all_prices_updated;

if ($total_selected_items === 0) {
    $checkout_message = "Select the items you want to check out";
} elseif ($can_checkout) {
    $checkout_message = "All selected items have confirmed pricing";
} elseif ($items_with_admin_prices > 0) {
    $checkout_message = "$items_with_admin_prices of $total_selected_items selected items have confirmed pricing";
} else {
    $checkout_message = "No selected items have confirmed pricing yet";
}

$selected_tax   = $selected_total * $tax_rate;
$selected_grand = $selected_total + $selected_tax;

// Step tracker: 1 = choosing, 2 = waiting on the store, 3 = ready to check out
$current_step = 1;
if ($total_selected_items > 0) $current_step = $can_checkout ? 3 : 2;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Active Media Designs &amp; Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* =========================================================
           Cart page — layout only.
           Built on the shared design tokens in main.css (paper/ink
           palette, CMYK inks, registration marks). No tokens are
           redefined here.
        ========================================================= */

        /* Scroll restore after a checkbox re-submit must be instant */
        html {
            scroll-behavior: auto !important;
        }

        [hidden] {
            display: none !important;
        }

        /* ---------- Hero ---------- */
        .cart-hero {
            position: relative;
            padding: 156px 0 32px;
            overflow: hidden;
        }

        .cart-hero__texture {
            position: absolute;
            top: 0;
            right: 0;
            width: 320px;
            height: 320px;
            color: var(--line);
            opacity: 0.7;
            pointer-events: none;
            background-image: radial-gradient(currentColor 1px, transparent 1.6px);
            background-size: 14px 14px;
            -webkit-mask-image: radial-gradient(circle at 100% 0, #000, transparent 70%);
            mask-image: radial-gradient(circle at 100% 0, #000, transparent 70%);
        }

        .cart-hero-title {
            font-size: clamp(2.2rem, 4.6vw, 3.3rem);
            margin-bottom: 14px;
        }

        .cart-hero-title .registered {
            position: relative;
            display: inline-block;
            color: var(--ink);
        }

        .cart-hero-title .registered::before,
        .cart-hero-title .registered::after {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            z-index: -1;
        }

        .cart-hero-title .registered::before {
            color: var(--cmyk-magenta);
            transform: translate(3px, 2px);
            opacity: 0.55;
        }

        .cart-hero-title .registered::after {
            color: var(--cmyk-cyan);
            transform: translate(-3px, -2px);
            opacity: 0.45;
        }

        .cart-hero-sub {
            font-size: 1.08rem;
            max-width: 58ch;
            margin-bottom: 22px;
        }

        .cart-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .cart-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: var(--paper-white);
            border: 1px solid var(--line);
            border-radius: var(--r-pill);
            font-size: 13px;
            font-weight: 600;
            color: var(--ink-soft);
        }

        .cart-pill i {
            color: var(--riso-blue);
            font-size: 12px;
        }

        /* ---------- Where you are in the order ---------- */
        .cart-steps {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 0 0 32px;
            padding: 0;
        }

        .cart-step {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px 10px 10px;
            background: var(--paper-white);
            border: 1px solid var(--line);
            border-radius: var(--r-pill);
            transition: var(--transition);
        }

        .cart-step__num {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 50%;
            border: 1.5px solid var(--ink-faint);
            color: var(--ink-faint);
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
        }

        .cart-step strong {
            display: block;
            font-size: 14px;
            line-height: 1.25;
            color: var(--ink);
        }

        .cart-step small {
            display: block;
            font-size: 12px;
            color: var(--ink-faint);
        }

        .cart-step.is-current {
            border-color: var(--ink);
            box-shadow: var(--shadow);
        }

        .cart-step.is-current .cart-step__num {
            background: var(--ink);
            border-color: var(--ink);
            color: var(--paper-white);
        }

        .cart-step.is-done .cart-step__num {
            background: var(--riso-blue);
            border-color: var(--riso-blue);
            color: var(--paper-white);
            font-size: 12px;
        }

        /* ---------- Two-column layout ---------- */
        .cart-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 32px;
            align-items: start;
            padding-bottom: 88px;
        }

        .cart-side {
            align-self: stretch;
        }

        /* ---------- How pricing works (same pattern as the FAQ) ---------- */
        .pricing-info {
            margin-bottom: 16px;
            background: var(--paper-white);
            border: 1px solid var(--line);
            border-radius: var(--r-md);
            overflow: hidden;
            transition: var(--transition);
        }

        .pricing-info[open] {
            border-color: var(--riso-red);
        }

        .pricing-info summary {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            cursor: pointer;
            list-style: none;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 15px;
        }

        .pricing-info summary::-webkit-details-marker {
            display: none;
        }

        .pricing-info summary .chev {
            margin-left: auto;
            color: var(--riso-red);
            transition: transform 0.3s var(--ease);
        }

        .pricing-info[open] summary .chev {
            transform: rotate(180deg);
        }

        .pricing-info__body {
            padding: 0 20px 18px;
            font-size: 14.5px;
        }

        .pricing-info__body p {
            font-size: 14.5px;
            margin-bottom: 8px;
        }

        .pricing-list {
            margin: 0 0 12px 18px;
            color: var(--ink-soft);
            font-size: 14.5px;
        }

        .pricing-list li {
            margin-bottom: 2px;
        }

        /* ---------- Toolbar ---------- */
        .cart-toolbar {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
            padding: 12px 18px;
            background: var(--paper-white);
            border: 1px solid var(--line);
            border-radius: var(--r-md);
        }

        .select-all {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 14.5px;
            cursor: pointer;
        }

        .toolbar-count {
            margin-left: auto;
            font-size: 13.5px;
            color: var(--ink-faint);
        }

        .remove-selected-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            background: transparent;
            border: 1.5px solid var(--riso-red);
            border-radius: var(--r-sm);
            color: var(--riso-red);
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            transition: var(--transition);
        }

        .remove-selected-btn:hover {
            background: var(--riso-red);
            color: var(--paper-white);
        }

        /* Custom checkbox — ink square, matches the ink-filled active states elsewhere */
        .cart-check {
            -webkit-appearance: none;
            appearance: none;
            flex-shrink: 0;
            display: inline-grid;
            place-content: center;
            width: 22px;
            height: 22px;
            margin: 0;
            background: var(--paper-white);
            border: 1.5px solid var(--ink);
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }

        .cart-check:hover {
            border-color: var(--riso-red);
        }

        .cart-check::after {
            content: "";
            width: 10px;
            height: 6px;
            border-left: 2px solid var(--paper-white);
            border-bottom: 2px solid var(--paper-white);
            transform: rotate(-45deg) translate(1px, -1px);
            opacity: 0;
            transition: opacity 0.15s;
        }

        .cart-check:checked,
        .cart-check:indeterminate {
            background: var(--ink);
            border-color: var(--ink);
        }

        .cart-check:checked::after {
            opacity: 1;
        }

        .cart-check:indeterminate::after {
            opacity: 1;
            width: 10px;
            height: 0;
            border-left: 0;
            transform: none;
        }

        /* ---------- Cart items ---------- */
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .cart-item {
            --item-ink: var(--ink);
            position: relative;
            display: grid;
            grid-template-columns: auto 128px minmax(0, 1fr) auto;
            grid-template-areas: "check image info actions";
            gap: 20px;
            align-items: start;
            padding: 22px;
            background: var(--paper-white);
            border: 1px solid var(--line);
            border-left: 4px solid var(--item-ink);
            border-radius: var(--r-md);
            transition: var(--transition);
        }

        .cart-item[data-ink="black"] {
            --item-ink: var(--cmyk-black);
        }

        .cart-item[data-ink="cyan"] {
            --item-ink: var(--cmyk-cyan);
        }

        .cart-item[data-ink="magenta"] {
            --item-ink: var(--cmyk-magenta);
        }

        .cart-item[data-ink="yellow"] {
            --item-ink: var(--cmyk-yellow);
        }

        .cart-item:hover {
            box-shadow: var(--shadow);
        }

        .cart-item:has(.cart-check:checked) {
            border-color: var(--ink);
            border-left-color: var(--item-ink);
            box-shadow: var(--shadow);
        }

        .item-checkbox {
            grid-area: check;
            padding-top: 4px;
        }

        .cart-item-image {
            grid-area: image;
            width: 128px;
            aspect-ratio: 1;
            display: grid;
            place-items: center;
            overflow: hidden;
            background: var(--paper-dim);
            border-radius: var(--r-md);
            color: var(--ink-faint);
            font-size: 26px;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-info {
            grid-area: info;
            min-width: 0;
        }

        .item-group {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink-soft);
        }

        .item-group::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--item-ink);
            box-shadow: 0 0 0 1px rgba(23, 20, 15, 0.18);
        }

        .cart-item-info h3 {
            margin: 4px 0 10px;
            font-size: 1.15rem;
        }

        .cart-item-info h3 a:hover {
            color: var(--riso-red);
        }

        /* Price */
        .price-display {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 6px 12px;
        }

        .price {
            margin: 0;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1.3rem;
            color: var(--riso-blue);
        }

        .price .was {
            margin-right: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--ink-faint);
            text-decoration: line-through;
        }

        .price-unit {
            font-size: 12.5px;
            color: var(--ink-faint);
        }

        .admin-price-notice,
        .estimate-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 11px;
            border-radius: var(--r-pill);
            font-size: 11.5px;
            font-weight: 600;
        }

        .admin-price-notice {
            background: var(--riso-blue);
            color: var(--paper-white);
        }

        .estimate-tag {
            background: var(--paper-dim);
            color: var(--ink-soft);
        }

        /* Notes from the store */
        .admin-notes-section {
            margin-top: 14px;
            padding: 12px 14px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-left: 3px solid var(--riso-blue);
            border-radius: var(--r-sm);
        }

        .admin-notes-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .admin-notes-header i {
            color: var(--riso-blue);
        }

        .admin-notes-content {
            margin-top: 4px;
            font-size: 13.5px;
            color: var(--ink-soft);
        }

        .pricing-status.badge {
            margin-left: auto;
            padding: 2px 10px;
            border-radius: var(--r-pill);
            font-size: 11.5px;
            font-weight: 600;
            background: var(--paper-dim);
            color: var(--ink-soft);
        }

        .pricing-status.pending {
            background: var(--cmyk-yellow);
            color: var(--ink);
        }

        .pricing-status.approved {
            background: var(--riso-blue);
            color: var(--paper-white);
        }

        .pricing-status.completed {
            background: var(--ink);
            color: var(--paper-white);
        }

        .pricing-status.cancelled {
            background: var(--riso-red);
            color: var(--paper-white);
        }

        /* Status callouts (per item and in the summary) */
        .pricing-status-alert {
            display: grid;
            grid-template-columns: auto 1fr;
            column-gap: 10px;
            margin-top: 14px;
            padding: 12px 14px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: var(--r-sm);
        }

        .pricing-status-alert>i {
            grid-row: 1 / span 2;
            margin-top: 3px;
        }

        .pricing-status-alert strong {
            font-size: 13.5px;
            line-height: 1.35;
            color: var(--ink);
        }

        .pricing-status-alert p {
            margin: 2px 0 0;
            font-size: 11px;
            line-height: 1.5;
        }

        .pricing-status-alert.success {
            background: rgba(36, 71, 143, 0.06);
            border-color: rgba(36, 71, 143, 0.3);
        }

        .pricing-status-alert.success>i {
            color: var(--riso-blue);
        }

        .pricing-status-alert.warning {
            border-color: rgba(23, 20, 15, 0.16);
        }

        .pricing-status-alert.cancelled {
            background: rgba(232, 67, 43, 0.06);
            border-color: rgba(232, 67, 43, 0.35);
        }

        .pricing-status-alert.cancelled>i {
            color: var(--riso-red);
        }

        .pricing-status-alert.pending {
            background: rgba(182, 121, 10, 0.07);
            border-color: rgba(182, 121, 10, 0.3);
        }

        .pricing-status-alert.pending>i {
            color: #b6790a;
        }

        /* Job specs */
        .printing-details {
            margin-top: 16px;
        }

        .details-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(118px, 1fr));
            gap: 8px;
        }

        .detail-item {
            padding: 8px 12px;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: var(--r-sm);
        }

        .detail-label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--ink-faint);
        }

        .detail-value {
            display: block;
            margin-top: 1px;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--ink);
        }

        .detail-value small {
            display: block;
            font-weight: 400;
            color: var(--ink-faint);
        }

        /* Artwork proofs */
        .custom-design {
            margin-top: 16px;
            padding: 14px;
            background: var(--paper);
            border: 1.5px dashed var(--line);
            border-radius: var(--r-md);
        }

        .custom-design-title {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 4px 10px;
            margin-bottom: 12px;
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 14px;
        }

        .custom-design-title i {
            color: var(--riso-red);
            align-self: center;
        }

        .custom-design-title small {
            font-family: var(--font-body);
            font-weight: 400;
            font-size: 12.5px;
            color: var(--ink-faint);
        }

        .design-previews {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .design-tile {
            width: 92px;
            margin: 0;
        }

        .design-tile img,
        .design-missing {
            width: 92px;
            height: 92px;
            border-radius: var(--r-sm);
        }

        .design-tile img {
            object-fit: cover;
            background: var(--paper-white);
            border: 1px solid var(--line);
            transition: var(--transition);
        }

        .design-tile a:hover img {
            border-color: var(--ink);
            box-shadow: var(--shadow-sm);
        }

        .design-missing {
            display: grid;
            place-items: center;
            border: 1.5px dashed var(--ink-faint);
            color: var(--ink-faint);
            font-size: 20px;
        }

        .design-tile figcaption {
            margin-top: 6px;
            text-align: center;
            font-size: 11.5px;
            font-weight: 500;
            color: var(--ink-soft);
        }

        .design-files {
            margin-top: 12px;
            font-size: 12px;
            color: var(--ink-faint);
            overflow-wrap: anywhere;
        }

        .design-files strong {
            color: var(--ink-soft);
            font-weight: 600;
        }

        /* Quantity / subtotal / remove */
        .cart-item-actions {
            grid-area: actions;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 16px;
            min-width: 150px;
        }

        .quantity-controls {
            display: inline-flex;
            align-items: center;
            overflow: hidden;
            background: var(--paper-white);
            border: 1.5px solid var(--ink);
            border-radius: var(--r-pill);
        }

        .quantity-btn {
            width: 36px;
            height: 38px;
            display: grid;
            place-items: center;
            background: transparent;
            border: 0;
            color: var(--ink);
            font-size: 11px;
            cursor: pointer;
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background: var(--ink);
            color: var(--paper-white);
        }

        .quantity-input {
            width: 52px;
            height: 38px;
            padding: 0;
            background: transparent;
            border: 0;
            text-align: center;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 14.5px;
            color: var(--ink);
            -moz-appearance: textfield;
        }

        .quantity-input::-webkit-outer-spin-button,
        .quantity-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .item-subtotal {
            text-align: right;
        }

        .item-subtotal__label {
            display: block;
            font-size: 12px;
            color: var(--ink-faint);
        }

        .subtotal-amount {
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--ink);
        }

        .remove-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 0;
            background: none;
            border: 0;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 13px;
            color: var(--ink-faint);
            cursor: pointer;
            transition: var(--transition);
        }

        .remove-btn:hover {
            color: var(--riso-red);
        }

        .item-toast {
            position: absolute;
            top: 12px;
            right: 14px;
            z-index: 5;
            padding: 5px 12px;
            background: var(--ink);
            color: var(--paper-white);
            border-radius: var(--r-pill);
            font-size: 12px;
            font-weight: 600;
        }

        /* ---------- Order summary ---------- */
        .cart-summary {
            position: sticky;
            top: 32px;
            max-height: calc(100vh - 64px);
            overflow-y: auto;
            background: var(--paper-white);
            border: 1px solid var(--line);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow);
        }

        /* A press colour bar — the four inks from the logo */
        .summary-bar {
            display: flex;
            height: 6px;
        }

        .summary-bar i {
            flex: 1;
        }

        .summary-bar i:nth-child(1) {
            background: var(--cmyk-cyan);
        }

        .summary-bar i:nth-child(2) {
            background: var(--cmyk-magenta);
        }

        .summary-bar i:nth-child(3) {
            background: var(--cmyk-yellow);
        }

        .summary-bar i:nth-child(4) {
            background: var(--cmyk-black);
        }

        .summary-body {
            padding: 24px 26px 26px;
        }

        .summary-title {
            margin-bottom: 4px;
            font-size: 1.25rem;
        }

        .summary-empty {
            margin: 10px 0 0;
            font-size: 13.5px;
        }

        .summary-rows {
            margin-top: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 7px 0;
            font-size: 14.5px;
            color: var(--ink-soft);
        }

        .summary-row span:last-child {
            font-weight: 600;
            color: var(--ink);
        }

        .summary-row.total {
            align-items: baseline;
            margin-top: 8px;
            padding-top: 16px;
            border-top: 1px dashed var(--line);
            font-family: var(--font-display);
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--ink);
        }

        .summary-row.total span:last-child {
            font-size: 1.7rem;
            letter-spacing: -0.02em;
        }

        .cart-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 22px;
        }

        .cart-buttons .btn {
            width: 100%;
            white-space: normal;
            text-align: center;
        }

        .btn-ink {
            background-color: var(--ink);
            border-color: var(--ink);
            color: var(--paper-white);
        }

        .btn-ink:hover {
            background-color: var(--riso-blue);
            border-color: var(--riso-blue);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-ink:disabled,
        .btn-ink[disabled] {
            background-color: var(--line, #ccc);
            border-color: var(--line, #ccc);
            color: var(--paper-white);
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-ink:disabled:hover,
        .btn-ink[disabled]:hover {
            background-color: var(--line, #ccc);
            border-color: var(--line, #ccc);
            transform: none;
            box-shadow: none;
        }

        .waiting-btn {
            background: color-mix(in srgb, var(--riso-blue) 50%, transparent);
            border: 1.5px dashed var(--riso-blue);
            color: var(--paper-white);
            cursor: not-allowed;
        }

        .continue-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 4px;
            font-weight: 600;
            font-size: 14px;
            color: var(--riso-blue);
        }

        .continue-btn:hover {
            gap: 11px;
            color: var(--riso-blue-dark);
        }

        .summary-fineprint {
            margin: 18px 0 0;
            font-size: 12.5px;
            color: var(--ink-faint);
        }

        /* ---------- Empty state ---------- */
        .empty-cart {
            position: relative;
            overflow: hidden;
            max-width: 640px;
            margin: 24px auto 96px;
            padding: 56px 32px 52px;
            text-align: center;
            background: var(--paper-white);
            border: 1.5px dashed var(--line);
            border-radius: var(--r-lg);
        }

        .empty-cart__icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 22px;
            display: grid;
            place-items: center;
            background: var(--paper-dim);
            border-radius: 50%;
            font-size: 28px;
            color: var(--ink);
        }

        .empty-cart h2 {
            margin-bottom: 10px;
            font-size: 1.6rem;
        }

        .empty-cart p {
            max-width: 42ch;
            margin: 0 auto 26px;
        }

        .empty-cart .hero-actions {
            justify-content: center;
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 1279px) {
            .cart-layout {
                grid-template-columns: minmax(0, 1fr);
            }

            .cart-summary {
                position: static;
                max-height: none;
            }
        }

        @media (max-width: 640px) {
            .cart-hero {
                padding-top: 120px;
            }
        }

        @media (max-width: 820px) {
            .cart-steps {
                grid-template-columns: minmax(0, 1fr);
                gap: 8px;
            }

            .cart-item {
                grid-template-columns: auto minmax(0, 1fr);
                grid-template-areas:
                    "check image"
                    "info info"
                    "actions actions";
                gap: 14px 16px;
                padding: 18px;
            }

            .cart-item-image {
                width: 96px;
            }

            .cart-item-actions {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                min-width: 0;
                padding-top: 14px;
                border-top: 1px dashed var(--line);
            }

            .item-subtotal {
                order: 2;
            }

            .remove-btn {
                order: 3;
            }
        }

        @media (max-width: 560px) {
            .toolbar-count {
                display: none;
            }

            .remove-selected-btn {
                margin-left: auto;
                white-space: nowrap;
            }

            .select-all {
                white-space: nowrap;
            }

            .cart-toolbar {
                gap: 10px;
                padding: 12px 14px;
            }

            .remove-selected-btn {
                padding: 9px 12px;
            }

            .summary-body {
                padding: 22px 20px 22px;
            }

            .empty-cart {
                padding: 44px 20px 40px;
            }
        }
    </style>
</head>

<body>
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="main.php"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

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
                <a href="view_cart.php" class="cart-icon active" aria-current="page">
                    <span class="side-nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $total_units > 99 ? '99+' : $total_units; ?></span>
                    </span>
                    <span class="side-nav-label">Cart</span>
                </a>
            </li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="../pages/website/profile.php" class="user-profile">
                    <i class="fas fa-user"></i>
                    <span class="side-nav-label user-name">
                        <?php
                        if (!empty($user_data['first_name'])) {
                            echo htmlspecialchars($user_data['first_name']);
                        } elseif (!empty($user_data['company_name'])) {
                            echo htmlspecialchars($user_data['company_name']);
                        } else {
                            echo 'User';
                        }
                        ?>
                    </span>
                </a>
            </li>
            <li>
                <a href="../accounts/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="side-nav-label">Log Out</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Hero -->
    <section class="cart-hero">
        <div class="cart-hero__texture" aria-hidden="true"></div>
        <div class="container">
            <span class="section-eyebrow"><span class="reg-mark"></span> Order review</span>
            <h1 class="cart-hero-title">Your print <span class="registered" data-text="cart.">cart.</span></h1>
            <p class="cart-hero-sub"><?php echo !empty($cart_items)
                ? 'Pick the jobs you want to move forward, then send them to our team. We confirm the final price before anything goes to checkout.'
                : 'Jobs you add from the service pages show up here for review before checkout.'; ?></p>

            <?php if (!empty($cart_items)): ?>
                <div class="cart-meta">
                    <span class="cart-pill"><i class="fas fa-layer-group"></i> <span class="js-lines"><?php echo count($cart_items); ?> <?php echo count($cart_items) === 1 ? 'job' : 'jobs'; ?></span></span>
                    <span class="cart-pill"><i class="fas fa-circle-check"></i> <span class="js-confirmed"><?php echo $confirmed_in_cart; ?> confirmed</span></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Cart -->
    <main class="cart-page">
        <div class="container">
            <?php if (!empty($cart_items)): ?>

                <ol class="cart-steps" aria-label="Order progress">
                    <li class="cart-step <?php echo cart_step_class(1, $current_step); ?>">
                        <span class="cart-step__num"><?php echo $current_step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?></span>
                        <span><strong>Select your orders</strong><small>Tick the items you want to quote</small></span>
                    </li>
                    <li class="cart-step <?php echo cart_step_class(2, $current_step); ?>">
                        <span class="cart-step__num"><?php echo $current_step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?></span>
                        <span><strong>We confirm the price</strong><small>Our team reviews each job</small></span>
                    </li>
                    <li class="cart-step <?php echo cart_step_class(3, $current_step); ?>">
                        <span class="cart-step__num">3</span>
                        <span><strong>Check out</strong><small>Opens once prices are set</small></span>
                    </li>
                </ol>

                <div class="cart-layout">
                    <div class="cart-main">
                        <details class="pricing-info">
                            <summary><i class="fas fa-info-circle"></i> How pricing works <i class="fas fa-chevron-down chev"></i></summary>
                            <div class="pricing-info__body">
                                <p>Prices shown here are <strong>estimates</strong> based on standard rates. The final price can change with:</p>
                                <ul class="pricing-list">
                                    <li>Complexity of your custom design</li>
                                    <li>Special material requirements</li>
                                    <li>Urgency of the order</li>
                                    <li>Quantity adjustments</li>
                                </ul>
                                <p>Select your items and choose <strong>Requote</strong>. We'll review your requirements and send back the exact price.</p>
                                <p class="quote-turnaround-note" style="margin-top:8px;font-size:13px;color:#6b7280;">
                                    <i class="fas fa-bolt" aria-hidden="true"></i>
                                    We quote most requests <strong>the same day</strong> during business hours (Mon&ndash;Sat, 8 AM&ndash;6 PM) &mdash; 1 business day max.
                                </p>
                            </div>
                        </details>

                        <form method="post" id="cartForm" action="view_cart.php">
                            <div class="cart-toolbar">
                                <label class="select-all">
                                    <input type="checkbox" class="cart-check" id="selectAll" onchange="toggleSelectAll(this); saveScrollPosition(); this.form.submit();">
                                    <span>Select all</span>
                                </label>
                                <span class="toolbar-count js-lines"><?php echo count($cart_items); ?> <?php echo count($cart_items) === 1 ? 'job' : 'jobs'; ?></span>
                                <button type="button" class="remove-selected-btn">
                                    <i class="fas fa-trash"></i> Remove selected
                                </button>
                            </div>

                            <div class="cart-items">
                                <?php foreach ($cart_items as $row):
                                    $has_admin_price = !empty($row['price_updated_by_admin']) && $row['quoted_price'] > 0;
                                    $actual_price    = $has_admin_price ? $row['quoted_price'] : $row['unit_price'];
                                    $item_total      = $actual_price * $row['quantity'];
                                    $is_selected     = in_array($row['item_id'], $selected_items);
                                    $ink             = cart_ink_for_group($row['product_group']);
                                    $image_path      = "../assets/images/services/service-" . $row['id'] . ".jpg";
                                    $has_image       = file_exists($image_path);
                                    $status          = strtolower((string)($row['pricing_status'] ?? ''));
                                    $status_class    = in_array($status, ['pending', 'approved', 'completed', 'cancelled'], true) ? $status : '';
                                ?>
                                    <article class="cart-item" data-ink="<?php echo $ink; ?>">
                                        <div class="item-checkbox">
                                            <input type="checkbox" class="cart-check" name="selected_items[]" value="<?php echo cart_h($row['item_id']); ?>"
                                                <?php echo $is_selected ? 'checked' : ''; ?>
                                                aria-label="Select <?php echo cart_h($row['product_name']); ?>"
                                                onchange="updateCartTotal(); saveScrollPosition(); this.form.submit();">
                                        </div>

                                        <div class="cart-item-image">
                                            <?php if ($has_image): ?>
                                                <img src="<?php echo cart_h($image_path); ?>" alt="<?php echo cart_h($row['product_name']); ?>" loading="lazy">
                                            <?php else: ?>
                                                <i class="fas fa-image" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </div>

                                        <div class="cart-item-info">
                                            <span class="item-group"><?php echo cart_h($row['product_group']); ?></span>
                                            <h3><a href="../pages/website/service_detail.php?id=<?php echo (int)$row['id']; ?>"><?php echo cart_h($row['product_name']); ?></a></h3>

                                            <div class="price-display">
                                                <?php if ($has_admin_price): ?>
                                                    <p class="price">
                                                        <span class="was">₱<?php echo number_format($row['unit_price'], 2); ?></span>₱<?php echo number_format($actual_price, 2); ?>
                                                    </p>
                                                    <span class="admin-price-notice"><i class="fas fa-check-circle"></i> Price confirmed by store</span>
                                                <?php else: ?>
                                                    <p class="price">₱<?php echo number_format($actual_price, 2); ?></p>
                                                    <span class="estimate-tag">Estimate</span>
                                                <?php endif; ?>
                                                <span class="price-unit">each</span>
                                            </div>

                                            <?php if (!empty($row['admin_notes'])): ?>
                                                <div class="admin-notes-section">
                                                    <div class="admin-notes-header">
                                                        <i class="fas fa-sticky-note"></i>
                                                        <span>Note from our team</span>
                                                        <?php if ($status_class !== ''): ?>
                                                            <span class="pricing-status badge <?php echo $status_class; ?>"><?php echo ucfirst($status_class); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="admin-notes-content"><?php echo htmlspecialchars($row['admin_notes']); ?></div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($status === 'cancelled'): ?>
                                                <div class="pricing-status-alert cancelled">
                                                    <i class="fas fa-ban"></i>
                                                    <strong>Pricing request cancelled</strong>
                                                    <p>Your pricing request for this item has been cancelled. Contact us for details, or remove the item from your cart.</p>
                                                </div>
                                            <?php elseif ($status === 'pending'):
                                                $item_eta = cart_item_quote_eta_text($row['quote_request_date'] ?? null);
                                            ?>
                                                <div class="pricing-status-alert pending">
                                                    <i class="fas fa-hourglass-half"></i>
                                                    <strong>Quote in progress</strong>
                                                    <p>
                                                        <?php if ($item_eta): ?>
                                                            Expect this item to be quoted on or before <strong><?php echo cart_h($item_eta); ?></strong>.
                                                        <?php else: ?>
                                                            We're working on this one — expect a quote within 1 business day.
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            $productGroup       = strtolower($row['product_group']);
                                            $isPrintingProduct  = in_array($productGroup, ['riso', 'offset', 'digital', 'riso printing', 'offset printing', 'digital printing']);
                                            $hasSpecs = !empty($row['layout_option']) || !empty($row['gsm_option']) || !empty($row['finish_option']) || !empty($row['paper_option']) || !empty($row['binding_option']) || !empty($row['size_option']);

                                            if ($isPrintingProduct && $hasSpecs):
                                            ?>
                                                <div class="printing-details">
                                                    <div class="details-row">
                                                        <?php if (!empty($row['size_option'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Size</span>
                                                                <span class="detail-value">
                                                                    <?php echo htmlspecialchars($row['size_option']); ?>
                                                                    <?php if (!empty($row['custom_size'])): ?>
                                                                        <small>Custom: <?php echo htmlspecialchars($row['custom_size']); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($row['finish_option_name'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Finish</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['finish_option_name']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($row['paper_option_name'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Paper</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['paper_option_name']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($row['binding_option_name'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Binding</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['binding_option_name']); ?></span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($row['layout_option_name'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">Layout type</span>
                                                                <span class="detail-value">
                                                                    <?php echo htmlspecialchars($row['layout_option_name']); ?>
                                                                    <?php if (!empty($row['layout_details'])): ?>
                                                                        <small>Details: <?php echo htmlspecialchars($row['layout_details']); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if (!empty($row['gsm_option'])): ?>
                                                            <div class="detail-item">
                                                                <span class="detail-label">GSM</span>
                                                                <span class="detail-value"><?php echo htmlspecialchars($row['gsm_option']); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            $layout_files = !empty($row['user_layout_files']) ? cart_layout_files_list($row['user_layout_files']) : [];
                                            if (!empty($layout_files)):
                                            ?>
                                                <div class="custom-design">
                                                    <div class="custom-design-title">
                                                        <i class="fas fa-file-upload"></i> Your uploaded layout file<?php echo count($layout_files) > 1 ? 's' : ''; ?>
                                                    </div>
                                                    <ul class="design-files" style="list-style:none;padding:0;margin:0;">
                                                        <?php foreach ($layout_files as $lf):
                                                            $url = cart_layout_file_url($lf);
                                                            $name = basename($lf);
                                                            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                                            $icon = $ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-image';
                                                        ?>
                                                            <li style="margin-bottom:4px;">
                                                                <?php if ($url && file_exists($url)): ?>
                                                                    <a href="<?php echo cart_h($url); ?>" target="_blank" rel="noopener">
                                                                        <i class="fas <?php echo cart_h($icon); ?>"></i> <?php echo cart_h($name); ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <i class="fas <?php echo cart_h($icon); ?>"></i> <?php echo cart_h($name); ?>
                                                                    <small style="color:#999;">(file not found)</small>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            $design = !empty($row['design_image']) ? cart_parse_design($row['design_image']) : null;
                                            $has_designs = $design && ($design['front_mockup'] !== '' || $design['back_mockup'] !== '' || $design['uploaded_file'] !== '' || $design['front_uploaded_file'] !== '' || $design['back_uploaded_file'] !== '');

                                            if ($has_designs):
                                                $upType = $design['upload_type'];
                                            ?>
                                                <div class="custom-design">
                                                    <div class="custom-design-title">
                                                        <i class="fas fa-palette"></i> Your custom design
                                                        <small><?php echo $upType === 'single' ? 'Same design on both sides' : 'Different designs for front and back'; ?></small>
                                                    </div>

                                                    <div class="design-previews">
                                                        <?php
                                                        if ($upType === 'single' && $design['uploaded_file'] !== '') {
                                                            cart_design_tile('Original file', $design['uploaded_file']);
                                                        }
                                                        if ($design['front_uploaded_file'] !== '') {
                                                            cart_design_tile('Front original', $design['front_uploaded_file']);
                                                        }
                                                        if ($design['back_uploaded_file'] !== '') {
                                                            cart_design_tile('Back original', $design['back_uploaded_file']);
                                                        }
                                                        if ($design['front_mockup'] !== '') {
                                                            cart_design_tile('Front mockup', $design['front_mockup'], 'fa-image');
                                                        }
                                                        if ($design['back_mockup'] !== '') {
                                                            cart_design_tile('Back mockup', $design['back_mockup'], 'fa-image');
                                                        }
                                                        ?>
                                                    </div>

                                                    <div class="design-files">
                                                        <strong>Upload type:</strong> <?php echo cart_h(ucfirst($upType)); ?>
                                                        <?php if ($upType === 'single' && $design['uploaded_file'] !== ''): ?>
                                                            &nbsp;&middot;&nbsp; <strong>File:</strong> <?php echo cart_h(basename($design['uploaded_file'])); ?>
                                                        <?php endif; ?>
                                                        <?php if ($upType === 'separate'): ?>
                                                            <?php if ($design['front_uploaded_file'] !== ''): ?>
                                                                &nbsp;&middot;&nbsp; <strong>Front:</strong> <?php echo cart_h(basename($design['front_uploaded_file'])); ?>
                                                            <?php endif; ?>
                                                            <?php if ($design['back_uploaded_file'] !== ''): ?>
                                                                &nbsp;&middot;&nbsp; <strong>Back:</strong> <?php echo cart_h(basename($design['back_uploaded_file'])); ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="cart-item-actions">
                                            <div class="quantity-controls">
                                                <button type="button" class="quantity-btn decrease" aria-label="Decrease quantity"><i class="fas fa-minus"></i></button>
                                                <input type="number" name="quantity" class="quantity-input" value="<?php echo (int)$row['quantity']; ?>" min="1" aria-label="Quantity">
                                                <button type="button" class="quantity-btn increase" aria-label="Increase quantity"><i class="fas fa-plus"></i></button>
                                            </div>

                                            <div class="item-subtotal">
                                                <span class="item-subtotal__label">Subtotal</span>
                                                <span class="subtotal-amount">₱<?php echo number_format($item_total, 2); ?></span>
                                            </div>

                                            <button type="button" class="remove-btn">
                                                <i class="fas fa-trash"></i> Remove
                                            </button>
                                            <input type="hidden" name="item_id" value="<?php echo cart_h($row['item_id']); ?>">
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>

                    <!-- Order Summary -->
                    <aside class="cart-side" aria-label="Order summary">
                        <div class="cart-summary">
                            <div class="summary-bar" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            <div class="summary-body">
                                <h2 class="summary-title">Order summary</h2>

                                <?php if ($total_selected_items === 0): ?>
                                    <p class="summary-empty">Select the jobs you want to quote or check out to see your totals.</p>
                                <?php endif; ?>

                                <?php if ($items_with_admin_prices > 0): ?>
                                    <div class="pricing-status-alert success">
                                        <i class="fas fa-check-circle"></i>
                                        <strong><?php echo $items_with_admin_prices; ?> selected item<?php echo $items_with_admin_prices === 1 ? ' has' : 's have'; ?> confirmed pricing</strong>
                                        <p>Ready for checkout at the store-approved price.</p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$all_prices_updated && $total_selected_items > 0): ?>
                                    <div class="pricing-status-alert warning">
                                        <i class="fas fa-clock"></i>
                                        <strong>Waiting for price confirmation</strong>
                                        <p>
                                            <?php if ($items_with_admin_prices > 0): ?>
                                                <?php echo $total_selected_items - $items_with_admin_prices; ?> of your <?php echo $total_selected_items; ?> selected items are still waiting for the store to confirm pricing.
                                            <?php else: ?>
                                                All <?php echo $total_selected_items; ?> selected items are waiting for the store to confirm pricing.
                                            <?php endif; ?>
                                            Checkout opens once every selected item is confirmed.
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="summary-rows">
                                    <div class="summary-row">
                                        <span>Subtotal (<span id="selected-count"><?php echo $total_selected_items; ?></span> items)</span>
                                        <span id="subtotal-amount">₱<?php echo number_format($selected_total, 2); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Tax (3%)</span>
                                        <span id="tax-amount">₱<?php echo number_format($selected_tax, 2); ?></span>
                                    </div>
                                    <div class="summary-row total">
                                        <span>Total</span>
                                        <span id="total-amount">₱<?php echo number_format($selected_grand, 2); ?></span>
                                    </div>
                                </div>

                                <div class="cart-buttons">
                                    <button type="submit" name="proceed_to_checkout" class="btn btn-primary checkout-btn" form="cartForm"
                                        <?php echo $can_checkout ? '' : 'hidden'; ?>>
                                        <i class="fas fa-check"></i> Proceed to checkout
                                    </button>

                                    <button type="button" class="btn waiting-btn" aria-disabled="true"
                                        title="<?php echo cart_h($checkout_message); ?>"
                                        <?php echo ($total_selected_items > 0 && !$can_checkout) ? '' : 'hidden'; ?>>
                                        <i class="fas fa-lock"></i> Proceed to checkout
                                    </button>

                                    <button type="submit" name="request_pricing" class="btn btn-ink request-btn" form="cartForm"
                                        <?php echo ($total_selected_items > 0 && $all_prices_updated) ? 'disabled title="All selected items already have a confirmed price."' : ''; ?>>
                                        <i class="fas fa-envelope"></i> Request price confirmation
                                    </button>

                                    <a href="main.php#services" class="continue-btn">
                                        <i class="fas fa-arrow-left"></i> Continue shopping
                                    </a>
                                </div>

                                <p class="summary-fineprint">Estimates only until our team confirms each item. Checkout is available once every selected item has a confirmed price.</p>
                            </div>
                        </div>
                    </aside>
                </div>

            <?php else: ?>

                <div class="empty-cart">
                    <div class="empty-cart__icon"><i class="fas fa-shopping-cart"></i></div>
                    <h2>Your cart is empty</h2>
                    <p>Pick a printing service to start a job. You can set the size, paper, finish and upload your artwork on each service page.</p>
                    <div class="hero-actions">
                        <a href="main.php#services" class="btn btn-primary"><i class="fas fa-store"></i> Browse services</a>
                        <a href="contact.php" class="btn btn-secondary">Talk to our team</a>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </main>

    <!-- Help band -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Need a hand with your order?</h2>
                <p>Ask about paper, finishes, artwork files or turnaround. Our team in Malolos will help you get the job right.</p>
                <div class="hero-actions">
                    <a href="#" class="btn btn-primary" onclick="toggleChat(); return false;">Chat with us</a>
                    <a href="contact.php" class="btn btn-secondary">Contact us</a>
                </div>
            </div>
        </div>
    </section>

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
                        <li><a href="main.php#offset">Offset Printing</a></li>
                        <li><a href="main.php#digital">Digital Printing</a></li>
                        <li><a href="main.php#riso">RISO Printing</a></li>
                        <li><a href="main.php#other">Other Services</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="about.php">Our Team</a></li>
                        <li><a href="about.php">Careers</a></li>
                        <li><a href="about.php">Testimonials</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="contact.php">FAQ</a></li>
                        <li><a href="contact.php">Shipping Info</a></li>
                        <li><a href="contact.php">Returns</a></li>
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
                    <p>&copy; <?php echo date('Y'); ?> Active Media Designs & Printing. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="">Privacy Policy</a>
                    <a href="">Terms of Service</a>
                    <a href="">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
        /* =========================================================
           Cart behaviour. Same endpoints and payloads as before
           (update_cart.php: update / remove / remove_selected /
           save_selected_items); only the markup hooks changed.
        ========================================================= */
        const CART_ENDPOINT = '../pages/website/update_cart.php';
        const TAX_RATE = <?php echo json_encode($tax_rate); ?>;

        // Price / quantity / confirmation state for every cart line
        const productData = <?php echo json_encode($product_data, JSON_FORCE_OBJECT); ?>;

        function saveScrollPosition() {
            sessionStorage.setItem('scrollPosition', window.scrollY);
        }

        // Restore scroll position instantly after a checkbox re-submit
        document.addEventListener('DOMContentLoaded', function() {
            const scrollPosition = sessionStorage.getItem('scrollPosition');
            if (scrollPosition) {
                window.scrollTo(0, parseInt(scrollPosition, 10));
                sessionStorage.removeItem('scrollPosition');
            }
        });

        /* ---------- helpers ---------- */
        function peso(n) {
            return '₱' + Number(n).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function cartRequest(action, fields, selectedIds) {
            const formData = new FormData();
            formData.append('action', action);
            Object.entries(fields || {}).forEach(([key, value]) => formData.append(key, value));
            (selectedIds || []).forEach(id => formData.append('selected_items[]', id));

            return fetch(CART_ENDPOINT, {
                method: 'POST',
                body: formData
            }).then(response => response.json());
        }

        function findCartItem(itemId) {
            const hidden = document.querySelector(`input[name="item_id"][value="${CSS.escape(String(itemId))}"]`);
            return hidden ? hidden.closest('.cart-item') : null;
        }

        function itemIdOf(element) {
            return element.closest('.cart-item').querySelector('input[name="item_id"]').value;
        }

        /* ---------- init ---------- */
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.getElementById('cartForm')) return; // empty cart: nothing to wire up

            setupQuantityControls();
            setupRemoveButtons();
            setupBulkRemove();

            updateSelectAll();
            updateCartTotal();
        });

        function setupQuantityControls() {
            document.querySelectorAll('.quantity-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    const input = this.closest('.quantity-controls').querySelector('.quantity-input');
                    const itemId = itemIdOf(this);

                    if (this.classList.contains('increase')) {
                        input.stepUp();
                    } else {
                        input.stepDown();
                        if (input.value < 1) input.value = 1;
                    }

                    if (parseInt(input.value, 10) !== productData[itemId].quantity) {
                        updateCartItem(itemId, input.value);
                    }
                });
            });

            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value < 1) this.value = 1;
                    const itemId = itemIdOf(this);
                    if (parseInt(this.value, 10) !== productData[itemId].quantity) {
                        updateCartItem(itemId, this.value);
                    }
                });
            });
        }

        function setupRemoveButtons() {
            document.querySelectorAll('.remove-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Are you sure you want to remove this item from your cart?')) return;
                    removeCartItem(itemIdOf(this));
                });
            });
        }

        function setupBulkRemove() {
            const bulkRemoveBtn = document.querySelector('.remove-selected-btn');
            if (bulkRemoveBtn) {
                bulkRemoveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    handleBulkRemove();
                });
            }
        }

        /* ---------- server actions ---------- */
        function handleBulkRemove() {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (selectedItems.length === 0) {
                alert('Please select at least one item to remove.');
                return;
            }

            if (!confirm(`Are you sure you want to remove ${selectedItems.length} selected item(s) from your cart?`)) {
                return;
            }

            removeSelectedItems(selectedItems);
        }

        function removeSelectedItems(selectedItems) {
            const itemIds = Array.from(selectedItems).map(item => item.value);

            cartRequest('remove_selected', {}, itemIds)
                .then(data => {
                    if (data.status === 'success' || data.status === 'partial') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error removing selected items');
                });
        }

        function updateCartItem(itemId, quantity) {
            showLoading(itemId);

            cartRequest('update', {
                    item_id: itemId,
                    quantity: quantity
                })
                .then(data => {
                    if (data.status === 'success') {
                        if (productData[itemId]) {
                            productData[itemId].quantity = parseInt(quantity, 10);
                            productData[itemId].subtotal = productData[itemId].price * parseInt(quantity, 10);
                        }

                        updateItemSubtotal(itemId);
                        updateCartTotal();
                        updateCartCount();
                        showSuccess(itemId, 'Quantity updated');
                    } else {
                        showError(itemId, 'Error: ' + data.message);
                        resetQuantityInput(itemId);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError(itemId, 'Network error updating quantity');
                    resetQuantityInput(itemId);
                });
        }

        function resetQuantityInput(itemId) {
            const item = findCartItem(itemId);
            if (item && productData[itemId]) {
                item.querySelector('.quantity-input').value = productData[itemId].quantity;
            }
        }

        function removeCartItem(itemId) {
            showLoading(itemId);

            cartRequest('remove', {
                    item_id: itemId
                })
                .then(data => {
                    if (data.status === 'success') {
                        const itemElement = findCartItem(itemId);
                        if (itemElement) itemElement.style.opacity = '0.5';

                        setTimeout(() => {
                            if (itemElement) itemElement.remove();
                            delete productData[itemId];

                            updateCartTotal();
                            updateCartCount();
                            updateSelectAll();

                            if (document.querySelectorAll('.cart-item').length === 0) {
                                location.reload();
                            }
                        }, 500);
                    } else {
                        showError(itemId, 'Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError(itemId, 'Network error removing item');
                });
        }

        function saveSelectedItems() {
            const selectedIds = Array.from(document.querySelectorAll('input[name="selected_items[]"]:checked')).map(item => item.value);

            cartRequest('save_selected_items', {}, selectedIds)
                .catch(error => console.error('Error saving selected items:', error));
        }

        /* ---------- per-item feedback ---------- */
        function showLoading(itemId) {
            const item = findCartItem(itemId);
            if (!item) return;
            item.style.opacity = '0.7';
            item.style.pointerEvents = 'none';
        }

        function showSuccess(itemId, message) {
            const item = findCartItem(itemId);
            if (!item) return;
            item.style.opacity = '1';
            item.style.pointerEvents = 'auto';

            const toast = document.createElement('div');
            toast.className = 'item-toast';
            toast.textContent = message;
            item.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        }

        function showError(itemId, message) {
            const item = findCartItem(itemId);
            if (item) {
                item.style.opacity = '1';
                item.style.pointerEvents = 'auto';
            }
            alert(message);
        }

        /* ---------- totals & selection ---------- */
        function updateItemSubtotal(itemId) {
            const item = findCartItem(itemId);
            const amount = item ? item.querySelector('.subtotal-amount') : null;
            if (amount && productData[itemId]) {
                amount.textContent = peso(productData[itemId].subtotal);
            }
        }

        // Nav badge + the "N jobs / N confirmed" labels
        function updateCartCount() {
            const ids = Object.keys(productData);
            const totalUnits = ids.reduce((sum, id) => sum + productData[id].quantity, 0);
            const confirmed = ids.filter(id => productData[id].hasAdminPrice).length;

            const cartCountElement = document.querySelector('.cart-count');
            if (cartCountElement) cartCountElement.textContent = totalUnits > 99 ? '99+' : totalUnits;

            document.querySelectorAll('.js-lines').forEach(el => {
                el.textContent = ids.length + (ids.length === 1 ? ' job' : ' jobs');
            });
            document.querySelectorAll('.js-confirmed').forEach(el => {
                el.textContent = confirmed + ' confirmed';
            });
        }

        function toggleSelectAll(checkbox) {
            document.querySelectorAll('input[name="selected_items[]"]').forEach(item => {
                item.checked = checkbox.checked;
            });
            updateCartTotal();
        }

        function updateSelectAll() {
            const itemCheckboxes = document.querySelectorAll('input[name="selected_items[]"]');
            const selectAll = document.getElementById('selectAll');
            if (!selectAll) return;

            if (itemCheckboxes.length > 0) {
                const allChecked = Array.from(itemCheckboxes).every(checkbox => checkbox.checked);
                const someChecked = Array.from(itemCheckboxes).some(checkbox => checkbox.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
        }

        function updateCartTotal() {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]:checked');
            let subtotal = 0;

            selectedItems.forEach(item => {
                if (productData[item.value]) subtotal += productData[item.value].subtotal;
            });

            const tax = subtotal * TAX_RATE;

            document.getElementById('selected-count').textContent = selectedItems.length;
            document.getElementById('subtotal-amount').textContent = peso(subtotal);
            document.getElementById('tax-amount').textContent = peso(tax);
            document.getElementById('total-amount').textContent = peso(subtotal + tax);

            updateSelectAll();
            updateCheckoutState();
            saveSelectedItems();
        }

        // Show the checkout button only when every selected item has a confirmed price
        function updateCheckoutState() {
            const selectedItems = Array.from(document.querySelectorAll('input[name="selected_items[]"]:checked'));
            const selectedCount = selectedItems.length;
            const confirmedCount = selectedItems.filter(item => productData[item.value] && productData[item.value].hasAdminPrice).length;
            const canCheckout = selectedCount > 0 && confirmedCount === selectedCount;

            const checkoutBtn = document.querySelector('.checkout-btn');
            const waitingBtn = document.querySelector('.waiting-btn');

            if (checkoutBtn) checkoutBtn.hidden = !canCheckout;
            if (waitingBtn) {
                waitingBtn.hidden = selectedCount === 0 || canCheckout;

                let message = 'Will be available once every selected item has confirmed pricing';
                if (confirmedCount > 0) {
                    message = `${confirmedCount} of ${selectedCount} selected items have confirmed pricing`;
                }
                waitingBtn.title = message;
            }
        }
    </script>
    <script>
        // Chat functionality with auto-scroll improvements
        let currentConversationId = null;
        let chatRefreshInterval = null;

        // Auto-scroll variables
        let isUserScrolling = false;
        let shouldAutoScroll = true;
        let scrollDebounceTimer = null;
        let lastScrollPosition = 0;
        let scrollDirection = 'down';

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

            // Setup scroll detection when chat opens
            setTimeout(() => {
                const chatWidget = document.getElementById('chatWidget');
                if (chatWidget && chatWidget.classList.contains('open')) {
                    setupScrollDetection();
                }
            }, 1000);
        });

        // Toggle chat widget
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            if (widget) {
                widget.classList.toggle('open');

                if (widget.classList.contains('open')) {
                    loadConversations();
                    startChatRefresh();
                    // Setup scroll detection when chat opens
                    setTimeout(setupScrollDetection, 500);
                } else {
                    stopChatRefresh();
                }
            }
        }

        // ========== AUTO-SCROLL DETECTION ==========
        function setupScrollDetection() {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            // Detect user scroll intent
            messagesList.addEventListener('scroll', function() {
                clearTimeout(scrollDebounceTimer);

                // Calculate scroll position and direction
                const currentScrollTop = messagesList.scrollTop;
                const maxScrollTop = messagesList.scrollHeight - messagesList.clientHeight;

                // Determine scroll direction
                if (currentScrollTop < lastScrollPosition) {
                    scrollDirection = 'up';
                } else if (currentScrollTop > lastScrollPosition) {
                    scrollDirection = 'down';
                }
                lastScrollPosition = currentScrollTop;

                // If user is scrolling up, they're likely reading old messages
                const isNearBottom = maxScrollTop - currentScrollTop <= 100; // 100px from bottom
                isUserScrolling = true;

                // If scrolling up OR not near bottom, user is reading old messages
                if (scrollDirection === 'up' || !isNearBottom) {
                    shouldAutoScroll = false;
                } else {
                    // If scrolling down and near bottom, enable auto-scroll
                    shouldAutoScroll = true;
                }

                // Reset after user stops scrolling
                scrollDebounceTimer = setTimeout(() => {
                    isUserScrolling = false;

                    // If user stopped near bottom, re-enable auto-scroll
                    const newScrollTop = messagesList.scrollTop;
                    const newMaxScroll = messagesList.scrollHeight - messagesList.clientHeight;
                    if (newMaxScroll - newScrollTop <= 50) {
                        shouldAutoScroll = true;
                    }
                }, 1000); // 1 second delay
            });

            // Also detect mouse wheel and touch events
            messagesList.addEventListener('wheel', function() {
                isUserScrolling = true;
            });

            messagesList.addEventListener('touchstart', function() {
                isUserScrolling = true;
            });

            // Keyboard shortcut to jump to bottom (Ctrl+End)
            messagesList.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'End') {
                    e.preventDefault();
                    scrollToBottom(messagesList, true);
                    shouldAutoScroll = true;
                    isUserScrolling = false;
                }
            });
        }

        function isAtBottom(element, threshold = 100) {
            if (!element) return false;
            const maxScrollTop = element.scrollHeight - element.clientHeight;
            return maxScrollTop - element.scrollTop <= threshold;
        }

        function scrollToBottom(element, smooth = false) {
            if (!element) return;

            const scrollOptions = {
                top: element.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            };

            element.scrollTo(scrollOptions);
            shouldAutoScroll = true;
        }

        // ========== NEW MESSAGES INDICATOR ==========
        function showNewMessagesIndicator() {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            // Remove existing indicator
            const existingIndicator = document.querySelector('.new-messages-indicator');
            if (existingIndicator) existingIndicator.remove();

            // Create indicator
            const indicator = document.createElement('div');
            indicator.className = 'new-messages-indicator';
            indicator.innerHTML = `
            <button onclick="scrollToNewMessages()">
                <i class="fas fa-arrow-down"></i>
                New messages
            </button>
        `;

            // Add to messages area
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.appendChild(indicator);
            }
        }

        function scrollToNewMessages() {
            const messagesList = document.getElementById('messagesList');
            if (messagesList) {
                scrollToBottom(messagesList, true);
                shouldAutoScroll = true;
                isUserScrolling = false;

                // Remove indicator
                const indicator = document.querySelector('.new-messages-indicator');
                if (indicator) indicator.remove();
            }
        }

        // Load conversations
        async function loadConversations() {
            try {
                const response = await fetch('../api/chat_api.php?action=conversations');
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
                const response = await fetch('../api/chat_api.php?action=conversation_limit');
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

                const response = await fetch('../api/chat_api.php', {
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

            // Reset scroll state
            shouldAutoScroll = true;
            isUserScrolling = false;

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

            // Setup scroll detection
            setTimeout(setupScrollDetection, 100);
        }

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;

            // Reset scroll state
            shouldAutoScroll = true;
            isUserScrolling = false;

            document.getElementById('chatConversations').style.display = 'flex';
            document.getElementById('chatMessages').classList.remove('active');
            document.getElementById('chatInputArea').classList.remove('active');
            document.getElementById('chatBackBtn').classList.remove('visible');
            document.getElementById('chatTitle').textContent = 'Messages';

            loadConversations();
        }

        // Load messages with auto-scroll improvements
        async function loadMessages(conversationId) {
            try {
                const response = await fetch(`../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
                const data = await response.json();

                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showChatError('Failed to load messages. Please try again.');
            }
        }

        // Render messages with auto-scroll logic
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!container) return;

            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

            // Store current scroll position
            const wasAtBottom = isAtBottom(container);

            // Clear container and render messages
            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == userId;
                const isSystem = msg.message_type === 'system';
                const isAdmin = msg.sender_role === 'admin';

                return `
            <div class="message-item ${isSent ? 'sent' : 'received'} ${isSystem ? 'system' : ''}" data-message-id="${msg.id}">
                ${!isSent && !isSystem ? `
                    <div class="message-sender">
                        ${escapeHtml(msg.sender_username)}
                    </div>
                ` : ''}
                <div class="message-bubble">
                    <div class="message-text">${escapeHtml(msg.message)}</div>
                    <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                </div>
            </div>
            `;
            }).join('');

            // Only auto-scroll if:
            // 1. User is not actively scrolling
            // 2. Should auto-scroll is true (user is at bottom or new message came in)
            // 3. User was already at bottom before rendering new messages
            if (!isUserScrolling && shouldAutoScroll && wasAtBottom) {
                setTimeout(() => {
                    scrollToBottom(container, true);
                }, 100);
            } else if (!wasAtBottom) {
                // Show "new messages" indicator
                showNewMessagesIndicator();
            }
        }

        // Send message with auto-scroll for user's own messages
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();

            if (!message || !currentConversationId) return;

            // Disable send button
            const sendBtn = document.getElementById('chatSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            try {
                const response = await fetch('../api/chat_api.php', {
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

                    // Force auto-scroll for user's own messages
                    shouldAutoScroll = true;
                    isUserScrolling = false;

                    // Load messages will handle scrolling
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

                const response = await fetch('../api/chat_api.php', {
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

            // Only scroll if user is at bottom
            if (!isUserScrolling && shouldAutoScroll && isAtBottom(messagesList)) {
                setTimeout(() => {
                    scrollToBottom(messagesList, true);
                }, 100);
            }
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
                const response = await fetch('../api/chat_api.php?action=unread_count');
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

        // Start auto-refresh with auto-scroll consideration
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    const messagesList = document.getElementById('messagesList');
                    if (messagesList) {
                        const wasAtBottom = isAtBottom(messagesList);

                        // Load messages
                        loadMessages(currentConversationId);

                        // Only show notification if user is not at bottom
                        if (!wasAtBottom && !isUserScrolling && !shouldAutoScroll) {
                            showNewMessagesIndicator();
                        }
                    }
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

        // Add CSS for new messages indicator
        const newMessagesIndicatorCSS = `
        .new-messages-indicator {
            position: absolute;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            animation: fadeInUp 0.3s ease;
        }
        
        .new-messages-indicator button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        
        .new-messages-indicator button:hover {
            transform: translateY(-2px);
            background: var(--primary-dark);
        }
        
        .new-messages-indicator button i {
            animation: bounce 2s infinite;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-3px);
            }
            60% {
                transform: translateY(-2px);
            }
        }
    `;

        // Inject CSS
        const style = document.createElement('style');
        style.textContent = newMessagesIndicatorCSS;
        document.head.appendChild(style);

        // Make functions available globally
        window.toggleChat = toggleChat;
        window.openConversation = openConversation;
        window.goBackToConversations = goBackToConversations;
        window.sendMessage = sendMessage;
        window.startNewConversation = startNewConversation;
        window.deleteConversation = deleteConversation;
        window.scrollToNewMessages = scrollToNewMessages;
    </script>
</body>

</html>
<?php
// Close connection
$inventory->close();