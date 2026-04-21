<?php
session_start();
$base_url = "http://localhost/smartdrive_x/";
$page_title = "Terms of Service | SmartDrive X";
include '../includes/header.php';
?>

<style>
    .legal-hero {
        background: linear-gradient(135deg, #1a1e16, #2b3327, #3d4a37);
        padding: 100px 0 80px; color: white;
    }
    .legal-content { max-width: 800px; margin: 0 auto; }
    .legal-content h3 { color: #2b3327; font-weight: 900; margin-top: 40px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0eadb; }
    .legal-content p, .legal-content li { color: #6c757d; font-weight: 600; line-height: 1.9; font-size: 0.95rem; }
    .legal-content ul { padding-left: 20px; }
    .legal-content li { margin-bottom: 8px; }
    .legal-content li::marker { color: #4a5c43; }
    .toc-card { background: #f8f9f7; border-radius: 20px; padding: 30px; border: 1px solid rgba(74,92,67,0.1); position: sticky; top: 100px; }
    .toc-card a { color: #4a5c43; text-decoration: none; font-weight: 700; display: block; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,0.04); transition: all 0.2s; font-size: 0.9rem; }
    .toc-card a:hover { color: #2b3327; padding-left: 8px; }
    .section-label { color: #889c7c; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; }
</style>

<section class="legal-hero">
    <div class="container text-center position-relative z-2">
        <span class="section-label d-block mb-3" data-aos="fade-up"><i class="fas fa-shield-alt me-2"></i>Legal</span>
        <h1 class="fw-black display-4 mb-3" data-aos="fade-up" data-aos-delay="100">Terms of Service</h1>
        <p class="opacity-50 fw-bold mb-0" data-aos="fade-up" data-aos-delay="200">Last updated: <?php echo date('F d, Y'); ?></p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-3 d-none d-lg-block" data-aos="fade-right">
                <div class="toc-card">
                    <h6 class="fw-black text-dark mb-3 text-uppercase" style="letter-spacing: 1px; font-size: 0.75rem;">Table of Contents</h6>
                    <a href="#acceptance">1. Acceptance of Terms</a>
                    <a href="#eligibility">2. Eligibility</a>
                    <a href="#booking">3. Booking & Payments</a>
                    <a href="#rental">4. Rental Terms</a>
                    <a href="#late">5. Late Returns</a>
                    <a href="#cancellation">6. Cancellations</a>
                    <a href="#liability">7. Liability</a>
                    <a href="#privacy">8. Privacy & Data</a>
                    <a href="#modifications">9. Modifications</a>
                    <a href="#contact-legal">10. Contact</a>
                </div>
            </div>
            <div class="col-lg-9" data-aos="fade-left">
                <div class="legal-content">
                    <p>Welcome to SmartDrive X. By accessing or using our platform, you agree to be bound by these Terms of Service. Please read them carefully before using our services.</p>

                    <h3 id="acceptance">1. Acceptance of Terms</h3>
                    <p>By registering, booking, or using any SmartDrive X services, you acknowledge that you have read, understood, and agree to be bound by these terms. If you do not agree, please refrain from using our platform.</p>

                    <h3 id="eligibility">2. Eligibility</h3>
                    <ul>
                        <li>You must be at least 21 years of age with a valid Indian driving license held for at least 1 year.</li>
                        <li>A government-issued photo ID (Aadhar Card, Passport, or Voter ID) is mandatory for identity verification.</li>
                        <li>Corporate accounts may have separate eligibility criteria as per their enterprise agreement.</li>
                    </ul>

                    <h3 id="booking">3. Booking & Payment Terms</h3>
                    <p>All bookings are subject to admin approval. Once approved, customers must complete payment within 48 hours to confirm the reservation. Failure to pay within this window may result in automatic cancellation.</p>
                    <ul>
                        <li>Prices include base rental + 18% GST as per government regulations.</li>
                        <li>Accepted payment methods: Credit Card, Debit Card, UPI, Net Banking.</li>
                        <li>Invoice and receipt are generated automatically upon successful payment.</li>
                        <li>Loyalty points are earned at a rate of 1 point per ₹100 spent.</li>
                    </ul>

                    <h3 id="rental">4. Rental Agreement</h3>
                    <p>Upon vehicle pickup, a digital rental agreement is activated. The renter is responsible for:</p>
                    <ul>
                        <li>Operating the vehicle in accordance with all applicable traffic laws.</li>
                        <li>Returning the vehicle in the same condition as received, subject to normal wear.</li>
                        <li>Reporting any accidents, damage, or mechanical issues immediately.</li>
                        <li>Not subletting, racing, or using the vehicle for any illegal purpose.</li>
                    </ul>

                    <h3 id="late">5. Late Return Policy</h3>
                    <p>SmartDrive X employs an automated late return penalty system:</p>
                    <ul>
                        <li><strong>Grace Period:</strong> 60 minutes after the scheduled return time (configurable by admin).</li>
                        <li><strong>Hourly Rate:</strong> ₹300/hour (configurable) applied after the grace period expires.</li>
                        <li><strong>GST:</strong> 18% GST is applied on all late return charges.</li>
                        <li><strong>Chargeable Hours:</strong> Calculated as ceil((late minutes - grace period) / 60).</li>
                        <li>All late charges are transparently displayed in the Final Settlement Invoice.</li>
                    </ul>

                    <h3 id="cancellation">6. Cancellation Policy</h3>
                    <ul>
                        <li><strong>Before Approval:</strong> Free cancellation with no charges.</li>
                        <li><strong>After Approval (before payment):</strong> No financial penalty, but repeat cancellations may affect account standing.</li>
                        <li><strong>After Payment:</strong> Subject to a processing fee of up to 10% of the booking amount.</li>
                        <li><strong>No-Show:</strong> Full booking amount is non-refundable if the customer fails to pick up the vehicle.</li>
                    </ul>

                    <h3 id="liability">7. Limitation of Liability</h3>
                    <p>SmartDrive X provides vehicles in roadworthy condition. However, we shall not be liable for:</p>
                    <ul>
                        <li>Traffic violations, fines, or penalties incurred during the rental period.</li>
                        <li>Personal belongings left in the vehicle after return.</li>
                        <li>Third-party damages caused by the renter's negligence.</li>
                        <li>Force majeure events including natural disasters, strikes, or government orders.</li>
                    </ul>

                    <h3 id="privacy">8. Privacy & Data Protection</h3>
                    <p>Your data is handled in accordance with our <a href="<?php echo $base_url; ?>pages/privacy.php" class="fw-bold" style="color: #4a5c43;">Privacy Policy</a>. We use industry-standard security measures including prepared statements, input sanitization, and role-based access control to protect your information.</p>

                    <h3 id="modifications">9. Modifications to Terms</h3>
                    <p>SmartDrive X reserves the right to update these terms at any time. Users will be notified of material changes via the in-app notification system. Continued use after modifications constitutes acceptance.</p>

                    <h3 id="contact-legal">10. Contact Information</h3>
                    <p>For legal inquiries or disputes related to these terms:</p>
                    <ul>
                        <li><strong>Email:</strong> legal@smartdrivex.com</li>
                        <li><strong>Phone:</strong> +91 98765 43210</li>
                        <li><strong>Address:</strong> GTU Campus, Ahmedabad, Gujarat 382424, India</li>
                    </ul>
                    <p class="mt-4 p-4 rounded-4 fw-bold small" style="background: #f8f9f7; border: 1px solid rgba(74,92,67,0.1); color: #4a5c43;">
                        <i class="fas fa-gavel me-2"></i>
                        <strong>Governing Law:</strong> These terms are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in Ahmedabad, Gujarat.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
