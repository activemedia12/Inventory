<?php
require_once '../config/db.php';

// fetch manpower rates from DB
$rates = [];
$res = $mysqli->query("SELECT task_name, hourly_rate FROM manpower_rates");
while ($row = $res->fetch_assoc()) {
    $rates[$row['task_name']] = $row['hourly_rate'];
}
$tasks = array_keys($rates);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Job Order Manpower Calculator</title>
<style>
  body{font-family: Arial, sans-serif; padding:20px; max-width:1000px}
  table{border-collapse:collapse; width:100%; margin-top:20px}
  th,td{border:1px solid #ccc; padding:6px; text-align:center}
  th{background:#f4f4f4}
  .total{font-weight:700; background:#fafafa}
</style>
<script>
const rates = <?= json_encode($rates) ?>;

function calculate() {
  let grandTotal = 0;
  let tbody = document.getElementById("results");
  tbody.innerHTML = "";

  Object.keys(rates).forEach(task => {
    let sessions = document.querySelectorAll(`#${task}-sessions .session`);
    let totalHours = 0;
    let totalCost = 0;

    sessions.forEach(s => {
      let start = s.querySelector("[name*='[start]']").value;
      let end   = s.querySelector("[name*='[end]']").value;
      let brk   = parseInt(s.querySelector("[name*='[break]']").value) || 0;

      if (start && end) {
        let startTime = new Date("1970-01-01T" + start + ":00");
        let endTime   = new Date("1970-01-01T" + end + ":00");

        if (endTime > startTime) {
          let hours = (endTime - startTime) / 3600000; // ms → hours
          hours -= (brk / 60.0);
          if (hours < 0) hours = 0;

          totalHours += hours;
          totalCost  += hours * rates[task];
        }
      }
    });

    if (totalHours > 0) {
      let row = `<tr>
        <td>${task}</td>
        <td>${totalHours.toFixed(2)}</td>
        <td>${totalCost.toFixed(2)}</td>
      </tr>`;
      tbody.innerHTML += row;
      grandTotal += totalCost;
    }
  });

  if (grandTotal > 0) {
    tbody.innerHTML += `<tr class="total"><td colspan="2">Grand Total</td><td>${grandTotal.toFixed(2)}</td></tr>`;
  }

  // store in hidden input so it goes with form submission
  document.getElementById("grand_total").value = grandTotal.toFixed(2);
}

function addSession(task) {
  let container = document.getElementById(task + '-sessions');
  let idx = container.children.length;
  let row = document.createElement('div');
  row.classList.add("session");
  row.innerHTML = `
    <input type="time" name="sessions[${task}][${idx}][start]" onchange="calculate()" required>
    <input type="time" name="sessions[${task}][${idx}][end]" onchange="calculate()" required>
    Break: <input type="number" name="sessions[${task}][${idx}][break]" min="0" value="0" onchange="calculate()"> mins
    <br>`;
  container.appendChild(row);
}
</script>
</head>
<body>

<h2>Job Order Calculator</h2>
<form method="post" action="job_orders.php">

<?php foreach ($tasks as $task): ?>
  <h3><?= htmlspecialchars($task) ?> Sessions</h3>
  <div id="<?= $task ?>-sessions">
    <div class="session">
      <input type="time" name="sessions[<?= $task ?>][0][start]" onchange="calculate()" required>
      <input type="time" name="sessions[<?= $task ?>][0][end]" onchange="calculate()" required>
      Break: <input type="number" name="sessions[<?= $task ?>][0][break]" min="0" value="0" onchange="calculate()"> mins
    </div>
  </div>
  <button type="button" onclick="addSession('<?= $task ?>')">+ Add Day</button>
  <hr>
<?php endforeach; ?>

<!-- hidden field for grand total -->
<input type="hidden" name="grand_total" id="grand_total" value="0">

<h3>Cost Breakdown</h3>
<table>
  <thead><tr><th>Task</th><th>Total Hours</th><th>Total Cost</th></tr></thead>
  <tbody id="results"></tbody>
</table>

<button type="submit">Save Job Order</button>
<a href="manage_prices.php"><button type="button">Manage Price Lists</button></a>
</form>

</body>
</html>
