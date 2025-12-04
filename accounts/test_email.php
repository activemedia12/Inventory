<?php
// test_email.php
require_once '../config/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>PHP Mailer Test Script</h2>";

// Test 1: Basic connectivity
echo "<h3>Test 1: SMTP Connection Test</h3>";
try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';
    
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'reportsjoborder@gmail.com';
    $mail->Password = 'kjyj krfm rkbk qmst';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    
    echo "Attempting to connect...<br>";
    if ($mail->smtpConnect()) {
        echo "<span style='color:green'>✓ Connected successfully!</span><br>";
        $mail->smtpClose();
    }
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Connection failed: " . $e->getMessage() . "</span><br>";
}

// Test 2: Send test email
echo "<h3>Test 2: Send Test Email</h3>";
if (isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'];
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'reportsjoborder@gmail.com';
        $mail->Password = 'kjyj krfm rkbk qmst';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        $mail->setFrom('reportsjoborder@gmail.com', 'Test Sender');
        $mail->addAddress($test_email);
        $mail->Subject = 'Test Email from PHPMailer';
        $mail->Body = 'This is a test email sent at ' . date('Y-m-d H:i:s');
        
        if ($mail->send()) {
            echo "<span style='color:green'>✓ Test email sent to $test_email</span>";
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>✗ Failed to send: " . $mail->ErrorInfo . "</span>";
    }
}

// Test form
echo "
<h3>Send a Test Email</h3>
<form method='post'>
    <input type='email' name='test_email' placeholder='Enter test email address' required>
    <button type='submit'>Send Test Email</button>
</form>
";

// Test 3: Check server configuration
echo "<h3>Test 3: Server Configuration</h3>";
echo "<pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "PHP Mail Function: " . (function_exists('mail') ? 'Enabled' : 'Disabled') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? 'Enabled' : 'Disabled') . "\n";
echo "SMTP in php.ini: " . ini_get('SMTP') . "\n";
echo "</pre>";
?>