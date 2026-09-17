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
$product_size   = $order['product_size']; // legacy/primary — kept for the pieces of the UI below that still show one value

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

// Quantity/Sets per Bind are shared across all paper groups (see
// job_orders.php) — total output volume is the same no matter how many
// paper types make up the job, only the per-group cut size differs.
$total_sheets = $number_of_sets * $quantity;

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

function determineSizePrice($price, $paper_size)
{
    if (strpos($paper_size, 'long') !== false || strpos($paper_size, 'f4') !== false) return (float)$price['long_price'];
    elseif (strpos($paper_size, 'short') !== false || strpos($paper_size, 'qto') !== false) return (float)$price['short_price'];
    elseif ($paper_size === '11x17') return (float)$price['short_price'] * 2;
    return (float)$price['long_price'];
}

function buildLayerData($color, $mapped, $unit_price, $price_per_sheet, $reams, $layer_cost, $total_sheets, $cut_sheets, $paper_type, $paper_size, $cut_size_label)
{
    return [
        'color'           => $color,
        'mapped'          => $mapped,
        'unit_price'      => (float)$unit_price,
        'price_per_sheet' => (float)$price_per_sheet,
        'reams'           => (float)$reams,
        'cost_ream'       => (float)$layer_cost,
        'total_sheets'    => (float)$total_sheets,
        'cut_sheets'      => (float)$cut_sheets,
        'paper_type'      => $paper_type,
        'paper_size'      => $paper_size,
        'cut_size'        => $cut_size_label,
    ];
}

// Computes the layer breakdown + total cost for ONE paper group. Kept as a
// function so the same carbonless/special-paper/ordinary-paper pricing logic
// runs identically for every group in a multi-paper-type job order.
function computeGroupLayers($inventory, $paper_type, $paper_size, $colors, $cut_sheets, $reams, $total_sheets, $cut_size_label)
{
    $layer_data  = [];
    $group_total = 0.0;

    if ($paper_type === 'carbonless') {
        $unique_types = array_unique(array_map(fn($c) => mapPaperType($c, $paper_type), $colors));
        if (empty($unique_types)) return [$layer_data, $group_total];
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
        foreach ($colors as $color) {
            $mappedType = mapPaperType($color, $paper_type);
            $price = $price_map[$mappedType] ?? null;
            if ($price) {
                $unit_price = determineSizePrice($price, $paper_size);
                $price_per_sheet = $price['price_per_sheet'] ?? ($unit_price / 500);
                $layer_cost_ream = $unit_price * $reams;
                $group_total += $layer_cost_ream;
                $layer_data[] = buildLayerData($color, $mappedType, $unit_price, $price_per_sheet, $reams, $layer_cost_ream, $total_sheets, $cut_sheets, $paper_type, $paper_size, $cut_size_label);
            }
        }
    } elseif ($paper_type === 'special paper') {
        $special_products = $inventory->query("SELECT product_name, product_group, unit_price FROM products WHERE LOWER(product_type) = 'special paper'")->fetch_all(MYSQLI_ASSOC);
        $special_product_map = [];
        foreach ($special_products as $p) {
            $key = strtolower(trim($p['product_name']));
            if (!isset($special_product_map[$key])) $special_product_map[$key] = $p;
        }
        foreach ($colors as $color) {
            $key = strtolower(trim($color));
            $product = $special_product_map[$key] ?? null;
            if ($product) {
                $pps = (float)$product['unit_price'];
                $layer_cost = $pps * $cut_sheets;
                $group_total += $layer_cost;
                $layer = buildLayerData($color, $product['product_name'], 0, $pps, $reams, $layer_cost, $total_sheets, $cut_sheets, $paper_type, $paper_size, $cut_size_label);
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
        foreach ($colors as $color) {
            $key = strtolower(trim($color));
            $product = $ordinary_product_map[$key] ?? null;
            if ($product) {
                $unit_price = (float)$product['unit_price'];
                $layer_cost_ream = $unit_price * $reams;
                $price_per_sheet = $reams > 0 ? ($unit_price / 500) : 0;
                $group_total += $layer_cost_ream;
                $layer = buildLayerData($color, $product['product_name'], $unit_price, $price_per_sheet, $reams, $layer_cost_ream, $total_sheets, $cut_sheets, $paper_type, $paper_size, $cut_size_label);
                $layer['is_ordinary_product'] = true;
                $layer_data[] = $layer;
            }
        }
    }

    return [$layer_data, $group_total];
}

// ── Paper groups (multi-paper-type support) ─────────────────────────
// job_order_paper_items holds every paper type/size used on this job (e.g.
// cover vs. inner pages); jobs saved before this feature existed have no
// rows here, so fall back to a single group built from the legacy columns.
$job_paper_items = [];
$jpi_stmt = $inventory->prepare("SELECT paper_type, paper_size, cut_size, paper_sequence FROM job_order_paper_items WHERE job_order_id = ? ORDER BY sort_order ASC");
$jpi_stmt->bind_param("i", $job_id);
$jpi_stmt->execute();
$jpi_res = $jpi_stmt->get_result();
while ($row = $jpi_res->fetch_assoc()) {
    $job_paper_items[] = $row;
}
$jpi_stmt->close();

if (empty($job_paper_items)) {
    $job_paper_items[] = [
        'paper_type'     => $order['paper_type'],
        'paper_size'     => $order['paper_size'],
        'cut_size'       => $order['product_size'],
        'paper_sequence' => $order['paper_sequence'],
    ];
}

$layer_data            = [];
$total_paper_cost_ream = 0.0;
$paper_groups_display  = []; // for the Job Details / Paper Cost Details summary

foreach ($job_paper_items as $g) {
    $g_paper_type     = strtolower(trim($g['paper_type']));
    $g_paper_size     = strtolower(trim($g['paper_size']));
    $g_cut_size_label = $g['cut_size'] ?: 'whole';
    $g_colors         = array_values(array_filter(array_map('trim', explode(',', $g['paper_sequence'] ?? '')), fn($c) => $c !== ''));

    $g_cut_size   = $cut_size_map[$g_cut_size_label] ?? 1;
    $g_cut_sheets = ($g_cut_size > 0) ? ($total_sheets / $g_cut_size) : 0;
    $g_reams      = $g_cut_sheets / 500;

    [$g_layers, $g_total] = computeGroupLayers($inventory, $g_paper_type, $g_paper_size, $g_colors, $g_cut_sheets, $g_reams, $total_sheets, $g_cut_size_label);
    $layer_data = array_merge($layer_data, $g_layers);
    $total_paper_cost_ream += $g_total;

    $paper_groups_display[] = [
        'paper_type' => $g_paper_type,
        'paper_size' => $g_paper_size,
        'cut_size'   => $g_cut_size_label,
        'cut_sheets' => $g_cut_sheets,
        'reams'      => $g_reams,
    ];
}

// Legacy single values — used by the digital/riso pricing proxy formulas
// (which pick their own paper independent of these groups) and by the
// "Job Details" card, kept as the FIRST group's numbers so single-group
// jobs (still the overwhelming majority) behave exactly as before.
$paper_type   = $paper_groups_display[0]['paper_type'] ?? '';
$paper_size   = $paper_groups_display[0]['paper_size'] ?? '';
$product_size = $paper_groups_display[0]['cut_size'] ?? 'whole';
$cut_sheets   = $paper_groups_display[0]['cut_sheets'] ?? 0;
$reams        = $paper_groups_display[0]['reams'] ?? 0;

// True only when EVERY layer across every group is special paper — a mixed
// job (one group special, another not) is handled per-layer via
// $layer['is_special'] in JS instead of this single flag.
$isSpecialPaper = !empty($layer_data) && count($layer_data) === count(array_filter($layer_data, fn($l) => !empty($l['is_special'])));

$js_rates        = json_encode($rates);
$js_layer_data   = json_encode($layer_data);
$js_cut_sheets   = $cut_sheets;
$js_total_sheets = $total_sheets;
$js_reams        = $reams;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Job Order Cost Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/pages/paper_cost.css">
</head>

<body>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li>
                    <a href="papers.php">
                        <i class="fas fa-boxes"></i> <span>Products</span>
                    </a>
                </li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li class="active"><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
    <div class="container">

        <header class="page-header">
            <div class="page-title">
                <h1>Expenses Management</h1>
                <div class="breadcrumb">
                    <a href="job_orders.php">Job Orders</a> <i class="fas fa-chevron-right" style="font-size:9px;"></i>
                    <a href="edit_job.php?id=<?= $job_id ?>">#<?= $job_id ?></a> <i class="fas fa-chevron-right" style="font-size:9px;"></i>
                    <span>Cost Calculator</span>
                </div>
            </div>
            <a href="job_orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Job Orders</a>
        </header>

        <div class="info-banner">
            <div class="icon"><i class="fas fa-building"></i></div>
            <div>
                <div class="value"><?= htmlspecialchars($client_name) ?> - <?= htmlspecialchars($project_name) ?></div>
                <div class="label">Logged <?= htmlspecialchars(date("M j, Y", strtotime($log_date))) ?></div>
            </div>
        </div>

        <!-- Cost Summary -->
        <div class="summary-bar">
            <div class="summary-stat">
                <div class="ss-icon"><i class="bi bi-layers"></i></div>
                <div>
                    <div class="ss-label">Paper Cost</div>
                    <div class="ss-value" id="summary-paper">₱0.00</div>
                </div>
            </div>
            <div class="summary-stat">
                <div class="ss-icon"><i class="bi bi-people"></i></div>
                <div>
                    <div class="ss-label">Labor Cost</div>
                    <div class="ss-value" id="summary-labor">₱0.00</div>
                </div>
            </div>
            <div class="summary-stat">
                <div class="ss-icon"><i class="bi bi-printer"></i></div>
                <div>
                    <div class="ss-label">Printing Cost</div>
                    <div class="ss-value" id="summary-printing">-</div>
                </div>
            </div>
            <div class="summary-stat total">
                <div class="ss-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div>
                    <div class="ss-label">Grand Total <span class="ss-note">reflects current prices</span></div>
                    <div class="ss-value" id="summary-total">₱0.00</div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            <!-- ── LEFT COLUMN ── -->
            <div class="col-lg-4">

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
                                    'Qty'          => $quantity,
                                    'Sets'         => $number_of_sets,
                                    'Total Pieces' => $total_sheets,
                                ] as $label => $value
                            ): ?>
                                <div class="detail-item">
                                    <div class="dl"><?= $label ?></div>
                                    <div class="dv"><?= htmlspecialchars((string)$value) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:10px;<?= count($paper_groups_display) > 1 ? '' : 'display:none' ?>" id="jobdetails-groups-note">
                            <span style="font-size:11px;color:var(--text-muted)">This job uses <?= count($paper_groups_display) ?> paper types - see the breakdown below.</span>
                        </div>
                        <?php foreach ($paper_groups_display as $gi => $pg): ?>
                            <div class="detail-grid" style="<?= count($paper_groups_display) > 1 ? 'margin-top:10px;padding-top:10px;border-top:1px dashed var(--border,#e2e2e2)' : '' ?>">
                                <?php foreach (
                                    [
                                        'Size'       => $pg['cut_size'],
                                        'Paper Type' => ucfirst($pg['paper_type']),
                                        'Paper Size' => ucfirst($pg['paper_size']),
                                        'Cut Sheets' => $pg['cut_sheets'],
                                        'Reams'      => number_format($pg['reams'], 3),
                                    ] as $label => $value
                                ): ?>
                                    <div class="detail-item">
                                        <div class="dl"><?= $label ?></div>
                                        <div class="dv"><?= htmlspecialchars((string)$value) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Printing Setup -->
                <div class="card">
                    <div class="card-header">
                        <div class="header-icon"><i class="bi bi-sliders"></i></div>
                        Printing Setup
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
                                                            <i class="bi bi-check-circle option-radio"></i>
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
                                                        <i class="bi bi-check-circle option-radio"></i>
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
                                    <strong>Back-to-Back</strong> - doubles price per sheet
                                </label>
                            </div>

                            <!-- Digital cost preview -->
                            <div class="digital-cost-preview" id="digital_cost_preview">
                                <div class="dcp-label"><i class="bi bi-calculator me-1"></i>Digital Printing Cost</div>
                                <div class="dcp-value" id="digital_cost_value">₱0.00</div>
                            </div>
                        </div><!-- /digital-section -->

                        <!-- ── RISO Printing Section ── -->
                        <div id="riso-section" class="riso-section">
                            <div class="riso-section-header">
                                <i class="bi bi-printer"></i> Riso Printing Options
                            </div>

                            <label class="form-label">Select Paper &amp; Size</label>
                            <?php
                            $riso_paper_names = array_keys($riso_prices);
                            $riso_sizes_map   = ['short' => 'Short', 'long' => 'Long', 'a4' => 'A4'];
                            foreach ($riso_paper_names as $pname):
                                $sizes = $riso_prices[$pname];
                            ?>
                                <div class="riso-paper-group">
                                    <div class="riso-paper-group-header">
                                        <?= htmlspecialchars($pname) ?>
                                    </div>
                                    <div>
                                        <?php foreach ($sizes as $sizeKey => $sizeData):
                                            $sizeLabel = ['short' => 'Short (8.5×11)', 'long' => 'Long (8.5×13)', 'a4' => 'A4 (8.5×11)'][$sizeKey] ?? strtoupper($sizeKey);
                                            $rowId = 'riso_row_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pname) . '_' . $sizeKey;
                                            $inputId = 'riso_price_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pname) . '_' . $sizeKey;
                                        ?>
                                            <div class="riso-option-row" id="<?= $rowId ?>"
                                                onclick="selectRisoOption(<?= htmlspecialchars(json_encode($pname), ENT_QUOTES) ?>, '<?= $sizeKey ?>')">
                                                <i class="bi bi-check-circle riso-radio"></i>
                                                <span class="option-label"><?= $sizeLabel ?></span>
                                                <span class="riso-unit-badge">per ream</span>
                                                <div class="riso-price-input">
                                                    <span class="riso-peso">₱</span>
                                                    <input type="number" step="0.01" min="0"
                                                        id="<?= $inputId ?>"
                                                        value="<?= $sizeData['price'] ?>"
                                                        onclick="event.stopPropagation()"
                                                        onchange="calculate()"
                                                        title="Edit price per ream">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Back to back -->
                            <div class="riso-back-to-back">
                                <input type="checkbox" class="form-check-input" id="riso_back_to_back" onchange="updateRisoBackToBack()">
                                <label for="riso_back_to_back">
                                    <i class="bi bi-arrow-left-right me-1"></i>
                                    <strong>Back-to-Back</strong> &mdash; adds <strong>₱200</strong> to total riso cost
                                </label>
                            </div>

                            <!-- Riso cost preview -->
                            <div id="riso_cost_preview" class="riso-cost-preview">
                                <div class="dcp-label"><i class="bi bi-calculator me-1"></i>Riso Printing Cost</div>
                                <div class="dcp-value" id="riso_cost_value">₱0.00</div>
                            </div>
                        </div><!-- /riso-section -->
                    </div>
                </div><!-- /Printing Setup card -->

                <!-- Paper Cost -->
                <div class="card">
                    <div class="card-header">
                        <div class="header-icon"><i class="bi bi-layers"></i></div>
                        Paper Cost Method
                    </div>
                    <div class="card-body">
                        <!-- Paper Pricing Method -->
                        <div class="mb-3" id="paper_method_row">
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
                    </div>
                </div><!-- /Paper Cost card -->

                <!-- Additional Costs -->
                <div class="card">
                    <div class="card-header">
                        <div class="header-icon"><i class="bi bi-plus-circle"></i></div>
                        Additional Costs
                    </div>
                    <div class="card-body">
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
                    <div class="table-scroll">
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
                        <div class="row mb-4" style="gap:10px">
                            <?php if (count($paper_groups_display) <= 1): ?>
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
                            <?php else: ?>
                                <?php foreach ($paper_groups_display as $gi => $pg): ?>
                                    <div class="col-sm-6 mt-2 mt-sm-0">
                                        <div class="detail-item">
                                            <div class="dl">Paper <?= $gi + 1 ?></div>
                                            <div class="dv"><?= htmlspecialchars(ucfirst($pg['paper_type'])) ?> / <?= htmlspecialchars(ucfirst($pg['paper_size'])) ?>
                                                <span style="font-size:12px;color:var(--text-muted)">(<?= htmlspecialchars($pg['cut_size']) ?>)</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="section-badge mb-3"><i class="bi bi-stack"></i> Paper Layers</div>
                        <div id="paper_details_display">
                            <?php if (!empty($layer_data)): ?>
                                <?php foreach ($layer_data as $layer): ?>
                                    <div class="paper-layer">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="layer-title"><?= htmlspecialchars($layer['color']) ?></div>
                                                <div class="layer-type">→ <?= htmlspecialchars($layer['mapped']) ?><?= count($paper_groups_display) > 1 ? ' (' . htmlspecialchars(ucfirst($layer['paper_type'])) . ' / ' . htmlspecialchars(ucfirst($layer['paper_size'])) . ')' : '' ?></div>
                                            </div>
                                            <div class="layer-cost">₱<?= number_format($layer['cost_ream'], 2) ?></div>
                                        </div>
                                        <div class="mt-1" style="font-size:11.5px;color:var(--text-muted)">
                                            <?php if (!empty($layer['is_special'])): ?>
                                                ₱<?= number_format($layer['price_per_sheet'], 4) ?>/sheet × <?= number_format($layer['cut_sheets'], 2) ?> sheets
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
                    <div class="table-scroll">
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
    </div><!-- /main-content -->

    <script>
        window.PAPER_COST_DATA = {
            rates: <?= $js_rates ?>,
            layerData: <?= $js_layer_data ?>,
            printingTypes: <?= $js_printing ?>,
            cutSheets: <?= $js_cut_sheets ?>,
            totalSheets: <?= $js_total_sheets ?>,
            reams: <?= $js_reams ?>,
            isSpecialPaper: <?= $isSpecialPaper ? 'true' : 'false' ?>,
            digitalPrices: <?= $js_digital_prices ?>,
            savedDigital: <?= json_encode($savedDigital) ?>,
            risoPrices: <?= $js_riso_prices ?>,
            savedRiso: <?= json_encode($savedRiso) ?>,
            itemizedExpenses: <?= $js_itemized_expenses ?>,
            totalLayers: <?= count($layer_data) ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/pages/paper_cost.js"></script>
</body>

</html>