<?php
$page_title = 'Verifikasi Pembayaran - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle payment verification
if (isset($_GET['action']) && isset($_GET['payment_id'])) {
    $action = $_GET['action'];
    $payment_id = $_GET['payment_id'];
    $admin_id = getUserId();
    
    try {
        $db->beginTransaction();
        
        if ($action === 'approve') {
            // Update payment status
            $update_payment = "UPDATE payments SET status = 'completed', verified_by = ?, verified_at = NOW() WHERE id = ?";
            $payment_stmt = $db->prepare($update_payment);
            $payment_stmt->execute([$admin_id, $payment_id]);
            
            // Get booking info
            $booking_query = "SELECT b.id, b.room_id FROM bookings b 
                     JOIN payments p ON b.id = p.booking_id 
                     WHERE p.id = ?";
            $booking_stmt = $db->prepare($booking_query);
            $booking_stmt->execute([$payment_id]);
            $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($booking) {
                // Update booking status to active (not just approved)
                $update_booking = "UPDATE bookings SET status = 'active' WHERE id = ?";
                $booking_update_stmt = $db->prepare($update_booking);
                $booking_update_stmt->execute([$booking['id']]);
                
                // Update room status to occupied
                $update_room = "UPDATE rooms SET status = 'occupied' WHERE id = ?";
                $room_stmt = $db->prepare($update_room);
                $room_stmt->execute([$booking['room_id']]);
            }
            
            $success = "Pembayaran berhasil diverifikasi dan booking diaktifkan!";
            
        } elseif ($action === 'reject') {
            // Update payment status
            $update_payment = "UPDATE payments SET status = 'rejected', verified_by = ?, verified_at = NOW() WHERE id = ?";
            $payment_stmt = $db->prepare($update_payment);
            $payment_stmt->execute([$admin_id, $payment_id]);
            
            // Get booking info and update
            $booking_query = "SELECT b.id, b.room_id FROM bookings b 
                             JOIN payments p ON b.id = p.booking_id 
                             WHERE p.id = ?";
            $booking_stmt = $db->prepare($booking_query);
            $booking_stmt->execute([$payment_id]);
            $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($booking) {
                // Update booking status to rejected
                $update_booking = "UPDATE bookings SET status = 'rejected' WHERE id = ?";
                $booking_update_stmt = $db->prepare($update_booking);
                $booking_update_stmt->execute([$booking['id']]);
                
                // Update room status back to available
                $update_room = "UPDATE rooms SET status = 'available' WHERE id = ?";
                $room_stmt = $db->prepare($update_room);
                $room_stmt->execute([$booking['room_id']]);
            }
            
            $success = "Pembayaran ditolak dan booking dibatalkan.";
        }
        
        // Log activity
        $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                     VALUES (?, 'VERIFY_PAYMENT', ?, ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            $admin_id, 
            ucfirst($action) . " payment ID: {$payment_id}", 
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $db->commit();
        
    } catch (PDOException $e) {
        $db->rollback();
        $error = "Terjadi kesalahan saat memproses verifikasi.";
    }
}

// Get payments that need verification
try {
    $query = "SELECT p.*, b.id as booking_id, b.start_date, b.end_date, b.total_amount,
              u.full_name, u.email, u.phone,
              r.room_number, r.room_type,
              admin.full_name as verified_by_name
              FROM payments p
              JOIN bookings b ON p.booking_id = b.id
              JOIN users u ON b.user_id = u.id
              JOIN rooms r ON b.room_id = r.id
              LEFT JOIN users admin ON p.verified_by = admin.id
              WHERE p.receipt_url IS NOT NULL
              ORDER BY p.created_at DESC";
    
    $stmt = $db->query($query);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Verifikasi <span style="color: var(--pink-primary);">Pembayaran</span></h2>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="dashboard.php" class="btn btn-pink">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Pembayaran (<?php echo count($payments); ?> pembayaran)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($payments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <h5>Tidak ada pembayaran yang perlu diverifikasi</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-pink">
                            <tr>
                                <th>Penyewa</th>
                                <th>Kamar</th>
                                <th>Periode</th>
                                <th>Pembayaran</th>
                                <th>Bukti</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                            <?php if ($payment['phone']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($payment['phone']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>Kamar <?php echo htmlspecialchars($payment['room_number']); ?></strong>
                                            <br><small class="text-muted"><?php echo ucfirst($payment['room_type']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo date('d/m/Y', strtotime($payment['start_date'])); ?></strong>
                                            <br><small class="text-muted">s/d <?php echo date('d/m/Y', strtotime($payment['end_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></strong>
                                            <br><small class="text-muted"><?php echo ucfirst($payment['payment_method']); ?></small>
                                            <br><small class="text-info"><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($payment['receipt_url']): ?>
                                            <a href="<?php echo $payment['receipt_url']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> Lihat
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $payment['status'] === 'pending' ? 'bg-warning' : 
                                                 ($payment['status'] === 'completed' ? 'bg-success' : 'bg-danger');
                                        ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                        <?php if ($payment['verified_by_name']): ?>
                                            <br><small class="text-muted">oleh <?php echo htmlspecialchars($payment['verified_by_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <div class="btn-group" role="group">
                                                <a href="payment-verification.php?action=approve&payment_id=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="Setujui"
                                                   onclick="return confirm('Setujui pembayaran ini?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="payment-verification.php?action=reject&payment_id=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="Tolak"
                                                   onclick="return confirm('Tolak pembayaran ini?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
