<?php
$page_title = 'Detail Kamar - KostKu';
require_once 'config/database.php';
require_once 'config/session.php';

// Check if room ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: rooms.php');
    exit();
}

$room_id = $_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Get room details
try {
    $query = "SELECT * FROM rooms WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        header('Location: rooms.php');
        exit();
    }
    
    // Get similar rooms
    $query = "SELECT * FROM rooms 
              WHERE room_type = ? AND id != ? AND status = 'available' 
              LIMIT 3";
    $stmt = $db->prepare($query);
    $stmt->execute([$room['room_type'], $room_id]);
    $similar_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" style="color: var(--pink-primary);">Beranda</a></li>
            <li class="breadcrumb-item"><a href="rooms.php" style="color: var(--pink-primary);">Kamar</a></li>
            <li class="breadcrumb-item active" aria-current="page">Kamar <?php echo $room['room_number']; ?></li>
        </ol>
    </nav>
    
    <div class="row g-5">
        <!-- Room Details -->
        <div class="col-lg-8">
            <div class="card">
                <div class="position-relative">
                    <img src="<?php echo $room['image_url'] ?: '/placeholder.svg?height=400&width=800'; ?>" 
                         class="card-img-top" alt="<?php echo $room['room_number']; ?>" 
                         style="height: 400px; object-fit: cover;">
                    
                    <div class="position-absolute top-0 end-0 m-3">
                        <span class="badge bg-<?php echo $room['status'] === 'available' ? 'success' : 'danger'; ?> p-2">
                            <?php echo $room['status'] === 'available' ? 'Tersedia' : 'Tidak Tersedia'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="card-title mb-0">Kamar <?php echo $room['room_number']; ?></h2>
                        <span class="badge" style="background-color: var(--pink-primary);">
                            <?php echo ucfirst($room['room_type']); ?>
                        </span>
                    </div>
                    
                    <h4 class="text-primary mb-3">
                        Rp <?php echo number_format($room['price'], 0, ',', '.'); ?> <small class="text-muted">/bulan</small>
                    </h4>
                    
                    <hr>
                    
                    <h5 class="mb-3">Deskripsi</h5>
                    <p class="text-muted"><?php echo $room['description']; ?></p>
                    
                    <h5 class="mb-3 mt-4">Fasilitas</h5>
                    <div class="row">
                        <?php 
                        $facilities = explode(',', $room['facilities']);
                        foreach ($facilities as $facility): 
                            $facility = trim($facility);
                        ?>
                            <div class="col-md-6 mb-2">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle me-2" style="color: var(--pink-primary);"></i>
                                    <span><?php echo $facility; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4">
                        <?php if (isLoggedIn() && !isAdmin() && $room['status'] === 'available'): ?>
                            <a href="booking.php?room_id=<?php echo $room['id']; ?>" class="btn btn-pink btn-lg">
                                <i class="fas fa-calendar-plus me-2"></i>Booking Sekarang
                            </a>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="login.php" class="btn btn-pink btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login untuk Booking
                            </a>
                        <?php elseif (isAdmin()): ?>
                            <a href="admin/edit-room.php?id=<?php echo $room['id']; ?>" class="btn btn-warning btn-lg">
                                <i class="fas fa-edit me-2"></i>Edit Kamar
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-lg" disabled>
                                <i class="fas fa-ban me-2"></i>Tidak Tersedia
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Room Features -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Detail Tambahan</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4 text-center">
                            <div class="p-3 rounded" style="background-color: var(--pink-light);">
                                <i class="fas fa-ruler-combined fa-2x mb-2" style="color: var(--pink-primary);"></i>
                                <h6>Ukuran Kamar</h6>
                                <p class="mb-0">
                                    <?php 
                                    echo $room['room_type'] === 'single' ? '3 x 4 m' : 
                                         ($room['room_type'] === 'double' ? '4 x 5 m' : '5 x 6 m'); 
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 rounded" style="background-color: var(--pink-light);">
                                <i class="fas fa-bolt fa-2x mb-2" style="color: var(--pink-primary);"></i>
                                <h6>Listrik</h6>
                                <p class="mb-0">Termasuk</p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 rounded" style="background-color: var(--pink-light);">
                                <i class="fas fa-wifi fa-2x mb-2" style="color: var(--pink-primary);"></i>
                                <h6>Internet</h6>
                                <p class="mb-0">WiFi Gratis</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Location -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Lokasi</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        <i class="fas fa-map-marker-alt me-2" style="color: var(--pink-primary);"></i>
                        Jl. Kost Nyaman No. 123, Kota Indah
                    </p>
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.0537222284476!2d106.82715931476949!3d-6.259291295467566!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e69f22d2a8d08b3%3A0x5410dc1e7a8c0a3!2sJakarta%2C%20Daerah%20Khusus%20Ibukota%20Jakarta!5e0!3m2!1sid!2sid!4v1623825278123!5m2!1sid!2sid" 
                                style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Booking Card -->
            <?php if ($room['status'] === 'available'): ?>
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <h5 class="mb-0">Booking Cepat</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h5 class="text-primary">
                                Rp <?php echo number_format($room['price'], 0, ',', '.'); ?> <small class="text-muted">/bulan</small>
                            </h5>
                        </div>
                        
                        <?php if (isLoggedIn() && !isAdmin()): ?>
                            <form action="booking.php" method="GET">
                                <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="duration" class="form-label">Durasi Sewa</label>
                                    <select class="form-select" id="duration" name="duration">
                                        <option value="1">1 Bulan</option>
                                        <option value="3">3 Bulan</option>
                                        <option value="6">6 Bulan</option>
                                        <option value="12">12 Bulan</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-pink btn-lg">
                                        <i class="fas fa-calendar-plus me-2"></i>Booking Sekarang
                                    </button>
                                </div>
                            </form>
                        <?php elseif (!isLoggedIn()): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Silakan <a href="login.php" style="color: var(--pink-primary);">login</a> untuk melakukan booking.
                            </div>
                            <div class="d-grid">
                                <a href="login.php" class="btn btn-pink btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Admin tidak dapat melakukan booking.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Similar Rooms -->
            <?php if (!empty($similar_rooms)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">Kamar Serupa</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($similar_rooms as $similar): ?>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $similar['image_url'] ?: '/placeholder.svg?height=80&width=80'; ?>" 
                                             class="rounded me-3" alt="<?php echo $similar['room_number']; ?>" 
                                             style="width: 80px; height: 80px; object-fit: cover;">
                                        <div>
                                            <h6 class="mb-1">Kamar <?php echo $similar['room_number']; ?></h6>
                                            <p class="text-primary mb-1">
                                                Rp <?php echo number_format($similar['price'], 0, ',', '.'); ?>
                                            </p>
                                            <a href="room-detail.php?id=<?php echo $similar['id']; ?>" 
                                               style="color: var(--pink-primary);">
                                                Lihat Detail
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Contact Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Butuh Bantuan?</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px; background-color: var(--pink-light);">
                                <i class="fas fa-phone" style="color: var(--pink-primary);"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="mb-1">Telepon</h6>
                            <p class="mb-0">+62 812-3456-7890</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px; background-color: var(--pink-light);">
                                <i class="fas fa-envelope" style="color: var(--pink-primary);"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="mb-1">Email</h6>
                            <p class="mb-0">info@kostku.com</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px; background-color: var(--pink-light);">
                                <i class="fas fa-comments" style="color: var(--pink-primary);"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="mb-1">WhatsApp</h6>
                            <p class="mb-0">+62 812-3456-7890</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
