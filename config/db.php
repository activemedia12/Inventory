<?php

// Make every mysqli error throw an exception (instead of failing silently).
// This is what lets try/catch + rollback in process_order.php actually work.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$user = 'root';
$password = '';

$inventory = new mysqli($host, $user, $password, 'inventory');

if ($inventory->connect_error) {
    die('Connection failed');
}

$inventory->set_charset("utf8mb4");
$inventory->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

// Where payment proofs and other private files are stored.
// Point this OUTSIDE your web root if you can, e.g. one folder above it:
//   define('PRIVATE_STORAGE_DIR', dirname($_SERVER['DOCUMENT_ROOT']) . '/private_storage');
// The default below (storage/private next to config/) is protected by an
// .htaccess deny-all, but truly outside the web root is safer still.
if (!defined('PRIVATE_STORAGE_DIR')) {
    define('PRIVATE_STORAGE_DIR', dirname(__DIR__) . '/storage/private');
}

require_once __DIR__ . '/security.php';