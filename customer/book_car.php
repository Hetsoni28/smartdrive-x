<?php
session_start();

// 🛡️ SECURITY: Customer Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php'; // 🧠 Brings in our Enterprise Math Engine

// ⚡ Define $base_url early for redirects
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

$error = '';
$success = '';

// Get and Sanitize the Car ID
$car_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$start_date = isset($_GET['start']) ? trim($_GET['start']) : '';
$end_date = isset($_GET['end']) ? trim($_GET['end']) : '';

// 🛡️ SECURE FETCH: Use Prepared Statements to fetch Car Details
$car_stmt = $conn->prepare("
    SELECT c.*, l.city_name 
    FROM cars c 
    LEFT JOIN locations l ON c.location_id = l.id 
    WHERE c.id = ?
");
$car_stmt->bind_param("i", $car_id);
$car_stmt->execute();
$car_result = $car_stmt->get_result();

if ($car_result->num_rows == 0) {
    header("Location: ../customer/search_cars.php");
    exit();
}
$car = $car_result->fetch_assoc();
$car_stmt->close();

// ==========================================
// 🎟️ FETCH ACTIVE COUPONS FOR JAVASCRIPT
// ==========================================
$coupons_query = mysqli_query($conn, "SELECT code, discount_type, discount_value FROM coupons WHERE expiry_date >= CURDATE()");
$active_coupons = [];
while($c = mysqli_fetch_assoc($coupons_query)) {
    $active_coupons[strtoupper($c['code'])] = [
        'type' => $c['discount_type'],
        'value' => (float)$c['discount_value']
    ];
}
$coupons_json = json_encode($active_coupons);

// ==========================================
// 💾 SECURE BACKEND BOOKING PROCESS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_booking'])) {
    $user_id = $_SESSION['user_id'];
    $post_start = trim($_POST['start_date']);
    $post_end = trim($_POST['end_date']);
    $coupon_code = strtoupper(trim($_POST['coupon_code']));
    
    // NEW LOGIC: Upsell Add-ons
    $addon_insurance = isset($_POST['addon_insurance']) ? 500 : 0; // ₹500/day
    $addon_gps = isset($_POST['addon_gps']) ? 150 : 0; // ₹150/day
    $daily_addon_cost = $addon_insurance + $addon_gps;
    
    // 🛑 SECURITY: Backend Date Validation (Prevent Hackers from bypassing HTML)
    $today = strtotime(date('Y-m-d'));
    if (strtotime($post_start) < $today) {
        $error = "Error: Pickup date cannot be in the past.";
    } elseif (strtotime($post_end) < strtotime($post_start)) {
        $error = "Error: Return date must be on or after the pickup date.";
    }

    // 1. Validate Coupon against the database securely
    $discount_val = 0;
    $discount_type = 'none';
    
    if (!empty($coupon_code) && empty($error)) {
        $check_coupon = $conn->prepare("SELECT discount_type, discount_value FROM coupons WHERE code = ? AND expiry_date >= CURDATE()");
        $check_coupon->bind_param("s", $coupon_code);
        $check_coupon->execute();
        $coupon_res = $check_coupon->get_result();
        
        if ($coupon_res->num_rows > 0) {
            $coupon_data = $coupon_res->fetch_assoc();
            $discount_type = $coupon_data['discount_type'];
            $discount_val = $coupon_data['discount_value'];
        } else {
            $error = "Invalid or expired coupon code.";
        }
        $check_coupon->close();
    }
    
    if (empty($error)) {
        // 2. Use our Centralized Math Engine
        $quote = generate_price_quote($car['base_price'], $post_start, $post_end, $discount_val, $discount_type);
        $total_days = $quote['total_days'];
        
        // Add Add-ons to the final math
        $total_addon_price = $daily_addon_cost * $total_days;
        $addon_gst = $total_addon_price * 0.18;
        $final_price = $quote['final_total'] + $total_addon_price + $addon_gst;
        
        // 3. Double check availability to prevent Double Bookings (Race Conditions)
        $check_avail = $conn->prepare("
            SELECT id FROM bookings 
            WHERE car_id = ? 
            AND booking_status IN ('confirmed', 'pending', 'approved', 'active') 
            AND (start_date <= ? AND end_date >= ?)
        ");
        $check_avail->bind_param("iss", $car_id, $post_end, $post_start);
        $check_avail->execute();
        $avail_res = $check_avail->get_result();
        
        if ($avail_res->num_rows > 0) {
            $error = "Sorry, this vehicle was just booked by someone else for those dates. Please select different dates.";
        } else {
            // 4. Save Booking Securely
            $insert_query = $conn->prepare("
                INSERT INTO bookings (user_id, car_id, start_date, end_date, total_days, final_price, booking_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $insert_query->bind_param("iissid", $user_id, $car_id, $post_start, $post_end, $total_days, $final_price);
            
            if ($insert_query->execute()) {
                $booking_id = $insert_query->insert_id;
                
                // 🔔 Fire live notifications (Fixing the undefined variable crash!)
                if (file_exists('../includes/notify.php')) {
                    include_once '../includes/notify.php';
                    if (function_exists('notify_booking_created')) {
                        $car_label = $car['brand'] . ' ' . $car['model'];
                        notify_booking_created($conn, $user_id, $booking_id, $car_label, $post_start, $post_end, $final_price);
                    }
                }
                
                // V2: Redirect to dashboard — customer waits for admin approval
                $_SESSION['cust_msg'] = "Booking submitted successfully! Your reservation (#BKG-$booking_id) is pending admin approval. You'll be notified once approved.";
                $_SESSION['cust_msg_type'] = "success";
                header("Location: " . $base_url . "customer/dashboard.php");
                exit();
            } else {
                $error = "Database Error: Failed to lock reservation.";
            }
            $insert_query->close();
        }
        $check_avail->close();
    }
}

// 📸 Quick Fallback Image Engine
$default_img = $car['image'] ?? "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&w=2070&auto=format&fit=crop";

$page_title = "Secure Checkout | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🌿 ENTERPRISE SAGE GREEN THEME */
    :root {
        --sage-dark: #2b3327;
        --sage-mid: #889c7c;
        --sage-pale: #e0eadb;
        --sage-white: #f4f5f3;
        --shadow-soft: 0 10px 30px rgba(0,0,0,0.04);
        --shadow-glow: 0 10px 25px rgba(136, 156, 124, 0.3);
    }

    body { background-color: var(--sage-white); }
    
    .checkout-header {
        background: linear-gradient(135deg, #1A1815 0%, var(--sage-dark) 100%);
        color: white; padding: 50px 0 60px 0; margin-top: -24px;
        position: relative; overflow: hidden;
    }

    .form-control-custom {
        background-color: #ffffff; border: 2px solid var(--sage-pale);
        border-radius: 14px; padding: 15px; transition: all 0.3s;
        font-weight: 600; color: #2C2822;
    }
    .form-control-custom:focus { border-color: var(--sage-mid); box-shadow: 0 0 0 4px rgba(136, 156, 124, 0.15); outline: none; }

    .receipt-card {
        border: 1px solid var(--sage-pale); border-radius: 24px; overflow: hidden;
        background: white; box-shadow: var(--shadow-soft);
        position: sticky; top: 100px;
    }
    .receipt-header {
        background: linear-gradient(to top, rgba(43, 51, 39, 0.95), rgba(43, 51, 39, 0.6)), url('<?php echo strpos($default_img, 'http') === 0 ? $default_img : '../'.$default_img; ?>') center/cover;
        padding: 50px 30px; color: white; position: relative;
    }

    /* Add-on Checkboxes (Premium UI) */
    .addon-card {
        border: 2px solid var(--sage-pale); border-radius: 16px; padding: 20px;
        cursor: pointer; transition: all 0.3s ease; margin-bottom: 15px; background: white;
    }
    .addon-card:hover { border-color: var(--sage-mid); background-color: rgba(136, 156, 124, 0.05); transform: translateY(-3px); box-shadow: var(--shadow-soft); }
    .addon-card.selected { border-color: var(--sage-dark); background-color: rgba(136, 156, 124, 0.1); }
    .form-check-input { width: 1.5em; height: 1.5em; margin-top: 0; cursor: pointer; }
    .form-check-input:checked { background-color: var(--sage-dark); border-color: var(--sage-dark); }

    .btn-sage {
        background-color: var(--sage-dark); color: white; border: none;
        transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px;
    }
    .btn-sage:hover {
        background-color: #1a1e17; color: white; transform: translateY(-3px);
        box-shadow: var(--shadow-glow);
    }
</style>

<div class="checkout-header shadow-sm">
    <div class="container d-flex justify-content-between align-items-center position-relative z-2">
        <h2 class="fw-bold mb-0"><i class="fas fa-lock me-2 text-warning"></i> Secure Reservation</h2>
        <a href="<?php echo $base_url; ?>customer/search_cars.php" class="btn btn-outline-light rounded-pill fw-bold"><i class="fas fa-arrow-left me-2"></i>Change Vehicle</a>
    </div>
</div>

<div class="container mt-5 mb-5" style="margin-top: -30px !important; position: relative; z-2">
    
    <?php if($error): ?>
        <div class="alert bg-white rounded-4 shadow-sm mb-4 border-0 border-start border-5 border-danger p-4 d-flex align-items-center" data-aos="fade-down">
            <i class="fas fa-exclamation-triangle fs-3 me-3 text-danger"></i> 
            <span class="fw-bold fs-5 text-dark"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form action="<?php echo $base_url; ?>customer/book_car.php?id=<?php echo $car_id; ?>" method="POST" id="bookingForm">
        <div class="row g-5">
            
            <div class="col-lg-7" data-aos="fade-right">
                
                <div class="d-flex align-items-center mb-4">
                    <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width:40px;height:40px;font-weight:900;">1</div>
                    <h3 class="fw-black mb-0 text-dark">Rental Parameters</h3>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-5 bg-white">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="color: var(--sage-dark);">Pickup Date & Time</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-white border-0 text-muted"><i class="far fa-calendar-alt"></i></span>
                                <input type="date" class="form-control form-control-lg form-control-custom border-0" 
                                       name="start_date" id="start_date" 
                                       value="<?php echo htmlspecialchars($start_date); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required onchange="calculatePriceLive()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase" style="color: var(--sage-dark);">Return Date & Time</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-white border-0 text-muted"><i class="far fa-calendar-check"></i></span>
                                <input type="date" class="form-control form-control-lg form-control-custom border-0" 
                                       name="end_date" id="end_date" 
                                       value="<?php echo htmlspecialchars($end_date); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required onchange="calculatePriceLive()">
                            </div>
                        </div>
                        <div class="col-12 mt-4">
                            <div class="alert bg-light border-0 d-flex align-items-center mb-0 rounded-4 p-3 shadow-sm">
                                <i class="fas fa-shield-alt fs-3 me-3 text-success"></i>
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Free Cancellation Policy</h6>
                                    <small class="text-muted mb-0 fw-bold" id="cancelText">Select dates to view your cancellation window.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center mb-4">
                    <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width:40px;height:40px;font-weight:900;">2</div>
                    <h3 class="fw-black mb-0 text-dark">Enhance Your Trip <span class="text-muted fs-6 fw-bold ms-2">(Optional)</span></h3>
                </div>
                
                <div class="mb-5">
                    <label class="addon-card d-flex justify-content-between align-items-center w-100" id="card_insurance">
                        <div class="d-flex align-items-center">
                            <input class="form-check-input me-3" type="checkbox" name="addon_insurance" id="chk_insurance" onchange="calculatePriceLive()">
                            <div>
                                <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-car-crash text-warning me-2"></i>Comprehensive Protection</h5>
                                <small class="text-muted fw-bold">Zero liability in case of accidental damage, scratches, or theft.</small>
                            </div>
                        </div>
                        <div class="fw-black text-dark fs-5">+₹500<span class="fs-6 text-muted fw-normal">/day</span></div>
                    </label>
                    
                    <label class="addon-card d-flex justify-content-between align-items-center w-100 mb-0" id="card_gps">
                        <div class="d-flex align-items-center">
                            <input class="form-check-input me-3" type="checkbox" name="addon_gps" id="chk_gps" onchange="calculatePriceLive()">
                            <div>
                                <h5 class="fw-bold mb-1 text-dark"><i class="fas fa-satellite-dish text-info me-2"></i>GPS Navigation System</h5>
                                <small class="text-muted fw-bold">Pre-loaded optimal routes to avoid traffic and cellular dead zones.</small>
                            </div>
                        </div>
                        <div class="fw-black text-dark fs-5">+₹150<span class="fs-6 text-muted fw-normal">/day</span></div>
                    </label>
                </div>

                <div class="d-flex align-items-center mb-4">
                    <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width:40px;height:40px;font-weight:900;">3</div>
                    <h3 class="fw-black mb-0 text-dark">Promotions & Offers</h3>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 bg-white">
                    <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden border">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-ticket-alt text-muted"></i></span>
                        <input type="text" class="form-control form-control-custom border-0" 
                               name="coupon_code" id="coupon_code" placeholder="Enter Promo Code" style="text-transform: uppercase;">
                        <button class="btn btn-dark fw-bold px-5" type="button" onclick="calculatePriceLive()" id="applyBtn">Apply</button>
                    </div>
                    <small id="couponMessage" class="mt-2 fw-bold d-block"></small>
                </div>
                
                <input type="hidden" id="car_base_price" value="<?php echo $car['base_price']; ?>">
            </div>

            <div class="col-lg-5" data-aos="fade-left" data-aos-delay="100">
                <div class="receipt-card">
                    
                    <div class="receipt-header">
                        <span class="badge bg-white text-dark mb-3 px-3 py-2 rounded-pill fw-bold shadow-sm"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($car['city_name']); ?> Hub</span>
                        <h2 class="fw-black mb-0 display-6"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h2>
                        <p class="text-light opacity-75 mb-0 small text-uppercase tracking-widest fw-bold mt-1"><?php echo htmlspecialchars($car['name']); ?></p>
                    </div>
                    
                    <div class="card-body p-4 p-md-5 bg-white">
                        <h5 class="fw-black mb-4 text-dark border-bottom border-2 pb-3">Fare Breakdown</h5>
                        
                        <div class="d-flex justify-content-between mb-3 text-muted">
                            <span id="days_count" class="fw-bold">Base Fare (0 Days)</span>
                            <span class="fw-bold text-dark" id="display_base">₹0.00</span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3 text-muted" id="weekend_row" style="display: none !important;">
                            <span class="fw-bold">Weekend Surge <i class="fas fa-arrow-up text-warning ms-1" title="15% increase for weekend days"></i></span>
                            <span class="fw-bold text-dark" id="display_surge">+ ₹0.00</span>
                        </div>

                        <div class="d-flex justify-content-between mb-3 text-muted" id="addon_row" style="display: none !important;">
                            <span class="fw-bold text-primary"><i class="fas fa-plus-circle me-1"></i> Add-ons Selected</span>
                            <span class="fw-bold text-primary" id="display_addons">+ ₹0.00</span>
                        </div>

                        <div class="d-flex justify-content-between mb-3 text-muted" id="discount_row" style="display: none !important;">
                            <span class="fw-bold text-success"><i class="fas fa-tag me-1"></i> Discount Applied</span>
                            <span class="fw-bold text-success" id="display_discount">- ₹0.00</span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3 text-muted border-bottom border-2 pb-3">
                            <span class="fw-bold">Taxes & Fees <span class="badge bg-secondary ms-1">18% GST</span></span>
                            <span class="fw-bold text-dark" id="display_gst">+ ₹0.00</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4 bg-light p-4 rounded-4 border">
                            <div>
                                <h4 class="fw-black mb-0 text-dark">Total Due</h4>
                                <small class="text-muted fw-bold">Includes all taxes</small>
                            </div>
                            <h2 class="fw-black mb-0" style="color: var(--sage-dark);" id="display_total">₹0.00</h2>
                        </div>
                        
                        <button type="submit" name="confirm_booking" class="btn btn-sage btn-lg w-100 rounded-pill fw-bold py-3 mt-4 shadow-lg position-relative overflow-hidden" id="submitBtn" onclick="this.innerHTML='Processing... <i class=\'fas fa-spinner fa-spin ms-2\'></i>';">
                            <span class="position-relative z-1">Lock Reservation <i class="fas fa-lock ms-2"></i></span>
                        </button>
                        <p class="text-center text-muted small fw-bold mt-4 mb-0"><i class="fas fa-shield-alt text-success me-1"></i> 256-bit PCI-DSS Secure Transaction</p>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // Initialize Animations
    AOS.init({ once: true, offset: 50, duration: 800, easing: 'ease-out-cubic' });

    // Load active coupons from PHP securely
    const activeCoupons = <?php echo $coupons_json; ?>;

    function calculatePriceLive() {
        const startInput = document.getElementById('start_date').value;
        const endInput = document.getElementById('end_date').value;
        const basePrice = parseFloat(document.getElementById('car_base_price').value);
        const couponInput = document.getElementById('coupon_code').value.trim().toUpperCase();
        const couponMsg = document.getElementById('couponMessage');
        const submitBtn = document.getElementById('submitBtn');
        
        // Add-ons
        const hasInsurance = document.getElementById('chk_insurance').checked;
        const hasGps = document.getElementById('chk_gps').checked;
        const addonCardIns = document.getElementById('card_insurance');
        const addonCardGps = document.getElementById('card_gps');
        
        hasInsurance ? addonCardIns.classList.add('selected') : addonCardIns.classList.remove('selected');
        hasGps ? addonCardGps.classList.add('selected') : addonCardGps.classList.remove('selected');

        if (!startInput || !endInput) return;

        // Use robust Date string parsing to avoid Timezone bugs
        const start = new Date(startInput + 'T00:00:00');
        const end = new Date(endInput + 'T00:00:00');
        
        const today = new Date();
        today.setHours(0,0,0,0);
        
        if (start < today) {
            document.getElementById('display_total').innerText = "Invalid Date";
            submitBtn.disabled = true;
            return;
        }
        if (end < start) {
            document.getElementById('display_total').innerText = "Invalid Return";
            submitBtn.disabled = true;
            return;
        }
        submitBtn.disabled = false;

        // Cancellation Policy Logic
        const cancelDate = new Date(start);
        cancelDate.setDate(cancelDate.getDate() - 2);
        if(cancelDate < today) {
            document.getElementById('cancelText').innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> Non-refundable.</span> Less than 48hrs to pickup.`;
        } else {
            const options = { weekday: 'short', month: 'short', day: 'numeric' };
            document.getElementById('cancelText').innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> Free cancellation until <span class="fw-bold text-dark">${cancelDate.toLocaleDateString('en-IN', options)}</span>.`;
        }

        // 1. Calculate Total Days
        const diffTime = Math.abs(end - start);
        let totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (totalDays === 0) totalDays = 1; // Minimum 1 day

        // 2. Count Weekend Days (Matches PHP Logic exactly)
        let weekendDays = 0;
        let tempDate = new Date(start);
        for (let i = 0; i <= totalDays; i++) {
            let day = tempDate.getDay();
            if (day === 0 || day === 6) { weekendDays++; }
            tempDate.setDate(tempDate.getDate() + 1);
        }
        
        let weekdayDays = totalDays - weekendDays;

        // 3. Base & Surge Fares
        let baseTotal = weekdayDays * basePrice;
        let surgeRate = basePrice * 1.15;
        let surgeTotal = weekendDays * surgeRate;
        let subtotal = baseTotal + surgeTotal;

        if (weekendDays > 0) {
            document.getElementById('weekend_row').style.setProperty('display', 'flex', 'important');
            document.getElementById('display_surge').innerText = '+ ₹' + surgeTotal.toFixed(2);
        } else {
            document.getElementById('weekend_row').style.setProperty('display', 'none', 'important');
        }

        // 4. Add-ons Calculation
        let dailyAddonCost = 0;
        if(hasInsurance) dailyAddonCost += 500;
        if(hasGps) dailyAddonCost += 150;
        
        let totalAddonPrice = dailyAddonCost * totalDays;
        if(totalAddonPrice > 0) {
            document.getElementById('addon_row').style.setProperty('display', 'flex', 'important');
            document.getElementById('display_addons').innerText = '+ ₹' + totalAddonPrice.toFixed(2);
        } else {
            document.getElementById('addon_row').style.setProperty('display', 'none', 'important');
        }

        // 5. Dynamic Coupon Engine
        let discountAmount = 0;
        couponMsg.innerText = '';
        
        if (couponInput !== '') {
            if (activeCoupons.hasOwnProperty(couponInput)) {
                let coupon = activeCoupons[couponInput];
                if (coupon.type === 'percentage') {
                    discountAmount = subtotal * (coupon.value / 100);
                } else if (coupon.type === 'fixed') {
                    discountAmount = coupon.value;
                }
                if (discountAmount > subtotal) discountAmount = subtotal;
                
                couponMsg.innerHTML = `<i class="fas fa-check-circle me-1"></i> Code Applied! Saved ₹${discountAmount.toFixed(2)}`;
                couponMsg.className = "mt-2 fw-bold d-block text-success";
                document.getElementById('discount_row').style.setProperty('display', 'flex', 'important');
            } else {
                couponMsg.innerHTML = `<i class="fas fa-times-circle me-1"></i> Invalid or expired code.`;
                couponMsg.className = "mt-2 fw-bold d-block text-danger";
                document.getElementById('discount_row').style.setProperty('display', 'none', 'important');
            }
        } else {
            document.getElementById('discount_row').style.setProperty('display', 'none', 'important');
        }

        let finalPostDiscount = (subtotal - discountAmount) + totalAddonPrice;

        // 6. GST 18%
        let gstAmount = finalPostDiscount * 0.18;
        let finalPayable = finalPostDiscount + gstAmount;

        // Update DOM
        document.getElementById('days_count').innerText = `Base Fare (${weekdayDays} Weekdays)`;
        document.getElementById('display_base').innerText = '₹' + baseTotal.toFixed(2);
        document.getElementById('display_discount').innerText = '- ₹' + discountAmount.toFixed(2);
        document.getElementById('display_gst').innerText = '+ ₹' + gstAmount.toFixed(2);
        document.getElementById('display_total').innerText = '₹' + finalPayable.toFixed(2);
    }

    window.onload = function() { calculatePriceLive(); };
</script>

<?php include '../includes/footer.php'; ?>