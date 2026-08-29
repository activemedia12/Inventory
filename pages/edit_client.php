<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}
require_once '../config/db.php';

if (!isset($_GET['id'])) {
    echo "No client selected.";
    exit;
}

$client_id = intval($_GET['id']);
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $inventory->prepare("UPDATE clients SET 
        client_name = ?, 
        taxpayer_name = ?, 
        tin = ?, 
        tax_type = ?, 
        rdo_code = ?, 
        client_address = ?, 
        province = ?,
        city = ?,
        barangay = ?,
        street = ?,
        building_no = ?,
        floor_no = ?,
        zip_code = ?,
        contact_person = ?, 
        contact_number = ?, 
        client_by = ?
        WHERE id = ?");

    $stmt->bind_param(
        "ssssssssssssssssi",
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
        $_POST['client_by'],
        $client_id
    );

    if ($stmt->execute()) {
        $message = "Client updated successfully. Redirecting to client list...";
        echo "<meta http-equiv='refresh' content='3;url=clients.php'>";
    } else {
        $message = "Failed to update client.";
    }
}

// Fetch provinces for the address dropdown (same source used on clients.php / job_orders.php)
$provinces = [];
$prov_result = $inventory->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($prow = $prov_result->fetch_assoc()) {
    $provinces[] = $prow['province'];
}

// Fetch current client data
$stmt = $inventory->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();

if (!$client) {
    echo "Client not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Edit Client - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/pages/edit_client.css">
</head>

<body>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li>
                    <a href="papers.php">
                        <i class="fas fa-boxes"></i> <span>Products</span>
                    </a>
                </li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li class="active"><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-title">
                <h1>Edit Client</h1>
                <div class="breadcrumb">
                    <a href="clients.php">Client Information</a> <i class="fas fa-chevron-right" style="font-size:9px;"></i> <span>Edit</span>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'Failed') !== false ? 'alert-danger' : 'alert-success' ?>">
                <i class="fas <?= strpos($message, 'Failed') !== false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-user-edit"></i>
                <h3><?= htmlspecialchars($client['client_name']) ?></h3>
            </div>
            <div class="form-card-body">
                <form method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Client Name *</label>
                            <input type="text" name="client_name" class="form-control" required
                                value="<?= htmlspecialchars($client['client_name']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Taxpayer Name *</label>
                            <input type="text" name="taxpayer_name" class="form-control" required
                                value="<?= htmlspecialchars($client['taxpayer_name']) ?>">
                        </div>

                        <div class="form-group">
                            <label>TIN</label>
                            <input type="text" name="tin" class="form-control"
                                value="<?= htmlspecialchars($client['tin']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Tax Type *</label>
                            <select name="tax_type" class="form-control" required>
                                <?php
                                $types = ['VAT', 'NONVAT', 'VAT-EXEMPT', 'NON-VAT EXEMPT', 'EXEMPT'];
                                foreach ($types as $type):
                                ?>
                                    <option value="<?= $type ?>" <?= $client['tax_type'] === $type ? 'selected' : '' ?>>
                                        <?= $type ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>RDO Code</label>
                            <input type="text" name="rdo_code" class="form-control"
                                value="<?= htmlspecialchars($client['rdo_code']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Contact Person *</label>
                            <input type="text" name="contact_person" class="form-control" required
                                value="<?= htmlspecialchars($client['contact_person']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Contact Number *</label>
                            <input type="text" id="contact_number" name="contact_number" class="form-control" required
                                value="<?= htmlspecialchars($client['contact_number']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Client By *</label>
                            <input type="text" name="client_by" class="form-control" required
                                value="<?= htmlspecialchars($client['client_by']) ?>">
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1; margin-top: 8px; padding-top: 12px; border-top: 1px solid var(--light-gray);">
                            <label style="margin-bottom:0;">Address</label>
                            <p style="font-size:12px; color:var(--gray); margin: 2px 0 0; text-transform:none; font-weight:400;">This feeds directly into the address used when creating job orders for this client.</p>
                        </div>

                        <input type="hidden" name="client_address" id="client_address" value="<?= htmlspecialchars($client['client_address']) ?>">

                        <div class="form-group">
                            <label>Province *</label>
                            <select id="province" name="province" class="form-control" required>
                                <option value="">Select Province</option>
                                <?php foreach ($provinces as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov) ?>" <?= ($client['province'] ?? '') === $prov ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>City / Municipality *</label>
                            <select id="city" name="city" class="form-control" required>
                                <option value="<?= htmlspecialchars($client['city'] ?? '') ?>" selected>
                                    <?= htmlspecialchars($client['city'] ?? 'Select City') ?>
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Barangay</label>
                            <input type="text" id="barangay" name="barangay" class="form-control"
                                placeholder="e.g. San Isidro" pattern="[^,]*" title="Commas are not allowed"
                                value="<?= htmlspecialchars($client['barangay'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Subdivision / Street</label>
                            <input type="text" id="street" name="street" class="form-control"
                                placeholder="e.g. Rizal St." pattern="[^,]*" title="Commas are not allowed"
                                value="<?= htmlspecialchars($client['street'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Building / House No.</label>
                            <input type="text" id="building_no" name="building_no" class="form-control"
                                placeholder="e.g. Bldg 4, Lot 6" pattern="[^,]*" title="Commas are not allowed"
                                value="<?= htmlspecialchars($client['building_no'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Floor / Room No.</label>
                            <input type="text" id="floor_no" name="floor_no" class="form-control"
                                placeholder="e.g. 2F, Room 201" pattern="[^,]*" title="Commas are not allowed"
                                value="<?= htmlspecialchars($client['floor_no'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>ZIP Code</label>
                            <input type="text" id="zip_code" name="zip_code" class="form-control"
                                placeholder="e.g. 3020" pattern="[^,]*" title="Commas are not allowed"
                                value="<?= htmlspecialchars($client['zip_code'] ?? '') ?>">
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Full Address Preview</label>
                            <input type="text" class="form-control" id="client_address_preview" readonly
                                style="background:var(--light); color:var(--gray);"
                                value="<?= htmlspecialchars($client['client_address']) ?>">
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="clients.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="../assets/js/pages/edit_client.js"></script>
</body>

</html>