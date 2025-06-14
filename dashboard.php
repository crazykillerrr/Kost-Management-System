<?php
$page_title = 'Dashboard - KostKu';
require_once 'config/database.php';
require_once 'config/session.php';

// Require login
requireLogin();

// Redirect admin to admin dashboard
if (isAdmin()) {
    header('Location: admin/dashboard.php');
    exit();
}

$user_id = getUserId();
$database = new Database();
$db = $database->getConnection();

// Get user data
try {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's active booking
    $query = "SELECT b.*, r.room_number, r.room_type, r.price, r.image_url 
              FROM bookings b
              JOIN rooms r ON b.room_id = r.id
              WHERE b.user_id = ? AND b.status IN ('active', 'approved')
              ORDER BY b.start_date ASC
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $active_booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user's booking stats
    $query = "SELECT 
              COUNT(*) as total_bookings,
              COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
              COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_bookings,
              COUNT(CASE WHEN status = 'active' THEN 1 END) as active_bookings,
              COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
              COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_bookings
              FROM bookings
              WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent activities
    $query = "SELECT * FROM activity_logs 
              WHERE user_id = ?
              ORDER BY created_at DESC
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payment status
    $query = "SELECT p.* FROM payments p
              JOIN bookings b ON p.booking_id = b.id
              WHERE b.user_id = ? AND p.status = 'pending'
              ORDER BY p.created_at DESC
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $pending_payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Dashboard <span style="color: var(--pink-primary);">Penyewa</span></h2>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-0">Selamat datang, <strong><?php echo $user['full_name']; ?></strong></p>
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
                        <i class="fas fa-calendar-check fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo $booking_stats['total_bookings']; ?></h3>
                    <p class="text-muted mb-0">Total Booking</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 60px; height: 60px; background-color: var(--pink-light);">
                        <i class="fas fa-bed fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo $booking_stats['active_bookings']; ?></h3>
                    <p class="text-muted mb-0">Kamar Aktif</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 60px; height: 60px; background-color: var(--pink-light);">
                        <i class="fas fa-clock fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo $booking_stats['pending_bookings']; ?></h3>
                    <p class="text-muted mb-0">Booking Pending</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-4">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 60px; height: 60px; background-color: var(--pink-light);">
                        <i class="fas fa-check-circle fa-2x" style="color: var(--pink-primary);"></i>
                    </div>
                    <h3 class="fw-bold"><?php echo $booking_stats['completed_bookings']; ?></h3>
                    <p class="text-muted mb-0">Booking Selesai</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Active Booking -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Kamar Aktif</h5>
                    <a href="my-bookings.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list me-1"></i>Semua Booking
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($active_booking): ?>
                        <div class="row">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <img src="<?php echo $active_booking['image_url'] ?: '/placeholder.svg?height=200&width=300'; ?>" 
                                     class="img-fluid rounded" alt="<?php echo $active_booking['room_number']; ?>" 
                                     style="height: 200px; width: 100%; object-fit: cover;">
                            </div>
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5>Kamar <?php echo $active_booking['room_number']; ?></h5>
                                    <span class="badge <?php 
                                        echo $active_booking['status'] === 'approved' ? 'bg-primary' : 'bg-success';
                                    ?>">
                                        <?php echo $active_booking['status'] === 'approved' ? 'Approved' : 'Aktif'; ?>
                                    </span>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong>Tipe:</strong> <?php echo ucfirst($active_booking['room_type']); ?></p>
                                        <p class="mb-1"><strong>Tanggal Mulai:</strong> <?php echo date('d/m/Y', strtotime($active_booking['start_date'])); ?></p>
                                        <p class="mb-1"><strong>Tanggal Selesai:</strong> <?php echo date('d/m/Y', strtotime($active_booking['end_date'])); ?></p>
                                    </div>
                                    <div class="col-sm-6">
                                        <p class="mb-1"><strong>Durasi:</strong> <?php echo $active_booking['duration_months']; ?> bulan</p>
                                        <p class="mb-1"><strong>Harga:</strong> Rp <?php echo number_format($active_booking['price'], 0, ',', '.'); ?> /bulan</p>
                                        <p class="mb-1"><strong>Total:</strong> Rp <?php echo number_format($active_booking['total_amount'], 0, ',', '.'); ?></p>
                                    </div>
                                </div>
                                
                                <div class="d-flex">
                                    <a href="room-detail.php?id=<?php echo $active_booking['room_id']; ?>" class="btn btn-sm btn-outline-secondary me-2">
                                        <i class="fas fa-info-circle me-1"></i>Detail Kamar
                                    </a>
                                    <a href="my-bookings.php" class="btn btn-sm btn-pink">
                                        <i class="fas fa-eye me-1"></i>Lihat Booking
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-bed fa-3x text-muted"></i>
                            </div>
                            <h5>Anda tidak memiliki kamar aktif</h5>
                            <p class="text-muted">Booking kamar sekarang untuk mendapatkan tempat tinggal yang nyaman.</p>
                            <a href="rooms.php" class="btn btn-pink">
                                <i class="fas fa-search me-2"></i>Cari Kamar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Aktivitas Terbaru</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($activities)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">Belum ada aktivitas.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Aktivitas</th>
                                        <th>Deskripsi</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $icon = '';
                                                switch ($activity['action']) {
                                                    case 'LOGIN':
                                                        $icon = '<i class="fas fa-sign-in-alt text-success"></i>';
                                                        break;
                                                    case 'CREATE_BOOKING':
                                                        $icon = '<i class="fas fa-calendar-plus text-primary"></i>';
                                                        break;
                                                    case 'CANCEL_BOOKING':
                                                        $icon = '<i class="fas fa-calendar-times text-danger"></i>';
                                                        break;
                                                    case 'UPLOAD_RECEIPT':
                                                        $icon = '<i class="fas fa-upload text-info"></i>';
                                                        break;
                                                    default:
                                                        $icon = '<i class="fas fa-history text-secondary"></i>';
                                                }
                                                echo $icon . ' ' . $activity['action'];
                                                ?>
                                            </td>
                                            <td><?php echo $activity['description']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></td>
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
            <!-- User Profile Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Profil Saya</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                             style="width: 100px; height: 100px; background-color: var(--pink-light); font-size: 2.5rem;">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                    </div>
                    
                    <h5 class="mb-1"><?php echo $user['full_name']; ?></h5>
                    <p class="text-muted mb-3"><?php echo $user['email']; ?></p>
                    
                    <div class="d-grid">
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-user-edit me-2"></i>Edit Profil
                        </a>
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user me-2"></i>Username</span>
                        <span class="text-muted"><?php echo $user['username']; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-phone me-2"></i>Telepon</span>
                        <span class="text-muted"><?php echo $user['phone'] ?: '-'; ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-alt me-2"></i>Bergabung</span>
                        <span class="text-muted"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                    </li>
                </ul>
            </div>
            
            <!-- Payment Reminder -->
            <?php if ($pending_payment): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pembayaran Pending</h5>
                    </div>
                    <div class="card-body">
                        <p>Anda memiliki pembayaran yang belum diselesaikan. Segera lakukan pembayaran untuk mengaktifkan booking Anda.</p>
                        <div class="d-grid">
                            <a href="my-bookings.php" class="btn btn-warning">
                                <i class="fas fa-money-bill-wave me-2"></i>Lihat Pembayaran
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Quick Links -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Menu Cepat</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="rooms.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-search me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Cari Kamar</h6>
                                <small class="text-muted">Temukan kamar yang sesuai</small>
                            </div>
                        </a>
                        <a href="my-bookings.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-calendar-check me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Booking Saya</h6>
                                <small class="text-muted">Kelola booking Anda</small>
                            </div>
                        </a>
                        <a href="profile.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-user-edit me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Edit Profil</h6>
                                <small class="text-muted">Perbarui informasi pribadi</small>
                            </div>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="fas fa-headset me-3" style="color: var(--pink-primary);"></i>
                            <div>
                                <h6 class="mb-0">Bantuan</h6>
                                <small class="text-muted">Hubungi customer service</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
