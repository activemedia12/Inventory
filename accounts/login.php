<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  $stmt = $inventory->prepare("SELECT id, password, role, email_verified FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $hashed, $role, $email_verified);
    $stmt->fetch();

    if (is_string($hashed) && password_verify($password, $hashed)) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

        if ($role === 'customer') {
          $stmt2 = $inventory->prepare("SELECT id FROM personal_customers WHERE user_id = ?");
          $stmt2->bind_param("i", $id);
          $stmt2->execute();
          $res2 = $stmt2->get_result();
          if ($row2 = $res2->fetch_assoc()) {
            $_SESSION['customer_table'] = 'personal_customers';
            $_SESSION['customer_id'] = $row2['id'];
          } else {
            $stmt3 = $inventory->prepare("SELECT id FROM company_customers WHERE user_id = ?");
            $stmt3->bind_param("i", $id);
            $stmt3->execute();
            $res3 = $stmt3->get_result();
            if ($row3 = $res3->fetch_assoc()) {
              $_SESSION['customer_table'] = 'company_customers';
              $_SESSION['customer_id'] = $row3['id'];
            }
          }
          header("Location: ../website/main.php");
        } else {
          header("Location: ../pages/dashboard.php");
        }
        exit;
    } else {
      $error = "Incorrect password.";
    }
  } else {
    $error = "User not found.";
  }

  $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Log In - Active Media Designs &amp; Printing</title>
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
      <div class="auth-grid">
        <a href="../website/sub-main.php" class="auth-brand auth-brand--link" aria-label="Back to Active Media Designs website">
          <div class="auth-brand__blobs" aria-hidden="true"><span></span><span></span><span></span></div>
          <div class="auth-topbar">
            <span class="auth-brandmark">
              <img src="../assets/images/plainlogo.png" alt="Active Media Designs Logo">
              AMDP Website
            </span>
            <span class="auth-backlink"><i class="fas fa-arrow-left"></i> Back to site</span>
          </div>
          <div class="auth-brand__content">
          <div class="auth-brand__mark">
            <img src="../assets/images/plainlogo.png" alt="">
          </div>
          <h1>Welcome back.</h1>
          <p>Log in to track job orders, manage deliveries and pick up right where you left off.</p>
          <div class="auth-brand__tags">
            <span>Offset</span>
            <span>Digital</span>
            <span>Riso</span>
          </div>
          </div>
        </a>

        <div class="auth-form-panel">
          <h1>Log In</h1>
          <p class="auth-subtitle">Enter your credentials to continue to your account.</p>

          <?php if ($error): ?>
            <div class="auth-notice auth-notice--error">
              <div class="auth-notice__icon"><i class="fas fa-exclamation-circle"></i></div>
              <div class="auth-notice__body">
                <?php
                if (strpos($error, '<a href') !== false) {
                  echo $error;
                } else {
                  echo htmlspecialchars($error);
                }
                ?>
              </div>
            </div>
          <?php endif; ?>

          <form method="post" id="loginForm">
            <div class="form-group">
              <label class="form-label" for="username">Username</label>
              <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
            </div>

            <div class="form-group">
              <label class="form-label" for="password">Password</label>
              <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                <i class="fas fa-eye password-toggle" onclick="togglePassword('password', this)"></i>
              </div>
            </div>

            <a href="forgot-password.php" class="auth-fp-link">Forgot password?</a>

            <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
              <span class="btn-label"><i class="fas fa-arrow-right-to-bracket"></i> Log In</span>
              <span class="btn-spinner"><i class="fas fa-circle-notch fa-spin"></i> Logging in...</span>
            </button>
          </form>

          <div class="auth-divider">or continue with</div>

          <div class="auth-social-group">
            <a href="#" class="auth-social-btn auth-social-btn--google" data-tooltip="Coming soon">
              <span class="auth-social-content">
                <svg viewBox="0 0 48 48" aria-hidden="true">
                  <path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84c-.51 2.75-2.06 5.08-4.39 6.64v5.52h7.11c4.16-3.83 6.56-9.47 6.56-16.17z"/>
                  <path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.11-5.52c-1.97 1.32-4.49 2.11-7.45 2.11-5.73 0-10.58-3.87-12.31-9.07H4.34v5.7C7.96 41.07 15.4 46 24 46z"/>
                  <path fill="#FBBC05" d="M11.69 28.18C11.25 26.86 11 25.45 11 24s.25-2.86.69-4.18v-5.7H4.34C2.85 17.09 2 20.45 2 24s.85 6.91 2.34 9.88l7.35-5.7z"/>
                  <path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.41 3.29l6.31-6.31C34.91 4.18 29.93 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.35 5.7c1.73-5.2 6.58-9.07 12.31-9.07z"/>
                </svg>
                Google
              </span>
            </a>
            <a href="#" class="auth-social-btn auth-social-btn--facebook" data-tooltip="Coming soon">
              <span class="auth-social-content">
                <svg viewBox="0 0 48 48" aria-hidden="true">
                  <path fill="#1877F2" d="M48 24C48 10.7 37.3 0 24 0S0 10.7 0 24c0 12 8.8 22 20.25 23.8V30.9h-6.1V24h6.1v-5.3c0-6 3.6-9.3 9-9.3 2.6 0 5.4.5 5.4.5v6h-3c-3 0-4 1.9-4 3.8V24h6.9l-1.1 6.9h-5.8v16.9C39.2 46 48 36 48 24z"/>
                </svg>
                Facebook
              </span>
            </a>
            <a href="#" class="auth-social-btn auth-social-btn--apple" data-tooltip="Coming soon">
              <span class="auth-social-content">
                <svg viewBox="0 0 384 512" aria-hidden="true">
                  <path fill="#000" d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z"/>
                </svg>
                Apple
              </span>
            </a>
          </div>

          <p class="auth-footer-note">Don't have an account? <a href="customer.php">Sign up</a></p>
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

    document.getElementById('loginForm').addEventListener('submit', function() {
      document.getElementById('submitBtn').classList.add('is-loading');
    });
  </script>
</body>

</html>