<?php
// =============================
// 2. save_client.php
// =============================

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $mysqli->prepare("INSERT INTO clients (
        client_name, taxpayer_name, tin, tax_type, rdo_code, client_address,
        province, city, barangay, street, building_no, floor_no, zip_code,
        contact_person, contact_number, client_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "ssssssssssssssss",
        $_POST['client_name'],
        $_POST['taxpayer_name'],
        $_POST['tin'],
        $_POST['tax_type'],
        $_POST['rdo_code'],
        $_POST['client_address'],
        $_POST['province'],
        $_POST['city'],
        $_POST['barangay'],
        $_POST['street'],
        $_POST['building_no'],
        $_POST['floor_no'],
        $_POST['zip_code'],
        $_POST['contact_person'],
        $_POST['contact_number'],
        $_POST['client_by']
    );

    if ($stmt->execute()) {
        header("Location: clients.php?success=1");
        exit;
    } else {
        echo "Failed to save client: " . $stmt->error;
    }
}
