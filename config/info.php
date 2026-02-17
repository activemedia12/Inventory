
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // 🔍 ENABLE FULL DEBUG OUTPUT
    $mail->SMTPDebug  = 4; // 0 = off | 2 = client/server | 4 = verbose
    $mail->Debugoutput = 'html';

    // SMTP settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'amdpreports@gmail.com';
    $mail->Password   = 'odyh qgxv iaez fylf'; // App password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Email
    $mail->setFrom('amdpreports@gmail.com', 'PHPMailer Debug');
    $mail->addAddress('activemediaprint@gmail.com');

    $mail->Subject = 'PHPMailer SMTP Debug Test';
    $mail->Body    = 'This is a PHPMailer debug test email.';

    $mail->send();
    echo '<h2 style="color:green;">✅ EMAIL SENT SUCCESSFULLY</h2>';
} catch (Exception $e) {
    echo '<h2 style="color:red;">❌ EMAIL FAILED</h2>';
    echo '<pre>' . htmlspecialchars($mail->ErrorInfo) . '</pre>';
}
