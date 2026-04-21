<?php
session_start();

// 🛡️ SECURITY: Customer Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

// Safe include for notifications
if (file_exists('../includes/notify.php')) {
    include_once '../includes/notify.php';
}

// ⚡ Define $base_url early for redirects
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

$error = '';
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id = (int)$_SESSION['user_id'];

// ==========================================
// 🔍 SECURE VALIDATION & DATA FETCHING
// ==========================================
$stmt = $conn->prepare("
    SELECT b.*, c.name as car_name, c.brand, c.image, l.city_name 
    FROM bookings b 
    JOIN cars c ON b.car_id = c.id 
    JOIN locations l ON c.location_id = l.id
    WHERE b.id = ? AND b.user_id = ? AND b.booking_status IN ('pending', 'approved')
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // If booking doesn't exist, is invalid, or already paid, kick them back
    header("Location: dashboard.php");
    exit();
}

$booking = $result->fetch_assoc();
$final_price = (float)$booking['final_price'];
$stmt->close();

// ==========================================
// 💳 PROCESS PAYMENT (ACID TRANSACTION)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['payment_method'])) {
    
    $payment_method = trim($_POST['payment_method']);
    
    // Reverse engineer GST (18%)
    $base_before_tax = $final_price / 1.18;
    $gst_amount = $final_price - $base_before_tax;
    
    // Generate a Professional Invoice Number
    $invoice_no = "INV-" . date("Ymd") . "-" . strtoupper(bin2hex(random_bytes(3)));
    $points_earned = floor($final_price / 100);
    
    // 🚦 START DATABASE TRANSACTION (Enterprise Data Protection)
    mysqli_begin_transaction($conn);
    
    try {
        $stmt1 = $conn->prepare("UPDATE bookings SET booking_status = 'confirmed', payment_status = 'paid' WHERE id = ? AND user_id = ?");
        $stmt1->bind_param("ii", $booking_id, $user_id);
        $stmt1->execute();
        
        // Step 2: Insert Secure Payment Record
        $stmt2 = $conn->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_status) VALUES (?, ?, ?, 'PAID')");
        $stmt2->bind_param("ids", $booking_id, $final_price, $payment_method);
        $stmt2->execute();
        
        // Step 3: Insert Invoice Record
        $stmt3 = $conn->prepare("INSERT INTO invoices (booking_id, invoice_number, gst_amount, total_with_tax) VALUES (?, ?, ?, ?)");
        $stmt3->bind_param("isdd", $booking_id, $invoice_no, $gst_amount, $final_price);
        $stmt3->execute();
        
        // Step 4: Update User Loyalty Points
        $stmt4 = $conn->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?");
        $stmt4->bind_param("ii", $points_earned, $user_id);
        $stmt4->execute();
        $_SESSION['loyalty_points'] = ($_SESSION['loyalty_points'] ?? 0) + $points_earned; // Sync live session
        
        // 🚀 COMMIT TRANSACTION (Lock data permanently)
        mysqli_commit($conn);
        
        if (function_exists('notify_payment_success')) {
            $car_label = $booking['brand'] . ' ' . $booking['car_name'];
            notify_payment_success($conn, $user_id, $booking_id, $car_label, $final_price, $payment_method);
        }
        // V2: Notify admin that payment was received
        if (function_exists('notify_payment_received_admin')) {
            $car_label = $booking['brand'] . ' ' . $booking['car_name'];
            notify_payment_received_admin($conn, $user_id, $booking_id, $car_label, $final_price, $payment_method);
        }
        
        // Redirect to Invoice generator
        header("Location: " . $base_url . "customer/invoice.php?id=$booking_id");
        exit();
        
    } catch (Exception $e) {
        // 🛑 CRITICAL FAILURE: Reverse everything if ANY query fails!
        mysqli_rollback($conn);
        $error = "Payment gateway simulation failed. Connection dropped. Please try again.";
        error_log("Payment Transaction Failed: " . $e->getMessage());
    }
}

// 📸 Smart Image Fallback
$db_image = $booking['image'] ?? '';
$brand = strtolower(trim($booking['brand']));

function resolveCarImage($db_img, $brand, $base) {
    if (!empty($db_img) && file_exists('../' . $db_img)) return $base . $db_img;
    $fallbacks = [
        'porsche' => 'https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=2070',
        'bmw' => 'https://images.unsplash.com/photo-1556189250-72ba954cfc2b?q=80&w=2070',
        'audi' => 'https://images.unsplash.com/photo-1606152421802-db97b9c7a11b?q=80&w=2070',
        'mercedes' => 'https://images.unsplash.com/photo-1610880846497-7257b23f6128?q=80&w=2070'
    ];
    return $fallbacks[$brand] ?? 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&w=2070';
}
$hero_bg = resolveCarImage($db_image, $brand, $base_url);

$page_title = "Secure Checkout | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🌿 CUSTOMER SAGE THEME & PAYMENT UI */
    :root {
        --sage-dark: #2b3327;
        --sage-mid: #889c7c;
        --sage-pale: #e0eadb;
        --sage-bg: #f4f5f3;
        --shadow-soft: 0 10px 30px rgba(0,0,0,0.04);
        --shadow-hover: 0 15px 35px rgba(74, 92, 67, 0.12);
    }
    body { background-color: var(--sage-bg); overflow-x: hidden; }

    /* 🦴 Skeleton Loading Engine */
    .skeleton-box { position: relative; overflow: hidden; background-color: #e2e8f0; color: transparent !important; border-color: transparent !important; user-select: none; pointer-events: none; border-radius: 12px; }
    .skeleton-box * { visibility: hidden; }
    .skeleton-box::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.6) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 100% { transform: translateX(100%); } }

    /* Gateway Layout */
    .gateway-header {
        background: linear-gradient(135deg, var(--sage-dark) 0%, #3a4734 100%);
        color: white; padding: 50px 0; margin-top: -24px; position: relative; overflow: hidden;
    }
    .gateway-header::after {
        content: '\f023'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: 5%; top: -30px; font-size: 10rem;
        color: rgba(255, 255, 255, 0.03); transform: rotate(15deg); pointer-events: none;
    }
    
    /* Payment Method Tabs */
    .payment-method-card {
        border: 2px solid transparent; border-radius: 16px; cursor: pointer; 
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: white;
    }
    .payment-method-card:hover { border-color: var(--sage-pale); transform: translateY(-5px); box-shadow: var(--shadow-soft); }
    .payment-method-card.active { border-color: var(--sage-mid); background-color: #fbfcfa; box-shadow: var(--shadow-hover); transform: translateY(-5px); }
    
    /* Stripe-style Input Fields */
    .stripe-input {
        background-color: #f8f9fa; border: 2px solid transparent; border-radius: 12px; 
        padding: 15px 20px; font-size: 1.05rem; transition: all 0.3s; font-weight: 600; color: var(--sage-dark);
    }
    .stripe-input:focus { border-color: var(--sage-mid); box-shadow: 0 0 0 4px rgba(136, 156, 124, 0.15); outline: none; background-color: white; }
    
    .gateway-box { display: none; animation: fadeIn 0.4s ease forwards; }
    .gateway-box.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

    /* Trust Badges */
    .trust-badge { display: flex; align-items: center; justify-content: center; gap: 20px; opacity: 0.6; font-size: 0.85rem; margin-top: 30px; font-weight: bold; text-transform: uppercase; }
    
    /* Processing Modal (Glassmorphism) */
    #processingModal .modal-content { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 24px; border: 1px solid rgba(255,255,255,0.8); }
    .processing-step { opacity: 0.3; transition: all 0.4s ease; font-weight: bold; margin: 15px 0; }
    .processing-step.active { opacity: 1; color: var(--sage-dark); font-size: 1.2rem; transform: scale(1.05); }
    .processing-step.done { opacity: 0.5; color: #198754; text-decoration: line-through; }
    
    /* Background Shapes */
    .shape-blob { position: absolute; border-radius: 50%; filter: blur(80px); z-index: -1; opacity: 0.3; pointer-events: none; }
    .shape-1 { background: var(--sage-mid); width: 400px; height: 400px; top: 100px; left: -100px; }

    /* 🔔 Toast Engine */
    #cust-toast-container { position: fixed; top: 20px; right: 20px; z-index: 999999; }
    .cust-toast {
        background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        display: flex; align-items: center; gap: 15px; min-width: 320px;
        transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    .cust-toast.show { transform: translateX(0); opacity: 1; }
    .cust-toast.danger { border-left: 5px solid #dc3545; }
    .cust-toast.warning { border-left: 5px solid #ffc107; }

    /* Fix Animation Shake Bug */
    .anim-shake { animation: shake 0.6s cubic-bezier(.36,.07,.19,.97) both; }
    @keyframes shake { 10%, 90% { transform: translateX(-2px); } 20%, 80% { transform: translateX(4px); } 30%, 50%, 70% { transform: translateX(-8px); } 40%, 60% { transform: translateX(8px); } }
</style>

<div id="cust-toast-container"></div>

<div class="gateway-header shadow-sm load-anim skeleton-box">
    <div class="container d-flex justify-content-between align-items-center position-relative z-2">
        <div>
            <h3 class="fw-black mb-1"><i class="fas fa-shield-check text-warning me-2"></i> Secure Gateway</h3>
            <p class="text-white opacity-75 mb-0 small fw-bold tracking-wide">CONFIRM BOOKING #BKG-<?php echo $booking_id; ?></p>
        </div>
        <span class="badge bg-white text-dark rounded-pill px-4 py-2 shadow-sm fw-bold d-none d-md-block"><i class="fas fa-lock text-success me-1"></i> 256-bit Encrypted</span>
    </div>
</div>

<div class="container mt-5 mb-5 position-relative z-2">
    <div class="shape-blob shape-1"></div>
    
    <?php if($error): ?>
        <div class="alert alert-danger rounded-4 shadow-sm p-4 fw-bold border-0 border-start border-4 border-danger anim-shake text-danger bg-white mb-4 d-flex align-items-center">
            <i class="fas fa-exclamation-circle fa-2x me-3"></i>
            <div>
                <h6 class="mb-0 fw-bold text-dark">Transaction Failed</h6>
                <small><?php echo htmlspecialchars($error); ?></small>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-5">
        
        <div class="col-lg-7" data-aos="fade-right">
            <h4 class="fw-black mb-4 text-dark load-anim skeleton-box">Select Payment Method</h4>
            
            <form action="<?php echo $base_url; ?>customer/payment.php?booking_id=<?php echo $booking_id; ?>" method="POST" id="paymentForm">
                
                <input type="hidden" name="payment_method" id="selected_method" value="Credit Card">
                <input type="hidden" name="upi_id" id="upi_id_hidden" value="">

                <div class="row g-3 mb-4 load-anim skeleton-box">
                    <div class="col-md-4">
                        <div class="payment-method-card active p-4 text-center h-100 shadow-sm" onclick="switchMethod('card', this, 'Credit Card')">
                            <i class="fas fa-credit-card fa-2x mb-3" style="color: var(--sage-dark);"></i>
                            <h6 class="fw-bold mb-0 text-dark">Card</h6>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="payment-method-card p-4 text-center h-100 shadow-sm" onclick="switchMethod('upi', this, 'UPI')">
                            <i class="fas fa-qrcode fa-2x mb-3 text-success"></i>
                            <h6 class="fw-bold mb-0 text-dark">UPI</h6>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="payment-method-card p-4 text-center h-100 shadow-sm" onclick="switchMethod('netbanking', this, 'Net Banking')">
                            <i class="fas fa-university fa-2x mb-3 text-info"></i>
                            <h6 class="fw-bold mb-0 text-dark">Banking</h6>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white gateway-box active load-anim skeleton-box" id="gateway-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0 text-dark">Credit / Debit Card</h5>
                        <div class="fs-4 text-muted"><i class="fab fa-cc-visa me-2"></i><i class="fab fa-cc-mastercard me-2"></i><i class="fab fa-cc-amex"></i></div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase tracking-wide">Card Number</label>
                        <div class="position-relative">
                            <input type="text" class="stripe-input w-100" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" required>
                            <i class="fas fa-credit-card position-absolute top-50 end-0 translate-middle-y me-3 text-muted"></i>
                        </div>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-6">
                            <label class="form-label fw-bold text-muted small text-uppercase tracking-wide">Expiry Date</label>
                            <input type="text" class="stripe-input w-100" id="cardExpiry" placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-muted small text-uppercase tracking-wide">Security Code (CVV)</label>
                            <input type="password" class="stripe-input w-100" id="cardCvv" placeholder="•••" maxlength="4" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold text-muted small text-uppercase tracking-wide">Cardholder Name</label>
                        <input type="text" class="stripe-input w-100" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" required>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white gateway-box load-anim skeleton-box" id="gateway-upi">
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center gap-2 px-4 py-2 rounded-pill shadow-sm mb-3" style="background: linear-gradient(135deg, #5f259f, #8b35cc); color: white;">
                            <span class="fw-black fs-5" style="letter-spacing:-1px; line-height:1;">Pe</span>
                            <span class="fw-bold">PhonePe UPI</span>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Scan & Pay Instantly</h5>
                        <p class="text-muted small fw-bold mb-0">Open any UPI app &rarr; Scan QR &rarr; Pay &#8377;<?php echo number_format($final_price, 0); ?></p>
                    </div>

                    <div class="text-center mb-4">
                        <div class="d-inline-block position-relative p-3 rounded-4 shadow-sm" style="background: white; border: 2.5px solid #e8d5f7;">
                            <div class="position-absolute" style="top:-10px; right:-10px;">
                                <span class="badge rounded-pill px-2 py-1 shadow" style="background:#5f259f; font-size:0.65rem;">
                                    <i class="fas fa-check-circle me-1"></i>Verified
                                </span>
                            </div>
                            <img src="<?php echo $base_url; ?>assets/images/phonepe_qr.png" alt="PhonePe UPI QR Code" style="width:200px; height:200px; object-fit:contain; border-radius:6px;" onerror="this.src='https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=upi://pay?pa=smartdrivex@phonepe'">
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <span class="badge bg-white border rounded-pill px-3 py-2 fw-bold text-dark shadow-sm" style="font-size:.78rem;"><i class="fas fa-qrcode me-1 text-success"></i>GPay</span>
                            <span class="badge bg-white border rounded-pill px-3 py-2 fw-bold text-dark shadow-sm" style="font-size:.78rem;"><i class="fas fa-wallet me-1 text-primary"></i>Paytm</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3 my-4">
                        <hr class="flex-grow-1 m-0" style="border-color:#e8d5f7;">
                        <span class="text-muted fw-bold small px-2">OR ENTER UPI ID</span>
                        <hr class="flex-grow-1 m-0" style="border-color:#e8d5f7;">
                    </div>

                    <div class="input-group shadow-sm rounded-3 overflow-hidden">
                        <span class="input-group-text bg-white" style="color:#5f259f; border-right:0;"><i class="fas fa-at"></i></span>
                        <input type="text" class="stripe-input flex-grow-1 border-start-0" id="upiIdInput" placeholder="yourname@ybl  or  yourname@paytm" oninput="syncUpiId(this.value)">
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white gateway-box load-anim skeleton-box" id="gateway-netbanking">
                    <h5 class="fw-bold mb-4 text-dark text-center">Select Your Bank</h5>
                    <select class="stripe-input w-100 mb-4 shadow-sm" style="cursor: pointer;">
                        <option value="">Choose your provider...</option>
                        <option>State Bank of India (SBI)</option>
                        <option>HDFC Bank</option>
                        <option>ICICI Bank</option>
                        <option>Axis Bank</option>
                        <option>Kotak Mahindra</option>
                    </select>
                    <div class="alert bg-light border border-secondary border-opacity-25 rounded-3 text-center text-muted small fw-bold p-3">
                        <i class="fas fa-lock me-1 text-success"></i> You will be securely redirected to your bank's portal.
                    </div>
                </div>

                <div class="load-anim skeleton-box">
                    <button type="button" id="paySubmitBtn" class="btn w-100 rounded-pill fw-bold py-3 mt-4 shadow-lg text-white" style="background: linear-gradient(135deg, var(--sage-dark) 0%, #3a4734 100%); font-size: 1.1rem; transition: transform 0.3s;" onclick="simulateBankProcessing()">
                        Pay ₹<?php echo number_format($final_price, 2); ?> Securely <i class="fas fa-lock ms-2 opacity-75"></i>
                    </button>
                    
                    <div class="trust-badge">
                        <span><i class="fas fa-check-circle text-success"></i> SSL Verified</span>
                        <span>|</span>
                        <span><i class="fas fa-shield-alt text-primary"></i> PCI-DSS Compliant</span>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-5" data-aos="fade-left" data-aos-delay="100">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden position-sticky load-anim skeleton-box" style="top: 100px;">
                <div class="p-4" style="background: linear-gradient(to top, rgba(43,51,39,0.9), rgba(43,51,39,0.5)), url('<?php echo $hero_bg; ?>') center/cover; color: white;">
                    <span class="badge mb-3 shadow-sm border border-light border-opacity-25" style="background-color: rgba(255,255,255,0.15); backdrop-filter: blur(5px);"><?php echo htmlspecialchars($booking['city_name']); ?> Hub</span>
                    <h3 class="fw-black mb-1 text-white"><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['car_name']); ?></h3>
                    <p class="text-light opacity-75 mb-0 small fw-bold tracking-wide">Ref: #BKG-<?php echo $booking_id; ?></p>
                </div>
                
                <div class="card-body p-4 p-xl-5 bg-white">
                    <h6 class="fw-bold text-uppercase text-muted mb-4 small tracking-wide border-bottom pb-2">Trip Summary</h6>
                    
                    <div class="d-flex justify-content-between mb-3 text-muted">
                        <span class="fw-bold">Rental Period</span>
                        <span class="fw-bold text-dark"><?php echo $booking['total_days']; ?> Days</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 text-muted">
                        <span class="fw-bold">Pickup</span>
                        <span class="fw-bold text-dark"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-4 text-muted pb-4 border-bottom">
                        <span class="fw-bold">Return</span>
                        <span class="fw-bold text-dark"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3 bg-light p-4 rounded-4 border shadow-sm">
                        <span class="fw-bold text-dark text-uppercase small tracking-wide">Amount Due</span>
                        <h2 class="fw-black mb-0" style="color: var(--sage-dark);">₹<?php echo number_format($final_price, 2); ?></h2>
                    </div>
                    
                    <p class="text-center text-muted small mt-4 fw-bold mb-0"><i class="fas fa-info-circle text-primary me-1"></i> Includes 18% GST & Platform Fees</p>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="processingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-5 text-center shadow-lg">
            <div class="mb-4">
                <svg id="processSpinner" width="80" height="80" viewBox="0 0 50 50" xmlns="http://www.w3.org/2000/svg" stroke="var(--sage-mid)">
                    <g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="4"><circle stroke-opacity=".2" cx="24" cy="24" r="24"/><path d="M24 0c13.255 0 24 10.745 24 24"><animateTransform attributeName="transform" type="rotate" from="0 24 24" to="360 24 24" dur="1s" repeatCount="indefinite"/></path></g></g>
                </svg>
                <i class="fas fa-check-circle fa-4x text-success d-none" id="processSuccess"></i>
            </div>
            <h4 class="fw-black text-dark mb-4" id="modalTitle">Processing Transaction</h4>
            <div id="step1" class="processing-step active">Establishing secure tunnel...</div>
            <div id="step2" class="processing-step">Authenticating details...</div>
            <div id="step3" class="processing-step">Confirming payment block...</div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // 1. SKELETON LOADER REMOVAL ENGINE
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            document.querySelectorAll('.skeleton-box').forEach(el => {
                el.classList.remove('skeleton-box');
            });
        }, 300);
    });

    // 2. INITIALIZE AOS
    if(typeof AOS !== 'undefined') {
        AOS.init({ once: true, offset: 50, duration: 800, easing: 'ease-out-cubic' });
    }

    // 3. TOAST NOTIFICATION ENGINE
    function showToast(msg, type) {
        const toastContainer = document.getElementById('cust-toast-container');
        let icon = 'fa-info-circle';
        if(type === 'success') icon = 'fa-check-circle text-success';
        if(type === 'warning') icon = 'fa-exclamation-triangle text-warning';
        if(type === 'danger') icon = 'fa-times-circle text-danger';

        const toast = document.createElement('div');
        toast.className = `cust-toast ${type}`;
        toast.innerHTML = `<i class="fas ${icon} fs-4"></i><div><h6 class="fw-bold mb-0 text-dark">System Alert</h6><small class="text-muted fw-bold">${msg}</small></div>`;
        
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 4000);
    }

    // 4. TAB SWITCHING
    function switchMethod(boxId, element, methodValue) {
        document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
        element.classList.add('active');
        document.querySelectorAll('.gateway-box').forEach(b => b.classList.remove('active'));
        document.getElementById('gateway-' + boxId).classList.add('active');
        document.getElementById('selected_method').value = methodValue;
    }

    // 5. UPI ID SYNC
    function syncUpiId(val) {
        document.getElementById('upi_id_hidden').value = val.trim();
    }

    // 6. STRIPE-STYLE CARD FORMATTER
    const cardInput = document.getElementById('cardNumber');
    if (cardInput) {
        cardInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            e.target.value = formatted;
        });
    }

    // 7. EXPIRY DATE AUTO-FORMATTER
    const expiryInput = document.getElementById('cardExpiry');
    if (expiryInput) {
        expiryInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                // Prevent invalid months (00 or >12)
                let month = parseInt(value.substring(0, 2));
                if(month === 0) value = '01' + value.substring(2);
                else if(month > 12) value = '12' + value.substring(2);
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }

    // 8. SMART PAY BUTTON — Per-method validation
    function simulateBankProcessing() {
        const btn = document.getElementById('paySubmitBtn');
        if(btn.disabled) return;
        
        const method = document.getElementById('selected_method').value;

        // === CARD VALIDATION ===
        if (method === 'Credit Card') {
            const cardNum = document.getElementById('cardNumber').value.replace(/\s/g,'');
            const expiry  = document.getElementById('cardExpiry').value;
            const cvv = document.getElementById('cardCvv').value;
            
            if (cardNum.length < 16) {
                showToast('Please enter a valid 16-digit card number.', 'warning');
                document.getElementById('cardNumber').focus();
                return;
            }
            if (expiry.length < 5) {
                showToast('Please enter a valid expiry date (MM/YY).', 'warning');
                document.getElementById('cardExpiry').focus();
                return;
            }
            if (cvv.length < 3) {
                showToast('Please enter a valid CVV.', 'warning');
                document.getElementById('cardCvv').focus();
                return;
            }
        }

        // === UPI VALIDATION ===
        if (method === 'UPI') {
            const upiId    = document.getElementById('upiIdInput').value.trim();
            const upiRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z]{2,}$/;
            if (upiId !== '' && !upiRegex.test(upiId)) {
                showToast('Invalid UPI ID format. E.g. name@ybl', 'warning');
                document.getElementById('upiIdInput').focus();
                return;
            }
        }

        // === NET BANKING VALIDATION ===
        if (method === 'Net Banking') {
            const bankSel = document.querySelector('#gateway-netbanking select');
            if (!bankSel.value) {
                showToast('Please select your bank to continue.', 'warning');
                bankSel.focus();
                return;
            }
        }

        // === SHOW PROCESSING MODAL ===
        btn.disabled = true;
        btn.innerHTML = 'Processing... <i class="fas fa-spinner fa-spin ms-2"></i>';
        
        const steps = {
            'Credit Card': ['Establishing encrypted tunnel...', 'Verifying card with issuing bank...', 'Locking payment block...'],
            'UPI': ['Initiating UPI deep-link...', 'Awaiting PhonePe authorization...', 'Payment confirmed — logging receipt...'],
            'Net Banking': ['Connecting to bank portal...', 'Awaiting 2-factor OTP verification...', 'Transaction locked and confirmed...']
        };

        const chosen = steps[method] || steps['Credit Card'];
        document.getElementById('step1').innerText = chosen[0];
        document.getElementById('step2').innerText = chosen[1];
        document.getElementById('step3').innerText = chosen[2];
        document.getElementById('modalTitle').innerText = method === 'UPI' ? 'Processing UPI Payment' : 'Processing Transaction';

        ['step1','step2','step3'].forEach(id => {
            document.getElementById(id).classList.remove('active','done');
        });
        document.getElementById('step1').classList.add('active');
        document.getElementById('processSpinner').classList.remove('d-none');
        document.getElementById('processSuccess').classList.add('d-none');

        const processModal = new bootstrap.Modal(document.getElementById('processingModal'));
        processModal.show();

        setTimeout(() => {
            document.getElementById('step1').classList.replace('active','done');
            document.getElementById('step2').classList.add('active');
        }, 1200);

        setTimeout(() => {
            document.getElementById('step2').classList.replace('active','done');
            document.getElementById('step3').classList.add('active');
        }, 2400);

        setTimeout(() => {
            document.getElementById('step3').classList.replace('active','done');
            document.getElementById('processSpinner').classList.add('d-none');
            document.getElementById('processSuccess').classList.remove('d-none');
            
            setTimeout(() => {
                document.getElementById('paymentForm').submit();
            }, 800);
        }, 3600);
    }
</script>

<?php include '../includes/footer.php'; ?>