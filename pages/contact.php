<?php
session_start();
include '../includes/db_connect.php';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/smartdrive_x/';

// ==========================================
// 🔒 CSRF TOKEN GENERATION
// ==========================================
if (empty($_SESSION['csrf_contact'])) {
    $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_contact'];

// ==========================================
// 📩 SERVER-SIDE FORM HANDLER (POST)
// ==========================================
$form_success = false;
$form_error = '';
$form_data = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => '', 'message_type' => 'general', 'priority' => 'medium', 'booking_ref' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_contact'], $_POST['csrf_token'])) {
        $form_error = 'Security validation failed. Please refresh and try again.';
    } else {
        // Sanitize all inputs
        $name         = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $phone        = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $subject      = htmlspecialchars(trim($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message      = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');
        $message_type = in_array($_POST['message_type'] ?? '', ['general', 'issue', 'feedback', 'billing', 'booking']) ? $_POST['message_type'] : 'general';
        $priority     = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'critical']) ? $_POST['priority'] : 'medium';
        $booking_ref  = htmlspecialchars(trim($_POST['booking_ref'] ?? ''), ENT_QUOTES, 'UTF-8');
        $latitude     = isset($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude    = isset($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $location_addr = htmlspecialchars(trim($_POST['location_address'] ?? ''), ENT_QUOTES, 'UTF-8');
        $user_id      = $_SESSION['user_id'] ?? null;
        $ip_address   = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        // Keep form data for re-display
        $form_data = compact('name', 'email', 'phone', 'subject', 'message', 'message_type', 'priority', 'booking_ref');

        // Server-side validation
        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            $form_error = 'Name must be between 2-100 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $form_error = 'Please enter a valid email address.';
        } elseif (!empty($phone) && !preg_match('/^[+]?[\d\s\-()]{7,20}$/', $phone)) {
            $form_error = 'Please enter a valid phone number.';
        } elseif (empty($subject) || strlen($subject) < 5 || strlen($subject) > 255) {
            $form_error = 'Subject must be between 5-255 characters.';
        } elseif (empty($message) || strlen($message) < 10 || strlen($message) > 5000) {
            $form_error = 'Message must be between 10-5000 characters.';
        } else {
            // Rate limiting: Max 5 submissions per hour per IP
            $rate_check = $conn->prepare("SELECT COUNT(*) as cnt FROM contact_messages WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $rate_check->bind_param("s", $ip_address);
            $rate_check->execute();
            $rate_count = $rate_check->get_result()->fetch_assoc()['cnt'];
            $rate_check->close();

            if ($rate_count >= 5) {
                $form_error = 'Too many submissions. Please try again in an hour.';
            } else {
                // Insert into database
                $stmt = $conn->prepare("
                    INSERT INTO contact_messages 
                    (user_id, name, email, phone, subject, message_type, message, priority, latitude, longitude, location_address, booking_ref, ip_address, user_agent, csrf_token) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "isssssssddsssss",
                    $user_id, $name, $email, $phone, $subject, $message_type, $message, $priority,
                    $latitude, $longitude, $location_addr, $booking_ref, $ip_address, $user_agent, $csrf_token
                );

                if ($stmt->execute()) {
                    $form_success = true;
                    $form_data = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => '', 'message_type' => 'general', 'priority' => 'medium', 'booking_ref' => ''];
                    // Regenerate CSRF
                    $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION['csrf_contact'];

                    // Admin notification
                    if (function_exists('mysqli_query')) {
                        $admin_ids_query = mysqli_query($conn, "SELECT id FROM users WHERE role_id = 1");
                        while ($admin = mysqli_fetch_assoc($admin_ids_query)) {
                            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, icon, link) VALUES (?, 'New Contact Message', ?, 'info', 'fa-envelope', 'admin/manage_contacts.php')");
                            $notif_msg = "New {$message_type} message from {$name}: {$subject}";
                            $notif_stmt->bind_param("is", $admin['id'], $notif_msg);
                            $notif_stmt->execute();
                            $notif_stmt->close();
                        }
                    }
                } else {
                    $form_error = 'Failed to submit your message. Please try again.';
                }
                $stmt->close();
            }
        }
    }
}

// Pre-fill logged-in user data
if (isset($_SESSION['user_id']) && empty($form_data['name'])) {
    $user_stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $user_info = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    if ($user_info) {
        $form_data['name'] = $user_info['name'];
        $form_data['email'] = $user_info['email'];
        $form_data['phone'] = $user_info['phone'] ?? '';
    }
}

$page_title = "Contact Us | SmartDrive X";
include '../includes/header.php';
?>

<style>
    /* ==========================================
       CONTACT PAGE — BEAST MODE STYLES
       ========================================== */
    .contact-hero {
        background: linear-gradient(135deg, #1a1e16 0%, #2b3327 50%, #4a5c43 100%);
        padding: 120px 0 100px; color: white; position: relative; overflow: hidden;
    }
    .contact-hero::before {
        content: ''; position: absolute; top: -30%; right: -10%; width: 500px; height: 500px;
        background: radial-gradient(circle, rgba(136,156,124,0.15) 0%, transparent 70%); border-radius: 50%;
    }
    .contact-hero::after {
        content: ''; position: absolute; bottom: -40%; left: -15%; width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(74,92,67,0.12) 0%, transparent 70%); border-radius: 50%;
    }
    .section-label { color: #889c7c; font-weight: 800; text-transform: uppercase; letter-spacing: 3px; font-size: 0.8rem; }

    /* Contact Cards */
    .contact-card {
        background: white; border-radius: 24px; padding: 35px; border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 15px 35px rgba(0,0,0,0.04); height: 100%; text-align: center;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .contact-card:hover { transform: translateY(-8px); box-shadow: 0 20px 45px rgba(74,92,67,0.15); }
    .contact-icon {
        width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px; font-size: 1.8rem; transition: all 0.3s ease;
    }
    .contact-card:hover .contact-icon { transform: scale(1.1) rotate(5deg); }

    /* Form Styles */
    .form-section { background: #f8f9f7; }
    .contact-form-card {
        background: white; border-radius: 24px; padding: 40px 35px; border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 20px 50px rgba(0,0,0,0.06);
    }
    .form-control-custom {
        border: 2px solid #e0eadb; border-radius: 14px; padding: 16px 20px; font-weight: 600;
        transition: all 0.3s ease; background: #fafbf9; font-size: 0.95rem;
    }
    .form-control-custom:focus { border-color: #4a5c43; box-shadow: 0 0 0 4px rgba(74,92,67,0.1); background: white; }
    .form-control-custom.is-invalid { border-color: #dc3545; }
    .form-control-custom.is-invalid:focus { box-shadow: 0 0 0 4px rgba(220,53,69,0.1); }
    .form-select-custom {
        border: 2px solid #e0eadb; border-radius: 14px; padding: 16px 20px; font-weight: 600;
        transition: all 0.3s ease; background-color: #fafbf9; font-size: 0.95rem; cursor: pointer;
    }
    .form-select-custom:focus { border-color: #4a5c43; box-shadow: 0 0 0 4px rgba(74,92,67,0.1); }
    .form-label-custom { font-weight: 800; color: #2b3327; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .form-helper { font-size: 0.75rem; color: #889c7c; font-weight: 600; margin-top: 5px; }
    textarea.form-control-custom { min-height: 140px; resize: vertical; }
    .char-count { font-size: 0.7rem; color: #889c7c; font-weight: 700; text-align: right; margin-top: 4px; }

    /* Submit Button */
    .btn-submit {
        background: linear-gradient(135deg, #2b3327, #4a5c43); color: white; border: none; border-radius: 16px;
        padding: 18px 40px; font-weight: 800; font-size: 1rem; letter-spacing: 0.5px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; overflow: hidden;
    }
    .btn-submit::before {
        content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        transition: left 0.5s ease;
    }
    .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(43,51,39,0.3); color: white; }
    .btn-submit:hover::before { left: 100%; }
    .btn-submit:disabled { opacity: 0.7; transform: none; box-shadow: none; }

    /* Alert Styles */
    .alert-success-custom {
        background: rgba(25,135,84,0.08); border: 2px solid rgba(25,135,84,0.2); border-radius: 16px;
        color: #198754; font-weight: 700; padding: 20px 24px;
    }
    .alert-error-custom {
        background: rgba(220,53,69,0.08); border: 2px solid rgba(220,53,69,0.2); border-radius: 16px;
        color: #dc3545; font-weight: 700; padding: 20px 24px;
    }

    /* Map Container */
    .map-wrap {
        border-radius: 20px; overflow: hidden; border: 2px solid rgba(0,0,0,0.06);
        box-shadow: 0 10px 25px rgba(0,0,0,0.06); height: 300px;
    }
    #contactMap { width: 100%; height: 300px; background: #e0eadb; }

    /* Location badge */
    .location-badge {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
        border-radius: 50px; background: rgba(25,135,84,0.08); border: 1px solid rgba(25,135,84,0.15);
        color: #198754; font-weight: 700; font-size: 0.8rem; transition: all 0.3s ease;
    }
    .location-badge.denied { background: rgba(255,193,7,0.08); border-color: rgba(255,193,7,0.15); color: #856404; }

    /* FAQ Section */
    .faq-section { background: white; border-top: 1px solid rgba(0,0,0,0.06); }
    .faq-item { border: 1px solid rgba(0,0,0,0.06); border-radius: 16px; overflow: hidden; margin-bottom: 12px; transition: all 0.3s ease; background: white; }
    .faq-item:hover { border-color: rgba(74,92,67,0.2); }
    .faq-item .accordion-button { font-weight: 700; padding: 18px 24px; background: white; box-shadow: none !important; border-radius: 16px !important; font-size: 0.95rem; }
    .faq-item .accordion-button:not(.collapsed) { color: #4a5c43; background: rgba(74,92,67,0.04); }
    .faq-item .accordion-button::after { flex-shrink: 0; }
    .faq-item .accordion-body { padding: 0 24px 20px; }

    /* Type selector cards */
    .type-option { cursor: pointer; transition: all 0.3s ease; border: 2px solid #e0eadb; border-radius: 14px; padding: 14px; text-align: center; }
    .type-option:hover { border-color: #889c7c; background: rgba(74,92,67,0.03); }
    .type-option.active { border-color: #4a5c43; background: rgba(74,92,67,0.06); }
    .type-option i { font-size: 1.3rem; display: block; margin-bottom: 6px; }
    .type-option small { font-weight: 700; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Right column sticky */
    .right-info-col { position: sticky; top: 100px; }

    /* Responsive */
    @media (max-width: 991px) {
        .contact-form-card { padding: 25px 18px; }
        .contact-hero { padding: 80px 0 60px; }
        .right-info-col { position: static; }
        .map-wrap, #contactMap { height: 250px; }
    }
    @media (max-width: 576px) {
        .contact-form-card { padding: 20px 16px; border-radius: 18px; }
        .contact-card { padding: 24px; }
        .type-option { padding: 10px; }
        .type-option i { font-size: 1.1rem; }
        .map-wrap, #contactMap { height: 200px; }
    }
</style>

<!-- ==========================================
     HERO SECTION
     ========================================== -->
<section class="contact-hero">
    <div class="container position-relative z-2 text-center">
        <span class="section-label d-block mb-3" data-aos="fade-up"><i class="fas fa-headset me-2"></i>Support Center</span>
        <h1 class="fw-black display-3 mb-4" data-aos="fade-up" data-aos-delay="100">We'd Love to<br><span style="color: #889c7c;">Hear From You</span></h1>
        <p class="lead opacity-75 fw-bold mb-0 mx-auto" style="max-width: 580px;" data-aos="fade-up" data-aos-delay="200">Have a question, need to report an issue, or want to share feedback? Our team responds within 24 hours.</p>
    </div>
</section>

<!-- ==========================================
     CONTACT CARDS
     ========================================== -->
<section class="py-5 my-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="0">
                <div class="contact-card">
                    <div class="contact-icon" style="background: rgba(74,92,67,0.1); color: #4a5c43;"><i class="fas fa-map-marker-alt"></i></div>
                    <h5 class="fw-black text-dark mb-2">Visit Our Office</h5>
                    <p class="text-muted fw-bold small mb-1">GTU Campus, Nr. Visat-Gandhinagar Hwy</p>
                    <p class="text-muted fw-bold small mb-0">Ahmedabad, Gujarat 382424, India</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="contact-card">
                    <div class="contact-icon" style="background: rgba(25,135,84,0.1); color: #198754;"><i class="fas fa-phone-alt"></i></div>
                    <h5 class="fw-black text-dark mb-2">Call Us</h5>
                    <p class="text-muted fw-bold small mb-1">+91 98765 43210</p>
                    <p class="text-muted fw-bold small mb-0">Mon–Sat: 9 AM – 7 PM IST</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-12" data-aos="fade-up" data-aos-delay="200">
                <div class="contact-card">
                    <div class="contact-icon" style="background: rgba(13,202,240,0.1); color: #0dcaf0;"><i class="fas fa-envelope"></i></div>
                    <h5 class="fw-black text-dark mb-2">Email Support</h5>
                    <p class="text-muted fw-bold small mb-1">support@smartdrivex.com</p>
                    <p class="text-muted fw-bold small mb-0">Avg. response: Under 4 hours</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     CONTACT FORM + MAP
     ========================================== -->
<section class="form-section py-5">
    <div class="container py-4">
        <div class="row g-5">
            <!-- FORM -->
            <div class="col-lg-7" data-aos="fade-right">
                <span class="section-label d-block mb-3"><i class="fas fa-paper-plane me-2"></i>Send a Message</span>
                <h2 class="fw-black display-6 text-dark mb-2">Drop Us a Line</h2>
                <p class="text-muted fw-bold mb-4">Fill out the form below and we'll get back to you as soon as possible.</p>

                <?php if ($form_success): ?>
                <div class="alert-success-custom mb-4" data-aos="fade-up">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:50px; height:50px; border-radius:50%; background:rgba(25,135,84,0.15); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="fas fa-check-circle fa-lg text-success"></i>
                        </div>
                        <div>
                            <strong class="d-block mb-1" style="font-size:1.05rem;">Message Sent Successfully!</strong>
                            <span class="small opacity-75">Your ticket has been created. Our team will respond within 24 hours via email.</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($form_error): ?>
                <div class="alert-error-custom mb-4" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $form_error; ?>
                </div>
                <?php endif; ?>

                <div class="contact-form-card">
                    <form method="POST" action="" id="contactForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="latitude" id="userLat" value="">
                        <input type="hidden" name="longitude" id="userLng" value="">
                        <input type="hidden" name="location_address" id="userLocAddr" value="">

                        <!-- Message Type Selector -->
                        <label class="form-label-custom mb-3">What can we help with?</label>
                        <div class="row g-2 mb-4">
                            <?php
                            $types = [
                                ['val' => 'general', 'icon' => 'fa-comments', 'label' => 'General', 'color' => '#4a5c43'],
                                ['val' => 'issue', 'icon' => 'fa-bug', 'label' => 'Report Issue', 'color' => '#dc3545'],
                                ['val' => 'feedback', 'icon' => 'fa-star', 'label' => 'Feedback', 'color' => '#ffc107'],
                                ['val' => 'billing', 'icon' => 'fa-credit-card', 'label' => 'Billing', 'color' => '#0dcaf0'],
                                ['val' => 'booking', 'icon' => 'fa-car-side', 'label' => 'Booking', 'color' => '#6f42c1'],
                            ];
                            foreach ($types as $t):
                            ?>
                            <div class="col">
                                <div class="type-option <?php echo $form_data['message_type'] === $t['val'] ? 'active' : ''; ?>" 
                                     onclick="selectType('<?php echo $t['val']; ?>', this)" data-type="<?php echo $t['val']; ?>">
                                    <i class="fas <?php echo $t['icon']; ?>" style="color: <?php echo $t['color']; ?>;"></i>
                                    <small class="text-muted d-block"><?php echo $t['label']; ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="message_type" id="messageType" value="<?php echo htmlspecialchars($form_data['message_type']); ?>">

                        <!-- Name & Email -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-custom" placeholder="John Doe" required minlength="2" maxlength="100"
                                       value="<?php echo htmlspecialchars($form_data['name']); ?>" <?php echo isset($_SESSION['user_id']) ? 'readonly' : ''; ?>>
                                <div class="invalid-feedback fw-bold small">Please enter your name (2-100 chars)</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control form-control-custom" placeholder="you@example.com" required
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" <?php echo isset($_SESSION['user_id']) ? 'readonly' : ''; ?>>
                                <div class="invalid-feedback fw-bold small">Please enter a valid email</div>
                            </div>
                        </div>

                        <!-- Phone & Priority -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">Phone <span class="text-muted">(Optional)</span></label>
                                <input type="tel" name="phone" class="form-control form-control-custom" placeholder="+91 98765 43210"
                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">Priority</label>
                                <select name="priority" class="form-select form-select-custom">
                                    <option value="low" <?php echo $form_data['priority'] === 'low' ? 'selected' : ''; ?>>🟢 Low</option>
                                    <option value="medium" <?php echo $form_data['priority'] === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                                    <option value="high" <?php echo $form_data['priority'] === 'high' ? 'selected' : ''; ?>>🟠 High</option>
                                    <option value="critical" <?php echo $form_data['priority'] === 'critical' ? 'selected' : ''; ?>>🔴 Critical</option>
                                </select>
                            </div>
                        </div>

                        <!-- Booking Ref (conditional) -->
                        <div id="bookingRefWrap" class="mb-3" style="display: <?php echo in_array($form_data['message_type'], ['booking', 'billing']) ? 'block' : 'none'; ?>;">
                            <label class="form-label-custom">Booking Reference</label>
                            <input type="text" name="booking_ref" class="form-control form-control-custom" placeholder="e.g. #BKG-123"
                                   value="<?php echo htmlspecialchars($form_data['booking_ref']); ?>">
                            <div class="form-helper">Enter your booking ID for faster resolution</div>
                        </div>

                        <!-- Subject -->
                        <div class="mb-3">
                            <label class="form-label-custom">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control form-control-custom" placeholder="Brief summary of your query" required minlength="5" maxlength="255"
                                   value="<?php echo htmlspecialchars($form_data['subject']); ?>">
                            <div class="invalid-feedback fw-bold small">Subject must be 5-255 characters</div>
                        </div>

                        <!-- Message -->
                        <div class="mb-4">
                            <label class="form-label-custom">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control form-control-custom" placeholder="Describe your query in detail..." required minlength="10" maxlength="5000" id="messageText"><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                            <div class="d-flex justify-content-between">
                                <div class="invalid-feedback fw-bold small">Message must be 10-5000 characters</div>
                                <div class="char-count"><span id="charCount">0</span> / 5000</div>
                            </div>
                        </div>

                        <!-- Location Badge -->
                        <div class="mb-4">
                            <div class="location-badge" id="locationBadge">
                                <i class="fas fa-crosshairs fa-spin"></i>
                                <span id="locationText">Detecting your location...</span>
                            </div>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="btn btn-submit w-100" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                        <p class="text-center text-muted small fw-bold mt-3 mb-0">
                            <i class="fas fa-lock me-1 opacity-50"></i>Your data is encrypted and secure. We never share your information.
                        </p>
                    </form>
                </div>
            </div>

            <!-- MAP + LIVE INFO -->
            <div class="col-lg-5" data-aos="fade-left">
                <div class="right-info-col">
                    <span class="section-label d-block mb-3"><i class="fas fa-map-marked-alt me-2"></i>Our Location</span>
                    <h2 class="fw-black text-dark mb-4" style="font-size: 1.8rem;">Find Us Here</h2>
                    
                    <div class="map-wrap mb-4">
                        <div id="contactMap"></div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="p-3 rounded-4 text-center" style="background: rgba(74,92,67,0.06); border: 1px solid rgba(74,92,67,0.1);">
                                <h5 class="fw-black text-dark mb-0" style="font-size: 1.1rem;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#198754; margin-right:6px;"></span>Online</h5>
                                <small class="text-muted fw-bold" style="font-size:0.7rem;">Support Status</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-4 text-center" style="background: rgba(255,193,7,0.06); border: 1px solid rgba(255,193,7,0.1);">
                                <h5 class="fw-black text-dark mb-0" style="font-size: 1.1rem;">&lt; 4 hrs</h5>
                                <small class="text-muted fw-bold" style="font-size:0.7rem;">Avg. Response</small>
                            </div>
                        </div>
                    </div>

                    <!-- Office Hours -->
                    <div class="p-4 rounded-4" style="background: white; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 8px 20px rgba(0,0,0,0.03);">
                        <h6 class="fw-black text-dark mb-3" style="font-size:0.9rem;"><i class="far fa-clock me-2 text-muted"></i>Office Hours</h6>
                        <?php
                        $hours = [
                            ['Mon – Fri', '9:00 AM – 7:00 PM', true],
                            ['Saturday', '10:00 AM – 5:00 PM', true],
                            ['Sunday', 'Closed', false],
                        ];
                        foreach ($hours as $h):
                        ?>
                        <div class="d-flex justify-content-between align-items-center py-2 <?php echo $h !== end($hours) ? 'border-bottom' : ''; ?>" style="border-color: rgba(0,0,0,0.05) !important;">
                            <span class="fw-bold text-dark" style="font-size:0.85rem;"><?php echo $h[0]; ?></span>
                            <span class="fw-bold <?php echo $h[2] ? 'text-success' : 'text-danger'; ?>" style="font-size:0.85rem;"><?php echo $h[1]; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==========================================
     FAQ SECTION
     ========================================== -->
<section class="faq-section py-5">
    <div class="container py-4" style="max-width: 820px;">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-label d-block mb-3"><i class="fas fa-question-circle me-2"></i>FAQ</span>
            <h2 class="fw-black text-dark" style="font-size: 2rem;">Frequently Asked Questions</h2>
            <p class="text-muted fw-bold small mt-2 mb-0">Quick answers to the most common queries about our services.</p>
        </div>
        <div class="accordion" id="faqAccordion" data-aos="fade-up" data-aos-delay="100">
            <?php
            $faqs = [
                ['q' => 'How do I report an issue with my booking?', 'a' => 'Use the contact form above and select "Report Issue" as the message type. Include your booking reference number (e.g., #BKG-123) for faster resolution. Our team will investigate and respond within 24 hours.'],
                ['q' => 'What documents do I need for renting a car?', 'a' => 'A valid Indian driving license, government-issued photo ID (Aadhar Card/Passport), and a credit/debit card for the security deposit. All documents are verified digitally during registration.'],
                ['q' => 'How does the late return policy work?', 'a' => 'We offer a 60-minute grace period after the scheduled return time. After that, charges are calculated at ₹300/hour plus 18% GST. All rates are configured by the admin and transparently shown in your final settlement invoice.'],
                ['q' => 'Can I cancel my booking?', 'a' => 'Yes! Cancellation before admin approval incurs no charge. After payment, cancellation within 48 hours of pickup gives you a 50% refund. See our full refund policy for details.'],
                ['q' => 'How do loyalty points work?', 'a' => 'You earn 1 point per ₹100 spent. Points accumulate to unlock Silver (0+), Gold (500+), and Platinum (1500+) tier benefits including priority support and exclusive discounts.'],
                ['q' => 'Is my payment information secure?', 'a' => 'Absolutely. We use prepared SQL statements, CSRF protection, XSS sanitization, and bcrypt password hashing. No card data is stored on our servers — all transactions go through secure payment gateways.'],
            ];
            foreach ($faqs as $i => $f):
            ?>
            <div class="faq-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?php echo $i > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $i; ?>">
                        <?php echo $f['q']; ?>
                    </button>
                </h2>
                <div id="faq<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-muted fw-bold small"><?php echo $f['a']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==========================================
     LEAFLET MAP + GEOLOCATION JS
     ========================================== -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ==========================================
    // 1. LEAFLET MAP — OFFICE LOCATION
    // ==========================================
    const officeLat = 23.1000, officeLng = 72.5000;
    const map = L.map('contactMap', { scrollWheelZoom: false, zoomControl: true }).setView([officeLat, officeLng], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 18
    }).addTo(map);

    const officeIcon = L.divIcon({
        className: 'custom-marker',
        html: '<div style="background:#4a5c43; color:white; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; box-shadow:0 4px 15px rgba(74,92,67,0.4); border:3px solid white;"><i class="fas fa-car-side"></i></div>',
        iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -45]
    });

    L.marker([officeLat, officeLng], { icon: officeIcon })
        .addTo(map)
        .bindPopup('<div style="text-align:center; padding:5px;"><strong style="font-size:0.9rem;">SmartDrive X HQ</strong><br><small style="color:#6c757d;">GTU Campus, Ahmedabad</small></div>');

    // Resize fix
    setTimeout(() => map.invalidateSize(), 300);

    // ==========================================
    // 2. GEOLOCATION — USER POSITION
    // ==========================================
    const badge = document.getElementById('locationBadge');
    const locText = document.getElementById('locationText');
    const latInput = document.getElementById('userLat');
    const lngInput = document.getElementById('userLng');
    const addrInput = document.getElementById('userLocAddr');

    if ('geolocation' in navigator) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude.toFixed(6);
                const lng = pos.coords.longitude.toFixed(6);
                latInput.value = lat;
                lngInput.value = lng;

                badge.classList.remove('denied');
                badge.innerHTML = '<i class="fas fa-map-pin"></i><span>Location captured: ' + lat + ', ' + lng + '</span>';

                // Reverse geocode
                fetch('https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng + '&format=json')
                .then(r => r.json()).then(data => {
                    if (data.display_name) {
                        const short = data.display_name.split(',').slice(0, 3).join(',');
                        addrInput.value = short;
                        locText.textContent = short;
                        badge.innerHTML = '<i class="fas fa-map-pin" style="color:#198754;"></i><span>' + short + '</span>';
                    }
                }).catch(() => {});

                // Add user marker on map
                const userIcon = L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background:#0dcaf0; color:white; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.9rem; box-shadow:0 4px 15px rgba(13,202,240,0.4); border:3px solid white;"><i class="fas fa-user"></i></div>',
                    iconSize: [32, 32], iconAnchor: [16, 32]
                });
                L.marker([lat, lng], { icon: userIcon }).addTo(map).bindPopup('<strong>Your Location</strong>');

                // Fit bounds to show both markers
                const bounds = L.latLngBounds([[officeLat, officeLng], [lat, lng]]);
                map.fitBounds(bounds, { padding: [50, 50] });
            },
            function(err) {
                badge.classList.add('denied');
                badge.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Location access denied — optional feature</span>';
            },
            { timeout: 10000, enableHighAccuracy: false }
        );
    } else {
        badge.classList.add('denied');
        badge.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Geolocation not supported</span>';
    }

    // ==========================================
    // 3. CLIENT-SIDE FORM VALIDATION
    // ==========================================
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        let valid = true;
        form.querySelectorAll('[required]').forEach(input => {
            if (!input.value.trim() || !input.checkValidity()) {
                input.classList.add('is-invalid');
                valid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            e.preventDefault();
            form.querySelector('.is-invalid')?.focus();
            return;
        }

        // Disable button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i>Sending...';
    });

    // Live validation on blur
    form.querySelectorAll('.form-control-custom').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && (!this.value.trim() || !this.checkValidity())) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        input.addEventListener('input', function() { this.classList.remove('is-invalid'); });
    });

    // Character counter
    const msgText = document.getElementById('messageText');
    const charCount = document.getElementById('charCount');
    if (msgText && charCount) {
        charCount.textContent = msgText.value.length;
        msgText.addEventListener('input', () => {
            charCount.textContent = msgText.value.length;
            charCount.style.color = msgText.value.length > 4500 ? '#dc3545' : '#889c7c';
        });
    }
});

// ==========================================
// 4. MESSAGE TYPE SELECTOR
// ==========================================
function selectType(type, el) {
    document.querySelectorAll('.type-option').forEach(o => o.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('messageType').value = type;

    // Show/hide booking ref
    const refWrap = document.getElementById('bookingRefWrap');
    refWrap.style.display = (type === 'booking' || type === 'billing') ? 'block' : 'none';
}
</script>

<?php include '../includes/footer.php'; ?>
