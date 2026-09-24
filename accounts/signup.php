<?php
session_start();
require_once '../config/db.php';

// SECURITY: this page creates employee/admin accounts, so only an already
// logged-in admin may use it. Without this check, anyone who finds this
// URL could POST role=admin and grant themselves full access.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: login.php");
  exit;
}

$message = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST['username']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $role = $_POST['role'];

  // Whitelist roles server-side — never trust the dropdown value alone
  $allowed_roles = ['employee', 'admin'];
  if (!in_array($role, $allowed_roles, true)) {
    $message = "Invalid role selected.";
    $role = null;
  }

  if ($role === null) {
    // invalid role — $message already set above, fall through to display it
  } elseif (!empty($username) && !empty($password) && !empty($confirm_password)) {
    // Check if passwords match
    if ($password !== $confirm_password) {
      $message = "Passwords do not match.";
    } else {
      // Check if username exists
      $check_stmt = $inventory->prepare("SELECT id FROM users WHERE username = ?");
      $check_stmt->bind_param("s", $username);
      $check_stmt->execute();
      $check_stmt->store_result();

      if ($check_stmt->num_rows > 0) {
        $message = "Username already exists. Please choose another.";
      } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $inventory->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed, $role);

        if ($stmt->execute()) {
          $message = "Account created successfully. Redirecting to login...";
          $success = true;
        } else {
          $message = "Error: " . $stmt->error;
        }

        $stmt->close();
      }
      $check_stmt->close();
    }
  } else {
    $message = "All fields are required.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Staff Account - Active Media Designs &amp; Printing</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/main.css">
  <?php if ($success): ?>
    <meta http-equiv="refresh" content="3;url=login.php">
  <?php endif; ?>
</head>

<body class="auth-page">
  <div class="auth-shell">
    <div class="auth-main">
      <div class="auth-grid auth-grid--simple">
        <div class="auth-bg-blobs" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="auth-topbar auth-simple-topbar">
          <a href="../pages/dashboard.php" class="auth-brandmark">
            <img src="../assets/images/plainlogo.png" alt="Active Media Designs Logo">
            AMDP Website
          </a>
          <a href="../pages/dashboard.php" class="auth-backlink"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
        </div>
        <div class="auth-form-panel">
          <div class="auth-icon-badge">
            <i class="fas fa-user-shield"></i>
          </div>
          <h1>Create Staff Account</h1>
          <p class="auth-subtitle">Admin-only tool. Set up login credentials for a new employee or admin.</p>

          <?php if (!empty($message)): ?>
            <div class="auth-notice <?php echo $success ? 'auth-notice--success' : 'auth-notice--error'; ?>">
              <div class="auth-notice__icon"><i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i></div>
              <div class="auth-notice__body"><?php echo htmlspecialchars($message); ?></div>
            </div>
          <?php endif; ?>

          <form method="post" id="signupForm">
            <div class="form-group">
              <label class="form-label" for="username">Username</label>
              <input type="text" id="username" name="username" placeholder="Enter a username" required autocomplete="off">
            </div>

            <div class="form-group">
              <label class="form-label" for="password">Password</label>
              <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Create a password" required autocomplete="new-password">
                <i class="fas fa-eye password-toggle" onclick="togglePassword('password', this)"></i>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="confirm_password">Confirm password</label>
              <div class="password-container">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required autocomplete="new-password">
                <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="role">Account type</label>
              <select id="role" name="role" required>
                <option value="" disabled selected>Select account type</option>
                <option value="employee">Employee</option>
                <option value="admin">Admin</option>
              </select>
            </div>

            <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
              <span class="btn-label"><i class="fas fa-user-plus"></i> Create Account</span>
              <span class="btn-spinner"><i class="fas fa-circle-notch fa-spin"></i> Creating...</span>
            </button>
          </form>

          <p class="auth-footer-note">Already have an account? <a href="login.php">Log in</a></p>
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

    document.getElementById('signupForm').addEventListener('submit', function() {
      document.getElementById('submitBtn').classList.add('is-loading');
    });
  </script>
</body>

</html>