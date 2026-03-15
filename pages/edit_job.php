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
        $floor_no, $building_no, $street,
        $barangay ? 'Brgy. ' . $barangay : '',
        $city, $province, $zip_code
    ]));

    $new_sequence        = $_POST['paper_sequence'] ?? [];
    $paper_sequence_str  = implode(', ', array_map('trim', $new_sequence));

    // Cut size map — matches job_orders.php (up to 1/50)
    $cut_size_map = [
        '1/2' => 2,  '1/3' => 3,  '1/4' => 4,  '1/6' => 6,
        '1/8' => 8,  '1/10' => 10,'1/12' => 12,'1/14' => 14,
        '1/16' => 16,'1/18' => 18,'1/20' => 20,'1/22' => 22,
        '1/24' => 24,'1/25' => 25,'1/26' => 26,'1/28' => 28,
        '1/30' => 30,'1/32' => 32,'1/36' => 36,'1/40' => 40,
        '1/48' => 48,'1/50' => 50,'whole' => 1,
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
        $log_date, $client_name, $client_address, $contact_person, $contact_number,
        $project_name, $quantity, $number_of_sets, $product_size, $serial_range,
        $paper_size, $custom_paper_size, $paper_type, $copies_per_set, $binding_type,
        $custom_binding, $special_instructions, $paper_sequence_str,
        $tin, $client_by, $tax_type, $ocn_number, $date_issued,
        $taxpayer_name, $rdo_code,
        $province, $city, $barangay, $street, $building_no, $floor_no, $zip_code,
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

        $available = $delivered_sheets - $used_sheets;
        $required  = $used_sheets_per_product + $spoil;

        if ($available < $required) {
            $_SESSION['message'] = "<div class='alert alert-danger'>❌ Not enough stock for <strong>" . htmlspecialchars($color) . "</strong>. Available: {$available} sheets, Required: {$required} sheets.</div>";
            header("Location: edit_job.php?id=$job_id");
            exit;
        }
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
        $taxpayer_name, $tin, $tax_type, $rdo_code, $client_address,
        $province, $city, $barangay, $street, $building_no, $floor_no, $zip_code,
        $contact_person, $client_by,
        $client_name, $contact_number
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-thumb { background: rgb(140,140,140); border-radius: 10px; }
        :root {
            --primary: #1877f2; --primary-light: #eef2ff; --secondary: #166fe5;
            --success: #42b72a; --danger: #ff4d4f; --warning: #f8961e;
            --dark: #1a1b25; --gray: #6c757d; --light-gray: #e9ecef;
            --lighter-gray: #f8f9fa; --white: #ffffff;
            --border-radius: 0.5rem;
            --shadow: 0 1px 3px rgba(0,0,0,.1); --transition: all 0.2s ease;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins'; }
        body { background:var(--lighter-gray); color:var(--dark); line-height:1.6; }
        .container { max-width:1200px; margin:0 auto; padding:2rem; }
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; padding-bottom:1rem; border-bottom:1px solid var(--light-gray); }
        .page-title { display:flex; align-items:center; gap:1rem; }
        .page-title h1 { font-size:1.75rem; font-weight:700; }
        .job-status { padding:.25rem .75rem; border-radius:50px; font-size:.875rem; font-weight:600; text-transform:capitalize; }
        .job-status.pending { background:rgba(255,123,0,.1); color:var(--warning); }
        .job-status.completed { background:rgba(40,167,69,.1); color:#28a745; }
        .user-avatar { width:40px; height:40px; border-radius:50%; background:var(--primary-light); display:flex; align-items:center; justify-content:center; color:var(--primary); font-weight:600; }
        .user-info small { color:var(--gray); font-size:.875rem; }
        .alert { padding:1rem; border-radius:var(--border-radius); margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:.75rem; }
        .alert-success { background:rgba(66,183,42,.1); color:#28a745; border-left:4px solid #28a745; }
        .alert-danger  { background:rgba(255,77,79,.1);  color:var(--danger);  border-left:4px solid var(--danger); }
        .edit-form { background:var(--white); border-radius:var(--border-radius); box-shadow:var(--shadow); overflow:hidden; }
        .form-tabs { display:flex; border-bottom:1px solid var(--light-gray); background:var(--lighter-gray); }
        .form-tab { padding:1rem 1.5rem; cursor:pointer; font-weight:500; color:var(--gray); border-bottom:3px solid transparent; transition:var(--transition); }
        .form-tab.active { color:var(--primary); border-bottom-color:var(--primary); background:var(--white); }
        .form-tab:hover:not(.active) { color:var(--dark); background:rgba(0,0,0,.03); }
        .form-content { padding:2rem; }
        .tab-content { display:none; }
        .tab-content.active { display:block; }
        .form-section { margin-bottom:2rem; }
        .section-title { display:flex; align-items:center; gap:.75rem; font-size:1.125rem; font-weight:600; margin-bottom:1.25rem; padding-bottom:.75rem; border-bottom:1px solid var(--light-gray); }
        .section-title i { color:var(--primary); }
        .form-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.5rem; }
        .form-group { margin-bottom:1.25rem; }
        .form-group label { display:block; margin-bottom:.5rem; font-size:.875rem; font-weight:500; color:var(--gray); }
        .form-control { width:100%; padding:.75rem 1rem; border:1px solid var(--light-gray); border-radius:var(--border-radius); font-size:.9375rem; transition:var(--transition); background:var(--white); font-family:'Poppins'; }
        .form-control:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(67,97,238,.15); }
        textarea.form-control { min-height:120px; resize:vertical; }
        .radio-group { display:flex; flex-wrap:wrap; gap:10px; }
        .radio-option { display:flex; gap:6px; align-items:center; }
        .form-actions { display:flex; justify-content:space-between; align-items:center; padding:1.5rem 2rem; border-top:1px solid var(--light-gray); background:var(--lighter-gray); }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:.75rem 1.5rem; border-radius:var(--border-radius); font-size:.9375rem; font-weight:500; cursor:pointer; transition:var(--transition); border:none; gap:.5rem; }
        .btn-primary { background:var(--primary); color:var(--white); }
        .btn-primary:hover { background:var(--secondary); }
        .btn-outline { background:transparent; border:1px solid var(--light-gray); color:var(--gray); text-decoration:none; }
        .btn-outline:hover { background:var(--light-gray); color:var(--dark); }
        @media(max-width:768px){
            .container{padding:1rem;} .page-header{flex-direction:column;align-items:flex-start;gap:1rem;}
            .form-tabs{overflow-x:auto;white-space:nowrap;} .form-grid{grid-template-columns:1fr;}
            .form-actions{flex-direction:column-reverse;gap:1rem;} .btn{width:100%;}
        }
    </style>
</head>
<body>
<div class="container">
    <header class="page-header">
        <div class="page-title">
            <h1>Edit Job Order #<?= $job_id ?></h1>
            <span class="job-status <?= htmlspecialchars($job['status']) ?>"><?= htmlspecialchars($job['status']) ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 2)) ?></div>
            <div class="user-info">
                <div><?= htmlspecialchars($_SESSION['username']) ?></div>
                <small><?= $_SESSION['role'] ?></small>
            </div>
        </div>
    </header>

    <?php if ($message): ?><?= $message ?><?php endif; ?>

    <form method="post" class="edit-form">
        <div class="form-tabs">
            <div class="form-tab active" data-tab="client-info"><i class="fas fa-building"></i> Client Info</div>
            <div class="form-tab" data-tab="order-details"><i class="fas fa-clipboard-list"></i> Order Details</div>
            <div class="form-tab" data-tab="specifications"><i class="fas fa-tools"></i> Specifications</div>
        </div>

        <div class="form-content">

            <!-- ── Client Info ── -->
            <div class="tab-content active" id="client-info">
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
                                <?php foreach (['VAT','NONVAT','VAT-EXEMPT','NON-VAT EXEMPT','EXEMPT'] as $tt): ?>
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
            </div>

            <!-- ── Order Details ── -->
            <div class="tab-content" id="order-details">
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

            <!-- ── Specifications ── -->
            <div class="tab-content" id="specifications">
                <div class="form-section">
                    <h3 class="section-title"><i class="fas fa-file-alt"></i> Paper Details</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Cut Size *</label>
                            <select id="product_size" name="product_size" class="form-control" required>
                                <?php foreach ([
                                    'whole','1/2','1/3','1/4','1/6','1/8','1/10','1/12','1/14','1/16','1/18','1/20',
                                    '1/22','1/24','1/25','1/26','1/28','1/30','1/32','1/36','1/40','1/48','1/50'
                                ] as $size): ?>
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
                                <option value="Pad"     <?= $job['binding_type'] === 'Pad'     ? 'selected' : '' ?>>Pad</option>
                                <option value="Custom"  <?= $job['binding_type'] === 'Custom'  ? 'selected' : '' ?>>Custom</option>
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

        <div class="form-actions">
            <a href="job_orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Cancel</a>
            <button type="submit" id="mainsubBtn" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </form>
</div>

<script>
// Tabs
document.querySelectorAll('.form-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.form-tab, .tab-content').forEach(el => el.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.add('active');
    });
});

// Validation scroll
document.querySelectorAll('[required]').forEach(field => {
    field.addEventListener('invalid', () => {
        field.style.borderColor = '#ff4d4f';
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    field.addEventListener('input', () => {
        if (field.checkValidity()) field.style.borderColor = '';
    });
});

// Binding custom toggle
document.getElementById('binding_type').addEventListener('change', function() {
    document.getElementById('custom_binding').style.display = this.value === 'Custom' ? 'block' : 'none';
});

// Province → City
document.getElementById('province').addEventListener('change', function() {
    const citySelect = document.getElementById('city');
    citySelect.innerHTML = '<option value="">Select City</option>';
    if (!this.value) return;
    fetch('get_cities.php?province=' + encodeURIComponent(this.value))
        .then(r => r.json())
        .then(cities => {
            cities.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c; opt.textContent = c;
                citySelect.appendChild(opt);
            });
        });
});

document.addEventListener('DOMContentLoaded', function() {
    const allProducts     = <?= json_encode($all_products) ?>;
    const paperTypeSelect = document.getElementById('paper_type');
    const paperSizeSelect = document.getElementById('paper_size');
    const copiesInput     = document.getElementById('copies_per_set');
    const seqContainer    = document.getElementById('paper-sequence-container');

    const preType     = <?= json_encode($job['paper_type']) ?>;
    const preSize     = <?= json_encode($job['paper_size']) ?>;
    const preCopies   = <?= (int)$job['copies_per_set'] ?>;
    const preSeq      = <?= json_encode(array_map('trim', explode(',', $job['paper_sequence']))) ?>;
    const preSpoilage = <?= json_encode($spoilage_map) ?>;

    function updateSizes() {
        const sel = paperTypeSelect.value;
        paperSizeSelect.innerHTML = '<option value="">Select</option>';
        const sizes = new Set();
        allProducts.forEach(p => { if (p.product_type === sel) sizes.add(p.product_group); });
        Array.from(sizes).sort().forEach(s => {
            const o = document.createElement('option');
            o.value = s; o.textContent = s;
            paperSizeSelect.appendChild(o);
        });
        const customOpt = document.createElement('option');
        customOpt.value = 'custom'; customOpt.textContent = 'Custom Size';
        paperSizeSelect.appendChild(customOpt);
        paperSizeSelect.value = preSize;
    }

    function updateSequence() {
        const type   = paperTypeSelect.value;
        const size   = paperSizeSelect.value;
        const copies = parseInt(copiesInput.value) || 0;
        seqContainer.innerHTML = '';

        if (!type || !size || copies <= 0) {
            seqContainer.innerHTML = '<div style="color:gray">Please select paper type, size, and copies per set.</div>';
            return;
        }

        const matching = allProducts.filter(p =>
            p.product_type === type && p.product_group === size && Number(p.available_sheets) > 0
        );

        const submitBtn = document.getElementById('mainsubBtn');
        if (matching.length === 0) {
            seqContainer.innerHTML = '<div style="color:var(--danger)">⚠ No available stock for the selected type and size.</div>';
            submitBtn.disabled = true;
            return;
        }
        submitBtn.disabled = false;

        for (let i = 0; i < copies; i++) {
            const group = document.createElement('div');
            group.style.marginBottom = '15px';

            const label = document.createElement('label');
            label.textContent = `Copy ${i + 1}:`;
            label.style.cssText = 'display:block;margin-bottom:8px;font-size:14px;color:var(--gray)';

            const select = document.createElement('select');
            select.name = 'paper_sequence[]';
            select.required = true;
            select.className = 'form-control';

            const spoilInput = document.createElement('input');
            spoilInput.type = 'number';
            spoilInput.name = 'spoilage[]';
            spoilInput.placeholder = 'Spoilage sheets';
            spoilInput.min = 0;
            spoilInput.value = 0;
            spoilInput.style.marginTop = '8px';
            spoilInput.className = 'form-control';

            const def = document.createElement('option');
            def.value = ''; def.textContent = 'Select Color';
            select.appendChild(def);

            matching.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.product_name;
                const reams = (p.available_sheets / 500).toFixed(2);
                opt.textContent = `${p.product_name} (${reams} reams available)`;
                if (preSeq[i] && preSeq[i].trim() === p.product_name) {
                    opt.selected = true;
                    if (preSpoilage[preSeq[i].trim()] !== undefined) {
                        spoilInput.value = preSpoilage[preSeq[i].trim()];
                    }
                }
                select.appendChild(opt);
            });

            group.appendChild(label);
            group.appendChild(select);
            group.appendChild(spoilInput);
            seqContainer.appendChild(group);
        }
    }

    paperTypeSelect.addEventListener('change', () => { updateSizes(); updateSequence(); });
    paperSizeSelect.addEventListener('change', updateSequence);
    copiesInput.addEventListener('input', updateSequence);

    // Restore province → city then initialize
    const savedProvince = <?= json_encode($job['province'] ?? '') ?>;
    const savedCity     = <?= json_encode($job['city'] ?? '') ?>;
    if (savedProvince) {
        fetch('get_cities.php?province=' + encodeURIComponent(savedProvince))
            .then(r => r.json())
            .then(cities => {
                const cs = document.getElementById('city');
                cs.innerHTML = '<option value="">Select City</option>';
                cities.forEach(c => {
                    const o = document.createElement('option');
                    o.value = c; o.textContent = c;
                    if (c === savedCity) o.selected = true;
                    cs.appendChild(o);
                });
            });
    }

    paperTypeSelect.value = preType;
    copiesInput.value     = preCopies;
    updateSizes();
    updateSequence();
});
</script>
</body>
</html>