<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Function to fetch data from database
function fetchData($inventory, $query)
{
    $result = $inventory->query($query);
    return $result->fetch_assoc()['total'] ?? 0;
}

// Quick Stats
$total_products = fetchData($inventory, "SELECT COUNT(*) AS total FROM products");
$deliveries_this_week = fetchData($inventory, "
  SELECT COUNT(*) AS total FROM delivery_logs
  WHERE YEARWEEK(delivery_date, 1) = YEARWEEK(CURDATE(), 1)
");
$out_of_stock = fetchData($inventory, "
  SELECT COUNT(*) AS total FROM products p
  LEFT JOIN (
    SELECT d.product_id,
          SUM(d.delivered_reams) AS total_reams,
          (SUM(d.delivered_reams) * 500) - IFNULL((
            SELECT SUM(u.used_sheets) FROM usage_logs u WHERE u.product_id = d.product_id
          ), 0) AS balance
    FROM delivery_logs d
    GROUP BY d.product_id
  ) AS stock ON p.id = stock.product_id
  WHERE IFNULL(balance, 0) <= 0
");

// === FINANCIAL SUMMARY FUNCTIONS ===
function getFinancialSummary($inventory, $period = 'month')
{
    $currentDate = date('Y-m-d');

    switch ($period) {
        case 'week':
            $dateCondition = "YEARWEEK(jo.log_date, 1) = YEARWEEK(CURDATE(), 1)";
            $periodLabel = 'This Week';
            break;
        case 'month':
            $dateCondition = "MONTH(jo.log_date) = MONTH(CURDATE()) AND YEAR(jo.log_date) = YEAR(CURDATE())";
            $periodLabel = 'This Month';
            break;
        case 'year':
            $dateCondition = "YEAR(jo.log_date) = YEAR(CURDATE())";
            $periodLabel = 'This Year';
            break;
        default:
            $dateCondition = "MONTH(jo.log_date) = MONTH(CURDATE()) AND YEAR(jo.log_date) = YEAR(CURDATE())";
            $periodLabel = 'This Month';
    }

    // All jobs in period regardless of pricing status
    $all_jobs_query = "
        SELECT COUNT(DISTINCT jo.id) as total_all_jobs
        FROM job_orders jo
        WHERE {$dateCondition}
    ";

    // Only jobs with both production cost AND selling price filled in
    $query = "
        SELECT 
            COUNT(DISTINCT jo.id) as total_jobs,
            COALESCE(SUM(jo.grand_total), 0) as total_expenses,
            COALESCE(SUM(jo.total_cost), 0) as total_revenue,
            COALESCE(SUM(jo.total_cost - jo.grand_total), 0) as total_profit
        FROM job_orders jo
        WHERE {$dateCondition}
        AND jo.grand_total > 0 AND jo.total_cost > 0
    ";

    // Jobs incomplete (missing cost or selling price)
    $excluded_query = "
        SELECT COUNT(DISTINCT jo.id) as excluded
        FROM job_orders jo
        WHERE {$dateCondition}
        AND (jo.grand_total IS NULL OR jo.grand_total <= 0 OR jo.total_cost IS NULL OR jo.total_cost <= 0)
    ";

    $all_result = $inventory->query($all_jobs_query);
    $total_all_jobs = (int)($all_result->fetch_assoc()['total_all_jobs'] ?? 0);

    $result = $inventory->query($query);
    $data = $result->fetch_assoc();

    $excl_result = $inventory->query($excluded_query);
    $excluded = (int)($excl_result->fetch_assoc()['excluded'] ?? 0);

    // Profit margin: profit as % of revenue (selling price), not expenses
    $profit_percent = ($data['total_revenue'] > 0)
        ? ($data['total_profit'] / $data['total_revenue']) * 100
        : 0;

    return [
        'period'       => $periodLabel,
        'total_jobs'   => $total_all_jobs,   // all jobs logged this period
        'jobs'         => $data['total_jobs'] ?? 0,  // jobs with complete pricing
        'excluded'     => $excluded,
        'expenses'     => $data['total_expenses'] ?? 0,
        'revenue'      => $data['total_revenue'] ?? 0,
        'profit'       => $data['total_profit'] ?? 0,
        'profit_percent' => $profit_percent
    ];
}

function getMonthlyBreakdown($inventory, $year = null)
{
    $year = $year ?? date('Y');

    $query = "
        SELECT 
            MONTH(jo.log_date) as month,
            COUNT(DISTINCT jo.id) as total_jobs,
            COALESCE(SUM(jo.grand_total), 0) as total_expenses,
            COALESCE(SUM(jo.total_cost), 0) as total_revenue,
            COALESCE(SUM(jo.total_cost - jo.grand_total), 0) as total_profit
        FROM job_orders jo
        WHERE YEAR(jo.log_date) = ?
        AND jo.grand_total > 0 AND jo.total_cost > 0
        GROUP BY MONTH(jo.log_date)
        ORDER BY month ASC
    ";

    $stmt = $inventory->prepare($query);
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $months = [];
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // Initialize all months with zero
    for ($i = 1; $i <= 12; $i++) {
        $months[$i] = [
            'month' => $monthNames[$i - 1],
            'month_num' => $i,
            'jobs' => 0,
            'expenses' => 0,
            'revenue' => 0,
            'profit' => 0,
            'profit_percent' => 0
        ];
    }

    // Fill in actual data
    while ($row = $result->fetch_assoc()) {
        $monthNum = (int)$row['month'];
        $months[$monthNum]['jobs'] = $row['total_jobs'];
        $months[$monthNum]['expenses'] = $row['total_expenses'];
        $months[$monthNum]['revenue'] = $row['total_revenue'];
        $months[$monthNum]['profit'] = $row['total_profit'];
        $months[$monthNum]['profit_percent'] = ($row['total_revenue'] > 0)
            ? ($row['total_profit'] / $row['total_revenue']) * 100
            : 0;
    }

    $stmt->close();
    return $months;
}

function getYearlySummary($inventory)
{
    $query = "
        SELECT 
            YEAR(jo.log_date) as year,
            COUNT(DISTINCT jo.id) as total_jobs,
            COALESCE(SUM(jo.grand_total), 0) as total_expenses,
            COALESCE(SUM(jo.total_cost), 0) as total_revenue,
            COALESCE(SUM(jo.total_cost - jo.grand_total), 0) as total_profit
        FROM job_orders jo
        WHERE (jo.grand_total > 0 AND jo.total_cost > 0)
        GROUP BY YEAR(jo.log_date)
        ORDER BY year DESC
        LIMIT 5
    ";

    $result = $inventory->query($query);
    $years = [];

    while ($row = $result->fetch_assoc()) {
        $row['profit_percent'] = ($row['total_revenue'] > 0)
            ? ($row['total_profit'] / $row['total_revenue']) * 100
            : 0;
        $years[] = $row;
    }

    return $years;
}

// Get financial summaries
$weekly_finance = getFinancialSummary($inventory, 'week');
$monthly_finance = getFinancialSummary($inventory, 'month');
$yearly_finance = getFinancialSummary($inventory, 'year');
$monthly_breakdown = getMonthlyBreakdown($inventory);
$yearly_summary = getYearlySummary($inventory);

// Calculate total profit for the year

// Recent Data
$recent_deliveries = $inventory->query("
  SELECT d.product_id, d.delivery_date, p.product_type, p.product_group, p.product_name, d.delivered_reams
  FROM delivery_logs d
  JOIN products p ON d.product_id = p.id
  ORDER BY d.delivery_date DESC
  LIMIT 5
");

$recent_usage = $inventory->query("
  SELECT u.product_id, u.log_date, p.product_type, p.product_group, p.product_name, u.used_sheets
  FROM usage_logs u
  JOIN products p ON u.product_id = p.id
  ORDER BY u.log_date DESC
  LIMIT 5
");

// Stock Summary Data
$stock_data = $inventory->query("
  SELECT 
    p.product_type, p.product_group, p.product_name,
    ((SELECT IFNULL(SUM(delivered_reams), 0) FROM delivery_logs WHERE product_id = p.id) * 500 -
    (SELECT IFNULL(SUM(used_sheets), 0) FROM usage_logs WHERE product_id = p.id)) AS available_sheets
  FROM products p
  ORDER BY p.product_type, p.product_name, p.product_group
");

$low_stock = fetchData($inventory, "
  SELECT COUNT(*) AS total FROM products p
  LEFT JOIN (
    SELECT d.product_id,
          SUM(d.delivered_reams) AS total_reams,
          (SUM(d.delivered_reams) * 500) - IFNULL((
            SELECT SUM(u.used_sheets) FROM usage_logs u WHERE u.product_id = d.product_id
          ), 0) AS balance
    FROM delivery_logs d
    GROUP BY d.product_id
  ) AS stock ON p.id = stock.product_id
  WHERE IFNULL(balance, 0) >= 0 
  AND IFNULL(balance, 0) < 10000 /* 20 reams * 500 sheets */
");

$grouped = [];
while ($row = $stock_data->fetch_assoc()) {
    $type = $row['product_type'];
    $group = $row['product_group'];
    $name = $row['product_name'];
    $sheets = max(0, $row['available_sheets']);
    $reams = $sheets / 500;

    if (!isset($grouped[$type])) $grouped[$type] = [];
    if (!isset($grouped[$type][$name])) $grouped[$type][$name] = [];
    $grouped[$type][$name][$group] = $reams;
}

$sql = "SELECT 
            jo.*, 
            u.username,
            jo.grand_total as total_expenses,
            jo.total_cost,
            (jo.total_cost - jo.grand_total) as profit
        FROM job_orders jo
        LEFT JOIN users u ON u.id = jo.created_by
        ORDER BY jo.created_at DESC 
        LIMIT 10";
$result = $inventory->query($sql);

$recent_orders = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

// format username
$username = ucfirst(strtolower(htmlspecialchars($_SESSION['username'])));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Dashboard - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
        /* ==========================================================
           Active Media Printing — Dashboard
           Reskinned to the shared minimal-SaaS design tokens.
           Same class names as before (so no HTML/JS was touched),
           consolidated to remove duplicate/conflicting rules.
           ========================================================== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbced3;
            border-radius: 8px;
        }

        :root {
            --primary: #4f5eff;
            --secondary: #4048e0;
            --primary-bg: #eef1ff;
            --light: #f6f6f7;
            --dark: #14171f;
            --gray: #6b7280;
            --light-gray: #e2e4e7;
            --card-bg: #ffffff;
            --success: #1a9c6b;
            --success-bg: #e3f6ee;
            --danger: #d9463c;
            --danger-bg: #fbe9e7;
            --warning: #b6790a;
            --warning-bg: #fdf2df;
            --info: #2a7ade;
            --info-bg: #e8f1fc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            border-right: 1px solid var(--light-gray);
            padding: 20px 0;
        }

        .brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .brand img {
            height: 100px;
            width: auto;
            padding-left: 0;
            transform: none;
        }

        .brand h2 {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }

        .nav-menu {
            list-style: none;
            padding: 0 12px;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .nav-menu li a:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        .nav-menu li a.active {
            background-color: var(--primary-bg);
            color: var(--secondary);
        }

        .nav-menu li a i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            color: var(--gray);
        }

        .nav-menu li a.active i,
        .nav-menu li a:hover i {
            color: inherit;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 28px 32px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .user-details h4 {
            font-weight: 600;
            font-size: 14px;
        }

        .user-details small {
            color: var(--gray);
            font-size: 12px;
        }

        /* Welcome banner — kept, de-glossed to fit the calmer palette */
        .welcome {
            font-style: normal;
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 20px;
            color: var(--dark);
            display: block;
        }

        /* Notice / quick tip */
        .quick-tip {
            background: var(--info-bg);
            border-left: 3px solid var(--info);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-tip i {
            color: var(--info);
            font-size: 16px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            margin-bottom: 20px;
            gap: 16px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            min-width: 0;
        }

        .ss {
            max-width: 600px;
        }

        .stat-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .stat-card .card-icon {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-bg);
            color: var(--primary);
            font-size: 16px;
            flex-shrink: 0;
        }

        .stat-card h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .stat-card p {
            color: var(--gray);
            font-size: 13px;
        }

        .stat-card .stat-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .stat-card .stat-period {
            font-size: 12px;
            color: var(--gray);
            margin-top: 4px;
        }

        .profit-positive {
            color: var(--success) !important;
        }

        .profit-negative {
            color: var(--danger) !important;
        }

        /* Financial Cards Grid */
        .finance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .finance-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
        }

        /* Week/month/year are told apart by icon + label, not a loud stripe */
        .finance-card.week,
        .finance-card.month,
        .finance-card.year {
            border-top: 1px solid var(--light-gray);
        }

        .finance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .finance-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .finance-title i {
            color: var(--gray);
        }

        .finance-badge {
            background: var(--light);
            color: var(--gray);
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .finance-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .finance-label {
            color: var(--gray);
        }

        .finance-value {
            font-weight: 600;
        }

        .finance-profit {
            padding-top: 10px;
            margin-top: 8px;
            border-top: 1px solid var(--light-gray);
            font-size: 16px;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
        }

        /* Monthly Breakdown */
        .monthly-breakdown {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
            overflow: auto;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
        }

        .section-title i {
            color: var(--gray);
        }

        .monthly-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .month-card {
            background: var(--light);
            border-radius: 6px;
            padding: 14px;
            transition: box-shadow 0.15s ease;
        }

        .month-card:hover {
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.06);
        }

        .month-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 6px;
            font-size: 13px;
        }

        .month-stat {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 12px;
        }

        .month-stat .label {
            color: var(--gray);
        }

        .month-stat .value {
            font-weight: 600;
        }

        .stock-cards {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
        }

        .out-card {
            min-width: 300px;
            max-height: 120px;
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            margin-bottom: 20px;
        }

        .recent-tables td {
            cursor: pointer;
            font-size: 13px;
        }

        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
            width: 100%;
            margin-bottom: 20px;
        }

        .table-card h3 {
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            font-size: 15px;
            font-weight: 600;
            color: var(--dark);
        }

        .table-card h3 i {
            margin-right: 8px;
            color: var(--gray);
        }

        table {
            min-width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
        }

        th {
            font-weight: 600;
            color: var(--gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        tr td {
            transition: background-color 0.15s ease;
        }

        tr:hover td {
            background-color: var(--light);
        }

        .view-all {
            display: inline-block;
            margin-top: 14px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        @media (max-width: 1200px) {
            .flex {
                flex-direction: column;
                gap: 20px;
            }

            .finance-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar-con {
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                position: fixed;
            }

            .sidebar {
                position: fixed;
                overflow: hidden;
                height: auto;
                width: auto;
                bottom: 12px;
                left: 50%;
                transform: translateX(-50%);
                padding: 6px;
                background-color: rgba(255, 255, 255, 0.85);
                backdrop-filter: blur(6px);
                box-shadow: 0 4px 16px rgba(20, 23, 31, 0.12);
                border-radius: 100px;
                touch-action: manipulation;
                z-index: 9999;
                flex-direction: row;
                border: 1px solid var(--light-gray);
                justify-content: center;
            }

            .sidebar .nav-menu {
                display: flex;
                flex-direction: row;
                padding: 0;
            }

            .sidebar img,
            .sidebar .brand,
            .sidebar .nav-menu li a span {
                display: none;
            }

            .sidebar .nav-menu li a {
                justify-content: center;
                padding: 12px;
            }

            .sidebar .nav-menu li a i {
                margin-right: 0;
            }

            .main-content {
                margin-left: 0;
                overflow: auto;
                margin-bottom: 90px;
                padding: 20px;
            }

            .stat-card {
                min-width: 100%;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .out-card {
                min-width: 100%;
            }

            .recent-tables {
                overflow: auto;
            }

            .tables-section {
                grid-template-columns: repeat(auto-fit, minmax(100%, 1fr));
                font-size: 90%;
            }

            .welcome {
                font-size: 20px !important;
            }

            .finance-grid {
                grid-template-columns: 1fr;
            }

            .monthly-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .user-info {
                margin-top: 4px;
            }

            .recent-tables table {
                min-width: 500px;
            }

            .monthly-grid {
                grid-template-columns: 1fr;
            }

            .recent-tables table {
                min-width: 800px;
            }

            .recent-tables td,
            .recent-tables th {
                font-size: 12px;
                padding: 8px 10px;
            }
        }

        .stat-card table th,
        .stat-card table td {
            font-size: 13px;
            border-bottom: 1px solid var(--light-gray);
            white-space: nowrap;
        }

        .stat-card table span.low {
            color: var(--danger);
            font-weight: 600;
        }

        .stat-card table span.mid {
            color: var(--warning);
            font-weight: 600;
        }

        .stat-card table span.high {
            color: var(--success);
            font-weight: 600;
        }

        /* Stock Summary Styles */
        .stock-summary {
            margin-top: 12px;
            overflow: auto;
        }

        .product-category {
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: visible;
        }

        .category-header {
            padding: 12px 15px;
            background-color: var(--card-bg);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-width: 500px;
            border-radius: 8px;
        }

        .category-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-title h4 {
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
        }

        .toggle-icon {
            color: var(--gray);
            font-size: 12px;
            transition: transform 0.2s ease;
        }

        .badge {
            background-color: var(--light);
            color: var(--gray);
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .category-summary {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .summary-item {
            font-size: 12px;
            color: var(--gray);
        }

        .summary-item strong {
            color: var(--dark);
            margin-left: 4px;
        }

        .stock-table-container {
            display: none;
            background-color: var(--card-bg);
            max-height: 250px;
            overflow: auto;
        }

        .stock-table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .stock-table-container th {
            font-weight: 600;
            font-size: 12px;
            color: var(--gray);
            padding: 8px 10px;
            text-align: left;
            position: sticky;
            top: 0;
            background-color: var(--card-bg);
            z-index: 2;
            box-shadow: 0 1px 0 var(--light-gray);
        }

        .stock-table-container th.text-center {
            text-align: center;
        }

        .stock-table-container td {
            padding: 10px;
            border-top: 1px solid var(--light-gray);
        }

        .product-name {
            font-weight: 600;
            color: var(--dark);
            min-width: 150px;
        }

        .stock-indicator {
            position: relative;
            min-width: 80px;
        }

        .stock-value {
            font-weight: 600;
            font-size: 13px;
            text-align: center;
        }

        .stock-bar {
            height: 4px;
            background-color: var(--light-gray);
            border-radius: 2px;
            margin: 5px 0;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background-color: var(--primary);
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .stock-label {
            font-size: 10px;
            color: var(--gray);
            text-align: center;
            text-transform: uppercase;
        }

        /* Stock level colors */
        .stock-indicator.high .bar-fill {
            background-color: var(--success);
        }

        .stock-indicator.mid .bar-fill {
            background-color: var(--warning);
        }

        .stock-indicator.low .bar-fill {
            background-color: var(--danger);
        }

        .na {
            color: var(--light-gray);
        }

        /* Modal Styles */
        @keyframes centerZoomIn {
            0% {
                transform: translate(-50%, -50%) scale(0.97);
                opacity: 0;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }

        @keyframes centerZoomOut {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }

            100% {
                transform: translate(-50%, -50%) scale(0.97);
                opacity: 0;
            }
        }

        @keyframes modalBackdropFadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
        }

        .modal.closing {
            animation: modalBackdropFadeOut 0.16s ease forwards;
        }

        .floating-window.closing {
            animation: centerZoomOut 0.16s ease-in forwards;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            background: rgba(20, 23, 31, 0.35);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
        }

        .modal-content h3 {
            margin: 0;
            padding: 16px 20px;
            background: var(--dark);
            color: white;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close {
            position: absolute;
            color: white;
            font-size: 22px;
            font-weight: normal;
            opacity: 0.8;
            transition: opacity 0.2s;
            cursor: pointer;
            right: 20px;
        }

        .close:hover {
            opacity: 1;
            color: white;
        }

        #lowStockItems {
            overflow: auto;
        }

        #lowStockItems table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        #lowStockItems th {
            text-align: left;
            padding: 10px 15px;
            background: var(--light);
            color: var(--gray);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid var(--light-gray);
        }

        #lowStockItems td {
            padding: 10px 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        #lowStockItems tr:last-child td {
            border-bottom: none;
        }

        #lowStockItems tr:hover {
            background: var(--light);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-message {
            padding: 30px;
            text-align: center;
            color: var(--gray);
        }

        #jobModal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(20, 23, 31, 0.35);
            backdrop-filter: blur(2px);
            z-index: 999;
            display: none;
        }

        .floating-window {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 1000px;
            height: 80vh;
            background: white;
            border-radius: 10px;
            box-shadow: 0 12px 32px rgba(20, 23, 31, 0.18);
            z-index: 1000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: centerZoomIn 0.18s ease-out forwards;
        }

        .window-header {
            padding: 14px 20px;
            background: var(--dark);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .window-title {
            display: flex;
            align-items: center;
            font-size: 15px;
            font-weight: 600;
        }

        .window-title i {
            margin-right: 10px;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 16px;
            cursor: pointer;
            padding: 6px;
            opacity: 0.85;
        }

        .close-btn:hover {
            opacity: 1;
        }

        .window-content {
            padding: 22px;
            overflow-y: auto;
            flex-grow: 1;
        }

        /* Product Info Compact Grid */
        .product-info-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }

        .info-item-compact strong {
            display: block;
            color: var(--gray);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }

        .info-item-compact span {
            font-size: 13px;
            color: var(--dark);
        }

        /* Stock Summary Cards */
        .stock-summary-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .stock-card-compact {
            padding: 14px;
            border-radius: 8px;
            background: var(--light);
            text-align: center;
        }

        .stock-card-compact h4 {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .stock-value-compact {
            font-size: 18px;
            font-weight: 700;
        }

        .stock-unit-compact {
            color: var(--gray);
            font-size: 11px;
        }

        /* Job modal section headers */
        .window-content .section-header {
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            margin-top: 20px;
        }

        .window-content .section-header i {
            margin-right: 8px;
            color: var(--gray);
        }

        .special-instructions {
            padding: 14px;
            background: var(--light);
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            margin-top: 14px;
            gap: 12px;
        }

        .status-toggle-form {
            display: flex;
        }

        .status-select {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            border: 1px solid var(--light-gray);
            background: var(--card-bg);
            color: var(--dark);
        }

        .status-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Status pill badges — one definition per status, no collisions */
        .status-pending {
            background: var(--warning-bg);
            color: var(--warning);
            border: 1px solid transparent;
        }

        .status-unpaid {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid transparent;
        }

        .status-for_delivery,
        .status-for-delivery {
            background: var(--info-bg);
            color: var(--info);
            border: 1px solid transparent;
        }

        .status-completed {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid transparent;
        }

        .btn-status {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.15s ease;
        }

        .btn-status:hover {
            opacity: 0.8;
        }

        .btn-edit,
        .btn-delete {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity 0.15s ease;
        }

        .btn-edit {
            background: var(--primary-bg);
            color: var(--secondary);
            border: 1px solid transparent;
        }

        .btn-delete {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid transparent;
        }

        .btn-edit:hover,
        .btn-delete:hover {
            opacity: 0.8;
        }

        /* Empty State */
        .empty-state {
            padding: 16px;
            text-align: center;
            color: var(--gray);
            background: var(--light);
            border-radius: 8px;
            font-size: 13px;
        }

        .empty-state i {
            margin-right: 8px;
        }

        .status-form {
            display: inline;
        }

        .flex {
            display: flex;
            flex-wrap: nowrap;
            margin-bottom: 20px;
        }

        .fw-bold {
            font-weight: 600 !important;
        }

        .text-muted {
            color: var(--gray) !important;
        }

        .status-badge {
            transition: opacity 0.15s ease;
        }

        .status-badge:hover {
            opacity: 0.85;
        }

        .empty-message {
            text-align: center;
            padding: 24px;
            color: var(--gray);
            font-size: 13px;
        }

        .text-success {
            color: var(--success);
        }

        .text-danger {
            color: var(--danger);
        }

        .text-warning {
            color: var(--warning);
        }

        .hover-tooltip {
            position: relative;
            cursor: help;
        }

        .hover-tooltip:hover:after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
        }

        .action-needed {
            animation: gentle-pulse 2s infinite;
        }

        @keyframes gentle-pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }

            100% {
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php" onclick="goToLastProductPage()"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div>
                <h1>Dashboard Overview</h1>
                <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
                    <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                    <small><?php echo $_SESSION['role']; ?></small>
                </div>
            </div>
        </header>

        <!-- Stats Cards         Stats Cards - Complete Fixed Version -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p class="stat-label">Total Products</p>
                        <h3><?= number_format($total_products) ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-boxes"></i></div>
                </div>
                <div class="stat-period">Active inventory items</div>
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p class="stat-label">Deliveries This Week</p>
                        <h3><?= number_format($deliveries_this_week) ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-truck"></i></div>
                </div>
                <div class="stat-period">Incoming stock this week</div>
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p class="stat-label">Out of Stock</p>
                        <h3 class="<?= $out_of_stock > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($out_of_stock) ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-period <?= $out_of_stock > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= $out_of_stock > 0 ? '⚠️ Needs immediate attention' : '✓ All items in stock' ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p class="stat-label">Low Stock Items</p>
                        <h3 class="<?= $low_stock > 0 ? 'text-warning' : 'text-success' ?>"><?= number_format($low_stock) ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-exclamation-circle"></i></div>
                </div>
                <div class="stat-period <?= $low_stock > 0 ? 'text-warning' : 'text-success' ?>">
                    <?= $low_stock > 0 ? '⚠️ ' . $low_stock . ' items below 20 reams' : '✓ Stock levels healthy' ?>
                </div>
            </div>
        </div>

        <?php
        // Scope to current month only
        $missing_costs = (int)($inventory->query("
    SELECT COUNT(*) AS cnt FROM job_orders
    WHERE MONTH(log_date) = MONTH(CURDATE()) AND YEAR(log_date) = YEAR(CURDATE())
    AND (grand_total IS NULL OR grand_total <= 0)
")->fetch_assoc()['cnt'] ?? 0);

        $missing_revenue = (int)($inventory->query("
    SELECT COUNT(*) AS cnt FROM job_orders
    WHERE MONTH(log_date) = MONTH(CURDATE()) AND YEAR(log_date) = YEAR(CURDATE())
    AND grand_total > 0
    AND (total_cost IS NULL OR total_cost <= 0)
")->fetch_assoc()['cnt'] ?? 0);

        $notice_parts = [];
        if ($missing_costs > 0)    $notice_parts[] = "<strong>{$missing_costs}</strong> job" . ($missing_costs != 1 ? 's' : '') . " missing production cost";
        if ($missing_revenue > 0)  $notice_parts[] = "<strong>{$missing_revenue}</strong> job" . ($missing_revenue != 1 ? 's' : '') . " missing selling price";
        ?>
        <?php if (!empty($notice_parts)): ?>
            <div style="display:flex; align-items:center; gap:10px; background:var(--warning-bg); border-left:3px solid var(--warning); padding:10px 16px; border-radius:6px; font-size:13px; margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle" style="color:var(--warning); flex-shrink:0;"></i>
                <span style="color:var(--warning);">
                    This month: <?= implode(' &amp; ', $notice_parts) ?> —
                    <a href="job_orders.php" style="color:var(--primary); font-weight:600;">complete them to see accurate figures</a>
                </span>
            </div>
        <?php endif; ?>

        <!-- Financial Summary Cards -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="font-size: 20px;"><i class="fas fa-chart-line" style="margin-right: 10px; color: var(--primary);"></i>Financial Performance</h2>
        </div>

        <div class="finance-grid">
            <?php foreach (
                [
                    ['data' => $weekly_finance,  'class' => 'week',  'icon' => 'fa-calendar-week', 'label' => 'This Week'],
                    ['data' => $monthly_finance, 'class' => 'month', 'icon' => 'fa-calendar-alt',  'label' => 'This Month'],
                    ['data' => $yearly_finance,  'class' => 'year',  'icon' => 'fa-calendar',       'label' => 'This Year'],
                ] as $card
            ):
                $f = $card['data'];
                $has_data = $f['jobs'] > 0;
            ?>
                <div class="finance-card <?= $card['class'] ?>">
                    <div class="finance-header">
                        <span class="finance-title">
                            <i class="fas <?= $card['icon'] ?>"></i> <?= $card['label'] ?>
                        </span>
                        <span class="finance-badge"><?= $f['total_jobs'] ?> Job<?= $f['total_jobs'] != 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (!$has_data && $f['total_jobs'] == 0): ?>
                        <!-- No jobs at all -->
                        <div style="text-align:center; padding: 20px 0; color: var(--gray); font-size: 13px;">
                            <i class="fas fa-inbox" style="font-size: 24px; opacity: 0.3; display: block; margin-bottom: 8px;"></i>
                            No job orders <?= strtolower($card['label']) ?>
                        </div>

                    <?php elseif (!$has_data && $f['excluded'] > 0): ?>
                        <!-- Jobs exist but none are priced yet -->
                        <div style="text-align:center; padding: 16px 0; color: var(--gray); font-size: 13px;">
                            <i class="fas fa-clock" style="font-size: 24px; color: var(--warning); display: block; margin-bottom: 8px;"></i>
                            <strong style="color: var(--dark);"><?= $f['excluded'] ?> job<?= $f['excluded'] != 1 ? 's' : '' ?> logged</strong><br>
                            <span style="font-size: 12px;">Awaiting cost &amp; price entry</span>
                            <br><br>
                            <a href="job_orders.php" style="font-size: 12px; color: var(--primary); font-weight: 600;">
                                &rarr; Enter missing data
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Has complete financial data -->
                        <div class="finance-row">
                            <span class="finance-label">Revenue:</span>
                            <span class="finance-value">&#8369; <?= number_format($f['revenue'], 2) ?></span>
                        </div>
                        <div class="finance-row">
                            <span class="finance-label">Expenses:</span>
                            <span class="finance-value">&#8369; <?= number_format($f['expenses'], 2) ?></span>
                        </div>
                        <div class="finance-profit">
                            <span>Profit:</span>
                            <span class="<?= $f['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                &#8369; <?= number_format($f['profit'], 2) ?>
                                <small>(<?= number_format($f['profit_percent'], 1) ?>% margin)</small>
                            </span>
                        </div>
                        <?php if ($f['excluded'] > 0): ?>
                            <div style="margin-top: 8px; font-size: 11px; color: var(--gray); border-top: 1px solid var(--light-gray); padding-top: 8px;">
                                <i class="fas fa-info-circle"></i>
                                <?= $f['jobs'] ?> of <?= $f['total_jobs'] ?> jobs priced &mdash;
                                <a href="job_orders.php" style="color: var(--primary);"><?= $f['excluded'] ?> incomplete</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Monthly Breakdown -->
        <div class="monthly-breakdown">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Monthly Breakdown <?= date('Y') ?>
                </div>
                <a href="job_orders.php" class="view-all">View All Jobs <i class="fas fa-arrow-right"></i></a>
            </div>

            <div class="monthly-grid">
                <?php
                $current_month = date('n');
                foreach ($monthly_breakdown as $index => $month):
                    $is_current = ($index == $current_month);
                ?>
                    <div class="month-card" style="<?= $is_current ? 'border: 2px solid var(--primary);' : '' ?>">
                        <div class="month-name">
                            <?= $month['month'] ?>
                            <?php if ($is_current): ?>
                                <span style="font-size: 10px; background: var(--primary); color: white; padding: 2px 6px; border-radius: 10px; margin-left: 5px;">Current</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($month['jobs'] == 0): ?>
                            <div style="font-size: 12px; color: var(--gray); margin-top: 8px; font-style: italic;">No complete data</div>
                        <?php else: ?>
                            <div class="month-stat">
                                <span class="label">Jobs:</span>
                                <span class="value"><?= $month['jobs'] ?></span>
                            </div>
                            <div class="month-stat">
                                <span class="label">Revenue:</span>
                                <span class="value">₱ <?= number_format($month['revenue'], 0) ?></span>
                            </div>
                            <div class="month-stat">
                                <span class="label">Expenses:</span>
                                <span class="value">₱ <?= number_format($month['expenses'], 0) ?></span>
                            </div>
                            <div class="month-stat">
                                <span class="label">Profit:</span>
                                <span class="value <?= $month['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                    ₱ <?= number_format($month['profit'], 0) ?>
                                </span>
                            </div>
                            <div class="month-stat">
                                <span class="label">Margin:</span>
                                <span class="value <?= $month['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                    <?= number_format($month['profit_percent'], 1) ?>%
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Yearly Summary -->
        <?php if (!empty($yearly_summary)): ?>
            <div class="monthly-breakdown" style="margin-top: 20px;">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-history"></i>
                        Yearly Performance Summary
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Jobs</th>
                            <th>Revenue</th>
                            <th>Expenses</th>
                            <th>Profit</th>
                            <th>Net Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($yearly_summary as $year): ?>
                            <tr>
                                <td><strong><?= $year['year'] ?></strong></td>
                                <td><?= $year['total_jobs'] ?></td>
                                <td>₱ <?= number_format($year['total_revenue'], 2) ?></td>
                                <td>₱ <?= number_format($year['total_expenses'], 2) ?></td>
                                <td class="<?= $year['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                    ₱ <?= number_format($year['total_profit'], 2) ?>
                                </td>
                                <td class="<?= $year['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                    <?= number_format($year['profit_percent'], 1) ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="flex">
            <div class="stock-cards">
                <!-- Stock Summary Card -->
                <div class="stat-card ss" style="margin-right: 20px;">
                    <div class="card-header">
                        <div>
                            <h3>Stock Summary</h3>
                        </div>
                        <div class="card-icon"><i class="fas fa-boxes"></i></div>
                    </div>

                    <div class="stock-summary">
                        <?php foreach ($grouped as $type => $products): ?>
                            <div class="product-category">
                                <div class="category-header" onclick="toggleStockTable('<?= md5($type) ?>')">
                                    <div class="category-title">
                                        <i class="fas fa-chevron-down toggle-icon"></i>
                                        <h4><?= htmlspecialchars($type) ?></h4>
                                        <span class="badge"><?= count($products) ?> items</span>
                                    </div>
                                    <div class="category-summary">
                                        <?php
                                        // Calculate summary stats for this category
                                        $totalReams = 0;
                                        $totalItems = 0;
                                        foreach ($products as $groupStocks) {
                                            foreach ($groupStocks as $reams) {
                                                if ($reams !== null) {
                                                    $totalReams += $reams;
                                                    $totalItems++;
                                                }
                                            }
                                        }
                                        ?>
                                        <div class="summary-item">
                                            <span>Total:</span>
                                            <strong><?= number_format($totalReams, 1) ?> reams</strong>
                                        </div>
                                        <?php
                                        // Check if any items in this category are low
                                        $has_low = false;
                                        foreach ($products as $groupStocks) {
                                            foreach ($groupStocks as $reams) {
                                                if ($reams !== null && $reams < 20) {
                                                    $has_low = true;
                                                    break;
                                                }
                                            }
                                        }
                                        ?>
                                        <?php if ($has_low): ?>
                                            <span class="badge" style="background: var(--warning-bg); color: var(--warning);">⚠️ Low stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="stock-table-container" id="table-<?= md5($type) ?>">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th class="product-name">Product</th>
                                                <?php
                                                $all_groups = [];
                                                foreach ($products as $pname => $groupStocks) {
                                                    foreach ($groupStocks as $grp => $_) $all_groups[$grp] = true;
                                                }
                                                $columns = array_keys($all_groups);
                                                foreach ($columns as $grp):
                                                ?>
                                                    <th class="text-center"><?= htmlspecialchars($grp) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $pname => $groupStocks): ?>
                                                <tr>
                                                    <td class="product-name"><?= htmlspecialchars($pname) ?></td>
                                                    <?php foreach ($columns as $grp): ?>
                                                        <?php
                                                        $reams = $groupStocks[$grp] ?? null;
                                                        if ($reams !== null) {
                                                            $class = 'low';
                                                            if ($reams >= 80) $class = 'high';
                                                            else if ($reams >= 20) $class = 'mid';
                                                            $percentage = min(100, ($reams / 100) * 100);
                                                        }
                                                        ?>
                                                        <td class="text-center">
                                                            <?php if ($reams !== null): ?>
                                                                <div class="stock-indicator <?= $class ?>">
                                                                    <div class="stock-value <?= $reams < 20 ? 'text-danger fw-bold' : '' ?>">
                                                                        <?= number_format($reams, 1) ?>
                                                                    </div>
                                                                    <div class="stock-bar">
                                                                        <div class="bar-fill" style="width: <?= $percentage ?>%"></div>
                                                                    </div>
                                                                    <div class="stock-label">reams</div>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="na">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="table-card" style="margin-bottom: 0;">
                <h3><i class="fas fa-truck"></i> Recent Deliveries</h3>
                <?php if ($recent_deliveries->num_rows == 0): ?>
                    <div class="empty-message">
                        <i class="fas fa-box-open" style="font-size: 40px; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No recent deliveries</p>
                        <a href="delivery.php" style="color: var(--primary); text-decoration: none;">Record a delivery →</a>
                    </div>
                <?php else: ?>
                    <div class="recent-tables">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Reams</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $recent_deliveries->fetch_assoc()): ?>
                                    <tr class="clickable-row" data-id="<?= $row['product_id'] ?>">
                                        <td><?= date("M j, Y", strtotime($row['delivery_date'])) ?></td>
                                        <td><?= "{$row['product_type']} - {$row['product_group']} - {$row['product_name']}" ?></td>
                                        <td><strong><?= number_format($row['delivered_reams'], 2) ?></strong></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card">
            <h3><i class="fas fa-history"></i> Recent Job Orders</h3>
            <?php if (empty($recent_orders)): ?>
                <div class="empty-message">
                    <i class="fas fa-clipboard-list" style="font-size: 40px; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>No recent job orders</p>
                    <a href="job_orders.php?action=new" style="color: var(--primary); text-decoration: none;">Create a job order →</a>
                </div>
            <?php else: ?>
                <div class="recent-tables table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Expenses</th>
                                <th>Revenue</th>
                                <th>Profit</th>
                                <th>Created By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order):
                                $total_expenses = floatval($order['total_expenses'] ?? 0);
                                $total_cost = floatval($order['total_cost'] ?? 0);
                                // Recalculate profit safely from the two fields
                                $profit = ($total_expenses > 0 && $total_cost > 0)
                                    ? $total_cost - $total_expenses
                                    : null;
                                $profit_class = ($profit !== null && $profit >= 0) ? 'profit-positive' : 'profit-negative';
                                // Net margin = profit / revenue * 100
                                $profit_percent = ($profit !== null && $total_cost > 0)
                                    ? ($profit / $total_cost) * 100
                                    : 0;

                                // Add status badge class
                                $status_class = 'status-' . str_replace('_', '-', $order['status']);
                            ?>
                                <tr class="clickable-row"
                                    data-order='<?= htmlspecialchars(json_encode($order), ENT_QUOTES, "UTF-8") ?>'
                                    data-role="<?= htmlspecialchars($_SESSION['role']) ?>">
                                    <td><?= htmlspecialchars($order['client_name']) ?></td>
                                    <td><?= htmlspecialchars($order['project_name']) ?></td>
                                    <td>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <span class="badge <?= $status_class ?> status-badge" style="cursor: pointer; padding: 5px 10px; border-radius: 20px;" onclick="event.stopPropagation(); openModal(<?= htmlspecialchars(json_encode($order), ENT_QUOTES, "UTF-8") ?>, '<?= $_SESSION['role'] ?>')">
                                                <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge <?= $status_class ?>" style="padding: 5px 10px; border-radius: 20px;">
                                                <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($total_expenses > 0): ?>
                                            ₱ <?= number_format($total_expenses, 2) ?>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:12px;">Not computed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($total_cost > 0): ?>
                                            <span class="fw-bold">₱ <?= number_format($total_cost, 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:12px;">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($profit !== null): ?>
                                            <span class="fw-bold <?= $profit_class ?>">
                                                ₱ <?= number_format($profit, 2) ?>
                                                <small>(<?= number_format($profit_percent, 1) ?>%)</small>
                                            </span>
                                        <?php elseif ($total_expenses <= 0): ?>
                                            <span class="text-muted" style="font-size:12px;">Compute expenses first</span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:12px;">Set selling price first</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($order['username'] ?? 'Unknown') ?></td>
                                    <td><?= date("M d, Y", strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 15px; text-align: right;">
                    <a href="job_orders.php" class="view-all">View all job orders <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Modal -->
    <div id="productModal" class="modal">
        <div class="floating-window" id="productModalBody"></div>
    </div>

    <div id="jobModal" class="modal">
        <!-- Content will be populated by JavaScript -->
    </div>

    <script>
        function goToLastProductPage() {
            const last = localStorage.getItem('lastProductPage');
            if (last) {
                window.location.href = last;
            } else {
                window.location.href = 'papers.php';
            }
        }

        // Toggle stock tables
        function toggleStockTable(id) {
            const container = document.getElementById(`table-${id}`);
            const icon = container.previousElementSibling.querySelector('.toggle-icon');

            if (container.style.display === 'block') {
                container.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            } else {
                container.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
        }

        // Initialize all as collapsed
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.stock-table-container').forEach(container => {
                container.style.display = 'none';
            });
        });

        // Close modal function — plays the closing animation, then hides
        const MODAL_CLOSE_ANIM_MS = 160;

        function animateModalClose(overlay, floatingWindow, afterHide) {
            if (!overlay || overlay.style.display === 'none' || overlay.style.display === '') {
                if (afterHide) afterHide();
                return;
            }
            overlay.classList.add('closing');
            if (floatingWindow) floatingWindow.classList.add('closing');

            setTimeout(() => {
                overlay.style.display = 'none';
                overlay.classList.remove('closing');
                if (floatingWindow) floatingWindow.classList.remove('closing');
                if (afterHide) afterHide();
            }, MODAL_CLOSE_ANIM_MS);
        }

        function closeModal() {
            const productModal = document.getElementById('productModal');
            const productModalBody = document.getElementById('productModalBody');
            const jobModal = document.getElementById('jobModal');
            const jobFloatingWindow = jobModal ? jobModal.querySelector('.floating-window') : null;

            animateModalClose(productModal, productModalBody, () => {
                if (productModalBody) productModalBody.innerHTML = ''; // Clear content
            });

            animateModalClose(jobModal, jobFloatingWindow);
        }

        // Click outside modal to close
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        });

        // Event delegation for ALL close buttons (static + dynamic from loaded content)
        document.addEventListener('click', function(e) {
            // Check if clicked element is the close button or inside it
            const closeBtn = e.target.closest('.close-btn');
            if (closeBtn) {
                e.preventDefault();
                e.stopPropagation();
                closeModal();
                return;
            }
        });

        // Clickable rows for product details (keep your existing fetch logic, just make sure it's not conflicting)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.clickable-row[data-id]').forEach(row => {
                row.addEventListener('click', function(e) {
                    if (e.target.closest('.status-badge')) return;
                    const productId = this.dataset.id;
                    if (!productId) return;

                    fetch(`product_info.php?id=${productId}`)
                        .then(res => {
                            if (!res.ok) throw new Error(`Failed to fetch: ${res.status}`);
                            return res.text();
                        })
                        .then(html => {
                            const modalBody = document.getElementById('productModalBody');
                            modalBody.innerHTML = html;
                            document.getElementById('productModal').style.display = 'flex';
                        })
                        .catch(err => {
                            document.getElementById('productModalBody').innerHTML = `
                        <div style="text-align:center; padding:40px; color:var(--danger);">
                            <i class="fas fa-exclamation-circle fa-3x" style="margin-bottom:20px;"></i>
                            <p><strong>Error loading product details</strong></p>
                            <small>${err.message}</small>
                        </div>
                    `;
                            document.getElementById('productModal').style.display = 'flex';
                        });
                });
            });
        });

        // Scroll position persistence
        const scrollKey = `scroll-position-/dashboard.php`;
        window.addEventListener('DOMContentLoaded', () => {
            const scrollY = sessionStorage.getItem(scrollKey);
            if (scrollY !== null) {
                window.scrollTo(0, parseInt(scrollY));
            }
        });

        window.addEventListener('scroll', () => {
            sessionStorage.setItem(scrollKey, window.scrollY);
        });

        // Clickable rows for job orders
        document.querySelectorAll('.clickable-row[data-order]').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.status-badge')) return;
                const orderData = JSON.parse(this.dataset.order);
                const userRole = this.dataset.role;
                openModal(orderData, userRole);
            });
        });

        function openModal(order, userRole) {
            function applyStatusColor(selectEl) {
                const status = selectEl.value;
                selectEl.classList.remove('status-pending', 'status-unpaid', 'status-for_delivery', 'status-completed');
                selectEl.classList.add(`status-${status}`);
            }

            const modal = document.getElementById('jobModal');

            let html = `
    <div class="floating-window">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-file-invoice"></i>
          Job Order #${order.id} - ${order.project_name || 'Untitled'}
        </div>
        <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
      </div>

      <div class="window-content">
        <!-- Quick Summary -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; background: var(--light); padding: 15px; border-radius: 8px;">
            <div style="text-align: center;">
                <div style="font-size: 12px; color: var(--gray);">Status</div>
                <div><span class="badge status-${order.status}" style="padding: 5px 10px;">${ucfirst(order.status)}</span></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 12px; color: var(--gray);">Profit</div>
                <div class="${(order.grand_total > 0 && order.total_cost > 0) ? ((order.total_cost - order.grand_total >= 0) ? 'profit-positive' : 'profit-negative') : 'text-muted'} fw-bold">${(order.grand_total > 0 && order.total_cost > 0) ? '₱' + number_format(order.total_cost - order.grand_total, 2) : 'N/A'}</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 12px; color: var(--gray);">Quantity</div>
                <div class="fw-bold">${order.quantity} pcs</div>
            </div>
        </div>

        <!-- Client Information Section -->
        <div class="section-header">
          <i class="fas fa-building"></i>
          Client Information
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Company</strong>
            <span>${order.client_name || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Person</strong>
            <span>${order.contact_person || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Number</strong>
            <span>${order.contact_number || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>TIN</strong>
            <span>${order.tin || 'None'}</span>
          </div>
        </div>

        <!-- Project Details Section -->
        <div class="section-header">
          <i class="fas fa-clipboard-list"></i>
          Project Details
        </div>
        <div class="stock-summary-compact">
          <div class="stock-card-compact">
            <h4>Order Quantity</h4>
            <div class="stock-value-compact">${order.quantity}</div>
            <div class="stock-unit-compact">pieces</div>
          </div>
          <div class="stock-card-compact">
            <h4>Sets per Bind</h4>
            <div class="stock-value-compact">${order.number_of_sets}</div>
            <div class="stock-unit-compact">sets</div>
          </div>
          <div class="stock-card-compact">
            <h4>Copies per Set</h4>
            <div class="stock-value-compact">${order.copies_per_set}</div>
            <div class="stock-unit-compact">copies</div>
          </div>
        </div>

        <!-- Specifications Section -->
        <div class="section-header">
          <i class="fas fa-tools"></i>
          Specifications
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Paper Size</strong>
            <span>${order.paper_size === 'custom' ? order.custom_paper_size : order.paper_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Paper Type</strong>
            <span>${order.paper_type}</span>
          </div>
          <div class="info-item-compact">
            <strong>Cut Size</strong>
            <span>${order.product_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Binding</strong>
            <span>${order.binding_type === 'Custom' ? order.custom_binding : order.binding_type}</span>
          </div>
        </div>

        <!-- Financial Summary -->
        <div class="section-header">
          <i class="fas fa-coins"></i>
          Financial Summary
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
            <div style="background: var(--light); padding: 10px; border-radius: 6px;">
                <div style="font-size: 11px; color: var(--gray);">Production Cost</div>
                <div class="fw-bold">${order.grand_total > 0 ? '&#8369;' + number_format(order.grand_total, 2) : '<span style=&quot;font-size:12px;color:#999;&quot;>Not computed</span>'}</div>
            </div>
            <div style="background: var(--light); padding: 10px; border-radius: 6px;">
                <div style="font-size: 11px; color: var(--gray);">Selling Price</div>
                <div class="fw-bold">${order.total_cost > 0 ? '&#8369;' + number_format(order.total_cost, 2) : '<span style=&quot;font-size:12px;color:#999;&quot;>Not set</span>'}</div>
            </div>
            <div style="background: var(--light); padding: 10px; border-radius: 6px;">
                <div style="font-size: 11px; color: var(--gray);">Profit</div>
                <div class="fw-bold ${(order.grand_total > 0 && order.total_cost > 0) ? ((order.total_cost - order.grand_total >= 0) ? 'profit-positive' : 'profit-negative') : ''}">${(order.grand_total > 0 && order.total_cost > 0) ? '&#8369;' + number_format(order.total_cost - order.grand_total, 2) : '<span style=&quot;font-size:12px;color:#999;&quot;>N/A</span>'}</div>
            </div>
        </div>

        <!-- Special Instructions -->










        <!-- Special Instructions -->
        <div class="section-header">
          <i class="fas fa-comment-alt"></i>
          Special Instructions
        </div>
        <div class="special-instructions">
          ${order.special_instructions ? order.special_instructions.replace(/\n/g, '<br>') : '<div class="empty-state"><p><i class="fas fa-info-circle"></i> No special instructions provided</p></div>'}
        </div>
  `;

            if (userRole === 'admin') {
                const statuses = ['pending', 'unpaid', 'for_delivery', 'completed'];
                const currentStatus = order.status;
                const options = statuses.map(status => {
                    const selected = status === currentStatus ? 'selected' : '';
                    const label = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
                    return `<option value="${status}" ${selected}>${label}</option>`;
                }).join('');

                html += `
          <div class="section-header">
            <i class="fas fa-cog"></i>
            Actions
          </div>
          <div class="action-buttons" style="flex-wrap: wrap;">
            <form class="status-toggle-form" data-job-id="${order.id}" style="display: flex; gap: 12px; flex-wrap: wrap;">
              <select name="new_status" class="status-select" style="padding: 8px 12px;">
                ${options}
              </select>
              <button type="submit" class="btn-status" style="padding: 8px 15px;">
                <i class="fas fa-sync-alt"></i> Update
              </button>
            </form>
            <a href="edit_job.php?id=${order.id}" class="btn-edit" style="padding: 8px 15px;">
              <i class="fas fa-edit"></i> Edit
            </a>
            <a href="delete_job.php?id=${order.id}" class="btn-delete" style="padding: 8px 15px;" onclick="return confirm('Are you sure you want to delete this job order? This action cannot be undone.')">
              <i class="fas fa-trash-alt"></i> Delete
            </a>
          </div>
        `;
            }

            html += `
            </div>
          </div>
        `;

            modal.innerHTML = html;
            modal.style.display = 'flex';

            // Attach form submit handler
            const statusForm = modal.querySelector('.status-toggle-form');
            if (statusForm) {
                statusForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const jobId = this.dataset.jobId;
                    const newStatus = this.querySelector('select[name="new_status"]').value;

                    fetch('update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `job_id=${encodeURIComponent(jobId)}&new_status=${encodeURIComponent(newStatus)}`
                        })
                        .then(response => response.text())
                        .then(data => {
                            location.reload();
                        })
                        .catch(err => {
                            alert('Status update failed. Please try again.');
                            console.error(err);
                        });
                });
            }

            // Apply status color
            const select = modal.querySelector('.status-select');
            if (select) {
                applyStatusColor(select);
                select.addEventListener('change', () => applyStatusColor(select));
            }
        }

        // Utility functions
        function ucfirst(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ');
        }

        function number_format(number, decimals) {
            return new Intl.NumberFormat('en-PH', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(number);
        }

        // Add keyboard shortcut to close modals (ESC key)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>

</html>