<?php
session_start();

// 🛡️ SECURITY: Admin Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

// Check session for PRG messages
if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
} else {
    $message = '';
    $msg_type = '';
}

// ==========================================
// 🗑️ SECURE DELETE REQUEST (SQLi PATCHED)
// ==========================================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    
    // Check if there are cars assigned to this location first
    $check_stmt = $conn->prepare("SELECT id FROM cars WHERE location_id = ?");
    $check_stmt->bind_param("i", $delete_id);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $_SESSION['admin_msg'] = "Operation Failed: There are active vehicles currently stationed at this hub. Relocate them before deleting.";
        $_SESSION['admin_msg_type'] = "warning";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM locations WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        if ($delete_stmt->execute()) {
            $_SESSION['admin_msg'] = "City Hub successfully removed from the network.";
            $_SESSION['admin_msg_type'] = "secondary";
        } else {
            $_SESSION['admin_msg'] = "Database Error: Could not delete location.";
            $_SESSION['admin_msg_type'] = "danger";
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
    
    header("Location: manage_locations.php");
    exit();
}

// ==========================================
// ➕ SECURE ADD LOCATION REQUEST
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_location'])) {
    // Format city name perfectly (e.g., "new delhi" -> "New Delhi")
    $city_name = ucwords(strtolower(trim($_POST['city_name'])));

    // Check if location already exists
    $check_exists = $conn->prepare("SELECT id FROM locations WHERE LOWER(city_name) = LOWER(?)");
    $check_exists->bind_param("s", $city_name);
    $check_exists->execute();
    $check_exists->store_result();

    if ($check_exists->num_rows > 0) {
        $_SESSION['admin_msg'] = "Conflict: '$city_name' already exists in your network.";
        $_SESSION['admin_msg_type'] = "warning";
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO locations (city_name) VALUES (?)");
        $insert_stmt->bind_param("s", $city_name);
        
        if ($insert_stmt->execute()) {
            $_SESSION['admin_msg'] = "Success! $city_name Hub deployed successfully.";
            $_SESSION['admin_msg_type'] = "success";
        } else {
            $_SESSION['admin_msg'] = "Error deploying location.";
            $_SESSION['admin_msg_type'] = "danger";
        }
        $insert_stmt->close();
    }
    $check_exists->close();
    
    header("Location: manage_locations.php");
    exit();
}

// ==========================================
// 📊 FETCH LOCATIONS & CAPACITY HEALTH
// ==========================================
// Enterprise query calculates total cars AND availability status per hub
$locations_query = mysqli_query($conn, "
    SELECT l.id, l.city_name, 
           COUNT(c.id) as total_cars,
           SUM(CASE WHEN c.status = 'available' THEN 1 ELSE 0 END) as avail_cars,
           SUM(CASE WHEN c.status = 'maintenance' THEN 1 ELSE 0 END) as maint_cars
    FROM locations l 
    LEFT JOIN cars c ON l.id = c.location_id 
    GROUP BY l.id 
    ORDER BY l.city_name ASC
");

$total_hubs = mysqli_num_rows($locations_query);
$total_fleet_network = 0;
$operational_capacity = 0;

$locations_data = [];
while($row = mysqli_fetch_assoc($locations_query)) {
    $locations_data[] = $row;
    $total_fleet_network += $row['total_cars'];
    $operational_capacity += $row['avail_cars'];
}

$page_title = "Hub Logistics | Admin";
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
        content: '\f5a0'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: 20px; bottom: -30px;
        font-size: 10rem; color: rgba(255,255,255,0.05); transform: rotate(-15deg); pointer-events: none;
    }

    .admin-stat-card {
        border-radius: 20px; border: 1px solid rgba(0,0,0,0.03); background: white;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
    }
    .admin-stat-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(77, 168, 156, 0.12); }

    /* Filter Bar */
    .filter-bar { background: white; border-radius: 20px; padding: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.02); }
    .form-control-search { background-color: #f8f9fa; border: none; border-radius: 12px; padding: 12px 20px; font-weight: 600; color: var(--teal-dark); transition: all 0.3s;}
    .form-control-search:focus { outline: none; box-shadow: 0 0 0 4px rgba(77, 168, 156, 0.15); background-color: white; }

    /* Map Card UI */
    .hub-card {
        border: none; border-radius: 24px; overflow: hidden; position: relative;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid #f1f1f1;
    }
    .hub-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(77, 168, 156, 0.15); border-color: var(--teal-light); }
    
    .hub-map-header {
        height: 140px; background: #e9ecef; position: relative; overflow: hidden;
    }
    .hub-map-header img { width: 100%; height: 100%; object-fit: cover; filter: grayscale(40%) contrast(120%); transition: all 0.5s; }
    .hub-card:hover .hub-map-header img { transform: scale(1.1); filter: grayscale(0%); }
    
    .hub-map-header::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: linear-gradient(to bottom, transparent, rgba(26, 38, 36, 0.9)); z-index: 1;
    }
    .hub-title { position: absolute; bottom: 15px; left: 20px; color: white; z-index: 2; margin: 0; font-weight: 800; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }

    /* Capacity Progress */
    .health-bar { height: 6px; border-radius: 10px; background-color: #f8f9fa; overflow: hidden; display: flex; }
    .health-avail { background-color: #198754; }
    .health-maint { background-color: #dc3545; }

    /* Modal Styling */
    .modal-content { border-radius: 24px; border: none; overflow: hidden; }
    .modal-header { background-color: var(--teal-dark); color: white; border: none; padding: 1.5rem; }
    .form-control-custom { background-color: #f8f9fa; border: 2px solid transparent; border-radius: 12px; padding: 15px; transition: all 0.3s; font-weight: 600;}
    .form-control-custom:focus { border-color: var(--teal-primary); box-shadow: 0 0 0 4px rgba(77, 168, 156, 0.15); background-color: white; }

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
    
    <div class="dashboard-content bg-light">
        
        <div class="admin-header-card d-flex justify-content-between align-items-center flex-wrap mb-4 shadow-sm" data-aos="fade-in">
            <div style="z-index: 2;">
                <h2 class="fw-bold mb-1">Logistics & Hub Network</h2>
                <p class="mb-0 text-light opacity-75 fs-5">Monitor global capacity and manage physical operational hubs.</p>
            </div>
            <div class="mt-3 mt-md-0" style="z-index: 2;">
                <button type="button" class="btn btn-light text-dark rounded-pill fw-bold px-4 py-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                    <i class="fas fa-map-pin me-2" style="color: var(--teal-primary);"></i> Deploy New Hub
                </button>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4" style="border-color: var(--teal-primary) !important;">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Operational Hubs</h6>
                    <h2 class="fw-black mb-0" style="color: var(--teal-primary);"><?php echo $total_hubs; ?></h2>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4 border-dark">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Total Network Fleet</h6>
                    <h2 class="fw-black text-dark mb-0"><?php echo $total_fleet_network; ?></h2>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="admin-stat-card p-4 text-center h-100 border-bottom border-4 border-success">
                    <h6 class="text-muted fw-bold text-uppercase mb-2 tracking-wide">Active Capacity</h6>
                    <h2 class="fw-black text-success mb-0"><?php echo $operational_capacity; ?></h2>
                </div>
            </div>
        </div>

        <div class="filter-bar mb-5" data-aos="fade-up" data-aos-delay="300">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control form-control-search" id="hubSearch" placeholder="Search by City Name or Hub ID...">
            </div>
        </div>

        <div class="row g-4" id="hubGrid">
            <?php if($total_hubs > 0): ?>
                <?php foreach($locations_data as $index => $loc): 
                    // Calculate Health Percentages
                    $total = max(1, $loc['total_cars']); // Prevent division by zero
                    $avail_pct = ($loc['avail_cars'] / $total) * 100;
                    $maint_pct = ($loc['maint_cars'] / $total) * 100;
                    
                    // Procedural mapping for visuals
                    $map_images = [
                        'https://images.unsplash.com/photo-1524661135-423995f22d0b?q=80&w=800',
                        'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?q=80&w=800',
                        'https://images.unsplash.com/photo-1449844908441-8829872d2607?q=80&w=800',
                        'https://images.unsplash.com/photo-1514565131-fce0801e5785?q=80&w=800'
                    ];
                    $bg = $map_images[$index % 4];
                ?>
                    <div class="col-xl-4 col-md-6 hub-item" data-name="<?php echo strtolower($loc['city_name']); ?>">
                        <div class="card hub-card h-100" data-aos="fade-up" data-aos-delay="<?php echo ($index % 3) * 100; ?>">
                            <div class="hub-map-header">
                                <img src="<?php echo $bg; ?>" alt="Map">
                                <h4 class="hub-title"><i class="fas fa-map-marker-alt text-danger me-2"></i><?php echo htmlspecialchars($loc['city_name']); ?></h4>
                            </div>
                            
                            <div class="card-body p-4 d-flex flex-column">
                                
                                <div class="mb-4 d-flex align-items-start">
                                    <div class="bg-light p-2 rounded text-muted me-3 border"><i class="fas fa-building"></i></div>
                                    <div>
                                        <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 1px;">Official Address</small>
                                        <p class="mb-0 fw-bold text-dark small">SmartDrive X Terminal, CBD Sector 4, <?php echo htmlspecialchars($loc['city_name']); ?>, IN</p>
                                    </div>
                                </div>

                                <div class="bg-light p-3 rounded-4 border mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted fw-bold text-uppercase tracking-wide">Fleet Health</small>
                                        <h5 class="fw-black mb-0" style="color: var(--teal-dark);"><?php echo $loc['total_cars']; ?> <span class="fs-6 text-muted fw-bold">Total</span></h5>
                                    </div>
                                    
                                    <div class="health-bar mb-2 shadow-sm">
                                        <div class="health-avail" style="width: <?php echo $avail_pct; ?>%" title="Available"></div>
                                        <div class="health-maint" style="width: <?php echo $maint_pct; ?>%" title="Maintenance"></div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between small fw-bold">
                                        <span class="text-success"><i class="fas fa-check-circle me-1"></i> <?php echo $loc['avail_cars']; ?> Ready</span>
                                        <span class="text-danger"><i class="fas fa-wrench me-1"></i> <?php echo $loc['maint_cars']; ?> Maint.</span>
                                    </div>
                                </div>
                                
                                <div class="mt-auto d-flex gap-2">
                                    <a href="<?php echo $base_url; ?>admin/manage_cars.php?location=<?php echo $loc['id']; ?>" class="btn rounded-pill fw-bold px-3 py-2 flex-grow-1" style="background: var(--teal-light); color: var(--teal-dark);">
                                        <i class="fas fa-car me-1"></i> View Inventory
                                    </a>
                                    <a href="<?php echo $base_url; ?>admin/manage_locations.php?delete=<?php echo $loc['id']; ?>" class="btn btn-outline-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; border-width: 2px;" onclick="return confirm('WARNING: Are you sure you want to permanently shut down this hub?');" title="Delete Hub">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5 bg-white rounded-4 border shadow-sm">
                        <i class="fas fa-globe-asia fa-4x mb-3 text-muted opacity-25 d-block"></i>
                        <h4 class="fw-bold text-dark">No Operational Hubs</h4>
                        <p class="text-muted mb-4">Deploy your first city location to start assigning vehicles to it.</p>
                        <button type="button" class="btn btn-teal rounded-pill fw-bold px-4 py-2" data-bs-toggle="modal" data-bs-target="#addLocationModal">Deploy First Hub</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addLocationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h5 class="modal-title fw-bold"><i class="fas fa-map-pin me-2 text-white"></i> Deploy City Hub</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage_locations.php" method="POST">
                <div class="modal-body p-4 p-md-5 bg-white">
                    <div class="text-center mb-4">
                        <div class="bg-light d-inline-block p-4 rounded-circle shadow-sm border mb-3">
                            <i class="fas fa-building fa-3x" style="color: var(--teal-primary);"></i>
                        </div>
                        <p class="text-muted small px-3 fw-bold">Adding a new city instantly generates a virtual terminal address and allows you to assign vehicles to this region.</p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Official City Name</label>
                        <div class="input-group shadow-sm rounded-3 overflow-hidden">
                            <span class="input-group-text bg-white border-0 text-muted"><i class="fas fa-city"></i></span>
                            <input type="text" name="city_name" class="form-control form-control-custom border-0 ps-0" placeholder="e.g., Mumbai, Bangalore" required autofocus>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 pt-0 pb-4 pe-4">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_location" class="btn rounded-pill px-4 fw-bold shadow-sm text-white" style="background: var(--teal-primary);"><i class="fas fa-rocket me-2"></i> Deploy Hub</button>
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

    // 3. LIVE SEARCH JAVASCRIPT ENGINE
    const searchInput = document.getElementById('hubSearch');
    const hubItems = document.querySelectorAll('.hub-item');

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            
            hubItems.forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(query)) {
                    item.style.display = 'block';
                    setTimeout(() => { item.style.opacity = '1'; item.style.transform = 'scale(1)'; }, 50);
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.9)';
                    setTimeout(() => { item.style.display = 'none'; }, 300);
                }
            });
        });
    }
</script>

<?php include '../includes/footer.php'; ?>