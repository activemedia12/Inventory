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
    <link rel="stylesheet" href="../assets/css/pages/dashboard.css" />
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

    <script src="../assets/js/pages/dashboard.js"></script>
</body>

</html>