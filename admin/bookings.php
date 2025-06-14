<?php
$page_title = 'Kelola Booking - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle booking actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $booking_id = $_GET['id'];
    $admin_id = getUserId();
    
    if ($action === 'approve') {
        try {
            // Use stored procedure for approval
            $stmt = $db->prepare("CALL ApproveBooking(?, ?, @status)");
            $stmt->bindParam(1, $booking_id, PDO::PARAM_INT);
            $stmt->bindParam(2, $admin_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Get result
            $result_stmt = $db->query("SELECT @status as status");
            $result = $result_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (strpos($result['status'], 'SUCCESS') !== false) {
                // Also update payment status if receipt exists
                $payment_query = "UPDATE payments p 
                             JOIN bookings b ON p.booking_id = b.id 
                             SET p.status = 'completed', p.verified_by = ?, p.verified_at = NOW() 
                             WHERE b.id = ? AND p.receipt_url IS NOT NULL";
                $payment_stmt = $db->prepare($payment_query);
                $payment_stmt->execute([$admin_id, $booking_id]);
            
                $success = "Booking berhasil diapprove dan pembayaran diverifikasi!";
            } else {
                $error = "Gagal approve booking: " . $result['status'];
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan saat approve booking.";
        }
    }
    
    if ($action === 'reject') {
        try {
            $db->beginTransaction();
            
            // Get room ID before rejecting
            $room_query = "SELECT room_id FROM bookings WHERE id = ?";
            $room_stmt = $db->prepare($room_query);
            $room_stmt->execute([$booking_id]);
            $room_id = $room_stmt->fetch(PDO::FETCH_ASSOC)['room_id'];
            
            // Update booking status
            $update_query = "UPDATE bookings SET status = 'rejected' WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$booking_id]);
            
            // Ensure room is available
            $room_update_query = "UPDATE rooms SET status = 'available' WHERE id = ?";
            $room_update_stmt = $db->prepare($room_update_query);
            $room_update_stmt->execute([$room_id]);
            
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                         VALUES (?, 'REJECT_BOOKING', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([
                $admin_id, 
                "Rejected booking ID: {$booking_id}", 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $db->commit();
            $success = "Booking berhasil ditolak!";
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Terjadi kesalahan saat menolak booking.";
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$room_type = $_GET['room_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$query = "SELECT b.*, u.full_name, u.email, u.phone, r.room_number, r.room_type, r.price, r.image_url,
          p.status as payment_status, p.receipt_url
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN rooms r ON b.room_id = r.id
          LEFT JOIN payments p ON b.id = p.booking_id
          WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND b.status = ?";
    $params[] = $status;
}

if ($room_type) {
    $query .= " AND r.room_type = ?";
    $params[] = $room_type;
}

if ($date_from) {
    $query .= " AND b.booking_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $query .= " AND b.booking_date <= ?";
    $params[] = $date_to;
}

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY b.created_at ASC";
        break;
    case 'start_date':
        $query .= " ORDER BY b.start_date ASC";
        break;
    case 'amount_desc':
        $query .= " ORDER BY b.total_amount DESC";
        break;
    case 'amount_asc':
        $query .= " ORDER BY b.total_amount ASC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY b.created_at DESC";
        break;
}

// Execute query
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get booking statistics
    $stats_query = "SELECT 
                   COUNT(*) as total_bookings,
                   COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
                   COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_bookings,
                   COUNT(CASE WHEN status = 'active' THEN 1 END) as active_bookings,
                   COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                   COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_bookings,
                   SUM(total_amount) as total_revenue
                   FROM bookings";
    $stats_stmt = $db->query($stats_query);
    $booking_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Kelola <span style="color: var(--pink-primary);">Booking</span></h2>
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
        <div class="col-md-2">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-calendar-check fa-2x mb-2" style="color: var(--pink-primary);"></i>
                    <h4 class="fw-bold"><?php echo $booking_stats['total_bookings']; ?></h4>
                    <p class="text-muted mb-0 small">Total</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-clock fa-2x mb-2 text-warning"></i>
                    <h4 class="fw-bold"><?php echo $booking_stats['pending_bookings']; ?></h4>
                    <p class="text-muted mb-0 small">Pending</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-check fa-2x mb-2 text-primary"></i>
                    <h4 class="fw-bold"><?php echo $booking_stats['approved_bookings']; ?></h4>
                    <p class="text-muted mb-0 small">Approved</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-play fa-2x mb-2 text-success"></i>
                    <h4 class="fw-bold"><?php echo $booking_stats['active_bookings']; ?></h4>
                    <p class="text-muted mb-0 small">Aktif</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-check-circle fa-2x mb-2 text-info"></i>
                    <h4 class="fw-bold"><?php echo $booking_stats['completed_bookings']; ?></h4>
                    <p class="text-muted mb-0 small">Selesai</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-times fa-2x mb-2 text-danger"></i>
                    <h4 class="fw-bold"><?php echo $booking_stats['rejected_bookings']; ?></h4>
                    <p class="text-muted mb-0 small">Ditolak</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Ditolak</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="room_type" class="form-label">Tipe Kamar</label>
                    <select class="form-select" id="room_type" name="room_type">
                        <option value="">Semua Tipe</option>
                        <option value="single" <?php echo $room_type === 'single' ? 'selected' : ''; ?>>Single</option>
                        <option value="double" <?php echo $room_type === 'double' ? 'selected' : ''; ?>>Double</option>
                        <option value="suite" <?php echo $room_type === 'suite' ? 'selected' : ''; ?>>Suite</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="sort" class="form-label">Urutkan</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                        <option value="start_date" <?php echo $sort === 'start_date' ? 'selected' : ''; ?>>Tanggal Mulai</option>
                        <option value="amount_desc" <?php echo $sort === 'amount_desc' ? 'selected' : ''; ?>>Nilai Tertinggi</option>
                        <option value="amount_asc" <?php echo $sort === 'amount_asc' ? 'selected' : ''; ?>>Nilai Terendah</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-pink me-2">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="bookings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bookings Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Booking (<?php echo count($bookings); ?> booking)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($bookings)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5>Tidak ada booking ditemukan</h5>
                    <p class="text-muted">Silakan ubah filter untuk melihat booking lainnya.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-pink">
                            <tr>
                                <th>Penyewa</th>
                                <th>Kamar</th>
                                <th>Periode</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Pembayaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo $booking['full_name']; ?></strong>
                                            <br><small class="text-muted"><?php echo $booking['email']; ?></small>
                                            <?php if ($booking['phone']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?php echo $booking['phone']; ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo $booking['image_url'] ?: '/placeholder.svg?height=40&width=60'; ?>" 
                                                 class="rounded me-2" alt="<?php echo $booking['room_number']; ?>" 
                                                 style="width: 60px; height: 40px; object-fit: cover;">
                                            <div>
                                                <strong><?php echo $booking['room_number']; ?></strong>
                                                <br><small class="text-muted"><?php echo ucfirst($booking['room_type']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo date('d/m/Y', strtotime($booking['start_date'])); ?></strong>
                                            <br><small class="text-muted">s/d <?php echo date('d/m/Y', strtotime($booking['end_date'])); ?></small>
                                            <br><small class="text-info"><?php echo $booking['duration_months']; ?> bulan</small>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?></strong>
                                        <br><small class="text-muted">Booking: <?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $booking['status'] === 'pending' ? 'bg-warning' : 
                                                 ($booking['status'] === 'approved' ? 'bg-primary' : 
                                                 ($booking['status'] === 'active' ? 'bg-success' : 
                                                 ($booking['status'] === 'completed' ? 'bg-info' : 'bg-danger')));
                                        ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($booking['payment_status']): ?>
                                            <span class="badge <?php 
                                                echo $booking['payment_status'] === 'pending' ? 'bg-warning' : 
                                                     ($booking['payment_status'] === 'completed' ? 'bg-success' : 'bg-danger');
                                            ?>">
                                                <?php echo ucfirst($booking['payment_status']); ?>
                                            </span>
                                            <?php if ($booking['receipt_url']): ?>
                                                <br><small class="text-success">
                                                    <i class="fas fa-file-image me-1"></i>Ada bukti
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="booking-detail.php?id=<?php echo $booking['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <a href="bookings.php?action=approve&id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-sm btn-outline-success" title="Approve"
                                                   onclick="return confirm('Approve booking ini?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="bookings.php?action=reject&id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" title="Reject"
                                                   onclick="return confirm('Tolak booking ini?')">
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
