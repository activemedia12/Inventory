<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: ../accounts/login.php");
    exit;
}

// This page has been merged into admin_orders.php, which now shows order
// details in a slide-over panel instead of a separate page. Old links /
// bookmarks to this file are redirected straight into that panel.
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($order_id > 0) {
    header("Location: admin_orders.php?open=" . $order_id);
} else {
    header("Location: admin_orders.php");
}
exit;