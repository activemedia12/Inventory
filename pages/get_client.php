<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config/db.php';

$client_id = intval($_GET['id'] ?? 0);
header('Content-Type: application/json');

if ($client_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid client id']);
    exit;
}

$stmt = $inventory->prepare(
    "SELECT id, client_name, taxpayer_name, tin, rdo_code, client_address,
            contact_person, contact_number, client_by
     FROM clients WHERE id = ?"
);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
$stmt->close();

if (!$client) {
    http_response_code(404);
    echo json_encode(['error' => 'Client not found']);
    exit;
}

echo json_encode($client);