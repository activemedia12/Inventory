<?php

/**
 * info.php — SMTP connectivity test tool.
 *
 * SECURITY NOTES vs. the original version of this file:
 * 1. The Gmail app password is no longer hardcoded — it comes from config.php.
 * 2. SMTPDebug is OFF by default. Verbose SMTP debug output (level 4) prints
 *    the entire authentication exchange to the page, which is very likely
 *    how the password leaked in the first place — enabling it on a
 *    publicly reachable URL is equivalent to posting the password itself.
 * 3. Access requires a one-time debug token (set below) so this can't be
 *    triggered by anyone who simply guesses/finds the URL.
 *
 * STRONGLY RECOMMENDED: delete this file from the server entirely once
 * you've confirmed mail sending works. Debug/test scripts should not live
 * in a production web root long-term. If you do keep it, also consider
 * moving it outside the public web root, or password-protecting the
 * directory via .htaccess.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Simple access guard ---
// Set AMDP_DEBUG_TOKEN as an environment variable (or in secrets.php as
// SECRET_DEBUG_TOKEN) to something long and random, then visit:
//   info.php?token=that-value
$expectedToken = amdp_config('AMDP_DEBUG_TOKEN', 'SECRET_DEBUG_TOKEN', null);
$providedToken = $_GET['token'] ?? '';

if (!$expectedToken || !hash_equals((string) $expectedToken, (string) $providedToken)) {
    http_response_code(403);
    die('❌ Not authorized. This tool requires a valid ?token= parameter.');
}

// Verbose debug is opt-in via ?debug=1 (only reachable after passing the
// token check above), and even then it should only ever be used privately.
$verbose = isset($_GET['debug']) && $_GET['debug'] === '1';

$mail = new PHPMailer(true);

try {
    if ($verbose) {
        $mail->SMTPDebug   = 2; // client/server only — avoids echoing full auth exchange
        $mail->Debugoutput = 'html';
    }

    amdp_configure_mailer($mail);

    $mail->Subject = 'PHPMailer SMTP Debug Test';
    $mail->Body    = 'This is a PHPMailer debug test email.';

    $mail->send();
    echo '<h2 style="color:green;">✅ EMAIL SENT SUCCESSFULLY</h2>';
} catch (Exception $e) {
    echo '<h2 style="color:red;">❌ EMAIL FAILED</h2>';
    echo '<pre>' . htmlspecialchars($mail->ErrorInfo) . '</pre>';
}
