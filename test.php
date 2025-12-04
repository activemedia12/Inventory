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

    .account-type-badge {
      display: inline-block;
      background: #1c1c1c;
      color: white;
      padding: 8px 16px;
      border-radius: 4px;
      margin-bottom: 15px;
      font-weight: 600;
      font-size: 14px;
    }
    
    .account-type-badge.company {
      background: #1890ff;
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
    
    .account-type-info {
      background: #f0f8ff;
      border: 1px solid #91d5ff;
      border-radius: 4px;
      padding: 12px 15px;
      margin-bottom: 20px;
      font-size: 14px;
    }
    
    .welcome-message {
      text-align: center;
      margin-bottom: 30px;
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
      background-color: black;
      border: none;
      font-size: 18px;
      line-height: 48px;
      padding: 0 30px;
      color: #fff;
      font-weight: 600;
      cursor: pointer;
      margin: 20px auto;
      display: block;
      transition: .3s;
      border-radius: 4px;
    }
    
    .start-button:hover {
      background-color: rgb(80, 80, 80);
      transform: translateY(-2px);
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
              }, 3000);
            </script>
          <?php endif; ?>
        <?php endif; ?>

        <form method="post" class="signup-form" id="wizardForm">
          <!-- STEP 1: Welcome Screen (No account type selection shown) -->
          <div class="step active" id="step1">
            <div class="welcome-message">
              <h2>Create Your Account</h2>
              <p>Welcome to Active Media Designs and Printing! Let's get you started with your customer account.</p>
              <p style="font-size: 13px; margin-top: 15px; color: #888;">
                By default, you'll be creating a personal account. If you need to register for a company, you can switch during the registration process.
              </p>
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
              <input type="text" name="first_name" placeholder="First Name *" value="<?php echo htmlspecialchars($first_name); ?>" class="<?php echo empty($first_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($first_name) && isset($_POST['customer_type'])): ?>
                <div class="error-text">First name is required</div>
              <?php endif; ?>

              <input type="text" name="middle_name" placeholder="Middle Initial (optional)" value="<?php echo htmlspecialchars($middle_name); ?>">

              <input type="text" name="last_name" placeholder="Last Name *" value="<?php echo htmlspecialchars($last_name); ?>" class="<?php echo empty($last_name) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($last_name) && isset($_POST['customer_type'])): ?>
                <div class="error-text">Last name is required</div>
              <?php endif; ?>

              <select name="gender">
                <option value="">Gender</option>
                <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo $gender === 'Other' ? 'selected' : ''; ?>>Other</option>
              </select>
              
              <label style="font-size: 12px; opacity: 0.5;">Enter your birth date:</label>
              <input type="date" name="birthdate" id="birthdate" placeholder="Birthdate" value="<?php echo htmlspecialchars($birthdate); ?>" onchange="calculateAgeFromDate()">
              <div id="ageDisplay" class="age-display"></div>

              <input type="text" name="personal_contact" placeholder="Contact Number *" value="<?php echo htmlspecialchars($personal_contact); ?>" class="<?php echo empty($personal_contact) && isset($_POST['customer_type']) ? 'error-field' : ''; ?>">
              <?php if (empty($personal_contact) && isset($_POST['customer_type'])): ?>
                <div class="error-text">Contact number is required</div>
              <?php endif; ?>
            </div>

            <div id="company_fields" style="<?php echo ($customer_type ?: 'personal') === 'company' ? 'display:block;' : 'display:none;'; ?>">
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
              <input type="text" name="address_line1" placeholder="Address Line 1 (optional)" value="<?php echo htmlspecialchars($address_line1); ?>">
              <input type="text" name="p_city" placeholder="City" value="<?php echo htmlspecialchars($p_city); ?>">
              <input type="text" name="p_province" placeholder="Province" value="<?php echo htmlspecialchars($p_province); ?>">
              <input type="text" name="p_zip" placeholder="ZIP Code" value="<?php echo htmlspecialchars($p_zip); ?>">
            </div>

            <div id="company_address" style="<?php echo ($customer_type ?: 'personal') === 'company' ? 'display:block;' : 'display:none;'; ?>">
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
            
            <!-- Account Type Badge -->
            <div style="margin-bottom: 15px;">
              <span class="account-type-badge <?php echo ($customer_type ?: 'personal') === 'personal' ? '' : 'company'; ?>">
                <?php echo ($customer_type ?: 'personal') === 'personal' ? 'Personal Account' : 'Company Account'; ?>
              </span>
            </div>
            
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
    const steps = ["step1", "step2", "step3", "step4"];
    let currentStep = 0;
    let currentAccountType = 'personal'; // Default to personal

    // Initialize form
    window.addEventListener('DOMContentLoaded', () => {
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
    });

    function startRegistration() {
      // Start with personal account (default)
      document.getElementById('customer_type').value = 'personal';
      currentAccountType = 'personal';
      showStep(1);
    }

    function startAsCompany() {
      // Start with company account
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

      // Update account type badge based on current step
      updateAccountTypeBadge();
      
      // Scroll to top of form
      document.querySelector('.signup-box').scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }

    function switchAccountType() {
      // Toggle between personal and company
      const newType = currentAccountType === 'personal' ? 'company' : 'personal';
      document.getElementById('customer_type').value = newType;
      currentAccountType = newType;
      
      toggleType(newType);
      updateAccountTypeBadge();
      
      // Clear validation errors when switching
      document.querySelectorAll('.error-field').forEach(el => {
        el.classList.remove('error-field');
      });
      document.querySelectorAll('.error-text').forEach(el => {
        el.style.display = 'none';
      });
    }

    function updateAccountTypeBadge() {
      const badgeText = currentAccountType === 'personal' ? 'Personal Account' : 'Company Account';
      const badgeClass = currentAccountType === 'personal' ? '' : 'company';
      
      // Update all badges
      document.querySelectorAll('.account-type-badge').forEach(badge => {
        badge.textContent = badgeText;
        badge.className = 'account-type-badge ' + badgeClass;
      });
      
      // Update switch link text
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
      } else {
        ageDisplay.textContent = 'Age: ' + age + ' years old';
        ageDisplay.style.color = '#666';
      }
    }

    function validateStep2() {
      const type = currentAccountType;
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
      showStep(3);
    }

    function prevStep(currentStepIndex) {
      showStep(currentStepIndex - 2);
    }

    function toggleType(type) {
      const isPersonal = (type === 'personal');
      
      // Toggle visibility of fields
      document.getElementById('personal_fields').style.display = isPersonal ? 'block' : 'none';
      document.getElementById('company_fields').style.display = isPersonal ? 'none' : 'block';
      document.getElementById('personal_address').style.display = isPersonal ? 'block' : 'none';
      document.getElementById('company_address').style.display = isPersonal ? 'none' : 'block';
      
      // Update current type
      currentAccountType = type;
      updateAccountTypeBadge();
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