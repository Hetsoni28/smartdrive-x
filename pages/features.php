<?php
session_start();
$base_url = "http://localhost/smartdrive_x/";
$page_title = "Features | SmartDrive X";
include '../includes/header.php';
?>

<style>
    .features-hero {
        background: linear-gradient(135deg, #1a1e16 0%, #2b3327 50%, #3d4a37 100%);
        padding: 120px 0 100px; color: white; position: relative; overflow: hidden;
    }
    .features-hero::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(136,156,124,0.08)"/></svg>') repeat;
        background-size: 30px 30px; pointer-events: none;
    }
    .feature-card {
        background: white; border-radius: 24px; padding: 40px 30px; height: 100%;
        border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.04);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; overflow: hidden;
    }
    .feature-card::before {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
        background: linear-gradient(90deg, var(--gradient-start, #4a5c43), var(--gradient-end, #889c7c));
        opacity: 0; transition: opacity 0.3s ease;
    }
    .feature-card:hover { transform: translateY(-10px); box-shadow: 0 25px 50px rgba(74,92,67,0.15); }
    .feature-card:hover::before { opacity: 1; }
    .feature-num { font-size: 5rem; font-weight: 900; color: rgba(74,92,67,0.06); position: absolute; top: 10px; right: 20px; line-height: 1; }
    .feature-icon { width: 70px; height: 70px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 20px; }
    .section-label { color: #889c7c; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; }
    .workflow-step { text-align: center; position: relative; }
    .workflow-step::after {
        content: '→'; position: absolute; right: -15px; top: 50px; font-size: 2rem; color: #889c7c; font-weight: 900;
    }
    .workflow-step:last-child::after { display: none; }
    .workflow-circle {
        width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px; font-size: 2rem; border: 3px solid rgba(74,92,67,0.15);
        transition: all 0.3s ease;
    }
    .workflow-step:hover .workflow-circle { transform: scale(1.1); border-color: #4a5c43; }
    .tech-badge { display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; border-radius: 50px; background: white; border: 1px solid rgba(0,0,0,0.08); font-weight: 700; font-size: 0.9rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03); transition: all 0.3s ease; }
    .tech-badge:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    @media(max-width: 768px) { .workflow-step::after { display: none; } }
</style>

<!-- HERO -->
<section class="features-hero">
    <div class="container position-relative z-2 text-center">
        <span class="section-label d-block mb-3" data-aos="fade-up"><i class="fas fa-star me-2"></i>Platform Capabilities</span>
        <h1 class="fw-black display-3 mb-4" data-aos="fade-up" data-aos-delay="100">Features That<br><span style="color: #889c7c;">Move You Forward</span></h1>
        <p class="lead opacity-75 fw-bold mb-0 mx-auto" style="max-width: 600px;" data-aos="fade-up" data-aos-delay="200">Everything you need for a seamless car rental experience — from instant bookings to automated settlements.</p>
    </div>
</section>

<!-- CORE FEATURES GRID -->
<section class="py-5 my-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3">Core Features</span>
            <h2 class="fw-black display-5 text-dark">Built for Excellence</h2>
        </div>
        <div class="row g-4">
            <?php
            $features = [
                ['icon' => 'fa-search', 'title' => 'Smart Fleet Search', 'desc' => 'Filter by brand, city, price range, and availability. Real-time results with instant booking.', 'bg' => 'rgba(74,92,67,0.1)', 'color' => '#4a5c43', 'g1' => '#4a5c43', 'g2' => '#889c7c'],
                ['icon' => 'fa-calendar-check', 'title' => 'Multi-Stage Bookings', 'desc' => 'From pending to approved to confirmed to active — every booking transitions through secure status gates.', 'bg' => 'rgba(13,110,253,0.1)', 'color' => '#0d6efd', 'g1' => '#0d6efd', 'g2' => '#6ea8fe'],
                ['icon' => 'fa-credit-card', 'title' => 'Secure Payments', 'desc' => 'ACID-compliant transactions with invoice generation, GST calculations, and loyalty point rewards.', 'bg' => 'rgba(25,135,84,0.1)', 'color' => '#198754', 'g1' => '#198754', 'g2' => '#5cb85c'],
                ['icon' => 'fa-clock', 'title' => 'Late Return Engine', 'desc' => 'Automated penalty calculation with configurable grace periods, hourly rates, and GST compliance.', 'bg' => 'rgba(220,53,69,0.1)', 'color' => '#dc3545', 'g1' => '#dc3545', 'g2' => '#e4606d'],
                ['icon' => 'fa-bell', 'title' => 'Real-Time Notifications', 'desc' => 'Live polling engine with 5-second interval updates. Toast alerts, in-app inbox, and smart badges.', 'bg' => 'rgba(255,193,7,0.1)', 'color' => '#ffc107', 'g1' => '#ffc107', 'g2' => '#ffda6a'],
                ['icon' => 'fa-file-invoice', 'title' => 'Professional Invoicing', 'desc' => 'Auto-generated invoices with QR codes, GST breakdown, and print-ready PDF export support.', 'bg' => 'rgba(13,202,240,0.1)', 'color' => '#0dcaf0', 'g1' => '#0dcaf0', 'g2' => '#6edff6'],
                ['icon' => 'fa-chart-pie', 'title' => 'Admin Analytics', 'desc' => 'Revenue dashboards, MoM growth tracking, customer analytics, and fleet capacity monitoring.', 'bg' => 'rgba(111,66,193,0.1)', 'color' => '#6f42c1', 'g1' => '#6f42c1', 'g2' => '#a084d8'],
                ['icon' => 'fa-shield-alt', 'title' => 'Enterprise Security', 'desc' => 'Role-based access, prepared statements, CSRF protection, XSS sanitization, and session management.', 'bg' => 'rgba(74,92,67,0.1)', 'color' => '#2b3327', 'g1' => '#2b3327', 'g2' => '#4a5c43'],
                ['icon' => 'fa-ticket-alt', 'title' => 'Coupon & Loyalty', 'desc' => 'Dynamic coupon engine with percentage and flat discounts. Points-based loyalty tier system.', 'bg' => 'rgba(253,126,20,0.1)', 'color' => '#fd7e14', 'g1' => '#fd7e14', 'g2' => '#fda94e'],
            ];
            foreach ($features as $i => $f):
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo ($i % 3) * 100; ?>">
                <div class="feature-card" style="--gradient-start: <?php echo $f['g1']; ?>; --gradient-end: <?php echo $f['g2']; ?>;">
                    <span class="feature-num"><?php echo str_pad($i+1, 2, '0', STR_PAD_LEFT); ?></span>
                    <div class="feature-icon" style="background: <?php echo $f['bg']; ?>; color: <?php echo $f['color']; ?>;"><i class="fas <?php echo $f['icon']; ?>"></i></div>
                    <h5 class="fw-black text-dark mb-2"><?php echo $f['title']; ?></h5>
                    <p class="text-muted fw-bold small mb-0"><?php echo $f['desc']; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="py-5" style="background: #f8f9f7;">
    <div class="container py-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3">How It Works</span>
            <h2 class="fw-black display-5 text-dark">4 Steps to Your Journey</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $steps = [
                ['icon' => 'fa-search', 'title' => 'Browse & Select', 'desc' => 'Search our fleet, filter by preferences', 'bg' => '#e0eadb'],
                ['icon' => 'fa-paper-plane', 'title' => 'Book & Wait', 'desc' => 'Submit booking, admin reviews', 'bg' => '#d1ecf1'],
                ['icon' => 'fa-credit-card', 'title' => 'Pay & Confirm', 'desc' => 'Secure payment after approval', 'bg' => '#d4edda'],
                ['icon' => 'fa-car-side', 'title' => 'Drive & Enjoy', 'desc' => 'Pick up your car and hit the road', 'bg' => '#fff3cd'],
            ];
            foreach ($steps as $i => $s):
            ?>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $i * 100; ?>">
                <div class="workflow-step">
                    <div class="workflow-circle" style="background: <?php echo $s['bg']; ?>; color: #2b3327;">
                        <i class="fas <?php echo $s['icon']; ?>"></i>
                    </div>
                    <h6 class="fw-black text-dark mb-1"><?php echo $s['title']; ?></h6>
                    <small class="text-muted fw-bold"><?php echo $s['desc']; ?></small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- TECH STACK -->
<section class="py-5 my-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3">Under the Hood</span>
            <h2 class="fw-black display-5 text-dark">Our Tech Stack</h2>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-3" data-aos="fade-up" data-aos-delay="100">
            <?php
            $techs = [
                ['icon' => 'fab fa-php', 'name' => 'Core PHP 8+', 'color' => '#777BB4'],
                ['icon' => 'fas fa-database', 'name' => 'MySQL/MariaDB', 'color' => '#4479A1'],
                ['icon' => 'fab fa-bootstrap', 'name' => 'Bootstrap 5.3', 'color' => '#7952B3'],
                ['icon' => 'fab fa-js', 'name' => 'Vanilla JS (ES6+)', 'color' => '#F7DF1E'],
                ['icon' => 'fab fa-css3-alt', 'name' => 'CSS3 + AOS', 'color' => '#1572B6'],
                ['icon' => 'fab fa-font-awesome', 'name' => 'Font Awesome 6', 'color' => '#339AF0'],
                ['icon' => 'fas fa-server', 'name' => 'XAMPP/Apache', 'color' => '#D22128'],
                ['icon' => 'fas fa-lock', 'name' => 'Prepared Statements', 'color' => '#198754'],
            ];
            foreach ($techs as $t):
            ?>
            <div class="tech-badge"><i class="<?php echo $t['icon']; ?>" style="color: <?php echo $t['color']; ?>; font-size: 1.2rem;"></i> <?php echo $t['name']; ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5" style="background: linear-gradient(135deg, #2b3327, #4a5c43); color: white;">
    <div class="container text-center py-5" data-aos="zoom-in">
        <h2 class="fw-black display-5 mb-3">Experience It Yourself</h2>
        <p class="opacity-75 fw-bold mb-5 mx-auto" style="max-width: 500px;">Explore our fleet and see these features in action.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?php echo $base_url; ?>customer/search_cars.php" class="btn btn-light rounded-pill fw-bold px-5 py-3 shadow-lg"><i class="fas fa-compass me-2"></i>Explore Fleet</a>
            <a href="<?php echo $base_url; ?>register.php" class="btn btn-outline-light rounded-pill fw-bold px-5 py-3">Sign Up Free</a>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
