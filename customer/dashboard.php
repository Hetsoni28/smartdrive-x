<?php
session_start();

// 🛡️ SECURITY CHECK: Role-Based Access Control (RBAC)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$first_name = explode(' ', trim($user_name))[0];

// ==========================================
// 🔔 SESSION TOAST MESSAGES (PRG Pattern)
// ==========================================
$cust_msg = '';
$cust_msg_type = '';
if (isset($_SESSION['cust_msg'])) {
    $cust_msg = $_SESSION['cust_msg'];
    $cust_msg_type = $_SESSION['cust_msg_type'] ?? 'info';
    unset($_SESSION['cust_msg'], $_SESSION['cust_msg_type']);
}

// ==========================================
// 🧠 DEFENSIVE PROGRAMMING: REAL-WORLD QUERIES
// ==========================================

// 1. Core KPIs
$booking_count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE user_id = $user_id AND booking_status != 'cancelled'");
$booking_count = $booking_count_query ? (mysqli_fetch_assoc($booking_count_query)['total'] ?? 0) : 0;

$points_query = mysqli_query($conn, "SELECT loyalty_points FROM users WHERE id = $user_id");
$loyalty_points = $points_query ? (mysqli_fetch_assoc($points_query)['loyalty_points'] ?? 0) : 0;

$spend_query = mysqli_query($conn, "SELECT SUM(final_price) as total_spent FROM bookings WHERE user_id = $user_id AND booking_status IN ('confirmed','active','completed')");
$total_spent = $spend_query ? (mysqli_fetch_assoc($spend_query)['total_spent'] ?? 0) : 0;

// 2. Action Required Check (Approved → Awaiting Payment)
$approved_query = mysqli_query($conn, "
    SELECT b.id, b.car_id, b.final_price, c.brand, c.name as car_name 
    FROM bookings b JOIN cars c ON b.car_id = c.id 
    WHERE b.user_id = $user_id AND b.booking_status = 'approved' 
    ORDER BY b.id DESC LIMIT 1
");
$approved_booking = $approved_query ? mysqli_fetch_assoc($approved_query) : null;

// 2b. Legacy pending check (for pre-V2 bookings)
$pending_query = mysqli_query($conn, "SELECT id, car_id, final_price FROM bookings WHERE user_id = $user_id AND booking_status = 'pending' ORDER BY id DESC LIMIT 1");
$pending_booking = $pending_query ? mysqli_fetch_assoc($pending_query) : null;

// 3. Active/Upcoming Trip Logic
$active_trip_query = mysqli_query($conn, "
    SELECT b.*, c.name as car_name, c.brand, l.city_name, c.model 
    FROM bookings b 
    JOIN cars c ON b.car_id = c.id 
    JOIN locations l ON c.location_id = l.id
    WHERE b.user_id = $user_id AND b.booking_status IN ('confirmed','active') AND b.end_date >= CURDATE()
    ORDER BY b.start_date ASC LIMIT 1
");
$active_trip = $active_trip_query ? mysqli_fetch_assoc($active_trip_query) : null;

// 4. Historical Data for Ledger
$recent_bookings_query = mysqli_query($conn, "
    SELECT b.*, c.name as car_name, c.brand 
    FROM bookings b 
    JOIN cars c ON b.car_id = c.id 
    WHERE b.user_id = $user_id 
    ORDER BY b.id DESC LIMIT 6
");

// 5. Eco-Metric: Carbon Offset Calculation (Estimated 5.2kg saved per rental day)
$days_query = mysqli_query($conn, "SELECT SUM(DATEDIFF(end_date, start_date)) as total_days FROM bookings WHERE user_id = $user_id AND booking_status IN ('confirmed','active','completed')");
$total_days = $days_query ? (mysqli_fetch_assoc($days_query)['total_days'] ?? 0) : 0;
$co2_saved = round($total_days * 5.2, 1);

// 6. Dynamic 6-Month Spending Chart Logic
$chart_months = [];
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_val = date('m', strtotime("-$i months"));
    $year_val = date('Y', strtotime("-$i months"));
    $chart_months[] = "'" . date('M', strtotime("-$i months")) . "'";
    
    // Fetch user spend for this specific month/year
    $monthly_spend = mysqli_query($conn, "SELECT SUM(final_price) as t FROM bookings WHERE user_id = $user_id AND booking_status IN ('confirmed','active','completed') AND MONTH(created_at) = '$month_val' AND YEAR(created_at) = '$year_val'");
    $spent = $monthly_spend ? (mysqli_fetch_assoc($monthly_spend)['t'] ?? 0) : 0;
    $chart_data[] = $spent;
}
$js_labels = implode(',', $chart_months);
$js_data = implode(',', $chart_data);

// 7. Membership Tier Engine & Dynamic Next Goal
$tier = "Silver"; $tier_color = "#6c757d"; $tier_bonus = "5%";
$next_tier_goal = 500; $next_tier_name = "Gold";
if($loyalty_points >= 500) { $tier = "Gold"; $tier_color = "#ffc107"; $tier_bonus = "10%"; $next_tier_goal = 1500; $next_tier_name = "Platinum"; }
if($loyalty_points >= 1500) { $tier = "Platinum"; $tier_color = "#0dcaf0"; $tier_bonus = "20%"; $next_tier_goal = 1500; $next_tier_name = "Max"; }

$points_needed = max(0, $next_tier_goal - $loyalty_points);
$progress_percent = min(100, ($loyalty_points / $next_tier_goal) * 100);

// 📸 Smart Image Fallback
function getCarImage($brand) {
    $images = [
        'porsche' => 'https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=800&auto=format&fit=crop',
        'bmw' => 'https://images.unsplash.com/photo-1556189250-72ba954cfc2b?q=80&w=800&auto=format&fit=crop',
        'audi' => 'https://images.unsplash.com/photo-1606152421802-db97b9c7a11b?q=80&w=800&auto=format&fit=crop'
    ];
    return $images[strtolower(trim($brand))] ?? 'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?q=80&w=800&auto=format&fit=crop';
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    :root {
        --sage-dark: #4a5c43;
        --sage-mid: #889c7c;
        --sage-pale: #e0eadb;
        --sage-bg: #f4f5f3;
        --dashboard-gradient: linear-gradient(135deg, #2b3327 0%, #4a5c43 100%);
    }

    body { background-color: var(--sage-bg); }

    /* Premium Greeting Section */
    .premium-banner {
        background: var(--dashboard-gradient);
        color: white; border-radius: 25px; padding: 3.5rem;
        position: relative; overflow: hidden; margin-bottom: 2.5rem;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    }
    .premium-banner::after {
        content: '\f1b9'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: -20px; bottom: -50px; font-size: 18rem;
        color: rgba(255,255,255,0.03); transform: rotate(-15deg); pointer-events: none;
    }

    /* KPI Cards */
    .beast-kpi-card {
        border: none; border-radius: 20px; background: white; padding: 25px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 10px 25px rgba(0,0,0,0.02); height: 100%;
    }
    .beast-kpi-card:hover { transform: translateY(-10px); box-shadow: 0 15px 35px rgba(74, 92, 67, 0.12); }
    
    .kpi-icon {
        width: 55px; height: 55px; border-radius: 14px;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        margin-bottom: 1.5rem;
    }

    /* Active Trip UI */
    .trip-card {
        background: white; border-radius: 24px; border: 1px solid rgba(0,0,0,0.05);
        overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.05);
    }
    .trip-img-box { position: relative; height: 300px; }
    .trip-img-box img { width: 100%; height: 100%; object-fit: cover; }
    .trip-overlay {
        position: absolute; bottom: 0; left: 0; right: 0; padding: 25px;
        background: linear-gradient(transparent, rgba(0,0,0,0.8)); color: white;
    }

    /* Animated Alerts & Tickers */
    .news-ticker {
        background: white; border-radius: 50px; padding: 10px 25px;
        margin-bottom: 2rem; border: 1px solid var(--sage-pale);
        display: flex; align-items: center;
    }
    .pulse-alert {
        animation: pulse-border 2s infinite; border: 2px solid #ffc107;
    }
    @keyframes pulse-border {
        0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
    }

    /* Data Table */
    .table-beast { border-collapse: separate; border-spacing: 0 10px; }
    .table-beast tr { background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.02); transition: transform 0.2s; }
    .table-beast tr:hover { transform: scale(1.01); background-color: #fbfcfa; }
    .table-beast td { border: none; padding: 20px; vertical-align: middle; }
    .table-beast td:first-child { border-radius: 15px 0 0 15px; }
    .table-beast td:last-child { border-radius: 0 15px 15px 0; }

    .pulse-dot {
        height: 10px; width: 10px; background-color: #198754;
        border-radius: 50%; display: inline-block; margin-right: 8px;
        box-shadow: 0 0 0 rgba(25, 135, 84, 0.4); animation: pulse-green 2s infinite;
    }
    @keyframes pulse-green { 0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(25, 135, 84, 0); } 100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); } }

    /* Failsafe to ensure content is visible if AOS JS fails to load */
    .dashboard-content { opacity: 1 !important; visibility: visible !important; }
</style>

<div class="dashboard-layout">
    
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content" id="mainDashboardContent">

        <?php if ($approved_booking): ?>
            <div class="alert bg-white pulse-alert rounded-4 p-3 d-flex align-items-center justify-content-between mb-4 shadow-sm" data-aos="fade-down" style="border-color: #198754 !important; animation-name: pulse-green-border;">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3 text-success fs-4"><i class="fas fa-thumbs-up"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1 text-dark"><i class="fas fa-check-circle text-success me-1"></i>Booking Approved! Complete Payment</h6>
                        <small class="text-muted">Your reservation for <strong><?php echo htmlspecialchars($approved_booking['brand'] . ' ' . $approved_booking['car_name']); ?></strong> (#BKG-<?php echo $approved_booking['id']; ?>) has been approved. Pay ₹<?php echo number_format($approved_booking['final_price'], 0); ?> to confirm.</small>
                    </div>
                </div>
                <a href="payment.php?booking_id=<?php echo $approved_booking['id']; ?>" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm"><i class="fas fa-credit-card me-1"></i>Pay Now</a>
            </div>
        <?php elseif ($pending_booking): ?>
            <div class="alert bg-white pulse-alert rounded-4 p-3 d-flex align-items-center justify-content-between mb-4 shadow-sm" data-aos="fade-down">
                <div class="d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 p-3 rounded-circle me-3 text-warning fs-4"><i class="fas fa-hourglass-half text-warning"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">Booking Submitted — Awaiting Approval</h6>
                        <small class="text-muted">Your reservation (#BKG-<?php echo $pending_booking['id']; ?>) is pending admin approval. You'll be notified once approved.</small>
                    </div>
                </div>
                <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold"><i class="fas fa-clock me-1"></i>Under Review</span>
            </div>
        <?php else: ?>
            <div class="news-ticker shadow-sm" data-aos="fade-down">
                <span class="badge bg-danger rounded-pill me-3">LIVE</span>
                <marquee class="fw-bold text-muted" scrollamount="5">
                    Welcome to SmartDrive X Premium Portal. You are currently a <span style="color: <?php echo $tier_color; ?>;"><?php echo $tier; ?> Member</span>. 
                    <?php if ($points_needed > 0): ?>
                        Earn <?php echo $points_needed; ?> more points to unlock <?php echo $next_tier_name; ?> status and <?php echo $tier_bonus; ?> extra discount!
                    <?php else: ?>
                        You have reached the maximum loyalty tier! Enjoy your exclusive <?php echo $tier_bonus; ?> discounts on all future rides.
                    <?php endif; ?>
                </marquee>
            </div>
        <?php endif; ?>

        <div class="premium-banner d-flex justify-content-between align-items-center flex-wrap shadow-lg" data-aos="fade-up">
            <div style="z-index: 2;">
                <h6 class="text-uppercase fw-bold mb-2 tracking-widest" style="color: var(--sage-mid); letter-spacing: 3px;">Member Dashboard</h6>
                <h1 class="display-4 fw-bold mb-2"><?php echo date('H') < 12 ? 'Good Morning' : (date('H') < 17 ? 'Good Afternoon' : 'Good Evening'); ?>, <?php echo htmlspecialchars($first_name); ?>!</h1>
                <p class="fs-5 opacity-75 mb-4">Your next adventure is just a few clicks away. Explore the curated fleet below.</p>
                <div class="d-flex gap-3">
                    <a href="<?php echo $base_url; ?>customer/search_cars.php" class="btn btn-light btn-lg rounded-pill fw-bold px-4 py-3 text-dark">
                        <i class="fas fa-search me-2"></i> Book A Ride
                    </a>
                </div>
            </div>
            <div class="text-end d-none d-xl-block" style="z-index: 2;">
                <div class="p-4 rounded-4 shadow-lg border border-light border-opacity-10" style="background: rgba(255,255,255,0.05); backdrop-filter: blur(10px);">
                    <h2 class="fw-bold mb-0">₹<?php echo number_format($total_spent, 0); ?></h2>
                    <small class="text-uppercase opacity-50 fw-bold">Total Lifetime Value</small>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="beast-kpi-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="fas fa-road"></i></div>
                    <h6 class="text-muted fw-bold small text-uppercase">Trips Completed</h6>
                    <h2 class="fw-bold mb-0"><?php echo $booking_count; ?></h2>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="beast-kpi-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-gem"></i></div>
                    <h6 class="text-muted fw-bold small text-uppercase">Loyalty Points</h6>
                    <h2 class="fw-bold mb-0"><?php echo $loyalty_points; ?></h2>
                    <div class="progress mt-3" style="height: 6px;">
                        <div class="progress-bar bg-warning" style="width: <?php echo $progress_percent; ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="beast-kpi-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info"><i class="fas fa-shield-alt"></i></div>
                    <h6 class="text-muted fw-bold small text-uppercase">Tier Status</h6>
                    <h3 class="fw-bold mb-0" style="color: <?php echo $tier_color; ?>;"><?php echo $tier; ?></h3>
                    <small class="text-muted fw-bold">Benefit: <?php echo $tier_bonus; ?> Off</small>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="beast-kpi-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-leaf"></i></div>
                    <h6 class="text-muted fw-bold small text-uppercase">Eco Footprint</h6>
                    <h3 class="fw-bold mb-0 text-success"><?php echo $co2_saved; ?> kg</h3>
                    <small class="text-muted fw-bold">CO₂ Saved vs Owning</small>
                </div>
            </div>
        </div>

        <div class="row g-5">
            <div class="col-xl-8">
                
                <h5 class="fw-bold mb-4 text-dark d-flex align-items-center">
                    <span class="pulse-dot"></span> Active Mission
                </h5>

                <?php if ($active_trip): 
                    $today = new DateTime();
                    $pickup = new DateTime($active_trip['start_date']);
                    $diff = $today->diff($pickup)->format("%r%a");
                    
                    if ($diff > 0) {
                        $trip_status = "Upcoming in $diff days";
                        $badge_color = "bg-warning text-dark";
                    } else {
                        $trip_status = "Currently Active";
                        $badge_color = "bg-success";
                    }
                ?>
                    <div class="trip-card mb-5" data-aos="zoom-in">
                        <div class="row g-0">
                            <div class="col-md-6">
                                <div class="trip-img-box">
                                    <img src="<?php echo getCarImage($active_trip['brand']); ?>" alt="Trip Car">
                                    <div class="trip-overlay">
                                        <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($active_trip['brand'] . ' ' . $active_trip['car_name']); ?></h4>
                                        <small class="opacity-75"><?php echo htmlspecialchars($active_trip['model'] ?? 'Premium'); ?> Edition</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 p-4 p-xl-5 d-flex flex-column">
                                <div class="mb-4">
                                    <span class="badge <?php echo $badge_color; ?> rounded-pill px-3 py-2 mb-3"><?php echo $trip_status; ?></span>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light p-3 rounded-3 me-3 text-danger"><i class="fas fa-map-marker-alt fa-lg"></i></div>
                                        <div>
                                            <small class="text-muted fw-bold text-uppercase">Assigned Hub</small>
                                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($active_trip['city_name'] ?? 'Main'); ?> Terminal</h6>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-light p-4 rounded-4 border mb-4">
                                    <div class="row">
                                        <div class="col-6 border-end">
                                            <small class="text-muted d-block mb-1 fw-bold">RETURN BY</small>
                                            <span class="fw-bold text-dark"><?php echo date('D, d M', strtotime($active_trip['end_date'])); ?></span>
                                        </div>
                                        <div class="col-6 ps-3">
                                            <small class="text-muted d-block mb-1 fw-bold">PIN CODE</small>
                                            <span class="fw-bold text-dark"><i class="fas fa-lock text-muted me-1"></i> **<?php echo substr(strval($active_trip['id'] * 1234), -2); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-auto d-flex gap-2">
                                    <a href="invoice.php?id=<?php echo $active_trip['id']; ?>" class="btn btn-dark rounded-pill fw-bold flex-grow-1 py-2">Quick Invoice</a>
                                    <button class="btn btn-outline-dark rounded-circle" title="Contact Support"><i class="fas fa-headset"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm rounded-4 p-5 mb-5 text-center bg-white" style="border: 2px dashed var(--sage-pale) !important;">
                        <i class="fas fa-car-side fa-4x mb-3 text-muted opacity-25"></i>
                        <h4 class="fw-bold text-dark">Ready for your next journey?</h4>
                        <p class="text-muted px-lg-5 mb-4">You have no active rentals at the moment. Browse our new fleet of SUVs and Sedans available in your city.</p>
                        <a href="<?php echo $base_url; ?>customer/search_cars.php" class="btn btn-sage rounded-pill fw-bold px-5 py-3" style="background: var(--sage-dark); color: white;">Explore Fleet</a>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm rounded-4 p-4 mb-5" data-aos="fade-up">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-chart-line text-primary me-2"></i> 6-Month Spending Analysis</h5>
                    </div>
                    <canvas id="userSpendChart" height="150"></canvas>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 overflow-hidden" data-aos="fade-up">
                    <h5 class="fw-bold mb-4 text-dark"><i class="fas fa-history text-muted me-2"></i> Transaction Ledger</h5>
                    <div class="table-responsive">
                        <table class="table table-beast align-middle mb-0">
                            <thead>
                                <tr class="text-muted small fw-bold text-uppercase">
                                    <th>Booking Ref</th>
                                    <th>Vehicle</th>
                                    <th>Dates</th>
                                    <th class="text-center">Total Paid</th>
                                    <th class="text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recent_bookings_query && mysqli_num_rows($recent_bookings_query) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($recent_bookings_query)): ?>
                                        <tr>
                                            <td class="fw-bold text-muted">#BKG-<?php echo $row['id']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-light p-2 rounded-2 me-3"><i class="fas fa-car text-sage-dark" style="color: var(--sage-dark);"></i></div>
                                                    <span class="fw-bold"><?php echo htmlspecialchars($row['brand'] . ' ' . ($row['car_name'] ?? '')); ?></span>
                                                </div>
                                            </td>
                                            <td class="small text-muted">
                                                <?php echo date('d M', strtotime($row['start_date'])); ?> - <?php echo date('d M', strtotime($row['end_date'])); ?>
                                            </td>
                                            <td class="text-center fw-bold text-dark">₹<?php echo number_format($row['final_price'], 0); ?></td>
                                            <td class="text-end">
                                                <?php 
                                                    $sl = get_booking_status_label($row['booking_status']);
                                                    if ($row['booking_status'] === 'approved'): 
                                                ?>
                                                    <a href="payment.php?booking_id=<?php echo $row['id']; ?>" class="badge <?php echo $sl['badge_class']; ?> rounded-pill px-3 py-2 text-decoration-none shadow-sm"><i class="fas fa-credit-card me-1"></i>PAY NOW</a>
                                                <?php elseif ($row['booking_status'] === 'completed'): ?>
                                                    <a href="final_invoice.php?id=<?php echo $row['id']; ?>" class="badge <?php echo $sl['badge_class']; ?> rounded-pill px-3 py-2 text-decoration-none shadow-sm"><i class="fas <?php echo $sl['icon']; ?> me-1"></i><?php echo $sl['label']; ?></a>
                                                <?php else: ?>
                                                    <span class="badge <?php echo $sl['badge_class']; ?> rounded-pill px-3 py-2 shadow-sm"><i class="fas <?php echo $sl['icon']; ?> me-1"></i><?php echo strtoupper($sl['label']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No recent transactions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <div class="col-xl-4" data-aos="fade-left">
                
                <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4 text-center overflow-hidden position-relative">
                    <div style="position: absolute; top: -10px; right: -10px; font-size: 5rem; opacity: 0.03;"><i class="fas fa-gem"></i></div>
                    <h6 class="fw-bold text-muted text-uppercase mb-4">Reward Progress</h6>
                    <div class="d-flex justify-content-center mb-4">
                        <div class="position-relative" style="width: 120px; height: 120px;">
                            <canvas id="rewardsCircle"></canvas>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <h3 class="fw-bold mb-0"><?php echo round($progress_percent); ?>%</h3>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted mb-4">Earn <strong><?php echo $points_needed; ?></strong> more points to unlock the <?php echo $next_tier_name; ?> tier perks!</p>
                    <button class="btn w-100 rounded-pill fw-bold py-3 shadow-sm text-white" style="background: var(--sage-dark);">Redeem Points</button>
                </div>

                <div class="card border-0 shadow-lg rounded-4 p-4 mb-4" style="background: var(--sage-dark); color: white;">
                    <h5 class="fw-bold mb-3"><i class="fas fa-headset text-warning me-2"></i> Concierge 24/7</h5>
                    <p class="small opacity-75 mb-4">Need emergency roadside assistance or want to extend your current booking? We're here for you.</p>
                    <a href="tel:1800555999" class="btn btn-warning text-dark fw-bold w-100 rounded-pill mb-2">Call Assistant</a>
                    <button class="btn btn-outline-light fw-bold w-100 rounded-pill">Open Live Chat</button>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
    // TOAST MESSAGE ENGINE (Session Flash Messages)
    <?php if($cust_msg): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const toastDiv = document.createElement('div');
        toastDiv.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;background:white;border-radius:16px;padding:16px 24px;box-shadow:0 15px 35px rgba(0,0,0,0.1);display:flex;align-items:center;gap:15px;min-width:320px;transform:translateX(120%);opacity:0;transition:all 0.5s cubic-bezier(0.68,-0.55,0.265,1.55);border-left:5px solid <?php echo $cust_msg_type === "success" ? "#198754" : ($cust_msg_type === "danger" ? "#dc3545" : "#ffc107"); ?>;';
        toastDiv.innerHTML = '<i class="fas <?php echo $cust_msg_type === "success" ? "fa-check-circle text-success" : ($cust_msg_type === "danger" ? "fa-times-circle text-danger" : "fa-info-circle text-warning"); ?> fs-4"></i><div><h6 class="fw-bold mb-0 text-dark">System Update</h6><small class="text-muted fw-bold"><?php echo addslashes($cust_msg); ?></small></div>';
        document.body.appendChild(toastDiv);
        setTimeout(() => { toastDiv.style.transform = 'translateX(0)'; toastDiv.style.opacity = '1'; }, 100);
        setTimeout(() => { toastDiv.style.transform = 'translateX(120%)'; toastDiv.style.opacity = '0'; setTimeout(() => toastDiv.remove(), 500); }, 5000);
    });
    <?php endif; ?>

    // ULTIMATE FAILSAFE: If AOS fails to run, force the content to be visible!
    setTimeout(() => {
        document.querySelectorAll('[data-aos]').forEach(el => {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
    }, 1200);

    // 1. REAL-WORLD DYNAMIC SPENDING ANALYSIS CHART
    const spendCanvas = document.getElementById('userSpendChart');
    if(spendCanvas) {
        const ctxSpend = spendCanvas.getContext('2d');
        new Chart(ctxSpend, {
            type: 'line',
            data: {
                labels: [<?php echo $js_labels; ?>], // Dynamically loads the last 6 months (e.g. 'Oct', 'Nov'...)
                datasets: [{
                    label: 'Monthly Spending (₹)',
                    data: [<?php echo $js_data; ?>], // Dynamically loads user's real DB spending!
                    borderColor: '#4a5c43',
                    backgroundColor: 'rgba(74, 92, 67, 0.05)',
                    borderWidth: 4,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true }, x: { grid: { display: false } } }
            }
        });
    }

    // 2. DYNAMIC REWARDS PROGRESS CIRCLE
    const rewardsCanvas = document.getElementById('rewardsCircle');
    if(rewardsCanvas) {
        const ctxRewards = rewardsCanvas.getContext('2d');
        new Chart(ctxRewards, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [<?php echo $loyalty_points; ?>, <?php echo $points_needed; ?>],
                    backgroundColor: ['#ffc107', '#f8f9fa'],
                    borderWidth: 0
                }]
            },
            options: { cutout: '85%', plugins: { tooltip: { enabled: false } }, responsive: true }
        });
    }
</script>

<?php include '../includes/footer.php'; ?>