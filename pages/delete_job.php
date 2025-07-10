<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
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
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Delete Job Order</title>
        <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            ::-webkit-scrollbar {
                width: 5px;
                height: 5px;
            }

            ::-webkit-scrollbar-thumb {
                background: rgb(140, 140, 140);
                border-radius: 10px;
            }

            :root {
                --primary: #1877f2;
                --secondary: #166fe5;
                --light: #f0f2f5;
                --dark: #1c1e21;
                --gray: #65676b;
                --light-gray: #e4e6eb;
                --card-bg: #ffffff;
                --success: #42b72a;
                --danger: #ff4d4f;
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Poppins', sans-serif;
            }

            body {
                background-color: var(--light);
                color: var(--dark);
                min-height: 100vh;
                padding-left: 70px;
            }

            /* Floating Mobile Navigation */
            .sidebar {
                width: 70px;
                background-color: var(--card-bg);
                height: 100vh;
                position: fixed;
                left: 0;
                top: 0;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                transition: width 0.3s ease;
                overflow: hidden;
            }

            .sidebar.expanded {
                width: 250px;
            }

            .brand {
                padding: 15px;
                border-bottom: 1px solid var(--light-gray);
                height: 70px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .brand img {
                height: 40px;
                width: auto;
                transform: rotate(45deg);
            }

            .brand h2 {
                display: none;
            }

            .sidebar.expanded .brand {
                justify-content: flex-start;
            }

            .sidebar.expanded .brand img {
                margin-right: 15px;
            }

            .sidebar.expanded .brand h2 {
                display: block;
                font-size: 16px;
                font-weight: 600;
                color: var(--dark);
            }

            .nav-menu {
                list-style: none;
                padding-top: 15px;
            }

            .nav-menu li a {
                display: flex;
                align-items: center;
                padding: 12px 15px;
                color: var(--dark);
                text-decoration: none;
                transition: background-color 0.3s;
                white-space: nowrap;
            }

            .nav-menu li a:hover,
            .nav-menu li a.active {
                background-color: var(--light-gray);
            }

            .nav-menu li a i {
                min-width: 40px;
                text-align: center;
                color: var(--gray);
                font-size: 18px;
            }

            .nav-menu li a span {
                display: none;
            }

            .sidebar.expanded .nav-menu li a span {
                display: inline;
            }

            .menu-toggle {
                display: none;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1100;
                background: var(--primary);
                color: white;
                border: none;
                border-radius: 5px;
                padding: 8px 12px;
                font-size: 18px;
                cursor: pointer;
            }

            /* Main Content */
            .main-content {
                padding: 20px;
                max-width: 800px;
                margin: 0 auto;
            }

            /* Confirmation Card */
            .confirmation-card {
                background: var(--card-bg);
                border-radius: 8px;
                padding: 30px;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
                text-align: center;
            }

            .confirmation-icon {
                font-size: 48px;
                color: var(--danger);
                margin-bottom: 20px;
            }

            .confirmation-title {
                font-size: 24px;
                font-weight: 600;
                margin-bottom: 15px;
                color: var(--dark);
            }

            .confirmation-message {
                font-size: 16px;
                color: var(--gray);
                margin-bottom: 25px;
                line-height: 1.6;
            }

            .confirmation-details {
                background: rgba(255, 77, 79, 0.05);
                border-radius: 6px;
                padding: 15px;
                margin: 20px 0;
                text-align: left;
                border-left: 3px solid var(--danger);
            }

            .confirmation-details p {
                margin-bottom: 8px;
            }

            .confirmation-details strong {
                color: var(--dark);
            }

            /* Form Elements */
            .form-group {
                margin-bottom: 20px;
                text-align: left;
            }

            .form-group label {
                display: flex;
                align-items: center;
                cursor: pointer;
                font-size: 14px;
                color: var(--dark);
            }

            .form-group input[type="checkbox"] {
                margin-right: 10px;
                width: 18px;
                height: 18px;
            }

            /* Buttons */
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
                font-size: 14px;
                margin: 0 10px;
            }

            .btn-danger {
                background-color: var(--danger);
                color: white;
                border: none;
            }

            .btn-danger:hover {
                background-color: #e63946;
            }

            .btn-outline {
                background-color: transparent;
                border: 1px solid var(--light-gray);
                color: var(--dark);
            }

            .btn-outline:hover {
                background-color: var(--light-gray);
            }

            /* Responsive Adjustments */
            @media (max-width: 768px) {
                body {
                    padding-left: 0;
                }

                .sidebar {
                    transform: translateX(-100%);
                    width: 250px;
                    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
                }

                .sidebar.expanded {
                    transform: translateX(0);
                }

                .menu-toggle {
                    display: block;
                }

                .main-content {
                    margin-left: 0;
                    padding: 15px;
                }

                .btn {
                    width: 100%;
                    margin: 5px 0;
                }
            }
        </style>
    </head>

    <body>
        <div class="main-content">
            <div class="confirmation-card">
                <div class="confirmation-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1 class="confirmation-title">Delete Job Order</h1>
                <p class="confirmation-message">Are you sure you want to permanently delete this job order?</p>

                <div class="confirmation-details">
                    <p><strong>Project Name:</strong> <?= htmlspecialchars($job['project_name']) ?></p>
                    <p><strong>Client:</strong> <?= htmlspecialchars($job['client_name']) ?></p>
                    <p><strong>Date:</strong> <?= date('M j, Y', strtotime($job['log_date'])) ?></p>
                    <p><strong>Quantity:</strong> <?= $job['quantity'] ?></p>
                </div>

                <form method="get" class="form-group">
                    <input type="hidden" name="id" value="<?= $job_id ?>">
                    <label>
                        <input type="checkbox" name="restore" value="yes">
                        Restore stock from this job order
                    </label>
                </form>

                <div>
                    <button type="submit" form="deleteForm" name="confirm" value="1" class="btn btn-danger">
                        Confirm Delete
                    </button>
                    <a href="job_orders.php" class="btn btn-outline">
                        Cancel
                    </a>
                </div>

                <form id="deleteForm" method="get" style="display: none;">
                    <input type="hidden" name="id" value="<?= $job_id ?>">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="restore" id="restoreStock" value="<?= $restore_stock ? 'yes' : 'no' ?>">
                </form>
            </div>
        </div>

        <script>
            // Enhanced mobile navigation toggle
            document.getElementById('menuToggle').addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('expanded');

                // Toggle menu icon
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('expanded')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            // Close menu when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.getElementById('sidebar');
                const menuToggle = document.getElementById('menuToggle');

                if (window.innerWidth <= 768 &&
                    !sidebar.contains(event.target) &&
                    event.target !== menuToggle &&
                    !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('expanded');
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });

            // Update the restore stock hidden input when checkbox changes
            document.querySelector('input[name="restore"]').addEventListener('change', function() {
                document.getElementById('restoreStock').value = this.checked ? 'yes' : 'no';
            });
        </script>
    </body>

    </html>
<?php
    exit;
}

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