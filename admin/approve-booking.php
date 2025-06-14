<?php
$page_title = 'Approve Booking - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireLogin();
requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID booking tidak valid.";
    header('Location: bookings.php');
    exit();
}

$booking_id = $_GET['id'];
$admin_id = getUserId();
$database = new Database();
$db = $database->getConnection();

// Get booking details
try {
    $query = "SELECT b.*, r.room_number, r.room_type, u.full_name, u.email 
              FROM bookings b
              JOIN rooms r ON b.room_id = r.id
              JOIN users u ON b.user_id = u.id
              WHERE b.id = ? AND b.status = 'pending'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $_SESSION['error'] = "Booking tidak ditemukan atau sudah diproses.";
        header('Location: bookings.php');
        exit();
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan sistem.";
    header('Location: bookings.php');
    exit();
}

// Process approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            // Use stored procedure for approval
            $stmt = $db->prepare("CALL ApproveBooking(?, ?, @status)");
            $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $admin_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Get result
            $stmt = $db->query("SELECT @status as status");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (strpos($result['status'], 'SUCCESS') !== false) {
                // Also update payment status if receipt exists
                $payment_query = "UPDATE payments p 
                         JOIN bookings b ON p.booking_id = b.id 
                         SET p.status = 'completed', p.verified_by = ?, p.verified_at = NOW() 
                         WHERE b.id = ? AND p.receipt_url IS NOT NULL";
                $payment_stmt = $db->prepare($payment_query);
                $payment_stmt->execute([$admin_id, $booking_id]);
                
                $_SESSION['success'] = "Booking berhasil disetujui dan pembayaran diverifikasi.";
            } else {
                $_SESSION['error'] = "Gagal menyetujui booking: " . $result['status'];
            }
            
        } elseif ($action === 'reject') {
            // Reject booking
            $notes = $_POST['notes'] ?? '';
            
            $update_query = "UPDATE bookings SET status = 'rejected', notes = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$notes, $booking_id]);
            
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                         VALUES (?, 'REJECT_BOOKING', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([
                $admin_id, 
                "Rejected booking ID: {$booking_id} - {$notes}", 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['success'] = "Booking berhasil ditolak.";
        }
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan sistem.";
    }
    
    header('Location: bookings.php');
    exit();
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" style="color: var(--pink-primary);">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="bookings.php" style="color: var(--pink-primary);">Bookings</a></li>
            <li class="breadcrumb-item active" aria-current="page">Approve Booking</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-check-circle me-2"></i>Approve Booking</h3>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5 class="mb-3">Detail Booking</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Booking ID:</strong></td>
                                    <td>#<?php echo $booking['id']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Kamar:</strong></td>
                                    <td><?php echo $booking['room_number']; ?> (<?php echo ucfirst($booking['room_type']); ?>)</td>
                                </tr>
                                <tr>
                                    <td><strong>Tanggal Booking:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Periode:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($booking['end_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Durasi:</strong></td>
                                    <td><?php echo $booking['duration_months']; ?> bulan</td>
                                </tr>
                                <tr>
                                    <td><strong>Total:</strong></td>
                                    <td><strong class="text-primary">Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="mb-3">Detail Penyewa</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Nama:</strong></td>
                                    <td><?php echo $booking['full_name']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo $booking['email']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                </tr>
                            </table>
                            
                            <?php if ($booking['notes']): ?>
                                <div class="mt-3">
                                    <h6>Catatan:</h6>
                                    <p class="text-muted"><?php echo $booking['notes']; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-lg w-100" onclick="return confirm('Apakah Anda yakin ingin menyetujui booking ini?')">
                                    <i class="fas fa-check me-2"></i>Setujui Booking
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-md-6">
                            <button type="button" class="btn btn-danger btn-lg w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="fas fa-times me-2"></i>Tolak Booking
                            </button>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="bookings.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Tolak Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Alasan Penolakan</label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                  placeholder="Masukkan alasan penolakan booking..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>Tolak Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
