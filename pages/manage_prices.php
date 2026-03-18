<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// $job_id is only used for the Back button — not required to load the page
$job_id = intval($_GET['id'] ?? 0);

// ── POST handler (PRG pattern) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['manpower'])) {
        $stmt = $inventory->prepare("UPDATE manpower_rates SET daily_rate=?, hourly_rate=? WHERE id=?");
        foreach ($_POST['manpower'] as $row) {
            $daily  = max(0, floatval($row['daily_rate']));
            $hourly = max(0, floatval($row['hourly_rate']));
            $id     = intval($row['id']);
            $stmt->bind_param("ddi", $daily, $hourly, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    if (isset($_POST['paper'])) {
        $stmt = $inventory->prepare("UPDATE paper_prices
            SET paper_type=?, orig_price=?, disc_price=?, short_price=?, long_price=?,
                price_per_sheet=?, cutting_cost=?, effective_date=?
            WHERE id=?");
        foreach ($_POST['paper'] as $row) {
            $paper_type      = $row['paper_type'];
            $orig_price      = max(0, floatval($row['orig_price']));
            $disc_price      = max(0, floatval($row['disc_price']));
            $short_price     = max(0, floatval($row['short_price']));
            $long_price      = max(0, floatval($row['long_price']));
            $price_per_sheet = !empty($row['price_per_sheet']) ? max(0, floatval($row['price_per_sheet'])) : null;
            $cutting_cost    = max(0, floatval($row['cutting_cost']));
            $effective_date  = $row['effective_date'];
            $id              = intval($row['id']);
            $stmt->bind_param("sddddddsi",
                $paper_type, $orig_price, $disc_price, $short_price, $long_price,
                $price_per_sheet, $cutting_cost, $effective_date, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    if (isset($_POST['cut'])) {
        $stmt = $inventory->prepare("UPDATE paper_cut_prices
            SET paper_type=?, short_price=?, long_price=?, price_per_sheet=?,
                cutting_cost=?, effective_date=?
            WHERE id=?");
        foreach ($_POST['cut'] as $row) {
            $paper_type      = $row['paper_type'];
            $short_price     = max(0, floatval($row['short_price']));
            $long_price      = max(0, floatval($row['long_price']));
            $price_per_sheet = !empty($row['price_per_sheet']) ? max(0, floatval($row['price_per_sheet'])) : null;
            $cutting_cost    = max(0, floatval($row['cutting_cost']));
            $effective_date  = $row['effective_date'];
            $id              = intval($row['id']);
            $stmt->bind_param("sddddsi",
                $paper_type, $short_price, $long_price,
                $price_per_sheet, $cutting_cost, $effective_date, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    if (isset($_POST['special'])) {
        $stmt = $inventory->prepare("UPDATE products SET unit_price=? WHERE id=?");
        foreach ($_POST['special'] as $row) {
            $unit_price = max(0, floatval($row['unit_price']));
            $id         = intval($row['id']);
            $stmt->bind_param("di", $unit_price, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    if (isset($_POST['ordinary'])) {
        $stmt = $inventory->prepare("UPDATE products SET unit_price=? WHERE id=?");
        foreach ($_POST['ordinary'] as $row) {
            $unit_price = max(0, floatval($row['unit_price']));
            $id         = intval($row['id']);
            $stmt->bind_param("di", $unit_price, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    if (isset($_POST['printing'])) {
        $stmt = $inventory->prepare("UPDATE printing_types
            SET base_cost=?, per_sheet_cost=?, apply_to_paper_cost=?, effective_date=?
            WHERE id=?");
        foreach ($_POST['printing'] as $row) {
            $base_cost           = max(0, floatval($row['base_cost']));
            $per_sheet_cost      = max(0, floatval($row['per_sheet_cost']));
            $apply_to_paper_cost = isset($row['apply_to_paper_cost']) ? 1 : 0;
            $effective_date      = $row['effective_date'];
            $id                  = intval($row['id']);
            $stmt->bind_param("dddsi",
                $base_cost, $per_sheet_cost, $apply_to_paper_cost, $effective_date, $id);
            $stmt->execute();
        }
        $stmt->close();
    }

    // PRG redirect — preserve job_id and active tab
    $tab = $_POST['active_tab'] ?? 'manpower-rates';
    $redirect = "manage_prices.php?saved=1&tab=" . urlencode($tab);
    if ($job_id) $redirect .= "&id=" . $job_id;
    header("Location: $redirect");
    exit;
}

// ── Fetch data ──────────────────────────────────────────────────────
$manpower_rates = $inventory->query("SELECT * FROM manpower_rates ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$paper_prices   = $inventory->query("SELECT * FROM paper_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$cut_prices     = $inventory->query("SELECT * FROM paper_cut_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$printing_types = $inventory->query("SELECT * FROM printing_types ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$special_prices = $inventory->query("
    SELECT id, product_name, product_group, unit_price
    FROM products
    WHERE LOWER(product_type) = 'special paper'
    ORDER BY product_group, product_name
")->fetch_all(MYSQLI_ASSOC);

$ordinary_prices = $inventory->query("
    SELECT id, product_name, product_group, unit_price
    FROM products
    WHERE LOWER(product_type) = 'ordinary paper'
    ORDER BY product_group, product_name
")->fetch_all(MYSQLI_ASSOC);

$saved   = isset($_GET['saved']);
$active  = $_GET['tab'] ?? 'manpower-rates';
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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #1877f2;
      --secondary: #166fe5;
    }
    body { background: #f8f9fa; font-family: 'Poppins', sans-serif; font-size: 15px; padding: 20px 0 40px; }
    .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,.1); margin-bottom: 20px; border: none; }
    .card-header { background: #fff; color: #000; border-radius: 10px 10px 0 0 !important; font-size: 120%; font-weight: 600; padding: 15px 30px; }
    .table th { background: var(--primary); color: white; }
    .nav-tabs .nav-link.active { font-weight: 600; color: var(--primary); border-bottom: 3px solid var(--secondary); }
    .price-table { font-size: 14px; }
    .price-table th, .price-table td { padding: 12px; text-align: center; vertical-align: middle; }
    .btn1 {
      padding: .5rem 1rem; border-radius: 6px; font-size: .85rem; cursor: pointer;
      background: rgba(40,167,69,.1); color: #28a745; border: 1px solid #28a745;
      display: inline-flex; align-items: center; gap: 5px; transition: all .2s;
    }
    .btn1:hover { background: rgba(40,167,69,.2); }
    .back-button { margin-bottom: 20px; }
    .btn-outline-primary { color: var(--secondary); border-color: var(--secondary); }
    .btn-outline-primary:hover { background: #1670e528; border-color: var(--secondary); color: var(--secondary); }
  </style>
</head>
<body>
<div class="container">

  <?php if ($job_id): ?>
  <div class="back-button">
    <a href="paper_cost.php?id=<?= $job_id ?>" class="btn btn-outline-primary">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>
  <?php endif; ?>

  <div class="row mb-4">
    <div class="col">
      <h1 class="display-5 fw-bold text-primary">Manage Price Lists</h1>
      <p class="lead">Update and maintain pricing information</p>
    </div>
  </div>

  <?php if ($saved): ?>
    <div class="alert alert-success alert-dismissible fade show" id="autoDismissAlert" role="alert">
      <i class="bi bi-check-circle"></i>&nbsp; Price list updated successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <script>
      setTimeout(() => {
        const a = document.getElementById('autoDismissAlert');
        if (a) bootstrap.Alert.getOrCreateInstance(a).close();
      }, 3000);
    </script>
  <?php endif; ?>

  <ul class="nav nav-tabs mb-4" id="priceTabs" role="tablist">
    <?php
    $tabs = [
      'manpower-rates' => 'Manpower Rates',
      'paper-prices'   => 'Carbonless Paper',
      'cut-prices'     => 'Ordinary Paper',
      'special-prices' => 'Special Paper',
      'printing-types' => 'Printing Types',
    ];
    foreach ($tabs as $id => $label):
      $isActive = ($active === $id);
    ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link <?= $isActive ? 'active' : '' ?>"
              data-bs-toggle="tab" data-bs-target="#<?= $id ?>"
              type="button" role="tab">
        <?= $label ?>
      </button>
    </li>
    <?php endforeach; ?>
  </ul>

  <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
    <button type="submit" form="masterForm" class="btn1" style="padding:.6rem 1.4rem; font-size:.95rem;">
      <i class="bi bi-check-circle-fill"></i>&nbsp; Save All
    </button>
  </div>

  <form id="masterForm" method="post">
<input type="hidden" name="active_tab" id="activeTabInput" value="manpower-rates">
<div class="tab-content">

    <!-- ── Manpower Rates ── -->
    <div class="tab-pane fade <?= $active === 'manpower-rates' ? 'show active' : '' ?>" id="manpower-rates" role="tabpanel">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Manpower Rates</span>
        </div>
        <div class="card-body p-0">
            <?php if ($job_id): ?><input type="hidden" name="job_id_ref" value="<?= $job_id ?>"><?php endif; ?>
            <div class="table-responsive">
              <table class="table table-striped table-hover price-table mb-0">
                <thead><tr><th>Task Name</th><th>Daily Rate (₱)</th><th>Hourly Rate (₱)</th></tr></thead>
                <tbody>
                  <?php foreach ($manpower_rates as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['task_name']) ?>
                        <input type="hidden" name="manpower[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <td><input type="number" step="0.01" min="0" name="manpower[<?= $row['id'] ?>][daily_rate]" value="<?= $row['daily_rate'] ?>" class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.01" min="0" name="manpower[<?= $row['id'] ?>][hourly_rate]" value="<?= $row['hourly_rate'] ?>" class="form-control form-control-sm" required></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div></div>
      </div>
    </div>

    <!-- ── Carbonless Paper ── -->
    <div class="tab-pane fade <?= $active === 'paper-prices' ? 'show active' : '' ?>" id="paper-prices" role="tabpanel">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Carbonless Paper Prices</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover price-table mb-0">
                <thead><tr>
                  <th>Paper Type</th><th>Original Price</th><th>Discounted Price</th>
                  <th>Short Price</th><th>Long Price</th><th>Price per Piece (₱)</th>
                  <th>Cutting Cost</th><th>Effective Date</th>
                </tr></thead>
                <tbody>
                  <?php foreach ($paper_prices as $row): ?>
                  <tr>
                    <td style="min-width:150px">
                      <?= htmlspecialchars($row['paper_type']) ?>
                      <input type="hidden" name="paper[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                      <input type="hidden" name="paper[<?= $row['id'] ?>][paper_type]" value="<?= htmlspecialchars($row['paper_type']) ?>">
                    </td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][orig_price]"  value="<?= $row['orig_price'] ?>"  class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][disc_price]"  value="<?= $row['disc_price'] ?>"  class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][short_price]" value="<?= $row['short_price'] ?>" class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][long_price]"  value="<?= $row['long_price'] ?>"  class="form-control form-control-sm" required></td>
                    <td>
                      <input type="number" step="0.0001" min="0" name="paper[<?= $row['id'] ?>][price_per_sheet]"
                             value="<?= htmlspecialchars($row['price_per_sheet'] ?? '') ?>" class="form-control form-control-sm" placeholder="0.0000">
                      <?php if (($row['price_per_sheet'] ?? 0) <= 0): ?>
                        <small class="text-muted">Auto: ₱<?= number_format($row['disc_price'] / 500, 4) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><input type="number" step="0.01" min="0" name="paper[<?= $row['id'] ?>][cutting_cost]"  value="<?= $row['cutting_cost'] ?>"  class="form-control form-control-sm" required></td>
                    <td><input type="date" name="paper[<?= $row['id'] ?>][effective_date]" value="<?= $row['effective_date'] ?>" class="form-control form-control-sm" required></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div></div>
      </div>
    </div>

    <!-- ── Ordinary Paper ── -->
    <div class="tab-pane fade <?= $active === 'cut-prices' ? 'show active' : '' ?>" id="cut-prices" role="tabpanel">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Ordinary Paper Prices</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover price-table mb-0">
                <thead><tr>
                  <th>Paper Name</th><th>Size</th><th>Price / Ream (₱)</th>
                </tr></thead>
                <tbody>
                  <?php foreach ($ordinary_prices as $row): ?>
                  <tr>
                    <td style="min-width:220px; text-align:left">
                      <?= htmlspecialchars($row['product_name']) ?>
                      <input type="hidden" name="ordinary[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <td><?= htmlspecialchars($row['product_group']) ?></td>
                    <td><input type="number" step="0.01" min="0" name="ordinary[<?= $row['id'] ?>][unit_price]"
                               value="<?= htmlspecialchars($row['unit_price']) ?>" class="form-control form-control-sm" required></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div></div>
      </div>
    </div>

    <!-- ── Special Paper ── -->
    <div class="tab-pane fade <?= $active === 'special-prices' ? 'show active' : '' ?>" id="special-prices" role="tabpanel">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Special Paper Prices</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover price-table mb-0">
                <thead><tr>
                  <th>Paper Name</th><th>Size</th><th>Price / Sheet (₱)</th>
                </tr></thead>
                <tbody>
                  <?php foreach ($special_prices as $row): ?>
                  <tr>
                    <td style="min-width:220px; text-align:left">
                      <?= htmlspecialchars($row['product_name']) ?>
                      <input type="hidden" name="special[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <td><?= htmlspecialchars($row['product_group']) ?></td>
                    <td><input type="number" step="0.0001" min="0" name="special[<?= $row['id'] ?>][unit_price]"
                               value="<?= htmlspecialchars($row['unit_price']) ?>" class="form-control form-control-sm" required></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div></div>
      </div>
    </div>

    <!-- ── Printing Types ── -->
    <div class="tab-pane fade <?= $active === 'printing-types' ? 'show active' : '' ?>" id="printing-types" role="tabpanel">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Printing Types</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover price-table mb-0">
                <thead><tr>
                  <th>Name</th><th>Base Cost (₱)</th><th>Per Sheet Cost (₱)</th>
                  <th>Apply to Paper Cost?</th><th>Effective Date</th>
                </tr></thead>
                <tbody>
                  <?php foreach ($printing_types as $row): ?>
                  <tr>
                    <td>
                      <?= htmlspecialchars($row['name']) ?>
                      <input type="hidden" name="printing[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                    </td>
                    <td><input type="number" step="0.01" min="0" name="printing[<?= $row['id'] ?>][base_cost]"      value="<?= $row['base_cost'] ?>"      class="form-control form-control-sm" required></td>
                    <td><input type="number" step="0.0001" min="0" name="printing[<?= $row['id'] ?>][per_sheet_cost]" value="<?= $row['per_sheet_cost'] ?>" class="form-control form-control-sm" required></td>
                    <td>
                      <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox"
                               name="printing[<?= $row['id'] ?>][apply_to_paper_cost]"
                               value="1" <?= $row['apply_to_paper_cost'] ? 'checked' : '' ?>>
                      </div>
                    </td>
                    <td><input type="date" name="printing[<?= $row['id'] ?>][effective_date]" value="<?= $row['effective_date'] ?>" class="form-control form-control-sm" required></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div></div>
      </div>
    </div>

  </div><!-- /tab-content -->
</form><!-- /masterForm -->
</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Keep track of active tab so POST redirect returns to the same tab
  document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => {
    btn.addEventListener('shown.bs.tab', e => {
      const tabId = e.target.getAttribute('data-bs-target').replace('#', '');
      document.querySelectorAll('input[name="active_tab"]').forEach(inp => inp.value = tabId);
    });
  });
</script>
</body>
</html>