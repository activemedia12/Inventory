<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$job_id = intval($_GET['id'] ?? 0);
if ($job_id <= 0) {
    echo "Invalid job order ID.";
    exit;
}

// Fetch job order
$stmt = $mysqli->prepare("SELECT * FROM job_orders WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    echo "Job order not found.";
    exit;
}

// Fetch dropdown data
$product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_sizes = $mysqli->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = $_POST['client_name'] ?? '';
    $client_address = $_POST['client_address'] ?? '';
    $contact_person = $_POST['contact_person'] ?? '';
    $contact_number = $_POST['contact_number'] ?? '';
    $project_name = $_POST['project_name'] ?? '';
    $serial_range = $_POST['serial_range'] ?? '';
    $quantity = intval($_POST['quantity']);
    $number_of_sets = intval($_POST['number_of_sets']);
    $product_size = $_POST['product_size'] ?? '';
    $paper_size = $_POST['paper_size'] ?? '';
    $custom_paper_size = $_POST['custom_paper_size'] ?? '';
    $paper_type = $_POST['paper_type'] ?? '';
    $copies_per_set = intval($_POST['copies_per_set']);
    $binding_type = $_POST['binding_type'] ?? '';
    $custom_binding = $_POST['custom_binding'] ?? '';
    $special_instructions = $_POST['special_instructions'] ?? '';
    $log_date = $_POST['log_date'] ?? date('Y-m-d');

    $cut_size_map = ['1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, 'whole' => 1];
    $cut_size = $cut_size_map[$product_size] ?? 1;
    $total_sheets = $number_of_sets * $quantity;
    $cut_sheets = $total_sheets / $cut_size;
    $reams = $cut_sheets / 500;
    $reams_per_product = $reams;
    $new_used_sheets = $reams_per_product * 500;

    // Compute old used sheets
    $old_cut_size = $cut_size_map[$job['product_size']] ?? 1;
    $old_total_sheets = $job['quantity'] * $job['number_of_sets'];
    $old_reams = ($old_total_sheets / $old_cut_size) / 500;
    $old_used_sheets = $old_reams * 500;

    $difference = $new_used_sheets - $old_used_sheets;

    // Update job_orders
    $stmt = $mysqli->prepare("UPDATE job_orders SET
        log_date = ?, client_name = ?, client_address = ?, contact_person = ?, contact_number = ?,
        project_name = ?, quantity = ?, number_of_sets = ?, product_size = ?, serial_range = ?,
        paper_size = ?, custom_paper_size = ?, paper_type = ?, copies_per_set = ?, binding_type = ?,
        custom_binding = ?, special_instructions = ?
        WHERE id = ?");

    $stmt->bind_param(
        "ssssssiisssssisssi",
        $log_date,
        $client_name,
        $client_address,
        $contact_person,
        $contact_number,
        $project_name,
        $quantity,
        $number_of_sets,
        $product_size,
        $serial_range,
        $paper_size,
        $custom_paper_size,
        $paper_type,
        $copies_per_set,
        $binding_type,
        $custom_binding,
        $special_instructions,
        $job_id
    );

    if ($stmt->execute()) {
        if ($difference != 0) {
            foreach (explode(',', $job['paper_sequence']) as $color) {
                $color = trim($color);
                $product = $mysqli->query("SELECT id FROM products WHERE product_type = '$paper_type' AND product_group = '$paper_size' AND product_name = '$color' LIMIT 1");

                if ($product && $product->num_rows > 0) {
                    $prod = $product->fetch_assoc();
                    $product_id = $prod['id'];
                    $note = "Adjustment from job order edit for $client_name";

                    $stmt2 = $mysqli->prepare("INSERT INTO usage_logs (product_id, used_sheets, log_date, job_order_id, usage_note) VALUES (?, ?, ?, ?, ?)");
                    $stmt2->bind_param("iisds", $product_id, $difference, $log_date, $job_id, $note);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }
        echo "✅ Job order updated successfully.";
    } else {
        echo "❌ Error updating job order: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Order</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .user-details h4 {
            font-weight: 500;
            font-size: 16px;
        }

        .user-details small {
            color: var(--gray);
            font-size: 14px;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background-color: rgba(66, 183, 42, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background-color: rgba(255, 77, 79, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Form Card */
        .form-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .form-card h2 {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: 20px;
            display: flex;
            align-items: center;
        }

        .form-card h2 i {
            margin-right: 10px;
            color: var(--primary);
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--gray);
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            background-color: var(--secondary);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--dark);
            margin-left: 10px;
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
        }

        /* Info Note */
        .info-note {
            padding: 15px;
            background-color: rgba(24, 119, 242, 0.05);
            border-radius: 6px;
            margin: 20px 0;
            font-size: 14px;
            color: var(--gray);
            border-left: 3px solid var(--primary);
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
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-info {
                margin-top: 10px;
            }
        }

        @media (max-width: 576px) {
            .form-card {
                padding: 15px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .btn-outline {
                margin-left: 0;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <header class="header">
            <h1>Edit Job Order #<?= $job_id ?></h1>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                    <small><?php echo $_SESSION['role']; ?></small>
                </div>
            </div>
        </header>

        <?php if (isset($message)): ?>
            <div class="alert <?php echo strpos($message, '❌') !== false ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <h2><i class="fas fa-edit"></i> Edit Job Order Details</h2>
            
            <form method="post">
                <div class="form-grid">
                    <!-- Client Information -->
                    <div class="form-group">
                        <label for="client_name">Client Name</label>
                        <input type="text" id="client_name" name="client_name" value="<?= htmlspecialchars($job['client_name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_address">Address</label>
                        <input type="text" id="client_address" name="client_address" value="<?= htmlspecialchars($job['client_address']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($job['contact_person']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($job['contact_number']) ?>" required>
                    </div>
                    
                    <!-- Project Information -->
                    <div class="form-group">
                        <label for="project_name">Project Name</label>
                        <input type="text" id="project_name" name="project_name" value="<?= htmlspecialchars($job['project_name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="serial_range">Serial Range</label>
                        <input type="text" id="serial_range" name="serial_range" value="<?= htmlspecialchars($job['serial_range']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="log_date">Order Date</label>
                        <input type="date" id="log_date" name="log_date" value="<?= $job['log_date'] ?>" required>
                    </div>
                    
                    <!-- Job Specifications -->
                    <div class="form-group">
                        <label for="quantity">Order Quantity</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="<?= $job['quantity'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="number_of_sets">Sets per Product</label>
                        <input type="number" id="number_of_sets" name="number_of_sets" min="1" value="<?= $job['number_of_sets'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_size">Product Size</label>
                        <select id="product_size" name="product_size" required>
                            <?php foreach (['whole','1/2','1/3','1/4','1/6','1/8'] as $size): ?>
                                <option value="<?= $size ?>" <?= $job['product_size'] == $size ? 'selected' : '' ?>><?= $size ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="paper_size">Paper Size</label>
                        <input type="text" id="paper_size" name="paper_size" value="<?= htmlspecialchars($job['paper_size']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="custom_paper_size">Custom Paper Size</label>
                        <input type="text" id="custom_paper_size" name="custom_paper_size" value="<?= htmlspecialchars($job['custom_paper_size']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="paper_type">Paper Type</label>
                        <input type="text" id="paper_type" name="paper_type" value="<?= htmlspecialchars($job['paper_type']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="copies_per_set">Copies per Set</label>
                        <input type="number" id="copies_per_set" name="copies_per_set" min="1" value="<?= $job['copies_per_set'] ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="binding_type">Binding Type</label>
                        <input type="text" id="binding_type" name="binding_type" value="<?= htmlspecialchars($job['binding_type']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="custom_binding">Custom Binding</label>
                        <input type="text" id="custom_binding" name="custom_binding" value="<?= htmlspecialchars($job['custom_binding']) ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="special_instructions">Special Instructions</label>
                    <textarea id="special_instructions" name="special_instructions"><?= htmlspecialchars($job['special_instructions']) ?></textarea>
                </div>
                
                <div class="info-note">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Paper sequence cannot be changed during editing.
                </div>
                
                <button type="submit" class="btn"><i class="fas fa-save"></i> Update Job Order</button>
                <a href="job_orders.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Job Orders</a>
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
    </script>
</body>
</html>
