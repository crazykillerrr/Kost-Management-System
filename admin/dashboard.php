<?php
$page_title = 'Admin Dashboard - KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Get statistics
try {
    // Room stats
    $room_query = "SELECT 
                  COUNT(*) as total_rooms,
                  COUNT(CASE WHEN status = 'available' THEN 1 END) as available_rooms,
                  COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_rooms,
                  COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_rooms
                  FROM rooms";
    $room_stmt = $db->query($room_query);
    $room_stats = $room_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Booking stats
    $booking_query = "SELECT 
                     COUNT(*) as total_bookings,
                     COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
                     COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_bookings,
                     COUNT(CASE WHEN status = 'active' THEN 1 END) as active_bookings,
                     COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                     COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_bookings
                     FROM bookings";
    $booking_stmt = $db->query($booking_query);
    $booking_stats = $booking_stmt->fetch(PDO::FETCH_ASSOC);
    
    // User stats
    $user_query = "SELECT 
                  COUNT(*) as total_users,
                  COUNT(CASE WHEN role = 'penyewa' THEN 1 END) as tenant_users,
                  COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_users
                  FROM users";
    $user_stmt = $db->query($user_query);
    $user_stats = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Revenue stats
    $revenue_query = "SELECT 
                     SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                     SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_revenue,
                     SUM(CASE WHEN MONTH(p.payment_date) = MONTH(CURRENT_DATE()) AND 
                               YEAR(p.payment_date) = YEAR(CURRENT_DATE()) AND 
                               p.status = 'completed' THEN p.amount ELSE 0 END) as current_month_revenue
                     FROM payments p";
    $revenue_stmt = $db->query($revenue_query);
    $revenue_stats = $revenue_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get occupancy rate
    $occupancy_query = "SELECT GetOccupancyRate() as occupancy_rate";
    $occupancy_stmt = $db->query($occupancy_query);
    $occupancy = $occupancy_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent bookings
    $recent_bookings_query = "SELECT b.*, u.full_name, u.email, r.room_number, r.room_type
                             FROM bookings b
                             JOIN users u ON b.user_id = u.id
                             JOIN rooms r ON b.room_id = r.id
                             ORDER BY b.created_at DESC
                             LIMIT 5";
    $recent_bookings_stmt = $db->query($recent_bookings_query);
    $recent_bookings = $recent_bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pending payments
    $pending_payments_query = "SELECT p.*, b.id as booking_id, u.full_name, r.room_number
                              FROM payments p
                              JOIN bookings b ON p.booking_id = b.id
                              JOIN users u ON b.user_id = u.id
                              JOIN rooms r ON b.room_id = r.id
                              WHERE p.status = 'pending' AND p.receipt_url IS NOT NULL
                              ORDER BY p.created_at DESC
                              LIMIT 5";
    $pending_payments_stmt = $db->query($pending_payments_query);
    $pending_payments = $pending_payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Dashboard <span style="color: var(--pink-primary);">Admin</span></h2>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-0">Selamat datang, <strong><?php echo getUsername(); ?></strong></p>
            <p class="text-muted">
                <i class="fas fa-calendar-alt me-1"></i>
                <?php echo date('l, d F Y'); ?>
            </p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 60px; height: 60px; background-color: var(--pink-light);">
                        <i class="fas fa-bed fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo $room_stats['total_rooms']; ?></h3>
                    <p class="text-muted mb-0">Total Kamar</p>
                    <small class="text-success">
                        <?php echo $room_stats['available_rooms']; ?> tersedia
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 60px; height: 60px; background-color: var(--pink-light);">
                        <i class="fas fa-calendar-check fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo $booking_stats['total_bookings']; ?></h3>
                    <p class="text-muted mb-0">Total Booking</p>
                    <small class="text-warning">
                        <?php echo $booking_stats['pending_bookings']; ?> pending
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 60px; height: 60px; background-color: var(--pink-light);">
                        <i class="fas fa-users fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo $user_stats['tenant_users']; ?></h3>
                    <p class="text-muted mb-0">Total Penyewa</p>
                    <small class="text-info">
                        <?php echo $user_stats['admin_users']; ?> admin
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 60px; height: 60px; background-color: var(--pink-light);">
                        <i class="fas fa-chart-line fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo number_format($occupancy['occupancy_rate'], 1); ?>%</h3>
                    <p class="text-muted mb-0">Tingkat Hunian</p>
                    <small class="text-primary">
                        <?php echo $room_stats['occupied_rooms']; ?> kamar terisi
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Revenue Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
                    <h4>Rp <?php echo number_format($revenue_stats['total_revenue'], 0, ',', '.'); ?></h4>
                    <p class="mb-0">Total Revenue</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card" style="background: linear-gradient(135deg, #007bff 0%, #6610f2 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-calendar-alt fa-3x mb-3"></i>
                    <h4>Rp <?php echo number_format($revenue_stats['current_month_revenue'], 0, ',', '.'); ?></h4>
                    <p class="mb-0">Revenue Bulan Ini</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-clock fa-3x mb-3"></i>
                    <h4>Rp <?php echo number_format($revenue_stats['pending_revenue'], 0, ',', '.'); ?></h4>
                    <p class="mb-0">Pending Revenue</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Recent Bookings -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Booking Terbaru</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list me-1"></i>Semua Booking
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_bookings)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">Belum ada booking.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-pink">
                                    <tr>
                                        <th>Penyewa</th>
                                        <th>Kamar</th>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo $booking['full_name']; ?></strong>
                                                    <br><small class="text-muted"><?php echo $booking['email']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: var(--pink-primary);">
                                                    <?php echo $booking['room_number']; ?>
                                                </span>
                                                <br><small class="text-muted"><?php echo ucfirst($booking['room_type']); ?></small>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
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
                                                <a href="booking-detail.php?id=<?php echo $booking['id']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
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
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Pending Payments -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pembayaran Pending</h5>
                    <span class="badge bg-warning"><?php echo count($pending_payments); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pending_payments)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">Tidak ada pembayaran pending.</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($pending_payments as $payment): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo $payment['full_name']; ?></h6>
                                            <p class="mb-1">Kamar <?php echo $payment['room_number']; ?></p>
                                            <small class="text-muted">
                                                Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?>
                                            </small>
                                        </div>
                                        <a href="payment-detail.php?id=<?php echo $payment['id']; ?>" 
                                           class="btn btn-sm btn-pink">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Aksi Cepat</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="rooms.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-bed me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Kelola Kamar</h6>
                                <small class="text-muted">Tambah, edit, hapus kamar</small>
                            </div>
                        </a>
                        <a href="bookings.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-calendar-check me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Kelola Booking</h6>
                                <small class="text-muted">Approve, reject booking</small>
                            </div>
                        </a>
                        <a href="payments.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-money-bill-wave me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Kelola Pembayaran</h6>
                                <small class="text-muted">Verifikasi pembayaran</small>
                            </div>
                        </a>
                        <a href="reports.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-chart-bar me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Laporan</h6>
                                <small class="text-muted">Lihat laporan dan statistik</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
