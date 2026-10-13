<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../permissions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee', 'super_admin'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// CSRF protection: every POST on this page must carry this session's token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

// Reports are all revenue / sales figures: only for users the super admin switched on.
if (!can('web_finance')) {
    http_response_code(403);
    require_once 'admin_layout.php';
    admin_layout_start(['page' => 'reports', 'title' => 'Reports']);
    ?>
    <div class="card">
        <div class="state state--lock">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <h3>Permission required</h3>
            <p><?php echo htmlspecialchars(permission_denied_message('web_finance')); ?></p>
            <a class="btn btn-secondary btn-sm" href="admin_dashboard.php">Back to dashboard</a>
        </div>
    </div>
    <?php
    admin_layout_end();
    ?>
    <script src="../../assets/js/website_admin.js"></script>
    </body>

    </html>
    <?php
    exit;
}

// Get date range filters
// Only real dates and known report types are accepted (anything else falls back to the default)
$start_date = valid_ymd($_GET['start_date'] ?? '') ?? date('Y-m-01'); // First day of current month
$end_date = valid_ymd($_GET['end_date'] ?? '') ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'sales';
if (!in_array($report_type, ['sales', 'customers', 'products'], true)) {
    $report_type = 'sales';
}

// Validate dates
if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Build date condition for queries
$date_condition = "o.created_at BETWEEN ? AND ?";
$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$types = 'ss';

// Single definition of "which order statuses count as revenue", used by every
// query below so Sales / Customers / Products reports (and the dashboard,
// and the CSV export) all agree on the same number. 'pending' orders are not
// yet paid for, so they are excluded here.
$REVENUE_STATUSES = ['paid', 'processing', 'ready_for_pickup', 'completed'];
$revenue_status_sql = "'" . implode("','", array_map([$inventory, 'real_escape_string'], $REVENUE_STATUSES)) . "'";

// Get sales report data
// Get sales report data
if ($report_type === 'sales') {
    // Debug: Check what dates are being used
    error_log("Date range: $start_date to $end_date");

    // Total sales with COALESCE to handle NULL values
    $sales_query = "SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(AVG(total_amount), 0) as avg_order_value,
        COUNT(DISTINCT user_id) as unique_customers
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ? 
        AND o.status IN ($revenue_status_sql)";

    $sales_stmt = $inventory->prepare($sales_query);

    // Format dates properly for MySQL
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';

    $sales_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $sales_stmt->execute();
    $sales_result = $sales_stmt->get_result();

    if ($sales_result) {
        $sales_data = $sales_result->fetch_assoc();

        // Debug output (remove in production)
        echo "<!-- Sales Data: " . print_r($sales_data, true) . " -->";

        // Ensure we have values even if NULL
        $sales_data = [
            'total_orders' => $sales_data['total_orders'] ?? 0,
            'total_revenue' => $sales_data['total_revenue'] ?? 0,
            'avg_order_value' => $sales_data['avg_order_value'] ?? 0,
            'unique_customers' => $sales_data['unique_customers'] ?? 0
        ];
    } else {
        // Handle query error
        $sales_data = [
            'total_orders' => 0,
            'total_revenue' => 0,
            'avg_order_value' => 0,
            'unique_customers' => 0
        ];
        echo "<!-- Query Error: " . $sales_stmt->error . " -->";
    }

    // Daily sales trend
    $daily_sales_query = "SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as order_count,
        COALESCE(SUM(o.total_amount), 0) as daily_revenue
        FROM orders o
        WHERE o.created_at BETWEEN ? AND ? 
        AND o.status IN ($revenue_status_sql)
        GROUP BY DATE(o.created_at)
        ORDER BY date";

    $daily_sales_stmt = $inventory->prepare($daily_sales_query);
    $daily_sales_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $daily_sales_stmt->execute();
    $daily_sales_result = $daily_sales_stmt->get_result();
    $daily_sales = $daily_sales_result ? $daily_sales_result->fetch_all(MYSQLI_ASSOC) : [];

    // Top products
    $top_products_query = "SELECT 
        oi.product_name,
        COALESCE(SUM(oi.quantity), 0) as total_sold,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.created_at BETWEEN ? AND ? 
        AND o.status IN ($revenue_status_sql)
        GROUP BY oi.product_name
        ORDER BY total_sold DESC
        LIMIT 10";

    $top_products_stmt = $inventory->prepare($top_products_query);
    $top_products_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $top_products_stmt->execute();
    $top_products_result = $top_products_stmt->get_result();
    $top_products = $top_products_result ? $top_products_result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get customer report data
if ($report_type === 'customers') {
    // Customer statistics
    $customer_stats_query = "SELECT 
        COUNT(DISTINCT co.user_id) as active_customers,
        COALESCE(AVG(co.order_count), 0) as avg_orders_per_customer,
        COALESCE(AVG(co.total_spent), 0) as avg_customer_value
        FROM (
            SELECT user_id, COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as total_spent
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ? 
            AND o.status IN ($revenue_status_sql)
            GROUP BY user_id
        ) as co";

    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';

    $customer_stats_stmt = $inventory->prepare($customer_stats_query);
    $customer_stats_stmt->bind_param('ss', $start_datetime, $end_datetime);
    $customer_stats_stmt->execute();
    $customer_stats_result = $customer_stats_stmt->get_result();
    $customer_stats = $customer_stats_result ? $customer_stats_result->fetch_assoc() : [
        'active_customers' => 0,
        'avg_orders_per_customer' => 0,
        'avg_customer_value' => 0
    ];

    // Top customers
    $top_customers_query = "SELECT 
        u.username,
        pc.first_name,
        pc.last_name,
        COUNT(o.order_id) as order_count,
        SUM(o.total_amount) as total_spent
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN personal_customers pc ON u.id = pc.user_id
        WHERE $date_condition AND o.status IN ($revenue_status_sql)
        GROUP BY o.user_id
        ORDER BY total_spent DESC
        LIMIT 10";

    $top_customers_stmt = $inventory->prepare($top_customers_query);
    $top_customers_stmt->bind_param($types, ...$params);
    $top_customers_stmt->execute();
    $top_customers = $top_customers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get product report data
if ($report_type === 'products') {
    // Product performance
    $product_performance_query = "SELECT 
        p.product_name,
        p.category,
        p.price,
        COUNT(oi.order_item_id) as times_ordered,
        COALESCE(SUM(oi.quantity), 0) as total_quantity,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue
        FROM products_offered p
        LEFT JOIN (
            SELECT oi.*
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE $date_condition AND o.status IN ($revenue_status_sql)
        ) oi ON p.id = oi.product_id
        GROUP BY p.id, p.product_name, p.category, p.price
        ORDER BY total_revenue DESC";

    $product_performance_stmt = $inventory->prepare($product_performance_query);
    $product_performance_stmt->bind_param($types, ...$params);
    $product_performance_stmt->execute();
    $product_performance = $product_performance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Category performance
    $category_performance_query = "SELECT 
        p.category,
        COUNT(oi.order_item_id) as total_orders,
        COALESCE(SUM(oi.quantity), 0) as total_quantity,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue
        FROM products_offered p
        LEFT JOIN (
            SELECT oi.*
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE $date_condition AND o.status IN ($revenue_status_sql)
        ) oi ON p.id = oi.product_id
        GROUP BY p.category
        ORDER BY total_revenue DESC";

    $category_performance_stmt = $inventory->prepare($category_performance_query);
    $category_performance_stmt->bind_param($types, ...$params);
    $category_performance_stmt->execute();
    $category_performance = $category_performance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get order status distribution
$status_distribution_query = "SELECT 
    o.status,
    COUNT(*) as order_count,
    SUM(o.total_amount) as total_amount
    FROM orders o
    WHERE $date_condition
    GROUP BY o.status
    ORDER BY order_count DESC";

$status_distribution_stmt = $inventory->prepare($status_distribution_query);
$status_distribution_stmt->bind_param($types, ...$params);
$status_distribution_stmt->execute();
$status_distribution = $status_distribution_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build a structured export payload for the currently active report.
// This drives the "Export CSV" button: it carries the summary stats plus
// every detail table's rows (not just whatever happens to be visible on
// screen), so the export is complete even while the detail table below is
// collapsed.
$export_payload = [
    'report_type' => $report_type,
    'date_range' => "$start_date to $end_date",
    'summary' => [],
    'sections' => [],
];

if ($report_type === 'sales') {
    $export_payload['summary'] = [
        'Total Orders' => $sales_data['total_orders'],
        'Total Revenue' => round((float) $sales_data['total_revenue'], 2),
        'Average Order Value' => round((float) $sales_data['avg_order_value'], 2),
        'Unique Customers' => $sales_data['unique_customers'],
    ];
    $export_payload['sections'][] = [
        'title' => 'Top Selling Products',
        'headers' => ['Product', 'Quantity Sold', 'Total Revenue'],
        'rows' => array_map(function ($p) { return [$p['product_name'], $p['total_sold'], round((float) $p['total_revenue'], 2)]; }, $top_products),
    ];
} elseif ($report_type === 'customers') {
    $export_payload['summary'] = [
        'Active Customers' => $customer_stats['active_customers'],
        'Avg Orders per Customer' => round((float) $customer_stats['avg_orders_per_customer'], 1),
        'Avg Customer Value' => round((float) $customer_stats['avg_customer_value'], 2),
    ];
    $export_payload['sections'][] = [
        'title' => 'Top Customers',
        'headers' => ['Customer', 'Username', 'Orders', 'Total Spent'],
        'rows' => array_map(
            function ($c) {
                return [trim($c['first_name'] . ' ' . $c['last_name']), $c['username'], $c['order_count'], round((float) $c['total_spent'], 2)];
            },
            $top_customers
        ),
    ];
} elseif ($report_type === 'products') {
    $export_payload['sections'][] = [
        'title' => 'Product Performance',
        'headers' => ['Product', 'Category', 'Price', 'Times Ordered', 'Total Quantity', 'Total Revenue'],
        'rows' => array_map(
            function ($p) {
                return [$p['product_name'], $p['category'], round((float) $p['price'], 2), $p['times_ordered'], $p['total_quantity'], round((float) $p['total_revenue'], 2)];
            },
            $product_performance
        ),
    ];
    $export_payload['sections'][] = [
        'title' => 'Category Performance',
        'headers' => ['Category', 'Total Orders', 'Total Quantity', 'Total Revenue'],
        'rows' => array_map(
            function ($c) {
                return [$c['category'], $c['total_orders'], $c['total_quantity'], round((float) $c['total_revenue'], 2)];
            },
            $category_performance
        ),
    ];
}
?>

<?php
require_once 'admin_layout.php';

$range_label = date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date));
$report_titles = ['sales' => 'Sales report', 'customers' => 'Customer report', 'products' => 'Product report'];
admin_layout_start([
    'page' => 'reports',
    'title' => 'Reports',
    'subtitle' => $report_titles[$report_type] . ': ' . $range_label,
    'chart' => true,
    'actions' => '<button type="button" class="btn btn-secondary" data-report-action="export"><i class="fas fa-download" aria-hidden="true"></i> Export CSV</button>',
]);
?>

<!-- Report type -->
<div class="report-tabs tabs" role="tablist" aria-label="Report type">
    <?php foreach ([['sales', 'fa-chart-line', 'Sales'], ['customers', 'fa-users', 'Customers'], ['products', 'fa-box', 'Products']] as [$key, $icon, $label]): ?>
        <button type="button" role="tab" aria-selected="<?php echo $report_type === $key ? 'true' : 'false'; ?>"
            class="tab-btn <?php echo $report_type === $key ? 'active' : ''; ?>" data-report-action="type" data-type="<?php echo $key; ?>">
            <i class="fas <?php echo $icon; ?>" aria-hidden="true"></i> <?php echo $label; ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Date range -->
<div class="report-filters">
    <form method="GET" id="reportForm">
        <input type="hidden" name="report_type" id="reportType" value="<?php echo esc_html($report_type); ?>">
        <div class="filter-row">
            <div class="form-group">
                <label for="start_date">From</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo esc_html($start_date); ?>" required>
            </div>
            <div class="form-group">
                <label for="end_date">To</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo esc_html($end_date); ?>" required>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                <button type="button" class="btn btn-secondary" data-report-action="reset"><i class="fas fa-rotate-left" aria-hidden="true"></i> This month</button>
            </div>
        </div>
    </form>
</div>

<?php
$toggle_btn = '<button type="button" class="btn btn-secondary detail-toggle-btn" data-report-action="toggle" aria-controls="detailTables">'
    . '<i id="toggleDetailIcon" class="fas fa-chevron-up" aria-hidden="true"></i> <span id="toggleDetailLabel">Hide detailed tables</span></button>';
?>

<?php if ($report_type === 'sales'): ?>
    <div class="kpi-grid">
        <div class="kpi"><span class="kpi-label"><i class="fas fa-receipt" aria-hidden="true"></i> Orders</span><span class="kpi-value"><?php echo (int) ($sales_data['total_orders'] ?? 0); ?></span><span class="kpi-meta">Paid or later</span></div>
        <div class="kpi"><span class="kpi-label"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Revenue</span><span class="kpi-value">&#8369;<?php echo number_format($sales_data['total_revenue'] ?? 0, 2); ?></span><span class="kpi-meta">In this period</span></div>
        <div class="kpi"><span class="kpi-label"><i class="fas fa-chart-pie" aria-hidden="true"></i> Average order</span><span class="kpi-value">&#8369;<?php echo number_format($sales_data['avg_order_value'] ?? 0, 2); ?></span><span class="kpi-meta">Revenue per order</span></div>
        <div class="kpi"><span class="kpi-label"><i class="fas fa-users" aria-hidden="true"></i> Unique customers</span><span class="kpi-value"><?php echo (int) ($sales_data['unique_customers'] ?? 0); ?></span><span class="kpi-meta">Who ordered</span></div>
    </div>

    <div class="report-grid">
        <section class="card chart-card" aria-labelledby="trendTitle">
            <div class="card-head"><h2 class="card-title" id="trendTitle">Sales trend</h2><span class="card-sub">Daily revenue</span></div>
            <?php if (empty($daily_sales)): ?>
                <div class="empty-state"><i class="fas fa-chart-line" aria-hidden="true"></i><h3>No sales in this period</h3><p>Pick a wider date range to see a trend.</p></div>
            <?php else: ?>
                <div class="chart-box"><canvas id="salesTrendChart" role="img" aria-label="Daily revenue over the selected period"></canvas></div>
            <?php endif; ?>
        </section>
        <section class="card chart-card" aria-labelledby="statusTitle">
            <div class="card-head"><h2 class="card-title" id="statusTitle">Order status</h2><span class="card-sub">All orders in range</span></div>
            <?php if (empty($status_distribution)): ?>
                <div class="empty-state"><i class="fas fa-chart-pie" aria-hidden="true"></i><h3>No orders in this period</h3></div>
            <?php else: ?>
                <div class="chart-box"><canvas id="statusChart" role="img" aria-label="Order status distribution"></canvas></div>
            <?php endif; ?>
        </section>
    </div>

    <?php echo $toggle_btn; ?>
    <div id="detailTables" class="detail-tables">
        <section class="data-table">
            <div class="card-head"><h2 class="card-title">Top selling products</h2><span class="card-sub">Top 10 by units sold</span></div>
            <?php if (empty($top_products)): ?>
                <div class="empty-state"><i class="fas fa-box-open" aria-hidden="true"></i><h3>No product sales in this period</h3></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th scope="col">Product</th><th scope="col" class="num">Quantity sold</th><th scope="col" class="num">Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($top_products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td class="num"><?php echo (int) $product['total_sold']; ?></td>
                                <td class="num">&#8369;<?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>

<?php if ($report_type === 'customers'): ?>
    <div class="kpi-grid">
        <div class="kpi"><span class="kpi-label"><i class="fas fa-users" aria-hidden="true"></i> Active customers</span><span class="kpi-value"><?php echo (int) ($customer_stats['active_customers'] ?? 0); ?></span><span class="kpi-meta">Ordered in this period</span></div>
        <div class="kpi"><span class="kpi-label"><i class="fas fa-receipt" aria-hidden="true"></i> Orders per customer</span><span class="kpi-value"><?php echo round($customer_stats['avg_orders_per_customer'] ?? 0, 1); ?></span><span class="kpi-meta">Average</span></div>
        <div class="kpi"><span class="kpi-label"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Value per customer</span><span class="kpi-value">&#8369;<?php echo number_format($customer_stats['avg_customer_value'] ?? 0, 2); ?></span><span class="kpi-meta">Average spend</span></div>
    </div>

    <?php echo $toggle_btn; ?>
    <div id="detailTables" class="detail-tables">
        <section class="data-table">
            <div class="card-head"><h2 class="card-title">Top customers</h2><span class="card-sub">Top 10 by spend</span></div>
            <?php if (empty($top_customers)): ?>
                <div class="empty-state"><i class="fas fa-user-slash" aria-hidden="true"></i><h3>No customer orders in this period</h3></div>
            <?php else: ?>
            <div class="table-scroll">
                <table class="table">
                    <thead><tr><th scope="col">Customer</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Total spent</th></tr></thead>
                    <tbody>
                        <?php foreach ($top_customers as $customer):
                            $cname = trim($customer['first_name'] . ' ' . $customer['last_name']); ?>
                            <tr>
                                <td>
                                    <div class="cell-stack">
                                        <span class="cell-main"><?php echo htmlspecialchars($cname !== '' ? $cname : $customer['username']); ?></span>
                                        <?php if ($cname !== ''): ?><span class="cell-sub"><?php echo htmlspecialchars($customer['username']); ?></span><?php endif; ?>
                                    </div>
                                </td>
                                <td class="num"><?php echo (int) $customer['order_count']; ?></td>
                                <td class="num">&#8369;<?php echo number_format($customer['total_spent'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>

<?php if ($report_type === 'products'):
    $products_total_revenue = array_sum(array_column($category_performance, 'total_revenue'));
    $top_category = $category_performance[0]['category'] ?? '—';
?>
    <div class="kpi-grid">
        <div class="kpi"><span class="kpi-label"><i class="fas fa-box" aria-hidden="true"></i> Products listed</span><span class="kpi-value"><?php echo count($product_performance); ?></span><span class="kpi-meta">In the catalog</span></div>
        <div class="kpi"><span class="kpi-label"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Revenue</span><span class="kpi-value">&#8369;<?php echo number_format($products_total_revenue, 2); ?></span><span class="kpi-meta">In this period</span></div>
        <div class="kpi"><span class="kpi-label"><i class="fas fa-crown" aria-hidden="true"></i> Top category</span><span class="kpi-value"><?php echo htmlspecialchars($top_category); ?></span><span class="kpi-meta">By revenue</span></div>
    </div>

    <?php echo $toggle_btn; ?>
    <div id="detailTables" class="detail-tables">
        <section class="data-table">
            <div class="card-head"><h2 class="card-title">Category performance</h2></div>
            <div class="table-scroll">
                <table class="table" data-paginate="10">
                    <thead><tr><th scope="col">Category</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Quantity</th><th scope="col" class="num">Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($category_performance as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['category']); ?></td>
                                <td class="num"><?php echo (int) $category['total_orders']; ?></td>
                                <td class="num"><?php echo (int) $category['total_quantity']; ?></td>
                                <td class="num">&#8369;<?php echo number_format($category['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="data-table">
            <div class="card-head"><h2 class="card-title">Product performance</h2><span class="card-sub">Sorted by revenue</span></div>
            <div class="table-scroll">
                <table class="table" data-paginate="10">
                    <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col" class="num">Price</th><th scope="col" class="num">Times ordered</th><th scope="col" class="num">Quantity</th><th scope="col" class="num">Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($product_performance as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><span class="category-badge"><?php echo htmlspecialchars($product['category']); ?></span></td>
                                <td class="num">&#8369;<?php echo number_format($product['price'], 2); ?></td>
                                <td class="num"><?php echo (int) $product['times_ordered']; ?></td>
                                <td class="num"><?php echo (int) $product['total_quantity']; ?></td>
                                <td class="num">&#8369;<?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
<?php endif; ?>

<?php admin_layout_end(); ?>

<?php
$wa_charts = ($report_type === 'sales') ? [
    'salesLabels' => array_column($daily_sales, 'date'),
    'salesValues' => array_column($daily_sales, 'daily_revenue'),
    'statusLabels' => array_map(function ($status) {
        return ucfirst(str_replace('_', ' ', $status['status']));
    }, $status_distribution),
    'statusValues' => array_column($status_distribution, 'order_count'),
] : null;
$wa_data = [
    'exportData' => $export_payload,
    'charts' => $wa_charts,
];
?>
<script>
    window.WA_CONFIG = {
        csrfToken: <?php echo esc_js(csrf_token()); ?>,
        flash: {
            message: <?php echo isset($_SESSION['message']) ? esc_js($_SESSION['message']) : 'null'; ?>,
            error: <?php echo isset($_SESSION['error']) ? esc_js($_SESSION['error']) : 'null'; ?>
        },
        data: <?php echo json_encode($wa_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    };
    <?php unset($_SESSION['message'], $_SESSION['error']); ?>
</script>
<script src="../../assets/js/website_admin.js"></script>
</body>

</html>