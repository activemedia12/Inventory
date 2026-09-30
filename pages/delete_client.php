<?php
session_start();
require_once '../config/db.php';
require_once 'permissions.php';

header('Content-Type: application/json');

// Must be logged in (this endpoint previously had no check at all)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

// JSON response (not the plain-text error) so clients.js can show it in its alert
if (!can('delete')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "You don't have permission to delete clients."]);
    exit;
}

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