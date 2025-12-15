<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

require_once '../../config/db.php';

// Get user and customer details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.username, u.email_verified,
                 p.first_name, p.middle_name, p.last_name, p.age, p.gender, 
                 p.birthdate, p.contact_number AS personal_contact, 
                 p.address_line1, p.city AS personal_city, 
                 p.province AS personal_province, p.zip_code AS personal_zip,
                 c.company_name, c.taxpayer_name, c.contact_person, 
                 c.contact_number AS company_contact, 
                 c.city AS company_city, c.province AS company_province, 
                 c.barangay, c.subd_or_street, c.building_or_block, 
                 c.lot_or_room_no, c.zip_code AS company_zip
          FROM users u
          LEFT JOIN personal_customers p ON u.id = p.user_id
          LEFT JOIN company_customers c ON u.id = c.user_id
          WHERE u.id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Check if user is personal or company customer
$is_personal = !empty($user_data['first_name']);
$is_company = !empty($user_data['company_name']);

// Initialize variables
$error = '';
$success = '';
$field_errors = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Personal customer update
    if ($is_personal) {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $birthdate = $_POST['birthdate'] ?? null;
        $personal_contact = trim($_POST['personal_contact'] ?? '');
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $p_city = trim($_POST['p_city'] ?? '');
        $p_province = trim($_POST['p_province'] ?? '');
        $p_zip = trim($_POST['p_zip'] ?? '');

        // Validation
        if (empty($first_name)) {
            $field_errors['first_name'] = "First name is required";
        }
        if (empty($last_name)) {
            $field_errors['last_name'] = "Last name is required";
        }
        if (empty($personal_contact)) {
            $field_errors['personal_contact'] = "Contact number is required";
        } elseif (!preg_match('/^[0-9+\-\s]{10,15}$/', $personal_contact)) {
            $field_errors['personal_contact'] = "Invalid contact number format";
        }

        // Calculate age from birthdate
        $age = null;
        if (!empty($birthdate)) {
            $birth_date = new DateTime($birthdate);
            $today = new DateTime();
            $age = $today->diff($birth_date)->y;
        }

        if (empty($field_errors)) {
            // Start transaction
            $inventory->begin_transaction();

            try {
                // Update personal customer
                $update_query = "UPDATE personal_customers SET 
                    first_name = ?, 
                    middle_name = ?, 
                    last_name = ?, 
                    age = ?, 
                    gender = ?, 
                    birthdate = ?, 
                    contact_number = ?, 
                    address_line1 = ?, 
                    city = ?, 
                    province = ?, 
                    zip_code = ? 
                    WHERE user_id = ?";
                
                $update_stmt = $inventory->prepare($update_query);
                $update_stmt->bind_param(
                    "sssisssssssi",
                    $first_name,
                    $middle_name,
                    $last_name,
                    $age,
                    $gender,
                    $birthdate,
                    $personal_contact,
                    $address_line1,
                    $p_city,
                    $p_province,
                    $p_zip,
                    $user_id
                );

                if ($update_stmt->execute()) {
                    $inventory->commit();
                    $success = "Profile updated successfully!";
                    // Refresh user data
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_data = $result->fetch_assoc();
                } else {
                    $inventory->rollback();
                    $error = "Failed to update profile. Please try again.";
                }
                $update_stmt->close();
                
            } catch (Exception $e) {
                $inventory->rollback();
                $error = "An error occurred: " . $e->getMessage();
            }
        }
    }
    // Company customer update
    elseif ($is_company) {
        $company_name = trim($_POST['company_name'] ?? '');
        $taxpayer_name = trim($_POST['taxpayer_name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $company_contact = trim($_POST['company_contact'] ?? '');
        $c_province = trim($_POST['c_province'] ?? '');
        $c_city = trim($_POST['c_city'] ?? '');
        $c_barangay = trim($_POST['c_barangay'] ?? '');
        $c_street = trim($_POST['c_street'] ?? '');
        $c_building = trim($_POST['c_building'] ?? '');
        $c_lotroom = trim($_POST['c_lotroom'] ?? '');
        $c_zip = trim($_POST['c_zip'] ?? '');

        // Validation
        if (empty($company_name)) {
            $field_errors['company_name'] = "Company name is required";
        }
        if (empty($contact_person)) {
            $field_errors['contact_person'] = "Contact person is required";
        }
        if (empty($company_contact)) {
            $field_errors['company_contact'] = "Contact number is required";
        } elseif (!preg_match('/^[0-9+\-\s]{10,15}$/', $company_contact)) {
            $field_errors['company_contact'] = "Invalid contact number format";
        }

        if (empty($field_errors)) {
            // Start transaction
            $inventory->begin_transaction();

            try {
                // Update company customer
                $update_query = "UPDATE company_customers SET 
                    company_name = ?, 
                    taxpayer_name = ?, 
                    contact_person = ?, 
                    contact_number = ?, 
                    province = ?, 
                    city = ?, 
                    barangay = ?, 
                    subd_or_street = ?, 
                    building_or_block = ?, 
                    lot_or_room_no = ?, 
                    zip_code = ? 
                    WHERE user_id = ?";
                
                $update_stmt = $inventory->prepare($update_query);
                $update_stmt->bind_param(
                    "sssssssssssi",
                    $company_name,
                    $taxpayer_name,
                    $contact_person,
                    $company_contact,
                    $c_province,
                    $c_city,
                    $c_barangay,
                    $c_street,
                    $c_building,
                    $c_lotroom,
                    $c_zip,
                    $user_id
                );

                if ($update_stmt->execute()) {
                    $inventory->commit();
                    $success = "Company profile updated successfully!";
                    // Refresh user data
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_data = $result->fetch_assoc();
                } else {
                    $inventory->rollback();
                    $error = "Failed to update profile. Please try again.";
                }
                $update_stmt->close();
                
            } catch (Exception $e) {
                $inventory->rollback();
                $error = "An error occurred: " . $e->getMessage();
            }
        }
    }

    // Handle password change
    if (!empty($_POST['current_password']) || !empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password)) {
            $field_errors['current_password'] = "Current password is required";
        }
        if (empty($new_password)) {
            $field_errors['new_password'] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $field_errors['new_password'] = "New password must be at least 8 characters";
        }
        if ($new_password !== $confirm_password) {
            $field_errors['confirm_password'] = "New passwords do not match";
        }

        if (empty($field_errors['current_password'] ?? null) && 
            empty($field_errors['new_password'] ?? null) && 
            empty($field_errors['confirm_password'] ?? null)) {
            
            // Verify current password
            $check_query = "SELECT password FROM users WHERE id = ?";
            $check_stmt = $inventory->prepare($check_query);
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $user = $check_result->fetch_assoc();
            $check_stmt->close();

            if ($user && password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pass_stmt = $inventory->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_pass_stmt->bind_param("si", $hashed_password, $user_id);

                if ($update_pass_stmt->execute()) {
                    if (empty($success)) {
                        $success = "Password changed successfully!";
                    } else {
                        $success .= " Password also changed successfully!";
                    }
                } else {
                    $error = $error ? $error . " Also failed to change password." : "Failed to change password.";
                }
                $update_pass_stmt->close();
            } else {
                $field_errors['current_password'] = "Current password is incorrect";
            }
        }
    }
}

// Get cart count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT SUM(ci.quantity) as total_items 
              FROM cart_items ci 
              JOIN carts c ON ci.cart_id = c.cart_id 
              WHERE c.user_id = ?";
    $stmt = $inventory->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result_cart = $stmt->get_result();
    $row = $result_cart->fetch_assoc();
    $cart_count = $row['total_items'] ? $row['total_items'] : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="icon" type="image/png" href="../../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        /* Edit Profile Specific Styles */
        .edit-profile-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 80vh;
        }
        
        .edit-profile-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px;
            background: var(--bg-white);
            box-shadow: var(--shadow);
        }
        
        .edit-profile-header h1 {
            font-size: 2.5em;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .edit-profile-header p {
            font-size: 1.2em;
            color: var(--text-light);
        }
        
        .edit-profile-form-container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--bg-white);
            padding: 40px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        
        .form-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section:last-of-type {
            border-bottom: none;
        }
        
        .section-title {
            color: var(--text-dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary-color);
            font-size: 1.5em;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-label .required {
            color: var(--accent-color);
            margin-left: 3px;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1em;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }
        
        .form-input.error, .form-select.error, .form-textarea.error {
            border-color: var(--accent-color);
        }
        
        .error-message {
            color: var(--accent-color);
            font-size: 0.85em;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .form-help {
            color: var(--text-light);
            font-size: 0.85em;
            margin-top: 5px;
            font-style: italic;
        }
        
        .age-display {
            background: var(--bg-light);
            padding: 12px 16px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 0.95em;
            color: var(--text-dark);
            border-left: 3px solid var(--primary-color);
        }
        
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.3);
        }
        
        .btn-secondary {
            background: var(--text-light);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: var(--bg-light);
            border-color: var(--text-light);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .error-message-global {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 5px;
        }
        
        .password-input-container {
            position: relative;
        }
        
        .form-note {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid var(--primary-color);
            margin: 20px 0;
            font-size: 0.9em;
            color: var(--text-light);
        }
        
        .email-verification {
            display: flex;
            align-items: center;
            justify-content: space-around;
            gap: 10px;
        }
        
        .email-verified {
            color: #28a745;
            font-weight: 600;
        }
        
        .email-not-verified {
            color: #dc3545;
            font-weight: 600;
        }
        
        .verification-btn {
            padding: 6px 12px;
            background: var(--primary-color);
            color: white;
            border: 2px solid var(--primary-color);
            font-size: 0.85em;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .verification-btn:hover {
            background-color: transparent;
            color: black;
        }
        
        .account-type-badge {
            display: inline-block;
            padding: 6px 12px;
            background: var(--bg-light);
            color: var(--text-dark);
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .account-type-personal {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .account-type-company {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .action-btn {
            padding: 12px 25px;
            background: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            font-size: 1em;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-weight: 500;
            font-family: 'Poppins';
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(44, 90, 160, 0.3);
        }
        
        @media (max-width: 768px) {
            .edit-profile-header, .edit-profile-form-container {
                font-size: 80%;
                margin: 20px;
            }
            
            .edit-profile-header h1 {
                font-size: 2em;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-section {
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            
            .form-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .edit-profile-page {
                padding: 20px 0;
            }
            
            .edit-profile-header {
                padding: 25px 15px;
            }
            
            .edit-profile-form-container {
                padding: 25px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="#" class="logo">
                    <img src="../../assets/images/plainlogo.png" alt="Active Media" class="logo-image">
                    <span>Active Media Designs & Printing</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="../../website/main.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="../../website/ai_image.php"><i class="fas fa-robot"></i> AI Services</a></li>
                    <li><a href="../../website/about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="../../website/contact.php"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>

                <div class="features">
                    <a href="#" class="chat-icon" id="chatButton">
                        <i class="fas fa-comments"></i>
                        <span class="chat-count" id="chatCount">0</span>
                    </a>
                    <a href="../../website/view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                </div>
                
                <div class="user-info">
                    <a href="../website/profile.php" class="user-profile">
                        <i class="fas fa-user"></i>
                        <span class="user-name">
                            <?php
                            if (!empty($user_data['first_name'])) {
                                echo htmlspecialchars($user_data['first_name']);
                            } elseif (!empty($user_data['company_name'])) {
                                echo htmlspecialchars($user_data['company_name']);
                            } else {
                                echo 'User';
                            }
                            ?>
                        </span>
                    </a>
                    <a href="../../accounts/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
                
                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <!-- Edit Profile Section -->
    <section class="edit-profile-page">
        <div class="container">
            <div class="edit-profile-header">
                <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
                <p>Update your personal information and account settings</p>
                <div class="email-verification">
                    <?php if ($user_data['email_verified']): ?>
                        <span class="email-verified">
                            <i class="fas fa-check-circle"></i> Email Verified
                        </span>
                    <?php else: ?>
                        <span class="email-not-verified">
                            <i class="fas fa-exclamation-circle"></i> Email Not Verified
                        </span>
                        <a href="../../accounts/email-verification.php" class="verification-btn">
                            <i class="fas fa-envelope"></i> Verify Now
                        </a>
                    <?php endif; ?>
                    <span class="account-type-badge <?php echo $is_personal ? 'account-type-personal' : 'account-type-company'; ?>">
                        <?php echo $is_personal ? 'Personal Account' : 'Company Account'; ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="success-message" id="successMessage">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-message-global">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" class="edit-profile-form-container" id="editProfileForm">
                <?php if ($is_personal): ?>
                    <!-- Personal Customer Form -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-user-circle"></i> Personal Information
                        </h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">First Name <span class="required">*</span></label>
                                <input type="text" 
                                       name="first_name" 
                                       class="form-input <?php echo isset($field_errors['first_name']) ? 'error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>"
                                       required>
                                <?php if (isset($field_errors['first_name'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($field_errors['first_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Middle Name</label>
                                <input type="text" 
                                       name="middle_name" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['middle_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Last Name <span class="required">*</span></label>
                                <input type="text" 
                                       name="last_name" 
                                       class="form-input <?php echo isset($field_errors['last_name']) ? 'error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>"
                                       required>
                                <?php if (isset($field_errors['last_name'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($field_errors['last_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo ($user_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($user_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($user_data['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Birthdate</label>
                                <input type="date" 
                                       name="birthdate" 
                                       id="birthdate"
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['birthdate'] ?? ''); ?>"
                                       onchange="calculateAgeFromDate()">
                                <div id="ageDisplay" class="age-display">
                                    <?php if (!empty($user_data['age'])): ?>
                                        Age: <?php echo htmlspecialchars($user_data['age']); ?> years old
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Contact Number <span class="required">*</span></label>
                                <input type="text" 
                                       name="personal_contact" 
                                       class="form-input <?php echo isset($field_errors['personal_contact']) ? 'error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($user_data['personal_contact'] ?? ''); ?>"
                                       placeholder="e.g., 09123456789"
                                       required>
                                <?php if (isset($field_errors['personal_contact'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($field_errors['personal_contact']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="form-help">Format: 09XXXXXXXXX or +639XXXXXXXXX</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-home"></i> Address Information
                        </h2>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">Address Line</label>
                                <input type="text" 
                                       name="address_line1" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['address_line1'] ?? ''); ?>"
                                       placeholder="Lot No., Block No., Phase No. Street, Subd.">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" 
                                       name="p_city" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['personal_city'] ?? ''); ?>"
                                       placeholder="City">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Province</label>
                                <input type="text" 
                                       name="p_province" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['personal_province'] ?? ''); ?>"
                                       placeholder="Province">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">ZIP Code</label>
                                <input type="text" 
                                       name="p_zip" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['personal_zip'] ?? ''); ?>"
                                       placeholder="ZIP Code">
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($is_company): ?>
                    <!-- Company Customer Form -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-building"></i> Company Information
                        </h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Company Name <span class="required">*</span></label>
                                <input type="text" 
                                       name="company_name" 
                                       class="form-input <?php echo isset($field_errors['company_name']) ? 'error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($user_data['company_name'] ?? ''); ?>"
                                       required>
                                <?php if (isset($field_errors['company_name'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($field_errors['company_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Taxpayer Name</label>
                                <input type="text" 
                                       name="taxpayer_name" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['taxpayer_name'] ?? ''); ?>"
                                       placeholder="Taxpayer Name (if different from company)">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Contact Person <span class="required">*</span></label>
                                <input type="text" 
                                       name="contact_person" 
                                       class="form-input <?php echo isset($field_errors['contact_person']) ? 'error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($user_data['contact_person'] ?? ''); ?>"
                                       required>
                                <?php if (isset($field_errors['contact_person'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($field_errors['contact_person']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Contact Number <span class="required">*</span></label>
                                <input type="text" 
                                       name="company_contact" 
                                       class="form-input <?php echo isset($field_errors['company_contact']) ? 'error' : ''; ?>"
                                       value="<?php echo htmlspecialchars($user_data['company_contact'] ?? ''); ?>"
                                       placeholder="e.g., 09123456789"
                                       required>
                                <?php if (isset($field_errors['company_contact'])): ?>
                                    <div class="error-message">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <?php echo htmlspecialchars($field_errors['company_contact']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="form-help">Format: 09XXXXXXXXX or +639XXXXXXXXX</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-map-marked-alt"></i> Company Address
                        </h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Province</label>
                                <input type="text" 
                                       name="c_province" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['company_province'] ?? ''); ?>"
                                       placeholder="Province">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">City</label>
                                <input type="text" 
                                       name="c_city" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['company_city'] ?? ''); ?>"
                                       placeholder="City">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Barangay</label>
                                <input type="text" 
                                       name="c_barangay" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['barangay'] ?? ''); ?>"
                                       placeholder="Barangay">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Subdivision/Street</label>
                                <input type="text" 
                                       name="c_street" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['subd_or_street'] ?? ''); ?>"
                                       placeholder="Subdivision or Street">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Building/Block</label>
                                <input type="text" 
                                       name="c_building" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['building_or_block'] ?? ''); ?>"
                                       placeholder="Building or Block">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Lot/Room No.</label>
                                <input type="text" 
                                       name="c_lotroom" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['lot_or_room_no'] ?? ''); ?>"
                                       placeholder="Lot or Room Number">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">ZIP Code</label>
                                <input type="text" 
                                       name="c_zip" 
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['company_zip'] ?? ''); ?>"
                                       placeholder="ZIP Code">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Password Change Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-lock"></i> Change Password
                    </h2>
                    <div class="form-note">
                        <i class="fas fa-info-circle"></i> 
                        Leave password fields blank if you don't want to change your password.
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <div class="password-input-container">
                                <input type="password" 
                                       name="current_password" 
                                       id="currentPassword"
                                       class="form-input <?php echo isset($field_errors['current_password']) ? 'error' : ''; ?>"
                                       autocomplete="current-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('currentPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($field_errors['current_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($field_errors['current_password']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <div class="password-input-container">
                                <input type="password" 
                                       name="new_password" 
                                       id="newPassword"
                                       class="form-input <?php echo isset($field_errors['new_password']) ? 'error' : ''; ?>"
                                       autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($field_errors['new_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($field_errors['new_password']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="form-help">Must be at least 8 characters long</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <div class="password-input-container">
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirmPassword"
                                       class="form-input <?php echo isset($field_errors['confirm_password']) ? 'error' : ''; ?>"
                                       autocomplete="new-password">
                                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($field_errors['confirm_password'])): ?>
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($field_errors['confirm_password']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" class="action-btn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>AMDP</h3>
                    <p>Professional printing services with quality, speed, and precision for all your business needs.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/profile.php?id=100063881538670"><i class="fab fa-facebook-f"></i></a>
                        <a href=""><i class="fab fa-twitter"></i></a>
                        <a href=""><i class="fab fa-instagram"></i></a>
                        <a href=""><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="../../website/main.php#offset">Offset Printing</a></li>
                        <li><a href="../../website/main.php#digital">Digital Printing</a></li>
                        <li><a href="../../website/main.php#riso">RISO Printing</a></li>
                        <li><a href="../../website/main.php#other">Other Services</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="../../website/about.php">About Us</a></li>
                        <li><a href="../../website/about.php">Our Team</a></li>
                        <li><a href="../../website/about.php">Careers</a></li>
                        <li><a href="../../website/about.php">Testimonials</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="../../website/contact.php">Contact Us</a></li>
                        <li><a href="../../website/contact.php">FAQ</a></li>
                        <li><a href="../../website/contact.php">Shipping Info</a></li>
                        <li><a href="../../website/contact.php">Returns</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i>Fausta Rd Lucero St Mabolo, Malolos, Philippines</li>
                        <li><i class="fas fa-phone"></i> (044) 796-4101</li>
                        <li><i class="fas fa-envelope"></i> activemediaprint@gmail.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2025 Active Media Designs & Printing. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="">Privacy Policy</a>
                    <a href="">Terms of Service</a>
                    <a href="">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Chat Widget -->
    <div class="chat-widget" id="chatWidget">
        <div class="chat-header">
            <button class="chat-back-btn" id="chatBackBtn">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 class="chat-title" id="chatTitle">Messages</h3>
            <button class="chat-close" id="chatCloseBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-body">
            <!-- Conversations List -->
            <div class="chat-conversations" id="chatConversations">
                <button class="chat-new-btn" id="newChatBtn">
                    <i class="fas fa-plus"></i> New Conversation
                </button>
                <div id="conversationsList"></div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages" id="chatMessages">
                <div class="messages-list" id="messagesList"></div>
                <div class="chat-input-area" id="chatInputArea">
                    <div class="chat-input-wrapper">
                        <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1"></textarea>
                        <button class="chat-send-btn" id="chatSendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/main.js"></script>
    <script>
        // Profile-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize form functionality
            setupFormValidation();
            
            // Auto-hide success message
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    successMessage.style.transition = 'opacity 0.5s';
                    
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // Calculate age if birthdate exists
            const birthdateInput = document.getElementById('birthdate');
            if (birthdateInput && birthdateInput.value) {
                calculateAgeFromDate();
            }
        });
        
        function setupFormValidation() {
            const form = document.getElementById('editProfileForm');
            if (!form) return;
            
            form.addEventListener('submit', function(event) {
                let isValid = true;
                
                // Clear previous error highlights
                document.querySelectorAll('.form-input.error, .form-select.error').forEach(el => {
                    el.classList.remove('error');
                });
                
                // Validate required fields
                const requiredFields = form.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('error');
                        
                        // Create error message if it doesn't exist
                        let errorDiv = field.nextElementSibling;
                        if (!errorDiv || !errorDiv.classList.contains('error-message')) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'error-message';
                            errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> This field is required';
                            field.parentNode.insertBefore(errorDiv, field.nextSibling);
                        }
                    }
                });
                
                // Validate password fields if any are filled
                const currentPassword = document.getElementById('currentPassword');
                const newPassword = document.getElementById('newPassword');
                const confirmPassword = document.getElementById('confirmPassword');
                
                const passwordFieldsFilled = currentPassword.value || newPassword.value || confirmPassword.value;
                
                if (passwordFieldsFilled) {
                    if (!currentPassword.value.trim()) {
                        isValid = false;
                        currentPassword.classList.add('error');
                    }
                    
                    if (!newPassword.value.trim()) {
                        isValid = false;
                        newPassword.classList.add('error');
                    } else if (newPassword.value.length < 8) {
                        isValid = false;
                        newPassword.classList.add('error');
                    }
                    
                    if (newPassword.value !== confirmPassword.value) {
                        isValid = false;
                        confirmPassword.classList.add('error');
                    }
                }
                
                if (!isValid) {
                    event.preventDefault();
                    
                    // Scroll to first error
                    const firstError = form.querySelector('.error');
                    if (firstError) {
                        firstError.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        firstError.focus();
                    }
                }
            });
        }
        
        function calculateAgeFromDate() {
            const birthdateInput = document.getElementById('birthdate');
            const ageDisplay = document.getElementById('ageDisplay');
            
            if (!birthdateInput || !birthdateInput.value || !ageDisplay) return;
            
            const birthdate = new Date(birthdateInput.value);
            const today = new Date();
            
            let age = today.getFullYear() - birthdate.getFullYear();
            const monthDiff = today.getMonth() - birthdate.getMonth();
            
            // Adjust age if birthday hasn't occurred this year
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                age--;
            }
            
            if (age < 0) {
                ageDisplay.textContent = 'Invalid birth date (future date)';
                ageDisplay.style.color = 'var(--accent-color)';
            } else if (age > 120) {
                ageDisplay.textContent = 'Age: ' + age + ' (please verify birth date)';
                ageDisplay.style.color = '#ff9800';
            } else {
                ageDisplay.textContent = 'Age: ' + age + ' years old';
                ageDisplay.style.color = 'var(--text-dark)';
            }
        }
        
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Format phone number as user types
        document.addEventListener('input', function(e) {
            if (e.target.name === 'personal_contact' || e.target.name === 'company_contact') {
                let value = e.target.value.replace(/\D/g, '');
                
                // Add +63 prefix if starts with 09
                if (value.startsWith('09') && value.length >= 10) {
                    value = '63' + value.substring(1);
                }
                
                // Format the number
                if (value.length > 0) {
                    if (value.startsWith('63')) {
                        value = '+63' + value.substring(2);
                    }
                    
                    // Add space after every 4 digits for readability
                    value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
                }
                
                e.target.value = value;
            }
        });
        
        // Show confirmation before leaving page if form has changes
        let formChanged = false;
        const form = document.getElementById('editProfileForm');
        if (form) {
            const initialValues = new FormData(form);
            
            form.addEventListener('input', function() {
                const currentValues = new FormData(form);
                formChanged = !arraysEqual([...initialValues], [...currentValues]);
            });
            
            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
            
            // Reset formChanged on submit
            form.addEventListener('submit', function() {
                formChanged = false;
            });
        }
        
        function arraysEqual(a, b) {
            if (a.length !== b.length) return false;
            
            a.sort();
            b.sort();
            
            for (let i = 0; i < a.length; i++) {
                if (a[i][0] !== b[i][0] || a[i][1] !== b[i][1]) {
                    return false;
                }
            }
            
            return true;
        }
    </script>
    <script>
        // Chat functionality
        let currentConversationId = null;
        let chatRefreshInterval = null;

        // Initialize chat when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Setup event listeners
            const chatButton = document.getElementById('chatButton');
            const chatCloseBtn = document.getElementById('chatCloseBtn');
            const chatBackBtn = document.getElementById('chatBackBtn');
            const newChatBtn = document.getElementById('newChatBtn');
            const chatSendBtn = document.getElementById('chatSendBtn');
            const chatInput = document.getElementById('chatInput');

            if (chatButton) {
                chatButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleChat();
                });
            }

            if (chatCloseBtn) {
                chatCloseBtn.addEventListener('click', toggleChat);
            }

            if (chatBackBtn) {
                chatBackBtn.addEventListener('click', goBackToConversations);
            }

            if (newChatBtn) {
                newChatBtn.addEventListener('click', startNewConversation);
            }

            if (chatSendBtn) {
                chatSendBtn.addEventListener('click', sendMessage);
            }

            if (chatInput) {
                chatInput.addEventListener('input', function() {
                    autoResize(this);
                });

                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }

            // Load initial unread count
            updateUnreadCount();

            // Check for unread messages every minute
            setInterval(updateUnreadCount, 60000);
        });

        // Toggle chat widget
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            if (widget) {
                widget.classList.toggle('open');

                if (widget.classList.contains('open')) {
                    loadConversations();
                    startChatRefresh();
                } else {
                    stopChatRefresh();
                }
            }
        }

        // Load conversations
        async function loadConversations() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversations');
                const data = await response.json();

                if (data.success) {
                    renderConversations(data.data);
                    updateUnreadCount();

                    // Show conversation count in the UI
                    updateConversationCount(data.data.length);
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
                showChatError('Failed to load conversations. Please try again.');
            }
        }

        // Add this function to check conversation limit
        async function checkConversationLimit() {
            try {
                const response = await fetch('../../api/chat_api.php?action=conversation_limit');
                const data = await response.json();

                if (data.success) {
                    return {
                        reached: data.reached || false,
                        count: data.count || 0,
                        limit: data.limit || 3
                    };
                }
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            } catch (error) {
                console.error('Error checking conversation limit:', error);
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            }
        }

        // Render conversations list with delete buttons
        function renderConversations(conversations) {
            const container = document.getElementById('conversationsList');
            if (!container) return;

            if (conversations.length === 0) {
                container.innerHTML = `
                <div class="chat-empty">
                    <i class="fas fa-comments"></i>
                    <p>No conversations yet</p>
                </div>
            `;
                return;
            }

            container.innerHTML = conversations.map(conv => `
            <div class="chat-conversation-item ${currentConversationId === conv.id ? 'active' : ''}" 
                 onclick="openConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation')}')">
                <div class="conversation-header">
                    <div class="conversation-name">${escapeHtml(conv.title || 'Conversation #' + conv.id)}</div>
                    <button class="delete-conversation-btn" onclick="event.stopPropagation(); deleteConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation #' + conv.id)}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="conversation-last-message">${escapeHtml(conv.last_message || 'No messages yet')}</div>
                <div class="conversation-footer">
                    <div class="conversation-time">${formatTime(conv.last_message_time)}</div>
                    ${conv.unread_count > 0 ? `<div class="conversation-unread">${conv.unread_count} new</div>` : ''}
                </div>
            </div>
        `).join('');
        }

        // Delete conversation
        async function deleteConversation(conversationId, conversationTitle) {
            if (!confirm(`Are you sure you want to delete "${conversationTitle}"? This action cannot be undone.`)) {
                return;
            }

            try {
                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_conversation',
                        conversation_id: conversationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // If we're currently viewing this conversation, go back to list
                    if (currentConversationId === conversationId) {
                        goBackToConversations();
                    }

                    // Remove the conversation item from UI
                    const conversationItem = document.querySelector(`.chat-conversation-item[onclick*="${conversationId}"]`);
                    if (conversationItem) {
                        conversationItem.remove();
                    }

                    // Reload conversations list
                    await loadConversations();

                    showChatSuccess('Conversation deleted successfully.');
                } else {
                    showChatError(data.message || 'Failed to delete conversation.');
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
                showChatError('Failed to delete conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Open conversation
        function openConversation(conversationId, title) {
            currentConversationId = conversationId;

            // Update UI
            document.getElementById('chatConversations').style.display = 'none';
            document.getElementById('chatMessages').classList.add('active');
            document.getElementById('chatInputArea').classList.add('active');
            document.getElementById('chatBackBtn').classList.add('visible');
            document.getElementById('chatTitle').textContent = title;

            // Load messages
            loadMessages(conversationId);

            // Mark as read
            markAsRead(conversationId);
        }

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;

            document.getElementById('chatConversations').style.display = 'block';
            document.getElementById('chatMessages').classList.remove('active');
            document.getElementById('chatInputArea').classList.remove('active');
            document.getElementById('chatBackBtn').classList.remove('visible');
            document.getElementById('chatTitle').textContent = 'Messages';

            loadConversations();
        }

        // Load messages
        async function loadMessages(conversationId) {
            try {
                const response = await fetch(`../../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
                const data = await response.json();

                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showChatError('Failed to load messages. Please try again.');
            }
        }

        // Render messages
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!container) return;

            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == userId;
                const isSystem = msg.message_type === 'system';
                const isAdmin = msg.sender_role === 'admin';

                return `
                <div class="message-item ${isSent ? 'sent' : 'received'} ${isSystem ? 'system' : ''}">
                    ${!isSent && !isSystem ? `
                        <div class="message-sender">
                            ${escapeHtml(msg.sender_display_name || msg.sender_username)}
                        </div>
                    ` : ''}
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(msg.message)}</div>
                        <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                    </div>
                </div>
            `;
            }).join('');
        }

        // Send message
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();

            if (!message || !currentConversationId) return;

            // Disable send button
            const sendBtn = document.getElementById('chatSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            try {
                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'send_message',
                        conversation_id: currentConversationId,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    input.value = '';
                    autoResize(input);
                    loadMessages(currentConversationId);
                    updateUnreadCount();
                } else {
                    showChatError(data.message || 'Failed to send message.');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showChatError('Failed to send message. Please try again.');
            } finally {
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        // Start new conversation with online admin
        async function startNewConversation() {
            try {
                // First check if user has reached conversation limit
                const limitCheck = await checkConversationLimit();
                if (limitCheck.reached) {
                    showChatError(`You have reached the maximum limit of 3 active conversations. You currently have ${limitCheck.count} active conversations. Please complete or close existing conversations before starting a new one.`);
                    return;
                }

                showChatLoading(true);

                const response = await fetch('../../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start_conversation',
                        title: 'Support Request',
                        request_online_admin: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const adminInfo = data.admin_name ? ` (Connected with: ${data.admin_name})` : '';
                    openConversation(data.conversation_id, 'Support Request');

                    if (data.admin_name) {
                        showSystemMessage(`You've been connected with administrator ${data.admin_name}. How can we help you?`);
                    }
                } else {
                    // Handle "no admin available" gracefully
                    if (data.message && data.message.includes('No administrators')) {
                        showChatError('No administrators are currently available. Please try again later or contact support via email.');
                    } else if (data.message && data.message.includes('maximum limit')) {
                        showChatError(data.message);
                        // Refresh conversations list to show current count
                        loadConversations();
                    } else {
                        showChatError(data.message || 'Failed to start conversation.');
                    }
                }
            } catch (error) {
                console.error('Error starting conversation:', error);
                showChatError('Failed to start conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Add this function to update conversation count display
        function updateConversationCount(count) {
            // Update the "New Conversation" button text
            const newChatBtn = document.getElementById('newChatBtn');
            if (newChatBtn) {
                const limitReached = count >= 3;
                newChatBtn.innerHTML = `<i class="fas fa-plus"></i> New Conversation (${count}/3)`;
                newChatBtn.disabled = limitReached;
                newChatBtn.title = limitReached ? 'Maximum 3 conversations reached' : 'Start a new conversation';

                if (limitReached) {
                    newChatBtn.classList.add('limit-reached');
                } else {
                    newChatBtn.classList.remove('limit-reached');
                }
            }

            // Also update conversation limit warning in conversations list
            const conversationsList = document.getElementById('conversationsList');
            if (conversationsList && count >= 3) {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (!warningElement) {
                    const warningDiv = document.createElement('div');
                    warningDiv.id = 'conversationLimitWarning';
                    warningDiv.className = 'conversation-limit-warning';
                    warningDiv.innerHTML = `
                    <div class="limit-warning-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Maximum conversations reached</strong>
                            <small>You have ${count} active conversations (maximum: 3). Please close or complete existing conversations to start new ones.</small>
                        </div>
                    </div>
                `;
                    conversationsList.parentNode.insertBefore(warningDiv, conversationsList);
                }
            } else {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (warningElement) {
                    warningElement.remove();
                }
            }
        }

        // Helper function to show system message
        function showSystemMessage(message) {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            const systemMessage = document.createElement('div');
            systemMessage.className = 'message-item system';
            systemMessage.innerHTML = `
            <div class="message-bubble">
                <div class="message-text">${escapeHtml(message)}</div>
                <div class="message-time">${formatMessageTime(new Date().toISOString())}</div>
            </div>
        `;
            messagesList.appendChild(systemMessage);
            messagesList.scrollTop = messagesList.scrollHeight;
        }

        // Add loading indicator
        function showChatLoading(show) {
            let loader = document.getElementById('chatLoader');
            if (!loader && show) {
                loader = document.createElement('div');
                loader.id = 'chatLoader';
                loader.className = 'chat-loader';
                loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                document.getElementById('chatMessages').prepend(loader);
            } else if (loader && !show) {
                loader.remove();
            }
        }

        // Show success message
        function showChatSuccess(message) {
            // Create success notification
            const successDiv = document.createElement('div');
            successDiv.className = 'chat-success-notification';
            successDiv.innerHTML = `
            <div class="success-content">
                <i class="fas fa-check-circle"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;

            // Add to chat widget
            const chatBody = document.querySelector('.chat-body');
            if (chatBody) {
                chatBody.prepend(successDiv);

                // Auto-remove after 3 seconds
                setTimeout(() => {
                    successDiv.remove();
                }, 3000);
            } else {
                alert(message); // Fallback
            }
        }

        // Mark messages as read
        async function markAsRead(conversationId) {
            // This happens automatically when loading messages via the API
            updateUnreadCount();
        }

        // Update unread count
        async function updateUnreadCount() {
            try {
                const response = await fetch('../../api/chat_api.php?action=unread_count');
                const data = await response.json();

                if (data.success) {
                    const count = data.count || 0;
                    const chatCount = document.getElementById('chatCount');
                    if (chatCount) {
                        chatCount.textContent = count;
                        chatCount.style.display = count > 0 ? 'flex' : 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating unread count:', error);
            }
        }

        // Start auto-refresh
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    loadMessages(currentConversationId);
                }
                updateUnreadCount();
            }, 5000); // Refresh every 5 seconds
        }

        // Stop auto-refresh
        function stopChatRefresh() {
            if (chatRefreshInterval) {
                clearInterval(chatRefreshInterval);
                chatRefreshInterval = null;
            }
        }

        // Helper functions
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 86400000) { // Less than 1 day
                return date.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } else if (diff < 604800000) { // Less than 1 week
                return date.toLocaleDateString([], {
                    weekday: 'short'
                });
            } else {
                return date.toLocaleDateString([], {
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function formatMessageTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-resize textarea
        function autoResize(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        // Show chat error
        function showChatError(message) {
            // You can implement a notification system here
            console.error('Chat Error:', message);
            alert(message); // Simple alert for now
        }

        // Make functions available globally
        window.toggleChat = toggleChat;
        window.openConversation = openConversation;
        window.goBackToConversations = goBackToConversations;
        window.sendMessage = sendMessage;
        window.startNewConversation = startNewConversation;
        window.deleteConversation = deleteConversation;
    </script>
</body>
</html>

<?php
// Close database connection
if (isset($stmt)) {
    $stmt->close();
}
if (isset($inventory)) {
    $inventory->close();
}
?>