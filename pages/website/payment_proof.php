<?php
/**
 * payment_proof.php?order_id=123
 *
 * Streams a payment proof to the person who is allowed to see it:
 *   - the customer who placed the order, or
 *   - an admin / employee.
 * Payment proofs are no longer reachable by a plain URL.
 */
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';

function proof_deny(int $code, string $text): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

if (!isset($_SESSION['user_id'])) {
    proof_deny(403, 'Please log in.');
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id || $order_id < 1) {
    proof_deny(400, 'Bad request.');
}

$stmt = $inventory->prepare("SELECT user_id, payment_proof FROM orders WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

$is_staff = in_array($_SESSION['role'] ?? '', ['admin', 'employee'], true);

// 404 (not 403) for other people's orders so order numbers can't be probed.
if (!$order || $order['payment_proof'] === '' || $order['payment_proof'] === null) {
    proof_deny(404, 'Not found.');
}
if (!$is_staff && (int) $order['user_id'] !== (int) $_SESSION['user_id']) {
    proof_deny(404, 'Not found.');
}

$path = payment_proof_path((int) $order['user_id'], (string) $order['payment_proof']);
if ($path === null) {
    proof_deny(404, 'File not found.');
}
$file = basename($path);   // used below for the download filename

$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = (string) $finfo->file($path);
$inline = in_array($mime, $allowed_mimes, true);
if (!$inline) {
    $mime = 'application/octet-stream';
}

session_write_close();   // don't hold the session lock while streaming

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($file) . '"');
readfile($path);
exit;