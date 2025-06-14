<?php
$page_title = 'Beranda - KostKu';
require_once 'config/database.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';

$database = new Database();
$db = $database->getConnection();

// Get available rooms count
$query = "SELECT COUNT(*) as available_rooms FROM rooms WHERE status = 'available'";
$stmt = $db->prepare($query);
$stmt->execute();
$available_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['available_rooms'];

// Get featured rooms
$query = "SELECT * FROM rooms WHERE status = 'available' ORDER BY created_at DESC LIMIT 3";
$stmt = $db->prepare($query);
$stmt->execute();
$featured_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Section -->
<div class="container-fluid py-5" style="background: linear-gradient(135deg, var(--pink-light) 0%, #ffffff 100%);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold text-dark mb-4">
                    Temukan <span style="color: var(--pink-primary);">Kost Impian</span> Anda
                </h1>
                <p class="lead text-muted mb-4">
                    Kost modern dengan fasilitas lengkap dan proses booking yang mudah. 
                    Dapatkan kenyamanan tinggal dengan harga terjangkau.
                </p>
                <div class="d-flex gap-3">
                    <?php if (!isLoggedIn()): ?>
                        <a href="register.php" class="btn btn-pink btn-lg px-4">
                            <i class="fas fa-user-plus me-2"></i>Daftar Sekarang
                        </a>
                        <a href="rooms.php" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="fas fa-search me-2"></i>Lihat Kamar
                        </a>
                    <?php else: ?>
                        <a href="rooms.php" class="btn btn-pink btn-lg px-4">
                            <i class="fas fa-bed me-2"></i>Booking Kamar
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-4">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="/placeholder.svg?height=400&width=500" alt="Kost Modern" class="img-fluid rounded-3 shadow-lg">
            </div>
        </div>
    </div>
</div>

<!-- Stats Section -->
<div class="container py-5">
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card stats-card h-100 text-center p-4">
                <div class="card-body">
                    <i class="fas fa-bed fa-3x mb-3" style="color: var(--pink-primary);"></i>
                    <h3 class="fw-bold"><?php echo $available_rooms; ?></h3>
                    <p class="text-muted mb-0">Kamar Tersedia</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card h-100 text-center p-4">
                <div class="card-body">
                    <i class="fas fa-wifi fa-3x mb-3" style="color: var(--pink-primary);"></i>
                    <h3 class="fw-bold">24/7</h3>
                    <p class="text-muted mb-0">WiFi Gratis</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card h-100 text-center p-4">
                <div class="card-body">
                    <i class="fas fa-shield-alt fa-3x mb-3" style="color: var(--pink-primary);"></i>
                    <h3 class="fw-bold">100%</h3>
                    <p class="text-muted mb-0">Keamanan</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card h-100 text-center p-4">
                <div class="card-body">
                    <i class="fas fa-headset fa-3x mb-3" style="color: var(--pink-primary);"></i>
                    <h3 class="fw-bold">24/7</h3>
                    <p class="text-muted mb-0">Support</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Featured Rooms -->
<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold">Kamar <span style="color: var(--pink-primary);">Unggulan</span></h2>
        <p class="text-muted">Pilihan kamar terbaik dengan fasilitas lengkap</p>
    </div>
    
    <div class="row g-4">
        <?php foreach ($featured_rooms as $room): ?>
            <div class="col-lg-4">
                <div class="card h-100">
                    <img src="<?php echo $room['image_url'] ?: '/placeholder.svg?height=250&width=400'; ?>" 
                         class="card-img-top" alt="<?php echo $room['room_number']; ?>" style="height: 250px; object-fit: cover;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title">Kamar <?php echo $room['room_number']; ?></h5>
                            <span class="badge badge-pink"><?php echo ucfirst($room['room_type']); ?></span>
                        </div>
                        <p class="card-text text-muted"><?php echo $room['description']; ?></p>
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-check-circle me-1" style="color: var(--pink-primary);"></i>
                                <?php echo $room['facilities']; ?>
                            </small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="text-primary mb-0">Rp <?php echo number_format($room['price'], 0, ',', '.'); ?></h4>
                                <small class="text-muted">per bulan</small>
                            </div>
                            <?php if (isLoggedIn() && !isAdmin()): ?>
                                <a href="booking.php?room_id=<?php echo $room['id']; ?>" class="btn btn-pink">
                                    <i class="fas fa-calendar-plus me-1"></i>Book
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-sign-in-alt me-1"></i>Login
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="text-center mt-5">
        <a href="rooms.php" class="btn btn-pink btn-lg">
            <i class="fas fa-eye me-2"></i>Lihat Semua Kamar
        </a>
    </div>
</div>

<!-- Features Section -->
<div class="container-fluid py-5" style="background: var(--pink-light);">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Mengapa Memilih <span style="color: var(--pink-primary);">KostQ?</span></h2>
            <p class="text-muted">Keunggulan yang membuat kami berbeda</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4 text-center">
                <div class="p-4">
                    <i class="fas fa-mobile-alt fa-4x mb-3" style="color: var(--pink-primary);"></i>
                    <h4>Booking Online</h4>
                    <p class="text-muted">Proses booking mudah dan cepat melalui sistem online 24/7</p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="p-4">
                    <i class="fas fa-credit-card fa-4x mb-3" style="color: var(--pink-primary);"></i>
                    <h4>Pembayaran Fleksibel</h4>
                    <p class="text-muted">Berbagai metode pembayaran untuk kemudahan Anda</p>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="p-4">
                    <i class="fas fa-tools fa-4x mb-3" style="color: var(--pink-primary);"></i>
                    <h4>Maintenance Cepat</h4>
                    <p class="text-muted">Tim maintenance siap membantu kapan saja dibutuhkan</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
