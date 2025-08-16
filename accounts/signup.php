<?php
require_once '../config/db.php';

$message = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST['username']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $role = $_POST['role'];

  if (!empty($username) && !empty($password) && !empty($confirm_password)) {
    // Check if passwords match
    if ($password !== $confirm_password) {
      $message = "Passwords do not match.";
    } else {
      // Check if username exists
      $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
      $check_stmt->bind_param("s", $username);
      $check_stmt->execute();
      $check_stmt->store_result();

      if ($check_stmt->num_rows > 0) {
        $message = "Username already exists. Please choose another.";
      } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $mysqli->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
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
  <title>Sign Up</title>
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

    .signup-container {
      display: flex;
      flex-direction: column;
      max-width: 900px;
      width: 100%;
      background-color: #fff;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      border-radius: 28px;
    }

    .header {
      text-align: center;
      padding: 20px;
      background: linear-gradient(90deg, rgba(176, 0, 176, 1) 0%, rgba(0, 0, 0, 1) 30%, rgba(0, 0, 0, 1) 40%, rgba(0, 145, 255, 1) 70%, rgba(255, 255, 0, 1) 100%);
      ;
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
      padding: 50px 0px;
      border-radius: 28px;
    }

    .logo-container img {
      max-width: 300px;
      height: auto;
      transform: rotate(45deg);
    }

    .signup-box {
      flex: 1;
      padding: 20px;
    }

    .signup-form input,
    .signup-form select {
      width: 100%;
      padding: 14px 16px;
      border: 1px solid rgb(220, 220, 220);
      font-size: 17px;
      margin-bottom: 12px;
      transition: 0.3s;
    }

    .signup-form input:focus,
    .signup-form select:focus {
      outline: none;
      border-color: #1c1c1c;
      box-shadow: 0px 0px 5px 1px #1c1c1c;
    }

    .signup-btn {
      background-color: black;
      border: none;
      font-size: 20px;
      line-height: 48px;
      padding: 0 16px;
      width: 100%;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      margin-bottom: 15px;
      transition: 0.3s;
    }

    .signup-btn:hover {
      background-color: rgb(80, 80, 80);
    }

    .error-message {
      color: #ff4d4f;
      background-color: #fff2f0;
      border: 1px solid #ffccc7;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .success-message {
      color: #52c41a;
      background-color: #f6ffed;
      border: 1px solid #b7eb8f;
      padding: 10px;
      border-radius: 6px;
      margin-bottom: 15px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .password-container {
      position: relative;
    }

    .password-toggle {
      position: absolute;
      right: 10px;
      top: 40%;
      transform: translateY(-50%);
      color: black;
      cursor: pointer;
    }

    .footer-text {
      text-align: center;
      margin-top: 20px;
      color: #1c1e21;
      font-size: 14px;
    }

    @media (max-width: 768px) {
      .content {
        flex-direction: column;
      }

      .signup-box {
        border-left: none;
        border-top: 1px solid #dddfe2;
      }

      .logo-container img {
        max-width: 200px;
      }
    }
  </style>
  <?php if ($success): ?>
    <meta http-equiv="refresh" content="3;url=login.php">
  <?php endif; ?>
</head>

<body>
  <div class="signup-container">
    <div class="header">
      <h1>Active Media Designs & Printing Inventory System</h1>
    </div>

    <div class="content">
      <div class="logo-container">
        <img src="../assets/images/plainlogo.png" alt="Active Media Designs Logo">
      </div>

      <div class="signup-box">
        <?php if (!empty($message)): ?>
          <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
            <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="signup-form">
          <input type="text" name="username" placeholder="Username" required>

          <div class="password-container">
            <input type="password" name="password" placeholder="Password" required>
            <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
          </div>

          <div class="password-container">
            <input type="password" name="confirm_password" placeholder="Re-enter Password" required>
            <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
          </div>

          <select name="role" required>
            <option value="" disabled selected>Select account type</option>
            <option value="employee">Employee</option>
            <option value="admin">Admin</option>
          </select>

          <button type="submit" class="signup-btn">Sign Up</button>

          <p class="footer-text">Already have an account? <a href="login.php">Log in</a></p>
        </form>
      </div>
    </div>
  </div>

  <script>
    function togglePassword(icon) {
      const passwordInput = icon.previousElementSibling;
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }
  </script>
</body>

</html>