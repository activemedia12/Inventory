<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $inventory->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Client deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete client.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
