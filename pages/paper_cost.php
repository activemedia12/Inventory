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
    '1/2' => 2,  '1/3' => 3,  '1/4' => 4,  '1/6' => 6,
    '1/8' => 8,  '1/10' => 10,'1/12' => 12,'1/14' => 14,
    '1/16' => 16,'1/18' => 18,'1/20' => 20,'1/22' => 22,
    '1/24' => 24,'1/25' => 25,'1/26' => 26,'1/28' => 28,
    '1/30' => 30,'1/32' => 32,'1/36' => 36,'1/40' => 40,
    '1/48' => 48,'1/50' => 50,'whole' => 1,
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

// ── Fetch paper prices — single query per type (fix N+1) ───────────
$layer_data = [];
$total_paper_cost_ream = 0.0;

if ($paper_type === 'carbonless') {
    // Fetch all carbonless prices at once
    $unique_types = array_unique(array_map(fn($c) => mapPaperType($c, $paper_type), $paper_sequence));
    $placeholders = implode(',', array_fill(0, count($unique_types), '?'));
    $price_stmt = $inventory->prepare(
        "SELECT paper_type, short_price, long_price, price_per_sheet
         FROM paper_prices
         WHERE paper_type IN ($placeholders)
         ORDER BY effective_date DESC"
    );
    $types_str = str_repeat('s', count($unique_types));
    $price_stmt->bind_param($types_str, ...array_values($unique_types));
    $price_stmt->execute();
    $price_rows = $price_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $price_stmt->close();

    // Index by paper_type (latest effective_date first due to ORDER BY)
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
    // Fetch all special paper products keyed by product_name (case-insensitive)
    $special_products = $inventory->query("
        SELECT product_name, product_group, unit_price
        FROM products
        WHERE LOWER(product_type) = 'special paper'
    ")->fetch_all(MYSQLI_ASSOC);

    $special_product_map = [];
    foreach ($special_products as $p) {
        $key = strtolower(trim($p['product_name']));
        if (!isset($special_product_map[$key])) {
            $special_product_map[$key] = $p;
        }
    }

    foreach ($paper_sequence as $color) {
        $key     = strtolower(trim($color));
        $product = $special_product_map[$key] ?? null;

        if ($product) {
            $pps        = (float)$product['unit_price'];
            $layer_cost = $pps * $cut_sheets;
            $total_paper_cost_ream += $layer_cost;

            $layer = buildLayerData($color, $product['product_name'], 0, $pps, $reams, $layer_cost, $total_sheets);
            $layer['is_special'] = true;
            $layer_data[] = $layer;
        }
    }

} else {
    // Ordinary paper — direct name match from products table, priced per ream
    $ordinary_products = $inventory->query("
        SELECT product_name, product_group, unit_price
        FROM products
        WHERE LOWER(product_type) = 'ordinary paper'
    ")->fetch_all(MYSQLI_ASSOC);

    $ordinary_product_map = [];
    foreach ($ordinary_products as $p) {
        $key = strtolower(trim($p['product_name']));
        if (!isset($ordinary_product_map[$key])) {
            $ordinary_product_map[$key] = $p;
        }
    }

    foreach ($paper_sequence as $color) {
        $key     = strtolower(trim($color));
        $product = $ordinary_product_map[$key] ?? null;

        if ($product) {
            $unit_price      = (float)$product['unit_price'];  // price per ream
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
    if (strpos($paper_size, 'long') !== false || strpos($paper_size, 'f4') !== false) {
        return (float)$price['long_price'];
    } elseif (strpos($paper_size, 'short') !== false || strpos($paper_size, 'qto') !== false) {
        return (float)$price['short_price'];
    } elseif ($paper_size === '11x17') {
        return (float)$price['short_price'] * 2;
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 7px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: #1876f299; border-radius: 10px; }
        :root { --primary: #1877f2; --secondary: #166fe5; }
        body { background: #f8f9fa; font-family: 'Poppins', sans-serif; font-size: 15px; padding: 20px 0 40px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,.1); margin-bottom: 20px; border: none; }
        .card-header { background: var(--primary); color: white; border-radius: 10px 10px 0 0 !important; font-size: 120%; font-weight: 600; padding: 15px 30px; }
        .session-row { background: #f8f9fa; border-radius: 5px; padding: 10px; margin-bottom: 10px; border-left: 4px solid var(--secondary); }
        .total-row { font-weight: 700; background: #0060b41a; }
        .cost-badge { font-size: 1.1em; padding: 8px 15px; border-radius: 20px; }
        .nav-tabs .nav-link.active { font-weight: 600; color: var(--primary); border-bottom: 3px solid var(--secondary); }
        .summary-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }
        .paper-layer { border-left: 3px solid #3498db; padding-left: 10px; margin-bottom: 20px; }
        .task-header { background: #e9ecef; padding: 10px 15px; border-radius: 5px; margin-bottom: 10px; }
        .session-table th { background: white; color: black; }
        .back-button { margin-bottom: 20px; }
        .btn-primary:hover { background: rgba(67,238,76,.1); border-color: #28a745; color: #28a745; }
        .btn-outline-primary { color: var(--secondary); border-color: var(--secondary); }
        .btn-outline-primary:hover { background: #1670e528; border-color: grey; color: grey; }
    </style>
    <script>
        const rates        = <?= $js_rates ?>;
        const layerData    = <?= $js_layer_data ?>;
        const printingTypes = <?= $js_printing ?>;
        const cutSheets    = <?= $js_cut_sheets ?>;
        const totalSheets  = <?= $js_total_sheets ?>;
        const reams        = <?= $js_reams ?>;
        const isSpecialPaper = <?= ($paper_type === 'special paper') ? 'true' : 'false' ?>;

        let paperCost    = 0;

        function initPaperPricing() {
            const methodRow = document.getElementById('paper_method_row');
            const sel = document.getElementById('paper_pricing_method');
            const sec = document.getElementById('custom_paper_section');

            if (isSpecialPaper) {
                // Special paper: always per-sheet — hide the method selector
                if (methodRow) methodRow.style.display = 'none';
                if (sec) sec.style.display = 'none';
            } else {
                if (!sel) return;
                sec.style.display = sel.value === 'custom' ? 'block' : 'none';
                sel.addEventListener('change', function () {
                    sec.style.display = this.value === 'custom' ? 'block' : 'none';
                    calculate();
                });
            }
        }

        function calculatePaperCost() {
            // Special paper: always price_per_sheet × cut_sheets (ream size varies per delivery)
            if (isSpecialPaper) {
                let total = 0;
                layerData.forEach(l => { total += l.price_per_sheet * cutSheets; });
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
                    layerData.forEach(l => { total += l.unit_price * reams; });
            }
            return total;
        }

        function calculate() {
            paperCost = calculatePaperCost();
            let grandTotal = paperCost;

            const tbody       = document.getElementById('results');
            const sessionBody = document.getElementById('session_details');
            tbody.innerHTML = '';
            sessionBody.innerHTML = '';

            const totalLayers = <?= count($paper_sequence) ?>;

            // Labor
            Object.keys(rates).forEach(task => {
                const container = document.getElementById(task + '-sessions');
                if (!container) return;
                let totalHours = 0, totalCost = 0;
                container.querySelectorAll('.session-row').forEach(s => {
                    const start = s.querySelector("[name*='[start]']").value;
                    const end   = s.querySelector("[name*='[end]']").value;
                    const brk   = parseInt(s.querySelector("[name*='[break]']").value) || 0;
                    if (start && end) {
                        const st = new Date('1970-01-01T' + start + ':00');
                        const en = new Date('1970-01-01T' + end   + ':00');
                        if (en > st) {
                            let h = (en - st) / 3600000 - brk / 60;
                            if (h < 0) h = 0;
                            const c = h * rates[task];
                            totalHours += h; totalCost += c;
                            sessionBody.innerHTML += `<tr>
                                <td style="padding:15px 30px">${task}</td>
                                <td style="padding:15px 30px">${formatTime12h(start)}</td>
                                <td style="padding:15px 30px">${formatTime12h(end)}</td>
                                <td style="padding:15px 30px">${brk}</td>
                                <td style="padding:15px 30px">${h.toFixed(2)}</td>
                                <td style="padding:15px 30px">₱${c.toFixed(2)}</td></tr>`;
                        }
                    }
                });
                if (totalHours > 0) {
                    tbody.innerHTML += `<tr>
                        <td style="padding:15px 30px">${task}</td>
                        <td style="padding:15px 30px">${totalHours.toFixed(2)}</td>
                        <td style="padding:15px 30px">₱${totalCost.toFixed(2)}</td></tr>`;
                    grandTotal += totalCost;
                }
            });

            // Printing
            const printingChoice = document.getElementById('printing_type')?.value || '';
            let printingCost = 0;
            if (printingChoice && printingTypes[printingChoice]) {
                const pt = printingTypes[printingChoice];
                printingCost += parseFloat(pt.base_cost);
                if (pt.per_sheet_cost > 0)
                    printingCost += cutSheets * totalLayers * parseFloat(pt.per_sheet_cost);
                if (pt.apply_to_paper_cost == 1) {
                    // base_cost is already inside printingCost which gets added to grandTotal below.
                    // Only bump paperCost so spoilage (10% of paper) reflects the printing base too.
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
                otherExp   = grandTotal * 0.25;
                grandTotal += otherExp;
            }

            // Layout fee

            // Discount / commission

            // Summary rows
            const laborCost = grandTotal - paperCost - printingCost
                            - (paperSpoilCheck?.checked ? paperSpoil : 0)
                            - (otherExpCheck?.checked ? otherExp : 0);
            tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding:15px 30px">Labor Cost</td><td style="padding:15px 30px">₱${laborCost.toFixed(2)}</td></tr>`;
            tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding:15px 30px">Paper Cost</td><td style="padding:15px 30px">₱${paperCost.toFixed(2)}</td></tr>`;
            if (printingCost > 0)
                tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding:15px 30px">Printing Cost (${printingChoice})</td><td style="padding:15px 30px">₱${printingCost.toFixed(2)}</td></tr>`;
            if (paperSpoilCheck?.checked)
                tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding:15px 30px">Paper Spoilage (10%)</td><td style="padding:15px 30px">₱${paperSpoil.toFixed(2)}</td></tr>`;
            if (otherExpCheck?.checked)
                tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding:15px 30px">Other Expenses (25%)</td><td style="padding:15px 30px">₱${otherExp.toFixed(2)}</td></tr>`;
            tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding:15px 30px;color:green;font-size:130%">Grand Total</td><td style="padding:15px 30px;color:green;font-size:130%">₱${grandTotal.toFixed(2)}</td></tr>`;

            // Update hidden inputs
            document.getElementById('grand_total').value                = grandTotal.toFixed(2);
            document.getElementById('printing_type_hidden').value       = printingChoice;
            document.getElementById('printing_cost_hidden').value       = printingCost.toFixed(2);
            document.getElementById('other_expenses_hidden').value      = otherExpCheck?.checked ? 1 : 0;
            document.getElementById('paper_spoilage_hidden').value      = paperSpoilCheck?.checked ? 1 : 0;
            document.getElementById('paper_pricing_method_hidden').value = document.getElementById('paper_pricing_method').value;
            document.getElementById('custom_paper_cost_hidden').value   = document.getElementById('custom_paper_cost').value;

            // Summary card
            document.getElementById('summary-paper').textContent = `₱${paperCost.toFixed(2)}`;
            document.getElementById('summary-labor').textContent = `₱${laborCost.toFixed(2)}`;
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
                    html += `<div class="paper-layer"><div class="d-flex justify-content-between"><div>
                        <strong>${l.color}</strong> <small class="text-muted">(→ ${l.mapped})</small></div><div>`;
                    if (method === 'piece' || isSpecialPaper) {
                        const pps = l.price_per_sheet > 0 ? l.price_per_sheet : (l.unit_price / 500);
                        const warning = (isSpecialPaper && l.pps_missing)
                            ? ` <span style="color:#dc3545;font-size:11px">⚠ price/sheet not set — using ream÷500 estimate</span>` : '';
                        html += `₱${(pps * cutSheets).toFixed(2)}</div></div>
                            <div class="small text-muted">₱${pps.toFixed(4)}/sheet × ${cutSheets} sheets${warning}</div>`;
                    } else if (method === 'custom') {
                        const cc = parseFloat(document.getElementById('custom_paper_cost')?.value) || 0;
                        html += `₱${(cc / layerData.length).toFixed(2)}</div></div>
                            <div class="small text-muted">Custom price allocation</div>`;
                    } else {
                        html += `₱${l.cost_ream.toFixed(2)}</div></div>
                            <div class="small text-muted">₱${l.unit_price.toFixed(2)}/ream × ${l.reams.toFixed(2)} reams</div>`;
                    }
                    html += `</div>`;
                });
                const total = calculatePaperCost();
                html += `<div class="total-row p-2 mt-3 rounded"><div class="d-flex justify-content-between fw-bold">
                    <div>Total Paper Cost (${method}):</div><div>₱${total.toFixed(2)}</div></div></div>`;
            } else {
                html = '<div class="alert alert-warning">No paper price rows found for the mapped types.</div>';
            }
            container.innerHTML = html;
        }

        function addSession(task) {
            const container = document.getElementById(task + '-sessions');
            const idx = container.children.length;
            const row = document.createElement('div');
            row.classList.add('session-row', 'row', 'g-2', 'align-items-center', 'mt-2');
            row.innerHTML = `
                <div class="col-md-3"><label class="form-label small">Start</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][start]" onchange="calculate()"></div>
                <div class="col-md-3"><label class="form-label small">End</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][end]" onchange="calculate()"></div>
                <div class="col-md-3"><label class="form-label small">Break (mins)</label>
                    <input type="number" class="form-control" name="sessions[${task}][${idx}][break]" min="0" value="0" onchange="calculate()"></div>
                <div class="col-md-3"><button type="button" class="btn btn-sm btn-outline-danger mt-md-4" onclick="this.parentElement.parentElement.remove();calculate()">
                    <i class="bi bi-trash"></i> Remove</button></div>`;
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

        window.onload = function () {
            initPaperPricing();
            calculate();
        };
    </script>
</head>
<body>
    <div class="container">
        <div class="back-button">
            <a href="job_orders.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-5 fw-bold text-primary">Expenses Calculator</h1>
                <p class="lead"><?= htmlspecialchars($client_name) ?> — <?= htmlspecialchars($project_name) ?> — <?= htmlspecialchars(date("F j, Y", strtotime($log_date))) ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="summary-card p-4 mb-4">
                    <h5>Cost Summary</h5>
                    <p style="font-size:80%;opacity:50%">*reflects current labor and paper prices</p>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Paper Cost:</span>
                        <span id="summary-paper" class="cost-badge bg-light text-dark">₱0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Labor Cost:</span>
                        <span id="summary-labor" class="cost-badge bg-light text-dark">₱0.00</span>
                    </div>
                    <hr class="my-3 bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong>Total Cost:</strong>
                        <strong id="summary-total" class="cost-badge bg-white text-primary">₱0.00</strong>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">Expenses Options</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Type of Printing</label>
                            <select id="printing_type" class="form-select" onchange="calculate()">
                                <option value="">-- Select Printing Type --</option>
                                <?php foreach ($printing_types as $name => $pt): ?>
                                    <option value="<?= htmlspecialchars($name) ?>" <?= ($order['printing_type'] === $name ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="paper_method_row">
                            <select id="paper_pricing_method" class="form-select">
                                <option value="ream"   <?= ($order['paper_pricing_method'] ?? 'ream') === 'ream'   ? 'selected' : '' ?>>By Ream (500 sheets)</option>
                                <option value="piece"  <?= ($order['paper_pricing_method'] ?? 'ream') === 'piece'  ? 'selected' : '' ?>>By Piece (per sheet)</option>
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
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="other_expenses"
                                   <?= $order['other_expenses'] == 1 ? 'checked' : '' ?> onchange="calculate()">
                            <label class="form-check-label" for="other_expenses">Add 25% Other Expenses</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="paper_spoilage"
                                   <?= $order['paper_spoilage'] == 1 ? 'checked' : '' ?> onchange="calculate()">
                            <label class="form-check-label" for="paper_spoilage">Add 10% Paper Spoilage</label>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" form="costForm" class="btn btn-primary p-2">
                        <i class="bi bi-check-circle"></i> Save Expenses
                    </button>
                    <a href="manage_prices.php?id=<?= $job_id ?>" class="btn btn-outline-primary">
                        <i class="bi bi-gear"></i> Manage Price Lists
                    </a>
                </div>

                <div class="card mb-4 mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center"><span>Job Details</span></div>
                    <div class="card-body p-4">
                        <?php foreach ([
                            'Quantity'        => $quantity,
                            'Number of Sets'  => $number_of_sets,
                            'Product Size'    => $product_size,
                            'Paper Type'      => $paper_type,
                            'Paper Size'      => $paper_size,
                            'Total Pieces'    => $total_sheets,
                            'Sheets after cut'=> $cut_sheets,
                            'Reams needed'    => number_format($reams, 2),
                        ] as $label => $value): ?>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold"><?= $label ?>:</div>
                            <div class="col-6"><?= htmlspecialchars((string)$value) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div><!-- /col-md-4 -->

            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Cost Breakdown</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="calculate()"><i class="bi bi-arrow-clockwise"></i> Recalculate</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr>
                                <th style="padding:15px 30px">Task</th>
                                <th style="padding:15px 30px">Total Hours</th>
                                <th style="padding:15px 30px">Total Cost</th>
                            </tr></thead>
                            <tbody id="results"></tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Paper Cost Details</div>
                    <div class="card-body" style="padding:30px">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="fw-bold">Cut Size:</div>
                                <div><?= htmlspecialchars($product_size) ?> (<?= htmlspecialchars((string)($cut_size_map[$product_size] ?? 1)) ?> per sheet)</div>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold">Paper Type:</div>
                                <div><?= htmlspecialchars(ucfirst($paper_type)) ?> Paper</div>
                            </div>
                        </div>
                        <h6 class="border-bottom pb-2 mb-4 mt-4">Paper Layers</h6>
                        <div id="paper_details_display">
                            <?php if (!empty($layer_data)): ?>
                                <?php foreach ($layer_data as $layer): ?>
                                    <div class="paper-layer">
                                        <div class="d-flex justify-content-between">
                                            <div><strong><?= htmlspecialchars($layer['color']) ?></strong>
                                                 <small class="text-muted">(→ <?= htmlspecialchars($layer['mapped']) ?>)</small></div>
                                            <div>₱<?= number_format($layer['cost_ream'], 2) ?></div>
                                        </div>
                                        <?php if (!empty($layer['is_special'])): ?>
                                            <div class="small text-muted">
                                                ₱<?= number_format($layer['price_per_sheet'], 4) ?>/sheet × <?= number_format($cut_sheets, 2) ?> sheets
                                                <?php if (!empty($layer['pps_missing'])): ?>
                                                    <span style="color:#dc3545"> ⚠ price/sheet not set — using ream÷500 estimate</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (!empty($layer['is_ordinary_product'])): ?>
                                            <div class="small text-muted">₱<?= number_format($layer['unit_price'], 2) ?>/ream × <?= number_format($layer['reams'], 2) ?> reams</div>
                                        <?php else: ?>
                                            <div class="small text-muted">₱<?= number_format($layer['unit_price'], 2) ?> × <?= number_format($layer['reams'], 2) ?> reams</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <div class="total-row p-2 mt-3 rounded">
                                    <div class="d-flex justify-content-between fw-bold">
                                        <div>Total Paper Cost<?= $paper_type === 'special paper' ? ' (per sheet)' : ' (ream)' ?>:</div>
                                        <div>₱<?= number_format($total_paper_cost_ream, 2) ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">No paper price rows found for the mapped types. Check price tables and mappings.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <form method="post" action="save_job_order_cost.php" id="costForm">
                    <input type="hidden" name="job_id"                   value="<?= htmlspecialchars((string)$job_id) ?>">
                    <input type="hidden" name="grand_total"              id="grand_total" value="0">
                    <input type="hidden" name="printing_type"            id="printing_type_hidden">
                    <input type="hidden" name="printing_cost"            id="printing_cost_hidden">
                    <input type="hidden" name="other_expenses_hidden"    id="other_expenses_hidden">
                    <input type="hidden" name="paper_spoilage_hidden"    id="paper_spoilage_hidden">
                    <input type="hidden" name="paper_pricing_method"     id="paper_pricing_method_hidden">
                    <input type="hidden" name="custom_paper_cost"        id="custom_paper_cost_hidden">

                    <div class="card mb-4">
                        <div class="card-header">Labor Sessions</div>
                        <div class="card-body" style="padding:30px">
                            <ul class="nav nav-tabs mb-3" id="taskTabs" role="tablist">
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
                                    <div class="task-header d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <span class="fw-bold"><?= htmlspecialchars(strtoupper($task)) ?></span>
                                            <span class="ms-2 text-muted">(Rate: ₱<?= htmlspecialchars((string)$rates[$task]) ?>/hr)</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSession('<?= htmlspecialchars($task) ?>')">
                                            <i class="bi bi-plus-circle"></i> Add Session
                                        </button>
                                    </div>
                                    <div id="<?= htmlspecialchars($task) ?>-sessions">
                                        <?php if (!empty($sessions_by_task[$task])): ?>
                                            <?php foreach ($sessions_by_task[$task] as $i => $s):
                                                $startVal = $s['start_time'] ? substr($s['start_time'], 0, 5) : '';
                                                $endVal   = $s['end_time']   ? substr($s['end_time'],   0, 5) : '';
                                            ?>
                                            <div class="session-row row g-2 align-items-center mt-2">
                                                <div class="col-md-3"><label class="form-label small">Start</label>
                                                    <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][start]"
                                                           value="<?= htmlspecialchars($startVal) ?>" onchange="calculate()"></div>
                                                <div class="col-md-3"><label class="form-label small">End</label>
                                                    <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][end]"
                                                           value="<?= htmlspecialchars($endVal) ?>" onchange="calculate()"></div>
                                                <div class="col-md-3"><label class="form-label small">Break (mins)</label>
                                                    <input type="number" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][break]"
                                                           min="0" value="<?= htmlspecialchars((string)$s['break_minutes']) ?>" onchange="calculate()"></div>
                                                <div class="col-md-3">
                                                    <button type="button" class="btn btn-sm btn-outline-danger mt-md-4"
                                                            onclick="this.parentElement.parentElement.remove();calculate()">
                                                        <i class="bi bi-trash"></i> Remove</button></div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="session-row row g-2 align-items-center mt-2">
                                                <div class="col-md-3"><label class="form-label small">Start</label>
                                                    <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][start]" onchange="calculate()"></div>
                                                <div class="col-md-3"><label class="form-label small">End</label>
                                                    <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][end]" onchange="calculate()"></div>
                                                <div class="col-md-3"><label class="form-label small">Break (mins)</label>
                                                    <input type="number" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][break]" min="0" value="0" onchange="calculate()"></div>
                                                <div class="col-md-3">
                                                    <button type="button" class="btn btn-sm btn-outline-danger mt-md-4"
                                                            onclick="this.parentElement.parentElement.remove();calculate()">
                                                        <i class="bi bi-trash"></i> Remove</button></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="card mt-4">
                    <div class="card-header">Session Details</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped session-table mb-0">
                                <thead><tr>
                                    <th style="padding:15px 30px">Task</th>
                                    <th style="padding:15px 30px">Start</th>
                                    <th style="padding:15px 30px">End</th>
                                    <th style="padding:15px 30px">Break (mins)</th>
                                    <th style="padding:15px 30px">Hours</th>
                                    <th style="padding:15px 30px">Cost</th>
                                </tr></thead>
                                <tbody id="session_details"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div><!-- /col-md-8 -->
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>