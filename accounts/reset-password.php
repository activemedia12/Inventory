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
    <title>Reset Password</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgb(140, 140, 140);
            border-radius: 10px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: rgb(245, 245, 245);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .reset-container {
            display: flex;
            flex-direction: column;
            max-width: 900px;
            width: 100%;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1), 0 8px 16px rgba(0, 0, 0, .1);
            overflow: hidden;
            border-radius: 28px;
        }

        .header {
            text-align: center;
            padding: 20px;
            background: linear-gradient(90deg, rgba(176, 0, 176, 1) 0%, rgba(0, 0, 0, 1) 30%, rgba(0, 0, 0, 1) 40%, rgba(0, 145, 255, 1) 70%, rgba(255, 255, 0, 1) 100%);
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
            border-radius: 28px;
        }

        .logo-container img {
            max-width: 300px;
            height: auto;
            transform: rotate(45deg);
        }

        .form-box {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-box h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 22px;
            color: #333;
        }

        .form-group {
            margin-bottom: 12px;
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid rgb(220, 220, 220);
            font-size: 16px;
            transition: .3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1c1c1c;
            box-shadow: 0 0 5px 1px #1c1c1c;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #777;
            font-size: 18px;
        }

        .btn {
            background-color: black;
            border: none;
            font-size: 20px;
            line-height: 48px;
            padding: 0 16px;
            width: 100%;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: .3s;
            margin-bottom: 15px;
        }

        .btn:hover {
            background-color: rgb(80, 80, 80);
        }

        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 15px;
            align-items: center;
            gap: 10px;
        }

        .message a {
            color: #1c1c1c;
        }

        .error {
            color: #ff4d4f;
            background: #fff2f0;
            border: 1px solid #ffccc7;
        }

        .success {
            color: #52c41a;
            background: #f6ffed;
            border: 1px solid #b7eb8f;
        }

        .invalid-token {
            text-align: center;
            padding: 30px;
        }

        .invalid-token h2 {
            color: #333;
            margin-bottom: 15px;
        }

        .invalid-token p {
            color: #666;
            margin-bottom: 20px;
        }

        .invalid-token a {
            display: inline-block;
            padding: 12px 24px;
            background-color: #000;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .invalid-token a:hover {
            background-color: #333;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .content {
                flex-direction: column;
            }

            .logo-container img {
                max-width: 200px;
            }

            .reset-container {
                scale: 0.8;
            }
        }
    </style>
</head>

<body>
    <div class="reset-container">
        <div class="header">
            <h1>RESET PASSWORD</h1>
        </div>

        <div class="content">
            <div class="logo-container">
                <img src="../assets/images/plainlogo.png" alt="Company Logo">
            </div>

            <div class="form-box">
                <?php if ($valid_token): ?>
                    <h2>Create New Password</h2>

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

                    <?php if ($valid_token && empty($success)): ?>
                        <form method="post">
                            <div class="form-group">
                                <input type="password" name="password" id="password" placeholder="New Password" required>
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('password', this)"></i>
                            </div>
                            <div class="form-group">
                                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required>
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
                            </div>
                            <button type="submit" class="btn">Reset Password</button>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="invalid-token">
                        <h2>Invalid or Expired Link</h2>
                        <p>The password reset link is no longer valid. Please request a new password reset link.</p>
                        <a href="forgot-password.php">Request New Reset Link</a>
                    </div>
                <?php endif; ?>
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
    </script>
</body>

</html>