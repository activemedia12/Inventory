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