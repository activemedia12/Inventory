<?php
// quotation_presets.php
// The ONLY place the Quotation feature touches the database, and it only
// ever touches the `quotation_presets` table. Quotes themselves are never saved.
//
//   GET  ?action=list                      -> anyone logged in
//   POST action=save   (name, data, ...)   -> needs the 'save_presets' permission
//   POST action=delete (id)                -> needs the 'save_presets' permission
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    out(['success' => false, 'message' => 'Unauthorized'], 403);
}

require_once '../config/db.php';
require_once 'permissions.php';

// Created on demand, same pattern as the other tables in this system.
$inventory->query("
    CREATE TABLE IF NOT EXISTS quotation_presets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        category VARCHAR(40) NOT NULL DEFAULT 'paper',
        data LONGTEXT NOT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        updated_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_quotation_preset_name (name)
    )
");

// Older installs: add the updated_by column once.
$col = $inventory->query("SHOW COLUMNS FROM quotation_presets LIKE 'updated_by'");
if ($col && $col->num_rows === 0) {
    $inventory->query("ALTER TABLE quotation_presets ADD COLUMN updated_by VARCHAR(100) DEFAULT NULL AFTER created_by");
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'list');

// ── List ────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $rows = $inventory
        ->query("SELECT id, name, description, category, data, created_by, updated_by, updated_at FROM quotation_presets ORDER BY name ASC")
        ->fetch_all(MYSQLI_ASSOC);

    $presets = [];
    foreach ($rows as $r) {
        $decoded = json_decode($r['data'], true);
        if (!is_array($decoded)) continue; // skip anything corrupted rather than break the page
        $presets[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'description' => $r['description'],
            'category'    => $r['category'],
            'created_by'  => $r['created_by'],
            'updated_by'  => $r['updated_by'],
            'updated_at'  => $r['updated_at'],
            'data'        => $decoded,
        ];
    }
    out(['success' => true, 'presets' => $presets, 'can_save' => can('save_presets')]);
}

// Everything below changes data: POST only, and only from our own JS.
// A custom header can't be set by a cross-site form post, which blocks CSRF.
if ($method !== 'POST') {
    out(['success' => false, 'message' => 'Invalid request'], 405);
}
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
    out(['success' => false, 'message' => 'Invalid request'], 400);
}
require_permission_json('save_presets', 'save or delete quotation presets');

// ── Save ────────────────────────────────────────────────────────────
if ($action === 'save') {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $cat  = trim($_POST['category'] ?? 'paper');
    $raw  = $_POST['data'] ?? '';
    $overwrite = ($_POST['overwrite'] ?? '') === '1';

    if ($name === '' || mb_strlen($name) > 100) {
        out(['success' => false, 'message' => 'Preset name is required (max 100 characters).']);
    }
    if (mb_strlen($desc) > 255) {
        out(['success' => false, 'message' => 'Description is too long (max 255 characters).']);
    }
    if (!preg_match('/^(paper|pt_\d+)$/', $cat)) {
        $cat = 'paper';
    }
    if ($raw === '' || strlen($raw) > 200000) {
        out(['success' => false, 'message' => 'Invalid preset data.']);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        out(['success' => false, 'message' => 'Invalid preset data.']);
    }
    $json = json_encode($decoded); // re-encode so only clean JSON is ever stored
    $by   = $_SESSION['username'] ?? null;

    $chk = $inventory->prepare("SELECT id, created_by, updated_by, updated_at FROM quotation_presets WHERE name = ?");
    $chk->bind_param("s", $name);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing && !$overwrite) {
        out([
            'success'    => false,
            'code'       => 'exists',
            'message'    => 'A preset with this name already exists.',
            'updated_by' => $existing['updated_by'] ?: $existing['created_by'],
            'updated_at' => $existing['updated_at'],
        ]);
    }

    if ($existing) {
        $id = (int)$existing['id'];
        $stmt = $inventory->prepare("UPDATE quotation_presets SET description = ?, category = ?, data = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $desc, $cat, $json, $by, $id);
    } else {
        $stmt = $inventory->prepare("INSERT INTO quotation_presets (name, description, category, data, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $desc, $cat, $json, $by, $by);
    }

    if ($stmt->execute()) {
        out(['success' => true, 'message' => $existing ? 'Preset updated.' : 'Preset saved.']);
    }
    out(['success' => false, 'message' => 'Database error while saving the preset.']);
}

// ── Delete ──────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        out(['success' => false, 'message' => 'Invalid preset.']);
    }
    $stmt = $inventory->prepare("DELETE FROM quotation_presets WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        out(['success' => true, 'message' => 'Preset deleted.']);
    }
    out(['success' => false, 'message' => 'Preset not found.']);
}

out(['success' => false, 'message' => 'Unknown action'], 400);
