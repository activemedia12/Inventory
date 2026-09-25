<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';
require_once '../../config/security.php';

// Make every SQL error throw, so the try/catch + rollback below really works.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** Show a friendly message and stop. Nothing has been saved when this is called. */
function order_fail(string $message): void
{
    http_response_code(422);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Order not placed</title></head>'
        . '<body style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:0 16px">'
        . '<h2>We couldn\'t place your order</h2><p>' . esc_html($message) . '</p>'
        . '<p><a href="../../website/view_cart.php">Back to your cart</a></p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../website/view_cart.php");
    exit;
}

if (!csrf_valid()) {
    order_fail('Your session expired. Please go back to checkout and try again.');
}

$user_id = (int) $_SESSION['user_id'];

// ---------------------------------------------------------------------
// 1. Which cart items? (clean list of positive integers)
// ---------------------------------------------------------------------
$selected_items = array_values(array_unique(array_filter(
    array_map('intval', explode(',', (string) ($_POST['selected_items'] ?? ''))),
    static function ($id) {
        return $id > 0;
    }
)));

if (!$selected_items) {
    order_fail('No items were selected.');
}

// ---------------------------------------------------------------------
// 2. Load the items from the DATABASE and work out the price ourselves.
//    The total posted by the browser is never trusted.
// ---------------------------------------------------------------------
$placeholders = implode(',', array_fill(0, count($selected_items), '?'));
$types        = str_repeat('i', count($selected_items) + 1);

$query = "SELECT p.id, p.product_name, p.category, p.price,
                 ci.item_id, ci.quantity, ci.quoted_price, ci.price_updated_by_admin,
                 ci.layout_option, ci.layout_details,
                 ci.gsm_option, ci.user_layout_files, ci.design_image,
                 ci.size_option, ci.custom_size, ci.color_option, ci.custom_color,
                 ci.finish_option, ci.paper_option, ci.binding_option
          FROM cart_items ci
          JOIN products_offered p ON ci.product_id = p.id
          JOIN carts c ON ci.cart_id = c.cart_id
          WHERE c.user_id = ? AND ci.item_id IN ($placeholders)";
$stmt = $inventory->prepare($query);
$stmt->bind_param($types, $user_id, ...$selected_items);
$stmt->execute();
$result = $stmt->get_result();

$items    = [];
$subtotal = 0.0;
while ($row = $result->fetch_assoc()) {
    $qty = (int) $row['quantity'];
    if ($qty < 1) {
        order_fail('One of the items in your cart has an invalid quantity.');
    }

    // Same rule as checkout.php / view_cart.php: the admin's quoted price wins
    // when there is one, otherwise the normal catalog price is used.
    $has_quote = !empty($row['price_updated_by_admin']) && (float) $row['quoted_price'] > 0;
    $unit      = $has_quote ? (float) $row['quoted_price'] : (float) $row['price'];

    $row['final_unit_price'] = $unit;
    $subtotal += $unit * $qty;
    $items[] = $row;
}

if (count($items) !== count($selected_items)) {
    order_fail('Some items are no longer in your cart. Please review your cart and try again.');
}

$total_amount = round($subtotal * (1 + ORDER_TAX_RATE), 2);
if ($total_amount <= 0) {
    order_fail('This order has no price yet. Please wait for the price quote before checking out.');
}

// If the page the customer saw disagrees with our number, do not charge anything unexpected.
$client_total = isset($_POST['total_amount']) ? (float) $_POST['total_amount'] : null;
if ($client_total === null || abs(round($client_total, 2) - $total_amount) > 0.01) {
    error_log("process_order: total mismatch for user $user_id (posted " . ($_POST['total_amount'] ?? 'none') . ", server $total_amount)");
    order_fail('The prices in your order have changed. Please go back to checkout to review the updated total.');
}

// ---------------------------------------------------------------------
// 3. Payment proof: strict validation, random name, private folder.
// ---------------------------------------------------------------------
$payment_proof      = '';
$saved_proof_path   = null;

if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] !== UPLOAD_ERR_NO_FILE) {
    $upload = save_uploaded_file(
        $_FILES['payment_proof'],
        private_storage_path("payments/user_{$user_id}"),
        upload_allowed_payment(),
        'payment_',
        UPLOAD_MAX_PAYMENT_BYTES
    );
    if (!$upload['ok']) {
        order_fail('Payment proof: ' . $upload['error']);
    }
    $payment_proof    = $upload['filename'];
    $saved_proof_path = $upload['path'];
}

// ---------------------------------------------------------------------
// 4. Save everything or nothing.
// ---------------------------------------------------------------------
$inventory->begin_transaction();

try {
    $stmt = $inventory->prepare("INSERT INTO orders (user_id, total_amount, payment_proof) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $user_id, $total_amount, $payment_proof);
    $stmt->execute();
    $order_id = $inventory->insert_id;

    $order_items_query = "INSERT INTO order_items (order_id, product_id, product_name, product_category, unit_price, quantity, layout_option, layout_details, gsm_option, user_layout_files, design_image, size_option, custom_size, color_option, custom_color, finish_option, paper_option, binding_option) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $order_items_stmt = $inventory->prepare($order_items_query);

    foreach ($items as $item) {
        $order_items_stmt->bind_param(
            "iissdissssssssssss",
            $order_id,
            $item['id'],
            $item['product_name'],
            $item['category'],
            $item['final_unit_price'],   // price actually charged (quote or catalog)
            $item['quantity'],
            $item['layout_option'],
            $item['layout_details'],
            $item['gsm_option'],
            $item['user_layout_files'],
            $item['design_image'],
            $item['size_option'],
            $item['custom_size'],
            $item['color_option'],
            $item['custom_color'],
            $item['finish_option'],
            $item['paper_option'],
            $item['binding_option']
        );
        $order_items_stmt->execute();
    }

    // Remove the ordered items from the cart
    $delete_query = "DELETE ci FROM cart_items ci
                     JOIN carts c ON ci.cart_id = c.cart_id
                     WHERE c.user_id = ? AND ci.item_id IN ($placeholders)";
    $delete_stmt = $inventory->prepare($delete_query);
    $delete_stmt->bind_param($types, $user_id, ...$selected_items);
    $delete_stmt->execute();

    $inventory->commit();
} catch (Throwable $e) {
    $inventory->rollback();
    if ($saved_proof_path !== null && is_file($saved_proof_path)) {
        @unlink($saved_proof_path);   // don't leave an orphan file behind
    }
    error_log('process_order failed: ' . $e->getMessage());
    order_fail('Something went wrong while saving your order. Nothing was charged. Please try again.');
}

header("Location: profile.php?order_success=1");
exit;