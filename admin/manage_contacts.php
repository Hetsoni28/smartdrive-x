<?php
session_start();

// 🛡️ RBAC: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

// ==========================================
// 📩 ACTION HANDLERS (POST)
// ==========================================
$action_msg = '';
$action_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $msg_id = (int)($_POST['msg_id'] ?? 0);

    if ($action === 'update_status' && $msg_id > 0) {
        $new_status = in_array($_POST['new_status'] ?? '', ['new', 'in_progress', 'resolved', 'closed']) ? $_POST['new_status'] : 'new';
        $admin_notes = htmlspecialchars(trim($_POST['admin_notes'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        $resolved_clause = ($new_status === 'resolved') ? ", resolved_at = NOW()" : "";
        
        $stmt = $conn->prepare("UPDATE contact_messages SET status = ?, admin_notes = ? {$resolved_clause} WHERE id = ?");
        $stmt->bind_param("ssi", $new_status, $admin_notes, $msg_id);
        
        if ($stmt->execute()) {
            $action_msg = "Ticket #CT-{$msg_id} status updated to " . ucfirst($new_status);
            $action_type = 'success';
            
            // Notify user if resolved
            if ($new_status === 'resolved') {
                $user_query = $conn->prepare("SELECT user_id FROM contact_messages WHERE id = ?");
                $user_query->bind_param("i", $msg_id);
                $user_query->execute();
                $cm_user = $user_query->get_result()->fetch_assoc();
                $user_query->close();
                
                if ($cm_user && $cm_user['user_id']) {
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, icon, link) VALUES (?, 'Issue Resolved', 'Your support ticket #CT-{$msg_id} has been resolved.', 'success', 'fa-check-circle', 'pages/contact.php')");
                    $notif_stmt->bind_param("i", $cm_user['user_id']);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            }
        } else {
            $action_msg = 'Failed to update status.';
            $action_type = 'danger';
        }
        $stmt->close();
    }

    if ($action === 'send_reply' && $msg_id > 0) {
        $reply = htmlspecialchars(trim($_POST['admin_reply'] ?? ''), ENT_QUOTES, 'UTF-8');
        if (strlen($reply) >= 5) {
            $admin_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("UPDATE contact_messages SET admin_reply = ?, replied_by = ?, replied_at = NOW(), status = 'resolved', resolved_at = NOW() WHERE id = ?");
            $stmt->bind_param("sii", $reply, $admin_id, $msg_id);
            
            if ($stmt->execute()) {
                $action_msg = "Reply sent to ticket #CT-{$msg_id}";
                $action_type = 'success';
                
                // In-app notification
                $user_query = $conn->prepare("SELECT user_id, email, name FROM contact_messages WHERE id = ?");
                $user_query->bind_param("i", $msg_id);
                $user_query->execute();
                $cm_data = $user_query->get_result()->fetch_assoc();
                $user_query->close();
                
                if ($cm_data && $cm_data['user_id']) {
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, icon, link) VALUES (?, 'Reply from Support', ?, 'success', 'fa-reply', 'pages/contact.php')");
                    $reply_preview = 'Admin replied: ' . substr(strip_tags($reply), 0, 100) . '...';
                    $notif_stmt->bind_param("is", $cm_data['user_id'], $reply_preview);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            } else {
                $action_msg = 'Failed to send reply.';
                $action_type = 'danger';
            }
            $stmt->close();
        } else {
            $action_msg = 'Reply must be at least 5 characters.';
            $action_type = 'warning';
        }
    }

    if ($action === 'delete' && $msg_id > 0) {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->bind_param("i", $msg_id);
        $stmt->execute();
        $action_msg = "Ticket #CT-{$msg_id} deleted permanently.";
        $action_type = 'warning';
        $stmt->close();
    }
}

// ==========================================
// 📊 DASHBOARD KPIs 
// ==========================================
$kpi_query = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(status = 'new') as cnt_new,
        SUM(status = 'in_progress') as cnt_progress,
        SUM(status = 'resolved') as cnt_resolved,
        SUM(status = 'closed') as cnt_closed,
        SUM(priority = 'critical') as cnt_critical,
        SUM(priority = 'high') as cnt_high
    FROM contact_messages
");
$kpi = mysqli_fetch_assoc($kpi_query);

// ==========================================
// 🔍 FILTERS & SEARCH
// ==========================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter   = isset($_GET['type'])   ? $_GET['type']   : 'all';
$search        = isset($_GET['search']) ? trim($_GET['search']) : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';

// Build query
$where = "WHERE 1=1";
$params = [];
$types_str = "";

if ($status_filter !== 'all') {
    $where .= " AND cm.status = ?";
    $params[] = $status_filter;
    $types_str .= "s";
}
if ($type_filter !== 'all') {
    $where .= " AND cm.message_type = ?";
    $params[] = $type_filter;
    $types_str .= "s";
}
if ($priority_filter !== 'all') {
    $where .= " AND cm.priority = ?";
    $params[] = $priority_filter;
    $types_str .= "s";
}
if (!empty($search)) {
    $where .= " AND (cm.name LIKE ? OR cm.email LIKE ? OR cm.subject LIKE ? OR CAST(cm.id AS CHAR) LIKE ?)";
    $search_term = "%{$search}%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $types_str .= "ssss";
}

// Pagination
$count_sql = "SELECT COUNT(*) as total FROM contact_messages cm {$where}";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types_str, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$per_page = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_pages = max(1, ceil($total_records / $per_page));
$offset = ($current_page - 1) * $per_page;

// Fetch messages
$sql = "SELECT cm.*, u.name as user_display_name FROM contact_messages cm LEFT JOIN users u ON cm.user_id = u.id {$where} ORDER BY cm.created_at DESC LIMIT {$per_page} OFFSET {$offset}";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types_str, ...$params);
}
$stmt->execute();
$messages = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<style>
    .query-card {
        background: white; border-radius: 16px; border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 16px;
        transition: all 0.3s ease; overflow: hidden;
    }
    .query-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .query-card.priority-critical { border-left: 4px solid #dc3545; }
    .query-card.priority-high { border-left: 4px solid #fd7e14; }
    .query-card.priority-medium { border-left: 4px solid #ffc107; }
    .query-card.priority-low { border-left: 4px solid #198754; }
    .kpi-card { border-radius: 16px; padding: 20px; text-align: center; border: 1px solid rgba(0,0,0,0.05); background: white; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
    .kpi-card h3 { font-weight: 900; font-size: 2rem; margin-bottom: 2px; }
    .kpi-card small { font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.65rem; }
    .filter-pill { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 50px; font-weight: 700; font-size: 0.8rem; border: 2px solid transparent; text-decoration: none; transition: all 0.3s; }
    .filter-pill:hover { transform: translateY(-2px); }
    .filter-pill.active { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .status-badge { padding: 5px 12px; border-radius: 50px; font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .type-badge { padding: 4px 10px; border-radius: 8px; font-weight: 700; font-size: 0.7rem; }
    .reply-box { background: rgba(74,92,67,0.04); border-radius: 12px; padding: 16px; border: 1px solid rgba(74,92,67,0.1); }
    .reply-box blockquote { background: rgba(255,255,255,0.8); border-radius: 8px; padding: 12px; border-left: 3px solid #4a5c43; font-size: 0.85rem; }
    .detail-modal .modal-content { border-radius: 20px; border: none; box-shadow: 0 25px 60px rgba(0,0,0,0.15); }
    .mini-map { border-radius: 12px; height: 200px; border: 2px solid rgba(0,0,0,0.05); }
</style>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content p-3 p-lg-4">
        <!-- Breadcrumb -->
    <nav class="mb-3">
        <ol class="breadcrumb small fw-bold">
            <li class="breadcrumb-item"><a href="dashboard.php" style="color: var(--theme-primary); text-decoration: none;">Admin Panel</a></li>
            <li class="breadcrumb-item active">Contact Messages</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-black text-dark mb-1" style="font-family: var(--font-heading);">Support Inbox</h2>
            <p class="text-muted fw-bold small mb-0"><?php echo $total_records; ?> total messages</p>
        </div>
    </div>

    <?php if ($action_msg): ?>
    <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show rounded-4 fw-bold shadow-sm" role="alert">
        <i class="fas fa-<?php echo $action_type === 'success' ? 'check-circle' : ($action_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?> me-2"></i>
        <?php echo $action_msg; ?>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- KPI CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3 col-xl">
            <div class="kpi-card">
                <h3 class="text-dark"><?php echo $kpi['total']; ?></h3>
                <small class="text-muted">Total Messages</small>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl">
            <div class="kpi-card" style="border-bottom: 3px solid #0dcaf0;">
                <h3 style="color: #0dcaf0;"><?php echo $kpi['cnt_new'] ?? 0; ?></h3>
                <small class="text-muted">New Tickets</small>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl">
            <div class="kpi-card" style="border-bottom: 3px solid #ffc107;">
                <h3 style="color: #ffc107;"><?php echo $kpi['cnt_progress'] ?? 0; ?></h3>
                <small class="text-muted">In Progress</small>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl">
            <div class="kpi-card" style="border-bottom: 3px solid #198754;">
                <h3 style="color: #198754;"><?php echo $kpi['cnt_resolved'] ?? 0; ?></h3>
                <small class="text-muted">Resolved</small>
            </div>
        </div>
        <div class="col-6 col-lg-3 col-xl">
            <div class="kpi-card" style="border-bottom: 3px solid #dc3545;">
                <h3 style="color: #dc3545;"><?php echo ($kpi['cnt_critical'] ?? 0) + ($kpi['cnt_high'] ?? 0); ?></h3>
                <small class="text-muted">High Priority</small>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <form class="d-flex gap-2 flex-wrap flex-grow-1" method="GET">
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control border-start-0 rounded-end-pill fw-bold" placeholder="Search name, email, ID..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status" class="form-select rounded-pill fw-bold" style="max-width: 160px;">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>🔵 New</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>🟡 In Progress</option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>🟢 Resolved</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>⚫ Closed</option>
            </select>
            <select name="type" class="form-select rounded-pill fw-bold" style="max-width: 160px;">
                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="general" <?php echo $type_filter === 'general' ? 'selected' : ''; ?>>💬 General</option>
                <option value="issue" <?php echo $type_filter === 'issue' ? 'selected' : ''; ?>>🐛 Issue</option>
                <option value="feedback" <?php echo $type_filter === 'feedback' ? 'selected' : ''; ?>>⭐ Feedback</option>
                <option value="billing" <?php echo $type_filter === 'billing' ? 'selected' : ''; ?>>💳 Billing</option>
                <option value="booking" <?php echo $type_filter === 'booking' ? 'selected' : ''; ?>>🚗 Booking</option>
            </select>
            <select name="priority" class="form-select rounded-pill fw-bold" style="max-width: 150px;">
                <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priority</option>
                <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>🔴 Critical</option>
                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>🟠 High</option>
                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>🟢 Low</option>
            </select>
            <button type="submit" class="btn rounded-pill fw-bold px-4 text-white" style="background: var(--theme-primary);">
                <i class="fas fa-filter me-1"></i>Filter
            </button>
            <?php if ($status_filter !== 'all' || $type_filter !== 'all' || !empty($search) || $priority_filter !== 'all'): ?>
                <a href="manage_contacts.php" class="btn btn-outline-secondary rounded-pill fw-bold px-3"><i class="fas fa-times me-1"></i>Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- MESSAGE LIST -->
    <?php if ($messages->num_rows === 0): ?>
        <div class="text-center py-5">
            <i class="far fa-inbox fa-4x text-muted opacity-25 mb-3 d-block"></i>
            <h5 class="fw-black text-muted">No Messages Found</h5>
            <p class="text-muted fw-bold small">Try adjusting your filters or check back later.</p>
        </div>
    <?php else: ?>
        <?php while ($msg = $messages->fetch_assoc()):
            $status_colors = ['new' => ['#0dcaf0', 'bg-info'], 'in_progress' => ['#ffc107', 'bg-warning'], 'resolved' => ['#198754', 'bg-success'], 'closed' => ['#6c757d', 'bg-secondary']];
            $type_colors = ['general' => '#4a5c43', 'issue' => '#dc3545', 'feedback' => '#ffc107', 'billing' => '#0dcaf0', 'booking' => '#6f42c1'];
            $sc = $status_colors[$msg['status']] ?? ['#6c757d', 'bg-secondary'];
            $tc = $type_colors[$msg['message_type']] ?? '#4a5c43';
        ?>
        <div class="query-card priority-<?php echo $msg['priority']; ?>">
            <div class="p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div class="d-flex align-items-center gap-3">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($msg['name']); ?>&background=<?php echo str_replace('#','',$tc); ?>&color=fff&bold=true&size=42" 
                             class="rounded-circle shadow-sm" width="42" height="42" alt="">
                        <div>
                            <h6 class="fw-black text-dark mb-0"><?php echo htmlspecialchars($msg['name']); ?></h6>
                            <small class="text-muted fw-bold"><?php echo htmlspecialchars($msg['email']); ?><?php echo $msg['phone'] ? ' · ' . htmlspecialchars($msg['phone']) : ''; ?></small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="type-badge" style="background: <?php echo $tc; ?>20; color: <?php echo $tc; ?>;"><?php echo ucfirst($msg['message_type']); ?></span>
                        <span class="status-badge" style="background: <?php echo $sc[0]; ?>20; color: <?php echo $sc[0]; ?>;">
                            <?php echo str_replace('_', ' ', ucfirst($msg['status'])); ?>
                        </span>
                        <small class="text-muted fw-bold">#CT-<?php echo $msg['id']; ?></small>
                    </div>
                </div>

                <h6 class="fw-black text-dark mb-1"><?php echo htmlspecialchars($msg['subject']); ?></h6>
                <p class="text-muted fw-bold small mb-2" style="max-height: 60px; overflow: hidden;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <small class="text-muted fw-bold"><i class="far fa-clock me-1"></i><?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?></small>
                        <?php if ($msg['booking_ref']): ?>
                            <small class="text-muted fw-bold"><i class="fas fa-bookmark me-1"></i><?php echo htmlspecialchars($msg['booking_ref']); ?></small>
                        <?php endif; ?>
                        <?php if ($msg['latitude'] && $msg['longitude']): ?>
                            <small class="text-success fw-bold"><i class="fas fa-map-pin me-1"></i>Location</small>
                        <?php endif; ?>
                        <?php if ($msg['admin_reply']): ?>
                            <small class="text-primary fw-bold"><i class="fas fa-reply me-1"></i>Replied</small>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill fw-bold px-3" data-bs-toggle="modal" data-bs-target="#detail<?php echo $msg['id']; ?>">
                            <i class="fas fa-eye me-1"></i>View
                        </button>
                        <?php if ($msg['status'] !== 'resolved' && $msg['status'] !== 'closed'): ?>
                        <button class="btn btn-sm rounded-pill fw-bold px-3 text-white" style="background: var(--theme-primary);" data-bs-toggle="modal" data-bs-target="#reply<?php echo $msg['id']; ?>">
                            <i class="fas fa-reply me-1"></i>Reply
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETAIL MODAL -->
        <div class="modal fade detail-modal" id="detail<?php echo $msg['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0 pb-0 px-4 pt-4">
                        <div>
                            <h5 class="fw-black text-dark mb-0">#CT-<?php echo $msg['id']; ?> — <?php echo htmlspecialchars($msg['subject']); ?></h5>
                            <small class="text-muted fw-bold"><?php echo date('d M Y, h:i A', strtotime($msg['created_at'])); ?></small>
                        </div>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4 pb-4">
                        <div class="d-flex gap-3 align-items-center mb-3 p-3 rounded-4" style="background: rgba(0,0,0,0.02);">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($msg['name']); ?>&background=4a5c43&color=fff&bold=true" class="rounded-circle" width="48" height="48" alt="">
                            <div>
                                <h6 class="fw-black mb-0 text-dark"><?php echo htmlspecialchars($msg['name']); ?></h6>
                                <small class="text-muted fw-bold"><?php echo htmlspecialchars($msg['email']); ?><?php echo $msg['phone'] ? ' · ' . htmlspecialchars($msg['phone']) : ''; ?></small>
                            </div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <span class="type-badge" style="background: <?php echo $tc; ?>20; color: <?php echo $tc; ?>;"><i class="fas fa-tag me-1"></i><?php echo ucfirst($msg['message_type']); ?></span>
                            <span class="status-badge" style="background: <?php echo $sc[0]; ?>20; color: <?php echo $sc[0]; ?>;"><?php echo str_replace('_', ' ', ucfirst($msg['status'])); ?></span>
                            <span class="badge bg-<?php echo $msg['priority'] === 'critical' ? 'danger' : ($msg['priority'] === 'high' ? 'warning text-dark' : ($msg['priority'] === 'medium' ? 'info' : 'success')); ?> rounded-pill"><?php echo ucfirst($msg['priority']); ?> Priority</span>
                            <?php if ($msg['booking_ref']): ?>
                                <span class="badge bg-light text-dark border rounded-pill"><i class="fas fa-bookmark me-1"></i><?php echo htmlspecialchars($msg['booking_ref']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="p-3 rounded-4 mb-3" style="background: #f8f9f7; border: 1px solid rgba(0,0,0,0.05);">
                            <p class="mb-0 fw-bold small text-dark" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                        </div>

                        <?php if ($msg['admin_reply']): ?>
                        <div class="reply-box mb-3">
                            <small class="fw-bold text-muted d-block mb-2"><i class="fas fa-reply me-1"></i>Admin Reply — <?php echo $msg['replied_at'] ? date('d M Y, h:i A', strtotime($msg['replied_at'])) : 'N/A'; ?></small>
                            <blockquote class="mb-0"><?php echo nl2br(htmlspecialchars($msg['admin_reply'])); ?></blockquote>
                        </div>
                        <?php endif; ?>

                        <?php if ($msg['latitude'] && $msg['longitude']): ?>
                        <div class="mb-3">
                            <small class="fw-bold text-muted d-block mb-2"><i class="fas fa-map-pin me-1"></i>User Location</small>
                            <div class="mini-map" id="map<?php echo $msg['id']; ?>"></div>
                            <small class="text-muted fw-bold mt-1 d-block"><?php echo $msg['location_address'] ? htmlspecialchars($msg['location_address']) : $msg['latitude'] . ', ' . $msg['longitude']; ?></small>
                        </div>
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                        <script>
                        document.getElementById('detail<?php echo $msg['id']; ?>').addEventListener('shown.bs.modal', function() {
                            if (this._mapInit) return;
                            this._mapInit = true;
                            const m = L.map('map<?php echo $msg['id']; ?>', { scrollWheelZoom: false }).setView([<?php echo $msg['latitude']; ?>, <?php echo $msg['longitude']; ?>], 14);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(m);
                            L.marker([<?php echo $msg['latitude']; ?>, <?php echo $msg['longitude']; ?>]).addTo(m);
                            setTimeout(() => m.invalidateSize(), 200);
                        });
                        </script>
                        <?php endif; ?>

                        <!-- STATUS UPDATE -->
                        <div class="border-top pt-3 mt-3">
                            <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="msg_id" value="<?php echo $msg['id']; ?>">
                                <div class="flex-grow-1">
                                    <label class="form-label fw-bold small text-muted">Update Status</label>
                                    <select name="new_status" class="form-select rounded-pill fw-bold">
                                        <option value="new" <?php echo $msg['status'] === 'new' ? 'selected' : ''; ?>>🔵 New</option>
                                        <option value="in_progress" <?php echo $msg['status'] === 'in_progress' ? 'selected' : ''; ?>>🟡 In Progress</option>
                                        <option value="resolved" <?php echo $msg['status'] === 'resolved' ? 'selected' : ''; ?>>🟢 Resolved</option>
                                        <option value="closed" <?php echo $msg['status'] === 'closed' ? 'selected' : ''; ?>>⚫ Closed</option>
                                    </select>
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label fw-bold small text-muted">Admin Notes</label>
                                    <input type="text" name="admin_notes" class="form-control rounded-pill fw-bold" placeholder="Internal note..." value="<?php echo htmlspecialchars($msg['admin_notes'] ?? ''); ?>">
                                </div>
                                <button type="submit" class="btn btn-dark rounded-pill fw-bold px-4"><i class="fas fa-save me-1"></i>Update</button>
                            </form>
                        </div>

                        <!-- Meta info -->
                        <div class="border-top pt-3 mt-3 d-flex flex-wrap gap-3">
                            <small class="text-muted fw-bold"><i class="fas fa-globe me-1"></i>IP: <?php echo htmlspecialchars($msg['ip_address'] ?? 'N/A'); ?></small>
                            <?php if ($msg['resolved_at']): ?>
                                <small class="text-success fw-bold"><i class="fas fa-check me-1"></i>Resolved: <?php echo date('d M Y', strtotime($msg['resolved_at'])); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- REPLY MODAL -->
        <div class="modal fade" id="reply<?php echo $msg['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: 20px; border: none;">
                    <div class="modal-header border-0 pb-0 px-4 pt-4">
                        <h5 class="fw-black text-dark">Reply to #CT-<?php echo $msg['id']; ?></h5>
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body px-4 pb-4">
                        <div class="p-3 rounded-4 mb-3" style="background: #f8f9f7; border: 1px solid rgba(0,0,0,0.05);">
                            <small class="text-muted fw-bold d-block mb-1">Original message from <?php echo htmlspecialchars($msg['name']); ?>:</small>
                            <p class="mb-0 text-dark fw-bold small"><?php echo htmlspecialchars(substr($msg['message'], 0, 200)); ?><?php echo strlen($msg['message']) > 200 ? '...' : ''; ?></p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="send_reply">
                            <input type="hidden" name="msg_id" value="<?php echo $msg['id']; ?>">
                            <textarea name="admin_reply" class="form-control rounded-4 fw-bold" rows="5" placeholder="Type your reply..." required minlength="5" style="border: 2px solid #e0eadb;"></textarea>
                            <small class="text-muted fw-bold d-block mt-2 mb-3"><i class="fas fa-info-circle me-1"></i>This reply will be sent as an in-app notification and the ticket will be marked as resolved.</small>
                            <button type="submit" class="btn w-100 rounded-pill fw-bold py-3 text-white" style="background: var(--theme-primary);">
                                <i class="fas fa-paper-plane me-2"></i>Send Reply & Resolve
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php endwhile; ?>
    <?php endif; ?>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($p = 1; $p <= $total_pages; $p++): 
                $params_str = http_build_query(array_merge($_GET, ['page' => $p]));
            ?>
            <li class="page-item <?php echo $p === $current_page ? 'active' : ''; ?>">
                <a class="page-link rounded-pill fw-bold mx-1 shadow-sm <?php echo $p === $current_page ? 'text-white' : ''; ?>" 
                   href="?<?php echo $params_str; ?>"
                   <?php echo $p === $current_page ? 'style="background:var(--theme-primary); border-color:var(--theme-primary);"' : ''; ?>>
                    <?php echo $p; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
