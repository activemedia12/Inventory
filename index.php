<?php
session_start();
$mysqli = new mysqli("localhost", "u382513771_admin", "Amdp@1205", "u382513771_inventory");

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $mysqli->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $result = $mysqli->query("SELECT * FROM users WHERE username = '$username'");

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user['username'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <title>Login - Inventory System</title>
  <style>
    :root {
      --primary: #1c1c1c;
      --primary-dark: rgba(28, 28, 28, 0.80);
      --danger: #f44336;
      --gray: #e0e0e0;
      --dark-gray: #757575;
      --light-gray: #f5f5f5;
    }

    .header-container {
        text-align: center;
        margin-bottom: 30px;
        position: relative;
        padding-bottom: 20px;
    }

    .header-container::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 4px;
        background: linear-gradient(90deg,rgba(176, 0, 176, 1) 10%, rgba(0, 0, 3, 1) 33%, rgba(35, 125, 222, 1) 65%, rgb(179, 179, 0) 90%);
        border-radius: 2px;
    }

    .company-name {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--secondary);
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 8px;
        position: relative;
        display: inline-block;
    }

    .report-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary);
        margin: 0;
        line-height: 1.2;
        background: linear-gradient(90deg,rgba(176, 0, 176, 1) 10%, rgba(0, 0, 3, 1) 33%, rgba(35, 125, 222, 1) 65%, rgb(179, 179, 0) 90%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: inline-block;
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins';
    }
    
    body {
      background-color: #f8f9fa;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background-image: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
    }
    
    .login-container {
      width: 100%;
      max-width: 400px;
      padding: 2rem;
      animation: fadeIn 0.5s ease;
    }
    
    .login-box {
      background: white;
      padding: 2.5rem;
      border-radius: 10px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }
    
    .login-box:hover {
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
      transform: translateY(-5px);
    }
    
    .login-box h2 {
      color: #333;
      text-align: center;
      margin-bottom: 1.5rem;
      font-size: 1.8rem;
      font-weight: 600;
    }
    
    .error {
      color: var(--danger);
      background-color: #fdecea;
      padding: 0.75rem;
      border-radius: 5px;
      margin-bottom: 1rem;
      text-align: center;
      font-size: 0.9rem;
      border-left: 4px solid var(--danger);
    }
    
    .input-group {
      margin-bottom: 1.5rem;
      position: relative;
    }
    
    .input-group input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 1rem;
      transition: all 0.3s ease;
    }
    
    .input-group input:focus {
      border-color: var(--primary);
      outline: none;
      box-shadow: 0 0 0 3px rgba(85, 85, 85, 0.2);
    }
    
    .input-group input::placeholder {
      color: var(--dark-gray);
    }
    
    button[type="submit"] {
      width: 100%;
      padding: 12px;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    button[type="submit"]:hover {
      background-color: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    button[type="submit"]:active {
      transform: translateY(0);
    }
    
    .login-footer {
      text-align: center;
      margin-top: 1.5rem;
      color: var(--dark-gray);
      font-size: 0.9rem;
    }
    
    .login-footer a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
    }
    
    .login-footer a:hover {
      text-decoration: underline;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Logo styling if you want to add one */
    .logo {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    
    .logo img {
      height: 50px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-box">
      <!-- Add this if you have a logo -->
      <!-- <div class="logo">
        <img src="logo.png" alt="Inventory System Logo">
      </div> -->
      <div class="header-container">
        <div class="company-name">Active Media Designs & Printing</div>
        <h1 class="report-title">Daily Stock Report</h1>
      </div>
      
      <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
      
      <form method="POST">
        <div class="input-group">
          <input type="text" name="username" placeholder="Username" required autofocus />
        </div>
        
        <div class="input-group">
          <input type="password" name="password" placeholder="Password" required />
        </div>
        
        <button type="submit">Login</button>
      </form>
      
      <div class="login-footer">
        <p>Don't have an account? <a href="#">Contact admin</a></p>
      </div>
    </div>
  </div>
</body>
</html>