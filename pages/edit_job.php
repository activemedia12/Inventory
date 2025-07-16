<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$job_id = intval($_GET['id'] ?? 0);
if ($job_id <= 0) {
    echo "Invalid job order ID.";
    exit;
}

// Fetch existing job order
$stmt = $mysqli->prepare("SELECT * FROM job_orders WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    echo "Job order not found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gather form inputs
    $client_name = $_POST['client_name'] ?? '';
    $client_address = $_POST['client_address'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $project_name = $_POST['project_name'] ?? '';
    $serial_range = $_POST['serial_range'] ?? '';
    $quantity = intval($_POST['quantity']);
    $number_of_sets = intval($_POST['number_of_sets']);
    $product_size = $_POST['product_size'] ?? '';
    $paper_size = $_POST['paper_size'] ?? '';
    $custom_paper_size = $_POST['custom_paper_size'] ?? '';
    $paper_type = $_POST['paper_type'] ?? '';
    $copies_per_set = intval($_POST['copies_per_set']);
    $binding_type = $_POST['binding_type'] ?? '';
    $custom_binding = $_POST['custom_binding'] ?? '';
    $special_instructions = $_POST['special_instructions'] ?? '';
    $log_date = $_POST['log_date'] ?? date('Y-m-d');
    $tin = trim($_POST['tin'] ?? '');
    $client_by = trim($_POST['client_by'] ?? '');
    $tax_type = trim($_POST['tax_type'] ?? '');
    $ocn_number = trim($_POST['ocn_number'] ?? '');
    $date_issued = $_POST['date_issued'] ?: null;
    $tax_payer = trim($_POST['taxpayer_name'] ?? '');
    $rdo_code = trim($_POST['rdo_code'] ?? '');
    $spoilage = $_POST['spoilage'];

    $new_sequence = $_POST['paper_sequence'] ?? [];
    $paper_sequence_str = implode(',', $new_sequence);

    // Recalculate used sheets
    $cut_size_map = ['1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, 'whole' => 1];
    $cut_size = $cut_size_map[$product_size] ?? 1;
    $total_sets = $quantity * $number_of_sets;
    $used_sheets_per_product = $total_sets / $cut_size;

    // Update job order details
    $stmt = $mysqli->prepare("UPDATE job_orders SET
        log_date = ?, client_name = ?, client_address = ?, contact_person = ?, contact_number = ?,
        project_name = ?, quantity = ?, number_of_sets = ?, product_size = ?, serial_range = ?,
        paper_size = ?, custom_paper_size = ?, paper_type = ?, copies_per_set = ?, binding_type = ?,
        custom_binding = ?, special_instructions = ?, paper_sequence = ?,
        tin = ?, client_by = ?, tax_type = ?, ocn_number = ?, date_issued = ?, taxpayer_name = ?, rdo_code = ?
        WHERE id = ?");

    $stmt->bind_param(
        "ssssssiisssssisssssssssssi",
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
        $tax_payer,
        $rdo_code,
        $job_id
    );

    if ($stmt->execute()) {
        // Step 1: Validate all stocks BEFORE deleting usage logs
        foreach ($new_sequence as $i => $color) {
            $color = trim($color);
            $spoil = isset($spoilage[$i]) ? intval($spoilage[$i]) : 0;

            // Fetch product ID
            $product_res = $mysqli->prepare("SELECT id FROM products WHERE product_type = ? AND product_group = ? AND product_name = ? LIMIT 1");
            $product_res->bind_param("sss", $paper_type, $paper_size, $color);
            $product_res->execute();
            $product_result = $product_res->get_result();

            if ($product_result && $product_result->num_rows > 0) {
                $prod = $product_result->fetch_assoc();
                $product_id = $prod['id'];

                // Get delivered sheets
                $delivered_res = $mysqli->query("SELECT IFNULL(SUM(delivered_reams), 0) AS total FROM delivery_logs WHERE product_id = $product_id");
                $delivered_sheets = $delivered_res->fetch_assoc()['total'] * 500;

                // Get used sheets EXCLUDING current job
                $used_res = $mysqli->query("SELECT IFNULL(SUM(used_sheets + spoilage_sheets), 0) AS total FROM usage_logs WHERE product_id = $product_id AND job_order_id != $job_id");
                $used_sheets = $used_res->fetch_assoc()['total'];

                $available_stock = $delivered_sheets - $used_sheets;

                if ($available_stock < ($used_sheets_per_product + $spoil)) {
                    $_SESSION['message'] = "<div class='alert alert-danger'>❌ Not enough stock for <strong>$color</strong>. Available: $available_stock sheets, Required: " . ($used_sheets_per_product + $spoil) . " sheets.</div>";
                    header("Location: edit_job.php?id=$job_id");
                    exit;
                }
            }

            $product_res->close();
        }

        // Step 2: Safe to delete old usage logs
        $old_logs = $mysqli->query("SELECT product_id, used_sheets, spoilage_sheets FROM usage_logs WHERE job_order_id = $job_id");

        while ($log = $old_logs->fetch_assoc()) {
            $product_id = $log['product_id'];
            $used = $log['used_sheets'];
            $spoil = $log['spoilage_sheets'];
        }

        $mysqli->query("DELETE FROM usage_logs WHERE job_order_id = $job_id");

        // Step 3: Insert updated usage logs with spoilage
        foreach ($new_sequence as $i => $color) {
            $color = trim($color);
            $spoil = isset($spoilage[$i]) ? intval($spoilage[$i]) : 0;

            $product_res = $mysqli->prepare("SELECT id FROM products WHERE product_type = ? AND product_group = ? AND product_name = ? LIMIT 1");
            $product_res->bind_param("sss", $paper_type, $paper_size, $color);
            $product_res->execute();
            $product_result = $product_res->get_result();

            if ($product_result && $product_result->num_rows > 0) {
                $prod = $product_result->fetch_assoc();
                $product_id = $prod['id'];
                $note = "Updated job order for $client_name";

                $log_stmt = $mysqli->prepare("
                    INSERT INTO usage_logs (product_id, used_sheets, spoilage_sheets, log_date, job_order_id, usage_note)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $log_stmt->bind_param("iiisis", $product_id, $used_sheets_per_product, $spoil, $log_date, $job_id, $note);
                $log_stmt->execute();
                $log_stmt->close();
            }

            $product_res->close();
        }

        $_SESSION['message'] = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Job order updated successfully</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error updating job order: " . $stmt->error . "</div>";
    }

    $stmt->close();
    // header("Location: job_orders.php");
    // exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<?php
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
?>

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
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgb(140, 140, 140);
            border-radius: 10px;
        }

        :root {
            --primary: #1877f2;
            --primary-light: #eef2ff;
            --secondary: #166fe5;
            --success: #42b72a;
            --danger: #ff4d4f;
            --warning: #f8961e;
            --dark: #1a1b25;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --lighter-gray: #f8f9fa;
            --white: #ffffff;
            --border-radius: 0.5rem;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins';
        }

        body {
            background-color: var(--lighter-gray);
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
        }

        .job-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .job-status.pending {
            background-color: rgba(255, 123, 0, 0.1);
            color: var(--warning);
        }

        .job-status.completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
        }

        .user-info small {
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: rgba(66, 183, 42, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background-color: rgba(255, 77, 79, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Form Layout */
        .edit-form {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .form-tabs {
            display: flex;
            border-bottom: 1px solid var(--light-gray);
            background: var(--lighter-gray);
        }

        .form-tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            border-bottom: 3px solid transparent;
            transition: var(--transition);
        }

        .form-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            background: var(--white);
        }

        .form-tab:hover:not(.active) {
            color: var(--dark);
            background: rgba(0, 0, 0, 0.03);
        }

        .form-content {
            padding: 2rem;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .section-title i {
            color: var(--primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-size: 0.9375rem;
            transition: var(--transition);
            background-color: var(--white);
            font-family: 'Poppins';
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .radio-group {
            display: block;
        }

        .radio-option {
            display: flex;
            gap: 10px;
        }

        .radio-option input {
            margin: 0;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--light-gray);
            background: var(--lighter-gray);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--secondary);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--gray);
            text-decoration: none;
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
            color: var(--dark);
        }

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-danger:hover {
            background-color: #ff4d4f;
        }

        /* Info Note */
        .info-note {
            padding: 1rem;
            background-color: var(--primary-light);
            border-radius: var(--border-radius);
            margin: 1.5rem 0;
            font-size: 0.875rem;
            color: var(--dark);
            border-left: 3px solid var(--primary);
            display: flex;
            gap: 0.75rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .form-tabs {
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 2px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column-reverse;
                gap: 1rem;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <header class="page-header">
            <div class="page-title">
                <h1>Edit Job Order <?= $job_id ?></h1>
                <span class="job-status <?= $job['status'] ?>"><?= $job['status'] ?></span>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                </div>
                <div class="user-info">
                    <div><?= htmlspecialchars($_SESSION['username']) ?></div>
                    <small><?= $_SESSION['role'] ?></small>
                </div>
            </div>
        </header>

        <?php if (isset($message)): ?>
            <div><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" class="edit-form">
            <div class="form-tabs">
                <div class="form-tab active" data-tab="client-info">
                    <i class="fas fa-building"></i> Client Info
                </div>
                <div class="form-tab" data-tab="order-details">
                    <i class="fas fa-clipboard-list"></i> Order Details
                </div>
                <div class="form-tab" data-tab="specifications">
                    <i class="fas fa-tools"></i> Specifications
                </div>
            </div>

            <div class="form-content">
                <div class="tab-content active" id="client-info">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i> Basic Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="client_name">Company Name *</label>
                                <input type="text" id="client_name" name="client_name" class="form-control"
                                    value="<?= htmlspecialchars($job['client_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="taxpayer_name">Tax Payer</label>
                                <input type="text" id="taxpayer_name" name="taxpayer_name" class="form-control"
                                    value="<?= htmlspecialchars($job['taxpayer_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="tin">TIN *</label>
                                <input type="text" id="tin" name="tin" class="form-control"
                                    value="<?= htmlspecialchars($job['tin'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Tax Type</label>
                                <div class="radio-group">
                                    <label>
                                        <input type="radio" name="tax_type" value="VAT" <?= ($job['tax_type'] ?? '') === 'VAT' ? 'checked' : '' ?> required> VAT
                                    </label>
                                    <label>
                                        <input type="radio" name="tax_type" value="NONVAT" <?= ($job['tax_type'] ?? '') === 'NONVAT' ? 'checked' : '' ?>> NONVAT
                                    </label>
                                    <label>
                                        <input type="radio" name="tax_type" value="VAT-EXEMPT" <?= ($job['tax_type'] ?? '') === 'VAT-EXEMPT' ? 'checked' : '' ?>> VAT-EXEMPT
                                    </label>
                                    <label>
                                        <input type="radio" name="tax_type" value="NON-VAT EXEMPT" <?= ($job['tax_type'] ?? '') === 'NON-VAT EXEMPT' ? 'checked' : '' ?>> NON-VAT EXEMPT
                                    </label>
                                    <label>
                                        <input type="radio" name="tax_type" value="EXEMPT" <?= ($job['tax_type'] ?? '') === 'EXEMPT' ? 'checked' : '' ?>> EXEMPT
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-address-card"></i> Contact Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="client_address">Address *</label>
                                <input type="text" id="client_address" name="client_address" class="form-control"
                                    value="<?= htmlspecialchars($job['client_address']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_person">Contact Person *</label>
                                <input type="text" id="contact_person" name="contact_person" class="form-control"
                                    value="<?= htmlspecialchars($job['contact_person']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_number">Contact Number *</label>
                                <input type="text" id="contact_number" name="contact_number" class="form-control"
                                    value="<?= htmlspecialchars($job['contact_number']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i> Additional Details
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="client_by">Client By *</label>
                                <input type="text" id="client_by" name="client_by" class="form-control"
                                    value="<?= htmlspecialchars($job['client_by'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="rdo_code">RDO Code</label>
                                <input type="text" id="rdo_code" name="rdo_code" class="form-control"
                                    value="<?= htmlspecialchars($job['rdo_code'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="ocn_number">OCN Number</label>
                                <input type="text" id="ocn_number" name="ocn_number" class="form-control"
                                    value="<?= htmlspecialchars($job['ocn_number'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_issued">Date Issued</label>
                                <input type="date" id="date_issued" name="date_issued" class="form-control"
                                    value="<?= htmlspecialchars($job['date_issued'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Details Tab -->
                <div class="tab-content" id="order-details">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-project-diagram"></i> Project Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="project_name">Project Name *</label>
                                <input type="text" id="project_name" name="project_name" class="form-control"
                                    value="<?= htmlspecialchars($job['project_name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="serial_range">Serial Range *</label>
                                <input type="text" id="serial_range" name="serial_range" class="form-control"
                                    value="<?= htmlspecialchars($job['serial_range']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="log_date">Order Date *</label>
                                <input type="date" id="log_date" name="log_date" class="form-control"
                                    value="<?= htmlspecialchars($job['log_date']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-cubes"></i> Quantity & Sets
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="quantity">Order Quantity *</label>
                                <input type="number" id="quantity" name="quantity" min="1" class="form-control"
                                    value="<?= $job['quantity'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="number_of_sets">Sets per Product *</label>
                                <input type="number" id="number_of_sets" name="number_of_sets" min="1" class="form-control"
                                    value="<?= $job['number_of_sets'] ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="copies_per_set">Copies per Set *</label>
                                <input type="number" id="copies_per_set" name="copies_per_set" min="1" class="form-control"
                                    value="<?= $job['copies_per_set'] ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Specifications Tab -->
                <div class="tab-content" id="specifications">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-file-alt"></i> Paper Details
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="product_size">Product Size *</label>
                                <select id="product_size" name="product_size" class="form-control" required>
                                    <?php foreach (['whole', '1/2', '1/3', '1/4', '1/6', '1/8'] as $size): ?>
                                        <option value="<?= $size ?>" <?= $job['product_size'] == $size ? 'selected' : '' ?>>
                                            <?= $size ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="paper_type">Paper / Media Type</label>
                                <select id="paper_type" name="paper_type" class="form-control" required>
                                    <option value="">Select</option>
                                    <?php
                                    $types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
                                    while ($row = $types->fetch_assoc()):
                                    ?>
                                        <option value="<?= htmlspecialchars($row['product_type']) ?>" <?= $job['paper_type'] === $row['product_type'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($row['product_type']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="paper_size">Paper Size</label>
                                <select id="paper_size" name="paper_size" class="form-control" required>
                                    <option value="">Select</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="custom_paper_size">Custom Paper Size</label>
                                <input type="text" id="custom_paper_size" name="custom_paper_size" class="form-control"
                                    value="<?= htmlspecialchars($job['custom_paper_size']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-book"></i> Binding & Finishing
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="binding_type">Binding Type *</label>
                                <input type="text" id="binding_type" name="binding_type" class="form-control"
                                    value="<?= htmlspecialchars($job['binding_type']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="custom_binding">Custom Binding</label>
                                <input type="text" id="custom_binding" name="custom_binding" class="form-control"
                                    value="<?= htmlspecialchars($job['custom_binding']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-comment-dots"></i> Special Instructions
                        </h3>
                        <div class="form-group">
                            <textarea id="special_instructions" name="special_instructions" class="form-control"><?= htmlspecialchars($job['special_instructions']) ?></textarea>
                        </div>
                    </div>

                    <div id="paper-sequence-container">
                    </div>

                </div>
            </div>

            <div class="form-actions">
                <div>
                    <a href="job_orders.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" id="mainsubBtn" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php
    $spoilage_map = [];

    $spoilage_query = $mysqli->prepare("
        SELECT p.product_name, u.spoilage_sheets
        FROM usage_logs u
        JOIN products p ON u.product_id = p.id
        WHERE u.job_order_id = ?
    ");
    $spoilage_query->bind_param("i", $job_id);
    $spoilage_query->execute();
    $spoilage_result = $spoilage_query->get_result();

    while ($row = $spoilage_result->fetch_assoc()) {
        $spoilage_map[$row['product_name']] = intval($row['spoilage_sheets']);
    }
    $spoilage_query->close();

    $product_query = $mysqli->query("
            SELECT 
                p.*,
                COALESCE(SUM(d.delivered_reams * 500), 0) -
                COALESCE(SUM(u.used_reams * 500), 0) AS available_sheets
            FROM products p
            LEFT JOIN delivery_logs d ON p.id = d.product_id
            LEFT JOIN usage_logs u ON p.id = u.product_id
            GROUP BY p.id
        ");
    ?>
    <script>
        // Tab functionality
        document.querySelectorAll('.form-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Dynamic field validation
        document.querySelectorAll('[required]').forEach(field => {
            field.addEventListener('invalid', () => {
                field.style.borderColor = '#ff4d4f';
                field.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            });

            field.addEventListener('input', () => {
                if (field.checkValidity()) {
                    field.style.borderColor = '';
                }
            });
        });

        document.addEventListener("DOMContentLoaded", function() {
            const allProducts = <?= json_encode($product_query->fetch_all(MYSQLI_ASSOC)); ?>;
            const paperTypeSelect = document.getElementById('paper_type');
            const paperSizeSelect = document.getElementById('paper_size');
            const copiesInput = document.getElementById('copies_per_set');
            const sequenceContainer = document.getElementById('paper-sequence-container');

            // Pre-filled values from PHP
            const preSelectedType = <?= json_encode($job['paper_type']) ?>;
            const preSelectedSize = <?= json_encode($job['paper_size']) ?>;
            const preSelectedCopies = <?= (int)$job['copies_per_set'] ?>;
            const preSelectedSequence = <?= json_encode(explode(',', $job['paper_sequence'])) ?>;
            const existingSpoilage = <?= json_encode($spoilage_map) ?>;

            function updatePaperSizeOptions() {
                const selectedType = paperTypeSelect.value;

                // Clear the dropdown
                paperSizeSelect.innerHTML = '<option value="">Select</option>';

                // Get unique product groups (sizes) that match the selected type
                const matchingSizes = new Set();
                allProducts.forEach(p => {
                    if (p.product_type === selectedType) {
                        matchingSizes.add(p.product_group);
                    }
                });

                // Append each matching size
                Array.from(matchingSizes).sort().forEach(size => {
                    const opt = document.createElement('option');
                    opt.value = size;
                    opt.textContent = size;
                    paperSizeSelect.appendChild(opt);
                });

                // Add custom option
                const customOpt = document.createElement('option');
                customOpt.value = 'custom';
                customOpt.textContent = 'Custom Size';
                paperSizeSelect.appendChild(customOpt);

                paperSizeSelect.value = preSelectedSize;

                if (!Array.from(paperSizeSelect.options).some(opt => opt.value === preSelectedSize)) {
                    paperSizeSelect.value = '';
                }
            }

            function updatePaperSequenceOptions() {
                const type = paperTypeSelect.value;
                const size = paperSizeSelect.value;
                const copies = parseInt(copiesInput.value) || 0;

                sequenceContainer.innerHTML = '';

                if (!type || !size || copies <= 0) {
                    sequenceContainer.innerHTML = '<div style="color: gray;">Please select paper type, size, and copies per set.</div>';
                    return;
                }

                // console.log("Selected Type:", type);
                // console.log("Selected Size:", size);
                // console.log("All Products:", allProducts);

                const matchingProducts = allProducts.filter(p =>
                    p.product_type === type &&
                    p.product_group === size &&
                    Number(p.available_sheets) > 0
                );

                if (matchingProducts.length === 0) {
                    const msg = document.createElement('div');
                    const submitBtn = document.getElementById('mainsubBtn');

                    msg.textContent = '⚠ No available stock for the selected type and size.';
                    msg.style.color = 'var(--danger)';
                    sequenceContainer.appendChild(msg);

                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('disabled');
                        submitBtn.title = 'Cannot submit — no stock available';
                    }

                    return;
                }

                const submitBtn = document.getElementById('mainsubBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('disabled');
                    submitBtn.title = '';
                }

                for (let i = 0; i < copies; i++) {
                    const group = document.createElement('div');
                    group.style.marginBottom = '15px';

                    const label = document.createElement('label');
                    label.textContent = `Copy ${i + 1}:`;
                    label.style.display = 'block';
                    label.style.marginBottom = '8px';
                    label.style.fontSize = '14px';
                    label.style.color = 'var(--gray)';

                    const select = document.createElement('select');
                    select.name = 'paper_sequence[]';
                    select.required = true;
                    select.style.width = '100%';
                    select.style.padding = '10px 12px';
                    select.style.border = '1px solid var(--light-gray)';
                    select.style.borderRadius = '6px';
                    select.style.fontSize = '14px';

                    const spoilageInput = document.createElement('input');
                    spoilageInput.type = 'number';
                    spoilageInput.name = 'spoilage[]';
                    spoilageInput.placeholder = 'Spoilage sheets';
                    spoilageInput.min = 0;
                    spoilageInput.style.marginTop = '8px';
                    spoilageInput.className = 'form-control';

                    const defaultOpt = document.createElement('option');
                    defaultOpt.textContent = 'Select Color';
                    defaultOpt.value = '';
                    select.appendChild(defaultOpt);

                    matchingProducts.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.product_name;
                        const reams = (p.available_sheets / 500).toFixed(2);
                        opt.textContent = `${p.product_name} (${reams} reams available)`;
                        if (preSelectedSequence[i] === p.product_name) {
                            opt.selected = true;
                            if (existingSpoilage[p.product_name]) {
                                spoilageInput.value = existingSpoilage[p.product_name];
                            }
                        }
                        select.appendChild(opt);
                    });

                    group.appendChild(label);
                    group.appendChild(select);
                    group.appendChild(spoilageInput);
                    sequenceContainer.appendChild(group);
                }
            }

            // Setup listeners
            paperTypeSelect.addEventListener('change', () => {
                updatePaperSizeOptions();
                updatePaperSequenceOptions();
            });
            paperSizeSelect.addEventListener('change', updatePaperSequenceOptions);
            copiesInput.addEventListener('input', updatePaperSequenceOptions);

            // Initialize with pre-filled values
            paperTypeSelect.value = preSelectedType;
            copiesInput.value = preSelectedCopies;
            updatePaperSizeOptions();
            updatePaperSequenceOptions();
        });
    </script>

    <?php if ($message && strpos($message, 'successfully') !== false): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.createElement('div');
                modal.className = 'success-modal';
                modal.innerHTML = `
      <div class="success-modal-content">
        <p>Redirecting to Job Orders...</p>
      </div>
    `;
                document.body.appendChild(modal);

                setTimeout(() => {
                    window.location.href = 'job_orders.php';
                }, 3000);
            });
        </script>

        <style>
            .success-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            }

            .success-modal-content {
                background: #fff;
                padding: 2rem;
                border-radius: 8px;
                text-align: center;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 90%;
                animation: popUp 0.3s ease-out;
            }

            .success-modal-content h2 {
                color: #28a745;
                margin-bottom: 0.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .success-modal-content p {
                font-size: 16px;
                color: #333;
            }

            @keyframes popUp {
                from {
                    transform: scale(0.9);
                    opacity: 0;
                }

                to {
                    transform: scale(1);
                    opacity: 1;
                }
            }
        </style>
    <?php endif; ?>

</body>

</html>