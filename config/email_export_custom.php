<?php
// email_export_custom.php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Step 1: Capture date range inputs
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Step 2: Validate and build query
if (!$start_date || !$end_date) {
    die("❌ Please provide both start and end dates.");
}

$query = "SELECT * FROM job_orders WHERE DATE(log_date) BETWEEN ? AND ? ORDER BY log_date ASC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Step 3: Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Job Orders");

$headers = [
    'DATE', 'STATUS', 'CLIENT BY', 'CONTACT PERSON', 'CONTACT NUMBER', 'CUSTOMER', 'PROJECT NAME',
    'ORDER QUANTITY', 'CUT SIZE', '# COPIES', 'PAPER', 'ORDER QUANTITY', 'BINDING TYPE', 'PAPER SIZE', 'PROJECT NAME',
    '# COPIES', 'COLOR SEQUENCE', 'PAPER', 'Special Instructions',
];
$sheet->fromArray($headers, NULL, 'A1');

$sheet->getStyle('A1:S1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF200']],
]);

$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->fromArray([
        date('F j, Y', strtotime($row['log_date'])),
        '',
        $row['client_by'],
        $row['contact_person'],
        $row['contact_number'],
        $row['client_name'],
        $row['project_name'],
        $row['quantity'],
        $row['product_size'],
        $row['copies_per_set'],
        $row['paper_type'],
        $row['quantity'],
        $row['binding_type'],
        $row['paper_size'],
        $row['project_name'],
        $row['copies_per_set'],
        $row['paper_sequence'],
        $row['paper_type'],
        $row['special_instructions'],
    ], NULL, 'A' . $rowNum++);
}

foreach (range('A', 'S') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = "Job_Orders_Report_" . date('Ymd_His') . ".xlsx";
$tempPath = sys_get_temp_dir() . '/' . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($tempPath);

// Step 4: Send Email
$mail = new PHPMailer(true);

$start = new DateTime($start_date);
$end   = new DateTime($end_date);

$startFormatted = $start->format('F j, Y');
$endFormatted   = $end->format('F j, Y');

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'reportsjoborder@gmail.com';
    $mail->Password   = 'kjyj krfm rkbk qmst'; // App password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('reportsjoborder@gmail.com', 'AMDP Inventory');
    $mail->addAddress('activemediaprint@gmail.com', 'Active Media');

    $mail->addAttachment($tempPath, $filename);

    $mail->isHTML(true);
    $mail->Subject = "Requested JO copies from $startFormatted to $endFormatted";
    $mail->Body    = "Good day!<br><br>Attached is the requested job orders report based on your selected dates.<br><br>Regards,<br>AMDP Inventory System";

    $mail->send();
    unlink($tempPath);
    ?>

      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <title>J.O. Request</title>
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
          <p class="export-success-message">The <strong>Job Order</strong> reports will be <strong>emailed</strong> to <em>activemediaprint@gmail.com</em>.</p>
          
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

}catch (Exception $e) {
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>J.O. Request</title>
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

