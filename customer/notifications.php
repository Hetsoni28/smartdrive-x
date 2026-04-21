<?php
session_start();

// 🛡️ SECURITY: Customer Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';
$user_id  = (int)$_SESSION['user_id'];

// 🛡️ Auto-create table guard (Failsafe wrapped in Try-Catch)
try {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL, message TEXT NOT NULL,
        type ENUM('success','info','warning','danger','announcement') DEFAULT 'info',
        icon VARCHAR(50) DEFAULT 'fa-bell', link VARCHAR(500) DEFAULT '',
        is_read TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) { /* Silent fail for production schema constraints */ }

// ==========================================
// 📊 ENTERPRISE KPI FETCH (Prepared Statement)
// ==========================================
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(is_read=0) as unread,
        SUM(type='success') as success_count,
        SUM(type='danger') as danger_count
    FROM notifications 
    WHERE user_id = ? OR user_id = 0
");
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// ==========================================
// 🔍 SECURE FILTERING & PAGINATION ENGINE
// ==========================================
$allowed_filters = ['all', 'unread', 'success', 'info', 'warning', 'danger', 'announcement'];
$filter = isset($_GET['filter']) && in_array($_GET['filter'], $allowed_filters) ? $_GET['filter'] : 'all';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Dynamic Where Clause Builder (100% SQLi Proof)
$where_sql = "WHERE (n.user_id = ? OR n.user_id = 0)";
$types = "i";
$params = [$user_id];

if ($filter === 'unread') {
    $where_sql .= " AND n.is_read = 0";
} elseif ($filter !== 'all') {
    $where_sql .= " AND n.type = ?";
    $types .= "s";
    $params[] = $filter;
}

// 1. Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM notifications n $where_sql";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();
$total_pages = ceil($total_records / $limit);

// 2. Fetch Paginated Records
$data_query = "
    SELECT n.*,
        CASE
            WHEN TIMESTAMPDIFF(SECOND, n.created_at, NOW()) < 60    THEN CONCAT(TIMESTAMPDIFF(SECOND, n.created_at, NOW()), ' sec ago')
            WHEN TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) < 60    THEN CONCAT(TIMESTAMPDIFF(MINUTE, n.created_at, NOW()), ' min ago')
            WHEN TIMESTAMPDIFF(HOUR,   n.created_at, NOW()) < 24    THEN CONCAT(TIMESTAMPDIFF(HOUR,   n.created_at, NOW()), ' hr ago')
            WHEN TIMESTAMPDIFF(DAY,    n.created_at, NOW()) < 7     THEN CONCAT(TIMESTAMPDIFF(DAY,    n.created_at, NOW()), ' days ago')
            ELSE DATE_FORMAT(n.created_at, '%d %b %Y, %h:%i %p')
        END AS time_ago
    FROM notifications n
    $where_sql
    ORDER BY n.created_at DESC 
    LIMIT ? OFFSET ?
";

// Append pagination params
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$data_stmt = $conn->prepare($data_query);
$data_stmt->bind_param($types, ...$params);
$data_stmt->execute();
$res = $data_stmt->get_result();

$notifications = [];
while ($r = $res->fetch_assoc()) {
    $notifications[] = $r;
}
$data_stmt->close();

$page_title = "Notification Centre | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🌿 ENTERPRISE SAGE GREEN THEME */
    :root {
        --sage-dark: #4a5c43;
        --sage-mid: #889c7c;
        --sage-pale: #e0eadb;
        --sage-deep: #2b3327;
        --sage-bg: #f4f5f3;
        --shadow-soft: 0 4px 20px rgba(0,0,0,0.03);
        --shadow-hover: 0 10px 30px rgba(74, 92, 67, 0.1);
    }
    body { background: var(--sage-bg); }

    /* 🦴 Skeleton Loading Engine */
    .skeleton-box { position: relative; overflow: hidden; background-color: #e2e8f0; color: transparent !important; border-color: transparent !important; user-select: none; pointer-events: none; border-radius: 12px; }
    .skeleton-box * { visibility: hidden; }
    .skeleton-box::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.6) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 100% { transform: translateX(100%); } }

    /* Hero Section */
    .notif-hero {
        background: linear-gradient(135deg, var(--sage-deep) 0%, #2d4a2a 100%);
        border-radius: 24px; padding: 3rem 2.5rem; color: white;
        box-shadow: 0 20px 40px rgba(43,51,39,.25); position: relative; overflow: hidden;
    }
    .notif-hero::after {
        content: '\f0f3'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: -20px; bottom: -50px; font-size: 16rem;
        color: rgba(255,255,255,.03); pointer-events: none; transform: rotate(-10deg);
    }

    /* KPI Bubbles */
    .stat-bubble {
        background: white; border-radius: 20px; padding: 25px; text-align: center;
        box-shadow: var(--shadow-soft); transition: transform 0.3s ease;
        border: 1px solid rgba(0,0,0,0.02); border-bottom: 4px solid var(--sage-pale);
    }
    .stat-bubble:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-bottom-color: var(--sage-dark); }

    /* Filters */
    .filter-pill {
        padding: 10px 22px; border-radius: 50px; border: 2px solid rgba(136,156,124,.25);
        background: white; font-weight: 700; cursor: pointer; font-size: 0.85rem;
        transition: all 0.3s; text-decoration: none; color: #6c757d; box-shadow: var(--shadow-soft);
    }
    .filter-pill:hover, .filter-pill.active { background: var(--sage-dark); color: white; border-color: var(--sage-dark); box-shadow: var(--shadow-hover); transform: translateY(-2px); }

    /* Notification Cards */
    .notif-card {
        background: white; border-radius: 20px; padding: 20px 25px;
        border-left: 5px solid transparent; margin-bottom: 12px;
        box-shadow: var(--shadow-soft); transition: all 0.3s cubic-bezier(.175,.885,.32,1.275);
        cursor: pointer; display: flex; align-items: flex-start; gap: 18px; position: relative;
    }
    .notif-card:hover { transform: translateX(8px); box-shadow: var(--shadow-hover); }
    
    .notif-card.unread { background: #f8fbf7; }
    .notif-card.unread::before {
        content: ''; position: absolute; left: -2px; top: 50%; transform: translateY(-50%);
        width: 10px; height: 10px; border-radius: 50%; background: var(--sage-dark); box-shadow: 0 0 10px rgba(74,92,67,0.5);
    }

    .notif-card.type-success  { border-left-color: #198754; }
    .notif-card.type-info     { border-left-color: #0dcaf0; }
    .notif-card.type-warning  { border-left-color: #ffc107; }
    .notif-card.type-danger   { border-left-color: #dc3545; }
    .notif-card.type-announcement { border-left-color: #6f42c1; }

    .notif-icon-wrap { width: 50px; height: 50px; border-radius: 14px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    
    .bg-success-soft { background: rgba(25,135,84,.1); color: #198754; }
    .bg-info-soft    { background: rgba(13,202,240,.1); color: #0a6070; }
    .bg-warning-soft { background: rgba(255,193,7,.1);  color: #856404; }
    .bg-danger-soft  { background: rgba(220,53,69,.1);  color: #dc3545; }
    .bg-announcement-soft { background: rgba(111,66,193,.1); color: #6f42c1; }

    .delete-btn { opacity: 0.4; transition: all 0.3s; }
    .notif-card:hover .delete-btn { opacity: 1; }
    .delete-btn:hover { color: #dc3545 !important; transform: scale(1.2); }

    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--sage-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--sage-dark); color: white; box-shadow: 0 5px 15px rgba(74, 92, 67, 0.4); }

    /* 🔔 Toast Engine */
    #cust-toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .cust-toast { background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 15px; min-width: 320px; transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); border: 1px solid rgba(0,0,0,0.05); }
    .cust-toast.show { transform: translateX(0); opacity: 1; }
    .cust-toast.success { border-left: 5px solid #198754; }
    .cust-toast.danger { border-left: 5px solid #dc3545; }

    .pulse-green { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #4caf50; animation: pulse 2s infinite; vertical-align: middle; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(76,175,80,.4); } 70% { box-shadow: 0 0 0 8px rgba(76,175,80,0); } 100% { box-shadow: 0 0 0 0 rgba(76,175,80,0); } }
</style>

<div id="cust-toast-container"></div>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="dashboard-content">

        <div class="notif-hero mb-5 load-anim skeleton-box" data-aos="fade-up">
            <div style="position:relative;z-index:2;" class="row align-items-center g-3">
                <div class="col-lg-8">
                    <span class="badge bg-white rounded-pill px-3 py-2 mb-3 fw-bold shadow-sm" style="color:var(--sage-dark);">
                        <span class="pulse-green me-2"></span>Live Synchronization
                    </span>
                    <h1 class="fw-black mb-2 display-5">Notification Centre</h1>
                    <p class="opacity-75 mb-0 fs-5">All your real-time alerts, booking updates, and system messages in one place.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <button class="btn btn-light fw-bold rounded-pill px-4 py-3 shadow-lg" style="color: var(--sage-dark);" onclick="markAllRead()">
                        <i class="fas fa-check-double me-2"></i>Mark All Read
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <?php
            $stat_items = [
                ['val'=>$stats['total']??0,         'label'=>'Total Logs',  'icon'=>'fa-bell',           'color'=>'var(--sage-dark)'],
                ['val'=>$stats['unread']??0,        'label'=>'Unread',      'icon'=>'fa-envelope',       'color'=>'#dc3545'],
                ['val'=>$stats['success_count']??0, 'label'=>'Successes',   'icon'=>'fa-check-circle',   'color'=>'#198754'],
                ['val'=>$stats['danger_count']??0,  'label'=>'Alerts',      'icon'=>'fa-exclamation',    'color'=>'#ffc107'],
            ];
            $delay = 0;
            foreach ($stat_items as $s): ?>
            <div class="col-xl-3 col-md-6 col-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                <div class="stat-bubble load-anim skeleton-box">
                    <i class="fas <?php echo $s['icon']; ?> fa-2x mb-3" style="color:<?php echo $s['color']; ?>;"></i>
                    <h2 class="fw-black mb-1"><?php echo $s['val']; ?></h2>
                    <small class="text-muted fw-bold text-uppercase tracking-wide" style="font-size:.75rem;"><?php echo $s['label']; ?></small>
                </div>
            </div>
            <?php $delay += 100; endforeach; ?>
        </div>

        <div class="d-flex gap-2 flex-wrap mb-4" data-aos="fade-up" data-aos-delay="400">
            <?php
            $filters = [
                'all'=>'All Logs','unread'=>'Unread Only','success'=>'Success',
                'info'=>'Information','warning'=>'Warnings','danger'=>'Critical Alerts','announcement'=>'Announcements'
            ];
            foreach ($filters as $k => $label):
                $active = $filter === $k ? 'active' : '';
            ?>
            <a href="?filter=<?php echo $k; ?>" class="filter-pill <?php echo $active; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
        </div>

        <div id="notifList" data-aos="fade-up" data-aos-delay="500">
        <?php if (empty($notifications)): ?>
            <div class="empty-state text-center py-5 bg-white rounded-4 border shadow-sm load-anim skeleton-box">
                <i class="far fa-bell-slash fa-4x mb-4 d-block text-muted opacity-25"></i>
                <h4 class="fw-bold text-dark">Log is empty</h4>
                <p class="text-muted small fw-bold">When you receive booking updates or system broadcasts, they will securely appear here.</p>
            </div>
        <?php else:
            foreach ($notifications as $n):
                $type    = htmlspecialchars($n['type']);
                $unread  = !$n['is_read'] ? 'unread' : '';
                $icon    = htmlspecialchars($n['icon']);
                $link    = htmlspecialchars($n['link']);
        ?>
            <div class="notif-card type-<?php echo $type; ?> <?php echo $unread; ?> load-anim skeleton-box"
                 id="notif-<?php echo $n['id']; ?>"
                 onclick="readAndGo(<?php echo $n['id']; ?>, '<?php echo $link ? $base_url . $link : 'javascript:void(0)'; ?>')">
                
                <div class="notif-icon-wrap bg-<?php echo $type; ?>-soft border border-light">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($n['title']); ?></h6>
                        <div class="d-flex align-items-center gap-3">
                            <small class="text-muted fw-bold"><i class="far fa-clock me-1 opacity-50"></i><?php echo $n['time_ago']; ?></small>
                            <?php if (!$n['is_read']): ?>
                                <span class="badge rounded-pill shadow-sm" style="background:var(--sage-dark);color:white;font-size:.65rem;">New</span>
                            <?php endif; ?>
                            <button class="btn btn-sm p-0 border-0 text-muted delete-btn"
                                    onclick="event.stopPropagation(); deleteNotif(<?php echo $n['id']; ?>)"
                                    title="Delete Notification" aria-label="Delete Notification">
                                <i class="fas fa-times fs-6"></i>
                            </button>
                        </div>
                    </div>
                    <p class="text-muted mb-0 small fw-bold mt-2 lh-lg"><?php echo htmlspecialchars($n['message']); ?></p>
                    <?php if ($link): ?>
                        <small class="fw-bold mt-2 d-inline-block px-3 py-1 rounded-pill bg-light border" style="color:var(--sage-dark);">
                            <i class="fas fa-external-link-alt me-1"></i> View attached details
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>

        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation" data-aos="fade-up" class="mt-5">
                <ul class="pagination pagination-custom justify-content-center">
                    <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link shadow-sm border" href="<?php if($page > 1) echo "?page=".($page - 1)."&filter=$filter"; else echo "#"; ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++ ): ?>
                        <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                            <a class="page-link shadow-sm border" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link shadow-sm border" href="<?php if($page < $total_pages) echo "?page=".($page + 1)."&filter=$filter"; else echo "#"; ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>

<div id="cust-toast-container"></div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
    const BASE = '<?php echo $base_url; ?>';

    // 1. SKELETON LOADER REMOVAL ENGINE
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            document.querySelectorAll('.skeleton-box').forEach(el => {
                el.classList.remove('skeleton-box');
            });
        }, 250); 
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
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 4000);
    }

    // 4. SECURE API INTERACTION LOGIC
    function readAndGo(id, url) {
        fetch(BASE + 'api/notifications.php', {
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_read&id=' + id
        })
        .catch(err => console.error(err))
        .finally(() => {
            const card = document.getElementById('notif-' + id);
            if (card) { 
                card.classList.remove('unread'); 
                const badge = card.querySelector('.badge');
                if (badge && badge.textContent === 'New') badge.remove();
            }
            if (url && url !== 'javascript:void(0)') window.location.href = url;
        });
    }

    function markAllRead() {
        fetch(BASE + 'api/notifications.php', { 
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_all'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notif-card.unread').forEach(c => c.classList.remove('unread'));
                document.querySelectorAll('.badge').forEach(b => { if (b.textContent.trim() === 'New') b.remove(); });
                showToast('All notifications marked as read!', 'success');
            }
        })
        .catch(() => showToast('Error communicating with server.', 'danger'));
    }

    function deleteNotif(id) {
        if (!confirm('Permanently delete this notification?')) return;
        fetch(BASE + 'api/notifications.php', {
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete&id=' + id
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const el = document.getElementById('notif-' + id);
                el.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                el.style.opacity = '0';
                el.style.transform = 'scale(0.9) translateX(40px)';
                setTimeout(() => el.remove(), 400);
                showToast('Notification deleted.', 'success');
            } else {
                showToast('Failed to delete.', 'danger');
            }
        })
        .catch(() => showToast('Network Error.', 'danger'));
    }
</script>

<?php include '../includes/footer.php'; ?>