<?php
/**
 * ==============================================================================
 * 🛡️ SMARTDRIVE X - ENTERPRISE SECURITY & AUTHENTICATION ENGINE (V3.0)
 * Purpose: Session Management, RBAC, Fingerprinting, and CSRF Defense
 * ==============================================================================
 */

declare(strict_types=1);

// 1. 🌐 DYNAMIC BASE URL CONFIGURATION
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_dir = '/smartdrive_x/';
$base_url = $protocol . $host . $base_dir;

// 2. 🔐 STRICT SESSION SECURITY INITIALIZATION
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie parameters before starting the session
    session_set_cookie_params([
        'lifetime' => 0,                      // Expires when browser closes
        'path'     => '/',
        'domain'   => $host,
        'secure'   => ($protocol === "https://"), // Only send over HTTPS if active
        'httponly' => true,                   // Prevent JavaScript XSS cookie theft
        'samesite' => 'Strict'                // Prevent Cross-Site Request Forgery
    ]);
    session_start();
}

/**
 * Extracts the true client IP Address, bypassing proxies and load balancers.
 */
function get_secure_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Grab the first IP in the forwarded list
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : '0.0.0.0';
}

/**
 * The Ultimate Security Gatekeeper
 * Enforces authentication, role-based access, and session integrity.
 * * @param int|null $required_role Pass 1 for Admin, 2 for Customer.
 */
function require_login(?int $required_role = null): void {
    global $base_url;

    // A. AUTHORIZATION CHECK
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $base_url . "login.php?error=unauthorized");
        exit();
    }

    // B. SESSION HIJACKING PROTECTION (SHA-256 Subnet Fingerprinting)
    // We hash the User Agent and IP Subnet. Using the subnet instead of exact IP 
    // prevents mobile users from being logged out when their cell tower switches.
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UnknownAgent';
    $ip_parts = explode('.', get_secure_client_ip());
    $subnet = count($ip_parts) === 4 ? "{$ip_parts[0]}.{$ip_parts[1]}.{$ip_parts[2]}" : get_secure_client_ip();
    
    $current_fingerprint = hash('sha256', $user_agent . $subnet);
    
    if (!isset($_SESSION['user_fingerprint'])) {
        // Initial login mapping
        $_SESSION['user_fingerprint'] = $current_fingerprint;
    } elseif (!hash_equals($_SESSION['user_fingerprint'], $current_fingerprint)) {
        // 🚨 HACKER ALERT: Session cookie was copied to a different device
        error_log("CRITICAL: Session Hijack Prevented for User ID: " . $_SESSION['user_id']);
        session_unset();
        session_destroy();
        header("Location: " . $base_url . "login.php?error=session_hijacked");
        exit();
    }

    // C. INACTIVITY AUTO-LOGOUT (Bank-Level Security)
    $timeout_duration = 1800; // 30 minutes
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: " . $base_url . "login.php?error=timeout");
        exit();
    }
    // Refresh activity timestamp
    $_SESSION['last_activity'] = time(); 

    // D. ROLE-BASED ACCESS CONTROL (RBAC)
    if ($required_role !== null && (int)$_SESSION['role_id'] !== $required_role) {
        // Bounce user to their correct contextual dashboard
        if ((int)$_SESSION['role_id'] === 1) {
            header("Location: " . $base_url . "admin/dashboard.php");
        } else {
            header("Location: " . $base_url . "customer/dashboard.php");
        }
        exit();
    }
}

// =========================================================
// 🛡️ CSRF (Cross-Site Request Forgery) DEFENSE ENGINE
// =========================================================

/**
 * Generates a secure, cryptographically random token for forms.
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the CSRF token on form submission using constant-time comparison.
 * * @param string|null $token The token submitted via POST/GET.
 */
function verify_csrf_token(?string $token): void {
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        // Log the intrusion attempt
        error_log("CSRF Attack Blocked. IP: " . get_secure_client_ip());
        
        // Return proper HTTP response code instead of a raw die()
        http_response_code(403);
        die(json_encode([
            'status' => 'error',
            'message' => 'Security Alert: Invalid Form Token. Request blocked by SmartDrive X Firewall.'
        ]));
    }
}
?>