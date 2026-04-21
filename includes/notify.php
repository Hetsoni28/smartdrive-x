<?php
/**
 * ==============================================================================
 * 🚀 SMARTDRIVE X - ENTERPRISE NOTIFICATION DISPATCHER (V3.0)
 * Purpose: High-performance, non-blocking notification trigger library.
 * ==============================================================================
 */

declare(strict_types=1);

if (!function_exists('notify')) {

    /**
     * Core push function — securely inserts a notification record.
     * Uses Static Caching and Prepared Statements for maximum performance.
     *
     * @param \mysqli $conn    The active database connection.
     * @param int     $user_id 0 = broadcast to all.
     * @param string  $title   The headline of the notification.
     * @param string  $message The body content.
     * @param string  $type    success|info|warning|danger|announcement
     * @param string  $link    Relative URL for redirection.
     * @param string  $icon    FontAwesome class (e.g., fa-car).
     * @return int|bool        Returns Insert ID on success, false on failure.
     */
    function notify(\mysqli $conn, int $user_id, string $title, string $message, string $type = 'info', string $link = '', string $icon = 'fa-bell') {
        
        // 🚀 PERFORMANCE OPTIMIZATION: Static Flag
        // Ensures the schema check only runs ONCE per PHP execution lifecycle, saving massive DB overhead.
        static $schema_checked = false;
        
        try {
            if (!$schema_checked) {
                $conn->query("
                    CREATE TABLE IF NOT EXISTS notifications (
                        id         INT AUTO_INCREMENT PRIMARY KEY,
                        user_id    INT NOT NULL,
                        title      VARCHAR(200) NOT NULL,
                        message    TEXT NOT NULL,
                        type       ENUM('success','info','warning','danger','announcement') DEFAULT 'info',
                        icon       VARCHAR(50) DEFAULT 'fa-bell',
                        link       VARCHAR(500) DEFAULT '',
                        is_read    TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user_read (user_id, is_read)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $schema_checked = true;
            }

            // 🛡️ SECURE INSERTION: Prepared Statements prevent SQL Injection
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link, icon, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
            if (!$stmt) {
                throw new \Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("isssss", $user_id, $title, $message, $type, $link, $icon);
            $stmt->execute();
            $insert_id = $stmt->insert_id;
            $stmt->close();

            return $insert_id;

        } catch (\Exception $e) {
            // 🛑 NON-BLOCKING FAILSAFE: 
            // If notification fails, do NOT crash the user's booking/payment flow.
            error_log("SmartDriveX Notification Dispatch Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch a notification to ALL registered Administrator accounts.
     */
    function notify_admins(\mysqli $conn, string $title, string $message, string $type = 'info', string $link = '', string $icon = 'fa-bell'): void {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE role_id = 1");
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($admin = $result->fetch_assoc()) {
                notify($conn, (int)$admin['id'], $title, $message, $type, $link, $icon);
            }
            $stmt->close();
        } catch (\Exception $e) {
            error_log("SmartDriveX Admin Dispatch Error: " . $e->getMessage());
        }
    }


    // ==========================================================================
    // ── PRE-BUILT ENTERPRISE TRIGGER SHORTCUTS ────────────────────────────────
    // ==========================================================================

    /** * Event: Customer just created a new booking.
     */
    function notify_booking_created(\mysqli $conn, int $user_id, int $booking_id, string $car_name, string $start_date, string $end_date, float $amount): void {
        // Formatted strings
        $formatted_amount = number_format($amount, 0);
        $date_range = date('d M', strtotime($start_date)) . " → " . date('d M Y', strtotime($end_date));

        // 1. Notify Admins
        notify_admins(
            $conn,
            "🚗 New Booking Received",
            "Customer #{$user_id} secured {$car_name} ({$date_range}) for ₹{$formatted_amount}.",
            'info',
            'admin/manage_bookings.php',
            'fa-calendar-check'
        );

        // 2. Notify Customer
        notify(
            $conn,
            $user_id,
            "Reservation Locked!",
            "Your request for the {$car_name} ({$date_range}) has been securely logged. Pending admin verification.",
            'info',
            'customer/rental_history.php',
            'fa-car-side'
        );
    }

    /** * Event: Admin manually confirmed a booking.
     */
    function notify_booking_confirmed(\mysqli $conn, int $user_id, int $booking_id, string $car_name): void {
        notify(
            $conn,
            $user_id,
            "✅ Booking Confirmed!",
            "Great news! Your booking for the {$car_name} is fully confirmed. See you on the road!",
            'success',
            'customer/rental_history.php',
            'fa-check-circle'
        );
    }

    /** * Event: Admin or System cancelled a booking.
     */
    function notify_booking_cancelled(\mysqli $conn, int $user_id, int $booking_id, string $car_name, string $reason = ''): void {
        $msg = "Your reservation for the {$car_name} has been cancelled.";
        if (!empty($reason)) {
            $msg .= " Reason: {$reason}";
        }

        notify(
            $conn,
            $user_id,
            "❌ Booking Cancelled",
            $msg,
            'danger',
            'customer/rental_history.php',
            'fa-times-circle'
        );
    }

    /** * Event: Payment successfully processed.
     */
    function notify_payment_success(\mysqli $conn, int $user_id, int $booking_id, string $car_name, float $amount, string $method): void {
        $formatted_amount = number_format($amount, 0);
        
        // 1. Notify Customer
        notify(
            $conn,
            $user_id,
            "💳 Payment Successful",
            "₹{$formatted_amount} processed securely via {$method} for the {$car_name}. Your tax invoice is ready.",
            'success',
            "customer/invoice.php?id={$booking_id}",
            'fa-file-invoice-dollar'
        );

        // 2. Notify Admins
        notify_admins(
            $conn,
            "💰 Payment Cleared",
            "₹{$formatted_amount} received via {$method} for booking #BKG-{$booking_id} ({$car_name}).",
            'success',
            'admin/manage_bookings.php',
            'fa-money-bill-wave'
        );
    }

    /** * Event: New user registers on the platform.
     */
    function notify_welcome(\mysqli $conn, int $user_id, string $name): void {
        $first_name = explode(' ', trim($name))[0];
        notify(
            $conn,
            $user_id,
            "🎉 Welcome to SmartDrive X, {$first_name}!",
            "Your enterprise account is ready. Browse our premium fleet and book your first ride to start earning loyalty points!",
            'success',
            'customer/search_cars.php',
            'fa-star'
        );
    }

    /** * Event: Admin pushes a global announcement.
     */
    function notify_announcement(\mysqli $conn, string $title, string $message, string $link = ''): void {
        // user_id = 0 acts as a global broadcast flag
        notify($conn, 0, $title, $message, 'announcement', $link, 'fa-bullhorn');
    }

    /** * Event: System detects critically low fleet availability.
     */
    function notify_low_fleet(\mysqli $conn, int $available_count): void {
        notify_admins(
            $conn,
            "⚠️ Critical: Low Fleet Capacity",
            "Only {$available_count} vehicle(s) are currently operational. Review maintenance logs to restore capacity.",
            'warning',
            'admin/manage_cars.php',
            'fa-exclamation-triangle'
        );
    }


    // ==========================================================================
    // ── V2.0 ENHANCED FLOW NOTIFICATION TRIGGERS ─────────────────────────────
    // ==========================================================================

    /** * Event: Admin approved a booking — customer must now pay.
     */
    function notify_booking_approved(\mysqli $conn, int $user_id, int $booking_id, string $car_name, float $amount): void {
        $formatted_amount = number_format($amount, 0);
        
        // Notify Customer
        notify(
            $conn,
            $user_id,
            "✅ Booking Approved!",
            "Great news! Your booking for the {$car_name} (Ref: #BKG-{$booking_id}) has been approved. Please complete your payment of ₹{$formatted_amount} to confirm.",
            'success',
            "customer/payment.php?booking_id={$booking_id}",
            'fa-thumbs-up'
        );
    }

    /** * Event: Payment reminder for an approved booking.
     */
    function notify_payment_reminder(\mysqli $conn, int $user_id, int $booking_id, string $car_name): void {
        notify(
            $conn,
            $user_id,
            "💳 Payment Reminder",
            "Your booking for the {$car_name} (Ref: #BKG-{$booking_id}) is approved and awaiting payment. Complete it soon to lock your reservation!",
            'warning',
            "customer/payment.php?booking_id={$booking_id}",
            'fa-credit-card'
        );
    }

    /** * Event: Customer's vehicle return is overdue.
     */
    function notify_late_return_alert(\mysqli $conn, int $user_id, int $booking_id, string $car_name, string $due_date): void {
        notify(
            $conn,
            $user_id,
            "⚠️ Overdue Return Alert",
            "Your rental of the {$car_name} (Ref: #BKG-{$booking_id}) was due for return on {$due_date}. Late charges may apply. Please return the vehicle immediately.",
            'danger',
            'customer/rental_history.php',
            'fa-exclamation-triangle'
        );
    }

    /** * Event: Admin alerted about an overdue vehicle.
     */
    function notify_late_return_admin(\mysqli $conn, int $user_id, int $booking_id, string $car_name, string $customer_name): void {
        notify_admins(
            $conn,
            "🚨 Late Return Detected",
            "Customer {$customer_name} has not returned the {$car_name} (Booking #BKG-{$booking_id}). Late charges are accumulating.",
            'danger',
            'admin/late_returns.php',
            'fa-clock'
        );
    }

    /** * Event: Ride completed — customer can view final invoice.
     */
    function notify_ride_completed(\mysqli $conn, int $user_id, int $booking_id, string $car_name, bool $has_late_charges = false): void {
        $extra_msg = $has_late_charges 
            ? " Late return charges have been applied. View your final settlement for details."
            : " No late fees applied. Thank you for returning on time!";
        
        notify(
            $conn,
            $user_id,
            "🏁 Ride Completed!",
            "Your journey with the {$car_name} (Ref: #BKG-{$booking_id}) is now complete.{$extra_msg}",
            $has_late_charges ? 'warning' : 'success',
            "customer/final_invoice.php?id={$booking_id}",
            'fa-flag-checkered'
        );
    }

    /** * Event: Admin notified that customer completed payment.
     */
    function notify_payment_received_admin(\mysqli $conn, int $user_id, int $booking_id, string $car_name, float $amount, string $method): void {
        $formatted_amount = number_format($amount, 0);
        
        notify_admins(
            $conn,
            "💰 Payment Received",
            "Customer #{$user_id} paid ₹{$formatted_amount} via {$method} for booking #BKG-{$booking_id} ({$car_name}). Booking is now Confirmed.",
            'success',
            'admin/manage_bookings.php',
            'fa-money-bill-wave'
        );
    }
}
?>