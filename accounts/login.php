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

<h2>Login</h2>
<form method="post">
  Username: <input type="text" name="username"><br>
  Password: 
  <input type="password" name="password" id="login-password">
  <button type="button" onclick="togglePassword('login-password', this)">Show</button><br>
  <button type="submit">Login</button>
</form>

<p><?php echo $error; ?></p>
<p>Don't have an account? <a href="signup.php">Create one</a></p>

<script src="../assets/js/password-toggle.js"></script>
