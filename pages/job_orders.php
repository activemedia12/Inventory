<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Handle search
$search_client = $_GET['client_name'] ?? '';
$search_date = $_GET['log_date'] ?? '';

$query = "SELECT * FROM job_orders WHERE 1=1";
$params = [];
$types = "";

if ($search_client !== '') {
    $query .= " AND client_name LIKE ?";
    $params[] = '%' . $search_client . '%';
    $types .= "s";
}

if ($search_date !== '') {
    $query .= " AND log_date = ?";
    $params[] = $search_date;
    $types .= "s";
}

$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Job Orders</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="job-orders-container">
  <h2 class="job-orders-title">Job Orders</h2>

  <form method="get" class="filter-form">
    <div class="form-group">
      <label for="client_name">Search by Client:</label>
      <input type="text" name="client_name" id="client_name" value="<?php echo htmlspecialchars($search_client); ?>">
    </div>

    <div class="form-group">
      <label for="log_date">Search by Date:</label>
      <input type="date" name="log_date" id="log_date" value="<?php echo htmlspecialchars($search_date); ?>">
    </div>

    <div class="form-group">
      <button type="submit">Filter</button>
      <a href="job_orders.php">Reset</a>
    </div>
  </form>

  <table class="job-orders-table" border="1" cellpadding="5" cellspacing="0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Client</th>
        <th>Quantity</th>
        <th>Paper Size</th>
        <th>Paper Type</th>
        <th>Copies/Set</th>
        <th>Binding</th>
        <th>Color Sequence</th>
        <th>Instructions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($row['log_date']); ?></td>
            <td>
              <?php echo htmlspecialchars($row['client_name']); ?><br>
              <small><?php echo htmlspecialchars($row['contact_person']); ?>, <?php echo htmlspecialchars($row['contact_number']); ?></small>
            </td>
            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
            <td>
              <?php echo $row['paper_size'] === 'custom' ? htmlspecialchars($row['custom_paper_size']) : htmlspecialchars($row['paper_size']); ?>
            </td>
            <td><?php echo htmlspecialchars($row['paper_type']); ?></td>
            <td><?php echo htmlspecialchars($row['copies_per_set']); ?></td>
            <td>
              <?php echo $row['binding_type'] === 'Custom' ? htmlspecialchars($row['custom_binding']) : htmlspecialchars($row['binding_type']); ?>
            </td>
            <td><?php echo htmlspecialchars($row['paper_sequence']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($row['special_instructions'])); ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="9">No job orders found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <p><a href="dashboard.php">← Back to Dashboard</a></p>
</div>

</body>
</html>
