<?php
session_start();

// 🛡️ SECURITY: Super Admin Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

// Safe check for notify functions
if (file_exists('../includes/notify.php')) {
    include_once '../includes/notify.php';
}

$message = '';
$msg_type = '';

// Check session for PRG (Post/Redirect/Get) toast messages
if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

// ==========================================
// 🏁 COMPLETE RIDE HANDLER (V2.0 — Auto Late Charge Calculator)
// ==========================================
if (isset($_POST['complete_ride'])) {
    $booking_id = intval($_POST['complete_booking_id']);
    $return_datetime_str = trim($_POST['return_datetime']);
    
    // Fetch booking details
    $bk_stmt = $conn->prepare("SELECT b.*, c.name as car_name, c.brand, u.name as customer_name, u.id as uid FROM bookings b JOIN cars c ON b.car_id = c.id JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $bk_stmt->bind_param("i", $booking_id);
    $bk_stmt->execute();
    $booking = $bk_stmt->get_result()->fetch_assoc();
    $bk_stmt->close();
    
    if ($booking && in_array($booking['booking_status'], ['confirmed', 'active'])) {
        // Get admin-configured rates
        $hourly_rate = floatval(get_system_setting($conn, 'late_hourly_rate', '300'));
        $gst_pct = floatval(get_system_setting($conn, 'gst_percentage', '18'));
        $grace_min = intval(get_system_setting($conn, 'grace_period_minutes', '60'));
        
        // Calculate due datetime (end_date + end_time)
        $end_time = $booking['end_time'] ?? '10:00:00';
        $due_datetime = new DateTime($booking['end_date'] . ' ' . $end_time);
        $return_datetime = new DateTime($return_datetime_str);
        
        // Calculate late charges
        $charges = calculate_late_charges($due_datetime, $return_datetime, $hourly_rate, $gst_pct, $grace_min);
        
        $extra = $charges['base_charge'];
        $gst_extra = $charges['gst_amount'];
        $total_penalty = $charges['total_penalty'];
        $late_hrs = $charges['chargeable_hours'];
        $late_mins = $charges['late_minutes'];
        $final_settlement = calculate_final_settlement($booking['final_price'], $extra, $gst_extra);
        
        // Update booking
        $upd = $conn->prepare("UPDATE bookings SET booking_status = 'completed', return_time = ?, late_hours = ?, extra_charges = ?, gst_on_extra = ?, final_settlement = ?, payment_status = 'settled' WHERE id = ?");
        $upd->bind_param("sidddi", $return_datetime_str, $late_hrs, $extra, $gst_extra, $final_settlement, $booking_id);
        $upd->execute();
        $upd->close();
        
        // If late, create late_returns ledger entry
        if ($charges['is_late'] && $charges['chargeable_hours'] > 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS late_returns (
                id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, due_datetime DATETIME NOT NULL, return_datetime DATETIME NOT NULL,
                late_minutes INT DEFAULT 0, chargeable_hours INT DEFAULT 0, hourly_rate DECIMAL(10,2) NOT NULL, grace_minutes INT DEFAULT 60,
                base_extra_charge DECIMAL(10,2) DEFAULT 0.00, gst_percentage DECIMAL(5,2) DEFAULT 18.00, gst_amount DECIMAL(10,2) DEFAULT 0.00,
                total_penalty DECIMAL(10,2) DEFAULT 0.00, admin_override TINYINT(1) DEFAULT 0, admin_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_booking (booking_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            $lr = $conn->prepare("INSERT INTO late_returns (booking_id, due_datetime, return_datetime, late_minutes, chargeable_hours, hourly_rate, grace_minutes, base_extra_charge, gst_percentage, gst_amount, total_penalty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $due_str = $due_datetime->format('Y-m-d H:i:s');
            $lr->bind_param("issiiididdd", $booking_id, $due_str, $return_datetime_str, $late_mins, $late_hrs, $hourly_rate, $grace_min, $extra, $gst_pct, $gst_extra, $total_penalty);
            $lr->execute();
            $lr->close();
        }
        
        // Notifications
        $car_full = $booking['brand'] . ' ' . $booking['car_name'];
        if (function_exists('notify_ride_completed')) {
            notify_ride_completed($conn, $booking['uid'], $booking_id, $car_full, ($total_penalty > 0));
        }
        
        $late_msg = $total_penalty > 0 ? " Late penalty of ₹" . number_format($total_penalty, 2) . " applied." : " No late charges.";
        $_SESSION['admin_msg'] = "Ride #BKG-$booking_id completed successfully!" . $late_msg;
        $_SESSION['admin_msg_type'] = "success";
    } else {
        $_SESSION['admin_msg'] = "Cannot complete this booking. Invalid status.";
        $_SESSION['admin_msg_type'] = "danger";
    }
    
    header("Location: manage_bookings.php");
    exit();
}

// ==========================================
// 🕒 OVERTIME & EXTRA CHARGES HANDLER (Legacy support)
// ==========================================
if (isset($_POST['add_overtime_charge'])) {
    $booking_id = intval($_POST['overtime_booking_id']);
    $extra_charge = floatval($_POST['overtime_base_charge']);
    
    $gst_pct = floatval(get_system_setting($conn, 'gst_percentage', '18'));
    $calculated_gst = round($extra_charge * ($gst_pct / 100), 2);
    $total_extra = $extra_charge + $calculated_gst;
    
    $update = mysqli_query($conn, "UPDATE bookings SET final_price = final_price + $total_extra WHERE id = $booking_id AND booking_status IN ('confirmed','active')");
    if (mysqli_affected_rows($conn) > 0) {
        $_SESSION['admin_msg'] = "Extra charge of ₹" . number_format($total_extra, 2) . " (incl. {$gst_pct}% GST) added to Booking #BKG-$booking_id.";
        $_SESSION['admin_msg_type'] = "success";
    } else {
        $_SESSION['admin_msg'] = "Action failed. Booking might not be active.";
        $_SESSION['admin_msg_type'] = "danger";
    }
    header("Location: manage_bookings.php");
    exit();
}

// ==========================================
// ⚡ QUICK ACTIONS ENGINE (Approve / Activate / Cancel / Delete)
// ==========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'approve') {
        // V2: Approve sets status to 'approved' — customer must pay to confirm
        $update = mysqli_query($conn, "UPDATE bookings SET booking_status = 'approved' WHERE id = $action_id AND booking_status = 'pending'");
        if (mysqli_affected_rows($conn) > 0) {
            $bk_query = mysqli_query($conn, "SELECT b.user_id, b.final_price, c.name as car_name, c.brand FROM bookings b JOIN cars c ON b.car_id = c.id WHERE b.id = $action_id");
            if ($bk = mysqli_fetch_assoc($bk_query)) {
                if(function_exists('notify_booking_approved')) {
                    notify_booking_approved($conn, $bk['user_id'], $action_id, $bk['brand'].' '.$bk['car_name'], $bk['final_price']);
                }
            }
            $_SESSION['admin_msg'] = "Booking #BKG-$action_id Approved! Customer notified to complete payment.";
            $_SESSION['admin_msg_type'] = "success";
        }
    } elseif ($action == 'mark_active') {
        // Transition confirmed → active (customer picked up the car)
        $update = mysqli_query($conn, "UPDATE bookings SET booking_status = 'active' WHERE id = $action_id AND booking_status = 'confirmed'");
        if (mysqli_affected_rows($conn) > 0) {
            $_SESSION['admin_msg'] = "Booking #BKG-$action_id is now Active! Vehicle dispatched.";
            $_SESSION['admin_msg_type'] = "success";
        }
    } elseif ($action == 'cancel') {
        $update = mysqli_query($conn, "UPDATE bookings SET booking_status = 'cancelled' WHERE id = $action_id AND booking_status NOT IN ('cancelled','completed')");
        if (mysqli_affected_rows($conn) > 0) {
            $bk_query = mysqli_query($conn, "SELECT b.user_id, c.name as car_name FROM bookings b JOIN cars c ON b.car_id = c.id WHERE b.id = $action_id");
            if ($bk = mysqli_fetch_assoc($bk_query)) {
                if(function_exists('notify_booking_cancelled')) notify_booking_cancelled($conn, $bk['user_id'], $action_id, $bk['car_name']);
            }
            $_SESSION['admin_msg'] = "Booking #BKG-$action_id has been Revoked & Cancelled.";
            $_SESSION['admin_msg_type'] = "warning";
        }
    } elseif ($action == 'delete') {
        $delete = mysqli_query($conn, "DELETE FROM bookings WHERE id = $action_id AND booking_status = 'cancelled'");
        if (mysqli_affected_rows($conn) > 0) {
            $_SESSION['admin_msg'] = "Booking record #BKG-$action_id permanently wiped.";
            $_SESSION['admin_msg_type'] = "secondary";
        } else {
            $_SESSION['admin_msg'] = "Cannot delete active bookings. Cancel it first.";
            $_SESSION['admin_msg_type'] = "danger";
        }
    }

    header("Location: manage_bookings.php");
    exit();
}

// ==========================================
// 📊 FETCH ANALYTICS FOR TOP CARDS
// ==========================================
$rev_query = mysqli_query($conn, "SELECT SUM(final_price) as total FROM bookings WHERE booking_status IN ('confirmed','active','completed')");
$total_revenue = mysqli_fetch_assoc($rev_query)['total'] ?? 0;

$pending_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE booking_status IN ('pending','approved')");
$pending_count = mysqli_fetch_assoc($pending_query)['count'] ?? 0;

$active_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE booking_status = 'active'");
$active_count = mysqli_fetch_assoc($active_query)['count'] ?? 0;

// ==========================================
// 🔍 ADVANCED SEARCH, FILTER & PAGINATION ENGINE
// ==========================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';

// Pagination Variables
$limit = 10; // Items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Dynamic WHERE Clause Builder
$where_sql = "WHERE 1=1 ";
if (!empty($search)) {
    // Advanced wildcard search across multiple tables
    $where_sql .= " AND (u.name LIKE '%$search%' OR b.id LIKE '%$search%' OR c.name LIKE '%$search%' OR u.phone LIKE '%$search%' OR u.email LIKE '%$search%') ";
}
if ($status_filter != 'all') {
    $where_sql .= " AND b.booking_status = '$status_filter' ";
}

// 1. Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id $where_sql";
$total_records = mysqli_fetch_assoc(mysqli_query($conn, $count_query))['total'];
$total_pages = ceil($total_records / $limit);

// 2. Fetch Paginated Records
$query = "
    SELECT b.id, b.start_date, b.end_date, b.total_days, b.final_price, b.booking_status, b.created_at,
           u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
           c.brand, c.name as car_name, c.base_price,
           l.city_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN cars c ON b.car_id = c.id
    LEFT JOIN locations l ON c.location_id = l.id
    $where_sql
    ORDER BY b.created_at DESC
    LIMIT $limit OFFSET $offset
";
$bookings_result = mysqli_query($conn, $query);

$page_title = "Manage Bookings | Admin";
include '../includes/header.php';
?>

<style>
    /* 🎨 ADMIN TEAL THEME */
    :root {
        --teal-primary: #4da89c;
        --mint-secondary: #8bd0b4;
        --mint-pale: #ccecd4;
        --teal-dark: #1a2624;
    }
    
    body { background-color: #f4f7f6; }

    /* Modern SaaS Cards */
    .admin-stat-card {
        border-radius: 24px; border: 1px solid rgba(0,0,0,0.03); background: white;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
        position: relative; overflow: hidden;
    }
    .admin-stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(77, 168, 156, 0.12); }
    
    .stat-icon-bg {
        position: absolute; right: -15px; bottom: -25px; font-size: 8rem; opacity: 0.04;
        transform: rotate(-15deg); pointer-events: none;
    }

    /* Floating Filter Bar */
    .filter-bar {
        background: white; border-radius: 20px; padding: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.04);
        border: 1px solid rgba(0,0,0,0.02);
    }
    .form-control-search { background-color: #f8f9fa; border: none; border-radius: 12px; padding: 12px 20px; font-weight: 600; color: var(--teal-dark); }
    .form-control-search:focus { outline: none; box-shadow: 0 0 0 4px rgba(77, 168, 156, 0.15); background-color: white; }

    /* Enterprise Table Styling */
    .table-container { background: white; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid rgba(0,0,0,0.02); }
    .table-beast { border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
    .table-beast th { background: #f8f9fa; color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 20px; font-weight: 800; }
    .table-beast tr { transition: all 0.2s; border-bottom: 1px solid #f1f1f1; }
    .table-beast tr:hover { background-color: rgba(77, 168, 156, 0.03); transform: scale(1.001); }
    .table-beast td { border: none; padding: 20px; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }

    /* Custom Status Badges */
    .status-badge { padding: 8px 16px; border-radius: 50px; font-weight: 700; font-size: 0.8rem; display: inline-flex; align-items: center; justify-content: center; }
    .status-confirmed { background-color: rgba(25, 135, 84, 0.1); color: #198754; border: 1px solid rgba(25, 135, 84, 0.2); }
    .status-pending { background-color: rgba(255, 193, 7, 0.15); color: #b07d00; border: 1px solid rgba(255, 193, 7, 0.3); }
    .status-cancelled { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; border: 1px solid rgba(220, 53, 69, 0.2); }

    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--teal-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--teal-primary); color: white; box-shadow: 0 5px 15px rgba(77, 168, 156, 0.4); }
    .pagination-custom .page-link:hover:not(.active) { background-color: var(--mint-pale); color: var(--teal-dark); }

    /* 🔔 Live Toast Animations */
    #admin-toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .admin-toast {
        background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        display: flex; align-items: center; gap: 15px; min-width: 320px;
        transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    .admin-toast.show { transform: translateX(0); opacity: 1; }
    .admin-toast.success { border-left: 5px solid #198754; }
    .admin-toast.warning { border-left: 5px solid #ffc107; }
    .admin-toast.danger { border-left: 5px solid #dc3545; }
    .admin-toast.secondary { border-left: 5px solid #6c757d; }

    /* 🖨️ Print Styles (For Export Report) */
    @media print {
        body { background: white !important; }
        .beast-sidebar, .filter-bar, .pagination-custom, .navbar, .top-utility-bar, .dropdown, button { display: none !important; }
        .dashboard-content { width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .table-container { box-shadow: none !important; border: none !important; }
        .table-beast th, .table-beast td { padding: 10px !important; border-bottom: 1px solid #ccc !important; }
        .admin-stat-card { break-inside: avoid; border: 1px solid #000; box-shadow: none !important; }
    }
</style>

<div id="admin-toast-container"></div>

<div class="dashboard-layout">
    
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content">
        
        <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-in">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>admin/dashboard.php" class="text-decoration-none fw-bold" style="color: var(--teal-primary);">Admin Panel</a></li>
                        <li class="breadcrumb-item active fw-bold text-muted" aria-current="page">Ledger</li>
                    </ol>
                </nav>
                <h2 class="fw-black text-dark mb-0">Reservation Control</h2>
            </div>
            <div>
                <button class="btn rounded-pill fw-bold shadow-sm text-white px-4 py-2" style="background-color: var(--teal-dark);" onclick="window.print()">
                    <i class="fas fa-print me-2"></i> Export PDF
                </button>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="0">
                <div class="admin-stat-card p-4 border-bottom border-5" style="border-bottom-color: var(--teal-primary) !important;">
                    <i class="fas fa-wallet stat-icon-bg" style="color: var(--teal-primary);"></i>
                    <h6 class="text-uppercase fw-bold text-muted mb-2 tracking-wide">Confirmed Revenue</h6>
                    <h2 class="fw-black mb-0" style="color: var(--teal-dark);">₹<?php echo number_format($total_revenue, 0); ?></h2>
                </div>
            </div>
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="admin-stat-card p-4 border-bottom border-5 border-warning">
                    <i class="fas fa-hourglass-half stat-icon-bg text-warning"></i>
                    <h6 class="text-uppercase fw-bold text-muted mb-2 tracking-wide">Pending Action</h6>
                    <div class="d-flex align-items-center">
                        <h2 class="fw-black mb-0 text-dark me-3"><?php echo $pending_count; ?></h2>
                        <?php if($pending_count > 0): ?><span class="badge bg-warning text-dark rounded-pill shadow-sm"><i class="fas fa-exclamation-circle me-1"></i> Review Needed</span><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="admin-stat-card p-4 border-bottom border-5 border-info">
                    <i class="fas fa-car-side stat-icon-bg text-info"></i>
                    <h6 class="text-uppercase fw-bold text-muted mb-2 tracking-wide">Active Fleet</h6>
                    <h2 class="fw-black mb-0 text-dark"><?php echo $active_count; ?> <span class="fs-6 text-muted fw-bold">Cars Rented</span></h2>
                </div>
            </div>
        </div>

        <div class="filter-bar mb-4" data-aos="fade-up" data-aos-delay="300">
            <form action="<?php echo $base_url; ?>admin/manage_bookings.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-search" name="search" placeholder="Search ID, Name, Email, or Car Model..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-control-search" name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>👍 Approved</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>✅ Confirmed</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>🚗 Active</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>🏁 Completed</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>❌ Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn text-white fw-bold rounded-3 shadow-sm py-2" style="background-color: var(--teal-primary);">Filter Ledger</button>
                </div>
            </form>
        </div>

        <div class="table-container mb-4" data-aos="fade-up" data-aos-delay="400">
            <div class="table-responsive">
                <table class="table table-beast">
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>Client Info</th>
                            <th>Vehicle & Duration</th>
                            <th>Revenue</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($bookings_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($bookings_result)): ?>
                                <tr>
                                    <td>
                                        <span class="fw-black text-dark fs-6">#BKG-<?php echo $row['id']; ?></span><br>
                                        <small class="text-muted fw-bold"><?php echo date('d M Y', strtotime($row['created_at'])); ?></small>
                                    </td>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['customer_name']); ?>&background=ccecd4&color=1a2624&bold=true" class="rounded-circle me-3" width="40">
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($row['customer_name']); ?></h6>
                                                <small class="text-muted fw-bold"><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($row['customer_email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <h6 class="mb-1 fw-bold text-dark"><i class="fas fa-car-side text-primary me-2"></i><?php echo htmlspecialchars($row['brand'] . ' ' . $row['car_name']); ?></h6>
                                        <small class="text-muted fw-bold bg-light px-2 py-1 rounded border">
                                            <?php echo date('d M', strtotime($row['start_date'])); ?> <i class="fas fa-arrow-right mx-1 text-muted" style="font-size: 0.6rem;"></i> <?php echo date('d M', strtotime($row['end_date'])); ?> (<?php echo $row['total_days']; ?> Days)
                                        </small>
                                    </td>
                                    
                                    <td class="fw-black fs-6" style="color: var(--teal-primary);">
                                        ₹<?php echo number_format($row['final_price'], 0); ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php 
                                            $sl = get_booking_status_label($row['booking_status']);
                                            echo '<span class="status-badge ' . $sl['badge_class'] . '"><i class="fas ' . $sl['icon'] . ' me-1"></i> ' . $sl['label'] . '</span>';
                                        ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-light rounded-circle shadow-sm" type="button" data-bs-toggle="dropdown" style="width: 35px; height: 35px;">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 overflow-hidden p-0">
                                                <li>
                                                    <a class="dropdown-item fw-bold py-3 border-bottom" href="javascript:void(0);" 
                                                       onclick="viewDetails('<?php echo $row['id']; ?>','<?php echo htmlspecialchars($row['customer_name']); ?>','<?php echo htmlspecialchars($row['customer_phone']); ?>','<?php echo htmlspecialchars($row['customer_email']); ?>','<?php echo htmlspecialchars($row['brand'].' '.$row['car_name']); ?>','<?php echo date('d M Y', strtotime($row['start_date'])); ?>','<?php echo date('d M Y', strtotime($row['end_date'])); ?>','<?php echo number_format($row['final_price'], 2); ?>','<?php echo $row['booking_status']; ?>')">
                                                        <i class="fas fa-eye text-primary me-2"></i> View Full Details
                                                    </a>
                                                </li>
                                                
                                                <?php if($row['booking_status'] == 'pending'): ?>
                                                    <li><a class="dropdown-item text-success fw-bold py-3 border-bottom" href="<?php echo $base_url; ?>admin/manage_bookings.php?action=approve&id=<?php echo $row['id']; ?>"><i class="fas fa-thumbs-up me-2"></i> Approve Booking</a></li>
                                                    <li><a class="dropdown-item text-danger fw-bold py-3" href="<?php echo $base_url; ?>admin/manage_bookings.php?action=cancel&id=<?php echo $row['id']; ?>" onclick="return confirm('Cancel this booking?');"><i class="fas fa-ban me-2"></i> Reject & Cancel</a></li>
                                                <?php elseif($row['booking_status'] == 'approved'): ?>
                                                    <li><a class="dropdown-item text-info fw-bold py-3 border-bottom" href="javascript:void(0);"><i class="fas fa-hourglass-half me-2"></i> Awaiting Payment</a></li>
                                                    <li><a class="dropdown-item text-danger fw-bold py-3" href="<?php echo $base_url; ?>admin/manage_bookings.php?action=cancel&id=<?php echo $row['id']; ?>" onclick="return confirm('Cancel this approved booking?');"><i class="fas fa-ban me-2"></i> Revoke Approval</a></li>
                                                <?php elseif($row['booking_status'] == 'confirmed'): ?>
                                                    <li><a class="dropdown-item fw-bold py-3 border-bottom" href="../customer/invoice.php?id=<?php echo $row['id']; ?>" target="_blank"><i class="fas fa-file-invoice text-info me-2"></i> View Invoice</a></li>
                                                    <li><a class="dropdown-item text-success fw-bold py-3 border-bottom" href="<?php echo $base_url; ?>admin/manage_bookings.php?action=mark_active&id=<?php echo $row['id']; ?>"><i class="fas fa-car-side me-2"></i> Mark Active (Dispatched)</a></li>
                                                    <li><a class="dropdown-item text-danger fw-bold py-3" href="<?php echo $base_url; ?>admin/manage_bookings.php?action=cancel&id=<?php echo $row['id']; ?>" onclick="return confirm('Revoke this confirmed booking?');"><i class="fas fa-times-circle me-2"></i> Revoke & Cancel</a></li>
                                                <?php elseif($row['booking_status'] == 'active'): ?>
                                                    <li><a class="dropdown-item fw-bold py-3 border-bottom" href="../customer/invoice.php?id=<?php echo $row['id']; ?>" target="_blank"><i class="fas fa-file-invoice text-info me-2"></i> View Invoice</a></li>
                                                    <li><a class="dropdown-item text-success fw-bold py-3 border-bottom" href="javascript:void(0);" onclick="openCompleteRideModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(addslashes($row['customer_name'])); ?>', '<?php echo $row['end_date']; ?>', '<?php echo $row['end_time'] ?? '10:00:00'; ?>')"><i class="fas fa-flag-checkered me-2"></i> Complete Ride</a></li>
                                                    <li><a class="dropdown-item fw-bold py-3 border-bottom text-warning" href="javascript:void(0);" onclick="openOvertimeModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars(addslashes($row['customer_name'])); ?>')"><i class="fas fa-clock me-2"></i> Add Manual Charge</a></li>
                                                <?php elseif($row['booking_status'] == 'completed'): ?>
                                                    <li><a class="dropdown-item fw-bold py-3 border-bottom" href="../customer/final_invoice.php?id=<?php echo $row['id']; ?>" target="_blank"><i class="fas fa-receipt text-success me-2"></i> Final Settlement</a></li>
                                                    <li><a class="dropdown-item fw-bold py-3" href="../customer/invoice.php?id=<?php echo $row['id']; ?>" target="_blank"><i class="fas fa-file-invoice text-info me-2"></i> Original Invoice</a></li>
                                                <?php else: ?>
                                                    <li><a class="dropdown-item text-secondary fw-bold py-3" href="<?php echo $base_url; ?>admin/manage_bookings.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this record permanently? This cannot be undone.');"><i class="fas fa-trash-alt me-2"></i> Wipe Record</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="opacity-25 mb-3"><i class="fas fa-search-minus fa-4x text-muted"></i></div>
                                    <h5 class="fw-bold text-dark">No Ledger Records Found</h5>
                                    <p class="text-muted small fw-bold">Try adjusting your filters or search criteria.</p>
                                    <a href="manage_bookings.php" class="btn rounded-pill fw-bold text-white px-4" style="background: var(--teal-primary);">Clear Filters</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation" data-aos="fade-up">
                <ul class="pagination pagination-custom justify-content-center">
                    <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php if($page <= 1){ echo '#'; } else { echo "?page=".($page - 1)."&search=$search&status=$status_filter"; } ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++ ): ?>
                        <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                            <a class="page-link" href="manage_bookings.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status_filter; ?>"> <?php echo $i; ?> </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php if($page >= $total_pages){ echo '#'; } else { echo "?page=".($page + 1)."&search=$search&status=$status_filter"; } ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div> 
</div> 

<div class="modal fade" id="bookingDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-white">
            <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative z-2">
                <h5 class="modal-title fw-black text-dark"><i class="fas fa-receipt me-2 text-muted"></i>Invoice #<span id="modal-id"></span></h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4 pt-3">
                <div class="d-flex align-items-center p-3 rounded-4 mb-4" style="background: rgba(77, 168, 156, 0.05); border: 1px solid rgba(77, 168, 156, 0.2);">
                    <div class="bg-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 50px; height: 50px;">
                        <i class="fas fa-user-tie fs-5" style="color: var(--teal-primary);"></i>
                    </div>
                    <div>
                        <h6 class="fw-black mb-1 text-dark" id="modal-name"></h6>
                        <div class="text-muted small fw-bold">
                            <span id="modal-email"></span> • <span id="modal-phone"></span>
                        </div>
                    </div>
                </div>
                
                <h6 class="text-uppercase text-muted fw-bold small mb-3 tracking-wide">Asset Deployed</h6>
                <div class="d-flex justify-content-between align-items-center border rounded-4 p-3 mb-4 bg-light">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark" id="modal-car"></h5>
                        <small class="text-muted fw-bold">Premium Tier</small>
                    </div>
                    <i class="fas fa-car-side fa-2x text-muted opacity-25"></i>
                </div>
                
                <div class="row text-center mb-4">
                    <div class="col-6 border-end">
                        <small class="text-muted text-uppercase fw-bold tracking-wide">Dispatch</small>
                        <h6 class="fw-bold mt-1 text-dark" id="modal-start"></h6>
                    </div>
                    <div class="col-6">
                        <small class="text-muted text-uppercase fw-bold tracking-wide">Return</small>
                        <h6 class="fw-bold mt-1 text-dark" id="modal-end"></h6>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center p-4 rounded-4" style="background: var(--teal-dark); color: white;">
                    <div>
                        <span class="text-uppercase fw-bold small opacity-75">Gross Revenue</span>
                        <div class="mt-1" id="modal-status-badge"></div>
                    </div>
                    <h2 class="fw-black mb-0 text-white">₹<span id="modal-price"></span></h2>
                </div>
            </div>
        </div>
</div>
</div>

<!-- Complete Ride Modal (V2.0) -->
<div class="modal fade" id="completeRideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-white">
            <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative z-2">
                <h5 class="modal-title fw-black text-dark"><i class="fas fa-flag-checkered text-success me-2"></i>Complete Ride</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <p class="text-muted small fw-bold mb-4">Enter the actual return date & time. The system will automatically calculate any late penalties based on configured rates.</p>
                <form action="manage_bookings.php" method="POST">
                    <input type="hidden" name="complete_booking_id" id="cr_booking_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Customer</label>
                        <input type="text" id="cr_customer_name" class="form-control bg-light fw-bold" readonly style="border-radius: 12px; padding: 12px;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Scheduled Return</label>
                        <input type="text" id="cr_due_datetime" class="form-control bg-light fw-bold text-muted" readonly style="border-radius: 12px; padding: 12px;">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Actual Return Date & Time</label>
                        <input type="datetime-local" name="return_datetime" id="cr_return_datetime" class="form-control fw-bold" required style="border-radius: 12px; padding: 12px; border: 2px solid rgba(77,168,156,0.2);" onchange="previewLateCharges()">
                    </div>
                    
                    <div id="cr_preview" class="d-none mb-4 p-3 rounded-4 border" style="background: #f8faf9;">
                        <h6 class="fw-bold text-dark mb-3"><i class="fas fa-calculator me-2" style="color: var(--teal-primary);"></i>Charge Preview</h6>
                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted fw-bold">Late Duration</span><span class="fw-bold text-dark" id="cr_late_dur">—</span></div>
                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted fw-bold">Chargeable Hours</span><span class="fw-bold text-dark" id="cr_charge_hrs">—</span></div>
                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted fw-bold">Base Charge</span><span class="fw-bold text-dark" id="cr_base">—</span></div>
                        <div class="d-flex justify-content-between mb-1 small"><span class="text-muted fw-bold">GST</span><span class="fw-bold text-dark" id="cr_gst">—</span></div>
                        <hr class="my-2 opacity-10">
                        <div class="d-flex justify-content-between"><span class="fw-black text-dark">Total Penalty</span><span class="fw-black" style="color: var(--teal-primary);" id="cr_total">₹0.00</span></div>
                    </div>
                    
                    <button type="submit" name="complete_ride" class="btn text-white fw-bold rounded-pill shadow-sm w-100 py-3" style="background: linear-gradient(135deg, var(--teal-primary) 0%, var(--teal-dark) 100%);">
                        <i class="fas fa-check-circle me-2"></i>Complete & Settle Ride
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Extra Charges Modal (Manual Override) -->
<div class="modal fade" id="overtimeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-white">
            <div class="modal-header border-0 pb-0 pt-4 px-4 position-relative z-2">
                <h5 class="modal-title fw-black text-dark"><i class="fas fa-clock text-warning me-2"></i>Manual Extra Charge</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <p class="text-muted small fw-bold mb-4">Manually add an extra base charge. GST will be auto-calculated using the admin-configured rate.</p>
                <form action="manage_bookings.php" method="POST">
                    <input type="hidden" name="overtime_booking_id" id="ot_booking_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Customer</label>
                        <input type="text" id="ot_customer_name" class="form-control bg-light fw-bold" readonly style="border-radius: 12px; padding: 12px;">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Extra Base Charge (₹)</label>
                        <input type="number" name="overtime_base_charge" id="ot_base_charge" class="form-control" step="0.01" min="1" required oninput="calculateGST()" placeholder="e.g. 500" style="border-radius: 12px; padding: 12px; border: 2px solid rgba(77,168,156,0.2);">
                    </div>
                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">GST (<?php echo get_system_setting($conn, 'gst_percentage', '18'); ?>%)</label>
                            <input type="text" id="ot_gst_show" class="form-control bg-light text-muted fw-bold" readonly value="₹0.00" style="border-radius: 12px; padding: 12px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-black small text-uppercase" style="color: var(--teal-primary);">Total Fine</label>
                            <input type="text" id="ot_total_show" class="form-control bg-light fw-black border-success text-success" readonly value="₹0.00" style="border-radius: 12px; padding: 12px;">
                        </div>
                    </div>
                    <button type="submit" name="add_overtime_charge" class="btn text-white fw-bold rounded-pill shadow-sm w-100 py-3" style="background-color: var(--teal-dark);">
                        Apply Extra Charges
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. BEAST MODE TOAST NOTIFICATION ENGINE (Replaces standard alerts)
    <?php if($message): ?>
        document.addEventListener("DOMContentLoaded", function() {
            const toastContainer = document.getElementById('admin-toast-container');
            const type = '<?php echo $msg_type; ?>';
            const msg = '<?php echo addslashes($message); ?>';
            
            let icon = 'fa-info-circle';
            if(type === 'success') icon = 'fa-check-circle text-success';
            if(type === 'warning') icon = 'fa-exclamation-triangle text-warning';
            if(type === 'danger') icon = 'fa-times-circle text-danger';

            const toast = document.createElement('div');
            toast.className = `admin-toast ${type}`;
            toast.innerHTML = `
                <i class="fas ${icon} fs-4"></i>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">System Update</h6>
                    <small class="text-muted fw-bold">${msg}</small>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }, 5000);
        });
    <?php endif; ?>

    // 2. MODAL DATA POPULATOR
    function viewDetails(id, name, phone, email, car, start, end, price, status) {
        document.getElementById('modal-id').innerText = id;
        document.getElementById('modal-name').innerText = name;
        document.getElementById('modal-phone').innerText = phone;
        document.getElementById('modal-email').innerText = email;
        document.getElementById('modal-car').innerText = car;
        document.getElementById('modal-start').innerText = start;
        document.getElementById('modal-end').innerText = end;
        document.getElementById('modal-price').innerText = price;
        
        const badgeMap = {
            'pending':   '<span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">Pending</span>',
            'approved':  '<span class="badge bg-info rounded-pill px-3 py-1 fw-bold">Approved</span>',
            'confirmed': '<span class="badge bg-primary rounded-pill px-3 py-1 fw-bold">Confirmed</span>',
            'active':    '<span class="badge bg-success rounded-pill px-3 py-1 fw-bold">Active</span>',
            'completed': '<span class="badge bg-secondary rounded-pill px-3 py-1 fw-bold">Completed</span>',
            'cancelled': '<span class="badge bg-danger rounded-pill px-3 py-1 fw-bold">Cancelled</span>',
        };
        document.getElementById('modal-status-badge').innerHTML = badgeMap[status] || badgeMap['pending'];
        
        var detailModal = new bootstrap.Modal(document.getElementById('bookingDetailsModal'));
        detailModal.show();
    }

    // 3. COMPLETE RIDE MODAL
    let crDueDateTime = null;
    const crHourlyRate = <?php echo floatval(get_system_setting($conn, 'late_hourly_rate', '300')); ?>;
    const crGstPct = <?php echo floatval(get_system_setting($conn, 'gst_percentage', '18')); ?>;
    const crGraceMin = <?php echo intval(get_system_setting($conn, 'grace_period_minutes', '60')); ?>;

    function openCompleteRideModal(id, name, endDate, endTime) {
        document.getElementById('cr_booking_id').value = id;
        document.getElementById('cr_customer_name').value = name;
        crDueDateTime = new Date(endDate + 'T' + endTime);
        document.getElementById('cr_due_datetime').value = crDueDateTime.toLocaleString('en-IN', {dateStyle: 'medium', timeStyle: 'short'});
        // Default return to now
        const now = new Date();
        const localISO = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        document.getElementById('cr_return_datetime').value = localISO;
        document.getElementById('cr_preview').classList.add('d-none');
        previewLateCharges();
        new bootstrap.Modal(document.getElementById('completeRideModal')).show();
    }

    function previewLateCharges() {
        const returnStr = document.getElementById('cr_return_datetime').value;
        if (!returnStr || !crDueDateTime) return;
        const returnDT = new Date(returnStr);
        const preview = document.getElementById('cr_preview');
        
        if (returnDT <= crDueDateTime) {
            preview.classList.add('d-none');
            return;
        }
        
        preview.classList.remove('d-none');
        const diffMs = returnDT - crDueDateTime;
        const totalLateMin = Math.floor(diffMs / 60000);
        const hrs = Math.floor(totalLateMin / 60);
        const mins = totalLateMin % 60;
        
        document.getElementById('cr_late_dur').textContent = `${hrs}h ${mins}m`;
        
        if (totalLateMin <= crGraceMin) {
            document.getElementById('cr_charge_hrs').textContent = '0 (within grace)';
            document.getElementById('cr_base').textContent = '₹0.00';
            document.getElementById('cr_gst').textContent = '₹0.00';
            document.getElementById('cr_total').textContent = '₹0.00 (No charge!)';
            return;
        }
        
        const chargeMin = totalLateMin - crGraceMin;
        const chargeHrs = Math.ceil(chargeMin / 60);
        const baseCharge = chargeHrs * crHourlyRate;
        const gstAmt = baseCharge * (crGstPct / 100);
        const total = baseCharge + gstAmt;
        
        document.getElementById('cr_charge_hrs').textContent = `${chargeHrs} hrs`;
        document.getElementById('cr_base').textContent = '₹' + baseCharge.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('cr_gst').textContent = '₹' + gstAmt.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('cr_total').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2});
    }

    // 4. OVERTIME GST CALCULATOR (Manual Override)
    function openOvertimeModal(id, name) {
        document.getElementById('ot_booking_id').value = id;
        document.getElementById('ot_customer_name').value = name;
        document.getElementById('ot_base_charge').value = '';
        calculateGST();
        new bootstrap.Modal(document.getElementById('overtimeModal')).show();
    }

    function calculateGST() {
        let base = parseFloat(document.getElementById('ot_base_charge').value) || 0;
        let gst = base * (crGstPct / 100);
        let total = base + gst;
        document.getElementById('ot_gst_show').value = '₹' + gst.toFixed(2);
        document.getElementById('ot_total_show').value = '₹' + total.toFixed(2);
    }
</script>

<?php include '../includes/footer.php'; ?>