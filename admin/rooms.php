<?php
$page_title = 'Kelola Kamar - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle room actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $room_id = $_GET['id'] ?? null;
    
    if ($action === 'delete' && $room_id) {
        try {
            // Check if room has active bookings
            $check_query = "SELECT COUNT(*) as active_bookings FROM bookings WHERE room_id = ? AND status IN ('active', 'approved')";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$room_id]);
            $active_bookings = $check_stmt->fetch(PDO::FETCH_ASSOC)['active_bookings'];
            
            if ($active_bookings > 0) {
                $error = "Tidak dapat menghapus kamar yang memiliki booking aktif!";
            } else {
                $delete_query = "DELETE FROM rooms WHERE id = ?";
                $delete_stmt = $db->prepare($delete_query);
                $delete_stmt->execute([$room_id]);
                
                $success = "Kamar berhasil dihapus!";
                
                // Log activity
                $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                             VALUES (?, 'DELETE_ROOM', ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    getUserId(), 
                    "Deleted room ID: {$room_id}", 
                    $_SERVER['REMOTE_ADDR']
                ]);
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan saat menghapus kamar.";
        }
    }
    
    if ($action === 'toggle_status' && $room_id) {
        try {
            $status_query = "SELECT status FROM rooms WHERE id = ?";
            $status_stmt = $db->prepare($status_query);
            $status_stmt->execute([$room_id]);
            $current_status = $status_stmt->fetch(PDO::FETCH_ASSOC)['status'];
            
            $new_status = ($current_status === 'available') ? 'maintenance' : 'available';
            
            $update_query = "UPDATE rooms SET status = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$new_status, $room_id]);
            
            $success = "Status kamar berhasil diubah!";
            
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                         VALUES (?, 'UPDATE_ROOM_STATUS', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([
                getUserId(), 
                "Changed room {$room_id} status to {$new_status}", 
                $_SERVER['REMOTE_ADDR']
            ]);
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan saat mengubah status kamar.";
        }
    }
}

// Get filter parameters
$room_type = $_GET['room_type'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'room_number_asc';

// Build query
$query = "SELECT r.*, 
          COUNT(b.id) as total_bookings,
          COUNT(CASE WHEN b.status = 'active' THEN 1 END) as active_bookings
          FROM rooms r
          LEFT JOIN bookings b ON r.id = b.room_id
          WHERE 1=1";
$params = [];

if ($room_type) {
    $query .= " AND r.room_type = ?";
    $params[] = $room_type;
}

if ($status) {
    $query .= " AND r.status = ?";
    $params[] = $status;
}

$query .= " GROUP BY r.id";

// Add sorting
switch ($sort) {
    case 'room_number_desc':
        $query .= " ORDER BY r.room_number DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY r.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY r.price DESC";
        break;
    case 'newest':
        $query .= " ORDER BY r.created_at DESC";
        break;
    case 'room_number_asc':
    default:
        $query .= " ORDER BY r.room_number ASC";
        break;
}

// Execute query
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get room statistics
    $stats_query = "SELECT 
                   COUNT(*) as total_rooms,
                   COUNT(CASE WHEN status = 'available' THEN 1 END) as available_rooms,
                   COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_rooms,
                   COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_rooms,
                   AVG(price) as avg_price
                   FROM rooms";
    $stats_stmt = $db->query($stats_query);
    $room_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Kelola <span style="color: var(--pink-primary);">Kamar</span></h2>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="add-room.php" class="btn btn-pink">
                <i class="fas fa-plus me-2"></i>Tambah Kamar
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
                    <i class="fas fa-bed fa-2x mb-2" style="color: var(--pink-primary);"></i>
                    <h4 class="fw-bold"><?php echo $room_stats['total_rooms']; ?></h4>
                    <p class="text-muted mb-0">Total Kamar</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                    <h4 class="fw-bold"><?php echo $room_stats['available_rooms']; ?></h4>
                    <p class="text-muted mb-0">Tersedia</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-user-check fa-2x mb-2 text-primary"></i>
                    <h4 class="fw-bold"><?php echo $room_stats['occupied_rooms']; ?></h4>
                    <p class="text-muted mb-0">Terisi</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-tools fa-2x mb-2 text-warning"></i>
                    <h4 class="fw-bold"><?php echo $room_stats['maintenance_rooms']; ?></h4>
                    <p class="text-muted mb-0">Maintenance</p>
                </div>
            </div>
        </div>
    </div>
    
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
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Tersedia</option>
                        <option value="occupied" <?php echo $status === 'occupied' ? 'selected' : ''; ?>>Terisi</option>
                        <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="sort" class="form-label">Urutkan</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="room_number_asc" <?php echo $sort === 'room_number_asc' ? 'selected' : ''; ?>>Nomor: A-Z</option>
                        <option value="room_number_desc" <?php echo $sort === 'room_number_desc' ? 'selected' : ''; ?>>Nomor: Z-A</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Harga: Rendah-Tinggi</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Harga: Tinggi-Rendah</option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-pink me-2">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="rooms.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Rooms Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Kamar (<?php echo count($rooms); ?> kamar)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($rooms)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                    <h5>Tidak ada kamar ditemukan</h5>
                    <p class="text-muted">Silakan ubah filter atau tambah kamar baru.</p>
                    <a href="add-room.php" class="btn btn-pink">
                        <i class="fas fa-plus me-2"></i>Tambah Kamar
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-pink">
                            <tr>
                                <th>Foto</th>
                                <th>Nomor Kamar</th>
                                <th>Tipe</th>
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Booking</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $room['image_url'] ?: '/placeholder.svg?height=60&width=80'; ?>" 
                                             class="rounded" alt="<?php echo $room['room_number']; ?>" 
                                             style="width: 80px; height: 60px; object-fit: cover;">
                                    </td>
                                    <td>
                                        <strong><?php echo $room['room_number']; ?></strong>
                                        <br><small class="text-muted">ID: <?php echo $room['id']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: var(--pink-primary);">
                                            <?php echo ucfirst($room['room_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>Rp <?php echo number_format($room['price'], 0, ',', '.'); ?></strong>
                                        <br><small class="text-muted">per bulan</small>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $room['status'] === 'available' ? 'bg-success' : 
                                                 ($room['status'] === 'occupied' ? 'bg-primary' : 'bg-warning');
                                        ?>">
                                            <?php 
                                            echo $room['status'] === 'available' ? 'Tersedia' : 
                                                 ($room['status'] === 'occupied' ? 'Terisi' : 'Maintenance');
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <strong><?php echo $room['total_bookings']; ?></strong> total
                                            <br><small class="text-muted"><?php echo $room['active_bookings']; ?> aktif</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="../room-detail.php?id=<?php echo $room['id']; ?>" 
                                               class="btn btn-sm btn-outline-secondary" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit-room.php?id=<?php echo $room['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($room['status'] !== 'occupied'): ?>
                                                <a href="rooms.php?action=toggle_status&id=<?php echo $room['id']; ?>" 
                                                   class="btn btn-sm btn-outline-warning" 
                                                   title="<?php echo $room['status'] === 'available' ? 'Set Maintenance' : 'Set Available'; ?>">
                                                    <i class="fas fa-<?php echo $room['status'] === 'available' ? 'tools' : 'check'; ?>"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($room['active_bookings'] == 0): ?>
                                                <a href="rooms.php?action=delete&id=<?php echo $room['id']; ?>" 
                                                   class="btn btn-sm btn-outline-danger btn-delete" title="Hapus">
                                                    <i class="fas fa-trash"></i>
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
