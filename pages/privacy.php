<?php
session_start();
$base_url = "http://localhost/smartdrive_x/";
$page_title = "Privacy Policy | SmartDrive X";
include '../includes/header.php';
?>

<style>
    .legal-hero { background: linear-gradient(135deg, #1a1e16, #2b3327, #3d4a37); padding: 100px 0 80px; color: white; }
    .legal-content { max-width: 800px; margin: 0 auto; }
    .legal-content h3 { color: #2b3327; font-weight: 900; margin-top: 40px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0eadb; }
    .legal-content p, .legal-content li { color: #6c757d; font-weight: 600; line-height: 1.9; font-size: 0.95rem; }
    .legal-content ul { padding-left: 20px; }
    .legal-content li { margin-bottom: 8px; }
    .legal-content li::marker { color: #4a5c43; }
    .info-box { background: #f8f9f7; border: 1px solid rgba(74,92,67,0.1); border-radius: 16px; padding: 24px; margin: 20px 0; }
    .section-label { color: #889c7c; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; }
    .toc-card { background: #f8f9f7; border-radius: 20px; padding: 30px; border: 1px solid rgba(74,92,67,0.1); position: sticky; top: 100px; }
    .toc-card a { color: #4a5c43; text-decoration: none; font-weight: 700; display: block; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.04); transition: all 0.2s; font-size: 0.9rem; }
    .toc-card a:hover { color: #2b3327; padding-left: 8px; }
</style>

<section class="legal-hero">
    <div class="container text-center">
        <span class="section-label d-block mb-3" data-aos="fade-up"><i class="fas fa-user-lock me-2"></i>Your Data, Your Rights</span>
        <h1 class="fw-black display-4 mb-3" data-aos="fade-up" data-aos-delay="100">Privacy Policy</h1>
        <p class="opacity-50 fw-bold mb-0" data-aos="fade-up" data-aos-delay="200">Last updated: <?php echo date('F d, Y'); ?></p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="toc-card">
                    <h6 class="fw-black text-dark mb-3 text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Sections</h6>
                    <a href="#overview">1. Overview</a>
                    <a href="#collect">2. Data We Collect</a>
                    <a href="#use">3. How We Use It</a>
                    <a href="#storage">4. Data Storage</a>
                    <a href="#sharing">5. Data Sharing</a>
                    <a href="#security">6. Security Measures</a>
                    <a href="#cookies">7. Cookies</a>
                    <a href="#rights">8. Your Rights</a>
                    <a href="#priv-contact">9. Contact</a>
                </div>
            </div>
            <div class="col-lg-9" data-aos="fade-left">
                <div class="legal-content">
                    <div class="info-box">
                        <p class="mb-0 fw-bold small" style="color: #4a5c43;"><i class="fas fa-info-circle me-2"></i><strong>Summary:</strong> We collect only what's necessary to provide our car rental services. We never sell your data. Your information is protected by industry-standard security measures.</p>
                    </div>

                    <h3 id="overview">1. Overview</h3>
                    <p>SmartDrive X ("we", "our", "us") is committed to protecting your personal information. This Privacy Policy explains how we collect, use, store, and protect data when you use our platform at smartdrivex.com.</p>

                    <h3 id="collect">2. Information We Collect</h3>
                    <p><strong>Information you provide directly:</strong></p>
                    <ul>
                        <li>Full name, email address, phone number, and password (hashed) during registration.</li>
                        <li>Driving license details and government ID for verification purposes.</li>
                        <li>Payment information during booking transactions.</li>
                        <li>Communication data when you contact our support team.</li>
                    </ul>
                    <p><strong>Information collected automatically:</strong></p>
                    <ul>
                        <li>Session data (session ID, login timestamps, role assignments).</li>
                        <li>Booking history, rental patterns, and loyalty point accumulation.</li>
                        <li>Browser type, IP address, and device information for security monitoring.</li>
                    </ul>

                    <h3 id="use">3. How We Use Your Information</h3>
                    <ul>
                        <li><strong>Service Delivery:</strong> Processing bookings, payments, invoices, and managing your account.</li>
                        <li><strong>Communication:</strong> Sending booking confirmations, approval notifications, and payment receipts.</li>
                        <li><strong>Security:</strong> Preventing fraud, detecting unauthorized access, and maintaining platform integrity.</li>
                        <li><strong>Improvement:</strong> Analyzing usage patterns to improve our fleet, pricing, and user experience.</li>
                        <li><strong>Legal Compliance:</strong> Meeting regulatory requirements including GST reporting.</li>
                    </ul>

                    <h3 id="storage">4. Data Storage & Retention</h3>
                    <p>Your data is stored in a MySQL/MariaDB database hosted on our secure servers. We retain your data for as long as your account is active plus an additional 3 years for legal compliance and audit purposes.</p>
                    <ul>
                        <li>Passwords are stored as secure hashes (never in plain text).</li>
                        <li>Payment card numbers are NOT stored on our servers.</li>
                        <li>Booking records are retained for GST compliance (minimum 6 years).</li>
                    </ul>

                    <h3 id="sharing">5. Data Sharing</h3>
                    <p>We do <strong>NOT</strong> sell, trade, or rent your personal information. We may share data only in these cases:</p>
                    <ul>
                        <li><strong>Service Providers:</strong> Payment gateways for transaction processing.</li>
                        <li><strong>Legal Requirements:</strong> When required by law, court order, or government regulation.</li>
                        <li><strong>Safety:</strong> To protect the rights, safety, or property of SmartDrive X and its users.</li>
                    </ul>

                    <h3 id="security">6. Security Measures</h3>
                    <div class="info-box">
                        <ul class="mb-0 small">
                            <li><strong>SQL Injection Prevention:</strong> All database queries use prepared statements with parameterized inputs.</li>
                            <li><strong>XSS Protection:</strong> User inputs are sanitized using htmlspecialchars() before rendering.</li>
                            <li><strong>Session Security:</strong> Role-based access control (RBAC) with server-side session management.</li>
                            <li><strong>CSRF Protection:</strong> Transaction forms include anti-forgery tokens.</li>
                            <li><strong>Password Hashing:</strong> bcrypt algorithm with salt for all user credentials.</li>
                        </ul>
                    </div>

                    <h3 id="cookies">7. Cookies</h3>
                    <p>SmartDrive X uses session cookies (PHPSESSID) that are essential for the platform to function. These cookies:</p>
                    <ul>
                        <li>Expire when you close your browser or after session timeout.</li>
                        <li>Do not track your browsing activity on other websites.</li>
                        <li>Cannot be used to personally identify you without logging in.</li>
                    </ul>

                    <h3 id="rights">8. Your Rights</h3>
                    <p>As a SmartDrive X user, you have the right to:</p>
                    <ul>
                        <li><strong>Access:</strong> View all personal data we hold about you via your Profile page.</li>
                        <li><strong>Rectification:</strong> Update your personal information at any time.</li>
                        <li><strong>Deletion:</strong> Request account deletion by contacting our support team.</li>
                        <li><strong>Data Portability:</strong> Request an export of your booking history and invoices.</li>
                        <li><strong>Opt-out:</strong> Unsubscribe from promotional communications at any time.</li>
                    </ul>

                    <h3 id="priv-contact">9. Contact Us</h3>
                    <p>For privacy-related inquiries or to exercise your data rights:</p>
                    <ul>
                        <li><strong>Data Protection Officer:</strong> dpo@smartdrivex.com</li>
                        <li><strong>Support:</strong> support@smartdrivex.com</li>
                        <li><strong>Address:</strong> GTU Campus, Ahmedabad, Gujarat 382424, India</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
