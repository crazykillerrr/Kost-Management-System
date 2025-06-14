<?php
$page_title = 'Kelola Pembayaran - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle payment verification
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $payment_id = $_GET['id'];
    $admin_id = getUserId();
    
    if ($action === 'approve') {
        try {
            $db->beginTransaction();
            
            // Update payment status
            $update_query = "UPDATE payments SET status = 'completed', verified_by = ?, verified_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$admin_id, $payment_id]);
            
            // Get booking info
            $booking_query = "SELECT b.*, r.room_id FROM payments p 
                             JOIN bookings b ON p.booking_id = b.id 
                             JOIN rooms r ON b.room_id = r.id 
                             WHERE p.id = ?";
            $booking_stmt = $db->prepare($booking_query);
            $booking_stmt->execute([$payment_id]);
            $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($booking) {
                // Update booking status to active
                $booking_update = "UPDATE bookings SET status = 'active' WHERE id = ?";
                $booking_stmt = $db->prepare($booking_update);
                $booking_stmt->execute([$booking['id']]);
                
                // Update room status to occupied
                $room_update = "UPDATE rooms SET status = 'occupied' WHERE id = ?";
                $room_stmt = $db->prepare($room_update);
                $room_stmt->execute([$booking['room_id']]);
            }
            
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                         VALUES (?, 'APPROVE_PAYMENT', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([
                $admin_id, 
                "Approved payment ID: {$payment_id}", 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $db->commit();
            $success = "Pembayaran berhasil diverifikasi!";
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Terjadi kesalahan saat memverifikasi pembayaran.";
        }
    }
    
    if ($action === 'reject') {
        try {
            $update_query = "UPDATE payments SET status = 'failed', verified_by = ?, verified_at = NOW() WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$admin_id, $payment_id]);
            
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                         VALUES (?, 'REJECT_PAYMENT', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([
                $admin_id, 
                "Rejected payment ID: {$payment_id}", 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $success = "Pembayaran ditolak!";
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan saat menolak pembayaran.";
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$query = "SELECT p.*, b.id as booking_id, b.start_date, b.end_date, b.total_amount as booking_amount,
          u.full_name, u.email, r.room_number, r.room_type
          FROM payments p
          JOIN bookings b ON p.booking_id = b.id
          JOIN users u ON b.user_id = u.id
          JOIN rooms r ON b.room_id = r.id
          WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND p.status = ?";
    $params[] = $status;
}

if ($date_from) {
    $query .= " AND p.payment_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND p.payment_date <= ?";
    $params[] = $date_to;
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY p.created_at ASC";
        break;
    case 'amount_desc':
        $query .= " ORDER BY p.amount DESC";
        break;
    case 'amount_asc':
        $query .= " ORDER BY p.amount ASC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY p.created_at DESC";
        break;
}

// Execute query
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment statistics
    $stats_query = "SELECT 
                   COUNT(*) as total_payments,
                   COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
                   COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
                   COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
                   SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                   SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_revenue
                   FROM payments";
    $stats_stmt = $db->query($stats_query);
    $payment_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Kelola <span style="color: var(--pink-primary);">Pembayaran</span></h2>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="reports.php" class="btn btn-outline-secondary me-2">
                <i class="fas fa-chart-bar me-2"></i>Laporan
            </a>
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
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-money-bill-wave fa-2x mb-2" style="color: var(--pink-primary);"></i>
                    <h4 class="fw-bold"><?php echo $payment_stats['total_payments']; ?></h4>
                    <p class="text-muted mb-0">Total Pembayaran</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-clock fa-2x mb-2 text-warning"></i>
                    <h4 class="fw-bold"><?php echo $payment_stats['pending_payments']; ?></h4>
                    <p class="text-muted mb-0">Pending</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                    <h4 class="fw-bold"><?php echo $payment_stats['completed_payments']; ?></h4>
                    <p class="text-muted mb-0">Berhasil</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-times-circle fa-2x mb-2 text-danger"></i>
                    <h4 class="fw-bold"><?php echo $payment_stats['failed_payments']; ?></h4>
                    <p class="text-muted mb-0">Gagal</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Revenue Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                    <h4>Rp <?php echo number_format($payment_stats['total_revenue'], 0, ',', '.'); ?></h4>
                    <p class="mb-0">Total Revenue</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-hourglass-half fa-3x mb-3"></i>
                    <h4>Rp <?php echo number_format($payment_stats['pending_revenue'], 0, ',', '.'); ?></h4>
                    <p class="mb-0">Pending Revenue</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Berhasil</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="sort" class="form-label">Urutkan</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                        <option value="amount_desc" <?php echo $sort === 'amount_desc' ? 'selected' : ''; ?>>Nilai Tertinggi</option>
                        <option value="amount_asc" <?php echo $sort === 'amount_asc' ? 'selected' : ''; ?>>Nilai Terendah</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-pink me-2">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="payments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Payments Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Pembayaran (<?php echo count($payments); ?> pembayaran)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($payments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                    <h5>Tidak ada pembayaran ditemukan</h5>
                    <p class="text-muted">Silakan ubah filter untuk melihat pembayaran lainnya.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-pink">
                            <tr>
                                <th>Penyewa</th>
                                <th>Kamar</th>
                                <th>Jumlah</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Bukti</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo $payment['full_name']; ?></strong>
                                            <br><small class="text-muted"><?php echo $payment['email']; ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: var(--pink-primary);">
                                            <?php echo $payment['room_number']; ?>
                                        </span>
                                        <br><small class="text-muted"><?php echo ucfirst($payment['room_type']); ?></small>
                                    </td>
                                    <td>
                                        <strong>Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></strong>
                                        <br><small class="text-muted">Booking: Rp <?php echo number_format($payment['booking_amount'], 0, ',', '.'); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?>
                                        <br><small class="text-muted"><?php echo date('H:i', strtotime($payment['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $payment['status'] === 'pending' ? 'bg-warning' : 
                                                 ($payment['status'] === 'completed' ? 'bg-success' : 'bg-danger');
                                        ?>">
                                            <?php 
                                            echo $payment['status'] === 'pending' ? 'Pending' : 
                                                 ($payment['status'] === 'completed' ? 'Berhasil' : 'Gagal');
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($payment['receipt_url']): ?>
                                            <a href="../<?php echo $payment['receipt_url']; ?>" target="_blank" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-file-image"></i> Lihat
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($payment['status'] === 'pending' && $payment['receipt_url']): ?>
                                                <a href="payments.php?action=approve&id=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-outline-success" title="Approve"
                                                   onclick="return confirm('Approve pembayaran ini?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="payments.php?action=reject&id=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" title="Reject"
                                                   onclick="return confirm('Tolak pembayaran ini?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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
