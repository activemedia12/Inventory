<?php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// === Validate input ===
$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;

if (!$start_date || !$end_date) {
    die("❌ Please provide both start and end dates.");
}

// === Create Spreadsheet ===
$spreadsheet = new Spreadsheet();

// === Sheet 1: Job Order Expenses Summary ===
$summarySheet = $spreadsheet->getActiveSheet();
$summarySheet->setTitle("Expenses Summary");

// Title and Date Range
$summarySheet->mergeCells('A1:S1');
$summarySheet->setCellValue('A1', 'JOB ORDER EXPENSES REPORT');
$summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$summarySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$summarySheet->mergeCells('A2:S2');
$summarySheet->setCellValue('A2', 'Date Range: ' . date('F j, Y', strtotime($start_date)) . ' to ' . date('F j, Y', strtotime($end_date)));
$summarySheet->getStyle('A2')->getFont()->setItalic(true);
$summarySheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$summarySheet->mergeCells('A3:S3');
$summarySheet->setCellValue('A3', 'Generated on: ' . date('F j, Y - g:i A'));
$summarySheet->getStyle('A3')->getFont()->setItalic(true);
$summarySheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Column Headers
$summaryHeaders = [
    'Job ID',
    'Date',
    'Client',
    'Project',
    'Quantity',
    'Sets',
    'Product Size',
    'Paper Type',
    'Paper Layers',
    'Printing Type',
    'Paper Cost',
    'Printing Cost',
    'Labor Cost',
    'Other Expenses (25%)',
    'Paper Spoilage (10%)',
    'Total Expenses',
    'Total Cost',
    'Profit',
    'Profit %'
];
$summarySheet->fromArray($summaryHeaders, NULL, 'A5');
$summarySheet->getStyle('A5:S5')->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

// Debug: Check connection
if (!$inventory) {
    die("❌ Database connection failed.");
}

// Fetch job order expenses data
$query = "
    SELECT 
        jo.id,
        jo.log_date,
        jo.client_name,
        jo.project_name,
        jo.quantity,
        jo.number_of_sets,
        jo.product_size,
        jo.paper_type,
        jo.paper_sequence,
        jo.printing_type,
        jo.grand_total,
        jo.total_cost,
        jo.printing_cost,
        jo.other_expenses,
        jo.paper_spoilage
    FROM job_orders jo
    WHERE DATE(jo.log_date) BETWEEN ? AND ?
    AND jo.grand_total > 0
    ORDER BY jo.log_date ASC, jo.id ASC
";

$stmt = $inventory->prepare($query);
if (!$stmt) {
    die("❌ Prepare failed: " . $inventory->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$totalJobs = $result->num_rows;

// Check if we have results
if ($totalJobs === 0) {
    $summarySheet->mergeCells('A6:S6');
    $summarySheet->setCellValue('A6', 'No job orders found in the selected date range.');
    $summarySheet->getStyle('A6')->getFont()->setItalic(true)->getColor()->setARGB('FF999999');
    $summarySheet->getStyle('A6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $total_revenue = 0;
    $total_expenses = 0;
    $total_profit = 0;
} else {
    $row = 6;
    $total_paper_cost = 0;
    $total_printing_cost = 0;
    $total_labor_cost = 0;
    $total_other_expenses = 0;
    $total_paper_spoilage = 0;
    $total_expenses = 0;
    $total_revenue = 0;
    $total_profit = 0;

    while ($job = $result->fetch_assoc()) {
        // Calculate labor cost from job_sessions table
        $labor_query = "SELECT SUM(cost) as total_labor FROM job_sessions WHERE job_id = ?";
        $labor_stmt = $inventory->prepare($labor_query);
        $labor_stmt->bind_param("i", $job['id']);
        $labor_stmt->execute();
        $labor_result = $labor_stmt->get_result();
        $labor_data = $labor_result->fetch_assoc();
        $labor_cost = $labor_data['total_labor'] ?? 0;
        $labor_stmt->close();

        // Get printing cost
        $printing_cost = $job['printing_cost'] ?? 0;

        // Calculate paper cost (estimated as grand_total - labor - printing)
        $paper_cost = max(0, $job['grand_total'] - $labor_cost - $printing_cost);

        // Calculate other expenses
        $other_expenses = ($job['other_expenses'] == 1) ? $job['grand_total'] * 0.25 : 0;
        $paper_spoilage = ($job['paper_spoilage'] == 1) ? $paper_cost * 0.10 : 0;

        // Total expenses (should match grand_total)
        $total_expense = $job['grand_total'];

        // Get total cost (revenue)
        $total_cost = $job['total_cost'] ?? 0;
        $profit = $total_cost - $total_expense;
        $profit_percent = $total_expense > 0 ? ($profit / $total_expense) * 100 : 0;

        // Count paper layers
        $paper_layers = !empty($job['paper_sequence']) ? count(explode(',', $job['paper_sequence'])) : 0;

        // Accumulate totals
        $total_paper_cost += $paper_cost;
        $total_printing_cost += $printing_cost;
        $total_labor_cost += $labor_cost;
        $total_other_expenses += $other_expenses;
        $total_paper_spoilage += $paper_spoilage;
        $total_expenses += $total_expense;
        $total_revenue += $total_cost;
        $total_profit += $profit;

        $summarySheet->fromArray([
            $job['id'],
            date('m/d/Y', strtotime($job['log_date'])),
            $job['client_name'],
            $job['project_name'],
            $job['quantity'],
            $job['number_of_sets'],
            $job['product_size'],
            $job['paper_type'],
            $paper_layers,
            $job['printing_type'] ?? 'N/A',
            number_format($paper_cost, 2),
            number_format($printing_cost, 2),
            number_format($labor_cost, 2),
            number_format($other_expenses, 2),
            number_format($paper_spoilage, 2),
            number_format($total_expense, 2),
            number_format($total_cost, 2),
            number_format($profit, 2),
            number_format($profit_percent, 2) . '%'
        ], null, 'A' . $row);

        $row++;
    }

    // Apply alignment styles to all data rows
    // Left align text columns (A-J)
    $summarySheet->getStyle('A6:J' . ($row - 1))->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);

    // Right align number columns (K-S)
    $summarySheet->getStyle('K6:S' . ($row - 1))->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    // Color code profit column
    for ($i = 6; $i < $row; $i++) {
        $profitValue = $summarySheet->getCell('R' . $i)->getValue();
        if (is_numeric(str_replace(['₱', ',', '%'], '', $profitValue))) {
            $profitNum = floatval(str_replace(['₱', ',', '%'], '', $profitValue));
            if ($profitNum > 0) {
                $summarySheet->getStyle('R' . $i)->getFont()->getColor()->setARGB('FF006600');
            } elseif ($profitNum < 0) {
                $summarySheet->getStyle('R' . $i)->getFont()->getColor()->setARGB('FFCC0000');
            }
        }
    }

    // Add Summary Totals
    $summaryRow = $row + 2;
    $summarySheet->mergeCells('A' . $summaryRow . ':J' . $summaryRow);
    $summarySheet->setCellValue('A' . $summaryRow, 'TOTALS SUMMARY');
    $summarySheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(14);
    $summarySheet->getStyle('A' . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    $summaryRow++;
    $summarySheet->setCellValue('A' . $summaryRow, 'Total Paper Cost:');
    $summarySheet->setCellValue('B' . $summaryRow, '₱' . number_format($total_paper_cost, 2));
    $summarySheet->setCellValue('C' . $summaryRow, 'Total Printing:');
    $summarySheet->setCellValue('D' . $summaryRow, '₱' . number_format($total_printing_cost, 2));
    $summarySheet->setCellValue('E' . $summaryRow, 'Total Labor:');
    $summarySheet->setCellValue('F' . $summaryRow, '₱' . number_format($total_labor_cost, 2));

    $summarySheet->getStyle('A' . $summaryRow . ':F' . $summaryRow)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);

    $summaryRow++;
    $summarySheet->setCellValue('A' . $summaryRow, 'Other Expenses:');
    $summarySheet->setCellValue('B' . $summaryRow, '₱' . number_format($total_other_expenses, 2));
    $summarySheet->setCellValue('C' . $summaryRow, 'Paper Spoilage:');
    $summarySheet->setCellValue('D' . $summaryRow, '₱' . number_format($total_paper_spoilage, 2));
    $summarySheet->setCellValue('E' . $summaryRow, 'Total Expenses:');
    $summarySheet->setCellValue('F' . $summaryRow, '₱' . number_format($total_expenses, 2));

    $summarySheet->getStyle('A' . $summaryRow . ':F' . $summaryRow)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);

    $summaryRow++;
    $summarySheet->setCellValue('A' . $summaryRow, 'Total Revenue:');
    $summarySheet->setCellValue('B' . $summaryRow, '₱' . number_format($total_revenue, 2));
    $summarySheet->setCellValue('C' . $summaryRow, 'Total Profit:');
    $profitCell = 'D' . $summaryRow;
    $summarySheet->setCellValue($profitCell, '₱' . number_format($total_profit, 2));

    $summarySheet->getStyle('A' . $summaryRow . ':D' . $summaryRow)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);

    // Color code total profit
    if ($total_profit > 0) {
        $summarySheet->getStyle($profitCell)->getFont()->getColor()->setARGB('FF006600');
    } elseif ($total_profit < 0) {
        $summarySheet->getStyle($profitCell)->getFont()->getColor()->setARGB('FFCC0000');
    }
}
$stmt->close();

// Auto-size columns
foreach (range('A', 'S') as $col) {
    $summarySheet->getColumnDimension($col)->setAutoSize(true);
}

// === Sheet 2: Detailed Labor Sessions ===
$laborSheet = $spreadsheet->createSheet();
$laborSheet->setTitle("Labor Sessions");

$laborHeaders = [
    'Job ID',
    'Client',
    'Project',
    'Task',
    'Date',
    'Start Time',
    'End Time',
    'Break (mins)',
    'Hours',
    'Rate',
    'Cost'
];
$laborSheet->fromArray($laborHeaders, NULL, 'A1');
$laborSheet->getStyle('A1:K1')->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFA500']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

// Fetch labor sessions
$laborQuery = "
    SELECT 
        js.job_id,
        jo.client_name,
        jo.project_name,
        js.task_name,
        DATE(js.created_at) as session_date,
        TIME(js.start_time) as start_time,
        TIME(js.end_time) as end_time,
        js.break_minutes,
        js.hours,
        mr.hourly_rate,
        js.cost
    FROM job_sessions js
    JOIN job_orders jo ON js.job_id = jo.id
    JOIN manpower_rates mr ON js.task_name = mr.task_name
    WHERE DATE(jo.log_date) BETWEEN ? AND ?
    ORDER BY js.job_id, js.created_at ASC
";
$laborStmt = $inventory->prepare($laborQuery);
$laborStmt->bind_param("ss", $start_date, $end_date);
$laborStmt->execute();
$laborResult = $laborStmt->get_result();

if ($laborResult->num_rows === 0) {
    $laborSheet->mergeCells('A2:K2');
    $laborSheet->setCellValue('A2', 'No labor sessions found in the selected date range.');
    $laborSheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setARGB('FF999999');
    $laborSheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
    $laborRow = 2;
    while ($session = $laborResult->fetch_assoc()) {
        $laborSheet->fromArray([
            $session['job_id'],
            $session['client_name'],
            $session['project_name'],
            $session['task_name'],
            date('m/d/Y', strtotime($session['session_date'])),
            $session['start_time'],
            $session['end_time'],
            $session['break_minutes'],
            number_format($session['hours'], 2),
            number_format($session['hourly_rate'], 2),
            number_format($session['cost'], 2)
        ], null, 'A' . $laborRow);
        $laborRow++;
    }

    // Apply alignment to labor data
    // Left align text columns (A-E)
    $laborSheet->getStyle('A2:E' . ($laborRow - 1))->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
    ]);

    // Center align time columns (F-H)
    $laborSheet->getStyle('F2:H' . ($laborRow - 1))->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);

    // Right align number columns (I-K)
    $laborSheet->getStyle('I2:K' . ($laborRow - 1))->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
}
$laborStmt->close();

foreach (range('A', 'K') as $col) {
    $laborSheet->getColumnDimension($col)->setAutoSize(true);
}

// === Sheet 3: Job Order Details ===
$detailsSheet = $spreadsheet->createSheet();
$detailsSheet->setTitle("Job Details");

$detailsHeaders = [
    'Job ID',
    'Date',
    'Client',
    'Project',
    'Quantity',
    'Sets',
    'Product Size',
    'Paper Size',
    'Paper Type',
    'Paper Sequence',
    'Binding Type',
    'Printing Type',
    'Special Instructions'
];
$detailsSheet->fromArray($detailsHeaders, NULL, 'A1');
$detailsSheet->getStyle('A1:M1')->applyFromArray([
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '90EE90']],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_LEFT,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

// Fetch job details
$detailsQuery = "
    SELECT 
        id,
        log_date,
        client_name,
        project_name,
        quantity,
        number_of_sets,
        product_size,
        paper_size,
        paper_type,
        paper_sequence,
        binding_type,
        printing_type,
        special_instructions
    FROM job_orders 
    WHERE DATE(log_date) BETWEEN ? AND ?
    ORDER BY log_date ASC, id ASC
";
$detailsStmt = $inventory->prepare($detailsQuery);
$detailsStmt->bind_param("ss", $start_date, $end_date);
$detailsStmt->execute();
$detailsResult = $detailsStmt->get_result();

if ($detailsResult->num_rows === 0) {
    $detailsSheet->mergeCells('A2:M2');
    $detailsSheet->setCellValue('A2', 'No job orders found in the selected date range.');
    $detailsSheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setARGB('FF999999');
    $detailsSheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
    $detailsRow = 2;
    while ($job = $detailsResult->fetch_assoc()) {
        $detailsSheet->fromArray([
            $job['id'],
            date('m/d/Y', strtotime($job['log_date'])),
            $job['client_name'],
            $job['project_name'],
            $job['quantity'],
            $job['number_of_sets'],
            $job['product_size'],
            $job['paper_size'],
            $job['paper_type'],
            $job['paper_sequence'],
            $job['binding_type'],
            $job['printing_type'] ?? 'N/A',
            $job['special_instructions']
        ], null, 'A' . $detailsRow);
        $detailsRow++;
    }

    // Apply alignment to job details
    // Left align all columns
    $detailsSheet->getStyle('A2:M' . ($detailsRow - 1))->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        'wrapText' => true
    ]);

    // Right align quantity and sets columns
    $detailsSheet->getStyle('E2:F' . ($detailsRow - 1))->applyFromArray([
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
}
$detailsStmt->close();

foreach (range('A', 'M') as $col) {
    $detailsSheet->getColumnDimension($col)->setAutoSize(true);
}

// === Save file to temp path ===
$filename = "Job_Expenses_Report_" . date('Ymd_His') . ".xlsx";
$tempPath = sys_get_temp_dir() . '/' . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($tempPath);

// === Send Email ===
$mail = new PHPMailer(true);
$startFormatted = (new DateTime($start_date))->format('F j, Y');
$endFormatted   = (new DateTime($end_date))->format('F j, Y');

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'amdpreports@gmail.com';
    $mail->Password   = 'odyh qgxv iaez fylf'; // App password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('amdpreports@gmail.com', 'AMDP Reports');
    $mail->addAddress('activemediaprint@gmail.com', 'Active Media');
    $mail->addCC('amdpreports@gmail.com');

    $mail->addAttachment($tempPath, $filename);

    $mail->isHTML(true);
    $mail->Subject = "Job Order Expenses Report: {$startFormatted} to {$endFormatted}";

    $profitClass = $total_profit >= 0 ? 'profit-positive' : 'profit-negative';
    $profitIcon = $total_profit >= 0 ? '📈' : '📉';

    $mail->Body    = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Job Order Expenses Report</h2>
            <p>Good day!</p>
            <p>Attached is the <b>Job Order Expenses Report</b> for the period:</p>
            <p><strong>{$startFormatted} to {$endFormatted}</strong></p>
            
            <h3>Report Summary:</h3>
            <ul>
                <li><strong>Total Job Orders:</strong> {$totalJobs}</li>
                <li><strong>Total Revenue:</strong> ₱" . number_format($total_revenue, 2) . "</li>
                <li><strong>Total Expenses:</strong> ₱" . number_format($total_expenses, 2) . "</li>
                <li><strong>Total Profit:</strong> <span style='color: " . ($total_profit >= 0 ? 'green' : 'red') . ";'>₱" . number_format($total_profit, 2) . "</span></li>
            </ul>
            
            <h3>Report Contents:</h3>
            <ul>
                <li><strong>Sheet 1:</strong> Expenses Summary - Overview with profit calculation</li>
                <li><strong>Sheet 2:</strong> Labor Sessions - Detailed labor hours and costs</li>
                <li><strong>Sheet 3:</strong> Job Details - Complete job specifications</li>
            </ul>
            
            <p>Please review the attached Excel file for complete details.</p>
            
            <p>Regards,<br>
            AMDP Inventory</p>
        </body>
        </html>
    ";

    $mail->send();
    unlink($tempPath);

    // Success HTML response
    echo generateSuccessHTML($startFormatted, $endFormatted, $total_revenue, $total_expenses, $total_profit, $totalJobs);
} catch (Exception $e) {
    echo generateErrorHTML($mail->ErrorInfo);
}

// Helper functions for HTML responses
function generateSuccessHTML($startDate, $endDate, $revenue, $expenses, $profit, $totalJobs)
{
    $profitClass = $profit >= 0 ? 'profit-positive' : 'profit-negative';
    $profitIcon = $profit >= 0 ? '📈' : '📉';

    // Format numbers for display
    $formattedRevenue = number_format($revenue, 2);
    $formattedExpenses = number_format($expenses, 2);
    $formattedProfit = number_format($profit, 2);

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expenses Export Success</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            overflow: hidden;
            font-family: \'Poppins\', sans-serif;
            text-align: center;
            padding: 20px;
            background: rgba(40, 167, 69, 0.2);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .export-success-box {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .export-success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .export-success-title {
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .export-success-message {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .export-success-date-range {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
            font-size: 18px;
        }
        
        .financial-summary {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .financial-item {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 16px;
        }
        
        .profit-positive {
            color: #28a745;
            font-weight: bold;
        }
        
        .profit-negative {
            color: #dc3545;
            font-weight: bold;
        }
        
        .export-button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }
        
        .export-close-btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
            font-weight: 500;
        }
        
        .export-close-btn:hover {
            background: rgba(244, 67, 54, 0.2);
            transform: translateY(-2px);
        }
        
        .export-countdown {
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
        
        .export-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: #28a745;
            width: 100%;
            animation: exportProgress 30s linear forwards;
        }
        
        @keyframes exportProgress {
            from { width: 100%; }
            to { width: 0%; }
        }
        
        .report-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 5px;
            flex: 1;
            min-width: 150px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="export-success-box">
        <div class="export-progress-bar"></div>
        <div class="export-success-icon">✅</div>
        <h1 class="export-success-title">EXPENSES REPORT EXPORTED</h1>
        <p class="export-success-message">The <strong>Job Order Expenses Report</strong> has been generated and emailed to <em>activemediaprint@gmail.com</em>.</p>
        
        <div class="export-success-date-range">
            📅 Report Period: ' . $startDate . ' to ' . $endDate . '
        </div>
        
        <div class="report-stats">
            <div class="stat-box">
                <div class="stat-label">Total Jobs</div>
                <div class="stat-value">' . $totalJobs . '</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">₱' . $formattedRevenue . '</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Expenses</div>
                <div class="stat-value">₱' . $formattedExpenses . '</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Total Profit</div>
                <div class="stat-value ' . $profitClass . '">' . $profitIcon . ' ₱' . $formattedProfit . '</div>
            </div>
        </div>
        
        <div class="financial-summary">
            <h4 style="margin-top: 0; color: #2c3e50;">📊 Financial Summary:</h4>
            <div class="financial-item">
                <span>Total Job Orders:</span>
                <span>' . $totalJobs . '</span>
            </div>
            <div class="financial-item">
                <span>Total Revenue:</span>
                <span>₱' . $formattedRevenue . '</span>
            </div>
            <div class="financial-item">
                <span>Total Expenses:</span>
                <span>₱' . $formattedExpenses . '</span>
            </div>
            <div class="financial-item">
                <span>Total Profit:</span>
                <span class="' . $profitClass . '">₱' . $formattedProfit . '</span>
            </div>
        </div>

        <div class="export-button">
            <button class="export-close-btn" onclick="window.close()">
                Close Window
            </button>
        </div>
        
        <div class="export-countdown">
            This window will close automatically in <span id="export-countdown">30</span> seconds
        </div>
    </div>

    <script>
        // Countdown timer
        let seconds = 30;
        const countdownElement = document.getElementById(\'export-countdown\');
        const countdownInterval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdownInterval);
                window.close();
            }
        }, 1000);
        
        // Close window after 30 seconds
        setTimeout(() => {
            window.close();
        }, 30000);
    </script>
</body>
</html>';
}

function generateErrorHTML($error)
{
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Error</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            overflow: hidden;
            font-family: \'Poppins\', sans-serif;
            text-align: center;
            padding: 20px;
            background: rgba(244, 67, 54, 0.2);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .export-error-box {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .export-error-icon {
            font-size: 60px;
            color: #f44336;
            margin-bottom: 20px;
        }
        
        .export-error-title {
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .export-error-message {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .export-error-details {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-family: monospace;
            color: #f44336;
            max-height: 150px;
            overflow-y: auto;
            text-align: left;
            font-size: 12px;
        }

        .export-button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }
        
        .export-close-btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
            font-weight: 500;
        }
        
        .export-close-btn:hover {
            background: rgba(244, 67, 54, 0.2);
            transform: translateY(-2px);
        }
        
        .export-countdown {
            margin-top: 20px;
            color: #6c757d;
            font-size: 14px;
        }
        
        .export-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: #f44336;
            width: 100%;
            animation: exportProgress 30s linear forwards;
        }
        
        @keyframes exportProgress {
            from { width: 100%; }
            to { width: 0%; }
        }
    </style>
</head>
<body>
    <div class="export-error-box">
        <div class="export-progress-bar"></div>
        <div class="export-error-icon">❌</div>
        <h1 class="export-error-title">EXPORT FAILED</h1>
        <p class="export-error-message">There was an error generating the expenses report. Please try again.</p>
        <p class="export-error-message">If the problem continues, please contact the system administrator.</p>
        
        <div class="export-error-details">
            <strong>Error Details:</strong><br>
            ' . htmlspecialchars($error) . '
        </div>

        <div class="export-button">
            <button class="export-close-btn" onclick="window.close()">
                Close Window
            </button>
        </div>
        
        <div class="export-countdown">
            This window will close automatically in <span id="export-countdown">30</span> seconds
        </div>
    </div>

    <script>
        // Countdown timer
        let seconds = 30;
        const countdownElement = document.getElementById(\'export-countdown\');
        const countdownInterval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdownInterval);
                window.close();
            }
        }, 1000);
        
        // Close window after 30 seconds
        setTimeout(() => {
            window.close();
        }, 30000);
    </script>
</body>
</html>';
}
