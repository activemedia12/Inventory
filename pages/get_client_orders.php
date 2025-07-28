<?php
require_once '../config/db.php';

$client_id = intval($_GET['client_id'] ?? 0);
$response = [
  'total_orders' => 0,
  'recent_orders' => []
];

if ($client_id > 0) {
  // Get client name from clients table
  $stmt = $mysqli->prepare("SELECT client_name FROM clients WHERE id = ?");
  $stmt->bind_param('i', $client_id);
  $stmt->execute();
  $stmt->bind_result($client_name);
  $stmt->fetch();
  $stmt->close();

  if ($client_name) {
    // Count job orders matching the client name
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM job_orders WHERE client_name = ?");
    $stmt->bind_param('s', $client_name);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    $response['total_orders'] = $total;

    // Fetch 3 most recent orders
    $stmt = $mysqli->prepare("
      SELECT project_name, log_date 
      FROM job_orders 
      WHERE client_name = ? 
      ORDER BY log_date DESC 
      LIMIT 5
    ");
    $stmt->bind_param('s', $client_name);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
      $response['recent_orders'][] = $row;
    }
  }
}

header('Content-Type: application/json');
echo json_encode($response);
