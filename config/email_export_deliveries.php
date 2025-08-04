<?php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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

// === Sheet 1: Paper Deliveries ===
$paperSheet = $spreadsheet->getActiveSheet();
$paperSheet->setTitle("Paper Deliveries");

$paperHeaders = [
  'Delivery Date', 'Product Type', 'Product Group', 'Color', 'Reams Delivered',
  'Unit', 'Amount per Ream', 'Supplier', 'Note'
];
$paperSheet->fromArray($paperHeaders, NULL, 'A1');
$paperSheet->getStyle('A1:I1')->applyFromArray([
  'font' => ['bold' => true],
  'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '90EE90']],
]);

$query = "
  SELECT dl.*, p.product_type, p.product_group, p.product_name, u.username
  FROM delivery_logs dl
  JOIN products p ON dl.product_id = p.id
  LEFT JOIN users u ON dl.created_by = u.id
  WHERE DATE(dl.delivery_date) BETWEEN ? AND ?
  ORDER BY dl.delivery_date ASC
";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$row = 2;
while ($r = $result->fetch_assoc()) {
  $paperSheet->fromArray([
    date('F j, Y', strtotime($r['delivery_date'])),
    $r['product_type'],
    $r['product_group'],
    $r['product_name'],
    $r['delivered_reams'],
    $r['unit'],
    $r['amount_per_ream'],
    $r['supplier_name'],
    $r['delivery_note']
  ], null, 'A' . $row++);
}
foreach (range('A', 'I') as $col) {
  $paperSheet->getColumnDimension($col)->setAutoSize(true);
}

// === Sheet 2: Insuance Deliveries ===
$insuanceSheet = $spreadsheet->createSheet();
$insuanceSheet->setTitle("Insuance Deliveries");

$insuanceHeaders = [
  'Delivery Date', 'Insuance Item', 'Quantity Delivered',
  'Unit', 'Amount per Unit', 'Supplier', 'Note'
];
$insuanceSheet->fromArray($insuanceHeaders, NULL, 'A1');
$insuanceSheet->getStyle('A1:G1')->applyFromArray([
  'font' => ['bold' => true],
  'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFD700']],
]);

$iq = "
  SELECT idl.*, u.username
  FROM insuance_delivery_logs idl
  LEFT JOIN users u ON idl.created_by = u.id
  WHERE DATE(idl.delivery_date) BETWEEN ? AND ?
  ORDER BY idl.delivery_date ASC
";
$istmt = $mysqli->prepare($iq);
$istmt->bind_param("ss", $start_date, $end_date);
$istmt->execute();
$insResult = $istmt->get_result();

$row = 2;
while ($r = $insResult->fetch_assoc()) {
  $insuanceSheet->fromArray([
    date('F j, Y', strtotime($r['delivery_date'])),
    $r['insuance_name'],
    $r['delivered_quantity'],
    $r['unit'],
    $r['amount_per_unit'],
    $r['supplier_name'],
    $r['delivery_note']
  ], null, 'A' . $row++);
}
foreach (range('A', 'G') as $col) {
  $insuanceSheet->getColumnDimension($col)->setAutoSize(true);
}

// === Save file to temp path ===
$filename = "Delivery_Report_" . date('Ymd_His') . ".xlsx";
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
  $mail->Username   = 'reportsjoborder@gmail.com';
  $mail->Password   = 'kjyj krfm rkbk qmst'; // Consider storing securely
  $mail->SMTPSecure = 'tls';
  $mail->Port       = 587;

  $mail->setFrom('reportsjoborder@gmail.com', 'AMDP Inventory');
  $mail->addAddress('activemediaprint@gmail.com', 'Active Media');

  $mail->addAttachment($tempPath, $filename);

  $mail->isHTML(true);
  $mail->Subject = "Requested Delivery Report from $startFormatted to $endFormatted";
  $mail->Body    = "Good day!<br><br>Attached is the requested <b>Delivery Report</b> (including Paper and Insuance Deliveries).<br><br>Regards,<br>AMDP Inventory System";

  $mail->send();
  unlink($tempPath);

    ?>

      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <title>Delivery Request</title>
        <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
          body {
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
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
            max-width: 500px;
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
            padding: 12px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            margin: 15px 0;
            font-weight: 500;
          }

          .export-button {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
          }
          
          .export-close-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
          }
          
          .export-close-btn:hover {
            background: rgba(244, 67, 54, 0.2);
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
        </style>
      </head>
      <body>
        <div class="export-success-box">
          <div class="export-progress-bar"></div>
          <div class="export-success-icon">✔</div>
          <h1 class="export-success-title">REQUEST ACCEPTED</h1>
          <p class="export-success-message">The <strong>Delivery</strong> reports will be <strong>emailed</strong> to <em>activemediaprint@gmail.com</em>.</p>
          
          <div class="export-success-date-range">
            <?= date('F j, Y', strtotime($startFormatted)) ?> to <?= date('F j, Y', strtotime($endFormatted)) ?>
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
          const countdownElement = document.getElementById('export-countdown');
          const countdownInterval = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
              clearInterval(countdownInterval);
              window.close();
            }
          }, 1000);
          
          // Close window after 5 seconds
          setTimeout(() => {
            window.close();
          }, 30000);
        </script>
      </body>
      </html>

    <?php

} catch (Exception $e) {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Delivery Request</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      overflow: hidden;
      font-family: 'Poppins', sans-serif;
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
      max-width: 500px;
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
    }

    .export-button {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .export-close-btn {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
      background: rgba(244, 67, 54, 0.1);
      color: #f44336;
      border: 1px solid #f44336;
    }
    
    .export-close-btn:hover {
      background: rgba(244, 67, 54, 0.2);
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
    <div class="export-error-icon">✖</div>
    <h1 class="export-error-title">REQUEST FAILED</h1>
    <p class="export-error-message">There's something wrong. Please try again</p>
    <br>
    <p class="export-error-message">If the problem continues, please contact admin.</p>
    
    <div class="export-error-details">
      Error: {$mail->ErrorInfo}
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
    const countdownElement = document.getElementById('export-countdown');
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
</html>
HTML;
}
