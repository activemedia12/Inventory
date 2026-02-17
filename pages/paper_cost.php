<?php
require_once '../config/db.php';

$job_id = $_GET['id'] ?? 0;
if (!$job_id) {
    die("No job order ID provided.");
}

// ========== FETCH MANPOWER RATES ==========
$rates = [];
$res = $inventory->query("SELECT task_name, hourly_rate FROM manpower_rates");
while ($row = $res->fetch_assoc()) {
    $rates[$row['task_name']] = $row['hourly_rate'];
}
$tasks = array_keys($rates);

// ========== FETCH JOB ORDER DATA ==========
$sql = "SELECT log_date, client_name, project_name, quantity, number_of_sets, product_size, paper_size, paper_type, paper_sequence, printing_type, other_expenses, paper_spoilage, paper_pricing_method, custom_paper_cost 
        FROM job_orders WHERE id = ?";
$stmt = $inventory->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Job order not found.");
}

// ========== FETCH EXISTING JOB SESSIONS ==========
$sessions_by_task = [];
$res2 = $inventory->prepare("SELECT * FROM job_sessions WHERE job_id = ? ORDER BY id ASC");
$res2->bind_param("i", $job_id);
$res2->execute();
$result2 = $res2->get_result();
while ($row = $result2->fetch_assoc()) {
    $sessions_by_task[$row['task_name']][] = $row;
}
$res2->close();

$client_name = $order['client_name'];
$project_name = $order['project_name'];
$log_date = $order['log_date'];

// ========== PAPER COST COMPUTATION ==========
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
    'whole' => 1
];
$cut_size     = $cut_size_map[$product_size] ?? 1;
$total_sheets = $number_of_sets * $quantity;
$cut_sheets   = ($cut_size > 0) ? ($total_sheets / $cut_size) : 0;
$reams        = $cut_sheets / 500;

$table = ($paper_type === 'carbonless') ? "paper_prices" : "paper_cut_prices";

// ========== FETCH PRINTING TYPES ==========
$printing_types = [];
$res3 = $inventory->query("SELECT * FROM printing_types ORDER BY name ASC");
while ($row = $res3->fetch_assoc()) {
    $printing_types[$row['name']] = $row;
}
$js_printing = json_encode($printing_types);

function mapPaperType($color, $paper_type)
{
    $c = strtolower($color);
    if ($paper_type === 'carbonless') {
        if (strpos($c, 'top') !== false) return 'TOP WHITE';
        if (strpos($c, 'middle') !== false) return 'MIDDLE';
        if (strpos($c, 'bottom') !== false) return 'BOTTOM';
    } else {
        if (strpos($c, 'white') !== false) return 'WHITE';
        return 'COLORED';
    }
    return strtoupper($color);
}

// ========== FETCH PAPER PRICES FOR ALL LAYERS ==========
$layer_data = [];
$total_paper_cost_ream = 0.0;

foreach ($paper_sequence as $color) {
    $mappedType = mapPaperType($color, $paper_type);

    $stmt2 = $inventory->prepare("SELECT short_price, long_price, price_per_sheet
                               FROM $table 
                               WHERE paper_type = ? 
                               ORDER BY effective_date DESC LIMIT 1");
    $stmt2->bind_param("s", $mappedType);
    $stmt2->execute();
    $price = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if ($price) {
        if (strpos($paper_size, "long") !== false || strpos($paper_size, "f4") !== false) {
            $unit_price = $price['long_price'];
        } elseif (strpos($paper_size, "short") !== false || strpos($paper_size, "qto") !== false) {
            $unit_price = $price['short_price'];
        } elseif ($paper_size === "11x17") {
            $unit_price = $price['short_price'] * 2;
        } else {
            $unit_price = $price['long_price'];
        }

        $price_per_sheet = $price['price_per_sheet'] ?? ($unit_price / 500);
        $layer_cost_ream = $unit_price * $reams;
        $total_paper_cost_ream += $layer_cost_ream;

        $layer_data[] = [
            "color" => $color,
            "mapped" => $mappedType,
            "unit_price" => (float)$unit_price,
            "price_per_sheet" => (float)$price_per_sheet,
            "reams" => (float)$reams,
            "cost_ream" => (float)$layer_cost_ream,
            "total_sheets" => (float)$total_sheets
        ];
    }
}

// Pass data to JavaScript
$js_rates = json_encode($rates);
$js_layer_data = json_encode($layer_data);
$js_cut_sheets = $cut_sheets;
$js_total_sheets = $total_sheets;
$js_reams = $reams;
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Job Order Cost Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }

        :root {
            --primary: #1877f2;
            --primary-light: #eef2ff;
            --secondary: #166fe5;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            padding-top: 20px;
            padding-bottom: 40px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }

        .card-header {
            background-color: var(--primary);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            font-size: 120%;
            font-weight: 600;
            padding: 15px 30px;
        }

        .btn-primary {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-primary:hover {
            background-color: rgba(67, 238, 76, 0.1);
            border-color: #28a745;
            color: #28a745;
        }

        .btn-outline-primary {
            color: var(--secondary);
            border-color: var(--secondary);
        }

        .btn-outline-primary:hover {
            background-color: #1670e528;
            border-color: grey;
            color: grey;
        }

        .session-row {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid var(--secondary);
        }

        .total-row {
            font-weight: 700;
            background-color: #0060b41a;
        }

        .cost-badge {
            font-size: 1.1em;
            padding: 8px 15px;
            border-radius: 20px;
        }

        .nav-tabs .nav-link.active {
            font-weight: 600;
            color: var(--primary);
            border-bottom: 3px solid var(--secondary);
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }

        .paper-layer {
            border-left: 3px solid #3498db;
            padding-left: 10px;
            margin-bottom: 20px;
        }

        .task-header {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .session-table th {
            background-color: white;
            color: black;
        }

        .back-button {
            margin-bottom: 20px;
        }
    </style>
    <script>
        // Global variables
        const rates = <?= $js_rates ?>;
        const layerData = <?= $js_layer_data ?>;
        const printingTypes = <?= $js_printing ?>;
        const cutSheets = <?= $js_cut_sheets ?>;
        const totalSheets = <?= $js_total_sheets ?>;
        const reams = <?= $js_reams ?>;
        
        let paperCost = 0;
        let selectedPrinting = null;

        // Show/hide custom paper cost section
        function initPaperPricing() {
            const paperMethodSelect = document.getElementById('paper_pricing_method');
            if (paperMethodSelect) {
                // Set initial state
                const customSection = document.getElementById('custom_paper_section');
                if (paperMethodSelect.value === 'custom') {
                    customSection.style.display = 'block';
                } else {
                    customSection.style.display = 'none';
                }
                
                // Add event listener
                paperMethodSelect.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customSection.style.display = 'block';
                    } else {
                        customSection.style.display = 'none';
                    }
                    calculate();
                });
            }
        }

        // Calculate paper cost based on selected method
        function calculatePaperCost() {
            const method = document.getElementById('paper_pricing_method')?.value || 'ream';
            let totalCost = 0;
            
            switch(method) {
                case 'piece':
                    // Calculate based on price_per_sheet
                    layerData.forEach(layer => {
                        if (layer.price_per_sheet && layer.price_per_sheet > 0) {
                            totalCost += layer.price_per_sheet * totalSheets;
                        } else {
                            // Fallback: calculate from ream price if per-sheet price not set
                            totalCost += (layer.unit_price / 500) * totalSheets;
                        }
                    });
                    break;
                    
                case 'custom':
                    // Use custom paper cost
                    const customCost = parseFloat(document.getElementById('custom_paper_cost')?.value) || 0;
                    totalCost = customCost;
                    break;
                    
                case 'ream':
                default:
                    // Original calculation (per ream)
                    layerData.forEach(layer => {
                        totalCost += layer.unit_price * reams;
                    });
                    break;
            }
            
            return totalCost;
        }

        // Main calculation function
        function calculate() {
            // Calculate paper cost dynamically
            paperCost = calculatePaperCost();
            
            let grandTotal = paperCost;
            let tbody = document.getElementById("results");
            let sessionBody = document.getElementById("session_details");
            tbody.innerHTML = "";
            sessionBody.innerHTML = "";

            let totalLayers = <?= count($paper_sequence) ?>;

            // === Labor ===
            Object.keys(rates).forEach(task => {
                let container = document.getElementById(task + '-sessions');
                if (!container) return;
                let sessions = container.querySelectorAll(".session-row");
                let totalHours = 0;
                let totalCost = 0;

                sessions.forEach(s => {
                    let start = s.querySelector("[name*='[start]']").value;
                    let end = s.querySelector("[name*='[end]']").value;
                    let brk = parseInt(s.querySelector("[name*='[break]']").value) || 0;

                    if (start && end) {
                        let startTime = new Date("1970-01-01T" + start + ":00");
                        let endTime = new Date("1970-01-01T" + end + ":00");

                        if (endTime > startTime) {
                            let hours = (endTime - startTime) / 3600000;
                            hours -= (brk / 60.0);
                            if (hours < 0) hours = 0;

                            let cost = hours * rates[task];
                            totalHours += hours;
                            totalCost += cost;

                            let detailRow = `<tr>
                        <td style="padding: 15px 30px;">${task}</td>
                        <td style="padding: 15px 30px;">${formatTime12h(start)}</td>
                        <td style="padding: 15px 30px;">${formatTime12h(end)}</td>
                        <td style="padding: 15px 30px;">${brk}</td>
                        <td style="padding: 15px 30px;">${hours.toFixed(2)}</td>
                        <td style="padding: 15px 30px;">₱${cost.toFixed(2)}</td>
                    </tr>`;
                            sessionBody.innerHTML += detailRow;
                        }
                    }
                });

                if (totalHours > 0) {
                    let row = `<tr>
                <td style="padding: 15px 30px;">${task}</td>
                <td style="padding: 15px 30px;">${totalHours.toFixed(2)}</td>
                <td style="padding: 15px 30px;">₱${totalCost.toFixed(2)}</td>
            </tr>`;
                    tbody.innerHTML += row;
                    grandTotal += totalCost;
                }
            });

            // === Printing Type ===
            let printingSelect = document.getElementById("printing_type");
            let printingChoice = printingSelect ? printingSelect.value : "";
            let printingCost = 0;
            if (printingChoice && printingTypes[printingChoice]) {
                let pt = printingTypes[printingChoice];
                printingCost += parseFloat(pt.base_cost);

                if (pt.per_sheet_cost > 0) {
                    printingCost += cutSheets * totalLayers * parseFloat(pt.per_sheet_cost);
                }

                if (pt.apply_to_paper_cost == 1) {
                    paperCost += parseFloat(pt.base_cost);
                    grandTotal += parseFloat(pt.base_cost);
                }
            }
            grandTotal += printingCost;

            // === Other Expenses (25%) ===
            let otherExpCheck = document.getElementById("other_expenses");
            let otherExp = 0;

            // === Paper Spoilage (10%) ===
            let paperSpoilCheck = document.getElementById("paper_spoilage");
            let paperSpoil = 0;

            // Create temporary variables for calculation
            let calculatedPaperCost = paperCost; // Start with base paper cost
            let calculatedGrandTotal = grandTotal; // Start with current total (paper + labor + printing)

            // Calculate paper spoilage
            if (paperSpoilCheck && paperSpoilCheck.checked) {
                paperSpoil = paperCost * 0.10; // Calculate 10% of paper cost
                calculatedPaperCost += paperSpoil; // Add spoilage to paper cost for display
                calculatedGrandTotal += paperSpoil; // Add spoilage to grand total
            }

            // Calculate other expenses (based on total INCLUDING paper spoilage)
            if (otherExpCheck && otherExpCheck.checked) {
                otherExp = calculatedGrandTotal * 0.25; // 25% of total (including spoilage)
                calculatedGrandTotal += otherExp; // Add other expenses to grand total
            }

            // === Final Rows ===
            tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding: 15px 30px;">Labor Cost</td><td style="padding: 15px 30px;">₱${(grandTotal - paperCost - printingCost).toFixed(2)}</td></tr>`;
            tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding: 15px 30px;">Paper Cost</td><td style="padding: 15px 30px;">₱${paperCost.toFixed(2)}</td></tr>`;
            if (printingCost > 0) {
                tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding: 15px 30px;">Printing Cost (${printingChoice})</td><td style="padding: 15px 30px;">₱${printingCost.toFixed(2)}</td></tr>`;
            }
            if (paperSpoilCheck && paperSpoilCheck.checked) {
                tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding: 15px 30px;">Paper Spoilage (10%)</td><td style="padding: 15px 30px;">₱${paperSpoil.toFixed(2)}</td></tr>`;
            }
            if (otherExpCheck && otherExpCheck.checked) {
                tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding: 15px 30px;">Other Expenses (25%)</td><td style="padding: 15px 30px;">₱${otherExp.toFixed(2)}</td></tr>`;
            }
            tbody.innerHTML += `<tr class="total-row"><td colspan="2" style="padding: 15px 30px; color:green; font-size:130%;">Grand Total</td><td style="padding: 15px 30px; color:green; font-size:130%;">₱${calculatedGrandTotal.toFixed(2)}</td></tr>`;

            let hidden = document.getElementById("grand_total");
            if (hidden) hidden.value = calculatedGrandTotal.toFixed(2);

            // Update summary
            document.getElementById("summary-paper").textContent = `₱${calculatedPaperCost.toFixed(2)}`;
            document.getElementById("summary-labor").textContent = `₱${(grandTotal - paperCost - printingCost).toFixed(2)}`;
            document.getElementById("summary-total").textContent = `₱${calculatedGrandTotal.toFixed(2)}`;

            // Update hidden inputs
            document.getElementById("printing_type_hidden").value = printingChoice || "";
            document.getElementById("printing_cost_hidden").value = printingCost.toFixed(2);
            document.getElementById("other_expenses_hidden").value = otherExpCheck.checked ? 1 : 0;
            document.getElementById("paper_spoilage_hidden").value = paperSpoilCheck.checked ? 1 : 0;
            document.getElementById('paper_pricing_method_hidden').value = document.getElementById('paper_pricing_method').value;
            document.getElementById('custom_paper_cost_hidden').value = document.getElementById('custom_paper_cost').value;
            
            // Update paper cost display
            updatePaperCostDisplay();
        }

        // Update paper cost display based on selected method
        function updatePaperCostDisplay() {
            const method = document.getElementById('paper_pricing_method')?.value || 'ream';
            const paperDetailsContainer = document.getElementById('paper_details_display');
            
            if (!paperDetailsContainer) return;
            
            let html = '';
            
            if (layerData.length > 0) {
                layerData.forEach(layer => {
                    html += `<div class="paper-layer">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${layer.color}</strong>
                                <small class="text-muted">(→ ${layer.mapped})</small>
                            </div>
                            <div>`;
                    
                    if (method === 'piece') {
                        const pricePerSheet = layer.price_per_sheet > 0 ? layer.price_per_sheet : (layer.unit_price / 500);
                        const layerCost = pricePerSheet * totalSheets;
                        html += `₱${layerCost.toFixed(2)}</div>
                        </div>
                        <div class="small text-muted">
                            ₱${pricePerSheet.toFixed(4)}/sheet × ${totalSheets} sheets
                        </div>`;
                    } else if (method === 'custom') {
                        const customCost = parseFloat(document.getElementById('custom_paper_cost')?.value) || 0;
                        html += `₱${(customCost / layerData.length).toFixed(2)}</div>
                        </div>
                        <div class="small text-muted">
                            Custom price allocation
                        </div>`;
                    } else {
                        html += `₱${layer.cost_ream.toFixed(2)}</div>
                        </div>
                        <div class="small text-muted">
                            ₱${layer.unit_price.toFixed(2)}/ream × ${layer.reams.toFixed(2)} reams
                        </div>`;
                    }
                    
                    html += `</div>`;
                });
                
                const totalCost = calculatePaperCost();
                html += `<div class="total-row p-2 mt-3 rounded">
                    <div class="d-flex justify-content-between fw-bold">
                        <div>Total Paper Cost (${method}):</div>
                        <div>₱${totalCost.toFixed(2)}</div>
                    </div>
                </div>`;
            } else {
                html = '<div class="alert alert-warning">No paper price rows found for the mapped types.</div>';
            }
            
            paperDetailsContainer.innerHTML = html;
        }

        function addSession(task) {
            let container = document.getElementById(task + '-sessions');
            let idx = container.children.length;
            let row = document.createElement('div');
            row.classList.add("session-row", "row", "g-2", "align-items-center", "mt-2");
            row.innerHTML = `
                <div class="col-md-3">
                    <label class="form-label small">Start</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][start]" onchange="calculate()" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">End</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][end]" onchange="calculate()" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Break (mins)</label>
                    <input type="number" class="form-control" name="sessions[${task}][${idx}][break]" min="0" value="0" onchange="calculate()">
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-sm btn-outline-danger mt-md-4" onclick="this.parentElement.parentElement.remove(); calculate()">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>`;
            container.appendChild(row);
            calculate();
        }

        function toggleSessions(task) {
            const container = document.getElementById(task + '-sessions');
            const button = document.querySelector(`button[onclick="toggleSessions('${task}')"]`);
            if (container.style.display === 'none') {
                container.style.display = 'block';
                button.innerHTML = '<i class="bi bi-chevron-up"></i> Hide Sessions';
            } else {
                container.style.display = 'none';
                button.innerHTML = '<i class="bi bi-chevron-down"></i> Show Sessions';
            }
        }

        function formatTime12h(timeStr) {
            if (!timeStr) return "";
            let [hour, minute] = timeStr.split(":").map(Number);
            let ampm = hour >= 12 ? "PM" : "AM";
            hour = hour % 12;
            if (hour === 0) hour = 12;
            return `${hour}:${minute.toString().padStart(2, "0")} ${ampm}`;
        }

        // Initialize on page load
        window.onload = function() {
            initPaperPricing();
            calculate();
            if (!window.location.hash.includes('reloaded')) {
                window.location.hash = 'reloaded';
                window.location.reload();
            }
        };
    </script>
</head>

<body>
    <div class="container">
        <div class="back-button">
            <button type="button" class="btn btn-outline-primary" onclick="window.location.href='job_orders.php'">
                <i class="bi bi-arrow-left"></i> Back
            </button>
        </div>
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-5 fw-bold text-primary">Expenses Calculator</h1>
                <p class="lead"><?= htmlspecialchars($client_name) ?> - <?= htmlspecialchars($project_name) ?> - <?= htmlspecialchars(date("F j, Y", strtotime($log_date))) ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="summary-card p-4 mb-4">
                    <h5 class="card-title">Cost Summary</h5>
                    <p style="font-size: 80%; opacity: 50%;">*based always on the current labor and paper prices</p>
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
                            <label for="printing_type" class="form-label">Type of Printing</label>
                            <select id="printing_type" name="printing_type" class="form-select" onchange="calculate()">
                                <option value="">-- Select Printing Type --</option>
                                <?php foreach ($printing_types as $name => $pt): ?>
                                    <option value="<?= htmlspecialchars($name) ?>"
                                        <?= ($order['printing_type'] === $name ? 'selected' : '') ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="paper_pricing_method" class="form-label">Paper Cost Calculation Method</label>
                            <select id="paper_pricing_method" name="paper_pricing_method" class="form-select" onchange="calculate()">
                                <option value="ream" <?= ($order['paper_pricing_method'] ?? 'ream') === 'ream' ? 'selected' : '' ?>>
                                    Calculate by Ream (500 sheets)
                                </option>
                                <option value="piece" <?= ($order['paper_pricing_method'] ?? 'ream') === 'piece' ? 'selected' : '' ?>>
                                    Calculate by Piece (per sheet)
                                </option>
                                <option value="custom" <?= ($order['paper_pricing_method'] ?? 'ream') === 'custom' ? 'selected' : '' ?>>
                                    Enter Custom Paper Cost
                                </option>
                            </select>
                        </div>

                        <div id="custom_paper_section" style="display: <?= (($order['paper_pricing_method'] ?? 'ream') === 'custom') ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <label for="custom_paper_cost" class="form-label">Total Paper Cost (₱)</label>
                                <input type="number" step="0.01" id="custom_paper_cost" name="custom_paper_cost" 
                                    value="<?= $order['custom_paper_cost'] ?? 0 ?>" class="form-control" onchange="calculate()">
                            </div>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="other_expenses"
                                <?= ($order['other_expenses'] == 1 ? 'checked' : '') ?>
                                onchange="calculate()">
                            <label class="form-check-label" for="other_expenses">
                                Add 25% Other Expenses
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="paper_spoilage"
                                <?= ($order['paper_spoilage'] == 1 ? 'checked' : '') ?>
                                onchange="calculate()">
                            <label class="form-check-label" for="paper_spoilage">
                                Add 10% Paper Expenses
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" form="costForm" class="btn btn-primary p-2">
                        <i class="bi bi-check-circle"></i> Save Expenses
                    </button>
                    <a onclick="window.location.href='manage_prices.php?id=<?= $job_id ?>'" class="btn btn-outline-primary">
                        <i class="bi bi-gear"></i> Manage Price Lists
                    </a>
                </div>

                <div class="card mb-4 mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Job Details</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Quantity:</div>
                            <div class="col-6"><?= htmlspecialchars($quantity) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Number of Sets:</div>
                            <div class="col-6"><?= htmlspecialchars($number_of_sets) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Product Size:</div>
                            <div class="col-6"><?= htmlspecialchars($product_size) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Paper Type:</div>
                            <div class="col-6"><?= htmlspecialchars($paper_type) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Paper Size:</div>
                            <div class="col-6"><?= htmlspecialchars($paper_size) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Total Pieces:</div>
                            <div class="col-6"><?= htmlspecialchars($total_sheets) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Sheets after cut:</div>
                            <div class="col-6"><?= htmlspecialchars($cut_sheets) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6 fw-bold">Reams needed:</div>
                            <div class="col-6"><?= number_format($reams, 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Cost Breakdown</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="calculate()">
                            <i class="bi bi-arrow-clockwise"></i> Recalculate
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="padding: 15px 30px;">Task</th>
                                    <th style="padding: 15px 30px;">Total Hours</th>
                                    <th style="padding: 15px 30px;">Total Cost</th>
                                </tr>
                            </thead>
                            <tbody id="results"></tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        Paper Cost Details
                    </div>
                    <div class="card-body" style="padding: 30px">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="fw-bold">Cut Size:</div>
                                <div><?= htmlspecialchars($product_size) ?> (<?= htmlspecialchars($cut_size_map[$product_size] ?? 1) ?> per sheet)</div>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold">Paper Type:</div>
                                <div><?= htmlspecialchars(ucfirst($paper_type)) ?> Paper</div>
                            </div>
                        </div>

                        <h6 class="border-bottom pb-2 mb-4 mt-4">Paper Layers</h6>
                        <div id="paper_details_display">
                            <!-- Dynamic content will be loaded here by JavaScript -->
                            <?php if (!empty($layer_data)): ?>
                                <?php foreach ($layer_data as $layer): ?>
                                    <div class="paper-layer">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?= htmlspecialchars($layer['color']) ?></strong>
                                                <small class="text-muted">(→ <?= htmlspecialchars($layer['mapped']) ?>)</small>
                                            </div>
                                            <div>₱<?= number_format($layer['cost_ream'], 2) ?></div>
                                        </div>
                                        <div class="small text-muted">
                                            ₱<?= number_format($layer['unit_price'], 2) ?> × <?= number_format($layer['reams'], 2) ?> reams
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="total-row p-2 mt-3 rounded">
                                    <div class="d-flex justify-content-between fw-bold">
                                        <div>Total Paper Cost (ream):</div>
                                        <div>₱<?= number_format($total_paper_cost_ream, 2) ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    No paper price rows found for the mapped types. Check your price tables and mappings.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <form method="post" action="save_job_order_cost.php" id="costForm">
                    <input type="hidden" name="grand_total" id="grand_total" value="0">
                    <input type="hidden" name="job_id" value="<?= htmlspecialchars($job_id) ?>">

                    <input type="hidden" name="printing_type" id="printing_type_hidden">
                    <input type="hidden" name="printing_cost" id="printing_cost_hidden">
                    <input type="hidden" name="other_expenses_hidden" id="other_expenses_hidden">
                    <input type="hidden" name="paper_spoilage_hidden" id="paper_spoilage_hidden">
                    <input type="hidden" name="paper_pricing_method" id="paper_pricing_method_hidden">
                    <input type="hidden" name="custom_paper_cost" id="custom_paper_cost_hidden">

                    <div class="card mb-4">
                        <div class="card-header">
                            Labor Sessions
                        </div>
                        <div class="card-body" style="padding: 30px">
                            <ul class="nav nav-tabs mb-3" id="taskTabs" role="tablist">
                                <?php foreach ($tasks as $index => $task): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" id="tab-<?= $task ?>" data-bs-toggle="tab" data-bs-target="#pane-<?= $task ?>" type="button" role="tab"><?= htmlspecialchars($task) ?></button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="tab-content" id="taskTabContent">
                                <?php foreach ($tasks as $index => $task): ?>
                                    <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="pane-<?= $task ?>" role="tabpanel">
                                        <div class="task-header d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <span class="fw-bold" style="text-transform: uppercase;"><?= htmlspecialchars(strtoupper($task)) ?></span>
                                                <span class="ms-2 text-muted">(Rate: ₱<?= htmlspecialchars($rates[$task]) ?>/hr)</span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSession('<?= htmlspecialchars($task) ?>')">
                                                <i class="bi bi-plus-circle"></i> Add Session
                                            </button>
                                        </div>

                                        <div id="<?= htmlspecialchars($task) ?>-sessions">
                                            <?php if (!empty($sessions_by_task[$task])): ?>
                                                <?php foreach ($sessions_by_task[$task] as $i => $s): ?>
                                                    <?php
                                                    $startVal = $s['start_time'] ? substr($s['start_time'], 0, 5) : '';
                                                    $endVal   = $s['end_time']   ? substr($s['end_time'], 0, 5)   : '';
                                                    ?>
                                                    <div class="session-row row g-2 align-items-center mt-2">
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Start</label>
                                                            <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][start]"
                                                                value="<?= htmlspecialchars($startVal) ?>" onchange="calculate()" required>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">End</label>
                                                            <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][end]"
                                                                value="<?= htmlspecialchars($endVal) ?>" onchange="calculate()" required>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label small">Break (mins)</label>
                                                            <input type="number" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][<?= $i ?>][break]"
                                                                min="0" value="<?= htmlspecialchars($s['break_minutes']) ?>" onchange="calculate()">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <button type="button" class="btn btn-sm btn-outline-danger mt-md-4" onclick="this.parentElement.parentElement.remove(); calculate()">
                                                                <i class="bi bi-trash"></i> Remove
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="session-row row g-2 align-items-center mt-2">
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Start</label>
                                                        <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][start]" onchange="calculate()" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small">End</label>
                                                        <input type="time" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][end]" onchange="calculate()" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label small">Break (mins)</label>
                                                        <input type="number" class="form-control" name="sessions[<?= htmlspecialchars($task) ?>][0][break]" min="0" value="0" onchange="calculate()">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <button type="button" class="btn btn-sm btn-outline-danger mt-md-4" onclick="this.parentElement.parentElement.remove(); calculate()">
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

                <div class="card mt-4">
                    <div class="card-header">
                        Session Details
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped session-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="padding: 15px 30px;">Task</th>
                                        <th style="padding: 15px 30px;">Start</th>
                                        <th style="padding: 15px 30px;">End</th>
                                        <th style="padding: 15px 30px;">Break (mins)</th>
                                        <th style="padding: 15px 30px;">Hours</th>
                                        <th style="padding: 15px 30px;">Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="session_details"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>