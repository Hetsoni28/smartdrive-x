<?php
session_start();

// 🛡️ SECURITY: Customer Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

// ⚡ Robust protocol handling for redirects
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = (int)$_SESSION['user_id'];

// ==========================================
// 📊 THE MEGA QUERY: Complete Booking + Settlement Data
// ==========================================
$stmt = $conn->prepare("
    SELECT 
        b.id, b.start_date, b.end_date, b.total_days, b.booking_status, b.final_price,
        b.start_time, b.end_time, b.return_time, b.late_hours, 
        b.extra_charges, b.gst_on_extra, b.final_settlement, b.payment_status,
        c.brand, c.name as car_name, c.model, c.base_price, c.image,
        l.city_name,
        u.name as customer_name, u.email, u.phone,
        i.invoice_number, i.gst_amount, i.total_with_tax
    FROM bookings b
    JOIN cars c ON b.car_id = c.id
    JOIN locations l ON c.location_id = l.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN invoices i ON i.booking_id = b.id
    WHERE b.id = ? AND b.user_id = ? AND b.booking_status = 'completed'
    LIMIT 1
");
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// 🛡️ Error Boundary
if ($result->num_rows === 0) {
    $_SESSION['cust_msg'] = "Error: Final invoice not found, booking not completed, or access denied.";
    $_SESSION['cust_msg_type'] = "danger";
    header("Location: " . $base_url . "customer/dashboard.php");
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// ==========================================
// 🧮 FINANCIAL CALCULATIONS
// ==========================================
$rental_cost = floatval($booking['total_with_tax'] ?? $booking['final_price']);
$rental_subtotal = $rental_cost - floatval($booking['gst_amount'] ?? 0);
$rental_gst = floatval($booking['gst_amount'] ?? 0);

$extra_charges = floatval($booking['extra_charges'] ?? 0);
$gst_on_extra = floatval($booking['gst_on_extra'] ?? 0);
$total_penalty = $extra_charges + $gst_on_extra;
$has_late_charges = ($extra_charges > 0);

$final_settlement = floatval($booking['final_settlement'] ?? ($rental_cost + $total_penalty));
$late_hours = intval($booking['late_hours'] ?? 0);

// Grace period and rate info from system settings
$grace_period = intval(get_system_setting($conn, 'grace_period_minutes', '60'));
$late_hourly_rate = floatval(get_system_setting($conn, 'late_hourly_rate', '300'));
$gst_percentage = floatval(get_system_setting($conn, 'gst_percentage', '18'));

// Late return ledger detail (if any)
$late_detail = null;
if ($has_late_charges) {
    $lr_stmt = $conn->prepare("SELECT * FROM late_returns WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
    $lr_stmt->bind_param("i", $booking_id);
    $lr_stmt->execute();
    $lr_result = $lr_stmt->get_result();
    $late_detail = $lr_result->fetch_assoc();
    $lr_stmt->close();
}

// Due DateTime calculation
$due_date = $booking['end_date'];
$due_time = $booking['end_time'] ?? '10:00:00';
$due_datetime_str = $due_date . ' ' . $due_time;

$return_datetime_str = $booking['return_time'] ?? $due_datetime_str;

// 📱 QR Code
$qr_data = urlencode("SmartDriveX-Settlement:" . $booking_id . "-" . ($booking['invoice_number'] ?? 'N/A'));
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&margin=10&data=" . $qr_data;

// 📸 Car Image Fallback
$db_image = $booking['image'] ?? '';
$brand = strtolower($booking['brand']);
function resolveSettlementCarImage($db_img, $brand, $base) {
    if (!empty($db_img)) {
        $decoded = json_decode($db_img, true);
        if (is_array($decoded) && isset($decoded[0]) && file_exists('../' . $decoded[0])) {
            return $base . $decoded[0];
        } elseif (file_exists('../' . $db_img)) {
            return $base . $db_img;
        }
    }
    $fallbacks = [
        'porsche' => 'https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=2070',
        'bmw' => 'https://images.unsplash.com/photo-1556189250-72ba954cfc2b?q=80&w=2070',
        'audi' => 'https://images.unsplash.com/photo-1606152421802-db97b9c7a11b?q=80&w=2070',
        'mercedes' => 'https://images.unsplash.com/photo-1610880846497-7257b23f6128?q=80&w=2070'
    ];
    return $fallbacks[$brand] ?? 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&w=2070';
}
$hero_bg = resolveSettlementCarImage($db_image, $brand, $base_url);

$page_title = "Final Settlement #BKG-" . $booking_id . " | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🌿 CUSTOMER SAGE GREEN THEME — FINAL SETTLEMENT */
    :root {
        --sage-dark: #2b3327;
        --sage-mid: #889c7c;
        --sage-pale: #e0eadb;
        --sage-bg: #f4f5f3;
        --sage-deep: #4a5c43;
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

    /* 📄 SETTLEMENT PAPER STYLING */
    .settlement-paper {
        background: white; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.08);
        position: relative; overflow: hidden; border: 1px solid rgba(0,0,0,0.05);
    }
    
    .settlement-header {
        background: linear-gradient(to top, rgba(43, 51, 39, 0.95), rgba(43, 51, 39, 0.7)), url('<?php echo $hero_bg; ?>') center/cover;
        padding: 50px 40px; color: white; position: relative;
    }

    /* Completed Stamp */
    .completed-stamp {
        position: absolute; top: 30%; left: 50%; transform: translate(-50%, -50%) rotate(-25deg);
        font-size: 6rem; font-weight: 900; color: rgba(25, 135, 84, 0.06); border: 10px solid rgba(25, 135, 84, 0.06);
        padding: 10px 40px; border-radius: 20px; pointer-events: none; z-index: 0; letter-spacing: 10px;
    }

    /* Late Return Warning Box */
    .late-return-box {
        background: linear-gradient(135deg, #fff5f5, #fff0f0);
        border: 2px solid rgba(220, 53, 69, 0.15);
        border-radius: 20px;
        position: relative;
        overflow: hidden;
    }
    .late-return-box::before {
        content: '\f017';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        right: -20px;
        bottom: -30px;
        font-size: 10rem;
        color: rgba(220, 53, 69, 0.03);
        pointer-events: none;
    }

    /* No Late Return - Clean Box */
    .ontime-box {
        background: linear-gradient(135deg, #f0fff4, #e8f5e9);
        border: 2px solid rgba(25, 135, 84, 0.15);
        border-radius: 20px;
    }

    .table-settlement { border-collapse: separate; border-spacing: 0; }
    .table-settlement th {
        background-color: var(--sage-pale) !important; color: var(--sage-dark); font-size: 0.8rem;
        text-transform: uppercase; letter-spacing: 1px; border: none; font-weight: 800; padding: 15px;
    }
    .table-settlement td { vertical-align: middle; border-color: rgba(0,0,0,0.05); padding: 16px 15px; }

    /* Summary Cards */
    .summary-card {
        background: white; border-radius: 16px; padding: 20px; border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: transform 0.3s ease;
    }
    .summary-card:hover { transform: translateY(-4px); }

    /* 🖨️ PRINT CSS */
    @media print {
        @page { margin: 0; size: A4; }
        body { background-color: white !important; margin: 0; padding: 0; }
        .no-print, .beast-sidebar, .smart-navbar, .top-utility-bar, footer { display: none !important; }
        .dashboard-content { width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .dashboard-layout { display: block; min-height: auto; }
        .settlement-paper { box-shadow: none !important; border: none !important; border-radius: 0 !important; width: 100%; max-width: 100%; margin: 0 !important; }
        .settlement-header { background-color: var(--sage-dark) !important; background-image: none !important; -webkit-print-color-adjust: exact; color-adjust: exact; padding: 30px !important; }
        .table-settlement th { background-color: #f4f5f3 !important; -webkit-print-color-adjust: exact; }
        .late-return-box, .ontime-box { -webkit-print-color-adjust: exact; color-adjust: exact; }
        .completed-stamp { color: rgba(25, 135, 84, 0.1) !important; border-color: rgba(25, 135, 84, 0.1) !important; }
    }
</style>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content">
        <div class="container-fluid" style="max-width: 1000px; margin: 0 auto;">
            
            <!-- Action Bar -->
            <div class="action-bar d-flex justify-content-between align-items-center mb-4 no-print p-3 rounded-pill shadow-sm border border-light load-anim skeleton-box" data-aos="fade-down">
                <a href="<?php echo $base_url; ?>customer/dashboard.php" class="btn btn-light rounded-pill fw-bold px-4 border"><i class="fas fa-arrow-left me-2"></i>Back</a>
                <div class="d-flex gap-2">
                    <a href="<?php echo $base_url; ?>customer/invoice.php?id=<?php echo $booking_id; ?>" class="btn btn-outline-dark rounded-pill fw-bold px-4">
                        <i class="fas fa-file-invoice me-2"></i>Original Invoice
                    </a>
                    <button onclick="window.print()" class="btn btn-sage rounded-pill fw-bold px-4 shadow-sm">
                        <i class="fas fa-print me-2"></i>Print PDF
                    </button>
                </div>
            </div>

            <!-- Settlement Paper -->
            <div class="settlement-paper load-anim skeleton-box" data-aos="fade-up" data-aos-delay="100">
                
                <div class="completed-stamp">SETTLED</div>
                
                <!-- Header -->
                <div class="settlement-header d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="fw-black mb-1"><i class="fas fa-car-side text-warning me-2"></i>SmartDrive X</h2>
                        <p class="text-light opacity-75 mb-0 small fw-bold tracking-widest">PREMIUM FLEET MANAGEMENT</p>
                        <p class="small text-light opacity-75 mt-3 mb-0 fw-bold">GTU Campus, Ahmedabad, Gujarat</p>
                        <p class="small text-light opacity-50 mb-0">GSTIN: 24AAACC1206D1Z1</p>
                    </div>
                    <div class="text-end mt-4 mt-md-0">
                        <h3 class="fw-black text-uppercase mb-1 tracking-wide">Final Settlement</h3>
                        <h4 style="color: var(--sage-pale);">#BKG-<?php echo $booking_id; ?></h4>
                        <?php if (!empty($booking['invoice_number'])): ?>
                            <p class="mb-0 text-light opacity-75 small mt-1 fw-bold">Invoice: <?php echo htmlspecialchars($booking['invoice_number']); ?></p>
                        <?php endif; ?>
                        <p class="mb-0 text-light opacity-75 small mt-1 fw-bold">Settlement Date: <?php echo date('d M, Y'); ?></p>
                    </div>
                </div>

                <!-- Body -->
                <div class="p-4 p-md-5 position-relative z-2">
                    
                    <!-- Customer + QR -->
                    <div class="row mb-5 justify-content-between">
                        <div class="col-sm-6">
                            <h6 class="text-muted text-uppercase fw-bold mb-3 tracking-wide" style="color: var(--sage-mid) !important;">Billed To:</h6>
                            <h4 class="fw-black text-dark mb-2"><?php echo htmlspecialchars($booking['customer_name'] ?? 'Guest'); ?></h4>
                            <p class="text-muted fw-bold mb-1 small"><i class="fas fa-envelope me-2" style="width: 15px;"></i><?php echo htmlspecialchars($booking['email'] ?? 'N/A'); ?></p>
                            <p class="text-muted fw-bold mb-0 small"><i class="fas fa-phone-alt me-2" style="width: 15px;"></i><?php echo htmlspecialchars($booking['phone'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-sm-4 text-sm-end mt-4 mt-sm-0 d-flex flex-column align-items-sm-end">
                            <h6 class="text-muted text-uppercase fw-bold mb-3 tracking-wide" style="color: var(--sage-mid) !important;">Scan to Verify:</h6>
                            <img src="<?php echo $qr_url; ?>" alt="Verification QR" class="border p-2 rounded-4 shadow-sm bg-white" style="width: 100px; height: 100px;">
                        </div>
                    </div>

                    <!-- Vehicle + Trip Summary -->
                    <div class="bg-light p-4 rounded-4 border mb-5">
                        <div class="row text-center g-3">
                            <div class="col-md-3 col-6 border-end">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Vehicle</small>
                                <span class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['car_name']); ?></span>
                            </div>
                            <div class="col-md-3 col-6 border-end">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Location</small>
                                <span class="fw-bold text-dark fs-6"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($booking['city_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="col-md-3 col-6 border-end">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Pickup</small>
                                <span class="fw-bold text-dark fs-6"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></span>
                                <small class="d-block text-muted fw-bold"><?php echo date('h:i A', strtotime($booking['start_time'] ?? '10:00:00')); ?></small>
                            </div>
                            <div class="col-md-3 col-6">
                                <small class="text-muted text-uppercase fw-bold d-block mb-1 tracking-wide">Scheduled Return</small>
                                <span class="fw-bold text-dark fs-6"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></span>
                                <small class="d-block text-muted fw-bold"><?php echo date('h:i A', strtotime($booking['end_time'] ?? '10:00:00')); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 1: Original Rental Cost -->
                    <h6 class="text-uppercase fw-bold mb-3 tracking-wide" style="color: var(--sage-mid);"><i class="fas fa-car me-2"></i>Original Rental Charges</h6>
                    <div class="table-responsive mb-5 border rounded-4 overflow-hidden">
                        <table class="table table-settlement mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">Description</th>
                                    <th class="py-3 text-center">Duration</th>
                                    <th class="py-3 text-center">Rate/Day</th>
                                    <th class="py-3 text-end pe-4">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4 py-3">
                                        <h6 class="fw-bold mb-1 text-dark">Vehicle Rental</h6>
                                        <small class="text-muted fw-bold"><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['car_name'] . ' ' . $booking['model']); ?></small>
                                    </td>
                                    <td class="py-3 text-center align-middle fw-bold text-dark"><?php echo $booking['total_days']; ?> Days</td>
                                    <td class="py-3 text-center align-middle fw-bold text-muted">₹<?php echo number_format($booking['base_price'], 2); ?></td>
                                    <td class="py-3 text-end align-middle fw-black text-dark pe-4">₹<?php echo number_format($rental_subtotal, 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-4 py-3" colspan="3">
                                        <span class="text-muted fw-bold">GST (18%)</span>
                                    </td>
                                    <td class="py-3 text-end fw-bold text-muted pe-4">₹<?php echo number_format($rental_gst, 2); ?></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr style="background-color: rgba(136,156,124,0.08);">
                                    <td class="ps-4 py-3" colspan="3"><strong class="text-dark">Rental Total (Incl. Tax)</strong></td>
                                    <td class="py-3 text-end pe-4"><strong class="text-dark fs-5">₹<?php echo number_format($rental_cost, 2); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- SECTION 2: Late Return Breakdown -->
                    <?php if ($has_late_charges): ?>
                        <h6 class="text-uppercase fw-bold mb-3 tracking-wide text-danger"><i class="fas fa-clock me-2"></i>Late Return Penalty</h6>
                        <div class="late-return-box p-4 mb-5">
                            <div class="row g-4 mb-4">
                                <div class="col-md-3 col-6">
                                    <div class="summary-card text-center">
                                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: .7rem;">Due Date & Time</small>
                                        <span class="fw-black text-dark d-block"><?php echo date('d M Y', strtotime($due_datetime_str)); ?></span>
                                        <small class="fw-bold text-muted"><?php echo date('h:i A', strtotime($due_datetime_str)); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="summary-card text-center">
                                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: .7rem;">Actual Return</small>
                                        <span class="fw-black text-danger d-block"><?php echo date('d M Y', strtotime($return_datetime_str)); ?></span>
                                        <small class="fw-bold text-danger"><?php echo date('h:i A', strtotime($return_datetime_str)); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="summary-card text-center">
                                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: .7rem;">Late Duration</small>
                                        <span class="fw-black text-danger d-block fs-5"><?php echo $late_detail ? $late_detail['late_minutes'] : ($late_hours * 60); ?> min</span>
                                        <small class="fw-bold text-muted">(<?php echo $late_hours; ?> chargeable hrs)</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="summary-card text-center">
                                        <small class="text-muted text-uppercase fw-bold d-block mb-2" style="font-size: .7rem;">Grace Period</small>
                                        <span class="fw-black text-success d-block fs-5"><?php echo $grace_period; ?> min</span>
                                        <small class="fw-bold text-muted">No charge within grace</small>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive border rounded-4 overflow-hidden bg-white">
                                <table class="table table-settlement mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4 py-3">Charge Description</th>
                                            <th class="py-3 text-center">Qty / Rate</th>
                                            <th class="py-3 text-end pe-4">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <h6 class="fw-bold mb-1 text-dark">Late Return Fee</h6>
                                                <small class="text-muted fw-bold"><?php echo $late_hours; ?> chargeable hour(s) × ₹<?php echo number_format($late_hourly_rate, 2); ?>/hr</small>
                                            </td>
                                            <td class="py-3 text-center align-middle fw-bold text-dark"><?php echo $late_hours; ?> × ₹<?php echo number_format($late_hourly_rate, 0); ?></td>
                                            <td class="py-3 text-end align-middle fw-black text-danger pe-4">₹<?php echo number_format($extra_charges, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="ps-4 py-3" colspan="2">
                                                <span class="text-muted fw-bold">GST on Late Charges (<?php echo number_format($gst_percentage, 0); ?>%)</span>
                                            </td>
                                            <td class="py-3 text-end fw-bold text-muted pe-4">₹<?php echo number_format($gst_on_extra, 2); ?></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background-color: rgba(220, 53, 69, 0.05);">
                                            <td class="ps-4 py-3" colspan="2"><strong class="text-danger">Total Late Penalty</strong></td>
                                            <td class="py-3 text-end pe-4"><strong class="text-danger fs-5">₹<?php echo number_format($total_penalty, 2); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <?php if ($late_detail && !empty($late_detail['admin_notes'])): ?>
                                <div class="mt-3 p-3 bg-white rounded-3 border">
                                    <small class="text-muted fw-bold"><i class="fas fa-sticky-note me-1"></i>Admin Notes: <?php echo htmlspecialchars($late_detail['admin_notes']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <!-- ON TIME RETURN -->
                        <h6 class="text-uppercase fw-bold mb-3 tracking-wide text-success"><i class="fas fa-check-circle me-2"></i>Return Status</h6>
                        <div class="ontime-box p-4 mb-5 text-center">
                            <i class="fas fa-check-double fa-3x mb-3 text-success opacity-50"></i>
                            <h4 class="fw-black text-success mb-2">On-Time Return — No Late Charges</h4>
                            <p class="text-muted fw-bold mb-0">The vehicle was returned within the scheduled timeframe. No penalty charges apply.</p>
                        </div>
                    <?php endif; ?>

                    <!-- SECTION 3: FINAL SETTLEMENT SUMMARY -->
                    <div class="row">
                        <div class="col-lg-5 ms-auto">
                            <h6 class="text-uppercase fw-bold mb-3 tracking-wide" style="color: var(--sage-mid);"><i class="fas fa-receipt me-2"></i>Settlement Summary</h6>
                            
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span class="fw-bold">Original Rental (Incl. Tax)</span>
                                <span class="fw-bold text-dark fs-6">₹<?php echo number_format($rental_cost, 2); ?></span>
                            </div>

                            <?php if ($has_late_charges): ?>
                            <div class="d-flex justify-content-between mb-3 text-muted">
                                <span class="fw-bold">Late Return Penalty</span>
                                <span class="fw-bold text-danger fs-6">+ ₹<?php echo number_format($total_penalty, 2); ?></span>
                            </div>
                            <?php endif; ?>

                            <hr class="border-secondary opacity-25 my-3">

                            <div class="d-flex justify-content-between align-items-center p-4 rounded-4 shadow-sm" style="background-color: var(--sage-dark); color: white;">
                                <h5 class="fw-bold mb-0">Final Settlement</h5>
                                <h3 class="fw-black mb-0">₹<?php echo number_format($final_settlement, 2); ?></h3>
                            </div>

                            <div class="text-center mt-3">
                                <span class="badge bg-success rounded-pill px-4 py-2 fw-bold shadow-sm"><i class="fas fa-check-circle me-1"></i>SETTLEMENT COMPLETE</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
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
        }, 300);
    });

    // 2. INITIALIZE AOS
    if(typeof AOS !== 'undefined') {
        AOS.init({ once: true, offset: 50, duration: 800, easing: 'ease-out-cubic' });
    }
</script>

<?php include '../includes/footer.php'; ?>
