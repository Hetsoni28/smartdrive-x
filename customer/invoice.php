<?php
session_start();

// 🛡️ SECURITY: Customer Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

// ⚡ Robust protocol handling for redirects
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = (int)$_SESSION['user_id'];

// ==========================================
// 📊 THE MEGA QUERY: Secured with Prepared Statements
// ==========================================
// Added LEFT JOIN for payments so the invoice doesn't crash if payment history is pending/missing.
$stmt = $conn->prepare("
    SELECT 
        i.invoice_number, i.gst_amount, i.total_with_tax, i.created_at as invoice_date,
        b.start_date, b.end_date, b.total_days, b.booking_status, b.final_price,
        b.extra_charges, b.gst_on_extra, b.final_settlement, b.return_time,
        c.brand, c.name as car_name, c.base_price, c.image,
        l.city_name,
        u.name as customer_name, u.email, u.phone,
        p.payment_method, p.payment_status, p.amount, p.created_at as payment_date
    FROM invoices i
    JOIN bookings b ON i.booking_id = b.id
    JOIN cars c ON b.car_id = c.id
    JOIN locations l ON c.location_id = l.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE i.booking_id = ? AND b.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// 🛡️ Error Boundary: Kick out gracefully if query fails or invoice missing
if ($result->num_rows === 0) {
    $_SESSION['cust_msg'] = "Error: Invoice not found or access denied.";
    $_SESSION['cust_msg_type'] = "danger";
    header("Location: " . $base_url . "customer/dashboard.php");
    exit();
}

$invoice = $result->fetch_assoc();
$stmt->close();

// 🧮 Calculate exact financials
$subtotal = max(0, $invoice['total_with_tax'] - $invoice['gst_amount']);
$points_earned = floor($invoice['total_with_tax'] / 100);

// Fix payment status logically
$payment_status = $invoice['payment_status'] ?? 'PENDING';

// 🌐 Fallbacks for missing database dates (Prevents PHP NULL Errors)
$invoice_date_formatted = !empty($invoice['invoice_date']) ? date('d M, Y', strtotime($invoice['invoice_date'])) : date('d M, Y');
$payment_date_formatted = !empty($invoice['payment_date']) ? date('d M Y, h:i A', strtotime($invoice['payment_date'])) : 'Processing...';

// 📱 Generate QR Code URL for the Invoice Number
$qr_data = urlencode("SmartDriveX-Verified-Invoice:" . $invoice['invoice_number']);
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=10&data=" . $qr_data;

// 📸 Quick Fallback Image Engine
$db_image = $invoice['image'] ?? '';
$brand = strtolower($invoice['brand']);

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

$page_title = "Invoice #" . htmlspecialchars($invoice['invoice_number']) . " | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🌿 CUSTOMER SAGE GREEN THEME */
    :root {
        --sage-dark: #2b3327;
        --sage-mid: #889c7c;
        --sage-pale: #e0eadb;
        --sage-bg: #f4f5f3;
    }
    body { background-color: var(--sage-bg); }

    /* 🦴 Skeleton Loading Engine */
    .skeleton-box { position: relative; overflow: hidden; background-color: #e2e8f0; color: transparent !important; border-color: transparent !important; user-select: none; pointer-events: none; }
    .skeleton-box * { visibility: hidden; }
    .skeleton-box::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.6) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 100% { transform: translateX(100%); } }

    .btn-sage { background-color: var(--sage-dark); color: white; border: none; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .btn-sage:hover { background-color: #1e241c; color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(43, 51, 39, 0.2); }

    /* Sticky Action Bar */
    .action-bar { position: sticky; top: 20px; z-index: 100; backdrop-filter: blur(10px); background: rgba(255,255,255,0.9); }

    /* 📄 INVOICE PAPER STYLING */
    .invoice-paper {
        background: white; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.08);
        position: relative; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);
    }
    
    .invoice-header {
        background: linear-gradient(to top, rgba(43, 51, 39, 0.95), rgba(43, 51, 39, 0.7)), url('<?php echo $hero_bg; ?>') center/cover;
        padding: 50px 40px; color: white; position: relative;
    }

    .paid-stamp {
        position: absolute; top: 30%; left: 50%; transform: translate(-50%, -50%) rotate(-25deg);
        font-size: 8rem; font-weight: 900; color: rgba(0, 0, 0, 0.03); border: 10px solid rgba(0, 0, 0, 0.03);
        padding: 10px 40px; border-radius: 20px; pointer-events: none; z-index: 0; letter-spacing: 15px;
    }
    .paid-stamp.status-paid { color: rgba(25, 135, 84, 0.06); border-color: rgba(25, 135, 84, 0.06); }
    .paid-stamp.status-failed { color: rgba(220, 53, 69, 0.06); border-color: rgba(220, 53, 69, 0.06); font-size: 6rem; }
    .paid-stamp.status-pending { color: rgba(255, 193, 7, 0.08); border-color: rgba(255, 193, 7, 0.08); font-size: 6rem; }

    .table-invoice { border-collapse: separate; border-spacing: 0; }
    .table-invoice th {
        background-color: var(--sage-pale) !important; color: var(--sage-dark); font-size: 0.8rem;
        text-transform: uppercase; letter-spacing: 1px; border: none; font-weight: 800; padding: 15px;
    }
    .table-invoice td { vertical-align: middle; border-color: rgba(0,0,0,0.05); padding: 20px 15px; }

    /* 🔔 Toast Engine */
    #cust-toast-container { position: fixed; top: 20px; right: 20px; z-index: 999999; }
    .cust-toast {
        background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        display: flex; align-items: center; gap: 15px; min-width: 320px;
        transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    .cust-toast.show { transform: translateX(0); opacity: 1; }
    .cust-toast.success { border-left: 5px solid #198754; }

    /* 🖨️ PRINT-SPECIFIC CSS ENGINE */
    @media print {
        @page { margin: 0; size: A4; }
        body { background-color: white !important; margin: 0; padding: 0; }
        .no-print, .beast-sidebar, .smart-navbar, .top-utility-bar, footer { display: none !important; }
        .dashboard-content { width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .dashboard-layout { display: block; min-height: auto; }
        
        .invoice-paper { 
            box-shadow: none !important; border: none !important; border-radius: 0 !important; 
            width: 100%; max-width: 100%; margin: 0 !important; padding: 0 !important;
        }
        
        /* Force background colors to print */
        .invoice-header { 
            background-color: var(--sage-dark) !important; 
            background-image: none !important; /* Remove bg image to save ink */
            -webkit-print-color-adjust: exact; color-adjust: exact; 
            padding: 30px !important;
        }
        .table-invoice th { background-color: #f4f5f3 !important; -webkit-print-color-adjust: exact; }
        
        .paid-stamp { border-width: 5px !important; }
        .paid-stamp.status-paid { color: rgba(25, 135, 84, 0.1) !important; border-color: rgba(25, 135, 84, 0.1) !important; }
        .paid-stamp.status-failed { color: rgba(220, 53, 69, 0.08) !important; border-color: rgba(220, 53, 69, 0.08) !important; }
        .paid-stamp.status-pending { color: rgba(255, 193, 7, 0.15) !important; border-color: rgba(255, 193, 7, 0.15) !important; }
    }
</style>

<div id="cust-toast-container"></div>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content">
        <div class="container-fluid" style="max-width: 1000px; margin: 0 auto;">
            
            <div class="action-bar d-flex justify-content-between align-items-center mb-4 no-print p-3 rounded-pill shadow-sm border border-light load-anim skeleton-box" data-aos="fade-down">
                <a href="<?php echo $base_url; ?>customer/dashboard.php" class="btn btn-light rounded-pill fw-bold px-4 border"><i class="fas fa-arrow-left me-2"></i>Back</a>
                <div class="d-flex gap-2">
                    <button onclick="emailInvoice()" class="btn btn-outline-dark rounded-pill fw-bold px-4" id="emailBtn">
                        <i class="fas fa-envelope me-2"></i>Email Copy
                    </button>
                    <button onclick="window.print()" class="btn btn-sage rounded-pill fw-bold px-4 shadow-sm">
                        <i class="fas fa-print me-2"></i>Print PDF
                    </button>
                </div>
            </div>

            <div class="invoice-paper load-anim skeleton-box" data-aos="fade-up" data-aos-delay="100">
                
                <?php 
                    $stamp_class = 'status-pending';
                    $stamp_text = 'PENDING';
                    if ($payment_status === 'PAID') {
                        $stamp_class = 'status-paid';
                        $stamp_text = 'PAID';
                    } elseif ($payment_status === 'FAILED') {
                        $stamp_class = 'status-failed';
                        $stamp_text = 'FAILED';
                    }
                ?>
                <div class="paid-stamp <?php echo $stamp_class; ?>"><?php echo $stamp_text; ?></div>
                
                <div class="invoice-header d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="fw-black mb-1"><i class="fas fa-car-side text-warning me-2"></i>SmartDrive X</h2>
                        <p class="text-light opacity-75 mb-0 small fw-bold tracking-widest">PREMIUM FLEET MANAGEMENT</p>
                        <p class="small text-light opacity-75 mt-3 mb-0 fw-bold">GTU Campus, Ahmedabad, Gujarat</p>
                        <p class="small text-light opacity-50 mb-0">GSTIN: 24AAACC1206D1Z1</p>
                    </div>
                    <div class="text-end mt-4 mt-md-0">
                        <h3 class="fw-black text-uppercase mb-1 tracking-wide">Tax Invoice</h3>
                        <h4 style="color: var(--sage-pale);">#<?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></h4>
                        <p class="mb-0 text-light opacity-75 small mt-2 fw-bold">Issue Date: <?php echo $invoice_date_formatted; ?></p>
                    </div>
                </div>

                <div class="p-4 p-md-5 position-relative z-2">
                    
                    <div class="row mb-5 justify-content-between">
                        <div class="col-sm-6">
                            <h6 class="text-muted text-uppercase fw-bold mb-3 tracking-wide" style="color: var(--sage-mid) !important;">Billed To:</h6>
                            <h4 class="fw-black text-dark mb-2"><?php echo htmlspecialchars($invoice['customer_name'] ?? 'Guest'); ?></h4>
                            <p class="text-muted fw-bold mb-1 small"><i class="fas fa-envelope me-2" style="width: 15px;"></i><?php echo htmlspecialchars($invoice['email'] ?? 'N/A'); ?></p>
                            <p class="text-muted fw-bold mb-0 small"><i class="fas fa-phone-alt me-2" style="width: 15px;"></i><?php echo htmlspecialchars($invoice['phone'] ?? 'N/A'); ?></p>
                        </div>
                        
                        <div class="col-sm-4 text-sm-end mt-4 mt-sm-0 d-flex flex-column align-items-sm-end">
                            <h6 class="text-muted text-uppercase fw-bold mb-3 tracking-wide" style="color: var(--sage-mid) !important;">Scan to Verify:</h6>
                            <img src="<?php echo $qr_url; ?>" alt="Verification QR" class="border p-2 rounded-4 shadow-sm bg-white" style="width: 100px; height: 100px;">
                        </div>
                    </div>

                    <div class="bg-light p-4 rounded-4 border mb-5">
                        <div class="row text-center g-3">
                            <div class="col-md-3 col-6 border-end">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Vehicle</small>
                                <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars(($invoice['brand'] ?? '') . ' ' . ($invoice['car_name'] ?? '')); ?></span>
                            </div>
                            <div class="col-md-3 col-6 border-end">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Location</small>
                                <span class="fw-bold text-dark fs-6"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($invoice['city_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="col-md-3 col-6 border-end">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Pickup</small>
                                <span class="fw-bold text-dark fs-6"><?php echo isset($invoice['start_date']) ? date('d M Y', strtotime($invoice['start_date'])) : 'N/A'; ?></span>
                            </div>
                            <div class="col-md-3 col-6">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Return</small>
                                <span class="fw-bold text-dark fs-6"><?php echo isset($invoice['end_date']) ? date('d M Y', strtotime($invoice['end_date'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-5 border rounded-4 overflow-hidden">
                        <table class="table table-invoice mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">Description</th>
                                    <th class="py-3 text-center">Duration</th>
                                    <th class="py-3 text-end pe-4">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4 py-4">
                                        <h6 class="fw-bold mb-1 text-dark">Comprehensive Vehicle Rental</h6>
                                        <small class="text-muted fw-bold">Includes base fare of ₹<?php echo number_format($invoice['base_price'] ?? 0, 2); ?>/day, applicable weekend surges, and any applied promo codes.</small>
                                    </td>
                                    <td class="py-4 text-center align-middle fw-bold text-dark fs-6"><?php echo (int)($invoice['total_days'] ?? 0); ?> Days</td>
                                    <td class="py-4 text-end align-middle fw-black text-dark pe-4 fs-6">₹<?php echo number_format($subtotal, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-lg-6 mb-4 mb-lg-0">
                            <h6 class="text-muted text-uppercase fw-bold mb-3 tracking-wide" style="color: var(--sage-mid) !important;">Payment Log</h6>
                            <div class="border rounded-4 p-4 bg-light shadow-sm">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted fw-bold small">Method:</span>
                                    <span class="fw-bold text-dark small"><i class="fas fa-credit-card me-1"></i> <?php echo htmlspecialchars($invoice['payment_method'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted fw-bold small">Transaction Date:</span>
                                    <span class="fw-bold text-dark small"><?php echo $payment_date_formatted; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted fw-bold small">Status:</span>
                                    <?php if($payment_status === 'PAID'): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-1"><i class="fas fa-check me-1"></i> SUCCESS</span>
                                    <?php elseif($payment_status === 'FAILED'): ?>
                                        <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fas fa-times me-1"></i> FAILED</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1"><i class="fas fa-clock me-1"></i> PENDING</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between mt-3 pt-3 border-top">
                                    <span class="text-muted fw-bold small"><i class="fas fa-star text-warning me-1"></i> Points Earned:</span>
                                    <span class="fw-black text-success small">+<?php echo $points_earned; ?> Pts</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 ms-auto">
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span class="fw-bold">Subtotal (Excl. Tax)</span>
                                <span class="fw-bold text-dark fs-6">₹<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span class="fw-bold">GST (18%)</span>
                                <span class="fw-bold text-dark fs-6">₹<?php echo number_format($invoice['gst_amount'] ?? 0, 2); ?></span>
                            </div>
                            <hr class="border-secondary opacity-25 my-3">
                            <div class="d-flex justify-content-between align-items-center p-4 rounded-4 shadow-sm" style="background-color: var(--sage-dark); color: white;">
                                <h5 class="fw-bold mb-0">Total Paid</h5>
                                <h3 class="fw-black mb-0">₹<?php echo number_format($invoice['total_with_tax'] ?? 0, 2); ?></h3>
                            </div>
                        </div>
                    </div>

                    <?php 
                        $has_late_charges = ($invoice['booking_status'] === 'completed' && floatval($invoice['extra_charges'] ?? 0) > 0);
                        $is_completed = ($invoice['booking_status'] === 'completed');
                    ?>
                    <?php if ($is_completed): ?>
                    <div class="mt-5 p-4 rounded-4 border shadow-sm text-center no-print" style="background: linear-gradient(135deg, rgba(77,168,156,0.05), rgba(136,156,124,0.05));">
                        <i class="fas fa-flag-checkered fa-2x mb-3" style="color: var(--sage-mid);"></i>
                        <h5 class="fw-bold text-dark mb-2">Ride Completed</h5>
                        <?php if ($has_late_charges): ?>
                            <p class="text-muted small mb-3 fw-bold">Late return charges of ₹<?php echo number_format($invoice['extra_charges'] + $invoice['gst_on_extra'], 2); ?> were applied. View the full settlement below.</p>
                        <?php else: ?>
                            <p class="text-muted small mb-3 fw-bold">No late return charges. View the final settlement confirmation below.</p>
                        <?php endif; ?>
                        <a href="<?php echo $base_url; ?>customer/final_invoice.php?id=<?php echo $booking_id; ?>" class="btn btn-sage rounded-pill fw-bold px-5 py-2 shadow-sm">
                            <i class="fas fa-receipt me-2"></i>View Final Settlement
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="text-center mt-5 pt-4 border-top border-2 border-light">
                        <p class="text-muted fw-bold mb-1">Thank you for driving with SmartDrive X!</p>
                        <p class="text-muted small mb-0 opacity-75 fw-bold">This is a computer-generated document. No physical signature is required.</p>
                    </div>

                </div>
            </div>
            
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
        }, 300); // 300ms buffer prevents layout jank
    });

    // 2. INITIALIZE AOS
    if(typeof AOS !== 'undefined') {
        AOS.init({ once: true, offset: 50, duration: 800, easing: 'ease-out-cubic' });
    }

    // 3. TOAST NOTIFICATION ENGINE (For Email Action)
    function showToast(msg, type) {
        const toastContainer = document.getElementById('cust-toast-container');
        let icon = 'fa-check-circle text-success';
        
        const toast = document.createElement('div');
        toast.className = `cust-toast ${type}`;
        toast.innerHTML = `<i class="fas ${icon} fs-4"></i><div><h6 class="fw-bold mb-0 text-dark">System Action</h6><small class="text-muted fw-bold">${msg}</small></div>`;
        
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 4000);
    }

    // 4. ASYNCHRONOUS EMAIL SIMULATION
    function emailInvoice() {
        const btn = document.getElementById('emailBtn');
        if(btn.classList.contains('disabled')) return;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        btn.classList.add('disabled');
        
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-check-circle me-2 text-success"></i>Sent to Email';
            showToast('A digital copy of this invoice has been sent to your registered email address.', 'success');
            
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-envelope me-2"></i>Email Copy';
                btn.classList.remove('disabled');
            }, 3000);
        }, 1500);
    }
</script>

<?php include '../includes/footer.php'; ?>