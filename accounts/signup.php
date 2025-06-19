<?php
require_once '../config/db.php';

$message = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (!empty($username) && !empty($password)) {
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
    } else {
        $message = "Username and password are required.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Sign Up</title>
  <?php if ($success): ?>
    <meta http-equiv="refresh" content="3;url=login.php">
  <?php endif; ?>
</head>
<body>

<div class="container">
  <h2 class="title">Create Account</h2>

  <?php if (!empty($message)): ?>
    <div class="message <?php echo $success ? 'success' : 'error'; ?>">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="form">
    <div class="form-group">
      <label for="username">Username:</label><br>
      <input type="text" id="username" name="username" required>
    </div>

    <div class="form-group">
      <label for="signup-password">Password:</label><br>
      <input type="password" id="signup-password" name="password" required>
      <button type="button" onclick="togglePassword('signup-password', this)">Show</button>
    </div>

    <div class="form-group">
      <label for="role">Role:</label><br>
      <select name="role" id="role">
        <option value="employee">Employee</option>
        <option value="admin">Admin</option>
      </select>
    </div>

    <div class="form-group">
      <button type="submit">Register</button>
    </div>

    <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
  </form>
</div>

<script src="../assets/js/password-toggle.js"></script>

</body>
</html>
