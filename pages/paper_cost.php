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
    <link rel="stylesheet" href="../assets/css/pages/paper_cost.css">
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

    <script>
        window.PAPER_COST_DATA = {
            rates: <?= $js_rates ?>,
            layerData: <?= $js_layer_data ?>,
            printingTypes: <?= $js_printing ?>,
            cutSheets: <?= $js_cut_sheets ?>,
            totalSheets: <?= $js_total_sheets ?>,
            reams: <?= $js_reams ?>,
            isSpecialPaper: <?= ($paper_type === 'special paper') ? 'true' : 'false' ?>,
            digitalPrices: <?= $js_digital_prices ?>,
            savedDigital: <?= json_encode($savedDigital) ?>,
            risoPrices: <?= $js_riso_prices ?>,
            savedRiso: <?= json_encode($savedRiso) ?>,
            itemizedExpenses: <?= $js_itemized_expenses ?>,
            totalLayers: <?= count($paper_sequence) ?>
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/pages/paper_cost.js"></script>
</body>

</html>