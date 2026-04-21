<?php
session_start();

// 🛡️ SECURITY CHECK: Kick out anyone who is NOT an Admin (role_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

// ==========================================
// 🧠 ENTERPRISE BUSINESS INTELLIGENCE (BI) ENGINE
// ==========================================

// 1. Total Lifetime Revenue & Bookings
$rev_query = mysqli_query($conn, "SELECT SUM(final_price) as total, COUNT(id) as total_bookings FROM bookings WHERE booking_status IN ('confirmed','active','completed')");
$rev_data = mysqli_fetch_assoc($rev_query);
$total_revenue = $rev_data['total'] ?? 0;
$total_bookings = $rev_data['total_bookings'] ?? 0;

// 1b. Pending Revenue (Money waiting to be collected)
$pending_rev_query = mysqli_query($conn, "SELECT SUM(final_price) as pending_total, COUNT(id) as pending_count FROM bookings WHERE booking_status = 'pending'");
$pending_data = mysqli_fetch_assoc($pending_rev_query);
$pending_revenue = $pending_data['pending_total'] ?? 0;
$pending_count = $pending_data['pending_count'] ?? 0;

// 2. Average Order Value (AOV)
$aov = ($total_bookings > 0) ? ($total_revenue / $total_bookings) : 0;

// 3. Month-over-Month (MoM) Growth & Target Tracker
$current_month_query = mysqli_query($conn, "SELECT SUM(final_price) as total FROM bookings WHERE booking_status IN ('confirmed','active','completed') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$month_revenue = mysqli_fetch_assoc($current_month_query)['total'] ?? 0;

$last_month_query = mysqli_query($conn, "SELECT SUM(final_price) as total FROM bookings WHERE booking_status IN ('confirmed','active','completed') AND MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)");
$last_month_revenue = mysqli_fetch_assoc($last_month_query)['total'] ?? 0;

$mom_growth = 0; $mom_direction = "up";
if ($last_month_revenue > 0) {
    $mom_growth = (($month_revenue - $last_month_revenue) / $last_month_revenue) * 100;
    if ($mom_growth < 0) { $mom_direction = "down"; $mom_growth = abs($mom_growth); }
}

// Target Logic (Dynamic Goal Setting)
$monthly_goal = 500000; // ₹5 Lakhs Goal
$goal_progress = min(100, ($month_revenue / $monthly_goal) * 100);

// 4. Client Analytics: Active Customers & The "Whale" (Top Spender)
$user_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role_id = 2");
$active_users = mysqli_fetch_assoc($user_query)['total'] ?? 0;

$whale_query = mysqli_query($conn, "
    SELECT u.name, SUM(b.final_price) as total_spent 
    FROM bookings b JOIN users u ON b.user_id = u.id 
    WHERE b.booking_status IN ('confirmed','active','completed') 
    GROUP BY u.id ORDER BY total_spent DESC LIMIT 1
");
$whale_data = mysqli_fetch_assoc($whale_query);
$top_customer = $whale_data['name'] ?? "No Data";
$top_customer_spend = $whale_data['total_spent'] ?? 0;

// 5. Most Rented Car
$top_car_query = mysqli_query($conn, "
    SELECT c.brand, c.name, COUNT(b.car_id) as rent_count 
    FROM bookings b JOIN cars c ON b.car_id = c.id 
    GROUP BY b.car_id ORDER BY rent_count DESC LIMIT 1
");
$top_car_data = mysqli_fetch_assoc($top_car_query);
$most_rented_car = $top_car_data ? $top_car_data['brand'] . ' ' . $top_car_data['name'] : "No Data Yet";

// 6. 📈 DYNAMIC CHART LOGIC: Monthly Revenue Array
$current_year = date('Y');
$monthly_revenue = array_fill(1, 12, 0); 
$monthly_query = mysqli_query($conn, "
    SELECT MONTH(created_at) as month_num, SUM(final_price) as monthly_total 
    FROM bookings WHERE booking_status IN ('confirmed','active','completed') AND YEAR(created_at) = '$current_year' 
    GROUP BY MONTH(created_at)
");
while($row = mysqli_fetch_assoc($monthly_query)) {
    $monthly_revenue[$row['month_num']] = (float)$row['monthly_total'];
}
$js_revenue_data = implode(',', $monthly_revenue); 

// 7. 🍩 FLEET STATUS, UTILIZATION & LOST REVENUE LOGIC
$total_fleet_query = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars"));
$total_fleet = max(1, $total_fleet_query['c'] ?? 1); // 🛑 Failsafe: Prevent division by zero
$fleet_avail = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars WHERE status = 'available'"))['c'] ?? 0;
$fleet_maint = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars WHERE status = 'maintenance'"))['c'] ?? 0;
$fleet_booked = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT car_id) as c FROM bookings WHERE booking_status IN ('confirmed','active') AND start_date <= CURDATE() AND end_date >= CURDATE()"))['c'] ?? 0;

$utilization_rate = round(($fleet_booked / $total_fleet) * 100);

// Maintenance Revenue Impact (How much daily money is lost because cars are broken?)
$maint_impact_query = mysqli_query($conn, "SELECT SUM(base_price) as lost_daily FROM cars WHERE status = 'maintenance'");
$lost_daily_rev = mysqli_fetch_assoc($maint_impact_query)['lost_daily'] ?? 0;

// 8. 🚨 CRITICAL ALERTS: OVERDUE VEHICLES
$overdue_returns = mysqli_query($conn, "
    SELECT b.id, u.name as customer, u.phone, c.brand, c.name as car_name, b.end_date 
    FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id
    WHERE b.booking_status IN ('confirmed','active') AND b.end_date < CURDATE()
");

// 8b. V2: LATE RETURN STATS
$late_count_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM late_returns");
$late_return_count = $late_count_query ? (mysqli_fetch_assoc($late_count_query)['c'] ?? 0) : 0;

$penalty_rev_query = mysqli_query($conn, "SELECT COALESCE(SUM(total_penalty), 0) as total FROM late_returns");
$penalty_revenue = $penalty_rev_query ? (mysqli_fetch_assoc($penalty_rev_query)['total'] ?? 0) : 0;

$active_rides_query = mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE booking_status = 'active'");
$active_rides_count = $active_rides_query ? (mysqli_fetch_assoc($active_rides_query)['c'] ?? 0) : 0;

// 9. UPCOMING RETURNS (Cars coming back in the next 3 days)
$upcoming_returns = mysqli_query($conn, "
    SELECT b.id, u.name as customer, c.brand, c.name as car_name, b.end_date, l.city_name
    FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id JOIN locations l ON c.location_id = l.id
    WHERE b.booking_status IN ('confirmed','active') AND b.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY b.end_date ASC LIMIT 4
");

// 10. LOCATION CAPACITY HEALTH
$location_health = mysqli_query($conn, "
    SELECT l.city_name, COUNT(c.id) as total_cars, SUM(CASE WHEN c.status = 'available' THEN 1 ELSE 0 END) as avail_cars
    FROM locations l LEFT JOIN cars c ON l.id = c.location_id
    GROUP BY l.id ORDER BY total_cars DESC LIMIT 4
");

// 11. Recent Bookings
$recent_bookings = mysqli_query($conn, "
    SELECT b.id, u.name as customer_name, c.name as car_name, b.final_price, b.booking_status, b.created_at 
    FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id
    ORDER BY b.created_at DESC LIMIT 6
");

// Dynamic Time Greeting
date_default_timezone_set('Asia/Kolkata');
$hour = date('H');
if ($hour < 12) { $greeting = "Good Morning"; $greeting_icon = "fa-sun text-warning"; }
elseif ($hour < 17) { $greeting = "Good Afternoon"; $greeting_icon = "fa-cloud-sun text-info"; }
else { $greeting = "Good Evening"; $greeting_icon = "fa-moon text-primary"; }

$page_title = "Command Center | Admin";
include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* 🎨 ENTERPRISE TEAL/MINT DASHBOARD STYLES (Strictly untouched palette) */
    :root {
        --teal-primary: #4da89c;
        --mint-secondary: #8bd0b4;
        --mint-pale: #ccecd4;
        --teal-dark: #1a2624;
    }

    body { background-color: #f4f7f6; }

    .admin-greeting {
        background: linear-gradient(135deg, var(--teal-primary) 0%, var(--teal-dark) 100%);
        color: white; border-radius: 24px; padding: 3.5rem 3rem;
        box-shadow: 0 20px 40px rgba(26, 38, 36, 0.2);
        position: relative; overflow: hidden;
    }
    .admin-greeting::after {
        content: '\f080'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: -20px; bottom: -60px; font-size: 18rem; color: rgba(255, 255, 255, 0.05);
        transform: rotate(-15deg); pointer-events: none;
    }

    .admin-stat-card {
        border-radius: 24px; border: 1px solid rgba(0,0,0,0.03);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        background: white; position: relative; overflow: hidden;
    }
    .admin-stat-card:hover { transform: translateY(-10px); box-shadow: 0 25px 50px rgba(77, 168, 156, 0.15); border-color: var(--mint-pale); }
    
    .stat-icon-wrapper {
        width: 60px; height: 60px; border-radius: 16px;
        display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
    }

    /* Custom Color Accents */
    .bg-teal-light { background-color: rgba(77, 168, 156, 0.15); color: var(--teal-primary); }
    .bg-blue-light { background-color: rgba(13, 110, 253, 0.12); color: #0d6efd; }
    .bg-orange-light { background-color: rgba(253, 126, 20, 0.12); color: #fd7e14; }
    .bg-red-light { background-color: rgba(220, 53, 69, 0.12); color: #dc3545; }
    .bg-purple-light { background-color: rgba(111, 66, 193, 0.12); color: #6f42c1; }

    /* Custom Progress Bars */
    .progress-custom { height: 8px; border-radius: 10px; background-color: var(--mint-pale); overflow: hidden; }
    .progress-teal { background-color: var(--teal-primary) !important; }

    .card-header-custom { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem; }
    .widget-list-item { transition: background 0.2s ease; border-radius: 12px; }
    .widget-list-item:hover { background-color: rgba(77, 168, 156, 0.05); }

    /* Table Enhancements */
    .table-hover tbody tr { transition: all 0.2s; }
    .table-hover tbody tr:hover { transform: scale(1.01); background-color: white; box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-radius: 10px; }

    /* Pulsing Alert */
    .pulse-alert { animation: pulse-red 2s infinite; border: 2px solid transparent; }
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }

    /* Data Refresh Toggle */
    .live-sync-btn { background: rgba(255,255,255,0.2); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.4); color: white; padding: 8px 16px; border-radius: 50px; font-size: 0.85rem; font-weight: bold; cursor: pointer; transition: all 0.3s; }
    .live-sync-btn.active { background: #198754; border-color: #198754; box-shadow: 0 0 15px rgba(25, 135, 84, 0.5); }
    .live-sync-btn i { transition: transform 1s; }
    .live-sync-btn.active i { animation: spin 2s linear infinite; }
    @keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<div class="dashboard-layout">
    
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content">
        
        <div class="admin-greeting d-flex justify-content-between align-items-center flex-wrap mb-4 shadow-lg" data-aos="zoom-in">
            <div style="z-index: 2;">
                <div class="d-flex align-items-center mb-3">
                    <span class="badge bg-white text-dark rounded-pill px-3 py-2 shadow-sm fw-bold me-3">
                        <i class="fas <?php echo $greeting_icon; ?> me-1"></i> <?php echo date('l, d F Y'); ?>
                    </span>
                    <button class="live-sync-btn" id="autoRefreshBtn" onclick="toggleAutoRefresh()">
                        <i class="fas fa-sync-alt me-1"></i> Live Sync: OFF
                    </button>
                </div>
                <h1 class="fw-bold mb-2"> <?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                
                <div class="mt-4" style="max-width: 500px;">
                    <div class="d-flex justify-content-between text-white fw-bold mb-2 small">
                        <span>Monthly Target: ₹<?php echo number_format($monthly_goal); ?></span>
                        <span><?php echo number_format($goal_progress, 1); ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                        <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" style="width: <?php echo $goal_progress; ?>%"></div>
                    </div>
                    <p class="mt-2 mb-0 text-light opacity-75 small fw-bold">Revenue this month: ₹<?php echo number_format($month_revenue); ?></p>
                </div>
            </div>
            
            <div class="mt-4 mt-lg-0 text-end" style="z-index: 2;">
                <div class="d-inline-block bg-white bg-opacity-10 p-4 rounded-4 border border-light border-opacity-25 backdrop-blur">
                    <p class="text-uppercase tracking-wide small text-light opacity-75 mb-1 fw-bold">MoM Growth</p>
                    <h2 class="fw-bold text-white mb-0">
                        <?php if($mom_growth > 0): ?>
                            <i class="fas fa-arrow-<?php echo $mom_direction; ?> text-<?php echo $mom_direction == 'up' ? 'success' : 'danger'; ?> me-2"></i>
                        <?php else: ?>
                            <i class="fas fa-minus text-warning me-2"></i>
                        <?php endif; ?>
                        <?php echo number_format($mom_growth, 1); ?>%
                    </h2>
                </div>
            </div>
        </div>

        <?php if(mysqli_num_rows($overdue_returns) > 0): ?>
            <div class="alert bg-white border-0 border-start border-danger border-5 shadow-sm rounded-4 mb-4 p-4 d-flex align-items-center pulse-alert" data-aos="fade-in">
                <div class="bg-danger bg-opacity-10 p-3 rounded-circle me-4 text-danger fs-3"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold text-danger mb-1">Critical Action Required: Overdue Vehicles</h5>
                    <p class="text-muted mb-0 small">There are currently <strong><?php echo mysqli_num_rows($overdue_returns); ?></strong> vehicles that have not been returned past their due date.</p>
                </div>
                <button class="btn btn-danger rounded-pill fw-bold px-4" type="button" data-bs-toggle="collapse" data-bs-target="#overdueList">View Details</button>
            </div>
            
            <div class="collapse mb-4" id="overdueList">
                <div class="card border-0 shadow-sm rounded-4 p-3 border-danger border-start border-2">
                    <?php while($overdue = mysqli_fetch_assoc($overdue_returns)): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 pt-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($overdue['brand'].' '.$overdue['car_name']); ?></h6>
                                <small class="text-danger fw-bold">Due: <?php echo date('d M Y', strtotime($overdue['end_date'])); ?></small>
                            </div>
                            <div class="text-end">
                                <span class="d-block text-muted small fw-bold"><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($overdue['customer']); ?></span>
                                <a href="tel:<?php echo htmlspecialchars($overdue['phone']); ?>" class="small text-decoration-none fw-bold"><i class="fas fa-phone-alt"></i> Call Client</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6">
                <div class="card admin-stat-card shadow-sm h-100 p-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold text-uppercase mb-0">Total Revenue</h6>
                        <div class="stat-icon-wrapper bg-teal-light"><i class="fas fa-wallet"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">₹<?php echo number_format($total_revenue, 0); ?></h3>
                    <small class="text-success fw-bold"><i class="fas fa-chart-line me-1"></i> AOV: ₹<?php echo number_format($aov, 0); ?></small>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card admin-stat-card shadow-sm h-100 p-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold text-uppercase mb-0">Pending Approvals</h6>
                        <div class="stat-icon-wrapper bg-orange-light"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?php echo $pending_count; ?> <span class="fs-6 text-muted fw-normal">Trips</span></h3>
                    <small class="text-warning text-dark fw-bold"><i class="fas fa-coins me-1"></i> ₹<?php echo number_format($pending_revenue, 0); ?> locked</small>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card admin-stat-card shadow-sm h-100 p-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold text-uppercase mb-0">Top Client (Whale)</h6>
                        <div class="stat-icon-wrapper bg-purple-light"><i class="fas fa-crown"></i></div>
                    </div>
                    <h4 class="fw-bold text-dark mb-1 text-truncate" title="<?php echo htmlspecialchars($top_customer); ?>"><?php echo htmlspecialchars($top_customer); ?></h4>
                    <small class="text-primary fw-bold"><i class="fas fa-gem me-1"></i> Lifetime: ₹<?php echo number_format($top_customer_spend, 0); ?></small>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card admin-stat-card shadow-sm h-100 p-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold text-uppercase mb-0">Fleet Utilization</h6>
                        <div class="stat-icon-wrapper bg-blue-light"><i class="fas fa-tachometer-alt"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?php echo $utilization_rate; ?>%</h3>
                    <div class="progress mt-2 progress-custom">
                        <div class="progress-bar progress-teal" style="width: <?php echo $utilization_rate; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="card admin-stat-card shadow-sm h-100 p-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold text-uppercase mb-0">Active Rides</h6>
                        <div class="stat-icon-wrapper" style="background:rgba(25,135,84,0.12);color:#198754;"><i class="fas fa-car-side"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?php echo $active_rides_count; ?> <span class="fs-6 text-muted fw-normal">On Road</span></h3>
                    <small class="text-success fw-bold"><i class="fas fa-road me-1"></i>Currently dispatched vehicles</small>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card admin-stat-card shadow-sm h-100 p-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold text-uppercase mb-0">Late Returns</h6>
                        <div class="stat-icon-wrapper bg-red-light"><i class="fas fa-clock"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1"><?php echo $late_return_count; ?> <span class="fs-6 text-muted fw-normal">Total</span></h3>
                    <a href="late_returns.php" class="small text-danger fw-bold text-decoration-none"><i class="fas fa-external-link-alt me-1"></i>View Late Returns Ledger</a>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card admin-stat-card shadow-sm h-100 p-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold text-uppercase mb-0">Penalty Revenue</h6>
                        <div class="stat-icon-wrapper bg-orange-light"><i class="fas fa-gavel"></i></div>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">₹<?php echo number_format($penalty_revenue, 0); ?></h3>
                    <small class="text-warning fw-bold"><i class="fas fa-coins me-1"></i>From late return penalties + GST</small>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-xl-8">
                <div class="card shadow-sm border-0 rounded-4 h-100 bg-white" data-aos="fade-up">
                    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0" style="color: var(--teal-dark);"><i class="fas fa-chart-area me-2" style="color: var(--teal-primary);"></i> Revenue Performance (<?php echo $current_year; ?>)</h5>
                    </div>
                    <div class="card-body p-4">
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm border-0 rounded-4 h-100 bg-white" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-header card-header-custom">
                        <h5 class="fw-bold mb-0" style="color: var(--teal-dark);"><i class="fas fa-car me-2" style="color: var(--teal-primary);"></i> Live Fleet Status</h5>
                    </div>
                    <div class="card-body p-4 d-flex flex-column justify-content-center align-items-center position-relative">
                        <canvas id="fleetChart" height="200"></canvas>
                        
                        <?php if($lost_daily_rev > 0): ?>
                            <div class="alert alert-danger border-0 mt-4 w-100 mb-0 py-2 text-center rounded-pill">
                                <small class="fw-bold"><i class="fas fa-exclamation-circle me-1"></i> Losing ₹<?php echo number_format($lost_daily_rev, 0); ?>/day to maintenance.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            
            <div class="col-xl-8">
                <div class="card shadow-sm border-0 rounded-4 h-100 bg-white" data-aos="fade-in">
                    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0" style="color: var(--teal-dark);"><i class="fas fa-history me-2" style="color: var(--teal-primary);"></i> Recent Transactions</h5>
                        <a href="manage_bookings.php" class="btn btn-sm rounded-pill fw-bold px-3 text-dark" style="background: var(--mint-pale);">View All Ledger</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 border-0">
                                <thead class="text-muted small text-uppercase" style="background: #f8f9fa;">
                                    <tr>
                                        <th class="ps-4 py-3 border-0">Client</th>
                                        <th class="py-3 border-0">Vehicle Rented</th>
                                        <th class="py-3 border-0 fw-bold">Gross Amount</th>
                                        <th class="py-3 border-0 text-center pe-4">Current Status</th>
                                    </tr>
                                </thead>
                                <tbody class="border-top-0">
                                    <?php if(mysqli_num_rows($recent_bookings) > 0): ?>
                                        <?php while($booking = mysqli_fetch_assoc($recent_bookings)): ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px;height:40px;">
                                                            <i class="fas fa-user text-muted"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($booking['customer_name']); ?></h6>
                                                            <small class="text-muted"><?php echo date('d M, Y', strtotime($booking['created_at'])); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3 text-muted fw-bold">
                                                    <i class="fas fa-car-side me-1 text-primary"></i> <?php echo htmlspecialchars($booking['car_name']); ?>
                                                </td>
                                                <td class="py-3 fw-bold text-dark">
                                                    ₹<?php echo number_format($booking['final_price'], 0); ?>
                                                </td>
                                                <td class="py-3 text-center pe-4">
                                                    <?php 
                                                        $bsl = get_booking_status_label($booking['booking_status']);
                                                        echo '<span class="badge ' . $bsl['badge_class'] . ' rounded-pill px-3"><i class="fas ' . $bsl['icon'] . ' me-1"></i>' . $bsl['label'] . '</span>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted">No recent bookings detected.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 d-flex flex-column gap-4">
                
                <div class="card shadow-sm border-0 rounded-4 bg-white flex-grow-1" data-aos="fade-in" data-aos-delay="100">
                    <div class="card-header card-header-custom">
                        <h5 class="fw-bold mb-0" style="color: var(--teal-dark);"><i class="fas fa-undo-alt me-2 text-warning"></i> Upcoming Returns</h5>
                    </div>
                    <div class="card-body p-3">
                        <?php if(mysqli_num_rows($upcoming_returns) > 0): ?>
                            <?php while($return = mysqli_fetch_assoc($upcoming_returns)): 
                                $today = new DateTime();
                                $return_date = new DateTime($return['end_date']);
                                $diff = $today->diff($return_date)->format("%r%a");
                                $badge_text = ($diff == 0) ? "Today" : (($diff == 1) ? "Tomorrow" : "In $diff Days");
                                $badge_color = ($diff == 0) ? "bg-danger" : "bg-warning text-dark";
                            ?>
                                <div class="widget-list-item d-flex justify-content-between align-items-center p-3 mb-2 border border-light bg-light rounded-4">
                                    <div>
                                        <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($return['brand'].' '.$return['car_name']); ?></h6>
                                        <small class="text-muted"><i class="fas fa-user me-1 text-primary"></i> <?php echo htmlspecialchars($return['customer']); ?> • <i class="fas fa-map-marker-alt ms-1 me-1 text-danger"></i> <?php echo htmlspecialchars($return['city_name']); ?></small>
                                    </div>
                                    <span class="badge <?php echo $badge_color; ?> rounded-pill shadow-sm"><?php echo $badge_text; ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-parking fa-3x mb-3 opacity-25"></i>
                                <p class="mb-0 fw-bold">No vehicles returning soon.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 bg-white" data-aos="fade-in" data-aos-delay="200">
                    <div class="card-header card-header-custom">
                        <h5 class="fw-bold mb-0" style="color: var(--teal-dark);"><i class="fas fa-building me-2" style="color: var(--teal-primary);"></i> Hub Capacity Health</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if(mysqli_num_rows($location_health) > 0): ?>
                            <?php while($loc = mysqli_fetch_assoc($location_health)): 
                                $total = $loc['total_cars'] > 0 ? $loc['total_cars'] : 1; 
                                $avail = $loc['avail_cars'] ? $loc['avail_cars'] : 0;
                                $percent = round(($avail / $total) * 100);
                                $p_color = ($percent < 20) ? 'bg-danger' : (($percent < 50) ? 'bg-warning' : 'progress-teal');
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-bold text-dark small"><i class="fas fa-map-pin text-muted me-1"></i> <?php echo htmlspecialchars($loc['city_name']); ?></span>
                                        <span class="small fw-bold text-muted"><?php echo $avail; ?> / <?php echo $loc['total_cars']; ?> Available</span>
                                    </div>
                                    <div class="progress rounded-pill bg-light" style="height: 8px;">
                                        <div class="progress-bar <?php echo $p_color; ?>" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted text-center fw-bold mb-0">No locations configured.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div> 
</div> 

<script>
    // 1. REVENUE LINE CHART
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    
    let gradientTeal = revCtx.createLinearGradient(0, 0, 0, 400);
    gradientTeal.addColorStop(0, 'rgba(77, 168, 156, 0.4)');
    gradientTeal.addColorStop(1, 'rgba(77, 168, 156, 0.0)');

    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Confirmed Revenue (₹)',
                data: [<?php echo $js_revenue_data; ?>], 
                borderColor: '#4da89c', 
                backgroundColor: gradientTeal,
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#4da89c',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4 
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(26, 38, 36, 0.95)',
                    titleFont: { size: 14, family: "'Plus Jakarta Sans', sans-serif" },
                    bodyFont: { size: 14, family: "'Plus Jakarta Sans', sans-serif", weight: 'bold' },
                    padding: 15,
                    displayColors: false,
                    callbacks: {
                        label: function(context) { return '₹' + context.parsed.y.toLocaleString('en-IN'); }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [5, 5], color: 'rgba(0,0,0,0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. FLEET STATUS DOUGHNUT CHART
    const fleetCtx = document.getElementById('fleetChart').getContext('2d');
    new Chart(fleetCtx, {
        type: 'doughnut',
        data: {
            labels: ['Available in Lot', 'Currently Rented', 'In Maintenance'],
            datasets: [{
                data: [<?php echo $fleet_avail; ?>, <?php echo $fleet_booked; ?>, <?php echo $fleet_maint; ?>],
                backgroundColor: ['#8bd0b4', '#4da89c', '#dc3545'],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '75%', 
            plugins: {
                legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, font: {family: "'Plus Jakarta Sans', sans-serif", weight: 'bold'} } },
                tooltip: { backgroundColor: 'rgba(26, 38, 36, 0.9)', bodyFont: { size: 14, family: "'Plus Jakarta Sans', sans-serif", weight: 'bold' }, padding: 12 }
            }
        }
    });

    // 3. AUTO-REFRESH COMMAND CENTER LOGIC
    let autoRefreshTimer = null;
    let isAutoRefresh = localStorage.getItem('adminAutoRefresh') === 'true';

    function initAutoRefresh() {
        const btn = document.getElementById('autoRefreshBtn');
        if (isAutoRefresh) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Live Sync: ON';
            autoRefreshTimer = setTimeout(() => { window.location.reload(); }, 60000); // 60 seconds
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Live Sync: OFF';
            clearTimeout(autoRefreshTimer);
        }
    }

    window.toggleAutoRefresh = function() {
        isAutoRefresh = !isAutoRefresh;
        localStorage.setItem('adminAutoRefresh', isAutoRefresh);
        initAutoRefresh();
    };

    // Run on load
    initAutoRefresh();
</script>

<?php include '../includes/footer.php'; ?>