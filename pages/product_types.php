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
            --primary-light: #eef1ff;
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

        /* ── Sidebar ── */
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
            background-color: var(--primary-light);
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
            background-color: var(--primary-light);
        }

        /* ── Main ── */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 28px 32px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--dark);
        }

        .btn-outline:hover {
            background-color: var(--light);
            opacity: 1;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
        }

        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
        }

        .user-details small {
            color: var(--gray);
            font-size: 12px;
        }

        /* ── Alerts ── */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
        }

        .alert i {
            font-size: 16px;
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success);
            border: none;
        }

        .alert-danger {
            background: var(--danger-bg);
            color: var(--danger);
            border: none;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            transition: background-color 0.15s ease, opacity 0.15s ease;
            text-decoration: none;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn i {
            font-size: 13px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
            opacity: 1;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-gray {
            background: var(--light-gray);
            color: var(--dark);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* ── Cards ── */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h3 i {
            color: var(--primary);
        }

        .card-body {
            padding: 20px;
        }

        /* ── Type Grid ── */
        .type-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .type-card {
            position: relative;
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-height: 260px;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .type-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(20, 23, 31, 0.10);
            border-color: var(--primary);
        }

        .type-card.inactive {
            opacity: 0.7;
        }

        .type-card-top {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-right: 28px;
        }

        .type-card-delete {
            position: absolute;
            top: 18px;
            right: 18px;
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            font-size: 13px;
            line-height: 1;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .type-card-delete:hover {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .type-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--primary);
            flex-shrink: 0;
        }

        .type-name {
            font-size: 14px;
            font-weight: 600;
        }

        .type-desc {
            font-size: 12px;
            color: var(--gray);
            margin-top: 2px;
        }

        .type-meta {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .badge-success {
            background: var(--success-bg);
            color: var(--success);
        }

        .badge-secondary {
            background: var(--light-gray);
            color: var(--gray);
        }

        .badge-info {
            background: var(--info-bg);
            color: var(--info);
        }

        .type-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
            padding-top: 4px;
        }

        .type-actions-primary {
            width: 100%;
            justify-content: center;
        }

        .type-actions-secondary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .type-actions-secondary .btn {
            width: 100%;
        }

        .type-actions-secondary form {
            display: contents;
        }

        /* ── Tables ── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table th {
            background: var(--light);
            padding: 10px 14px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid var(--light-gray);
        }

        .data-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--light-gray);
            vertical-align: middle;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover td {
            background: var(--light);
        }

        /* ── Tabs ── */
        .tab-bar {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }

        .tab-btn {
            padding: 10px 18px;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray);
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: color 0.15s ease, border-color 0.15s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tab-btn.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-btn:hover {
            color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* ── Forms ── */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .form-group label span.req {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-family: inherit;
            font-size: 13px;
            color: var(--dark);
            background: white;
            outline: none;
            transition: border-color 0.15s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
        }

        select.form-control {
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            cursor: pointer;
        }

        .form-check input {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(20, 23, 31, 0.35);
            backdrop-filter: blur(2px);
            z-index: 999;
            display: none;
        }

        .modal-overlay.open {
            display: block;
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.97);
            width: 90%;
            max-width: 560px;
            max-height: 85vh;
            background: white;
            border-radius: 10px;
            box-shadow: 0 12px 32px rgba(20, 23, 31, 0.18);
            z-index: 1000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            opacity: 0;
            pointer-events: none;
            transition: all 0.18s ease;
        }

        .modal.open {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
            pointer-events: auto;
        }

        .modal-header {
            padding: 14px 20px;
            background: var(--dark);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h4 {
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
            padding: 6px;
            opacity: 0.85;
            border-radius: 4px;
        }

        .modal-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        /* ── Options builder ── */
        .options-row {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }

        .options-row input {
            flex: 1;
        }

        .add-option-btn {
            background: none;
            border: 2px dashed var(--light-gray);
            border-radius: 8px;
            padding: 8px;
            width: 100%;
            color: var(--gray);
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            margin-top: 4px;
            transition: all 0.15s ease;
        }

        .add-option-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* ── Back breadcrumb ── */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            color: var(--gray);
        }

        .breadcrumb a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            padding: 6px 10px 6px 8px;
            margin-left: -8px;
            border-radius: 6px;
            transition: background-color 0.15s ease;
        }

        .breadcrumb a:hover {
            background-color: var(--primary-light);
            text-decoration: none;
        }

        .breadcrumb i {
            font-size: 11px;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 36px;
            margin-bottom: 12px;
            color: var(--light-gray);
        }

        .empty-state p {
            font-size: 13px;
            margin-bottom: 16px;
        }

        /* ── Pricing table inputs ── */
        .price-input {
            width: 120px;
        }

        @media (max-width: 1100px) {
            .type-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-wrap: wrap;
                gap: 12px;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

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

            .form-row {
                grid-template-columns: 1fr;
            }

            .type-grid {
                grid-template-columns: 1fr;
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


    <script>
        // ── Tab switching ────────────────────────────────────────────────
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector('#tab-' + tab).classList.add('active');
            document.querySelectorAll('.tab-btn').forEach(b => {
                if (b.textContent.toLowerCase().includes(tab)) b.classList.add('active');
            });
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }

        // ── Type Modal ───────────────────────────────────────────────────
        // Distinct paper type/size pairs, sourced from real paper stock,
        // used to drive the cascading "Requires Paper Stock" dropdowns.
        const paperPairs = <?= json_encode($paper_pairs) ?>;

        function togglePaperFields() {
            const on = document.getElementById('modal_requires_paper').checked;
            document.getElementById('paperFieldsGroup').style.display = on ? 'block' : 'none';
        }

        function updateModalPaperSizes(selectedSize) {
            const type = document.getElementById('modal_paper_type').value;
            const sizeSelect = document.getElementById('modal_paper_size');
            const sizes = [...new Set(paperPairs.filter(p => p.product_type === type).map(p => p.product_group))];

            sizeSelect.innerHTML = '';
            if (!type || sizes.length === 0) {
                sizeSelect.innerHTML = '<option value="">Select paper type first</option>';
                return;
            }
            sizeSelect.innerHTML = '<option value="">Select</option>';
            sizes.forEach(size => {
                const opt = document.createElement('option');
                opt.value = size;
                opt.textContent = size;
                if (selectedSize && selectedSize === size) opt.selected = true;
                sizeSelect.appendChild(opt);
            });
        }

        function openTypeModal(data) {
            document.getElementById('typeModalOverlay').classList.add('open');
            document.getElementById('typeModal').classList.add('open');
            if (data) {
                document.getElementById('typeModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Product Type';
                document.getElementById('modal_type_id').value = data.id;
                document.getElementById('modal_name').value = data.name;
                document.getElementById('modal_description').value = data.description || '';
                document.getElementById('modal_icon').value = data.icon || '';
                document.getElementById('modal_sort').value = data.sort_order;
                document.getElementById('modal_active').checked = data.is_active == 1;

                const requiresPaper = !!data.requires_paper && data.requires_paper != 0;
                document.getElementById('modal_requires_paper').checked = requiresPaper;
                document.getElementById('modal_paper_type').value = data.paper_type || '';
                updateModalPaperSizes(data.paper_size || '');
                document.getElementById('modal_cut_size').value = data.cut_size || 'whole';
                togglePaperFields();
            } else {
                document.getElementById('typeModalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Product Type';
                document.getElementById('modal_type_id').value = 0;
                document.getElementById('modal_name').value = '';
                document.getElementById('modal_description').value = '';
                document.getElementById('modal_icon').value = 'fa-print';
                document.getElementById('modal_sort').value = 0;
                document.getElementById('modal_active').checked = true;

                document.getElementById('modal_requires_paper').checked = false;
                document.getElementById('modal_paper_type').value = '';
                updateModalPaperSizes();
                document.getElementById('modal_cut_size').value = 'whole';
                togglePaperFields();
            }
        }

        function closeTypeModal() {
            document.getElementById('typeModalOverlay').classList.remove('open');
            document.getElementById('typeModal').classList.remove('open');
        }

        // ── Field Modal ──────────────────────────────────────────────────
        function openFieldModal(data) {
            document.getElementById('fieldModalOverlay').classList.add('open');
            document.getElementById('fieldModal').classList.add('open');
            if (data) {
                document.getElementById('fieldModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Field';
                document.getElementById('fmodal_field_id').value = data.id;
                document.getElementById('fmodal_label').value = data.field_label;
                document.getElementById('fmodal_name').value = data.field_name;
                document.getElementById('fmodal_type').value = data.field_type;
                document.getElementById('fmodal_sort').value = data.sort_order;
                document.getElementById('fmodal_required').checked = data.is_required == 1;
            } else {
                document.getElementById('fieldModalTitle').innerHTML = '<i class="fas fa-plus"></i> Add Field';
                document.getElementById('fmodal_field_id').value = 0;
                document.getElementById('fmodal_label').value = '';
                document.getElementById('fmodal_name').value = '';
                document.getElementById('fmodal_type').value = 'text';
                document.getElementById('fmodal_sort').value = 0;
                document.getElementById('fmodal_required').checked = false;
            }
        }

        function closeFieldModal() {
            document.getElementById('fieldModalOverlay').classList.remove('open');
            document.getElementById('fieldModal').classList.remove('open');
        }

        // Auto-generate field_name from label
        document.getElementById('fmodal_label')?.addEventListener('input', function() {
            const nameField = document.getElementById('fmodal_name');
            if (document.getElementById('fmodal_field_id').value === '0') {
                nameField.value = this.value.toLowerCase().trim().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
            }
        });

        // ── Options builder ──────────────────────────────────────────────
        function addOption() {
            const list = document.getElementById('options-list');
            const row = document.createElement('div');
            row.className = 'options-row';
            row.innerHTML = `
        <input type="text" name="opt_label[]" class="form-control" placeholder="e.g. Small">
        <input type="text" name="opt_value[]" class="form-control" placeholder="e.g. S">
        <button type="button" onclick="removeOption(this)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:16px;padding:4px;">
            <i class="fas fa-times-circle"></i>
        </button>`;
            list.appendChild(row);
        }

        function removeOption(btn) {
            btn.closest('.options-row').remove();
        }

        // ── Pricing row builder ──────────────────────────────────────────
        let newPricingIdx = 9000;

        function addPricingRow() {
            const body = document.getElementById('pricing-body');
            const idx = ++newPricingIdx;
            const fieldsOptions = <?= json_encode(array_map(fn($f) => ['id' => $f['id'], 'field_label' => $f['field_label']], array_filter($fields, fn($f) => $f['field_type'] === 'dropdown'))) ?>;
            let optHtml = '<option value="">— Base Price —</option>';
            fieldsOptions.forEach(f => {
                optHtml += `<option value="${f.id}">${f.field_label}</option>`;
            });

            const row = document.createElement('tr');
            row.innerHTML = `
        <td>
            <input type="hidden" name="pricing[${idx}][id]" value="0">
            <select name="pricing[${idx}][variant_field_id]" class="form-control" style="min-width:140px;">${optHtml}</select>
        </td>
        <td><input type="text" name="pricing[${idx}][variant_value]" class="form-control price-input" placeholder="e.g. XL"></td>
        <td><input type="number" name="pricing[${idx}][price]" class="form-control price-input" step="0.01" min="0" required placeholder="0.00"></td>
        <td><input type="date" name="pricing[${idx}][effective_date]" class="form-control" value="<?= date('Y-m-d') ?>"></td>
        <td><button type="button" onclick="this.closest('tr').remove()" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></td>`;
            body.appendChild(row);

            // Show save button + form submit
            const form = document.getElementById('pricing-form');
            let saveBtn = document.getElementById('inline-save-btn');
            if (!saveBtn) {
                const wrap = document.createElement('div');
                wrap.style.marginTop = '16px';
                wrap.innerHTML = `<button type="submit" id="inline-save-btn" class="btn btn-success"><i class="fas fa-save"></i> Save Pricing</button>`;
                form.appendChild(wrap);
            }

            // Remove empty state if present
            document.querySelector('#tab-pricing .empty-state')?.remove();
        }

        // Close modals on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeTypeModal();
                closeFieldModal();
            }
        });

        // ── Delete pricing row ───────────────────────────────────────────
        // Uses a standalone hidden form to avoid nested <form> (invalid HTML).
        function deletePricingRow(pricingId, typeId) {
            if (!confirm('Delete this pricing row?')) return;
            const form = document.getElementById('delete-pricing-form');
            form.querySelector('[name="pricing_id"]').value = pricingId;
            form.querySelector('[name="type_id"]').value = typeId;
            form.submit();
        }
    </script>

    <!-- Standalone form for delete_pricing — keeps it outside the pricing-form to avoid nested forms -->
    <form id="delete-pricing-form" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_pricing">
        <input type="hidden" name="pricing_id" value="">
        <input type="hidden" name="type_id" value="">
    </form>

</body>

</html>