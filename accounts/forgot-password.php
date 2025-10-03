<?php
require_once '../config/db.php';

require_once '../config/vendor/autoload.php';

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
            $reset_link = $base_url . "/inventory/accounts/reset-password.php?token=" . urlencode($token);
            
            try {
                $mail = new PHPMailer(true);
                
                // SMTP Configuration (same as your export script)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'reportsjoborder@gmail.com';
                $mail->Password   = 'kjyj krfm rkbk qmst'; // App password
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;

                $mail->setFrom('reportsjoborder@gmail.com', 'Active Media');
                $mail->addAddress($email); // Send to the user's email
                
                $mail->isHTML(true);
                $mail->Subject = "Password Reset Request";
                $mail->Body    = "
                    <h2>Password Reset</h2>
                    <p>You requested a password reset for your account.</p>
                    <p>Click the link below to reset your password (valid for 1 hour):</p>
                    <p><a href='$reset_link'>$reset_link</a></p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <br>
                    <p>Regards,<br>Active Media Designs and Printing</p>
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
    <title>Forgot Password</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #000;
            --secondary: #333;
            --error: #ff4d4f;
            --success: #52c41a;
            --text: #1c1c1c;
            --border: rgb(220, 220, 220);
            --bg: rgb(245, 245, 245);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .forgot-container {
            width: 100%;
            max-width: 900px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1), 0 8px 16px rgba(0, 0, 0, .1);
            border-radius: 28px;
            overflow: hidden;
        }

        .header {
            text-align: center;
            padding: 20px;
            background: linear-gradient(90deg, 
                rgba(176, 0, 176, 1) 0%, 
                rgba(0, 0, 0, 1) 30%, 
                rgba(0, 0, 0, 1) 40%, 
                rgba(0, 145, 255, 1) 70%, 
                rgba(255, 255, 0, 1) 100%);
            color: white;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .content {
            display: flex;
            padding: 20px;
        }

        .logo-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px 0;
        }

        .logo-container img {
            max-width: 300px;
            height: auto;
            transform: rotate(45deg);
        }

        .form-box {
            flex: 1;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-box h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 22px;
            color: var(--text);
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
        }

        .btn {
            position: relative;
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            overflow: hidden;
            height: 52px;
        }

        .btn:hover {
            background-color: var(--secondary);
        }

        .btn .btn-text {
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn .loading-spinner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
        }

        .btn.loading .btn-text {
            opacity: 0;
            visibility: hidden;
        }

        .btn.loading .loading-spinner {
            display: flex;
        }

        .message {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error {
            color: var(--error);
            background: #fff2f0;
            border: 1px solid #ffccc7;
        }

        .success {
            color: var(--success);
            background: #f6ffed;
            border: 1px solid #b7eb8f;
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
            color: var(--text);
        }

        .back-to-login a {
            color: var(--text);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .back-to-login a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .content {
                flex-direction: column;
            }

            .logo-container img {
                max-width: 200px;
            }

            .forgot-container {
                transform: scale(0.9);
            }
        }
    </style>
</head>

<body>
    <div class="forgot-container">
        <div class="header">
            <h1>Password Recovery</h1>
        </div>

        <div class="content">
            <div class="logo-container">
                <img src="../assets/images/plainlogo.png" alt="Company Logo">
            </div>

            <div class="form-box">
                <?php if (!empty($error)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <h2>Reset Your Password</h2>
                <form method="post" id="forgotForm">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Enter your email address" required>
                    </div>
                    <button type="submit" class="btn" id="submitBtn">
                        <span class="btn-text">Send Reset Link</span>
                        <span class="loading-spinner">
                            <i class="fas fa-circle-notch fa-spin"></i> Processing...
                        </span>
                    </button>
                </form>

                <div class="back-to-login">
                    Remember your password? <a href="login.php">Log in instead</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    form.submit();
                }, 500);
            });
        });
    </script>
</body>

</html>