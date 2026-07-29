<?php
$secretsFile = __DIR__ . '/secrets.php';
if (file_exists($secretsFile)) {
    require_once $secretsFile;
}

/**
 * Reads a value from an environment variable first, falling back to a
 * constant defined in secrets.php, then to a hardcoded default (only used
 * for genuinely non-sensitive values like SMTP port).
 */
function amdp_config(string $envName, string $constName, $default = null)
{
    $val = getenv($envName);
    if ($val !== false && $val !== '') {
        return $val;
    }
    if (defined($constName)) {
        return constant($constName);
    }
    if ($default !== null) {
        return $default;
    }
    die("❌ Missing required configuration: {$envName} / {$constName}. "
        . "Set the environment variable or define it in secrets.php.");
}

// ------------------------------
// SMTP / Email credentials
// ------------------------------
define('SMTP_HOST',     amdp_config('AMDP_SMTP_HOST', 'SECRET_SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_USERNAME', amdp_config('AMDP_SMTP_USERNAME', 'SECRET_SMTP_USERNAME'));
define('SMTP_PASSWORD', amdp_config('AMDP_SMTP_PASSWORD', 'SECRET_SMTP_PASSWORD'));
define('SMTP_PORT',     (int) amdp_config('AMDP_SMTP_PORT', 'SECRET_SMTP_PORT', 587));
define('SMTP_SECURE',   amdp_config('AMDP_SMTP_SECURE', 'SECRET_SMTP_SECURE', 'tls'));

define('MAIL_FROM_EMAIL', amdp_config('AMDP_MAIL_FROM_EMAIL', 'SECRET_MAIL_FROM_EMAIL', 'amdpreports@gmail.com'));
define('MAIL_FROM_NAME',  'AMDP Reports');
define('MAIL_TO_EMAIL',   amdp_config('AMDP_MAIL_TO_EMAIL', 'SECRET_MAIL_TO_EMAIL', 'activemediaprint@gmail.com'));
define('MAIL_TO_NAME',    'Active Media');
define('MAIL_CC_EMAIL',   amdp_config('AMDP_MAIL_CC_EMAIL', 'SECRET_MAIL_CC_EMAIL', 'amdpreports@gmail.com'));

// ------------------------------
// Database credentials (used only by the SQL backup step in
// email_export_today.php — db.php should ideally be refactored to use
// these same constants too).
// ------------------------------
define('BACKUP_DB_HOST', amdp_config('AMDP_DB_HOST', 'SECRET_DB_HOST', 'localhost'));
define('BACKUP_DB_USER', amdp_config('AMDP_DB_USER', 'SECRET_DB_USER'));
define('BACKUP_DB_PASS', amdp_config('AMDP_DB_PASS', 'SECRET_DB_PASS'));
define('BACKUP_DB_NAME', amdp_config('AMDP_DB_NAME', 'SECRET_DB_NAME', 'u382513771_inventory'));

// ------------------------------
// Additional email accounts used by the accounts/ folder (login, signup,
// password reset, email verification). These are SEPARATE Gmail accounts
// from the one above — each needs its own app password.
// ------------------------------

// "auth" account — used for password-reset and resend-verification emails
// (sent to whatever address the visitor enters, not a fixed recipient).
define('AUTH_SMTP_USERNAME', amdp_config('AMDP_AUTH_SMTP_USERNAME', 'SECRET_AUTH_SMTP_USERNAME'));
define('AUTH_SMTP_PASSWORD', amdp_config('AMDP_AUTH_SMTP_PASSWORD', 'SECRET_AUTH_SMTP_PASSWORD'));
define('AUTH_MAIL_FROM_NAME', 'Active Media');

// "verify" account — used for the initial signup verification email sent
// from customer.php.
define('VERIFY_SMTP_USERNAME', amdp_config('AMDP_VERIFY_SMTP_USERNAME', 'SECRET_VERIFY_SMTP_USERNAME'));
define('VERIFY_SMTP_PASSWORD', amdp_config('AMDP_VERIFY_SMTP_PASSWORD', 'SECRET_VERIFY_SMTP_PASSWORD'));
define('VERIFY_MAIL_FROM_NAME', 'Active Media');

/**
 * Configure a PHPMailer instance for a transactional email sent to a
 * dynamic (visitor-supplied) address, using one of the accounts above.
 *
 * @param \PHPMailer\PHPMailer\PHPMailer $mail
 * @param string $account 'auth' or 'verify'
 * @param string $toEmail Recipient's email address
 * @param string|null $toName Optional recipient display name
 */
function amdp_configure_transactional_mailer(\PHPMailer\PHPMailer\PHPMailer $mail, string $account, string $toEmail, ?string $toName = null): void
{
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;

    if ($account === 'auth') {
        $mail->Username = AUTH_SMTP_USERNAME;
        $mail->Password = AUTH_SMTP_PASSWORD;
        $mail->setFrom(AUTH_SMTP_USERNAME, AUTH_MAIL_FROM_NAME);
    } elseif ($account === 'verify') {
        $mail->Username = VERIFY_SMTP_USERNAME;
        $mail->Password = VERIFY_SMTP_PASSWORD;
        $mail->setFrom(VERIFY_SMTP_USERNAME, VERIFY_MAIL_FROM_NAME);
    } else {
        throw new \InvalidArgumentException("Unknown mail account: $account");
    }

    $mail->addAddress($toEmail, $toName ?? '');
}

/**
 * Helper: apply the standard SMTP settings to a PHPMailer instance.
 * Keeps every script's SMTP block to one line instead of six.
 */
function amdp_configure_mailer(\PHPMailer\PHPMailer\PHPMailer $mail): void
{
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO_EMAIL, MAIL_TO_NAME);
    $mail->addCC(MAIL_CC_EMAIL);
}
