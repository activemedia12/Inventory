<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo "Unauthorized access.";
  exit;
}

require_once '../config/db.php';

$job_id = intval($_POST['job_id'] ?? 0);
$new_status = $_POST['new_status'] ?? '';

if ($job_id <= 0 || !in_array($new_status, ['pending', 'completed'])) {
  http_response_code(400);
  echo "Invalid request. job_id: $job_id, new_status: $new_status";
  exit;
}


// Check current status
$current = $mysqli->query("SELECT status FROM job_orders WHERE id = $job_id")->fetch_assoc();
if (!$current) {
  http_response_code(404);
  echo "Job order not found.";
  exit;
}

if ($current['status'] === $new_status) {   
  echo "No change in status.";
  exit;
}

// Update status (and set completed_date if applicable)
if ($new_status === 'completed') {
  $stmt = $mysqli->prepare("UPDATE job_orders SET status = 'completed', completed_date = NOW() WHERE id = ?");
} else {
  $stmt = $mysqli->prepare("UPDATE job_orders SET status = 'pending', completed_date = NULL WHERE id = ?");
}

$stmt->bind_param("i", $job_id);

if ($stmt->execute()) {
  echo "Status updated to $new_status.";
} else {
  http_response_code(500);
  echo "Failed to update status.";
}
$stmt->close();
