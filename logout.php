<?php
/**
 * ==============================================================================
 * 🛡️ SMARTDRIVE X - ENTERPRISE LOGOUT PROTOCOL (V3.0)
 * Purpose: Securely terminates the session, destroys cookies, and invalidates cache.
 * ==============================================================================
 */

// 1. STRICT CACHE INVALIDATION
// This prevents users on public computers from hitting the "Back" button 
// and viewing sensitive dashboard data from the browser's local cache.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // A date in the past

// 2. Initialize the Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Clear ALL Session Variables Explicitly
// This is faster and safer than relying solely on session_destroy()
$_SESSION = array();

// 4. Annihilate the Session Cookie
// We must exactly match the parameters used to create the cookie in session_check.php
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    
    // PHP 7.3+ supports passing an array for advanced cookie options like SameSite
    setcookie(
        session_name(), 
        '', 
        time() - 86400, // Set expiration to 24 hours in the past
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// 5. Destroy the Session completely from the server
session_destroy();

// 6. Dynamic Base URL Calculation (Failsafe for live servers)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_dir = '/smartdrive_x/';
$base_url = $protocol . $host . $base_dir;

// 7. Secure Redirect to the Login Portal
// We append a query string so the login page can display a "Ghost Toast" confirming logout.
header("Location: " . $base_url . "login.php?logout=success");
exit();
?>