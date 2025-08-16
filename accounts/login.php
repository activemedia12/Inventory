<?php
session_start();
require_once '../config/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST['username']);
  $password = $_POST['password'];

  $stmt = $mysqli->prepare("SELECT id, password, role FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $hashed, $role);
    $stmt->fetch();

    if (password_verify($password, $hashed)) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $id;
      $_SESSION['username'] = $username;
      $_SESSION['role'] = $role;
      header("Location: ../pages/dashboard.php");
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
  <title>Login</title>
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

    .login-container {
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
    }

    .logo-container img {
      max-width: 300px;
      height: auto;
      transform: rotate(45deg);
    }

    .login-box {
      flex: 1;
      padding: 20px;
    }

    .login-form input {
      width: 100%;
      padding: 14px 16px;
      border: 1px solid rgb(220, 220, 220);
      font-size: 17px;
      margin-bottom: 12px;
      transition: 0.3s;
    }

    .login-form input:focus {
      outline: none;
      border-color: #1c1c1c;
      box-shadow: 0px 0px 5px 1px #1c1c1c;
    }

    .login-btn {
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

    .login-btn:hover {
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

    .error-message i {
      font-size: 16px;
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
      .login-container {
        scale: 0.8;
      }
      .content {
        flex-direction: column;
      }

      .login-box {
        border-left: none;
        border-top: 1px solid #dddfe2;
      }

      .logo-container img {
        max-width: 200px;
      }
    }

    @media (max-width: 578px) {
      .header h1 {
        font-size: 20px;
      }

      .logo-container img {
        max-width: 150px;
      }

      .login-container {
        scale: 0.7;
      }

      .login-box {
        padding: 12px;
        border-top: none;
      }
    }
  </style>
</head>

<body>
  <div class="login-container">
    <div class="header">
      <h1>Welcome to Active Media Designs & Printing</h1>
    </div>

    <div class="content">
      <div class="logo-container">
        <img src="../assets/images/plainlogo.png" alt="Active Media Designs Logo">
      </div>

      <div class="login-box">
        <?php if ($error): ?>
          <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="login-form">
          <input type="text" name="username" placeholder="Username" required>

          <div class="password-container">
            <input type="password" name="password" placeholder="Password" required>
            <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
          </div>

          <button type="submit" class="login-btn">Log In</button>

          <p class="footer-text">Don't have an account? <a href="customer.php">Sign in</a></p>
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