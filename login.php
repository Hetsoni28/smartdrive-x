<?php
/**
 * ==============================================================================
 * 🛡️ SMARTDRIVE X - ENTERPRISE AUTHENTICATION PORTAL (V3.0)
 * Purpose: Secure Login, CSRF Defense, Brute-Force Protection & Session Handshake
 * ==============================================================================
 */

session_start();
include 'includes/db_connect.php';

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
$toast_msg = '';
$toast_type = 'danger';

// 3. CAPTURE URL ALERTS (From session_check.php)
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
            $toast_msg = "Secure Area: Please authenticate to continue.";
            $toast_type = "warning";
            break;
        case 'session_hijacked':
            $toast_msg = "Security Alert: Session fingerprint changed. Please log in again.";
            $toast_type = "danger";
            break;
        case 'timeout':
            $toast_msg = "Session Expired: You were securely logged out due to inactivity.";
            $toast_type = "info";
            break;
    }
}

// ==========================================
// 🛡️ SECURE AUTHENTICATION ENGINE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // A. CSRF Validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please refresh the page and try again.";
    } else {
        
        // B. Brute-Force Rate Limiting (5 attempts / 60 seconds)
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
            $lockout_time = 60; 
            if (time() - $_SESSION['last_failed_login'] < $lockout_time) {
                $wait = $lockout_time - (time() - $_SESSION['last_failed_login']);
                $error = "Too many failed attempts. Please wait {$wait} seconds.";
            } else {
                $_SESSION['login_attempts'] = 0; // Reset after wait
            }
        }

        if (empty($error)) {
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            
            if (empty($email) || empty($password)) {
                $error = "Please provide both your email and password.";
            } else {
                try {
                    // C. Prepared Statement to block SQLi. Also fetch 'status' for suspension checks.
                    $stmt = $conn->prepare("SELECT id, role_id, name, password, status FROM users WHERE email = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 1) {
                        $user = $result->fetch_assoc();
                        
                        // D. Account Suspension Check
                        if (isset($user['status']) && $user['status'] === 'suspended') {
                            $error = "Account Suspended: Please contact support to restore access.";
                        } 
                        // E. Password Verification
                        elseif (password_verify($password, $user['password'])) {
                            
                            // 🛑 CRITICAL SECURITY UPGRADE: Session Fixation Defense
                            session_regenerate_id(true);
                            
                            // Reset Brute-force counters
                            $_SESSION['login_attempts'] = 0;
                            
                            // Set Core Session Variables
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['role_id'] = $user['role_id'];
                            $_SESSION['user_name'] = $user['name'];
                            $_SESSION['last_activity'] = time();
                            
                            // 🔐 FINGERPRINT HANDSHAKE: Establish tracking for session_check.php
                            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UnknownAgent';
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                            if (!empty($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
                            elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
                            
                            $ip_parts = explode('.', trim($ip));
                            $subnet = count($ip_parts) === 4 ? "{$ip_parts[0]}.{$ip_parts[1]}.{$ip_parts[2]}" : trim($ip);
                            $_SESSION['user_fingerprint'] = hash('sha256', $user_agent . $subnet);
                            
                            // Route to ecosystem
                            header("Location: " . ($user['role_id'] == 1 ? "admin/dashboard.php" : "customer/dashboard.php"));
                            exit();
                        } else {
                            $error = "Invalid credentials provided.";
                            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                            $_SESSION['last_failed_login'] = time();
                        }
                    } else {
                        // Vague error to prevent email enumeration
                        $error = "Invalid credentials provided.";
                        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                        $_SESSION['last_failed_login'] = time();
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $error = "Authentication server offline. Please try again later.";
                    error_log("Login DB Error: " . $e->getMessage());
                }
            }
        }
    }
}

// 🧠 Dynamic Greeting Logic
date_default_timezone_set('Asia/Kolkata');
$hour = date('H');
if ($hour < 12) { $greeting = "Good Morning"; }
elseif ($hour < 17) { $greeting = "Good Afternoon"; }
else { $greeting = "Good Evening"; }

$page_title = "Secure Login | SmartDrive X";
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
            width: 100%; max-width: 1200px; display: flex; min-height: 700px; position: relative;
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
            animation: crossfade 20s infinite ease-in-out;
        }
        .bg-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 2;
            background: linear-gradient(to top, rgba(30, 36, 28, 0.95) 0%, rgba(30, 36, 28, 0.4) 50%, rgba(30, 36, 28, 0.1) 100%);
        }

        @keyframes crossfade {
            0%, 25% { background-image: url('https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=2070'); }
            33%, 58% { background-image: url('https://images.unsplash.com/photo-1617531653332-bd46c24f2068?q=80&w=2115'); }
            66%, 91% { background-image: url('https://images.unsplash.com/photo-1606016159991-d8532e856086?q=80&w=2080'); }
            100% { background-image: url('https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=2070'); }
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
            width: 50%; padding: 60px 80px; display: flex; flex-direction: column;
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

        /* Social Auth Buttons */
        .social-btn {
            display: flex; align-items: center; justify-content: center;
            width: 100%; padding: 14px; border-radius: 14px;
            border: 2px solid #f1f3f5; background: white;
            color: #495057; font-weight: 700; transition: all 0.3s ease;
            margin-bottom: 15px; cursor: pointer; text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }
        .social-btn:hover { border-color: var(--sage-mid); background: #f8f9fa; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(136, 156, 124, 0.1); }
        .social-btn img { width: 22px; margin-right: 12px; }

        .divider { display: flex; align-items: center; text-align: center; margin: 30px 0; color: #adb5bd; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 2px solid #f1f3f5; }
        .divider:not(:empty)::before { margin-right: 1em; }
        .divider:not(:empty)::after { margin-left: 1em; }

        /* Floating Labels UI */
        .form-floating-custom { position: relative; margin-bottom: 1.5rem; }
        .form-floating-custom input {
            width: 100%; padding: 24px 20px 10px 20px; font-size: 1rem;
            background-color: #f8f9fa; border: 2px solid transparent; border-radius: 14px;
            transition: all 0.3s; font-weight: 600; color: var(--sage-dark);
        }
        .form-floating-custom input:focus { border-color: var(--sage-mid); background-color: white; outline: none; box-shadow: 0 5px 20px rgba(136, 156, 124, 0.15); }
        .form-floating-custom input.is-valid { border-color: #198754; background-color: white; }
        
        .form-floating-custom label {
            position: absolute; top: 50%; left: 20px; transform: translateY(-50%);
            color: #adb5bd; font-size: 1rem; font-weight: 600; pointer-events: none;
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .form-floating-custom input:focus ~ label,
        .form-floating-custom input:not(:placeholder-shown) ~ label {
            top: 14px; font-size: 0.75rem; color: var(--sage-mid); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;
        }

        .password-toggle {
            position: absolute; top: 50%; right: 20px; transform: translateY(-50%);
            cursor: pointer; color: #adb5bd; transition: color 0.3s; z-index: 10; font-size: 1.1rem;
        }
        .password-toggle:hover { color: var(--sage-dark); }

        .btn-auth {
            background: linear-gradient(135deg, var(--sage-dark) 0%, #3a4734 100%);
            color: white; border: none; padding: 18px; font-size: 1.1rem;
            border-radius: 14px; font-weight: 800; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 20px rgba(43, 51, 39, 0.2); margin-top: 15px; letter-spacing: 0.5px;
        }
        .btn-auth:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(43, 51, 39, 0.3); color: white; }

        /* 🔔 Toast Engine */
        #auth-toast-container { position: fixed; top: 20px; right: 20px; z-index: 999999; }
        .auth-toast {
            background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 15px; min-width: 320px;
            transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .auth-toast.show { transform: translateX(0); opacity: 1; }
        .auth-toast.danger { border-left: 5px solid #dc3545; }
        .auth-toast.warning { border-left: 5px solid #ffc107; }
        .auth-toast.info { border-left: 5px solid #0dcaf0; }

        /* Animation Shake */
        .anim-shake { animation: shake 0.6s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake { 10%, 90% { transform: translateX(-2px); } 20%, 80% { transform: translateX(4px); } 30%, 50%, 70% { transform: translateX(-8px); } 40%, 60% { transform: translateX(8px); } }

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
                    <i class="fas fa-shield-check text-success me-2 fs-5"></i> Enterprise Security
                </div>
                <h2 class="display-4 fw-black mb-3" style="line-height: 1.1;">The Open Road<br>Awaits.</h2>
                <p class="lead opacity-75 mb-4 pe-4 fw-bold">Access your centralized dashboard to manage active reservations, download tax invoices, and track your Smart Rewards points.</p>
                
                <div class="d-flex align-items-center mt-5 pt-4 border-top border-secondary border-opacity-25">
                    <div class="d-flex me-3">
                        <img src="https://ui-avatars.com/api/?name=A+K&background=889c7c&color=fff" class="rounded-circle border border-2 border-dark shadow-sm" width="45" style="margin-right: -15px;">
                        <img src="https://ui-avatars.com/api/?name=P+P&background=b9cbb3&color=fff" class="rounded-circle border border-2 border-dark shadow-sm" width="45" style="margin-right: -15px;">
                        <img src="https://ui-avatars.com/api/?name=R+S&background=e0eadb&color=000" class="rounded-circle border border-2 border-dark shadow-sm" width="45">
                    </div>
                    <div class="small fw-bold opacity-75 tracking-wide text-uppercase">Join 5,000+ Verified Riders</div>
                </div>
            </div>
        </div>

        <div class="auth-form-side">
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>Back to Home</a>
            
            <div class="mb-4 mt-4" data-aos="fade-down" data-aos-delay="100">
                <h2 class="fw-black text-dark mb-1"><?php echo $greeting; ?></h2>
                <p class="text-muted fw-bold">Sign in to your SmartDrive X account.</p>
            </div>

            <div data-aos="fade-up" data-aos-delay="200">
                <button type="button" class="social-btn" onclick="showToast('Google SSO Authentication is simulated for this demo.', 'info')">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="G"> Continue with Google
                </button>
            </div>

            <div class="divider" data-aos="fade-up" data-aos-delay="300">or sign in with email</div>

            <?php if($error): ?>
                <div class="alert alert-danger rounded-4 border-0 border-start border-5 border-danger p-3 mb-4 shadow-sm fw-bold text-danger bg-danger bg-opacity-10 d-flex align-items-center anim-shake">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i> 
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm" class="needs-validation" novalidate data-aos="fade-up" data-aos-delay="400">
                
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-floating-custom shadow-sm rounded-4">
                    <input type="email" name="email" id="email" placeholder=" " required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <label for="email">Registered Email</label>
                    <div class="invalid-feedback px-3 pb-2 fw-bold small">Please enter a valid email.</div>
                </div>
                
                <div class="form-floating-custom shadow-sm rounded-4">
                    <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
                    <label for="password">Secure Password</label>
                    <i class="fas fa-eye password-toggle" id="togglePassword" title="Show/Hide Password"></i>
                    <div class="invalid-feedback px-3 pb-2 fw-bold small">Password is required.</div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-4 px-1">
                    <div class="form-check">
                        <input class="form-check-input shadow-none border-secondary" type="checkbox" id="rememberMe">
                        <label class="form-check-label text-muted small fw-bold" for="rememberMe" style="cursor: pointer;">Remember me</label>
                    </div>
                    <a href="#" class="text-decoration-none small fw-bold" style="color: var(--sage-dark);" onclick="showToast('Password reset emails are disabled in this demo.', 'info')">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-auth w-100" id="loginBtn">
                    Secure Login <i class="fas fa-lock ms-2 opacity-75"></i>
                </button>
            </form>
            
            <div class="text-center mt-5" data-aos="fade-up" data-aos-delay="500">
                <p class="text-muted fw-bold small">New to SmartDrive X? <a href="register.php" class="text-decoration-none ms-1 fw-black" style="color: var(--sage-dark); border-bottom: 2px solid var(--sage-mid);">Create an account</a></p>
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

    // 3. TOAST NOTIFICATION ENGINE (For GET Errors)
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

    <?php if($toast_msg): ?>
        document.addEventListener("DOMContentLoaded", () => showToast('<?php echo addslashes($toast_msg); ?>', '<?php echo $toast_type; ?>'));
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

    // 6. CLIENT-SIDE FORM VALIDATION & SUBMIT STATE
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');

    if(loginForm) {
        loginForm.addEventListener('submit', function(event) {
            if (!loginForm.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                loginBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Authenticating...';
                loginBtn.classList.add('disabled');
                loginBtn.style.opacity = '0.8';
            }
            loginForm.classList.add('was-validated');
        }, false);
    }
</script>

</body>
</html>