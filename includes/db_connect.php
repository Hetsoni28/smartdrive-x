<?php
// ==========================================
// File: includes/db_connect.php
// Purpose: Establishes MySQL database connection (Improved Version)
// ==========================================

// 1. Enable MySQLi error reporting (for development)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 2. Database Credentials
    $host = "localhost";
    $username = "root";       
    $password = "";           
    $database = "smartdrive_db"; // ⚠️ Make sure this matches your DB name

    // 3. Create Connection
    $conn = mysqli_connect($host, $username, $password, $database);

    // 4. Set Charset (important for ₹, emojis, etc.)
    mysqli_set_charset($conn, "utf8mb4");

} catch (mysqli_sql_exception $e) {
    // 5. Handle Connection Error
    die("Database Connection Failed: " . $e->getMessage());
}
?>