<?php
session_start();

// 🛡️ SECURITY CHECK: Role-Based Access Control (RBAC)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url  = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

$user_id  = (int)$_SESSION['user_id'];
$message  = '';
$msg_type = '';

// Check session for PRG (Post/Redirect/Get) toast messages
if (isset($_SESSION['cust_msg'])) {
    $message = $_SESSION['cust_msg'];
    $msg_type = $_SESSION['cust_msg_type'];
    unset($_SESSION['cust_msg'], $_SESSION['cust_msg_type']);
}

// ==========================================
// 💾 HANDLE PROFILE UPDATE (PRG Pattern)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = trim(htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8'));
    $phone = trim(htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8'));

    // Phone validation
    if (!preg_match('/^[0-9+\-\s]{7,15}$/', $phone)) {
        $_SESSION['cust_msg'] = 'Please enter a valid phone number.';
        $_SESSION['cust_msg_type'] = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name=?, phone=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $phone, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name; // Update active session
            $_SESSION['cust_msg'] = 'Profile updated successfully!';
            $_SESSION['cust_msg_type'] = 'success';
        } else {
            $_SESSION['cust_msg'] = 'Error saving profile. Please try again.';
            $_SESSION['cust_msg_type'] = 'danger';
        }
        $stmt->close();
    }
    header("Location: profile.php");
    exit();
}

// ==========================================
// 🔑 HANDLE PASSWORD CHANGE (PRG Pattern)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    // 🛡️ Secure Fetch: Current Password Hash
    $hash_stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $hash_stmt->bind_param("i", $user_id);
    $hash_stmt->execute();
    $hash_row = $hash_stmt->get_result()->fetch_assoc();
    $hash_stmt->close();

    if (!password_verify($current, $hash_row['password'])) {
        $_SESSION['cust_msg'] = 'Current password is incorrect.';
        $_SESSION['cust_msg_type'] = 'danger';
    } elseif (strlen($new_pass) < 8) {
        $_SESSION['cust_msg'] = 'New password must be at least 8 characters long.';
        $_SESSION['cust_msg_type'] = 'warning';
    } elseif ($new_pass !== $confirm) {
        $_SESSION['cust_msg'] = 'New passwords do not match.';
        $_SESSION['cust_msg_type'] = 'warning';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['cust_msg'] = 'Password changed successfully! Stay secure.';
        $_SESSION['cust_msg_type'] = 'success';
    }
    header("Location: profile.php");
    exit();
}

// ==========================================
// 📊 FETCH USER DATA + STATS (100% Prepared Statements)
// ==========================================
// User Details
$user_stmt = $conn->prepare("SELECT name, email, phone, loyalty_points, created_at FROM users WHERE id=?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Lifetime Stats
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        COALESCE(SUM(CASE WHEN booking_status='confirmed' THEN final_price ELSE 0 END),0) as total_spent,
        COALESCE(SUM(CASE WHEN booking_status='confirmed' THEN total_days ELSE 0 END),0) as total_days
    FROM bookings WHERE user_id=?
");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Favorite Car
$fav_stmt = $conn->prepare("
    SELECT c.brand, c.name as cname, COUNT(b.id) as trips
    FROM bookings b JOIN cars c ON b.car_id=c.id
    WHERE b.user_id=? AND b.booking_status='confirmed'
    GROUP BY b.car_id ORDER BY trips DESC LIMIT 1
");
$fav_stmt->bind_param("i", $user_id);
$fav_stmt->execute();
$fav = $fav_stmt->get_result()->fetch_assoc();
$fav_stmt->close();

// Tier Engine
$pts   = $user['loyalty_points'];
$tier  = 'Silver'; $tier_icon = 'fa-medal';    $tier_color = '#6c757d'; $next_pts = 500; $next_tier_name = 'Gold';
if ($pts >= 500)  { $tier = 'Gold';     $tier_icon = 'fa-star';  $tier_color = '#ffc107'; $next_pts = 1500; $next_tier_name = 'Platinum'; }
if ($pts >= 1500) { $tier = 'Platinum'; $tier_icon = 'fa-crown'; $tier_color = '#0dcaf0'; $next_pts = $pts; $next_tier_name = 'Max'; }
$tier_pct = min(100, $pts >= 1500 ? 100 : round(($pts / $next_pts) * 100));

// Profile completion Engine
$completion = 60;
if (!empty($user['phone'])) $completion += 20;
if ($stats['total_bookings'] > 0) $completion += 20;

// Recent bookings (last 3)
$recent_stmt = $conn->prepare("
    SELECT b.id, b.start_date, b.end_date, b.final_price, b.booking_status, c.brand, c.name as car_name
    FROM bookings b JOIN cars c ON b.car_id=c.id
    WHERE b.user_id=? ORDER BY b.id DESC LIMIT 3
");
$recent_stmt->bind_param("i", $user_id);
$recent_stmt->execute();
$recent = $recent_stmt->get_result();
$recent_stmt->close();

$page_title = "Account Settings | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🌿 ENTERPRISE SAGE GREEN THEME */
    :root {
        --sage-dark:  #4a5c43;
        --sage-mid:   #889c7c;
        --sage-pale:  #e0eadb;
        --sage-bg:    #f4f5f3;
        --sage-deep:  #2b3327;
        --shadow-soft: 0 4px 15px rgba(0,0,0,0.03);
        --shadow-hover: 0 10px 25px rgba(74,92,67,0.1);
    }
    body { background: var(--sage-bg); }

    /* 🦴 Skeleton Loading Engine */
    .skeleton-box { position: relative; overflow: hidden; background-color: #e2e8f0; color: transparent !important; border-color: transparent !important; user-select: none; pointer-events: none; border-radius: 12px; }
    .skeleton-box * { visibility: hidden; }
    .skeleton-box::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.6) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 100% { transform: translateX(100%); } }

    /* ─── HERO ─── */
    .profile-hero {
        background: linear-gradient(135deg, var(--sage-deep) 0%, var(--sage-dark) 100%);
        border-radius: 24px; padding: 3rem 2.5rem; color: white; position: relative; overflow: hidden;
        box-shadow: 0 20px 40px rgba(43,51,39,.2);
    }
    .profile-hero::after {
        content:'\f2c2'; font-family:'Font Awesome 5 Free'; font-weight:900;
        position:absolute; right:-30px; bottom:-50px; font-size:16rem;
        color:rgba(255,255,255,.04); pointer-events:none; transform: rotate(-10deg);
    }

    /* ─── AVATAR ─── */
    .avatar-ring {
        width: 120px; height: 120px; border-radius: 50%;
        border: 4px solid rgba(255,255,255,.2); box-shadow: 0 0 0 4px var(--sage-mid);
        transition: transform .4s ease, box-shadow .4s; cursor: pointer; object-fit: cover;
    }
    .avatar-ring:hover { transform: scale(1.05); box-shadow: 0 0 0 6px var(--sage-pale); }
    .avatar-overlay {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%;
        background: rgba(0,0,0,0.5); color: white; display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s; pointer-events: none;
    }
    .avatar-container:hover .avatar-overlay { opacity: 1; }

    /* ─── STATS CARD ─── */
    .stat-card {
        background: white; border-radius: 20px; padding: 25px; box-shadow: var(--shadow-soft);
        border-bottom: 4px solid transparent; transition: transform .35s ease, box-shadow .35s; text-align: center;
    }
    .stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); }
    .stat-card.green  { border-bottom-color: var(--sage-dark); }
    .stat-card.gold   { border-bottom-color: #ffc107; }
    .stat-card.blue   { border-bottom-color: #0dcaf0; }
    .stat-card.purple { border-bottom-color: #6f42c1; }

    /* ─── TABS ─── */
    .profile-tabs { border-bottom: 2px solid var(--sage-pale); margin-bottom: 0; }
    .profile-tabs .nav-link {
        color: #6c757d; font-weight:700; border:none; padding:15px 25px;
        border-radius:12px 12px 0 0; position:relative; transition: all .3s; background: transparent;
    }
    .profile-tabs .nav-link:hover { color: var(--sage-dark); background: rgba(136,156,124,.05); }
    .profile-tabs .nav-link.active { color: var(--sage-dark); background: white; box-shadow: 0 -4px 0 var(--sage-dark) inset; }

    /* ─── INPUTS ─── */
    .field-group {
        background: #f8f9f7; border: 2px solid transparent; border-radius: 12px; padding: 14px 18px;
        transition: all .3s; font-weight: 600; color: var(--sage-deep);
    }
    .field-group:focus { border-color: var(--sage-mid); box-shadow: 0 0 0 .25rem rgba(136,156,124,.15); background: white; outline: none; }
    .field-group[readonly] { background: #eff1ec; cursor: not-allowed; color: #6c757d; }

    /* ─── STRENGTH METER ─── */
    .strength-track { height: 6px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin-top: 8px; }
    .strength-fill  { height: 100%; width: 0; border-radius: 10px; transition: width .4s ease, background .4s ease; }

    /* ─── ACTIVITY ITEM ─── */
    .activity-item {
        display: flex; align-items: center; gap: 16px; padding: 15px 20px; border-radius: 16px;
        background: #f8f9f7; border-left: 4px solid transparent; transition: all .3s; margin-bottom: 12px;
    }
    .activity-item:hover { background: white; box-shadow: var(--shadow-soft); transform: translateX(5px); }
    .activity-item.confirmed { border-left-color: var(--sage-dark); }
    .activity-item.pending   { border-left-color: #ffc107; }
    .activity-item.cancelled { border-left-color: #dc3545; }

    /* ─── DANGER ZONE ─── */
    .danger-zone { border: 2px solid rgba(220,53,69,.2); border-radius: 16px; background: rgba(220,53,69,.03); padding: 25px; transition: all 0.3s; }
    .danger-zone:hover { border-color: rgba(220,53,69,.4); background: rgba(220,53,69,.05); }

    /* ─── BTN ─── */
    .btn-sage { background: var(--sage-dark); color: white; border: none; transition: all .3s; }
    .btn-sage:hover { background: var(--sage-deep); color: white; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(74,92,67,.3); }

    /* 🔔 Toast Engine */
    #cust-toast-container { position: fixed; top: 20px; right: 20px; z-index: 99999; }
    .cust-toast { background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 15px; min-width: 320px; transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); }
    .cust-toast.show { transform: translateX(0); opacity: 1; }
    .cust-toast.success { border-left: 5px solid #198754; }
    .cust-toast.warning { border-left: 5px solid #ffc107; }
    .cust-toast.danger { border-left: 5px solid #dc3545; }
</style>

<div id="cust-toast-container"></div>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="dashboard-content">

        <div class="profile-hero mb-5 load-anim skeleton-box" data-aos="fade-up">
            <div class="row align-items-center g-4 position-relative z-2">
                <div class="col-auto">
                    <div class="position-relative avatar-container" onclick="document.getElementById('avatarUpload').click()">
                        <img id="avatarImg" src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=889c7c&color=fff&size=120&bold=true" class="avatar-ring shadow-sm" alt="Avatar">
                        <div class="avatar-overlay"><i class="fas fa-camera fs-4"></i></div>
                        <input type="file" id="avatarUpload" class="d-none" accept="image/*" onchange="previewAvatar(this)">
                    </div>
                </div>
                <div class="col">
                    <div class="badge rounded-pill mb-2 px-3 py-2 shadow-sm" style="background:rgba(255,255,255,.15); color:<?php echo $tier_color; ?>; border:1px solid rgba(255,255,255,.2); font-size: 0.85rem; letter-spacing: 1px;">
                        <i class="fas <?php echo $tier_icon; ?> me-1"></i> <?php echo $tier; ?> Tier Member
                    </div>
                    <h1 class="display-6 fw-black mb-1"><?php echo htmlspecialchars($user['name']); ?></h1>
                    <p class="opacity-75 mb-2 fw-bold"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                    <small class="opacity-50 fw-bold">Active member since <?php echo date('F Y', strtotime($user['created_at'])); ?></small>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="p-4 rounded-4 shadow-sm" style="background:rgba(255,255,255,.1); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.15);">
                        <div class="small fw-bold text-uppercase opacity-75 mb-1" style="letter-spacing:1px;">Loyalty Points Balance</div>
                        <div class="display-5 fw-black" style="color:<?php echo $tier_color; ?>;"><?php echo number_format($pts); ?></div>
                        <div class="small opacity-75 mb-3 fw-bold">
                            <?php echo $tier !== 'Platinum' ? "Earn " . number_format($next_pts - $pts) . " more for " . $next_tier_name : "Maximum Tier Reached!"; ?>
                        </div>
                        <div style="background:rgba(255,255,255,.2); height:6px; border-radius:10px; overflow:hidden;">
                            <div style="height:100%; width:<?php echo $tier_pct; ?>%; background:<?php echo $tier_color; ?>; border-radius:10px; transition:width 1.5s ease-out;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="0">
                <div class="stat-card green load-anim skeleton-box">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm border" style="width:55px;height:55px;background:rgba(74,92,67,.05);">
                        <i class="fas fa-road fa-lg text-success"></i>
                    </div>
                    <h2 class="fw-black mb-0"><?php echo $stats['total_bookings']; ?></h2>
                    <small class="text-muted fw-bold text-uppercase tracking-wide" style="font-size: 0.75rem;">Lifetime Trips</small>
                </div>
            </div>
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card gold load-anim skeleton-box">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm border" style="width:55px;height:55px;background:rgba(255,193,7,.05);">
                        <i class="fas fa-wallet fa-lg text-warning"></i>
                    </div>
                    <h2 class="fw-black mb-0" style="color:var(--sage-dark);">₹<?php echo number_format($stats['total_spent'], 0); ?></h2>
                    <small class="text-muted fw-bold text-uppercase tracking-wide" style="font-size: 0.75rem;">Total Investment</small>
                </div>
            </div>
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card blue load-anim skeleton-box">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm border" style="width:55px;height:55px;background:rgba(13,202,240,.05);">
                        <i class="fas fa-calendar-day fa-lg text-info"></i>
                    </div>
                    <h2 class="fw-black mb-0"><?php echo $stats['total_days']; ?></h2>
                    <small class="text-muted fw-bold text-uppercase tracking-wide" style="font-size: 0.75rem;">Days on Road</small>
                </div>
            </div>
            <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card purple load-anim skeleton-box">
                    <div class="rounded-4 d-inline-flex align-items-center justify-content-center mb-3 shadow-sm border" style="width:55px;height:55px;background:rgba(111,66,193,.05);">
                        <i class="fas fa-heart fa-lg" style="color:#6f42c1;"></i>
                    </div>
                    <h5 class="fw-black mb-0 text-truncate" style="color:#6f42c1; max-width:90%; margin:0 auto;">
                        <?php echo $fav ? htmlspecialchars($fav['brand']) : 'N/A'; ?>
                    </h5>
                    <small class="text-muted fw-bold text-uppercase tracking-wide" style="font-size: 0.75rem;">Favourite Brand</small>
                </div>
            </div>
        </div>

        <div class="row g-5">

            <div class="col-xl-4" data-aos="fade-right">
                <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white load-anim skeleton-box">
                    <h6 class="fw-bold text-uppercase text-muted small mb-4 tracking-widest">Account Health</h6>
                    <?php
                    $checks = [
                        ['label'=>'Account Registered', 'done'=>true, 'icon'=>'fa-user'],
                        ['label'=>'Email Verified',     'done'=>true, 'icon'=>'fa-envelope'],
                        ['label'=>'Phone Number Added', 'done'=>!empty($user['phone']), 'icon'=>'fa-phone'],
                        ['label'=>'First Trip Completed','done'=>$stats['total_bookings']>0, 'icon'=>'fa-car'],
                    ];
                    foreach ($checks as $c): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 border" style="width:40px;height:40px;background:<?php echo $c['done']?'rgba(74,92,67,.05)':'#f8f9fa'; ?>">
                            <i class="fas <?php echo $c['icon']; ?> small" style="color:<?php echo $c['done']?'var(--sage-dark)':'#adb5bd'; ?>;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold small text-dark"><?php echo $c['label']; ?></div>
                        </div>
                        <?php if ($c['done']): ?>
                            <i class="fas fa-check-circle text-success fs-5 shadow-sm rounded-circle"></i>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark rounded-pill shadow-sm" style="font-size:.65rem;">Pending</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="fw-bold text-muted text-uppercase tracking-wide">Completion</small>
                            <small class="fw-black" style="color:var(--sage-dark);"><?php echo $completion; ?>%</small>
                        </div>
                        <div class="progress rounded-pill shadow-sm" style="height:8px;background:var(--sage-pale);">
                            <div class="progress-bar" style="width:<?php echo $completion; ?>%;background:var(--sage-dark);"></div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white load-anim skeleton-box">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h6 class="fw-bold text-uppercase text-muted small mb-0 tracking-widest">Recent Activity</h6>
                        <a href="<?php echo $base_url; ?>customer/rental_history.php" class="small fw-bold text-decoration-none" style="color:var(--sage-dark);">View Ledger →</a>
                    </div>
                    <?php if (mysqli_num_rows($recent) > 0):
                        while ($r = mysqli_fetch_assoc($recent)):
                            $s = $r['booking_status'];
                    ?>
                    <div class="activity-item <?php echo $s; ?> border shadow-sm">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 border bg-white" style="width:45px;height:45px;">
                            <i class="fas fa-car" style="color:<?php echo $s==='confirmed'?'var(--sage-dark)':($s==='pending'?'#ffc107':'#dc3545'); ?>;"></i>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-black text-dark small text-truncate"><?php echo htmlspecialchars($r['brand'].' '.$r['car_name']); ?></div>
                            <div class="text-muted fw-bold" style="font-size:.7rem;"><?php echo date('d M', strtotime($r['start_date'])); ?> <i class="fas fa-arrow-right mx-1" style="font-size: 0.5rem;"></i> <?php echo date('d M', strtotime($r['end_date'])); ?></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-black small" style="color:var(--sage-dark);">₹<?php echo number_format($r['final_price'],0); ?></div>
                            <span class="badge rounded-pill mt-1" style="font-size:.6rem;background:<?php echo $s==='confirmed'?'rgba(74,92,67,.12)':($s==='pending'?'rgba(255,193,7,.15)':'rgba(220,53,69,.1)');?>;color:<?php echo $s==='confirmed'?'var(--sage-dark)':($s==='pending'?'#856404':'#dc3545');?>;">
                                <?php echo strtoupper($s); ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                    <p class="text-muted text-center small fw-bold py-4 bg-light rounded-4 border"><i class="fas fa-history mb-2 d-block fs-4 opacity-50"></i>No booking history yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-8" data-aos="fade-left">
                <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden load-anim skeleton-box">

                    <ul class="nav profile-tabs px-4 pt-3 bg-light" id="pTabs" role="tablist" style="gap:5px;">
                        <li class="nav-item"><button class="nav-link active shadow-sm" data-bs-toggle="tab" data-bs-target="#tab-info"><i class="fas fa-user-edit me-2"></i>Profile</button></li>
                        <li class="nav-item"><button class="nav-link shadow-sm" data-bs-toggle="tab" data-bs-target="#tab-security"><i class="fas fa-shield-alt me-2"></i>Security</button></li>
                        <li class="nav-item"><button class="nav-link shadow-sm" data-bs-toggle="tab" data-bs-target="#tab-id"><i class="fas fa-id-badge me-2"></i>KYC</button></li>
                        <li class="nav-item"><button class="nav-link shadow-sm" data-bs-toggle="tab" data-bs-target="#tab-danger"><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Danger</button></li>
                    </ul>

                    <div class="tab-content p-4 p-md-5">

                        <div class="tab-pane fade show active" id="tab-info">
                            <h4 class="fw-black mb-1 text-dark">Personal Information</h4>
                            <p class="text-muted small mb-4 fw-bold border-bottom pb-3">Keep your contact details up to date to receive booking confirmations.</p>

                            <form action="profile.php" method="POST" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label class="fw-bold text-muted small text-uppercase mb-2 tracking-wide">Registered Email</label>
                                    <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                        <span class="input-group-text bg-light border-0"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control field-group border-0" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                        <span class="input-group-text bg-light border-0 text-success fw-bold small"><i class="fas fa-check-circle me-1"></i>Verified</span>
                                    </div>
                                    <small class="text-muted mt-2 d-block fw-bold"><i class="fas fa-lock me-1"></i> Email is tied to your financial ledger and cannot be changed.</small>
                                </div>

                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <label class="fw-bold text-muted small text-uppercase mb-2 tracking-wide">Full Legal Name</label>
                                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                            <span class="input-group-text bg-white border-0"><i class="fas fa-user text-muted"></i></span>
                                            <input type="text" name="name" class="form-control field-group border-0 ps-0" value="<?php echo htmlspecialchars($user['name']); ?>" required minlength="2">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold text-muted small text-uppercase mb-2 tracking-wide">Contact Number</label>
                                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                            <span class="input-group-text bg-white border-0"><i class="fas fa-phone text-muted"></i></span>
                                            <input type="tel" name="phone" class="form-control field-group border-0 ps-0" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="+91 98765 43210" required pattern="[0-9+\-\s]{7,15}">
                                        </div>
                                        <div class="invalid-feedback fw-bold mt-2">Provide a valid 10-digit number.</div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end pt-4 border-top">
                                    <button type="submit" name="update_profile" class="btn btn-sage rounded-pill px-5 py-3 fw-bold shadow-sm hover-lift">
                                        <i class="fas fa-save me-2"></i> Save Profile Settings
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="tab-security">
                            <h4 class="fw-black mb-1 text-dark">Security Settings</h4>
                            <p class="text-muted small mb-4 fw-bold border-bottom pb-3">Use a strong, unique password to protect your payment methods and loyalty points.</p>

                            <form action="profile.php" method="POST" id="securityForm" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label class="fw-bold text-muted small text-uppercase mb-2 tracking-wide">Current Password</label>
                                    <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                                        <span class="input-group-text bg-white border-0"><i class="fas fa-lock text-muted"></i></span>
                                        <input type="password" name="current_password" id="currentPass" class="form-control field-group border-0 ps-0" placeholder="Enter your current password" required>
                                        <button type="button" class="input-group-text bg-white border-0 toggle-eye" data-target="currentPass"><i class="fas fa-eye text-muted"></i></button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="fw-bold text-muted small text-uppercase mb-2 tracking-wide">New Password</label>
                                    <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                                        <span class="input-group-text bg-white border-0"><i class="fas fa-key text-muted"></i></span>
                                        <input type="password" name="new_password" id="newPass" class="form-control field-group border-0 ps-0" placeholder="Minimum 8 characters" required minlength="8" oninput="checkStrength(this.value)">
                                        <button type="button" class="input-group-text bg-white border-0 toggle-eye" data-target="newPass"><i class="fas fa-eye text-muted"></i></button>
                                    </div>
                                    <div class="strength-track mt-3 shadow-sm border"><div class="strength-fill" id="strengthFill"></div></div>
                                    <small class="fw-bold mt-2 d-block text-uppercase tracking-wide" id="strengthLabel" style="color:#aaa; font-size: 0.7rem;">Awaiting Input</small>
                                </div>

                                <div class="mb-4 p-4 rounded-4 shadow-sm" style="background:#f8f9f7; border: 1px solid rgba(0,0,0,0.05);">
                                    <div class="row g-3 fw-bold small text-muted" id="pwRules">
                                        <div class="col-6"><span class="rule" id="r-len"><i class="fas fa-circle me-2" style="font-size:.5rem;"></i>8+ characters</span></div>
                                        <div class="col-6"><span class="rule" id="r-upper"><i class="fas fa-circle me-2" style="font-size:.5rem;"></i>Uppercase letter</span></div>
                                        <div class="col-6"><span class="rule" id="r-lower"><i class="fas fa-circle me-2" style="font-size:.5rem;"></i>Lowercase letter</span></div>
                                        <div class="col-6"><span class="rule" id="r-num"><i class="fas fa-circle me-2" style="font-size:.5rem;"></i>Numeric digit</span></div>
                                    </div>
                                </div>

                                <div class="mb-5">
                                    <label class="fw-bold text-muted small text-uppercase mb-2 tracking-wide">Confirm New Password</label>
                                    <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                                        <span class="input-group-text bg-white border-0"><i class="fas fa-check-double text-muted"></i></span>
                                        <input type="password" name="confirm_password" id="confirmPass" class="form-control field-group border-0 ps-0" placeholder="Re-enter to confirm" required oninput="checkMatch()">
                                        <button type="button" class="input-group-text bg-white border-0 toggle-eye" data-target="confirmPass"><i class="fas fa-eye text-muted"></i></button>
                                    </div>
                                    <small class="text-danger fw-bold mt-2 d-none" id="matchErr"><i class="fas fa-times-circle me-1"></i> Passwords do not match.</small>
                                    <small class="text-success fw-bold mt-2 d-none" id="matchOk"><i class="fas fa-check-circle me-1"></i> Passwords match.</small>
                                </div>

                                <div class="d-flex justify-content-end pt-4 border-top">
                                    <button type="submit" name="change_password" class="btn btn-sage rounded-pill px-5 py-3 fw-bold shadow-sm hover-lift" id="secBtn" disabled>
                                        <i class="fas fa-shield-alt me-2"></i> Secure Account
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade" id="tab-id">
                            <h4 class="fw-black mb-1 text-dark">Identity Verification (KYC)</h4>
                            <p class="text-muted small mb-4 fw-bold border-bottom pb-3">Upload a valid government-issued ID to instantly unlock premium and luxury vehicle tiers.</p>

                            <div class="d-flex align-items-center gap-4 p-4 rounded-4 mb-4 border shadow-sm" style="background:#fffbf0; border-color:#f6e0b5 !important;">
                                <i class="fas fa-user-clock fa-3x text-warning opacity-75"></i>
                                <div>
                                    <h5 class="fw-bold mb-1 text-dark">Status: Action Required</h5>
                                    <p class="text-muted small mb-0 fw-bold">Your profile is restricted to basic vehicles. Complete KYC to lift restrictions.</p>
                                </div>
                            </div>

                            <div class="row g-3">
                                <?php
                                $id_types = [
                                    ['icon'=>'fa-id-card',   'title'=>"Driver's License", 'desc'=>'Mandatory for all bookings'],
                                    ['icon'=>'fa-address-card','title'=>'Aadhaar Card',   'desc'=>'For Indian Residents'],
                                    ['icon'=>'fa-passport',  'title'=>'Passport',         'desc'=>'For International Renters'],
                                ];
                                foreach ($id_types as $idt): ?>
                                <div class="col-12">
                                    <label class="btn btn-outline-secondary w-100 text-start border rounded-4 p-3 d-flex align-items-center gap-4 hover-lift bg-light shadow-sm" style="cursor:pointer;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-white border shadow-sm" style="width:50px;height:50px;">
                                            <i class="fas <?php echo $idt['icon']; ?> fs-5" style="color:var(--sage-dark);"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold text-dark mb-0"><?php echo $idt['title']; ?></h6>
                                            <small class="text-muted fw-bold"><?php echo $idt['desc']; ?></small>
                                        </div>
                                        <div class="btn btn-sage btn-sm rounded-pill px-3 fw-bold"><i class="fas fa-upload me-1"></i> Upload</div>
                                        <input type="file" class="d-none" accept="image/*,.pdf">
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="alert border-0 rounded-4 mt-4 shadow-sm p-4 d-flex align-items-center" style="background:rgba(136,156,124,.08);">
                                <i class="fas fa-lock fa-2x me-3" style="color:var(--sage-dark);"></i>
                                <div>
                                    <strong class="text-dark d-block">Enterprise-Grade Privacy</strong>
                                    <small class="text-muted fw-bold">Your documents are encrypted via 256-bit AES and are strictly used for identity verification.</small>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-danger">
                            <h4 class="fw-black mb-1 text-danger">Danger Zone</h4>
                            <p class="text-muted small mb-4 fw-bold border-bottom pb-3">These actions are permanent and immediately affect your access to the platform.</p>

                            <div class="danger-zone mb-4 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
                                    <div>
                                        <h5 class="fw-bold mb-1 text-dark">Pause Account Activity</h5>
                                        <p class="text-muted small fw-bold mb-0">Temporarily freeze your account. Existing bookings are safe, but no new bookings can be made.</p>
                                    </div>
                                    <button class="btn btn-outline-danger rounded-pill px-4 fw-bold shadow-sm" onclick="if(confirm('Freeze your account? You must contact support to unfreeze it.')) showToast('Request sent to support team.', 'warning')">
                                        <i class="fas fa-snowflake me-2"></i>Freeze Account
                                    </button>
                                </div>
                            </div>

                            <div class="danger-zone shadow-sm" style="background: rgba(220,53,69,.08); border-color: rgba(220,53,69,.3);">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-4">
                                    <div>
                                        <h5 class="fw-black mb-1 text-danger">Delete Account Permanently</h5>
                                        <p class="text-danger opacity-75 small fw-bold mb-0">All historical data, invoices, and <strong class="text-dark">Loyalty Points</strong> will be permanently wiped.</p>
                                    </div>
                                    <button class="btn btn-danger rounded-pill px-4 fw-bold shadow-sm" onclick="confirmDelete()">
                                        <i class="fas fa-skull-crossbones me-2"></i>Delete Forever
                                    </button>
                                </div>
                            </div>
                        </div>

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

    // 3. BEAST MODE TOAST NOTIFICATION ENGINE
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
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 5000);
    }

    <?php if($message): ?>
        document.addEventListener("DOMContentLoaded", () => showToast('<?php echo addslashes($message); ?>', '<?php echo $msg_type; ?>'));
    <?php endif; ?>

    // 4. PASSWORD SHOW/HIDE
    document.querySelectorAll('.toggle-eye').forEach(btn => {
        btn.addEventListener('click', function() {
            const inp = document.getElementById(this.dataset.target);
            const icon = this.querySelector('i');
            if (inp.type === 'password') {
                inp.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                inp.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // 5. ENTERPRISE PASSWORD STRENGTH METER
    function checkStrength(val) {
        const fill  = document.getElementById('strengthFill');
        const label = document.getElementById('strengthLabel');
        const rules = {
            'r-len':   val.length >= 8,
            'r-upper': /[A-Z]/.test(val),
            'r-lower': /[a-z]/.test(val),
            'r-num':   /[0-9]/.test(val),
        };
        let score = Object.values(rules).filter(Boolean).length;

        // Visual checkmarks
        Object.entries(rules).forEach(([id, ok]) => {
            const el  = document.getElementById(id);
            const ico = el.querySelector('i');
            if (ok) {
                el.style.color = '#198754';
                ico.className = 'fas fa-check-circle me-2 text-success fs-6';
            } else {
                el.style.color = '#aaa';
                ico.className = 'fas fa-circle me-2 text-muted';
                ico.style.fontSize = '.5rem';
            }
        });

        const configs = [
            { w:'0%',   bg:'#e9ecef', txt:'Awaiting Input',  col:'#aaa' },
            { w:'25%',  bg:'#dc3545', txt:'Weak Security',   col:'#dc3545' },
            { w:'50%',  bg:'#fd7e14', txt:'Fair Security',   col:'#fd7e14' },
            { w:'75%',  bg:'#ffc107', txt:'Good Security',   col:'#ffc107' },
            { w:'100%', bg:'#198754', txt:'Strong Security', col:'#198754' },
        ];
        
        const c = configs[score] || configs[0];
        fill.style.width      = c.w;
        fill.style.background = c.bg;
        label.textContent     = c.txt;
        label.style.color     = c.col;
        checkMatch();
    }

    // 6. PASSWORD MATCH VALIDATOR
    function checkMatch() {
        const np = document.getElementById('newPass').value;
        const cp = document.getElementById('confirmPass').value;
        const err = document.getElementById('matchErr');
        const ok  = document.getElementById('matchOk');
        const btn = document.getElementById('secBtn');
        
        if (!cp) { err.classList.add('d-none'); ok.classList.add('d-none'); btn.disabled = true; return; }
        
        if (np === cp && np.length >= 8) {
            err.classList.add('d-none'); ok.classList.remove('d-none'); btn.disabled = false;
        } else {
            ok.classList.add('d-none'); err.classList.remove('d-none'); btn.disabled = true;
        }
    }

    // 7. SIMULATED ASYNC AVATAR UPLOAD
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const img = document.getElementById('avatarImg');
            // Simulate loading state
            img.style.opacity = '0.5';
            showToast('Uploading new avatar...', 'info');
            
            setTimeout(() => {
                const reader = new FileReader();
                reader.onload = e => { 
                    img.src = e.target.result; 
                    img.style.opacity = '1';
                    showToast('Avatar updated successfully!', 'success');
                };
                reader.readAsDataURL(input.files[0]);
            }, 1000);
        }
    }

    // 8. DESTRUCTIVE ACTION SAFEGUARD
    function confirmDelete() {
        const first = confirm('CRITICAL WARNING: This will permanently delete your SmartDrive X account and erase all Loyalty Points.\n\nAre you absolutely sure?');
        if (first) {
            const typed = prompt('Type DELETE to confirm:');
            if (typed === 'DELETE') {
                showToast('Account deletion protocol initiated. You will be logged out shortly.', 'danger');
                setTimeout(() => window.location.href = '../logout.php', 3000);
            } else {
                showToast('Deletion cancelled. Account is safe.', 'success');
            }
        }
    }
</script>

<?php include '../includes/footer.php'; ?>