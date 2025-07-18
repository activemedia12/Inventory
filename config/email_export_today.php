<?php
// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Autoload dependencies
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Step 1: Fetch today's job orders
$today = date('Y-m-d');
$stmt = $mysqli->prepare("SELECT * FROM job_orders WHERE DATE(log_date) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

// Step 2: Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Job Orders");

// Header row
$headers = [
    'DATE', 'STATUS', 'CLIENT BY', 'CONTACT PERSON', 'CONTACT NUMBER', 'CUSTOMER', 'PROJECT NAME',
    'ORDER QUANTITY', 'CUT SIZE', '# COPIES', 'PAPER', 'ORDER QUANTITY', 'BINDING TYPE', 'CUT SIZE', 'PROJECT NAME', '# COPIES', 'COLOR SEQUENCE',
    'PAPER',
    'Special Instructions',
];
$sheet->fromArray($headers, NULL, 'A1');

// Apply styles to header
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF200']],
];
$sheet->getStyle('A1:S1')->applyFromArray($headerStyle);

// Step 3: Fill data rows
$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->fromArray([
        $row['log_date'],
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
        $row['product_size'],
        $row['project_name'],
        $row['copies_per_set'],
        $row['paper_sequence'],
        $row['paper_type'],
        $row['special_instructions'],
    ], NULL, 'A' . $rowNum++);
}

// Step 4: Auto-size columns
foreach (range('A', 'S') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Step 5: Save Excel file
$filename = "Job_Orders_Report_{$today}.xlsx";
$tempPath = sys_get_temp_dir() . '/' . $filename;

$writer = new Xlsx($spreadsheet);
$writer->save($tempPath);

// Step 6: Send email with Excel attachment
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'reportsjoborder@gmail.com';
    $mail->Password   = 'kjyj krfm rkbk qmst'; // ✅ Gmail app password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('reportsjoborder@gmail.com', 'Job Order Report');

    // Multiple recipients (add as many as you like)
    $mail->addAddress('activemediaprint@gmail.com', 'Active Media');
    // $mail->addAddress('another@example.com', 'Another Recipient');

    $mail->addAttachment($tempPath, $filename);

    $mail->isHTML(true);
    $mail->Subject = "Job Orders Report - ". date('F j, Y');
    $mail->Body    = "Good Day!,<br><br>Attached is the job orders report for <strong>" . date('F j, Y') . "</strong>.<br><br>Regards,<br>AMDP Inventory System";

    $mail->send();
    echo "✅ Email sent successfully.";

    unlink($tempPath); // Delete the file after sending

} catch (Exception $e) {
    echo "❌ Email failed: {$mail->ErrorInfo}";
}
