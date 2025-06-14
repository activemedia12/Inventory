<?php
$mysqli = new mysqli("localhost", "root", "", "inventory");

$product_id = intval($_GET['product_id'] ?? 0);
$edit_date = $_GET['log_date'] ?? null;

$product = $mysqli->query("SELECT product FROM stock_data WHERE id = $product_id")->fetch_assoc();

$usage_logs = $mysqli->query("SELECT * FROM stock_usage_logs WHERE product_id = $product_id ORDER BY log_date ASC");
$delivery_logs = $mysqli->query("SELECT * FROM stock_delivery_logs WHERE product_id = $product_id ORDER BY log_date ASC");

$total_delivery = 0;
$total_used = 0;

$delivery_logs->data_seek(0);
while ($row = $delivery_logs->fetch_assoc()) {
  $total_delivery += floatval($row['delivery_total']);
}
$delivery_logs->data_seek(0);

$usage_logs->data_seek(0);
while ($row = $usage_logs->fetch_assoc()) {
  $total_used += floatval($row['used_total']);
}
$usage_logs->data_seek(0);

$current_stock = $total_delivery - $total_used;

// Load entry for editing
$delivery_edit = $edit_date ? $mysqli->query("SELECT * FROM stock_delivery_logs WHERE product_id = $product_id AND log_date = '$edit_date'")->fetch_assoc() : null;
$usage_edit = $edit_date ? $mysqli->query("SELECT * FROM stock_usage_logs WHERE product_id = $product_id AND log_date = '$edit_date'")->fetch_assoc() : null;

function formatDateForDisplay($dateString) {
    $months = [
        '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
        '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
        '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
    ];
    
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateString, $matches)) {
        $year = $matches[1];
        $month = $matches[2];
        $day = ltrim($matches[3], '0');
        return $months[$month] . ' ' . $day . ', ' . $year;
    }
    
    return $dateString;
}

function formatNumber($value) {
    $value = floatval($value);
    return $value == floor($value) ? number_format($value, 0) : number_format($value, 2);
}

function getStockLevelClass($stock) {
    $stock = floatval($stock);
    if ($stock <= 0) return 'stock-level-critical';
    if ($stock <= 25) return 'stock-level-low';
    if ($stock <= 50) return 'stock-level-medium';
    return 'stock-level-high';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Log - <?= htmlspecialchars($product['product']) ?></title>
    <style>
        :root {
            --primary: #1c1c1c;
            --secondary:rgba(28, 28, 28, 0.80);
            --accent:rgb(210, 203, 61);
            --light: #f8f9fa;
            --dark: #212529;
            --success:rgb(51, 51, 51);
            --warning: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .back-btn svg {
            margin-right: 8px;
        }
        
        h2, h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--accent);
        }
        
        .card h4 {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card p {
            font-size: 24px;
            font-weight: bold;
            color: var(--dark);
        }
        
        form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        fieldset {
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        legend {
            padding: 0 10px;
            color: var(--primary);
            font-weight: 600;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        input[type="date"] {
            width: 20%;
            padding: 10px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 16px;
            transition: border 0.3s ease;
        }

        input[type="number"],
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 16px;
            transition: border 0.3s ease;
        }
        
        input[type="date"]:focus,
        input[type="number"]:focus,
        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .input-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        th {
            background-color: var(--primary);
            color: white;
            font-weight: 500;
        }
        
        tr:nth-child(even) {
            background-color: var(--light);
        }

        tr {
            transition: 0.3s;
        }
        
        tr:hover {
            background-color:rgb(230, 230, 230);
        }
        
        .action-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .action-link:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
        }

        .error-message {
            color: #dc3545;
            margin-left: 15px;
            font-weight: 500;
            display: none;
        }
        
        .form-footer {
            display: flex;
            align-items: center;
        }

        .stock-level-critical {
            background-color: #f8d7da; 
            border-left: 4px solid #dc3545; 
        }
        .stock-level-low {
            background-color: #f8d7da; 
            border-left: 4px solid #dc3545; 
        }
        .stock-level-medium {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107; 
        }
        .stock-level-high {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .input-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
    <script>
        function validateDate() {
            // Skip validation if we're editing an existing entry
            if (document.querySelector('input[name="log_date"][readonly]')) {
                document.getElementById('date-error').style.display = 'none';
                return true;
            }

            const dateInput = document.querySelector('input[name="log_date"]');
            const dateValue = dateInput.value;
            const existingDates = [];
            const errorElement = document.getElementById('date-error');

            // Get all dates from both tables
            document.querySelectorAll('table tbody tr td:first-child').forEach(cell => {
                const dateText = cell.textContent.trim();
                if (dateText) {
                    // Convert displayed date back to YYYY-MM-DD format
                    const parts = dateText.split(' ');
                    if (parts.length === 3) {
                        const monthNames = ["January", "February", "March", "April", "May", "June",
                                          "July", "August", "September", "October", "November", "December"];
                        const month = (monthNames.indexOf(parts[0]) + 1).toString().padStart(2, '0');
                        const day = parts[1].replace(',', '').padStart(2, '0');
                        const year = parts[2];
                        existingDates.push(`${year}-${month}-${day}`);
                    }
                }
            });

            if (existingDates.includes(dateValue)) {
                errorElement.textContent = 'A record for this date already exist. Please edit the existing record.';
                errorElement.style.display = 'inline';
                dateInput.focus();
                return false;
            } else {
                errorElement.style.display = 'none';
                return true;
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <a href="index.html" class="back-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Dashboard
        </a>

        <h2>Inventory Log: <?= htmlspecialchars($product['product']) ?></h2>
        
        <div class="summary-cards">
            <div class="card">
                <h4>Total Delivered</h4>
                <p><?= formatNumber($total_delivery) ?></p>
            </div>
            <div class="card">
                <h4>Total Used</h4>
                <p><?= formatNumber($total_used) ?></p>
            </div>
            <div class="card <?= getStockLevelClass($current_stock) ?>">
                <h4>Current Stock</h4>
                <p><?= formatNumber($current_stock) ?></p>
            </div>
        </div>

        <form method="post" action="save_combined_log.php<?= $edit_date ? '?edit=1' : '' ?>" onsubmit="return validateDate()">
            <input type="hidden" name="product_id" value="<?= $product_id ?>">
            <h3><?= $edit_date ? "Edit Entry for " . formatDateForDisplay($edit_date) : "New Entry" ?></h3>

            <label>Date</label>
            <input type="date" name="log_date" required value="<?= $edit_date ?? '' ?>" <?= $edit_date ? 'readonly' : '' ?>>

            <fieldset>
                <legend>Deliveries</legend>
                <div class="input-grid">
                    <div>
                        <label>Previous Stocks</label>
                        <input type="number" step="0.01" name="beginning_inventory" placeholder="0" value="<?= isset($delivery_edit['beginning_inventory']) ? formatNumber($delivery_edit['beginning_inventory']) : '' ?>">
                    </div>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div>
                        <label>Delivery <?= $i ?></label>
                        <input type="number" step="0.01" name="delivery_<?= $i ?>" placeholder="0" value="<?= isset($delivery_edit["delivery_$i"]) ? formatNumber($delivery_edit["delivery_$i"]) : '' ?>">
                    </div>
                    <?php endfor; ?>
                </div>
                
                <label>Delivery Note</label>
                <textarea name="delivery_note" placeholder="Add any notes about the delivery..."><?= htmlspecialchars($delivery_edit['delivery_note'] ?? '') ?></textarea>
            </fieldset>

            <fieldset>
                <legend>Usage</legend>
                <div class="input-grid">
                    <div>
                        <label>Used For</label>
                        <input type="text" name="used_for" placeholder="Purpose" value="<?= $usage_edit['used_for'] ?? '' ?>">
                    </div>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div>
                        <label>Used <?= $i ?></label>
                        <input type="number" step="0.01" name="used_<?= $i ?>" placeholder="0" value="<?= isset($usage_edit["used_$i"]) ? formatNumber($usage_edit["used_$i"]) : '' ?>">
                    </div>
                    <?php endfor; ?>
                </div>
                
                <label>Usage Note</label>
                <textarea name="usage_note" placeholder="Add any notes about the usage..."><?= htmlspecialchars($usage_edit['usage_note'] ?? '') ?></textarea>
            </fieldset>

            <div class="form-footer">
                <button type="submit" class="btn"><?= $edit_date ? 'Save Changes' : 'Save Entry' ?></button>
                <span id="date-error" class="error-message"></span>
            </div>
            
        </form>

        <div class="section-title">
            <h3>Delivery History</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Previous Stocks</th>
                    <th>D1</th>
                    <th>D2</th>
                    <th>D3</th>
                    <th>D4</th>
                    <th>D5</th>
                    <th>Total</th>
                    <th>Note</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $delivery_logs->data_seek(0); while ($row = $delivery_logs->fetch_assoc()): ?>
                <tr>
                    <td><?= formatDateForDisplay($row['log_date']) ?></td>
                    <td><?= formatNumber($row['beginning_inventory']) ?></td>
                    <td><?= formatNumber($row['delivery_1']) ?></td>
                    <td><?= formatNumber($row['delivery_2']) ?></td>
                    <td><?= formatNumber($row['delivery_3']) ?></td>
                    <td><?= formatNumber($row['delivery_4']) ?></td>
                    <td><?= formatNumber($row['delivery_5']) ?></td>
                    <td><?= formatNumber($row['delivery_total']) ?></td>
                    <td><?= htmlspecialchars($row['delivery_note'] ?? '') ?></td>
                    <td><a href="?product_id=<?= $product_id ?>&log_date=<?= $row['log_date'] ?>" class="action-link">Edit</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="section-title">
            <h3>Usage History</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Used For</th>
                    <th>U1</th>
                    <th>U2</th>
                    <th>U3</th>
                    <th>U4</th>
                    <th>U5</th>
                    <th>U6</th>
                    <th>Total</th>
                    <th>Note</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $usage_logs->data_seek(0); while ($row = $usage_logs->fetch_assoc()): ?>
                <tr>
                    <td><?= formatDateForDisplay($row['log_date']) ?></td>
                    <td><?= htmlspecialchars($row['used_for']) ?></td>
                    <td><?= formatNumber($row['used_1']) ?></td>
                    <td><?= formatNumber($row['used_2']) ?></td>
                    <td><?= formatNumber($row['used_3']) ?></td>
                    <td><?= formatNumber($row['used_4']) ?></td>
                    <td><?= formatNumber($row['used_5']) ?></td>
                    <td><?= formatNumber($row['used_6']) ?></td>
                    <td><?= formatNumber($row['used_total']) ?></td>
                    <td><?= htmlspecialchars($row['usage_note'] ?? '') ?></td>
                    <td><a href="?product_id=<?= $product_id ?>&log_date=<?= $row['log_date'] ?>" class="action-link">Edit</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>