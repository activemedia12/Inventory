<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized.";
    exit;
}

require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: job_orders.php");
    exit;
}

$job_id               = intval($_POST['job_id'] ?? 0);
$grand_total          = floatval($_POST['grand_total'] ?? 0);
$printing_type        = $_POST['printing_type'] ?? null;
$printing_cost        = floatval($_POST['printing_cost'] ?? 0);
$other_expenses       = intval($_POST['other_expenses_hidden'] ?? 0);
$paper_spoilage       = intval($_POST['paper_spoilage_hidden'] ?? 0);
$sessions             = $_POST['sessions'] ?? [];
$paper_pricing_method = $_POST['paper_pricing_method'] ?? 'ream';
$custom_paper_cost    = floatval($_POST['custom_paper_cost'] ?? 0);

// Itemized "other expenses" (book cover, plastic cover, strings, ring, etc.)
$itemized_raw   = json_decode($_POST['itemized_expenses_hidden'] ?? '[]', true);
$itemized_items = [];
if (is_array($itemized_raw)) {
    foreach ($itemized_raw as $item) {
        $ex_name  = trim($item['name'] ?? '');
        $ex_price = floatval($item['price'] ?? 0);
        if ($ex_name === '') continue;
        $itemized_items[] = ['name' => $ex_name, 'price' => $ex_price];
    }
}
$itemized_expenses_total = array_sum(array_column($itemized_items, 'price'));

if ($job_id <= 0) {
    header("Location: job_orders.php");
    exit;
}

// Ensure job_orders has a column to hold the itemized expenses total
$colCheck = $inventory->query("
    SELECT COUNT(*) AS c FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_orders' AND COLUMN_NAME = 'itemized_expenses_total'
")->fetch_assoc();
if ($colCheck && $colCheck['c'] == 0) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN itemized_expenses_total DECIMAL(10,2) DEFAULT 0.00");
}

// Ensure the itemized expenses table exists (mirrors paper_cost.php)
$inventory->query("
    CREATE TABLE IF NOT EXISTS job_order_itemized_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        expense_name VARCHAR(150) NOT NULL,
        expense_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sort_order INT DEFAULT 0,
        INDEX idx_job (job_id)
    )
");

// 1. Save grand total + all options
$stmt = $inventory->prepare("
    UPDATE job_orders 
    SET grand_total = ?, printing_type = ?, printing_cost = ?,
        other_expenses = ?, paper_spoilage = ?,
        paper_pricing_method = ?, custom_paper_cost = ?,
        itemized_expenses_total = ?
    WHERE id = ?
");
$stmt->bind_param("dsdiisddi",
    $grand_total, $printing_type, $printing_cost,
    $other_expenses, $paper_spoilage,
    $paper_pricing_method, $custom_paper_cost,
    $itemized_expenses_total,
    $job_id
);
$stmt->execute();
$stmt->close();

// 1b. Sync the itemized expense line items (replaces the full list for this job)
$delIE = $inventory->prepare("DELETE FROM job_order_itemized_expenses WHERE job_id = ?");
$delIE->bind_param("i", $job_id);
$delIE->execute();
$delIE->close();

if (!empty($itemized_items)) {
    $insIE = $inventory->prepare("
        INSERT INTO job_order_itemized_expenses (job_id, expense_name, expense_price, sort_order)
        VALUES (?, ?, ?, ?)
    ");
    $sort_order = 0;
    foreach ($itemized_items as $item) {
        $insIE->bind_param("isdi", $job_id, $item['name'], $item['price'], $sort_order);
        $insIE->execute();
        $sort_order++;
    }
    $insIE->close();
}

// 2. Wipe old sessions (prepared statement)
$del = $inventory->prepare("DELETE FROM job_sessions WHERE job_id = ?");
$del->bind_param("i", $job_id);
$del->execute();
$del->close();

// 3. Insert new sessions — fetch all rates at once first (fix N+1)
if (!empty($sessions)) {
    $rates_result = $inventory->query("SELECT task_name, hourly_rate FROM manpower_rates");
    $all_rates = [];
    while ($r = $rates_result->fetch_assoc()) {
        $all_rates[$r['task_name']] = (float)$r['hourly_rate'];
    }

    $stmt2 = $inventory->prepare("
        INSERT INTO job_sessions (job_id, task_name, start_time, end_time, break_minutes, hours, cost)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($sessions as $task_name => $taskSessions) {
        $rate = $all_rates[$task_name] ?? 0;

        foreach ($taskSessions as $s) {
            $start = $s['start'] ?? null;
            $end   = $s['end']   ?? null;
            $break = intval($s['break'] ?? 0);

            if ($start && $end) {
                $start_dt = strtotime($start);
                $end_dt   = strtotime($end);

                if ($end_dt > $start_dt) {
                    $hours = ($end_dt - $start_dt) / 3600 - ($break / 60);
                    if ($hours < 0) $hours = 0;
                    $cost = $hours * $rate;

                    $stmt2->bind_param("isssidd",
                        $job_id, $task_name, $start, $end, $break, $hours, $cost
                    );
                    $stmt2->execute();
                }
            }
        }
    }
    $stmt2->close();
}

// 4. Redirect back (PRG)
header("Location: job_orders.php?updated=1&id=" . $job_id);
exit;
?>