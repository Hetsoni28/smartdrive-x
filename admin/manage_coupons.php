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
// 🗑️ SECURE DELETE REQUEST (SQLi PATCHED)
// ==========================================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Using Prepared Statements to prevent SQL Injection
    $delete_stmt = $conn->prepare("DELETE FROM coupons WHERE id = ?");
    $delete_stmt->bind_param("i", $delete_id);
    
    if ($delete_stmt->execute()) {
        $_SESSION['admin_msg'] = "Promo code successfully deactivated and wiped.";
        $_SESSION['admin_msg_type'] = "secondary";
    } else {
        $_SESSION['admin_msg'] = "Database Error: Could not delete coupon.";
        $_SESSION['admin_msg_type'] = "danger";
    }
    $delete_stmt->close();
    
    header("Location: manage_coupons.php");
    exit();
}

// ==========================================
// ➕ SECURE ADD COUPON REQUEST
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_coupon'])) {
    // Force uppercase and strip spaces
    $code = strtoupper(preg_replace('/\s+/', '', $_POST['code']));
    $discount_type = trim($_POST['discount_type']);
    $discount_value = floatval($_POST['discount_value']);
    $expiry_date = trim($_POST['expiry_date']);

    // Backend Validation
    if ($discount_type == 'percentage' && $discount_value > 100) {
        $_SESSION['admin_msg'] = "Error: Percentage discount cannot exceed 100%.";
        $_SESSION['admin_msg_type'] = "danger";
    } else {
        // Check if code already exists
        $check_stmt = $conn->prepare("SELECT id FROM coupons WHERE code = ?");
        $check_stmt->bind_param("s", $code);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $_SESSION['admin_msg'] = "Conflict: The code '$code' already exists.";
            $_SESSION['admin_msg_type'] = "warning";
        } else {
            // Insert securely
            $insert_stmt = $conn->prepare("INSERT INTO coupons (code, discount_type, discount_value, expiry_date) VALUES (?, ?, ?, ?)");
            $insert_stmt->bind_param("ssds", $code, $discount_type, $discount_value, $expiry_date);
            
            if ($insert_stmt->execute()) {
                $_SESSION['admin_msg'] = "Success! Promo code '$code' is now live.";
                $_SESSION['admin_msg_type'] = "success";
            } else {
                $_SESSION['admin_msg'] = "System Error: Failed to generate coupon.";
                $_SESSION['admin_msg_type'] = "danger";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    }
    header("Location: manage_coupons.php");
    exit();
}

// ==========================================
// 🔍 SEARCH, FILTER & PAGINATION ENGINE
// ==========================================
$today = date('Y-m-d');
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_sql = "WHERE 1=1 ";
if (!empty($search)) $where_sql .= " AND code LIKE '%$search%' ";

if ($status_filter == 'active') {
    $where_sql .= " AND expiry_date >= '$today' ";
} elseif ($status_filter == 'expired') {
    $where_sql .= " AND expiry_date < '$today' ";
}

// Pagination Count
$total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM coupons $where_sql"))['total'];
$total_pages = ceil($total_records / $limit);

// Fetch Table Data
$coupons_query = mysqli_query($conn, "SELECT * FROM coupons $where_sql ORDER BY expiry_date DESC, id DESC LIMIT $limit OFFSET $offset");

// KPI Calculations (Global, unfiltered)
$active_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM coupons WHERE expiry_date >= '$today'"))['c'] ?? 0;
$expired_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM coupons WHERE expiry_date < '$today'"))['c'] ?? 0;
$max_discount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(discount_value) as m FROM coupons WHERE discount_type = 'percentage' AND expiry_date >= '$today'"))['m'] ?? 0;

$page_title = "Discount Engine | Admin";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🎨 ADMIN TEAL THEME (Preserved) */
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
        content: '\f145'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: 20px; bottom: -30px; font-size: 10rem; 
        color: rgba(255,255,255,0.05); transform: rotate(-15deg); pointer-events: none;
    }

    .admin-stat-card {
        border-radius: 20px; border: 1px solid rgba(0,0,0,0.03); background: white;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
    }
    .admin-stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(77, 168, 156, 0.12); }

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

    /* Promo Code Badge */
    .promo-badge {
        background: rgba(77, 168, 156, 0.1); color: var(--teal-dark); border: 1px dashed var(--teal-primary);
        font-family: monospace; font-size: 1rem; letter-spacing: 2px; padding: 8px 15px; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: space-between; min-width: 150px;
    }
    .copy-btn { cursor: pointer; transition: all 0.2s; border: none; background: transparent; color: var(--teal-primary); }
    .copy-btn:hover { color: var(--teal-dark); transform: scale(1.2); }

    /* Modals & Forms */
    .modal-content { border-radius: 20px; border: none; overflow: hidden; }
    .modal-header { background-color: var(--teal-dark); color: white; border: none; padding: 1.5rem; }
    .form-control-custom { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 12px; padding: 12px 20px; transition: all 0.3s; }
    .form-control-custom:focus { border-color: var(--teal-primary); box-shadow: 0 0 0 0.25rem rgba(77, 168, 156, 0.25); background-color: white; outline: none; }

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
        
        <div class="admin-header-card d-flex justify-content-between align-items-center flex-wrap mb-4 shadow-sm" data-aos="fade-in">
            <div style="z-index: 2;">
                <h2 class="fw-bold mb-1"><i class="fas fa-ticket-alt me-2 opacity-75"></i>Discount Engine</h2>
                <p class="mb-0 text-light opacity-75 fs-5">Generate and monitor promotional campaigns.</p>
            </div>
            <div class="mt-3 mt-md-0" style="z-index: 2;">
                <button type="button" class="btn btn-light text-dark rounded-pill fw-bold px-4 py-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCouponModal">
                    <i class="fas fa-magic me-2" style="color: var(--teal-primary);"></i> Create Promo
                </button>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4 border-success">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Active Promos</h6>
                    <h2 class="fw-black text-success mb-0"><?php echo $active_count; ?></h2>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4 border-danger">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Expired Campaigns</h6>
                    <h2 class="fw-black text-danger mb-0"><?php echo $expired_count; ?></h2>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4" style="border-color: var(--teal-primary) !important;">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Max Active Discount</h6>
                    <h2 class="fw-black mb-0" style="color: var(--teal-primary);"><?php echo $max_discount; ?>% OFF</h2>
                </div>
            </div>
        </div>

        <div class="filter-bar mb-4" data-aos="fade-up" data-aos-delay="300">
            <form action="manage_coupons.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-search" name="search" placeholder="Search Code..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select form-control-search" name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>✅ Active</option>
                        <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>❌ Expired</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn text-white fw-bold rounded-3 shadow-sm py-2" style="background-color: var(--teal-primary);">Filter Engine</button>
                </div>
            </form>
        </div>

        <div class="table-container mb-4" data-aos="fade-up" data-aos-delay="400">
            <div class="table-responsive">
                <table class="table table-hover table-beast">
                    <thead>
                        <tr>
                            <th>Promo Code</th>
                            <th>Value</th>
                            <th>Expiration Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($coupons_query) > 0): ?>
                            <?php while($coupon = mysqli_fetch_assoc($coupons_query)): 
                                $is_expired = ($coupon['expiry_date'] < $today);
                            ?>
                                <tr>
                                    <td>
                                        <div class="promo-badge shadow-sm">
                                            <span id="code-<?php echo $coupon['id']; ?>" class="fw-bold"><?php echo htmlspecialchars($coupon['code']); ?></span>
                                            <button class="copy-btn" onclick="copyToClipboard('code-<?php echo $coupon['id']; ?>', this)" title="Copy Code">
                                                <i class="far fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="fw-black text-dark fs-6">
                                        <?php if($coupon['discount_type'] == 'percentage'): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info rounded-pill px-3 py-2"><i class="fas fa-percent me-1"></i> <?php echo $coupon['discount_value']; ?> OFF</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill px-3 py-2 border" style="background-color: var(--teal-light); color: var(--teal-dark); border-color: var(--teal-primary) !important;"><i class="fas fa-rupee-sign me-1"></i> <?php echo number_format($coupon['discount_value'], 0); ?> OFF</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted fw-bold">
                                        <i class="far fa-calendar-alt me-2 text-primary"></i><?php echo date('d M Y', strtotime($coupon['expiry_date'])); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($is_expired): ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i> Expired</span>
                                        <?php else: ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i> Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?php echo $base_url; ?>admin/manage_coupons.php?delete=<?php echo $coupon['id']; ?>" 
                                           class="btn btn-light btn-sm text-danger border rounded-circle shadow-sm d-inline-flex align-items-center justify-content-center"
                                           style="width: 35px; height: 35px; transition: 0.2s;"
                                           onclick="return confirm('Are you sure you want to permanently delete this promo code?');"
                                           onmouseover="this.classList.replace('btn-light','btn-danger'); this.classList.replace('text-danger','text-white');"
                                           onmouseout="this.classList.replace('btn-danger','btn-light'); this.classList.replace('text-white','text-danger');"
                                           title="Delete Coupon">
                                           <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-ticket-alt fa-4x mb-3 opacity-25 d-block"></i>
                                    <h5 class="fw-bold text-dark">No promotional codes found.</h5>
                                    <p class="small fw-bold">Try adjusting filters or create a new campaign.</p>
                                    <a href="manage_coupons.php" class="btn rounded-pill fw-bold text-white px-4 mt-2" style="background: var(--teal-primary);">Clear Filters</a>
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
                            <a class="page-link" href="manage_coupons.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status_filter; ?>"> <?php echo $i; ?> </a>
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

<div class="modal fade" id="addCouponModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold"><i class="fas fa-magic me-2 text-white"></i> Generate Promo Campaign</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo $base_url; ?>admin/manage_coupons.php" method="POST" id="promoForm">
                <div class="modal-body p-4 p-md-5 bg-light">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Promo Code Identifier</label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-0 text-muted"><i class="fas fa-tag"></i></span>
                            <input type="text" name="code" class="form-control form-control-custom border-0 ps-0" placeholder="e.g., SUMMER25" style="text-transform: uppercase; font-family: monospace; font-size: 1.1rem; letter-spacing: 2px;" required autofocus>
                        </div>
                        <small class="text-muted mt-2 d-block fw-bold"><i class="fas fa-info-circle me-1"></i> Customers will type this exactly to claim.</small>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Discount Type</label>
                            <select name="discount_type" id="discount_type" class="form-select form-control-custom shadow-sm" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Value</label>
                            <input type="number" step="0.01" min="1" name="discount_value" id="discount_value" class="form-control form-control-custom shadow-sm" placeholder="e.g., 15" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Expiration Date</label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-0 text-muted"><i class="far fa-calendar-times"></i></span>
                            <input type="date" name="expiry_date" class="form-control form-control-custom border-0 ps-0" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <small class="text-danger mt-2 d-block fw-bold"><i class="fas fa-clock me-1"></i> Expires automatically at 23:59 on this date.</small>
                    </div>

                </div>
                <div class="modal-footer bg-light border-0 pt-0 pb-4 pe-4">
                    <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_coupon" class="btn rounded-pill px-4 fw-bold shadow-sm text-white" style="background: var(--teal-primary);"><i class="fas fa-rocket me-2"></i> Launch Campaign</button>
                </div>
            </form>
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

    // 3. COPY TO CLIPBOARD UX
    function copyToClipboard(elementId, btnElement) {
        var textToCopy = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(textToCopy).then(function() {
            var originalIcon = btnElement.innerHTML;
            btnElement.innerHTML = '<i class="fas fa-check text-success"></i>';
            setTimeout(function() { btnElement.innerHTML = originalIcon; }, 2000);
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
        });
    }

    // 4. FRONTEND FORM VALIDATION
    const promoForm = document.getElementById('promoForm');
    const typeSelect = document.getElementById('discount_type');
    const valInput = document.getElementById('discount_value');

    promoForm.addEventListener('submit', function(e) {
        if (typeSelect.value === 'percentage' && parseFloat(valInput.value) > 100) {
            e.preventDefault();
            alert("Error: Percentage discount cannot be greater than 100%.");
            valInput.focus();
        }
    });
</script>

<?php include '../includes/footer.php'; ?>