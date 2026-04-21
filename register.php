<?php
/**
 * ==============================================================================
 * 🛡️ SMARTDRIVE X - ENTERPRISE REGISTRATION PORTAL (V3.0)
 * Purpose: Secure Onboarding, Password Enforcement, CSRF Defense & Input Sanitization
 * ==============================================================================
 */

session_start();
include 'includes/db_connect.php';
include 'includes/functions.php'; // Required for is_strong_password()

// 🌐 BULLETPROOF PATHING
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . "/smartdrive_x/"; 

// 1. SEAMLESS REDIRECT FOR ALREADY AUTHENTICATED USERS
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role_id'] == 1 ? "admin/dashboard.php" : "customer/dashboard.php"));
    exit();
}

// 2. CSRF TOKEN GENERATION
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

// Variables to hold user input in case of validation failure
$name_val = '';
$email_val = '';
$phone_val = '';

// ==========================================
// 🛡️ SECURE REGISTRATION ENGINE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. CSRF Validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        
        // B. Input Sanitization (XSS Defense)
        $name_val  = trim(htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'));
        $email_val = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $phone_val = trim(htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8'));
        $password  = $_POST['password']; // Do not sanitize passwords, hash them

        // C. Strict Backend Validation
        if (empty($name_val) || empty($email_val) || empty($password) || empty($phone_val)) {
            $error = "All fields are required to create a secure account.";
        } elseif (!filter_var($email_val, FILTER_VALIDATE_EMAIL)) {
            $error = "Please provide a valid email address.";
        } elseif (!preg_match('/^[0-9+\-\s]{7,15}$/', $phone_val)) {
            $error = "Please provide a valid phone number.";
        } elseif (!is_strong_password($password)) {
            $error = "Security Policy: Password must be 8+ characters and contain uppercase, lowercase, and numbers.";
        } else {
            
            try {
                // D. Prevent Duplicate Accounts (Prepared Statement)
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check_stmt->bind_param("s", $email_val);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows > 0) {
                    $error = "An account with this email already exists. Please log in.";
                } else {
                    // E. Cryptographically Secure Password Hashing
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $role_id = 2; // Fixed as Customer

                    // F. Secure Database Insertion
                    $insert_stmt = $conn->prepare("INSERT INTO users (role_id, name, email, password, phone, loyalty_points) VALUES (?, ?, ?, ?, ?, 500)"); // 500 Welcome Points
                    $insert_stmt->bind_param("issss", $role_id, $name_val, $email_val, $hashed_password, $phone_val);

                    if ($insert_stmt->execute()) {
                        $success = "Account created securely! Redirecting to login portal...";
                        // Clear form values on success
                        $name_val = $email_val = $phone_val = ''; 
                    } else {
                        $error = "System Error: Could not provision account. Please contact support.";
                        error_log("Registration Error: " . $insert_stmt->error);
                    }
                    $insert_stmt->close();
                }
                $check_stmt->close();
            } catch (Exception $e) {
                $error = "Database server unavailable. Please try again later.";
                error_log("Registration DB Exception: " . $e->getMessage());
            }
        }
    }
}

$page_title = "Create Account | SmartDrive X";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        /* 🌿 BEAST MODE AUTHENTICATION THEME */
        :root {
            --sage-dark: #2b3327;
            --sage-mid: #889c7c;
            --sage-pale: #e0eadb;
            --sage-white: #f4f5f3;
            --shadow-float: 0 40px 80px rgba(0,0,0,0.15);
        }

        body { background-color: var(--sage-white); overflow-x: hidden; font-family: 'Plus Jakarta Sans', sans-serif; }

        /* 🦴 Skeleton Loading Engine */
        .skeleton-box { position: relative; overflow: hidden; background-color: #e2e8f0; color: transparent !important; border-color: transparent !important; user-select: none; pointer-events: none; border-radius: 12px; }
        .skeleton-box * { visibility: hidden; }
        .skeleton-box::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.6) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 100% { transform: translateX(100%); } }

        /* 📦 Split Screen Layout */
        .auth-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }

        .auth-card {
            background: white; border-radius: 30px; overflow: hidden; box-shadow: var(--shadow-float);
            width: 100%; max-width: 1200px; display: flex; min-height: 750px; position: relative;
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* 🖼️ Left Side: Cinematic Image Slider */
        .auth-image-side {
            width: 50%; position: relative; display: flex; flex-direction: column;
            justify-content: flex-end; padding: 60px; color: white; overflow: hidden;
        }
        
        .bg-slider {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1;
            background-size: cover; background-position: center;
            background-image: url('https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?q=80&w=2070&auto=format&fit=crop');
            transform: scale(1.05); transition: transform 10s ease;
        }
        .auth-card:hover .bg-slider { transform: scale(1.0); }
        
        .bg-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 2;
            background: linear-gradient(to top, rgba(30, 36, 28, 0.95) 0%, rgba(30, 36, 28, 0.4) 50%, rgba(30, 36, 28, 0.1) 100%);
        }

        .auth-image-content { position: relative; z-index: 3; }
        
        .trust-pill {
            background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2); border-radius: 50px;
            padding: 8px 20px; display: inline-flex; align-items: center;
            font-size: 0.85rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
            margin-bottom: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        /* 📝 Right Side: Form Area */
        .auth-form-side {
            width: 50%; padding: 50px 80px; display: flex; flex-direction: column;
            justify-content: center; background-color: white; position: relative; z-index: 4;
        }

        .back-btn {
            position: absolute; top: 40px; left: 40px;
            color: #adb5bd; font-weight: 800; text-decoration: none; text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem;
            transition: all 0.3s; display: flex; align-items: center;
        }
        .back-btn i { transition: transform 0.3s; }
        .back-btn:hover { color: var(--sage-dark); }
        .back-btn:hover i { transform: translateX(-5px); }

        /* Floating Labels UI */
        .form-floating-custom { position: relative; margin-bottom: 1.2rem; }
        .form-floating-custom input {
            width: 100%; padding: 24px 20px 10px 20px; font-size: 0.95rem;
            background-color: #f8f9fa; border: 2px solid transparent; border-radius: 14px;
            transition: all 0.3s; font-weight: 600; color: var(--sage-dark);
        }
        .form-floating-custom input:focus { border-color: var(--sage-mid); background-color: white; outline: none; box-shadow: 0 5px 20px rgba(136, 156, 124, 0.15); }
        .form-floating-custom input.is-valid { border-color: #198754; background-color: white; }
        
        .form-floating-custom label {
            position: absolute; top: 50%; left: 20px; transform: translateY(-50%);
            color: #adb5bd; font-size: 0.95rem; font-weight: 600; pointer-events: none;
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .form-floating-custom input:focus ~ label,
        .form-floating-custom input:not(:placeholder-shown) ~ label {
            top: 14px; font-size: 0.7rem; color: var(--sage-mid); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
        }

        .password-toggle {
            position: absolute; top: 50%; right: 20px; transform: translateY(-50%);
            cursor: pointer; color: #adb5bd; transition: color 0.3s; z-index: 10; font-size: 1.1rem;
        }
        .password-toggle:hover { color: var(--sage-dark); }

        /* Password Strength Meter */
        .strength-meter { height: 6px; background-color: #e9ecef; border-radius: 4px; margin-top: 8px; overflow: hidden; border: 1px solid rgba(0,0,0,0.05); }
        .strength-bar { height: 100%; width: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); border-radius: 4px; }
        .rule-check { color: #adb5bd; font-size: 0.8rem; transition: color 0.3s; }
        .rule-check.valid { color: #198754; }

        .btn-auth {
            background: linear-gradient(135deg, var(--sage-dark) 0%, #3a4734 100%);
            color: white; border: none; padding: 18px; font-size: 1.1rem;
            border-radius: 14px; font-weight: 800; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 20px rgba(43, 51, 39, 0.2); margin-top: 15px; letter-spacing: 0.5px;
        }
        .btn-auth:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(43, 51, 39, 0.3); color: white; }
        .btn-auth:disabled { background: #adb5bd; cursor: not-allowed; box-shadow: none; transform: none; }

        /* 🔔 Toast Engine */
        #auth-toast-container { position: fixed; top: 20px; right: 20px; z-index: 999999; }
        .auth-toast {
            background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 15px; min-width: 320px;
            transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .auth-toast.show { transform: translateX(0); opacity: 1; }
        .auth-toast.danger { border-left: 5px solid #dc3545; }
        .auth-toast.success { border-left: 5px solid #198754; }
        .auth-toast.warning { border-left: 5px solid #ffc107; }

        @media (max-width: 991px) {
            .auth-image-side { display: none; }
            .auth-form-side { width: 100%; padding: 40px 30px; }
            .auth-card { min-height: auto; max-width: 500px; }
        }
    </style>
</head>
<body>

<div id="auth-toast-container"></div>

<div class="auth-wrapper">
    <div class="auth-card load-anim skeleton-box" data-aos="zoom-in" data-aos-duration="800">
        
        <div class="auth-image-side">
            <div class="bg-slider"></div>
            <div class="bg-overlay"></div>
            
            <div class="auth-image-content" data-aos="fade-right" data-aos-delay="300">
                <div class="trust-pill shadow-sm">
                    <i class="fas fa-gift text-warning me-2 fs-5"></i> 500 Bonus Points
                </div>
                <h2 class="display-4 fw-black mb-3" style="line-height: 1.1;">Join The Elite<br>Fleet.</h2>
                <p class="lead opacity-75 mb-0 pe-4 fw-bold">Create your account today. Unlock instant bookings, zero hidden fees, and seamless digital invoicing.</p>
            </div>
        </div>

        <div class="auth-form-side">
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>Back to Home</a>
            
            <div class="mb-4 mt-4" data-aos="fade-down" data-aos-delay="100">
                <h2 class="fw-black text-dark mb-1">Create Account</h2>
                <p class="text-muted fw-bold">Sign up to start driving your dreams.</p>
            </div>

            <form action="register.php" method="POST" id="registerForm" class="needs-validation" novalidate data-aos="fade-up" data-aos-delay="200">
                
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-floating-custom shadow-sm rounded-4">
                    <input type="text" name="name" id="name" placeholder=" " required autocomplete="name" autofocus value="<?php echo htmlspecialchars($name_val); ?>">
                    <label for="name">Full Legal Name</label>
                    <div class="invalid-feedback px-3 pb-2 fw-bold small">Please provide your full name.</div>
                </div>

                <div class="row g-2">
                    <div class="col-sm-6 form-floating-custom shadow-sm rounded-4">
                        <input type="email" name="email" id="email" placeholder=" " required autocomplete="email" value="<?php echo htmlspecialchars($email_val); ?>">
                        <label for="email">Email Address</label>
                    </div>
                    <div class="col-sm-6 form-floating-custom shadow-sm rounded-4">
                        <input type="tel" name="phone" id="phone" placeholder=" " required autocomplete="tel" pattern="[0-9+\-\s]{7,15}" value="<?php echo htmlspecialchars($phone_val); ?>">
                        <label for="phone">Phone Number</label>
                    </div>
                </div>
                
                <div class="form-floating-custom mb-2 shadow-sm rounded-4">
                    <input type="password" name="password" id="password" placeholder=" " required autocomplete="new-password">
                    <label for="password">Create Secure Password</label>
                    <i class="fas fa-eye password-toggle" id="togglePassword" title="Show/Hide Password"></i>
                </div>
                
                <div class="strength-meter mb-2 shadow-sm">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                
                <div class="row g-2 mb-4 px-2" id="pwRules">
                    <div class="col-6"><span class="rule-check fw-bold" id="r-len"><i class="fas fa-times-circle me-1"></i>8+ Chars</span></div>
                    <div class="col-6"><span class="rule-check fw-bold" id="r-upper"><i class="fas fa-times-circle me-1"></i>Uppercase</span></div>
                    <div class="col-6"><span class="rule-check fw-bold" id="r-lower"><i class="fas fa-times-circle me-1"></i>Lowercase</span></div>
                    <div class="col-6"><span class="rule-check fw-bold" id="r-num"><i class="fas fa-times-circle me-1"></i>Number</span></div>
                </div>

                <div class="form-check mb-4 px-3">
                    <input class="form-check-input shadow-none border-secondary" type="checkbox" id="terms" required>
                    <label class="form-check-label text-muted small fw-bold" for="terms" style="cursor: pointer;">
                        I agree to the <a href="#" class="text-decoration-none" style="color: var(--sage-dark);">Terms of Service</a> & <a href="#" class="text-decoration-none" style="color: var(--sage-dark);">Privacy Policy</a>.
                    </label>
                </div>

                <button type="submit" class="btn-auth w-100" id="registerBtn" disabled>
                    Secure Registration <i class="fas fa-shield-alt ms-2 opacity-75"></i>
                </button>
            </form>
            
            <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="300">
                <p class="text-muted fw-bold small">Already a member? <a href="login.php" class="text-decoration-none ms-1 fw-black" style="color: var(--sage-dark); border-bottom: 2px solid var(--sage-mid);">Sign in securely</a></p>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // 1. SKELETON LOADER REMOVAL (Core Web Vitals Optimization)
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            document.querySelectorAll('.skeleton-box').forEach(el => {
                el.classList.remove('skeleton-box');
            });
        }, 200); 
    });

    // 2. INITIALIZE AOS
    if(typeof AOS !== 'undefined') {
        AOS.init({ once: true, offset: 50, duration: 800, easing: 'ease-out-cubic' });
    }

    // 3. TOAST NOTIFICATION ENGINE (For Backend Errors/Success)
    function showToast(msg, type) {
        const toastContainer = document.getElementById('auth-toast-container');
        let icon = 'fa-info-circle text-info';
        if(type === 'success') icon = 'fa-check-circle text-success';
        if(type === 'warning') icon = 'fa-exclamation-triangle text-warning';
        if(type === 'danger') icon = 'fa-shield-alt text-danger';

        const toast = document.createElement('div');
        toast.className = `auth-toast ${type}`;
        toast.innerHTML = `<i class="fas ${icon} fa-2x"></i><div><h6 class="fw-bold mb-0 text-dark">System Protocol</h6><small class="text-muted fw-bold">${msg}</small></div>`;
        
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 6000);
    }

    <?php if($error): ?>
        document.addEventListener("DOMContentLoaded", () => showToast('<?php echo addslashes($error); ?>', 'danger'));
    <?php endif; ?>
    <?php if($success): ?>
        document.addEventListener("DOMContentLoaded", () => {
            showToast('<?php echo addslashes($success); ?>', 'success');
            setTimeout(() => { window.location.href = 'login.php'; }, 2500);
        });
    <?php endif; ?>

    // 4. INTERACTIVE PASSWORD VISIBILITY TOGGLE
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');

    if(togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // 5. LIVE EMAIL REGEX VALIDATION (Green Border feedback)
    const emailInput = document.getElementById('email');
    if(emailInput) {
        emailInput.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(emailRegex.test(this.value)) {
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
            }
        });
    }

    // 6. ENTERPRISE LIVE PASSWORD STRENGTH ENGINE
    const strengthBar = document.getElementById('strengthBar');
    const registerBtn = document.getElementById('registerBtn');
    const termsCheck = document.getElementById('terms');

    function updateRule(id, isValid) {
        const el = document.getElementById(id);
        const icon = el.querySelector('i');
        if(isValid) {
            el.classList.add('valid');
            icon.classList.replace('fa-times-circle', 'fa-check-circle');
        } else {
            el.classList.remove('valid');
            icon.classList.replace('fa-check-circle', 'fa-times-circle');
        }
    }

    function checkFormValidity() {
        const val = passwordInput.value;
        const len = val.length >= 8;
        const up  = /[A-Z]/.test(val);
        const low = /[a-z]/.test(val);
        const num = /[0-9]/.test(val);
        
        updateRule('r-len', len);
        updateRule('r-upper', up);
        updateRule('r-lower', low);
        updateRule('r-num', num);

        let strength = (len + up + low + num) * 25;
        strengthBar.style.width = strength + '%';

        if (strength <= 25) strengthBar.style.backgroundColor = '#dc3545';
        else if (strength <= 75) strengthBar.style.backgroundColor = '#ffc107';
        else strengthBar.style.backgroundColor = '#198754';

        // Only enable button if password is 100% strong AND terms are checked
        if(strength === 100 && termsCheck.checked) {
            registerBtn.disabled = false;
        } else {
            registerBtn.disabled = true;
        }
    }

    if(passwordInput) passwordInput.addEventListener('input', checkFormValidity);
    if(termsCheck) termsCheck.addEventListener('change', checkFormValidity);

    // 7. CLIENT-SIDE FORM SUBMISSION STATE
    const registerForm = document.getElementById('registerForm');

    if(registerForm) {
        registerForm.addEventListener('submit', function(event) {
            if (!registerForm.checkValidity() || registerBtn.disabled) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                registerBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Provisioning Account...';
                registerBtn.classList.add('disabled');
                registerBtn.style.opacity = '0.8';
            }
            registerForm.classList.add('was-validated');
        }, false);
    }
</script>

</body>
</html>