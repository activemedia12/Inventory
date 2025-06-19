<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_sizes = $mysqli->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");
$product_names = $mysqli->query("SELECT DISTINCT product_name FROM products ORDER BY product_name");

$product_name_options = [];
while ($row = $product_names->fetch_assoc()) {
    $product_name_options[] = $row['product_name'];
}

$product_query = $mysqli->query("
    SELECT p.id, p.product_type, p.product_group, p.product_name,
    COALESCE(SUM(d.delivered_reams * 500), 0) - COALESCE(SUM(u.used_sheets), 0) AS available_sheets
    FROM products p
    LEFT JOIN delivery_logs d ON p.id = d.product_id
    LEFT JOIN usage_logs u ON p.id = u.product_id
    GROUP BY p.id
    ORDER BY p.product_type, p.product_group, p.product_name
");

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Job Order Inputs
    $client_name = $_POST['client_name'];
    $client_address = $_POST['client_address'];
    $contact_person = $_POST['contact_person'];
    $contact_number = $_POST['contact_number'];
    $quantity = intval($_POST['quantity']);
    $number_of_sets = intval($_POST['number_of_sets']);
    $product_size = $_POST['product_size'];
    $paper_size = $_POST['paper_size'];
    $custom_paper_size = $_POST['custom_paper_size'] ?? '';
    $paper_type = $_POST['paper_type'];
    $copies_per_set = intval($_POST['copies_per_set']);
    $binding_type = $_POST['binding_type'];
    $custom_binding = $_POST['custom_binding'] ?? '';
    $paper_sequence = $_POST['paper_sequence'] ?? [];
    $special_instructions = $_POST['special_instructions'];
    $log_date = $_POST['log_date'] ?? date('Y-m-d');
    $created_by = $_SESSION['user_id'];

    // Cut size mapping
    $cut_size_map = [
        '1/2' => 2, '1/3' => 3, '1/4' => 4,
        '1/6' => 6, '1/8' => 8, 'whole' => 1
    ];
    $cut_size = $cut_size_map[$product_size] ?? 1;

    // Reams calculation
    $total_sheets = $number_of_sets * $quantity;
    $cut_sheets = $total_sheets / $cut_size;
    $reams = $cut_sheets / 500;
    $reams_per_product = ($copies_per_set > 0) ? $reams / $copies_per_set : $reams;

    // Store job order
    $stmt = $mysqli->prepare("INSERT INTO job_orders (
        log_date, client_name, client_address, contact_person, contact_number,
        quantity, paper_size, custom_paper_size, paper_type, copies_per_set,
        binding_type, custom_binding, paper_sequence, special_instructions, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $paper_sequence_str = implode(', ', $paper_sequence);

    if ($stmt) {
        $stmt->bind_param("sssssisssissssi",
            $log_date, $client_name, $client_address, $contact_person, $contact_number,
            $quantity, $paper_size, $custom_paper_size, $paper_type, $copies_per_set,
            $binding_type, $custom_binding, $paper_sequence_str, $special_instructions, $created_by
        );

        if ($stmt->execute()) {
            $success = true;
            foreach ($paper_sequence as $color) {
                $product = $mysqli->query("
                    SELECT p.id, 
                    COALESCE(SUM(d.delivered_reams * 500), 0) - COALESCE(SUM(u.used_sheets), 0) AS available
                    FROM products p
                    LEFT JOIN delivery_logs d ON p.id = d.product_id
                    LEFT JOIN usage_logs u ON p.id = u.product_id
                    WHERE p.product_type = '$paper_type'
                    AND p.product_group = '$paper_size'
                    AND p.product_name = '$color'
                    GROUP BY p.id
                    LIMIT 1
                ");

                if ($product && $product->num_rows > 0) {
                    $prod = $product->fetch_assoc();
                    $product_id = $prod['id'];
                    $available = floatval($prod['available']);
                    $used_sheets = $reams_per_product * 500;

                    if ($available < $used_sheets) {
                        $message .= "❌ Not enough stock for $color. Available: $available, Required: $used_sheets.<br>";
                        $success = false;
                        continue;
                    }

                    $usage_stmt = $mysqli->prepare("INSERT INTO usage_logs (product_id, used_sheets, log_date, usage_note) VALUES (?, ?, ?, ?)");
                    $note = "Auto-deducted from job order for $client_name";
                    $usage_stmt->bind_param("iiss", $product_id, $used_sheets, $log_date, $note);
                    $usage_stmt->execute();
                    $usage_stmt->close();
                } else {
                    $message .= "❌ Product not found for $color.<br>";
                    $success = false;
                }
            }

            if ($success) {
                $message = "✅ Job order saved. Reams used per product: " . number_format($reams_per_product, 2);
            }
        } else {
            $message = "❌ Error saving job order: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $message = "❌ Failed to prepare job order insert.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Job Order / Usage</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="usage-container">
  <h2 class="usage-title">Job Order / Product Usage</h2>
  <?php if ($message): ?>
    <div class="message"><?php echo $message; ?></div>
  <?php endif; ?>

  <form method="post" class="usage-form">
    <fieldset>
      <legend>Client Details</legend>
      <input name="client_name" placeholder="Client Name" required><br>
      <input name="client_address" placeholder="Address" required><br>
      <input name="contact_person" placeholder="Contact Person" required><br>
      <input name="contact_number" placeholder="Contact Number" required><br>
    </fieldset>

    <fieldset>
      <legend>Job Specifications</legend>
      <input type="number" name="quantity" min="1" placeholder="Order Quantity" required><br>
      <input type="number" name="number_of_sets" min="1" placeholder="Number of Sets per Product" required><br>

      <label>Product Size:</label>
      <select name="product_size" required>
        <option value="">-- Select Product Size --</option>
        <option value="whole">Whole</option>
        <option value="1/2">1/2</option>
        <option value="1/3">1/3</option>
        <option value="1/4">1/4</option>
        <option value="1/6">1/6</option>
        <option value="1/8">1/8</option>
      </select><br>

      <label>Paper Size:</label>
      <select name="paper_size" required>
        <option value="">-- Select Paper Size --</option>
        <?php while ($size = $product_sizes->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($size['product_group']) ?>"><?= htmlspecialchars($size['product_group']) ?></option>
        <?php endwhile; ?>
        <option value="custom">Add Custom Size...</option>
      </select>
      <input type="text" name="custom_paper_size" placeholder="Custom Size"><br>

      <label>Paper Type:</label>
      <select name="paper_type" required>
        <option value="">-- Select Paper Type --</option>
        <?php while ($type = $product_types->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($type['product_type']) ?>"><?= htmlspecialchars($type['product_type']) ?></option>
        <?php endwhile; ?>
      </select><br>

      <input type="number" name="copies_per_set" id="copies_per_set" min="0" placeholder="Copies per Set" required><br>

      <label>Binding:</label>
      <select name="binding_type" required>
        <option value="">-- Select Binding --</option>
        <option value="Booklet">Booklet</option>
        <option value="Pad">Pad</option>
        <option value="Custom">Custom</option>
      </select>
      <input type="text" name="custom_binding" placeholder="Custom Binding Type"><br>

      <label>Paper Color Sequence:</label>
      <div id="paper-sequence-container"></div>

      <textarea name="special_instructions" placeholder="Special Instructions" rows="3"></textarea><br>
      <input type="date" name="log_date" value="<?= date('Y-m-d') ?>">
    </fieldset>

    <button type="submit">Submit Job Order</button>
    <p><a href="dashboard.php">&larr; Back</a></p>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const allProducts = <?= json_encode($product_query->fetch_all(MYSQLI_ASSOC)); ?>;
  const paperTypeSelect = document.querySelector('[name="paper_type"]');
  const paperSizeSelect = document.querySelector('[name="paper_size"]');
  const copiesInput = document.querySelector('[name="copies_per_set"]');
  const sequenceContainer = document.getElementById('paper-sequence-container');

  function updatePaperSequenceOptions() {
    const type = paperTypeSelect.value;
    const size = paperSizeSelect.value;
    const copies = parseInt(copiesInput.value) || 0;

    // Clear and exit early if not enough input
    if (!type || !size || copies <= 0) {
      sequenceContainer.innerHTML = '';
      return;
    }

    // ✅ Define the matching products
    const matchingProducts = allProducts.filter(p =>
      p.product_type === type &&
      p.product_group === size &&
      p.available_sheets > 0
    );

    // Clear old fields
    sequenceContainer.innerHTML = '';

    // If nothing available
    if (matchingProducts.length === 0) {
      const msg = document.createElement('div');
      msg.textContent = '⚠ No available stock for the selected type and size.';
      msg.style.color = 'red';
      sequenceContainer.appendChild(msg);
      return;
    }

    // Create dropdowns
    for (let i = 0; i < copies; i++) {
      const label = document.createElement('label');
      label.textContent = `Copy ${i + 1}:`;

      const select = document.createElement('select');
      select.name = 'paper_sequence[]';
      select.required = true;

      const defaultOpt = document.createElement('option');
      defaultOpt.textContent = '-- Select Color --';
      defaultOpt.value = '';
      select.appendChild(defaultOpt);

      matchingProducts.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.product_name;
        const reams = (p.available_sheets / 500).toFixed(2);
        opt.textContent = `${p.product_name} (${reams} reams)`;
        select.appendChild(opt);
      });

      sequenceContainer.appendChild(label);
      sequenceContainer.appendChild(select);
      sequenceContainer.appendChild(document.createElement('br'));
    }
  }

  // Attach change events
  paperTypeSelect.addEventListener('change', updatePaperSequenceOptions);
  paperSizeSelect.addEventListener('change', updatePaperSequenceOptions);
  copiesInput.addEventListener('input', updatePaperSequenceOptions);
});
</script>


</body>
</html>
