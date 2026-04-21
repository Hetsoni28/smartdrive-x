<?php
session_start();

// 🛡️ SECURITY: Super Admin Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

$message = '';
$msg_type = '';

// Check session for PRG (Post/Redirect/Get) toast messages
if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

// ==========================================
// ⚙️ SECURE QUICK ACTIONS (Suspend / Unsuspend)
// ==========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action_id = intval($_GET['id']);
    
    if ($_GET['action'] == 'toggle_status') {
        $current = $_GET['current'];
        $new_status = ($current === 'active') ? 'suspended' : 'active';
        
        // Use Prepared Statements to prevent SQL Injection
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role_id = 2");
        $stmt->bind_param("si", $new_status, $action_id);
        
        if ($stmt->execute()) {
            $_SESSION['admin_msg'] = "Customer account has been successfully {$new_status}.";
            $_SESSION['admin_msg_type'] = ($new_status === 'suspended') ? "warning" : "success";
        } else {
            $_SESSION['admin_msg'] = "System Error: Could not update account status.";
            $_SESSION['admin_msg_type'] = "danger";
        }
        $stmt->close();
        
        header("Location: manage_users.php");
        exit();
    }
}

// ==========================================
// 🧠 ENTERPRISE BI: KPI METRICS
// ==========================================
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role_id = 2"))['total'] ?? 0;

$new_this_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total FROM users 
    WHERE role_id = 2 AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())
"))['total'] ?? 0;

$top_tier_query = mysqli_query($conn, "
    SELECT COUNT(DISTINCT user_id) as total 
    FROM bookings 
    WHERE booking_status IN ('confirmed', 'active', 'completed') 
    GROUP BY user_id 
    HAVING COUNT(id) > 5
");
$top_tier_count = mysqli_num_rows($top_tier_query);

// New Metric: Suspended Accounts Risk Pool
$suspended_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role_id = 2 AND status = 'suspended'"))['total'] ?? 0;


// ==========================================
// 🔍 ADVANCED SEARCH, FILTER & PAGINATION
// ==========================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_sql = "WHERE u.role_id = 2 ";
if (!empty($search)) {
    $where_sql .= " AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%' OR u.phone LIKE '%$search%') ";
}
if ($status_filter != 'all') {
    $where_sql .= " AND u.status = '$status_filter' ";
}

// Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM users u $where_sql";
$total_records = mysqli_fetch_assoc(mysqli_query($conn, $count_query))['total'] ?? 0;
$total_pages = ceil($total_records / $limit);

// Main Financial Aggregation Query
$users_query = mysqli_query($conn, "
    SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, u.loyalty_points,
           COUNT(b.id) as total_bookings,
           COALESCE(SUM(b.final_price), 0) as total_spent
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id AND b.booking_status IN ('confirmed', 'active', 'completed')
    $where_sql
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
");

$page_title = "Manage Customers | SmartDrive X Admin";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🎨 ADMIN TEAL THEME */
    :root {
        --teal-primary: #4da89c;
        --teal-dark: #1a2624;
        --teal-light: #ccecd4;
    }
    
    body { background-color: #f4f7f6; }
    
    .admin-header-card {
        background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal-primary) 100%);
        color: white; border-radius: 20px; padding: 2.5rem;
        position: relative; overflow: hidden;
    }
    .admin-header-card::after {
        content: '\f0c0'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: 20px; bottom: -30px; font-size: 10rem; 
        color: rgba(255,255,255,0.05); transform: rotate(-15deg); pointer-events: none;
    }

    .admin-stat-card {
        border-radius: 20px; border: 1px solid rgba(0,0,0,0.03); background: white;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
    }
    .admin-stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(77, 168, 156, 0.12); }
    
    .stat-icon-wrapper {
        width: 60px; height: 60px; border-radius: 16px;
        display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
    }

    /* Filter Bar */
    .filter-bar { background: white; border-radius: 20px; padding: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.02); }
    .form-control-search { background-color: #f8f9fa; border: none; border-radius: 12px; padding: 12px 20px; font-weight: 600; color: var(--teal-dark); }
    .form-control-search:focus { outline: none; box-shadow: 0 0 0 4px rgba(77, 168, 156, 0.15); background-color: white; }

    /* Enterprise Table */
    .table-container { background: white; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid rgba(0,0,0,0.02); }
    .table-beast { border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
    .table-beast th { background: #f8f9fa; color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 20px; font-weight: 800; }
    .table-beast tr { transition: all 0.2s; border-bottom: 1px solid #f1f1f1; }
    .table-beast tr:hover { background-color: rgba(77, 168, 156, 0.03); transform: scale(1.001); }
    .table-beast td { border: none; padding: 20px; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }

    .user-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--teal-light); }

    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--teal-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--teal-primary); color: white; box-shadow: 0 5px 15px rgba(77, 168, 156, 0.4); }

    /* 🔔 Live Toast Notifications */
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
    
    /* Custom Modal */
    .modal-content { border-radius: 24px; border: none; overflow: hidden; }
    .modal-header { background-color: var(--teal-dark); color: white; border: none; padding: 1.5rem; }
</style>

<div id="admin-toast-container"></div>

<div class="dashboard-layout">
    
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content">
        
        <div class="admin-header-card d-flex justify-content-between align-items-center flex-wrap mb-4 shadow-sm" data-aos="fade-in">
            <div style="z-index: 2;">
                <h2 class="fw-bold mb-1"><i class="fas fa-users me-2 opacity-75"></i>Client Roster</h2>
                <p class="mb-0 text-light opacity-75 fs-5">Monitor user activity, track lifetime spending, and manage accounts.</p>
            </div>
            <div class="mt-3 mt-md-0" style="z-index: 2;">
                <span class="badge bg-white text-dark rounded-pill px-4 py-2 shadow-sm fw-bold fs-6">
                    <i class="fas fa-database me-2" style="color: var(--teal-primary);"></i> <?php echo $total_customers; ?> Total Profiles
                </span>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="admin-stat-card p-4 h-100 border-bottom border-4" style="border-color: var(--teal-primary) !important;" data-aos="fade-up" data-aos-delay="0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold small text-uppercase mb-0">Total Clients</h6>
                        <div class="stat-icon-wrapper" style="background: rgba(77, 168, 156, 0.15); color: var(--teal-primary);"><i class="fas fa-users"></i></div>
                    </div>
                    <h2 class="fw-black text-dark mb-0"><?php echo $total_customers; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-stat-card p-4 h-100 border-bottom border-4 border-success" data-aos="fade-up" data-aos-delay="100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold small text-uppercase mb-0">New This Month</h6>
                        <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success"><i class="fas fa-user-plus"></i></div>
                    </div>
                    <h2 class="fw-black text-dark mb-0"><?php echo $new_this_month; ?> <span class="fs-6 text-success"><i class="fas fa-arrow-up"></i></span></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-stat-card p-4 h-100 border-bottom border-4 border-warning" data-aos="fade-up" data-aos-delay="200">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold small text-uppercase mb-0">Top Tier Users</h6>
                        <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning"><i class="fas fa-gem"></i></div>
                    </div>
                    <h2 class="fw-black text-dark mb-0"><?php echo $top_tier_count; ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-stat-card p-4 h-100 border-bottom border-4 border-danger" data-aos="fade-up" data-aos-delay="300">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-muted fw-bold small text-uppercase mb-0">Suspended</h6>
                        <div class="stat-icon-wrapper bg-danger bg-opacity-10 text-danger"><i class="fas fa-ban"></i></div>
                    </div>
                    <h2 class="fw-black text-dark mb-0"><?php echo $suspended_count; ?></h2>
                </div>
            </div>
        </div>

        <div class="filter-bar mb-4" data-aos="fade-up" data-aos-delay="400">
            <form action="manage_users.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-search" name="search" placeholder="Search by Name, Email, or Phone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-control-search" name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>✅ Active Accounts</option>
                        <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>❌ Suspended Accounts</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn text-white fw-bold rounded-3 shadow-sm py-2" style="background-color: var(--teal-primary);">Filter Roster</button>
                </div>
            </form>
        </div>

        <div class="table-container mb-4" data-aos="fade-up" data-aos-delay="500">
            <div class="table-responsive">
                <table class="table table-beast">
                    <thead>
                        <tr>
                            <th>Client Info</th>
                            <th>Contact Details</th>
                            <th class="text-center">Engagement</th>
                            <th>Lifetime Value</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($users_query) > 0): ?>
                            <?php while($user = mysqli_fetch_assoc($users_query)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=ccecd4&color=1a2624&bold=true" class="user-avatar me-3 shadow-sm">
                                            <div>
                                                <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($user['name']); ?></h6>
                                                <small class="text-muted fw-bold">Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark mb-1"><i class="fas fa-envelope text-muted me-1"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php if($user['phone']): ?>
                                            <div class="small text-muted fw-bold"><i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                                        <?php else: ?>
                                            <div class="small text-muted opacity-50 fw-bold"><i class="fas fa-phone-slash me-1"></i> No Phone</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 py-2 fs-6 shadow-sm"><i class="fas fa-car-side me-1 text-primary"></i> <?php echo $user['total_bookings']; ?> Trips</span>
                                    </td>
                                    <td>
                                        <h6 class="fw-black mb-0" style="color: var(--teal-primary);">₹<?php echo number_format($user['total_spent'], 0); ?></h6>
                                        <small class="text-warning fw-bold"><i class="fas fa-gem me-1"></i><?php echo $user['loyalty_points']; ?> Pts</small>
                                    </td>
                                    <td class="text-center">
                                        <?php if(isset($user['status']) && $user['status'] == 'suspended'): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-2"><i class="fas fa-ban me-1"></i> Suspended</span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i> Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm border rounded-circle shadow-sm" data-bs-toggle="dropdown" style="width: 35px; height: 35px;">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 overflow-hidden p-0">
                                                <li>
                                                    <a class="dropdown-item py-3 fw-bold border-bottom" href="javascript:void(0);" 
                                                       onclick="viewDossier('<?php echo htmlspecialchars($user['name']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['phone']); ?>', '<?php echo date('d M Y', strtotime($user['created_at'])); ?>', '<?php echo $user['total_bookings']; ?>', '<?php echo number_format($user['total_spent'], 0); ?>', '<?php echo $user['loyalty_points']; ?>')">
                                                        <i class="fas fa-id-card text-primary me-2"></i> View Dossier
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item py-3 fw-bold border-bottom" href="mailto:<?php echo htmlspecialchars($user['email']); ?>">
                                                        <i class="fas fa-envelope text-info me-2"></i> Send Email
                                                    </a>
                                                </li>
                                                <?php if(isset($user['status']) && $user['status'] == 'suspended'): ?>
                                                    <li>
                                                        <a class="dropdown-item py-3 text-success fw-bold" href="manage_users.php?action=toggle_status&id=<?php echo $user['id']; ?>&current=suspended" onclick="return confirm('Restore this account access?');">
                                                            <i class="fas fa-undo me-2"></i> Restore Account
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item py-3 text-danger fw-bold" href="manage_users.php?action=toggle_status&id=<?php echo $user['id']; ?>&current=active" onclick="return confirm('Suspend this user account? They will be locked out immediately.');">
                                                            <i class="fas fa-ban me-2"></i> Suspend Account
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-search-minus fa-4x mb-3 opacity-25 d-block"></i>
                                    <h5 class="fw-bold text-dark">No clients found.</h5>
                                    <p class="small fw-bold">Try adjusting your filters or search terms.</p>
                                    <a href="manage_users.php" class="btn rounded-pill fw-bold text-white px-4 mt-2" style="background: var(--teal-primary);">Clear Filters</a>
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
                            <a class="page-link" href="manage_users.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status_filter; ?>"> <?php echo $i; ?> </a>
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

<div class="modal fade" id="dossierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg bg-light border-0">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold"><i class="fas fa-id-card me-2 text-white"></i> Client Dossier</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="bg-white p-4 text-center border-bottom">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm border" style="width: 80px; height: 80px;">
                        <i class="fas fa-user-tie fa-2x" style="color: var(--teal-primary);"></i>
                    </div>
                    <h4 class="fw-black text-dark mb-1" id="dos-name">Client Name</h4>
                    <p class="text-muted small fw-bold mb-0">Member since <span id="dos-date"></span></p>
                </div>
                <div class="p-4">
                    <h6 class="text-uppercase text-muted fw-bold small mb-3 tracking-wide">Contact Information</h6>
                    <div class="bg-white rounded-3 p-3 border mb-4">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-envelope text-muted me-3" style="width: 20px;"></i>
                            <span class="fw-bold text-dark" id="dos-email"></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-phone-alt text-muted me-3" style="width: 20px;"></i>
                            <span class="fw-bold text-dark" id="dos-phone"></span>
                        </div>
                    </div>

                    <h6 class="text-uppercase text-muted fw-bold small mb-3 tracking-wide">Financial Metrics</h6>
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 border h-100">
                                <i class="fas fa-car-side text-primary mb-2"></i>
                                <h5 class="fw-black text-dark mb-0" id="dos-trips">0</h5>
                                <small class="text-muted fw-bold" style="font-size: 0.7rem;">Trips</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 border h-100">
                                <i class="fas fa-wallet text-success mb-2"></i>
                                <h5 class="fw-black text-dark mb-0">₹<span id="dos-spent">0</span></h5>
                                <small class="text-muted fw-bold" style="font-size: 0.7rem;">LTV</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white rounded-3 p-3 border h-100">
                                <i class="fas fa-gem text-warning mb-2"></i>
                                <h5 class="fw-black text-dark mb-0" id="dos-points">0</h5>
                                <small class="text-muted fw-bold" style="font-size: 0.7rem;">Points</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold w-100" data-bs-dismiss="modal">Close Dossier</button>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // 1. BEAST MODE TOAST NOTIFICATION ENGINE
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
            toast.innerHTML = `<i class="fas ${icon} fs-4"></i><div><h6 class="fw-bold mb-0 text-dark">System Update</h6><small class="text-muted fw-bold">${msg}</small></div>`;
            
            toastContainer.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 5000);
        });
    <?php endif; ?>

    // 2. Initialize AOS Animations
    AOS.init({ once: true, offset: 50, duration: 800, easing: 'ease-out-cubic' });

    // 3. Dynamic Dossier Populator
    function viewDossier(name, email, phone, date, trips, spent, points) {
        document.getElementById('dos-name').innerText = name;
        document.getElementById('dos-email').innerText = email;
        document.getElementById('dos-phone').innerText = phone || 'Not Provided';
        document.getElementById('dos-date').innerText = date;
        document.getElementById('dos-trips').innerText = trips;
        document.getElementById('dos-spent').innerText = spent;
        document.getElementById('dos-points').innerText = points;
        
        var dossierModal = new bootstrap.Modal(document.getElementById('dossierModal'));
        dossierModal.show();
    }
</script>

<?php include '../includes/footer.php'; ?>