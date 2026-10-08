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

// Handle customer actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // Permission gate (buttons stay visible; the page's own banner shows the notice)
    $needs = ['update_customer' => 'web_customers', 'delete_customer' => 'web_delete'];
    if (isset($needs[$action]) && !can($needs[$action])) {
        $_SESSION['error'] = permission_denied_message($needs[$action]);
        header("Location: admin_customers.php");
        exit;
    }

    switch ($action) {
        case 'update_customer':
            $user_id = $_POST['user_id'];
            $username = trim($_POST['username']);

            // Determine whether this account is a personal or company
            // customer by checking which row actually exists, rather than
            // trusting a client-supplied type - the previous version only
            // ever updated personal_customers, so editing a company
            // customer silently updated nothing but the username.
            $type_check_query = "SELECT
                    (SELECT COUNT(*) FROM personal_customers WHERE user_id = ?) AS is_personal,
                    (SELECT COUNT(*) FROM company_customers WHERE user_id = ?) AS is_company";
            $type_check_stmt = $inventory->prepare($type_check_query);
            $type_check_stmt->bind_param("ii", $user_id, $user_id);
            $type_check_stmt->execute();
            $type_row = $type_check_stmt->get_result()->fetch_assoc();
            $is_company = !empty($type_row['is_company']);

            // Check if username already exists (excluding current user)
            $check_query = "SELECT id FROM users WHERE username = ? AND id != ? AND role = 'customer'";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("si", $username, $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $_SESSION['error'] = "Username already exists!";
            } else {
                // Start transaction
                $inventory->begin_transaction();

                try {
                    // Update users table
                    $user_query = "UPDATE users SET username = ? WHERE id = ? AND role = 'customer'";
                    $user_stmt = $inventory->prepare($user_query);
                    $user_stmt->bind_param("si", $username, $user_id);
                    $user_stmt->execute();

                    if ($is_company) {
                        // Update company_customers table
                        $company_name = trim($_POST['company_name'] ?? '');
                        $taxpayer_name = trim($_POST['taxpayer_name'] ?? '');
                        $contact_person = trim($_POST['contact_person'] ?? '');
                        $contact_number = trim($_POST['company_contact_number'] ?? '');
                        $building_or_block = trim($_POST['building_or_block'] ?? '');
                        $lot_or_room_no = trim($_POST['lot_or_room_no'] ?? '');
                        $subd_or_street = trim($_POST['subd_or_street'] ?? '');
                        $barangay = trim($_POST['barangay'] ?? '');
                        $city = trim($_POST['company_city'] ?? '');
                        $province = trim($_POST['company_province'] ?? '');
                        $zip_code = trim($_POST['company_zip_code'] ?? '');

                        $customer_query = "UPDATE company_customers SET
                                        company_name = ?, taxpayer_name = ?, contact_person = ?, contact_number = ?,
                                        building_or_block = ?, lot_or_room_no = ?, subd_or_street = ?, barangay = ?,
                                        city = ?, province = ?, zip_code = ?
                                        WHERE user_id = ?";
                        $customer_stmt = $inventory->prepare($customer_query);
                        $customer_stmt->bind_param(
                            "sssssssssssi",
                            $company_name,
                            $taxpayer_name,
                            $contact_person,
                            $contact_number,
                            $building_or_block,
                            $lot_or_room_no,
                            $subd_or_street,
                            $barangay,
                            $city,
                            $province,
                            $zip_code,
                            $user_id
                        );
                        $customer_stmt->execute();
                    } else {
                        // Update personal_customers table
                        $first_name = trim($_POST['first_name'] ?? '');
                        $last_name = trim($_POST['last_name'] ?? '');
                        $contact_number = trim($_POST['contact_number'] ?? '');
                        $address_line1 = trim($_POST['address_line1'] ?? '');
                        $city = trim($_POST['city'] ?? '');

                        $customer_query = "UPDATE personal_customers SET 
                                        first_name = ?, last_name = ?, contact_number = ?, 
                                        address_line1 = ?, city = ? 
                                        WHERE user_id = ?";
                        $customer_stmt = $inventory->prepare($customer_query);
                        $customer_stmt->bind_param("sssssi", $first_name, $last_name, $contact_number, $address_line1, $city, $user_id);
                        $customer_stmt->execute();
                    }

                    $inventory->commit();
                    $_SESSION['message'] = "Customer updated successfully!";
                } catch (Exception $e) {
                    $inventory->rollback();
                    $_SESSION['error'] = "Failed to update customer!";
                }
            }
            break;

        case 'delete_customer':
            $user_id = $_POST['user_id'];

            // Check if customer has orders
            $check_query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result()->fetch_assoc();

            if ($result['order_count'] > 0) {
                $_SESSION['error'] = "Cannot delete customer - they have existing orders!";
            } else {
                // Start transaction
                $inventory->begin_transaction();

                try {
                    // Delete from carts and cart_items
                    $cart_query = "SELECT cart_id FROM carts WHERE user_id = ?";
                    $cart_stmt = $inventory->prepare($cart_query);
                    $cart_stmt->bind_param("i", $user_id);
                    $cart_stmt->execute();
                    $cart_result = $cart_stmt->get_result();

                    while ($cart = $cart_result->fetch_assoc()) {
                        $delete_cart_items = "DELETE FROM cart_items WHERE cart_id = ?";
                        $delete_items_stmt = $inventory->prepare($delete_cart_items);
                        $delete_items_stmt->bind_param("i", $cart['cart_id']);
                        $delete_items_stmt->execute();
                    }

                    // Delete cart
                    $delete_cart = "DELETE FROM carts WHERE user_id = ?";
                    $delete_cart_stmt = $inventory->prepare($delete_cart);
                    $delete_cart_stmt->bind_param("i", $user_id);
                    $delete_cart_stmt->execute();

                    // Delete any open pricing requests tied to this customer
                    $delete_pricing = "DELETE FROM pricing_requests WHERE user_id = ?";
                    $delete_pricing_stmt = $inventory->prepare($delete_pricing);
                    $delete_pricing_stmt->bind_param("i", $user_id);
                    $delete_pricing_stmt->execute();

                    // Delete from personal_customers
                    $delete_personal = "DELETE FROM personal_customers WHERE user_id = ?";
                    $delete_personal_stmt = $inventory->prepare($delete_personal);
                    $delete_personal_stmt->bind_param("i", $user_id);
                    $delete_personal_stmt->execute();

                    // Delete from company_customers - this was missing
                    // entirely, so deleting a company account left its
                    // company_customers row orphaned (pointing at a user_id
                    // that no longer existed in users).
                    $delete_company = "DELETE FROM company_customers WHERE user_id = ?";
                    $delete_company_stmt = $inventory->prepare($delete_company);
                    $delete_company_stmt->bind_param("i", $user_id);
                    $delete_company_stmt->execute();

                    // Finally delete user
                    $delete_user = "DELETE FROM users WHERE id = ? AND role = 'customer'";
                    $delete_user_stmt = $inventory->prepare($delete_user);
                    $delete_user_stmt->bind_param("i", $user_id);

                    if ($delete_user_stmt->execute()) {
                        $inventory->commit();
                        $_SESSION['message'] = "Customer deleted successfully!";
                    } else {
                        throw new Exception("Failed to delete customer");
                    }
                } catch (Exception $e) {
                    $inventory->rollback();
                    $_SESSION['error'] = "Failed to delete customer!";
                }
            }
            break;
    }

    header("Location: admin_customers.php");
    exit;
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    switch ($_GET['ajax']) {
        case 'get_customer':
            $user_id = $_GET['user_id'];
            $query = "SELECT u.id, u.username, 
                            pc.first_name, pc.last_name, pc.middle_name, pc.age, pc.gender, pc.birthdate, 
                            pc.contact_number, pc.address_line1, pc.city, pc.province, pc.zip_code,
                            cc.company_name, cc.taxpayer_name, cc.contact_person, cc.contact_number AS company_contact, 
                            cc.province AS company_province, cc.city AS company_city, cc.barangay, cc.subd_or_street, 
                            cc.building_or_block, cc.lot_or_room_no, cc.zip_code AS company_zip,
                            CASE 
                                WHEN pc.user_id IS NOT NULL THEN 'personal'
                                WHEN cc.user_id IS NOT NULL THEN 'company'
                                ELSE 'unknown'
                            END AS customer_type
                    FROM users u
                    LEFT JOIN personal_customers pc ON u.id = pc.user_id
                    LEFT JOIN company_customers cc ON u.id = cc.user_id
                    WHERE u.id = ? AND u.role = 'customer'";

            $stmt = $inventory->prepare($query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $customer = $result->fetch_assoc();
                echo json_encode($customer);
            } else {
                echo json_encode(['error' => 'Customer not found']);
            }
            exit;

        case 'get_customer_stats':
            $user_id = $_GET['user_id'];

            // Get order statistics
            $order_stats_query = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_order_value,
                MAX(created_at) as last_order_date
                FROM orders 
                WHERE user_id = ? AND status IN ('paid', 'processing', 'ready_for_pickup', 'completed')";
            $order_stats_stmt = $inventory->prepare($order_stats_query);
            $order_stats_stmt->bind_param("i", $user_id);
            $order_stats_stmt->execute();
            $order_stats = $order_stats_stmt->get_result()->fetch_assoc();

            // Get recent orders
            $recent_orders_query = "SELECT order_id, total_amount, status, created_at 
                                   FROM orders 
                                   WHERE user_id = ? 
                                   ORDER BY created_at DESC 
                                   LIMIT 5";
            $recent_orders_stmt = $inventory->prepare($recent_orders_query);
            $recent_orders_stmt->bind_param("i", $user_id);
            $recent_orders_stmt->execute();
            $recent_orders_result = $recent_orders_stmt->get_result();
            $recent_orders = [];
            while ($row = $recent_orders_result->fetch_assoc()) {
                $recent_orders[] = $row;
            }

            echo json_encode([
                'order_stats' => $order_stats,
                'recent_orders' => $recent_orders
            ]);
            exit;
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$SORT_LABELS = [
    'newest' => 'Newest',
    'name' => 'Name A-Z',
    'orders' => 'Most orders',
    'spent' => 'Highest spent',
];
if (!array_key_exists($sort, $SORT_LABELS)) {
    $sort = 'newest';
}

// Shared WHERE clause + params, used by both the count query (for
// pagination) and the main listing query below.
$where_clause = "WHERE u.role = 'customer'";
$where_params = [];
$where_types = '';

if (!empty($search)) {
    $where_clause .= " AND (u.username LIKE ? OR pc.first_name LIKE ? OR pc.last_name LIKE ? OR pc.contact_number LIKE ?
                      OR cc.company_name LIKE ? OR cc.contact_person LIKE ? OR cc.contact_number LIKE ?)";
    $search_term = "%$search%";
    $where_params = array_merge($where_params, array_fill(0, 7, $search_term));
    $where_types .= str_repeat('s', 7);
}

// Pagination: this listing had no LIMIT at all, so every customer ever
// registered got dumped into the DOM on every load. Count matching
// customers first (COUNT(DISTINCT ...) since the join can multiply rows
// per order), then page the main query.
$per_page = 25;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$count_query = "SELECT COUNT(DISTINCT u.id) as total
          FROM users u
          LEFT JOIN personal_customers pc ON u.id = pc.user_id
          LEFT JOIN company_customers cc ON u.id = cc.user_id
          $where_clause";
$count_stmt = $inventory->prepare($count_query);
if (!empty($where_params)) {
    $count_stmt->bind_param($where_types, ...$where_params);
}
$count_stmt->execute();
$total_customers_matching = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int) ceil($total_customers_matching / $per_page));
$page = min($page, $total_pages); // clamp so a stale/typed-in page= doesn't return an empty page
$offset = ($page - 1) * $per_page;

// Build query for customers
$query = "SELECT u.id, u.username, 
                 pc.first_name, pc.last_name, pc.contact_number, pc.city,
                 cc.company_name, cc.contact_person, cc.contact_number as company_contact, cc.city as company_city,
                 COUNT(o.order_id) as order_count,
                 COALESCE(SUM(o.total_amount), 0) as total_spent,
                 MAX(o.created_at) as last_order_date
          FROM users u
          LEFT JOIN personal_customers pc ON u.id = pc.user_id
          LEFT JOIN company_customers cc ON u.id = cc.user_id
          LEFT JOIN orders o ON u.id = o.user_id AND o.status IN ('paid', 'processing', 'ready_for_pickup', 'completed')
          $where_clause";
$params = $where_params;
$types = $where_types;

// Add group by and sorting
$query .= " GROUP BY u.id";

switch ($sort) {
    case 'name':
        // Personal customers sort by first/last name; company customers
        // have no first_name/last_name so they used to sort as NULL (i.e.
        // dumped at one end regardless of their actual company name).
        // Sort everyone by whichever display name they actually have.
        $query .= " ORDER BY COALESCE(CONCAT(pc.first_name, ' ', pc.last_name), cc.company_name) ASC";
        break;
    case 'orders':
        $query .= " ORDER BY order_count DESC";
        break;
    case 'spent':
        $query .= " ORDER BY total_spent DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY u.id DESC";
        break;
}

$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Prepare and execute query
$stmt = $inventory->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers_result = $stmt->get_result();
$customers = [];
while ($row = $customers_result->fetch_assoc()) {
    $customers[] = $row;
}

// Get total statistics for dashboard
$stats_query = "SELECT 
    COUNT(*) as total_customers,
    AVG(order_count) as avg_orders_per_customer
    FROM (
        SELECT u.id, COUNT(o.order_id) as order_count
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.role = 'customer'
        GROUP BY u.id
    ) as customer_stats";
$stats_result = $inventory->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Personal vs company split for the summary tiles (same rule as get_customer's
// customer_type: a personal_customers row wins, otherwise a company row).
$type_result = $inventory->query("SELECT
        COALESCE(SUM(pc.user_id IS NOT NULL), 0) AS personal_total,
        COALESCE(SUM(pc.user_id IS NULL AND cc.user_id IS NOT NULL), 0) AS company_total
    FROM users u
    LEFT JOIN personal_customers pc ON u.id = pc.user_id
    LEFT JOIN company_customers cc ON u.id = cc.user_id
    WHERE u.role = 'customer'");
$type_counts = $type_result->fetch_assoc();
$personal_total = (int) $type_counts['personal_total'];
$company_total = (int) $type_counts['company_total'];
$total_customers = (int) $stats['total_customers'];
$avg_orders = round((float) ($stats['avg_orders_per_customer'] ?? 0), 1);

/**
 * Small presentation helpers (UI only - no business logic).
 */
function cu_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $chars = function_exists('mb_substr') ? mb_substr($name, 0, 2) : substr($name, 0, 2);
    return function_exists('mb_strtoupper') ? mb_strtoupper($chars) : strtoupper($chars);
}

function cu_display_name(array $customer): string
{
    if (!empty($customer['first_name'])) {
        return trim($customer['first_name'] . ' ' . $customer['last_name']);
    }
    if (!empty($customer['company_name'])) {
        return $customer['company_name'];
    }
    return 'Customer';
}

function build_query_url(array $overrides = []): string
{
    $current = ['search' => $_GET['search'] ?? '', 'sort' => $_GET['sort'] ?? '', 'page' => $_GET['page'] ?? ''];
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    if (isset($merged['sort']) && $merged['sort'] === 'newest') {
        unset($merged['sort']); // default order, keep the URL clean
    }
    return 'admin_customers.php' . ($merged ? ('?' . http_build_query($merged)) : '');
}

$open_id = isset($_GET['open']) ? (int) $_GET['open'] : 0;

// Display-only values for the result range.
$range_from = $total_customers_matching > 0 ? $offset + 1 : 0;
$range_to = $offset + count($customers);
$is_filtered = ($search !== '');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-customers" data-page="customers">
    <div class="admin-container">
        <main class="main-content">
            <div class="wa-page">

                <!-- 1. Page header -->
                <header class="wa-page-head">
                    <div>
                        <h1 class="wa-page-title">Customer Management</h1>
                        <p class="wa-page-desc">Browse registered customers, review their order history and keep their details up to date.</p>
                    </div>
                    <div class="wa-page-actions">
                        <a class="btn btn-secondary" href="<?php echo build_query_url(); ?>">
                            <i class="fas fa-rotate" aria-hidden="true"></i> Refresh
                        </a>
                    </div>
                </header>

                <!-- 2. Summary -->
                <section class="wa-stats" aria-label="Customer summary">
                    <a class="wa-stat tone-total <?php echo (!$is_filtered && $sort === 'newest') ? 'is-active' : ''; ?>" href="admin_customers.php">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Total customers</span>
                            <span class="wa-stat-value"><?php echo $total_customers; ?></span>
                            <span class="wa-stat-sub">All registered accounts</span>
                        </span>
                    </a>
                    <div class="wa-stat tone-completed">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Personal</span>
                            <span class="wa-stat-value"><?php echo $personal_total; ?></span>
                            <span class="wa-stat-sub">Individual customers</span>
                        </span>
                    </div>
                    <div class="wa-stat tone-processing">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-building"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Company</span>
                            <span class="wa-stat-value"><?php echo $company_total; ?></span>
                            <span class="wa-stat-sub">Business accounts</span>
                        </span>
                    </div>
                    <div class="wa-stat tone-pending">
                        <span class="wa-stat-icon" aria-hidden="true"><i class="fas fa-cart-shopping"></i></span>
                        <span class="wa-stat-body">
                            <span class="wa-stat-label">Avg orders per customer</span>
                            <span class="wa-stat-value"><?php echo $avg_orders; ?></span>
                            <span class="wa-stat-sub">Across all customers</span>
                        </span>
                    </div>
                </section>

                <!-- 3 + 4. Search, sort and quick sort tabs (one control) -->
                <section class="wa-filterbar" aria-label="Search and sort customers">
                    <form class="wa-filterbar-form" method="get" action="admin_customers.php" role="search">
                        <div class="wa-search">
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            <input type="search" name="search" id="customerSearch" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Search by name, email or phone" aria-label="Search customers by name, email or phone" autocomplete="off">
                            <?php if ($search !== ''): ?>
                                <a class="wa-search-clear" href="<?php echo build_query_url(['search' => '', 'page' => '']); ?>" aria-label="Clear search" title="Clear search">
                                    <i class="fas fa-xmark" aria-hidden="true"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="wa-select">
                            <label class="sr-only" for="customerSortFilter">Sort customers</label>
                            <select name="sort" id="customerSortFilter">
                                <?php foreach ($SORT_LABELS as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $sort === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                        <?php if ($is_filtered || $sort !== 'newest'): ?>
                            <a href="admin_customers.php" class="btn btn-ghost"><i class="fas fa-rotate-left" aria-hidden="true"></i> Reset</a>
                        <?php endif; ?>
                    </form>

                    <nav class="wa-tabs" aria-label="Sort customers">
                        <?php foreach ($SORT_LABELS as $val => $label):
                            $is_active = $sort === $val;
                        ?>
                            <a class="wa-tab <?php echo $is_active ? 'is-active' : ''; ?>"
                                href="<?php echo build_query_url(['sort' => $val, 'page' => '']); ?>"
                                <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </section>

                <!-- 5 + 6. Customers data and pagination -->
                <section class="wa-datacard" aria-labelledby="customersHeading">
                    <div class="wa-datacard-head">
                        <div>
                            <h2 class="wa-datacard-title" id="customersHeading"><?php echo $is_filtered ? 'Matching customers' : 'All customers'; ?></h2>
                            <p class="wa-datacard-sub">
                                <?php if ($total_customers_matching > 0): ?>
                                    Showing <?php echo $range_from; ?>&ndash;<?php echo $range_to; ?> of <?php echo $total_customers_matching; ?> &middot; <?php echo htmlspecialchars(strtolower($SORT_LABELS[$sort])); ?>
                                <?php else: ?>
                                    Nothing to show
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($is_filtered): ?>
                            <div class="wa-filter-chips" aria-label="Active filters">
                                <span class="wa-filter-chip">
                                    <span>Search: &ldquo;<?php echo htmlspecialchars($search); ?>&rdquo;</span>
                                    <a href="<?php echo build_query_url(['search' => '', 'page' => '']); ?>" aria-label="Remove search filter" title="Remove search filter"><i class="fas fa-xmark" aria-hidden="true"></i></a>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="wa-table-wrap">
                        <table class="ord-table cust-table">
                            <caption class="sr-only">Customers</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col" class="is-num">Orders</th>
                                    <th scope="col" class="is-num">Total spent</th>
                                    <th scope="col">Last order</th>
                                    <th scope="col"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody id="customersTable">
                                <?php if (empty($customers)): ?>
                                    <tr>
                                        <td colspan="6" class="empty-row">
                                            <div class="wa-empty">
                                                <span class="wa-empty-icon" aria-hidden="true"><i class="fas fa-user-slash"></i></span>
                                                <div class="wa-empty-title">No customers found</div>
                                                <p class="wa-empty-text">
                                                    <?php echo $is_filtered ? 'Nothing matches the current search. Try a different term or clear the search.' : 'Customers will appear here as they register.'; ?>
                                                </p>
                                                <?php if ($is_filtered): ?>
                                                    <a href="admin_customers.php" class="btn btn-outline btn-sm"><i class="fas fa-rotate-left" aria-hidden="true"></i> Clear search</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($customers as $customer):
                                    $display_name = cu_display_name($customer);
                                    $is_company_row = empty($customer['first_name']) && !empty($customer['company_name']);
                                    $phone = $customer['contact_number'] ?: ($customer['company_contact'] ?? '');
                                    $city = $customer['city'] ?: ($customer['company_city'] ?? '');
                                ?>
                                    <tr class="ord-row cust-row" id="customer-row-<?php echo (int) $customer['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($display_name); ?>"
                                        data-email="<?php echo htmlspecialchars($customer['username']); ?>">
                                        <td class="cust-col-customer">
                                            <div class="cust-customer">
                                                <span class="wa-avatar" aria-hidden="true"><?php echo htmlspecialchars(cu_initials($display_name)); ?></span>
                                                <div>
                                                    <div class="cust-name-row">
                                                        <button type="button" class="cust-name" onclick="openCustomerPanel(<?php echo (int) $customer['id']; ?>)"
                                                            title="<?php echo htmlspecialchars($display_name); ?>"
                                                            aria-label="View <?php echo htmlspecialchars($display_name); ?>"><?php echo htmlspecialchars($display_name); ?></button>
                                                        <?php if ($is_company_row): ?>
                                                            <span class="wa-chip tone-total"><i class="fas fa-building" aria-hidden="true"></i> Company</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="cust-sub">
                                                        <i class="fas fa-envelope" aria-hidden="true"></i>
                                                        <span title="<?php echo htmlspecialchars($customer['username']); ?>"><?php echo htmlspecialchars($customer['username']); ?></span>
                                                    </div>
                                                    <div class="ord-meta">User ID <?php echo (int) $customer['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="cust-col-contact">
                                            <?php if ($phone !== '' || $city !== ''): ?>
                                                <div class="cust-contact">
                                                    <?php if ($phone !== ''): ?>
                                                        <div><i class="fas fa-phone" aria-hidden="true"></i> <?php echo htmlspecialchars($phone); ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($city !== ''): ?>
                                                        <div><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo htmlspecialchars($city); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="cust-muted">No contact info</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cust-col-orders is-num">
                                            <span class="cust-count"><?php echo (int) $customer['order_count']; ?> <small><?php echo ((int) $customer['order_count'] === 1) ? 'order' : 'orders'; ?></small></span>
                                        </td>
                                        <td class="cust-col-spent is-num">
                                            <span class="ord-total">&#8369;<?php echo number_format($customer['total_spent'], 2); ?></span>
                                        </td>
                                        <td class="cust-col-last">
                                            <?php if ($customer['last_order_date']): ?>
                                                <time class="cust-date" datetime="<?php echo date('c', strtotime($customer['last_order_date'])); ?>"><?php echo date('M j, Y', strtotime($customer['last_order_date'])); ?></time>
                                            <?php else: ?>
                                                <span class="cust-muted">No orders yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="cust-col-actions">
                                            <div class="ord-actions">
                                                <button type="button" class="btn btn-sm btn-outline" onclick="openCustomerPanel(<?php echo (int) $customer['id']; ?>)">
                                                    <i class="fas fa-eye" aria-hidden="true"></i> View
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline btn-icon" onclick="editCustomer(<?php echo (int) $customer['id']; ?>)"
                                                    aria-label="Edit <?php echo htmlspecialchars($display_name); ?>" title="Edit customer">
                                                    <i class="fas fa-pen-to-square" aria-hidden="true"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline btn-icon cust-delete" onclick="confirmDelete(<?php echo (int) $customer['id']; ?>, <?php echo esc_attr_js($customer['username']); ?>)"
                                                    aria-label="Delete <?php echo htmlspecialchars($display_name); ?>" title="Delete customer">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="wa-datacard-foot">
                        <span class="wa-datacard-foot-text">Showing <?php echo count($customers); ?> of <?php echo $total_customers_matching; ?> customers</span>
                        <?php if ($total_pages > 1): ?>
                            <nav class="wa-pager" aria-label="Pagination">
                                <a class="wa-pager-link <?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page - 1]); ?>"
                                    <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>><i class="fas fa-chevron-left" aria-hidden="true"></i> Prev</a>
                                <?php
                                $window = 2;
                                for ($p = 1; $p <= $total_pages; $p++) {
                                    $show = $p === 1 || $p === $total_pages || abs($p - $page) <= $window;
                                    if (!$show) {
                                        if ($p === 2 || $p === $total_pages - 1) {
                                            echo '<span class="wa-pager-gap" aria-hidden="true">&hellip;</span>';
                                        }
                                        continue;
                                    }
                                    $active = $p === $page;
                                    echo '<a class="wa-pager-link' . ($active ? ' is-active' : '') . '" href="' . build_query_url(['page' => $p]) . '"'
                                        . ($active ? ' aria-current="page"' : '') . ' aria-label="Page ' . $p . '">' . $p . '</a>';
                                }
                                ?>
                                <a class="wa-pager-link <?php echo $page >= $total_pages ? 'is-disabled' : ''; ?>" href="<?php echo build_query_url(['page' => $page + 1]); ?>"
                                    <?php echo $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                            </nav>
                        <?php endif; ?>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Customer detail slide-over panel -->
    <div class="panel-overlay" id="panelOverlay" onclick="closeCustomerPanel()"></div>
    <aside class="slide-panel" id="slidePanel" role="dialog" aria-modal="true" aria-labelledby="panelTitle" aria-hidden="true">
        <div class="panel-header">
            <div class="panel-heading">
                <div class="panel-title-row">
                    <h2 id="panelTitle">Customer Details</h2>
                    <span id="panelHeadBadge"></span>
                </div>
                <p class="panel-subtitle" id="panelSubtitle"></p>
            </div>
            <button type="button" class="panel-close" id="panelClose" onclick="closeCustomerPanel()" aria-label="Close customer details"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="panel-status-bar" id="panelStatusBar"></div>
        <div class="panel-body" id="panelBody" aria-live="polite">
            <div class="od-skeleton" aria-hidden="true">
                <div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:40%"></span><span class="wa-skel"></span><span class="wa-skel" style="width:70%"></span></div></div>
            </div>
        </div>
    </aside>

    <!-- Edit Customer dialog -->
    <div class="modal" id="editCustomerModal" role="dialog" aria-modal="true" aria-labelledby="editCustomerTitle">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <div>
                    <h2 id="editCustomerTitle">Edit customer</h2>
                    <p class="ord-dialog-sub" id="editCustomerSub"></p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('editCustomerModal')" aria-label="Close">&times;</button>
            </div>
            <form id="editCustomerForm" method="post">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_customer">
                    <input type="hidden" name="user_id" id="editUserId">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editUsername"><i class="fas fa-envelope" aria-hidden="true"></i> Username / email</label>
                            <input type="email" name="username" id="editUsername" class="form-control" required>
                        </div>

                        <div class="form-group" id="personalContactGroup">
                            <label for="editContactNumber"><i class="fas fa-phone" aria-hidden="true"></i> Phone number</label>
                            <input type="text" name="contact_number" id="editContactNumber" class="form-control">
                        </div>
                    </div>

                    <!-- Personal customer fields -->
                    <div id="personalFields">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="editFirstName"><i class="fas fa-user" aria-hidden="true"></i> First name</label>
                                <input type="text" name="first_name" id="editFirstName" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="editLastName"><i class="fas fa-user" aria-hidden="true"></i> Last name</label>
                                <input type="text" name="last_name" id="editLastName" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="editAddressLine1"><i class="fas fa-location-dot" aria-hidden="true"></i> Address line 1</label>
                            <input type="text" name="address_line1" id="editAddressLine1" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="editCity"><i class="fas fa-city" aria-hidden="true"></i> City</label>
                            <input type="text" name="city" id="editCity" class="form-control">
                        </div>
                    </div>

                    <!-- Company customer fields -->
                    <div id="companyFields" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="editCompanyName"><i class="fas fa-building" aria-hidden="true"></i> Company name</label>
                                <input type="text" name="company_name" id="editCompanyName" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="editTaxpayerName"><i class="fas fa-file-invoice" aria-hidden="true"></i> Taxpayer name</label>
                                <input type="text" name="taxpayer_name" id="editTaxpayerName" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="editContactPerson"><i class="fas fa-user" aria-hidden="true"></i> Contact person</label>
                                <input type="text" name="contact_person" id="editContactPerson" class="form-control">
                            </div>

                            <div class="form-group">
                                <label for="editCompanyContactNumber"><i class="fas fa-phone" aria-hidden="true"></i> Phone number</label>
                                <input type="text" name="company_contact_number" id="editCompanyContactNumber" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="editBuildingOrBlock">Building / block</label>
                                <input type="text" name="building_or_block" id="editBuildingOrBlock" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="editLotOrRoomNo">Lot / room no.</label>
                                <input type="text" name="lot_or_room_no" id="editLotOrRoomNo" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="editSubdOrStreet">Subdivision / street</label>
                                <input type="text" name="subd_or_street" id="editSubdOrStreet" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="editBarangay">Barangay</label>
                                <input type="text" name="barangay" id="editBarangay" class="form-control">
                            </div>
                        </div>

                        <div class="form-row cols-3">
                            <div class="form-group">
                                <label for="editCompanyCity"><i class="fas fa-city" aria-hidden="true"></i> City</label>
                                <input type="text" name="company_city" id="editCompanyCity" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="editCompanyProvince">Province</label>
                                <input type="text" name="company_province" id="editCompanyProvince" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="editCompanyZipCode">Zip code</label>
                                <input type="text" name="company_zip_code" id="editCompanyZipCode" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCustomerModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update customer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-stack" id="toastStack" aria-live="polite"></div>

    <?php
    $wa_data = [
        'openId' => (int) $open_id,
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