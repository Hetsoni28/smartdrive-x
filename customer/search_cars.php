<?php
session_start();

// 🛡️ SECURITY: Customer Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

// 🌐 BULLETPROOF PATHING
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . "/smartdrive_x/"; 

// ==========================================
// 🔍 SEARCH PARAMETERS & DEFAULTS
// ==========================================
// Default to tomorrow and the day after if no dates provided
$default_start = date('Y-m-d', strtotime('+1 day'));
$default_end   = date('Y-m-d', strtotime('+3 days'));

$start_date   = isset($_GET['start_date']) && !empty($_GET['start_date']) ? trim($_GET['start_date']) : $default_start;
$end_date     = isset($_GET['end_date']) && !empty($_GET['end_date']) ? trim($_GET['end_date']) : $default_end;
$location_id  = isset($_GET['location']) ? intval($_GET['location']) : 0;
$brand_filter = isset($_GET['brand']) ? trim($_GET['brand']) : 'all';
$max_price    = isset($_GET['max_price']) ? intval($_GET['max_price']) : 25000;
$sort_filter  = isset($_GET['sort']) ? trim($_GET['sort']) : 'price_asc';

// Auto-fix user date errors safely
if ($start_date && $end_date && $end_date < $start_date) {
    $end_date = $start_date; 
}

// Pagination setup
$limit = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// ==========================================
// 🧠 ENTERPRISE AVAILABILITY ENGINE (Prepared Statements)
// ==========================================
$base_sql = "
    FROM cars c
    LEFT JOIN locations l ON c.location_id = l.id
    WHERE c.status = 'available' 
    AND c.base_price <= ?
";

$types = "d"; 
$params = [$max_price];

// 1. Location Filter
if ($location_id > 0) {
    $base_sql .= " AND c.location_id = ?";
    $types .= "i";
    $params[] = $location_id;
}

// 2. Brand Filter
if ($brand_filter !== 'all' && !empty($brand_filter)) {
    $base_sql .= " AND c.brand = ?";
    $types .= "s";
    $params[] = $brand_filter;
}

// 3. The Date Collision Engine (CRITICAL)
if (!empty($start_date) && !empty($end_date)) {
    $base_sql .= " AND c.id NOT IN (
        SELECT car_id FROM bookings 
        WHERE booking_status IN ('confirmed', 'pending')
        AND (start_date <= ? AND end_date >= ?)
    )";
    $types .= "ss";
    $params[] = $end_date;
    $params[] = $start_date;
}

// -- Count Query for Pagination --
$count_query = "SELECT COUNT(c.id) as total " . $base_sql;
$count_stmt = $conn->prepare($count_query);
if ($types) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();
$total_pages = ceil($total_results / $limit);

// -- Data Query --
$order_map = [
    'price_asc'  => 'c.base_price ASC',
    'price_desc' => 'c.base_price DESC',
    'name_asc'   => 'c.brand ASC, c.name ASC',
    'newest'     => 'c.id DESC'
];
$order_by = $order_map[$sort_filter] ?? 'c.base_price ASC';

$data_query = "SELECT c.*, l.city_name " . $base_sql . " ORDER BY $order_by LIMIT ? OFFSET ?";
$data_types = $types . "ii";
$data_params = $params;
array_push($data_params, $limit, $offset);

$data_stmt = $conn->prepare($data_query);
if ($data_types) { $data_stmt->bind_param($data_types, ...$data_params); }
$data_stmt->execute();
$fleet_result = $data_stmt->get_result();

// Fetch Hubs & Brands for Sidebar
$locations_result = mysqli_query($conn, "SELECT id, city_name FROM locations ORDER BY city_name ASC");
$brands_result = mysqli_query($conn, "SELECT DISTINCT brand FROM cars WHERE status = 'available' ORDER BY brand ASC");

// ==========================================
// 📸 SMART IMAGE PARSER (Supports Multi-Image JSON)
// ==========================================
function parseCarImages($db_image_data, $base_url, $brand) {
    $images = [];
    if (!empty($db_image_data)) {
        $decoded = json_decode($db_image_data, true);
        if (is_array($decoded)) {
            foreach($decoded as $img) { if(file_exists('../'.$img)) $images[] = $base_url . $img; }
        } elseif (file_exists('../' . $db_image_data)) {
            $images[] = $base_url . $db_image_data; // Legacy fallback
        }
    }
    // Deep fallback to Unsplash
    if(empty($images)) {
        $fallbacks = [
            'porsche'     => 'https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=800',
            'bmw'         => 'https://images.unsplash.com/photo-1556189250-72ba954cfc2b?q=80&w=800',
            'audi'        => 'https://images.unsplash.com/photo-1606152421802-db97b9c7a11b?q=80&w=800',
            'range rover' => 'https://images.unsplash.com/photo-1606016159991-d8532e856086?q=80&w=800',
            'honda'       => 'https://images.unsplash.com/photo-1590362891991-f776e747a588?q=80&w=800',
            'mercedes'    => 'https://images.unsplash.com/photo-1610880846497-7257b23f6128?q=80&w=800',
            'toyota'      => 'https://images.unsplash.com/photo-1559416523-140ddc3d238c?q=80&w=800'
        ];
        $images[] = $fallbacks[strtolower(trim($brand))] ?? 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?q=80&w=800';
    }
    return $images;
}

$page_title = "Find Your Drive | SmartDrive X";
include '../includes/header.php';
?>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />

<style>
    /* 🌿 SAGE GREEN CUSTOMER THEME */
    :root {
        --sage-dark: #2b3327;
        --sage-mid: #889c7c;
        --sage-pale: #e0eadb;
        --sage-bg: #f4f5f3;
    }
    body { background-color: var(--sage-bg); }

    /* 🦴 Skeleton Loading Engine */
    .skeleton-box { position: relative; overflow: hidden; background-color: #e2e8f0; color: transparent !important; border-color: transparent !important; user-select: none; pointer-events: none; border-radius: 12px; }
    .skeleton-box * { visibility: hidden; }
    .skeleton-box::after { content: ""; position: absolute; top: 0; right: 0; bottom: 0; left: 0; transform: translateX(-100%); background-image: linear-gradient(90deg, rgba(255, 255, 255, 0) 0, rgba(255, 255, 255, 0.4) 20%, rgba(255, 255, 255, 0.6) 60%, rgba(255, 255, 255, 0)); animation: shimmer 1.5s infinite; }
    @keyframes shimmer { 100% { transform: translateX(100%); } }

    /* Hero Search Bar */
    .search-hero {
        background: linear-gradient(135deg, rgba(43,51,39,0.95) 0%, rgba(43,51,39,0.7) 100%), url('https://images.unsplash.com/photo-1493238792000-8113da705763?q=80&w=2070') center/cover;
        padding: 80px 0 100px 0; margin-top: -24px; color: white; position: relative;
    }
    
    .glass-search-bar {
        background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);
        border-radius: 50px; padding: 10px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    }
    .glass-search-bar .form-control, .glass-search-bar .form-select {
        border: none; background: transparent; font-weight: 700; color: var(--sage-dark); box-shadow: none; padding: 15px 20px;
    }
    .glass-search-bar .form-control:focus, .glass-search-bar .form-select:focus { outline: none; box-shadow: none; background: rgba(136, 156, 124, 0.05); border-radius: 50px; }

    /* Filter Sidebar */
    .filter-sidebar {
        background: white; border-radius: 24px; padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02);
        position: sticky; top: 100px;
    }

    .form-control-custom, .form-select-custom {
        background-color: #f8f9fa; border: 2px solid transparent; border-radius: 12px;
        padding: 14px 18px; font-weight: 600; color: var(--sage-dark); transition: all 0.3s;
    }
    .form-control-custom:focus, .form-select-custom:focus {
        border-color: var(--sage-mid); box-shadow: 0 0 0 4px rgba(136, 156, 124, 0.15); background-color: white; outline: none;
    }

    /* Price Slider Customization */
    .price-slider { -webkit-appearance: none; width: 100%; height: 8px; border-radius: 5px; background: var(--sage-pale); outline: none; }
    .price-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 24px; height: 24px; border-radius: 50%; background: var(--sage-dark); cursor: pointer; transition: transform 0.2s; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    .price-slider::-webkit-slider-thumb:hover { transform: scale(1.2); }

    /* Car Cards */
    .car-card {
        border: none; border-radius: 24px; overflow: hidden; background: white; display: flex; flex-direction: column; height: 100%;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.02);
    }
    .car-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(136, 156, 124, 0.15); border-color: var(--sage-mid); }
    
    .img-wrapper { position: relative; height: 260px; overflow: hidden; background: #e9ecef; }
    .thumb-swiper { width: 100%; height: 100%; }
    .thumb-swiper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.8s ease; }
    .car-card:hover .thumb-swiper img { transform: scale(1.05); }
    .swiper-pagination-bullet { background: white !important; opacity: 0.8; }

    .spec-icon { color: var(--sage-dark); background: var(--sage-pale); padding: 12px; border-radius: 12px; display: inline-block; width: 100%; text-align: center; transition: all 0.3s; }
    .car-card:hover .spec-icon { background: var(--sage-dark); color: white; }

    .btn-sage { background-color: var(--sage-dark); color: white; border: none; font-weight: bold; transition: all 0.3s; }
    .btn-sage:hover { background-color: #1e241c; color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(136, 156, 124, 0.3); }

    /* Pagination */
    .pagination-custom .page-link { border: none; color: var(--sage-dark); font-weight: bold; border-radius: 12px; margin: 0 5px; padding: 10px 20px; }
    .pagination-custom .page-item.active .page-link { background-color: var(--sage-dark); color: white; box-shadow: 0 5px 15px rgba(43, 51, 39, 0.3); }
</style>

<div class="search-hero shadow-sm">
    <div class="container position-relative z-2">
        <h1 class="display-4 fw-black mb-3 text-center load-anim skeleton-box" data-aos="fade-down">Select Your Drive</h1>
        <p class="lead text-center opacity-75 mb-5 load-anim skeleton-box" data-aos="fade-down" data-aos-delay="100">Our intelligent engine filters out booked vehicles to show you real-time availability.</p>
        
        <div class="glass-search-bar load-anim skeleton-box" style="max-width: 1000px; margin: 0 auto;" data-aos="fade-up" data-aos-delay="200">
            <form action="search_cars.php" method="GET" class="row g-0 align-items-center m-0">
                <div class="col-md-3">
                    <select name="location" class="form-select" required>
                        <option value="0">All Pick-up Hubs</option>
                        <?php mysqli_data_seek($locations_result, 0); while($loc = mysqli_fetch_assoc($locations_result)): ?>
                            <option value="<?php echo $loc['id']; ?>" <?php echo $location_id == $loc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc['city_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 border-start">
                    <input type="date" name="start_date" id="hero_start" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3 border-start">
                    <input type="date" name="end_date" id="hero_end" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3 p-1 d-grid">
                    <button type="submit" class="btn btn-sage rounded-pill py-3 fs-5"><i class="fas fa-search me-2"></i> Find Cars</button>
                </div>
                <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand_filter); ?>">
                <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_filter); ?>">
            </form>
        </div>
    </div>
</div>

<div class="container my-5 py-3">
    <div class="row g-5">
        
        <div class="col-lg-3">
            <div class="filter-sidebar load-anim skeleton-box" data-aos="fade-right">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-black text-dark mb-0"><i class="fas fa-sliders-h me-2 text-muted"></i> Filters</h5>
                    <?php if($brand_filter !== 'all' || $max_price !== 25000 || $location_id !== 0): ?>
                        <a href="search_cars.php" class="text-decoration-none small text-danger fw-bold"><i class="fas fa-times me-1"></i> Reset</a>
                    <?php endif; ?>
                </div>
                
                <form action="search_cars.php" method="GET" id="sidebarForm">
                    <input type="hidden" name="location" value="<?php echo $location_id; ?>">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase tracking-wide mb-3">Vehicle Brand</label>
                        <select name="brand" class="form-select form-select-custom shadow-sm" onchange="document.getElementById('sidebarForm').submit();">
                            <option value="all">All Brands</option>
                            <?php while($brand = mysqli_fetch_assoc($brands_result)): ?>
                                <option value="<?php echo htmlspecialchars($brand['brand']); ?>" <?php echo $brand_filter == $brand['brand'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand['brand']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold text-muted small text-uppercase tracking-wide mb-0">Max Daily Rate</label>
                            <span class="badge bg-dark rounded-pill shadow-sm py-2 px-3" id="priceLabel">₹<?php echo number_format($max_price, 0); ?></span>
                        </div>
                        <input type="range" name="max_price" class="price-slider mt-2" min="1000" max="25000" step="500" value="<?php echo $max_price; ?>" id="priceRange" 
                               oninput="document.getElementById('priceLabel').innerText = '₹' + parseInt(this.value).toLocaleString('en-IN');" 
                               onchange="document.getElementById('sidebarForm').submit();">
                        <div class="d-flex justify-content-between text-muted small fw-bold mt-3">
                            <span>₹1k</span>
                            <span>₹25k+</span>
                        </div>
                    </div>

                    <div class="mb-4 pt-4 border-top">
                        <label class="form-label fw-bold text-muted small text-uppercase tracking-wide mb-3">Sort Results</label>
                        <select name="sort" class="form-select form-select-custom shadow-sm" onchange="document.getElementById('sidebarForm').submit();">
                            <option value="price_asc" <?php echo $sort_filter == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo $sort_filter == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name_asc" <?php echo $sort_filter == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                            <option value="newest" <?php echo $sort_filter == 'newest' ? 'selected' : ''; ?>>Newest Additions</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 rounded-pill fw-bold py-3 shadow-sm d-lg-none mt-3">Apply Filters</button>
                </form>
            </div>
        </div>

        <div class="col-lg-9">
            
            <div class="d-flex justify-content-between align-items-center mb-4 load-anim skeleton-box">
                <h4 class="fw-black text-dark mb-0">Available Vehicles</h4>
                <span class="badge bg-white text-dark border px-4 py-2 fw-bold shadow-sm fs-6 rounded-pill"><?php echo $total_results; ?> Cars Found</span>
            </div>

            <div class="row g-4">
                <?php if($total_results > 0): ?>
                    <?php while($car = mysqli_fetch_assoc($fleet_result)): 
                        $car_images = parseCarImages($car['image'], $base_url, $car['brand']);
                    ?>
                        <div class="col-md-6 col-xl-6 load-anim skeleton-box" data-aos="fade-up">
                            <div class="car-card">
                                <div class="img-wrapper">
                                    <div class="swiper thumb-swiper">
                                        <div class="swiper-wrapper">
                                            <?php foreach($car_images as $img): ?>
                                                <div class="swiper-slide"><img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($car['brand']); ?>"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if(count($car_images) > 1): ?><div class="swiper-pagination"></div><?php endif; ?>
                                    </div>
                                    
                                    <div class="position-absolute top-0 start-0 m-3 z-3">
                                        <span class="badge bg-white text-dark rounded-pill px-3 py-2 shadow-sm fw-bold"><i class="fas fa-bolt text-warning me-1"></i> Instant Book</span>
                                    </div>
                                </div>
                                
                                <div class="card-body p-4 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <p class="text-muted small text-uppercase mb-1 fw-bold tracking-wide" style="color: var(--sage-mid) !important;"><?php echo htmlspecialchars($car['brand']); ?></p>
                                            <h4 class="card-title fw-black mb-0 text-dark"><?php echo htmlspecialchars($car['name']); ?></h4>
                                        </div>
                                        <span class="badge bg-light text-dark border px-3 py-2 shadow-sm rounded-pill"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($car['city_name'] ?? 'Any Hub'); ?></span>
                                    </div>
                                    
                                    <p class="text-muted small mb-4 pb-3 border-bottom fw-bold"><?php echo htmlspecialchars($car['model']); ?></p>

                                    <div class="row text-center mb-4 g-2">
                                        <div class="col-4"><div class="spec-icon shadow-sm"><i class="fas fa-cogs mb-2 fs-5"></i><br><small class="fw-bold">Auto</small></div></div>
                                        <div class="col-4"><div class="spec-icon shadow-sm"><i class="fas fa-users mb-2 fs-5"></i><br><small class="fw-bold">5 Seats</small></div></div>
                                        <div class="col-4"><div class="spec-icon shadow-sm"><i class="fas fa-gas-pump mb-2 fs-5"></i><br><small class="fw-bold">Petrol</small></div></div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-4">
                                        <div>
                                            <h3 class="fw-black mb-0" style="color: var(--sage-dark);">₹<?php echo number_format($car['base_price'], 0); ?></h3>
                                            <small class="text-muted fw-bold">per day (excl. tax)</small>
                                        </div>
                                        <a href="book_car.php?id=<?php echo $car['id']; ?>&start=<?php echo urlencode($start_date); ?>&end=<?php echo urlencode($end_date); ?>" 
                                           class="btn btn-sage rounded-pill px-4 py-3 shadow-sm fs-6">
                                           Select Vehicle <i class="fas fa-arrow-right ms-2"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12" data-aos="fade-up">
                        <div class="text-center py-5 bg-white rounded-4 border shadow-sm load-anim skeleton-box">
                            <i class="fas fa-car-crash fa-4x mb-4 text-muted opacity-25"></i>
                            <h3 class="fw-black text-dark mb-2">No Vehicles Available</h3>
                            <p class="text-muted fw-bold mb-4 px-5">We are fully booked for these exact dates, or no vehicles match your current filters. Try adjusting your search criteria.</p>
                            <a href="search_cars.php" class="btn btn-sage rounded-pill px-5 py-3 fw-bold shadow-sm">Clear Filters & Try Again</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-5" data-aos="fade-up">
                    <ul class="pagination pagination-custom justify-content-center">
                        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                            <a class="page-link shadow-sm border" href="<?php if($page > 1) echo "?page=".($page - 1)."&start_date=$start_date&end_date=$end_date&location=$location_id&brand=$brand_filter&max_price=$max_price&sort=$sort_filter"; else echo "#"; ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++ ): ?>
                            <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                                <a class="page-link shadow-sm border" href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&location=<?php echo $location_id; ?>&brand=<?php echo $brand_filter; ?>&max_price=<?php echo $max_price; ?>&sort=<?php echo $sort_filter; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                            <a class="page-link shadow-sm border" href="<?php if($page < $total_pages) echo "?page=".($page + 1)."&start_date=$start_date&end_date=$end_date&location=$location_id&brand=$brand_filter&max_price=$max_price&sort=$sort_filter"; else echo "#"; ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>

<script>
    // 1. SKELETON LOADER REMOVAL ENGINE
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(() => {
            document.querySelectorAll('.skeleton-box').forEach(el => {
                el.classList.remove('skeleton-box');
            });
        }, 300); // 300ms buffer prevents layout shifts while images load
    });

    // 2. INITIALIZE AOS
    if(typeof AOS !== 'undefined') {
        AOS.init({ once: true, offset: 50, duration: 800, easing: 'ease-out-cubic' });
    }

    // 3. DATE VALIDATION LOGIC (Prevent Time Travel)
    const heroStart = document.getElementById('hero_start');
    const heroEnd = document.getElementById('hero_end');
    if(heroStart && heroEnd) {
        heroStart.addEventListener('change', function() {
            heroEnd.min = this.value;
            if(heroEnd.value && heroEnd.value < this.value) { 
                heroEnd.value = this.value; 
            }
        });
    }

    // 4. SWIPER FOR MULTI-IMAGE CAR GALLERIES
    var swipers = new Swiper(".thumb-swiper", {
        effect: "fade",
        grabCursor: true,
        autoplay: { delay: 3500, disableOnInteraction: false },
        pagination: { el: ".swiper-pagination", dynamicBullets: true }
    });
</script>

<?php include '../includes/footer.php'; ?>