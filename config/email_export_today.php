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
    'DATE',
    'STATUS',
    'CLIENT BY',
    'CONTACT PERSON',
    'CONTACT NUMBER',
    'CUSTOMER',
    'PROJECT NAME',
    'ORDER QUANTITY',
    'CUT SIZE',
    '# COPIES',
    'PAPER',
    'ORDER QUANTITY',
    'BINDING TYPE',
    'PAPER SIZE',
    'PROJECT NAME',
    '# COPIES',
    'COLOR SEQUENCE',
    'PAPER',
    'Special Instructions'
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
    ], NULL, 'A' . $rowNum++);
}

foreach (range('A', 'S') as $col) {
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

// Fallback: Pure PHP SQL dump with collation fix and view handling
if (!$success) {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die("❌ Database connection failed for SQL export");
    }

    // Set charset to ensure proper encoding
    $conn->set_charset("utf8mb4");

    // Get all tables and views
    $tables = [];
    $views = [];

    // Get regular tables
    $res = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $res->fetch_array()) {
        $tables[] = $row[0];
    }

    // Get views
    $res = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    while ($row = $res->fetch_array()) {
        $views[] = $row[0];
    }

    $sqlContent = "-- Database Backup for $dbName\n";
    $sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sqlContent .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
    $sqlContent .= "SET time_zone = '+00:00';\n\n";

    // Process regular tables (with data)
    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $res = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $res->fetch_assoc();
        $createTable = $row['Create Table'];

        // Replace the unsupported collation with a compatible one
        $createTable = str_replace(
            'utf8mb4_uca1400_ai_ci',
            'utf8mb4_general_ci',
            $createTable
        );

        // Also replace any other potential problematic collations
        $createTable = str_replace(
            'utf8mb4_unicode_ci',
            'utf8mb4_general_ci',
            $createTable
        );

        $sqlContent .= "-- Table structure for table `$table`\n";
        $sqlContent .= "DROP TABLE IF EXISTS `$table`;\n";
        $sqlContent .= $createTable . ";\n\n";

        // Get table data
        $res = $conn->query("SELECT * FROM `$table`");
        if ($res->num_rows > 0) {
            $sqlContent .= "-- Dumping data for table `$table`\n";

            while ($data = $res->fetch_assoc()) {
                $columns = array_map(function ($col) {
                    return "`$col`";
                }, array_keys($data));
                $values = array_map(function ($val) use ($conn) {
                    // Handle NULL values and escape strings
                    if ($val === null) {
                        return 'NULL';
                    }
                    return "'" . $conn->real_escape_string($val) . "'";
                }, array_values($data));

                $sqlContent .= "INSERT INTO `$table` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
            }
            $sqlContent .= "\n";
        }
    }

    // Process views (structure only, no data)
    foreach ($views as $view) {
        // Get CREATE VIEW statement
        $res = $conn->query("SHOW CREATE VIEW `$view`");
        $row = $res->fetch_assoc();
        $createView = $row['Create View'];

        // Replace collation in view definition if needed
        $createView = str_replace(
            'utf8mb4_uca1400_ai_ci',
            'utf8mb4_general_ci',
            $createView
        );

        $sqlContent .= "-- View structure for view `$view`\n";
        $sqlContent .= "DROP VIEW IF EXISTS `$view`;\n";
        $sqlContent .= $createView . ";\n\n";

        // Note: No data dumping for views
    }

    $sqlContent .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // Write the SQL content to file
    if (file_put_contents($sqlTempPath, $sqlContent) === false) {
        die("❌ Failed to write SQL backup file");
    }

    $conn->close();
}

// ------------------------------
// 4. Send Email
// ------------------------------
$mail = new PHPMailer(true);
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

    $mail->addAttachment($excelTempPath, $excelFilename);
    $mail->addAttachment($sqlTempPath, $sqlFilename);

    $mail->isHTML(true);
    $mail->Subject = "Job Orders Report - " . date('F j, Y');
    $mail->Body = "Good Day!,<br><br>Attached is the job orders report and full database backup for <strong>" . date('F j, Y') . "</strong>.<br><br>Regards,<br>AMDP Inventory System";

    $mail->send();
    echo "✅ Email sent successfully.";

    // Clean up temp files
    if (file_exists($excelTempPath)) {
        unlink($excelTempPath);
    }
    if (file_exists($sqlTempPath)) {
        unlink($sqlTempPath);
    }
} catch (Exception $e) {
    echo "❌ Email failed: {$mail->ErrorInfo}";
}
