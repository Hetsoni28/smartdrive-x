<?php
session_start();

// 🛡️ SECURITY: Super Admin Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php"); 
    exit();
}

include '../includes/db_connect.php';
include '../includes/notify.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';
$admin_id = (int)$_SESSION['user_id'];

// Auto-create notifications table (Failsafe)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL, message TEXT NOT NULL,
    type ENUM('success','info','warning','danger','announcement') DEFAULT 'info',
    icon VARCHAR(50) DEFAULT 'fa-bell', link VARCHAR(500) DEFAULT '',
    is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Flash message variables for our Toast Engine
$message = ''; $msg_type = '';

// ==========================================
// 🗑️ SECURE WIPE ALL LOGIC
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'wipe_all') {
    mysqli_query($conn, "TRUNCATE TABLE notifications");
    $_SESSION['admin_msg'] = "Entire notification history has been wiped clean.";
    $_SESSION['admin_msg_type'] = "secondary";
    header("Location: notifications.php");
    exit();
}

// PRG Pattern for Toast Messages
if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

// ==========================================
// ✉️ HANDLE SEND NOTIFICATION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notif'])) {
    $title   = trim(htmlspecialchars($_POST['title'] ?? ''));
    $msg     = trim(htmlspecialchars($_POST['message'] ?? ''));
    $type    = $_POST['type'] ?? 'info';
    $icon    = $_POST['icon'] ?? 'fa-bell';
    $link    = trim($_POST['link'] ?? '');
    $target  = (int)($_POST['target_user'] ?? 0); // 0 = broadcast

    if ($title && $msg) {
        if ($target === 0) {
            notify($conn, 0, $title, $msg, $type, $link, $icon); // broadcast
            $message = "Announcement broadcast to all registered users!"; 
            $msg_type = 'success';
        } else {
            notify($conn, $target, $title, $msg, $type, $link, $icon);
            $message = "Notification sent directly to user #$target!"; 
            $msg_type = 'success';
        }
    } else {
        $message = "Title and message fields are required."; 
        $msg_type = 'danger';
    }
}

// ==========================================
// 📊 FETCH DATA, ANALYTICS & PAGINATION
// ==========================================

// Fetch customers for dropdown
$customers = [];
$res = mysqli_query($conn, "SELECT id, name, email FROM users WHERE role_id = 2 ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($res)) $customers[] = $r;

// Fetch Global KPIs
$stats_res = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(is_read=0) as unread, COUNT(DISTINCT user_id) as unique_users, SUM(user_id=0) as broadcasts FROM notifications");
$stats = mysqli_fetch_assoc($stats_res);

// Pagination & Search Engine
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_sql = "WHERE 1=1 ";
if (!empty($search)) {
    $where_sql .= " AND (n.title LIKE '%$search%' OR n.message LIKE '%$search%' OR u.name LIKE '%$search%') ";
}

$total_records_query = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM notifications n LEFT JOIN users u ON n.user_id = u.id $where_sql"));
$total_pages = ceil(($total_records_query['total'] ?? 0) / $limit);

// Fetch Activity Log
$notifications = [];
$log_query = mysqli_query($conn, "
    SELECT n.*, 
           CASE n.user_id WHEN 0 THEN 'ALL (Broadcast)' ELSE CONCAT(u.name, ' (#', n.user_id, ')') END as recipient_name,
           CASE
               WHEN TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) < 60 THEN CONCAT(TIMESTAMPDIFF(MINUTE, n.created_at, NOW()), 'm ago')
               WHEN TIMESTAMPDIFF(HOUR,   n.created_at, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR,   n.created_at, NOW()), 'h ago')
               ELSE DATE_FORMAT(n.created_at, '%d %b %Y, %h:%i %p')
           END AS time_ago
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    $where_sql
    ORDER BY n.created_at DESC 
    LIMIT $limit OFFSET $offset
");
while ($r = mysqli_fetch_assoc($log_query)) $notifications[] = $r;

$page_title = "Notification Hub | Admin";
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
    body { background: #f4f7f6; }

    /* Hero Section */
    .admin-hero {
        background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal-primary) 100%);
        border-radius: 24px; padding: 3rem; color: white;
        box-shadow: 0 20px 40px rgba(26, 38, 36, 0.2); position: relative; overflow: hidden;
    }
    .admin-hero::after {
        content: '\f0f3'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: -20px; bottom: -50px; font-size: 15rem;
        color: rgba(255, 255, 255, 0.05); transform: rotate(-15deg); pointer-events: none;
    }

    /* Cards & Form Fields */
    .compose-card {
        background: white; border-radius: 24px; padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02);
    }
    .field-teal {
        background: #f8f9fa; border: 2px solid transparent; border-radius: 14px;
        padding: 14px 20px; font-weight: 600; color: var(--teal-dark); transition: all 0.3s; width: 100%;
    }
    .field-teal:focus { border-color: var(--teal-primary); box-shadow: 0 0 0 .25rem rgba(77, 168, 156, 0.15); background: white; outline: none; }

    /* KPI Stats */
    .stat-kpi {
        background: white; border-radius: 20px; padding: 25px; text-align: center;
        box-shadow: 0 10px 25px rgba(0,0,0,0.03); transition: transform 0.3s ease; border: 1px solid rgba(0,0,0,0.02);
    }
    .stat-kpi:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(77, 168, 156, 0.1); }

    /* Icon Picker */
    .icon-picker { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
    .icon-opt {
        width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center;
        justify-content: center; cursor: pointer; border: 2px solid transparent; font-size: 1.2rem;
        background: #f8f9fa; transition: all 0.2s; color: #6c757d;
    }
    .icon-opt:hover, .icon-opt.selected { border-color: var(--teal-primary); background: rgba(77, 168, 156, 0.1); color: var(--teal-primary); transform: scale(1.1); }

    /* Log Rows */
    .notif-row {
        display: flex; align-items: flex-start; gap: 16px; padding: 20px; border-radius: 20px;
        background: white; margin-bottom: 12px; border: 1px solid rgba(0,0,0,0.03); border-left: 5px solid transparent;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: all 0.3s;
    }
    .notif-row:hover { transform: translateX(5px); box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
    .notif-row.type-success  { border-left-color: #198754; }
    .notif-row.type-info     { border-left-color: #0dcaf0; }
    .notif-row.type-warning  { border-left-color: #ffc107; }
    .notif-row.type-danger   { border-left-color: #dc3545; }
    .notif-row.type-announcement { border-left-color: #6f42c1; }

    /* Type Select Pills */
    .type-pill-label { cursor: pointer; transition: all 0.2s; border: 2px solid transparent; }
    .type-pill-label:hover { transform: translateY(-2px); }

    /* Custom Button */
    .btn-teal { background: var(--teal-primary); color: white; border: none; transition: all 0.3s; }
    .btn-teal:hover { background: var(--teal-dark); color: white; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(77, 168, 156, 0.3); }

    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--teal-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--teal-primary); color: white; box-shadow: 0 5px 15px rgba(77, 168, 156, 0.4); }

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
</style>

<div id="admin-toast-container"></div>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content">

        <div class="admin-hero mb-5" data-aos="zoom-in">
            <div style="position:relative; z-index:2;">
                <span class="badge bg-white rounded-pill px-4 py-2 mb-3 fw-bold shadow-sm" style="color: var(--teal-dark);">
                    <i class="fas fa-satellite-dish me-2 text-primary"></i>Live Communication Hub
                </span>
                <h1 class="display-5 fw-bold mb-2">Notification Control Centre</h1>
                <p class="opacity-75 mb-0 fs-5">Send targeted alerts, monitor system activity, and manage the notification pipeline.</p>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <?php
            $kpis = [
                ['v'=>$stats['total']??0,        'l'=>'Total Dispatched', 'i'=>'fa-paper-plane',  'c'=>'var(--teal-primary)', 'border'=>'border-primary'],
                ['v'=>$stats['unread']??0,       'l'=>'Pending Read',     'i'=>'fa-envelope',     'c'=>'#ffc107', 'border'=>'border-warning'],
                ['v'=>$stats['unique_users']??0, 'l'=>'Users Reached',    'i'=>'fa-users',        'c'=>'#6f42c1', 'border'=>'border-info'],
                ['v'=>$stats['broadcasts']??0,   'l'=>'Mass Broadcasts',  'i'=>'fa-bullhorn',     'c'=>'#dc3545', 'border'=>'border-danger'],
            ];
            $delay = 0;
            foreach ($kpis as $k): ?>
                <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                    <div class="stat-kpi border-bottom border-4 <?php echo $k['border']; ?>">
                        <i class="fas <?php echo $k['i']; ?> fa-2x mb-3" style="color:<?php echo $k['c']; ?>;"></i>
                        <h2 class="fw-black mb-1 text-dark"><?php echo number_format($k['v']); ?></h2>
                        <small class="text-muted fw-bold text-uppercase tracking-wide" style="font-size: .75rem;"><?php echo $k['l']; ?></small>
                    </div>
                </div>
            <?php $delay += 100; endforeach; ?>
        </div>

        <div class="row g-5">

            <div class="col-xl-5" data-aos="fade-right">
                <div class="compose-card mb-4">
                    <h4 class="fw-bold mb-1 text-dark"><i class="fas fa-magic me-2" style="color:var(--teal-primary);"></i>Compose Alert</h4>
                    <p class="text-muted small mb-4 fw-bold border-bottom pb-3">Send to a specific customer or broadcast to all.</p>

                    <form method="POST" id="composeForm">

                        <div class="mb-4">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Target Audience</label>
                            <select name="target_user" class="field-teal shadow-sm" onchange="previewTarget(this)">
                                <option value="0">📢 ALL Customers (Mass Broadcast)</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?> — <?php echo htmlspecialchars($c['email']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-2 p-3 rounded-3 bg-light border fw-bold small text-dark d-flex align-items-center" id="targetPreview">
                                <i class="fas fa-broadcast-tower me-2 text-danger fs-5"></i>
                                Alert will be broadcast to all network users.
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Notification Category</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php
                                $types = [
                                    'announcement'=>['color'=>'#6f42c1','label'=>'Announcement'],
                                    'info'        =>['color'=>'#0dcaf0','label'=>'Information'],
                                    'success'     =>['color'=>'#198754','label'=>'Success'],
                                    'warning'     =>['color'=>'#ffc107','label'=>'Warning'],
                                    'danger'      =>['color'=>'#dc3545','label'=>'Critical Alert'],
                                ];
                                foreach ($types as $k => $t): ?>
                                    <label class="type-pill-label d-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-light fw-bold small shadow-sm" style="font-size: .85rem;">
                                        <input type="radio" name="type" value="<?php echo $k; ?>" class="d-none" <?php echo $k==='announcement'?'checked':''; ?>>
                                        <span class="rounded-circle" style="width:12px;height:12px;background:<?php echo $t['color']; ?>;display:inline-block;"></span>
                                        <?php echo $t['label']; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Visual Icon</label>
                            <input type="hidden" name="icon" id="iconInput" value="fa-bullhorn">
                            <div class="icon-picker">
                                <?php
                                $icons = ['fa-bullhorn','fa-car-side','fa-check-circle','fa-exclamation-triangle',
                                          'fa-gift','fa-tag','fa-star','fa-shield-alt','fa-times-circle',
                                          'fa-file-invoice-dollar','fa-calendar-check','fa-info-circle','fa-heart'];
                                foreach ($icons as $ic): ?>
                                    <div class="icon-opt shadow-sm <?php echo $ic==='fa-bullhorn'?'selected':''; ?>" onclick="selectIcon('<?php echo $ic; ?>')" title="<?php echo $ic; ?>">
                                        <i class="fas <?php echo $ic; ?>"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Headline Title</label>
                            <input type="text" name="title" class="field-teal shadow-sm" placeholder="e.g. 🎉 Flash Weekend Sale!" required maxlength="200">
                        </div>

                        <div class="mb-4">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Body Message</label>
                            <textarea name="message" class="field-teal shadow-sm" rows="3" placeholder="Enter the full notification details here..." required style="resize:none;"></textarea>
                        </div>

                        <div class="mb-5">
                            <label class="fw-bold text-muted small text-uppercase mb-2">Action Link <span class="fw-normal text-muted">(Optional)</span></label>
                            <input type="text" name="link" class="field-teal shadow-sm" placeholder="e.g. customer/search_cars.php">
                        </div>

                        <button type="submit" name="send_notif" class="btn btn-teal w-100 rounded-pill fw-bold py-3 fs-5 shadow-sm">
                            <i class="fas fa-paper-plane me-2"></i> Dispatch Alert
                        </button>
                    </form>
                </div>

                <div class="compose-card">
                    <h5 class="fw-bold mb-3 text-dark"><i class="fas fa-bolt me-2 text-warning"></i> Rapid Templates</h5>
                    <div class="d-flex flex-column gap-2">
                        <?php
                        $templates = [
                            ['emoji'=>'🎉','label'=>'Weekend Deal','title'=>'🎉 Weekend Special!','msg'=>'Get 20% off all bookings this weekend. Use code WEEKEND20 at checkout!','type'=>'announcement'],
                            ['emoji'=>'🔧','label'=>'Maintenance Alert','title'=>'🔧 Scheduled Maintenance','msg'=>'Some vehicles will be under maintenance this weekend. Plan your bookings early.','type'=>'warning'],
                            ['emoji'=>'⭐','label'=>'Reward Points','title'=>'⭐ Bonus Points Weekend','msg'=>'Earn 2× loyalty points on all bookings made this weekend. Don\'t miss out!','type'=>'success'],
                        ];
                        foreach ($templates as $tpl): ?>
                            <button class="btn btn-light text-start rounded-3 border fw-bold py-2 px-3 transition shadow-sm"
                                    onmouseover="this.style.borderColor='var(--teal-primary)';" onmouseout="this.style.borderColor='transparent';"
                                    onclick="fillTemplate('<?php echo addslashes($tpl['title']); ?>','<?php echo addslashes($tpl['msg']); ?>','<?php echo $tpl['type']; ?>')">
                                <span class="me-2 fs-5"><?php echo $tpl['emoji']; ?></span> <?php echo $tpl['label']; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-7" data-aos="fade-left">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0 text-dark"><i class="fas fa-history text-muted me-2"></i> Transmission Log</h4>
                    <a href="?action=wipe_all" class="btn btn-sm btn-outline-danger rounded-pill fw-bold px-4" onclick="return confirm('CRITICAL: Are you sure you want to permanently wipe all notification history?');">
                        <i class="fas fa-trash-alt me-1"></i> Wipe History
                    </a>
                </div>

                <form action="notifications.php" method="GET" class="mb-4">
                    <div class="input-group shadow-sm rounded-pill overflow-hidden bg-white border">
                        <span class="input-group-text bg-white border-0 text-muted ps-4"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control border-0 py-3 fw-bold text-dark shadow-none" placeholder="Search logs by keyword or user..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-teal px-4 fw-bold">Search</button>
                    </div>
                </form>

                <div id="adminNotifList">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-5 bg-white rounded-4 border shadow-sm mt-3">
                            <i class="far fa-bell-slash fa-4x mb-3 text-muted opacity-25 d-block"></i>
                            <h4 class="fw-bold text-dark">No Transmissions Found</h4>
                            <p class="text-muted mb-0 fw-bold">The log is empty. Dispatch an alert to populate this list.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): $type = htmlspecialchars($n['type']); ?>
                            <div class="notif-row type-<?php echo $type; ?>" id="an-<?php echo $n['id']; ?>">
                                <?php 
                                    $bg_colors = ['success'=>'rgba(25,135,84,.1)','info'=>'rgba(13,202,240,.1)','warning'=>'rgba(255,193,7,.1)','danger'=>'rgba(220,53,69,.1)','announcement'=>'rgba(111,66,193,.1)'];
                                    $txt_colors = ['success'=>'#198754','info'=>'#0a6070','warning'=>'#856404','danger'=>'#dc3545','announcement'=>'#6f42c1'];
                                ?>
                                <div style="min-width:50px; height:50px; border-radius:14px; display:flex; align-items:center; justify-content:center; background: <?php echo $bg_colors[$type]??'#eee'; ?>;">
                                    <i class="fas <?php echo htmlspecialchars($n['icon']); ?> fs-5" style="color: <?php echo $txt_colors[$type]??'#333'; ?>;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($n['title']); ?></h6>
                                            <span class="badge rounded-pill mt-1" style="background:<?php echo $bg_colors[$type]??'#eee'; ?>; color:<?php echo $txt_colors[$type]??'#333'; ?>; border: 1px solid <?php echo $txt_colors[$type]??'#333'; ?>;">
                                                <?php echo ucfirst($type); ?>
                                            </span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <small class="text-muted fw-bold"><i class="far fa-clock me-1"></i> <?php echo $n['time_ago']; ?></small>
                                            <button class="btn btn-sm btn-light border-0 rounded-circle text-danger shadow-sm" onclick="deleteAdminNotif(<?php echo $n['id']; ?>)" style="width:30px; height:30px;" title="Delete Record">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mb-2 text-muted fw-bold small"><?php echo htmlspecialchars($n['message']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                        <small class="text-muted fw-bold">
                                            <i class="fas fa-user-circle me-1 text-primary"></i> <?php echo htmlspecialchars($n['recipient_name']); ?>
                                        </small>
                                        <?php if (!$n['is_read']): ?>
                                            <span class="badge bg-danger rounded-pill" style="font-size:0.65rem;">Unread</span>
                                        <?php else: ?>
                                            <span class="badge bg-success rounded-pill opacity-50" style="font-size:0.65rem;"><i class="fas fa-check-double"></i> Read</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4" data-aos="fade-up">
                                <ul class="pagination pagination-custom justify-content-center mb-0">
                                    <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                                        <a class="page-link shadow-sm" href="<?php if($page <= 1){ echo '#'; } else { echo "?page=".($page - 1)."&search=$search"; } ?>"><i class="fas fa-chevron-left"></i></a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++ ): ?>
                                        <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                                            <a class="page-link shadow-sm" href="notifications.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>"> <?php echo $i; ?> </a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                                        <a class="page-link shadow-sm" href="<?php if($page >= $total_pages){ echo '#'; } else { echo "?page=".($page + 1)."&search=$search"; } ?>"><i class="fas fa-chevron-right"></i></a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
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

    // 3. COMPOSE LOGIC
    const BASE = '<?php echo $base_url; ?>';

    function previewTarget(sel) {
        const prev = document.getElementById('targetPreview');
        if (sel.value == 0) {
            prev.innerHTML = '<i class="fas fa-broadcast-tower me-2 text-danger fs-5"></i> Alert will be broadcast to all network users.';
            prev.className = "mt-2 p-3 rounded-3 bg-light border fw-bold small text-dark d-flex align-items-center";
        } else {
            const txt = sel.options[sel.selectedIndex].text;
            prev.innerHTML = '<i class="fas fa-user-lock me-2 fs-5" style="color:var(--teal-primary);"></i> Encrypted direct message to: <span class="ms-2 text-primary">' + txt + '</span>';
            prev.className = "mt-2 p-3 rounded-3 border fw-bold small text-dark d-flex align-items-center";
            prev.style.backgroundColor = "rgba(77, 168, 156, 0.05)";
        }
    }

    // Type Pill visual selector
    document.querySelectorAll('input[name="type"]').forEach(r => {
        r.addEventListener('change', () => {
            document.querySelectorAll('.type-pill-label').forEach(x => { x.style.borderColor = 'transparent'; x.classList.replace('shadow-sm', 'bg-light'); });
            if (r.checked) {
                const lbl = r.closest('label');
                lbl.style.borderColor = 'var(--teal-primary)';
                lbl.classList.remove('bg-light');
                lbl.classList.add('shadow-sm');
            }
        });
        if (r.checked) {
            const lbl = r.closest('label');
            lbl.style.borderColor = 'var(--teal-primary)';
            lbl.classList.remove('bg-light');
        }
    });

    function selectIcon(ic) {
        document.getElementById('iconInput').value = ic;
        document.querySelectorAll('.icon-opt').forEach(el => el.classList.remove('selected'));
        event.currentTarget.classList.add('selected');
    }

    function fillTemplate(title, msg, type) {
        document.querySelector('input[name="title"]').value = title;
        document.querySelector('textarea[name="message"]').value = msg;
        const radio = document.querySelector(`input[name="type"][value="${type}"]`);
        if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
    }

    // API Call for Single Deletion
    function deleteAdminNotif(id) {
        if(!confirm('Delete this notification log?')) return;
        
        fetch(BASE + 'api/notifications.php?action=delete', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'id=' + id
        }).then(r => r.json()).then(d => {
            if (d.success) {
                const el = document.getElementById('an-' + id);
                el.style.transform = 'scale(0.9)';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 300);
            } else {
                alert('Failed to delete notification.');
            }
        });
    }
</script>

<?php include '../includes/footer.php'; ?> 