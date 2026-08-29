<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$job_id = intval($_GET['id'] ?? 0);
if ($job_id <= 0) {
    header("Location: job_orders.php");
    exit;
}

// Fetch existing job order
$stmt = $inventory->prepare("SELECT * FROM job_orders WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: job_orders.php");
    exit;
}

// ── POST handler ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name          = $_POST['client_name']          ?? '';
    $contact_person       = $_POST['contact_person']       ?? '';
    $contact_number       = $_POST['contact_number']       ?? '';
    $project_name         = $_POST['project_name']         ?? '';
    $serial_range         = $_POST['serial_range']         ?? '';
    $quantity             = intval($_POST['quantity']);
    $number_of_sets       = intval($_POST['number_of_sets']);
    $product_size         = $_POST['product_size']         ?? '';
    $paper_size           = $_POST['paper_size']           ?? '';
    $custom_paper_size    = $_POST['custom_paper_size']    ?? '';
    $paper_type           = $_POST['paper_type']           ?? '';
    $copies_per_set       = intval($_POST['copies_per_set']);
    $binding_type         = $_POST['binding_type']         ?? '';
    $custom_binding       = $_POST['custom_binding']       ?? '';
    $special_instructions = $_POST['special_instructions'] ?? '';
    $log_date             = $_POST['log_date']             ?? date('Y-m-d');
    $tin                  = trim($_POST['tin']             ?? '');
    $client_by            = trim($_POST['client_by']       ?? '');
    $tax_type             = trim($_POST['tax_type']        ?? '');
    $ocn_number           = trim($_POST['ocn_number']      ?? '');
    $date_issued          = !empty($_POST['date_issued'])  ? $_POST['date_issued'] : null;
    $taxpayer_name        = trim($_POST['taxpayer_name']   ?? '');
    $rdo_code             = trim($_POST['rdo_code']        ?? '');
    $province             = trim($_POST['province']        ?? '');
    $city                 = trim($_POST['city']            ?? '');
    $barangay             = trim($_POST['barangay']        ?? '');
    $street               = trim($_POST['street']          ?? '');
    $building_no          = trim($_POST['building_no']     ?? '');
    $floor_no             = trim($_POST['floor_no']        ?? '');
    $zip_code             = trim($_POST['zip_code']        ?? '');
    $spoilage             = is_array($_POST['spoilage'] ?? null) ? $_POST['spoilage'] : [];

    // Build client_address from components
    $client_address = implode(', ', array_filter([
        $floor_no,
        $building_no,
        $street,
        $barangay ? 'Brgy. ' . $barangay : '',
        $city,
        $province,
        $zip_code
    ]));

    $new_sequence        = $_POST['paper_sequence'] ?? [];
    $paper_sequence_str  = implode(', ', array_map('trim', $new_sequence));

    // Cut size map — matches job_orders.php (up to 1/50)
    $cut_size_map = [
        '1/2' => 2,
        '1/3' => 3,
        '1/4' => 4,
        '1/6' => 6,
        '1/8' => 8,
        '1/10' => 10,
        '1/12' => 12,
        '1/14' => 14,
        '1/16' => 16,
        '1/18' => 18,
        '1/20' => 20,
        '1/22' => 22,
        '1/24' => 24,
        '1/25' => 25,
        '1/26' => 26,
        '1/28' => 28,
        '1/30' => 30,
        '1/32' => 32,
        '1/36' => 36,
        '1/40' => 40,
        '1/48' => 48,
        '1/50' => 50,
        'whole' => 1,
    ];
    $cut_size             = $cut_size_map[$product_size] ?? 1;
    $total_sets           = $quantity * $number_of_sets;
    $used_sheets_per_product = intval($total_sets / $cut_size);

    // Update job order
    $stmt = $inventory->prepare("UPDATE job_orders SET
        log_date = ?, client_name = ?, client_address = ?, contact_person = ?, contact_number = ?,
        project_name = ?, quantity = ?, number_of_sets = ?, product_size = ?, serial_range = ?,
        paper_size = ?, custom_paper_size = ?, paper_type = ?, copies_per_set = ?, binding_type = ?,
        custom_binding = ?, special_instructions = ?, paper_sequence = ?,
        tin = ?, client_by = ?, tax_type = ?, ocn_number = ?, date_issued = ?,
        taxpayer_name = ?, rdo_code = ?,
        province = ?, city = ?, barangay = ?, street = ?, building_no = ?, floor_no = ?, zip_code = ?
        WHERE id = ?");

    $stmt->bind_param(
        "ssssssiisssssissssssssssssssssssi",
        $log_date,
        $client_name,
        $client_address,
        $contact_person,
        $contact_number,
        $project_name,
        $quantity,
        $number_of_sets,
        $product_size,
        $serial_range,
        $paper_size,
        $custom_paper_size,
        $paper_type,
        $copies_per_set,
        $binding_type,
        $custom_binding,
        $special_instructions,
        $paper_sequence_str,
        $tin,
        $client_by,
        $tax_type,
        $ocn_number,
        $date_issued,
        $taxpayer_name,
        $rdo_code,
        $province,
        $city,
        $barangay,
        $street,
        $building_no,
        $floor_no,
        $zip_code,
        $job_id
    );

    if (!$stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error updating job order: " . $stmt->error . "</div>";
        $stmt->close();
        header("Location: edit_job.php?id=$job_id");
        exit;
    }
    $stmt->close();

    // ── Fix 4: Fetch all product IDs at once (no N+1) ────────────────
    $product_ids = []; // color => product_id
    if (!empty($new_sequence)) {
        $unique_colors = array_unique(array_map('trim', $new_sequence));
        $placeholders  = implode(',', array_fill(0, count($unique_colors), '?'));
        $id_stmt = $inventory->prepare(
            "SELECT id, product_name FROM products
             WHERE product_type = ? AND product_group = ? AND product_name IN ($placeholders)
             LIMIT " . count($unique_colors)
        );
        $bind_types = 'ss' . str_repeat('s', count($unique_colors));
        $bind_args  = array_merge([$paper_type, $paper_size], array_values($unique_colors));
        $id_stmt->bind_param($bind_types, ...$bind_args);
        $id_stmt->execute();
        $id_result = $id_stmt->get_result();
        while ($row = $id_result->fetch_assoc()) {
            $product_ids[$row['product_name']] = $row['id'];
        }
        $id_stmt->close();
    }

    // ── Fix 8: Validate stock using prepared statements ───────────────
    foreach ($new_sequence as $i => $color) {
        $color  = trim($color);
        $spoil  = intval($spoilage[$i] ?? 0);
        $prod_id = $product_ids[$color] ?? null;
        if (!$prod_id) continue;

        // Delivered sheets
        $del_stmt = $inventory->prepare(
            "SELECT IFNULL(SUM(delivered_reams), 0) AS total FROM delivery_logs WHERE product_id = ?"
        );
        $del_stmt->bind_param("i", $prod_id);
        $del_stmt->execute();
        $delivered_sheets = (int)$del_stmt->get_result()->fetch_assoc()['total'] * 500;
        $del_stmt->close();

        // Used sheets EXCLUDING current job
        $used_stmt = $inventory->prepare(
            "SELECT IFNULL(SUM(used_sheets + spoilage_sheets), 0) AS total
             FROM usage_logs WHERE product_id = ? AND job_order_id != ?"
        );
        $used_stmt->bind_param("ii", $prod_id, $job_id);
        $used_stmt->execute();
        $used_sheets = (int)$used_stmt->get_result()->fetch_assoc()['total'];
        $used_stmt->close();

        // Allow negative stock — no blocking on insufficient stock
    }

    // ── Delete old usage logs (prepared statement) ────────────────────
    $del_logs = $inventory->prepare("DELETE FROM usage_logs WHERE job_order_id = ?");
    $del_logs->bind_param("i", $job_id);
    $del_logs->execute();
    $del_logs->close();

    // ── Insert updated usage logs ─────────────────────────────────────
    if (!empty($product_ids)) {
        $log_stmt = $inventory->prepare(
            "INSERT INTO usage_logs (product_id, used_sheets, spoilage_sheets, log_date, job_order_id, usage_note)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($new_sequence as $i => $color) {
            $color   = trim($color);
            $spoil   = intval($spoilage[$i] ?? 0);
            $prod_id = $product_ids[$color] ?? null;
            if (!$prod_id) continue;

            $note = "Updated job order for " . $client_name;
            $log_stmt->bind_param("iiisis", $prod_id, $used_sheets_per_product, $spoil, $log_date, $job_id, $note);
            $log_stmt->execute();
        }
        $log_stmt->close();
    }

    // Also update the client record with latest details
    $client_upd = $inventory->prepare(
        "UPDATE clients SET taxpayer_name=?, tin=?, tax_type=?, rdo_code=?, client_address=?,
         province=?, city=?, barangay=?, street=?, building_no=?, floor_no=?, zip_code=?,
         contact_person=?, client_by=?
         WHERE client_name=? AND contact_number=? LIMIT 1"
    );
    $client_upd->bind_param(
        "ssssssssssssssss",
        $taxpayer_name,
        $tin,
        $tax_type,
        $rdo_code,
        $client_address,
        $province,
        $city,
        $barangay,
        $street,
        $building_no,
        $floor_no,
        $zip_code,
        $contact_person,
        $client_by,
        $client_name,
        $contact_number
    );
    $client_upd->execute();
    $client_upd->close();

    // PRG redirect
    $_SESSION['message'] = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Job order updated successfully.</div>";
    header("Location: job_orders.php");
    exit;
}

// ── Fetch provinces for dropdown ──────────────────────────────────────
$provinces = [];
$res = $inventory->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($row = $res->fetch_assoc()) $provinces[] = $row['province'];

// ── Fetch spoilage map for this job ───────────────────────────────────
$spoilage_map = [];
$sq = $inventory->prepare(
    "SELECT p.product_name, u.spoilage_sheets FROM usage_logs u
     JOIN products p ON u.product_id = p.id WHERE u.job_order_id = ?"
);
$sq->bind_param("i", $job_id);
$sq->execute();
$sr = $sq->get_result();
while ($row = $sr->fetch_assoc()) {
    $spoilage_map[$row['product_name']] = intval($row['spoilage_sheets']);
}
$sq->close();

// ── Fix 5: Products query EXCLUDES current job's usage ────────────────
$product_query = $inventory->prepare("
    SELECT
        p.id, p.product_name, p.product_type, p.product_group,
        COALESCE(d.total_delivered, 0) * 500
        - COALESCE(u.total_used, 0)
        + COALESCE(this_job.job_used, 0) AS available_sheets
    FROM products p
    LEFT JOIN (
        SELECT product_id, SUM(delivered_reams) AS total_delivered
        FROM delivery_logs GROUP BY product_id
    ) d ON p.id = d.product_id
    LEFT JOIN (
        SELECT product_id, SUM(used_sheets + spoilage_sheets) AS total_used
        FROM usage_logs GROUP BY product_id
    ) u ON p.id = u.product_id
    LEFT JOIN (
        SELECT product_id, SUM(used_sheets + spoilage_sheets) AS job_used
        FROM usage_logs WHERE job_order_id = ? GROUP BY product_id
    ) this_job ON p.id = this_job.product_id
");
$product_query->bind_param("i", $job_id);
$product_query->execute();
$all_products = $product_query->get_result()->fetch_all(MYSQLI_ASSOC);
$product_query->close();

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Order <?= $job_id ?></title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/pages/edit_job.css">
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
                    <ul class="submenu">
                        <li><a href="papers.php">Papers</a></li>
                        <li><a href="insuances.php">Consumables</a></li>
                    </ul>
                </li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li class="active"><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="container">
            <header class="page-header">
                <div class="page-title">
                    <div>
                        <h1>Edit Job Order #<?= $job_id ?></h1>
                        <div class="breadcrumb">
                            <a href="job_orders.php">Job Orders</a> <i class="fas fa-chevron-right" style="font-size:9px;"></i> <span>Edit #<?= $job_id ?></span>
                        </div>
                    </div>
                    <span class="job-status <?= htmlspecialchars($job['status']) ?>"><?= htmlspecialchars($job['status']) ?></span>
                </div>
            </header>

            <?php if ($message): ?><?= $message ?><?php endif; ?>

            <div class="info-banner">
                <div class="icon"><i class="fas fa-building"></i></div>
                <div>
                    <div class="value"><?= htmlspecialchars($job['client_name']) ?> — <?= htmlspecialchars($job['project_name']) ?></div>
                    <div class="label">Ordered <?= date('M j, Y', strtotime($job['log_date'])) ?> &middot; Qty <?= (int)$job['quantity'] ?> &middot; <?= (int)$job['number_of_sets'] ?> set(s)</div>
                </div>
            </div>

            <form method="post" class="edit-form">
                <div class="form-tabs">
                    <div class="form-tab active" data-tab="client-info"><i class="fas fa-building"></i> Client Info</div>
                    <div class="form-tab" data-tab="order-details"><i class="fas fa-clipboard-list"></i> Order Details</div>
                    <div class="form-tab" data-tab="specifications"><i class="fas fa-tools"></i> Specifications</div>
                </div>

                <div class="form-content">

                    <!-- ── Client Info ── -->
                    <div class="tab-content active" id="client-info">
                      <div class="tab-grid">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Company / Trade Name *</label>
                                    <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($job['client_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Taxpayer Name</label>
                                    <input type="text" name="taxpayer_name" class="form-control" value="<?= htmlspecialchars($job['taxpayer_name'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>TIN</label>
                                    <input type="text" name="tin" class="form-control" value="<?= htmlspecialchars($job['tin'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Tax Type *</label>
                                    <div class="radio-group">
                                        <?php foreach (['VAT', 'NONVAT', 'VAT-EXEMPT', 'NON-VAT EXEMPT', 'EXEMPT'] as $tt): ?>
                                            <div class="radio-option">
                                                <input type="radio" name="tax_type" value="<?= $tt ?>" <?= ($job['tax_type'] ?? '') === $tt ? 'checked' : '' ?> required>
                                                <label><?= $tt ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>RDO Code</label>
                                    <input type="text" name="rdo_code" class="form-control" value="<?= htmlspecialchars($job['rdo_code'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Client By *</label>
                                    <input type="text" name="client_by" class="form-control" value="<?= htmlspecialchars($job['client_by'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-address-card"></i> Contact</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Contact Person *</label>
                                    <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($job['contact_person']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Contact Number *</label>
                                    <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($job['contact_number']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-section span-2">
                            <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Address</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Province *</label>
                                    <select id="province" name="province" class="form-control" required>
                                        <option value="">Select Province</option>
                                        <?php foreach ($provinces as $prov): ?>
                                            <option value="<?= htmlspecialchars($prov) ?>" <?= ($job['province'] ?? '') === $prov ? 'selected' : '' ?>><?= htmlspecialchars($prov) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>City / Municipality *</label>
                                    <select id="city" name="city" class="form-control" required>
                                        <option value="<?= htmlspecialchars($job['city'] ?? '') ?>" selected><?= htmlspecialchars($job['city'] ?? 'Select City') ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Barangay</label>
                                    <input type="text" name="barangay" class="form-control" value="<?= htmlspecialchars($job['barangay'] ?? '') ?>" placeholder="e.g. San Isidro">
                                </div>
                                <div class="form-group">
                                    <label>Street</label>
                                    <input type="text" name="street" class="form-control" value="<?= htmlspecialchars($job['street'] ?? '') ?>" placeholder="e.g. Rizal St.">
                                </div>
                                <div class="form-group">
                                    <label>Building / Block</label>
                                    <input type="text" name="building_no" class="form-control" value="<?= htmlspecialchars($job['building_no'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Lot / Room No.</label>
                                    <input type="text" name="floor_no" class="form-control" value="<?= htmlspecialchars($job['floor_no'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>ZIP Code</label>
                                    <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($job['zip_code'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                      </div>
                    </div>

                    <!-- ── Order Details ── -->
                    <div class="tab-content" id="order-details">
                      <div class="tab-grid">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-project-diagram"></i> Project Information</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Project Name *</label>
                                    <input type="text" name="project_name" class="form-control" value="<?= htmlspecialchars($job['project_name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Serial Range *</label>
                                    <input type="text" name="serial_range" class="form-control" value="<?= htmlspecialchars($job['serial_range']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Order Date *</label>
                                    <input type="date" name="log_date" class="form-control" value="<?= htmlspecialchars($job['log_date']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>OCN Number</label>
                                    <input type="text" name="ocn_number" class="form-control" value="<?= htmlspecialchars($job['ocn_number'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Date Issued</label>
                                    <input type="date" name="date_issued" class="form-control" value="<?= htmlspecialchars($job['date_issued'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-cubes"></i> Quantity &amp; Sets</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Order Quantity *</label>
                                    <input type="number" name="quantity" min="1" class="form-control" value="<?= $job['quantity'] ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Sets per Bind *</label>
                                    <input type="number" name="number_of_sets" min="1" class="form-control" value="<?= $job['number_of_sets'] ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Copies per Set *</label>
                                    <input type="number" id="copies_per_set" name="copies_per_set" min="1" class="form-control" value="<?= $job['copies_per_set'] ?>" required>
                                </div>
                            </div>
                        </div>
                      </div>
                    </div>

                    <!-- ── Specifications ── -->
                    <div class="tab-content" id="specifications">
                      <div class="tab-grid">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-file-alt"></i> Paper Details</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Cut Size *</label>
                                    <select id="product_size" name="product_size" class="form-control" required>
                                        <?php foreach (
                                            [
                                                'whole',
                                                '1/2',
                                                '1/3',
                                                '1/4',
                                                '1/6',
                                                '1/8',
                                                '1/10',
                                                '1/12',
                                                '1/14',
                                                '1/16',
                                                '1/18',
                                                '1/20',
                                                '1/22',
                                                '1/24',
                                                '1/25',
                                                '1/26',
                                                '1/28',
                                                '1/30',
                                                '1/32',
                                                '1/36',
                                                '1/40',
                                                '1/48',
                                                '1/50'
                                            ] as $size
                                        ): ?>
                                            <option value="<?= $size ?>" <?= $job['product_size'] === $size ? 'selected' : '' ?>><?= $size ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Paper / Media Type *</label>
                                    <select id="paper_type" name="paper_type" class="form-control" required>
                                        <option value="">Select</option>
                                        <?php
                                        $types = $inventory->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
                                        while ($row = $types->fetch_assoc()):
                                        ?>
                                            <option value="<?= htmlspecialchars($row['product_type']) ?>" <?= $job['paper_type'] === $row['product_type'] ? 'selected' : '' ?>><?= htmlspecialchars($row['product_type']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Paper Size *</label>
                                    <select id="paper_size" name="paper_size" class="form-control" required>
                                        <option value="">Select</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Custom Paper Size</label>
                                    <input type="text" name="custom_paper_size" class="form-control" value="<?= htmlspecialchars($job['custom_paper_size']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-book"></i> Binding &amp; Finishing</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Binding Type *</label>
                                    <select id="binding_type" name="binding_type" class="form-control" required>
                                        <option value="Booklet" <?= $job['binding_type'] === 'Booklet' ? 'selected' : '' ?>>Booklet</option>
                                        <option value="Pad" <?= $job['binding_type'] === 'Pad'     ? 'selected' : '' ?>>Pad</option>
                                        <option value="Custom" <?= $job['binding_type'] === 'Custom'  ? 'selected' : '' ?>>Custom</option>
                                    </select>
                                    <input type="text" id="custom_binding" name="custom_binding" class="form-control" style="margin-top:.5rem;<?= $job['binding_type'] === 'Custom' ? '' : 'display:none' ?>" value="<?= htmlspecialchars($job['custom_binding']) ?>" placeholder="Enter custom binding">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-comment-dots"></i> Special Instructions</h3>
                            <div class="form-group">
                                <textarea name="special_instructions" class="form-control"><?= htmlspecialchars($job['special_instructions']) ?></textarea>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-palette"></i> Paper Sequence</h3>
                            <div id="paper-sequence-container"></div>
                        </div>
                      </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="job_orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Cancel</a>
                    <button type="submit" id="mainsubBtn" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Insufficient Stock Confirmation Modal ── -->
    <div id="insufficientStockModal" style="
    display:none; position:fixed; inset:0; z-index:9999;
    background:rgba(20,23,31,0.45); backdrop-filter:blur(2px); align-items:center; justify-content:center;">
        <div style="
      background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.2);
      max-width:460px; width:90%; padding:32px 28px; position:relative;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                <div style="
          background:#fdf2df; border-radius:50%; width:44px; height:44px;
          display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                    <i class="fas fa-exclamation-triangle" style="color:#b6790a; font-size:20px;"></i>
                </div>
                <h5 style="margin:0; font-weight:700; font-size:17px; color:#1a1a1a;">Insufficient Stock</h5>
            </div>
            <p style="color:#555; margin-bottom:12px; font-size:14px;">
                The following paper color(s) have <strong>no available stock</strong>:
            </p>
            <ul id="insufficientStockList" style="
        color:#d9463c; font-size:14px; font-weight:600;
        margin:0 0 18px 0; padding-left:20px;"></ul>
            <p style="color:#555; font-size:14px; margin-bottom:24px;">
                Stock will go negative if you continue. Do you still want to save this job order?
            </p>
            <div style="display:flex; gap:12px; justify-content:flex-end;">
                <button id="cancelStockModal" type="button" style="
          padding:9px 20px; border-radius:7px; border:1px solid #ccc;
          background:#fff; color:#555; font-size:14px; cursor:pointer; font-weight:500;">
                    Cancel
                </button>
                <button id="confirmStockModal" type="button" style="
          padding:9px 20px; border-radius:7px; border:none;
          background:#d9463c; color:#fff; font-size:14px; cursor:pointer; font-weight:600;">
                    <i class="fas fa-check"></i> Yes, Save Anyway
                </button>
            </div>
        </div>
    </div>
    <script>
        window.JO_DATA = {
            allProducts: <?= json_encode($all_products) ?>,
            preType: <?= json_encode($job['paper_type']) ?>,
            preSize: <?= json_encode($job['paper_size']) ?>,
            preCopies: <?= (int)$job['copies_per_set'] ?>,
            preSeq: <?= json_encode(array_map('trim', explode(',', $job['paper_sequence']))) ?>,
            preSpoilage: <?= json_encode($spoilage_map) ?>,
            savedProvince: <?= json_encode($job['province'] ?? '') ?>,
            savedCity: <?= json_encode($job['city'] ?? '') ?>
        };
    </script>                                        

    <script src="../assets/js/pages/edit_job.js"></script>                                        
</body>

</html>