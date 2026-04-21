<?php
session_start();

// 🛡️ SECURITY CHECK: Customer Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

$user_id    = (int)$_SESSION['user_id'];
$user_name  = $_SESSION['user_name'] ?? 'User';
$first_name = explode(' ', trim($user_name))[0];

// ==========================================
// 📊 HIGH-PERFORMANCE KPI AGGREGATION
// ==========================================
// Combined into a single prepared statement for speed
$kpi_stmt = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN booking_status != 'cancelled' THEN 1 END) as total_trips,
        COALESCE(SUM(CASE WHEN booking_status IN ('confirmed','active','completed') THEN final_price ELSE 0 END), 0) as total_spent,
        COALESCE(SUM(CASE WHEN booking_status IN ('confirmed','active','completed') THEN total_days ELSE 0 END), 0) as total_days
    FROM bookings 
    WHERE user_id = ?
");
$kpi_stmt->bind_param("i", $user_id);
$kpi_stmt->execute();
$kpi_stats = $kpi_stmt->get_result()->fetch_assoc();
$kpi_stmt->close();

$total_trips = $kpi_stats['total_trips'];
$total_spent = $kpi_stats['total_spent'];
$total_days  = $kpi_stats['total_days'];

// Favorite Car (Prepared Statement)
$fav_stmt = $conn->prepare("
    SELECT c.brand, c.name, COUNT(b.id) as cnt
    FROM bookings b JOIN cars c ON b.car_id = c.id
    WHERE b.user_id = ? AND b.booking_status IN ('confirmed','active','completed')
    GROUP BY b.car_id ORDER BY cnt DESC LIMIT 1
");
$fav_stmt->bind_param("i", $user_id);
$fav_stmt->execute();
$fav_car_row = $fav_stmt->get_result()->fetch_assoc();
$fav_stmt->close();
$fav_car = $fav_car_row ? $fav_car_row['brand'] . ' ' . $fav_car_row['name'] : 'N/A';

// Loyalty Tier Engine
$loyalty_stmt = $conn->prepare("SELECT loyalty_points FROM users WHERE id = ?");
$loyalty_stmt->bind_param("i", $user_id);
$loyalty_stmt->execute();
$loyalty_points = $loyalty_stmt->get_result()->fetch_assoc()['loyalty_points'] ?? 0;
$loyalty_stmt->close();

$tier = 'Silver'; $tier_color = '#6c757d';
if ($loyalty_points >= 500)  { $tier = 'Gold';     $tier_color = '#ffc107'; }
if ($loyalty_points >= 1500) { $tier = 'Platinum'; $tier_color = '#0dcaf0'; }

// ==========================================
// 🔍 SECURE DYNAMIC SEARCH & PAGINATION ENGINE
// ==========================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_filter   = isset($_GET['sort'])   ? $_GET['sort']   : 'newest';
$search_query  = isset($_GET['search']) ? trim($_GET['search']) : '';

$limit = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Build Base Query
$where_sql = "WHERE b.user_id = ?";
$types = "i";
$params = [$user_id];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'approved', 'confirmed', 'active', 'completed', 'cancelled'])) {
    $where_sql .= " AND b.booking_status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $where_sql .= " AND (c.brand LIKE ? OR c.name LIKE ? OR l.city_name LIKE ? OR b.id LIKE ?)";
    $search_term = "%{$search_query}%";
    $types .= "ssss";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

// 1. Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM bookings b JOIN cars c ON b.car_id = c.id LEFT JOIN locations l ON c.location_id = l.id $where_sql";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();
$total_pages = ceil($total_results / $limit);

// 2. Fetch Paginated Records
$order_map = [
    'newest'     => 'b.id DESC',
    'oldest'     => 'b.id ASC',
    'price_high' => 'b.final_price DESC',
    'price_low'  => 'b.final_price ASC',
    'duration'   => 'b.total_days DESC',
];
$order_by = $order_map[$sort_filter] ?? 'b.id DESC';

$data_query = "
    SELECT b.id, b.start_date, b.end_date, b.total_days, b.final_price, b.booking_status, b.created_at,
           c.brand, c.name as car_name, c.model, c.image, c.base_price,
           l.city_name
    FROM bookings b
    JOIN cars c ON b.car_id = c.id
    LEFT JOIN locations l ON c.location_id = l.id
    $where_sql
    ORDER BY $order_by 
    LIMIT ? OFFSET ?
";

$types .= "ii";
array_push($params, $limit, $offset);

$data_stmt = $conn->prepare($data_query);
$data_stmt->bind_param($types, ...$params);
$data_stmt->execute();
$bookings_result = $data_stmt->get_result();

// ==========================================
// 📸 SMART CAR IMAGE RENDERER (Multi-Image Support)
// ==========================================
function getHistoryCarImage($db_image, $brand, $base_url) {
    // Check if it's a JSON array (from the multi-image update)
    if (!empty($db_image)) {
        $decoded = json_decode($db_image, true);
        if (is_array($decoded) && isset($decoded[0]) && file_exists('../' . $decoded[0])) {
            return $base_url . $decoded[0];
        } elseif (file_exists('../' . $db_image)) {
            return $base_url . $db_image;
        }
    }
    // Fallbacks
    $images = [
        'porsche'    => 'https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=600',
        'bmw'        => 'https://images.unsplash.com/photo-1556189250-72ba954cfc2b?q=80&w=600',
        'audi'       => 'https://images.unsplash.com/photo-1606152421802-db97b9c7a11b?q=80&w=600',
        'range rover'=> 'https://images.unsplash.com/photo-1606016159991-d8532e856086?q=80&w=600',
        'honda'      => 'https://images.unsplash.com/photo-1590362891991-f776e747a588?q=80&w=600',
        'mercedes'   => 'https://images.unsplash.com/photo-1610880846497-7257b23f6128?q=80&w=600',
        'toyota'     => 'https://images.unsplash.com/photo-1559416523-140ddc3d238c?q=80&w=600',
    ];
    return $images[strtolower(trim($brand))] ?? 'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?q=80&w=600';
}

$page_title = "Rental History | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* ==========================================
       🌿 RENTAL HISTORY — SAGE GREEN THEME
    ========================================== */
    :root {
        --sage-dark:  #4a5c43;
        --sage-mid:   #889c7c;
        --sage-pale:  #e0eadb;
        --sage-bg:    #f4f5f3;
        --sage-deep:  #2b3327;
        --shadow-soft: 0 4px 20px rgba(0,0,0,0.04);
        --shadow-hover: 0 15px 35px rgba(74,92,67,0.12);
    }

    body { background-color: var(--sage-bg); }

    /* 🦴 Skeleton Loading Engine */
    .skeleton-box { position: relative; overflow: hidden; background-color: #e2e8f0; color: transparent !important; border-color: transparent !important; user-select: none; pointer-events: none; border-radius: 12px; }
    .skeleton-box * { visibility: hidden; }
    .skeleton-box::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.6) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 100% { transform: translateX(100%); } }

    /* ---- PAGE HERO ---- */
    .history-hero {
        background: linear-gradient(135deg, var(--sage-deep) 0%, var(--sage-dark) 100%);
        border-radius: 24px; padding: 3.5rem 2.5rem; position: relative; overflow: hidden; color: white;
        box-shadow: 0 20px 40px rgba(43,51,39,.2);
    }
    .history-hero::after {
        content: '\f1b9'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: -30px; bottom: -60px; font-size: 18rem;
        color: rgba(255,255,255,.04); transform: rotate(-15deg); pointer-events: none;
    }

    /* ---- KPI CARDS ---- */
    .kpi-card {
        background: white; border: none; border-radius: 20px; padding: 25px;
        box-shadow: var(--shadow-soft); transition: transform .35s ease, box-shadow .35s ease;
        border-bottom: 4px solid transparent; height: 100%;
    }
    .kpi-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); }
    .kpi-card.green  { border-bottom-color: var(--sage-dark); }
    .kpi-card.gold   { border-bottom-color: #ffc107; }
    .kpi-card.blue   { border-bottom-color: #0dcaf0; }
    .kpi-card.purple { border-bottom-color: #6f42c1; }

    .kpi-icon { width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }

    /* ---- FILTER BAR ---- */
    .filter-bar { background: white; border-radius: 20px; padding: 1.5rem; box-shadow: var(--shadow-soft); border: 1px solid rgba(136,156,124,.15); }
    .filter-pill {
        border: 2px solid var(--sage-pale); border-radius: 50px; padding: 8px 20px; font-weight: 700;
        font-size: .85rem; color: var(--sage-dark); background: white; cursor: pointer; transition: all .2s ease;
        text-decoration: none; display: inline-flex; align-items: center;
    }
    .filter-pill:hover, .filter-pill.active { background: var(--sage-dark); border-color: var(--sage-dark); color: white; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(74,92,67,.2); }
    .filter-pill.active-warning { background: #ffc107; border-color: #ffc107; color: #212529; }
    .filter-pill.active-danger  { background: #dc3545; border-color: #dc3545; color: white; }

    .form-control-sage { background: #f8f9f7; border: 2px solid transparent; border-radius: 12px; padding: 12px 18px; transition: all .3s; font-weight: 600; color: var(--sage-dark); }
    .form-control-sage:focus { border-color: var(--sage-mid); box-shadow: 0 0 0 .25rem rgba(136,156,124,.2); background: white; outline: none; }

    /* ---- BOOKING CARDS ---- */
    .booking-card {
        background: white; border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-soft);
        border: 1px solid rgba(136,156,124,.12); transition: all .4s cubic-bezier(.175,.885,.32,1.275);
        display: flex; flex-direction: column; height: 100%;
    }
    .booking-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-hover); border-color: var(--sage-mid); }
    .img-wrapper { position: relative; overflow: hidden; height: 220px; }
    .booking-card-img { height: 100%; width: 100%; object-fit: cover; transition: transform .6s ease; }
    .booking-card:hover .booking-card-img { transform: scale(1.08); }

    /* Status Badges */
    .badge-confirmed { background: rgba(74,92,67,.12); color: var(--sage-dark); border: 1px solid rgba(74,92,67,.25); }
    .badge-pending   { background: rgba(255,193,7,.15); color: #856404; border: 1px solid rgba(255,193,7,.3); }
    .badge-cancelled { background: rgba(220,53,69,.1);  color: #dc3545;  border: 1px solid rgba(220,53,69,.25); }

    /* Progress Bar */
    .trip-progress { height: 6px; border-radius: 10px; background: var(--sage-pale); overflow: hidden; }
    .trip-progress-fill { height: 100%; background: linear-gradient(90deg, var(--sage-dark), var(--sage-mid)); border-radius: 10px; transition: width 1s ease; }

    /* Empty State */
    .empty-state { background: white; border-radius: 24px; padding: 5rem 2rem; text-align: center; box-shadow: var(--shadow-soft); border: 2px dashed var(--sage-pale); }
    
    /* Buttons */
    .btn-sage { background: var(--sage-dark); color: white; border: none; transition: all .3s; font-weight: bold; }
    .btn-sage:hover { background: var(--sage-deep); color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(74,92,67,.3); }

    .results-strip { background: white; border-radius: 50px; padding: 10px 25px; display: inline-flex; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,.05); border: 1px solid var(--sage-pale); font-weight: 600; }

    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--sage-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--sage-dark); color: white; box-shadow: 0 5px 15px rgba(74, 92, 67, 0.4); }
</style>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="dashboard-content">

        <div class="history-hero mb-5 load-anim skeleton-box" data-aos="fade-up">
            <div class="row align-items-center">
                <div class="col-lg-8" style="z-index:2; position:relative;">
                    <span class="badge bg-white text-dark rounded-pill px-4 py-2 mb-3 shadow-sm fw-bold tracking-wide" style="color: var(--sage-dark) !important;">
                        <i class="fas fa-history me-2" style="color:var(--sage-mid);"></i>Trip Chronicle
                    </span>
                    <h1 class="display-4 fw-black mb-2">Your Rental History</h1>
                    <p class="fs-5 opacity-75 mb-0 fw-bold">Every journey you've taken with SmartDrive X — all in one secure ledger.</p>
                </div>
                <div class="col-lg-4 mt-4 mt-lg-0 text-lg-end" style="z-index:2; position:relative;">
                    <div class="d-inline-block p-4 rounded-4 shadow-sm" style="background:rgba(255,255,255,.07); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.12);">
                        <div class="small fw-bold text-uppercase opacity-75 mb-1 tracking-widest">Membership Tier</div>
                        <h3 class="fw-black mb-0" style="color: <?php echo $tier_color; ?>;">
                            <i class="fas fa-<?php echo $tier === 'Platinum' ? 'crown' : ($tier === 'Gold' ? 'star' : 'medal'); ?> me-2"></i><?php echo $tier; ?>
                        </h3>
                        <small class="opacity-75 fw-bold"><?php echo number_format($loyalty_points); ?> Loyalty Points</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="0">
                <div class="kpi-card green load-anim skeleton-box">
                    <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="fas fa-road"></i></div>
                    <h6 class="text-muted fw-bold text-uppercase small mb-1 tracking-wide">Total Trips</h6>
                    <h2 class="fw-black mb-0"><?php echo $total_trips; ?></h2>
                </div>
            </div>
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="kpi-card gold load-anim skeleton-box">
                    <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-wallet"></i></div>
                    <h6 class="text-muted fw-bold text-uppercase small mb-1 tracking-wide">Total Spent</h6>
                    <h2 class="fw-black mb-0" style="color: var(--sage-dark);">₹<?php echo number_format($total_spent, 0); ?></h2>
                </div>
            </div>
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="kpi-card blue load-anim skeleton-box">
                    <div class="kpi-icon bg-info bg-opacity-10 text-info"><i class="fas fa-calendar-day"></i></div>
                    <h6 class="text-muted fw-bold text-uppercase small mb-1 tracking-wide">Days on Road</h6>
                    <h2 class="fw-black mb-0"><?php echo $total_days; ?></h2>
                </div>
            </div>
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="kpi-card purple load-anim skeleton-box">
                    <div class="kpi-icon" style="background:rgba(111,66,193,.1); color:#6f42c1;"><i class="fas fa-heart"></i></div>
                    <h6 class="text-muted fw-bold text-uppercase small mb-1 tracking-wide">Favourite Car</h6>
                    <h5 class="fw-black mb-0 text-truncate" title="<?php echo htmlspecialchars($fav_car); ?>" style="color:#6f42c1;">
                        <?php echo htmlspecialchars($fav_car); ?>
                    </h5>
                </div>
            </div>
        </div>

        <div class="filter-bar mb-4 load-anim skeleton-box" data-aos="fade-up">
            <form method="GET" action="rental_history.php" id="filterForm">
                <div class="row g-4 align-items-center">

                    <div class="col-lg-4">
                        <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                            <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control form-control-sage border-0 ps-0"
                                   placeholder="Search by car, city, or ID..."
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="?status=all&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                               class="filter-pill <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                               <i class="fas fa-list me-1"></i> All
                            </a>
                            <a href="?status=pending&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                               class="filter-pill <?php echo $status_filter === 'pending' ? 'active-warning' : ''; ?>">
                               <i class="fas fa-clock me-1"></i> Pending
                            </a>
                            <a href="?status=approved&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                               class="filter-pill <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" style="<?php echo $status_filter === 'approved' ? 'background:#0dcaf0;border-color:#0dcaf0;color:white;' : ''; ?>">
                               <i class="fas fa-thumbs-up me-1"></i> Approved
                            </a>
                            <a href="?status=confirmed&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                               class="filter-pill <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                               <i class="fas fa-check-double me-1"></i> Confirmed
                            </a>
                            <a href="?status=active&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                               class="filter-pill <?php echo $status_filter === 'active' ? 'active' : ''; ?>" style="<?php echo $status_filter === 'active' ? 'background:#198754;border-color:#198754;color:white;' : ''; ?>">
                               <i class="fas fa-car-side me-1"></i> Active
                            </a>
                            <a href="?status=completed&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                               class="filter-pill <?php echo $status_filter === 'completed' ? 'active' : ''; ?>" style="<?php echo $status_filter === 'completed' ? 'background:#6c757d;border-color:#6c757d;color:white;' : ''; ?>">
                               <i class="fas fa-flag-checkered me-1"></i> Completed
                            </a>
                            <a href="?status=cancelled&sort=<?php echo $sort_filter; ?>&search=<?php echo urlencode($search_query); ?>"
                               class="filter-pill <?php echo $status_filter === 'cancelled' ? 'active-danger' : ''; ?>">
                               <i class="fas fa-times-circle me-1"></i> Cancelled
                            </a>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="d-flex gap-2 align-items-center">
                            <label class="fw-bold text-muted small text-nowrap text-uppercase">Sort by:</label>
                            <select name="sort" class="form-select form-control-sage shadow-sm" onchange="this.form.submit()">
                                <option value="newest"     <?php echo $sort_filter==='newest'     ? 'selected':''; ?>>Newest First</option>
                                <option value="oldest"     <?php echo $sort_filter==='oldest'     ? 'selected':''; ?>>Oldest First</option>
                                <option value="price_high" <?php echo $sort_filter==='price_high' ? 'selected':''; ?>>Highest Price</option>
                                <option value="price_low"  <?php echo $sort_filter==='price_low'  ? 'selected':''; ?>>Lowest Price</option>
                                <option value="duration"   <?php echo $sort_filter==='duration'   ? 'selected':''; ?>>Longest Trip</option>
                            </select>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 load-anim skeleton-box" data-aos="fade-up">
            <div class="results-strip">
                <i class="fas fa-car text-muted me-2"></i>
                <span class="fw-black text-dark"><?php echo $total_results; ?></span>
                <span class="text-muted ms-1">
                    booking<?php echo $total_results !== 1 ? 's' : ''; ?> found
                    <?php if ($status_filter !== 'all'): ?>
                        <span class="badge rounded-pill ms-2" style="background: var(--sage-pale); color: var(--sage-dark);">
                            <?php echo ucfirst($status_filter); ?>
                        </span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($search_query) || $status_filter !== 'all'): ?>
                <a href="rental_history.php" class="btn btn-outline-secondary rounded-pill btn-sm fw-bold px-3">
                    <i class="fas fa-times me-1"></i> Clear Filters
                </a>
            <?php endif; ?>
        </div>

        <?php if ($total_results > 0): ?>
            <div class="row g-5">
                <?php while ($booking = $bookings_result->fetch_assoc()):
                    $img_src = getHistoryCarImage($booking['image'], $booking['brand'], $base_url);

                    // Compute trip progress
                    $today      = new DateTime();
                    $start      = new DateTime($booking['start_date']);
                    $end        = new DateTime($booking['end_date']);
                    $created    = new DateTime($booking['created_at']);

                    $total_trip_days = max(1, $start->diff($end)->days);
                    $days_elapsed    = max(0, min($total_trip_days, $today->diff($start)->days));
                    $progress_pct    = min(100, round(($days_elapsed / $total_trip_days) * 100));

                    // Status helpers — V2 unified badge system
                    $s = $booking['booking_status'];
                    $sl = get_booking_status_label($s);
                    $badge_class = $sl['badge_class'];
                    $badge_icon  = $sl['icon'];
                    $badge_label = $sl['label'];

                    $is_active   = ($s === 'active' || ($s === 'confirmed' && $today >= $start && $today <= $end));
                    $is_upcoming = (in_array($s, ['confirmed','approved']) && $today < $start);
                    $is_past     = ($s === 'completed' || ($s === 'confirmed' && $today > $end));
                    $days_to_go  = $today < $start ? $today->diff($start)->days : 0;
                ?>
                <div class="col-xl-4 col-lg-6" data-aos="fade-up">
                    <div class="booking-card load-anim skeleton-box">
                        <div class="img-wrapper">
                            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($booking['brand']); ?>" class="booking-card-img">
                            <span class="position-absolute top-0 start-0 m-3 badge bg-white text-dark shadow-sm px-3 py-2 rounded-pill fw-bold" style="font-size:.8rem;">
                                <i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($booking['city_name'] ?? 'N/A'); ?>
                            </span>
                            <span class="position-absolute top-0 end-0 m-3 badge rounded-pill px-3 py-2 fw-bold shadow-sm <?php echo $badge_class; ?>" style="font-size:.8rem;">
                                <i class="fas <?php echo $badge_icon; ?> me-1"></i> <?php echo $badge_label; ?>
                            </span>

                            <?php if ($is_active): ?>
                                <div class="position-absolute bottom-0 start-0 end-0" style="background:linear-gradient(transparent, rgba(0,0,0,.8)); padding: 15px;">
                                    <span class="badge bg-success rounded-pill px-3 py-2 fw-bold shadow-sm">
                                        <span class="me-2" style="display:inline-block; width:8px; height:8px; background:#fff; border-radius:50%; animation: pulse 1.5s infinite;"></span>
                                        Active Trip
                                    </span>
                                </div>
                            <?php elseif ($is_upcoming): ?>
                                <div class="position-absolute bottom-0 start-0 end-0" style="background:linear-gradient(transparent, rgba(0,0,0,.8)); padding: 15px;">
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">
                                        <i class="fas fa-hourglass-half me-1"></i> Starts in <?php echo $days_to_go; ?> day<?php echo $days_to_go !== 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-4 d-flex flex-column flex-grow-1">
                            <div class="mb-4 border-bottom pb-3">
                                <p class="text-muted small fw-bold text-uppercase mb-1 tracking-wide" style="color: var(--sage-mid) !important;">
                                    <?php echo htmlspecialchars($booking['brand']); ?>
                                </p>
                                <h5 class="fw-black text-dark mb-1">
                                    <?php echo htmlspecialchars($booking['car_name'] . ' ' . $booking['model']); ?>
                                </h5>
                                <small class="text-muted fw-bold">Ref: #BKG-<?php echo $booking['id']; ?></small>
                            </div>

                            <div class="d-flex gap-3 mb-4 p-3 rounded-4 border shadow-sm" style="background:#f8f9f7;">
                                <div class="text-center flex-fill">
                                    <div class="small text-muted fw-bold text-uppercase tracking-wide" style="font-size:.7rem;">Pickup</div>
                                    <div class="fw-black text-dark"><?php echo date('d M Y', strtotime($booking['start_date'])); ?></div>
                                </div>
                                <div class="d-flex align-items-center px-2">
                                    <div class="text-center">
                                        <i class="fas fa-arrow-right text-muted small"></i>
                                        <div class="small fw-bold" style="color: var(--sage-mid); font-size:.7rem; margin-top:2px;">
                                            <?php echo $booking['total_days']; ?> day<?php echo $booking['total_days'] != 1 ? 's' : ''; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center flex-fill">
                                    <div class="small text-muted fw-bold text-uppercase tracking-wide" style="font-size:.7rem;">Return</div>
                                    <div class="fw-black text-dark"><?php echo date('d M Y', strtotime($booking['end_date'])); ?></div>
                                </div>
                            </div>

                            <?php if ($s === 'confirmed' && ($is_active || $is_past)): ?>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <small class="text-muted fw-bold text-uppercase tracking-wide" style="font-size: 0.7rem;"><?php echo $is_past ? 'Trip Completed' : 'Trip Progress'; ?></small>
                                        <small class="fw-bold" style="color: var(--sage-dark);"><?php echo $progress_pct; ?>%</small>
                                    </div>
                                    <div class="trip-progress shadow-sm">
                                        <div class="trip-progress-fill" style="width:<?php echo $progress_pct; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top border-secondary border-opacity-10">
                                <div>
                                    <div class="small text-muted fw-bold text-uppercase tracking-wide" style="font-size: 0.7rem;">Total Paid</div>
                                    <h4 class="fw-black mb-0" style="color: var(--sage-dark);">₹<?php echo number_format($booking['final_price'], 0); ?></h4>
                                </div>

                                <div class="d-flex gap-2">
                                    <?php if ($s === 'approved'): ?>
                                        <a href="<?php echo $base_url; ?>customer/payment.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-success text-white rounded-pill px-4 py-2 fw-bold shadow-sm" style="font-size:.85rem;">
                                            <i class="fas fa-credit-card me-1"></i> Pay Now
                                        </a>
                                    <?php elseif ($s === 'confirmed' || $s === 'active'): ?>
                                        <a href="<?php echo $base_url; ?>customer/invoice.php?id=<?php echo $booking['id']; ?>" class="btn btn-sage rounded-pill px-4 py-2 fw-bold shadow-sm" style="font-size:.85rem;">
                                            <i class="fas fa-file-invoice me-1"></i> Invoice
                                        </a>
                                    <?php elseif ($s === 'completed'): ?>
                                        <a href="<?php echo $base_url; ?>customer/final_invoice.php?id=<?php echo $booking['id']; ?>" class="btn btn-sage rounded-pill px-4 py-2 fw-bold shadow-sm" style="font-size:.85rem;">
                                            <i class="fas fa-receipt me-1"></i> Final Invoice
                                        </a>
                                    <?php elseif ($s === 'pending'): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold" style="font-size:.8rem;"><i class="fas fa-hourglass-half me-1"></i>Under Review</span>
                                    <?php elseif ($s === 'cancelled'): ?>
                                        <a href="<?php echo $base_url; ?>customer/search_cars.php" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold" style="font-size:.85rem;">
                                            <i class="fas fa-redo me-1"></i> Rebook
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" data-aos="fade-up" class="mt-5">
                    <ul class="pagination pagination-custom justify-content-center">
                        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                            <a class="page-link shadow-sm border" href="<?php if($page > 1) echo "?page=".($page - 1)."&status=$status_filter&sort=$sort_filter&search=$search_query"; else echo "#"; ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++ ): ?>
                            <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                                <a class="page-link shadow-sm border" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&sort=<?php echo $sort_filter; ?>&search=<?php echo $search_query; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                            <a class="page-link shadow-sm border" href="<?php if($page < $total_pages) echo "?page=".($page + 1)."&status=$status_filter&sort=$sort_filter&search=$search_query"; else echo "#"; ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state load-anim skeleton-box" data-aos="zoom-in">
                <i class="fas fa-route fa-4x mb-4 opacity-25" style="color: var(--sage-dark);"></i>
                <h3 class="fw-black text-dark mb-2">
                    <?php echo !empty($search_query) ? 'No matching logs found' : 'No Bookings Yet'; ?>
                </h3>
                <p class="text-muted mb-4 px-lg-5 fw-bold">
                    <?php if (!empty($search_query)): ?>
                        No bookings match "<strong><?php echo htmlspecialchars($search_query); ?></strong>". Try adjusting your filters.
                    <?php elseif ($status_filter !== 'all'): ?>
                        You have no <strong><?php echo $status_filter; ?></strong> bookings yet.
                    <?php else: ?>
                        Your ledger is currently empty. Start your first journey today and it will be securely logged here.
                    <?php endif; ?>
                </p>
                <a href="<?php echo $base_url; ?>customer/search_cars.php" class="btn btn-sage rounded-pill fw-bold px-5 py-3 shadow-lg hover-lift">
                    <i class="fas fa-compass me-2"></i> Explore the Fleet
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // 1. SKELETON LOADER REMOVAL ENGINE (Core Web Vitals Optimization)
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            document.querySelectorAll('.skeleton-box').forEach(el => {
                el.classList.remove('skeleton-box');
            });
        }, 300); // 300ms buffer prevents layout jank
    });

    // 2. INITIALIZE AOS
    if(typeof AOS !== 'undefined') {
        AOS.init({ once: true, offset: 40, duration: 750, easing: 'ease-out-cubic' });
    }

    // 3. LIVE SEARCH ON ENTER
    document.querySelector('input[name="search"]')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.closest('form').submit();
        }
    });
</script>

<style>
    @keyframes pulse {
        0%   { box-shadow: 0 0 0 0 rgba(255,255,255,.7); }
        70%  { box-shadow: 0 0 0 6px rgba(255,255,255,0); }
        100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
    }
</style>

<?php include '../includes/footer.php'; ?>