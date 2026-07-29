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
    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbced3;
            border-radius: 8px;
        }

        :root {
            --primary: #4f5eff;
            --secondary: #4048e0;
            --primary-bg: #eef1ff;
            --light: #f6f6f7;
            --dark: #14171f;
            --gray: #6b7280;
            --light-gray: #e2e4e7;
            --card-bg: #ffffff;
            --success: #1a9c6b;
            --success-bg: #e3f6ee;
            --danger: #d9463c;
            --danger-bg: #fbe9e7;
            --warning: #b6790a;
            --warning-bg: #fdf2df;
            --info: #2a7ade;
            --info-bg: #e8f1fc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            border-right: 1px solid var(--light-gray);
            padding: 20px 0;
            overflow-y: auto;
        }

        .brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .brand img {
            height: 100px;
            width: auto;
            padding-left: 0;
            transform: none;
        }

        .nav-menu {
            list-style: none;
            padding: 0 12px;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .nav-menu li a:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        .nav-menu li.active>a,
        .nav-menu li a.active {
            background-color: var(--primary-bg);
            color: var(--secondary);
        }

        .nav-menu li a i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            color: var(--gray);
        }

        .nav-menu li.active>a i,
        .nav-menu li a:hover i {
            color: inherit;
        }

        .submenu {
            list-style: none;
            margin: 2px 0 6px 14px;
            padding-left: 14px;
            border-left: 2px solid var(--light-gray);
        }

        .submenu li a {
            padding: 7px 10px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--gray);
            border-radius: 6px;
        }

        .submenu li a:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        .submenu li a.activate {
            color: var(--secondary);
            font-weight: 600;
            background-color: var(--primary-bg);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 28px 32px;
            max-width: 900px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }

        .header-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .header-title h1 {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark);
        }

        .breadcrumb {
            font-size: 12.5px;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .breadcrumb a {
            color: var(--gray);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .user-details h4 {
            font-weight: 600;
            font-size: 14px;
        }

        .user-details small {
            color: var(--gray);
            font-size: 12px;
        }

        /* Alerts */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-size: 13px;
            font-weight: 500;
        }

        .alert i {
            margin-right: 10px;
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success);
        }

        .alert-danger {
            background-color: var(--danger-bg);
            color: var(--danger);
        }

        /* Info / stock summary card */
        .info-banner {
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--primary-bg);
            border-radius: 8px;
            padding: 16px 18px;
            margin-bottom: 20px;
        }

        .info-banner .icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .info-banner .value {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
        }

        .info-banner .label {
            font-size: 12px;
            color: var(--gray);
        }

        /* Form card */
        .form-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            overflow: hidden;
        }

        .form-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-card-header i {
            color: var(--gray);
        }

        .form-card-header h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-card-body {
            padding: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 16px;
        }

        .form-group {
            margin-bottom: 4px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 13px;
            background: var(--card-bg);
            color: var(--dark);
            transition: border-color 0.15s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--light-gray);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease;
            border: none;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--gray);
        }

        .btn-outline:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar-con {
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                position: fixed;
            }

            .sidebar {
                position: fixed;
                overflow: hidden;
                height: auto;
                width: auto;
                bottom: 12px;
                left: 50%;
                transform: translateX(-50%);
                padding: 6px;
                background-color: rgba(255, 255, 255, 0.85);
                backdrop-filter: blur(6px);
                box-shadow: 0 4px 16px rgba(20, 23, 31, 0.12);
                border-radius: 100px;
                touch-action: manipulation;
                z-index: 9999;
                flex-direction: row;
                border: 1px solid var(--light-gray);
                justify-content: center;
            }

            .sidebar .nav-menu {
                display: flex;
                flex-direction: row;
                padding: 0;
            }

            .sidebar img,
            .sidebar .brand,
            .sidebar .nav-menu li a span,
            .sidebar .submenu {
                display: none;
            }

            .sidebar .nav-menu li a {
                justify-content: center;
                padding: 12px;
            }

            .sidebar .nav-menu li a i {
                margin-right: 0;
            }

            .main-content {
                margin-left: 0;
                margin-bottom: 90px;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
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

    <script>
        // Format phone number input
        document.getElementById('contact_number')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^\d+]/g, '');
        });

        // Focus first field with error
        document.querySelector('form').addEventListener('submit', function(e) {
            updateClientAddress();
            const invalidFields = this.querySelectorAll(':invalid');
            if (invalidFields.length > 0) {
                e.preventDefault();
                invalidFields[0].focus();
                invalidFields[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });

        // Build the client_address string from the structured fields below,
        // exactly the same way clients.php and job_orders.php do it, so the
        // saved address always matches what job_orders.php will prefill.
        function updateClientAddress() {
            const floor = document.getElementById("floor_no").value.trim();
            const building = document.getElementById("building_no").value.trim();
            const street = document.getElementById("street").value.trim();
            const barangayEl = document.getElementById("barangay");
            const barangay = barangayEl.value.trim().replace(/\b\w/g, c => c.toUpperCase());
            barangayEl.value = barangay;

            const city = document.getElementById("city").value.trim();
            const province = document.getElementById("province").value.trim();
            const zip = document.getElementById("zip_code").value.trim();

            const parts = [];
            if (floor) parts.push(floor);
            if (building) parts.push(building);
            if (street) parts.push(street);
            if (barangay) parts.push("Brgy. " + barangay);
            if (city) parts.push(city);
            if (province) parts.push(province);
            if (zip) parts.push(zip);

            const fullAddress = parts.join(", ");
            document.getElementById("client_address").value = fullAddress;

            const preview = document.getElementById("client_address_preview");
            if (preview) preview.value = fullAddress;
        }

        // Suggest RDO code based on the selected city
        function suggestRDO() {
            const city = document.getElementById("city").value.trim();
            const rdoInput = document.getElementById("rdo_code");

            const matchedCity = Object.keys(rdoMapping).find(key =>
                city.toLowerCase().includes(key.toLowerCase())
            );

            if (matchedCity) {
                rdoInput.value = `${rdoMapping[matchedCity]} - ${matchedCity}`;
            }
        }

        const rdoMapping = {
            "Laoag City, Ilocos Norte": "001",
            "Vigan, Ilocos Sur": "002",
            "San Fernando, La Union": "003",
            "Calasiao, West Pangasinan": "004",
            "Alaminos, Pangasinan": "005",
            "Urdaneta, Pangasinan": "006",
            "Bangued, Abra": "007",
            "Baguio City": "008",
            "La Trinidad, Benguet": "009",
            "Bontoc, Mt. Province": "010",
            "Tabuk City, Kalinga": "011",
            "Lagawe, Ifugao": "012",
            "Tuguegarao, Cagayan": "013",
            "Bayombong, Nueva Vizcaya": "014",
            "Naguilian, Isabela": "015",
            "Cabarroguis, Quirino": "016",
            "Tarlac City, Tarlac": "17A",
            "Paniqui, Tarlac": "17B",
            "Olongapo City": "018",
            "Subic Bay Freeport Zone": "019",
            "Balanga, Bataan": "020",
            "North Pampanga": "21A",
            "South Pampanga": "21B",
            "Clark Freeport Zone": "21C",
            "Baler, Aurora": "022",
            "North Nueva Ecija": "23A",
            "South Nueva Ecija": "23B",
            "Valenzuela City": "024",
            "Plaridel, Bulacan": "25A (now RDO West Bulacan)",
            "Sta. Maria, Bulacan": "25B (now RDO East Bulacan)",
            "Malabon-Navotas": "026",
            "Caloocan City": "027",
            "Novaliches": "028",
            "Tondo – San Nicolas": "029",
            "Binondo": "030",
            "Sta. Cruz": "031",
            "Quiapo-Sampaloc-San Miguel-Sta. Mesa": "032",
            "Intramuros-Ermita-Malate": "033",
            "Paco-Pandacan-Sta. Ana-San Andres": "034",
            "Romblon": "035",
            "Puerto Princesa": "036",
            "San Jose, Occidental Mindoro": "037",
            "North Quezon City": "038",
            "South Quezon City": "039",
            "Cubao": "040",
            "Mandaluyong City": "041",
            "San Juan": "042",
            "Pasig": "043",
            "Taguig-Pateros": "044",
            "Marikina": "045",
            "Cainta-Taytay": "046",
            "East Makati": "047",
            "West Makati": "048",
            "North Makati": "049",
            "South Makati": "050",
            "Pasay City": "051",
            "Parañaque": "052",
            "Las Piñas City": "53A",
            "Muntinlupa City": "53B",
            "Trece Martirez City, East Cavite": "54A",
            "Kawit, West Cavite": "54B",
            "San Pablo City": "055",
            "Calamba, Laguna": "056",
            "Biñan, Laguna": "057",
            "Batangas City": "058",
            "Lipa City": "059",
            "Lucena City": "060",
            "Gumaca, Quezon": "061",
            "Boac, Marinduque": "062",
            "Calapan, Oriental Mindoro": "063",
            "Talisay, Camarines Norte": "064",
            "Naga City": "065",
            "Iriga City": "066",
            "Legazpi City, Albay": "067",
            "Sorsogon, Sorsogon": "068",
            "Virac, Catanduanes": "069",
            "Masbate, Masbate": "070",
            "Kalibo, Aklan": "071",
            "Roxas City": "072",
            "San Jose, Antique": "073",
            "Iloilo City": "074",
            "Zarraga, Iloilo City": "075",
            "Victorias City, Negros Occidental": "076",
            "Bacolod City": "077",
            "Binalbagan, Negros Occidental": "078",
            "Dumaguete City": "079",
            "Mandaue City": "080",
            "Cebu City North": "081",
            "Cebu City South": "082",
            "Talisay City, Cebu": "083",
            "Tagbilaran City": "084",
            "Catarman, Northern Samar": "085",
            "Borongan, Eastern Samar": "086",
            "Calbayog City, Samar": "087",
            "Tacloban City": "088",
            "Ormoc City": "089",
            "Maasin, Southern Leyte": "090",
            "Dipolog City": "091",
            "Pagadian City, Zamboanga del Sur": "092",
            "Zamboanga City, Zamboanga del Sur": "093A",
            "Ipil, Zamboanga Sibugay": "093B",
            "Isabela, Basilan": "094",
            "Jolo, Sulu": "095",
            "Bongao, Tawi-Tawi": "096",
            "Gingoog City": "097",
            "Cagayan de Oro City": "098",
            "Malaybalay City, Bukidnon": "099",
            "Ozamis City": "100",
            "Iligan City": "101",
            "Marawi City": "102",
            "Butuan City": "103",
            "Bayugan City, Agusan del Sur": "104",
            "Surigao City": "105",
            "Tandag, Surigao del Sur": "106",
            "Cotabato City": "107",
            "Kidapawan, North Cotabato": "108",
            "Tacurong, Sultan Kudarat": "109",
            "General Santos City": "110",
            "Koronadal City, South Cotabato": "111",
            "Tagum, Davao del Norte": "112",
            "West Davao City": "113A",
            "East Davao City": "113B",
            "Mati, Davao Oriental": "114",
            "Digos, Davao del Sur": "115"
        };

        document.addEventListener('DOMContentLoaded', function() {
            // Prevent commas in the address-part fields
            document.querySelectorAll('input[pattern="[^,]*"]').forEach(input => {
                input.addEventListener('keydown', e => {
                    if (e.key === ',') e.preventDefault();
                });
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/,/g, '');
                });
            });

            // Keep the address preview + hidden field in sync as the user types
            ["floor_no", "building_no", "street", "barangay", "zip_code"].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener("input", updateClientAddress);
            });

            // Province → City dropdown
            const province = document.getElementById("province");
            const city = document.getElementById("city");

            if (province && city) {
                province.addEventListener("change", function() {
                    const selectedProvince = this.value;
                    city.innerHTML = '<option value="">Select City</option>';
                    updateClientAddress();

                    if (!selectedProvince) return;

                    fetch(`get_cities.php?province=${encodeURIComponent(selectedProvince)}`)
                        .then(res => res.json())
                        .then(cities => {
                            cities.forEach(cityName => {
                                const option = document.createElement("option");
                                option.value = cityName;
                                option.textContent = cityName;
                                city.appendChild(option);
                            });
                        });
                });

                city.addEventListener("change", () => {
                    suggestRDO();
                    updateClientAddress();
                });
            }

            // Make sure the hidden/preview address reflects the loaded data on first render
            updateClientAddress();
        });
    </script>
</body>

</html>