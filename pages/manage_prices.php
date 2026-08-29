<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

$job_id = intval($_GET['id'] ?? 0);

// ── Ensure digital_printing_prices table exists ───────────────────
$inventory->query("
    CREATE TABLE IF NOT EXISTS digital_printing_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        paper_type VARCHAR(50) NOT NULL,
        color_mode VARCHAR(20) NOT NULL,
        size_label VARCHAR(100) NOT NULL,
        content_type VARCHAR(50) DEFAULT NULL,
        price_per_paper DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        UNIQUE KEY unique_combo (paper_type, color_mode, size_label, content_type)
    )
");

// Seed defaults if empty
$count = $inventory->query("SELECT COUNT(*) as c FROM digital_printing_prices")->fetch_assoc()['c'];
if ($count == 0) {
  $defaults = [
    ['bond', 'bw', 'short', 'text_only', 5.00],
    ['bond', 'bw', 'short', 'image_text', 7.00],
    ['bond', 'bw', 'short', 'image_only', 10.00],
    ['bond', 'bw', 'long', 'text_only', 7.00],
    ['bond', 'bw', 'long', 'image_text', 9.00],
    ['bond', 'bw', 'long', 'image_only', 13.00],
    ['bond', 'colored', 'short', 'text_only', 15.00],
    ['bond', 'colored', 'short', 'image_text', 25.00],
    ['bond', 'colored', 'short', 'image_only', 40.00],
    ['bond', 'colored', 'long', 'text_only', 20.00],
    ['bond', 'colored', 'long', 'image_text', 30.00],
    ['bond', 'colored', 'long', 'image_only', 50.00],
    ['photo', 'colored', '3R size & wallet (2/3pcs)', NULL, 50.00],
    ['photo', 'colored', '4R size or 4x6 in (2pcs)', NULL, 50.00],
    ['photo', 'colored', '5R size or 5x7 in (1pc)', NULL, 50.00],
    ['photo', 'colored', '6R size or 6x8 in (1pc)', NULL, 50.00],
    ['photo', 'colored', 'A4 size', NULL, 75.00],
    ['photo', 'bw', '3R size & wallet (2/3pcs)', NULL, 15.00],
    ['photo', 'bw', '4R size or 4x6 in (2pcs)', NULL, 15.00],
    ['photo', 'bw', '5R size or 5x7 in (1pc)', NULL, 15.00],
    ['photo', 'bw', '6R size or 6x8 in (1pc)', NULL, 15.00],
    ['photo', 'bw', 'A4 size', NULL, 30.00],
    ['glossy', 'colored', 'A4 * 8.5x11 * 8.5x13 (70/80GSM)', NULL, 40.00],
    ['glossy', 'colored', 'A4 * 8.5x11 * 8.5x13 (100/120GSM)', NULL, 40.00],
    ['glossy', 'colored', 'A3, 12x18 UP (130/220GSM)', NULL, 80.00],
    ['glossy', 'colored', 'A3, 12x18 UP (250/300GSM)', NULL, 90.00],
    ['glossy', 'bw', 'A4 * 8.5x11 * 8.5x13 (70/80GSM)', NULL, 10.00],
    ['glossy', 'bw', 'A4 * 8.5x11 * 8.5x13 (100/120GSM)', NULL, 15.00],
    ['glossy', 'bw', 'A3, 12x18 UP (130/220GSM)', NULL, 25.00],
    ['glossy', 'bw', 'A3, 12x18 UP (250/300GSM)', NULL, 35.00],
    ['sticker', 'colored', 'A4 * 8.5x11 * 8.5x13', NULL, 50.00],
    ['sticker', 'colored', 'A3, 12x18 UP', NULL, 100.00],
    ['sticker', 'bw', 'A4 * 8.5x11 * 8.5x13', NULL, 20.00],
    ['sticker', 'bw', 'A3, 12x18 UP', NULL, 50.00],
  ];
  $ins = $inventory->prepare("INSERT IGNORE INTO digital_printing_prices (paper_type,color_mode,size_label,content_type,price_per_paper) VALUES (?,?,?,?,?)");
  foreach ($defaults as $d) {
    $ins->bind_param("ssssd", $d[0], $d[1], $d[2], $d[3], $d[4]);
    $ins->execute();
  }
  $ins->close();
}

// ── POST handler (PRG) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  if (isset($_POST['manpower'])) {
    $stmt = $inventory->prepare("UPDATE manpower_rates SET daily_rate=?, hourly_rate=? WHERE id=?");
    foreach ($_POST['manpower'] as $row) {
      $daily = max(0, floatval($row['daily_rate']));
      $hourly = max(0, floatval($row['hourly_rate']));
      $id = intval($row['id']);
      $stmt->bind_param("ddi", $daily, $hourly, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  if (isset($_POST['paper'])) {
    $stmt = $inventory->prepare("UPDATE paper_prices SET paper_type=?,orig_price=?,disc_price=?,short_price=?,long_price=?,price_per_sheet=?,cutting_cost=?,effective_date=? WHERE id=?");
    foreach ($_POST['paper'] as $row) {
      $pt = $row['paper_type'];
      $op = max(0, floatval($row['orig_price']));
      $dp = max(0, floatval($row['disc_price']));
      $sp = max(0, floatval($row['short_price']));
      $lp = max(0, floatval($row['long_price']));
      $pps = !empty($row['price_per_sheet']) ? max(0, floatval($row['price_per_sheet'])) : null;
      $cc = max(0, floatval($row['cutting_cost']));
      $ed = $row['effective_date'];
      $id = intval($row['id']);
      $stmt->bind_param("sddddddsi", $pt, $op, $dp, $sp, $lp, $pps, $cc, $ed, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  if (isset($_POST['cut'])) {
    $stmt = $inventory->prepare("UPDATE paper_cut_prices SET paper_type=?,short_price=?,long_price=?,price_per_sheet=?,cutting_cost=?,effective_date=? WHERE id=?");
    foreach ($_POST['cut'] as $row) {
      $pt = $row['paper_type'];
      $sp = max(0, floatval($row['short_price']));
      $lp = max(0, floatval($row['long_price']));
      $pps = !empty($row['price_per_sheet']) ? max(0, floatval($row['price_per_sheet'])) : null;
      $cc = max(0, floatval($row['cutting_cost']));
      $ed = $row['effective_date'];
      $id = intval($row['id']);
      $stmt->bind_param("sddddsi", $pt, $sp, $lp, $pps, $cc, $ed, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  if (isset($_POST['special'])) {
    $stmt = $inventory->prepare("UPDATE products SET unit_price=? WHERE id=?");
    foreach ($_POST['special'] as $row) {
      $up = max(0, floatval($row['unit_price']));
      $id = intval($row['id']);
      $stmt->bind_param("di", $up, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  if (isset($_POST['ordinary'])) {
    $stmt = $inventory->prepare("UPDATE products SET unit_price=? WHERE id=?");
    foreach ($_POST['ordinary'] as $row) {
      $up = max(0, floatval($row['unit_price']));
      $id = intval($row['id']);
      $stmt->bind_param("di", $up, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  if (isset($_POST['printing'])) {
    $stmt = $inventory->prepare("UPDATE printing_types SET base_cost=?,per_sheet_cost=?,apply_to_paper_cost=?,effective_date=? WHERE id=?");
    foreach ($_POST['printing'] as $row) {
      $bc = max(0, floatval($row['base_cost']));
      $psc = max(0, floatval($row['per_sheet_cost']));
      $atpc = isset($row['apply_to_paper_cost']) ? 1 : 0;
      $ed = $row['effective_date'];
      $id = intval($row['id']);
      $stmt->bind_param("dddsi", $bc, $psc, $atpc, $ed, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  if (isset($_POST['riso'])) {
    $stmt = $inventory->prepare("UPDATE riso_printing_prices SET price_per_ream=? WHERE id=?");
    foreach ($_POST['riso'] as $id => $row) {
      $price = max(0, floatval($row['price']));
      $id    = intval($id);
      $stmt->bind_param("di", $price, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  if (isset($_POST['digital'])) {
    $stmt = $inventory->prepare("UPDATE digital_printing_prices SET price_per_paper=? WHERE id=?");
    foreach ($_POST['digital'] as $id => $row) {
      $price = max(0, floatval($row['price']));
      $id    = intval($id);
      $stmt->bind_param("di", $price, $id);
      $stmt->execute();
    }
    $stmt->close();
  }

  $tab = $_POST['active_tab'] ?? 'manpower-rates';
  $redirect = "manage_prices.php?saved=1&tab=" . urlencode($tab);
  if ($job_id) $redirect .= "&id=" . $job_id;
  header("Location: $redirect");
  exit;
}

// ── Fetch data ────────────────────────────────────────────────────
$manpower_rates  = $inventory->query("SELECT * FROM manpower_rates ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$paper_prices    = $inventory->query("SELECT * FROM paper_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$cut_prices      = $inventory->query("SELECT * FROM paper_cut_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$printing_types  = $inventory->query("SELECT * FROM printing_types ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$special_prices  = $inventory->query("SELECT id, product_name, product_group, unit_price FROM products WHERE LOWER(product_type) = 'special paper' ORDER BY product_group, product_name")->fetch_all(MYSQLI_ASSOC);
$ordinary_prices = $inventory->query("SELECT id, product_name, product_group, unit_price FROM products WHERE LOWER(product_type) = 'ordinary paper' ORDER BY product_group, product_name")->fetch_all(MYSQLI_ASSOC);
$digital_rows    = $inventory->query("SELECT * FROM digital_printing_prices ORDER BY paper_type, color_mode, size_label, content_type")->fetch_all(MYSQLI_ASSOC);

// Index digital prices
$digital_indexed = [];
foreach ($digital_rows as $dr) {
  $digital_indexed[$dr['paper_type']][$dr['color_mode']][] = $dr;
}

$riso_prices_list = $inventory->query("SELECT * FROM riso_printing_prices ORDER BY paper_name, FIELD(size_label,'short','a4','long')")->fetch_all(MYSQLI_ASSOC);

$saved  = isset($_GET['saved']);
$active = $_GET['tab'] ?? 'manpower-rates';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Price Lists</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/pages/manage_prices.css">
</head>

<body>

  <!-- Topbar -->
  <div class="topbar">
    <div class="container">
      <div class="inner">
        <div class="topbar-left">
          <?php if ($job_id): ?>
            <a href="paper_cost.php?id=<?= $job_id ?>" class="btn-back">
              <i class="bi bi-arrow-left"></i> Back
            </a>
          <?php endif; ?>
          <div>
            <div class="topbar-title"><i class="bi bi-tags me-2"></i>Manage Price Lists</div>
            <div class="topbar-sub">Update and maintain all pricing information</div>
          </div>
        </div>
        <button type="submit" form="masterForm" class="btn-save-all">
          <i class="bi bi-check-circle-fill"></i> Save All Changes
        </button>
      </div>
    </div>
  </div>

  <div class="container">

    <?php if ($saved): ?>
      <div class="alert-success-custom">
        <i class="bi bi-check-circle-fill" style="font-size:18px"></i>
        Price list updated successfully!
        <button style="margin-left:auto;background:none;border:none;cursor:pointer;color:#065f46;font-size:18px" onclick="this.parentElement.remove()">×</button>
      </div>
      <script>
        setTimeout(() => document.querySelector('.alert-success-custom')?.remove(), 3000);
      </script>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="price-tabs-wrapper">
      <div class="price-tabs" id="priceTabs">
        <?php
        $tabs = [
          'manpower-rates'  => ['icon' => 'bi-people-fill',     'label' => 'Manpower Rates'],
          'paper-prices'    => ['icon' => 'bi-file-earmark',    'label' => 'Carbonless Paper'],
          'cut-prices'      => ['icon' => 'bi-scissors',        'label' => 'Ordinary Paper'],
          'special-prices'  => ['icon' => 'bi-gem',             'label' => 'Special Paper'],
          'printing-types'  => ['icon' => 'bi-printer-fill',    'label' => 'Printing Types'],
          'digital-prices'  => ['icon' => 'bi-display',         'label' => 'Digital Printing', 'badge_class' => 'digital'],
          'riso-prices'     => ['icon' => 'bi-printer',          'label' => 'Riso Printing',     'badge_class' => 'digital'],
        ];
        foreach ($tabs as $id => $t):
        ?>
          <button class="tab-btn <?= $active === $id ? 'active' : '' ?>"
            data-tab="<?= $id ?>" onclick="switchTab('<?= $id ?>')">
            <i class="<?= $t['icon'] ?>"></i>
            <?= $t['label'] ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <form id="masterForm" method="post">
      <input type="hidden" name="active_tab" id="activeTabInput" value="<?= htmlspecialchars($active) ?>">
      <?php if ($job_id): ?><input type="hidden" name="job_id_ref" value="<?= $job_id ?>"><?php endif; ?>

      <!-- ── MANPOWER RATES ── -->
      <div class="tab-content-pane <?= $active === 'manpower-rates' ? 'active' : '' ?>" id="tab-manpower-rates">
        <div class="price-card">
          <div class="price-card-header">
            <h6>
              <div class="card-icon"><i class="bi bi-people-fill"></i></div>Manpower Rates
            </h6>
            <span style="font-size:11.5px;color:var(--text-muted)"><?= count($manpower_rates) ?> tasks</span>
          </div>
          <div style="overflow-x:auto">
            <table class="price-table">
              <thead>
                <tr>
                  <th>Task Name</th>
                  <th>Daily Rate (₱)</th>
                  <th>Hourly Rate (₱)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($manpower_rates as $row): ?>
                  <tr>
                    <td>
                      <div style="font-weight:600"><?= htmlspecialchars($row['task_name']) ?></div>
                      <input type="hidden" name="manpower[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <td>
                      <input type="number" step="0.01" min="0" name="manpower[<?= $row['id'] ?>][daily_rate]"
                        value="<?= $row['daily_rate'] ?>" class="form-control" required>
                    </td>
                    <td>
                      <input type="number" step="0.01" min="0" name="manpower[<?= $row['id'] ?>][hourly_rate]"
                        value="<?= $row['hourly_rate'] ?>" class="form-control" required>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── CARBONLESS PAPER ── -->
      <div class="tab-content-pane <?= $active === 'paper-prices' ? 'active' : '' ?>" id="tab-paper-prices">
        <div class="price-card">
          <div class="price-card-header">
            <h6>
              <div class="card-icon"><i class="bi bi-file-earmark"></i></div>Carbonless Paper Prices
            </h6>
            <span style="font-size:11.5px;color:var(--text-muted)"><?= count($paper_prices) ?> entries</span>
          </div>
          <div style="overflow-x:auto">
            <table class="price-table">
              <thead>
                <tr>
                  <th>Paper Type</th>
                  <th>Original (₱)</th>
                  <th>Discounted (₱)</th>
                  <th>Short (₱)</th>
                  <th>Long (₱)</th>
                  <th>Per Sheet (₱)</th>
                  <th>Cutting (₱)</th>
                  <th>Effective Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($paper_prices as $row): ?>
                  <tr>
                    <td>
                      <?= htmlspecialchars($row['paper_type']) ?>
                      <input type="hidden" name="paper[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                      <input type="hidden" name="paper[<?= $row['id'] ?>][paper_type]" value="<?= htmlspecialchars($row['paper_type']) ?>">
                    </td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][orig_price]" value="<?= $row['orig_price'] ?>" class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][disc_price]" value="<?= $row['disc_price'] ?>" class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][short_price]" value="<?= $row['short_price'] ?>" class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][long_price]" value="<?= $row['long_price'] ?>" class="form-control form-control-sm" required></td>
                    <td>
                      <input type="number" step="0.0001" min="0" name="paper[<?= $row['id'] ?>][price_per_sheet]"
                        value="<?= htmlspecialchars($row['price_per_sheet'] ?? '') ?>" class="form-control form-control-sm" placeholder="0.0000">
                      <?php if (($row['price_per_sheet'] ?? 0) <= 0): ?>
                        <div style="font-size:10.5px;color:var(--text-muted);margin-top:2px">Auto: ₱<?= number_format($row['disc_price'] / 500, 4) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][cutting_cost]" value="<?= $row['cutting_cost'] ?>" class="form-control form-control-sm" required></td>
                    <td><input type="date" name="paper[<?= $row['id'] ?>][effective_date]" value="<?= $row['effective_date'] ?>" class="form-control form-control-sm" required></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── ORDINARY PAPER ── -->
      <div class="tab-content-pane <?= $active === 'cut-prices' ? 'active' : '' ?>" id="tab-cut-prices">
        <div class="price-card">
          <div class="price-card-header">
            <h6>
              <div class="card-icon"><i class="bi bi-scissors"></i></div>Ordinary Paper Prices
            </h6>
            <span style="font-size:11.5px;color:var(--text-muted)"><?= count($ordinary_prices) ?> products</span>
          </div>
          <div style="overflow-x:auto">
            <table class="price-table">
              <thead>
                <tr>
                  <th>Paper Name</th>
                  <th>Size / Group</th>
                  <th>Price / Ream (₱)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ordinary_prices as $row): ?>
                  <tr>
                    <td>
                      <?= htmlspecialchars($row['product_name']) ?>
                      <input type="hidden" name="ordinary[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <td>
                      <span style="background:var(--primary-light);color:var(--primary);padding:2px 9px;border-radius:20px;font-size:11.5px;font-weight:600">
                        <?= htmlspecialchars($row['product_group']) ?>
                      </span>
                    </td>
                    <td>
                      <input type="number" step="0.01" min="0" name="ordinary[<?= $row['id'] ?>][unit_price]"
                        value="<?= htmlspecialchars($row['unit_price']) ?>" class="form-control" required>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── SPECIAL PAPER ── -->
      <div class="tab-content-pane <?= $active === 'special-prices' ? 'active' : '' ?>" id="tab-special-prices">
        <div class="price-card">
          <div class="price-card-header">
            <h6>
              <div class="card-icon"><i class="bi bi-gem"></i></div>Special Paper Prices
            </h6>
            <span style="font-size:11.5px;color:var(--text-muted)"><?= count($special_prices) ?> products</span>
          </div>
          <div style="overflow-x:auto">
            <table class="price-table">
              <thead>
                <tr>
                  <th>Paper Name</th>
                  <th>Size / Group</th>
                  <th>Price / Sheet (₱)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($special_prices as $row): ?>
                  <tr>
                    <td>
                      <?= htmlspecialchars($row['product_name']) ?>
                      <input type="hidden" name="special[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <td>
                      <span style="background:var(--primary-light);color:var(--primary);padding:2px 9px;border-radius:20px;font-size:11.5px;font-weight:600">
                        <?= htmlspecialchars($row['product_group']) ?>
                      </span>
                    </td>
                    <td>
                      <input type="number" step="0.0001" min="0" name="special[<?= $row['id'] ?>][unit_price]"
                        value="<?= htmlspecialchars($row['unit_price']) ?>" class="form-control" required>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── PRINTING TYPES ── -->
      <div class="tab-content-pane <?= $active === 'printing-types' ? 'active' : '' ?>" id="tab-printing-types">
        <div class="price-card">
          <div class="price-card-header">
            <h6>
              <div class="card-icon"><i class="bi bi-printer-fill"></i></div>Printing Types
            </h6>
            <span style="font-size:11.5px;color:var(--text-muted)"><?= count($printing_types) ?> types</span>
          </div>
          <div style="overflow-x:auto">
            <table class="price-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Base Cost (₱)</th>
                  <th>Per Sheet (₱)</th>
                  <th>Apply to Paper?</th>
                  <th>Effective Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($printing_types as $row):
                  $isDigital = stripos($row['name'], 'digital') !== false;
                  $isRiso = stripos($row['name'], 'riso') !== false;
                ?>
                  <tr <?= ($isDigital || $isRiso) ? 'style="background:#f8faff"' : '' ?>>
                    <td>
                      <span style="font-weight:600"><?= htmlspecialchars($row['name']) ?></span>
                      <?php if ($isDigital): ?>
                        <div style="margin-top:3px">
                        </div>
                      <?php elseif ($isRiso): ?>
                        <div style="margin-top:3px">
                        </div>
                      <?php endif; ?>
                      <input type="hidden" name="printing[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <?php if ($isDigital || $isRiso): ?>
                      <td colspan="3" style="text-align:center;color:var(--text-muted);font-size:12.5px;font-style:italic">
                        — not used for <?= $isDigital ? 'Digital' : 'Riso' ?> printing —
                        <input type="hidden" name="printing[<?= $row['id'] ?>][base_cost]" value="<?= $row['base_cost'] ?>">
                        <input type="hidden" name="printing[<?= $row['id'] ?>][per_sheet_cost]" value="<?= $row['per_sheet_cost'] ?>">
                        <input type="hidden" name="printing[<?= $row['id'] ?>][apply_to_paper_cost]" value="0">
                      </td>
                    <?php else: ?>
                      <td><input type="number" step="0.01" min="0" name="printing[<?= $row['id'] ?>][base_cost]" value="<?= $row['base_cost'] ?>" class="form-control form-control-sm" required></td>
                      <td><input type="number" step="0.0001" min="0" name="printing[<?= $row['id'] ?>][per_sheet_cost]" value="<?= $row['per_sheet_cost'] ?>" class="form-control form-control-sm" required></td>
                      <td>
                        <div class="form-check form-switch d-flex justify-content-center">
                          <input class="form-check-input" type="checkbox" name="printing[<?= $row['id'] ?>][apply_to_paper_cost]" value="1" <?= $row['apply_to_paper_cost'] ? 'checked' : '' ?>>
                        </div>
                      </td>
                    <?php endif; ?>
                    <td><input type="date" name="printing[<?= $row['id'] ?>][effective_date]" value="<?= $row['effective_date'] ?>" class="form-control form-control-sm" required></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ── DIGITAL PRINTING PRICES ── -->
      <div class="tab-content-pane <?= $active === 'digital-prices' ? 'active' : '' ?>" id="tab-digital-prices">

        <!-- Info banner -->
        <div style="background:linear-gradient(135deg,#1877f2 0%,#0f4c9e 100%);border-radius:14px;padding:18px 22px;margin-bottom:20px;color:#fff;display:flex;align-items:center;gap:14px">
          <i class="bi bi-display" style="font-size:28px;opacity:0.8"></i>
          <div>
            <div style="font-weight:700;font-size:1rem">Digital Printing Price Matrix</div>
            <div style="font-size:12px;opacity:0.8;margin-top:2px">Set per-paper prices for each digital printing option. Back-to-back printing doubles the price automatically in the calculator.</div>
          </div>
        </div>

        <?php
        $paperTypes = [
          'bond'    => ['icon' => 'bi-file-earmark',  'label' => 'Bond Paper'],
          'photo'   => ['icon' => 'bi-image',          'label' => 'Photo Paper'],
          'glossy'  => ['icon' => 'bi-stars',          'label' => 'C2S Glossy Paper'],
          'sticker' => ['icon' => 'bi-tag',            'label' => 'Sticker Paper'],
        ];
        $firstPt = array_key_first($paperTypes);
        ?>

        <!-- Paper type sub-tabs -->
        <div class="digital-paper-tabs mb-4">
          <?php foreach ($paperTypes as $ptKey => $ptInfo): ?>
            <button type="button" class="dp-tab-btn <?= $ptKey === $firstPt ? 'active' : '' ?>"
              data-dptab="<?= $ptKey ?>" onclick="switchDpTab('<?= $ptKey ?>')">
              <i class="<?= $ptInfo['icon'] ?>"></i> <?= $ptInfo['label'] ?>
            </button>
          <?php endforeach; ?>
        </div>

        <?php foreach ($paperTypes as $ptKey => $ptInfo): ?>
          <div class="dp-content <?= $ptKey === $firstPt ? 'active' : '' ?>" id="dptab-<?= $ptKey ?>">
            <div class="price-card">
              <div class="price-card-header">
                <h6>
                  <div class="card-icon"><i class="<?= $ptInfo['icon'] ?>"></i></div>
                  <?= $ptInfo['label'] ?> — Prices per Paper
                </h6>
                <span style="font-size:11.5px;color:var(--text-muted)">Edit prices below — click Save All when done</span>
              </div>
              <div class="card-body" style="padding:20px">
                <div class="color-mode-grid">
                  <?php foreach (['colored' => ['label' => '🎨 Colored', 'class' => 'colored'], 'bw' => ['label' => '⬛ Black & White', 'class' => 'bw']] as $cmKey => $cmInfo): ?>
                    <div class="cm-panel">
                      <div class="cm-panel-header <?= $cmInfo['class'] ?>">
                        <?= $cmInfo['label'] ?>
                      </div>

                      <?php if ($ptKey === 'bond'): ?>
                        <?php foreach (['short' => 'Short (8.5×11 in)', 'long' => 'Long (8.5×13 in)'] as $sizeKey => $sizeLabel): ?>
                          <div class="dp-size-header"><?= $sizeLabel ?></div>
                          <?php
                          $ctLabels = ['text_only' => 'Text Only', 'image_text' => 'Image with Text', 'image_only' => 'Image Only'];
                          foreach ($ctLabels as $ctKey => $ctLabel):
                            // Find DB row
                            $dbRow = null;
                            foreach ($digital_rows as $dr) {
                              if ($dr['paper_type'] === $ptKey && $dr['color_mode'] === $cmKey && $dr['size_label'] === $sizeKey && $dr['content_type'] === $ctKey) {
                                $dbRow = $dr;
                                break;
                              }
                            }
                            $price = $dbRow ? $dbRow['price_per_paper'] : 0;
                            $rowId = $dbRow ? $dbRow['id'] : 0;
                          ?>
                            <div class="dp-row">
                              <div class="dp-row-label"><?= $ctLabel ?></div>
                              <?php if ($rowId): ?>
                                <div class="dp-price-wrapper">
                                  <span class="dp-peso">₱</span>
                                  <input type="number" step="0.01" min="0"
                                    name="digital[<?= $rowId ?>][price]"
                                    value="<?= number_format($price, 2, '.', '') ?>"
                                    class="dp-price-input" required>
                                </div>
                              <?php else: ?>
                                <span style="font-size:11px;color:var(--text-muted)">N/A</span>
                              <?php endif; ?>
                            </div>
                          <?php endforeach; ?>
                        <?php endforeach; ?>

                      <?php else: ?>
                        <?php
                        $rows = $digital_indexed[$ptKey][$cmKey] ?? [];
                        foreach ($rows as $dr):
                        ?>
                          <div class="dp-row">
                            <div class="dp-row-label"><?= htmlspecialchars($dr['size_label']) ?></div>
                            <div class="dp-price-wrapper">
                              <span class="dp-peso">₱</span>
                              <input type="number" step="0.01" min="0"
                                name="digital[<?= $dr['id'] ?>][price]"
                                value="<?= number_format($dr['price_per_paper'], 2, '.', '') ?>"
                                class="dp-price-input" required>
                            </div>
                          </div>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?>
                          <div style="padding:14px;font-size:12px;color:var(--text-muted);text-align:center">No entries found</div>
                        <?php endif; ?>
                      <?php endif; ?>

                    </div><!-- /cm-panel -->
                  <?php endforeach; ?>
                </div><!-- /color-mode-grid -->
              </div>
            </div>
          </div><!-- /dp-content -->
        <?php endforeach; ?>

      </div><!-- /tab-digital-prices -->


      <!-- ── RISO PRINTING PRICES ── -->
      <div class="tab-content-pane <?= $active === 'riso-prices' ? 'active' : '' ?>" id="tab-riso-prices">

        <div style="background:linear-gradient(135deg,#e67e22 0%,#c0392b 100%);border-radius:14px;padding:18px 22px;margin-bottom:20px;color:#fff;display:flex;align-items:center;gap:14px">
          <i class="bi bi-printer" style="font-size:28px;opacity:0.8"></i>
          <div>
            <div style="font-weight:700;font-size:1rem">Riso Printing Price Matrix</div>
            <div style="font-size:12px;opacity:0.8;margin-top:2px">Prices are per ream (500 sheets). Back-to-back adds a flat &#8369;200 surcharge to the total.</div>
          </div>
        </div>

        <div class="price-card">
          <div class="price-card-header">
            <h6>
              <div class="card-icon" style="background:#fff3e0;color:#e67e22"><i class="bi bi-printer"></i></div>Riso Paper Prices (per Ream)
            </h6>
            <span style="font-size:11.5px;color:var(--text-muted)"><?= count($riso_prices_list) ?> entries</span>
          </div>
          <div style="overflow-x:auto">
            <table class="price-table">
              <thead>
                <tr>
                  <th style="background:#e67e22">Paper Name</th>
                  <th style="background:#e67e22">Size</th>
                  <th style="background:#e67e22">Price / Ream (&#8369;)</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $sizeLabels = ['short' => 'Short (8.5x11)', 'long' => 'Long (8.5x13)', 'a4' => 'A4 (8.5x11)'];
                $lastPaper  = null;
                foreach ($riso_prices_list as $row):
                  $isNewPaper = $row['paper_name'] !== $lastPaper;
                  $lastPaper  = $row['paper_name'];
                ?>
                  <?php if ($isNewPaper): ?>
                    <tr>
                      <td colspan="3" style="background:#fff8f0;font-weight:700;font-size:12px;color:#92400e;padding:8px 16px;border-bottom:1px solid #f0d9c0">
                        <i class="bi bi-file-earmark me-1"></i><?= htmlspecialchars($row['paper_name']) ?>
                      </td>
                    </tr>
                  <?php endif; ?>
                  <tr>
                    <td style="padding-left:28px;color:var(--text-muted);font-size:12.5px">
                      <?= htmlspecialchars($row['paper_name']) ?>
                    </td>
                    <td>
                      <span style="background:#fff3e0;color:#e67e22;padding:2px 9px;border-radius:20px;font-size:11.5px;font-weight:600">
                        <?= htmlspecialchars($sizeLabels[$row['size_label']] ?? strtoupper($row['size_label'])) ?>
                      </span>
                    </td>
                    <td>
                      <input type="number" step="0.01" min="0"
                        name="riso[<?= $row['id'] ?>][price]"
                        value="<?= htmlspecialchars($row['price_per_ream']) ?>"
                        class="form-control" required>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div><!-- /tab-riso-prices -->

    </form><!-- /masterForm -->

  </div><!-- /container -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/pages/manage_prices.js"></script>
</body>

</html>