<?php

/**
 * test_email.php — SMTP connectivity/send test tool.
 *
 * SECURITY NOTES vs. the original version:
 * 1. No hardcoded passwords — pulled from config.php.
 * 2. Requires a secret token (like info.php) so it can't be triggered or
 *    scraped for credentials by anyone who finds the URL.
 * 3. Verbose SMTPDebug output is off by default — level-4 debug prints the
 *    authentication exchange to the page, which is effectively the same as
 *    posting the password.
 * 4. The "send test email to any address" form is gated behind the same
 *    token, so this can't be used as a free relay to send mail from your
 *    accounts to arbitrary addresses.
 *
 * STRONGLY RECOMMENDED: delete this file once you've confirmed mail sending
 * works. Test/debug scripts don't belong in a production web root.
 */

require_once '../config/vendor/autoload.php';
require_once '../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Simple access guard ---
// Set AMDP_DEBUG_TOKEN as an environment variable (or SECRET_DEBUG_TOKEN in
// secrets.php) to a long random string, then visit: test_email.php?token=...
$expectedToken = amdp_config('AMDP_DEBUG_TOKEN', 'SECRET_DEBUG_TOKEN', null);
$providedToken = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$expectedToken || !hash_equals((string) $expectedToken, (string) $providedToken)) {
    http_response_code(403);
    die('❌ Not authorized. This tool requires a valid ?token= parameter.');
}

echo "<h2>PHP Mailer Test Script</h2>";

// Test 1: Basic connectivity (using the "verify" account)
echo "<h3>Test 1: SMTP Connection Test</h3>";
try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = VERIFY_SMTP_USERNAME;
    $mail->Password = VERIFY_SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;

    echo "Attempting to connect...<br>";
    if ($mail->smtpConnect()) {
        echo "<span style='color:green'>✓ Connected successfully!</span><br>";
        $mail->smtpClose();
    }
} catch (Exception $e) {
    echo "<span style='color:red'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

// Test 2: Send test email (using the "reports" account)
echo "<h3>Test 2: Send Test Email</h3>";
if (isset($_POST['test_email'])) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);

    if (!$test_email) {
        echo "<span style='color:red'>✗ Please enter a valid email address</span>";
    } else {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port       = SMTP_PORT;

            $mail->setFrom(SMTP_USERNAME, 'AMDP Reports');
            $mail->addAddress($test_email);
            $mail->Subject = 'Test Email from PHPMailer';
            $mail->Body = 'This is a test email sent at ' . date('Y-m-d H:i:s');

            if ($mail->send()) {
                echo "<span style='color:green'>✓ Test email sent to " . htmlspecialchars($test_email) . "</span>";
            }
        } catch (Exception $e) {
            echo "<span style='color:red'>✗ Failed to send: " . htmlspecialchars($mail->ErrorInfo) . "</span>";
        }
    }
}

// Test form (token carried through as a hidden field so the POST is still authorized)
echo "
<h3>Send a Test Email</h3>
<form method='post'>
    <input type='hidden' name='token' value='" . htmlspecialchars($providedToken) . "'>
    <input type='email' name='test_email' placeholder='Enter test email address' required>
    <button type='submit'>Send Test Email</button>
</form>
";

// Test 3: Check server configuration
echo "<h3>Test 3: Server Configuration</h3>";
echo "<pre>";
echo "PHP Version: " . htmlspecialchars(phpversion()) . "\n";
echo "PHP Mail Function: " . (function_exists('mail') ? 'Enabled' : 'Disabled') . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? 'Enabled' : 'Disabled') . "\n";
echo "SMTP in php.ini: " . htmlspecialchars(ini_get('SMTP')) . "\n";
echo "</pre>";
