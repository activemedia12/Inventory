<?php
/**
 * upload_balance_proof.php  (POST: order_id, balance_proof file, csrf token)
 *
 * Lets a customer upload the proof for the remaining balance of their own
 * downpayment order. Put it next to process_order.php.
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';
require_once '../../config/security.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function balance_fail(string $message): void
{
    http_response_code(422);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Upload failed</title></head>'
        . '<body style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:0 16px">'
        . '<h2>We couldn\'t save your proof</h2><p>' . esc_html($message) . '</p>'
        . '<p><a href="profile.php">Back to your orders</a></p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit;
}
if (!csrf_valid()) {
    balance_fail('Your session expired. Please go back and try again.');
}

$user_id  = (int) $_SESSION['user_id'];
$order_id = (int) ($_POST['order_id'] ?? 0);

try {
    // Only the customer's own order; same message for "not yours" and "not found".
    $stmt = $inventory->prepare("SELECT status, total_amount, amount_paid, is_downpayment, balance_proof FROM orders WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
} catch (Throwable $e) {
    error_log('upload_balance_proof lookup failed: ' . $e->getMessage());
    balance_fail('Something went wrong. Please try again later.');
}
if (!$order_id || !$order) {
    balance_fail('Order not found.');
}

// A balance is owed only after the downpayment is verified and until the balance is verified.
$owed = round((float) $order['total_amount'] - (float) $order['amount_paid'], 2);
if (empty($order['is_downpayment']) || in_array($order['status'], ['pending', 'cancelled'], true) || $owed <= 0.004) {
    balance_fail('This order has no balance waiting for a payment proof.');
}

if (!isset($_FILES['balance_proof']) || $_FILES['balance_proof']['error'] === UPLOAD_ERR_NO_FILE) {
    balance_fail('Please choose a file to upload.');
}
$upload = save_uploaded_file(
    $_FILES['balance_proof'],
    private_storage_path("payments/user_{$user_id}"),
    upload_allowed_payment(),
    'payment_',
    UPLOAD_MAX_PAYMENT_BYTES
);
if (!$upload['ok']) {
    balance_fail('Payment proof: ' . $upload['error']);
}

try {
    // The shop re-checks the amount on the new file, so any earlier checked amount is cleared.
    $stmt = $inventory->prepare("UPDATE orders SET balance_proof = ?, balance_proof_amount = NULL WHERE order_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $upload['filename'], $order_id, $user_id);
    $stmt->execute();
} catch (Throwable $e) {
    error_log('upload_balance_proof save failed: ' . $e->getMessage());
    if (is_file($upload['path'])) {
        @unlink($upload['path']);
    }
    balance_fail('Something went wrong while saving your proof. Please try again.');
}

// Replace the previous balance proof file, if there was one.
if (!empty($order['balance_proof'])) {
    $old = payment_proof_path($user_id, (string) $order['balance_proof']);
    if ($old && is_file($old)) {
        @unlink($old);
    }
}

header("Location: profile.php?balance_uploaded=1");
exit;