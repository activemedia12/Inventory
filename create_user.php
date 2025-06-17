<?php
$mysqli = new mysqli("localhost", "u382513771_admin", "Amdp@1205", "u382513771_inventory");

$username = 'admin';
$password = password_hash('amdp@1205', PASSWORD_DEFAULT);

$query = "INSERT INTO users (username, password) VALUES (?, ?)";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();

if ($stmt->affected_rows === 1) {
    echo "✅ User created successfully.";
} else {
    echo "❌ Failed to create user.";
}
?>
