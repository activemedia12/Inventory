<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo 'Not authenticated';
    exit;
}

require_once '../config/db.php';
require_once 'papers_data.php';
require_once 'papers_table_render.php';

$stock_unit  = ($_GET['stock_unit'] ?? 'reams') === 'sheets' ? 'sheets' : 'reams';
$type_filter = trim($_GET['product_type'] ?? '');
$size_filter = trim($_GET['product_group'] ?? '');
$name_filter = trim($_GET['product_name'] ?? '');

$products = get_filtered_papers($inventory, $type_filter, $size_filter, $name_filter);
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

header('Content-Type: text/html; charset=UTF-8');
echo render_papers_table($products, $stock_unit, $is_admin);

$inventory->close();