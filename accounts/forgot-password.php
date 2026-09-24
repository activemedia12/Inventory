<?php
require_once '../config/db.php';

require_once '../config/vendor/autoload.php';
require_once '../config/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
date_default_timezone_set('Asia/Manila');

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if user exists
        $stmt = $inventory->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id);
            $stmt->fetch();

            // Generate token and expiration
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Delete any existing tokens for this user
            $delete_stmt = $inventory->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();

            // Store new token
            $insert_stmt = $inventory->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("iss", $user_id, $token, $expires);
            $insert_stmt->execute();

            // Send email using PHPMailer (reused from your export script)
            $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
            $reset_link = $base_url . "/accounts/reset-password.php?token=" . urlencode($token);

            try {
                $mail = new PHPMailer(true);
                amdp_configure_transactional_mailer($mail, 'auth', $email);

                $mail->isHTML(true);
                $mail->Subject = "Password Reset Request";
                $mail->Body    = "
                    <h2>Password Reset</h2>
                    <p>You requested a password reset for your account.</p>
                    <p>Click the link below to reset your password (valid for 1 hour):</p>
                    <p><a href='$reset_link'>$reset_link</a></p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <br>
                    <p>Regards,<br>Active Media Designs & Printing</p>
                ";

                $mail->send();
                $success = "Password reset link has been sent to your email address.";
            } catch (Exception $e) {
                $error = "Failed to send email. Please try again later.";
                // For debugging:
                // $error = "Email could not be sent. Error: " . $mail->ErrorInfo;
            }
        } else {
            $error = "No account found with that email address";
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
    <title>Forgot Password - Active Media Designs &amp; Printing</title>
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
                    <div class="auth-icon-badge">
                        <i class="fas fa-key"></i>
                    </div>
                    <h1>Password Recovery</h1>
                    <p class="auth-subtitle">Enter the email address on your account and we'll send you a link to reset your password.</p>

                    <?php if (!empty($error)): ?>
                        <div class="auth-notice auth-notice--error">
                            <div class="auth-notice__icon"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="auth-notice__body"><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="auth-notice auth-notice--success">
                            <div class="auth-notice__icon"><i class="fas fa-check-circle"></i></div>
                            <div class="auth-notice__body"><?php echo htmlspecialchars($success); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="forgotForm">
                        <div class="form-group">
                            <label class="form-label" for="email">Email address</label>
                            <input type="email" id="email" name="email" placeholder="Enter your email address" required autocomplete="email">
                        </div>
                        <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
                            <span class="btn-label"><i class="fas fa-paper-plane"></i> Send Reset Link</span>
                            <span class="btn-spinner"><i class="fas fa-circle-notch fa-spin"></i> Sending...</span>
                        </button>
                    </form>

                    <p class="auth-footer-note">Remember your password? <a href="login.php">Log in instead</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('forgotForm').addEventListener('submit', function() {
            document.getElementById('submitBtn').classList.add('is-loading');
        });
    </script>
</body>

</html>