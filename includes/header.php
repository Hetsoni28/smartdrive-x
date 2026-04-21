<?php
// ==========================================
// 🛡️ SECURE SESSION & GLOBAL CONFIG
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🌐 BULLETPROOF PATHING 
$base_url = isset($base_url) ? $base_url : "http://localhost/smartdrive_x/";

// 🎨 DYNAMIC THEME ENGINE (Admin = Teal, Customer/Guest = Sage Green)
$role_id = $_SESSION['role_id'] ?? 0;
if ($role_id == 1) {
    $theme_primary = "#4da89c"; // Teal
    $theme_secondary = "#8bd0b4"; // Mint
    $theme_bg = "#f1f7ec";
} else {
    $theme_primary = "#4a5c43"; // Sage Dark (Updated to match your V2 design system)
    $theme_secondary = "#889c7c"; // Sage Mid
    $theme_bg = "#f4f5f3";
}

// 🏷️ DYNAMIC PAGE TITLE GENERATOR
$current_filename = basename($_SERVER['PHP_SELF'], ".php");
$page_title = ucwords(str_replace('_', ' ', $current_filename));
if ($page_title == 'Index') $page_title = "Home";

// Initial Notification Count (Secure Fetch)
$initial_notif_count = 0;
if (isset($_SESSION['user_id']) && isset($conn)) {
    $uid = (int)$_SESSION['user_id'];
    $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE (user_id = ? OR user_id = 0) AND is_read = 0");
    if ($count_stmt) {
        $count_stmt->bind_param("i", $uid);
        $count_stmt->execute();
        $initial_notif_count = $count_stmt->get_result()->fetch_assoc()['c'] ?? 0;
        $count_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SmartDrive X | <?php echo $page_title; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/style.css">

    <style>
        /* ==========================================
           1. CSS VARIABLE INJECTION (Dynamic Theme)
           ========================================== */
        :root {
            --theme-primary: <?php echo $theme_primary; ?>;
            --theme-secondary: <?php echo $theme_secondary; ?>;
            --theme-bg: <?php echo $theme_bg; ?>;
            --font-heading: 'Outfit', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;
        }

        /* ==========================================
           2. GLOBAL & PRELOADER STYLES
           ========================================== */
        body { font-family: var(--font-body); overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6, .navbar-brand { font-family: var(--font-heading); }

        #global-loader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: var(--bs-body-bg); z-index: 99999;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        .spinner-car {
            font-size: 3.5rem; color: var(--theme-primary);
            animation: driveBounce 1.5s infinite cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes driveBounce {
            0% { transform: translateX(-60px) scale(0.9); opacity: 0; }
            50% { transform: translateX(0px) scale(1.1); opacity: 1; }
            100% { transform: translateX(60px) scale(0.9); opacity: 0; }
        }

        /* ==========================================
           3. MICRO-HEADER & STICKY NAVBAR
           ========================================== */
        .top-utility-bar {
            background: linear-gradient(90deg, #111827 0%, #1f2937 100%); 
            color: var(--theme-secondary); font-size: 0.75rem; font-weight: 700;
            padding: 8px 0; letter-spacing: 0.5px;
        }
        .top-utility-bar a { color: var(--theme-secondary); text-decoration: none; transition: color 0.2s ease; }
        .top-utility-bar a:hover { color: #fff; }

        .smart-navbar {
            background: rgba(var(--bs-body-bg-rgb), 0.85) !important;
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }
        
        .navbar-brand { color: var(--theme-primary) !important; font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px; }

        /* Modern Nav Links */
        .navbar-nav .nav-link {
            color: var(--bs-body-color) !important; font-weight: 700; font-size: 0.95rem;
            padding: 0.6rem 1.2rem; margin: 0 0.2rem; border-radius: 50px;
            transition: all 0.3s ease; position: relative;
        }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active {
            color: var(--theme-primary) !important; background: rgba(0,0,0,0.04);
        }

        /* ==========================================
           4. INTERACTIVE WIDGETS (Search, Notify, Profile)
           ========================================== */
        .nav-icon-btn {
            cursor: pointer; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;
            border-radius: 50%; background: transparent; color: var(--bs-body-color);
            transition: all 0.3s ease; border: none; position: relative;
        }
        .nav-icon-btn:hover { background: rgba(0,0,0,0.05); color: var(--theme-primary); transform: translateY(-2px); }

        /* Notification Engine UI */
        .notification-dot {
            position: absolute; top: 8px; right: 8px; width: 8px; height: 8px;
            background-color: #dc3545; border-radius: 50%; box-shadow: 0 0 0 2px var(--bs-body-bg);
            animation: pulse-red 2s infinite;
        }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
        
        .notify-dropdown {
            width: 360px; padding: 0; border: 1px solid rgba(0,0,0,0.05); border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15); overflow: hidden;
            animation: slideDown 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* Scrollbar for Dropdown */
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: var(--theme-primary); }

        /* Search Modal */
        #searchModal { backdrop-filter: blur(15px); background: rgba(0, 0, 0, 0.7); }
        .search-input-huge {
            font-size: clamp(2rem, 5vw, 3.5rem); font-family: var(--font-heading); font-weight: 900;
            background: transparent; border: none; border-bottom: 4px solid var(--theme-primary);
            color: white; border-radius: 0; padding: 15px 0; text-align: center; box-shadow: none !important;
        }
        .search-input-huge::placeholder { color: rgba(255,255,255,0.2); }

        /* Profile Dropdown */
        .profile-dropdown { border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 20px 40px rgba(0,0,0,0.12); border-radius: 20px; padding: 10px; animation: slideDown 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .profile-dropdown .dropdown-item { border-radius: 10px; transition: all 0.2s; font-weight: 600; padding: 10px 15px; }
        .profile-dropdown .dropdown-item:hover { background: rgba(0,0,0,0.04); color: var(--theme-primary); transform: translateX(3px); }

        /* Offcanvas App Drawer */
        .offcanvas { border-left: none; box-shadow: -10px 0 40px rgba(0,0,0,0.1); }
        
        /* 🦴 Skeleton Loader */
        .skeleton-text { height: 12px; background: #e2e8f0; border-radius: 6px; margin-bottom: 8px; animation: pulse-bg 1.5s infinite; }
        .skeleton-avatar { width: 36px; height: 36px; background: #e2e8f0; border-radius: 10px; animation: pulse-bg 1.5s infinite; }
        @keyframes pulse-bg { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
    </style>
</head>
<body>

    <div id="global-loader">
        <i class="fas fa-car-side spinner-car"></i>
        <h6 class="mt-4 fw-black tracking-widest text-uppercase" style="color: var(--theme-primary); letter-spacing: 3px;">SmartDrive X</h6>
    </div>

    <div class="top-utility-bar d-none d-md-block">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <span class="me-4"><i class="fas fa-envelope me-2 opacity-75"></i> support@smartdrivex.com</span>
                <span><i class="fas fa-phone-alt me-2 opacity-75"></i> +91 98765 43210</span>
            </div>
            <div>
                <span class="me-4"><i class="fas fa-map-marker-alt me-1 text-danger"></i> Serving 4 Major Hubs</span>
                <a href="#" class="me-3"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg smart-navbar sticky-top py-2 py-lg-3">
        <div class="container">
            
            <a class="navbar-brand" href="<?php echo $base_url; ?>index.php">
                <i class="fas fa-car-side me-2"></i>SmartDrive<span style="color: var(--theme-secondary);">X</span>
            </a>
            
            <button class="navbar-toggler border-0 shadow-none p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">
                <i class="fas fa-bars fs-2" style="color: var(--theme-primary);"></i>
            </button>
            
            <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarNav">
                <ul class="navbar-nav me-auto ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_filename == 'index' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>index.php">
                            <i class="fas fa-home me-1 d-none d-xl-inline"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_filename == 'search_cars' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>customer/search_cars.php">
                            <i class="fas fa-compass me-1 d-none d-xl-inline"></i> Explore Fleet
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    
                    <li class="nav-item">
                        <button class="nav-icon-btn" data-bs-toggle="modal" data-bs-target="#searchModal" title="Search Fleet">
                            <i class="fas fa-search"></i>
                        </button>
                    </li>

                    <li class="nav-item me-1">
                        <button class="nav-icon-btn theme-toggle-btn" title="Toggle Theme">
                            <i class="fas fa-moon"></i>
                        </button>
                    </li>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        
                        <?php if ($_SESSION['role_id'] == 1): ?>
                            <li class="nav-item me-2">
                                <a class="btn rounded-pill fw-bold px-4 shadow-sm text-white" href="<?php echo $base_url; ?>admin/dashboard.php" style="background-color: var(--theme-primary);">
                                    <i class="fas fa-shield-alt me-2"></i>Admin Panel
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item me-2">
                                <span class="badge rounded-pill border p-2 px-3 shadow-sm d-flex align-items-center" style="background: var(--bs-body-bg); color: var(--bs-body-color);">
                                    <i class="fas fa-star text-warning me-2"></i> <?php echo number_format($_SESSION['loyalty_points'] ?? 0); ?> Pts
                                </span>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item dropdown me-2" id="notifDropdownLi">
                            <button class="nav-icon-btn dropdown-toggle text-decoration-none position-relative" 
                                    data-bs-toggle="dropdown" aria-expanded="false" id="notifBellBtn" onclick="window.TitanEngine.loadDropdown()">
                                <i class="<?php echo $initial_notif_count > 0 ? 'fas' : 'far'; ?> fa-bell" id="bellIcon" style="<?php echo $initial_notif_count > 0 ? 'color:#dc3545;' : ''; ?>"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?php echo $initial_notif_count > 0 ? '' : 'd-none'; ?>" 
                                      id="notifBadge" style="font-size:0.6rem; min-width:18px;"><?php echo $initial_notif_count > 99 ? '99+' : $initial_notif_count; ?></span>
                            </button>
                            
                            <div class="dropdown-menu dropdown-menu-end notify-dropdown bg-white" id="notifDropdown">
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="background: var(--theme-primary); color: white;">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-bell"></i>
                                        <span class="fw-bold tracking-wide text-uppercase" style="font-size: 0.85rem;">Notifications</span>
                                        <span class="badge rounded-pill bg-white text-dark" id="dropdownCount"><?php echo $initial_notif_count; ?></span>
                                    </div>
                                    <button class="btn btn-sm text-white border-0 p-0 opacity-75 fw-bold" onclick="window.TitanEngine.markAllRead()" style="font-size:0.75rem; background:none;">
                                        <i class="fas fa-check-double me-1"></i>Mark all read
                                    </button>
                                </div>
                                
                                <div id="notifItems" class="custom-scroll" style="max-height:360px; overflow-y:auto;">
                                    <div class="p-3 border-bottom">
                                        <div class="d-flex gap-3">
                                            <div class="skeleton-avatar"></div>
                                            <div class="flex-grow-1">
                                                <div class="skeleton-text w-75"></div>
                                                <div class="skeleton-text w-100"></div>
                                                <div class="skeleton-text w-50"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="p-3 text-center border-top bg-light">
                                    <a href="<?php echo $base_url; ?><?php echo $_SESSION['role_id']==1?'admin':'customer'; ?>/notifications.php" class="text-decoration-none small fw-bold" style="color: var(--theme-primary);">
                                        View All Notifications <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center rounded-pill pe-3 py-1 shadow-sm border" href="#" data-bs-toggle="dropdown" style="background: var(--bs-body-bg);">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=<?php echo str_replace('#', '', $theme_primary); ?>&color=fff&bold=true" 
                                     alt="Profile" class="rounded-circle shadow-sm me-2" width="36" height="36">
                                <span class="fw-bold d-none d-xl-inline"><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0]); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end profile-dropdown bg-white">
                                <li class="px-3 py-3 border-bottom mb-2 text-center bg-light rounded-top">
                                    <h6 class="fw-black mb-0 text-dark"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></h6>
                                    <small class="text-muted fw-bold"><?php echo $_SESSION['role_id'] == 1 ? 'System Administrator' : 'Valued Customer'; ?></small>
                                </li>
                                
                                <?php if ($_SESSION['role_id'] == 2): ?>
                                    <li><a class="dropdown-item" href="<?php echo $base_url; ?>customer/dashboard.php"><i class="fas fa-layer-group text-muted me-2"></i> My Garage</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $base_url; ?>customer/profile.php"><i class="fas fa-user-cog text-muted me-2"></i> Settings & KYC</a></li>
                                    <li><hr class="dropdown-divider my-2"></li>
                                <?php endif; ?>
                                
                                <li><a class="dropdown-item text-danger fw-bold" href="<?php echo $base_url; ?>logout.php"><i class="fas fa-power-off me-2"></i> Secure Logout</a></li>
                            </ul>
                        </li>

                    <?php else: // GUEST ACTIONS ?>
                        <li class="nav-item d-none d-xl-block">
                            <a class="nav-link fw-bold" href="<?php echo $base_url; ?>login.php">Log In</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn rounded-pill px-4 py-2 fw-bold shadow-sm text-white" href="<?php echo $base_url; ?>register.php" style="background-color: var(--theme-primary);">Sign Up Free</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="modal fade" id="searchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content bg-transparent border-0">
                <div class="modal-header border-0 pe-5 pt-5">
                    <button type="button" class="btn-close btn-close-white fs-4 shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex align-items-center justify-content-center">
                    <form action="<?php echo $base_url; ?>customer/search_cars.php" method="GET" class="w-75 text-center">
                        <h2 class="text-white fw-black mb-4">What are you looking for?</h2>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control search-input-huge" placeholder="e.g., Porsche 911, SUV, Mumbai..." autofocus>
                        </div>
                        <p class="text-white opacity-50 mt-4 tracking-wide text-uppercase small fw-bold"><i class="fas fa-keyboard me-2"></i>Press Enter to Search</p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end bg-white" tabindex="-1" id="mobileMenu">
        <div class="offcanvas-header border-bottom p-4">
            <h4 class="offcanvas-title fw-black" style="color: var(--theme-primary);"><i class="fas fa-car-side me-2"></i>SmartDrive<span class="opacity-50">X</span></h4>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-4">
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="text-center mb-4 pb-4 border-bottom bg-light rounded-4 p-3 shadow-sm">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=<?php echo str_replace('#', '', $theme_primary); ?>&color=fff&bold=true" 
                         alt="Profile" class="rounded-circle shadow-sm mb-3 border border-white border-4" width="80" height="80">
                    <h5 class="fw-black mb-1 text-dark"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
                    <?php if ($_SESSION['role_id'] == 2): ?>
                        <span class="badge bg-warning text-dark rounded-pill shadow-sm py-2 px-3 mt-1"><i class="fas fa-star me-1"></i> <?php echo number_format($_SESSION['loyalty_points'] ?? 0); ?> Points</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <ul class="navbar-nav fs-5 flex-grow-1 gap-2">
                <li class="nav-item"><a class="nav-link fw-bold rounded-3 px-3" href="<?php echo $base_url; ?>index.php"><i class="fas fa-home me-3 text-muted"></i> Home</a></li>
                <li class="nav-item"><a class="nav-link fw-bold rounded-3 px-3" href="<?php echo $base_url; ?>customer/search_cars.php"><i class="fas fa-compass me-3 text-muted"></i> Explore Fleet</a></li>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="my-2 border-top"></li>
                    <?php if ($_SESSION['role_id'] == 1): ?>
                        <li class="nav-item"><a class="nav-link fw-bold text-warning rounded-3 px-3" href="<?php echo $base_url; ?>admin/dashboard.php"><i class="fas fa-shield-alt me-3"></i> Admin Panel</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link fw-bold rounded-3 px-3" href="<?php echo $base_url; ?>customer/dashboard.php"><i class="fas fa-layer-group me-3 text-muted"></i> My Garage</a></li>
                        <li class="nav-item"><a class="nav-link fw-bold rounded-3 px-3" href="<?php echo $base_url; ?>customer/profile.php"><i class="fas fa-user-cog me-3 text-muted"></i> Settings</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <div class="mt-auto pt-4 border-top">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $base_url; ?>logout.php" class="btn btn-outline-danger w-100 rounded-pill fw-bold py-3 shadow-sm"><i class="fas fa-sign-out-alt me-2"></i> Secure Logout</a>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>login.php" class="btn btn-outline-dark w-100 rounded-pill fw-bold py-3 mb-3 shadow-sm">Log In</a>
                    <a href="<?php echo $base_url; ?>register.php" class="btn w-100 rounded-pill fw-bold py-3 text-white shadow-sm" style="background-color: var(--theme-primary);">Sign Up Free</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // 1. BULLETPROOF PRELOADER
        let _loaderHidden = false;
        function hideGlobalLoader() {
            if (_loaderHidden) return;
            _loaderHidden = true;
            const loader = document.getElementById('global-loader');
            if (!loader) return;
            loader.style.opacity = '0';
            setTimeout(() => { loader.style.display = 'none'; }, 500);
        }
        document.addEventListener('DOMContentLoaded', hideGlobalLoader);
        setTimeout(hideGlobalLoader, 1500); // 1.5s hard failsafe

        // 2. TITAN NOTIFICATION ENGINE (IIFE to prevent global pollution)
        <?php if (isset($_SESSION['user_id'])): ?>
        window.TitanEngine = (function() {
            const BASE = '<?php echo $base_url; ?>';
            const API  = BASE + 'api/notifications.php';
            let lastCount = <?php echo $initial_notif_count; ?>;
            let dropdownLoaded = false;

            // UI Updaters
            function updateBadge(cnt) {
                const badge = document.getElementById('notifBadge');
                const bell  = document.getElementById('bellIcon');
                const dCount = document.getElementById('dropdownCount');
                if (!badge || !bell) return;
                
                if (cnt > 0) {
                    badge.textContent = cnt > 99 ? '99+' : cnt;
                    badge.classList.remove('d-none');
                    bell.classList.replace('far', 'fas');
                    bell.style.color = '#dc3545';
                } else {
                    badge.classList.add('d-none');
                    bell.classList.replace('fas', 'far');
                    bell.style.color = '';
                }
                if(dCount) dCount.textContent = cnt;
            }

            function renderItems(notifs) {
                const box = document.getElementById('notifItems');
                if (!box) return;
                
                if (!notifs.length) {
                    box.innerHTML = '<div class="text-center p-5 text-muted fw-bold"><i class="far fa-bell-slash fa-3x mb-3 d-block opacity-50"></i>You are all caught up!</div>';
                    return;
                }

                const typeColors = { success: '19,135,84', info: '13,202,240', warning: '255,193,7', danger: '220,53,69', announcement: '111,66,193' };
                const typeText = { success: '#198754', info: '#0a6070', warning: '#856404', danger: '#dc3545', announcement: '#6f42c1' };

                box.innerHTML = notifs.slice(0, 8).map(n => {
                    const rgb = typeColors[n.type] || '74,92,67';
                    const tc  = typeText[n.type] || 'var(--theme-primary)';
                    const bgClass = n.is_read == 0 ? 'bg-light' : 'bg-white';
                    const link = n.link ? BASE + n.link : 'javascript:void(0)';
                    
                    return `
                    <div class="d-flex align-items-start gap-3 p-3 border-bottom ${bgClass}" 
                         style="cursor:pointer; transition: background 0.2s;" 
                         onmouseover="this.style.background='rgba(0,0,0,0.03)'" 
                         onmouseout="this.style.background='${n.is_read == 0 ? '#f8f9fa' : '#fff'}'"
                         onclick="window.TitanEngine.readItem(${n.id}, '${link}')">
                        
                        <div style="width:40px; height:40px; border-radius:12px; background:rgba(${rgb},0.1); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="fas ${n.icon}" style="color:${tc}; font-size:1rem;"></i>
                        </div>
                        <div style="min-width:0; flex-grow:1;">
                            <div class="fw-bold text-dark d-flex justify-content-between align-items-center" style="font-size:0.85rem;">
                                <span class="text-truncate pe-2">${n.title}</span>
                                ${n.is_read == 0 ? '<span class="badge rounded-pill bg-danger" style="font-size:0.6rem;">New</span>' : ''}
                            </div>
                            <div class="text-muted text-truncate" style="font-size:0.8rem; max-width:260px;">${n.message}</div>
                            <div class="text-muted fw-bold mt-1" style="font-size:0.7rem;"><i class="far fa-clock me-1 opacity-50"></i>${n.time_ago}</div>
                        </div>
                    </div>`;
                }).join('');
            }

            // Polling Function
            function poll() {
                fetch(API + '?action=fetch&limit=10', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if(data.error) return; // Silent fail
                    const newCount = parseInt(data.unread_count) || 0;
                    
                    // Show Toast if count increased
                    if (newCount > lastCount && lastCount >= 0) {
                        const unread = (data.notifications || []).filter(n => n.is_read == 0);
                        const fresh  = unread.slice(0, newCount - lastCount);
                        fresh.forEach((n, i) => {
                            setTimeout(() => { if(typeof showToast === 'function') showToast(n.title, n.type); }, i * 600);
                        });
                    }

                    lastCount = newCount;
                    updateBadge(newCount);
                    if (dropdownLoaded) renderItems(data.notifications || []);
                }).catch(() => {});
            }

            // Public API methods attached to window.TitanEngine
            return {
                init: function() {
                    updateBadge(lastCount);
                    poll();
                    setInterval(poll, 5000); // 5s Poll
                    
                    // Stop rendering if dropdown closes
                    document.addEventListener('hidden.bs.dropdown', (e) => {
                        if (e.target && e.target.closest('#notifDropdownLi')) dropdownLoaded = false;
                    });
                },
                loadDropdown: function() {
                    dropdownLoaded = true;
                    fetch(API + '?action=fetch&limit=8', { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if(!data.error) {
                            renderItems(data.notifications || []);
                            updateBadge(data.unread_count);
                            lastCount = data.unread_count;
                        }
                    });
                },
                readItem: function(id, url) {
                    fetch(API, { 
                        method: 'POST', 
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                        body: 'action=mark_read&id=' + id 
                    }).finally(() => {
                        if (url && url !== 'javascript:void(0)') window.location.href = url;
                    });
                },
                markAllRead: function() {
                    fetch(API, { 
                        method: 'POST', 
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                        body: 'action=mark_all' 
                    }).then(() => {
                        lastCount = 0;
                        updateBadge(0);
                        this.loadDropdown();
                    });
                }
            };
        })();
        
        // Start Engine
        document.addEventListener('DOMContentLoaded', window.TitanEngine.init);
        <?php endif; ?>
    </script>