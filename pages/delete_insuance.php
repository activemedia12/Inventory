<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $mysqli->prepare("DELETE FROM insuances WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: insuances.php?msg=Insuance+deleted+successfully");
    } else {
        header("Location: insuances.php?msg=Failed+to+delete+insuance");
    }
    exit;
}
?>
