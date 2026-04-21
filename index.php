<?php
/**
 * =============================================================================
 * 🚀 SMARTDRIVE X — BEAST MODE V7.0 | ENTERPRISE HOMEPAGE ENGINE
 * Architect : Senior Full-Stack Lead  |  Stack: Core PHP 8+, CSS3, ES6+
 * Fixes     : filter-btn/trigger mismatch, missing CSS utilities, skeleton bug,
 *             newsletter-section undefined, border-color undefined, hover-translate
 * New       : Typed-text hero, How-It-Works, Testimonials, Quick-Contact modal,
 *             dark-mode toggle, back-to-top, ripple buttons, progress nav-bar
 * =============================================================================
 */
declare(strict_types=1);

/* ── 1. SECURITY HEADERS ──────────────────────────────────────────────────── */
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=()");

/* ── 2. SECURE SESSION ────────────────────────────────────────────────────── */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Strict'
    ]);
    session_start();
}

/* ── 3. CSRF TOKEN ────────────────────────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ── 4. PATHING ───────────────────────────────────────────────────────────── */
require_once 'includes/db_connect.php';
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$base_url = $proto . $_SERVER['HTTP_HOST'] . "/smartdrive_x/";

/* ── 5. AJAX API ROUTER ───────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'Security token mismatch. Request blocked.']);
        exit;
    }

    /* A. Newsletter */
    if ($_POST['ajax_action'] === 'newsletter_subscribe') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
            exit;
        }
        // DB insert would go here — simulated for safety
        usleep(700000);
        echo json_encode(['status' => 'success', 'message' => 'You\'re in the Elite Fleet! 500 Bonus Points added instantly.']);
        exit;
    }

    /* B. Quick Contact */
    if ($_POST['ajax_action'] === 'quick_contact') {
        $name  = htmlspecialchars(strip_tags(trim($_POST['name']  ?? '')), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars(strip_tags(trim($_POST['phone'] ?? '')), ENT_QUOTES, 'UTF-8');
        if (empty($name) || empty($phone)) {
            echo json_encode(['status' => 'error', 'message' => 'Name and phone number are required.']);
            exit;
        }
        if (!preg_match('/^[6-9]\d{9}$/', preg_replace('/\D/', '', $phone))) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 10-digit Indian mobile number.']);
            exit;
        }
        usleep(500000);
        echo json_encode(['status' => 'success', 'message' => 'Our concierge will call you within 15 minutes, ' . $name . '!']);
        exit;
    }
    echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
    exit;
}

/* ── 6. DATABASE SERVICE LAYER ────────────────────────────────────────────── */
$view_data = [
    'locations' => [],
    'featured'  => [],
    'stats'     => ['cars' => 0, 'users' => 0, 'hubs' => 0, 'bookings' => 0],
    'social'    => [],
    'reviews'   => [],   // NEW: testimonials
];

/* Failsafe demo cars (shown when DB unavailable) */
$demo_cars = [
    ['id'=>1,'brand'=>'Porsche','name'=>'911 Carrera S','base_price'=>18500,'model'=>'Cabriolet','city_name'=>'Mumbai','image'=>''],
    ['id'=>2,'brand'=>'BMW','name'=>'M5 Competition','base_price'=>14000,'model'=>'Sedan','city_name'=>'Delhi','image'=>''],
    ['id'=>3,'brand'=>'Range Rover','name'=>'Autobiography','base_price'=>12000,'model'=>'SUV','city_name'=>'Bangalore','image'=>''],
    ['id'=>4,'brand'=>'Mercedes','name'=>'AMG GT 63','base_price'=>16000,'model'=>'Coupe','city_name'=>'Mumbai','image'=>''],
    ['id'=>5,'brand'=>'Audi','name'=>'RS7 Sportback','base_price'=>11500,'model'=>'Sedan','city_name'=>'Hyderabad','image'=>''],
    ['id'=>6,'brand'=>'Lamborghini','name'=>'Urus Pearl','base_price'=>35000,'model'=>'SUV','city_name'=>'Mumbai','image'=>''],
];

/* Static testimonials (would come from DB in production) */
$static_reviews = [
    ['name'=>'Arjun Mehta','role'=>'CEO, TechVentures','rating'=>5,'text'=>'Booked a Porsche 911 for our product launch event. The entire experience — from the app UI to the white-glove delivery — was absolutely flawless. This is luxury car rental reimagined.','avatar'=>'https://randomuser.me/api/portraits/men/32.jpg','car'=>'Porsche 911 Carrera'],
    ['name'=>'Priya Sharma','role'=>'Architect, Mumbai','rating'=>5,'text'=>'I was skeptical at first, but SmartDrive X completely won me over. The Bluetooth digital key feature is a game-changer. Range Rover Autobiography arrived 10 mins early, immaculate condition.','avatar'=>'https://randomuser.me/api/portraits/women/44.jpg','car'=>'Range Rover Autobiography'],
    ['name'=>'Rahul Nair','role'=>'Wedding Planner','rating'=>5,'text'=>'Used their fleet for a high-profile celebrity wedding. Six vehicles, zero issues. Real-time telemetry tracking gave our team total visibility. Their concierge team is genuinely world-class.','avatar'=>'https://randomuser.me/api/portraits/men/67.jpg','car'=>'Mercedes AMG S-Class'],
];

try {
    $res = $conn->query("SELECT id, city_name FROM locations ORDER BY city_name ASC");
    if ($res) { while ($r = $res->fetch_assoc()) $view_data['locations'][] = $r; }

    $res = $conn->query("SELECT c.*, l.city_name FROM cars c LEFT JOIN locations l ON c.location_id = l.id WHERE c.status='available' ORDER BY c.base_price DESC LIMIT 6");
    if ($res) { while ($r = $res->fetch_assoc()) $view_data['featured'][] = $r; }

    $view_data['stats']['cars']     = (int)($conn->query("SELECT COUNT(*) FROM cars")->fetch_row()[0] ?? 0);
    $view_data['stats']['users']    = (int)($conn->query("SELECT COUNT(*) FROM users WHERE role_id=2")->fetch_row()[0] ?? 0);
    $view_data['stats']['hubs']     = (int)($conn->query("SELECT COUNT(*) FROM locations")->fetch_row()[0] ?? 0);
    $view_data['stats']['bookings'] = (int)($conn->query("SELECT COUNT(*) FROM bookings WHERE booking_status='confirmed'")->fetch_row()[0] ?? 0);

    $res = $conn->query("SELECT c.name,c.brand,l.city_name,b.created_at FROM bookings b JOIN cars c ON b.car_id=c.id LEFT JOIN locations l ON c.location_id=l.id WHERE b.booking_status='confirmed' ORDER BY b.created_at DESC LIMIT 10");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $diff = time() - strtotime($r['created_at']);
            $ago  = $diff < 3600 ? round($diff/60).'m ago' : ($diff < 86400 ? round($diff/3600).'h ago' : round($diff/86400).'d ago');
            $view_data['social'][] = ['label' => htmlspecialchars($r['brand'].' '.$r['name']), 'loc' => htmlspecialchars($r['city_name'] ?? 'Hub'), 'ago' => $ago];
        }
    }
} catch (Exception $e) {
    error_log("[SmartDrive X] DB Error: " . $e->getMessage());
}

/* Apply failsafe demo data */
if (empty($view_data['featured']))  $view_data['featured']  = $demo_cars;
if (empty($view_data['reviews']))   $view_data['reviews']   = $static_reviews;
if ($view_data['stats']['cars'] === 0) {
    $view_data['stats'] = ['cars' => 142, 'users' => 28400, 'hubs' => 18, 'bookings' => 91600];
}
if (empty($view_data['social'])) {
    $view_data['social'] = [
        ['label'=>'Porsche 911 Carrera','loc'=>'Mumbai','ago'=>'3m ago'],
        ['label'=>'BMW M5 Competition','loc'=>'Delhi','ago'=>'11m ago'],
        ['label'=>'Range Rover Auto','loc'=>'Bangalore','ago'=>'28m ago'],
        ['label'=>'Mercedes AMG GT','loc'=>'Hyderabad','ago'=>'1h ago'],
    ];
}

/* ── 7. IMAGE PARSER ──────────────────────────────────────────────────────── */
function parseCarImages(string $raw, string $base, string $brand): array {
    $imgs = [];
    if (!empty($raw)) {
        $dec = json_decode($raw, true);
        if (is_array($dec)) {
            foreach ($dec as $p) { if (file_exists($p)) $imgs[] = $base . $p; }
        } elseif (file_exists($raw)) {
            $imgs[] = $base . $raw;
        }
    }
    if (empty($imgs)) {
        $fb = [
            'porsche'     => 'https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=80&w=900&auto=format',
            'bmw'         => 'https://images.unsplash.com/photo-1556189250-72ba954cfc2b?q=80&w=900&auto=format',
            'audi'        => 'https://images.unsplash.com/photo-1606152421802-db97b9c7a11b?q=80&w=900&auto=format',
            'range rover' => 'https://images.unsplash.com/photo-1606016159991-d8532e856086?q=80&w=900&auto=format',
            'mercedes'    => 'https://images.unsplash.com/photo-1610880846497-7257b23f6128?q=80&w=900&auto=format',
            'lamborghini' => 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?q=80&w=900&auto=format',
        ];
        $key    = strtolower(trim($brand));
        $imgs[] = $fb[$key] ?? 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?q=80&w=900&auto=format';
    }
    return $imgs;
}

$page_title = "SmartDrive X | India's Elite Luxury Car Rental";
include 'includes/header.php';
?>
<!--------------------------------------------------------------------------- -->
<!-- EXTERNAL ASSETS                                                           -->
<!--------------------------------------------------------------------------- -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  BEAST CSS ENGINE                                                        -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<style>
/* ── TOKENS ─────────────────────────────────────────────────────────────── */
:root {
    --c-mint     : #8ce5c3;
    --c-mint-dk  : #5dcda5;
    --c-sage     : #c8f2ba;
    --c-cream    : #fbf8d6;
    --c-taupe    : #a89684;
    --text-dark  : #1a2421;
    --bg-main    : #f7f5e8;
    --bg-surface : #ffffff;
    --bg-glass   : rgba(255,255,255,0.72);
    --text-muted : #6b7280;
    --border-soft: rgba(168,150,132,0.18);
    --border-glass:rgba(255,255,255,0.45);
    --sh-xs : 0 2px 8px rgba(0,0,0,0.04);
    --sh-sm : 0 8px 24px rgba(0,0,0,0.06);
    --sh-md : 0 20px 48px rgba(0,0,0,0.10);
    --sh-lg : 0 32px 72px rgba(0,0,0,0.14);
    --sh-mint: 0 18px 40px rgba(140,229,195,0.28);
    --radius-sm:16px; --radius-md:24px; --radius-lg:36px; --radius-xl:48px;
    --font-main:'Inter',system-ui,sans-serif;
    --nav-h:76px;
    --transition: all .35s cubic-bezier(.4,0,.2,1);
}
[data-theme="dark"] {
    --bg-main    : #0f1714;
    --bg-surface : #192220;
    --bg-glass   : rgba(18,30,26,0.80);
    --text-dark  : #f0ead6;
    --text-muted : #8e9e9a;
    --border-soft: rgba(140,229,195,0.12);
    --border-glass:rgba(140,229,195,0.18);
    --sh-sm : 0 8px 24px rgba(0,0,0,0.45);
    --sh-md : 0 20px 48px rgba(0,0,0,0.65);
    --sh-mint: 0 18px 40px rgba(140,229,195,0.08);
}

/* ── RESET / BASE ────────────────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{
    font-family:var(--font-main);
    background:var(--bg-main);
    color:var(--text-dark);
    overflow-x:hidden;
    transition:background .45s ease,color .45s ease;
}
img{max-width:100%;display:block}
a{text-decoration:none;color:inherit}

/* ── PROGRESS BAR ────────────────────────────────────────────────────────── */
#scroll-progress{
    position:fixed;top:0;left:0;height:3px;width:0;z-index:10000;
    background:linear-gradient(90deg,var(--c-mint),var(--c-sage),var(--c-mint));
    transition:width .1s linear;
}

/* ── DARK MODE TOGGLE ────────────────────────────────────────────────────── */
#theme-toggle{
    position:fixed;top:50%;right:20px;transform:translateY(-50%);z-index:9990;
    width:44px;height:44px;border-radius:50%;border:2px solid var(--border-soft);
    background:var(--bg-surface);color:var(--text-dark);cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    box-shadow:var(--sh-md);transition:var(--transition);
}
#theme-toggle:hover{background:var(--c-mint);color:#1a2421;border-color:var(--c-mint)}

/* ── BACK TO TOP ─────────────────────────────────────────────────────────── */
#back-top{
    position:fixed;bottom:28px;right:24px;z-index:9990;
    width:48px;height:48px;border-radius:50%;border:none;
    background:var(--text-dark);color:var(--c-mint);cursor:pointer;
    display:flex;align-items:center;justify-content:center;
    box-shadow:var(--sh-md);transition:var(--transition);
    opacity:0;pointer-events:none;transform:translateY(20px);
}
#back-top.show{opacity:1;pointer-events:all;transform:translateY(0)}
#back-top:hover{background:var(--c-mint);color:#1a2421;transform:translateY(-4px)}

/* ── SKELETON LOADER ─────────────────────────────────────────────────────── */
.skel{
    position:relative;overflow:hidden;
    background:rgba(168,150,132,0.12);
    color:transparent!important;border-color:transparent!important;
    pointer-events:none;border-radius:var(--radius-sm);
}
.skel *{visibility:hidden}
.skel::after{
    content:'';position:absolute;inset:0;transform:translateX(-100%);
    background:linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent);
    animation:shimmer 1.4s infinite;
}
@keyframes shimmer{to{transform:translateX(100%)}}

/* ── UTILITY CLASSES ─────────────────────────────────────────────────────── */
.text-mint  {color:var(--c-mint)!important}
.bg-surface {background:var(--bg-surface)!important}
.bg-dark-custom{background:#121816}
.border-soft{border:1px solid var(--border-soft)!important}
.tracking-wider {letter-spacing:.08em}
.tracking-widest{letter-spacing:.18em}
.fw-black   {font-weight:900!important}
.fw-extrabold{font-weight:800!important}
.hover-lift {transition:var(--transition)}
.hover-lift:hover{transform:translateY(-6px);box-shadow:var(--sh-mint)}

/* ── NAV PROGRESS ────────────────────────────────────────────────────────── */
.navbar-scrolled{
    background:var(--bg-glass)!important;
    backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
    box-shadow:var(--sh-sm);
}

/* ── RIPPLE BUTTON ───────────────────────────────────────────────────────── */
.btn-ripple{position:relative;overflow:hidden;transition:var(--transition)}
.btn-ripple .ripple-wave{
    position:absolute;border-radius:50%;background:rgba(255,255,255,0.35);
    transform:scale(0);animation:ripple-anim .65s linear;pointer-events:none;
}
@keyframes ripple-anim{to{transform:scale(4);opacity:0}}

.btn-mint{
    background:var(--c-mint);color:#1a2421;border:none;
    font-weight:900;letter-spacing:.03em;
    transition:var(--transition);
    box-shadow:0 8px 20px rgba(140,229,195,0.30);
}
.btn-mint:hover{background:var(--c-mint-dk);color:#0f1714;transform:translateY(-3px);box-shadow:var(--sh-mint)}
.btn-mint:active{transform:translateY(0)}

/* ── HERO ────────────────────────────────────────────────────────────────── */
.hero-section{
    min-height:100vh;display:flex;align-items:center;
    position:relative;overflow:hidden;margin-top:calc(-1 * var(--nav-h));
}
.hero-bg{
    position:absolute;inset:0;z-index:0;
    background-size:cover;background-position:center;
    filter:brightness(.6) contrast(1.1);transform:scale(1.06);
    animation:heroFade 22s ease-in-out infinite;
}
@keyframes heroFade{
    0%,22%{background-image:url('https://images.unsplash.com/photo-1603584173870-7f23fdae1b7a?q=85&w=2000&auto=format')}
    27%,47%{background-image:url('https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=85&w=2000&auto=format')}
    52%,72%{background-image:url('https://images.unsplash.com/photo-1503377215949-6f5628b031c2?q=85&w=2000&auto=format')}
    77%,97%{background-image:url('https://images.unsplash.com/photo-1617531653332-bd46c24f2068?q=85&w=2000&auto=format')}
    100%   {background-image:url('https://images.unsplash.com/photo-1603584173870-7f23fdae1b7a?q=85&w=2000&auto=format')}
}
.hero-overlay{
    position:absolute;inset:0;z-index:1;
    background:linear-gradient(115deg,rgba(15,23,18,.96) 0%,rgba(15,23,18,.72) 45%,rgba(15,23,18,.10) 100%);
}
.hero-content{position:relative;z-index:2;padding-top:var(--nav-h)}

.hero-badge{
    display:inline-flex;align-items:center;gap:.5rem;
    padding:.45rem 1.2rem;border-radius:50px;
    background:rgba(140,229,195,.12);color:var(--c-mint);
    border:1px solid rgba(140,229,195,.35);
    font-size:.72rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase;
    backdrop-filter:blur(10px);margin-bottom:1.75rem;
    animation:heroFadeIn 1s .2s both;
}
.hero-h1{
    font-size:clamp(2.6rem,7vw,5.5rem);font-weight:900;line-height:1.04;
    color:#fff;margin-bottom:1.5rem;
    animation:heroFadeIn 1s .5s both;
}
.hero-h1 .accent{
    background:linear-gradient(120deg,var(--c-mint),var(--c-sage));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;
}
.hero-sub{
    font-size:clamp(1rem,2.2vw,1.2rem);font-weight:600;
    color:rgba(255,255,255,.72);max-width:560px;
    margin-bottom:2.5rem;animation:heroFadeIn 1s .8s both;
}
.hero-actions{animation:heroFadeIn 1s 1.1s both;display:flex;flex-wrap:wrap;gap:1rem}
@keyframes heroFadeIn{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}

.hero-scroll-hint{
    position:absolute;bottom:32px;left:50%;transform:translateX(-50%);
    z-index:3;display:flex;flex-direction:column;align-items:center;gap:8px;
    color:rgba(255,255,255,.5);font-size:.7rem;font-weight:700;
    letter-spacing:.15em;text-transform:uppercase;animation:bounce 2s infinite;
}
.hero-scroll-hint span{width:24px;height:38px;border:2px solid rgba(255,255,255,.3);border-radius:12px;position:relative}
.hero-scroll-hint span::after{
    content:'';position:absolute;top:6px;left:50%;transform:translateX(-50%);
    width:4px;height:8px;background:var(--c-mint);border-radius:2px;
    animation:scrollDot 1.8s infinite;
}
@keyframes scrollDot{0%,100%{opacity:1;top:6px}50%{opacity:.3;top:18px}}
@keyframes bounce{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(6px)}}

/* ── BOOKING WIDGET ──────────────────────────────────────────────────────── */
.booking-wrapper{position:relative;z-index:10;margin-top:-90px}
.booking-glass{
    background:var(--bg-glass);
    backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);
    border-radius:var(--radius-lg);padding:2.25rem 2.5rem;
    box-shadow:var(--sh-lg);border:1px solid var(--border-glass);
    position:relative;overflow:hidden;
}
.booking-glass::before{
    content:'';position:absolute;top:0;left:-120%;width:40%;height:100%;
    background:linear-gradient(to right,transparent,rgba(255,255,255,.18),transparent);
    transform:skewX(-18deg);animation:glassSheen 7s ease-in-out infinite;
}
@keyframes glassSheen{0%,75%{left:-120%}100%{left:200%}}
.form-ctrl{
    background:var(--bg-main);border:2px solid transparent;
    border-radius:var(--radius-sm);padding:1.05rem 1.2rem;
    font-weight:700;color:var(--text-dark);
    transition:var(--transition);width:100%;
    font-family:var(--font-main);font-size:.95rem;
}
.form-ctrl:focus{
    border-color:var(--c-mint);
    box-shadow:0 0 0 5px rgba(140,229,195,.15);
    outline:none;background:var(--bg-surface);
}
.form-ctrl option{color:#1a2421;background:#fff}

/* ── SECTION LABELS ──────────────────────────────────────────────────────── */
.sec-label{
    display:inline-block;font-size:.7rem;font-weight:800;
    letter-spacing:.22em;text-transform:uppercase;
    color:var(--c-mint);margin-bottom:.6rem;
}
.sec-heading{
    font-size:clamp(1.9rem,4.5vw,3rem);font-weight:900;
    line-height:1.12;color:var(--text-dark);
}

/* ── HOW IT WORKS ────────────────────────────────────────────────────────── */
.step-card{
    background:var(--bg-surface);border:1px solid var(--border-soft);
    border-radius:var(--radius-md);padding:2.2rem;
    position:relative;transition:var(--transition);overflow:hidden;
}
.step-card::after{
    content:'';position:absolute;bottom:-40px;right:-40px;
    width:120px;height:120px;border-radius:50%;
    background:radial-gradient(circle,rgba(140,229,195,.12) 0%,transparent 70%);
    transition:var(--transition);
}
.step-card:hover{transform:translateY(-8px);box-shadow:var(--sh-mint)}
.step-card:hover::after{width:180px;height:180px}
.step-num{
    font-size:4rem;font-weight:900;line-height:1;
    background:linear-gradient(135deg,var(--c-mint),var(--c-sage));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;margin-bottom:1rem;display:block;
}
.step-icon-wrap{
    width:56px;height:56px;border-radius:16px;
    background:rgba(140,229,195,.12);border:1px solid rgba(140,229,195,.3);
    display:flex;align-items:center;justify-content:center;
    margin-bottom:1.2rem;font-size:1.4rem;color:var(--c-mint);
    transition:var(--transition);
}
.step-card:hover .step-icon-wrap{background:var(--c-mint);color:#1a2421}
.connector-line{
    position:absolute;top:38px;left:calc(100% + 0px);width:100%;height:2px;
    background:linear-gradient(90deg,var(--c-mint),transparent);
    display:none;
}
@media(min-width:768px){.connector-line{display:block}}

/* ── FEATURE CARDS ───────────────────────────────────────────────────────── */
.feature-card{
    background:var(--bg-surface);border:1px solid var(--border-soft);
    border-radius:var(--radius-md);padding:2rem;transition:var(--transition);
    cursor:default;overflow:hidden;position:relative;
}
.feature-card::before{
    content:'';position:absolute;inset:0;
    background:linear-gradient(135deg,rgba(140,229,195,0) 0%,rgba(200,242,186,.08) 100%);
    opacity:0;transition:var(--transition);
}
.feature-card:hover{transform:translateY(-6px);box-shadow:var(--sh-mint);border-color:rgba(140,229,195,.4)}
.feature-card:hover::before{opacity:1}
.feat-icon{
    width:52px;height:52px;border-radius:14px;
    background:linear-gradient(135deg,rgba(140,229,195,.15),rgba(200,242,186,.25));
    border:1px solid rgba(140,229,195,.25);
    display:flex;align-items:center;justify-content:center;
    font-size:1.3rem;color:var(--c-mint);margin-bottom:1.1rem;
    transition:var(--transition);
}
.feature-card:hover .feat-icon{background:var(--c-mint);color:#1a2421;transform:rotate(-6deg) scale(1.1)}

/* ── FLEET FILTER ────────────────────────────────────────────────────────── */
.filter-wrap{display:flex;flex-wrap:wrap;gap:.6rem}
.filter-btn{
    background:var(--bg-surface);border:2px solid var(--border-soft);
    color:var(--text-muted);border-radius:50px;
    padding:.6rem 1.5rem;font-weight:800;font-size:.87rem;
    transition:var(--transition);cursor:pointer;font-family:var(--font-main);
}
.filter-btn:hover{border-color:var(--c-mint);color:var(--text-dark)}
.filter-btn.active{
    background:var(--text-dark);color:var(--c-mint);
    border-color:var(--text-dark);box-shadow:var(--sh-sm);
}

/* ── CAR CARD ────────────────────────────────────────────────────────────── */
.car-card{
    background:var(--bg-surface);border:1px solid var(--border-soft);
    border-radius:var(--radius-md);overflow:hidden;
    box-shadow:var(--sh-sm);transition:var(--transition);
    display:flex;flex-direction:column;height:100%;
    transform-style:preserve-3d;
}
.car-card:hover{box-shadow:var(--sh-mint);border-color:rgba(140,229,195,.35)}
.car-img-wrap{height:260px;overflow:hidden;position:relative}
.car-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform .8s ease}
.car-card:hover .car-img-wrap img{transform:scale(1.08)}
.car-badge{
    position:absolute;top:14px;left:14px;z-index:3;
    padding:.3rem .85rem;border-radius:50px;
    background:var(--bg-surface);color:var(--text-dark);
    font-size:.72rem;font-weight:800;
    box-shadow:var(--sh-sm);border:1px solid var(--border-soft);
}
.car-loc{
    position:absolute;bottom:14px;right:14px;z-index:3;
    padding:.3rem .85rem;border-radius:50px;
    background:rgba(0,0,0,.72);color:#fff;
    font-size:.72rem;font-weight:700;
    backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.15);
}
.car-body{padding:1.5rem 1.6rem;flex:1;display:flex;flex-direction:column}
.car-brand{font-size:.68rem;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:var(--text-muted);margin-bottom:.3rem}
.car-name{font-size:1.3rem;font-weight:900;color:var(--text-dark);margin-bottom:1.2rem}
.spec-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:1.2rem}
.spec-pill{
    display:flex;flex-direction:column;align-items:center;gap:.25rem;
    padding:.7rem .5rem;border-radius:14px;
    background:var(--bg-main);border:1px solid var(--border-soft);
    font-size:.7rem;font-weight:700;color:var(--text-dark);
    transition:var(--transition);
}
.car-card:hover .spec-pill{background:rgba(140,229,195,.08);border-color:rgba(140,229,195,.3)}
.spec-pill i{font-size:1rem;color:var(--c-mint)}
.car-footer{
    display:flex;align-items:center;justify-content:space-between;
    padding-top:1.1rem;border-top:1px solid var(--border-soft);margin-top:auto;
}
.car-price{font-size:1.55rem;font-weight:900;color:var(--text-dark)}
.car-price sup{font-size:.9rem;font-weight:800;vertical-align:top;margin-top:.3rem}
.car-price small{font-size:.72rem;font-weight:600;color:var(--text-muted);display:block}

/* ── STATS BAND ──────────────────────────────────────────────────────────── */
.stats-band{background:#0f1714;position:relative;overflow:hidden}
.stats-band::before{
    content:'';position:absolute;top:-60%;left:-10%;
    width:50%;height:200%;border-radius:50%;
    background:radial-gradient(circle,rgba(140,229,195,.06) 0%,transparent 70%);
}
.stat-item{padding:1.5rem;position:relative;z-index:1}
.stat-num{
    font-size:clamp(2.2rem,5vw,3.5rem);font-weight:900;
    background:linear-gradient(135deg,var(--c-mint),var(--c-sage));
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;
    background-clip:text;line-height:1;display:block;
}
.stat-label{font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-top:.4rem}
.stat-divider{width:1px;background:rgba(255,255,255,.08);margin:1rem 0}

/* ── ESTIMATOR ───────────────────────────────────────────────────────────── */
.estimator-card{
    background:var(--text-dark);border-radius:var(--radius-lg);
    padding:3rem;position:relative;overflow:hidden;
    box-shadow:var(--sh-lg);
}
.estimator-card::before{
    content:'';position:absolute;top:-30%;right:-20%;width:400px;height:400px;
    border-radius:50%;
    background:radial-gradient(circle,rgba(140,229,195,.12) 0%,transparent 65%);
    pointer-events:none;
}
.est-select{
    background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
    border-radius:var(--radius-sm);padding:1rem 1.2rem;
    color:#fff;font-weight:700;font-family:var(--font-main);
    width:100%;outline:none;
}
.est-select option{color:#1a2421;background:#fff}
.range-track{-webkit-appearance:none;width:100%;height:7px;border-radius:4px;background:rgba(255,255,255,.1);outline:none;margin:1rem 0}
.range-track::-webkit-slider-thumb{
    -webkit-appearance:none;width:28px;height:28px;border-radius:50%;
    background:var(--c-mint);cursor:pointer;border:3px solid #1a2421;
    box-shadow:0 2px 10px rgba(0,0,0,.3);transition:transform .2s;
}
.range-track::-webkit-slider-thumb:hover{transform:scale(1.2)}
.est-result{
    background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);
    border-radius:var(--radius-sm);padding:1.5rem;
}

/* ── TESTIMONIALS ────────────────────────────────────────────────────────── */
.review-card{
    background:var(--bg-surface);border:1px solid var(--border-soft);
    border-radius:var(--radius-md);padding:2rem;
    transition:var(--transition);position:relative;overflow:hidden;
}
.review-card::before{
    content:'\201C';position:absolute;top:-10px;right:20px;
    font-size:8rem;font-weight:900;color:var(--c-mint);opacity:.08;
    line-height:1;font-family:serif;
}
.review-card:hover{transform:translateY(-6px);box-shadow:var(--sh-mint);border-color:rgba(140,229,195,.35)}
.review-stars{color:#f59e0b;letter-spacing:.1em;margin-bottom:.9rem}
.review-text{font-size:.93rem;font-weight:600;line-height:1.7;color:var(--text-dark);margin-bottom:1.4rem}
.reviewer{display:flex;align-items:center;gap:.9rem}
.reviewer img{width:46px;height:46px;border-radius:50%;object-fit:cover;border:2px solid var(--border-soft)}
.reviewer-name{font-weight:800;font-size:.9rem;color:var(--text-dark)}
.reviewer-role{font-size:.76rem;font-weight:600;color:var(--text-muted)}
.review-car{
    display:inline-flex;align-items:center;gap:.4rem;
    font-size:.68rem;font-weight:800;letter-spacing:.1em;
    text-transform:uppercase;color:var(--c-mint);margin-top:.5rem;
}

/* ── APP PROMO ───────────────────────────────────────────────────────────── */
.app-promo{
    border-radius:var(--radius-xl);overflow:hidden;
    background:linear-gradient(130deg,#c8f2ba 0%,#8ce5c3 55%,#6ddbb0 100%);
    position:relative;box-shadow:var(--sh-lg);
}
.app-promo::after{
    content:'';position:absolute;top:0;right:0;bottom:0;width:50%;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.12));
}
.app-promo-img{
    position:absolute;right:-4%;bottom:-18%;height:128%;width:auto;
    filter:drop-shadow(0 30px 50px rgba(0,0,0,.25));
    transition:transform .2s ease-out;
}
.store-btn{
    display:inline-flex;align-items:center;gap:.7rem;
    padding:.85rem 1.8rem;border-radius:50px;font-weight:800;
    font-size:.9rem;transition:var(--transition);cursor:pointer;
    font-family:var(--font-main);border:none;
}
.store-btn-dark{background:#1a2421;color:#fff;box-shadow:var(--sh-md)}
.store-btn-dark:hover{background:#0f1714;transform:translateY(-3px)}
.store-btn-light{background:#fff;color:#1a2421;box-shadow:var(--sh-md)}
.store-btn-light:hover{background:#f0f0f0;transform:translateY(-3px)}

/* ── FAQ ─────────────────────────────────────────────────────────────────── */
.faq-item{
    border:1px solid var(--border-soft);border-radius:var(--radius-sm);
    background:var(--bg-surface);overflow:hidden;transition:var(--transition);margin-bottom:.75rem;
}
.faq-item:hover{border-color:rgba(140,229,195,.35)}
.faq-q{
    padding:1.3rem 1.5rem;cursor:pointer;
    font-weight:800;font-size:.97rem;color:var(--text-dark);
    display:flex;align-items:center;justify-content:space-between;
    user-select:none;transition:var(--transition);
}
.faq-q:hover{color:var(--c-mint-dk)}
.faq-icon{
    width:28px;height:28px;border-radius:8px;
    background:rgba(140,229,195,.12);color:var(--c-mint);
    display:flex;align-items:center;justify-content:center;
    font-size:.8rem;flex-shrink:0;transition:var(--transition);
}
.faq-item.open .faq-icon{background:var(--c-mint);color:#1a2421;transform:rotate(45deg)}
.faq-a{
    max-height:0;overflow:hidden;transition:max-height .4s ease,padding .4s ease;
    font-size:.92rem;font-weight:600;line-height:1.8;color:var(--text-muted);
}
.faq-item.open .faq-a{max-height:300px;padding:0 1.5rem 1.3rem}

/* ── NEWSLETTER ──────────────────────────────────────────────────────────── */
.nl-section{
    background:var(--text-dark);border-radius:var(--radius-xl);
    padding:3.5rem;position:relative;overflow:hidden;box-shadow:var(--sh-lg);
}
.nl-section::before{
    content:'';position:absolute;top:-50%;left:-20%;
    width:500px;height:500px;border-radius:50%;
    background:radial-gradient(circle,rgba(140,229,195,.1) 0%,transparent 65%);
}
.nl-input-wrap{position:relative}
.nl-input{
    width:100%;padding:1.1rem 180px 1.1rem 1.4rem;
    border-radius:50px;border:none;
    font-weight:700;font-family:var(--font-main);font-size:.97rem;
    background:rgba(255,255,255,.08);color:#fff;outline:none;
    border:1px solid rgba(255,255,255,.12);
    transition:var(--transition);
}
.nl-input::placeholder{color:rgba(255,255,255,.45)}
.nl-input:focus{background:rgba(255,255,255,.13);border-color:var(--c-mint)}
.nl-submit{
    position:absolute;right:6px;top:6px;bottom:6px;
    padding:0 1.5rem;border-radius:50px;border:none;
    background:var(--c-mint);color:#1a2421;font-weight:900;
    font-family:var(--font-main);cursor:pointer;transition:var(--transition);
    white-space:nowrap;
}
.nl-submit:hover{background:var(--c-sage)}

/* ── QUICK CONTACT MODAL ─────────────────────────────────────────────────── */
.modal-glass{
    background:var(--bg-glass);backdrop-filter:blur(30px);
    -webkit-backdrop-filter:blur(30px);border:1px solid var(--border-glass);
    border-radius:var(--radius-lg);padding:2.5rem;box-shadow:var(--sh-lg);
}

/* ── TOAST STACK ─────────────────────────────────────────────────────────── */
#toast-stack{
    position:fixed;bottom:28px;left:24px;z-index:99999;
    display:flex;flex-direction:column;gap:10px;pointer-events:none;
    max-width:340px;
}
.sdx-toast{
    background:var(--bg-surface);border-left:4px solid var(--c-mint);
    border:1px solid var(--border-soft);border-left:4px solid var(--c-mint);
    border-radius:18px;padding:14px 18px;
    display:flex;align-items:center;gap:14px;
    box-shadow:var(--sh-md);pointer-events:all;
    transform:translateX(-120%);opacity:0;
    transition:transform .55s cubic-bezier(.68,-.55,.265,1.55),opacity .4s ease;
}
.sdx-toast.show{transform:translateX(0);opacity:1}
.toast-icon{
    width:40px;height:40px;border-radius:50%;flex-shrink:0;
    background:rgba(140,229,195,.15);color:var(--c-mint);
    display:flex;align-items:center;justify-content:center;font-size:1.05rem;
}

/* ── RESPONSIVE BREAKPOINTS ──────────────────────────────────────────────── */
@media(max-width:768px){
    .booking-glass{padding:1.5rem}
    .hero-actions .btn{width:100%;justify-content:center}
    .estimator-card{padding:2rem 1.5rem}
    .nl-section{padding:2rem 1.5rem}
    .nl-input{padding-right:1.4rem;padding-bottom:1.1rem}
    .nl-submit{position:static;margin-top:.75rem;width:100%;padding:1rem;border-radius:50px}
    .nl-input-wrap{display:flex;flex-direction:column}
    .app-promo-img{display:none}
    .hero-scroll-hint{display:none}
    #toast-stack{left:12px;right:12px;max-width:none}
    #theme-toggle{display:none}
}
@media(max-width:480px){
    .spec-grid{grid-template-columns:repeat(3,1fr)}
    .hero-h1{font-size:2.3rem}
}
</style>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SCHEMA MARKUP                                                           -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<script type="application/ld+json">
{
  "@context":"https://schema.org","@type":"AutoRental",
  "name":"SmartDrive X","url":"<?= $base_url ?>",
  "description":"India's premier luxury car rental — Porsches, BMWs, SUVs. Digital key, zero paperwork.",
  "priceRange":"$$$","telephone":"+91-98765-43210",
  "areaServed":"India","currenciesAccepted":"INR"
}
</script>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  UI SCAFFOLDING                                                          -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div id="scroll-progress"></div>
<div id="toast-stack"></div>

<button id="theme-toggle" aria-label="Toggle theme" title="Toggle dark / light mode">
    <i class="fas fa-moon" id="theme-icon"></i>
</button>
<button id="back-top" aria-label="Back to top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 1 — HERO                                                        -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="hero-section">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>

    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-xl-7 col-lg-9">
                <div class="hero-badge">
                    <i class="fas fa-circle" style="font-size:.45rem;animation:pulse 1.5s infinite"></i>
                    Elite Fleet — Live Availability Active
                </div>

                <h1 class="hero-h1">
                    Command<br>
                    The <span class="accent" id="typed-target">Horizon.</span>
                </h1>

                <p class="hero-sub">
                    Experience uncompromising luxury. India's most pristine collection of premium
                    vehicles with real-time booking, digital keys, and zero hidden fees.
                </p>

                <div class="hero-actions">
                    <button class="btn btn-mint btn-ripple px-4 py-3 rounded-pill fw-black fs-6"
                            onclick="scrollTo({top:document.getElementById('booking-widget').offsetTop-30,behavior:'smooth'})">
                        Reserve Now &nbsp;<i class="fas fa-arrow-right"></i>
                    </button>
                    <button class="btn btn-outline-light btn-ripple px-4 py-3 rounded-pill fw-bold"
                            data-bs-toggle="modal" data-bs-target="#videoModal">
                        <i class="fas fa-play me-2"></i> Watch Cinematic
                    </button>
                    <button class="btn btn-ripple px-4 py-3 rounded-pill fw-bold"
                            style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(8px)"
                            data-bs-toggle="modal" data-bs-target="#contactModal">
                        <i class="fas fa-headset me-2"></i> Talk to Concierge
                    </button>
                </div>

                <!-- Live stats pills -->
                <div class="d-flex flex-wrap gap-3 mt-4" style="animation:heroFadeIn 1s 1.4s both">
                    <div style="background:rgba(140,229,195,.1);border:1px solid rgba(140,229,195,.25);border-radius:50px;padding:.45rem 1rem;color:rgba(255,255,255,.85);font-size:.78rem;font-weight:700;backdrop-filter:blur(10px)">
                        <i class="fas fa-car me-1 text-mint"></i>
                        <?= number_format($view_data['stats']['cars']) ?>+ Premium Cars
                    </div>
                    <div style="background:rgba(140,229,195,.1);border:1px solid rgba(140,229,195,.25);border-radius:50px;padding:.45rem 1rem;color:rgba(255,255,255,.85);font-size:.78rem;font-weight:700;backdrop-filter:blur(10px)">
                        <i class="fas fa-map-marker-alt me-1 text-mint"></i>
                        <?= $view_data['stats']['hubs'] ?> City Hubs
                    </div>
                    <div style="background:rgba(140,229,195,.1);border:1px solid rgba(140,229,195,.25);border-radius:50px;padding:.45rem 1rem;color:rgba(255,255,255,.85);font-size:.78rem;font-weight:700;backdrop-filter:blur(10px)">
                        <i class="fas fa-shield-alt me-1 text-mint"></i>
                        Zero Hidden Fees
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="hero-scroll-hint">
        <span></span>Scroll to Explore
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 2 — BOOKING WIDGET                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="container booking-wrapper" id="booking-widget">
    <div class="booking-glass skel" data-aos="fade-up" data-aos-duration="900">
        <div class="row g-3 align-items-end">
            <div class="col-12 mb-1">
                <span class="sec-label"><i class="fas fa-bolt me-1"></i> Instant Availability</span>
                <p class="mb-0" style="font-size:.85rem;font-weight:700;color:var(--text-muted)">Select city, dates &amp; discover your perfect ride</p>
            </div>
        </div>
        <form action="<?= htmlspecialchars($base_url) ?>customer/search_cars.php" method="GET"
              class="row g-3 align-items-end mt-0" id="mainSearchForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="col-md-4">
                <label class="form-label fw-extrabold small text-uppercase tracking-widest" style="font-size:.65rem;color:var(--text-muted)">
                    <i class="fas fa-map-marker-alt me-1" style="color:#ef4444"></i> Pickup Hub
                </label>
                <select name="location" class="form-ctrl" required>
                    <option value="">Select target city…</option>
                    <?php foreach ($view_data['locations'] as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['city_name']) ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($view_data['locations'])): ?>
                    <option value="1">Mumbai</option>
                    <option value="2">Delhi</option>
                    <option value="3">Bangalore</option>
                    <option value="4">Hyderabad</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-extrabold small text-uppercase tracking-widest" style="font-size:.65rem;color:var(--text-muted)">
                    <i class="far fa-calendar-alt me-1 text-mint"></i> Start Date
                </label>
                <input type="date" name="start_date" id="home_start" class="form-ctrl"
                       min="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-extrabold small text-uppercase tracking-widest" style="font-size:.65rem;color:var(--text-muted)">
                    <i class="far fa-calendar-check me-1 text-mint"></i> Return Date
                </label>
                <input type="date" name="end_date" id="home_end" class="form-ctrl"
                       min="<?= date('Y-m-d') ?>" required disabled>
            </div>

            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-ripple fw-black py-3 rounded-3"
                        style="background:var(--text-dark);color:var(--c-mint);font-size:.95rem;height:100%">
                    <i class="fas fa-search me-1"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 3 — HOW IT WORKS                                                -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5 mt-5">
    <div class="container py-4">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="sec-label">Zero Friction Process</span>
            <h2 class="sec-heading mt-1">Up &amp; Driving in 3 Steps.</h2>
        </div>

        <div class="row g-4 position-relative">
            <!-- connector -->
            <div class="d-none d-md-block" style="position:absolute;top:56px;left:calc(16.66% + 28px);width:calc(66.66% - 56px);height:2px;background:linear-gradient(90deg,var(--c-mint),var(--c-sage),var(--c-mint));opacity:.35;z-index:0;border-radius:2px"></div>

            <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                <div class="step-card skel h-100">
                    <span class="step-num">01</span>
                    <div class="step-icon-wrap"><i class="fas fa-search"></i></div>
                    <h4 class="fw-black mb-2" style="font-size:1.1rem">Choose &amp; Compare</h4>
                    <p style="font-size:.88rem;font-weight:600;color:var(--text-muted);line-height:1.7;margin:0">
                        Filter our live inventory by city, dates, and vehicle tier. Real-time availability — no phantom listings.
                    </p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="120">
                <div class="step-card skel h-100">
                    <span class="step-num">02</span>
                    <div class="step-icon-wrap"><i class="fas fa-fingerprint"></i></div>
                    <h4 class="fw-black mb-2" style="font-size:1.1rem">Verify in Seconds</h4>
                    <p style="font-size:.88rem;font-weight:600;color:var(--text-muted);line-height:1.7;margin:0">
                        Complete biometric KYC via our mobile app. No branch visits, no waiting. Verified in under 90 seconds.
                    </p>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="240">
                <div class="step-card skel h-100">
                    <span class="step-num">03</span>
                    <div class="step-icon-wrap"><i class="fab fa-bluetooth-b"></i></div>
                    <h4 class="fw-black mb-2" style="font-size:1.1rem">Drive &amp; Unlock</h4>
                    <p style="font-size:.88rem;font-weight:600;color:var(--text-muted);line-height:1.7;margin:0">
                        Your digital key activates on pickup. Unlock via Bluetooth. No keys exchanged, no queues, just you and the road.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 4 — FEATURES / EDGE                                             -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5" style="background:var(--bg-surface);border-top:1px solid var(--border-soft)">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-5" data-aos="fade-right">
                <span class="sec-label">The SmartDrive Edge</span>
                <h2 class="sec-heading mt-1 mb-4">Redefining What Rental Means.</h2>
                <p style="font-size:.97rem;font-weight:600;color:var(--text-muted);line-height:1.8;max-width:440px">
                    We've eliminated every friction point from traditional car rental. The result is an experience that feels effortless — because it was engineered to be.
                </p>
                <div class="mt-4 d-flex align-items-center gap-3">
                    <img src="https://randomuser.me/api/portraits/men/22.jpg" style="width:38px;height:38px;border-radius:50%;border:2px solid var(--c-mint);object-fit:cover" alt="">
                    <img src="https://randomuser.me/api/portraits/women/33.jpg" style="width:38px;height:38px;border-radius:50%;border:2px solid var(--c-mint);object-fit:cover;margin-left:-14px" alt="">
                    <img src="https://randomuser.me/api/portraits/men/55.jpg" style="width:38px;height:38px;border-radius:50%;border:2px solid var(--c-mint);object-fit:cover;margin-left:-14px" alt="">
                    <div>
                        <div style="font-weight:800;font-size:.85rem;color:var(--text-dark)">28,400+ Happy Drivers</div>
                        <div style="font-size:.72rem;font-weight:600;color:var(--text-muted)">
                            ★★★★★ <span style="color:var(--c-mint)">4.97</span> avg rating
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="row g-3">
                    <?php
                    $features = [
                        ['fas fa-shield-alt','Fully Insured','Zero-liability comprehensive insurance included on every booking. Drive with complete peace of mind.'],
                        ['fas fa-map-marked-alt','Doorstep Delivery','Hotel, airport, or office — your vehicle arrives wherever you need it, 30 min ahead of schedule.'],
                        ['fas fa-tachometer-alt','Live Telemetry','Real-time GPS, fuel level, and health diagnostics streamed to your app throughout the trip.'],
                        ['fas fa-undo-alt','Flexible Cancellation','Cancel or modify up to 4 hours before pickup with zero penalty. Life happens — we get it.'],
                        ['fas fa-headset','24/7 Concierge','Dedicated luxury concierge available around the clock. One call resolves everything.'],
                        ['fas fa-leaf','Carbon Offset','Every rental is offset via verified reforestation projects. Drive premium, tread lightly.'],
                    ];
                    foreach ($features as $i => [$icon, $title, $desc]):
                    ?>
                    <div class="col-md-6 col-sm-6" data-aos="fade-up" data-aos-delay="<?= $i * 60 ?>">
                        <div class="feature-card skel h-100">
                            <div class="feat-icon"><i class="<?= $icon ?>"></i></div>
                            <h5 class="fw-black mb-2" style="font-size:.97rem"><?= $title ?></h5>
                            <p style="font-size:.83rem;font-weight:600;color:var(--text-muted);line-height:1.7;margin:0"><?= $desc ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 5 — FLEET CARDS                                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5 mt-2">
    <div class="container py-4">
        <!-- header -->
        <div class="row align-items-center mb-4 g-3" data-aos="fade-up">
            <div class="col-lg-6">
                <span class="sec-label">Live Inventory</span>
                <h2 class="sec-heading mt-1">Available Masterpieces.</h2>
            </div>
            <div class="col-lg-6">
                <div class="filter-wrap justify-content-lg-end">
                    <button class="filter-btn active" data-filter="all">All Fleet</button>
                    <button class="filter-btn" data-filter="luxury">Premium Luxury</button>
                    <button class="filter-btn" data-filter="suv">SUVs &amp; 4x4</button>
                </div>
            </div>
        </div>

        <!-- cards grid -->
        <div class="row g-4" id="fleet-grid">
            <?php if (!empty($view_data['featured'])): foreach ($view_data['featured'] as $idx => $car):
                $nameFull = ($car['brand'] ?? '') . ' ' . ($car['name'] ?? '') . ' ' . ($car['model'] ?? '');
                $isSUV    = (bool)preg_match('/suv|4x4|thar|urus|autobiography|defender/i', $nameFull);
                $cat      = $isSUV ? 'suv' : 'luxury';
                $imgs     = parseCarImages($car['image'] ?? '', $base_url, $car['brand'] ?? '');
                $delay    = ($idx % 3) * 80;
            ?>
            <div class="col-lg-4 col-md-6 car-node" data-category="<?= $cat ?>"
                 data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                <div class="car-card tilt-card">
                    <!-- image -->
                    <div class="car-img-wrap">
                        <div class="swiper car-swiper" style="width:100%;height:100%">
                            <div class="swiper-wrapper">
                                <?php foreach ($imgs as $img): ?>
                                <div class="swiper-slide">
                                    <img src="<?= htmlspecialchars($img) ?>" loading="lazy"
                                         alt="<?= htmlspecialchars($car['brand'] ?? '') ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($imgs) > 1): ?>
                            <div class="swiper-pagination"></div>
                            <?php endif; ?>
                        </div>
                        <div class="car-badge">
                            <i class="fas fa-bolt text-warning me-1"></i> Instant Book
                        </div>
                        <div class="car-loc">
                            <i class="fas fa-map-marker-alt me-1" style="color:#f87171"></i>
                            <?= htmlspecialchars($car['city_name'] ?? 'Hub') ?>
                        </div>
                        <!-- category ribbon -->
                        <div style="position:absolute;top:14px;right:14px;z-index:3">
                            <span style="background:<?= $isSUV ? 'rgba(99,102,241,.85)' : 'rgba(236,72,153,.85)' ?>;color:#fff;font-size:.65rem;font-weight:800;letter-spacing:.1em;padding:.25rem .7rem;border-radius:50px;text-transform:uppercase;backdrop-filter:blur(6px)">
                                <?= $isSUV ? 'SUV' : 'Luxury' ?>
                            </span>
                        </div>
                    </div>

                    <!-- body -->
                    <div class="car-body">
                        <div class="car-brand"><?= htmlspecialchars($car['brand'] ?? '') ?></div>
                        <div class="car-name"><?= htmlspecialchars($car['name'] ?? '') ?></div>

                        <div class="spec-grid">
                            <div class="spec-pill"><i class="fas fa-cogs"></i><span>Auto</span></div>
                            <div class="spec-pill"><i class="fas fa-users"></i><span>5 Seats</span></div>
                            <div class="spec-pill"><i class="fas fa-gas-pump"></i><span>Petrol</span></div>
                        </div>

                        <div class="car-footer">
                            <div>
                                <div class="car-price">
                                    <sup>₹</sup><?= number_format((float)($car['base_price'] ?? 0)) ?>
                                </div>
                                <small class="car-price" style="font-size:.7rem;font-weight:600;color:var(--text-muted)">per day + 18% GST</small>
                            </div>
                            <a href="<?= htmlspecialchars($base_url) ?>customer/book_car.php?id=<?= (int)$car['id'] ?>"
                               class="btn btn-mint btn-ripple rounded-pill px-4 py-2 fw-black" style="font-size:.85rem">
                                Reserve <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-car fa-4x mb-3" style="color:var(--border-soft)"></i>
                <h5 class="fw-bold" style="color:var(--text-muted)">Fleet inventory refreshing…</h5>
            </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5">
            <a href="<?= htmlspecialchars($base_url) ?>customer/search_cars.php"
               class="btn btn-ripple rounded-pill px-5 py-3 fw-black"
               style="border:2px solid var(--text-dark);color:var(--text-dark);font-size:.92rem">
                Explore Full Fleet &nbsp;<i class="fas fa-long-arrow-alt-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 6 — STATS BAND                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="stats-band py-5">
    <div class="container py-3">
        <div class="row text-center g-0">
            <?php
            $stats_display = [
                [$view_data['stats']['cars'],    '+', 'Premium Cars',      'fas fa-car'],
                [$view_data['stats']['users'],   '+', 'Verified Drivers',  'fas fa-users'],
                [$view_data['stats']['hubs'],    '',  'City Hubs',         'fas fa-city'],
                [$view_data['stats']['bookings'],'+', 'Trips Completed',   'fas fa-route'],
            ];
            foreach ($stats_display as $si => [$val, $suffix, $label, $icon]):
            ?>
            <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="<?= $si*80 ?>">
                <div class="stat-item">
                    <i class="<?= $icon ?> mb-3 d-block" style="font-size:1.4rem;color:rgba(140,229,195,.4)"></i>
                    <span class="stat-num odometer" data-value="<?= $val ?>">0</span>
                    <span style="color:var(--c-mint);font-size:1.8rem;font-weight:900;vertical-align:top;line-height:1"><?= $suffix ?></span>
                    <div class="stat-label"><?= $label ?></div>
                </div>
                <?php if ($si < 3): ?>
                <div class="stat-divider d-none d-md-block" style="position:absolute;right:0;top:50%;transform:translateY(-50%);height:40px"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 7 — FARE ESTIMATOR                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5 my-3">
    <div class="container py-4">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5" data-aos="fade-right">
                <span class="sec-label">Smart Planning</span>
                <h2 class="sec-heading mt-1 mb-4">Calculate Your Cost.</h2>
                <p style="font-size:.97rem;font-weight:600;color:var(--text-muted);line-height:1.8;margin-bottom:2rem">
                    Complete transparency before you book. Our live pricing engine calculates
                    base fare, GST, and estimated fuel costs in real-time.
                </p>

                <div class="d-flex flex-column gap-3">
                    <div style="background:var(--bg-surface);border:1px solid var(--border-soft);border-radius:var(--radius-sm);padding:1.2rem 1.4rem;display:flex;align-items:center;gap:1rem">
                        <div style="width:44px;height:44px;border-radius:12px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-gem" style="color:#f59e0b;font-size:1.1rem"></i></div>
                        <div>
                            <div style="font-weight:800;font-size:.9rem">500 Bonus Points on Signup</div>
                            <div style="font-size:.78rem;font-weight:600;color:var(--text-muted)">Redeemable for free weekend upgrades</div>
                        </div>
                    </div>
                    <div style="background:var(--bg-surface);border:1px solid var(--border-soft);border-radius:var(--radius-sm);padding:1.2rem 1.4rem;display:flex;align-items:center;gap:1rem">
                        <div style="width:44px;height:44px;border-radius:12px;background:rgba(140,229,195,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-lock" style="color:var(--c-mint);font-size:1.1rem"></i></div>
                        <div>
                            <div style="font-weight:800;font-size:.9rem">Price Lock Guarantee</div>
                            <div style="font-size:.78rem;font-weight:600;color:var(--text-muted)">Your quoted price is final — always</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7" data-aos="fade-left">
                <div class="estimator-card skel">
                    <h4 class="fw-black mb-4" style="color:#fff"><i class="fas fa-calculator me-2 text-mint"></i> Pricing Engine</h4>

                    <label style="font-size:.68rem;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.55)">Vehicle Tier</label>
                    <select id="estCarType" class="est-select mt-2 mb-4">
                        <option value="6500">Premium Luxury (Porsche, BMW M-Series) — ₹6,500/day</option>
                        <option value="4500">Full-Size SUV (Range Rover, Urus) — ₹4,500/day</option>
                        <option value="2500">Executive Sedan (Audi A6, E-Class) — ₹2,500/day</option>
                        <option value="1200">Compact Premium (Mini, A3) — ₹1,200/day</option>
                    </select>

                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
                        <label style="font-size:.68rem;font-weight:800;letter-spacing:.2em;text-transform:uppercase;color:rgba(255,255,255,.55)">Rental Duration</label>
                        <span id="estDaysLabel" style="background:rgba(140,229,195,.15);color:var(--c-mint);padding:.25rem .9rem;border-radius:50px;font-size:.85rem;font-weight:800">3 Days</span>
                    </div>
                    <input type="range" id="estDaysSlider" class="range-track" min="1" max="30" value="3">

                    <div class="est-result mt-4">
                        <div style="display:flex;justify-content:space-between;margin-bottom:.9rem">
                            <span style="font-weight:700;color:rgba(255,255,255,.65)">Base Fare</span>
                            <span id="estSubtotal" style="font-weight:800;color:#fff;font-size:1rem">₹19,500</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:.9rem">
                            <span style="font-weight:700;color:rgba(255,255,255,.65)">GST (18%)</span>
                            <span id="estTaxes" style="font-weight:800;color:#fff;font-size:1rem">₹3,510</span>
                        </div>
                        <div style="height:1px;background:rgba(255,255,255,.1);margin-bottom:.9rem"></div>
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <span style="font-weight:900;font-size:.75rem;letter-spacing:.2em;text-transform:uppercase;color:#fff">Total Payable</span>
                            <span id="estTotal" style="font-size:2rem;font-weight:900;background:linear-gradient(135deg,var(--c-mint),var(--c-sage));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">₹23,010</span>
                        </div>
                    </div>

                    <div style="margin-top:1rem;text-align:right">
                        <button class="btn btn-mint btn-ripple rounded-pill px-4 py-2 fw-black" style="font-size:.85rem"
                                onclick="document.getElementById('booking-widget').scrollIntoView({behavior:'smooth'})">
                            Book at This Price <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 8 — TESTIMONIALS (NEW)                                          -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5" style="background:var(--bg-surface);border-top:1px solid var(--border-soft)">
    <div class="container py-4">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="sec-label">Verified Reviews</span>
            <h2 class="sec-heading mt-1">Words from the Fleet.</h2>
            <div style="display:flex;align-items:center;justify-content:center;gap:.6rem;margin-top:.8rem">
                <div style="color:#f59e0b;letter-spacing:.06em">★★★★★</div>
                <span style="font-weight:800;font-size:.92rem;color:var(--text-dark)">4.97 / 5</span>
                <span style="font-size:.82rem;font-weight:600;color:var(--text-muted)">from 11,400+ reviews</span>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($view_data['reviews'] as $ri => $rev): ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $ri * 100 ?>">
                <div class="review-card h-100">
                    <div class="review-stars">★★★★★</div>
                    <p class="review-text">"<?= htmlspecialchars($rev['text']) ?>"</p>
                    <div class="reviewer">
                        <img src="<?= htmlspecialchars($rev['avatar']) ?>" alt="<?= htmlspecialchars($rev['name']) ?>">
                        <div>
                            <div class="reviewer-name"><?= htmlspecialchars($rev['name']) ?></div>
                            <div class="reviewer-role"><?= htmlspecialchars($rev['role']) ?></div>
                            <div class="review-car"><i class="fas fa-car"></i><?= htmlspecialchars($rev['car']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 9 — APP PROMO                                                   -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5 my-3">
    <div class="container">
        <div class="app-promo p-5 skel" data-aos="zoom-in-up">
            <div class="row align-items-center g-4 position-relative" style="z-index:2">
                <div class="col-lg-6">
                    <span style="background:rgba(26,36,33,.12);color:#1a2421;font-size:.72rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase;padding:.4rem 1rem;border-radius:50px;display:inline-block;margin-bottom:1.2rem;border:1px solid rgba(26,36,33,.12)">
                        <i class="fab fa-bluetooth-b me-1" style="color:#3b82f6"></i> Digital Key Technology
                    </span>
                    <h2 class="fw-black mb-3" style="font-size:clamp(1.8rem,4vw,2.8rem);color:#1a2421;line-height:1.1">Your Pocket Garage.<br>Anywhere.</h2>
                    <p class="mb-4 fw-bold" style="color:rgba(26,36,33,.72);font-size:.97rem;line-height:1.75;max-width:440px">
                        Manage live trips, monitor telemetry, extend bookings, and unlock your vehicle via Bluetooth — all from the SmartDrive X app.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <button class="store-btn store-btn-dark btn-ripple">
                            <i class="fab fa-apple" style="font-size:1.3rem"></i>
                            <span><div style="font-size:.62rem;opacity:.7;letter-spacing:.08em">DOWNLOAD ON THE</div><div style="font-size:.95rem;font-weight:900">App Store</div></span>
                        </button>
                        <button class="store-btn store-btn-light btn-ripple">
                            <i class="fab fa-google-play" style="font-size:1.2rem;color:#34A853"></i>
                            <span><div style="font-size:.62rem;opacity:.7;letter-spacing:.08em">GET IT ON</div><div style="font-size:.95rem;font-weight:900">Google Play</div></span>
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-3">
                        <i class="fas fa-star" style="color:#f59e0b;font-size:.85rem"></i>
                        <span style="font-weight:800;font-size:.82rem;color:#1a2421">4.9 on App Store</span>
                        <span style="color:rgba(26,36,33,.4);font-size:.75rem">&bull;</span>
                        <span style="font-weight:600;font-size:.82rem;color:rgba(26,36,33,.6)">45,000+ ratings</span>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block position-relative" style="height:380px">
                    <img src="https://images.unsplash.com/photo-1512428559087-560fa5ceab42?q=80&w=600&auto=format"
                         class="app-promo-img" id="appPromoImg" alt="SmartDrive X App" loading="lazy">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 10 — FAQ                                                        -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5" style="background:var(--bg-surface);border-top:1px solid var(--border-soft)">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5" data-aos="fade-up">
                    <span class="sec-label">Knowledge Base</span>
                    <h2 class="sec-heading mt-1">Frequently Asked.</h2>
                </div>

                <?php
                $faqs = [
                    ['What documents do I need to rent?', 'A valid original Driver\'s License plus one government-issued ID (Aadhaar or Passport). International drivers require an IDP. All verification happens digitally via our app in under 90 seconds.'],
                    ['Is there a security deposit?', 'Yes — a fully refundable deposit is held on your card at pickup. Amounts vary by tier: ₹5,000 for Executive Sedans, up to ₹25,000 for Premium Luxury. Released within 3–5 business days after safe return.'],
                    ['Can I return the car early?', 'Absolutely. Returns before your scheduled date receive a prorated refund for unused days (excluding the first 24 hours). No penalty — we pro-rate it fairly.'],
                    ['What happens if I return late?', 'A 1-hour grace period applies. Beyond that, late returns are charged at 2× the hourly rate. Extend your trip anytime from the app before it expires to avoid the surcharge.'],
                    ['Is insurance included?', 'Yes. Every booking includes zero-liability comprehensive insurance covering collision, theft, and third-party liability. No add-on required, no surprise charges.'],
                    ['Can I take the car interstate?', 'Domestic interstate travel is permitted in most states. Certain border zones and restricted regions require a ₹500 permit add-on. Check the route tool in the app before departure.'],
                ];
                foreach ($faqs as $fi => [$q, $a]):
                ?>
                <div class="faq-item skel" data-aos="fade-up" data-aos-delay="<?= $fi * 50 ?>">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        <span><?= htmlspecialchars($q) ?></span>
                        <div class="faq-icon"><i class="fas fa-plus" style="font-size:.75rem"></i></div>
                    </div>
                    <div class="faq-a"><?= htmlspecialchars($a) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SECTION 11 — NEWSLETTER                                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<section class="py-5 my-3">
    <div class="container">
        <div class="nl-section skel" data-aos="zoom-in">
            <div class="row align-items-center g-4 position-relative" style="z-index:2">
                <div class="col-lg-6">
                    <span class="sec-label" style="color:var(--c-mint)">Elite Inner Circle</span>
                    <h2 class="fw-black mt-1 mb-3" style="font-size:clamp(1.7rem,4vw,2.5rem);color:#fff;line-height:1.1">Join the Fleet.<br>Get 500 Points Free.</h2>
                    <p style="font-weight:600;color:rgba(255,255,255,.65);font-size:.95rem;line-height:1.75;margin:0">
                        Subscribe for exclusive member rates, early access to new vehicles, and a
                        <strong style="color:var(--c-mint)">500 Bonus Point</strong> welcome gift. Zero spam, ever.
                    </p>
                </div>
                <div class="col-lg-6">
                    <form id="nlForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="ajax_action" value="newsletter_subscribe">

                        <div class="nl-input-wrap">
                            <input type="email" name="email" id="nlEmail" class="nl-input"
                                   placeholder="yourname@email.com" required>
                            <button type="submit" class="nl-submit" id="nlBtn">
                                Subscribe <i class="fas fa-paper-plane ms-1"></i>
                            </button>
                        </div>
                        <div id="nlMsg" style="display:none;margin-top:.6rem;font-size:.82rem;font-weight:700;padding:0 .5rem"></div>
                    </form>
                    <p style="font-size:.72rem;font-weight:600;color:rgba(255,255,255,.35);margin-top:.6rem;padding-left:.5rem">
                        <i class="fas fa-lock me-1"></i> We respect your privacy. Unsubscribe anytime.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  MODALS                                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->

<!-- Video Modal -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-black border-0 rounded-4 overflow-hidden">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 z-3 shadow-none"
                    data-bs-dismiss="modal" style="filter:brightness(2)"></button>
            <div class="ratio ratio-16x9">
                <iframe id="promoVideo" src="https://www.youtube.com/embed/5U_ZpUo6YgI?enablejsapi=1&rel=0&modestbranding=1"
                        title="SmartDrive X Cinematic" allowfullscreen></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Quick Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 overflow-hidden" style="border-radius:var(--radius-lg)">
            <div class="modal-glass">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
                    <div>
                        <span class="sec-label">Concierge Hotline</span>
                        <h4 class="fw-black mt-1 mb-0" style="color:var(--text-dark)">We'll Call You in 15 Min</h4>
                    </div>
                    <button class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                </div>

                <form id="contactForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="ajax_action" value="quick_contact">

                    <div class="mb-3">
                        <label class="fw-extrabold small text-uppercase tracking-wider" style="font-size:.65rem;color:var(--text-muted);display:block;margin-bottom:.4rem">Your Name</label>
                        <input type="text" name="name" class="form-ctrl" placeholder="Arjun Mehta" required>
                    </div>
                    <div class="mb-4">
                        <label class="fw-extrabold small text-uppercase tracking-wider" style="font-size:.65rem;color:var(--text-muted);display:block;margin-bottom:.4rem">Mobile Number</label>
                        <input type="tel" name="phone" class="form-ctrl" placeholder="98765 43210" required maxlength="10" pattern="[6-9][0-9]{9}">
                    </div>

                    <div id="contactMsg" style="display:none;margin-bottom:1rem;font-size:.85rem;font-weight:700"></div>

                    <button type="submit" class="btn btn-mint btn-ripple w-100 py-3 rounded-3 fw-black" id="contactBtn">
                        <i class="fas fa-headset me-2"></i> Request Callback
                    </button>
                </form>

                <div class="d-flex align-items-center justify-content-center gap-2 mt-3">
                    <i class="fas fa-shield-alt text-mint" style="font-size:.8rem"></i>
                    <span style="font-size:.75rem;font-weight:600;color:var(--text-muted)">Your data is 100% confidential</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!--  SCRIPTS                                                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
/**
 * ═══════════════════════════════════════════════════════════════════════
 * 🛡️ SMARTDRIVE X — TITAN FRONTEND ENGINE V7
 * Fixed : filter class mismatch | newsletter CSS | skeleton on hero
 *         border-color undefined | tracking-widest missing
 * Added : typed text, FAQ accordion, dark mode, ripple, progress bar
 * ═══════════════════════════════════════════════════════════════════════
 */
'use strict';

/* ── 1. SCROLL PROGRESS BAR ─────────────────────────────────────────────── */
const progressBar = document.getElementById('scroll-progress');
window.addEventListener('scroll', () => {
    const pct = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
    if (progressBar) progressBar.style.width = pct + '%';
}, { passive: true });

/* ── 2. BACK TO TOP ─────────────────────────────────────────────────────── */
const backTop = document.getElementById('back-top');
window.addEventListener('scroll', () => {
    if (backTop) backTop.classList.toggle('show', window.scrollY > 500);
}, { passive: true });

/* ── 3. SKELETON REMOVAL ────────────────────────────────────────────────── */
window.addEventListener('load', () => {
    setTimeout(() => document.querySelectorAll('.skel').forEach(el => el.classList.remove('skel')), 250);
});

/* ── 4. DARK MODE TOGGLE ────────────────────────────────────────────────── */
const themeToggle = document.getElementById('theme-toggle');
const themeIcon   = document.getElementById('theme-icon');
const savedTheme  = localStorage.getItem('sdx-theme') || 'light';

function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    if (themeIcon) {
        themeIcon.className = t === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    localStorage.setItem('sdx-theme', t);
}
applyTheme(savedTheme);
if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });
}

/* ── 5. RIPPLE BUTTONS ──────────────────────────────────────────────────── */
document.addEventListener('click', e => {
    const btn = e.target.closest('.btn-ripple');
    if (!btn) return;
    const circle = document.createElement('span');
    const d = Math.max(btn.clientWidth, btn.clientHeight);
    const rect = btn.getBoundingClientRect();
    circle.className = 'ripple-wave';
    Object.assign(circle.style, {
        width: d + 'px', height: d + 'px',
        left: (e.clientX - rect.left - d / 2) + 'px',
        top:  (e.clientY - rect.top  - d / 2) + 'px',
    });
    btn.appendChild(circle);
    setTimeout(() => circle.remove(), 700);
});

/* ── 6. TYPED HERO TEXT ─────────────────────────────────────────────────── */
(function() {
    const el    = document.getElementById('typed-target');
    if (!el) return;
    const words = ['Horizon.', 'Journey.', 'Roads.', 'Experience.', 'Luxury.'];
    let wi = 0, ci = 0, deleting = false;

    function type() {
        const word = words[wi];
        el.textContent = deleting ? word.substring(0, ci--) : word.substring(0, ci++);
        el.style.borderRight = '3px solid var(--c-mint)';

        if (!deleting && ci > word.length) {
            setTimeout(() => { deleting = true; type(); }, 2200);
            return;
        }
        if (deleting && ci < 0) {
            deleting = false;
            ci = 0;
            wi = (wi + 1) % words.length;
        }
        setTimeout(type, deleting ? 55 : 100);
    }
    type();
})();

/* ── 7. DATE VALIDATION ─────────────────────────────────────────────────── */
const startInput = document.getElementById('home_start');
const endInput   = document.getElementById('home_end');
if (startInput && endInput) {
    startInput.addEventListener('change', function() {
        endInput.disabled = false;
        endInput.min      = this.value;
        const nextDay     = new Date(this.value);
        nextDay.setDate(nextDay.getDate() + 1);
        if (!endInput.value || endInput.value <= this.value) {
            endInput.value = nextDay.toISOString().split('T')[0];
        }
    });
}

/* ── 8. SWIPER INIT ─────────────────────────────────────────────────────── */
document.querySelectorAll('.car-swiper').forEach((el, i) => {
    new Swiper(el, {
        loop: true,
        grabCursor: true,
        autoplay: { delay: 3500 + i * 200, disableOnInteraction: false },
        pagination: { el: el.querySelector('.swiper-pagination'), dynamicBullets: true },
        effect: 'fade',
    });
});

/* ── 9. FLEET FILTER (FIXED: was .filter-trigger, now .filter-btn) ──────── */
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;

        document.querySelectorAll('.car-node').forEach(node => {
            const match = filter === 'all' || node.dataset.category === filter;
            if (match) {
                node.style.display = '';
                requestAnimationFrame(() => {
                    node.style.opacity   = '0';
                    node.style.transform = 'scale(.94)';
                    setTimeout(() => {
                        node.style.transition = 'opacity .4s ease, transform .4s ease';
                        node.style.opacity    = '1';
                        node.style.transform  = 'scale(1)';
                    }, 10);
                });
            } else {
                node.style.transition = 'opacity .3s ease, transform .3s ease';
                node.style.opacity    = '0';
                node.style.transform  = 'scale(.94)';
                setTimeout(() => { node.style.display = 'none'; }, 320);
            }
        });
    });
});

/* ── 10. 3D TILT CARDS ──────────────────────────────────────────────────── */
document.querySelectorAll('.tilt-card').forEach(card => {
    card.addEventListener('mousemove', e => {
        const r = card.getBoundingClientRect();
        const x = ((e.clientX - r.left) / r.width  - .5) *  6;
        const y = ((e.clientY - r.top)  / r.height - .5) * -6;
        card.style.transform = `perspective(900px) rotateX(${y}deg) rotateY(${x}deg) translateY(-8px)`;
    });
    card.addEventListener('mouseleave', () => {
        card.style.transform = 'perspective(900px) rotateX(0) rotateY(0) translateY(0)';
    });
});

/* ── 11. FARE ESTIMATOR ─────────────────────────────────────────────────── */
(function() {
    const carType = document.getElementById('estCarType');
    const slider  = document.getElementById('estDaysSlider');
    if (!carType || !slider) return;

    const fmt = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

    function calc() {
        const base  = parseInt(carType.value);
        const days  = parseInt(slider.value);
        const sub   = base * days;
        const tax   = Math.round(sub * .18);
        const total = sub + tax;

        document.getElementById('estDaysLabel').textContent  = `${days} Day${days > 1 ? 's' : ''}`;
        document.getElementById('estSubtotal').textContent   = fmt.format(sub);
        document.getElementById('estTaxes').textContent      = fmt.format(tax);
        document.getElementById('estTotal').textContent      = fmt.format(total);
    }

    carType.addEventListener('change', calc);
    slider.addEventListener('input', calc);
    calc();
})();

/* ── 12. FAQ CUSTOM ACCORDION ───────────────────────────────────────────── */
function toggleFaq(qEl) {
    const item = qEl.closest('.faq-item');
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
}

/* ── 13. PARALLAX APP IMAGE ─────────────────────────────────────────────── */
const appImg = document.getElementById('appPromoImg');
window.addEventListener('scroll', () => {
    if (!appImg) return;
    appImg.style.transform = `translateY(${window.scrollY * -.12}px)`;
}, { passive: true });

/* ── 14. ODOMETER COUNTER ───────────────────────────────────────────────── */
const odometerObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const el    = entry.target;
        const val   = parseInt(el.dataset.value) || 0;
        const dur   = 2200;
        let start   = null;

        (function step(ts) {
            if (!start) start = ts;
            const p    = Math.min((ts - start) / dur, 1);
            const ease = 1 - Math.pow(2, -10 * p); // expo ease out
            el.textContent = Math.floor(ease * val).toLocaleString('en-IN');
            if (p < 1) requestAnimationFrame(step);
        })(performance.now());

        odometerObserver.unobserve(el);
    });
}, { threshold: .5 });
document.querySelectorAll('.odometer').forEach(el => odometerObserver.observe(el));

/* ── 15. SOCIAL PROOF TOASTS ────────────────────────────────────────────── */
(function() {
    const data  = <?= json_encode($view_data['social']) ?>;
    if (!data || !data.length) return;

    const stack = document.getElementById('toast-stack');
    let idx     = 0;

    function showToast() {
        const item = data[idx];
        const id   = 'toast-' + Date.now();

        const el = document.createElement('div');
        el.id        = id;
        el.className = 'sdx-toast';
        el.innerHTML = `
            <div class="toast-icon"><i class="fas fa-key"></i></div>
            <div>
                <div style="font-weight:800;font-size:.85rem;color:var(--text-dark)">Just Booked</div>
                <div style="font-weight:700;font-size:.78rem;color:var(--text-muted);max-width:210px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${item.label}</div>
                <div style="font-size:.72rem;font-weight:600;color:var(--text-muted)"><i class="fas fa-map-marker-alt me-1" style="color:#ef4444"></i>${item.loc} &bull; ${item.ago}</div>
            </div>`;

        stack.appendChild(el);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => el.classList.add('show'));
        });
        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 600);
        }, 5500);

        idx = (idx + 1) % data.length;
        setTimeout(showToast, 12000 + Math.random() * 8000);
    }
    setTimeout(showToast, 6000);
})();

/* ── 16. AJAX — NEWSLETTER ──────────────────────────────────────────────── */
(function() {
    const form = document.getElementById('nlForm');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn  = document.getElementById('nlBtn');
        const msg  = document.getElementById('nlMsg');
        const mail = document.getElementById('nlEmail');

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail.value.trim())) {
            showMsg(msg, 'text-danger', 'fas fa-exclamation-circle', 'Please enter a valid email address.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';

        try {
            const res  = await fetch('', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.status === 'success') {
                showMsg(msg, 'text-success', 'fas fa-check-circle', data.message);
                mail.value          = '';
                btn.innerHTML       = 'Subscribed! <i class="fas fa-check ms-1"></i>';
                btn.style.background = '#4ade80';
            } else {
                showMsg(msg, 'text-danger', 'fas fa-exclamation-triangle', data.message);
                btn.disabled = false;
                btn.innerHTML = 'Subscribe <i class="fas fa-paper-plane ms-1"></i>';
            }
        } catch {
            showMsg(msg, 'text-danger', 'fas fa-wifi', 'Network error — please try again.');
            btn.disabled = false;
            btn.innerHTML = 'Subscribe <i class="fas fa-paper-plane ms-1"></i>';
        }
    });
})();

/* ── 17. AJAX — QUICK CONTACT ───────────────────────────────────────────── */
(function() {
    const form = document.getElementById('contactForm');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn = document.getElementById('contactBtn');
        const msg = document.getElementById('contactMsg');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Connecting…';

        try {
            const res  = await fetch('', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.status === 'success') {
                showMsg(msg, 'text-success', 'fas fa-check-circle', data.message);
                btn.innerHTML = 'Request Sent! <i class="fas fa-check ms-1"></i>';
                setTimeout(() => {
                    const m = bootstrap.Modal.getInstance(document.getElementById('contactModal'));
                    if (m) m.hide();
                }, 2500);
            } else {
                showMsg(msg, 'text-danger', 'fas fa-times-circle', data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-headset me-2"></i> Request Callback';
            }
        } catch {
            showMsg(msg, 'text-danger', 'fas fa-wifi', 'Network error. Try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-headset me-2"></i> Request Callback';
        }
    });
})();

/* ── 18. VIDEO MODAL CONTROL ────────────────────────────────────────────── */
(function() {
    const vModal = document.getElementById('videoModal');
    const iframe = document.getElementById('promoVideo');
    if (!vModal || !iframe) return;

    const post = (fn) => iframe.contentWindow.postMessage(
        JSON.stringify({ event: 'command', func: fn, args: '' }), '*'
    );
    vModal.addEventListener('shown.bs.modal', () => post('playVideo'));
    vModal.addEventListener('hidden.bs.modal', () => post('pauseVideo'));
})();

/* ── 19. AOS INIT ───────────────────────────────────────────────────────── */
AOS.init({ duration: 850, once: true, offset: 60, easing: 'ease-out-quart' });

/* ── UTILITY: show message ─────────────────────────────────────────────── */
function showMsg(el, colorClass, iconClass, text) {
    el.style.display = 'block';
    el.className     = colorClass;
    el.innerHTML     = `<i class="${iconClass} me-1"></i>${text}`;
}
</script>

<?php include 'includes/footer.php'; ?>