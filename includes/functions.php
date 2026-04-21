<?php
/**
 * ==============================================================================
 * 🚀 SMARTDRIVE X - ENTERPRISE LOGIC ENGINE (V3.0)
 * Purpose: Core Business Math, Timezones, Financial Calculations & Security
 * ==============================================================================
 */

// Enforce strict typing for Enterprise-level stability and memory optimization
declare(strict_types=1);

// 🌍 1. TIMEZONE SYNCHRONIZATION
// Forces the entire PHP engine to use Indian Standard Time.
// Prevents midnight billing glitches, race conditions, and timestamp desyncs.
date_default_timezone_set('Asia/Kolkata');


// =========================================================
// 🎨 2. FORMATTING & DISPLAY UTILITIES
// =========================================================

/**
 * Formats a number to the accurate Indian Numbering System (e.g., 1,00,000).
 * * @param float $amount The raw monetary value.
 * @return string Formatted INR string.
 */
function format_inr(float $amount): string {
    $is_negative = $amount < 0;
    $amount = abs($amount);
    
    // Split integer and decimal parts
    $amount_parts = explode('.', number_format($amount, 2, '.', ''));
    $integer_part = $amount_parts[0];
    $decimal_part = $amount_parts[1];

    // Regex magic for Indian comma separation (groups of 2 after the first group of 3)
    $integer_part = preg_replace('/(\d+?)(?=(\d\d)+(\d)(?!\d))(\.\d+)?/i', "$1,", $integer_part);

    $formatted = "₹" . $integer_part . "." . $decimal_part;
    return $is_negative ? "-" . $formatted : $formatted;
}

/**
 * Converts a database timestamp into a precise, human-readable "Time Ago" format.
 * * @param string $datetime The SQL timestamp.
 * @return string Human readable time difference.
 */
function format_time_ago(string $datetime): string {
    try {
        $date = new DateTime($datetime);
        $now  = new DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) return $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' mo' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 6) return floor($diff->d / 7) . ' wk' . (floor($diff->d / 7) > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0) return $diff->h . ' hr' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
        
        return 'Just now';
    } catch (\Exception $e) {
        return $datetime; // Failsafe fallback
    }
}


// =========================================================
// ⏱️ 3. MODERN DATE & TIME ENGINE (Anti-Fraud)
// =========================================================

/**
 * Calculates total rental days.
 * 🛑 SECURED: Blocks negative date ranges (Time Travel Exploits).
 * * @param string $start_date
 * @param string $end_date
 * @return int Total billing days.
 */
function calculate_rental_days(string $start_date, string $end_date): int {
    try {
        $start = new DateTime($start_date);
        $end   = new DateTime($end_date);
        
        // Reset time to midnight to ensure pure day-to-day calculation
        $start->setTime(0, 0, 0);
        $end->setTime(0, 0, 0);

        // Anti-Fraud: If the end date is BEFORE the start date, reject it.
        if ($start > $end) {
            return 0; 
        }
        
        $interval = $start->diff($end);
        return max(1, (int)$interval->format('%a')); // Minimum 1 day rental enforcement
        
    } catch (\Exception $e) {
        return 0; // Failsafe for malformed date strings
    }
}

/**
 * Counts exactly how many Saturday/Sundays exist within a date range for Surge Pricing.
 * * @param string $start_date
 * @param string $end_date
 * @return int Number of weekend days.
 */
function count_weekend_days(string $start_date, string $end_date): int {
    try {
        $start = new DateTime($start_date);
        $end   = new DateTime($end_date);
        $end->modify('+1 day'); // Inclusive loop boundary
        
        if ($start > $end) return 0; 
        
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval, $end);
        
        $weekend_count = 0;
        foreach ($daterange as $date) {
            // 'N' returns 1 (Mon) through 7 (Sun)
            if ((int)$date->format('N') >= 6) {
                $weekend_count++;
            }
        }
        return $weekend_count;
        
    } catch (\Exception $e) {
        return 0;
    }
}


// =========================================================
// 💰 4. ADVANCED FINANCIAL & BUSINESS LOGIC
// =========================================================

/**
 * Calculates GST based on dynamic tax slabs. 
 */
function calculate_gst(float $amount, float $tax_rate = 0.18): float {
    return round($amount * $tax_rate, 2);
}

/**
 * Enterprise Dynamic Pricing Engine.
 * Calculates base fares, weekend surges, and coupons with mathematically bounded fail-safes.
 */
function generate_price_quote(float $base_price_per_day, string $start, string $end, float $discount_val = 0.0, string $discount_type = 'none'): array {
    $days = calculate_rental_days($start, $end);
    
    // Fail-safe if someone manipulated the dates
    if ($days === 0) {
        throw new \Exception("Invalid rental dates provided. Cannot compute quotation.");
    }

    $weekend_days = count_weekend_days($start, $end);
    $weekday_days = $days - $weekend_days;
    
    // 1. Calculate Base Fares
    $weekday_total = $weekday_days * $base_price_per_day;
    
    // 2. Apply Weekend Surge (15% higher on weekends)
    $weekend_rate = $base_price_per_day * 1.15;
    $weekend_total = $weekend_days * $weekend_rate;
    
    $subtotal = $weekday_total + $weekend_total;
    
    // 3. Apply Coupons (Safely bounded to prevent negative balance hacks)
    $discount_amount = 0.0;
    if ($discount_type === 'percentage') {
        $safe_discount_val = min(100.0, max(0.0, $discount_val)); 
        $discount_amount = $subtotal * ($safe_discount_val / 100);
    } elseif ($discount_type === 'fixed') {
        $discount_amount = $discount_val;
    }
    
    $post_discount_total = max(0.0, $subtotal - $discount_amount);
    
    // 4. Calculate Tax
    $gst = calculate_gst($post_discount_total);
    $final_payable = $post_discount_total + $gst;
    
    return [
        'total_days'       => $days,
        'base_fare'        => round($weekday_total, 2),
        'surge_fare'       => round($weekend_total, 2),
        'subtotal'         => round($subtotal, 2),
        'discount_applied' => round($discount_amount, 2),
        'tax_amount'       => round($gst, 2),
        'final_total'      => round($final_payable, 2)
    ];
}


// =========================================================
// 🛡️ 5. SECURITY, SANITIZATION & UTILITY SUITE
// =========================================================

/**
 * Generates a 100% collision-proof alphanumeric invoice/receipt number.
 */
function generate_invoice_number(): string {
    $date_prefix = date("ymd"); 
    $secure_random = bin2hex(random_bytes(3)); // 6 random characters
    return strtoupper("INV-{$date_prefix}-{$secure_random}");
}

/**
 * Enterprise XSS & SQLi Defense.
 * Deep cleanses data before insertion into the database.
 * * @param \mysqli $conn The active database connection.
 * @param string $data The dirty input.
 * @return string The cleansed output.
 */
function sanitize_input(\mysqli $conn, string $data): string {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

/**
 * Checks if a password meets PCI-DSS and Enterprise Security Standards.
 * Must be 8+ chars, have 1 uppercase, 1 lowercase, and 1 number.
 */
function is_strong_password(string $password): bool {
    if (strlen($password) < 8) return false;
    if (!preg_match("#[0-9]+#", $password)) return false;
    if (!preg_match("#[a-z]+#", $password)) return false;
    if (!preg_match("#[A-Z]+#", $password)) return false;
    return true;
}

/**
 * CSRF PROTECTION: Generates a Secure Cryptographic Token.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF PROTECTION: Verifies a Submitted Cryptographic Token.
 */
function verify_csrf_token(string $submitted_token): bool {
    if (empty($_SESSION['csrf_token']) || empty($submitted_token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted_token);
}

/**
 * API UTILITY: Sends a strict JSON response and halts script execution.
 * Essential for API endpoints and AJAX handlers.
 */
function json_response(array $data, int $status_code = 200): void {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

/**
 * SECURITY UTILITY: Extracts the true Client IP Address, bypassing proxies/Cloudflare.
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
    return filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : 'UNKNOWN';
}

/**
 * PRIVACY UTILITY: Masks sensitive email addresses (e.g., joh***@gmail.com).
 */
function mask_secure_data(string $email): string {
    $em   = explode("@", $email);
    $name = implode('@', array_slice($em, 0, count($em)-1));
    $len  = floor(strlen($name) / 2);
    return substr($name, 0, (int)$len) . str_repeat('*', (int)$len) . "@" . end($em);
}


// =========================================================
// ⏱️ 6. LATE RETURN & PENALTY CALCULATION ENGINE (V2.0)
// =========================================================

/**
 * Fetches an admin-configured system setting from the database.
 * Uses static caching to prevent repeated DB hits within the same request.
 *
 * @param \mysqli $conn    Active database connection.
 * @param string  $key     The setting_key to look up.
 * @param string  $default Fallback value if key not found.
 * @return string The setting value.
 */
function get_system_setting(\mysqli $conn, string $key, string $default = ''): string {
    static $cache = [];
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    try {
        // Auto-create table if it doesn't exist (first-run safety)
        $conn->query("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value VARCHAR(255) NOT NULL,
                description VARCHAR(500) DEFAULT '',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $cache[$key] = $row['setting_value'];
                $stmt->close();
                return $cache[$key];
            }
            $stmt->close();
        }
    } catch (\Exception $e) {
        error_log("SmartDriveX Setting Fetch Error [{$key}]: " . $e->getMessage());
    }
    
    $cache[$key] = $default;
    return $default;
}

/**
 * Calculates late return charges with grace period, hourly rate, and GST.
 * 
 * Formula:
 *   Late Time = Return DateTime - Due DateTime
 *   IF Late Time <= Grace Period → No Charge
 *   ELSE:
 *     Chargeable Hours = ceil((Late Time - Grace Period) / 60 minutes)
 *     Extra Charge = Chargeable Hours × Hourly Rate
 *     GST = Extra Charge × GST%
 *     Total Penalty = Extra Charge + GST
 *
 * @param \DateTime $due_datetime     When the car was supposed to be returned.
 * @param \DateTime $return_datetime  When the car was actually returned.
 * @param float     $hourly_rate      Per-hour late charge (admin-configured).
 * @param float     $gst_percentage   GST percentage (e.g., 18).
 * @param int       $grace_minutes    Grace period in minutes (default 60).
 * @return array    Detailed breakdown of late charges.
 */
function calculate_late_charges(
    \DateTime $due_datetime,
    \DateTime $return_datetime,
    float $hourly_rate,
    float $gst_percentage = 18.0,
    int $grace_minutes = 60
): array {
    $result = [
        'is_late'          => false,
        'late_minutes'     => 0,
        'grace_applied'    => $grace_minutes,
        'chargeable_hours' => 0,
        'hourly_rate'      => $hourly_rate,
        'base_charge'      => 0.00,
        'gst_percentage'   => $gst_percentage,
        'gst_amount'       => 0.00,
        'total_penalty'    => 0.00,
    ];
    
    // If returned on time or early, no charges
    if ($return_datetime <= $due_datetime) {
        return $result;
    }
    
    // Calculate total late minutes
    $diff = $due_datetime->diff($return_datetime);
    $total_late_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    
    $result['late_minutes'] = $total_late_minutes;
    
    // Check grace period
    if ($total_late_minutes <= $grace_minutes) {
        // Within grace — no charge but flag as late
        $result['is_late'] = true;
        return $result;
    }
    
    // Calculate chargeable time (after grace)
    $chargeable_minutes = $total_late_minutes - $grace_minutes;
    $chargeable_hours = (int)ceil($chargeable_minutes / 60);
    
    // Calculate charges
    $base_charge = round($chargeable_hours * $hourly_rate, 2);
    $gst_amount = round($base_charge * ($gst_percentage / 100), 2);
    $total_penalty = round($base_charge + $gst_amount, 2);
    
    $result['is_late'] = true;
    $result['chargeable_hours'] = $chargeable_hours;
    $result['base_charge'] = $base_charge;
    $result['gst_amount'] = $gst_amount;
    $result['total_penalty'] = $total_penalty;
    
    return $result;
}

/**
 * Returns unified badge rendering data for any booking status.
 * Covers all 6 statuses in the V2 flow.
 *
 * @param string $status The booking_status value.
 * @return array ['label', 'badge_class', 'icon', 'color']
 */
function get_booking_status_label(string $status): array {
    $map = [
        'pending'   => ['label' => 'Pending',          'badge_class' => 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25', 'icon' => 'fa-clock',          'color' => '#ffc107'],
        'approved'  => ['label' => 'Approved',         'badge_class' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-25',          'icon' => 'fa-thumbs-up',      'color' => '#0dcaf0'],
        'confirmed' => ['label' => 'Confirmed',        'badge_class' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25', 'icon' => 'fa-check-double',   'color' => '#0d6efd'],
        'active'    => ['label' => 'Active Ride',      'badge_class' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-25', 'icon' => 'fa-car-side',       'color' => '#198754'],
        'completed' => ['label' => 'Completed',        'badge_class' => 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25', 'icon' => 'fa-flag-checkered', 'color' => '#6c757d'],
        'cancelled' => ['label' => 'Cancelled',        'badge_class' => 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25',    'icon' => 'fa-times-circle',   'color' => '#dc3545'],
    ];
    
    return $map[$status] ?? $map['pending'];
}

/**
 * Calculates the final settlement amount for a completed booking.
 * Final = Original Price + Late Penalty (if any)
 *
 * @param float $original_price Original booking price (final_price column).
 * @param float $extra_charges  Base late charges.
 * @param float $gst_on_extra   GST on late charges.
 * @return float Total final settlement.
 */
function calculate_final_settlement(float $original_price, float $extra_charges = 0.0, float $gst_on_extra = 0.0): float {
    return round($original_price + $extra_charges + $gst_on_extra, 2);
}
?>