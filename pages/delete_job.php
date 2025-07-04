<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$job_id = intval($_GET['id'] ?? 0);
$restore_stock = isset($_GET['restore']) && $_GET['restore'] === 'yes';

if ($job_id <= 0) {
    die("Invalid Job ID.");
}

// Fetch job order
$stmt = $mysqli->prepare("SELECT * FROM job_orders WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    die("Job order not found.");
}

// Confirm UI
if (!isset($_GET['confirm'])) {
    ?>
    <h2>Delete Job Order</h2>
    <p>Are you sure you want to delete this job order: <strong><?= htmlspecialchars($job['project_name']) ?></strong>?</p>
    <form method="get">
        <input type="hidden" name="id" value="<?= $job_id ?>">
        <label><input type="checkbox" name="restore" value="yes"> Restore stock from this job order?</label><br><br>
        <button type="submit" name="confirm" value="1">Yes, Delete</button>
        <a href="job_orders.php">Cancel</a>
    </form>
    <?php
    exit;
}

// Restore stock if chosen
if ($restore_stock) {
    $paper_type = $job['paper_type'];
    $paper_size = $job['paper_size'];
    $product_size = $job['product_size'];
    $copies_per_set = intval($job['copies_per_set']);
    $quantity = intval($job['quantity']);
    $number_of_sets = intval($job['number_of_sets']);
    $paper_sequence = explode(',', $job['paper_sequence']);
    $log_date = $job['log_date'];

    $cut_size_map = ['1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, 'whole' => 1];
    $cut_size = $cut_size_map[$product_size] ?? 1;

    $total_sheets = $number_of_sets * $quantity;
    $cut_sheets = $total_sheets / $cut_size;
    $reams = $cut_sheets / 500;
    $reams_per_product = $reams;
    $used_sheets = $reams_per_product * 500;

    foreach ($paper_sequence as $color) {
        $color = trim($color);

        $product = $mysqli->query("
            SELECT id FROM products
            WHERE product_type = '$paper_type'
              AND product_group = '$paper_size'
              AND product_name = '$color'
            LIMIT 1
        ");

        if ($product && $product->num_rows > 0) {
            $prod = $product->fetch_assoc();
            $product_id = $prod['id'];

            $note = "Stock restored after deleting job order ID #$job_id";
            $stmt_restore = $mysqli->prepare("INSERT INTO usage_logs (product_id, used_sheets, log_date, job_order_id, usage_note) VALUES (?, ?, ?, ?, ?)");
            $negative_sheets = -$used_sheets;
            $stmt_restore->bind_param("iisds", $product_id, $negative_sheets, $log_date, $job_id, $note);
            $stmt_restore->execute();
            $stmt_restore->close();
        }
    }
}

// Delete usage logs and job order
$mysqli->query("DELETE FROM usage_logs WHERE job_order_id = $job_id");

$stmt = $mysqli->prepare("DELETE FROM job_orders WHERE id = ?");
$stmt->bind_param("i", $job_id);
if ($stmt->execute()) {
    $msg = "Job Order deleted successfully";
    if ($restore_stock) {
        $msg .= " and stock was restored.";
    }
    header("Location: job_orders.php?msg=" . urlencode($msg));
    exit;
} else {
    die("Failed to delete Job Order: " . $stmt->error);
}
?>
