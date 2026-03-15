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

if ($job_id <= 0) {
    header("Location: job_orders.php");
    exit;
}

// 1. Save grand total + all options
$stmt = $inventory->prepare("
    UPDATE job_orders 
    SET grand_total = ?, printing_type = ?, printing_cost = ?,
        other_expenses = ?, paper_spoilage = ?,
        paper_pricing_method = ?, custom_paper_cost = ?
    WHERE id = ?
");
$stmt->bind_param("dsdiisdi",
    $grand_total, $printing_type, $printing_cost,
    $other_expenses, $paper_spoilage,
    $paper_pricing_method, $custom_paper_cost,
    $job_id
);
$stmt->execute();
$stmt->close();

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