<?php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$today = date('Y-m-d');

// ------------------------------
// 1. Fetch today's job orders
// ------------------------------
$stmt = $inventory->prepare("SELECT * FROM job_orders WHERE DATE(log_date) = ?");
$stmt->bind_param("s", $today);
$stmt->execute();
$result = $stmt->get_result();

// ------------------------------
// 2. Generate Excel Report
// ------------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Job Orders");

$headers = [
    'DATE','STATUS','CLIENT BY','CONTACT PERSON','CONTACT NUMBER','CUSTOMER','PROJECT NAME',
    'ORDER QUANTITY','CUT SIZE','# COPIES','PAPER','ORDER QUANTITY','BINDING TYPE','PAPER SIZE',
    'PROJECT NAME','# COPIES','COLOR SEQUENCE','PAPER','Special Instructions'
];
$sheet->fromArray($headers, NULL, 'A1');

$sheet->getStyle('A1:S1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF200']]
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
    ], NULL, 'A'.$rowNum++);
}

foreach (range('A','S') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$excelFilename = "Job_Orders_Report_{$today}.xlsx";
$excelTempPath = '/tmp/' . $excelFilename;

$writer = new Xlsx($spreadsheet);
$writer->save($excelTempPath);

// ------------------------------
// 3. Generate SQL Backup
// ------------------------------
$sqlFilename = "Database_Backup_{$today}.sql";
$sqlTempPath = '/tmp/' . $sqlFilename;

$dbHost = 'localhost';       // Hostinger usually uses localhost
$dbUser = 'u382513771_admin';
$dbPass = 'Amdp@1205';
$dbName = 'u382513771_inventory';

// Attempt using mysqldump first
$dumpPath = '/usr/bin/mysqldump';
$success = false;

if (function_exists('exec') && is_executable($dumpPath)) {
    $dumpCommand = "$dumpPath --host=$dbHost --user=$dbUser --password=$dbPass $dbName > $sqlTempPath";
    exec($dumpCommand, $output, $resultCode);
    if ($resultCode === 0) {
        $success = true;
    }
}

// Fallback: Pure PHP SQL dump
if (!$success) {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die("❌ Database connection failed for SQL export");
    }

    $tables = [];
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }

    $sqlContent = "SET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $table) {
        $res = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $res->fetch_assoc();
        $sqlContent .= $row['Create Table'] . ";\n\n";

        $res = $conn->query("SELECT * FROM `$table`");
        while ($data = $res->fetch_assoc()) {
            $columns = array_map(function($col){ return "`$col`"; }, array_keys($data));
            $values = array_map(function($val){ return "'" . addslashes($val) . "'"; }, array_values($data));
            $sqlContent .= "INSERT INTO `$table` (" . implode(",", $columns) . ") VALUES (" . implode(",", $values) . ");\n";
        }
        $sqlContent .= "\n";
    }

    $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($sqlTempPath, $sqlContent);
}

// ------------------------------
// 4. Send Email
// ------------------------------
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'reportsjoborder@gmail.com';
    $mail->Password = 'kjyj krfm rkbk qmst';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('reportsjoborder@gmail.com', 'AMDP Inventory');
    $mail->addAddress('activemediaprint@gmail.com', 'Active Media');

    $mail->addAttachment($excelTempPath, $excelFilename);
    $mail->addAttachment($sqlTempPath, $sqlFilename);

    $mail->isHTML(true);
    $mail->Subject = "Job Orders Report - ". date('F j, Y');
    $mail->Body = "Good Day!,<br><br>Attached is the job orders report and full database backup for <strong>" . date('F j, Y') . "</strong>.<br><br>Regards,<br>AMDP Inventory System";

    $mail->send();
    echo "✅ Email sent successfully.";

    unlink($excelTempPath);
    unlink($sqlTempPath);

} catch (Exception $e) {
    echo "❌ Email failed: {$mail->ErrorInfo}";
}
