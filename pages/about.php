<?php
session_start();
include '../includes/db_connect.php';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

// Live Stats
$total_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM cars"))['c'] ?? 0;
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role_id = 2"))['c'] ?? 0;
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE booking_status != 'cancelled'"))['c'] ?? 0;
$total_cities = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM locations"))['c'] ?? 0;

$page_title = "About Us | SmartDrive X";
include '../includes/header.php';
?>

<style>
    .about-hero {
        background: linear-gradient(135deg, #1a1e16 0%, #2b3327 40%, #4a5c43 100%);
        padding: 120px 0 100px; color: white; position: relative; overflow: hidden;
    }
    .about-hero::before {
        content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px;
        background: radial-gradient(circle, rgba(136,156,124,0.15) 0%, transparent 70%);
        border-radius: 50%; pointer-events: none;
    }
    .about-hero::after {
        content: ''; position: absolute; bottom: -40%; left: -10%; width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(74,92,67,0.2) 0%, transparent 70%);
        border-radius: 50%; pointer-events: none;
    }
    .stat-counter { font-size: 3.5rem; font-weight: 900; line-height: 1; }
    .stat-counter .plus { font-size: 2rem; color: #889c7c; }
    .value-card {
        background: white; border-radius: 24px; padding: 40px 30px; text-align: center;
        border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.04);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); height: 100%;
    }
    .value-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(74,92,67,0.15); }
    .value-icon { width: 80px; height: 80px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.8rem; }
    .timeline-item { position: relative; padding-left: 40px; padding-bottom: 40px; border-left: 3px solid rgba(74,92,67,0.15); }
    .timeline-item:last-child { border-left: 3px solid transparent; }
    .timeline-dot { position: absolute; left: -10px; top: 0; width: 20px; height: 20px; border-radius: 50%; background: #4a5c43; border: 4px solid #e0eadb; }
    .team-card { border-radius: 24px; overflow: hidden; background: white; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.04); transition: all 0.3s ease; }
    .team-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(74,92,67,0.12); }
    .team-img { height: 280px; object-fit: cover; width: 100%; }
    .section-label { color: #889c7c; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; }
</style>

<!-- HERO -->
<section class="about-hero">
    <div class="container position-relative z-2">
        <div class="row align-items-center">
            <div class="col-lg-7" data-aos="fade-right">
                <span class="section-label d-block mb-3"><i class="fas fa-car-side me-2"></i>Our Story</span>
                <h1 class="fw-black display-3 mb-4" style="line-height: 1.1;">Redefining Urban<br><span style="color: #889c7c;">Mobility</span></h1>
                <p class="lead opacity-75 fw-bold mb-5" style="max-width: 520px;">SmartDrive X was born from a simple idea: premium car rentals shouldn't be complicated. We combine cutting-edge technology with a curated fleet to deliver an experience that goes beyond transportation.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="<?php echo $base_url; ?>customer/search_cars.php" class="btn btn-light rounded-pill fw-bold px-5 py-3 shadow-sm"><i class="fas fa-compass me-2"></i>Explore Fleet</a>
                    <a href="<?php echo $base_url; ?>pages/contact.php" class="btn btn-outline-light rounded-pill fw-bold px-5 py-3">Contact Us</a>
                </div>
            </div>
            <div class="col-lg-5 mt-5 mt-lg-0" data-aos="fade-left" data-aos-delay="200">
                <div class="row g-4 text-center">
                    <div class="col-6">
                        <div class="p-4 rounded-4" style="background:rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="stat-counter text-white"><?php echo $total_cars; ?><span class="plus">+</span></div>
                            <small class="text-white opacity-50 fw-bold text-uppercase tracking-wide">Premium Vehicles</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-4 rounded-4" style="background:rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="stat-counter text-white"><?php echo $total_users; ?><span class="plus">+</span></div>
                            <small class="text-white opacity-50 fw-bold text-uppercase tracking-wide">Happy Drivers</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-4 rounded-4" style="background:rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="stat-counter text-white"><?php echo $total_bookings; ?><span class="plus">+</span></div>
                            <small class="text-white opacity-50 fw-bold text-uppercase tracking-wide">Trips Completed</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-4 rounded-4" style="background:rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);">
                            <div class="stat-counter text-white"><?php echo $total_cities; ?><span class="plus">+</span></div>
                            <small class="text-white opacity-50 fw-bold text-uppercase tracking-wide">City Hubs</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- MISSION & VALUES -->
<section class="py-5 my-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3">What Drives Us</span>
            <h2 class="fw-black display-5 text-dark">Our Core Values</h2>
            <p class="text-muted fw-bold mx-auto" style="max-width:600px;">Every decision we make is guided by these four principles that define who we are.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="0">
                <div class="value-card">
                    <div class="value-icon" style="background: rgba(74,92,67,0.1); color: #4a5c43;"><i class="fas fa-shield-alt"></i></div>
                    <h5 class="fw-black text-dark mb-3">Safety First</h5>
                    <p class="text-muted fw-bold small mb-0">Every vehicle undergoes 150+ point inspection before joining our fleet. Your safety is non-negotiable.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="value-card">
                    <div class="value-icon" style="background: rgba(13,202,240,0.1); color: #0dcaf0;"><i class="fas fa-bolt"></i></div>
                    <h5 class="fw-black text-dark mb-3">Tech-Driven</h5>
                    <p class="text-muted fw-bold small mb-0">Real-time fleet tracking, instant approvals, and automated billing — powered by modern technology.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="value-card">
                    <div class="value-icon" style="background: rgba(255,193,7,0.1); color: #ffc107;"><i class="fas fa-gem"></i></div>
                    <h5 class="fw-black text-dark mb-3">Premium Quality</h5>
                    <p class="text-muted fw-bold small mb-0">From hatchbacks to luxury sedans, every car in our fleet is maintained to the highest standards.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="value-card">
                    <div class="value-icon" style="background: rgba(25,135,84,0.1); color: #198754;"><i class="fas fa-leaf"></i></div>
                    <h5 class="fw-black text-dark mb-3">Sustainability</h5>
                    <p class="text-muted fw-bold small mb-0">Shared mobility reduces carbon footprints. We're committed to a greener future for urban transport.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- JOURNEY TIMELINE -->
<section class="py-5" style="background: #f8f9f7;">
    <div class="container py-5">
        <div class="row align-items-start">
            <div class="col-lg-5 mb-5 mb-lg-0" data-aos="fade-right">
                <span class="section-label d-block mb-3">Our Journey</span>
                <h2 class="fw-black display-5 text-dark mb-4">Building the<br>Future of Rental</h2>
                <p class="text-muted fw-bold">From a college project to a full-fledged fleet management platform — here's how SmartDrive X evolved.</p>
            </div>
            <div class="col-lg-6 offset-lg-1" data-aos="fade-left">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <h6 class="fw-black text-dark mb-1">Phase 1 — Foundation</h6>
                    <small class="text-muted fw-bold d-block mb-2">Core Platform Built</small>
                    <p class="text-muted fw-bold small mb-0">Launched with basic booking, search, and admin management. CRUD operations and secure authentication layer.</p>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background: #0dcaf0;"></div>
                    <h6 class="fw-black text-dark mb-1">Phase 2 — Fleet Intelligence</h6>
                    <small class="text-muted fw-bold d-block mb-2">Smart Pricing & Coupon Engine</small>
                    <p class="text-muted fw-bold small mb-0">Integrated dynamic pricing, GST calculation, coupon redemption, and real-time fleet capacity monitoring.</p>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background: #198754;"></div>
                    <h6 class="fw-black text-dark mb-1">Phase 3 — Enterprise V2.0</h6>
                    <small class="text-muted fw-bold d-block mb-2">Production-Level Upgrade</small>
                    <p class="text-muted fw-bold small mb-0">Multi-stage booking approval, late return penalty engine, final settlement invoicing, and admin configuration panel.</p>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot" style="background: #ffc107;"></div>
                    <h6 class="fw-black text-dark mb-1">Phase 4 — Future Vision</h6>
                    <small class="text-muted fw-bold d-block mb-2">Coming Soon</small>
                    <p class="text-muted fw-bold small mb-0">Mobile applications, GPS live tracking, AI-powered pricing optimization, and multi-city franchise model.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- TEAM -->
<section class="py-5 my-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3">The Brains</span>
            <h2 class="fw-black display-5 text-dark">Meet Our Team</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="0">
                <div class="team-card">
                    <img src="https://ui-avatars.com/api/?name=Het+Soni&background=4a5c43&color=fff&size=400&bold=true&font-size=0.4" class="team-img" alt="Het Soni">
                    <div class="p-4 text-center">
                        <h5 class="fw-black text-dark mb-1">Het Soni</h5>
                        <small class="text-muted fw-bold d-block mb-3">Full Stack Developer & Project Lead</small>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="#" class="text-muted"><i class="fab fa-github fs-5"></i></a>
                            <a href="#" class="text-muted"><i class="fab fa-linkedin fs-5"></i></a>
                            <a href="#" class="text-muted"><i class="fas fa-envelope fs-5"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="team-card">
                    <img src="https://ui-avatars.com/api/?name=Vaidik+Bhatt&background=889c7c&color=fff&size=400&bold=true&font-size=0.4" class="team-img" alt="Vaidik Bhatt">
                    <div class="p-4 text-center">
                        <h5 class="fw-black text-dark mb-1">Vaidik Bhatt</h5>
                        <small class="text-muted fw-bold d-block mb-3">System Admin & Database Architect</small>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="#" class="text-muted"><i class="fab fa-github fs-5"></i></a>
                            <a href="#" class="text-muted"><i class="fab fa-linkedin fs-5"></i></a>
                            <a href="#" class="text-muted"><i class="fas fa-envelope fs-5"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="py-5" style="background: linear-gradient(135deg, #2b3327, #4a5c43); color: white;">
    <div class="container text-center py-5" data-aos="zoom-in">
        <h2 class="fw-black display-5 mb-3">Ready to Hit the Road?</h2>
        <p class="opacity-75 fw-bold mb-5 mx-auto" style="max-width: 500px;">Join thousands of happy drivers and experience premium car rentals like never before.</p>
        <a href="<?php echo $base_url; ?>register.php" class="btn btn-light rounded-pill fw-bold px-5 py-3 shadow-lg"><i class="fas fa-rocket me-2"></i>Get Started Free</a>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
