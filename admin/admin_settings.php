<?php
session_start();

// 🛡️ SECURITY: Super Admin Access Only
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../login.php");
    exit();
}

include '../includes/db_connect.php';
include '../includes/functions.php';

$message = '';
$msg_type = '';

// Auto-create settings table & seed defaults
$conn->query("
    CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value VARCHAR(255) NOT NULL,
        description VARCHAR(500) DEFAULT '',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$conn->query("
    INSERT INTO system_settings (setting_key, setting_value, description) VALUES
        ('late_hourly_rate', '300', 'Per-hour charge for late vehicle returns (in ₹)'),
        ('gst_percentage', '18', 'GST tax percentage applied on extra/late charges'),
        ('grace_period_minutes', '60', 'Minutes of grace before late charges kick in')
    ON DUPLICATE KEY UPDATE setting_key = setting_key
");

// ==========================================
// 💾 SAVE SETTINGS HANDLER
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $fields = [
        'late_hourly_rate'     => ['label' => 'Hourly Late Fee', 'min' => 0, 'max' => 50000],
        'gst_percentage'       => ['label' => 'GST Percentage',  'min' => 0, 'max' => 100],
        'grace_period_minutes' => ['label' => 'Grace Period',    'min' => 0, 'max' => 1440],
    ];
    
    $errors = [];
    foreach ($fields as $key => $meta) {
        $val = isset($_POST[$key]) ? floatval($_POST[$key]) : 0;
        if ($val < $meta['min'] || $val > $meta['max']) {
            $errors[] = "{$meta['label']} must be between {$meta['min']} and {$meta['max']}.";
            continue;
        }
        $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        $str_val = strval($val);
        $stmt->bind_param("ss", $str_val, $key);
        $stmt->execute();
        $stmt->close();
    }
    
    if (empty($errors)) {
        $_SESSION['admin_msg'] = "System settings updated successfully.";
        $_SESSION['admin_msg_type'] = "success";
    } else {
        $_SESSION['admin_msg'] = implode(' ', $errors);
        $_SESSION['admin_msg_type'] = "danger";
    }
    
    header("Location: admin_settings.php");
    exit();
}

// Check session for PRG toast messages
if (isset($_SESSION['admin_msg'])) {
    $message = $_SESSION['admin_msg'];
    $msg_type = $_SESSION['admin_msg_type'];
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

// ==========================================
// 📊 FETCH CURRENT SETTINGS
// ==========================================
$hourly_rate   = get_system_setting($conn, 'late_hourly_rate', '300');
$gst_pct       = get_system_setting($conn, 'gst_percentage', '18');
$grace_minutes = get_system_setting($conn, 'grace_period_minutes', '60');

// Example calculation for preview
$example_late_hours = 3;
$example_base = $example_late_hours * floatval($hourly_rate);
$example_gst = round($example_base * (floatval($gst_pct) / 100), 2);
$example_total = $example_base + $example_gst;

$page_title = "System Settings | Admin";
include '../includes/header.php';
?>

<style>
    :root {
        --teal-primary: #4da89c;
        --mint-secondary: #8bd0b4;
        --mint-pale: #ccecd4;
        --teal-dark: #1a2624;
    }
    body { background-color: #f4f7f6; }

    .settings-card {
        border-radius: 24px; border: 1px solid rgba(0,0,0,0.03); background: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.04); overflow: hidden;
    }
    .settings-header {
        background: linear-gradient(135deg, var(--teal-primary) 0%, var(--teal-dark) 100%);
        color: white; padding: 2.5rem; position: relative; overflow: hidden;
    }
    .settings-header::after {
        content: '\f013'; font-family: 'Font Awesome 5 Free'; font-weight: 900;
        position: absolute; right: -20px; bottom: -40px; font-size: 12rem;
        color: rgba(255,255,255,0.04); transform: rotate(-15deg); pointer-events: none;
    }
    .form-control-custom {
        border: 2px solid rgba(77, 168, 156, 0.15); border-radius: 14px; padding: 15px 20px;
        font-weight: 700; font-size: 1.1rem; color: var(--teal-dark); transition: all 0.3s;
    }
    .form-control-custom:focus {
        border-color: var(--teal-primary); box-shadow: 0 0 0 4px rgba(77, 168, 156, 0.12); outline: none;
    }
    .preview-card {
        border-radius: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px dashed rgba(77, 168, 156, 0.25); position: sticky; top: 100px;
    }
    .setting-group {
        background: #f8faf9; border-radius: 16px; padding: 25px; margin-bottom: 20px;
        border: 1px solid rgba(77, 168, 156, 0.08); transition: all 0.3s;
    }
    .setting-group:hover { border-color: var(--teal-primary); transform: translateX(4px); box-shadow: 0 5px 15px rgba(77,168,156,0.08); }
    .setting-icon {
        width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center;
        justify-content: center; font-size: 1.3rem; flex-shrink: 0;
    }

    /* Toast */
    #admin-toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .admin-toast {
        background: white; border-radius: 16px; padding: 16px 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        display: flex; align-items: center; gap: 15px; min-width: 320px;
        transform: translateX(120%); opacity: 0; transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    .admin-toast.show { transform: translateX(0); opacity: 1; }
    .admin-toast.success { border-left: 5px solid #198754; }
    .admin-toast.danger { border-left: 5px solid #dc3545; }
</style>

<div id="admin-toast-container"></div>

<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="dashboard-content">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="<?php echo $base_url; ?>admin/dashboard.php" class="text-decoration-none fw-bold" style="color: var(--teal-primary);">Admin Panel</a></li>
                        <li class="breadcrumb-item active fw-bold text-muted" aria-current="page">System Settings</li>
                    </ol>
                </nav>
                <h2 class="fw-black text-dark mb-0"><i class="fas fa-cogs me-2" style="color: var(--teal-primary);"></i>System Configuration</h2>
            </div>
        </div>

        <div class="row g-5">
            
            <!-- LEFT: Settings Form -->
            <div class="col-lg-7">
                <div class="settings-card">
                    <div class="settings-header">
                        <h3 class="fw-black mb-1"><i class="fas fa-sliders-h me-2"></i>Late Return & Billing Rules</h3>
                        <p class="mb-0 opacity-75 fw-bold">Configure the penalty engine, tax rates, and grace periods for the entire platform.</p>
                    </div>
                    
                    <form action="admin_settings.php" method="POST" class="p-4 p-md-5">
                        
                        <div class="setting-group d-flex align-items-start gap-4">
                            <div class="setting-icon bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label fw-black text-dark mb-1">Hourly Late Fee</label>
                                <p class="text-muted small fw-bold mb-3">Amount charged per hour after the grace period expires. Used in the formula: <code>Chargeable Hours × Rate</code></p>
                                <div class="input-group">
                                    <span class="input-group-text bg-white fw-bold border-end-0" style="border-radius: 14px 0 0 14px; border: 2px solid rgba(77,168,156,0.15); border-right: 0;">₹</span>
                                    <input type="number" class="form-control form-control-custom border-start-0" name="late_hourly_rate" 
                                           value="<?php echo htmlspecialchars($hourly_rate); ?>" min="0" max="50000" step="10" required
                                           id="input_rate" oninput="updatePreview()"
                                           style="border-radius: 0 14px 14px 0;">
                                    <span class="input-group-text bg-white fw-bold text-muted" style="border-radius: 0 14px 14px 0; border: 2px solid rgba(77,168,156,0.15); border-left: 0;">/hr</span>
                                </div>
                            </div>
                        </div>

                        <div class="setting-group d-flex align-items-start gap-4">
                            <div class="setting-icon bg-success bg-opacity-10 text-success">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label fw-black text-dark mb-1">GST Percentage</label>
                                <p class="text-muted small fw-bold mb-3">Tax applied on late charges. Standard Indian GST is 18%. This is applied on top of the base extra charge.</p>
                                <div class="input-group">
                                    <input type="number" class="form-control form-control-custom" name="gst_percentage"
                                           value="<?php echo htmlspecialchars($gst_pct); ?>" min="0" max="100" step="0.5" required
                                           id="input_gst" oninput="updatePreview()"
                                           style="border-radius: 14px 0 0 14px;">
                                    <span class="input-group-text bg-white fw-bold text-muted" style="border-radius: 0 14px 14px 0; border: 2px solid rgba(77,168,156,0.15); border-left: 0;">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="setting-group d-flex align-items-start gap-4">
                            <div class="setting-icon bg-info bg-opacity-10 text-info">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label fw-black text-dark mb-1">Grace Period</label>
                                <p class="text-muted small fw-bold mb-3">Number of minutes after the due time where NO late charges are applied. Default: 60 minutes (1 hour).</p>
                                <div class="input-group">
                                    <input type="number" class="form-control form-control-custom" name="grace_period_minutes"
                                           value="<?php echo htmlspecialchars($grace_minutes); ?>" min="0" max="1440" step="5" required
                                           id="input_grace" oninput="updatePreview()"
                                           style="border-radius: 14px 0 0 14px;">
                                    <span class="input-group-text bg-white fw-bold text-muted" style="border-radius: 0 14px 14px 0; border: 2px solid rgba(77,168,156,0.15); border-left: 0;">minutes</span>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="save_settings" class="btn text-white fw-bold rounded-pill shadow-lg w-100 py-3 mt-3" style="background: linear-gradient(135deg, var(--teal-primary) 0%, var(--teal-dark) 100%); font-size: 1.05rem;">
                            <i class="fas fa-save me-2"></i>Save Configuration
                        </button>
                    </form>
                </div>
            </div>

            <!-- RIGHT: Live Preview -->
            <div class="col-lg-5">
                <div class="preview-card p-4 p-md-5">
                    <h5 class="fw-black text-dark mb-1"><i class="fas fa-calculator me-2" style="color: var(--teal-primary);"></i>Live Preview</h5>
                    <p class="text-muted small fw-bold mb-4">Example: Customer returns <strong>3.5 hours late</strong></p>

                    <div class="bg-white rounded-4 p-4 shadow-sm border mb-4">
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span class="fw-bold">Late Duration</span>
                            <span class="fw-bold text-dark" id="prev_late">3h 30m</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span class="fw-bold">Grace Period</span>
                            <span class="fw-bold text-success" id="prev_grace">-60 min (free)</span>
                        </div>
                        <hr class="my-2 opacity-10">
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span class="fw-bold">Chargeable Hours</span>
                            <span class="fw-bold text-dark" id="prev_hours">ceil(150/60) = 3 hrs</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span class="fw-bold">Base Extra Charge</span>
                            <span class="fw-bold text-dark" id="prev_base">₹900.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 text-muted small">
                            <span class="fw-bold">GST (<span id="prev_gst_label">18</span>%)</span>
                            <span class="fw-bold text-dark" id="prev_gst">₹162.00</span>
                        </div>
                        <hr class="my-2 opacity-10">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-black text-dark">Total Penalty</span>
                            <h4 class="fw-black mb-0" style="color: var(--teal-primary);" id="prev_total">₹1,062.00</h4>
                        </div>
                    </div>

                    <div class="alert border-0 rounded-4 shadow-sm p-3 mb-0" style="background: rgba(77,168,156,0.08); border: 1px solid rgba(77,168,156,0.15) !important;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle me-3 fs-5" style="color: var(--teal-primary);"></i>
                            <small class="fw-bold text-dark">This preview updates in real-time as you adjust the settings above.</small>
                        </div>
                    </div>
                </div>

                <!-- Formula Reference -->
                <div class="settings-card mt-4 p-4">
                    <h6 class="fw-black text-dark mb-3"><i class="fas fa-book me-2 text-muted"></i>Penalty Formula</h6>
                    <div class="bg-dark text-white rounded-4 p-4" style="font-family: 'Fira Code', monospace; font-size: 0.8rem; line-height: 1.8;">
                        <code style="color: #8bd0b4;">IF</code> <code style="color: #e0eadb;">late_time</code> <code style="color: #ffc107;"><=</code> <code style="color: #e0eadb;">grace_period</code><br>
                        &nbsp;&nbsp;<code style="color: #6c757d;">→ No Charge</code><br><br>
                        <code style="color: #8bd0b4;">ELSE</code><br>
                        &nbsp;&nbsp;<code style="color: #e0eadb;">hours</code> = <code style="color: #ffc107;">ceil</code>(<code style="color: #e0eadb;">(late - grace)</code> / 60)<br>
                        &nbsp;&nbsp;<code style="color: #e0eadb;">charge</code> = <code style="color: #e0eadb;">hours</code> × <code style="color: #e0eadb;">rate</code><br>
                        &nbsp;&nbsp;<code style="color: #e0eadb;">gst</code> = <code style="color: #e0eadb;">charge</code> × <code style="color: #e0eadb;">gst%</code><br>
                        &nbsp;&nbsp;<code style="color: #4da89c; font-weight: bold;">total</code> = <code style="color: #e0eadb;">charge</code> + <code style="color: #e0eadb;">gst</code>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Live Preview Calculator
    function updatePreview() {
        const rate = parseFloat(document.getElementById('input_rate').value) || 0;
        const gst = parseFloat(document.getElementById('input_gst').value) || 0;
        const grace = parseInt(document.getElementById('input_grace').value) || 0;
        
        const totalLateMinutes = 210; // 3h 30m example
        const chargeableMinutes = Math.max(0, totalLateMinutes - grace);
        const chargeableHours = Math.ceil(chargeableMinutes / 60);
        
        const baseCharge = chargeableHours * rate;
        const gstAmount = baseCharge * (gst / 100);
        const total = baseCharge + gstAmount;
        
        document.getElementById('prev_grace').textContent = `-${grace} min (free)`;
        document.getElementById('prev_hours').textContent = chargeableMinutes > 0 
            ? `ceil(${chargeableMinutes}/60) = ${chargeableHours} hrs` 
            : '0 hrs (within grace)';
        document.getElementById('prev_base').textContent = '₹' + baseCharge.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('prev_gst_label').textContent = gst;
        document.getElementById('prev_gst').textContent = '₹' + gstAmount.toLocaleString('en-IN', {minimumFractionDigits: 2});
        document.getElementById('prev_total').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits: 2});
    }

    // Toast Engine
    <?php if($message): ?>
    document.addEventListener("DOMContentLoaded", function() {
        const toastContainer = document.getElementById('admin-toast-container');
        const type = '<?php echo $msg_type; ?>';
        const msg = '<?php echo addslashes($message); ?>';
        let icon = type === 'success' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
        const toast = document.createElement('div');
        toast.className = `admin-toast ${type}`;
        toast.innerHTML = `<i class="fas ${icon} fs-4"></i><div><h6 class="fw-bold mb-0 text-dark">System Update</h6><small class="text-muted fw-bold">${msg}</small></div>`;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 500); }, 5000);
    });
    <?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>
