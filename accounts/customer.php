<?php
require_once '../config/db.php';
require_once '../config/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

$message = '';
$success = false;
$errors = [];

// Load form data from session or initialize empty array
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Extract all form fields with default values
$customer_type    = $form_data['customer_type'] ?? '';
$first_name       = $form_data['first_name'] ?? '';
$middle_name      = $form_data['middle_name'] ?? '';
$last_name        = $form_data['last_name'] ?? '';
$age              = $form_data['age'] ?? '';
$gender           = $form_data['gender'] ?? '';
$birthdate        = $form_data['birthdate'] ?? '';
$personal_contact = $form_data['personal_contact'] ?? '';

$company_name     = $form_data['company_name'] ?? '';
$taxpayer_name    = $form_data['taxpayer_name'] ?? '';
$contact_person   = $form_data['contact_person'] ?? '';
$company_contact  = $form_data['company_contact'] ?? '';

$address_line1    = $form_data['address_line1'] ?? '';
$p_city           = $form_data['p_city'] ?? '';
$p_province       = $form_data['p_province'] ?? '';
$p_zip            = $form_data['p_zip'] ?? '';

$c_province       = $form_data['c_province'] ?? '';
$c_city           = $form_data['c_city'] ?? '';
$c_barangay       = $form_data['c_barangay'] ?? '';
$c_street         = $form_data['c_street'] ?? '';
$c_building       = $form_data['c_building'] ?? '';
$c_lotroom        = $form_data['c_lotroom'] ?? '';
$c_zip            = $form_data['c_zip'] ?? '';

$username         = $form_data['username'] ?? '';
$password         = $form_data['password'] ?? '';
$confirm_password = $form_data['confirm_password'] ?? '';

// Function to send verification email using same setup as forgot-password
function sendVerificationEmail($email, $verification_token, $customer_type = 'personal') {
    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $verify_link = $base_url . "/inventory/accounts/email-verification.php?token=" . urlencode($verification_token);
    
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration (same as your forgot-password script)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'reportsjoborder@gmail.com';
        $mail->Password   = 'kjyj krfm rkbk qmst'; // App password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('reportsjoborder@gmail.com', 'Active Media');
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        $mail->Subject = "Email Verification - Active Media";
        
        if ($customer_type === 'personal') {
            $mail->Body = "
                <h2>Welcome to Active Media Designs and Printing!</h2>
                <p>Thank you for registering with us. Please verify your email address to activate your account.</p>
                <p>Click the link below to verify your email address:</p>
                <p><a href='$verify_link'>$verify_link</a></p>
                <p>This link will expire in 24 hours.</p>
                <br>
                <p>If you didn't create an account, please ignore this email.</p>
                <br>
                <p>Regards,<br>Active Media Designs and Printing</p>
            ";
        } else {
            $mail->Body = "
                <h2>Welcome to Active Media Designs and Printing!</h2>
                <p>Thank you for registering your company with us. Please verify your email address to activate your account.</p>
                <p>Click the link below to verify your email address:</p>
                <p><a href='$verify_link'>$verify_link</a></p>
                <p>This link will expire in 24 hours.</p>
                <br>
                <p>If you didn't create an account, please ignore this email.</p>
                <br>
                <p>Regards,<br>Active Media Designs and Printing</p>
            ";
        }
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Step 1
  $customer_type = $_POST['customer_type'] ?? '';

  // Step 2 (Personal)
  $first_name = trim($_POST['first_name'] ?? '');
  $middle_name = trim($_POST['middle_name'] ?? '');
  $last_name = trim($_POST['last_name'] ?? '');
  $age = $_POST['age'] !== '' ? (int)$_POST['age'] : null;
  $gender = $_POST['gender'] ?? '';
  $birthdate = $_POST['birthdate'] ?? null;
  $personal_contact = trim($_POST['personal_contact'] ?? '');

  // Step 2 (Company)
  $company_name = trim($_POST['company_name'] ?? '');
  $taxpayer_name = trim($_POST['taxpayer_name'] ?? '');
  $contact_person = trim($_POST['contact_person'] ?? '');
  $company_contact = trim($_POST['company_contact'] ?? '');

  // Step 3 (Personal address)
  $address_line1 = trim($_POST['address_line1'] ?? '');
  $p_city = trim($_POST['p_city'] ?? '');
  $p_province = trim($_POST['p_province'] ?? '');
  $p_zip = trim($_POST['p_zip'] ?? '');

  // Step 3 (Company address)
  $c_province = trim($_POST['c_province'] ?? '');
  $c_city = trim($_POST['c_city'] ?? '');
  $c_barangay = trim($_POST['c_barangay'] ?? '');
  $c_street = trim($_POST['c_street'] ?? '');
  $c_building = trim($_POST['c_building'] ?? '');
  $c_lotroom = trim($_POST['c_lotroom'] ?? '');
  $c_zip = trim($_POST['c_zip'] ?? '');

  // Step 4 (Account)
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  $role = 'customer'; // force role

  // Validation
  if (empty($username)) {
    $errors[] = "Email/Username is required";
  } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address";
  }

  if (empty($password)) {
    $errors[] = "Password is required";
  } elseif (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters";
  }

  if ($password !== $confirm_password) {
    $errors[] = "Passwords do not match";
  }

  if ($customer_type === 'personal') {
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($personal_contact)) $errors[] = "Contact number is required";
  } else {
    if (empty($company_name)) $errors[] = "Company name is required";
    if (empty($contact_person)) $errors[] = "Contact person is required";
    if (empty($company_contact)) $errors[] = "Contact number is required";
  }

  if (empty($errors)) {
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if username exists
    $check_stmt = $inventory->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
      $errors[] = "That email is already registered. Please choose another.";
    }
    $check_stmt->close();

    if (empty($errors)) {
      // Insert with hashed password
      $stmt = $inventory->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
      $stmt->bind_param("sss", $username, $hashed_password, $role);

      if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();

        if ($customer_type === 'personal') {
          $insert = $inventory->prepare("INSERT INTO personal_customers 
                    (user_id, first_name, middle_name, last_name, age, gender, birthdate, contact_number, address_line1, city, province, zip_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $bind_age = $age === null ? null : $age;
          $bind_birthdate = $birthdate === '' ? null : $birthdate;
          $insert->bind_param(
            "isssisssssss",
            $user_id,
            $first_name,
            $middle_name,
            $last_name,
            $bind_age,
            $gender,
            $bind_birthdate,
            $personal_contact,
            $address_line1,
            $p_city,
            $p_province,
            $p_zip
          );
          if ($insert->execute()) {
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $update_stmt = $inventory->prepare("UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $verification_token, $verification_expires, $user_id);
            
            if ($update_stmt->execute()) {
              // Send verification email
              if (sendVerificationEmail($username, $verification_token, 'personal')) {
                $success = true;
                $message = "Account created successfully! Please check your email to verify your account before logging in.";
                
                // Clear form data from session on success
                unset($_SESSION['form_data']);
                $_SESSION['display_message'] = $message;
                $_SESSION['is_success'] = true;
              } else {
                $errors[] = "Account created but verification email failed to send. Please <a href='email-verification.php'>request a new verification email</a>.";
                $message = implode("<br>", $errors);
              }
            } else {
              $errors[] = "Error generating verification token. Please contact support.";
              $message = implode("<br>", $errors);
            }
            $update_stmt->close();
          } else {
            $errors[] = "Error saving personal customer: " . $insert->error;
            $message = implode("<br>", $errors);
          }
          $insert->close();
        } else {
          $insert = $inventory->prepare("INSERT INTO company_customers
                    (user_id, company_name, taxpayer_name, contact_person, contact_number, province, city, barangay, subd_or_street, building_or_block, lot_or_room_no, zip_code)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
          $insert->bind_param(
            "isssssssssss",
            $user_id,
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
            $c_zip
          );
          if ($insert->execute()) {
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $update_stmt = $inventory->prepare("UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $verification_token, $verification_expires, $user_id);
            
            if ($update_stmt->execute()) {
              // Send verification email
              if (sendVerificationEmail($username, $verification_token, 'company')) {
                $success = true;
                $message = "Company account created successfully! Please check your email to verify your account before logging in.";
                
                // Clear form data from session on success
                unset($_SESSION['form_data']);
                $_SESSION['display_message'] = $message;
                $_SESSION['is_success'] = true;
              } else {
                $errors[] = "Account created but verification email failed to send. Please <a href='email-verification.php'>request a new verification email</a>.";
                $message = implode("<br>", $errors);
              }
            } else {
              $errors[] = "Error generating verification token. Please contact support.";
              $message = implode("<br>", $errors);
            }
            $update_stmt->close();
          } else {
            $errors[] = "Error saving company customer: " . $insert->error;
            $message = implode("<br>", $errors);
          }
          $insert->close();
        }
      } else {
        $errors[] = "Error creating user account: " . $stmt->error;
        $stmt->close();
      }
    }

    if (!empty($errors)) {
      $message = implode("<br>", $errors);
      $_SESSION['form_data'] = $_POST;
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no"/>
  <title>Register Customer</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    /* ... (keep all your existing CSS styles) ... */
    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgb(140, 140, 140);
      border-radius: 10px;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: rgb(245, 245, 245);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px;
    }

    .signup-container {
      display: flex;
      flex-direction: column;
      max-width: 900px;
      width: 100%;
      background: #fff;
      box-shadow: 0 2px 4px rgba(0, 0, 0, .1), 0 8px 16px rgba(0, 0, 0, .1);
      overflow: hidden;
    }

    .header {
      text-align: center;
      padding: 20px;
      background: linear-gradient(90deg, rgba(176, 0, 176, 1) 0%, rgba(0, 0, 0, 1) 30%, rgba(0, 0, 0, 1) 40%, rgba(0, 145, 255, 1) 70%, rgba(255, 255, 0, 1) 100%);
      color: white;
    }

    .header h1 {
      font-size: 24px;
      font-weight: 600;
    }

    .content {
      display: flex;
      padding: 20px;
    }

    .logo-container {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 50px 0;
    }

    .logo-container img {
      max-width: 300px;
      height: auto;
      transform: rotate(45deg);
    }

    .signup-box {
      flex: 1;
      padding: 20px;
    }

    .signup-form input,
    .signup-form select {
      width: 100%;
      padding: 14px 16px;
      border: 1px solid rgb(220, 220, 220);
      font-size: 17px;
      margin-bottom: 12px;
      transition: .3s;
    }

    .signup-form input:focus,
    .signup-form select:focus {
      outline: none;
      border-color: #1c1c1c;
      box-shadow: 0 0 5px 1px #1c1c1c;
    }

    .signup-btn {
      background-color: black;
      border: none;
      font-size: 20px;
      line-height: 48px;
      padding: 0 16px;
      width: 100%;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      margin-top: 5px;
      transition: .3s;
    }

    .signup-btn:hover {
      background-color: rgb(80, 80, 80);
    }

    .error-message {
      color: #ff4d4f;
      background: #fff2f0;
      border: 1px solid #ffccc7;
      padding: 10px;
      margin-bottom: 15px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .success-message {
      color: #52c41a;
      background: #f6ffed;
      border: 1px solid #b7eb8f;
      padding: 10px;
      margin-bottom: 15px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .footer-text {
      text-align: center;
      margin-top: 20px;
      color: #1c1c1c;
    }

    .footer-text a {
      text-decoration: none;
      font-weight: 800;
      color: #1c1c1c;
    }

    .footer-text a:hover {
      text-decoration: underline;
    }

    .step {
      display: none;
    }

    .step.active {
      display: block;
    }

    .step-title {
      font-weight: 600;
      margin-bottom: 12px;
    }

    .step-actions {
      display: flex;
      gap: 10px;
      margin-top: 10px;
    }

    .btn-secondary {
      background: #eaeaea;
      border: none;
      line-height: 44px;
      padding: 0 16px;
      cursor: pointer;
    }

    .btn-secondary:hover {
      background: #dadada;
    }

    .stepcon {
      display: flex;
      flex-direction: column;
    }

    .btn {
      border: 2px solid black;
      padding: 20px 50px;
      background-color: transparent;
      font-size: 20px;
      transition: 0.3s;
    }

    .btn:hover {
      background-color: #1c1c1c;
      color: white;
      cursor: pointer;
    }

    .error-field {
      border-color: #ff4d4f !important;
    }

    .error-text {
      color: #ff4d4f;
      font-size: 13px;
      margin-top: -10px;
      margin-bottom: 10px;
    }

    @media (max-width: 768px) {
      .content {
        flex-direction: column;
      }

      .signup-box {
        border-left: none;
        border-top: 1px solid #dddfe2;
      }

      .logo-container img {
        max-width: 200px;
      }

      .signup-container {
        scale: 0.8;
      }
    }
  </style>
</head>

<body>
  <div class="signup-container">
    <div class="header">
      <h1>CUSTOMER REGISTRATION</h1>
    </div>

    <div class="content">
      <div class="logo-container">
        <img src="../assets/images/plainlogo.png" alt="Active Media Designs Logo">
      </div>

      <div class="signup-box">
        <?php
        if (isset($_SESSION['display_message'])) {
          $message = $_SESSION['display_message'];
          $success = $_SESSION['is_success'];
          unset($_SESSION['display_message']);
          unset($_SESSION['is_success']);
        }
        ?>

        <?php if (!empty($message)): ?>
          <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
            <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
          </div>

          <?php if ($success): ?>
            <script>
              setTimeout(function() {
                window.location.href = "login.php";
              }, 5000);
            </script>
          <?php endif; ?>
        <?php endif; ?>

        <form method="post" class="signup-form" id="wizardForm">
          <!-- STEP 1: Account Type -->
          <div class="step active" id="step1">
            <div class="step-title">SELECT AN ACCOUNT TYPE</div>
            <input type="hidden" name="customer_type" id="customer_type" required value="<?php echo htmlspecialchars($customer_type); ?>">
            <div class="stepcon" style="display: flex; gap: 10px; margin-top: 15px;">
              <button type="button" class="btn <?php echo $customer_type === 'personal' ? 'active-btn' : ''; ?>" onclick="selectType('personal')">Personal</button>
              <button type="button" class="btn <?php echo $customer_type === 'company' ? 'active-btn' : ''; ?>" onclick="selectType('company')">Company</button>
            </div>
          </div>

          <!-- STEP 2: Customer Info -->
          <div class="step" id="step2">
            <div class="step-title">FILL UP YOUR INFORMATION</div>
            <div id="personal_fields" style="display:none;">
              <input type="text" name="first_name" placeholder="First Name *" value="<?php echo htmlspecialchars($first_name); ?>" class="<?php echo empty($first_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($first_name) && isset($_POST['customer_type'])): ?>
                <div class="error-text">First name is required</div>
              <?php endif; ?>

              <input type="text" name="middle_name" placeholder="Middle Initial (optional)" value="<?php echo htmlspecialchars($middle_name); ?>">

              <input type="text" name="last_name" placeholder="Last Name *" value="<?php echo htmlspecialchars($last_name); ?>" class="<?php echo empty($last_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($last_name) && isset($_POST['customer_type'])): ?>
                <div class="error-text">Last name is required</div>
              <?php endif; ?>

              <input type="number" name="age" placeholder="Age" value="<?php echo htmlspecialchars($age); ?>">

              <select name="gender">
                <option value="">Gender</option>
                <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
              </select>
              
              <label style="font-size: 12px; opacity: 0.5;">Enter your birth date:</label>
              <input type="date" name="birthdate" placeholder="Birthdate" value="<?php echo htmlspecialchars($birthdate); ?>">

              <input type="text" name="personal_contact" placeholder="Contact Number *" value="<?php echo htmlspecialchars($personal_contact); ?>" class="<?php echo empty($personal_contact) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($personal_contact) && isset($_POST['customer_type'])): ?>
                <div class="error-text">Contact number is required</div>
              <?php endif; ?>
            </div>

            <div id="company_fields" style="display:none;">
              <input type="text" name="company_name" placeholder="Company Name *" value="<?php echo htmlspecialchars($company_name); ?>" class="<?php echo empty($company_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($company_name) && isset($_POST['customer_type'])): ?>
                <div class="error-text">Company name is required</div>
              <?php endif; ?>

              <input type="text" name="taxpayer_name" placeholder="Taxpayer Name" value="<?php echo htmlspecialchars($taxpayer_name); ?>">

              <input type="text" name="contact_person" placeholder="Contact Person *" value="<?php echo htmlspecialchars($contact_person); ?>" class="<?php echo empty($contact_person) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($contact_person) && isset($_POST['customer_type'])): ?>
                <div class="error-text">Contact person is required</div>
              <?php endif; ?>

              <input type="text" name="company_contact" placeholder="Contact Number *" value="<?php echo htmlspecialchars($company_contact); ?>" class="<?php echo empty($company_contact) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($company_contact) && isset($_POST['customer_type'])): ?>
                <div class="error-text">Contact number is required</div>
              <?php endif; ?>
            </div>

            <div class="step-actions">
              <button type="button" class="btn-secondary" onclick="prevStep(2)">Back</button>
              <button type="button" class="btn-secondary" onclick="validateStep2()">Next</button>
            </div>
          </div>

          <!-- STEP 3: Address -->
          <div class="step" id="step3">
            <div class="step-title">FILL UP ADDRESS</div>
            <div id="personal_address" style="display:none;">
              <input type="text" name="address_line1" placeholder="Address Line 1 (optional)" value="<?php echo htmlspecialchars($address_line1); ?>">
              <input type="text" name="p_city" placeholder="City" value="<?php echo htmlspecialchars($p_city); ?>">
              <input type="text" name="p_province" placeholder="Province" value="<?php echo htmlspecialchars($p_province); ?>">
              <input type="text" name="p_zip" placeholder="ZIP Code" value="<?php echo htmlspecialchars($p_zip); ?>">
            </div>

            <div id="company_address" style="display:none;">
              <input type="text" name="c_province" placeholder="Province" value="<?php echo htmlspecialchars($c_province); ?>">
              <input type="text" name="c_city" placeholder="City" value="<?php echo htmlspecialchars($c_city); ?>">
              <input type="text" name="c_barangay" placeholder="Barangay" value="<?php echo htmlspecialchars($c_barangay); ?>">
              <input type="text" name="c_street" placeholder="Subd or Street" value="<?php echo htmlspecialchars($c_street); ?>">
              <input type="text" name="c_building" placeholder="Building or Block" value="<?php echo htmlspecialchars($c_building); ?>">
              <input type="text" name="c_lotroom" placeholder="Lot / Room No." value="<?php echo htmlspecialchars($c_lotroom); ?>">
              <input type="text" name="c_zip" placeholder="ZIP Code" value="<?php echo htmlspecialchars($c_zip); ?>">
            </div>

            <div class="step-actions">
              <button type="button" class="btn-secondary" onclick="prevStep(3)">Back</button>
              <button type="button" class="btn-secondary" onclick="nextStep(3)">Next</button>
            </div>
          </div>

          <!-- STEP 4: Account Login -->
          <div class="step" id="step4">
            <div class="step-title">LOGIN CREDENTIALS</div>
            <input type="email" name="username" placeholder="Email (will be your username for login) *" value="<?php echo htmlspecialchars($username); ?>" class="<?php echo empty($username) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>" required>
            <?php if (empty($username) && isset($_POST['customer_type'])): ?>
              <div class="error-text">Email is required</div>
            <?php endif; ?>

            <div class="password-container" style="position:relative;">
              <input type="password" name="password" placeholder="Password *" id="pwd1" class="<?php echo (empty($password) || (isset($_POST['password']) && strlen($password) < 8)) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>" required>
              <i class="fas fa-eye password-toggle" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;" onclick="togglePassword('pwd1', this)"></i>
            </div>
            <?php if (empty($password) && isset($_POST['customer_type'])): ?>
              <div class="error-text">Password is required</div>
            <?php elseif (isset($_POST['password']) && strlen($password) < 8): ?>
              <div class="error-text">Password must be at least 8 characters</div>
            <?php endif; ?>

            <div class="password-container" style="position:relative;">
              <input type="password" name="confirm_password" placeholder="Re-enter Password *" id="pwd2" class="<?php echo (empty($confirm_password) || (isset($_POST['confirm_password']) && $password !== $confirm_password)) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>" required>
              <i class="fas fa-eye password-toggle" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;" onclick="togglePassword('pwd2', this)"></i>
            </div>
            <?php if (empty($confirm_password) && isset($_POST['customer_type'])): ?>
              <div class="error-text">Please confirm your password</div>
            <?php elseif (isset($_POST['confirm_password']) && $password !== $confirm_password): ?>
              <div class="error-text">Passwords do not match</div>
            <?php endif; ?>

            <button type="submit" class="signup-btn">Create Account</button>
            <div class="step-actions">
              <button type="button" class="btn-secondary" onclick="prevStep(4)">Back</button>
            </div>
          </div>
        </form>

        <p class="footer-text">Already have an account? <a href="login.php">Log in</a></p>
      </div>
    </div>
  </div>
  <script>
    // ... (keep all your existing JavaScript) ...
    const steps = ["step1", "step2", "step3", "step4"];
    let currentStep = 0;

    // Initialize form with any saved data
    window.addEventListener('DOMContentLoaded', () => {
      const customerType = document.getElementById('customer_type').value;
      if (customerType) {
        toggleType(customerType);
        showStep(1); // Skip to step 2 if type already selected
      }
    });

    function showStep(idx) {
      currentStep = idx;
      steps.forEach((id, i) => {
        document.getElementById(id).classList.toggle('active', i === idx);
      });

      // Scroll to top of form for better UX
      document.querySelector('.signup-box').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }

    function selectType(type) {
      document.getElementById('customer_type').value = type;
      toggleType(type);
      showStep(1);

      // Update button styles to show selection
      document.querySelectorAll('.stepcon .btn').forEach(btn => {
        btn.classList.remove('active-btn');
      });
      event.target.classList.add('active-btn');
    }

    function validateStep2() {
      const type = document.getElementById('customer_type').value;
      let isValid = true;

      // Reset error states
      document.querySelectorAll('.error-field').forEach(el => {
        el.classList.remove('error-field');
      });
      document.querySelectorAll('.error-text').forEach(el => {
        el.style.display = 'none';
      });

      if (type === 'personal') {
        const fn = document.querySelector('[name="first_name"]').value.trim();
        const ln = document.querySelector('[name="last_name"]').value.trim();
        const contact = document.querySelector('[name="personal_contact"]').value.trim();

        if (!fn) {
          document.querySelector('[name="first_name"]').classList.add('error-field');
          isValid = false;
        }
        if (!ln) {
          document.querySelector('[name="last_name"]').classList.add('error-field');
          isValid = false;
        }
        if (!contact) {
          document.querySelector('[name="personal_contact"]').classList.add('error-field');
          isValid = false;
        }
      } else {
        const company = document.querySelector('[name="company_name"]').value.trim();
        const contactPerson = document.querySelector('[name="contact_person"]').value.trim();
        const contact = document.querySelector('[name="company_contact"]').value.trim();

        if (!company) {
          document.querySelector('[name="company_name"]').classList.add('error-field');
          isValid = false;
        }
        if (!contactPerson) {
          document.querySelector('[name="contact_person"]').classList.add('error-field');
          isValid = false;
        }
        if (!contact) {
          document.querySelector('[name="company_contact"]').classList.add('error-field');
          isValid = false;
        }
      }

      if (isValid) {
        showStep(2); // Move to step 3 (address)
      } else {
        // Show error messages
        document.querySelectorAll('.error-field').forEach(field => {
          const errorDiv = field.nextElementSibling;
          if (errorDiv && errorDiv.classList.contains('error-text')) {
            errorDiv.style.display = 'block';
          }
        });

        // Scroll to first error
        const firstError = document.querySelector('.error-field');
        if (firstError) {
          firstError.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
          firstError.focus();
        }
      }
    }

    function nextStep(current) {
      // This is for the address step (step 3) to move to account (step 4)
      showStep(3);
    }

    function prevStep(currentStepIndex) {
      // currentStepIndex is 2, 3, or 4 (step number)
      showStep(currentStepIndex - 2);
    }

    function toggleType(type) {
      const isPersonal = (type === 'personal');
      document.getElementById('personal_fields').style.display = isPersonal ? 'block' : 'none';
      document.getElementById('company_fields').style.display = isPersonal ? 'none' : 'block';
      document.getElementById('personal_address').style.display = isPersonal ? 'block' : 'none';
      document.getElementById('company_address').style.display = isPersonal ? 'none' : 'block';
    }

    function togglePassword(inputId, iconEl) {
      const input = document.getElementById(inputId);
      const isPw = input.type === 'password';
      input.type = isPw ? 'text' : 'password';
      if (iconEl) {
        iconEl.classList.toggle('fa-eye');
        iconEl.classList.toggle('fa-eye-slash');
      }
    }

    // Save form data to sessionStorage
    document.getElementById('wizardForm').addEventListener('input', (e) => {
      if (e.target.name) {
        const formData = new FormData(document.getElementById('wizardForm'));
        const obj = {};
        formData.forEach((value, key) => obj[key] = value);
        sessionStorage.setItem('signupForm', JSON.stringify(obj));
      }
    });

    // Clear sessionStorage on successful submission
    window.addEventListener('beforeunload', () => {
      if (<?php echo $success ? 'true' : 'false'; ?>) {
        sessionStorage.removeItem('signupForm');
      }
    });
  </script>
</body>

</html>