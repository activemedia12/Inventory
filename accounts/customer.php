<?php
require_once '../config/db.php';
require_once '../config/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit_$ip";
$current_time = time();

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => $current_time];
}

// Reset if more than 1 hour passed
if ($current_time - $_SESSION[$rate_limit_key]['time'] > 3600) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => $current_time];
}

// Check rate limit (max 10 registrations per hour)
if ($_SESSION[$rate_limit_key]['count'] >= 10) {
    die("Too many registration attempts. Please try again later.");
}

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
$agree_terms      = $form_data['agree_terms'] ?? '';

// List of disposable email domains
$disposable_domains = [
    'tempmail.com', 'guerrillamail.com', 'mailinator.com', 
    '10minutemail.com', 'yopmail.com', 'throwawaymail.com',
    'fakeinbox.com', 'trashmail.com', 'getairmail.com'
];

// Function to calculate age from birthdate
function calculateAge($birthdate) {
    if (empty($birthdate)) {
        return null;
    }
    
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    
    return $age->y; // Return years only
}

// Function to check if email is disposable
function isDisposableEmail($email) {
    global $disposable_domains;
    
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    return in_array($domain, $disposable_domains);
}

// Function to validate Philippine phone number
function validatePHPhoneNumber($number) {
    // Remove all non-digit characters
    $cleaned = preg_replace('/\D/', '', $number);
    
    // Check if it's a valid Philippine mobile number (09XXXXXXXXX) or landline
    if (preg_match('/^09[0-9]{9}$/', $cleaned)) {
        return $cleaned;
    }
    
    // Check for landline format (02XXXXXXXX or area code + number)
    if (preg_match('/^0[2-9][0-9]{7,9}$/', $cleaned)) {
        return $cleaned;
    }
    
    return false;
}

// Function to validate TIN (Tax Identification Number) - Philippine format
function validateTIN($tin) {
    if (empty($tin)) return true; // Optional field
    
    // Remove all non-digit characters
    $cleaned = preg_replace('/\D/', '', $tin);
    
    // TIN should be 9-12 digits
    if (strlen($cleaned) >= 9 && strlen($cleaned) <= 12) {
        return $cleaned;
    }
    
    return false;
}

// Function to validate ZIP code (Philippine format)
function validatePHZipCode($zip) {
    if (empty($zip)) return true; // Optional field
    
    // Philippine ZIP codes are 4 digits
    if (preg_match('/^\d{4}$/', $zip)) {
        return $zip;
    }
    
    return false;
}

// Function to send verification email
function sendVerificationEmail($email, $verification_token, $customer_type = 'personal') {
    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $verify_link = $base_url . "/inventory/accounts/email-verification.php?token=" . urlencode($verification_token);
    
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
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
        error_log("Email verification error for $email: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission. Please refresh the page and try again.";
    }
    
    // Rate limiting check
    $_SESSION[$rate_limit_key]['count']++;
    
    // Step 1
    $customer_type = $_POST['customer_type'] ?? '';

    // Step 2 (Personal)
    $first_name = filter_var(trim($_POST['first_name'] ?? ''), FILTER_SANITIZE_STRING);
    $middle_name = filter_var(trim($_POST['middle_name'] ?? ''), FILTER_SANITIZE_STRING);
    $last_name = filter_var(trim($_POST['last_name'] ?? ''), FILTER_SANITIZE_STRING);
    $gender = filter_var($_POST['gender'] ?? '', FILTER_SANITIZE_STRING);
    $birthdate = $_POST['birthdate'] ?? null;
    $personal_contact = trim($_POST['personal_contact'] ?? '');
    
    // Calculate age from birthdate
    $age = null;
    if (!empty($birthdate)) {
        $age = calculateAge($birthdate);
    }

    // Step 2 (Company)
    $company_name = filter_var(trim($_POST['company_name'] ?? ''), FILTER_SANITIZE_STRING);
    $taxpayer_name = filter_var(trim($_POST['taxpayer_name'] ?? ''), FILTER_SANITIZE_STRING);
    $contact_person = filter_var(trim($_POST['contact_person'] ?? ''), FILTER_SANITIZE_STRING);
    $company_contact = trim($_POST['company_contact'] ?? '');

    // Step 3 (Personal address)
    $address_line1 = filter_var(trim($_POST['address_line1'] ?? ''), FILTER_SANITIZE_STRING);
    $p_city = filter_var(trim($_POST['p_city'] ?? ''), FILTER_SANITIZE_STRING);
    $p_province = filter_var(trim($_POST['p_province'] ?? ''), FILTER_SANITIZE_STRING);
    $p_zip = trim($_POST['p_zip'] ?? '');

    // Step 3 (Company address)
    $c_province = filter_var(trim($_POST['c_province'] ?? ''), FILTER_SANITIZE_STRING);
    $c_city = filter_var(trim($_POST['c_city'] ?? ''), FILTER_SANITIZE_STRING);
    $c_barangay = filter_var(trim($_POST['c_barangay'] ?? ''), FILTER_SANITIZE_STRING);
    $c_street = filter_var(trim($_POST['c_street'] ?? ''), FILTER_SANITIZE_STRING);
    $c_building = filter_var(trim($_POST['c_building'] ?? ''), FILTER_SANITIZE_STRING);
    $c_lotroom = filter_var(trim($_POST['c_lotroom'] ?? ''), FILTER_SANITIZE_STRING);
    $c_zip = trim($_POST['c_zip'] ?? '');

    // Step 4 (Account)
    $username = filter_var(trim($_POST['username'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;
    $role = 'customer';

    // Validation
    if (empty($username)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    } elseif (isDisposableEmail($username)) {
        $errors[] = "Temporary/disposable email addresses are not allowed";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Terms agreement check
    if (!$agree_terms) {
        $errors[] = "You must agree to the Terms & Conditions and Privacy Policy";
    }

    if ($customer_type === 'personal') {
        if (empty($first_name)) $errors[] = "First name is required";
        if (empty($last_name)) $errors[] = "Last name is required";
        if (empty($personal_contact)) {
            $errors[] = "Contact number is required";
        } else {
            $valid_contact = validatePHPhoneNumber($personal_contact);
            if (!$valid_contact) {
                $errors[] = "Please enter a valid phone number (09XXXXXXXXX or landline format)";
            } else {
                $personal_contact = $valid_contact;
            }
        }
        
        // Age validation
        if (!empty($birthdate) && $age !== null && $age < 13) {
            $errors[] = "You must be at least 13 years old to register";
        }
        
        // ZIP code validation
        if (!empty($p_zip) && !validatePHZipCode($p_zip)) {
            $errors[] = "Please enter a valid 4-digit ZIP code";
        }
    } else {
        if (empty($company_name)) $errors[] = "Company name is required";
        if (empty($contact_person)) $errors[] = "Contact person is required";
        if (empty($company_contact)) {
            $errors[] = "Contact number is required";
        } else {
            $valid_contact = validatePHPhoneNumber($company_contact);
            if (!$valid_contact) {
                $errors[] = "Please enter a valid phone number (09XXXXXXXXX or landline format)";
            } else {
                $company_contact = $valid_contact;
            }
        }
        
        // TIN validation
        if (!empty($taxpayer_name)) {
            $valid_tin = validateTIN($taxpayer_name);
            if (!$valid_tin) {
                $errors[] = "Please enter a valid Tax Identification Number (9-12 digits)";
            } else {
                $taxpayer_name = $valid_tin;
            }
        }
        
        // ZIP code validation
        if (!empty($c_zip) && !validatePHZipCode($c_zip)) {
            $errors[] = "Please enter a valid 4-digit ZIP code";
        }
    }

    if (empty($errors)) {
        try {
            // Start database transaction
            $inventory->begin_transaction();
            
            // Check if username exists and is verified
            $check_stmt = $inventory->prepare("SELECT id, verification_token FROM users WHERE username = ?");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                // Check if the account is verified
                $check_stmt->bind_result($existing_id, $existing_token);
                $check_stmt->fetch();
                
                if ($existing_token === null) {
                    $errors[] = "An account with this email already exists and is already verified.";
                } else {
                    $errors[] = "An account with this email already exists but is not verified. Please check your email for verification link.";
                }
            }
            $check_stmt->close();
            
            if (empty($errors)) {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user with transaction
                $stmt = $inventory->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $hashed_password, $role);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error creating user account: " . $stmt->error);
                }
                
                $user_id = $stmt->insert_id;
                $stmt->close();
                
                // Generate verification token
                $verification_token = bin2hex(random_bytes(32));
                $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Update user with verification token
                $update_stmt = $inventory->prepare("UPDATE users SET verification_token = ?, verification_expires = ? WHERE id = ?");
                $update_stmt->bind_param("ssi", $verification_token, $verification_expires, $user_id);
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Error generating verification token: " . $update_stmt->error);
                }
                $update_stmt->close();
                
                // Insert customer data based on type
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
                    
                    if (!$insert->execute()) {
                        throw new Exception("Error saving personal customer: " . $insert->error);
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
                    
                    if (!$insert->execute()) {
                        throw new Exception("Error saving company customer: " . $insert->error);
                    }
                    $insert->close();
                }
                
                // Send verification email
                if (!sendVerificationEmail($username, $verification_token, $customer_type)) {
                    // Log email failure but don't fail the transaction
                    error_log("Failed to send verification email to: $username");
                    // Still consider it a success since account was created
                }
                
                // Commit transaction
                $inventory->commit();
                
                $success = true;
                $message = "Account created successfully! Please check your <strong>profile page<strong> to verify your account.";
                
                // Clear form data from session on success
                unset($_SESSION['form_data']);
                $_SESSION['display_message'] = $message;
                $_SESSION['is_success'] = true;
                
                // Generate new CSRF token after successful submission
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $inventory->rollback();
            $errors[] = "Registration failed. Please try again. If the problem persists, contact support.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $_SESSION['form_data'] = $_POST;
    }
}

// Regenerate CSRF token for new form
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

    .signup-btn:disabled {
      background-color: #666;
      cursor: not-allowed;
    }

    .signup-btn:hover:not(:disabled) {
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

    .success-text {
      color: #52c41a;
      font-size: 13px;
      margin-top: -10px;
      margin-bottom: 10px;
    }

    .required::after {
      content: " *";
      color: #ff4d4f;
    }

    .optional {
      color: #666;
      font-size: 12px;
    }

    .optional::after {
      content: " (optional)";
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

    .account-type-badge {
      display: inline-block;
      background: #1c1c1c;
      color: white;
      padding: 8px 16px;
      margin-bottom: 15px;
      font-weight: 600;
      font-size: 14px;
    }
    
    .account-type-badge.company {
      background: black;
    }
    
    .switch-account-type {
      display: block;
      margin-top: 15px;
      text-align: center;
      color: #1890ff;
      cursor: pointer;
      font-weight: 500;
      font-size: 14px;
      text-decoration: underline;
    }
    
    .switch-account-type:hover {
      color: #096dd9;
    }
    
    .welcome-message {
      text-align: center;
      margin-bottom: 20px;
    }
    
    .welcome-message h2 {
      margin-bottom: 10px;
      color: #1c1c1c;
    }
    
    .welcome-message p {
      color: #666;
      line-height: 1.6;
    }
    
    .start-button {
      border: 2px solid black;
      background-color: black;
      font-size: 18px;
      line-height: 48px;
      padding: 0 30px;
      color: #fff;
      cursor: pointer;
      margin: auto;
      display: block;
      transition: 0.3s;
    }
    
    .start-button:hover {
      background-color: transparent;
      color: black;
    }
    
    .alternative-option {
      text-align: center;
      margin-top: 20px;
      color: #666;
      font-size: 14px;
    }
    
    .alternative-option a {
      color: #1890ff;
      text-decoration: none;
      font-weight: 500;
    }
    
    .alternative-option a:hover {
      text-decoration: underline;
    }
    
    .terms-checkbox {
      margin: 15px 0;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    
    .terms-checkbox input[type="checkbox"] {
      width: auto;
      margin-top: 5px;
    }
    
    .terms-checkbox label {
      font-size: 14px;
      line-height: 1.4;
    }
    
    .terms-checkbox a {
      color: #1890ff;
      text-decoration: none;
    }
    
    .terms-checkbox a:hover {
      text-decoration: underline;
    }
    
    .loading-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    
    .spinner {
      border: 5px solid #f3f3f3;
      border-top: 5px solid #3498db;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    .password-strength {
      height: 5px;
      margin-top: -10px;
      margin-bottom: 15px;
      border-radius: 2px;
      transition: all 0.3s;
    }
    
    .strength-0 { width: 0%; background: #ff4d4f; }
    .strength-1 { width: 25%; background: #ff4d4f; }
    .strength-2 { width: 50%; background: #faad14; }
    .strength-3 { width: 75%; background: #52c41a; }
    .strength-4 { width: 100%; background: #52c41a; }
    
    .password-requirements {
      font-size: 12px;
      color: #666;
      margin-top: -10px;
      margin-bottom: 10px;
    }
    
    .requirement {
      display: flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 3px;
    }
    
    .requirement.valid {
      color: #52c41a;
    }
    
    .requirement.invalid {
      color: #ff4d4f;
    }
    
    .field-hint {
      font-size: 12px;
      color: #666;
      margin-top: -10px;
      margin-bottom: 10px;
      font-style: italic;
    }
  </style>
</head>

<body>
  <div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
  </div>
  
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
              }, 7000);
            </script>
          <?php endif; ?>
        <?php endif; ?>

        <form method="post" class="signup-form" id="wizardForm" onsubmit="return validateFinalForm()">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          
          <!-- STEP 1: Welcome Screen -->
          <div class="step active" id="step1">
            <div class="welcome-message">
              <h2>Create Your Account</h2>
              <p>Welcome to Active Media Designs and Printing! Let's get you started with your customer account.</p>
            </div>
            
            <button type="button" class="start-button" onclick="startRegistration()">
              Get Started
            </button>
            
            <div class="alternative-option">
              <p>Registering for a business? <a href="javascript:void(0)" onclick="startAsCompany()">Click here to register as a company</a></p>
            </div>
            
            <input type="hidden" name="customer_type" id="customer_type" required value="personal">
          </div>

          <!-- STEP 2: Customer Info -->
          <div class="step" id="step2">
            <div class="step-title">FILL UP YOUR INFORMATION</div>
            
            <!-- Account Type Badge and Switcher -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
              <div>
                <span class="account-type-badge <?php echo ($customer_type ?: 'personal') === 'personal' ? '' : 'company'; ?>" id="accountTypeBadge">
                  <?php echo ($customer_type ?: 'personal') === 'personal' ? 'Personal Account' : 'Company Account'; ?>
                </span>
              </div>
              <a href="javascript:void(0)" class="switch-account-type" onclick="switchAccountType()" id="switchAccountLink">
                <?php echo ($customer_type ?: 'personal') === 'personal' ? 'Signing up as a company?' : 'Switch to Personal Account'; ?>
              </a>
            </div>
            
            <div id="personal_fields" style="<?php echo ($customer_type ?: 'personal') === 'personal' ? 'display:block;' : 'display:none;'; ?>">
              <label class="required">First Name</label>
              <input type="text" name="first_name" placeholder="Enter your first name" 
                     value="<?php echo htmlspecialchars($first_name); ?>" 
                     class="<?php echo empty($first_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>"
                     autocomplete="given-name"
                     oninput="validateField(this, 'first_name')">
              <div class="error-text" id="error_first_name" style="display:none;"></div>

              <label class="optional">Middle Name</label>
              <input type="text" name="middle_name" placeholder="Enter your middle name (optional)" 
                     value="<?php echo htmlspecialchars($middle_name); ?>"
                     autocomplete="additional-name"
                     oninput="validateField(this, 'middle_name')">

              <label class="required">Last Name</label>
              <input type="text" name="last_name" placeholder="Enter your last name" 
                     value="<?php echo htmlspecialchars($last_name); ?>" 
                     class="<?php echo empty($last_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>"
                     autocomplete="family-name"
                     oninput="validateField(this, 'last_name')">
              <div class="error-text" id="error_last_name" style="display:none;"></div>

              <label>Gender (Optional)</label>
              <select name="gender" autocomplete="sex">
                <option value="">Select Gender</option>
                <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
                <option value="Prefer not to say" <?php echo $gender === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
              </select>
              
              <label>Birth Date (Optional)</label>
              <input type="date" name="birthdate" id="birthdate" 
                     value="<?php echo htmlspecialchars($birthdate); ?>" 
                     onchange="calculateAgeFromDate(); validateField(this, 'birthdate')"
                     max="<?php echo date('Y-m-d'); ?>">
              <div id="ageDisplay" class="age-display"></div>

              <label class="required">Contact Number</label>
              <input type="tel" name="personal_contact" placeholder="09XXXXXXXXX or landline" 
                     value="<?php echo htmlspecialchars($personal_contact); ?>" 
                     class="<?php echo empty($personal_contact) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>"
                     autocomplete="tel"
                     oninput="formatPhoneNumber(this); validateField(this, 'personal_contact')">
              <div class="field-hint">Format: 09XXXXXXXXX for mobile or 02XXXXXXXX for landline</div>
              <div class="error-text" id="error_personal_contact" style="display:none;"></div>
            </div>

            <div id="company_fields" style="<?php echo ($customer_type ?: 'personal') === 'company' ? 'display:block;' : 'display:none;'; ?>">
              <label class="required">Company Name</label>
              <input type="text" name="company_name" placeholder="Enter company name" 
                     value="<?php echo htmlspecialchars($company_name); ?>" 
                     class="<?php echo empty($company_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>"
                     autocomplete="organization"
                     oninput="validateField(this, 'company_name')">
              <div class="error-text" id="error_company_name" style="display:none;"></div>

              <label class="optional">Taxpayer Name / TIN</label>
              <input type="text" name="taxpayer_name" placeholder="Tax Identification Number (optional)" 
                     value="<?php echo htmlspecialchars($taxpayer_name); ?>"
                     oninput="validateField(this, 'taxpayer_name')">
              <div class="field-hint">9-12 digits. Leave blank if not available.</div>
              <div class="error-text" id="error_taxpayer_name" style="display:none;"></div>

              <label class="required">Contact Person</label>
              <input type="text" name="contact_person" placeholder="Full name of contact person" 
                     value="<?php echo htmlspecialchars($contact_person); ?>" 
                     class="<?php echo empty($contact_person) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>"
                     autocomplete="name"
                     oninput="validateField(this, 'contact_person')">
              <div class="error-text" id="error_contact_person" style="display:none;"></div>

              <label class="required">Contact Number</label>
              <input type="tel" name="company_contact" placeholder="09XXXXXXXXX or landline" 
                     value="<?php echo htmlspecialchars($company_contact); ?>" 
                     class="<?php echo empty($company_contact) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>"
                     autocomplete="tel"
                     oninput="formatPhoneNumber(this); validateField(this, 'company_contact')">
              <div class="field-hint">Format: 09XXXXXXXXX for mobile or 02XXXXXXXX for landline</div>
              <div class="error-text" id="error_company_contact" style="display:none;"></div>
            </div>

            <div class="step-actions">
              <button type="button" class="btn-secondary" onclick="goBackToWelcome()">Back</button>
              <button type="button" class="btn-secondary" onclick="validateStep2()">Next</button>
            </div>
          </div>

          <!-- STEP 3: Address -->
          <div class="step" id="step3">
            <div class="step-title">FILL UP ADDRESS</div>
            
            <!-- Account Type Badge -->
            <div style="margin-bottom: 15px;">
              <span class="account-type-badge <?php echo ($customer_type ?: 'personal') === 'personal' ? '' : 'company'; ?>" id="accountTypeBadgeStep3">
                <?php echo ($customer_type ?: 'personal') === 'personal' ? 'Personal Account' : 'Company Account'; ?>
              </span>
            </div>
            
            <div id="personal_address" style="<?php echo ($customer_type ?: 'personal') === 'personal' ? 'display:block;' : 'display:none;'; ?>">
              <label class="optional">Address Line 1</label>
              <input type="text" name="address_line1" placeholder="House no., Street, Subdivision" 
                     value="<?php echo htmlspecialchars($address_line1); ?>"
                     autocomplete="address-line1"
                     oninput="validateField(this, 'address_line1')">

              <label class="optional">City</label>
              <input type="text" name="p_city" placeholder="City" 
                     value="<?php echo htmlspecialchars($p_city); ?>"
                     autocomplete="address-level2"
                     oninput="validateField(this, 'p_city')">

              <label class="optional">Province</label>
              <input type="text" name="p_province" placeholder="Province" 
                     value="<?php echo htmlspecialchars($p_province); ?>"
                     autocomplete="address-level1"
                     oninput="validateField(this, 'p_province')">

              <label class="optional">ZIP Code</label>
              <input type="text" name="p_zip" placeholder="0000" 
                     value="<?php echo htmlspecialchars($p_zip); ?>"
                     autocomplete="postal-code"
                     oninput="formatZipCode(this); validateField(this, 'p_zip')">
              <div class="field-hint">4-digit Philippine ZIP code</div>
              <div class="error-text" id="error_p_zip" style="display:none;"></div>
            </div>

            <div id="company_address" style="<?php echo ($customer_type ?: 'personal') === 'company' ? 'display:block;' : 'display:none;'; ?>">
              <label class="optional">Province</label>
              <input type="text" name="c_province" placeholder="Province" 
                     value="<?php echo htmlspecialchars($c_province); ?>"
                     autocomplete="address-level1"
                     oninput="validateField(this, 'c_province')">

              <label class="optional">City</label>
              <input type="text" name="c_city" placeholder="City" 
                     value="<?php echo htmlspecialchars($c_city); ?>"
                     autocomplete="address-level2"
                     oninput="validateField(this, 'c_city')">

              <label class="optional">Barangay</label>
              <input type="text" name="c_barangay" placeholder="Barangay" 
                     value="<?php echo htmlspecialchars($c_barangay); ?>"
                     oninput="validateField(this, 'c_barangay')">

              <label class="optional">Subdivision or Street</label>
              <input type="text" name="c_street" placeholder="Subdivision or Street name" 
                     value="<?php echo htmlspecialchars($c_street); ?>"
                     autocomplete="address-line1"
                     oninput="validateField(this, 'c_street')">

              <label class="optional">Building or Block</label>
              <input type="text" name="c_building" placeholder="Building name or Block no." 
                     value="<?php echo htmlspecialchars($c_building); ?>"
                     oninput="validateField(this, 'c_building')">

              <label class="optional">Lot / Room No.</label>
              <input type="text" name="c_lotroom" placeholder="Lot number or Room no." 
                     value="<?php echo htmlspecialchars($c_lotroom); ?>"
                     oninput="validateField(this, 'c_lotroom')">

              <label class="optional">ZIP Code</label>
              <input type="text" name="c_zip" placeholder="0000" 
                     value="<?php echo htmlspecialchars($c_zip); ?>"
                     autocomplete="postal-code"
                     oninput="formatZipCode(this); validateField(this, 'c_zip')">
              <div class="field-hint">4-digit Philippine ZIP code</div>
              <div class="error-text" id="error_c_zip" style="display:none;"></div>
            </div>

            <div class="step-actions">
              <button type="button" class="btn-secondary" onclick="prevStep(3)">Back</button>
              <button type="button" class="btn-secondary" onclick="validateStep3()">Next</button>
            </div>
          </div>

          <!-- STEP 4: Account Login -->
          <div class="step" id="step4">
            <div class="step-title">LOGIN CREDENTIALS</div>
            
            <!-- Account Type Badge -->
            <div style="margin-bottom: 15px;">
              <span class="account-type-badge <?php echo ($customer_type ?: 'personal') === 'personal' ? '' : 'company'; ?>">
                <?php echo ($customer_type ?: 'personal') === 'personal' ? 'Personal Account' : 'Company Account'; ?>
              </span>
            </div>
            
            <label class="required">Email Address</label>
            <input type="email" name="username" placeholder="your.email@example.com" 
                   value="<?php echo htmlspecialchars($username); ?>" 
                   class="<?php echo empty($username) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>" 
                   required
                   autocomplete="email"
                   oninput="validateEmail(this)">
            <div class="error-text" id="error_username" style="display:none;"></div>

            <label class="required">Password</label>
            <div class="password-container" style="position:relative;">
              <input type="password" name="password" placeholder="Create a strong password" 
                     id="pwd1" 
                     class="<?php echo (empty($password) || (isset($_POST['password']) && strlen($password) < 8)) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>" 
                     required
                     autocomplete="new-password"
                     oninput="checkPasswordStrength(this.value); validatePassword(this)">
              <i class="fas fa-eye password-toggle" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;" onclick="togglePassword('pwd1', this)"></i>
            </div>
            <div class="password-strength" id="passwordStrength"></div>
            <div class="password-requirements" id="passwordRequirements">
              <div class="requirement invalid" id="req_length"><i class="fas fa-times"></i> At least 8 characters</div>
              <div class="requirement invalid" id="req_upper"><i class="fas fa-times"></i> At least one uppercase letter</div>
              <div class="requirement invalid" id="req_lower"><i class="fas fa-times"></i> At least one lowercase letter</div>
              <div class="requirement invalid" id="req_number"><i class="fas fa-times"></i> At least one number</div>
            </div>
            <div class="error-text" id="error_password" style="display:none;"></div>

            <label class="required">Confirm Password</label>
            <div class="password-container" style="position:relative;">
              <input type="password" name="confirm_password" placeholder="Re-enter your password" 
                     id="pwd2" 
                     class="<?php echo (empty($confirm_password) || (isset($_POST['confirm_password']) && $password !== $confirm_password)) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>" 
                     required
                     autocomplete="new-password"
                     oninput="checkPasswordMatch()">
              <i class="fas fa-eye password-toggle" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);cursor:pointer;" onclick="togglePassword('pwd2', this)"></i>
            </div>
            <div class="error-text" id="error_confirm_password" style="display:none;"></div>
            <div class="success-text" id="success_password_match" style="display:none;"><i class="fas fa-check"></i> Passwords match</div>

            <div class="terms-checkbox">
              <input type="checkbox" name="agree_terms" id="agree_terms" value="1" 
                     <?php echo ($agree_terms == 1) ? 'checked' : ''; ?>
                     onchange="validateTerms()">
              <label for="agree_terms">
                I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a> and 
                <a href="privacy.php" target="_blank">Privacy Policy</a>
              </label>
            </div>
            <div class="error-text" id="error_agree_terms" style="display:none;"></div>

            <button type="submit" class="signup-btn" id="submitBtn">Create Account</button>
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
    const steps = ["step1", "step2", "step3", "step4"];
    let currentStep = 0;
    let currentAccountType = 'personal';
    let formData = {};

    // Initialize form
    window.addEventListener('DOMContentLoaded', () => {
      // Load saved data from sessionStorage
      const savedData = sessionStorage.getItem('signupForm');
      if (savedData) {
        formData = JSON.parse(savedData);
        Object.keys(formData).forEach(key => {
          const input = document.querySelector(`[name="${key}"]`);
          if (input && input.type !== 'hidden') {
            input.value = formData[key];
          }
        });
        
        // Restore customer type
        if (formData.customer_type) {
          currentAccountType = formData.customer_type;
          document.getElementById('customer_type').value = currentAccountType;
          toggleType(currentAccountType);
          updateAccountTypeBadge();
        }
      }
      
      // Set default to personal if not already set
      const customerTypeInput = document.getElementById('customer_type');
      if (!customerTypeInput.value) {
        customerTypeInput.value = 'personal';
      }
      currentAccountType = customerTypeInput.value;
      
      // Calculate age from any existing birthdate
      const birthdateInput = document.getElementById('birthdate');
      if (birthdateInput && birthdateInput.value) {
        calculateAgeFromDate();
      }
      
      // Initialize password strength
      checkPasswordStrength(document.getElementById('pwd1')?.value || '');
      checkPasswordMatch();
      
      // Add input listeners to save data
      document.querySelectorAll('input, select').forEach(input => {
        if (input.name && input.type !== 'submit' && input.type !== 'button') {
          input.addEventListener('input', saveFormData);
          input.addEventListener('change', saveFormData);
        }
      });
    });

    function showLoading(show) {
      document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
    }

    function startRegistration() {
      document.getElementById('customer_type').value = 'personal';
      currentAccountType = 'personal';
      toggleType('personal');
      showStep(1);
    }

    function startAsCompany() {
      document.getElementById('customer_type').value = 'company';
      currentAccountType = 'company';
      toggleType('company');
      showStep(1);
    }

    function goBackToWelcome() {
      showStep(0);
    }

    function showStep(idx) {
      currentStep = idx;
      steps.forEach((id, i) => {
        document.getElementById(id).classList.toggle('active', i === idx);
      });

      updateAccountTypeBadge();
      
      document.querySelector('.signup-box').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }

    function switchAccountType() {
      const newType = currentAccountType === 'personal' ? 'company' : 'personal';
      document.getElementById('customer_type').value = newType;
      currentAccountType = newType;
      
      toggleType(newType);
      updateAccountTypeBadge();
      
      clearValidationErrors();
    }

    function updateAccountTypeBadge() {
      const badgeText = currentAccountType === 'personal' ? 'Personal Account' : 'Company Account';
      const badgeClass = currentAccountType === 'personal' ? '' : 'company';
      
      document.querySelectorAll('.account-type-badge').forEach(badge => {
        badge.textContent = badgeText;
        badge.className = 'account-type-badge ' + badgeClass;
      });
      
      const switchLink = document.getElementById('switchAccountLink');
      if (switchLink) {
        switchLink.textContent = currentAccountType === 'personal' 
          ? 'Signing up as a company?' 
          : 'Switch to Personal Account';
      }
    }

    function calculateAgeFromDate() {
      const birthdateInput = document.getElementById('birthdate');
      const ageDisplay = document.getElementById('ageDisplay');
      
      if (!birthdateInput || !birthdateInput.value) {
        ageDisplay.textContent = '';
        return;
      }
      
      const birthdate = new Date(birthdateInput.value);
      const today = new Date();
      
      let age = today.getFullYear() - birthdate.getFullYear();
      const monthDiff = today.getMonth() - birthdate.getMonth();
      
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
        age--;
      }
      
      if (age < 0) {
        ageDisplay.textContent = 'Invalid birth date (future date)';
        ageDisplay.style.color = '#ff4d4f';
      } else if (age > 120) {
        ageDisplay.textContent = 'Age: ' + age + ' (please verify birth date)';
        ageDisplay.style.color = '#faad14';
      } else if (age < 13) {
        ageDisplay.textContent = 'Age: ' + age + ' (must be 13 or older)';
        ageDisplay.style.color = '#ff4d4f';
      } else {
        ageDisplay.textContent = 'Age: ' + age + ' years old';
        ageDisplay.style.color = '#666';
      }
    }

    function validateStep2() {
      const type = currentAccountType;
      let isValid = true;
      clearValidationErrors();

      if (type === 'personal') {
        const fn = document.querySelector('[name="first_name"]').value.trim();
        const ln = document.querySelector('[name="last_name"]').value.trim();
        const contact = document.querySelector('[name="personal_contact"]').value.trim();

        if (!fn) {
          showError('first_name', 'First name is required');
          document.querySelector('[name="first_name"]').classList.add('error-field');
          isValid = false;
        }
        
        if (!ln) {
          showError('last_name', 'Last name is required');
          document.querySelector('[name="last_name"]').classList.add('error-field');
          isValid = false;
        }
        
        if (!contact) {
          showError('personal_contact', 'Contact number is required');
          document.querySelector('[name="personal_contact"]').classList.add('error-field');
          isValid = false;
        } else if (!validatePhoneNumber(contact)) {
          showError('personal_contact', 'Please enter a valid phone number');
          document.querySelector('[name="personal_contact"]').classList.add('error-field');
          isValid = false;
        }
      } else {
        const company = document.querySelector('[name="company_name"]').value.trim();
        const contactPerson = document.querySelector('[name="contact_person"]').value.trim();
        const contact = document.querySelector('[name="company_contact"]').value.trim();

        if (!company) {
          showError('company_name', 'Company name is required');
          document.querySelector('[name="company_name"]').classList.add('error-field');
          isValid = false;
        }
        
        if (!contactPerson) {
          showError('contact_person', 'Contact person is required');
          document.querySelector('[name="contact_person"]').classList.add('error-field');
          isValid = false;
        }
        
        if (!contact) {
          showError('company_contact', 'Contact number is required');
          document.querySelector('[name="company_contact"]').classList.add('error-field');
          isValid = false;
        } else if (!validatePhoneNumber(contact)) {
          showError('company_contact', 'Please enter a valid phone number');
          document.querySelector('[name="company_contact"]').classList.add('error-field');
          isValid = false;
        }
      }

      if (isValid) {
        showStep(2);
      } else {
        scrollToFirstError();
      }
    }

    function validateStep3() {
      clearValidationErrors();
      let isValid = true;
      
      // Validate ZIP codes if provided
      if (currentAccountType === 'personal') {
        const zip = document.querySelector('[name="p_zip"]').value.trim();
        if (zip && !validateZipCode(zip)) {
          showError('p_zip', 'Please enter a valid 4-digit ZIP code');
          document.querySelector('[name="p_zip"]').classList.add('error-field');
          isValid = false;
        }
      } else {
        const zip = document.querySelector('[name="c_zip"]').value.trim();
        if (zip && !validateZipCode(zip)) {
          showError('c_zip', 'Please enter a valid 4-digit ZIP code');
          document.querySelector('[name="c_zip"]').classList.add('error-field');
          isValid = false;
        }
      }
      
      if (isValid) {
        showStep(3);
      } else {
        scrollToFirstError();
      }
    }

    function nextStep(current) {
      showStep(3);
    }

    function prevStep(currentStepIndex) {
      showStep(currentStepIndex - 2);
    }

    function toggleType(type) {
      const isPersonal = (type === 'personal');
      
      document.getElementById('personal_fields').style.display = isPersonal ? 'block' : 'none';
      document.getElementById('company_fields').style.display = isPersonal ? 'none' : 'block';
      document.getElementById('personal_address').style.display = isPersonal ? 'block' : 'none';
      document.getElementById('company_address').style.display = isPersonal ? 'none' : 'block';
      
      currentAccountType = type;
      updateAccountTypeBadge();
      saveFormData();
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

    // Validation functions
    function validateEmail(input) {
      const email = input.value.trim();
      const errorElement = document.getElementById('error_username');
      
      if (!email) {
        showError('username', 'Email is required');
        input.classList.add('error-field');
        return false;
      }
      
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        showError('username', 'Please enter a valid email address');
        input.classList.add('error-field');
        return false;
      }
      
      // Check for disposable emails
      const disposableDomains = ['tempmail.com', 'guerrillamail.com', 'mailinator.com', 
                                 '10minutemail.com', 'yopmail.com', 'throwawaymail.com'];
      const domain = email.split('@')[1].toLowerCase();
      if (disposableDomains.includes(domain)) {
        showError('username', 'Temporary/disposable email addresses are not allowed');
        input.classList.add('error-field');
        return false;
      }
      
      clearError('username');
      input.classList.remove('error-field');
      return true;
    }

    function validatePassword(input) {
      const password = input.value;
      const errorElement = document.getElementById('error_password');
      
      if (!password) {
        showError('password', 'Password is required');
        input.classList.add('error-field');
        return false;
      }
      
      if (password.length < 8) {
        showError('password', 'Password must be at least 8 characters');
        input.classList.add('error-field');
        return false;
      }
      
      if (!/[A-Z]/.test(password)) {
        showError('password', 'Password must contain at least one uppercase letter');
        input.classList.add('error-field');
        return false;
      }
      
      if (!/[a-z]/.test(password)) {
        showError('password', 'Password must contain at least one lowercase letter');
        input.classList.add('error-field');
        return false;
      }
      
      if (!/[0-9]/.test(password)) {
        showError('password', 'Password must contain at least one number');
        input.classList.add('error-field');
        return false;
      }
      
      clearError('password');
      input.classList.remove('error-field');
      return true;
    }

    function checkPasswordStrength(password) {
      let strength = 0;
      
      if (password.length >= 8) strength++;
      if (/[A-Z]/.test(password)) strength++;
      if (/[a-z]/.test(password)) strength++;
      if (/[0-9]/.test(password)) strength++;
      
      const strengthBar = document.getElementById('passwordStrength');
      strengthBar.className = 'password-strength strength-' + strength;
      
      // Update requirement indicators
      document.getElementById('req_length').className = password.length >= 8 ? 'requirement valid' : 'requirement invalid';
      document.getElementById('req_upper').className = /[A-Z]/.test(password) ? 'requirement valid' : 'requirement invalid';
      document.getElementById('req_lower').className = /[a-z]/.test(password) ? 'requirement valid' : 'requirement invalid';
      document.getElementById('req_number').className = /[0-9]/.test(password) ? 'requirement valid' : 'requirement invalid';
    }

    function checkPasswordMatch() {
      const password = document.getElementById('pwd1').value;
      const confirm = document.getElementById('pwd2').value;
      const errorElement = document.getElementById('error_confirm_password');
      const successElement = document.getElementById('success_password_match');
      
      if (!confirm) {
        clearError('confirm_password');
        successElement.style.display = 'none';
        return false;
      }
      
      if (password !== confirm) {
        showError('confirm_password', 'Passwords do not match');
        document.getElementById('pwd2').classList.add('error-field');
        successElement.style.display = 'none';
        return false;
      }
      
      clearError('confirm_password');
      document.getElementById('pwd2').classList.remove('error-field');
      successElement.style.display = 'block';
      return true;
    }

    function validateTerms() {
      const checkbox = document.getElementById('agree_terms');
      const errorElement = document.getElementById('error_agree_terms');
      
      if (!checkbox.checked) {
        showError('agree_terms', 'You must agree to the Terms & Conditions and Privacy Policy');
        return false;
      }
      
      clearError('agree_terms');
      return true;
    }

    function validatePhoneNumber(number) {
      const cleaned = number.replace(/\D/g, '');
      return /^09[0-9]{9}$/.test(cleaned) || /^0[2-9][0-9]{7,9}$/.test(cleaned);
    }

    function formatPhoneNumber(input) {
      let value = input.value.replace(/\D/g, '');
      
      if (value.startsWith('09') && value.length <= 11) {
        input.value = value;
      } else if (value.startsWith('0') && value.length <= 10) {
        input.value = value;
      }
    }

    function validateZipCode(zip) {
      return /^\d{4}$/.test(zip);
    }

    function formatZipCode(input) {
      let value = input.value.replace(/\D/g, '').substring(0, 4);
      input.value = value;
    }

    function validateField(input, fieldName) {
      const value = input.value.trim();
      const errorElement = document.getElementById('error_' + fieldName);
      
      // Basic required field validation
      if (input.classList.contains('required') && !value) {
        showError(fieldName, 'This field is required');
        input.classList.add('error-field');
        return false;
      }
      
      // Special validations
      switch(fieldName) {
        case 'personal_contact':
        case 'company_contact':
          if (value && !validatePhoneNumber(value)) {
            showError(fieldName, 'Please enter a valid phone number');
            input.classList.add('error-field');
            return false;
          }
          break;
          
        case 'taxpayer_name':
          if (value && !/^\d{9,12}$/.test(value.replace(/\D/g, ''))) {
            showError(fieldName, 'Please enter a valid 9-12 digit TIN');
            input.classList.add('error-field');
            return false;
          }
          break;
          
        case 'p_zip':
        case 'c_zip':
          if (value && !validateZipCode(value)) {
            showError(fieldName, 'Please enter a valid 4-digit ZIP code');
            input.classList.add('error-field');
            return false;
          }
          break;
          
        case 'birthdate':
          if (value) {
            const birthdate = new Date(value);
            const today = new Date();
            let age = today.getFullYear() - birthdate.getFullYear();
            const monthDiff = today.getMonth() - birthdate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
              age--;
            }
            
            if (age < 13) {
              showError(fieldName, 'You must be at least 13 years old');
              input.classList.add('error-field');
              return false;
            }
          }
          break;
      }
      
      clearError(fieldName);
      input.classList.remove('error-field');
      return true;
    }

    function showError(fieldName, message) {
      const errorElement = document.getElementById('error_' + fieldName);
      if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
      }
    }

    function clearError(fieldName) {
      const errorElement = document.getElementById('error_' + fieldName);
      if (errorElement) {
        errorElement.style.display = 'none';
      }
    }

    function clearValidationErrors() {
      document.querySelectorAll('.error-text').forEach(el => {
        el.style.display = 'none';
      });
      document.querySelectorAll('.error-field').forEach(el => {
        el.classList.remove('error-field');
      });
    }

    function scrollToFirstError() {
      const firstError = document.querySelector('.error-field');
      if (firstError) {
        firstError.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });
        firstError.focus();
      }
    }

    function validateFinalForm() {
      clearValidationErrors();
      let isValid = true;
      
      // Validate email
      const emailInput = document.querySelector('[name="username"]');
      if (!validateEmail(emailInput)) isValid = false;
      
      // Validate password
      const passwordInput = document.querySelector('[name="password"]');
      if (!validatePassword(passwordInput)) isValid = false;
      
      // Validate password match
      if (!checkPasswordMatch()) isValid = false;
      
      // Validate terms
      if (!validateTerms()) isValid = false;
      
      if (!isValid) {
        scrollToFirstError();
        return false;
      }
      
      // Show loading and submit
      showLoading(true);
      document.getElementById('submitBtn').disabled = true;
      return true;
    }

    function saveFormData() {
      const form = document.getElementById('wizardForm');
      const formData = new FormData(form);
      const obj = {};
      formData.forEach((value, key) => obj[key] = value);
      sessionStorage.setItem('signupForm', JSON.stringify(obj));
    }

    // Clear sessionStorage on successful submission
    window.addEventListener('beforeunload', () => {
      if (<?php echo $success ? 'true' : 'false'; ?>) {
        sessionStorage.removeItem('signupForm');
      }
    });

    // Handle browser back/forward buttons
    window.addEventListener('pageshow', function(event) {
      if (event.persisted) {
        // Page was loaded from cache, restore form state
        const savedData = sessionStorage.getItem('signupForm');
        if (savedData) {
          formData = JSON.parse(savedData);
          if (formData.customer_type) {
            currentAccountType = formData.customer_type;
            document.getElementById('customer_type').value = currentAccountType;
            toggleType(currentAccountType);
            updateAccountTypeBadge();
          }
        }
      }
    });
  </script>
</body>
</html>