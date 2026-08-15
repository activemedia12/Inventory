<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$job_id = intval($_GET['id'] ?? 0);
if (!$job_id) {
    header("Location: job_orders.php");
    exit;
}

// ── Create digital_printing_prices table if not exists ──────────────
$inventory->query("
    CREATE TABLE IF NOT EXISTS digital_printing_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        paper_type VARCHAR(50) NOT NULL,
        color_mode VARCHAR(20) NOT NULL,
        size_label VARCHAR(100) NOT NULL,
        content_type VARCHAR(50) DEFAULT NULL,
        price_per_paper DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        UNIQUE KEY unique_combo (paper_type, color_mode, size_label, content_type)
    )
");

// ── Create digital job settings table ──────────────────────────────
$inventory->query("
    CREATE TABLE IF NOT EXISTS digital_job_settings (
        job_id INT PRIMARY KEY,
        paper_type VARCHAR(50) DEFAULT 'bond',
        color_mode VARCHAR(20) DEFAULT 'colored',
        size_label VARCHAR(100) DEFAULT NULL,
        content_type VARCHAR(50) DEFAULT NULL,
        back_to_back TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// ── AJAX: save digital settings ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_digital'])) {
    $dpt  = $_POST['d_paper_type']   ?? 'bond';
    $dcm  = $_POST['d_color_mode']   ?? 'colored';
    $dsl  = $_POST['d_size_label']   ?? null;
    $dct  = $_POST['d_content_type'] ?? null;
    $dbtb = intval($_POST['d_back_to_back'] ?? 0);

    $stmt = $inventory->prepare("
        INSERT INTO digital_job_settings (job_id, paper_type, color_mode, size_label, content_type, back_to_back)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            paper_type=VALUES(paper_type), color_mode=VALUES(color_mode),
            size_label=VALUES(size_label), content_type=VALUES(content_type),
            back_to_back=VALUES(back_to_back)
    ");
    $stmt->bind_param("issssi", $job_id, $dpt, $dcm, $dsl, $dct, $dbtb);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

// ── Load saved digital settings for this job ────────────────────────
$savedDigital = $inventory->query("SELECT * FROM digital_job_settings WHERE job_id = $job_id")->fetch_assoc();
$savedDigital = $savedDigital ?: [
    'paper_type'   => 'bond',
    'color_mode'   => 'colored',
    'size_label'   => null,
    'content_type' => null,
    'back_to_back' => 0,
];

// ── Create riso_printing_prices table ──────────────────────────────
$inventory->query("
    CREATE TABLE IF NOT EXISTS riso_printing_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        paper_name VARCHAR(100) NOT NULL,
        size_label VARCHAR(20) NOT NULL,
        price_per_ream DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        UNIQUE KEY unique_combo (paper_name, size_label)
    )
");

// ── Seed riso defaults ─────────────────────────────────────────────
$risoCount = $inventory->query("SELECT COUNT(*) as c FROM riso_printing_prices")->fetch_assoc()['c'];
if ($risoCount == 0) {
    $risoDefaults = [
        ['Book Paper #50-70 GSM', 'short', 550.00],
        ['Book Paper #50-70 GSM', 'long',  650.00],
        ['Book Paper #50-70 GSM', 'a4',    620.00],
        ['Bond Paper 50 GSM',     'short', 500.00],
        ['Bond Paper 50 GSM',     'long',  550.00],
        ['Newsprint White 52 GSM', 'short', 450.00],
        ['Newsprint White 52 GSM', 'long',  500.00],
        ['Newsprint White 48.8 GSM', 'short', 400.00],
        ['Newsprint White 48.8 GSM', 'long', 450.00],
    ];
    $risoIns = $inventory->prepare("INSERT IGNORE INTO riso_printing_prices (paper_name,size_label,price_per_ream) VALUES (?,?,?)");
    foreach ($risoDefaults as $rd) {
        $risoIns->bind_param("ssd", $rd[0], $rd[1], $rd[2]);
        $risoIns->execute();
    }
    $risoIns->close();
}

// ── Create riso job settings table ────────────────────────────────
$inventory->query("
    CREATE TABLE IF NOT EXISTS riso_job_settings (
        job_id INT PRIMARY KEY,
        paper_name VARCHAR(100) DEFAULT NULL,
        size_label VARCHAR(20) DEFAULT NULL,
        back_to_back TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// ── AJAX: save riso settings ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_riso'])) {
    $rpn  = $_POST['r_paper_name']   ?? null;
    $rsl  = $_POST['r_size_label']   ?? null;
    $rbtb = intval($_POST['r_back_to_back'] ?? 0);
    $stmt = $inventory->prepare("
        INSERT INTO riso_job_settings (job_id, paper_name, size_label, back_to_back)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            paper_name=VALUES(paper_name), size_label=VALUES(size_label),
            back_to_back=VALUES(back_to_back)
    ");
    $stmt->bind_param("issi", $job_id, $rpn, $rsl, $rbtb);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => true]);
    exit;
}

// ── Load saved riso settings ───────────────────────────────────────
$savedRiso = $inventory->query("SELECT * FROM riso_job_settings WHERE job_id = $job_id")->fetch_assoc();
$savedRiso = $savedRiso ?: ['paper_name' => null, 'size_label' => null, 'back_to_back' => 0];

// ── Fetch riso prices ──────────────────────────────────────────────
$riso_rows = $inventory->query("SELECT * FROM riso_printing_prices ORDER BY paper_name, size_label")->fetch_all(MYSQLI_ASSOC);
$riso_prices = [];
foreach ($riso_rows as $rr) {
    $riso_prices[$rr['paper_name']][$rr['size_label']] = ['id' => $rr['id'], 'price' => $rr['price_per_ream']];
}
$js_riso_prices = json_encode($riso_prices);

// ── Seed default digital prices if table is empty ──────────────────
$count = $inventory->query("SELECT COUNT(*) as c FROM digital_printing_prices")->fetch_assoc()['c'];
if ($count == 0) {
    $defaults = [
        // Bond Paper - B&W
        ['bond', 'bw', 'short', 'text_only', 5.00],
        ['bond', 'bw', 'short', 'image_text', 7.00],
        ['bond', 'bw', 'short', 'image_only', 10.00],
        ['bond', 'bw', 'long', 'text_only', 7.00],
        ['bond', 'bw', 'long', 'image_text', 9.00],
        ['bond', 'bw', 'long', 'image_only', 13.00],
        // Bond Paper - Colored
        ['bond', 'colored', 'short', 'text_only', 15.00],
        ['bond', 'colored', 'short', 'image_text', 25.00],
        ['bond', 'colored', 'short', 'image_only', 40.00],
        ['bond', 'colored', 'long', 'text_only', 20.00],
        ['bond', 'colored', 'long', 'image_text', 30.00],
        ['bond', 'colored', 'long', 'image_only', 50.00],
        // Photo Paper - Colored
        ['photo', 'colored', '3R size & wallet (2/3pcs)', NULL, 50.00],
        ['photo', 'colored', '4R size or 4x6 in (2pcs)', NULL, 50.00],
        ['photo', 'colored', '5R size or 5x7 in (1pc)', NULL, 50.00],
        ['photo', 'colored', '6R size or 6x8 in (1pc)', NULL, 50.00],
        ['photo', 'colored', 'A4 size', NULL, 75.00],
        // Photo Paper - B&W
        ['photo', 'bw', '3R size & wallet (2/3pcs)', NULL, 15.00],
        ['photo', 'bw', '4R size or 4x6 in (2pcs)', NULL, 15.00],
        ['photo', 'bw', '5R size or 5x7 in (1pc)', NULL, 15.00],
        ['photo', 'bw', '6R size or 6x8 in (1pc)', NULL, 15.00],
        ['photo', 'bw', 'A4 size', NULL, 30.00],
        // C2S Glossy - Colored
        ['glossy', 'colored', 'A4 * 8.5x11 * 8.5x13 (70/80GSM)', NULL, 40.00],
        ['glossy', 'colored', 'A4 * 8.5x11 * 8.5x13 (100/120GSM)', NULL, 40.00],
        ['glossy', 'colored', 'A3, 12x18 UP (130/220GSM)', NULL, 80.00],
        ['glossy', 'colored', 'A3, 12x18 UP (250/300GSM)', NULL, 90.00],
        // C2S Glossy - B&W
        ['glossy', 'bw', 'A4 * 8.5x11 * 8.5x13 (70/80GSM)', NULL, 10.00],
        ['glossy', 'bw', 'A4 * 8.5x11 * 8.5x13 (100/120GSM)', NULL, 15.00],
        ['glossy', 'bw', 'A3, 12x18 UP (130/220GSM)', NULL, 25.00],
        ['glossy', 'bw', 'A3, 12x18 UP (250/300GSM)', NULL, 35.00],
        // Sticker - Colored
        ['sticker', 'colored', 'A4 * 8.5x11 * 8.5x13', NULL, 50.00],
        ['sticker', 'colored', 'A3, 12x18 UP', NULL, 100.00],
        // Sticker - B&W
        ['sticker', 'bw', 'A4 * 8.5x11 * 8.5x13', NULL, 20.00],
        ['sticker', 'bw', 'A3, 12x18 UP', NULL, 50.00],
    ];
    $ins = $inventory->prepare("INSERT IGNORE INTO digital_printing_prices (paper_type,color_mode,size_label,content_type,price_per_paper) VALUES (?,?,?,?,?)");
    foreach ($defaults as $d) {
        $ins->bind_param("ssssd", $d[0], $d[1], $d[2], $d[3], $d[4]);
        $ins->execute();
    }
    $ins->close();
}

// ── Fetch digital prices ───────────────────────────────────────────
$dp_rows = $inventory->query("SELECT * FROM digital_printing_prices ORDER BY paper_type,color_mode,size_label,content_type")->fetch_all(MYSQLI_ASSOC);
$digital_prices = [];
foreach ($dp_rows as $dp) {
    $digital_prices[$dp['paper_type']][$dp['color_mode']][$dp['size_label']][$dp['content_type'] ?? '__'] = ['id' => $dp['id'], 'price' => $dp['price_per_paper']];
}
$js_digital_prices = json_encode($digital_prices);

// ── Create itemized "other expenses" table (book cover, plastic cover,
//    strings, ring, etc. — free-form name + price pairs per job) ──────
$inventory->query("
    CREATE TABLE IF NOT EXISTS job_order_itemized_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        expense_name VARCHAR(150) NOT NULL,
        expense_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sort_order INT DEFAULT 0,
        INDEX idx_job (job_id)
    )
");

// ── AJAX: save itemized other expenses (replaces the full list for this job) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_itemized_expenses'])) {
    $items = json_decode($_POST['items'] ?? '[]', true);
    if (!is_array($items)) $items = [];

    $del = $inventory->prepare("DELETE FROM job_order_itemized_expenses WHERE job_id = ?");
    $del->bind_param("i", $job_id);
    $del->execute();
    $del->close();

    $ins = $inventory->prepare("INSERT INTO job_order_itemized_expenses (job_id, expense_name, expense_price, sort_order) VALUES (?, ?, ?, ?)");
    $sort_order = 0;
    foreach ($items as $item) {
        $ex_name  = trim($item['name'] ?? '');
        $ex_price = floatval($item['price'] ?? 0);
        if ($ex_name === '') continue;
        $ins->bind_param("isdi", $job_id, $ex_name, $ex_price, $sort_order);
        $ins->execute();
        $sort_order++;
    }
    $ins->close();

    echo json_encode(['ok' => true]);
    exit;
}

// ── Load saved itemized other expenses ────────────────────────────────
$savedItemizedExpenses = [];
$ie_stmt = $inventory->prepare("SELECT expense_name, expense_price FROM job_order_itemized_expenses WHERE job_id = ? ORDER BY sort_order ASC, id ASC");
$ie_stmt->bind_param("i", $job_id);
$ie_stmt->execute();
$ie_result = $ie_stmt->get_result();
while ($row = $ie_result->fetch_assoc()) {
    $savedItemizedExpenses[] = ['name' => $row['expense_name'], 'price' => (float)$row['expense_price']];
}
$ie_stmt->close();
$js_itemized_expenses = json_encode($savedItemizedExpenses);

// ── Manpower rates ──────────────────────────────────────────────────
$rates = [];
$res = $inventory->query("SELECT task_name, hourly_rate FROM manpower_rates");
while ($row = $res->fetch_assoc()) {
    $rates[$row['task_name']] = $row['hourly_rate'];
}
$tasks = array_keys($rates);

// ── Job order data ──────────────────────────────────────────────────
$sql = "SELECT log_date, client_name, project_name, quantity, number_of_sets,
               product_size, paper_size, paper_type, paper_sequence,
               printing_type, other_expenses, paper_spoilage,
               paper_pricing_method, custom_paper_cost
        FROM job_orders WHERE id = ?";
$stmt = $inventory->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: job_orders.php");
    exit;
}

// ── Existing sessions ───────────────────────────────────────────────
$sessions_by_task = [];
$res2 = $inventory->prepare("SELECT * FROM job_sessions WHERE job_id = ? ORDER BY id ASC");
$res2->bind_param("i", $job_id);
$res2->execute();
$result2 = $res2->get_result();
while ($row = $result2->fetch_assoc()) {
    $sessions_by_task[$row['task_name']][] = $row;
}
$res2->close();

$client_name  = $order['client_name'];
$project_name = $order['project_name'];
$log_date     = $order['log_date'];

// ── Paper cost computation ──────────────────────────────────────────
$quantity       = $order['quantity'];
$number_of_sets = $order['number_of_sets'];
$product_size   = $order['product_size'];
$paper_size     = strtolower(trim($order['paper_size']));
$paper_type     = strtolower(trim($order['paper_type']));
$paper_sequence = array_map('trim', explode(',', $order['paper_sequence']));

$cut_size_map = [
    '1/2' => 2,
    '1/3' => 3,
    '1/4' => 4,
    '1/6' => 6,
    '1/8' => 8,
    '1/10' => 10,
    '1/12' => 12,
    '1/14' => 14,
    '1/16' => 16,
    '1/18' => 18,
    '1/20' => 20,
    '1/22' => 22,
    '1/24' => 24,
    '1/25' => 25,
    '1/26' => 26,
    '1/28' => 28,
    '1/30' => 30,
    '1/32' => 32,
    '1/36' => 36,
    '1/40' => 40,
    '1/48' => 48,
    '1/50' => 50,
    'whole' => 1,
];
$cut_size     = $cut_size_map[$product_size] ?? 1;
$total_sheets = $number_of_sets * $quantity;
$cut_sheets   = ($cut_size > 0) ? ($total_sheets / $cut_size) : 0;
$reams        = $cut_sheets / 500;

// ── Printing types ──────────────────────────────────────────────────
$printing_types = [];
$res3 = $inventory->query("SELECT * FROM printing_types ORDER BY name ASC");
while ($row = $res3->fetch_assoc()) {
    $printing_types[$row['name']] = $row;
}
$js_printing = json_encode($printing_types);

// ── Map paper color to DB type ──────────────────────────────────────
function mapPaperType($color, $paper_type)
{
    $c = strtolower($color);
    if ($paper_type === 'carbonless') {
        if (strpos($c, 'top') !== false)    return 'TOP WHITE';
        if (strpos($c, 'middle') !== false) return 'MIDDLE';
        if (strpos($c, 'bottom') !== false) return 'BOTTOM';
    } elseif ($paper_type === 'special paper') {
        return strtoupper($color);
    } else {
        if (strpos($c, 'white') !== false) return 'WHITE';
        return 'COLORED';
    }
    return strtoupper($color);
}

// ── Fetch paper prices ─────────────────────────────────────────────
$layer_data = [];
$total_paper_cost_ream = 0.0;

if ($paper_type === 'carbonless') {
    $unique_types = array_unique(array_map(fn($c) => mapPaperType($c, $paper_type), $paper_sequence));
    $placeholders = implode(',', array_fill(0, count($unique_types), '?'));
    $price_stmt = $inventory->prepare("SELECT paper_type, short_price, long_price, price_per_sheet FROM paper_prices WHERE paper_type IN ($placeholders) ORDER BY effective_date DESC");
    $types_str = str_repeat('s', count($unique_types));
    $price_stmt->bind_param($types_str, ...array_values($unique_types));
    $price_stmt->execute();
    $price_rows = $price_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $price_stmt->close();
    $price_map = [];
    foreach ($price_rows as $p) {
        if (!isset($price_map[$p['paper_type']])) $price_map[$p['paper_type']] = $p;
    }
    foreach ($paper_sequence as $color) {
        $mappedType = mapPaperType($color, $paper_type);
        $price = $price_map[$mappedType] ?? null;
        if ($price) {
            $unit_price = determineSizePrice($price, $paper_size);
            $price_per_sheet = $price['price_per_sheet'] ?? ($unit_price / 500);
            $layer_cost_ream = $unit_price * $reams;
            $total_paper_cost_ream += $layer_cost_ream;
            $layer_data[] = buildLayerData($color, $mappedType, $unit_price, $price_per_sheet, $reams, $layer_cost_ream, $total_sheets);
        }
    }
} elseif ($paper_type === 'special paper') {
    $special_products = $inventory->query("SELECT product_name, product_group, unit_price FROM products WHERE LOWER(product_type) = 'special paper'")->fetch_all(MYSQLI_ASSOC);
    $special_product_map = [];
    foreach ($special_products as $p) {
        $key = strtolower(trim($p['product_name']));
        if (!isset($special_product_map[$key])) $special_product_map[$key] = $p;
    }
    foreach ($paper_sequence as $color) {
        $key = strtolower(trim($color));
        $product = $special_product_map[$key] ?? null;
        if ($product) {
            $pps = (float)$product['unit_price'];
            $layer_cost = $pps * $cut_sheets;
            $total_paper_cost_ream += $layer_cost;
            $layer = buildLayerData($color, $product['product_name'], 0, $pps, $reams, $layer_cost, $total_sheets);
            $layer['is_special'] = true;
            $layer_data[] = $layer;
        }
    }
} else {
    $ordinary_products = $inventory->query("SELECT product_name, product_group, unit_price FROM products WHERE LOWER(product_type) = 'ordinary paper'")->fetch_all(MYSQLI_ASSOC);
    $ordinary_product_map = [];
    foreach ($ordinary_products as $p) {
        $key = strtolower(trim($p['product_name']));
        if (!isset($ordinary_product_map[$key])) $ordinary_product_map[$key] = $p;
    }
    foreach ($paper_sequence as $color) {
        $key = strtolower(trim($color));
        $product = $ordinary_product_map[$key] ?? null;
        if ($product) {
            $unit_price = (float)$product['unit_price'];
            $layer_cost_ream = $unit_price * $reams;
            $price_per_sheet = $reams > 0 ? ($unit_price / 500) : 0;
            $total_paper_cost_ream += $layer_cost_ream;
            $layer = buildLayerData($color, $product['product_name'], $unit_price, $price_per_sheet, $reams, $layer_cost_ream, $total_sheets);
            $layer['is_ordinary_product'] = true;
            $layer_data[] = $layer;
        }
    }
}

function determineSizePrice($price, $paper_size)
{
    if (strpos($paper_size, 'long') !== false || strpos($paper_size, 'f4') !== false) return (float)$price['long_price'];
    elseif (strpos($paper_size, 'short') !== false || strpos($paper_size, 'qto') !== false) return (float)$price['short_price'];
    elseif ($paper_size === '11x17') return (float)$price['short_price'] * 2;
    return (float)$price['long_price'];
}

function buildLayerData($color, $mapped, $unit_price, $price_per_sheet, $reams, $layer_cost, $total_sheets)
{
    return [
        'color'           => $color,
        'mapped'          => $mapped,
        'unit_price'      => (float)$unit_price,
        'price_per_sheet' => (float)$price_per_sheet,
        'reams'           => (float)$reams,
        'cost_ream'       => (float)$layer_cost,
        'total_sheets'    => (float)$total_sheets,
    ];
}

$js_rates       = json_encode($rates);
$js_layer_data  = json_encode($layer_data);
$js_cut_sheets  = $cut_sheets;
$js_total_sheets = $total_sheets;
$js_reams       = $reams;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Job Order Cost Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1877f2;
            --primary-dark: #0f5cbf;
            --primary-light: #e8f0fe;
            --secondary: #166fe5;
            --success: #1aad4b;
            --danger: #e53935;
            --warning: #f59e0b;
            --bg: #f0f4fc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --shadow: 0 2px 12px rgba(24, 119, 242, 0.08);
            --shadow-lg: 0 8px 32px rgba(24, 119, 242, 0.13);
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1877f240;
            border-radius: 10px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: var(--bg);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: var(--text);
            padding: 0 0 60px;
        }

        /* ── Topbar ── */
        .topbar {
            background: var(--card-bg);
            border-bottom: 2px solid var(--primary-light);
            padding: 14px 0;
            margin-bottom: 28px;
            box-shadow: var(--shadow);
        }

        .topbar .container {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .topbar-sub {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--primary);
            border: 1.5px solid var(--primary);
            background: transparent;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: var(--primary);
            color: #fff;
        }

        /* ── Cards ── */
        .card {
            background: var(--card-bg);
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
            padding: 14px 22px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: none;
        }

        .card-header .header-icon {
            width: 28px;
            height: 28px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .card-body {
            padding: 20px;
        }

        /* ── Summary Card ── */
        .summary-card {
            background: linear-gradient(135deg, #1877f2 0%, #0f4c9e 100%);
            border-radius: 14px;
            padding: 22px;
            color: #fff;
            margin-bottom: 20px;
            box-shadow: 0 6px 24px rgba(24, 119, 242, 0.3);
        }

        .summary-card h5 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.75;
            margin-bottom: 14px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .summary-item .label {
            font-size: 0.85rem;
            opacity: 0.85;
        }

        .summary-item .value {
            font-weight: 700;
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 12px;
            border-radius: 20px;
        }

        .summary-divider {
            border-color: rgba(255, 255, 255, 0.25);
            margin: 12px 0;
        }

        .summary-total {
            font-size: 1.2rem !important;
            background: rgba(255, 255, 255, 0.95) !important;
            color: var(--primary) !important;
        }

        /* ── Form controls ── */
        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 13.5px;
            padding: 9px 13px;
            transition: all 0.2s;
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.12);
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .form-check-label {
            font-size: 13px;
            font-weight: 500;
        }

        /* ── Buttons ── */
        .btn-primary-solid {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 11px 22px;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(24, 119, 242, 0.3);
            width: 100%;
            justify-content: center;
        }

        .btn-primary-solid:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(24, 119, 242, 0.4);
        }

        .btn-outline-primary-custom {
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 10px;
            padding: 10px 18px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            width: 100%;
            justify-content: center;
        }

        .btn-outline-primary-custom:hover {
            background: var(--primary);
            color: #fff;
        }

        .btn-danger-sm {
            background: transparent;
            color: var(--danger);
            border: 1.5px solid var(--danger);
            border-radius: 7px;
            padding: 5px 11px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-danger-sm:hover {
            background: var(--danger);
            color: #fff;
        }

        .btn-add-session {
            background: #e8f5ee;
            color: var(--success);
            border: 1.5px solid #a7d9b5;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 12.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-add-session:hover {
            background: var(--success);
            color: #fff;
            border-color: var(--success);
        }

        /* ── Tab Navigation ── */
        .task-tabs {
            border: none;
            gap: 4px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .task-tabs .nav-link {
            border: 1.5px solid var(--border) !important;
            border-radius: 9px !important;
            color: var(--text-muted);
            font-size: 12.5px;
            font-weight: 500;
            padding: 7px 15px;
            background: #fff;
            transition: all 0.2s;
        }

        .task-tabs .nav-link.active {
            background: var(--primary) !important;
            color: #fff !important;
            border-color: var(--primary) !important;
            font-weight: 600;
        }

        .task-tabs .nav-link:hover:not(.active) {
            border-color: var(--primary) !important;
            color: var(--primary);
        }

        /* ── Session rows ── */
        .session-row {
            background: var(--bg);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
        }

        .task-info-bar {
            background: var(--primary-light);
            border-radius: 9px;
            padding: 10px 16px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .task-info-bar .task-name {
            font-weight: 700;
            color: var(--primary);
            font-size: 13.5px;
        }

        .task-info-bar .task-rate {
            font-size: 12px;
            color: var(--text-muted);
            background: #fff;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* ── Tables ── */
        .data-table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .data-table thead th {
            background: var(--primary);
            color: #fff;
            padding: 12px 16px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table thead th:first-child {
            border-radius: 0;
        }

        .data-table tbody tr {
            transition: background 0.15s;
        }

        .data-table tbody tr:hover {
            background: var(--primary-light);
        }

        .data-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
        }

        .data-table .total-row td {
            background: #f0f4fc;
            font-weight: 700;
            font-size: 13.5px;
            border-bottom: none;
        }

        .data-table .grand-row td {
            font-size: 1.05rem;
            color: var(--success);
            border-bottom: none;
        }

        /* ── Paper layer ── */
        .paper-layer {
            background: var(--bg);
            border-left: 4px solid var(--primary);
            border-radius: 0 9px 9px 0;
            padding: 12px 16px;
            margin-bottom: 12px;
        }

        .paper-layer .layer-title {
            font-weight: 600;
            font-size: 13.5px;
            color: var(--text);
        }

        .paper-layer .layer-type {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .paper-layer .layer-cost {
            font-weight: 700;
            color: var(--primary);
        }

        .paper-total-bar {
            background: var(--primary-light);
            border-radius: 9px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            margin-top: 6px;
        }

        /* ── Job details ── */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .detail-item {
            background: var(--bg);
            border-radius: 9px;
            padding: 10px 14px;
        }

        .detail-item .dl {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .detail-item .dv {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-top: 2px;
        }

        /* ── Digital Printing section ── */
        #digital-section {
            display: none;
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 18px;
            margin-top: 14px;
            background: #f5f8ff;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .digital-header {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Color mode toggle */
        .color-toggle {
            display: flex;
            gap: 0;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            overflow: hidden;
            margin-bottom: 14px;
        }

        .color-toggle label {
            flex: 1;
            text-align: center;
            padding: 9px 12px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            background: #fff;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .color-toggle input[type="radio"] {
            display: none;
        }

        .color-toggle input:checked+label {
            background: var(--primary);
            color: #fff;
            font-weight: 600;
        }

        .color-toggle .divider {
            width: 1px;
            background: var(--border);
        }

        /* Paper type tabs */
        .paper-type-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .paper-type-btn {
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 500;
            border: 1.5px solid var(--border);
            background: #fff;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .paper-type-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            font-weight: 600;
        }

        .paper-type-btn:hover:not(.active) {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Digital price options */
        .digital-options {
            display: none;
        }

        .digital-options.visible {
            display: block;
        }

        .price-option-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            padding: 10px 14px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            gap: 10px;
        }

        .price-option-row:hover,
        .price-option-row.selected {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .price-option-row.selected .option-radio {
            color: var(--primary);
        }

        .option-label {
            font-size: 13px;
            font-weight: 500;
            flex: 1;
        }

        .option-price-input {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #f8faff;
            border: 1.5px solid var(--border);
            border-radius: 7px;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        .option-price-input input {
            border: none;
            background: transparent;
            width: 70px;
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
            text-align: right;
            outline: none;
            font-family: 'Poppins', sans-serif;
        }

        /* Back to back */
        .back-to-back-row {
            background: #fff7ed;
            border: 1.5px solid #fcd34d;
            border-radius: 9px;
            padding: 10px 14px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-to-back-row label {
            font-size: 13px;
            font-weight: 500;
            color: #92400e;
            margin: 0;
            cursor: pointer;
        }

        /* Digital cost display */
        .digital-cost-preview {
            background: linear-gradient(135deg, #1877f2 0%, #0f4c9e 100%);
            color: #fff;
            border-radius: 10px;
            padding: 12px 16px;
            margin-top: 12px;
            display: none;
        }

        .digital-cost-preview .dcp-label {
            font-size: 11px;
            opacity: 0.75;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .digital-cost-preview .dcp-value {
            font-size: 1.4rem;
            font-weight: 700;
        }

        /* Sections badge */
        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Recalc button */
        .btn-recalc {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 7px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-recalc:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .detail-grid {
                grid-template-columns: 1fr;
            }

            body {
                padding: 12px 0 40px;
            }
        }

        .riso-option-row:hover,
        .riso-option-row.selected {
            background: #fff3e0 !important;
        }

        .riso-option-row.selected .riso-radio {
            color: #e67e22 !important;
        }
    </style>
    <script>
        const rates = <?= $js_rates ?>;
        const layerData = <?= $js_layer_data ?>;
        const printingTypes = <?= $js_printing ?>;
        const cutSheets = <?= $js_cut_sheets ?>;
        const totalSheets = <?= $js_total_sheets ?>;
        const reams = <?= $js_reams ?>;
        const isSpecialPaper = <?= ($paper_type === 'special paper') ? 'true' : 'false' ?>;
        const digitalPrices = <?= $js_digital_prices ?>;
        const savedDigital = <?= json_encode($savedDigital) ?>;
        const risoPrices = <?= $js_riso_prices ?>;
        const savedRiso = <?= json_encode($savedRiso) ?>;
        const RISO_BTB_SURCHARGE = 200;

        let paperCost = 0;

        // ── Itemized "other expenses" state (book cover, plastic cover, strings, ring, etc.) ──
        let itemizedExpenses = <?= $js_itemized_expenses ?>; // [{name, price}, ...]
        let itemizedSaveTimeout = null;

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function renderItemizedExpenses() {
            const container = document.getElementById('itemized_expenses_list');
            if (!container) return;
            container.innerHTML = '';
            if (itemizedExpenses.length === 0) {
                container.innerHTML = '<div style="font-size:12px;color:var(--text-muted);padding:2px 0 6px">No additional expenses added yet.</div>';
                return;
            }
            itemizedExpenses.forEach((exp, idx) => {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px';
                row.innerHTML = `
                    <input type="text" class="form-control form-control-sm" placeholder="Expense name"
                        value="${escapeHtml(exp.name)}" style="flex:2"
                        oninput="updateItemizedExpense(${idx}, 'name', this.value)">
                    <div style="display:flex;align-items:center;gap:4px;flex:1;min-width:110px">
                        <span style="font-size:12px;color:var(--text-muted)">₱</span>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                            value="${exp.price}" style="width:100%"
                            oninput="updateItemizedExpense(${idx}, 'price', this.value)">
                    </div>
                    <button type="button" class="btn-danger-sm" title="Remove"
                        onclick="removeItemizedExpense(${idx})"><i class="bi bi-trash"></i></button>
                `;
                container.appendChild(row);
            });
        }

        function addItemizedExpense() {
            itemizedExpenses.push({ name: '', price: 0 });
            renderItemizedExpenses();
            saveItemizedExpenses();
            calculate();
        }

        function updateItemizedExpense(idx, field, value) {
            if (!itemizedExpenses[idx]) return;
            itemizedExpenses[idx][field] = field === 'price' ? (parseFloat(value) || 0) : value;
            saveItemizedExpenses();
            calculate();
        }

        function removeItemizedExpense(idx) {
            itemizedExpenses.splice(idx, 1);
            renderItemizedExpenses();
            saveItemizedExpenses();
            calculate();
        }

        function getItemizedExpensesTotal() {
            return itemizedExpenses.reduce((sum, e) => sum + (parseFloat(e.price) || 0), 0);
        }

        function saveItemizedExpenses() {
            clearTimeout(itemizedSaveTimeout);
            itemizedSaveTimeout = setTimeout(() => {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'ajax_save_itemized_expenses=1&items=' + encodeURIComponent(JSON.stringify(itemizedExpenses))
                });
            }, 500);
        }

        // ── Digital printing state ──────────────────────────────────
        // Initialise from saved DB state
        let digitalState = {
            paperType: savedDigital.paper_type || 'bond',
            colorMode: savedDigital.color_mode || 'colored',
            selectedKey: savedDigital.size_label || null,
            selectedContent: savedDigital.content_type || null,
            backToBack: savedDigital.back_to_back == 1,
            priceOverride: null,
        };

        // ── AJAX auto-save digital state ─────────────────────────────
        let _saveTimer = null;

        function saveDigitalState() {
            clearTimeout(_saveTimer);
            _saveTimer = setTimeout(() => {
                const form = new FormData();
                form.append('ajax_save_digital', '1');
                form.append('d_paper_type', digitalState.paperType);
                form.append('d_color_mode', digitalState.colorMode);
                form.append('d_size_label', digitalState.selectedKey ?? '');
                form.append('d_content_type', digitalState.selectedContent ?? '');
                form.append('d_back_to_back', digitalState.backToBack ? '1' : '0');
                fetch(window.location.href, {
                        method: 'POST',
                        body: form
                    })
                    .catch(() => {}); // silent — non-critical
            }, 400);
        }

        // ── Restore saved digital UI state ───────────────────────────
        function restoreDigitalState() {
            // Color mode radio
            const cmInput = document.getElementById(
                digitalState.colorMode === 'bw' ? 'cm_bw' : 'cm_colored'
            );
            if (cmInput) cmInput.checked = true;

            // Paper type tab
            document.querySelectorAll('.paper-type-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.pt === digitalState.paperType);
            });

            // Show the right option panel
            renderDigitalOptions();

            // Back-to-back
            const btbCb = document.getElementById('back_to_back');
            if (btbCb) btbCb.checked = digitalState.backToBack;

            // Select the saved row
            if (digitalState.selectedKey) {
                const pt = digitalState.paperType;
                const cm = digitalState.colorMode;
                const sk = digitalState.selectedKey;
                const sc = digitalState.selectedContent || '__';
                // Build the same cssId as PHP renders
                let rawId;
                if (pt === 'bond') {
                    rawId = pt + '_' + cm + '_' + sk + '_' + (sc === '__' ? sc : sc);
                } else {
                    rawId = pt + '_' + cm + '_' + sk + '___';
                }
                const cssInputId = rawId.replace(/[^a-zA-Z0-9]/g, '_');
                const row = document.getElementById('row_' + cssInputId);
                if (row) row.classList.add('selected');
            }
        }

        // ── RISO state ──────────────────────────────────────────────────
        let risoState = {
            paperName: savedRiso.paper_name || null,
            sizeLabel: savedRiso.size_label || null,
            backToBack: savedRiso.back_to_back == 1,
        };

        function saveRisoState() {
            clearTimeout(saveRisoState._t);
            saveRisoState._t = setTimeout(() => {
                const form = new FormData();
                form.append('ajax_save_riso', '1');
                form.append('r_paper_name', risoState.paperName ?? '');
                form.append('r_size_label', risoState.sizeLabel ?? '');
                form.append('r_back_to_back', risoState.backToBack ? '1' : '0');
                fetch(window.location.href, {
                    method: 'POST',
                    body: form
                }).catch(() => {});
            }, 400);
        }

        function getRisoCost() {
            if (!risoState.paperName || !risoState.sizeLabel) return 0;
            const entry = risoPrices[risoState.paperName]?.[risoState.sizeLabel];
            if (!entry) return 0;
            const inputEl = document.getElementById('riso_price_' + risoState.paperName.replace(/[^a-zA-Z0-9]/g, '_') + '_' + risoState.sizeLabel);
            const pricePerReem = inputEl ? parseFloat(inputEl.value) || entry.price : entry.price;
            const totalLayers = <?= count($paper_sequence) ?>;
            const effectiveReams = Math.max(reams * totalLayers, 1); // per layer, minimum 1 ream total
            let cost = pricePerReem * effectiveReams;
            if (risoState.backToBack) cost += RISO_BTB_SURCHARGE;
            return cost;
        }

        function selectRisoOption(paperName, sizeLabel) {
            risoState.paperName = paperName;
            risoState.sizeLabel = sizeLabel;
            // Highlight selected row
            document.querySelectorAll('.riso-option-row').forEach(r => r.classList.remove('selected'));
            const rowId = 'riso_row_' + paperName.replace(/[^a-zA-Z0-9]/g, '_') + '_' + sizeLabel;
            const row = document.getElementById(rowId);
            if (row) row.classList.add('selected');
            // Update size button active states inside the paper group
            document.querySelectorAll('.riso-size-btn').forEach(b => {
                b.classList.toggle('active',
                    b.dataset.paper === paperName && b.dataset.size === sizeLabel);
            });
            saveRisoState();
            calculate();
        }

        function updateRisoBackToBack() {
            risoState.backToBack = document.getElementById('riso_back_to_back')?.checked || false;
            saveRisoState();
            calculate();
        }

        function restoreRisoState() {
            if (risoState.paperName && risoState.sizeLabel) {
                selectRisoOption(risoState.paperName, risoState.sizeLabel);
            }
            const cb = document.getElementById('riso_back_to_back');
            if (cb) cb.checked = risoState.backToBack;
        }

        function initPaperPricing() {
            const methodRow = document.getElementById('paper_method_row');
            const sel = document.getElementById('paper_pricing_method');
            const sec = document.getElementById('custom_paper_section');
            if (isSpecialPaper) {
                if (methodRow) methodRow.style.display = 'none';
                if (sec) sec.style.display = 'none';
            } else {
                if (!sel) return;
                sec.style.display = sel.value === 'custom' ? 'block' : 'none';
                sel.addEventListener('change', function() {
                    sec.style.display = this.value === 'custom' ? 'block' : 'none';
                    calculate();
                });
            }
        }

        function calculatePaperCost() {
            if (isSpecialPaper) {
                let total = 0;
                layerData.forEach(l => {
                    total += l.price_per_sheet * cutSheets;
                });
                return total;
            }
            const method = document.getElementById('paper_pricing_method')?.value || 'ream';
            let total = 0;
            switch (method) {
                case 'piece':
                    layerData.forEach(l => {
                        const pps = l.price_per_sheet > 0 ? l.price_per_sheet : (l.unit_price / 500);
                        total += pps * cutSheets;
                    });
                    break;
                case 'custom':
                    total = parseFloat(document.getElementById('custom_paper_cost')?.value) || 0;
                    break;
                default:
                    layerData.forEach(l => {
                        total += l.unit_price * reams;
                    });
            }
            return total;
        }

        function getDigitalCost() {
            const printingChoice = document.getElementById('printing_type')?.value || '';
            if (!printingChoice.toUpperCase().includes('DIGITAL')) return 0;

            const pt = digitalState.paperType;
            const cm = digitalState.colorMode;
            const sk = digitalState.selectedKey;
            const sc = digitalState.selectedContent;

            if (!pt || !cm || !sk) return 0;

            const contentKey = sc || '__';
            let basePrice = 0;

            if (digitalPrices[pt] && digitalPrices[pt][cm] && digitalPrices[pt][cm][sk]) {
                const entry = digitalPrices[pt][cm][sk][contentKey];
                if (entry) {
                    // Check if user overrode the price
                    const inputEl = document.getElementById(`price_input_${cssId(pt+'_'+cm+'_'+sk+'_'+contentKey)}`);
                    basePrice = inputEl ? parseFloat(inputEl.value) || entry.price : entry.price;
                }
            }

            if (basePrice <= 0) return 0;

            const totalLayers = <?= count($paper_sequence) ?>;
            const multiplier = digitalState.backToBack ? 2 : 1;
            return basePrice * multiplier * cutSheets * totalLayers;
        }

        function cssId(str) {
            return str.replace(/[^a-zA-Z0-9]/g, '_');
        }

        function calculate() {
            paperCost = calculatePaperCost();
            let grandTotal = paperCost;

            const tbody = document.getElementById('results');
            const sessionBody = document.getElementById('session_details');
            tbody.innerHTML = '';
            sessionBody.innerHTML = '';
            const totalLayers = <?= count($paper_sequence) ?>;

            // Labor
            Object.keys(rates).forEach(task => {
                const container = document.getElementById(task + '-sessions');
                if (!container) return;
                let totalHours = 0,
                    totalCost = 0;
                container.querySelectorAll('.session-row').forEach(s => {
                    const start = s.querySelector("[name*='[start]']").value;
                    const end = s.querySelector("[name*='[end]']").value;
                    const brk = parseInt(s.querySelector("[name*='[break]']").value) || 0;
                    if (start && end) {
                        const st = new Date('1970-01-01T' + start + ':00');
                        const en = new Date('1970-01-01T' + end + ':00');
                        if (en > st) {
                            let h = (en - st) / 3600000 - brk / 60;
                            if (h < 0) h = 0;
                            const c = h * rates[task];
                            totalHours += h;
                            totalCost += c;
                            sessionBody.innerHTML += `<tr>
                                <td>${task}</td>
                                <td>${formatTime12h(start)}</td>
                                <td>${formatTime12h(end)}</td>
                                <td>${brk} min</td>
                                <td>${h.toFixed(2)} hrs</td>
                                <td>₱${c.toFixed(2)}</td></tr>`;
                        }
                    }
                });
                if (totalHours > 0) {
                    tbody.innerHTML += `<tr>
                        <td>${task}</td>
                        <td>${totalHours.toFixed(2)} hrs</td>
                        <td>₱${totalCost.toFixed(2)}</td></tr>`;
                    grandTotal += totalCost;
                }
            });

            // Printing (non-digital) or Digital
            const printingChoice = document.getElementById('printing_type')?.value || '';
            let printingCost = 0;
            const isDigital = printingChoice.toUpperCase().includes('DIGITAL');
            const isRiso = printingChoice.toUpperCase().includes('RISO');

            // Hide all special previews first
            const digitalPreview = document.getElementById('digital_cost_preview');
            const risoPreview = document.getElementById('riso_cost_preview');
            if (digitalPreview) digitalPreview.style.display = 'none';
            if (risoPreview) risoPreview.style.display = 'none';

            if (isDigital) {
                printingCost = getDigitalCost();
                if (digitalPreview && printingCost > 0) {
                    const previewVal = document.getElementById('digital_cost_value');
                    digitalPreview.style.display = 'block';
                    const btb = digitalState.backToBack ? ' (back-to-back ×2)' : '';
                    const totalLayers2 = <?= count($paper_sequence) ?>;
                    const sheets = cutSheets * totalLayers2;
                    const layerNote = totalLayers2 > 1 ? ` (${cutSheets} × ${totalLayers2} layers)` : '';
                    if (previewVal) previewVal.innerHTML = `₱${printingCost.toFixed(2)}<br><small style="font-size:11px;opacity:0.8">${sheets} sheets${layerNote}${btb}</small>`;
                }
            } else if (isRiso) {
                printingCost = getRisoCost();
                if (risoPreview && printingCost > 0) {
                    const previewVal = document.getElementById('riso_cost_value');
                    risoPreview.style.display = 'block';
                    const risoLayers = <?= count($paper_sequence) ?>;
                    const effReams = Math.max(reams * risoLayers, 1);
                    const layerNote = risoLayers > 1 ? ` (${reams.toFixed(2)} × ${risoLayers} layers)` : '';
                    const btbNote = risoState.backToBack ? ` + ₱${RISO_BTB_SURCHARGE} back-to-back` : '';
                    if (previewVal) previewVal.innerHTML = `₱${printingCost.toFixed(2)}<br><small style="font-size:11px;opacity:0.8">${effReams.toFixed(2)} reams${layerNote}${btbNote}</small>`;
                }
            } else {
                if (printingChoice && printingTypes[printingChoice]) {
                    const pt = printingTypes[printingChoice];
                    printingCost += parseFloat(pt.base_cost);
                    if (pt.per_sheet_cost > 0)
                        printingCost += cutSheets * totalLayers * parseFloat(pt.per_sheet_cost);
                    if (pt.apply_to_paper_cost == 1)
                        paperCost += parseFloat(pt.base_cost);
                }
            }
            grandTotal += printingCost;

            // Paper spoilage (10%)
            const paperSpoilCheck = document.getElementById('paper_spoilage');
            let paperSpoil = 0;
            if (paperSpoilCheck?.checked) {
                paperSpoil = paperCost * 0.10;
                grandTotal += paperSpoil;
            }

            // Other expenses (25%)
            const otherExpCheck = document.getElementById('other_expenses');
            let otherExp = 0;
            if (otherExpCheck?.checked) {
                otherExp = grandTotal * 0.25;
                grandTotal += otherExp;
            }

            // Itemized other expenses (book cover, plastic cover, strings, ring, etc.)
            const itemizedTotal = getItemizedExpensesTotal();
            grandTotal += itemizedTotal;

            const laborCost = grandTotal - paperCost - printingCost -
                (paperSpoilCheck?.checked ? paperSpoil : 0) -
                (otherExpCheck?.checked ? otherExp : 0) -
                itemizedTotal;

            // Summary rows in table
            tbody.innerHTML += `<tr class="total-row"><td colspan="2">Labor Cost</td><td>₱${laborCost.toFixed(2)}</td></tr>`;
            tbody.innerHTML += `<tr class="total-row"><td colspan="2">Paper Cost</td><td>₱${paperCost.toFixed(2)}</td></tr>`;
            if (printingCost > 0) {
                const pLabel = isDigital ? `Digital Printing Cost` : isRiso ? `Riso Printing Cost` : `Printing Cost (${printingChoice})`;
                tbody.innerHTML += `<tr class="total-row"><td colspan="2">${pLabel}</td><td>₱${printingCost.toFixed(2)}</td></tr>`;
            }
            if (paperSpoilCheck?.checked)
                tbody.innerHTML += `<tr class="total-row"><td colspan="2">Paper Spoilage (10%)</td><td>₱${paperSpoil.toFixed(2)}</td></tr>`;
            if (otherExpCheck?.checked)
                tbody.innerHTML += `<tr class="total-row"><td colspan="2">Other Expenses (25%)</td><td>₱${otherExp.toFixed(2)}</td></tr>`;
            if (itemizedTotal > 0)
                tbody.innerHTML += `<tr class="total-row"><td colspan="2">Additional Expenses (Itemized)</td><td>₱${itemizedTotal.toFixed(2)}</td></tr>`;
            tbody.innerHTML += `<tr class="grand-row"><td colspan="2"><i class="bi bi-check-circle-fill me-1"></i>Grand Total</td><td>₱${grandTotal.toFixed(2)}</td></tr>`;

            // Hidden inputs
            document.getElementById('grand_total').value = grandTotal.toFixed(2);
            document.getElementById('printing_type_hidden').value = printingChoice;
            document.getElementById('printing_cost_hidden').value = printingCost.toFixed(2);
            document.getElementById('other_expenses_hidden').value = otherExpCheck?.checked ? 1 : 0;
            document.getElementById('itemized_expenses_hidden').value = JSON.stringify(itemizedExpenses);
            document.getElementById('paper_spoilage_hidden').value = paperSpoilCheck?.checked ? 1 : 0;
            document.getElementById('paper_pricing_method_hidden').value = document.getElementById('paper_pricing_method')?.value || 'ream';
            document.getElementById('custom_paper_cost_hidden').value = document.getElementById('custom_paper_cost')?.value || 0;

            // Summary card
            document.getElementById('summary-paper').textContent = `₱${paperCost.toFixed(2)}`;
            document.getElementById('summary-labor').textContent = `₱${laborCost.toFixed(2)}`;
            document.getElementById('summary-printing').textContent = printingCost > 0 ? `₱${printingCost.toFixed(2)}` : '—';
            document.getElementById('summary-total').textContent = `₱${grandTotal.toFixed(2)}`;

            updatePaperCostDisplay();
        }

        function updatePaperCostDisplay() {
            const method = document.getElementById('paper_pricing_method')?.value || 'ream';
            const container = document.getElementById('paper_details_display');
            if (!container) return;
            let html = '';
            if (layerData.length > 0) {
                layerData.forEach(l => {
                    let costStr = '',
                        metaStr = '';
                    if (method === 'piece' || isSpecialPaper) {
                        const pps = l.price_per_sheet > 0 ? l.price_per_sheet : (l.unit_price / 500);
                        costStr = `₱${(pps * cutSheets).toFixed(2)}`;
                        metaStr = `₱${pps.toFixed(4)}/sheet × ${cutSheets} sheets`;
                    } else if (method === 'custom') {
                        const cc = parseFloat(document.getElementById('custom_paper_cost')?.value) || 0;
                        costStr = `₱${(cc / layerData.length).toFixed(2)}`;
                        metaStr = 'Custom price allocation';
                    } else {
                        costStr = `₱${l.cost_ream.toFixed(2)}`;
                        metaStr = `₱${l.unit_price.toFixed(2)}/ream × ${l.reams.toFixed(2)} reams`;
                    }
                    html += `<div class="paper-layer">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="layer-title">${l.color}</div>
                                <div class="layer-type">→ ${l.mapped}</div>
                            </div>
                            <div class="layer-cost">${costStr}</div>
                        </div>
                        <div class="mt-1" style="font-size:11.5px;color:var(--text-muted)">${metaStr}</div>
                    </div>`;
                });
                const total = calculatePaperCost();
                html += `<div class="paper-total-bar">
                    <span>Total Paper Cost</span>
                    <span style="color:var(--primary)">₱${total.toFixed(2)}</span>
                </div>`;
            } else {
                html = '<div class="alert alert-warning" style="font-size:13px">No paper price rows found for the mapped types.</div>';
            }
            container.innerHTML = html;
        }

        // ── Digital printing UI ─────────────────────────────────────
        function onPrintingTypeChange() {
            const val = document.getElementById('printing_type')?.value || '';
            const digitalSec = document.getElementById('digital-section');
            const risoSec = document.getElementById('riso-section');
            const paperMethodRow = document.getElementById('paper_method_row');
            const isDigital = val.toUpperCase().includes('DIGITAL');
            const isRiso = val.toUpperCase().includes('RISO');

            if (digitalSec) digitalSec.style.display = isDigital ? 'block' : 'none';
            if (risoSec) risoSec.style.display = isRiso ? 'block' : 'none';

            if (paperMethodRow) {
                const lockMethod = (isDigital || isRiso) && !isSpecialPaper;
                paperMethodRow.style.opacity = lockMethod ? '0.4' : '1';
                paperMethodRow.style.pointerEvents = lockMethod ? 'none' : '';
            }
            renderDigitalOptions();
            calculate();
        }

        function setColorMode(mode) {
            digitalState.colorMode = mode;
            digitalState.selectedKey = null;
            digitalState.selectedContent = null;
            renderDigitalOptions();
            saveDigitalState();
            calculate();
        }

        function setPaperType(type) {
            digitalState.paperType = type;
            digitalState.selectedKey = null;
            digitalState.selectedContent = null;
            document.querySelectorAll('.paper-type-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.pt === type);
            });
            renderDigitalOptions();
            saveDigitalState();
            calculate();
        }

        function renderDigitalOptions() {
            const pt = digitalState.paperType;
            const cm = digitalState.colorMode;
            const container = document.getElementById('digital-price-options');
            if (!container) return;

            // Hide all sections
            container.querySelectorAll('.digital-options').forEach(el => el.classList.remove('visible'));

            const sectionId = `dopt_${pt}_${cm}`;
            const section = document.getElementById(sectionId);
            if (section) {
                section.classList.add('visible');
            }
        }

        function selectDigitalOption(sizeLabel, contentType, priceInputId) {
            digitalState.selectedKey = sizeLabel;
            digitalState.selectedContent = contentType;

            document.querySelectorAll('.price-option-row').forEach(r => r.classList.remove('selected'));
            const row = document.getElementById('row_' + priceInputId);
            if (row) row.classList.add('selected');

            saveDigitalState();
            calculate();
        }

        function updateBackToBack() {
            const cb = document.getElementById('back_to_back');
            digitalState.backToBack = cb ? cb.checked : false;
            saveDigitalState();
            calculate();
        }

        function addSession(task) {
            const container = document.getElementById(task + '-sessions');
            const idx = container.children.length;
            const row = document.createElement('div');
            row.classList.add('session-row', 'row', 'g-2', 'align-items-center');
            row.innerHTML = `
                <div class="col-md-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][start]" onchange="calculate()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Time</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][end]" onchange="calculate()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Break (mins)</label>
                    <input type="number" class="form-control" name="sessions[${task}][${idx}][break]" min="0" value="0" onchange="calculate()">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn-danger-sm" onclick="this.closest('.session-row').remove();calculate()">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>`;
            container.appendChild(row);
            calculate();
        }

        function formatTime12h(t) {
            if (!t) return '';
            let [h, m] = t.split(':').map(Number);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m.toString().padStart(2,'0')} ${ampm}`;
        }

        window.onload = function() {
            initPaperPricing();
            onPrintingTypeChange(); // shows/hides digital + riso sections
            restoreDigitalState(); // re-apply saved digital selections
            restoreRisoState(); // re-apply saved riso selections
            renderItemizedExpenses(); // re-apply saved itemized other expenses
            calculate();
        };
    </script>
</head>

<body>

    <!-- Topbar -->
    <div class="topbar">
        <div class="container">
            <a href="job_orders.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <div>
                <div class="topbar-title"><i class="bi bi-calculator me-2"></i>Expenses Calculator</div>
                <div class="topbar-sub"><?= htmlspecialchars($client_name) ?> &mdash; <?= htmlspecialchars($project_name) ?> &mdash; <?= htmlspecialchars(date("F j, Y", strtotime($log_date))) ?></div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row g-4">

            <!-- ── LEFT COLUMN ── -->
            <div class="col-lg-4">

                <!-- Summary Card -->
                <div class="summary-card">
                    <h5><i class="bi bi-pie-chart me-1"></i> Cost Summary</h5>
                    <div class="summary-item">
                        <span class="label"><i class="bi bi-layers me-1"></i>Paper Cost</span>
                        <span class="value" id="summary-paper">₱0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="label"><i class="bi bi-people me-1"></i>Labor Cost</span>
                        <span class="value" id="summary-labor">₱0.00</span>
                    </div>
                    <div class="summary-item">
                        <span class="label"><i class="bi bi-printer me-1"></i>Printing Cost</span>
                        <span class="value" id="summary-printing">—</span>
                    </div>
                    <hr class="summary-divider">
                    <div class="summary-item">
                        <span style="font-weight:700;font-size:0.9rem">Grand Total</span>
                        <span class="value summary-total" id="summary-total">₱0.00</span>
                    </div>
                    <div style="font-size:10.5px;opacity:0.6;margin-top:8px">*Reflects current labor & paper prices</div>
                </div>

                <!-- Expense Options -->
                <div class="card">
                    <div class="card-header">
                        <div class="header-icon"><i class="bi bi-sliders"></i></div>
                        Expense Options
                    </div>
                    <div class="card-body">
                        <!-- Printing Type -->
                        <div class="mb-3">
                            <label class="form-label">Type of Printing</label>
                            <select id="printing_type" class="form-select" onchange="onPrintingTypeChange()">
                                <option value="">— Select Printing Type —</option>
                                <?php foreach ($printing_types as $name => $pt): ?>
                                    <option value="<?= htmlspecialchars($name) ?>" <?= ($order['printing_type'] === $name ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Digital Printing Section -->
                        <div id="digital-section">
                            <div class="digital-header"><i class="bi bi-display"></i> Digital Printing Options</div>

                            <!-- Color Mode -->
                            <label class="form-label">Color Mode</label>
                            <div class="color-toggle mb-3">
                                <input type="radio" name="color_mode" id="cm_colored" value="colored" checked onchange="setColorMode('colored')">
                                <label for="cm_colored"><i class="bi bi-palette"></i> Colored</label>
                                <div class="divider"></div>
                                <input type="radio" name="color_mode" id="cm_bw" value="bw" onchange="setColorMode('bw')">
                                <label for="cm_bw"><i class="bi bi-circle-half"></i> Black & White</label>
                            </div>

                            <!-- Paper Type -->
                            <label class="form-label">Paper Type</label>
                            <div class="paper-type-tabs mb-3">
                                <button class="paper-type-btn active" data-pt="bond" onclick="setPaperType('bond')">
                                    <i class="bi bi-file-earmark"></i> Normal
                                </button>
                                <button class="paper-type-btn" data-pt="photo" onclick="setPaperType('photo')">
                                    <i class="bi bi-image"></i> Photo Paper
                                </button>
                                <button class="paper-type-btn" data-pt="glossy" onclick="setPaperType('glossy')">
                                    <i class="bi bi-stars"></i> C2S Glossy
                                </button>
                                <button class="paper-type-btn" data-pt="sticker" onclick="setPaperType('sticker')">
                                    <i class="bi bi-tag"></i> Sticker
                                </button>
                            </div>

                            <!-- Price Options Container -->
                            <div id="digital-price-options">

                                <?php
                                // Build all combinations
                                $digitalOptGroups = [
                                    'bond' => [
                                        'colored' => [
                                            'short' => ['text_only' => 'Text Only', 'image_text' => 'Image w/ Text', 'image_only' => 'Image Only'],
                                            'long'  => ['text_only' => 'Text Only', 'image_text' => 'Image w/ Text', 'image_only' => 'Image Only'],
                                        ],
                                        'bw' => [
                                            'short' => ['text_only' => 'Text Only', 'image_text' => 'Image w/ Text', 'image_only' => 'Image Only'],
                                            'long'  => ['text_only' => 'Text Only', 'image_text' => 'Image w/ Text', 'image_only' => 'Image Only'],
                                        ],
                                    ],
                                    'photo' => [
                                        'colored' => ['sizes' => ['3R size & wallet (2/3pcs)', '4R size or 4x6 in (2pcs)', '5R size or 5x7 in (1pc)', '6R size or 6x8 in (1pc)', 'A4 size']],
                                        'bw'      => ['sizes' => ['3R size & wallet (2/3pcs)', '4R size or 4x6 in (2pcs)', '5R size or 5x7 in (1pc)', '6R size or 6x8 in (1pc)', 'A4 size']],
                                    ],
                                    'glossy' => [
                                        'colored' => ['sizes' => ['A4 * 8.5x11 * 8.5x13 (70/80GSM)', 'A4 * 8.5x11 * 8.5x13 (100/120GSM)', 'A3, 12x18 UP (130/220GSM)', 'A3, 12x18 UP (250/300GSM)']],
                                        'bw'      => ['sizes' => ['A4 * 8.5x11 * 8.5x13 (70/80GSM)', 'A4 * 8.5x11 * 8.5x13 (100/120GSM)', 'A3, 12x18 UP (130/220GSM)', 'A3, 12x18 UP (250/300GSM)']],
                                    ],
                                    'sticker' => [
                                        'colored' => ['sizes' => ['A4 * 8.5x11 * 8.5x13', 'A3, 12x18 UP']],
                                        'bw'      => ['sizes' => ['A4 * 8.5x11 * 8.5x13', 'A3, 12x18 UP']],
                                    ],
                                ];

                                foreach ($digitalOptGroups as $ptKey => $cmGroups):
                                    foreach ($cmGroups as $cmKey => $data):
                                        $sectionId = "dopt_{$ptKey}_{$cmKey}";
                                        $isDefault = ($ptKey === 'bond' && $cmKey === 'colored');
                                ?>
                                        <div class="digital-options <?= $isDefault ? 'visible' : '' ?>" id="<?= $sectionId ?>">
                                            <?php if ($ptKey === 'bond'): ?>
                                                <?php foreach (['short' => 'Short (8.5×11)', 'long' => 'Long (8.5×13)'] as $sizeKey => $sizeLabel): ?>
                                                    <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin:10px 0 6px">
                                                        <?= $sizeLabel ?>
                                                    </div>
                                                    <?php foreach ($data[$sizeKey] as $ctKey => $ctLabel):
                                                        $price = $digital_prices[$ptKey][$cmKey][$sizeKey][$ctKey]['price'] ?? 0;
                                                        $inputId = $ptKey . '_' . $cmKey . '_' . $sizeKey . '_' . $ctKey;
                                                        $cssInputId = preg_replace('/[^a-zA-Z0-9]/', '_', $inputId);
                                                    ?>
                                                        <div class="price-option-row" id="row_<?= $cssInputId ?>"
                                                            onclick="selectDigitalOption('<?= $sizeKey ?>','<?= $ctKey ?>','<?= $cssInputId ?>')">
                                                            <i class="bi bi-check-circle option-radio" style="color:var(--border)"></i>
                                                            <span class="option-label"><?= $ctLabel ?></span>
                                                            <div class="option-price-input">
                                                                <span style="color:var(--text-muted);font-size:11px">₱</span>
                                                                <input type="number" step="0.01" min="0"
                                                                    id="price_input_<?= $cssInputId ?>"
                                                                    value="<?= $price ?>"
                                                                    onclick="event.stopPropagation()"
                                                                    onchange="calculate()"
                                                                    title="Edit price">
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php foreach ($data['sizes'] as $sizeLabel):
                                                    $price = $digital_prices[$ptKey][$cmKey][$sizeLabel]['__']['price'] ?? 0;
                                                    $inputId = $ptKey . '_' . $cmKey . '_' . $sizeLabel . '___';
                                                    $cssInputId = preg_replace('/[^a-zA-Z0-9]/', '_', $inputId);
                                                ?>
                                                    <div class="price-option-row" id="row_<?= $cssInputId ?>"
                                                        onclick="selectDigitalOption('<?= htmlspecialchars($sizeLabel, ENT_QUOTES) ?>',null,'<?= $cssInputId ?>')">
                                                        <i class="bi bi-check-circle option-radio" style="color:var(--border)"></i>
                                                        <span class="option-label"><?= htmlspecialchars($sizeLabel) ?></span>
                                                        <div class="option-price-input">
                                                            <span style="color:var(--text-muted);font-size:11px">₱</span>
                                                            <input type="number" step="0.01" min="0"
                                                                id="price_input_<?= $cssInputId ?>"
                                                                value="<?= $price ?>"
                                                                onclick="event.stopPropagation()"
                                                                onchange="calculate()"
                                                                title="Edit price">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                <?php endforeach;
                                endforeach; ?>

                            </div><!-- /digital-price-options -->

                            <!-- Back to Back -->
                            <div class="back-to-back-row">
                                <input type="checkbox" class="form-check-input" id="back_to_back" onchange="updateBackToBack()">
                                <label for="back_to_back">
                                    <i class="bi bi-arrow-left-right me-1"></i>
                                    <strong>Back-to-Back</strong> — doubles price per sheet
                                </label>
                            </div>

                            <!-- Digital cost preview -->
                            <div class="digital-cost-preview" id="digital_cost_preview">
                                <div class="dcp-label"><i class="bi bi-calculator me-1"></i>Digital Printing Cost</div>
                                <div class="dcp-value" id="digital_cost_value">₱0.00</div>
                            </div>
                        </div><!-- /digital-section -->

                        <!-- ── RISO Printing Section ── -->
                        <div id="riso-section" style="display:none;border:2px solid #e67e22;border-radius:12px;padding:18px;margin-top:14px;background:#fffbf5;animation:fadeIn 0.3s ease">
                            <div style="font-size:0.78rem;font-weight:700;color:#e67e22;text-transform:uppercase;letter-spacing:0.6px;margin-bottom:14px;display:flex;align-items:center;gap:6px">
                                <i class="bi bi-printer"></i> Riso Printing Options
                            </div>

                            <label class="form-label">Select Paper &amp; Size</label>
                            <?php
                            $riso_paper_names = array_keys($riso_prices);
                            $riso_sizes_map   = ['short' => 'Short', 'long' => 'Long', 'a4' => 'A4'];
                            foreach ($riso_paper_names as $pname):
                                $sizes = $riso_prices[$pname];
                            ?>
                                <div style="background:#fff;border:1.5px solid #f0d9c0;border-radius:10px;margin-bottom:10px;overflow:hidden">
                                    <div style="background:#fef3e2;padding:9px 14px;font-size:12.5px;font-weight:700;color:#92400e;border-bottom:1px solid #f0d9c0">
                                        <?= htmlspecialchars($pname) ?>
                                    </div>
                                    <div style="display:flex;flex-direction:column">
                                        <?php foreach ($sizes as $sizeKey => $sizeData):
                                            $sizeLabel = ['short' => 'Short (8.5×11)', 'long' => 'Long (8.5×13)', 'a4' => 'A4 (8.5×11)'][$sizeKey] ?? strtoupper($sizeKey);
                                            $rowId = 'riso_row_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pname) . '_' . $sizeKey;
                                            $inputId = 'riso_price_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pname) . '_' . $sizeKey;
                                        ?>
                                            <div class="riso-option-row" id="<?= $rowId ?>"
                                                onclick="selectRisoOption(<?= htmlspecialchars(json_encode($pname), ENT_QUOTES) ?>, '<?= $sizeKey ?>')"
                                                style="display:flex;align-items:center;padding:9px 14px;border-bottom:1px solid #f5ece0;cursor:pointer;transition:background 0.15s;gap:10px">
                                                <i class="bi bi-check-circle riso-radio" style="color:#e0cbb8"></i>
                                                <span style="flex:1;font-size:12.5px;font-weight:500"><?= $sizeLabel ?></span>
                                                <span style="font-size:11px;color:#92400e;background:#fef3e2;padding:2px 7px;border-radius:20px;font-weight:600">per ream</span>
                                                <div style="display:flex;align-items:center;gap:4px;background:#fff8f0;border:1.5px solid #f0d9c0;border-radius:7px;padding:4px 10px">
                                                    <span style="color:#92400e;font-size:11px;font-weight:600">₱</span>
                                                    <input type="number" step="0.01" min="0"
                                                        id="<?= $inputId ?>"
                                                        value="<?= $sizeData['price'] ?>"
                                                        onclick="event.stopPropagation()"
                                                        onchange="calculate()"
                                                        style="border:none;background:transparent;width:70px;font-size:13px;font-weight:700;color:#c05621;text-align:right;outline:none;font-family:'Poppins',sans-serif"
                                                        title="Edit price per ream">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Back to back -->
                            <div style="background:#fff7ed;border:1.5px solid #fcd34d;border-radius:9px;padding:10px 14px;margin-top:4px;display:flex;align-items:center;gap:10px">
                                <input type="checkbox" class="form-check-input" id="riso_back_to_back" onchange="updateRisoBackToBack()">
                                <label for="riso_back_to_back" style="font-size:13px;font-weight:500;color:#92400e;margin:0;cursor:pointer">
                                    <i class="bi bi-arrow-left-right me-1"></i>
                                    <strong>Back-to-Back</strong> &mdash; adds <strong>₱200</strong> to total riso cost
                                </label>
                            </div>

                            <!-- Riso cost preview -->
                            <div id="riso_cost_preview" style="display:none;background:linear-gradient(135deg,#e67e22 0%,#c0392b 100%);color:#fff;border-radius:10px;padding:12px 16px;margin-top:12px">
                                <div style="font-size:11px;opacity:0.75;text-transform:uppercase;letter-spacing:0.5px"><i class="bi bi-calculator me-1"></i>Riso Printing Cost</div>
                                <div style="font-size:1.4rem;font-weight:700" id="riso_cost_value">₱0.00</div>
                            </div>
                        </div><!-- /riso-section -->

                        <!-- Paper Pricing Method -->
                        <div class="mb-3 mt-3" id="paper_method_row">
                            <label class="form-label">Paper Pricing Method</label>
                            <select id="paper_pricing_method" class="form-select" onchange="calculate()">
                                <option value="ream" <?= ($order['paper_pricing_method'] ?? 'ream') === 'ream'   ? 'selected' : '' ?>>By Ream (500 sheets)</option>
                                <option value="piece" <?= ($order['paper_pricing_method'] ?? 'ream') === 'piece'  ? 'selected' : '' ?>>By Piece (per sheet)</option>
                                <option value="custom" <?= ($order['paper_pricing_method'] ?? 'ream') === 'custom' ? 'selected' : '' ?>>Custom Paper Cost</option>
                            </select>
                        </div>
                        <div id="custom_paper_section" style="display:none">
                            <div class="mb-3">
                                <label class="form-label">Total Paper Cost (₱)</label>
                                <input type="number" step="0.01" min="0" id="custom_paper_cost"
                                    value="<?= $order['custom_paper_cost'] ?? 0 ?>" class="form-control" onchange="calculate()">
                            </div>
                        </div>

                        <!-- Add-ons -->
                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
                            <label style="display:flex;align-items:center;gap:10px;background:var(--bg);border-radius:9px;padding:10px 14px;cursor:pointer">
                                <input class="form-check-input" type="checkbox" id="other_expenses"
                                    <?= $order['other_expenses'] == 1 ? 'checked' : '' ?> onchange="calculate()">
                                <span>
                                    <span style="font-weight:600;font-size:13px">+25% Other Expenses</span><br>
                                    <span style="font-size:11px;color:var(--text-muted)">Applied to grand total</span>
                                </span>
                            </label>
                            <label style="display:flex;align-items:center;gap:10px;background:var(--bg);border-radius:9px;padding:10px 14px;cursor:pointer">
                                <input class="form-check-input" type="checkbox" id="paper_spoilage"
                                    <?= $order['paper_spoilage'] == 1 ? 'checked' : '' ?> onchange="calculate()">
                                <span>
                                    <span style="font-weight:600;font-size:13px">+10% Paper Spoilage</span><br>
                                    <span style="font-size:11px;color:var(--text-muted)">Applied to paper cost</span>
                                </span>
                            </label>
                        </div>

                        <div style="margin-top:14px;background:var(--bg);border-radius:9px;padding:12px 14px">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                                <div>
                                    <span style="font-weight:600;font-size:13px">Additional Expenses</span><br>
                                    <span style="font-size:11px;color:var(--text-muted)">Materials & add-ons, e.g. plastic cover, ring binders, etc.</span>
                                </div>
                                <button type="button" class="btn-add-session" onclick="addItemizedExpense()" title="Add expense">
                                    <i class="bi bi-plus-circle"></i> Add
                                </button>   
                            </div>
                            <div id="itemized_expenses_list"></div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px">
                    <button type="submit" form="costForm" class="btn-primary-solid">
                        <i class="bi bi-check-circle-fill"></i> Save Expenses
                    </button>
                    <a href="manage_prices.php?id=<?= $job_id ?>" class="btn-outline-primary-custom">
                        <i class="bi bi-gear-fill"></i> Manage Price Lists
                    </a>
                </div>

                <!-- Job Details -->
                <div class="card">
                    <div class="card-header">
                        <div class="header-icon"><i class="bi bi-info-circle"></i></div>
                        Job Details
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <?php foreach (
                                [
                                    'Qty'           => $quantity,
                                    'Sets'          => $number_of_sets,
                                    'Size'          => $product_size,
                                    'Paper Type'    => ucfirst($paper_type),
                                    'Paper Size'    => ucfirst($paper_size),
                                    'Total Pieces'  => $total_sheets,
                                    'Cut Sheets'    => $cut_sheets,
                                    'Reams'         => number_format($reams, 3),
                                ] as $label => $value
                            ): ?>
                                <div class="detail-item">
                                    <div class="dl"><?= $label ?></div>
                                    <div class="dv"><?= htmlspecialchars((string)$value) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /col-lg-4 -->

            <!-- ── RIGHT COLUMN ── -->
            <div class="col-lg-8">

                <!-- Cost Breakdown -->
                <div class="card">
                    <div class="card-header" style="justify-content:space-between">
                        <div style="display:flex;align-items:center;gap:8px">
                            <div class="header-icon"><i class="bi bi-table"></i></div>
                            Cost Breakdown
                        </div>
                        <button class="btn-recalc" onclick="calculate()">
                            <i class="bi bi-arrow-clockwise"></i> Recalculate
                        </button>
                    </div>
                    <div style="overflow-x:auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Task / Item</th>
                                    <th>Hours / Units</th>
                                    <th>Cost</th>
                                </tr>
                            </thead>
                            <tbody id="results">
                                <tr>
                                    <td colspan="3" style="text-align:center;padding:20px;color:var(--text-muted)">
                                        <i class="bi bi-hourglass me-1"></i> Calculating...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Paper Cost Details -->
                <div class="card">
                    <div class="card-header">
                        <div class="header-icon"><i class="bi bi-layers"></i></div>
                        Paper Cost Details
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-sm-6">
                                <div class="detail-item">
                                    <div class="dl">Cut Size</div>
                                    <div class="dv"><?= htmlspecialchars($product_size) ?> <span style="font-size:12px;color:var(--text-muted)">(<?= htmlspecialchars((string)($cut_size_map[$product_size] ?? 1)) ?> per sheet)</span></div>
                                </div>
                            </div>
                            <div class="col-sm-6 mt-2 mt-sm-0">
                                <div class="detail-item">
                                    <div class="dl">Paper Type</div>
                                    <div class="dv"><?= htmlspecialchars(ucfirst($paper_type)) ?> Paper</div>
                                </div>
                            </div>
                        </div>
                        <div class="section-badge mb-3"><i class="bi bi-stack"></i> Paper Layers</div>
                        <div id="paper_details_display">
                            <?php if (!empty($layer_data)): ?>
                                <?php foreach ($layer_data as $layer): ?>
                                    <div class="paper-layer">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="layer-title"><?= htmlspecialchars($layer['color']) ?></div>
                                                <div class="layer-type">→ <?= htmlspecialchars($layer['mapped']) ?></div>
                                            </div>
                                            <div class="layer-cost">₱<?= number_format($layer['cost_ream'], 2) ?></div>
                                        </div>
                                        <div class="mt-1" style="font-size:11.5px;color:var(--text-muted)">
                                            <?php if (!empty($layer['is_special'])): ?>
                                                ₱<?= number_format($layer['price_per_sheet'], 4) ?>/sheet × <?= number_format($cut_sheets, 2) ?> sheets
                                            <?php else: ?>
                                                ₱<?= number_format($layer['unit_price'], 2) ?>/ream × <?= number_format($layer['reams'], 2) ?> reams
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="paper-total-bar">
                                    <span>Total Paper Cost</span>
                                    <span style="color:var(--primary)">₱<?= number_format($total_paper_cost_ream, 2) ?></span>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning" style="font-size:13px">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    No paper price rows found. Check price tables and mappings.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Labor Sessions Form -->
                <form method="post" action="save_job_order_cost.php" id="costForm">
                    <input type="hidden" name="job_id" value="<?= htmlspecialchars((string)$job_id) ?>">
                    <input type="hidden" name="grand_total" id="grand_total" value="0">
                    <input type="hidden" name="printing_type" id="printing_type_hidden">
                    <input type="hidden" name="printing_cost" id="printing_cost_hidden">
                    <input type="hidden" name="other_expenses_hidden" id="other_expenses_hidden">
                    <input type="hidden" name="itemized_expenses_hidden" id="itemized_expenses_hidden">
                    <input type="hidden" name="paper_spoilage_hidden" id="paper_spoilage_hidden">
                    <input type="hidden" name="paper_pricing_method" id="paper_pricing_method_hidden">
                    <input type="hidden" name="custom_paper_cost" id="custom_paper_cost_hidden">

                    <div class="card">
                        <div class="card-header">
                            <div class="header-icon"><i class="bi bi-clock"></i></div>
                            Labor Sessions
                        </div>
                        <div class="card-body">
                            <ul class="nav task-tabs mb-3" id="taskTabs" role="tablist">
                                <?php foreach ($tasks as $index => $task): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?>"
                                            data-bs-toggle="tab" data-bs-target="#pane-<?= htmlspecialchars($task) ?>"
                                            type="button"><?= htmlspecialchars($task) ?></button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content">
                                <?php foreach ($tasks as $index => $task): ?>
                                    <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="pane-<?= htmlspecialchars($task) ?>">
                                        <div class="task-info-bar">
                                            <span class="task-name"><i class="bi bi-person-gear me-1"></i><?= htmlspecialchars(strtoupper($task)) ?></span>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <span class="task-rate">₱<?= htmlspecialchars((string)$rates[$task]) ?>/hr</span>
                                                <button type="button" class="btn-add-session" onclick="addSession('<?= htmlspecialchars($task) ?>')">
                                                    <i class="bi bi-plus-circle"></i> Add Session
                                                </button>
                                            </div>
                                        </div>
                                        <div id="<?= htmlspecialchars($task) ?>-sessions">
                                            <?php if (!empty($sessions_by_task[$task])): ?>
                                                <?php foreach ($sessions_by_task[$task] as $i => $s):
                                                    $startVal = $s['start_time'] ? substr($s['start_time'], 0, 5) : '';
                                                    $endVal   = $s['end_time']   ? substr($s['end_time'],   0, 5) : '';
                                                ?>
                                                    <div class="session-row row g-2 align-items-center">
                                                        <div class="col-md-3">
                                                            <label class="form-label">Start Time</label>
                                                            <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][start]" value="<?= htmlspecialchars($startVal) ?>" onchange="calculate()">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">End Time</label>
                                                            <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][end]" value="<?= htmlspecialchars($endVal) ?>" onchange="calculate()">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Break (mins)</label>
                                                            <input type="number" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][break]" min="0" value="<?= htmlspecialchars((string)$s['break_minutes']) ?>" onchange="calculate()">
                                                        </div>
                                                        <div class="col-md-3 d-flex align-items-end">
                                                            <button type="button" class="btn-danger-sm" onclick="this.closest('.session-row').remove();calculate()">
                                                                <i class="bi bi-trash"></i> Remove
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="session-row row g-2 align-items-center">
                                                    <div class="col-md-3">
                                                        <label class="form-label">Start Time</label>
                                                        <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][start]" onchange="calculate()">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">End Time</label>
                                                        <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][end]" onchange="calculate()">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Break (mins)</label>
                                                        <input type="number" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][break]" min="0" value="0" onchange="calculate()">
                                                    </div>
                                                    <div class="col-md-3 d-flex align-items-end">
                                                        <button type="button" class="btn-danger-sm" onclick="this.closest('.session-row').remove();calculate()">
                                                            <i class="bi bi-trash"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Session Details Table -->
                <div class="card mt-2">
                    <div class="card-header">
                        <div class="header-icon"><i class="bi bi-list-check"></i></div>
                        Session Details
                    </div>
                    <div style="overflow-x:auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Break</th>
                                    <th>Hours</th>
                                    <th>Cost</th>
                                </tr>
                            </thead>
                            <tbody id="session_details">
                                <tr>
                                    <td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">
                                        No sessions logged yet
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /col-lg-8 -->
        </div><!-- /row -->
    </div><!-- /container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>