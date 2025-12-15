<?php
// admin_status.php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'heartbeat') {
    $stmt = $inventory->prepare("UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'timestamp' => date('Y-m-d H:i:s')]);
    exit;
}

$inventory->close();
?>