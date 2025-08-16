<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
        header("Location: website/main.php");
    } else {
        header("Location: pages/dashboard.php");
    }
} else {
    header("Location: accounts/login.php");
}
exit;