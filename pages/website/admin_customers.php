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
        <div class="main-content">
            <div class="header">
                <h1>Customer Management</h1>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card customers">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card orders">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-number"><?php echo round($stats['avg_orders_per_customer'], 1); ?></div>
                    <div class="stat-label">Avg Orders per Customer</div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="search-filter">
                <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search by name, email, or phone..."
                        value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">

                    <select name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                        <option value="orders" <?php echo $sort === 'orders' ? 'selected' : ''; ?>>Most Orders</option>
                        <option value="spent" <?php echo $sort === 'spent' ? 'selected' : ''; ?>>Highest Spent</option>
                    </select>

                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>

                    <a href="admin_customers.php" class="btn" style="background: var(--gray); color: white; text-decoration: none; padding: 10px 15px;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </form>
            </div>

            <!-- Customers Table -->
            <div class="customers-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Contact Info</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo $customer['id']; ?></td>
                                <td>
                                    <strong>
                                        <?php
                                        if (!empty($customer['first_name'])) {
                                            echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']);
                                        } elseif (!empty($customer['company_name'])) {
                                            echo htmlspecialchars($customer['company_name']);
                                        } else {
                                            echo 'Customer';
                                        }
                                        ?>
                                    </strong>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['username']); ?>
                                    </small>
                                    <?php if (!empty($customer['middle_name'])): ?>
                                        <br>
                                        <small class="text-muted">Middle: <?php echo htmlspecialchars($customer['middle_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($customer['contact_number']) || !empty($customer['company_contact'])): ?>
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($customer['contact_number'] ?: $customer['company_contact']); ?>
                                        <br>
                                    <?php endif; ?>

                                    <?php if (!empty($customer['city']) || !empty($customer['company_city'])): ?>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($customer['city'] ?: $customer['company_city']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No location info</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <strong><?php echo $customer['order_count']; ?> orders</strong>
                                </td>
                                <td>
                                    <strong>₱<?php echo number_format($customer['total_spent'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($customer['last_order_date']): ?>
                                        <?php echo date('M j, Y', strtotime($customer['last_order_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No orders yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info" onclick="viewCustomerDetails(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-warning" onclick="editCustomer(<?php echo $customer['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger" onclick="confirmDelete(<?php echo (int) $customer['id']; ?>, <?php echo esc_attr_js($customer['username']); ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1):
                // Preserve search/sort on every pagination link
                $base_params = [];
                if (!empty($search)) {
                    $base_params['search'] = $search;
                }
                if (!empty($sort) && $sort !== 'newest') {
                    $base_params['sort'] = $sort;
                }
                $page_url = function ($p) use ($base_params) {
                    return '?' . http_build_query(array_merge($base_params, ['page' => $p]));
                };
            ?>
                <div class="pagination">
                    <span class="pagination-summary">
                        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_customers_matching); ?>
                        of <?php echo $total_customers_matching; ?> customers
                    </span>
                    <div class="pagination-controls">
                        <a href="<?php echo htmlspecialchars($page_url(max(1, $page - 1))); ?>"
                           class="btn <?php echo $page <= 1 ? 'btn-disabled' : ''; ?>"
                           <?php echo $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                        <?php
                        $window = 2;
                        for ($p = 1; $p <= $total_pages; $p++) {
                            $show = $p === 1 || $p === $total_pages || abs($p - $page) <= $window;
                            if (!$show) {
                                if ($p === 2 || $p === $total_pages - 1) {
                                    echo '<span class="pagination-ellipsis">&hellip;</span>';
                                }
                                continue;
                            }
                            $active = $p === $page ? 'active' : '';
                            echo '<a href="' . htmlspecialchars($page_url($p)) . '" class="btn ' . $active . '">' . $p . '</a>';
                        }
                        ?>
                        <a href="<?php echo htmlspecialchars($page_url(min($total_pages, $page + 1))); ?>"
                           class="btn <?php echo $page >= $total_pages ? 'btn-disabled' : ''; ?>"
                           <?php echo $page >= $total_pages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2><i class="fas fa-user-circle"></i> Customer Details</h2>
                <button type="button" class="modal-close" onclick="closeModal(\'customerModal\')" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="customerDetails">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="modal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Customer</h2>
                <button type="button" class="modal-close" onclick="closeModal(\'editCustomerModal\')" aria-label="Close">&times;</button>
            </div>
            <form id="editCustomerForm" method="post">
<?php echo csrf_field(); ?>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_customer">
                    <input type="hidden" name="user_id" id="editUserId">

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Username/Email</label>
                            <input type="email" name="username" id="editUsername" class="form-control" required>
                        </div>

                        <div class="form-group" id="personalContactGroup">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="text" name="contact_number" id="editContactNumber" class="form-control">
                        </div>
                    </div>

                    <!-- Personal customer fields -->
                    <div id="personalFields">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> First Name</label>
                                <input type="text" name="first_name" id="editFirstName" class="form-control">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Last Name</label>
                                <input type="text" name="last_name" id="editLastName" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address Line 1</label>
                            <input type="text" name="address_line1" id="editAddressLine1" class="form-control">
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-city"></i> City</label>
                            <input type="text" name="city" id="editCity" class="form-control">
                        </div>
                    </div>

                    <!-- Company customer fields -->
                    <div id="companyFields" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-building"></i> Company Name</label>
                                <input type="text" name="company_name" id="editCompanyName" class="form-control">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-file-invoice"></i> Taxpayer Name</label>
                                <input type="text" name="taxpayer_name" id="editTaxpayerName" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Contact Person</label>
                                <input type="text" name="contact_person" id="editContactPerson" class="form-control">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="text" name="company_contact_number" id="editCompanyContactNumber" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Building/Block</label>
                                <input type="text" name="building_or_block" id="editBuildingOrBlock" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Lot/Room No.</label>
                                <input type="text" name="lot_or_room_no" id="editLotOrRoomNo" class="form-control">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Subdivision/Street</label>
                                <input type="text" name="subd_or_street" id="editSubdOrStreet" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Barangay</label>
                                <input type="text" name="barangay" id="editBarangay" class="form-control">
                            </div>
                        </div>

                        <div class="form-row cols-3">
                            <div class="form-group">
                                <label><i class="fas fa-city"></i> City</label>
                                <input type="text" name="company_city" id="editCompanyCity" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Province</label>
                                <input type="text" name="company_province" id="editCompanyProvince" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Zip Code</label>
                                <input type="text" name="company_zip_code" id="editCompanyZipCode" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editCustomerModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php
    ?>
    <script>
        window.WA_CONFIG = {
            csrfToken: <?php echo esc_js(csrf_token()); ?>,
            flash: {
                message: <?php echo isset($_SESSION['message']) ? esc_js($_SESSION['message']) : 'null'; ?>,
                error: <?php echo isset($_SESSION['error']) ? esc_js($_SESSION['error']) : 'null'; ?>
            }
        };
        <?php unset($_SESSION['message'], $_SESSION['error']); ?>
    </script>
    <script src="../../assets/js/website_admin.js"></script>

</body>

</html>