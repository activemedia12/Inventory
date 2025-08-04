<?php
// products.php
session_start();
$default = 'papers.php';
$last = $_COOKIE['lastProductPage'] ?? null;

// Basic security: allow only known pages
$allowed = ['papers.php', 'insuances.php'];

if ($last && in_array(basename($last), $allowed)) {
    header("Location: $last");
} else {
    header("Location: $default");
}
exit;
?>
