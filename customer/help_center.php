<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url  = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Customer';

// ==========================================
// 📩 HANDLE SUPPORT TICKET SUBMISSION
// ==========================================
$ticket_msg  = '';
$ticket_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject  = trim(htmlspecialchars($_POST['subject']));
    $category = trim(htmlspecialchars($_POST['category']));
    $detail   = trim(htmlspecialchars($_POST['detail']));

    if (empty($subject) || empty($detail) || empty($category)) {
        $ticket_msg  = 'Please fill in all fields before submitting.';
        $ticket_type = 'warning';
    } else {
        // ── In a live system you'd INSERT into a support_tickets table.
        // ── We simulate success gracefully.
        $ticket_ref  = 'TKT-' . strtoupper(bin2hex(random_bytes(3)));
        $ticket_msg  = "Ticket <strong>#{$ticket_ref}</strong> submitted successfully! Our team will respond within 24 hours.";
        $ticket_type = 'success';
    }
}

// Fetch user stats for personalisation
$stats_row = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as bookings FROM bookings WHERE user_id=$user_id"
));

include '../includes/header.php';
?>

<style>
:root {
    --sage-dark: #4a5c43;
    --sage-mid:  #889c7c;
    --sage-pale: #e0eadb;
    --sage-bg:   #f4f5f3;
    --sage-deep: #2b3327;
}
body { background: var(--sage-bg); }

/* ─── HERO ─── */
.help-hero {
    background: linear-gradient(135deg, var(--sage-deep) 0%, #3d5038 100%);
    border-radius: 24px; padding: 3.5rem 2.5rem;
    color: white; position: relative; overflow: hidden;
    box-shadow: 0 20px 50px rgba(43,51,39,.25);
}
.help-hero::after {
    content: '\f128'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
    position: absolute; right: -20px; bottom: -40px; font-size: 16rem;
    color: rgba(255,255,255,.04); pointer-events: none;
}

/* ─── SEARCH BOX ─── */
.help-search {
    max-width: 640px; margin: 0 auto;
    position: relative;
}
.help-search input {
    border: none; border-radius: 50px; padding: 18px 28px 18px 56px;
    font-size: 1rem; font-weight: 600; width: 100%;
    box-shadow: 0 10px 40px rgba(0,0,0,.15);
    transition: all .3s;
}
.help-search input:focus { outline: none; box-shadow: 0 10px 40px rgba(0,0,0,.25); }
.help-search .search-icon {
    position: absolute; left: 20px; top: 50%; transform: translateY(-50%);
    color: var(--sage-mid); font-size: 1.1rem;
}

/* ─── CATEGORY CARDS ─── */
.cat-card {
    background: white; border-radius: 20px; padding: 28px;
    text-align: center; cursor: pointer; border: 2px solid transparent;
    box-shadow: 0 4px 16px rgba(0,0,0,.04);
    transition: all .35s cubic-bezier(.175,.885,.32,1.275);
    text-decoration: none; color: inherit;
}
.cat-card:hover, .cat-card.active {
    border-color: var(--sage-mid);
    transform: translateY(-6px);
    box-shadow: 0 16px 40px rgba(74,92,67,.12);
    color: var(--sage-dark);
}
.cat-icon {
    width: 60px; height: 60px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; margin: 0 auto 12px;
}

/* ─── FAQ ACCORDION ─── */
.faq-item {
    background: white; border-radius: 16px; margin-bottom: 12px;
    border: 1px solid rgba(136,156,124,.15);
    box-shadow: 0 2px 8px rgba(0,0,0,.03);
    overflow: hidden;
    transition: box-shadow .3s;
}
.faq-item:hover { box-shadow: 0 6px 20px rgba(74,92,67,.08); }
.faq-question {
    padding: 18px 24px; font-weight: 700; cursor: pointer;
    display: flex; justify-content: space-between; align-items: center;
    border: none; background: none; width: 100%; text-align: left;
    transition: color .3s;
}
.faq-question:hover, .faq-question[aria-expanded="true"] { color: var(--sage-dark); }
.faq-question .faq-chevron {
    font-size: .85rem; transition: transform .35s ease;
    color: var(--sage-mid); flex-shrink: 0; margin-left: 12px;
}
.faq-question[aria-expanded="true"] .faq-chevron { transform: rotate(180deg); }
.faq-answer { padding: 0 24px 20px; color: #6c757d; line-height: 1.7; }

/* ─── CONTACT OPTIONS ─── */
.contact-card {
    background: white; border-radius: 20px; padding: 28px;
    border: 1px solid rgba(136,156,124,.15);
    box-shadow: 0 4px 16px rgba(0,0,0,.04);
    transition: all .35s;
    text-align: center;
}
.contact-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(74,92,67,.1); }

/* ─── TICKET FORM ─── */
.ticket-form-wrap {
    background: white; border-radius: 24px; padding: 2.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,.05);
    border: 1px solid rgba(136,156,124,.15);
}
.field-sage {
    background: #f8f9f7; border: 2px solid transparent;
    border-radius: 14px; padding: 13px 18px; font-weight: 600;
    transition: all .3s; width: 100%;
}
.field-sage:focus {
    border-color: var(--sage-mid);
    box-shadow: 0 0 0 .25rem rgba(136,156,124,.2);
    background: white; outline: none;
}

/* ─── STATUS BADGE ─── */
.status-timeline { list-style: none; padding: 0; margin: 0; position: relative; }
.status-timeline::before {
    content: ''; position: absolute; left: 18px; top: 0; bottom: 0;
    width: 2px; background: var(--sage-pale);
}
.status-timeline li {
    display: flex; align-items: flex-start; gap: 16px;
    padding: 14px 0; position: relative;
}
.tl-dot {
    width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    position: relative; z-index: 1;
}

/* ─── SEARCH RESULTS ─── */
.search-result-item {
    padding: 12px 16px; border-radius: 12px; cursor: pointer;
    transition: background .2s; margin-bottom: 6px;
}
.search-result-item:hover { background: var(--sage-pale); }

.btn-sage { background: var(--sage-dark); color: white; border: none; transition: all .3s; }
.btn-sage:hover { background: var(--sage-deep); color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(74,92,67,.3); }
</style>

<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>

<div class="dashboard-content">

    <!-- ═══ HERO ═══ -->
    <div class="help-hero mb-5">
        <div style="position:relative;z-index:2;">
            <span class="badge bg-white rounded-pill px-3 py-2 mb-3 fw-bold shadow-sm" style="color:var(--sage-dark);">
                <i class="fas fa-headset me-2" style="color:var(--sage-mid);"></i>24/7 Support Centre
            </span>
            <h1 class="display-5 fw-bold mb-2">How can we help you?</h1>
            <p class="fs-5 opacity-75 mb-4">Search our knowledge base or reach out to our team — we're always here.</p>

            <!-- Search -->
            <div class="help-search">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="helpSearch" placeholder="Search FAQs, topics, guides..."
                       autocomplete="off" oninput="filterFaqs(this.value)">
            </div>

            <!-- Quick stats -->
            <div class="d-flex gap-4 mt-4 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-success rounded-circle p-2"><i class="fas fa-circle" style="font-size:.5rem;"></i></span>
                    <small class="fw-bold opacity-80">Support Online</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-clock opacity-60"></i>
                    <small class="fw-bold opacity-80">Avg. response: &lt; 2 hours</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-star text-warning"></i>
                    <small class="fw-bold opacity-80">4.9 / 5 support rating</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ FLASH ═══ -->
    <?php if ($ticket_msg): ?>
    <div class="alert alert-<?php echo $ticket_type; ?> alert-dismissible rounded-4 border-0 border-start border-4 border-<?php echo $ticket_type; ?> p-4 mb-4 fw-bold shadow-sm">
        <i class="fas <?php echo $ticket_type==='success'?'fa-check-circle':'fa-exclamation-triangle'; ?> me-2"></i>
        <?php echo $ticket_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ═══ CATEGORIES ═══ -->
    <h4 class="fw-bold mb-4">Browse by Topic</h4>
    <div class="row g-3 mb-5" id="categoryRow">
        <?php
        $cats = [
            ['icon'=>'fa-car',           'label'=>'Bookings & Rentals', 'color'=>'rgba(74,92,67,.1)',    'text'=>'var(--sage-dark)', 'key'=>'booking'],
            ['icon'=>'fa-credit-card',   'label'=>'Payments & Billing', 'color'=>'rgba(255,193,7,.1)',   'text'=>'#856404',          'key'=>'payment'],
            ['icon'=>'fa-user',          'label'=>'Account & Profile',  'color'=>'rgba(13,202,240,.1)',  'text'=>'#0a6070',          'key'=>'account'],
            ['icon'=>'fa-map-marker-alt','label'=>'Locations & Hubs',   'color'=>'rgba(220,53,69,.1)',   'text'=>'#dc3545',          'key'=>'location'],
            ['icon'=>'fa-shield-alt',    'label'=>'Safety & Insurance', 'color'=>'rgba(111,66,193,.1)',  'text'=>'#6f42c1',          'key'=>'safety'],
            ['icon'=>'fa-gift',          'label'=>'Rewards & Coupons',  'color'=>'rgba(136,156,124,.15)','text'=>'var(--sage-dark)', 'key'=>'rewards'],
        ];
        foreach ($cats as $cat): ?>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="javascript:void(0)" class="cat-card d-flex flex-column align-items-center cat-filter"
               data-cat="<?php echo $cat['key']; ?>" onclick="filterByCategory('<?php echo $cat['key']; ?>', this)">
                <div class="cat-icon" style="background:<?php echo $cat['color']; ?>">
                    <i class="fas <?php echo $cat['icon']; ?>" style="color:<?php echo $cat['text']; ?>"></i>
                </div>
                <span class="fw-bold small text-center" style="font-size:.82rem;"><?php echo $cat['label']; ?></span>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ═══ FAQ + TICKET FORM ═══ -->
    <div class="row g-4">

        <!-- FAQ Panel -->
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0">Frequently Asked Questions</h4>
                <span class="badge rounded-pill px-3 py-2 fw-bold" style="background:var(--sage-pale);color:var(--sage-dark);" id="faqCount">18 articles</span>
            </div>

            <div id="faqList">
            <?php
            $faqs = [
                // Booking
                ['cat'=>'booking','q'=>'How do I make a booking?','a'=>'Go to "Book a New Ride" from your sidebar. Select your pickup hub, dates, and the vehicle you\'d like. Review the fare breakdown (including GST & weekend surcharges) then click "Confirm & Pay" to proceed to the payment gateway.'],
                ['cat'=>'booking','q'=>'Can I cancel or modify my booking?','a'=>'Yes! Cancellations made 48+ hours before pickup receive a full refund. Within 48 hours, a 25% cancellation fee applies. To modify dates, cancel and rebook — this ensures accurate pricing. You can manage this from your Rental History page.'],
                ['cat'=>'booking','q'=>'What documents do I need to pick up the car?','a'=>'A valid Driver\'s License matching your registered name is mandatory. You may also need a government-issued photo ID (Aadhaar or Passport). Documents must be original — photocopies are not accepted.'],
                ['cat'=>'booking','q'=>'What happens if I return the car late?','a'=>'Late returns attract an overtime charge of 1.5× the daily rate for each additional hour. If the car is more than 3 hours late, a full extra day is charged. Please contact support in advance if you anticipate a delay.'],
                ['cat'=>'booking','q'=>'Is a security deposit required?','a'=>'A refundable security deposit of ₹5,000–₹25,000 (depending on vehicle category) is held at pickup. This is released within 5 business days after a clean return with no damage.'],
                // Payment
                ['cat'=>'payment','q'=>'What payment methods are accepted?','a'=>'We accept Credit/Debit Cards (Visa, Mastercard, Amex), UPI (GPay, PhonePe, Paytm), and Net Banking from all major Indian banks. All transactions are encrypted with 256-bit SSL.'],
                ['cat'=>'payment','q'=>'When will I receive my invoice?','a'=>'Your digital invoice is generated automatically the moment your payment clears. You can view and print it anytime from the "Rental History" page by clicking the Invoice button next to your confirmed booking.'],
                ['cat'=>'payment','q'=>'Why was my payment declined?','a'=>'Common causes: insufficient funds, expired card, incorrect CVV, or your bank\'s international/online transaction limits. Try a different payment method or contact your bank. For persistent issues, reach out to our support team.'],
                ['cat'=>'payment','q'=>'Can I use a coupon or promo code?','a'=>'Absolutely! Enter your promo code in the "Promotions & Offers" section on the booking page. Valid codes show an instant discount in the fare breakdown. Codes cannot be applied after a booking is confirmed.'],
                // Account
                ['cat'=>'account','q'=>'How do I change my password?','a'=>'Go to Profile → Security tab. Enter your current password, then type and confirm your new password. We recommend using a strong password with uppercase, lowercase, numbers, and symbols.'],
                ['cat'=>'account','q'=>'Can I change my registered email?','a'=>'For security reasons, your registered email is permanent — it\'s tied to your booking history and payment records. If you have an urgent need to change it, contact our support team with identity proof.'],
                ['cat'=>'account','q'=>'How do I delete my account?','a'=>'Go to Profile → Danger Zone. You can deactivate temporarily or permanently delete your account. Permanent deletion removes all data and cannot be undone. Active or future bookings must be cancelled first.'],
                // Location
                ['cat'=>'location','q'=>'Which cities does SmartDrive X operate in?','a'=>'We currently operate in 4 major hubs: Ahmedabad, Mumbai, Delhi, and Bangalore. New cities are being added every quarter. Each hub has designated pickup and drop-off points.'],
                ['cat'=>'location','q'=>'Can I pick up from one city and drop off in another?','a'=>'One-way rental is available for select routes (e.g., Ahmedabad → Mumbai). An inter-city transfer fee applies. This option is shown on the booking page when available for your selected dates.'],
                // Safety
                ['cat'=>'safety','q'=>'Are the vehicles insured?','a'=>'Yes, all SmartDrive X vehicles carry comprehensive insurance covering third-party liability. You are covered for standard on-road incidents. Personal accidents are covered up to ₹2 lakh per incident.'],
                ['cat'=>'safety','q'=>'What should I do in case of an accident?','a'=>'1) Ensure everyone is safe. 2) Call emergency services if needed. 3) Do not move the vehicle unless directed by police. 4) Call our 24/7 helpline: +91 98765 43210 immediately. 5) Do not admit fault at the scene.'],
                // Rewards
                ['cat'=>'rewards','q'=>'How does the loyalty points system work?','a'=>'You earn 1 point for every ₹100 spent on confirmed bookings. Points accumulate across all trips. Silver tier starts at 0 pts, Gold at 500 pts, and Platinum at 1,500 pts. Higher tiers unlock bigger discounts and priority support.'],
                ['cat'=>'rewards','q'=>'Can I redeem my loyalty points for discounts?','a'=>'Yes! Loyalty points can be redeemed during checkout. Every 100 points = ₹10 off your booking. Points are valid for 12 months from the date they were earned. Redemption is coming to all accounts soon!'],
            ];

            foreach ($faqs as $i => $faq): ?>
            <div class="faq-item" data-cat="<?php echo $faq['cat']; ?>" data-q="<?php echo strtolower($faq['q'] . ' ' . $faq['a']); ?>">
                <button class="faq-question" type="button"
                        data-bs-toggle="collapse" data-bs-target="#faq<?php echo $i; ?>"
                        aria-expanded="false">
                    <span><?php echo htmlspecialchars($faq['q']); ?></span>
                    <i class="fas fa-chevron-down faq-chevron"></i>
                </button>
                <div class="collapse" id="faq<?php echo $i; ?>">
                    <p class="faq-answer mb-0"><?php echo htmlspecialchars($faq['a']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <!-- No results -->
            <div id="noFaqResults" class="text-center py-5 d-none">
                <i class="fas fa-search fa-3x mb-3 opacity-25" style="color:var(--sage-mid);"></i>
                <h6 class="fw-bold text-muted">No articles found</h6>
                <p class="text-muted small">Try different keywords or submit a support ticket.</p>
            </div>
        </div>

        <!-- Right panel -->
        <div class="col-lg-5">

            <!-- ── Contact Methods ── -->
            <h5 class="fw-bold mb-3">Contact Support</h5>
            <div class="row g-3 mb-4">
                <div class="col-6">
                    <div class="contact-card">
                        <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:50px;height:50px;background:rgba(74,92,67,.1);">
                            <i class="fas fa-phone" style="color:var(--sage-dark);font-size:1.2rem;"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Call Us</h6>
                        <p class="small text-muted mb-2">24/7 Helpline</p>
                        <a href="tel:+919876543210" class="fw-bold small" style="color:var(--sage-dark);">+91 98765 43210</a>
                    </div>
                </div>
                <div class="col-6">
                    <div class="contact-card">
                        <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:50px;height:50px;background:rgba(13,202,240,.1);">
                            <i class="fas fa-comments" style="color:#0a6070;font-size:1.2rem;"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Live Chat</h6>
                        <p class="small text-muted mb-2">Instant replies</p>
                        <button class="btn btn-sm btn-sage rounded-pill fw-bold px-3" onclick="openChat()">Start Chat</button>
                    </div>
                </div>
                <div class="col-6">
                    <div class="contact-card">
                        <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:50px;height:50px;background:rgba(111,66,193,.1);">
                            <i class="fas fa-envelope" style="color:#6f42c1;font-size:1.2rem;"></i>
                        </div>
                        <h6 class="fw-bold mb-1">Email</h6>
                        <p class="small text-muted mb-2">Reply in &lt; 24h</p>
                        <a href="mailto:support@smartdrivex.com" class="fw-bold small" style="color:#6f42c1;">support@<br>smartdrivex.com</a>
                    </div>
                </div>
                <div class="col-6">
                    <div class="contact-card">
                        <div class="rounded-3 d-inline-flex align-items-center justify-content-center mb-2"
                             style="width:50px;height:50px;background:rgba(40,167,69,.1);">
                            <i class="fab fa-whatsapp" style="color:#25d366;font-size:1.3rem;"></i>
                        </div>
                        <h6 class="fw-bold mb-1">WhatsApp</h6>
                        <p class="small text-muted mb-2">Quick queries</p>
                        <a href="https://wa.me/919876543210" target="_blank" class="fw-bold small" style="color:#25d366;">+91 98765 43210</a>
                    </div>
                </div>
            </div>

            <!-- ── Submit Ticket ── -->
            <div class="ticket-form-wrap">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;background:rgba(74,92,67,.1);">
                        <i class="fas fa-ticket-alt" style="color:var(--sage-dark);"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Submit a Ticket</h5>
                        <small class="text-muted">We reply within 24 hours</small>
                    </div>
                </div>

                <form action="<?php echo $base_url; ?>customer/help_center.php" method="POST">

                    <div class="mb-3">
                        <label class="fw-bold text-muted small text-uppercase mb-2">Category</label>
                        <select name="category" class="field-sage" required>
                            <option value="">Select a category...</option>
                            <option>Booking Issue</option>
                            <option>Payment / Refund</option>
                            <option>Vehicle Problem</option>
                            <option>Account Access</option>
                            <option>Coupon / Rewards</option>
                            <option>Other</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold text-muted small text-uppercase mb-2">Subject</label>
                        <input type="text" name="subject" class="field-sage" placeholder="Brief description of your issue" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold text-muted small text-uppercase mb-2">Details</label>
                        <textarea name="detail" class="field-sage" rows="4"
                                  placeholder="Describe your issue in detail. Include booking IDs, dates, or any error messages..."
                                  required style="resize:vertical;"></textarea>
                    </div>

                    <div class="mb-4 p-3 rounded-3" style="background:#f8f9f7;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-user-circle" style="color:var(--sage-mid);"></i>
                            <div>
                                <div class="fw-bold small"><?php echo htmlspecialchars($user_name); ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?php echo $stats_row['bookings']; ?> booking(s) on account</div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="submit_ticket" class="btn btn-sage w-100 rounded-pill fw-bold py-3">
                        <i class="fas fa-paper-plane me-2"></i>Submit Support Ticket
                    </button>
                </form>
            </div>

            <!-- ── Response Timeline ── -->
            <div class="card border-0 shadow-sm rounded-4 p-4 mt-4 bg-white">
                <h6 class="fw-bold mb-3">Expected Response Times</h6>
                <ul class="status-timeline">
                    <?php
                    $timeline = [
                        ['icon'=>'fa-bolt',     'bg'=>'rgba(255,193,7,.15)','color'=>'#856404', 'title'=>'Live Chat',    'time'=>'< 5 minutes'],
                        ['icon'=>'fa-phone',    'bg'=>'rgba(74,92,67,.12)', 'color'=>'var(--sage-dark)','title'=>'Phone', 'time'=>'Immediate'],
                        ['icon'=>'fab fa-whatsapp','bg'=>'rgba(37,211,102,.12)','color'=>'#25d366','title'=>'WhatsApp', 'time'=>'< 1 hour'],
                        ['icon'=>'fa-ticket-alt','bg'=>'rgba(13,202,240,.12)','color'=>'#0a6070','title'=>'Support Ticket','time'=>'< 24 hours'],
                        ['icon'=>'fa-envelope','bg'=>'rgba(111,66,193,.12)','color'=>'#6f42c1','title'=>'Email','time'=>'1–2 business days'],
                    ];
                    foreach ($timeline as $tl): ?>
                    <li>
                        <div class="tl-dot" style="background:<?php echo $tl['bg']; ?>;">
                            <i class="<?php echo (strpos($tl['icon'],'fab ')===0 ? $tl['icon'] : 'fas '.$tl['icon']); ?>"
                               style="color:<?php echo $tl['color']; ?>;font-size:.85rem;"></i>
                        </div>
                        <div>
                            <div class="fw-bold small"><?php echo $tl['title']; ?></div>
                            <div class="text-muted" style="font-size:.78rem;"><?php echo $tl['time']; ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div><!-- /right col -->
    </div>

    <!-- ═══ LIVE CHAT MODAL ═══ -->
    <div class="modal fade" id="chatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="p-4" style="background:linear-gradient(135deg,var(--sage-deep),var(--sage-dark));color:white;">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width:44px;height:44px;background:rgba(255,255,255,.15);">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div>
                            <div class="fw-bold">SmartBot Assistant</div>
                            <div class="small opacity-70"><span class="text-success me-1">●</span>Online — Ready to help</div>
                        </div>
                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="p-4" id="chatBody" style="height:280px;overflow-y:auto;background:#f8f9f7;">
                    <div class="chat-msg bot-msg mb-3 p-3 rounded-3 shadow-sm bg-white" style="max-width:90%;font-size:.9rem;">
                        <i class="fas fa-robot me-2" style="color:var(--sage-mid);"></i>
                        Hi <strong><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></strong>! 👋 I'm SmartBot. How can I help you today? Try asking about bookings, payments, or account issues.
                    </div>
                </div>
                <div class="p-3 border-top" style="background:white;">
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <?php foreach (['Booking help','Payment issue','Cancel booking','Loyalty points'] as $chip): ?>
                        <button class="btn btn-sm rounded-pill fw-bold" style="background:var(--sage-pale);color:var(--sage-dark);font-size:.78rem;"
                                onclick="sendChat(this.textContent)"><?php echo $chip; ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="input-group">
                        <input type="text" id="chatInput" class="form-control rounded-start-3 border-0"
                               style="background:#f8f9f7;" placeholder="Type your message..."
                               onkeydown="if(event.key==='Enter')sendChat()">
                        <button class="btn btn-sage rounded-end-3 px-3" onclick="sendChat()"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /dashboard-content -->
</div><!-- /dashboard-layout -->

<script>
// ─── Category Filter ───
function filterByCategory(key, el) {
    document.querySelectorAll('.cat-filter').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    let shown = 0;
    document.querySelectorAll('.faq-item').forEach(f => {
        const match = key === 'all' || f.dataset.cat === key;
        f.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    document.getElementById('faqCount').textContent = shown + ' articles';
    document.getElementById('noFaqResults').classList.toggle('d-none', shown > 0);
    document.getElementById('helpSearch').value = '';
}

// ─── Live FAQ Search ───
function filterFaqs(query) {
    const q = query.trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('.faq-item').forEach(f => {
        const text = f.dataset.q || '';
        const match = q === '' || text.includes(q);
        f.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    // Reset category highlight when searching
    document.querySelectorAll('.cat-filter').forEach(c => c.classList.remove('active'));
    document.getElementById('faqCount').textContent = shown + ' articles';
    document.getElementById('noFaqResults').classList.toggle('d-none', shown > 0);
}

// ─── Live Chat Bot ───
function openChat() {
    new bootstrap.Modal(document.getElementById('chatModal')).show();
    document.getElementById('chatInput').focus();
}

function sendChat(txt) {
    const input = document.getElementById('chatInput');
    const msg   = txt || input.value.trim();
    if (!msg) return;

    const body = document.getElementById('chatBody');

    // User bubble
    body.insertAdjacentHTML('beforeend',
        `<div class="d-flex justify-content-end mb-2">
            <div class="p-3 rounded-3 shadow-sm text-white fw-bold" style="background:var(--sage-dark);max-width:85%;font-size:.88rem;">${msg}</div>
        </div>`
    );

    input.value = '';
    body.scrollTop = body.scrollHeight;

    // Bot typing indicator
    const typingId = 'typing_' + Date.now();
    body.insertAdjacentHTML('beforeend',
        `<div id="${typingId}" class="chat-msg bot-msg mb-2 p-3 rounded-3 bg-white shadow-sm" style="max-width:90%;font-size:.9rem;">
            <i class="fas fa-robot me-2" style="color:var(--sage-mid);"></i>
            <span class="typing-dots">Typing<span class="dot">.</span><span class="dot">.</span><span class="dot">.</span></span>
        </div>`
    );
    body.scrollTop = body.scrollHeight;

    // Bot replies
    const replies = {
        'booking': 'To make a booking, click "Book a New Ride" in the sidebar. Select your dates and vehicle, review the fare, then proceed to payment.',
        'cancel':'Cancellations 48+ hours before pickup get a full refund. Within 48 hours, a 25% fee applies. Manage this in your Rental History.',
        'payment':'We accept Cards, UPI (PhonePe/GPay), and Net Banking. All transactions are SSL-encrypted. Visit the Payment page after confirming your booking.',
        'password':'Go to Profile → Security tab to change your password. You\'ll need your current password to set a new one.',
        'loyalty':'You earn 1 point per ₹100 spent. Reach 500 pts for Gold tier and 1,500 pts for Platinum tier with exclusive benefits!',
        'points': 'Your loyalty points are visible in your Profile and on the dashboard. Every 100 points = ₹10 off future bookings.',
        'invoice':'Your invoice is auto-generated when payment clears. Find it in Rental History → click "Invoice" on a confirmed booking.',
        'default': 'Thanks for reaching out! 😊 For complex issues, please submit a support ticket and our team will respond within 24 hours.'
    };

    const lower = msg.toLowerCase();
    let reply = replies['default'];
    if (lower.includes('book'))    reply = replies['booking'];
    if (lower.includes('cancel'))  reply = replies['cancel'];
    if (lower.includes('pay'))     reply = replies['payment'];
    if (lower.includes('password') || lower.includes('login')) reply = replies['password'];
    if (lower.includes('loyalty') || lower.includes('reward')) reply = replies['loyalty'];
    if (lower.includes('point'))   reply = replies['points'];
    if (lower.includes('invoice') || lower.includes('receipt')) reply = replies['invoice'];

    setTimeout(() => {
        const typingEl = document.getElementById(typingId);
        if (typingEl) typingEl.innerHTML = `<i class="fas fa-robot me-2" style="color:var(--sage-mid);"></i>${reply}`;
        body.scrollTop = body.scrollHeight;
    }, 1200);
}

// ─── Typing dots animation ───
setInterval(() => {
    document.querySelectorAll('.dot').forEach((d, i) => {
        setTimeout(() => {
            d.style.opacity = d.style.opacity === '0.2' ? '1' : '0.2';
        }, i * 200);
    });
}, 600);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>AOS.init({ once: true, duration: 600 });</script>
<?php include '../includes/footer.php'; ?>
