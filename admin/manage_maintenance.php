<?php
session_start();

// 🛡️ SECURITY: Admin Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

// Safe include for notifications
if (file_exists('../includes/notify.php')) {
    include_once '../includes/notify.php';
}

$message = '';
$msg_type = '';

// Check session for PRG (Post/Redirect/Get) toast messages
if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

// ==========================================
// 🛠️ ACTION 1: SEND CAR TO WORKSHOP (Secured)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_to_workshop'])) {
    $car_id = intval($_POST['car_id']);
    
    // Prevent taking a car offline if someone is currently driving it or about to!
    $check_stmt = $conn->prepare("SELECT id FROM bookings WHERE car_id = ? AND booking_status = 'confirmed' AND end_date >= CURDATE()");
    $check_stmt->bind_param("i", $car_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['admin_msg'] = "Action Denied: This vehicle has an active or upcoming confirmed booking. Cancel the booking first.";
        $_SESSION['admin_msg_type'] = "danger";
    } else {
        $update_stmt = $conn->prepare("UPDATE cars SET status = 'maintenance' WHERE id = ?");
        $update_stmt->bind_param("i", $car_id);
        
        if ($update_stmt->execute()) {
            // Fetch car details to notify admins
            $info_stmt = $conn->prepare("SELECT brand, name FROM cars WHERE id = ?");
            $info_stmt->bind_param("i", $car_id);
            $info_stmt->execute();
            $car_info = $info_stmt->get_result()->fetch_assoc();
            
            if (function_exists('notify_admins')) {
                notify_admins($conn, "⚠️ Vehicle Offline", "{$car_info['brand']} {$car_info['name']} has been sent to maintenance.", 'warning', 'admin/manage_maintenance.php', 'fa-wrench');
            }

            $_SESSION['admin_msg'] = "Vehicle taken offline. It is now hidden from the customer booking portal.";
            $_SESSION['admin_msg_type'] = "warning";
        } else {
            $_SESSION['admin_msg'] = "Database Error: Could not update vehicle status.";
            $_SESSION['admin_msg_type'] = "danger";
        }
        $update_stmt->close();
    }
    $check_stmt->close();
    
    header("Location: manage_maintenance.php");
    exit();
}

// ==========================================
// ✅ ACTION 2: LOG COMPLETED SERVICE (Secured)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complete_service'])) {
    $car_id = intval($_POST['car_id']);
    $service_date = trim($_POST['service_date']);
    $cost = floatval($_POST['cost']);
    $description = trim($_POST['description']);

    // Server-side validation: ensure car is actually in maintenance
    $status_stmt = $conn->prepare("SELECT status FROM cars WHERE id = ?");
    $status_stmt->bind_param("i", $car_id);
    $status_stmt->execute();
    $car_status = $status_stmt->get_result()->fetch_assoc()['status'] ?? '';
    $status_stmt->close();

    if ($car_status !== 'maintenance') {
        $_SESSION['admin_msg'] = "Action Denied: This vehicle is not currently in maintenance.";
        $_SESSION['admin_msg_type'] = "danger";
    } else {
        // 🚦 Enterprise Transaction Engine
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Securely Insert the maintenance record
            $stmt = $conn->prepare("INSERT INTO maintenance_records (car_id, service_date, description, cost) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issd", $car_id, $service_date, $description, $cost);
            $stmt->execute();
            $stmt->close();
            
            // 2. Make the car available again
            $avail_stmt = $conn->prepare("UPDATE cars SET status = 'available' WHERE id = ?");
            $avail_stmt->bind_param("i", $car_id);
            $avail_stmt->execute();
            $avail_stmt->close();
            
            mysqli_commit($conn);
            $_SESSION['admin_msg'] = "Service logged securely. The vehicle is back online and ready for customers.";
            $_SESSION['admin_msg_type'] = "success";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['admin_msg'] = "System Failure: Could not log service. Data rolled back safely.";
            $_SESSION['admin_msg_type'] = "danger";
        }
    }
    header("Location: manage_maintenance.php");
    exit();
}

// ==========================================
// 📊 FETCH DATA, ANALYTICS & PAGINATION
// ==========================================
// 1. KPI Analytics
$offline_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars WHERE status = 'maintenance'"))['c'] ?? 0;
$lifetime_cost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(cost) as total FROM maintenance_records"))['total'] ?? 0;
$monthly_cost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(cost) as total FROM maintenance_records WHERE MONTH(service_date) = MONTH(CURDATE()) AND YEAR(service_date) = YEAR(CURDATE())"))['total'] ?? 0;

// 2. Active Job Cards (Cars currently broken down)
$in_workshop_query = mysqli_query($conn, "
    SELECT c.id, c.brand, c.name, c.model, l.city_name 
    FROM cars c LEFT JOIN locations l ON c.location_id = l.id 
    WHERE c.status = 'maintenance'
");

// 3. Available cars for dropdown
$available_cars_query = mysqli_query($conn, "
    SELECT c.id, c.brand, c.name, l.city_name 
    FROM cars c LEFT JOIN locations l ON c.location_id = l.id 
    WHERE c.status = 'available' ORDER BY c.brand ASC
");

// 4. Ledger Pagination & Search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_sql = "WHERE 1=1 ";
if (!empty($search)) {
    $where_sql .= " AND (c.brand LIKE '%$search%' OR c.name LIKE '%$search%' OR m.description LIKE '%$search%') ";
}

$total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM maintenance_records m JOIN cars c ON m.car_id = c.id $where_sql"))['total'];
$total_pages = ceil($total_records / $limit);

$history_query = mysqli_query($conn, "
    SELECT m.*, c.brand, c.name 
    FROM maintenance_records m JOIN cars c ON m.car_id = c.id 
    $where_sql ORDER BY m.service_date DESC, m.id DESC LIMIT $limit OFFSET $offset
");

$page_title = "Fleet Maintenance | Admin";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<style>
    /* 🎨 ADMIN TEAL THEME */
    :root {
        --teal-primary: #4da89c;
        --teal-dark: #1a2624;
        --mint-pale: #ccecd4;
    }
    
    body { background-color: #f4f7f6; }
    
    .admin-header-card {
        background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal-primary) 100%);
        color: white; border-radius: 20px; padding: 2.5rem;
        position: relative; overflow: hidden;
    }
    .admin-header-card::after {
        content: '\f0ad'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: 20px; bottom: -30px; font-size: 10rem; 
        color: rgba(255,255,255,0.05); transform: rotate(-15deg); pointer-events: none;
    }

    .admin-stat-card {
        border-radius: 20px; border: 1px solid rgba(0,0,0,0.03); background: white;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
    }
    .admin-stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(77, 168, 156, 0.12); }

    /* Kanban Job Card UI */
    .job-card {
        border-left: 5px solid #dc3545; border-radius: 16px; background: white;
        transition: transform 0.3s ease, box-shadow 0.3s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.03);
    }
    .job-card:hover { transform: translateX(5px); box-shadow: 0 10px 25px rgba(220, 53, 69, 0.1); }
    
    /* Pulse Indicator */
    .pulse-red {
        display: inline-block; width: 12px; height: 12px; border-radius: 50%;
        background: #dc3545; box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); animation: pulse 2s infinite;
    }
    @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }

    /* Enterprise Table & Search */
    .filter-bar { background: white; border-radius: 20px; padding: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.02); }
    .form-control-search { background-color: #f8f9fa; border: none; border-radius: 12px; padding: 12px 20px; font-weight: 600; color: var(--teal-dark); }
    .form-control-search:focus { outline: none; box-shadow: 0 0 0 4px rgba(77, 168, 156, 0.15); background-color: white; }
    
    .table-container { background: white; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid rgba(0,0,0,0.02); }
    .table-beast { border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
    .table-beast th { background: #f8f9fa; color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 20px; font-weight: 800; }
    .table-beast td { padding: 20px; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }
    .table-hover tbody tr:hover { background-color: rgba(77, 168, 156, 0.03); }

    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--teal-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--teal-primary); color: white; box-shadow: 0 5px 15px rgba(77, 168, 156, 0.4); }

    /* Modals */
    .modal-content { border-radius: 24px; border: none; overflow: hidden; }
    .form-control-custom { background-color: #f8f9fa; border: 2px solid transparent; border-radius: 12px; padding: 14px 20px; font-weight: 600;}
    .form-control-custom:focus { border-color: var(--teal-primary); box-shadow: 0 0 0 0.25rem rgba(77, 168, 156, 0.25); background-color: white; }

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
    .admin-toast.secondary { border-left: 5px solid #6c757d; }
</style>

<div id="admin-toast-container"></div>

<div class="dashboard-layout">
    
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content bg-light">
        
        <div class="admin-header-card d-flex justify-content-between align-items-center flex-wrap mb-4 shadow-sm" data-aos="fade-in">
            <div style="z-index: 2;">
                <h2 class="fw-bold mb-1">Fleet Maintenance Tracker</h2>
                <p class="mb-0 text-light opacity-75 fs-5">Log repair costs, monitor workshop status, and manage offline vehicles.</p>
            </div>
            <div class="mt-3 mt-md-0" style="z-index: 2;">
                <button type="button" class="btn btn-warning text-dark rounded-pill fw-bold px-4 py-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#sendWorkshopModal">
                    <i class="fas fa-exclamation-triangle me-2"></i> Report Issue & Take Offline
                </button>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4 border-danger">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Vehicles Offline</h6>
                    <div class="d-flex align-items-center justify-content-center">
                        <?php if($offline_count > 0): ?><span class="pulse-red me-2"></span><?php endif; ?>
                        <h2 class="fw-black text-dark mb-0"><?php echo $offline_count; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4 border-warning">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Expenses (This Month)</h6>
                    <h2 class="fw-black text-warning mb-0">₹<?php echo number_format($monthly_cost, 0); ?></h2>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4 border-secondary">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Lifetime Repair Costs</h6>
                    <h2 class="fw-black text-secondary mb-0">₹<?php echo number_format($lifetime_cost, 0); ?></h2>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-hard-hat text-danger me-2"></i> Active Job Cards</h5>
                    <span class="badge bg-danger rounded-pill"><?php echo $offline_count; ?> pending</span>
                </div>
                
                <?php if(mysqli_num_rows($in_workshop_query) > 0): ?>
                    <div class="d-flex flex-column gap-3" data-aos="fade-right">
                        <?php while($car = mysqli_fetch_assoc($in_workshop_query)): ?>
                            <div class="job-card p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <span class="badge bg-light text-dark border mb-2"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($car['city_name'] ?? 'Unknown'); ?> Hub</span>
                                        <h5 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['name']); ?></h5>
                                        <small class="text-muted fw-bold"><?php echo htmlspecialchars($car['model']); ?></small>
                                    </div>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill"><i class="fas fa-cog fa-spin me-1"></i> Offline</span>
                                </div>
                                
                                <div class="mt-2 pt-3 border-top border-light">
                                    <button class="btn btn-outline-success rounded-pill fw-bold w-100 py-2" 
                                            onclick="openServiceModal(<?php echo $car['id']; ?>, '<?php echo addslashes(htmlspecialchars($car['brand'] . ' ' . $car['name'])); ?>')">
                                        <i class="fas fa-clipboard-check me-2"></i> Resolve & Restore Status
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white h-100 d-flex justify-content-center" data-aos="fade-right">
                        <i class="fas fa-check-double fa-4x mb-3 text-success opacity-50"></i>
                        <h5 class="fw-bold text-dark">Fleet is 100% Operational!</h5>
                        <p class="text-muted small fw-bold mb-0">No vehicles are currently in the workshop.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-xl-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i> Maintenance Ledger</h5>
                </div>

                <div class="filter-bar mb-3" data-aos="fade-up">
                    <form action="manage_maintenance.php" method="GET" class="row g-3 align-items-center">
                        <div class="col-md-9">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control form-control-search" name="search" placeholder="Search by Vehicle or Keyword..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn text-white fw-bold rounded-3 shadow-sm py-2" style="background-color: var(--teal-primary);">Search Logs</button>
                        </div>
                    </form>
                </div>

                <div class="table-container" data-aos="fade-up" data-aos-delay="100">
                    <div class="table-responsive">
                        <table class="table table-hover table-beast">
                            <thead class="bg-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Vehicle Repaired</th>
                                    <th>Mechanic Notes</th>
                                    <th class="text-end pe-4">Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($history_query) > 0): ?>
                                    <?php while($record = mysqli_fetch_assoc($history_query)): ?>
                                        <tr>
                                            <td class="text-muted small fw-bold">
                                                <i class="far fa-calendar-check me-1"></i> <?php echo date('d M, Y', strtotime($record['service_date'])); ?>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                <i class="fas fa-car-side me-1" style="color: var(--teal-primary);"></i> <?php echo htmlspecialchars($record['brand'] . ' ' . $record['name']); ?>
                                            </td>
                                            <td>
                                                <span class="d-inline-block text-truncate text-muted" style="max-width: 250px;" title="<?php echo htmlspecialchars($record['description']); ?>">
                                                    <?php echo htmlspecialchars($record['description']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4 fw-black text-danger">
                                                ₹<?php echo number_format($record['cost'], 0); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fas fa-receipt fa-3x opacity-25 mb-3 d-block"></i>
                                            <h6 class="fw-bold text-dark">No maintenance records found.</h6>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4" data-aos="fade-up">
                        <ul class="pagination pagination-custom justify-content-center mb-0">
                            <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php if($page <= 1){ echo '#'; } else { echo "?page=".($page - 1)."&search=$search"; } ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for($i = 1; $i <= $total_pages; $i++ ): ?>
                                <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                                    <a class="page-link" href="manage_maintenance.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>"> <?php echo $i; ?> </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                                <a class="page-link" href="<?php if($page >= $total_pages){ echo '#'; } else { echo "?page=".($page + 1)."&search=$search"; } ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="sendWorkshopModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-warning border-0 p-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-exclamation-triangle me-2"></i> Report Vehicle Issue</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_maintenance.php" method="POST">
                <div class="modal-body p-4 p-md-5 bg-light">
                    <p class="text-muted small mb-4 fw-bold">Taking a vehicle offline will instantly remove it from the customer search portal to prevent new bookings.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small text-uppercase">Select Operational Vehicle</label>
                        <select name="car_id" class="form-select form-control-custom shadow-sm" required>
                            <option value="">Browse available fleet...</option>
                            <?php while($car = mysqli_fetch_assoc($available_cars_query)): ?>
                                <option value="<?php echo $car['id']; ?>">
                                    <?php echo htmlspecialchars($car['brand'] . ' ' . $car['name'] . ' (' . ($car['city_name'] ?? 'Unknown Hub') . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 pt-0 pb-4 pe-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_to_workshop" class="btn btn-warning rounded-pill px-4 fw-bold text-dark shadow-sm">Take Offline</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="completeServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-0 p-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-clipboard-check me-2"></i> Log Service & Restore</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_maintenance.php" method="POST">
                <div class="modal-body p-4 p-md-5 bg-light">
                    
                    <input type="hidden" name="car_id" id="service_car_id" value="">
                    
                    <div class="bg-white p-3 rounded-4 border border-success border-opacity-25 mb-4 text-center shadow-sm">
                        <h6 class="mb-0 text-success fw-bold text-uppercase small tracking-wide" id="service_car_name">Vehicle Name Here</h6>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Service Date</label>
                            <input type="date" name="service_date" class="form-control form-control-custom shadow-sm" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Total Cost (₹)</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-white border-0 text-muted">₹</span>
                                <input type="number" step="0.01" min="0" name="cost" class="form-control form-control-custom border-start-0 ps-0" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Mechanic Notes / Parts Replaced</label>
                        <textarea name="description" class="form-control form-control-custom shadow-sm" rows="3" placeholder="e.g., Oil change, replaced front brake pads, wheel alignment." required></textarea>
                    </div>
                    
                </div>
                <div class="modal-footer bg-light border-0 pt-0 pb-4 pe-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="complete_service" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">Save & Restore Vehicle</button>
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

    // 3. Modal Data Populator
    function openServiceModal(carId, carName) {
        document.getElementById('service_car_id').value = carId;
        // Safe injection
        const nameBadge = document.getElementById('service_car_name');
        nameBadge.textContent = '';
        const icon = document.createElement('i');
        icon.className = 'fas fa-car me-2';
        nameBadge.appendChild(icon);
        nameBadge.appendChild(document.createTextNode(carName));
        
        var myModal = new bootstrap.Modal(document.getElementById('completeServiceModal'));
        myModal.show();
    }
</script>

<?php include '../includes/footer.php'; ?>