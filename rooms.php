<?php
$page_title = 'Daftar Kamar - KostKu';
require_once 'config/database.php';
require_once 'config/session.php';

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$room_type = $_GET['room_type'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'price_asc';

// Build query
$query = "SELECT * FROM rooms WHERE 1=1";
$params = [];

if ($room_type) {
    $query .= " AND room_type = ?";
    $params[] = $room_type;
}

if ($min_price) {
    $query .= " AND price >= ?";
    $params[] = $min_price;
}

if ($max_price) {
    $query .= " AND price <= ?";
    $params[] = $max_price;
}

// Only show available rooms for regular users
if (!isAdmin()) {
    $query .= " AND status = 'available'";
}

// Add sorting
switch ($sort) {
    case 'price_desc':
        $query .= " ORDER BY price DESC";
        break;
    case 'newest':
        $query .= " ORDER BY created_at DESC";
        break;
    case 'price_asc':
    default:
        $query .= " ORDER BY price ASC";
        break;
}

// Execute query
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get min and max prices for filter
    $price_query = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM rooms";
    $price_stmt = $db->query($price_query);
    $price_range = $price_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <h2 class="mb-4 text-center">Daftar <span style="color: var(--pink-primary);">Kamar</span></h2>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="room_type" class="form-label">Tipe Kamar</label>
                    <select class="form-select" id="room_type" name="room_type">
                        <option value="">Semua Tipe</option>
                        <option value="single" <?php echo $room_type === 'single' ? 'selected' : ''; ?>>Single</option>
                        <option value="double" <?php echo $room_type === 'double' ? 'selected' : ''; ?>>Double</option>
                        <option value="suite" <?php echo $room_type === 'suite' ? 'selected' : ''; ?>>Suite</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="min_price" class="form-label">Harga Minimum</label>
                    <input type="number" class="form-control" id="min_price" name="min_price" 
                           value="<?php echo $min_price; ?>" min="<?php echo $price_range['min_price']; ?>" 
                           max="<?php echo $price_range['max_price']; ?>" step="100000">
                </div>
                
                <div class="col-md-3">
                    <label for="max_price" class="form-label">Harga Maksimum</label>
                    <input type="number" class="form-control" id="max_price" name="max_price" 
                           value="<?php echo $max_price; ?>" min="<?php echo $price_range['min_price']; ?>" 
                           max="<?php echo $price_range['max_price']; ?>" step="100000">
                </div>
                
                <div class="col-md-3">
                    <label for="sort" class="form-label">Urutkan</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Harga: Rendah ke Tinggi</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Harga: Tinggi ke Rendah</option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                    </select>
                </div>
                
                <div class="col-12 d-flex justify-content-between">
                    <button type="submit" class="btn btn-pink">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                    <a href="rooms.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Rooms List -->
    <div class="row g-4">
        <?php if (empty($rooms)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Tidak ada kamar yang sesuai dengan filter Anda.
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rooms as $room): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="position-relative">
                            <img src="<?php echo $room['image_url'] ?: '/placeholder.svg?height=200&width=400'; ?>" 
                                 class="card-img-top" alt="<?php echo $room['room_number']; ?>" 
                                 style="height: 200px; object-fit: cover;">
                            
                            <div class="position-absolute top-0 end-0 m-3">
                                <span class="badge bg-<?php echo $room['status'] === 'available' ? 'success' : 'danger'; ?> p-2">
                                    <?php echo $room['status'] === 'available' ? 'Tersedia' : 'Tidak Tersedia'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title">Kamar <?php echo $room['room_number']; ?></h5>
                                <span class="badge" style="background-color: var(--pink-primary);">
                                    <?php echo ucfirst($room['room_type']); ?>
                                </span>
                            </div>
                            
                            <p class="card-text text-muted mb-3"><?php echo substr($room['description'], 0, 100); ?>...</p>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-check-circle me-1" style="color: var(--pink-primary);"></i>
                                    <?php 
                                    $facilities = explode(',', $room['facilities']);
                                    echo implode(', ', array_slice($facilities, 0, 3));
                                    if (count($facilities) > 3) echo '...';
                                    ?>
                                </small>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="text-primary mb-0">Rp <?php echo number_format($room['price'], 0, ',', '.'); ?></h4>
                                    <small class="text-muted">per bulan</small>
                                </div>
                                
                                <a href="room-detail.php?id=<?php echo $room['id']; ?>" class="btn btn-outline-pink">
                                    <i class="fas fa-info-circle me-1"></i>Detail
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
