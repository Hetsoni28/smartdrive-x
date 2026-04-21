<?php
// ==========================================
// 🧭 ENTERPRISE ROUTING & ACTIVE STATE ENGINE
// ==========================================
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role_id'] ?? 0;

// 🌐 BULLETPROOF PATHING
$base_url = isset($base_url) ? $base_url : "http://localhost/smartdrive_x/";

// ==========================================
// 🔔 LIVE NOTIFICATION ENGINE (Failsafe wrapped)
// ==========================================
$admin_pending_count = 0;
if ($role == 1 && isset($conn)) {
    try {
        $badge_stmt = $conn->prepare("SELECT COUNT(*) as c FROM bookings WHERE booking_status = 'pending'");
        if ($badge_stmt) {
            $badge_stmt->execute();
            $admin_pending_count = $badge_stmt->get_result()->fetch_assoc()['c'] ?? 0;
            $badge_stmt->close();
        }
    } catch (Exception $e) {
        $admin_pending_count = 0; // Failsafe
    }
}
?>

<?php if ($role == 1): // 🎨 ADMIN TEAL THEME ?>
<style>
    :root {
        --sidebar-primary: #4da89c;      
        --sidebar-secondary: #8bd0b4;    
        --sidebar-bg: #131d1b;           
        --sidebar-text: #a8c4c0;         
        --sidebar-hover: rgba(77, 168, 156, 0.15);
        --sidebar-active-bg: rgba(77, 168, 156, 0.2);
    }
</style>
<?php else: // 🎨 CUSTOMER SAGE THEME ?>
<style>
    :root {
        --sidebar-primary: #889c7c;      
        --sidebar-secondary: #b9cbb3;    
        --sidebar-bg: #1a2018;           
        --sidebar-text: #b0bfa9;         
        --sidebar-hover: rgba(136, 156, 124, 0.15);
        --sidebar-active-bg: rgba(136, 156, 124, 0.2);
    }
</style>
<?php endif; ?>

<style>
    /* ==========================================
       🌿 SAAS SIDEBAR ARCHITECTURE
       ========================================== */
    .dashboard-layout {
        display: flex; min-height: calc(100vh - 76px); 
        background-color: var(--bs-body-bg); position: relative; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .beast-sidebar {
        width: 280px; background: var(--sidebar-bg); color: var(--sidebar-text);
        flex-shrink: 0; border-right: 1px solid rgba(255,255,255,0.05);
        transition: width 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        z-index: 100; display: flex; flex-direction: column; position: relative;
    }

    /* 🦴 Skeleton Loader inside Sidebar */
    .sidebar-skeleton { background: rgba(255,255,255,0.05); color: transparent !important; border-radius: 8px; animation: pulse-bg 1.5s infinite; pointer-events: none; }
    @keyframes pulse-bg { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }

    /* Collapsed "Mini" State */
    .beast-sidebar.collapsed { width: 85px; }
    .beast-sidebar.collapsed .sidebar-text, 
    .beast-sidebar.collapsed .dropdown-chevron,
    .beast-sidebar.collapsed .sidebar-widget,
    .beast-sidebar.collapsed .user-info-block,
    .beast-sidebar.collapsed .quick-action-text {
        display: none !important;
    }
    .beast-sidebar.collapsed .sidebar-heading { text-align: center; font-size: 0.5rem; padding-left: 0; margin-top: 1rem;}
    .beast-sidebar.collapsed .nav-link { justify-content: center; padding: 1rem 0; margin: 0.2rem 0.5rem; }
    .beast-sidebar.collapsed .nav-link i.menu-icon { margin-right: 0 !important; font-size: 1.4rem; }

    /* Sidebar Toggle Button */
    .sidebar-toggler {
        position: absolute; right: -16px; top: 35px; width: 32px; height: 32px;
        background: var(--sidebar-primary); color: white; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; cursor: pointer;
        z-index: 101; border: 4px solid var(--bs-body-bg); transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .sidebar-toggler:hover { transform: scale(1.15); background: var(--sidebar-secondary); color: var(--sidebar-bg); }

    /* Custom Scrollbar */
    .sidebar-sticky {
        position: sticky; top: 76px; height: calc(100vh - 76px);
        overflow-y: auto; overflow-x: hidden; padding: 1.5rem 1rem;
        display: flex; flex-direction: column;
    }
    .sidebar-sticky::-webkit-scrollbar { width: 4px; }
    .sidebar-sticky::-webkit-scrollbar-track { background: transparent; }
    .sidebar-sticky::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }

    .sidebar-heading {
        font-size: 0.7rem; text-transform: uppercase; letter-spacing: 2px;
        color: rgba(255,255,255,0.3); margin-bottom: 0.8rem; font-weight: 800;
        margin-top: 1.5rem; padding-left: 1rem; white-space: nowrap;
    }

    /* 🔗 Navigation Links & The "Active Pill" */
    .sidebar-nav .nav-link {
        color: var(--sidebar-text); font-weight: 600; padding: 0.8rem 1rem;
        border-radius: 12px; margin-bottom: 0.3rem; transition: all 0.3s ease; 
        display: flex; align-items: center; justify-content: space-between;
        white-space: nowrap; position: relative; overflow: hidden;
    }
    .sidebar-nav .nav-link .link-content { display: flex; align-items: center; }
    .sidebar-nav .nav-link i.menu-icon { width: 30px; font-size: 1.2rem; transition: transform 0.3s ease; margin-right: 10px; text-align: center; }

    /* Hover State */
    .sidebar-nav .nav-link:hover, .sidebar-nav .nav-link[aria-expanded="true"] {
        background: var(--sidebar-hover); color: white; transform: translateX(4px);
    }
    
    /* Active State (The Pill) */
    .sidebar-nav .nav-link.active {
        background: var(--sidebar-active-bg); color: white; font-weight: 700;
    }
    .sidebar-nav .nav-link.active::before {
        content: ''; position: absolute; left: 0; top: 10%; height: 80%; width: 4px;
        background-color: var(--sidebar-primary); border-radius: 0 4px 4px 0;
    }
    .sidebar-nav .nav-link.active i.menu-icon, .sidebar-nav .nav-link[aria-expanded="true"] i.menu-icon {
        color: var(--sidebar-primary); transform: scale(1.1);
    }

    /* 🔽 Accordion Sub-Menus */
    .sidebar-submenu { list-style: none; padding-left: 2.5rem; margin-top: 0.2rem; margin-bottom: 0.5rem; }
    .sidebar-submenu .nav-link {
        padding: 0.6rem 1rem; font-size: 0.9rem; background: transparent !important; 
        color: rgba(255,255,255,0.5); border: none !important; margin-bottom: 0;
    }
    .sidebar-submenu .nav-link.active { color: white; background: transparent !important; font-weight: 800; }
    .sidebar-submenu .nav-link.active::before { display: none; } /* Hide pill on submenu */
    .sidebar-submenu .nav-link::before {
        content: '•'; position: absolute; left: -10px; font-size: 1.2rem; color: rgba(255,255,255,0.2); transition: color 0.3s ease;
    }
    .sidebar-submenu .nav-link:hover, .sidebar-submenu .nav-link.active { color: var(--sidebar-secondary); transform: translateX(3px); }
    .sidebar-submenu .nav-link.active::before { color: var(--sidebar-secondary); }

    .dropdown-chevron { font-size: 0.8rem; transition: transform 0.3s ease; }
    .nav-link[aria-expanded="true"] .dropdown-chevron { transform: rotate(180deg); }

    /* ⚡ Quick Action Button */
    .quick-action-btn {
        background: linear-gradient(135deg, var(--sidebar-primary) 0%, rgba(255,255,255,0.2) 100%);
        color: white !important; border: 1px solid rgba(255,255,255,0.1);
        padding: 12px; border-radius: 12px; text-align: center; display: block;
        margin: 1.2rem 0; font-weight: 800; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); text-decoration: none;
    }
    .quick-action-btn:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.3); transform: translateY(-3px); color: white; }

    /* 📊 Bottom Widgets */
    .sidebar-widget {
        background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.05);
        border-radius: 16px; padding: 1.2rem; margin-top: auto; 
    }

    /* Main Content Area */
    .dashboard-content {
        flex-grow: 1; padding: 2.5rem; overflow-x: hidden;
        width: calc(100% - 280px); transition: width 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .dashboard-layout.sidebar-minimized .dashboard-content { width: calc(100% - 85px); }

    @media (max-width: 991.98px) {
        .beast-sidebar { display: none; }
        .dashboard-content { padding: 1.5rem; width: 100% !important; }
    }
</style>

<div class="beast-sidebar shadow-lg" id="mainSidebar">
    
    <div class="sidebar-toggler shadow-sm" id="sidebarToggleBtn">
        <i class="fas fa-angle-left" id="toggleIcon"></i>
    </div>

    <div class="sidebar-sticky">
        
        <div class="d-flex align-items-center mb-3 p-3 rounded-4 shadow-sm" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);">
            <div class="position-relative">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Guest'); ?>&background=<?php echo $role == 1 ? '4da89c' : '889c7c'; ?>&color=fff&bold=true" class="rounded-circle border border-2 border-white" width="48" height="48">
                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-2 border-dark rounded-circle" style="margin-right: -2px; margin-bottom: 2px;"></span>
            </div>
            <div class="overflow-hidden ms-3 user-info-block">
                <h6 class="mb-0 fw-bold text-white text-truncate" title="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?>"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></h6>
                <small class="fw-bold text-uppercase" style="color: var(--sidebar-secondary); font-size: 0.65rem; letter-spacing: 1px;">
                    <?php echo $role == 1 ? '<i class="fas fa-crown me-1"></i> System Admin' : '<i class="fas fa-user-check me-1"></i> Premium Client'; ?>
                </small>
            </div>
        </div>

        <?php if($role == 1): ?>
            <a href="<?php echo $base_url; ?>admin/manage_cars.php" class="quick-action-btn">
                <i class="fas fa-plus me-1"></i> <span class="quick-action-text">Add Vehicle</span>
            </a>
        <?php else: ?>
            <a href="<?php echo $base_url; ?>customer/search_cars.php" class="quick-action-btn">
                <i class="fas fa-car me-1"></i> <span class="quick-action-text">Book New Ride</span>
            </a>
        <?php endif; ?>

        <ul class="nav flex-column sidebar-nav flex-grow-1 mt-2">
            
            <?php if ($role == 1): // ================== 🛡️ ADMIN SYSTEM MAP ================== ?>
                
                <div class="sidebar-heading">Analytics</div>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/dashboard.php">
                        <div class="link-content"><i class="fas fa-chart-pie menu-icon"></i> <span class="sidebar-text">Command Center</span></div>
                    </a>
                </li>

                <div class="sidebar-heading mt-3">Operations</div>
                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($current_page, ['manage_bookings.php', 'invoice.php', 'late_returns.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#bookingsMenu" style="cursor: pointer;" role="button" aria-expanded="<?php echo in_array($current_page, ['manage_bookings.php', 'invoice.php', 'late_returns.php']) ? 'true' : 'false'; ?>">
                        <div class="link-content">
                            <i class="fas fa-calendar-check menu-icon"></i> <span class="sidebar-text">Reservations</span>
                        </div>
                        <div class="sidebar-text d-flex align-items-center">
                            <?php if($admin_pending_count > 0): ?>
                                <span class="badge bg-danger rounded-pill me-2 shadow-sm anim-pulse" style="font-size: 0.65rem;"><?php echo $admin_pending_count; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down dropdown-chevron"></i>
                        </div>
                    </a>
                    <div class="collapse <?php echo in_array($current_page, ['manage_bookings.php', 'invoice.php', 'late_returns.php']) ? 'show' : ''; ?>" id="bookingsMenu">
                        <ul class="sidebar-submenu">
                            <li><a class="nav-link <?php echo $current_page == 'manage_bookings.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_bookings.php">All Bookings</a></li>
                            <li><a class="nav-link <?php echo $current_page == 'late_returns.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/late_returns.php">Late Returns</a></li>
                            <li><a class="nav-link" href="<?php echo $base_url; ?>admin/manage_bookings.php?status=confirmed">Financial Reports</a></li>
                        </ul>
                    </div>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo in_array($current_page, ['manage_cars.php', 'manage_locations.php', 'manage_maintenance.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#fleetMenu" style="cursor: pointer;" role="button" aria-expanded="<?php echo in_array($current_page, ['manage_cars.php', 'manage_locations.php', 'manage_maintenance.php']) ? 'true' : 'false'; ?>">
                        <div class="link-content"><i class="fas fa-car-side menu-icon"></i> <span class="sidebar-text">Fleet Control</span></div>
                        <i class="fas fa-chevron-down dropdown-chevron sidebar-text"></i>
                    </a>
                    <div class="collapse <?php echo in_array($current_page, ['manage_cars.php', 'manage_locations.php', 'manage_maintenance.php']) ? 'show' : ''; ?>" id="fleetMenu">
                        <ul class="sidebar-submenu">
                            <li><a class="nav-link <?php echo $current_page == 'manage_cars.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_cars.php">Vehicle Inventory</a></li>
                            <li><a class="nav-link <?php echo $current_page == 'manage_locations.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_locations.php">City Hubs</a></li>
                            <li><a class="nav-link <?php echo $current_page == 'manage_maintenance.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_maintenance.php">Maintenance Logs</a></li>
                        </ul>
                    </div>
                </li>

                <div class="sidebar-heading mt-3">Growth & Users</div>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_users.php">
                        <div class="link-content"><i class="fas fa-users menu-icon"></i> <span class="sidebar-text">Manage Customers</span></div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'manage_coupons.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_coupons.php">
                        <div class="link-content"><i class="fas fa-ticket-alt menu-icon"></i> <span class="sidebar-text">Discount Engine</span></div>
                    </a>
                </li>
                <?php
                // Count new contact messages
                $contact_new_count = 0;
                if (isset($conn)) {
                    $cn_result = mysqli_query($conn, "SELECT COUNT(*) as c FROM contact_messages WHERE status = 'new'");
                    $contact_new_count = $cn_result ? (mysqli_fetch_assoc($cn_result)['c'] ?? 0) : 0;
                }
                ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'manage_contacts.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/manage_contacts.php">
                        <div class="link-content"><i class="fas fa-envelope menu-icon"></i> <span class="sidebar-text">Contact Messages</span></div>
                        <?php if ($contact_new_count > 0): ?>
                        <span class="badge bg-info rounded-pill shadow-sm sidebar-text" style="font-size: 0.65rem;"><?php echo $contact_new_count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <div class="sidebar-heading mt-3">Configuration</div>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_settings.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/admin_settings.php">
                        <div class="link-content"><i class="fas fa-cogs menu-icon"></i> <span class="sidebar-text">System Settings</span></div>
                    </a>
                </li>

            <?php else: // ================== 👤 CUSTOMER SYSTEM MAP ================== ?>

                <div class="sidebar-heading">My Garage</div>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>customer/dashboard.php">
                        <div class="link-content"><i class="fas fa-home menu-icon"></i> <span class="sidebar-text">My Dashboard</span></div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'search_cars.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>customer/search_cars.php">
                        <div class="link-content"><i class="fas fa-search menu-icon"></i> <span class="sidebar-text">Book a New Ride</span></div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'rental_history.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>customer/rental_history.php">
                        <div class="link-content"><i class="fas fa-history menu-icon"></i> <span class="sidebar-text">Rental History</span></div>
                    </a>
                </li>
                
                <div class="sidebar-heading mt-4">Settings</div>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>customer/profile.php">
                        <div class="link-content"><i class="fas fa-user-cog menu-icon"></i> <span class="sidebar-text">Account Profile</span></div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>" href="<?php echo $base_url; ?>customer/notifications.php">
                        <div class="link-content">
                            <i class="fas fa-bell menu-icon"></i>
                            <span class="sidebar-text">Notifications</span>
                            <span class="notif-sidebar-badge ms-auto badge rounded-pill bg-danger d-none" id="sidebarNotifBadge" style="font-size:.65rem;"></span>
                        </div>
                    </a>
                </li>
                <li class="nav-item mt-2">
                    <a class="nav-link <?php echo $current_page == 'help_center.php' ? 'text-warning active' : 'text-warning'; ?>"
                       href="<?php echo $base_url; ?>customer/help_center.php"
                       style="background: rgba(255, 193, 7, 0.07); border: 1px solid rgba(255,193,7,0.2);">
                        <div class="link-content">
                            <i class="fas fa-headset menu-icon text-warning"></i> <span class="sidebar-text text-warning fw-bold">Help Center</span>
                        </div>
                    </a>
                </li>

            <?php endif; ?>

            <div class="sidebar-heading mt-4">Security</div>
            <li class="nav-item mt-1">
                <a class="nav-link text-danger" href="<?php echo $base_url; ?>logout.php" style="background: rgba(220, 53, 69, 0.05); border: 1px solid rgba(220, 53, 69, 0.2);">
                    <div class="link-content"><i class="fas fa-power-off menu-icon text-danger"></i> <span class="sidebar-text text-danger fw-bold">Secure Logout</span></div>
                </a>
            </li>
        </ul>

        <?php if ($role == 1): ?>
            <div class="sidebar-widget mt-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-uppercase fw-bold text-white tracking-wide"><i class="fas fa-server me-2" style="color: var(--sidebar-secondary);"></i> Server Status</span>
                </div>
                <div class="d-flex justify-content-between text-muted small mb-1 fw-bold">
                    <span>Database</span><span class="text-success"><i class="fas fa-check-circle me-1"></i>Connected</span>
                </div>
                <div class="progress mb-2 rounded-pill shadow-sm" style="height: 6px; background: rgba(255,255,255,0.1);">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 100%;"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="sidebar-widget mt-4 text-center">
                <i class="fas fa-gem fa-2x mb-3" style="color: var(--sidebar-secondary);"></i>
                <h6 class="fw-bold text-white mb-1">Smart Rewards</h6>
                <p class="small opacity-75 mb-0 fw-bold"><?php echo isset($_SESSION['loyalty_points']) ? number_format($_SESSION['loyalty_points']) : '0'; ?> Points</p>
                <div class="mt-3 pt-3 border-top border-light border-opacity-10 text-start">
                    <div class="d-flex justify-content-between small fw-bold mb-2 text-white opacity-75">
                        <span>Profile Setup</span><span>80%</span>
                    </div>
                    <div class="progress rounded-pill shadow-sm" style="height: 6px; background: rgba(255,255,255,0.1);">
                        <div class="progress-bar" role="progressbar" style="width: 80%; background-color: var(--sidebar-secondary);"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>