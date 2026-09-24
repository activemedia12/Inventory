<?php
require_once '../config/db.php';
session_start();
date_default_timezone_set('Asia/Manila');

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

$error = '';
$success = '';
$valid_token = false;
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    // Validate token
    $stmt = $inventory->prepare("
        SELECT user_id 
        FROM password_resets 
        WHERE token = ? AND expires_at > NOW()
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id);
        $stmt->fetch();
        $valid_token = true;

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Validation
            if (empty($password)) {
                $error = "Password is required";
            } elseif (strlen($password) < 8) {
                $error = "Password must be at least 8 characters";
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
                $error = "Must contain uppercase, lowercase, and number";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } else {
                // Update password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $inventory->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                $update_stmt->execute();

                // Mark token as used
                $delete_stmt = $inventory->prepare("DELETE FROM password_resets WHERE token = ?");
                $delete_stmt->bind_param("s", $token);
                $delete_stmt->execute();

                $success = "Your password has been updated successfully. You can now <a href='login.php'>login</a> with your new password.";
                $_POST = array();
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Reset Password - Active Media Designs &amp; Printing</title>
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
                    <?php if ($valid_token): ?>
                        <div class="auth-icon-badge">
                            <i class="fas fa-lock-open"></i>
                        </div>
                        <h1>Create New Password</h1>
                        <p class="auth-subtitle">Choose a strong password you haven't used before.</p>

                        <?php if (!empty($error)): ?>
                            <div class="auth-notice auth-notice--error">
                                <div class="auth-notice__icon"><i class="fas fa-exclamation-circle"></i></div>
                                <div class="auth-notice__body"><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="auth-notice auth-notice--success">
                                <div class="auth-notice__icon"><i class="fas fa-check-circle"></i></div>
                                <div class="auth-notice__body"><?php echo $success; ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($valid_token && empty($success)): ?>
                            <form method="post" id="resetForm">
                                <div class="form-group">
                                    <label class="form-label" for="password">New password</label>
                                    <div class="password-container">
                                        <input type="password" name="password" id="password" placeholder="New Password" required autocomplete="new-password">
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('password', this)"></i>
                                    </div>
                                    <p class="form-help">At least 8 characters, with an uppercase letter, a lowercase letter, and a number.</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm new password</label>
                                    <div class="password-container">
                                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required autocomplete="new-password">
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
                                    <span class="btn-label"><i class="fas fa-check"></i> Reset Password</span>
                                    <span class="btn-spinner"><i class="fas fa-circle-notch fa-spin"></i> Saving...</span>
                                </button>
                            </form>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="auth-icon-badge auth-icon-badge--alert">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                        <h1>Invalid or Expired Link</h1>
                        <p class="auth-subtitle">This password reset link is no longer valid. Please request a new one.</p>
                        <a href="forgot-password.php" class="btn btn-primary auth-btn">
                            <span class="btn-label"><i class="fas fa-rotate-right"></i> Request New Reset Link</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconEl) {
            const input = document.getElementById(inputId);
            const isPw = input.type === 'password';
            input.type = isPw ? 'text' : 'password';
            iconEl.classList.toggle('fa-eye');
            iconEl.classList.toggle('fa-eye-slash');
        }

        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function() {
                document.getElementById('submitBtn').classList.add('is-loading');
            });
        }
    </script>
</body>

</html>