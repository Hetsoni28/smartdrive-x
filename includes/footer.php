<?php
// ==========================================
// 🛡️ SECURE SESSION & GLOBAL CONFIG
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🌐 BULLETPROOF PATHING 
$base_url = isset($base_url) ? $base_url : "http://localhost/smartdrive_x/";

// 🎨 DYNAMIC THEME ENGINE (Admin = Teal, Customer/Guest = Sage Green)
$role_id = $_SESSION['role_id'] ?? 0;
if ($role_id == 1) {
    $theme_primary = "#4da89c"; // Teal
    $theme_secondary = "#8bd0b4"; // Mint
    $theme_bg = "#f1f7ec";
    $footer_bg = "#151b1a"; // Deep Admin Dark
} else {
    $theme_primary = "#4a5c43"; // Sage Dark
    $theme_secondary = "#889c7c"; // Sage Mid
    $theme_bg = "#f4f5f3";
    $footer_bg = "#1e241c"; // Deep Sage Dark
}
?>

    <div class="footer-gradient-bar"></div>
    
    <footer class="pt-5 pb-3 mt-auto footer-beast" style="background-color: <?php echo $footer_bg; ?>;">
        <div class="container pt-4">
            <div class="row mb-5 g-5">
                
                <div class="col-lg-4 col-md-6 pe-lg-5" data-aos="fade-up" data-aos-delay="0">
                    <h4 class="text-uppercase fw-black mb-4 brand-text tracking-widest d-flex align-items-center">
                        <div class="brand-icon-wrap me-3 d-flex align-items-center justify-content-center border border-white border-opacity-10 rounded-3">
                            <i class="fas fa-car-side"></i>
                        </div>
                        SmartDrive<span class="opacity-50 text-white">X</span>
                    </h4>
                    <p class="footer-text mb-4 fw-bold">
                        Your ultimate destination for premium car rentals and intelligent fleet management. Drive your dreams with our seamless, tech-driven platform designed for the modern road.
                    </p>
                    
                    <div class="social-wrapper d-flex gap-3 mt-4">
                        <a href="javascript:void(0);" class="social-btn" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="javascript:void(0);" class="social-btn" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="javascript:void(0);" class="social-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="javascript:void(0);" class="social-btn" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <h6 class="text-uppercase fw-bold mb-4 text-white tracking-widest opacity-50">Navigation</h6>
                    <ul class="list-unstyled footer-menu">
                        <li><a href="<?php echo $base_url; ?>index.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> Homepage</a></li>
                        <li><a href="<?php echo $base_url; ?>customer/search_cars.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> Explore Fleet</a></li>
                        <li><a href="<?php echo $base_url; ?>pages/about.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> About Us</a></li>
                        <li><a href="<?php echo $base_url; ?>pages/features.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> Features</a></li>
                        <?php if(!isset($_SESSION['user_id'])): ?>
                            <li><a href="<?php echo $base_url; ?>login.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> Secure Login</a></li>
                        <?php else: ?>
                            <?php $dash_link = ($role_id == 1) ? 'admin/dashboard.php' : 'customer/dashboard.php'; ?>
                            <li><a href="<?php echo $base_url . $dash_link; ?>" class="footer-link"><i class="fas fa-chevron-right me-2"></i> My Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-6" data-aos="fade-up" data-aos-delay="150">
                    <h6 class="text-uppercase fw-bold mb-4 text-white tracking-widest opacity-50">Company</h6>
                    <ul class="list-unstyled footer-menu">
                        <li><a href="<?php echo $base_url; ?>pages/contact.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> Contact Us</a></li>
                        <li><a href="<?php echo $base_url; ?>pages/careers.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> Careers</a></li>
                        <li><a href="<?php echo $base_url; ?>customer/help_center.php" class="footer-link"><i class="fas fa-chevron-right me-2"></i> Help Center</a></li>
                    </ul>

                    <h6 class="text-uppercase fw-bold mb-3 mt-4 text-white tracking-widest opacity-50">Legal</h6>
                    <ul class="list-unstyled footer-menu">
                        <li><a href="<?php echo $base_url; ?>pages/terms.php" class="footer-link"><i class="fas fa-shield-alt me-2"></i> Terms of Service</a></li>
                        <li><a href="<?php echo $base_url; ?>pages/privacy.php" class="footer-link"><i class="fas fa-user-lock me-2"></i> Privacy Policy</a></li>
                        <li><a href="<?php echo $base_url; ?>pages/refund.php" class="footer-link"><i class="fas fa-undo me-2"></i> Refund Policy</a></li>
                    </ul>
                </div>

                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <h6 class="text-uppercase fw-bold mb-4 text-white tracking-widest opacity-50">Stay Updated</h6>
                    <p class="footer-text small mb-4 fw-bold">Subscribe to our digital newsletter for exclusive weekend promo codes and new vehicle announcements.</p>
                    
                    <form id="newsletterForm" class="mb-4 position-relative">
                        <div class="input-group rounded-pill overflow-hidden p-1 bg-white bg-opacity-10 border border-light border-opacity-25 focus-ring-wrapper transition-all">
                            <input type="email" id="nlEmail" class="form-control border-0 shadow-none ps-4 fw-bold bg-transparent text-white" placeholder="Enter your email address..." required>
                            <button class="btn text-white rounded-pill fw-bold px-4 btn-subscribe shadow-sm" type="submit" id="nlBtn" style="background-color: <?php echo $theme_primary; ?>;">
                                Subscribe
                            </button>
                        </div>
                        <div id="nlSuccess" class="position-absolute mt-2 fw-bold small text-success" style="display: none; opacity: 0; transition: opacity 0.3s;">
                            <i class="fas fa-check-circle me-1"></i> Successfully subscribed!
                        </div>
                    </form>

                    <ul class="list-unstyled footer-text small mt-4 fw-bold">
                        <li class="mb-3 d-flex align-items-center opacity-75 hover-opacity-100 transition-all"><i class="fas fa-map-marker-alt me-3 fs-5 brand-icon"></i> <span>GTU Campus, Ahmedabad, Gujarat, IN</span></li>
                        <li class="mb-3 d-flex align-items-center opacity-75 hover-opacity-100 transition-all"><i class="fas fa-envelope me-3 fs-5 brand-icon"></i> <span>support@smartdrivex.com</span></li>
                        <li class="d-flex align-items-center opacity-75 hover-opacity-100 transition-all"><i class="fas fa-phone-alt me-3 fs-5 brand-icon"></i> <span>+91 98765 43210</span></li>
                    </ul>
                </div>
            </div>

            <div class="pt-4 pb-2 border-top border-light border-opacity-10">
                <div class="row align-items-center g-3">
                    
                    <div class="col-md-4 text-center text-md-start">
                        <span class="opacity-50 small fw-bold tracking-wide text-white">© <?php echo date('Y'); ?> SmartDrive X. All rights reserved.</span>
                    </div>
                    
                    <div class="col-md-4 text-center">
                        <div class="d-inline-flex align-items-center px-3 py-2 rounded-pill shadow-sm" style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05);">
                            <span class="pulse-indicator me-2" style="background-color: #198754; width: 8px; height: 8px;"></span>
                            <span class="small text-white opacity-75 fw-bold me-3">System Operational</span>
                            <span class="small text-white opacity-75 fw-bold border-start border-light border-opacity-25 ps-3" id="liveServerClock">
                                <i class="far fa-clock me-1"></i> --:--:--
                            </span>
                        </div>
                    </div>
                    
                    <div class="col-md-4 text-center text-md-end">
                        <span class="badge px-3 py-2 rounded-pill project-badge shadow-sm">
                            <i class="fas fa-graduation-cap me-1 text-warning"></i> BCA Final Year Project
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <div id="backToTopContainer">
        <svg class="progress-ring" width="55" height="55">
            <circle class="progress-ring__circle" stroke="<?php echo $theme_primary; ?>" stroke-width="4" fill="transparent" r="24" cx="27.5" cy="27.5"/>
        </svg>
        <button id="backToTopBtn" aria-label="Scroll to top">
            <i class="fas fa-chevron-up"></i>
        </button>
    </div>

    <style>
        /* Top Gradient Bar */
        .footer-gradient-bar {
            height: 6px; width: 100%;
            background: linear-gradient(90deg, #2b3327 0%, <?php echo $theme_primary; ?> 50%, <?php echo $theme_secondary; ?> 100%);
            background-size: 200% 200%;
            animation: gradientShift 5s ease infinite;
        }
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

        /* Typography & Colors */
        .footer-beast { color: #e0eadb; position: relative; z-index: 10; }
        .footer-text { opacity: 0.6; font-size: 0.95rem; line-height: 1.8; color: white; }
        .brand-text { color: <?php echo $theme_secondary; ?>; }
        .brand-icon-wrap { width: 45px; height: 45px; background: rgba(255,255,255,0.05); color: <?php echo $theme_secondary; ?>; font-size: 1.2rem; }
        .brand-icon { color: <?php echo $theme_secondary; ?>; width: 20px; text-align: center; }
        .tracking-widest { letter-spacing: 2px; }
        .transition-all { transition: all 0.3s ease; }
        .hover-opacity-100:hover { opacity: 1 !important; }

        /* Navigation Links */
        .footer-menu li { margin-bottom: 0.8rem; }
        .footer-link {
            color: rgba(255, 255, 255, 0.5); text-decoration: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: inline-flex; align-items: center; font-size: 0.95rem; font-weight: 600;
        }
        .footer-link i { font-size: 0.75rem; transition: transform 0.3s ease; opacity: 0.3; }
        .footer-link:hover { color: #fff !important; transform: translateX(8px); }
        .footer-link:hover i { opacity: 1; transform: translateX(4px); color: <?php echo $theme_secondary; ?>; }

        /* Social Media Icons */
        .social-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 42px; height: 42px; border-radius: 50%;
            background-color: rgba(255,255,255,0.05); color: white;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); text-decoration: none;
            border: 1px solid rgba(255,255,255,0.1); position: relative; overflow: hidden;
        }
        .social-btn::before {
            content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 100%;
            background: <?php echo $theme_primary; ?>; transition: all 0.3s ease; z-index: 1;
        }
        .social-btn:hover::before { top: 0; }
        .social-btn i { position: relative; z-index: 2; font-size: 1.1rem; }
        .social-btn:hover { color: white; transform: translateY(-5px); border-color: <?php echo $theme_primary; ?>; box-shadow: 0 10px 20px rgba(0,0,0,0.3); }

        /* Newsletter Input */
        #nlEmail::placeholder { color: rgba(255,255,255,0.4); }
        .focus-ring-wrapper:focus-within { border-color: <?php echo $theme_primary; ?> !important; box-shadow: 0 0 0 3px rgba(255,255,255,0.1); }
        .btn-subscribe { transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); border: none; }
        .btn-subscribe:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(0,0,0,0.3); color: white; }
        .btn-subscribe.loading { background-color: #6c757d !important; pointer-events: none; }

        /* Project Badge */
        .project-badge {
            background-color: rgba(255,255,255,0.05); color: rgba(255,255,255,0.8);
            border: 1px solid rgba(255,255,255,0.1); font-weight: 700; letter-spacing: 0.5px;
        }

        /* Progress Scroll to Top */
        #backToTopContainer {
            position: fixed; bottom: 30px; right: 30px; z-index: 9999;
            width: 55px; height: 55px; opacity: 0; transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); pointer-events: none;
        }
        #backToTopContainer.show-btn { opacity: 1; transform: translateY(0); pointer-events: all; }
        
        .progress-ring { position: absolute; top: 0; left: 0; pointer-events: none; transform: rotate(-90deg); }
        .progress-ring__circle { transition: stroke-dashoffset 0.1s; transform-origin: 50% 50%; stroke-linecap: round; }
        
        #backToTopBtn {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            border: none; outline: none; background-color: <?php echo $theme_primary; ?>; color: white;
            cursor: pointer; width: 43px; height: 43px; border-radius: 50%;
            font-size: 1rem; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center;
        }
        #backToTopContainer:hover #backToTopBtn { background-color: white; color: <?php echo $theme_primary; ?>; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="<?php echo $base_url; ?>assets/js/script.js"></script>

    <script>
        const SmartDriveFooter = (function() {
            "use strict";

            // 1. PRELOADER KILL-SWITCH
            const initPreloader = () => {
                const hideLoader = () => {
                    const preloader = document.getElementById('global-loader') || document.querySelector('.preloader');
                    if (preloader && preloader.style.display !== 'none') {
                        preloader.style.transition = "opacity 0.5s ease-out";
                        preloader.style.opacity = "0";
                        setTimeout(() => { preloader.style.display = "none"; }, 500);
                    }
                };
                // Fire immediately on DOM load, with a hard failsafe
                document.addEventListener('DOMContentLoaded', hideLoader);
                window.addEventListener('load', hideLoader);
                setTimeout(hideLoader, 1500); 
            };

            // 2. LIVE SERVER CLOCK (Hardware Accelerated)
            const initClock = () => {
                const clockEl = document.getElementById('liveServerClock');
                if (!clockEl) return;

                // Use native Intl formatter for perfect IST timezone rendering
                const formatter = new Intl.DateTimeFormat('en-IN', {
                    timeZone: 'Asia/Kolkata',
                    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
                });

                const tick = () => {
                    const timeString = formatter.format(new Date());
                    // Update only the text node to prevent DOM reflows
                    clockEl.childNodes[2].nodeValue = ` ${timeString} IST`;
                    requestAnimationFrame(tick);
                };
                requestAnimationFrame(tick);
            };

            // 3. PROGRESS BACK-TO-TOP BUTTON
            const initScrollToTop = () => {
                const container = document.getElementById("backToTopContainer");
                const btn = document.getElementById("backToTopBtn");
                const circle = document.querySelector('.progress-ring__circle');
                
                if (!container || !btn || !circle) return;

                const radius = circle.r.baseVal.value;
                const circumference = radius * 2 * Math.PI;
                
                circle.style.strokeDasharray = `${circumference} ${circumference}`;
                circle.style.strokeDashoffset = circumference;

                const updateProgress = () => {
                    const scrollY = window.scrollY || document.documentElement.scrollTop;
                    const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
                    
                    // Show/Hide button
                    if (scrollY > 300) {
                        container.classList.add("show-btn");
                    } else {
                        container.classList.remove("show-btn");
                    }

                    // Calculate SVG Stroke offset
                    if (maxScroll > 0) {
                        const scrollPercent = scrollY / maxScroll;
                        const offset = circumference - scrollPercent * circumference;
                        circle.style.strokeDashoffset = offset;
                    }
                };

                // Use requestAnimationFrame for buttery smooth scroll tracking
                let ticking = false;
                window.addEventListener('scroll', () => {
                    if (!ticking) {
                        window.requestAnimationFrame(() => {
                            updateProgress();
                            ticking = false;
                        });
                        ticking = true;
                    }
                });

                btn.addEventListener('click', () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            };

            // 4. ENTERPRISE NEWSLETTER SIMULATION
            const initNewsletter = () => {
                const form = document.getElementById('newsletterForm');
                if (!form) return;

                form.addEventListener('submit', async (e) => {
                    e.preventDefault(); 
                    
                    const btn = document.getElementById('nlBtn');
                    const input = document.getElementById('nlEmail');
                    const msg = document.getElementById('nlSuccess');
                    
                    // Lock UI
                    btn.classList.add('loading');
                    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
                    input.setAttribute('disabled', 'true');
                    
                    // Simulate API Delay
                    await new Promise(resolve => setTimeout(resolve, 1200));
                    
                    // Success UI
                    btn.classList.remove('loading');
                    btn.classList.replace('btn-subscribe', 'btn-success');
                    btn.style.backgroundColor = '#198754';
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    
                    msg.style.display = 'block';
                    // Trigger reflow for transition
                    void msg.offsetWidth;
                    msg.style.opacity = '1';
                    
                    // Reset UI after 4 seconds
                    setTimeout(() => {
                        msg.style.opacity = '0';
                        setTimeout(() => { msg.style.display = 'none'; }, 300);
                        
                        btn.classList.replace('btn-success', 'btn-subscribe');
                        btn.style.backgroundColor = '<?php echo $theme_primary; ?>';
                        btn.innerHTML = 'Subscribe';
                        input.removeAttribute('disabled');
                        input.value = '';
                    }, 4000);
                });
            };

            return {
                ignite: () => {
                    initPreloader();
                    initClock();
                    initScrollToTop();
                    initNewsletter();
                }
            };
        })();

        // Start the Engine
        document.addEventListener('DOMContentLoaded', SmartDriveFooter.ignite);
    </script>
</body>
</html>