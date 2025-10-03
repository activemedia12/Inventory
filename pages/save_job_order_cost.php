<?php
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $job_id        = intval($_POST['job_id'] ?? 0);
    $grand_total   = floatval($_POST['grand_total'] ?? 0);
    $printing_type = $_POST['printing_type'] ?? null;
    $printing_cost = floatval($_POST['printing_cost'] ?? 0);
    $other_expenses = intval($_POST['other_expenses_hidden'] ?? 0);
    $paper_spoilage = intval($_POST['paper_spoilage_hidden'] ?? 0);
    $sessions      = $_POST['sessions'] ?? [];

    if ($job_id <= 0) {
        die("❌ Invalid job order ID.");
    }

    // 1. Save grand total + printing + expenses
    $stmt = $inventory->prepare("
        UPDATE job_orders 
        SET grand_total = ?, printing_type = ?, printing_cost = ?, other_expenses = ?, paper_spoilage = ?
        WHERE id = ?
    ");
    if (!$stmt) {
        die("❌ Prepare failed: " . $inventory->error);
    }
    $stmt->bind_param("dsdiii", $grand_total, $printing_type, $printing_cost, $other_expenses, $paper_spoilage, $job_id);
    $stmt->execute();
    $stmt->close();

    // 2. Wipe old sessions
    $inventory->query("DELETE FROM job_sessions WHERE job_id = " . intval($job_id));

    // 3. Insert new sessions
    if (!empty($sessions)) {
        $stmt2 = $inventory->prepare("
            INSERT INTO job_sessions (job_id, task_name, start_time, end_time, break_minutes, hours, cost)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($sessions as $task_name => $taskSessions) {
            foreach ($taskSessions as $s) {
                $start = $s['start'] ?? null;
                $end   = $s['end'] ?? null;
                $break = intval($s['break'] ?? 0);

                if ($start && $end) {
                    $start_dt = strtotime($start);
                    $end_dt   = strtotime($end);

                    if ($end_dt > $start_dt) {
                        $hours = ($end_dt - $start_dt) / 3600 - ($break / 60);
                        if ($hours < 0) $hours = 0;

                        // get rate for this task
                        $rateRes = $inventory->prepare("SELECT hourly_rate FROM manpower_rates WHERE task_name = ?");
                        $rateRes->bind_param("s", $task_name);
                        $rateRes->execute();
                        $rateRow = $rateRes->get_result()->fetch_assoc();
                        $rateRes->close();

                        $rate = $rateRow['hourly_rate'] ?? 0;
                        $cost = $hours * $rate;

                        $stmt2->bind_param("isssidd", $job_id, $task_name, $start, $end, $break, $hours, $cost);
                        $stmt2->execute();
                    }
                }
            }
        }

        $stmt2->close();
    }

    // 4. Redirect back
    header("Location: job_orders.php?updated=1&id=" . $job_id);
    exit;
}
?>
