<?php
// Task Scheduler Script
// This script handles automated tasks like cleaning old logs, updating room status, etc.

require_once '../config/database.php';

class TaskScheduler {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function cleanOldLogs() {
        try {
            // Delete logs older than 90 days
            $query = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $deleted = $stmt->rowCount();
            echo "Cleaned $deleted old log entries\n";
            
            return true;
        } catch (PDOException $e) {
            echo "Error cleaning logs: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function updateExpiredBookings() {
        try {
            // Update bookings that have passed their end date
            $query = "UPDATE bookings SET status = 'completed' 
                     WHERE status = 'active' AND end_date < CURDATE()";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $updated = $stmt->rowCount();
            echo "Updated $updated expired bookings\n";
            
            // Update room status for completed bookings
            $query = "UPDATE rooms r 
                     INNER JOIN bookings b ON r.id = b.room_id 
                     SET r.status = 'available' 
                     WHERE b.status = 'completed' AND r.status = 'occupied'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $rooms_updated = $stmt->rowCount();
            echo "Updated $rooms_updated room statuses\n";
            
            return true;
        } catch (PDOException $e) {
            echo "Error updating expired bookings: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function sendPaymentReminders() {
        try {
            // Find bookings with pending payments due in 3 days
            $query = "SELECT b.*, u.email, u.full_name, r.room_number, p.amount
                     FROM bookings b
                     INNER JOIN users u ON b.user_id = u.id
                     INNER JOIN rooms r ON b.room_id = r.id
                     INNER JOIN payments p ON b.id = p.booking_id
                     WHERE b.status = 'approved' 
                     AND p.status = 'pending'
                     AND DATEDIFF(b.start_date, CURDATE()) <= 3
                     AND DATEDIFF(b.start_date, CURDATE()) >= 0";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($pending_payments as $payment) {
                // In a real application, you would send email here
                echo "Payment reminder needed for: {$payment['full_name']} - Room {$payment['room_number']}\n";
                
                // Log the reminder
                $log_query = "INSERT INTO activity_logs (user_id, action, description) 
                             VALUES (?, 'PAYMENT_REMINDER', ?)";
                $log_stmt = $this->db->prepare($log_query);
                $log_stmt->execute([
                    $payment['user_id'], 
                    "Payment reminder sent for booking ID: {$payment['id']}"
                ]);
            }
            
            echo "Processed " . count($pending_payments) . " payment reminders\n";
            return true;
        } catch (PDOException $e) {
            echo "Error sending payment reminders: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function generateMonthlyReport() {
        try {
            $current_month = date('Y-m');
            
            // Get monthly statistics
            $stats_query = "SELECT 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_bookings,
                SUM(CASE WHEN status IN ('completed', 'active') THEN total_amount ELSE 0 END) as total_revenue
                FROM bookings 
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
            
            $stmt = $this->db->prepare($stats_query);
            $stmt->execute([$current_month]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get occupancy rate
            $occupancy_query = "SELECT GetOccupancyRate() as occupancy_rate";
            $stmt = $this->db->prepare($occupancy_query);
            $stmt->execute();
            $occupancy = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "Monthly Report for $current_month:\n";
            echo "- Completed Bookings: {$stats['completed_bookings']}\n";
            echo "- Active Bookings: {$stats['active_bookings']}\n";
            echo "- Total Revenue: Rp " . number_format($stats['total_revenue'], 0, ',', '.') . "\n";
            echo "- Occupancy Rate: {$occupancy['occupancy_rate']}%\n";
            
            return true;
        } catch (PDOException $e) {
            echo "Error generating monthly report: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function runAllTasks() {
        echo "Starting scheduled tasks at " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat('-', 50) . "\n";
        
        $this->cleanOldLogs();
        echo "\n";
        
        $this->updateExpiredBookings();
        echo "\n";
        
        $this->sendPaymentReminders();
        echo "\n";
        
        // Run monthly report only on the 1st of each month
        if (date('d') == '01') {
            $this->generateMonthlyReport();
            echo "\n";
        }
        
        echo str_repeat('-', 50) . "\n";
        echo "All tasks completed at " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the scheduler
$scheduler = new TaskScheduler();
$scheduler->runAllTasks();

// To set up automated execution:
// 1. Linux/Mac: Add to crontab
//    0 2 * * * /usr/bin/php /path/to/task-scheduler.php
// 
// 2. Windows: Use Task Scheduler
//    - Create Basic Task
//    - Set trigger (daily at 2 AM)
//    - Action: Start a program
//    - Program: php.exe
//    - Arguments: /path/to/task-scheduler.php
?>
