<?php
// This page has been merged into admin_orders.php, which now shows order
// details in a slide-over panel. Old links and bookmarks to this file are
// redirected straight into that panel. admin_orders.php does the login and
// role check, so nothing is needed here.
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

header('Location: admin_orders.php' . ($order_id > 0 ? '?open=' . $order_id : ''));
exit;