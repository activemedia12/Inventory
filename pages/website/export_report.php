<?php
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

// Get parameters
$report_type = $_GET['report_type'] ?? 'sales';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Validate dates
if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

// Build date condition
$date_condition = "o.created_at BETWEEN ? AND ?";
$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$types = 'ss';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . $start_date . '_to_' . $end_date . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

switch ($report_type) {
    case 'sales':
        exportSalesReport($output, $inventory, $date_condition, $params, $types);
        break;
    case 'customers':
        exportCustomersReport($output, $inventory, $date_condition, $params, $types);
        break;
    case 'products':
        exportProductsReport($output, $inventory, $date_condition, $params, $types);
        break;
    default:
        fputcsv($output, ['Error', 'Invalid report type']);
        break;
}

fclose($output);
exit;

// Sales Report Export
function exportSalesReport($output, $tshirtprint, $date_condition, $params, $types) {
    // Write header
    fputcsv($output, ['Sales Report - ' . $_GET['start_date'] . ' to ' . $_GET['end_date']]);
    fputcsv($output, []); // Empty row
    
    // Total sales summary
    $sales_query = "SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value,
        COUNT(DISTINCT user_id) as unique_customers
        FROM orders o
        WHERE $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')";
    
    $sales_stmt = $tshirtprint->prepare($sales_query);
    $sales_stmt->bind_param($types, ...$params);
    $sales_stmt->execute();
    $sales_data = $sales_stmt->get_result()->fetch_assoc();
    
    // Summary section
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Orders', $sales_data['total_orders']]);
    fputcsv($output, ['Total Revenue', '₱' . number_format($sales_data['total_revenue'], 2)]);
    fputcsv($output, ['Average Order Value', '₱' . number_format($sales_data['avg_order_value'], 2)]);
    fputcsv($output, ['Unique Customers', $sales_data['unique_customers']]);
    fputcsv($output, []); // Empty row
    
    // Daily sales trend
    fputcsv($output, ['DAILY SALES TREND']);
    fputcsv($output, ['Date', 'Orders', 'Revenue']);
    
    $daily_sales_query = "SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as order_count,
        SUM(o.total_amount) as daily_revenue
        FROM orders o
        WHERE $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY DATE(o.created_at)
        ORDER BY date";
    
    $daily_sales_stmt = $tshirtprint->prepare($daily_sales_query);
    $daily_sales_stmt->bind_param($types, ...$params);
    $daily_sales_stmt->execute();
    $daily_sales = $daily_sales_stmt->get_result();
    
    while ($row = $daily_sales->fetch_assoc()) {
        fputcsv($output, [
            $row['date'],
            $row['order_count'],
            '₱' . number_format($row['daily_revenue'], 2)
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // Top products
    fputcsv($output, ['TOP PRODUCTS']);
    fputcsv($output, ['Product Name', 'Quantity Sold', 'Total Revenue']);
    
    $top_products_query = "SELECT 
        oi.product_name,
        SUM(oi.quantity) as total_sold,
        SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY oi.product_name
        ORDER BY total_sold DESC
        LIMIT 20";
    
    $top_products_stmt = $tshirtprint->prepare($top_products_query);
    $top_products_stmt->bind_param($types, ...$params);
    $top_products_stmt->execute();
    $top_products = $top_products_stmt->get_result();
    
    while ($row = $top_products->fetch_assoc()) {
        fputcsv($output, [
            $row['product_name'],
            $row['total_sold'],
            '₱' . number_format($row['total_revenue'], 2)
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // Order status distribution
    fputcsv($output, ['ORDER STATUS DISTRIBUTION']);
    fputcsv($output, ['Status', 'Order Count', 'Total Amount']);
    
    $status_query = "SELECT 
        o.status,
        COUNT(*) as order_count,
        SUM(o.total_amount) as total_amount
        FROM orders o
        WHERE $date_condition
        GROUP BY o.status
        ORDER BY order_count DESC";
    
    $status_stmt = $tshirtprint->prepare($status_query);
    $status_stmt->bind_param($types, ...$params);
    $status_stmt->execute();
    $status_data = $status_stmt->get_result();
    
    while ($row = $status_data->fetch_assoc()) {
        fputcsv($output, [
            ucfirst(str_replace('_', ' ', $row['status'])),
            $row['order_count'],
            '₱' . number_format($row['total_amount'], 2)
        ]);
    }
}

// Customers Report Export
function exportCustomersReport($output, $tshirtprint, $date_condition, $params, $types) {
    // Write header
    fputcsv($output, ['Customers Report - ' . $_GET['start_date'] . ' to ' . $_GET['end_date']]);
    fputcsv($output, []); // Empty row
    
    // Customer statistics
    $customer_stats_query = "SELECT 
        COUNT(DISTINCT co.user_id) as active_customers,
        AVG(co.order_count) as avg_orders_per_customer,
        AVG(co.total_spent) as avg_customer_value
        FROM (
            SELECT user_id, COUNT(*) as order_count, SUM(total_amount) as total_spent
            FROM orders o
            WHERE $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
            GROUP BY user_id
        ) as co";
    
    $customer_stats_stmt = $tshirtprint->prepare($customer_stats_query);
    $customer_stats_stmt->bind_param($types, ...$params);
    $customer_stats_stmt->execute();
    $customer_stats = $customer_stats_stmt->get_result()->fetch_assoc();
    
    // Summary section
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Active Customers', $customer_stats['active_customers']]);
    fputcsv($output, ['Average Orders per Customer', round($customer_stats['avg_orders_per_customer'], 1)]);
    fputcsv($output, ['Average Customer Value', '₱' . number_format($customer_stats['avg_customer_value'], 2)]);
    fputcsv($output, []); // Empty row
    
    // Top customers
    fputcsv($output, ['TOP CUSTOMERS']);
    fputcsv($output, ['Customer Name', 'Email', 'Orders', 'Total Spent']);
    
    $top_customers_query = "SELECT 
        u.username,
        pc.first_name,
        pc.last_name,
        COUNT(o.order_id) as order_count,
        SUM(o.total_amount) as total_spent
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN personal_customers pc ON u.id = pc.user_id
        WHERE $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY o.user_id
        ORDER BY total_spent DESC
        LIMIT 50";
    
    $top_customers_stmt = $tshirtprint->prepare($top_customers_query);
    $top_customers_stmt->bind_param($types, ...$params);
    $top_customers_stmt->execute();
    $top_customers = $top_customers_stmt->get_result();
    
    while ($row = $top_customers->fetch_assoc()) {
        fputcsv($output, [
            $row['first_name'] . ' ' . $row['last_name'],
            $row['username'],
            $row['order_count'],
            '₱' . number_format($row['total_spent'], 2)
        ]);
    }
}

// Products Report Export
function exportProductsReport($output, $tshirtprint, $date_condition, $params, $types) {
    // Write header
    fputcsv($output, ['Products Report - ' . $_GET['start_date'] . ' to ' . $_GET['end_date']]);
    fputcsv($output, []); // Empty row
    
    // Product performance
    fputcsv($output, ['PRODUCT PERFORMANCE']);
    fputcsv($output, ['Product Name', 'Category', 'Price', 'Times Ordered', 'Total Quantity', 'Total Revenue']);
    
    $product_performance_query = "SELECT 
        p.product_name,
        p.category,
        p.price,
        COUNT(oi.order_item_id) as times_ordered,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM products_offered p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY p.id, p.product_name, p.category, p.price
        ORDER BY total_revenue DESC";
    
    $product_performance_stmt = $tshirtprint->prepare($product_performance_query);
    $product_performance_stmt->bind_param($types, ...$params);
    $product_performance_stmt->execute();
    $product_performance = $product_performance_stmt->get_result();
    
    while ($row = $product_performance->fetch_assoc()) {
        fputcsv($output, [
            $row['product_name'],
            $row['category'],
            '₱' . number_format($row['price'], 2),
            $row['times_ordered'],
            $row['total_quantity'],
            '₱' . number_format($row['total_revenue'], 2)
        ]);
    }
    
    fputcsv($output, []); // Empty row
    
    // Category performance
    fputcsv($output, ['CATEGORY PERFORMANCE']);
    fputcsv($output, ['Category', 'Total Orders', 'Total Quantity', 'Total Revenue']);
    
    $category_performance_query = "SELECT 
        p.category,
        COUNT(oi.order_item_id) as total_orders,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM products_offered p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.order_id AND $date_condition AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
        GROUP BY p.category
        ORDER BY total_revenue DESC";
    
    $category_performance_stmt = $tshirtprint->prepare($category_performance_query);
    $category_performance_stmt->bind_param($types, ...$params);
    $category_performance_stmt->execute();
    $category_performance = $category_performance_stmt->get_result();
    
    while ($row = $category_performance->fetch_assoc()) {
        fputcsv($output, [
            $row['category'],
            $row['total_orders'],
            $row['total_quantity'],
            '₱' . number_format($row['total_revenue'], 2)
        ]);
    }
}
?>