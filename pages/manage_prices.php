<?php
require_once '../config/db.php';

$job_id = $_GET['id'] ?? 0;
if (!$job_id) {
  die("No job order ID provided.");
}

$message = '';

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Update manpower
  if (isset($_POST['manpower'])) {
    foreach ($_POST['manpower'] as $row) {
      $id = $row['id'];
      $daily_rate = $row['daily_rate'];
      $hourly_rate = $row['hourly_rate'];

      $stmt = $inventory->prepare("UPDATE manpower_rates SET daily_rate=?, hourly_rate=? WHERE id=?");
      $stmt->bind_param("ddi", $daily_rate, $hourly_rate, $id);
      $stmt->execute();
    }
    $message = "You have successfully updated the pricelists.";
  }

  // Update paper prices
  if (isset($_POST['paper'])) {
    foreach ($_POST['paper'] as $row) {
      $id = $row['id'];
      $paper_type = $row['paper_type'];
      $orig_price = $row['orig_price'];
      $disc_price = $row['disc_price'];
      $short_price = $row['short_price'];
      $long_price = $row['long_price'];
      $cutting_cost = $row['cutting_cost'];
      $effective_date = $row['effective_date'];

      $stmt = $inventory->prepare("UPDATE paper_prices 
                SET paper_type=?, orig_price=?, disc_price=?, short_price=?, long_price=?, cutting_cost=?, effective_date=? 
                WHERE id=?");
      $stmt->bind_param("sdddddsi", $paper_type, $orig_price, $disc_price, $short_price, $long_price, $cutting_cost, $effective_date, $id);
      $stmt->execute();
    }
    $message = "You have successfully updated the pricelists.";
  }

  // Update cut paper prices
  if (isset($_POST['cut'])) {
    foreach ($_POST['cut'] as $row) {
      $id = $row['id'];
      $paper_type = $row['paper_type'];
      $short_price = $row['short_price'];
      $long_price = $row['long_price'];
      $cutting_cost = $row['cutting_cost'];
      $effective_date = $row['effective_date'];

      $stmt = $inventory->prepare("UPDATE paper_cut_prices 
                SET paper_type=?, short_price=?, long_price=?, cutting_cost=?, effective_date=? 
                WHERE id=?");
      $stmt->bind_param("sdddsi", $paper_type, $short_price, $long_price, $cutting_cost, $effective_date, $id);
      $stmt->execute();
    }
    $message = "You have successfully updated the pricelists.";
  }

  // Update printing types
  if (isset($_POST['printing'])) {
    foreach ($_POST['printing'] as $row) {
      $id = $row['id'];
      $base_cost = $row['base_cost'];
      $per_sheet_cost = $row['per_sheet_cost'];
      $apply_to_paper_cost = isset($row['apply_to_paper_cost']) ? 1 : 0;
      $effective_date = $row['effective_date'];

      $stmt = $inventory->prepare("UPDATE printing_types 
                SET base_cost=?, per_sheet_cost=?, apply_to_paper_cost=?, effective_date=? 
                WHERE id=?");
      $stmt->bind_param("dddsi", $base_cost, $per_sheet_cost, $apply_to_paper_cost, $effective_date, $id);
      $stmt->execute();
    }
    $message = "You have successfully updated the pricelists.";
  }
}

// Fetch all records
$manpower_rates = $inventory->query("SELECT * FROM manpower_rates")->fetch_all(MYSQLI_ASSOC);
$paper_prices = $inventory->query("SELECT * FROM paper_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$cut_prices = $inventory->query("SELECT * FROM paper_cut_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$printing_types = $inventory->query("SELECT * FROM printing_types ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #1877f2;
      --primary-light: #eef2ff;
      --secondary: #166fe5;
      --accent: #e74c3c;
      --light: #ecf0f1;
      --dark: #2c3e50;
    }

    body {
      background-color: #f8f9fa;
      font-family: 'Poppins', sans-serif;
      font-size: 15px;
      padding-top: 20px;
      padding-bottom: 40px;
    }

    .card {
      border-radius: 10px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
      border: none;
    }

    .card-header {
      background-color: #ffffffff;
      color: Black;
      border-radius: 10px 10px 0 0 !important;
      font-size: 120%;
      font-weight: 600;
      padding: 15px 30px;
    }

    .btn-primary {
      background-color: var(--secondary);
      border-color: var(--secondary);
    }

    .btn-primary:hover {
      background-color: #166fe5;
      border-color: #166fe5;
    }

    .btn-outline-primary {
      color: var(--secondary);
      border-color: var(--secondary);
    }

    .btn-outline-primary:hover {
      background-color: #1670e528;
      border-color: var(--secondary);
      color: var(--secondary);
    }

    .table th {
      background-color: var(--primary);
      color: white;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: none;
      border-radius: 8px;
    }

    .form-label {
      font-weight: 500;
    }

    .nav-tabs .nav-link.active {
      font-weight: 600;
      color: var(--primary);
      border-bottom: 3px solid var(--secondary);
    }

    .price-table {
      font-size: 14px;
    }

    .price-table th,
    .price-table td {
      padding: 12px;
      text-align: center;
      vertical-align: middle;
    }

    .section-title {
      border-left: 4px solid var(--primary);
      padding-left: 15px;
      margin: 30px 0 20px;
      font-weight: 600;
    }

    .update-form {
      background-color: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 15px;
      border-left: 4px solid var(--secondary);
    }

    .form-control-sm {
      padding: 5px 10px;
      font-size: 14px;
    }

    .back-button {
      margin-bottom: 20px;
    }

    .btn1 {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      background: rgba(67, 238, 76, 0.1);
      color: #28a745;
      border: 1px solid #28a745;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
      gap: 5px;
    }

    .btn1:hover {
      background: rgba(40, 167, 69, 0.2);
    }

    .form-check-input {
      margin-top: 0;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="back-button">
      <button type="button" class="btn btn-outline-primary" onclick="window.location.href='paper_cost.php?id=<?= $job_id ?>'">
        <i class="bi bi-arrow-left"></i> Back
      </button>
    </div>

    <div class="row mb-4">
      <div class="col">
        <h1 class="display-5 fw-bold text-primary">Manage Price Lists</h1>
        <p class="lead">Update and maintain pricing information</p>
      </div>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert" id="autoDismissAlert">
        <i class="bi bi-check-circle"></i> &nbsp;<?= htmlspecialchars($message) ?>
      </div>

      <script>
        setTimeout(() => {
          const alert = document.getElementById('autoDismissAlert');
          if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
          }
        }, 3000);
      </script>
    <?php endif; ?>

    <div class="row">
      <div class="col-md-12">
        <ul class="nav nav-tabs mb-4" id="priceTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="manpower-tab" data-bs-toggle="tab" data-bs-target="#manpower-rates" type="button" role="tab">Manpower Rates</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="paper-tab" data-bs-toggle="tab" data-bs-target="#paper-prices" type="button" role="tab">Carbonless Paper Prices</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="cut-tab" data-bs-toggle="tab" data-bs-target="#cut-prices" type="button" role="tab">Ordinary Paper Prices</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="printing-tab" data-bs-toggle="tab" data-bs-target="#printing-types" type="button" role="tab">Printing Types</button>
          </li>
        </ul>

        <div class="tab-content" id="priceTabContent">
          <!-- Manpower Rates Tab -->
          <div class="tab-pane fade show active" id="manpower-rates" role="tabpanel">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span>Manpower Rates</span>
                <button type="button" class="btn1" onclick="submitAllForms()">
                  <i class="bi bi-check-circle"></i> Update
                </button>
              </div>
              <div class="card-body p-0">
                <form id="manpowerForm" method="post">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover price-table mb-0">
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
                            <td><?= htmlspecialchars($row['task_name']) ?>
                              <input type="hidden" name="manpower[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                              <input type="hidden" name="manpower[<?= $row['id'] ?>][price_type]" value="manpower">
                            </td>
                            <td>
                              <input type="number" step="0.01" name="manpower[<?= $row['id'] ?>][daily_rate]" value="<?= $row['daily_rate'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="manpower[<?= $row['id'] ?>][hourly_rate]" value="<?= $row['hourly_rate'] ?>" class="form-control form-control-sm" required>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Paper Prices Tab -->
          <div class="tab-pane fade" id="paper-prices" role="tabpanel">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span>Carbonless Paper Prices</span>
                <button type="button" class="btn1" onclick="submitAllForms()">
                  <i class="bi bi-check-circle"></i> Update
                </button>
              </div>
              <div class="card-body p-0">
                <form id="paperForm" method="post">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover price-table mb-0">
                      <thead>
                        <tr>
                          <th>Paper Type</th>
                          <th>Original Price</th>
                          <th>Discounted Price</th>
                          <th>Short Price</th>
                          <th>Long Price</th>
                          <th>Cutting Cost</th>
                          <th>Effective Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($paper_prices as $row): ?>
                          <tr>
                            <td style="min-width: 150px;">
                              <?= htmlspecialchars($row['paper_type']) ?>
                              <input type="hidden" name="paper[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                              <input type="hidden" name="paper[<?= $row['id'] ?>][price_type]" value="paper">
                              <input type="hidden" name="paper[<?= $row['id'] ?>][paper_type]" value="<?= $row['paper_type'] ?>">
                            </td>
                            <td>
                              <input type="number" step="0.01" name="paper[<?= $row['id'] ?>][orig_price]" value="<?= $row['orig_price'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="paper[<?= $row['id'] ?>][disc_price]" value="<?= $row['disc_price'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="paper[<?= $row['id'] ?>][short_price]" value="<?= $row['short_price'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="paper[<?= $row['id'] ?>][long_price]" value="<?= $row['long_price'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="paper[<?= $row['id'] ?>][cutting_cost]" value="<?= $row['cutting_cost'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="date" name="paper[<?= $row['id'] ?>][effective_date]" value="<?= $row['effective_date'] ?>" class="form-control form-control-sm" required>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Cut Prices Tab -->
          <div class="tab-pane fade" id="cut-prices" role="tabpanel">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span>Ordinary Paper Prices</span>
                <button type="button" class="btn1" onclick="submitAllForms()">
                  <i class="bi bi-check-circle"></i> Update
                </button>
              </div>
              <div class="card-body p-0">
                <form id="cutForm" method="post">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover price-table mb-0">
                      <thead>
                        <tr>
                          <th>Paper Type</th>
                          <th>Short Price</th>
                          <th>Long Price</th>
                          <th>Cutting Cost</th>
                          <th>Effective Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($cut_prices as $row): ?>
                          <tr>
                            <td>
                              <?= htmlspecialchars($row['paper_type']) ?>
                              <input type="hidden" name="cut[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                              <input type="hidden" name="cut[<?= $row['id'] ?>][price_type]" value="cut">
                              <input type="hidden" name="cut[<?= $row['id'] ?>][paper_type]" value="<?= $row['paper_type'] ?>">
                            </td>
                            <td>
                              <input type="number" step="0.01" name="cut[<?= $row['id'] ?>][short_price]" value="<?= $row['short_price'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="cut[<?= $row['id'] ?>][long_price]" value="<?= $row['long_price'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.01" name="cut[<?= $row['id'] ?>][cutting_cost]" value="<?= $row['cutting_cost'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="date" name="cut[<?= $row['id'] ?>][effective_date]" value="<?= $row['effective_date'] ?>" class="form-control form-control-sm" required>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Printing Types Tab -->
          <div class="tab-pane fade" id="printing-types" role="tabpanel">
            <div class="card">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span>Printing Types</span>
                <button type="button" class="btn1" onclick="submitAllForms()">
                  <i class="bi bi-check-circle"></i> Update
                </button>
              </div>
              <div class="card-body p-0">
                <form id="printingForm" method="post">
                  <div class="table-responsive">
                    <table class="table table-striped table-hover price-table mb-0">
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Base Cost (₱)</th>
                          <th>Per Sheet Cost (₱)</th>
                          <th>Effective Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($printing_types as $row): ?>
                          <tr>
                            <td>
                              <?= htmlspecialchars($row['name']) ?>
                              <input type="hidden" name="printing[<?= $row['id'] ?>][id]" value="<?= $row['id'] ?>">
                              <input type="hidden" name="printing[<?= $row['id'] ?>][price_type]" value="printing">
                            </td>
                            <td>
                              <input type="number" step="0.01" name="printing[<?= $row['id'] ?>][base_cost]" value="<?= $row['base_cost'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="number" step="0.0001" name="printing[<?= $row['id'] ?>][per_sheet_cost]" value="<?= $row['per_sheet_cost'] ?>" class="form-control form-control-sm" required>
                            </td>
                            <td>
                              <input type="date" name="printing[<?= $row['id'] ?>][effective_date]" value="<?= $row['effective_date'] ?>" class="form-control form-control-sm" required>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function submitAllForms() {
      const manpower = document.getElementById('manpowerForm');
      const paper = document.getElementById('paperForm');
      const cut = document.getElementById('cutForm');
      const printing = document.getElementById('printingForm');

      // Create a hidden form to merge all data
      let masterForm = document.createElement("form");
      masterForm.method = "post";
      masterForm.style.display = "none";

      [manpower, paper, cut, printing].forEach(f => {
        new FormData(f).forEach((value, key) => {
          let input = document.createElement("input");
          input.type = "hidden";
          input.name = key;
          input.value = value;
          masterForm.appendChild(input);
        });
      });

      document.body.appendChild(masterForm);
      masterForm.submit();
    }
  </script>
</body>

</html>