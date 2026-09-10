<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

/**
 * Figures out where "back" should go: the page that linked here, as long as
 * it's actually part of this app (never trust an arbitrary redirect target).
 * On GET this reads the Referer header; on POST it reads the hidden
 * "return_to" field the form carries forward from that GET load, since the
 * Referer on a self-submitting POST is just this same edit page.
 */
function resolve_return_url(string $candidate, string $fallback): string
{
    if ($candidate === '') return $fallback;
    $host = parse_url($candidate, PHP_URL_HOST);
    $curHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($host && $curHost && strcasecmp($host, $curHost) === 0) {
        return $candidate;
    }
    return $fallback;
}

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

$return_to = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? resolve_return_url($_POST['return_to'] ?? '', 'job_orders.php')
    : resolve_return_url($_SERVER['HTTP_REFERER'] ?? '', 'job_orders.php');

// Cut-size options — matches job_orders.php (up to 1/50). Defined at file
// scope so both the POST handler and the GET-rendered form/JS data can use it.
$cut_size_map = [
    '1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, '1/10' => 10,
    '1/12' => 12, '1/14' => 14, '1/16' => 16, '1/18' => 18, '1/20' => 20,
    '1/22' => 22, '1/24' => 24, '1/25' => 25, '1/26' => 26, '1/28' => 28,
    '1/30' => 30, '1/32' => 32, '1/36' => 36, '1/40' => 40, '1/48' => 48,
    '1/50' => 50, 'whole' => 1,
];

// Whether this job order is a non-paper product type (t-shirts, mugs, etc.)
$is_non_paper = !empty($job['product_type_id']);

// Renders one repeatable "paper group" block (Paper Type / Size / Cut Size /
// Copies per Set + its own color sequence). Mirrors job_orders.php's create
// form so both use the same paper_group[idx][...] field naming.
function render_paper_group_html($idx, $group, $cut_size_map, $inventory, $spoilage_map = [], $collapsed = false) {
    $pg_type   = $group['paper_type'] ?? '';
    $pg_size   = $group['paper_size'] ?? '';
    $pg_custom = $group['custom_paper_size'] ?? '';
    $pg_cut    = $group['cut_size'] ?? '';
    $pg_copies = $group['copies_per_set'] ?? '';
    $pg_seq    = $group['paper_sequence'] ?? [];
    if (!is_array($pg_seq)) {
        // Defensive: job_order_paper_items rows store this as a comma-separated
        // string, not an array — callers should convert first, but just in case.
        $pg_seq = array_map('trim', explode(',', (string)$pg_seq));
    }
    $pg_spoil  = [];
    foreach ($pg_seq as $c) {
        $pg_spoil[] = $spoilage_map[$c] ?? 0;
    }

    // Summary shown in the collapsed header row so a job with many paper
    // types stays scannable at a glance without expanding every card.
    $pg_seq_clean = array_values(array_filter($pg_seq, fn($c) => $c !== ''));
    $size_label = $pg_size === 'custom' ? ($pg_custom !== '' ? $pg_custom : 'Custom') : $pg_size;
    $summary = ($pg_type !== '' && $pg_size !== '')
        ? $pg_type . ' / ' . $size_label
        : 'Not yet configured';

    ob_start();
    ?>
    <div class="paper-group<?= $collapsed ? ' collapsed' : '' ?>" data-group-index="<?= $idx ?>" data-presize='<?= htmlspecialchars(json_encode($pg_size), ENT_QUOTES) ?>' data-preseq='<?= htmlspecialchars(json_encode($pg_seq), ENT_QUOTES) ?>' data-prespoil='<?= htmlspecialchars(json_encode($pg_spoil), ENT_QUOTES) ?>'>
        <div class="pg-header">
            <div class="pg-header-left">
                <i class="fas fa-chevron-down pg-chevron"></i>
                <span class="pg-title">Paper <?= $idx + 1 ?></span>
                <span class="pg-summary"><?= htmlspecialchars($summary) ?></span>
                <?php if (!empty($pg_seq_clean)): ?>
                    <span class="pg-summary-colors"><?= htmlspecialchars(implode(', ', $pg_seq_clean)) ?></span>
                <?php endif; ?>
            </div>
            <button type="button" class="removePaperGroupBtn" title="Remove this paper type">
                <i class="fas fa-times-circle"></i>
            </button>
        </div>
        <div class="pg-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Paper / Media Type *</label>
                    <select name="paper_group[<?= $idx ?>][paper_type]" class="form-control pg-paper-type" required>
                        <option value="">Select</option>
                        <?php
                        $types = $inventory->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
                        while ($row = $types->fetch_assoc()):
                        ?>
                            <option value="<?= htmlspecialchars($row['product_type']) ?>" <?= $pg_type === $row['product_type'] ? 'selected' : '' ?>><?= htmlspecialchars($row['product_type']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Paper Size *</label>
                    <select name="paper_group[<?= $idx ?>][paper_size]" class="form-control pg-paper-size" required>
                        <option value="">Select</option>
                    </select>
                    <input type="text" name="paper_group[<?= $idx ?>][custom_paper_size]" class="form-control pg-custom-paper-size" placeholder="Enter custom paper size" style="display:none;margin-top:.5rem;" value="<?= htmlspecialchars($pg_custom) ?>">
                </div>
                <div class="form-group">
                    <label>Cut Size *</label>
                    <select name="paper_group[<?= $idx ?>][cut_size]" class="form-control pg-cut-size" required>
                        <option value="">Select</option>
                        <?php foreach (array_keys($cut_size_map) as $cs): ?>
                            <option value="<?= $cs ?>" <?= $pg_cut === $cs ? 'selected' : '' ?>><?= $cs === 'whole' ? 'Whole' : $cs ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Number of Copies per Set *</label>
                    <input type="number" name="paper_group[<?= $idx ?>][copies_per_set]" class="form-control pg-copies-per-set" min="1" required value="<?= htmlspecialchars($pg_copies) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Color of Paper (In-Proper Order) *</label>
                <div class="pg-sequence-container"></div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Renders one repeatable "non-paper Paper Stock Used" group (Paper Type /
// Size / Cut Size / Color — simpler than the paper flow's groups above, no
// copies-per-set/multi-color list, since it's just "this product consumes
// paper stock X at quantity ÷ cut size sheets", possibly from more than one
// paper type.
function render_np_paper_group_html($idx, $group, $cut_size_map, $inventory) {
    $pg_type  = $group['paper_type'] ?? '';
    $pg_size  = $group['paper_size'] ?? '';
    $pg_cut   = $group['cut_size'] ?? 'whole';
    $pg_color = $group['color'] ?? '';
    ob_start();
    ?>
    <div class="np-paper-group" data-group-index="<?= $idx ?>" data-presize='<?= htmlspecialchars(json_encode($pg_size), ENT_QUOTES) ?>' data-precolor='<?= htmlspecialchars(json_encode($pg_color), ENT_QUOTES) ?>' style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;align-items:end;margin-bottom:10px;">
        <div class="form-group" style="margin:0;">
            <label style="font-size:11px;">Paper Type</label>
            <select class="form-control np-paper-type" name="np_paper_group[<?= $idx ?>][paper_type]">
                <option value="">Select</option>
                <?php
                $types = $inventory->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
                while ($row = $types->fetch_assoc()):
                ?>
                    <option value="<?= htmlspecialchars($row['product_type']) ?>" <?= $pg_type === $row['product_type'] ? 'selected' : '' ?>><?= htmlspecialchars($row['product_type']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:11px;">Paper Size</label>
            <select class="form-control np-paper-size" name="np_paper_group[<?= $idx ?>][paper_size]">
                <option value="">Select paper type first</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:11px;">Color</label>
            <select class="form-control np-paper-color" name="np_paper_group[<?= $idx ?>][color]">
                <option value="">Select paper size first</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:11px;">Cut Size</label>
            <select class="form-control np-cut-size" name="np_paper_group[<?= $idx ?>][cut_size]">
                <option value="">Select</option>
                <?php foreach (array_keys($cut_size_map) as $cs): ?>
                    <option value="<?= $cs ?>" <?= $pg_cut === $cs ? 'selected' : '' ?>><?= $cs === 'whole' ? 'Whole Sheet (1)' : $cs ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="button" class="np-remove-group-btn" title="Remove" style="background:none;border:none;color:var(--danger,#d9463c);cursor:pointer;font-size:15px;padding:6px;">
            <i class="fas fa-times-circle"></i>
        </button>
    </div>
    <?php
    return ob_get_clean();
}

// This job's existing paper-group breakdown (empty for jobs saved before
// this feature existed, or for non-paper jobs).
$job_paper_items = [];
$jpi_stmt = $inventory->prepare("
    SELECT paper_type, paper_size, custom_paper_size, cut_size, copies_per_set, paper_sequence
    FROM job_order_paper_items WHERE job_order_id = ? ORDER BY sort_order ASC
");
$jpi_stmt->bind_param("i", $job_id);
$jpi_stmt->execute();
$jpi_res = $jpi_stmt->get_result();
while ($row = $jpi_res->fetch_assoc()) {
    $job_paper_items[] = $row;
}
$jpi_stmt->close();

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

    $product_type_id     = !empty($_POST['product_type_id']) ? intval($_POST['product_type_id']) : null;
    $is_non_paper         = ($product_type_id !== null);

    // ── Parse repeatable "paper groups" (paper flow only) ──────────────
    // Quantity/Sets per Bind are shared across groups; only type/size/cut
    // size/colors/spoilage can differ per group (e.g. cover vs. inner pages).
    $paper_groups_raw = $_POST['paper_group'] ?? [];
    $paper_groups = [];
    foreach ($paper_groups_raw as $g) {
        $pg_type = trim($g['paper_type'] ?? '');
        $pg_size = trim($g['paper_size'] ?? '');
        if ($pg_type === '' || $pg_size === '') continue; // skip incomplete rows
        $paper_groups[] = [
            'paper_type'        => $pg_type,
            'paper_size'        => $pg_size,
            'custom_paper_size' => trim($g['custom_paper_size'] ?? ''),
            'cut_size'          => $g['cut_size'] ?? 'whole',
            'copies_per_set'    => max(1, intval($g['copies_per_set'] ?? 1)),
            // Keep raw index alignment with 'spoilage' below — filtered later per-loop.
            'paper_sequence'    => array_map('trim', $g['paper_sequence'] ?? []),
            'spoilage'          => is_array($g['spoilage'] ?? null) ? $g['spoilage'] : [],
        ];
    }

    // Legacy/primary columns on job_orders — first group's values, kept for
    // backward compatibility with search and any code reading them directly.
    // Full multi-group detail lives in job_order_paper_items.
    if (!$is_non_paper && !empty($paper_groups)) {
        $primary            = $paper_groups[0];
        $paper_type         = $primary['paper_type'];
        $paper_size         = $primary['paper_size'];
        $custom_paper_size  = $primary['custom_paper_size'];
        $product_size       = $primary['cut_size'];
        $copies_per_set     = $primary['copies_per_set'];
        $paper_sequence_str = implode(', ', array_filter($primary['paper_sequence'], fn($c) => $c !== ''));
    }

    // ── Parse repeatable non-paper "Paper Stock Used" groups ───────────
    // Same idea as $paper_groups above, but simpler: one color per group,
    // no copies-per-set/spoilage — just "this product consumes paper stock
    // X at quantity ÷ cut size sheets", possibly across more than one type.
    $np_paper_groups = [];
    if ($is_non_paper) {
        foreach ($_POST['np_paper_group'] ?? [] as $g) {
            $g_type = trim($g['paper_type'] ?? '');
            $g_size = trim($g['paper_size'] ?? '');
            if ($g_type === '' || $g_size === '') continue;
            $g_color = trim($g['color'] ?? '');
            $np_paper_groups[] = [
                'paper_type'        => $g_type,
                'paper_size'        => $g_size,
                'custom_paper_size' => '',
                'cut_size'          => $g['cut_size'] ?? 'whole',
                'copies_per_set'    => 1,
                'paper_sequence'    => [$g_color !== '' ? $g_color : 'Any'],
            ];
        }
        if (!empty($np_paper_groups)) {
            $primary            = $np_paper_groups[0];
            $paper_type         = $primary['paper_type'];
            $paper_size         = $primary['paper_size'];
            $custom_paper_size  = '';
            $product_size       = $primary['cut_size'];
            $copies_per_set     = 1;
            $paper_sequence_str = implode(', ', $primary['paper_sequence']);
        }
    }

    $cut_size             = $cut_size_map[$product_size] ?? 1;

    // Non-paper product types have no meaningful "sets per bind" — matches
    // job_orders.php's create-flow reams calc (quantity ÷ cut size only),
    // so editing "Sets per Bind" on a non-paper job can't skew the deduction.
    if ($is_non_paper) {
        $used_sheets_per_product = intval($quantity / $cut_size);
    } else {
        $total_sets              = $quantity * $number_of_sets;
        $used_sheets_per_product = intval($total_sets / $cut_size);
    }

    // Update job order
    $stmt = $inventory->prepare("UPDATE job_orders SET
        log_date = ?, client_name = ?, client_address = ?, contact_person = ?, contact_number = ?,
        project_name = ?, quantity = ?, number_of_sets = ?, product_size = ?, serial_range = ?,
        paper_size = ?, custom_paper_size = ?, paper_type = ?, copies_per_set = ?, binding_type = ?,
        custom_binding = ?, special_instructions = ?, paper_sequence = ?,
        tin = ?, client_by = ?, tax_type = ?, ocn_number = ?, date_issued = ?,
        taxpayer_name = ?, rdo_code = ?,
        province = ?, city = ?, barangay = ?, street = ?, building_no = ?, floor_no = ?, zip_code = ?,
        product_type_id = ?
        WHERE id = ?");

    $stmt->bind_param(
        "ssssssiisssssissssssssssssssssssii",
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
        $product_type_id,
        $job_id
    );

    if (!$stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error updating job order: " . $stmt->error . "</div>";
        $stmt->close();
        header("Location: edit_job.php?id=$job_id");
        exit;
    }
    $stmt->close();

    // ── Fetch all product IDs at once (no N+1) ────────────────────────
    // Both flows now use the same repeatable-group storage — paper flow
    // groups differ per paper type/size with multiple colors each; non-paper
    // "Paper Stock Used" groups are simpler (one color each) but share the
    // exact same shape, so both can go through the same logic below.
    $groups_for_deduction = !$is_non_paper ? $paper_groups : $np_paper_groups;

    if (!empty($groups_for_deduction)) {
        $product_ids = []; // "type|size|color" => product_id
        $seen_pairs = [];
        foreach ($groups_for_deduction as $group) {
            $colors = array_values(array_unique(array_filter($group['paper_sequence'], fn($c) => $c !== '')));
            if (empty($colors)) continue;
            $key_prefix = $group['paper_type'] . '|' . $group['paper_size'] . '|';
            if (isset($seen_pairs[$key_prefix])) continue;
            $seen_pairs[$key_prefix] = true;

            $placeholders = implode(',', array_fill(0, count($colors), '?'));
            $id_stmt = $inventory->prepare(
                "SELECT id, product_name FROM products
                 WHERE product_type = ? AND product_group = ? AND product_name IN ($placeholders)
                 LIMIT " . count($colors)
            );
            $bind_types = 'ss' . str_repeat('s', count($colors));
            $bind_args  = array_merge([$group['paper_type'], $group['paper_size']], $colors);
            $id_stmt->bind_param($bind_types, ...$bind_args);
            $id_stmt->execute();
            $id_result = $id_stmt->get_result();
            while ($row = $id_result->fetch_assoc()) {
                $product_ids[$key_prefix . $row['product_name']] = $row['id'];
            }
            $id_stmt->close();
        }

        // ── Delete old usage logs ──
        $del_logs = $inventory->prepare("DELETE FROM usage_logs WHERE job_order_id = ?");
        $del_logs->bind_param("i", $job_id);
        $del_logs->execute();
        $del_logs->close();

        // ── Insert updated usage logs, one per color per group ──
        $log_stmt = $inventory->prepare(
            "INSERT INTO usage_logs (product_id, used_sheets, spoilage_sheets, log_date, job_order_id, usage_note)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        foreach ($groups_for_deduction as $group) {
            $group_cut_size = $cut_size_map[$group['cut_size']] ?? 1;
            // Non-paper types have no meaningful "sets per bind" (same as
            // the single-group calc above) — paper flow multiplies by it.
            $group_total_sheets = $is_non_paper ? $quantity : ($quantity * $number_of_sets);
            $group_used_sheets  = intval($group_total_sheets / $group_cut_size);
            $key_prefix = $group['paper_type'] . '|' . $group['paper_size'] . '|';

            foreach ($group['paper_sequence'] as $i => $color) {
                if ($color === '') continue;
                $prod_id = $product_ids[$key_prefix . $color] ?? null;
                if (!$prod_id) continue;
                $spoil = intval($group['spoilage'][$i] ?? 0);
                $note  = "Updated job order for " . $client_name;
                $log_stmt->bind_param("iiisis", $prod_id, $group_used_sheets, $spoil, $log_date, $job_id, $note);
                $log_stmt->execute();
            }
        }
        $log_stmt->close();

        // ── Replace job_order_paper_items with the current group breakdown ──
        $del_items = $inventory->prepare("DELETE FROM job_order_paper_items WHERE job_order_id = ?");
        $del_items->bind_param("i", $job_id);
        $del_items->execute();
        $del_items->close();

        $jopi_stmt = $inventory->prepare("
            INSERT INTO job_order_paper_items
                (job_order_id, paper_type, paper_size, custom_paper_size, cut_size, copies_per_set, paper_sequence, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($groups_for_deduction as $i => $group) {
            $seq_str = implode(', ', array_filter($group['paper_sequence'], fn($c) => $c !== ''));
            $jopi_stmt->bind_param(
                "issssisi",
                $job_id,
                $group['paper_type'],
                $group['paper_size'],
                $group['custom_paper_size'],
                $group['cut_size'],
                $group['copies_per_set'],
                $seq_str,
                $i
            );
            $jopi_stmt->execute();
        }
        $jopi_stmt->close();
    } else {
        // ── Edge case: paper flow with no valid groups, or a non-paper type
        // that doesn't consume paper stock at all — nothing to deduct, and
        // no per-group breakdown to keep from a previous save. ──
        $del_logs = $inventory->prepare("DELETE FROM usage_logs WHERE job_order_id = ?");
        $del_logs->bind_param("i", $job_id);
        $del_logs->execute();
        $del_logs->close();

        $del_items = $inventory->prepare("DELETE FROM job_order_paper_items WHERE job_order_id = ?");
        $del_items->bind_param("i", $job_id);
        $del_items->execute();
        $del_items->close();
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

    // ── Save/clear dynamic field values for non-paper jobs ────────────
    $del_fv = $inventory->prepare("DELETE FROM job_order_field_values WHERE job_order_id = ?");
    $del_fv->bind_param("i", $job_id);
    $del_fv->execute();
    $del_fv->close();

    if ($is_non_paper && !empty($_POST['pt_field'])) {
        $fv_stmt = $inventory->prepare(
            "INSERT INTO job_order_field_values (job_order_id, field_id, field_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)"
        );
        foreach ($_POST['pt_field'] as $field_id => $value) {
            $fid = intval($field_id);
            $val = trim($value);
            $fv_stmt->bind_param("iis", $job_id, $fid, $val);
            $fv_stmt->execute();
        }
        $fv_stmt->close();
    }

    // Auto-save estimated total cost for non-paper jobs (from JS calculation) —
    // only while no cost has been set yet, so it doesn't clobber a value
    // staff already entered via "Set Total Cost".
    if ($is_non_paper && !empty($_POST['np_estimated_cost']) && empty(floatval($job['total_cost'] ?? 0))) {
        $np_cost = floatval($_POST['np_estimated_cost']);
        if ($np_cost > 0) {
            $update_cost = $inventory->prepare("UPDATE job_orders SET total_cost = ? WHERE id = ?");
            $update_cost->bind_param("di", $np_cost, $job_id);
            $update_cost->execute();
            $update_cost->close();
        }
    }

    // PRG redirect — back to wherever the user came from (preserving any
    // filters/pagination on the job orders list), falling back to the
    // plain list if that isn't available or safe.
    $_SESSION['message'] = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Job order updated successfully.</div>";
    header("Location: $return_to");
    exit;
}

// ── Fetch provinces for dropdown ──────────────────────────────────────
$provinces = [];
$res = $inventory->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($row = $res->fetch_assoc()) $provinces[] = $row['province'];

// ── Active product types + fields/options/pricing (non-paper support) ──
// Mirrors job_orders.php so the same print-type selector, dynamic fields,
// and "Paper Stock Used" section can be reused here for editing.
$active_product_types_result = $inventory->query("
    SELECT pt.*, COUNT(ptf.id) AS field_count
    FROM product_types pt
    LEFT JOIN product_type_fields ptf ON ptf.product_type_id = pt.id
    WHERE pt.is_active = 1
    GROUP BY pt.id
    ORDER BY pt.sort_order ASC, pt.name ASC
");
$active_product_types = [];
while ($row = $active_product_types_result->fetch_assoc()) {
    $active_product_types[] = $row;
}

$pt_fields_all = [];
$pt_options_all = [];
if (!empty($active_product_types)) {
    $pt_ids = implode(',', array_column($active_product_types, 'id'));

    $fields_result = $inventory->query("
        SELECT * FROM product_type_fields
        WHERE product_type_id IN ($pt_ids)
        ORDER BY sort_order ASC
    ");
    while ($row = $fields_result->fetch_assoc()) {
        $pt_fields_all[$row['product_type_id']][] = $row;
    }

    $options_result = $inventory->query("
        SELECT o.*, f.product_type_id
        FROM product_type_field_options o
        JOIN product_type_fields f ON o.field_id = f.id
        WHERE f.product_type_id IN ($pt_ids)
        ORDER BY o.sort_order ASC
    ");
    while ($row = $options_result->fetch_assoc()) {
        $pt_options_all[$row['field_id']][] = $row;
    }
}

$pt_pricing_all = [];
$pricing_result = $inventory->query("
    SELECT product_type_id, variant_field_id, variant_value, price_per_piece
    FROM product_type_pricing
    ORDER BY product_type_id, effective_date DESC
");
while ($row = $pricing_result->fetch_assoc()) {
    $pt_pricing_all[$row['product_type_id']][] = $row;
}

// Paper defaults per product type (non-paper types that still consume paper
// stock) — used when switching to a DIFFERENT non-paper type in this form;
// the CURRENT job's own saved groups (job_order_paper_items, fetched below
// as $job_paper_items) take priority for the type it's already set to.
$pt_paper_defaults_all = [];
if (!empty($active_product_types)) {
    $pd_result = $inventory->query("
        SELECT * FROM product_type_paper_defaults
        WHERE product_type_id IN ($pt_ids)
        ORDER BY sort_order ASC
    ");
    while ($row = $pd_result->fetch_assoc()) {
        $pt_paper_defaults_all[$row['product_type_id']][] = $row;
    }
}

// This job's already-saved dynamic field values, keyed by field_id, so the
// non-paper form can be pre-filled when editing.
$existing_field_values = [];
$efv_stmt = $inventory->prepare("SELECT field_id, field_value FROM job_order_field_values WHERE job_order_id = ?");
$efv_stmt->bind_param("i", $job_id);
$efv_stmt->execute();
$efv_res = $efv_stmt->get_result();
while ($row = $efv_res->fetch_assoc()) {
    $existing_field_values[$row['field_id']] = $row['field_value'];
}
$efv_stmt->close();

// Whether this job's saved paper_type/size actually reflect real paper stock
// usage (vs. the "N/A" dummy written for non-paper types that don't consume
// paper) — same check used by job_order_card_renderer.php.
$np_uses_paper = $is_non_paper && !empty(trim($job['paper_type'] ?? '')) && trim($job['paper_type']) !== 'N/A';
$np_saved_color = trim(explode(',', $job['paper_sequence'] ?? '')[0] ?? '');
if ($np_saved_color === 'Any') $np_saved_color = '';

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
                    <div class="value"><?= htmlspecialchars($job['client_name']) ?> - <?= htmlspecialchars($job['project_name']) ?></div>
                    <div class="label">Ordered <?= date('M j, Y', strtotime($job['log_date'])) ?> &middot; Qty <?= (int)$job['quantity'] ?> &middot; <?= (int)$job['number_of_sets'] ?> set(s)</div>
                </div>
            </div>

            <form method="post" class="edit-form">
                <input type="hidden" name="return_to" value="<?= htmlspecialchars($return_to) ?>">
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
                                    <input type="number" id="quantity" name="quantity" min="1" class="form-control" value="<?= $job['quantity'] ?>" required>
                                </div>
                                <div class="form-group" id="number-of-sets-group">
                                    <label>Sets per Bind *</label>
                                    <input type="number" id="number_of_sets" name="number_of_sets" min="1" class="form-control" value="<?= $job['number_of_sets'] ?>" required>
                                </div>
                            </div>
                        </div>
                      </div>
                    </div>

                    <!-- ── Specifications ── -->
                    <div class="tab-content" id="specifications">
                      <div class="tab-grid">

                        <div class="form-section span-2">
                            <h3 class="section-title"><i class="fas fa-tags"></i> Print Type</h3>
                            <div class="form-grid">
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <div id="print-type-selector" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;">
                                        <label class="print-type-option" data-type="paper">
                                            <input type="radio" name="print_category" value="paper" <?= !$is_non_paper ? 'checked' : '' ?> style="display:none;">
                                            <div class="print-type-card <?= !$is_non_paper ? 'active' : '' ?>">
                                                <i class="fas fa-file-alt"></i>
                                                <span>Receipts</span>
                                            </div>
                                        </label>
                                        <?php foreach ($active_product_types as $pt): ?>
                                            <label class="print-type-option" data-type="pt_<?= $pt['id'] ?>">
                                                <input type="radio" name="print_category" value="pt_<?= $pt['id'] ?>" <?= ((int)($job['product_type_id'] ?? 0) === (int)$pt['id']) ? 'checked' : '' ?> style="display:none;">
                                                <div class="print-type-card <?= ((int)($job['product_type_id'] ?? 0) === (int)$pt['id']) ? 'active' : '' ?>">
                                                    <i class="fas <?= htmlspecialchars($pt['icon'] ?? 'fa-print') ?>"></i>
                                                    <span><?= htmlspecialchars($pt['name']) ?></span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="product_type_id" id="selected_product_type_id" value="<?= htmlspecialchars($job['product_type_id'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section" id="paper-specs-section" style="<?= $is_non_paper ? 'display:none' : '' ?>">
                            <h3 class="section-title"><i class="fas fa-file-alt"></i> Paper Types Used</h3>
                            <div id="paper-groups-container"><?php
                                if (!empty($job_paper_items)) {
                                    $total_groups = count($job_paper_items);
                                    foreach ($job_paper_items as $gi => $g) {
                                        $g['paper_sequence'] = array_map('trim', explode(',', $g['paper_sequence'] ?? ''));
                                        echo render_paper_group_html($gi, $g, $cut_size_map, $inventory, $spoilage_map, $total_groups > 1);
                                    }
                                } else {
                                    // Legacy job with no job_order_paper_items rows yet — synthesize
                                    // one group from the job's existing single-value columns.
                                    echo render_paper_group_html(0, [
                                        'paper_type'        => $job['paper_type'],
                                        'paper_size'        => $job['paper_size'],
                                        'custom_paper_size' => $job['custom_paper_size'],
                                        'cut_size'          => $job['product_size'],
                                        'copies_per_set'    => $job['copies_per_set'],
                                        'paper_sequence'    => array_map('trim', explode(',', $job['paper_sequence'] ?? '')),
                                    ], $cut_size_map, $inventory, $spoilage_map, false);
                                }
                            ?></div>
                            <button type="button" id="addPaperGroupBtn" class="btn btn-outline" style="margin-top:6px;">
                                <i class="fas fa-plus"></i> Add Another Paper Type
                            </button>
                            <small style="color:var(--gray,#888);display:block;margin-top:6px;">
                                Add a separate paper type/size for each part of the job that uses different stock (e.g. cover vs. inner pages). Quantity and Sets per Bind apply to all of them.
                            </small>

                            <!-- Legacy single-value fields, kept only for the non-paper "Paper
                                 Stock Used" flow below, which still writes its single choice here. -->
                            <input type="hidden" id="paper_type" name="paper_type" value="">
                            <input type="hidden" id="paper_size" name="paper_size" value="">
                            <input type="hidden" id="custom_paper_size" name="custom_paper_size" value="">
                            <input type="hidden" id="product_size" name="product_size" value="">
                            <input type="hidden" id="copies_per_set" name="copies_per_set" value="">
                        </div>

                        <div class="form-section" id="paper-binding-section" style="<?= $is_non_paper ? 'display:none' : '' ?>">
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

                        <!-- ── Non-paper Dynamic Fields Section ── -->
                        <div class="form-section span-2" id="nonpaper-specs-section" style="<?= $is_non_paper ? '' : 'display:none' ?>">
                            <h3 class="section-title"><i class="fas fa-sliders-h"></i> Job Specifications</h3>
                            <div id="dynamic-fields-container" class="form-grid"></div>

                            <!-- Paper stock section: only shown for product types flagged as "requires paper" -->
                            <div id="np-paper-stock-section" style="display:<?= ($is_non_paper && !empty($job_paper_items)) ? 'block' : 'none' ?>;margin-top:16px;padding:14px 16px;background:#f5f5f5;border-radius:10px;">
                                <label style="font-weight:600;font-size:13px;display:block;margin-bottom:10px;">
                                    <i class="fas fa-scroll"></i> Paper Stock Used
                                </label>
                                <div id="np-paper-groups-container"><?php
                                    if ($is_non_paper) {
                                        foreach ($job_paper_items as $gi => $g) {
                                            $colors = array_map('trim', explode(',', $g['paper_sequence'] ?? ''));
                                            $color = $colors[0] ?? '';
                                            if ($color === 'Any') $color = '';
                                            echo render_np_paper_group_html($gi, [
                                                'paper_type' => $g['paper_type'],
                                                'paper_size' => $g['paper_size'],
                                                'cut_size'   => $g['cut_size'],
                                                'color'      => $color,
                                            ], $cut_size_map, $inventory);
                                        }
                                    }
                                ?></div>
                                <button type="button" id="addNpPaperGroupBtn" class="btn btn-outline btn-sm" style="margin-top:4px;">
                                    <i class="fas fa-plus"></i> Add Another Paper Type
                                </button>
                                <small style="color:#888;font-size:11px;display:block;margin-top:8px;">
                                    Defaults come from this product type's settings but can be changed, added to, or removed per order. Order Quantity ÷ Cut Size sheets will be deducted from each paper stock.
                                </small>
                            </div>

                            <!-- Cost estimate display -->
                            <div id="np-cost-estimate" style="display:none;margin-top:16px;padding:14px 18px;background:#f5f5f5;border-radius:10px;border-left:4px solid var(--primary,#2f6feb);">
                                <strong style="font-size:13px;color:#888;">Estimated Project Price</strong>
                                <div style="font-size:20px;font-weight:700;color:var(--primary,#2f6feb);margin-top:4px;">
                                    ₱<span id="np-cost-value">0.00</span>
                                </div>
                                <small style="color:#888;font-size:11px;">This price will be auto-saved as the initial project cost only while none has been set yet. Adjust later via "Set Total Cost".</small>
                            </div>
                            <input type="hidden" name="np_estimated_cost" id="np_estimated_cost" value="0">
                        </div>
                      </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="<?= htmlspecialchars($return_to) ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Cancel</a>
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
            nextPaperGroupIndex: <?= json_encode(max(1, count($job_paper_items))) ?>,
            nextNpGroupIndex: <?= json_encode($is_non_paper ? max(1, count($job_paper_items)) : 1) ?>,
            savedProvince: <?= json_encode($job['province'] ?? '') ?>,
            savedCity: <?= json_encode($job['city'] ?? '') ?>,

            // ── Non-paper support ──
            ptFieldsAll: <?= json_encode($pt_fields_all) ?>,
            ptOptionsAll: <?= json_encode($pt_options_all) ?>,
            ptPricingAll: <?= json_encode($pt_pricing_all) ?>,
            ptPaperDefaultsAll: <?= json_encode($pt_paper_defaults_all) ?>,
            productTypesById: <?= json_encode(array_column($active_product_types, null, 'id')) ?>,
            cutSizeOptions: <?= json_encode(array_keys($cut_size_map)) ?>,
            currentProductTypeId: <?= json_encode($job['product_type_id'] ?? null) ?>,
            existingFieldValues: <?= json_encode($existing_field_values) ?>,
            npCurrentQuantity: <?= (int)$job['quantity'] ?>,
            npPaperType: <?= json_encode($np_uses_paper ? $job['paper_type'] : '') ?>,
            npPaperSize: <?= json_encode($np_uses_paper ? $job['paper_size'] : '') ?>,
            npPaperColor: <?= json_encode($np_uses_paper ? $np_saved_color : '') ?>,
            npCutSize: <?= json_encode($job['product_size'] ?? 'whole') ?>
        };
    </script>                                        

    <script src="../assets/js/pages/edit_job.js"></script>                                        
</body>

</html>