<?php
$page_title = 'Kelola Pengguna - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle user actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = $_GET['id'];
    $admin_id = getUserId();
    
    if ($action === 'toggle_status') {
        try {
            // Get current status
            $status_query = "SELECT status FROM users WHERE id = ?";
            $status_stmt = $db->prepare($status_query);
            $status_stmt->execute([$user_id]);
            $current_status = $status_stmt->fetch(PDO::FETCH_ASSOC)['status'];
            
            $new_status = ($current_status === 'active') ? 'inactive' : 'active';
            
            $update_query = "UPDATE users SET status = ? WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$new_status, $user_id]);
            
            $success = "Status pengguna berhasil diubah!";
            
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                         VALUES (?, 'UPDATE_USER_STATUS', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([
                $admin_id, 
                "Changed user {$user_id} status to {$new_status}", 
                $_SERVER['REMOTE_ADDR']
            ]);
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan saat mengubah status pengguna.";
        }
    }
}

// Get filter parameters
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$query = "SELECT u.*, 
          COUNT(b.id) as total_bookings,
          COUNT(CASE WHEN b.status = 'active' THEN 1 END) as active_bookings,
          SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END) as total_spent
          FROM users u
          LEFT JOIN bookings b ON u.id = b.user_id
          WHERE 1=1";
$params = [];

if ($role) {
    $query .= " AND u.role = ?";
    $params[] = $role;
}

if ($status) {
    $query .= " AND u.status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " GROUP BY u.id";

// Add sorting
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY u.created_at ASC";
        break;
    case 'name_asc':
        $query .= " ORDER BY u.full_name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY u.full_name DESC";
        break;
    case 'bookings_desc':
        $query .= " ORDER BY total_bookings DESC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY u.created_at DESC";
        break;
}

// Execute query
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user statistics
    $stats_query = "SELECT 
                   COUNT(*) as total_users,
                   COUNT(CASE WHEN role = 'penyewa' THEN 1 END) as tenant_users,
                   COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_users,
                   COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                   COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users
                   FROM users";
    $stats_stmt = $db->query($stats_query);
    $user_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Kelola <span style="color: var(--pink-primary);">Pengguna</span></h2>
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
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-users fa-2x mb-2" style="color: var(--pink-primary);"></i>
                    <h4 class="fw-bold"><?php echo $user_stats['total_users']; ?></h4>
                    <p class="text-muted mb-0">Total Pengguna</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-user fa-2x mb-2 text-primary"></i>
                    <h4 class="fw-bold"><?php echo $user_stats['tenant_users']; ?></h4>
                    <p class="text-muted mb-0">Penyewa</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-user-shield fa-2x mb-2 text-warning"></i>
                    <h4 class="fw-bold"><?php echo $user_stats['admin_users']; ?></h4>
                    <p class="text-muted mb-0">Admin</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center p-3">
                    <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                    <h4 class="fw-bold"><?php echo $user_stats['active_users']; ?></h4>
                    <p class="text-muted mb-0">Aktif</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">Semua Role</option>
                        <option value="penyewa" <?php echo $role === 'penyewa' ? 'selected' : ''; ?>>Penyewa</option>
                        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Tidak Aktif</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="search" class="form-label">Cari</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Nama, email, username...">
                </div>
                
                <div class="col-md-3">
                    <label for="sort" class="form-label">Urutkan</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Terlama</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Nama A-Z</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Nama Z-A</option>
                        <option value="bookings_desc" <?php echo $sort === 'bookings_desc' ? 'selected' : ''; ?>>Booking Terbanyak</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-pink me-2">
                        <i class="fas fa-search me-1"></i>Cari
                    </button>
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Daftar Pengguna (<?php echo count($users); ?> pengguna)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>Tidak ada pengguna ditemukan</h5>
                    <p class="text-muted">Silakan ubah filter untuk melihat pengguna lainnya.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-pink">
                            <tr>
                                <th>Pengguna</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Statistik Booking</th>
                                <th>Total Spent</th>
                                <th>Bergabung</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                 style="width: 40px; height: 40px; background-color: var(--pink-light);">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo $user['full_name']; ?></strong>
                                                <br><small class="text-muted"><?php echo $user['email']; ?></small>
                                                <br><small class="text-muted">@<?php echo $user['username']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-warning' : 'bg-primary'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $user['status'] === 'active' ? 'Aktif' : 'Tidak Aktif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <strong><?php echo $user['total_bookings']; ?></strong> total
                                            <?php if ($user['active_bookings'] > 0): ?>
                                                <br><small class="text-success"><?php echo $user['active_bookings']; ?> aktif</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($user['total_spent'] > 0): ?>
                                            <strong>Rp <?php echo number_format($user['total_spent'], 0, ',', '.'); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                        <br><small class="text-muted"><?php echo date('H:i', strtotime($user['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($user['id'] != getUserId()): ?>
                                                <a href="users.php?action=toggle_status&id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-sm btn-outline-<?php echo $user['status'] === 'active' ? 'danger' : 'success'; ?>" 
                                                   title="<?php echo $user['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan'; ?>"
                                                   onclick="return confirm('<?php echo $user['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan'; ?> pengguna ini?')">
                                                    <i class="fas fa-<?php echo $user['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
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
