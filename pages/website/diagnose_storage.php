<?php
/**
 * TEMPORARY DIAGNOSTIC SCRIPT — delete this file as soon as you're done with it.
 * It reveals server file paths and should never stay on a live site.
 *
 * Visit this page once while logged in as admin, read the output, then delete the file.
 */
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'employee'], true)) {
    die('Log in as admin first, then reload this page.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Which files are actually running ===\n";
echo "db.php:            " . realpath(__DIR__ . '/../../config/db.php') . "  (modified " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/../../config/db.php')) . ")\n";
$secpath = __DIR__ . '/../../config/security.php';
echo "security.php:       " . (file_exists($secpath) ? realpath($secpath) . "  (modified " . date('Y-m-d H:i:s', filemtime($secpath)) . ")" : "NOT FOUND at $secpath") . "\n";
echo "process_order.php:  " . __DIR__ . '/process_order.php' . "  (modified " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/process_order.php')) . ")\n";
echo "payment_proof.php:  " . __DIR__ . '/payment_proof.php' . "  (modified " . date('Y-m-d H:i:s', filemtime(__DIR__ . '/payment_proof.php')) . ")\n";

echo "\n=== What PRIVATE_STORAGE_DIR resolves to right now ===\n";
echo "PRIVATE_STORAGE_DIR = " . (defined('PRIVATE_STORAGE_DIR') ? PRIVATE_STORAGE_DIR : 'NOT DEFINED') . "\n";
if (defined('PRIVATE_STORAGE_DIR')) {
    echo "Exists?    " . (is_dir(PRIVATE_STORAGE_DIR) ? 'yes' : 'no') . "\n";
    echo "Writable?  " . (is_dir(PRIVATE_STORAGE_DIR) && is_writable(PRIVATE_STORAGE_DIR) ? 'yes' : 'no') . "\n";
    echo "Real path: " . (realpath(PRIVATE_STORAGE_DIR) ?: '(does not exist yet)') . "\n";
}

function list_tree($dir, $prefix = '  ', $depth = 0) {
    if ($depth > 3 || !is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            echo "{$prefix}{$item}/\n";
            list_tree($path, $prefix . '  ', $depth + 1);
        } else {
            echo "{$prefix}{$item}  (" . filesize($path) . " bytes)\n";
        }
    }
}

echo "\n=== Everything under public_html/storage (if it exists) ===\n";
$storage_root = dirname(__DIR__, 2) . '/storage';
if (is_dir($storage_root)) {
    echo "Found at: $storage_root\n";
    list_tree($storage_root);
} else {
    echo "No 'storage' folder directly inside public_html.\n";
}

echo "\n=== Everything under the old public upload path (if it exists) ===\n";
$old_uploads = dirname(__DIR__, 2) . '/assets/uploads/payments';
if (is_dir($old_uploads)) {
    echo "Found at: $old_uploads\n";
    list_tree($old_uploads);
} else {
    echo "Not found at $old_uploads\n";
}

echo "\n=== Last 5 orders with a payment proof, per the database ===\n";
$res = $inventory->query("SELECT order_id, user_id, payment_proof, created_at FROM orders WHERE payment_proof IS NOT NULL AND payment_proof != '' ORDER BY order_id DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "order_id={$row['order_id']}  user_id={$row['user_id']}  file='{$row['payment_proof']}'  placed={$row['created_at']}\n";
        // Show whether payment_proof_path() (the function admin pages use) can actually find it
        if (function_exists('payment_proof_path')) {
            $found = payment_proof_path((int) $row['user_id'], (string) $row['payment_proof']);
            echo "    payment_proof_path() finds it at: " . ($found ?: 'NOWHERE — this is why the image is broken') . "\n";
        }
    }
} else {
    echo "No orders with a payment proof found.\n";
}

echo "\n=== Done. DELETE THIS FILE NOW. ===\n";