<?php
require_once '../config/db.php';
require_once '../config/vendor/autoload.php';
require_once '../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
date_default_timezone_set('Asia/Manila');

$error = '';
$success = '';
$show_form = true;

$token = $_GET['token'] ?? '';

if (!empty($token)) {
    // Verify token
    $stmt = $inventory->prepare("
        SELECT id, username 
        FROM users 
        WHERE verification_token = ? AND verification_expires > NOW() AND email_verified = FALSE
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $email);
        $stmt->fetch();

        // Mark email as verified
        $update_stmt = $inventory->prepare("UPDATE users SET email_verified = TRUE, verification_token = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);

        if ($update_stmt->execute()) {
            $success = "Your email has been verified successfully! You can now <a href='login.php'>login</a> to your account.";
            $show_form = false;
        } else {
            $error = "Error verifying email. Please try again.";
        }
        $update_stmt->close();
    } else {
        $error = "Invalid or expired verification link. Please request a new verification email.";
        $show_form = true;
    }
    $stmt->close();
}

// Resend verification email
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if user exists and needs verification
        $stmt = $inventory->prepare("
            SELECT id, email_verified 
            FROM users 
            WHERE username = ? AND email_verified = FALSE
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $email_verified);
            $stmt->fetch();

            // Generate new verification token
            $new_token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $update_stmt = $inventory->prepare("
                UPDATE users 
                SET verification_token = ?, verification_expires = ? 
                WHERE id = ?
            ");
            $update_stmt->bind_param("ssi", $new_token, $expires, $user_id);

            if ($update_stmt->execute()) {
                // Send verification email
                $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
                $verify_link = $base_url . "/accounts/email-verification.php?token=" . urlencode($new_token);

                try {
                    $mail = new PHPMailer(true);
                    amdp_configure_transactional_mailer($mail, 'auth', $email);

                    $mail->isHTML(true);
                    $mail->Subject = "Verify Your Email Address - Active Media";
                    $mail->Body    = "
                        <h2>Email Verification</h2>
                        <p>Thank you for registering with Active Media Designs & Printing!</p>
                        <p>Please click the link below to verify your email address:</p>
                        <p><a href='$verify_link' style='background: #000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;'>Verify Email Address</a></p>
                        <p>Or copy and paste this link in your browser:<br>$verify_link</p>
                        <p>This link will expire in 24 hours.</p>
                        <br>
                        <p>If you didn't create an account, please ignore this email.</p>
                        <br>
                        <p>Regards,<br>Active Media Designs & Printing</p>
                    ";

                    $mail->send();
                    $success = "A new verification link has been sent to your email address.";
                    $show_form = false;
                } catch (Exception $e) {
                    $error = "Failed to send verification email. Please try again later.";
                }
            } else {
                $error = "Error generating verification link. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error = "No pending verification found for this email, or email is already verified.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Email Verification - Active Media Designs &amp; Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body class="auth-page">
    <div class="auth-shell">
        <div class="auth-main">
            <div class="auth-grid auth-grid--simple">
                <div class="auth-bg-blobs" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="auth-topbar auth-simple-topbar">
                    <a href="../website/sub-main.php" class="auth-brandmark">
                        <img src="../assets/images/plainlogo.png" alt="Active Media Designs Logo">
                        AMDP Website
                    </a>
                    <a href="login.php" class="auth-backlink"><i class="fas fa-arrow-left"></i> Back to login</a>
                </div>
                <div class="auth-form-panel">
                    <div class="auth-icon-badge <?php echo !empty($success) ? 'auth-icon-badge--ok' : (!empty($error) && !$show_form ? 'auth-icon-badge--alert' : ''); ?>">
                        <i class="fas <?php echo !empty($success) ? 'fa-envelope-circle-check' : 'fa-envelope'; ?>"></i>
                    </div>
                    <h1>Email Verification</h1>

                    <?php if (!empty($error)): ?>
                        <div class="auth-notice auth-notice--error">
                            <div class="auth-notice__icon"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="auth-notice__body"><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="auth-notice auth-notice--success">
                            <div class="auth-notice__icon"><i class="fas fa-check-circle"></i></div>
                            <div class="auth-notice__body"><?php echo $success; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_form): ?>
                        <?php if (empty($token)): ?>
                            <p class="auth-subtitle">Enter your email to receive a verification link.</p>
                            <form method="post" id="verificationForm">
                                <div class="form-group">
                                    <label class="form-label" for="email">Email address</label>
                                    <input type="email" id="email" name="email" placeholder="Enter your email address" required autocomplete="email">
                                </div>
                                <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
                                    <span class="btn-label"><i class="fas fa-paper-plane"></i> Send Verification Link</span>
                                    <span class="btn-spinner"><i class="fas fa-circle-notch fa-spin"></i> Processing...</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="auth-footer-note"><a href="login.php">Back to Login</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('verificationForm');
        if (form) {
            form.addEventListener('submit', function() {
                document.getElementById('submitBtn').classList.add('is-loading');
            });
        }
    </script>
</body>

</html>