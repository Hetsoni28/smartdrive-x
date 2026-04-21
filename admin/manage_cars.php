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

// Check session for PRG toast messages
if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

function setAdminMsg($msg, $type="success") {
    $_SESSION['admin_msg'] = $msg;
    $_SESSION['admin_msg_type'] = $type;
}

// ==========================================
// ⚙️ QUICK ACTIONS (Delete & Status Toggle)
// ==========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action_id = intval($_GET['id']);
    
    if ($_GET['action'] == 'delete') {
        // Pre-check for active bookings
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE car_id = ? AND booking_status IN ('pending', 'confirmed')");
        $check_stmt->bind_param("i", $action_id);
        $check_stmt->execute();
        $check_stmt->bind_result($bk_count);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($bk_count > 0) {
            setAdminMsg("Cannot delete this car. It has active or pending bookings.", "danger");
        } else {
            // Delete physical image files to save server space
            $img_stmt = $conn->prepare("SELECT image FROM cars WHERE id = ?");
            $img_stmt->bind_param("i", $action_id);
            $img_stmt->execute();
            $img_stmt->bind_result($img_data);
            
            if($img_stmt->fetch()) {
                $images = json_decode($img_data, true);
                if (is_array($images)) {
                    foreach ($images as $img) { if (file_exists('../' . $img)) @unlink('../' . $img); }
                } elseif (!empty($img_data) && file_exists('../' . $img_data)) {
                    @unlink('../' . $img_data);
                }
            }
            $img_stmt->close();
            
            // Delete car record
            $del_stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
            $del_stmt->bind_param("i", $action_id);
            if ($del_stmt->execute()) {
                setAdminMsg("Vehicle permanently removed from the fleet.", "secondary");
            }
            $del_stmt->close();
        }
    } 
    elseif ($_GET['action'] == 'toggle') {
        $current_status = mysqli_real_escape_string($conn, $_GET['current']);
        $new_status = ($current_status == 'available') ? 'maintenance' : 'available';
        
        $toggle_stmt = $conn->prepare("UPDATE cars SET status = ? WHERE id = ?");
        $toggle_stmt->bind_param("si", $new_status, $action_id);
        $toggle_stmt->execute();
        $toggle_stmt->close();
        
        setAdminMsg("Vehicle status updated to " . ucfirst($new_status) . ".", "success");
    }
    header("Location: manage_cars.php");
    exit();
}

// ==========================================
// 🛠️ HELPER: MULTI-IMAGE UPLOAD ENGINE
// ==========================================
function processImageUploads($files_array) {
    $uploaded_paths = [];
    $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
    $upload_dir = '../uploads/cars/';
    
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $file_count = count($files_array['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if ($files_array['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $files_array['name'][$i];
            $file_size = $files_array['size'][$i];
            $file_tmp = $files_array['tmp_name'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_types) && $file_size <= 5242880) { // Max 5MB per image
                $new_file_name = uniqid('fleet_') . '_' . $i . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                if (move_uploaded_file($file_tmp, $destination)) {
                    $uploaded_paths[] = 'uploads/cars/' . $new_file_name;
                }
            }
        }
    }
    return $uploaded_paths;
}

// ==========================================
// 📸 ADD NEW VEHICLE
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_car'])) {
    $brand = trim($_POST['brand']);
    $name = trim($_POST['name']);
    $model = trim($_POST['model']);
    $fuel_type = trim($_POST['fuel_type']);
    $transmission = trim($_POST['transmission']);
    $base_price = floatval($_POST['base_price']);
    $location_id = intval($_POST['location_id']);
    
    $new_images = [];
    if (isset($_FILES['car_images'])) {
        $new_images = processImageUploads($_FILES['car_images']);
    }
    $db_image_path = !empty($new_images) ? json_encode($new_images) : NULL;

    $insert_stmt = $conn->prepare("INSERT INTO cars (brand, name, model, fuel_type, transmission, base_price, location_id, status, image) VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?)");
    $insert_stmt->bind_param("sssssdis", $brand, $name, $model, $fuel_type, $transmission, $base_price, $location_id, $db_image_path);
    
    if ($insert_stmt->execute()) {
        setAdminMsg("New vehicle successfully added with " . count($new_images) . " images!", "success");
    } else {
        setAdminMsg("Database Error: Failed to add vehicle.", "danger");
    }
    $insert_stmt->close();
    header("Location: manage_cars.php");
    exit();
}

// ==========================================
// 🛠️ EDIT VEHICLE (Modify Details & Gallery)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_car'])) {
    $edit_id = intval($_POST['car_id']);
    $brand = trim($_POST['brand']);
    $name = trim($_POST['name']);
    $model = trim($_POST['model']);
    $fuel_type = trim($_POST['fuel_type']);
    $transmission = trim($_POST['transmission']);
    $base_price = floatval($_POST['base_price']);
    $location_id = intval($_POST['location_id']);

    // 1. Fetch existing images
    $get_img_stmt = $conn->prepare("SELECT image FROM cars WHERE id = ?");
    $get_img_stmt->bind_param("i", $edit_id);
    $get_img_stmt->execute();
    $get_img_stmt->bind_result($existing_img_data);
    $get_img_stmt->fetch();
    $get_img_stmt->close();

    $existing_images = [];
    if (!empty($existing_img_data)) {
        $decoded = json_decode($existing_img_data, true);
        if (is_array($decoded)) { $existing_images = $decoded; } 
        else { $existing_images[] = $existing_img_data; }
    }

    // Process removals explicitly selected by admin
    if (isset($_POST['remove_images']) && is_array($_POST['remove_images'])) {
        foreach ($_POST['remove_images'] as $img_to_remove) {
            $key = array_search($img_to_remove, $existing_images);
            if ($key !== false) {
                unset($existing_images[$key]);
                if (file_exists('../' . $img_to_remove)) {
                    unlink('../' . $img_to_remove);
                }
            }
        }
        $existing_images = array_values($existing_images); // reindex
    }

    // 2. Process new uploads and append
    if (isset($_FILES['new_car_images']) && !empty($_FILES['new_car_images']['name'][0])) {
        $new_uploaded = processImageUploads($_FILES['new_car_images']);
        $existing_images = array_merge($existing_images, $new_uploaded);
    }
    
    $final_image_json = !empty($existing_images) ? json_encode($existing_images) : NULL;

    $update_stmt = $conn->prepare("UPDATE cars SET brand=?, name=?, model=?, fuel_type=?, transmission=?, base_price=?, location_id=?, image=? WHERE id=?");
    $update_stmt->bind_param("sssssdisi", $brand, $name, $model, $fuel_type, $transmission, $base_price, $location_id, $final_image_json, $edit_id);
    
    if ($update_stmt->execute()) {
        setAdminMsg("Vehicle details & gallery successfully updated!", "success");
    } else {
        setAdminMsg("Database Error: Failed to update vehicle.", "danger");
    }
    $update_stmt->close();
    header("Location: manage_cars.php");
    exit();
}

// ==========================================
// 🔍 SEARCH, FILTER & PAGINATION
// ==========================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$loc_filter = isset($_GET['location']) ? intval($_GET['location']) : 0;

$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_sql = "WHERE 1=1 ";
if (!empty($search)) $where_sql .= " AND (c.brand LIKE '%$search%' OR c.name LIKE '%$search%' OR c.model LIKE '%$search%') ";
if ($status_filter != 'all') $where_sql .= " AND c.status = '$status_filter' ";
if ($loc_filter > 0) $where_sql .= " AND c.location_id = $loc_filter ";

// Stats
$total_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars"))['c'];
$avail_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars WHERE status = 'available'"))['c'];
$maint_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars WHERE status = 'maintenance'"))['c'];

// Pagination Count
$total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM cars c $where_sql"))['total'];
$total_pages = ceil($total_records / $limit);

// Fetch Table Data
$cars_query = mysqli_query($conn, "SELECT c.*, l.city_name FROM cars c LEFT JOIN locations l ON c.location_id = l.id $where_sql ORDER BY c.id DESC LIMIT $limit OFFSET $offset");

// Fetch Locations for Modals & Filters
$locations_query = mysqli_query($conn, "SELECT * FROM locations ORDER BY city_name ASC");
$locations_array = [];
while($loc = mysqli_fetch_assoc($locations_query)) { $locations_array[] = $loc; }

// Smart RAW image parser
function parseCarImagesRaw($db_image_data) {
    if (empty($db_image_data)) return [];
    $decoded = json_decode($db_image_data, true);
    if (is_array($decoded)) return $decoded;
    return [$db_image_data];
}

$page_title = "Fleet Inventory | Admin";
include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

<style>
    :root { 
        --teal-primary: #4da89c; 
        --teal-dark: #1a2624; 
        --mint-pale: #ccecd4; 
        --gray-bg: #f4f7f6;
    }
    
    body { background-color: var(--gray-bg); }
    .admin-header-card { background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal-primary) 100%); color: white; border-radius: 20px; padding: 2.5rem; }

    /* Filter Bar */
    .filter-bar { background: white; border-radius: 20px; padding: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.02); }
    .form-control-search { background-color: #f8f9fa; border: none; border-radius: 12px; padding: 12px 20px; font-weight: 600; color: var(--teal-dark); transition: all 0.3s ease; }
    .form-control-search:focus { outline: none; box-shadow: 0 0 0 4px rgba(77, 168, 156, 0.15); background-color: white; }

    /* Enterprise Table */
    .table-container { background: white; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.03); overflow: hidden; }
    .table-beast th { background: #f8f9fa; color: #6c757d; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 20px; font-weight: 800; white-space: nowrap; }
    .table-beast td { padding: 20px; vertical-align: middle; border-bottom: 1px solid #f8f9fa; }
    .table-hover tbody tr:hover { background-color: rgba(77, 168, 156, 0.03); }
    
    /* Swiper Thumbnail Gallery */
    .thumb-swiper { width: 120px; height: 75px; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: 2px solid white; flex-shrink: 0; }
    .thumb-swiper img { width: 100%; height: 100%; object-fit: cover; }
    .swiper-pagination-bullet { background: white !important; opacity: 0.8; width: 6px; height: 6px; }
    
    /* Drag & Drop Multi-Uploader */
    .upload-zone { border: 2px dashed #dee2e6; border-radius: 15px; padding: 30px; text-align: center; background: #f8f9fa; cursor: pointer; position: relative; transition: all 0.3s; }
    .upload-zone:hover, .upload-zone.dragover { border-color: var(--teal-primary); background: rgba(77, 168, 156, 0.05); }
    .hidden-file-input { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 3; }
    
    .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; margin-top: 15px; z-index: 4; position: relative; }
    .preview-thumb { width: 100%; height: 80px; object-fit: cover; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border: 2px solid white; }

    /* Modals */
    .modal-content { border-radius: 20px; border: none; }
    .modal-header { background-color: var(--teal-dark); color: white; border: none; padding: 1.5rem; }
    .form-control-custom { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px; padding: 12px; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; }
    .form-control-custom:focus { border-color: var(--teal-primary); box-shadow: 0 0 0 0.25rem rgba(77, 168, 156, 0.25); background: white; }

    /* Skeleton Loader */
    .skeleton-row td { position: relative; overflow: hidden; background: #fff; }
    .skeleton-block { height: 20px; background: #e9ecef; border-radius: 6px; position: relative; overflow: hidden; width: 100%; }
    .skeleton-block.w-50 { width: 50%; }
    .skeleton-block.w-75 { width: 75%; }
    .skeleton-block::after { content: ""; position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent); animation: skeleton-shimmer 1.5s infinite; }
    @keyframes skeleton-shimmer { 100% { left: 100%; } }

    /* Buttons */
    .btn-teal { background-color: var(--teal-primary); color: white; transition: all 0.3s ease; }
    .btn-teal:hover { background-color: #3e8e83; color: white; transform: translateY(-1px); box-shadow: 0 8px 15px rgba(77, 168, 156, 0.3) !important; }

    /* Custom Checkbox for image removal */
    .img-remove-check { cursor: pointer; width: 22px; height: 22px; margin: 0; accent-color: #dc3545; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); border-radius: 4px; pointer-events: auto; }
    
    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--teal-dark); font-weight: bold; border-radius: 10px; margin: 0 4px; padding: 10px 18px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--teal-primary); color: white; box-shadow: 0 5px 15px rgba(77, 168, 156, 0.4); }

    /* Modern Toasts */
    #admin-toast-container { position: fixed; top: 20px; right: 20px; z-index: 1060; }
    .beast-toast { background: white; border-radius: 12px; padding: 16px 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-left: 6px solid var(--teal-primary); display: flex; align-items: center; gap: 15px; min-width: 300px; transform: translateX(120%); opacity: 0; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); margin-bottom: 10px; }
    .beast-toast.show { transform: translateX(0); opacity: 1; }
    .beast-toast.success { border-color: #198754; }
    .beast-toast.danger { border-color: #dc3545; }
    .beast-toast.warning { border-color: #ffc107; }
</style>

<div id="admin-toast-container"></div>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="dashboard-content">
        <div class="admin-header-card d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 shadow-sm" data-aos="fade-in">
            <div class="mb-3 mb-md-0">
                <h2 class="fw-bold mb-1"><i class="fas fa-car-side me-2 opacity-75"></i>Fleet Inventory Manager</h2>
                <p class="mb-0 opacity-75">Upload multi-image galleries, manage pricing, and control vehicle routing.</p>
            </div>
            <button class="btn btn-light text-dark rounded-pill fw-bold px-4 py-3 shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#addCarModal">
                <i class="fas fa-plus me-2" style="color: var(--teal-primary);"></i> Add Vehicle
            </button>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
                    <h6 class="text-muted fw-bold text-uppercase mb-2">Total Fleet</h6>
                    <h2 class="fw-bold text-dark mb-0"><?php echo $total_cars; ?></h2>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center border-bottom border-4 border-success">
                    <h6 class="text-muted fw-bold text-uppercase mb-2">Available for Rent</h6>
                    <h2 class="fw-bold text-success mb-0"><?php echo $avail_cars; ?></h2>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center border-bottom border-4 border-danger">
                    <h6 class="text-muted fw-bold text-uppercase mb-2">In Maintenance</h6>
                    <h2 class="fw-bold text-danger mb-0"><?php echo $maint_cars; ?></h2>
                </div>
            </div>
        </div>

        <div class="filter-bar mb-4" data-aos="fade-up" data-aos-delay="300">
            <form action="manage_cars.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 text-muted ps-3"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control form-control-search" name="search" placeholder="Search Brand or Model..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-control-search" name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="available" <?php echo $status_filter == 'available' ? 'selected' : ''; ?>>✅ Available</option>
                        <option value="maintenance" <?php echo $status_filter == 'maintenance' ? 'selected' : ''; ?>>🔧 Maintenance</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-control-search" name="location">
                        <option value="0">All Hubs</option>
                        <?php foreach($locations_array as $loc): ?>
                            <option value="<?php echo $loc['id']; ?>" <?php echo $loc_filter == $loc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc['city_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-teal fw-bold rounded-3 shadow-sm py-2">Filter</button>
                </div>
            </form>
        </div>

        <div class="table-container mb-4" data-aos="fade-up" data-aos-delay="400">
            <div class="table-responsive">
                <table class="table table-hover table-beast mb-0 text-nowrap">
                    <thead>
                        <tr>
                            <th>Gallery & Details</th>
                            <th>Category</th>
                            <th>Hub Location</th>
                            <th>Daily Rate</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="skeletonTableBody">
                        <?php for($i=0; $i<4; $i++): ?>
                        <tr class="skeleton-row">
                            <td>
                                <div class="d-flex">
                                    <div class="thumb-swiper me-3" style="background:#e9ecef"></div>
                                    <div class="d-flex flex-column justify-content-center w-100">
                                        <div class="skeleton-block w-50 mb-2"></div>
                                        <div class="skeleton-block w-25"></div>
                                    </div>
                                </div>
                            </td>
                            <td><div class="skeleton-block"></div></td>
                            <td><div class="skeleton-block w-75"></div></td>
                            <td><div class="skeleton-block w-50"></div></td>
                            <td><div class="skeleton-block mx-auto w-50"></div></td>
                            <td class="text-end pe-4"><div class="skeleton-block d-inline-block rounded-circle" style="width:35px; height:35px"></div></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                    <tbody id="realTableBody" style="display:none;">
                        <?php if(mysqli_num_rows($cars_query) > 0): ?>
                            <?php while($car = mysqli_fetch_assoc($cars_query)): 
                                $raw_images = parseCarImagesRaw($car['image']);
                                $fallback_img = 'https://images.unsplash.com/photo-1494976388531-d1058494cdd8?q=80&w=400';
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="swiper thumb-swiper me-3">
                                                <div class="swiper-wrapper">
                                                    <?php 
                                                    if(empty($raw_images)){
                                                        echo '<div class="swiper-slide"><img src="'.$fallback_img.'" alt="Fallback"></div>';
                                                    } else {
                                                        foreach($raw_images as $img): 
                                                            $valid_src = (file_exists('../'.$img)) ? $base_url.$img : $fallback_img;
                                                    ?>
                                                            <div class="swiper-slide"><img src="<?php echo $valid_src; ?>" alt="Car" loading="lazy"></div>
                                                    <?php 
                                                        endforeach; 
                                                    }
                                                    ?>
                                                </div>
                                                <?php if(count($raw_images) > 1): ?><div class="swiper-pagination"></div><?php endif; ?>
                                            </div>
                                            
                                            <div>
                                                <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($car['brand']); ?></h6>
                                                <small class="text-muted fw-bold"><?php echo htmlspecialchars($car['name']); ?> 
                                                    <?php if(count($raw_images) > 1) echo "<span class='badge bg-light text-dark ms-1 border'><i class='fas fa-images'></i> ".count($raw_images)."</span>"; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-muted fw-bold"><?php echo htmlspecialchars($car['model']); ?></td>
                                    <td class="fw-bold text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($car['city_name'] ?? 'Unassigned'); ?></td>
                                    <td class="fw-black fs-6" style="color: var(--teal-primary);">₹<?php echo number_format($car['base_price'], 0); ?></td>
                                    
                                    <td class="text-center">
                                        <?php if($car['status'] == 'available'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3 py-2">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3 py-2">Maintenance</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm border rounded-circle shadow-sm dropdown-toggle-no-caret" data-bs-toggle="dropdown" style="width: 35px; height: 35px;">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 overflow-hidden p-0">
                                                <li>
                                                    <a class="dropdown-item py-3 fw-bold border-bottom" href="#"
                                                        onclick="openEditModal(<?php echo $car['id']; ?>, <?php
                                                            $imgData = array_map(function($img) use ($base_url, $fallback_img) {
                                                                return ['path' => $img, 'src' => (file_exists('../'.$img)) ? $base_url.$img : $fallback_img];
                                                            }, $raw_images);
                                                            echo htmlspecialchars(json_encode([
                                                                'brand'        => $car['brand'],
                                                                'name'         => $car['name'],
                                                                'model'        => $car['model'],
                                                                'fuel_type'    => $car['fuel_type'] ?? '',
                                                                'transmission' => $car['transmission'] ?? '',
                                                                'base_price'   => $car['base_price'],
                                                                'location_id'  => $car['location_id'],
                                                                'images'       => $imgData
                                                            ]), ENT_QUOTES);
                                                        ?>); return false;">
                                                        <i class="fas fa-edit text-primary me-2"></i> Edit Details &amp; Gallery
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item py-3 fw-bold border-bottom" href="<?php echo $base_url; ?>admin/manage_cars.php?action=toggle&id=<?php echo $car['id']; ?>&current=<?php echo $car['status']; ?>">
                                                        <?php echo $car['status'] == 'available' ? '<i class="fas fa-wrench text-danger me-2"></i> Send to Maintenance' : '<i class="fas fa-check-circle text-success me-2"></i> Mark Available'; ?>
                                                    </a>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item py-3 text-danger fw-bold" onclick="confirmDeletion(<?php echo $car['id']; ?>)">
                                                        <i class="fas fa-trash-alt me-2"></i> Delete Vehicle
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="opacity-25 mb-3"><i class="fas fa-search-minus fa-4x text-muted"></i></div>
                                    <h5 class="fw-bold text-dark">No Vehicles Found</h5>
                                    <p class="text-muted small fw-bold">Try adjusting your filters or add a new car.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation" data-aos="fade-up">
                <ul class="pagination pagination-custom justify-content-center flex-wrap">
                    <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php if($page <= 1){ echo '#'; } else { echo "?page=".($page - 1)."&search=$search&status=$status_filter&location=$loc_filter"; } ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++ ): ?>
                        <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                            <a class="page-link" href="manage_cars.php?page=<?php echo $i; ?>&search=<?php echo $search; ?>&status=<?php echo $status_filter; ?>&location=<?php echo $loc_filter; ?>"> <?php echo $i; ?> </a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php if($page >= $total_pages){ echo 'disabled'; } ?>">
                        <a class="page-link" href="<?php if($page >= $total_pages){ echo '#'; } else { echo "?page=".($page + 1)."&search=$search&status=$status_filter&location=$loc_filter"; } ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addCarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle text-white me-2"></i> Register New Fleet Vehicle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4 p-md-5 bg-light">
                <form action="manage_cars.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate onsubmit="showSpinner(this)">
                    <div class="row g-4 mb-4">
                        <div class="col-lg-5">
                            <label class="form-label fw-bold text-muted small text-uppercase">Vehicle Gallery <span class="text-danger">*</span></label>
                            <div class="upload-zone" id="mainDropZone">
                                <input type="file" name="car_images[]" id="mainFileInput" class="hidden-file-input" accept="image/*" multiple required>
                                <div class="upload-content" id="mainUploadContent">
                                    <i class="fas fa-cloud-upload-alt fa-3x mb-3" style="color: var(--teal-primary);"></i>
                                    <h6 class="fw-bold text-dark mb-1">Drag & Drop Images</h6>
                                    <p class="small text-muted mb-0">Or click to browse (Max 5MB each)</p>
                                </div>
                                <div class="preview-grid" id="mainPreviewGrid"></div>
                            </div>
                            <div class="invalid-feedback d-block mt-2" id="gallery-error" style="display:none !important;">Please select at least one image.</div>
                        </div>

                        <div class="col-lg-7">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted small text-uppercase">Brand Make <span class="text-danger">*</span></label>
                                    <input type="text" name="brand" class="form-control form-control-custom" placeholder="e.g., Mercedes-Benz" required>
                                    <div class="invalid-feedback">Brand make is required.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted small text-uppercase">Vehicle Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control form-control-custom" placeholder="e.g., S-Class 350d" required>
                                    <div class="invalid-feedback">Vehicle name is required.</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted small text-uppercase">Model Category <span class="text-danger">*</span></label>
                                    <input type="text" name="model" class="form-control form-control-custom" placeholder="e.g., Luxury Sedan" required>
                                    <div class="invalid-feedback">Category is required.</div>
                                </div>
                                <div class="col-12">
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="form-label fw-bold text-muted small text-uppercase">Fuel Type</label>
                                            <input type="text" name="fuel_type" class="form-control form-control-custom" placeholder="e.g., Petrol">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fw-bold text-muted small text-uppercase">Transmission</label>
                                            <input type="text" name="transmission" class="form-control form-control-custom" placeholder="e.g., Automatic">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Daily Rental Rate (₹) <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text bg-white border-primary text-muted">₹</span>
                                <input type="number" name="base_price" class="form-control form-control-custom border-start-0 m-0" style="border-radius:0 10px 10px 0;" placeholder="5000" min="500" step="0.01" required>
                                <div class="invalid-feedback">Please provide a valid rate (min 500).</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Assign to City Hub <span class="text-danger">*</span></label>
                            <select name="location_id" class="form-select form-control-custom shadow-sm" required>
                                <option value="">Select Operational Hub...</option>
                                <?php foreach($locations_array as $loc): ?>
                                    <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['city_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a hub.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-4">
                        <button type="button" class="btn btn-light border rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_car" class="btn btn-teal rounded-pill px-5 fw-bold shadow-sm submit-btn">
                            <i class="fas fa-upload me-2 btn-icon"></i><span class="btn-text">Register Vehicle</span>
                            <span class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- 🛠️  SHARED EDIT MODAL (Single Instance)   -->
<!-- ========================================== -->
<div class="modal fade" id="editCarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editModalTitle"><i class="fas fa-edit me-2"></i> Edit Vehicle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form action="manage_cars.php" method="POST" enctype="multipart/form-data" id="editCarForm">
                    <input type="hidden" name="edit_car" value="1">
                    <input type="hidden" name="car_id" id="edit_car_id">

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Brand</label>
                            <input type="text" name="brand" id="edit_brand" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Name / Model Variant</label>
                            <input type="text" name="name" id="edit_name" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Category</label>
                            <input type="text" name="model" id="edit_model" class="form-control form-control-custom" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Fuel Type</label>
                            <input type="text" name="fuel_type" id="edit_fuel_type" class="form-control form-control-custom" placeholder="e.g., Petrol">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-muted small text-uppercase">Transmission</label>
                            <input type="text" name="transmission" id="edit_transmission" class="form-control form-control-custom" placeholder="e.g., Automatic">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Rate (₹)</label>
                            <input type="number" name="base_price" id="edit_base_price" class="form-control form-control-custom" step="0.01" min="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Hub</label>
                            <select name="location_id" id="edit_location_id" class="form-select form-control-custom" required>
                                <?php foreach($locations_array as $loc): ?>
                                    <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['city_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="border-top pt-4 mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase mb-3 d-block">1. Manage Existing Gallery</label>
                        <div class="d-flex flex-wrap gap-3 mb-2" id="existingImagesContainer"></div>
                        <p class="text-danger small mb-0 fw-bold" id="editRemoveImgHint" style="display:none;"><i class="fas fa-trash-alt me-1"></i> Check the red box on any image above to permanently delete it.</p>
                    </div>

                    <div class="border-top pt-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">2. Append New Images</label>
                        <div class="upload-zone p-4 rounded-4" id="editDropZone">
                            <input type="file" name="new_car_images[]" id="editFileInput" class="hidden-file-input" accept="image/*" multiple>
                            <div class="upload-content text-center py-3" id="editUploadContent">
                                <i class="fas fa-plus-circle fs-1 mb-2 opacity-75" style="color: var(--teal-primary);"></i>
                                <p class="small text-dark mb-0 fw-bold">Click to browse or Drag &amp; Drop</p>
                            </div>
                            <div class="preview-grid" id="editPreviewGrid"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-3">
                        <button type="button" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-teal rounded-pill px-5 fw-bold shadow-sm" id="editSubmitBtn">
                            <span id="editBtnText">Save All Changes</span>
                            <span class="spinner-border spinner-border-sm d-none ms-2" id="editSpinner" role="status"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script>
    // Skeleton Simulator
    document.addEventListener("DOMContentLoaded", function() {
        const skeleton = document.getElementById('skeletonTableBody');
        const real = document.getElementById('realTableBody');
        if (skeleton && real) {
            setTimeout(() => {
                skeleton.style.display = 'none';
                real.style.display = 'table-row-group';
                
                // Initialize swipers ONLY after table is visible
                var swipers = new Swiper(".thumb-swiper", {
                    effect: "fade",
                    autoplay: { delay: 2500, disableOnInteraction: false },
                    pagination: { el: ".swiper-pagination", dynamicBullets: true }
                });
            }, 500); // 500ms artificial delay for BEAST MODE skeleton effect
        }
    });

    // Toast Engine
    <?php if($message): ?>
        document.addEventListener("DOMContentLoaded", function() {
            const toastContainer = document.getElementById('admin-toast-container');
            const type = '<?php echo $msg_type; ?>';
            const msg = '<?php echo addslashes($message); ?>';
            
            let icon = 'fa-info-circle text-primary';
            if(type === 'success') icon = 'fa-check-circle text-success';
            if(type === 'warning') icon = 'fa-exclamation-triangle text-warning';
            if(type === 'danger') icon = 'fa-times-circle text-danger';

            const toast = document.createElement('div');
            toast.className = `beast-toast ${type}`;
            toast.innerHTML = `
                <i class="fas ${icon} fs-3"></i>
                <div>
                    <h6 class="fw-bold mb-1 text-dark">System Update</h6>
                    <small class="text-muted fw-bold d-block">${msg}</small>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => { 
                toast.classList.remove('show'); 
                setTimeout(() => toast.remove(), 400); 
            }, 6000);
        });
    <?php endif; ?>

    // ============================================
    // Bootstrap Validation — Add Form ONLY
    // ============================================
    (function () {
        'use strict';
        var addForm = document.querySelector('#addCarModal .needs-validation');
        if (addForm) {
            addForm.addEventListener('submit', function (event) {
                if (!addForm.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                addForm.classList.add('was-validated');
            }, false);
        }
    })();

    // ============================================
    // openEditModal — Populate shared modal via JS
    // ============================================
    function openEditModal(carId, carDataJSON) {
        var carData = (typeof carDataJSON === 'string') ? JSON.parse(carDataJSON) : carDataJSON;

        // Populate text fields
        document.getElementById('edit_car_id').value       = carId;
        document.getElementById('edit_brand').value        = carData.brand;
        document.getElementById('edit_name').value         = carData.name;
        document.getElementById('edit_model').value        = carData.model;
        document.getElementById('edit_fuel_type').value    = carData.fuel_type || '';
        document.getElementById('edit_transmission').value = carData.transmission || '';
        document.getElementById('edit_base_price').value   = carData.base_price;

        // Update modal title
        document.getElementById('editModalTitle').innerHTML =
            '<i class="fas fa-edit me-2"></i> Edit ' + carData.brand + ' ' + carData.name;

        // Set location dropdown
        var locSelect = document.getElementById('edit_location_id');
        for (var i = 0; i < locSelect.options.length; i++) {
            locSelect.options[i].selected = (String(locSelect.options[i].value) === String(carData.location_id));
        }

        // Build existing images section
        var container = document.getElementById('existingImagesContainer');
        var hint      = document.getElementById('editRemoveImgHint');
        container.innerHTML = '';

        // Reset file upload section
        document.getElementById('editPreviewGrid').innerHTML = '';
        document.getElementById('editUploadContent').style.display = 'block';
        document.getElementById('editFileInput').value = '';

        if (carData.images && carData.images.length > 0) {
            hint.style.display = 'block';
            carData.images.forEach(function(imgObj) {
                var div = document.createElement('div');
                div.className = 'position-relative d-inline-block';
                var safePath = imgObj.path ? imgObj.path.replace(/"/g, '&quot;') : '';
                var safeSrc  = imgObj.src  ? imgObj.src.replace(/"/g, '&quot;')  : '';
                div.innerHTML =
                    '<img src="' + safeSrc + '" class="rounded-3 border shadow-sm" style="width:80px;height:80px;object-fit:cover;">'
                  + '<label class="position-absolute shadow-sm bg-white rounded-circle d-flex align-items-center justify-content-center" style="top:-8px;right:-8px;width:28px;height:28px;cursor:pointer;border:1px solid #dee2e6;">'
                  + '<input type="checkbox" name="remove_images[]" value="' + safePath + '" style="width:16px;height:16px;accent-color:#dc3545;cursor:pointer;margin:0;padding:0;">'
                  + '</label>';
                container.appendChild(div);
            });
        } else {
            hint.style.display = 'none';
            container.innerHTML = '<small class="text-muted">No images uploaded.</small>';
        }

        // Show modal — compatible with all Bootstrap 5.x versions
        var modalEl = document.getElementById('editCarModal');
        var bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        bsModal.show();
    }

    // Edit form submit — show spinner
    document.getElementById('editCarForm').addEventListener('submit', function() {
        var btn     = document.getElementById('editSubmitBtn');
        var spinner = document.getElementById('editSpinner');
        var txt     = document.getElementById('editBtnText');
        if (spinner) spinner.classList.remove('d-none');
        if (txt) txt.textContent = 'Saving...';
        btn.disabled = true;
    });

    // ============================================
    // Multi Image Uploader — Add Modal
    // ============================================
    function setupMultiUploader(inputId, gridId, contentId, zoneId) {
        var input   = document.getElementById(inputId);
        var grid    = document.getElementById(gridId);
        var content = document.getElementById(contentId);
        var zone    = document.getElementById(zoneId);
        if (!input || !zone) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(ev) {
            zone.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); }, false);
        });
        ['dragenter', 'dragover'].forEach(function(ev) {
            zone.addEventListener(ev, function() { zone.classList.add('dragover'); }, false);
        });
        ['dragleave', 'drop'].forEach(function(ev) {
            zone.addEventListener(ev, function() { zone.classList.remove('dragover'); }, false);
        });

        zone.addEventListener('drop', function(e) {
            var files = e.dataTransfer.files;
            handleFiles(files, inputId);
        });

        input.addEventListener('change', function() {
            handleFiles(this.files, inputId);
        });

        function handleFiles(files, srcId) {
            if (grid) grid.innerHTML = '';
            if (files.length > 0 && content) content.style.display = 'none';
            else if (content) content.style.display = 'block';

            if (srcId === 'mainFileInput') {
                var err = document.getElementById('gallery-error');
                if (err) err.style.setProperty('display', files.length === 0 ? 'block' : 'none', 'important');
            }
            for (var i = 0; i < files.length; i++) {
                if (files[i].size > 5242880) { alert('File "' + files[i].name + '" is over 5MB.'); continue; }
                (function(file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-thumb';
                        if (grid) grid.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                })(files[i]);
            }
        }
    }

    // Init Add Modal uploader
    setupMultiUploader('mainFileInput', 'mainPreviewGrid', 'mainUploadContent', 'mainDropZone');

    // Init Edit Modal new-images uploader
    setupMultiUploader('editFileInput', 'editPreviewGrid', 'editUploadContent', 'editDropZone');

    // ============================================
    // Delete Confirmation
    // ============================================
    function confirmDeletion(carId) {
        if (confirm('DANGER! Are you sure you want to permanently delete this vehicle and all its images? This cannot be undone.')) {
            window.location.href = 'manage_cars.php?action=delete&id=' + carId;
        }
    }
</script>

<?php include '../includes/footer.php'; ?>