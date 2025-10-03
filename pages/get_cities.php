<?php
require_once '../config/db.php';

$province = $_GET['province'] ?? '';
$province = $inventory->real_escape_string($province);

$cities = [];
$result = $inventory->query("SELECT city FROM locations WHERE province = '$province' ORDER BY city ASC");
while ($row = $result->fetch_assoc()) {
    $cities[] = $row['city'];
}

header('Content-Type: application/json');
echo json_encode($cities);
