<?php
require_once '../config/db.php';  // adjust path

$message = '';

// --- Insert / Update Paper Prices ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['price_type']) && $_POST['price_type'] === 'paper') {
        $id = $_POST['id'] ?? '';
        $paper_type = $_POST['paper_type'];
        $orig_price = $_POST['orig_price'];
        $disc_price = $_POST['disc_price'];
        $short_price = $_POST['short_price'];
        $long_price = $_POST['long_price'];
        $cutting_cost = $_POST['cutting_cost'];
        $effective_date = $_POST['effective_date'];

        if ($id) {
            // Update
            $stmt = $mysqli->prepare("UPDATE paper_prices 
                SET paper_type=?, orig_price=?, disc_price=?, short_price=?, long_price=?, cutting_cost=?, effective_date=? 
                WHERE id=?");
            $stmt->bind_param("sddddsdi", $paper_type, $orig_price, $disc_price, $short_price, $long_price, $cutting_cost, $effective_date, $id);
            $stmt->execute();
            $message = "Paper price updated.";
        } else {
            // Insert
            $stmt = $mysqli->prepare("INSERT INTO paper_prices 
                (paper_type, orig_price, disc_price, short_price, long_price, cutting_cost, effective_date) 
                VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("sddddd", $paper_type, $orig_price, $disc_price, $short_price, $long_price, $cutting_cost, $effective_date);
            $stmt->execute();
            $message = "Paper price added.";
        }
    }

    // --- Insert / Update Cut Prices ---
    if (isset($_POST['price_type']) && $_POST['price_type'] === 'cut') {
        $id = $_POST['id'] ?? '';
        $paper_color = $_POST['paper_color'];
        $short_price = $_POST['short_price'];
        $long_price = $_POST['long_price'];
        $cutting_cost = $_POST['cutting_cost'];
        $effective_date = $_POST['effective_date'];

        if ($id) {
            $stmt = $mysqli->prepare("UPDATE paper_cut_prices 
                SET paper_color=?, short_price=?, long_price=?, cutting_cost=?, effective_date=? 
                WHERE id=?");
            $stmt->bind_param("sddssi", $paper_color, $short_price, $long_price, $cutting_cost, $effective_date, $id);
            $stmt->execute();
            $message = "Cut price updated.";
        } else {
            $stmt = $mysqli->prepare("INSERT INTO paper_cut_prices 
                (paper_color, short_price, long_price, cutting_cost, effective_date) 
                VALUES (?,?,?,?,?)");
            $stmt->bind_param("sddds", $paper_color, $short_price, $long_price, $cutting_cost, $effective_date);
            $stmt->execute();
            $message = "Cut price added.";
        }
    }
}

// Fetch all current prices
$paper_prices = $mysqli->query("SELECT * FROM paper_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
$cut_prices = $mysqli->query("SELECT * FROM paper_cut_prices ORDER BY effective_date DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Price Lists</title>
<style>
    body { font-family: Arial, sans-serif; padding:20px; }
    table { border-collapse: collapse; width:100%; margin-bottom:20px; }
    th, td { border:1px solid #ccc; padding:6px; text-align:center; }
    th { background:#f0f0f0; }
    form { margin-bottom:40px; padding:10px; border:1px solid #ccc; }
    h3 { background:#eee; padding:8px; }
</style>
</head>
<body>

<h2>Manage Price Lists</h2>
<?php if ($message): ?>
  <p style="color:green;"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<!-- Paper Prices Form -->
<h3>Add / Update Paper Prices</h3>
<form method="post">
  <input type="hidden" name="price_type" value="paper">
  <label>Paper Type:
    <select name="paper_type" required>
      <option value="TOP WHITE">TOP WHITE</option>
      <option value="MIDDLE">MIDDLE</option>
      <option value="BOTTOM">BOTTOM</option>
    </select>
  </label><br><br>
  <label>Original Price: <input type="number" step="0.01" name="orig_price" required></label><br>
  <label>Discounted Price: <input type="number" step="0.01" name="disc_price" required></label><br>
  <label>Price (10 outs): <input type="number" step="0.01" name="short_price" required></label><br>
  <label>Price (8 outs): <input type="number" step="0.01" name="long_price" required></label><br>
  <label>Cutting Cost: <input type="number" step="0.01" name="cutting_cost" required></label><br>
  <label>Effective Date: <input type="date" name="effective_date" required></label><br><br>
  <button type="submit">Save</button>
</form>

<!-- Display Paper Prices -->
<table>
  <tr><th>ID</th><th>Paper Type</th><th>Orig</th><th>Disc</th><th>10 Outs</th><th>8 Outs</th><th>Cutting</th><th>Effective</th></tr>
  <?php foreach ($paper_prices as $row): ?>
    <tr>
      <td><?= $row['id'] ?></td>
      <td><?= $row['paper_type'] ?></td>
      <td><?= $row['orig_price'] ?></td>
      <td><?= $row['disc_price'] ?></td>
      <td><?= $row['short_price'] ?></td>
      <td><?= $row['long_price'] ?></td>
      <td><?= $row['cutting_cost'] ?></td>
      <td><?= $row['effective_date'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<!-- Cut Prices Form -->
<h3>Add / Update Cut Prices</h3>
<form method="post">
  <input type="hidden" name="price_type" value="cut">
  <label>Paper Color:
    <select name="paper_color" required>
      <option value="WHITE">WHITE</option>
      <option value="COLORED">COLORED</option>
    </select>
  </label><br><br>
  <label>Short Price: <input type="number" step="0.01" name="short_price" required></label><br>
  <label>Long Price: <input type="number" step="0.01" name="long_price" required></label><br>
  <label>Cutting Cost: <input type="number" step="0.01" name="cutting_cost" required></label><br>
  <label>Effective Date: <input type="date" name="effective_date" required></label><br><br>
  <button type="submit">Save</button>
</form>

<!-- Display Cut Prices -->
<table>
  <tr><th>ID</th><th>Paper Color</th><th>Short</th><th>Long</th><th>Cutting</th><th>Effective</th></tr>
  <?php foreach ($cut_prices as $row): ?>
    <tr>
      <td><?= $row['id'] ?></td>
      <td><?= $row['paper_color'] ?></td>
      <td><?= $row['short_price'] ?></td>
      <td><?= $row['long_price'] ?></td>
      <td><?= $row['cutting_cost'] ?></td>
      <td><?= $row['effective_date'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>

</body>
</html>
