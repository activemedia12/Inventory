<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once '../config/db.php';
require_once 'delivery_group_render.php';
require_once 'delivery_data.php';

const DELIVERY_GROUPS_PER_PAGE = 15;

$history_param = $_GET['history'] ?? '60';
$history_is_all = ($history_param === 'all');
$history_days = $history_is_all ? null : max(1, intval($history_param));

$date_filter_sql = $history_is_all ? '' : "AND dl.delivery_date >= DATE_SUB(CURDATE(), INTERVAL {$history_days} DAY)";
$date_filter_sql_ins = $history_is_all ? '' : "AND idl.delivery_date >= DATE_SUB(CURDATE(), INTERVAL {$history_days} DAY)";

$offset = max(0, intval($_GET['offset'] ?? 0));
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

[$page_dates, $has_more] = get_delivery_date_page($inventory, $date_filter_sql, $date_filter_sql_ins, DELIVERY_GROUPS_PER_PAGE, $offset);

$html = '';
if (!empty($page_dates)) {
    $grouped_product_logs = get_product_logs_for_dates($inventory, $page_dates);
    $grouped_insuance_logs = get_insuance_logs_for_dates($inventory, $page_dates);

    foreach ($page_dates as $date) {
        $html .= render_delivery_group(
            $date,
            $grouped_product_logs[$date] ?? [],
            $grouped_insuance_logs[$date] ?? [],
            $is_admin,
            false // dynamically-loaded groups render already-visible, no scroll-reveal
        );
    }
}

header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'has_more' => $has_more,
    'count' => count($page_dates),
]);
$inventory->close();