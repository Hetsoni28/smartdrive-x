<?php
session_start();

// 🛡️ SECURITY: Super Admin Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

// Auto-create late_returns table
$conn->query("
    CREATE TABLE IF NOT EXISTS late_returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        due_datetime DATETIME NOT NULL,
        return_datetime DATETIME NOT NULL,
        late_minutes INT DEFAULT 0,
        chargeable_hours INT DEFAULT 0,
        hourly_rate DECIMAL(10,2) NOT NULL,
        grace_minutes INT DEFAULT 60,
        base_extra_charge DECIMAL(10,2) DEFAULT 0.00,
        gst_percentage DECIMAL(5,2) DEFAULT 18.00,
        gst_amount DECIMAL(10,2) DEFAULT 0.00,
        total_penalty DECIMAL(10,2) DEFAULT 0.00,
        admin_override TINYINT(1) DEFAULT 0,
        admin_notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_booking (booking_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$message = '';
$msg_type = '';

if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

// ==========================================
// 🔧 MANUAL OVERRIDE HANDLER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['override_charge'])) {
    $lr_id = intval($_POST['lr_id']);
    $new_total = floatval($_POST['new_total']);
    $notes = trim($_POST['admin_notes'] ?? '');
    
    // Reverse engineer: base = total / 1.GST
    $gst_pct = floatval(get_system_setting($conn, 'gst_percentage', '18'));
    $new_base = round($new_total / (1 + ($gst_pct / 100)), 2);
    $new_gst = round($new_total - $new_base, 2);
    
    $stmt = $conn->prepare("UPDATE late_returns SET base_extra_charge = ?, gst_amount = ?, total_penalty = ?, admin_override = 1, admin_notes = ? WHERE id = ?");
    $stmt->bind_param("dddsi", $new_base, $new_gst, $new_total, $notes, $lr_id);
    $stmt->execute();
    
    // Also update the booking
    $lr_row = $conn->query("SELECT booking_id FROM late_returns WHERE id = $lr_id")->fetch_assoc();
    if ($lr_row) {
        $bid = $lr_row['booking_id'];
        $conn->query("UPDATE bookings SET extra_charges = $new_base, gst_on_extra = $new_gst WHERE id = $bid");
        // Recalc final settlement
        $bk = $conn->query("SELECT final_price FROM bookings WHERE id = $bid")->fetch_assoc();
        if ($bk) {
            $settlement = round($bk['final_price'] + $new_base + $new_gst, 2);
            $conn->query("UPDATE bookings SET final_settlement = $settlement WHERE id = $bid");
        }
    }
    $stmt->close();
    
    $_SESSION['admin_msg'] = "Override applied successfully to Late Return #$lr_id.";
    $_SESSION['admin_msg_type'] = "success";
    header("Location: late_returns.php");
    exit();
}

// ==========================================
// 📊 ANALYTICS
// ==========================================
$total_lr = $conn->query("SELECT COUNT(*) as c FROM late_returns")->fetch_assoc()['c'] ?? 0;
$total_penalties = $conn->query("SELECT COALESCE(SUM(total_penalty), 0) as t FROM late_returns")->fetch_assoc()['t'] ?? 0;
$avg_late = $conn->query("SELECT COALESCE(AVG(late_minutes), 0) as a FROM late_returns WHERE late_minutes > 0")->fetch_assoc()['a'] ?? 0;
$avg_late_hrs = round($avg_late / 60, 1);

// ==========================================
// 🔍 FETCH LATE RETURNS WITH PAGINATION
// ==========================================
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$count = $conn->query("SELECT COUNT(*) as c FROM late_returns")->fetch_assoc()['c'] ?? 0;
$total_pages = ceil($count / $limit);

$result = $conn->query("
    SELECT lr.*, 
           b.final_price, b.start_date, b.end_date,
           u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
           c.brand, c.name as car_name
    FROM late_returns lr
    JOIN bookings b ON lr.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    JOIN cars c ON b.car_id = c.id
    ORDER BY lr.created_at DESC
    LIMIT $limit OFFSET $offset
");

$page_title = "Late Returns | Admin";
include '../includes/header.php';
?>

<style>
    :root {
        --teal-primary: #4da89c;
        --mint-secondary: #8bd0b4;
        --mint-pale: #ccecd4;
        --teal-dark: #1a2624;
    }
    body { background-color: #f4f7f6; }

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
    .table-container { background: white; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid rgba(0,0,0,0.02); }
    .table-beast { border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
    .table-beast th { background: #f8f9fa; color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 20px; font-weight: 800; }
    .table-beast tr { transition: all 0.2s; border-bottom: 1px solid #f1f1f1; }
    .table-beast tr:hover { background-color: rgba(77, 168, 156, 0.03); }
    .table-beast td { border: none; padding: 18px 20px; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }

    .pagination-custom .page-link { border: none; color: var(--teal-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--teal-primary); color: white; box-shadow: 0 5px 15px rgba(77, 168, 156, 0.4); }

    #admin-toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .admin-toast {
        background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        display: flex; align-items: center; gap: 15px; min-width: 320px;
        transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    .admin-toast.show { transform: translateX(0); opacity: 1; }
    .admin-toast.success { border-left: 5px solid #198754; }
</style>

<div id="admin-toast-container"></div>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>admin/dashboard.php" class="text-decoration-none fw-bold" style="color: var(--teal-primary);">Admin Panel</a></li>
                        <li class="breadcrumb-item active fw-bold text-muted">Late Returns</li>
                    </ol>
                </nav>
                <h2 class="fw-black text-dark mb-0"><i class="fas fa-clock me-2 text-warning"></i>Late Return Management</h2>
            </div>
            <a href="admin_settings.php" class="btn rounded-pill fw-bold shadow-sm text-white px-4 py-2" style="background-color: var(--teal-dark);">
                <i class="fas fa-cogs me-2"></i>Configure Rates
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="admin-stat-card p-4 border-bottom border-5 border-warning">
                    <i class="fas fa-clock stat-icon-bg text-warning"></i>
                    <h6 class="text-uppercase fw-bold text-muted mb-2 tracking-wide">Total Late Returns</h6>
                    <h2 class="fw-black mb-0 text-dark"><?php echo $total_lr; ?></h2>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="admin-stat-card p-4 border-bottom border-5" style="border-bottom-color: var(--teal-primary) !important;">
                    <i class="fas fa-rupee-sign stat-icon-bg" style="color: var(--teal-primary);"></i>
                    <h6 class="text-uppercase fw-bold text-muted mb-2 tracking-wide">Penalty Revenue</h6>
                    <h2 class="fw-black mb-0 text-dark">₹<?php echo number_format($total_penalties, 0); ?></h2>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="admin-stat-card p-4 border-bottom border-5 border-info">
                    <i class="fas fa-hourglass-half stat-icon-bg text-info"></i>
                    <h6 class="text-uppercase fw-bold text-muted mb-2 tracking-wide">Avg Late Duration</h6>
                    <h2 class="fw-black mb-0 text-dark"><?php echo $avg_late_hrs; ?> <span class="fs-6 text-muted fw-bold">Hours</span></h2>
                </div>
            </div>
        </div>

        <!-- Late Returns Table -->
        <div class="table-container mb-4">
            <div class="table-responsive">
                <table class="table table-beast">
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Client</th>
                            <th>Vehicle</th>
                            <th>Due Time</th>
                            <th>Returned</th>
                            <th class="text-center">Late</th>
                            <th class="text-end">Penalty</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="fw-black text-dark">#BKG-<?php echo $row['booking_id']; ?></span><br>
                                        <small class="text-muted fw-bold"><?php echo date('d M Y', strtotime($row['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($row['customer_name']); ?></h6>
                                        <small class="text-muted fw-bold"><?php echo htmlspecialchars($row['customer_phone']); ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($row['brand'] . ' ' . $row['car_name']); ?></span>
                                    </td>
                                    <td>
                                        <small class="fw-bold text-muted"><?php echo date('d M, h:i A', strtotime($row['due_datetime'])); ?></small>
                                    </td>
                                    <td>
                                        <small class="fw-bold text-danger"><?php echo date('d M, h:i A', strtotime($row['return_datetime'])); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $hrs = floor($row['late_minutes'] / 60);
                                        $mins = $row['late_minutes'] % 60;
                                        ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold">
                                            <?php echo $hrs; ?>h <?php echo $mins; ?>m
                                        </span>
                                        <br>
                                        <small class="text-muted fw-bold"><?php echo $row['chargeable_hours']; ?> billable hrs</small>
                                    </td>
                                    <td class="text-end">
                                        <span class="fw-black text-dark">₹<?php echo number_format($row['total_penalty'], 2); ?></span><br>
                                        <small class="text-muted fw-bold">GST: ₹<?php echo number_format($row['gst_amount'], 2); ?></small>
                                        <?php if($row['admin_override']): ?>
                                            <br><span class="badge bg-info rounded-pill mt-1" style="font-size: 0.6rem;">OVERRIDDEN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-dark rounded-pill fw-bold px-3" onclick="openOverrideModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['customer_name'])); ?>', <?php echo $row['total_penalty']; ?>, '<?php echo htmlspecialchars(addslashes($row['admin_notes'] ?? '')); ?>')">
                                            <i class="fas fa-edit me-1"></i>Override
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="opacity-25 mb-3"><i class="fas fa-check-circle fa-4x text-success"></i></div>
                                    <h5 class="fw-bold text-dark">No Late Returns Recorded</h5>
                                    <p class="text-muted small fw-bold">All vehicles have been returned on time. Great fleet discipline!</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-custom justify-content-center">
                    <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="<?php echo $page > 1 ? "?page=".($page-1) : "#"; ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="<?php echo $page < $total_pages ? "?page=".($page+1) : "#"; ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>

<!-- Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden bg-white">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-black text-dark"><i class="fas fa-edit text-info me-2"></i>Manual Override</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-3">
                <p class="text-muted small fw-bold mb-4">Adjust the total penalty for <strong id="ov_name"></strong>. The system will automatically recalculate GST breakdown.</p>
                <form action="late_returns.php" method="POST">
                    <input type="hidden" name="lr_id" id="ov_lr_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">New Total Penalty (₹)</label>
                        <input type="number" name="new_total" id="ov_total" class="form-control" step="0.01" min="0" required style="border-radius: 12px; padding: 12px;">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Admin Notes</label>
                        <textarea name="admin_notes" id="ov_notes" class="form-control" rows="3" placeholder="Reason for override..." style="border-radius: 12px; padding: 12px;"></textarea>
                    </div>
                    <button type="submit" name="override_charge" class="btn text-white fw-bold rounded-pill shadow-sm w-100 py-3" style="background-color: var(--teal-dark);">
                        Apply Override
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openOverrideModal(id, name, total, notes) {
        document.getElementById('ov_lr_id').value = id;
        document.getElementById('ov_name').textContent = name;
        document.getElementById('ov_total').value = total;
        document.getElementById('ov_notes').value = notes;
        new bootstrap.Modal(document.getElementById('overrideModal')).show();
    }

    <?php if($message): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const tc = document.getElementById('admin-toast-container');
        const t = document.createElement('div');
        t.className = 'admin-toast <?php echo $msg_type; ?>';
        t.innerHTML = '<i class="fas fa-check-circle text-success fs-4"></i><div><h6 class="fw-bold mb-0 text-dark">System Update</h6><small class="text-muted fw-bold"><?php echo addslashes($message); ?></small></div>';
        tc.appendChild(t);
        setTimeout(() => t.classList.add('show'), 100);
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 500); }, 5000);
    });
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
