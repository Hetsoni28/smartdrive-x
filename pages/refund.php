<?php
session_start();
$base_url = "http://localhost/smartdrive_x/";
$page_title = "Refund Policy | SmartDrive X";
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
    .refund-table { border-radius: 16px; overflow: hidden; border: 1px solid rgba(74,92,67,0.15); }
    .refund-table th { background: #2b3327; color: white; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; padding: 16px 20px; border: none; }
    .refund-table td { padding: 16px 20px; font-weight: 600; border-color: rgba(0,0,0,0.05); vertical-align: middle; }
    .refund-table tbody tr:hover { background: rgba(74,92,67,0.03); }
    .section-label { color: #889c7c; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; }
    .process-step { display: flex; align-items: flex-start; gap: 20px; margin-bottom: 30px; }
    .step-num { width: 50px; height: 50px; border-radius: 50%; background: rgba(74,92,67,0.1); color: #4a5c43; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.2rem; flex-shrink: 0; }
</style>

<section class="legal-hero">
    <div class="container text-center">
        <span class="section-label d-block mb-3" data-aos="fade-up"><i class="fas fa-undo me-2"></i>Returns & Refunds</span>
        <h1 class="fw-black display-4 mb-3" data-aos="fade-up" data-aos-delay="100">Refund Policy</h1>
        <p class="opacity-50 fw-bold mb-0" data-aos="fade-up" data-aos-delay="200">Last updated: <?php echo date('F d, Y'); ?></p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="legal-content" data-aos="fade-up">
            <p>At SmartDrive X, we strive for complete customer satisfaction. This policy outlines the conditions under which refunds are processed for our car rental services.</p>

            <h3>1. Refund Eligibility</h3>
            <div class="table-responsive my-4">
                <table class="table refund-table mb-0">
                    <thead>
                        <tr>
                            <th>Scenario</th>
                            <th>Refund Amount</th>
                            <th>Processing Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Cancellation before approval</strong><br><small class="text-muted">Booking still in "Pending" status</small></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 fw-bold">100% Refund</span></td>
                            <td>Instant</td>
                        </tr>
                        <tr>
                            <td><strong>Cancellation after approval</strong><br><small class="text-muted">Before payment is made</small></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 fw-bold">No charge</span></td>
                            <td>N/A</td>
                        </tr>
                        <tr>
                            <td><strong>Cancellation after payment</strong><br><small class="text-muted">More than 48h before pickup</small></td>
                            <td><span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 rounded-pill px-3 py-2 fw-bold">90% Refund</span></td>
                            <td>5-7 Business Days</td>
                        </tr>
                        <tr>
                            <td><strong>Cancellation after payment</strong><br><small class="text-muted">Within 48h of pickup</small></td>
                            <td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-2 fw-bold">50% Refund</span></td>
                            <td>5-7 Business Days</td>
                        </tr>
                        <tr>
                            <td><strong>No-Show</strong><br><small class="text-muted">Customer doesn't pick up vehicle</small></td>
                            <td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-3 py-2 fw-bold">No Refund</span></td>
                            <td>N/A</td>
                        </tr>
                        <tr>
                            <td><strong>Vehicle unavailability</strong><br><small class="text-muted">SmartDrive X unable to provide vehicle</small></td>
                            <td><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-3 py-2 fw-bold">100% Refund</span></td>
                            <td>1-3 Business Days</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h3>2. Late Return Charges</h3>
            <p>Late return penalties are <strong>non-refundable</strong> as they are calculated automatically based on the actual return time. The penalty structure is:</p>
            <ul>
                <li>60-minute grace period after scheduled return (no charge).</li>
                <li>₹300/hour applied after grace period expires.</li>
                <li>18% GST added on all late charges.</li>
                <li>All charges are transparently displayed in the Final Settlement Invoice.</li>
            </ul>

            <h3>3. Refund Process</h3>
            <div class="process-step" data-aos="fade-up">
                <div class="step-num">1</div>
                <div>
                    <h6 class="fw-black text-dark mb-1">Initiate Request</h6>
                    <p class="text-muted fw-bold small mb-0">Contact our support team at support@smartdrivex.com with your Booking Reference ID (e.g., #BKG-123).</p>
                </div>
            </div>
            <div class="process-step" data-aos="fade-up" data-aos-delay="100">
                <div class="step-num">2</div>
                <div>
                    <h6 class="fw-black text-dark mb-1">Review & Verification</h6>
                    <p class="text-muted fw-bold small mb-0">Our team reviews the request against the eligibility criteria above. You'll receive a confirmation within 24 hours.</p>
                </div>
            </div>
            <div class="process-step" data-aos="fade-up" data-aos-delay="200">
                <div class="step-num">3</div>
                <div>
                    <h6 class="fw-black text-dark mb-1">Refund Processing</h6>
                    <p class="text-muted fw-bold small mb-0">Approved refunds are processed to the original payment method within the stated processing time.</p>
                </div>
            </div>
            <div class="process-step" data-aos="fade-up" data-aos-delay="300">
                <div class="step-num">4</div>
                <div>
                    <h6 class="fw-black text-dark mb-1">Confirmation</h6>
                    <p class="text-muted fw-bold small mb-0">You'll receive an in-app notification and email confirming the refund with transaction details.</p>
                </div>
            </div>

            <h3>4. Exceptions</h3>
            <ul>
                <li>Refunds for damage deposits are processed after vehicle inspection (up to 7 days).</li>
                <li>Promotional or discounted bookings may have reduced refund eligibility.</li>
                <li>Corporate/enterprise bookings follow their specific contract terms.</li>
            </ul>

            <h3>5. Contact</h3>
            <p>For refund-related inquiries:</p>
            <div class="p-4 rounded-4 fw-bold small" style="background: #f8f9f7; border: 1px solid rgba(74,92,67,0.1); color: #4a5c43;">
                <i class="fas fa-envelope me-2"></i> refunds@smartdrivex.com &nbsp;|&nbsp;
                <i class="fas fa-phone-alt me-2"></i> +91 98765 43210 &nbsp;|&nbsp;
                <i class="fas fa-clock me-2"></i> Mon–Sat, 9 AM – 7 PM IST
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
