<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// ── PRG: Handle all POST actions ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add / Edit Product Type ───────────────────────────────────
    if ($action === 'save_type') {
        $id          = intval($_POST['type_id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon        = trim($_POST['icon'] ?? 'fa-print');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $sort_order  = intval($_POST['sort_order'] ?? 0);

        // ── Paper stock consumption (for non-paper product types that still use paper) ──
        $requires_paper = isset($_POST['requires_paper']) ? 1 : 0;
        $paper_type     = $requires_paper ? trim($_POST['paper_type'] ?? '') : null;
        $paper_size     = $requires_paper ? trim($_POST['paper_size'] ?? '') : null;
        $cut_size       = $requires_paper ? trim($_POST['cut_size'] ?? 'whole') : null;
        if (!$requires_paper) {
            // Don't silently keep stale values if the toggle is turned off
            $paper_type = null;
            $paper_size = null;
            $cut_size   = null;
        }

        if ($name !== '') {
            // ── Duplicate name check (case-insensitive, excluding self on edit) ──
            $dupCheck = $inventory->prepare("SELECT id FROM product_types WHERE LOWER(name) = LOWER(?) AND id != ? LIMIT 1");
            $dupCheck->bind_param("si", $name, $id);
            $dupCheck->execute();
            $dupExists = $dupCheck->get_result()->fetch_assoc();

            if ($dupExists) {
                $_SESSION['pt_message'] = ['type' => 'error', 'text' => "A product type named \"$name\" already exists."];
            } else {
                if ($id > 0) {
                    $stmt = $inventory->prepare("UPDATE product_types SET name=?, description=?, icon=?, is_active=?, sort_order=?, requires_paper=?, paper_type=?, paper_size=?, cut_size=? WHERE id=?");
                    $stmt->bind_param("sssiiisssi", $name, $description, $icon, $is_active, $sort_order, $requires_paper, $paper_type, $paper_size, $cut_size, $id);
                } else {
                    $stmt = $inventory->prepare("INSERT INTO product_types (name, description, icon, is_active, sort_order, requires_paper, paper_type, paper_size, cut_size) VALUES (?,?,?,?,?,?,?,?,?)");
                    $stmt->bind_param("sssiiisss", $name, $description, $icon, $is_active, $sort_order, $requires_paper, $paper_type, $paper_size, $cut_size);
                }
                $stmt->execute();
                $_SESSION['pt_message'] = ['type' => 'success', 'text' => $id > 0 ? 'Product type updated.' : 'Product type added.'];
            }
        } else {
            $_SESSION['pt_message'] = ['type' => 'error', 'text' => 'Name is required.'];
        }
        header("Location: product_types.php");
        exit;
    }

    // ── Toggle active status ──────────────────────────────────────
    if ($action === 'toggle_type') {
        $id = intval($_POST['type_id'] ?? 0);
        $inventory->query("UPDATE product_types SET is_active = NOT is_active WHERE id = $id");
        header("Location: product_types.php");
        exit;
    }

    // ── Delete Product Type ───────────────────────────────────────
    if ($action === 'delete_type') {
        $id = intval($_POST['type_id'] ?? 0);
        $inventory->query("DELETE FROM product_types WHERE id = $id");
        $_SESSION['pt_message'] = ['type' => 'success', 'text' => 'Product type deleted.'];
        header("Location: product_types.php");
        exit;
    }

    // ── Add / Edit Field ──────────────────────────────────────────
    if ($action === 'save_field') {
        $field_id       = intval($_POST['field_id'] ?? 0);
        $type_id        = intval($_POST['type_id'] ?? 0);
        $field_name     = trim(strtolower(str_replace(' ', '_', $_POST['field_name'] ?? '')));
        $field_label    = trim($_POST['field_label'] ?? '');
        $field_type     = $_POST['field_type'] ?? 'text';
        $is_required    = isset($_POST['is_required']) ? 1 : 0;
        $sort_order     = intval($_POST['sort_order'] ?? 0);

        if ($type_id > 0 && $field_name !== '' && $field_label !== '') {
            // ── Duplicate field_name check, scoped to this product type ──
            $dupCheck = $inventory->prepare("SELECT id FROM product_type_fields WHERE product_type_id = ? AND field_name = ? AND id != ? LIMIT 1");
            $dupCheck->bind_param("isi", $type_id, $field_name, $field_id);
            $dupCheck->execute();
            $dupExists = $dupCheck->get_result()->fetch_assoc();

            if ($dupExists) {
                $_SESSION['pt_message'] = ['type' => 'error', 'text' => "A field with the internal name \"$field_name\" already exists on this product type. Use a different label."];
            } else {
                if ($field_id > 0) {
                    $stmt = $inventory->prepare("UPDATE product_type_fields SET field_name=?, field_label=?, field_type=?, is_required=?, sort_order=? WHERE id=?");
                    $stmt->bind_param("sssiii", $field_name, $field_label, $field_type, $is_required, $sort_order, $field_id);
                } else {
                    $stmt = $inventory->prepare("INSERT INTO product_type_fields (product_type_id, field_name, field_label, field_type, is_required, sort_order) VALUES (?,?,?,?,?,?)");
                    $stmt->bind_param("isssii", $type_id, $field_name, $field_label, $field_type, $is_required, $sort_order);
                }
                $stmt->execute();
                $_SESSION['pt_message'] = ['type' => 'success', 'text' => 'Field saved.'];
            }
        } else {
            $_SESSION['pt_message'] = ['type' => 'error', 'text' => 'Field name and label are required.'];
        }
        header("Location: product_types.php?manage=" . $type_id);
        exit;
    }

    // ── Delete Field ──────────────────────────────────────────────
    if ($action === 'delete_field') {
        $field_id = intval($_POST['field_id'] ?? 0);
        $type_id  = intval($_POST['type_id'] ?? 0);
        $inventory->query("DELETE FROM product_type_fields WHERE id = $field_id");
        $_SESSION['pt_message'] = ['type' => 'success', 'text' => 'Field deleted.'];
        header("Location: product_types.php?manage=" . $type_id);
        exit;
    }

    // ── Save Dropdown Options ─────────────────────────────────────
    if ($action === 'save_options') {
        $field_id = intval($_POST['field_id'] ?? 0);
        $type_id  = intval($_POST['type_id'] ?? 0);
        $labels   = $_POST['opt_label'] ?? [];
        $values   = $_POST['opt_value'] ?? [];

        if ($field_id > 0) {
            $inventory->query("DELETE FROM product_type_field_options WHERE field_id = $field_id");
            $stmt = $inventory->prepare("INSERT INTO product_type_field_options (field_id, label, value, sort_order) VALUES (?,?,?,?)");
            foreach ($labels as $i => $label) {
                $label = trim($label);
                $value = trim($values[$i] ?? '');
                if ($label !== '' && $value !== '') {
                    $stmt->bind_param("issi", $field_id, $label, $value, $i);
                    $stmt->execute();
                }
            }
            $_SESSION['pt_message'] = ['type' => 'success', 'text' => 'Options saved.'];
        }
        header("Location: product_types.php?manage=" . $type_id . "&options=" . $field_id);
        exit;
    }

    // ── Save Pricing ──────────────────────────────────────────────
    if ($action === 'save_pricing') {
        $type_id = intval($_POST['type_id'] ?? 0);
        $prices  = $_POST['pricing'] ?? [];

        foreach ($prices as $p) {
            $price           = floatval($p['price'] ?? 0);
            $variant_field   = !empty($p['variant_field_id']) ? intval($p['variant_field_id']) : null;
            $variant_value   = !empty($p['variant_value']) ? trim($p['variant_value']) : null;
            $effective_date  = $p['effective_date'] ?? date('Y-m-d');
            $pid             = intval($p['id'] ?? 0);

            if ($pid > 0) {
                $stmt = $inventory->prepare("UPDATE product_type_pricing SET price_per_piece=?, variant_field_id=?, variant_value=?, effective_date=? WHERE id=?");
                $stmt->bind_param("dissl", $price, $variant_field, $variant_value, $effective_date, $pid);
                $stmt->execute();
            } else {
                $stmt = $inventory->prepare("INSERT INTO product_type_pricing (product_type_id, price_per_piece, variant_field_id, variant_value, effective_date) VALUES (?,?,?,?,?)");
                $stmt->bind_param("idiss", $type_id, $price, $variant_field, $variant_value, $effective_date);
                $stmt->execute();
            }
        }
        $_SESSION['pt_message'] = ['type' => 'success', 'text' => 'Pricing saved.'];
        header("Location: product_types.php?manage=" . $type_id . "&tab=pricing");
        exit;
    }

    // ── Delete Pricing Row ────────────────────────────────────────
    if ($action === 'delete_pricing') {
        $pid     = intval($_POST['pricing_id'] ?? 0);
        $type_id = intval($_POST['type_id'] ?? 0);
        $inventory->query("DELETE FROM product_type_pricing WHERE id = $pid");
        header("Location: product_types.php?manage=" . $type_id . "&tab=pricing");
        exit;
    }
}

// ── Flash message ─────────────────────────────────────────────────
$flash = null;
if (isset($_SESSION['pt_message'])) {
    $flash = $_SESSION['pt_message'];
    unset($_SESSION['pt_message']);
}

// ── Fetch all product types ───────────────────────────────────────
$types_result = $inventory->query("SELECT * FROM product_types ORDER BY sort_order ASC, name ASC");
$types = [];
while ($row = $types_result->fetch_assoc()) {
    $types[] = $row;
}

// ── Fetch distinct paper type/size pairs (from the paper inventory) ──
// Used to populate the "Requires Paper Stock" dropdowns below, so the
// values entered here always match real paper stock in `products`.
$paper_pairs = [];
$paper_types_list = [];
$pp_result = $inventory->query("SELECT DISTINCT product_type, product_group FROM products ORDER BY product_type, product_group");
while ($row = $pp_result->fetch_assoc()) {
    $paper_pairs[] = $row;
    if (!in_array($row['product_type'], $paper_types_list, true)) {
        $paper_types_list[] = $row['product_type'];
    }
}

// Same cut-size options used by job_orders.php, so a piece count entered
// here maps to the exact same sheet math when stock gets deducted.
$cut_size_options = [
    'whole' => 'Whole Sheet (1)',
    '1/2'   => '1/2',
    '1/3'   => '1/3',
    '1/4'   => '1/4',
    '1/6'   => '1/6',
    '1/8'   => '1/8',
    '1/10'  => '1/10',
    '1/12'  => '1/12',
    '1/14'  => '1/14',
    '1/16'  => '1/16',
    '1/18'  => '1/18',
    '1/20'  => '1/20',
    '1/22'  => '1/22',
    '1/24'  => '1/24',
    '1/25'  => '1/25',
    '1/26'  => '1/26',
    '1/28'  => '1/28',
    '1/30'  => '1/30',
    '1/32'  => '1/32',
    '1/36'  => '1/36',
    '1/40'  => '1/40',
    '1/48'  => '1/48',
    '1/50'  => '1/50',
];

// ── Manage mode: fetch fields + pricing for selected type ─────────
$manage_id   = intval($_GET['manage'] ?? 0);
$active_tab  = $_GET['tab'] ?? 'fields';
$options_fid = intval($_GET['options'] ?? 0);
$manage_type = null;
$fields      = [];
$pricing     = [];

if ($manage_id > 0) {
    $r = $inventory->query("SELECT * FROM product_types WHERE id = $manage_id");
    $manage_type = $r->fetch_assoc();

    $fr = $inventory->query("SELECT * FROM product_type_fields WHERE product_type_id = $manage_id ORDER BY sort_order ASC, field_label ASC");
    while ($row = $fr->fetch_assoc()) {
        $fields[] = $row;
    }

    $pr = $inventory->query("SELECT p.*, f.field_label FROM product_type_pricing p LEFT JOIN product_type_fields f ON p.variant_field_id = f.id WHERE p.product_type_id = $manage_id ORDER BY p.effective_date DESC");
    while ($row = $pr->fetch_assoc()) {
        $pricing[] = $row;
    }
}

// ── Fetch options for a specific field ───────────────────────────
$options_field = null;
$field_options = [];
if ($options_fid > 0) {
    $ofr = $inventory->query("SELECT * FROM product_type_fields WHERE id = $options_fid");
    $options_field = $ofr->fetch_assoc();
    $opr = $inventory->query("SELECT * FROM product_type_field_options WHERE field_id = $options_fid ORDER BY sort_order ASC");
    while ($row = $opr->fetch_assoc()) {
        $field_options[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Product Types - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../assets/css/pages/product_types.css" />
</head>

<body>

    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="papers.php"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li class="active"><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">

        <!-- Header -->
        <header class="header">
            <div>
                <h1>
                    <?= $manage_type ? htmlspecialchars($manage_type['name']) . ' - Manage' : 'Product Types' ?>
                </h1>
                <p style="color:var(--gray);font-size:13px;margin-top:4px;">
                    <i class="fas fa-calendar-alt" style="margin-right:5px;"></i> <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="job_orders.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Job Orders
                </a>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
                    <div class="user-details">
                        <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
                        <small><?= $_SESSION['role'] ?></small>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($flash['text']) ?>
            </div>
        <?php endif; ?>

        <?php if (!$manage_type): ?>
            <!-- ══════════════════════════════════════════════════════════
         LIST VIEW — All Product Types
    ══════════════════════════════════════════════════════════ -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-tags"></i> All Product Types</h3>
                    <button class="btn btn-primary" onclick="openTypeModal()">
                        <i class="fas fa-plus"></i> Add Product Type
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($types)): ?>
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <p>No product types yet. Add your first one to get started.</p>
                            <button class="btn btn-primary" onclick="openTypeModal()">
                                <i class="fas fa-plus"></i> Add Product Type
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="type-grid">
                            <?php foreach ($types as $t): ?>
                                <div class="type-card <?= $t['is_active'] ? '' : 'inactive' ?>">
                                    <form method="POST" onsubmit="return confirm('Delete this product type? All fields and pricing will also be deleted.')">
                                        <input type="hidden" name="action" value="delete_type">
                                        <input type="hidden" name="type_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="type-card-delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <div class="type-card-top">
                                        <div class="type-icon">
                                            <i class="fas <?= htmlspecialchars($t['icon'] ?? 'fa-print') ?>"></i>
                                        </div>
                                        <div>
                                            <div class="type-name"><?= htmlspecialchars($t['name']) ?></div>
                                            <?php if ($t['description']): ?>
                                                <div class="type-desc"><?= htmlspecialchars($t['description']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="type-meta">
                                        <span class="badge <?= $t['is_active'] ? 'badge-success' : 'badge-secondary' ?>">
                                            <?= $t['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                        <span style="font-size:11px;color:var(--gray);">Order: <?= $t['sort_order'] ?></span>
                                    </div>
                                    <?php if (!empty($t['requires_paper'])): ?>
                                        <div class="type-meta" style="margin-top:6px;">
                                            <span class="badge badge-info" title="Deducts paper stock on job orders">
                                                <i class="fas fa-scroll"></i>
                                                Uses Paper: <?= htmlspecialchars($t['paper_type']) ?> / <?= htmlspecialchars($t['paper_size']) ?>
                                                (<?= htmlspecialchars($t['cut_size'] ?? 'whole') ?>)
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="type-actions">
                                        <a href="product_types.php?manage=<?= $t['id'] ?>" class="btn btn-primary btn-sm type-actions-primary">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                        <div class="type-actions-secondary">
                                            <button class="btn btn-gray btn-sm" onclick="openTypeModal(<?= htmlspecialchars(json_encode($t)) ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Toggle active status?')">
                                                <input type="hidden" name="action" value="toggle_type">
                                                <input type="hidden" name="type_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-<?= $t['is_active'] ? 'warning' : 'success' ?> btn-sm">
                                                    <i class="fas fa-<?= $t['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                                    <?= $t['is_active'] ? 'Disable' : 'Enable' ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- ══════════════════════════════════════════════════════════
         MANAGE VIEW — Fields & Pricing for a specific type
    ══════════════════════════════════════════════════════════ -->
            <div class="breadcrumb">
                <a href="product_types.php"><i class="fas fa-arrow-left"></i> Product Types</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= htmlspecialchars($manage_type['name']) ?></span>
            </div>

            <!-- Tab Bar -->
            <div class="tab-bar">
                <button class="tab-btn <?= $active_tab === 'fields' ? 'active' : '' ?>" onclick="switchTab('fields')">
                    <i class="fas fa-list-ul"></i> Fields
                </button>
                <button class="tab-btn <?= $active_tab === 'pricing' ? 'active' : '' ?>" onclick="switchTab('pricing')">
                    <i class="fas fa-peso-sign"></i> Pricing
                </button>
            </div>

            <!-- ── Fields Tab ── -->
            <div class="tab-content <?= $active_tab === 'fields' ? 'active' : '' ?>" id="tab-fields">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list-ul"></i> Form Fields</h3>
                        <button class="btn btn-primary" onclick="openFieldModal()">
                            <i class="fas fa-plus"></i> Add Field
                        </button>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <?php if (empty($fields)): ?>
                            <div class="empty-state">
                                <i class="fas fa-list-ul"></i>
                                <p>No fields yet. Add fields that employees will fill in for this product type.</p>
                                <button class="btn btn-primary" onclick="openFieldModal()">
                                    <i class="fas fa-plus"></i> Add Field
                                </button>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Label</th>
                                        <th>Field Name</th>
                                        <th>Type</th>
                                        <th>Required</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fields as $f): ?>
                                        <tr>
                                            <td style="color:var(--gray);"><?= $f['sort_order'] ?></td>
                                            <td><strong><?= htmlspecialchars($f['field_label']) ?></strong></td>
                                            <td><code style="background:var(--light);padding:2px 6px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($f['field_name']) ?></code></td>
                                            <td>
                                                <span class="badge badge-success" style="text-transform:capitalize;">
                                                    <?= htmlspecialchars($f['field_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($f['is_required']): ?>
                                                    <i class="fas fa-check-circle" style="color:var(--success);"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-minus" style="color:var(--light-gray);"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                                    <button class="btn btn-gray btn-sm" onclick='openFieldModal(<?= htmlspecialchars(json_encode($f)) ?>)'>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($f['field_type'] === 'dropdown'): ?>
                                                        <a href="product_types.php?manage=<?= $manage_id ?>&options=<?= $f['id'] ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-list"></i> Options
                                                        </a>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field?')">
                                                        <input type="hidden" name="action" value="delete_field">
                                                        <input type="hidden" name="field_id" value="<?= $f['id'] ?>">
                                                        <input type="hidden" name="type_id" value="<?= $manage_id ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($options_field): ?>
                    <!-- ── Inline Options Editor ── -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> Options for: <?= htmlspecialchars($options_field['field_label']) ?></h3>
                            <a href="product_types.php?manage=<?= $manage_id ?>" class="btn btn-gray btn-sm">
                                <i class="fas fa-times"></i> Close
                            </a>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="save_options">
                                <input type="hidden" name="field_id" value="<?= $options_fid ?>">
                                <input type="hidden" name="type_id" value="<?= $manage_id ?>">

                                <div style="display:grid;grid-template-columns:1fr 1fr 40px;gap:8px;margin-bottom:8px;">
                                    <strong style="font-size:12px;color:var(--gray);">LABEL (shown to user)</strong>
                                    <strong style="font-size:12px;color:var(--gray);">VALUE (stored in DB)</strong>
                                    <span></span>
                                </div>
                                <div id="options-list">
                                    <?php if (empty($field_options)): ?>
                                        <div class="options-row">
                                            <input type="text" name="opt_label[]" class="form-control" placeholder="e.g. Small">
                                            <input type="text" name="opt_value[]" class="form-control" placeholder="e.g. S">
                                            <button type="button" onclick="removeOption(this)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:16px;padding:4px;">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($field_options as $opt): ?>
                                            <div class="options-row">
                                                <input type="text" name="opt_label[]" class="form-control" value="<?= htmlspecialchars($opt['label']) ?>">
                                                <input type="text" name="opt_value[]" class="form-control" value="<?= htmlspecialchars($opt['value']) ?>">
                                                <button type="button" onclick="removeOption(this)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:16px;padding:4px;">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="add-option-btn" onclick="addOption()">
                                    <i class="fas fa-plus"></i> Add Option
                                </button>
                                <div style="margin-top:16px;display:flex;gap:8px;">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Save Options
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Pricing Tab ── -->
            <div class="tab-content <?= $active_tab === 'pricing' ? 'active' : '' ?>" id="tab-pricing">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-peso-sign"></i> Pricing (per piece)</h3>
                        <button class="btn btn-primary" onclick="addPricingRow()">
                            <i class="fas fa-plus"></i> Add Price Row
                        </button>
                    </div>
                    <div class="card-body">
                        <p style="font-size:13px;color:var(--gray);margin-bottom:16px;">
                            Set a base price per piece. You can also set variant-specific prices (e.g. XL costs more than S). Leave variant blank for the base/default price.
                        </p>
                        <form method="POST" id="pricing-form">
                            <input type="hidden" name="action" value="save_pricing">
                            <input type="hidden" name="type_id" value="<?= $manage_id ?>">

                            <table class="data-table" id="pricing-table">
                                <thead>
                                    <tr>
                                        <th>Variant Field</th>
                                        <th>Variant Value</th>
                                        <th>Price Per Piece (₱)</th>
                                        <th>Effective Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="pricing-body">
                                    <?php foreach ($pricing as $p): ?>
                                        <tr data-pricing-id="<?= $p['id'] ?>">
                                            <td>
                                                <input type="hidden" name="pricing[<?= $p['id'] ?>][id]" value="<?= $p['id'] ?>">
                                                <select name="pricing[<?= $p['id'] ?>][variant_field_id]" class="form-control" style="min-width:140px;">
                                                    <option value="">— Base Price —</option>
                                                    <?php foreach ($fields as $f): if ($f['field_type'] === 'dropdown'): ?>
                                                            <option value="<?= $f['id'] ?>" <?= $p['variant_field_id'] == $f['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($f['field_label']) ?>
                                                            </option>
                                                    <?php endif;
                                                    endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="pricing[<?= $p['id'] ?>][variant_value]" class="form-control price-input" value="<?= htmlspecialchars($p['variant_value'] ?? '') ?>" placeholder="e.g. XL">
                                            </td>
                                            <td>
                                                <input type="number" name="pricing[<?= $p['id'] ?>][price]" class="form-control price-input" value="<?= $p['price_per_piece'] ?>" step="0.01" min="0" required>
                                            </td>
                                            <td>
                                                <input type="date" name="pricing[<?= $p['id'] ?>][effective_date]" class="form-control" value="<?= $p['effective_date'] ?>">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="deletePricingRow(<?= $p['id'] ?>, <?= $manage_id ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php if (!empty($pricing)): ?>
                                <div style="margin-top:16px;">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Save Pricing
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>

                        <?php if (empty($pricing)): ?>
                            <div class="empty-state">
                                <i class="fas fa-peso-sign"></i>
                                <p>No pricing set yet. Add at least a base price per piece.</p>
                                <button class="btn btn-primary" onclick="addPricingRow()">
                                    <i class="fas fa-plus"></i> Add Base Price
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div><!-- end main-content -->


    <!-- ══ Modal: Add/Edit Product Type ══════════════════════════════ -->
    <div class="modal-overlay" id="typeModalOverlay" onclick="closeTypeModal()"></div>
    <div class="modal" id="typeModal">
        <div class="modal-header">
            <h4 id="typeModalTitle"><i class="fas fa-tags"></i> Add Product Type</h4>
            <button class="modal-close" onclick="closeTypeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_type">
            <input type="hidden" name="type_id" id="modal_type_id" value="0">
            <div class="modal-body">
                <div class="form-group">
                    <label>Name <span class="req">*</span></label>
                    <input type="text" name="name" id="modal_name" class="form-control" placeholder="e.g. T-Shirt" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="modal_description" class="form-control" placeholder="Short description">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Icon (Font Awesome class)</label>
                        <input type="text" name="icon" id="modal_icon" class="form-control" placeholder="fa-tshirt">
                        <small style="color:var(--gray);font-size:11px;">e.g. fa-tshirt, fa-mug-hot, fa-umbrella</small>
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="modal_sort" class="form-control" value="0" min="0">
                    </div>
                </div>
                <label class="form-check">
                    <input type="checkbox" name="is_active" id="modal_active" checked>
                    Active (visible in job orders)
                </label>

                <hr style="border:none;border-top:1px solid var(--light-gray);margin:16px 0;">

                <label class="form-check">
                    <input type="checkbox" name="requires_paper" id="modal_requires_paper" onchange="togglePaperFields()">
                    This product still uses paper stock (deduct on job orders)
                </label>
                <small style="color:var(--gray);font-size:11px;display:block;margin-top:2px;">
                    e.g. mugs or shirts printed on transfer paper. Quantity ordered ÷ cut size will be deducted from the matching paper stock.
                </small>

                <div id="paperFieldsGroup" style="display:none;margin-top:12px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Paper Type</label>
                            <select name="paper_type" id="modal_paper_type" class="form-control" onchange="updateModalPaperSizes()">
                                <option value="">Select</option>
                                <?php foreach ($paper_types_list as $pt_name): ?>
                                    <option value="<?= htmlspecialchars($pt_name) ?>"><?= htmlspecialchars($pt_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Paper Size</label>
                            <select name="paper_size" id="modal_paper_size" class="form-control">
                                <option value="">Select paper type first</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Cut Size</label>
                        <select name="cut_size" id="modal_cut_size" class="form-control">
                            <?php foreach ($cut_size_options as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--gray);font-size:11px;">How many pieces are cut from one sheet of this paper.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeTypeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>


    <!-- ══ Modal: Add/Edit Field ═════════════════════════════════════ -->
    <div class="modal-overlay" id="fieldModalOverlay" onclick="closeFieldModal()"></div>
    <div class="modal" id="fieldModal">
        <div class="modal-header">
            <h4 id="fieldModalTitle"><i class="fas fa-list-ul"></i> Add Field</h4>
            <button class="modal-close" onclick="closeFieldModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_field">
            <input type="hidden" name="type_id" value="<?= $manage_id ?>">
            <input type="hidden" name="field_id" id="fmodal_field_id" value="0">
            <div class="modal-body">
                <div class="form-group">
                    <label>Field Label <span class="req">*</span></label>
                    <input type="text" name="field_label" id="fmodal_label" class="form-control" placeholder="e.g. Shirt Size" required>
                </div>
                <div class="form-group">
                    <label>Field Name (internal key) <span class="req">*</span></label>
                    <input type="text" name="field_name" id="fmodal_name" class="form-control" placeholder="e.g. shirt_size" required>
                    <small style="color:var(--gray);font-size:11px;">Lowercase, no spaces. Auto-generated from label.</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Field Type <span class="req">*</span></label>
                        <select name="field_type" id="fmodal_type" class="form-control">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="dropdown">Dropdown</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="textarea">Textarea</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="fmodal_sort" class="form-control" value="0" min="0">
                    </div>
                </div>
                <label class="form-check">
                    <input type="checkbox" name="is_required" id="fmodal_required">
                    Required field
                </label>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeFieldModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Field</button>
            </div>
        </form>
    </div>

    <!-- Standalone form for delete_pricing — keeps it outside the pricing-form to avoid nested forms -->
    <form id="delete-pricing-form" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_pricing">
        <input type="hidden" name="pricing_id" value="">
        <input type="hidden" name="type_id" value="">
    </form>

    <script>
        window.PRODUCT_TYPES_DATA = {
            paperPairs: <?= json_encode($paper_pairs) ?>,
            dropdownFields: <?= json_encode(array_map(fn($f) => ['id' => $f['id'], 'field_label' => $f['field_label']], array_filter($fields, fn($f) => $f['field_type'] === 'dropdown'))) ?>,
            today: <?= json_encode(date('Y-m-d')) ?>
        };
    </script>
    <script src="../assets/js/pages/product_types.js"></script>
</body>

</html>