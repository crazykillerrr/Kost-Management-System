<?php
$page_title = 'Laporan - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

$database = new Database();
$db = $database->getConnection();

// Handle backup request
if (isset($_POST['backup_database'])) {
    $backup_result = performDatabaseBackup();
}

// Handle table backup request
if (isset($_POST['backup_table'])) {
    $table_name = $_POST['table_name'] ?? '';
    if (!empty($table_name)) {
        $backup_result = performTableBackup($table_name);
    }
}

// Handle restore request
if (isset($_POST['restore_database']) && isset($_FILES['restore_file'])) {
    $restore_result = restoreDatabase($_FILES['restore_file']);
}

// Get date range from parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'monthly';

// Function to perform full database backup
function performDatabaseBackup() {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'kost_management';
    
    $backup_file = 'backup_full_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = '../backups/';
    
    // Create backup directory if it doesn't exist
    if (!file_exists($backup_path)) {
        mkdir($backup_path, 0755, true);
    }
    
    $full_path = $backup_path . $backup_file;
    
    // Use mysqldump command
    $command = "mysqldump --host=$host --user=$username --password=$password $database > $full_path";
    
    // Execute the command
    $output = null;
    $return_var = null;
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        // Update last backup time in session or file
        file_put_contents($backup_path . 'last_backup.txt', date('d/m/Y H:i:s'));
        
        return [
            'success' => true,
            'message' => 'Backup full database berhasil dibuat: ' . $backup_file,
            'file' => $backup_file,
            'size' => filesize($full_path)
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Gagal membuat backup database'
        ];
    }
}

// Function to perform table backup
function performTableBackup($table_name) {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'kost_management';
    
    $backup_file = 'backup_table_' . $table_name . '_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_path = '../backups/';
    
    // Create backup directory if it doesn't exist
    if (!file_exists($backup_path)) {
        mkdir($backup_path, 0755, true);
    }
    
    $full_path = $backup_path . $backup_file;
    
    // Use mysqldump command for specific table
    $command = "mysqldump --host=$host --user=$username --password=$password $database $table_name > $full_path";
    
    // Execute the command
    $output = null;
    $return_var = null;
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        return [
            'success' => true,
            'message' => 'Backup tabel ' . $table_name . ' berhasil dibuat: ' . $backup_file,
            'file' => $backup_file,
            'size' => filesize($full_path)
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Gagal membuat backup tabel ' . $table_name
        ];
    }
}

// Function to restore database
function restoreDatabase($file) {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'kost_management';
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [
            'success' => false,
            'message' => 'Error uploading file'
        ];
    }
    
    $upload_path = '../backups/restore_' . date('Y-m-d_H-i-s') . '.sql';
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Use mysql command to restore
        $command = "mysql --host=$host --user=$username --password=$password $database < $upload_path";
        
        $output = null;
        $return_var = null;
        exec($command, $output, $return_var);
        
        // Clean up uploaded file
        unlink($upload_path);
        
        if ($return_var === 0) {
            return [
                'success' => true,
                'message' => 'Database berhasil direstore'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Gagal restore database'
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => 'Gagal upload file'
        ];
    }
}

// Get last backup time
$last_backup = '';
$backup_file_path = '../backups/last_backup.txt';
if (file_exists($backup_file_path)) {
    $last_backup = file_get_contents($backup_file_path);
}

// Get available tables for backup
$tables = [];
try {
    $tables_query = "SHOW TABLES";
    $tables_stmt = $db->query($tables_query);
    while ($row = $tables_stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
} catch (PDOException $e) {
    // Handle error silently
}

try {
    // Revenue statistics
    $revenue_query = "SELECT 
                     SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as total_revenue,
                     SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_revenue,
                     COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_payments,
                     COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_payments
                     FROM payments p
                     WHERE DATE(p.payment_date) BETWEEN ? AND ?";
    $revenue_stmt = $db->prepare($revenue_query);
    $revenue_stmt->execute([$start_date, $end_date]);
    $revenue_stats = $revenue_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize revenue stats if null
    if (!$revenue_stats || $revenue_stats['total_revenue'] === null) {
        $revenue_stats = [
            'total_revenue' => 0,
            'pending_revenue' => 0,
            'completed_payments' => 0,
            'pending_payments' => 0
        ];
    }
    
    // Booking statistics
    $booking_query = "SELECT 
                     COUNT(*) as total_bookings,
                     COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
                     COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_bookings,
                     COUNT(CASE WHEN status = 'active' THEN 1 END) as active_bookings,
                     COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                     COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_bookings,
                     COALESCE(AVG(total_amount), 0) as avg_booking_value
                     FROM bookings
                     WHERE DATE(booking_date) BETWEEN ? AND ?";
    $booking_stmt = $db->prepare($booking_query);
    $booking_stmt->execute([$start_date, $end_date]);
    $booking_stats = $booking_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize booking stats if null
    if (!$booking_stats) {
        $booking_stats = [
            'total_bookings' => 0,
            'pending_bookings' => 0,
            'approved_bookings' => 0,
            'active_bookings' => 0,
            'completed_bookings' => 0,
            'rejected_bookings' => 0,
            'avg_booking_value' => 0
        ];
    }
    
    // Room type performance
    $room_type_query = "SELECT 
                       r.room_type,
                       COUNT(b.id) as total_bookings,
                       COALESCE(SUM(b.total_amount), 0) as total_revenue,
                       COALESCE(AVG(b.total_amount), 0) as avg_revenue,
                       COUNT(CASE WHEN b.status = 'active' THEN 1 END) as active_bookings
                       FROM rooms r
                       LEFT JOIN bookings b ON r.id = b.room_id 
                       AND DATE(b.booking_date) BETWEEN ? AND ?
                       GROUP BY r.room_type
                       ORDER BY total_revenue DESC";
    $room_type_stmt = $db->prepare($room_type_query);
    $room_type_stmt->execute([$start_date, $end_date]);
    $room_type_stats = $room_type_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top performing rooms
    $top_rooms_query = "SELECT 
                       r.room_number,
                       r.room_type,
                       r.price,
                       COUNT(b.id) as total_bookings,
                       COALESCE(SUM(b.total_amount), 0) as total_revenue,
                       COUNT(CASE WHEN b.status = 'active' THEN 1 END) as active_bookings
                       FROM rooms r
                       LEFT JOIN bookings b ON r.id = b.room_id 
                       AND DATE(b.booking_date) BETWEEN ? AND ?
                       GROUP BY r.id, r.room_number, r.room_type, r.price
                       ORDER BY total_revenue DESC
                       LIMIT 10";
    $top_rooms_stmt = $db->prepare($top_rooms_query);
    $top_rooms_stmt->execute([$start_date, $end_date]);
    $top_rooms = $top_rooms_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Customer statistics
    $customer_query = "SELECT 
                      COUNT(DISTINCT u.id) as total_customers,
                      COUNT(DISTINCT CASE WHEN DATE(b.booking_date) BETWEEN ? AND ? THEN u.id END) as active_customers,
                      COALESCE(AVG(customer_bookings.booking_count), 0) as avg_bookings_per_customer
                      FROM users u
                      LEFT JOIN bookings b ON u.id = b.user_id
                      LEFT JOIN (
                          SELECT user_id, COUNT(*) as booking_count
                          FROM bookings
                          WHERE DATE(booking_date) BETWEEN ? AND ?
                          GROUP BY user_id
                      ) customer_bookings ON u.id = customer_bookings.user_id
                      WHERE u.role = 'penyewa'";
    $customer_stmt = $db->prepare($customer_query);
    $customer_stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $customer_stats = $customer_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Initialize customer stats if null
    if (!$customer_stats) {
        $customer_stats = [
            'total_customers' => 0,
            'active_customers' => 0,
            'avg_bookings_per_customer' => 0
        ];
    }
    
    // Get occupancy rate - handle if function doesn't exist
    try {
        $occupancy_query = "SELECT GetOccupancyRate() as occupancy_rate";
        $occupancy_stmt = $db->query($occupancy_query);
        $occupancy = $occupancy_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If function doesn't exist, calculate manually
        $occupancy_manual_query = "SELECT 
                                  COALESCE((COUNT(CASE WHEN r.status = 'occupied' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 0) as occupancy_rate
                                  FROM rooms r";
        $occupancy_stmt = $db->query($occupancy_manual_query);
        $occupancy = $occupancy_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Initialize occupancy if null
    if (!$occupancy || $occupancy['occupancy_rate'] === null) {
        $occupancy = ['occupancy_rate' => 0];
    }

    // Recent bookings
    $recent_bookings_query = "SELECT 
                             b.id,
                             u.full_name,
                             r.room_number,
                             r.room_type,
                             b.booking_date,
                             b.start_date,
                             b.total_amount,
                             b.status
                             FROM bookings b
                             JOIN users u ON b.user_id = u.id
                             JOIN rooms r ON b.room_id = r.id
                             WHERE DATE(b.booking_date) BETWEEN ? AND ?
                             ORDER BY b.booking_date DESC
                             LIMIT 20";
    $recent_bookings_stmt = $db->prepare($recent_bookings_query);
    $recent_bookings_stmt->execute([$start_date, $end_date]);
    $recent_bookings = $recent_bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment summary
    $payment_summary_query = "SELECT 
                             p.id,
                             u.full_name,
                             r.room_number,
                             p.amount,
                             p.payment_date,
                             p.status,
                             p.payment_method
                             FROM payments p
                             JOIN bookings b ON p.booking_id = b.id
                             JOIN users u ON b.user_id = u.id
                             JOIN rooms r ON b.room_id = r.id
                             WHERE DATE(p.payment_date) BETWEEN ? AND ?
                             ORDER BY p.payment_date DESC
                             LIMIT 20";
    $payment_summary_stmt = $db->prepare($payment_summary_query);
    $payment_summary_stmt->execute([$start_date, $end_date]);
    $payment_summary = $payment_summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Log error and set default values
    error_log("Report Error: " . $e->getMessage());
    
    // Set default values to prevent errors
    $revenue_stats = ['total_revenue' => 0, 'pending_revenue' => 0, 'completed_payments' => 0, 'pending_payments' => 0];
    $booking_stats = ['total_bookings' => 0, 'pending_bookings' => 0, 'approved_bookings' => 0, 'active_bookings' => 0, 'completed_bookings' => 0, 'rejected_bookings' => 0, 'avg_booking_value' => 0];
    $room_type_stats = [];
    $top_rooms = [];
    $customer_stats = ['total_customers' => 0, 'active_customers' => 0, 'avg_bookings_per_customer' => 0];
    $occupancy = ['occupancy_rate' => 0];
    $recent_bookings = [];
    $payment_summary = [];
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <!-- Header Section -->
    <div class="row mb-4 no-print">
        <div class="col-md-6">
            <h2>Laporan <span style="color: var(--pink-primary);">Sistem</span></h2>
        </div>
        <div class="col-md-6 text-md-end">
            <button onclick="window.print()" class="btn btn-success me-2">
                <i class="fas fa-print me-2"></i>Cetak Laporan
            </button>
            <a href="dashboard.php" class="btn btn-pink">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Database Backup Section - Hidden from print -->
    <div class="card mb-4 no-print">
        <div class="card-header" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
            <h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup Database:</h5>
        </div>
        <div class="card-body" style="background-color: #f8f9fa;">
            <?php if (isset($backup_result)): ?>
                <div class="alert alert-<?php echo $backup_result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $backup_result['success'] ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $backup_result['message']; ?>
                    <?php if ($backup_result['success']): ?>
                        <br><small>Ukuran file: <?php echo number_format($backup_result['size'] / 1024, 2); ?> KB</small>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($restore_result)): ?>
                <div class="alert alert-<?php echo $restore_result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $restore_result['success'] ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $restore_result['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <p class="mb-3 text-muted">Backup seluruh data sistem ke file SQL:</p>
            
            <div class="row g-3">
                <!-- Full Database Backup -->
                <div class="col-md-4">
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membuat backup full database?')" class="h-100">
                        <div class="d-grid h-100">
                            <button type="submit" name="backup_database" class="btn btn-primary btn-lg h-100">
                                <i class="fas fa-database fa-2x mb-2"></i><br>
                                <strong>Backup Full Database</strong>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Table Backup -->
                <div class="col-md-4">
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membuat backup tabel yang dipilih?')" class="h-100">
                        <div class="d-grid h-100">
                            <div class="btn-group-vertical h-100">
                                <select name="table_name" class="form-select" required>
                                    <option value="">Pilih Tabel</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="backup_table" class="btn btn-outline-primary flex-grow-1">
                                    <i class="fas fa-table fa-2x mb-2"></i><br>
                                    <strong>Backup Tabel</strong>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Database Restore -->
                <div class="col-md-4">
                    <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('PERINGATAN: Restore akan mengganti semua data yang ada. Apakah Anda yakin?')" class="h-100">
                        <div class="d-grid h-100">
                            <div class="btn-group-vertical h-100">
                                <input type="file" name="restore_file" class="form-control" accept=".sql" required>
                                <button type="submit" name="restore_database" class="btn btn-warning flex-grow-1">
                                    <i class="fas fa-upload fa-2x mb-2"></i><br>
                                    <strong>Restore Database</strong>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($last_backup)): ?>
                <div class="mt-3 p-3 bg-light rounded">
                    <i class="fas fa-info-circle text-info me-2"></i>
                    <strong>Backup terakhir:</strong> <?php echo htmlspecialchars($last_backup); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Print Header -->
    <div class="print-only text-center mb-4">
        <h1 style="color: var(--pink-primary); margin-bottom: 10px;">LAPORAN SISTEM KOST MANAGEMENT</h1>
        <h3>Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></h3>
        <p>Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?></p>
        <hr style="border: 2px solid var(--pink-primary); margin: 20px 0;">
    </div>
    
    <!-- Filter Section -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Jenis Laporan</label>
                    <select class="form-select" id="report_type" name="report_type">
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                        <option value="quarterly" <?php echo $report_type === 'quarterly' ? 'selected' : ''; ?>>Kuartalan</option>
                        <option value="yearly" <?php echo $report_type === 'yearly' ? 'selected' : ''; ?>>Tahunan</option>
                        <option value="custom" <?php echo $report_type === 'custom' ? 'selected' : ''; ?>>Custom</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Tanggal Selesai</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-pink">
                        <i class="fas fa-chart-bar me-2"></i>Generate Laporan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card summary-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-money-bill-wave fa-3x mb-3"></i>
                    <h4>Rp <?php echo number_format($revenue_stats['total_revenue'], 0, ',', '.'); ?></h4>
                    <p class="mb-0">Total Revenue</p>
                    <small><?php echo $revenue_stats['completed_payments']; ?> transaksi</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card summary-card" style="background: linear-gradient(135deg, #007bff 0%, #6610f2 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-calendar-check fa-3x mb-3"></i>
                    <h4><?php echo $booking_stats['total_bookings']; ?></h4>
                    <p class="mb-0">Total Booking</p>
                    <small><?php echo $booking_stats['active_bookings']; ?> aktif</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card summary-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-users fa-3x mb-3"></i>
                    <h4><?php echo $customer_stats['active_customers']; ?></h4>
                    <p class="mb-0">Customer Aktif</p>
                    <small>dari <?php echo $customer_stats['total_customers']; ?> total</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card summary-card" style="background: linear-gradient(135deg, var(--pink-primary) 0%, var(--pink-dark) 100%); color: white;">
                <div class="card-body text-center p-4">
                    <i class="fas fa-chart-line fa-3x mb-3"></i>
                    <h4><?php echo number_format($occupancy['occupancy_rate'], 1); ?>%</h4>
                    <p class="mb-0">Tingkat Hunian</p>
                    <small>Occupancy Rate</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Room Type Performance -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-home me-2"></i>Performa Tipe Kamar</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-pink">
                                <tr>
                                    <th>Tipe</th>
                                    <th>Booking</th>
                                    <th>Revenue</th>
                                    <th>Rata-rata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($room_type_stats)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($room_type_stats as $type): ?>
                                        <tr>
                                            <td>
                                                <span class="badge" style="background-color: var(--pink-primary);">
                                                    <?php echo ucfirst(htmlspecialchars($type['room_type'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $type['total_bookings']; ?></td>
                                            <td>Rp <?php echo number_format($type['total_revenue'], 0, ',', '.'); ?></td>
                                            <td>Rp <?php echo number_format($type['avg_revenue'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Status Summary -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Status Booking</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="p-3 rounded status-card" style="background-color: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107;">
                                <h6 class="mb-1">Pending</h6>
                                <h4 class="mb-0 text-warning"><?php echo $booking_stats['pending_bookings']; ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded status-card" style="background-color: rgba(0, 123, 255, 0.1); border-left: 4px solid #007bff;">
                                <h6 class="mb-1">Approved</h6>
                                <h4 class="mb-0 text-primary"><?php echo $booking_stats['approved_bookings']; ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded status-card" style="background-color: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745;">
                                <h6 class="mb-1">Aktif</h6>
                                <h4 class="mb-0 text-success"><?php echo $booking_stats['active_bookings']; ?></h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded status-card" style="background-color: rgba(23, 162, 184, 0.1); border-left: 4px solid #17a2b8;">
                                <h6 class="mb-1">Selesai</h6>
                                <h4 class="mb-0 text-info"><?php echo $booking_stats['completed_bookings']; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Statistics -->
    <div class="row g-4 mt-4">
        <!-- Top Performing Rooms -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top 10 Kamar Terbaik</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-pink">
                                <tr>
                                    <th>Rank</th>
                                    <th>Kamar</th>
                                    <th>Tipe</th>
                                    <th>Booking</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_rooms)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($top_rooms as $index => $room): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $index + 1; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge" style="background-color: var(--pink-primary);">
                                                    <?php echo ucfirst(htmlspecialchars($room['room_type'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $room['total_bookings']; ?>
                                                <?php if ($room['active_bookings'] > 0): ?>
                                                    <br><small class="text-success"><?php echo $room['active_bookings']; ?> aktif</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong>Rp <?php echo number_format($room['total_revenue'], 0, ',', '.'); ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Bookings -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Booking Terbaru</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-pink">
                                <tr>
                                    <th>Customer</th>
                                    <th>Kamar</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_bookings)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Tidak ada data</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($booking['room_number']); ?></strong>
                                                <br><small class="text-muted"><?php echo ucfirst($booking['room_type']); ?></small>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'pending' => 'warning',
                                                    'approved' => 'primary',
                                                    'active' => 'success',
                                                    'completed' => 'info',
                                                    'rejected' => 'danger'
                                                ];
                                                $color = $status_colors[$booking['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td>Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Summary -->
    <div class="row g-4 mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Ringkasan Pembayaran</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-pink">
                                <tr>
                                    <th>Customer</th>
                                    <th>Kamar</th>
                                    <th>Jumlah</th>
                                    <th>Tanggal</th>
                                    <th>Metode</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payment_summary)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Tidak ada data pembayaran</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($payment_summary as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['room_number']); ?></td>
                                            <td><strong>Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></strong></td>
                                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($payment['payment_method'] ?? 'Transfer'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $payment_status_colors = [
                                                    'pending' => 'warning',
                                                    'completed' => 'success',
                                                    'failed' => 'danger'
                                                ];
                                                $color = $payment_status_colors[$payment['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Report -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Ringkasan Laporan Periode <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-chart-line me-2"></i>Performa Keuangan:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Total Revenue: <strong>Rp <?php echo number_format($revenue_stats['total_revenue'], 0, ',', '.'); ?></strong></li>
                                <li><i class="fas fa-clock text-warning me-2"></i>Pending Revenue: <strong>Rp <?php echo number_format($revenue_stats['pending_revenue'], 0, ',', '.'); ?></strong></li>
                                <li><i class="fas fa-calculator text-info me-2"></i>Rata-rata Nilai Booking: <strong>Rp <?php echo number_format($booking_stats['avg_booking_value'], 0, ',', '.'); ?></strong></li>
                                <li><i class="fas fa-percentage text-primary me-2"></i>Tingkat Hunian: <strong><?php echo number_format($occupancy['occupancy_rate'], 1); ?>%</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-cogs me-2"></i>Performa Operasional:</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-calendar-check text-success me-2"></i>Total Booking: <strong><?php echo $booking_stats['total_bookings']; ?></strong></li>
                                <li><i class="fas fa-users text-info me-2"></i>Customer Aktif: <strong><?php echo $customer_stats['active_customers']; ?></strong></li>
                                <li><i class="fas fa-star text-warning me-2"></i>Rata-rata Booking per Customer: <strong><?php echo number_format($customer_stats['avg_bookings_per_customer'], 1); ?></strong></li>
                                <li><i class="fas fa-thumbs-up text-primary me-2"></i>Tingkat Approval: <strong><?php echo $booking_stats['total_bookings'] > 0 ? number_format((($booking_stats['approved_bookings'] + $booking_stats['active_bookings']) / $booking_stats['total_bookings']) * 100, 1) : 0; ?>%</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Print Footer -->
    <div class="print-only mt-5 text-center">
        <hr style="border: 1px solid #ddd; margin: 30px 0;">
        <p><strong>Sistem Kost Management</strong></p>
        <p>Laporan ini digenerate secara otomatis oleh sistem</p>
    </div>
</div>

<style>
/* Print Styles */
@media print {
    /* Hide elements that shouldn't be printed */
    .no-print, .btn, .navbar, .breadcrumb, .alert {
        display: none !important;
    }
    
    /* Show print-only elements */
    .print-only {
        display: block !important;
    }
    
    /* General print styles */
    body {
        font-size: 12px !important;
        line-height: 1.4 !important;
        color: #000 !important;
        background: white !important;
    }
    
    .container {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    /* Card styles for print */
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        margin-bottom: 20px !important;
        page-break-inside: avoid;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #ddd !important;
        padding: 10px 15px !important;
    }
    
    .card-body {
        padding: 15px !important;
    }
    
    /* Summary cards for print */
    .summary-card {
        background: #f8f9fa !important;
        color: #000 !important;
        border: 1px solid #ddd !important;
    }
    
    .summary-card .card-body {
        text-align: center !important;
    }
    
    .summary-card h4 {
        color: #000 !important;
        font-weight: bold !important;
    }
    
    /* Status cards for print */
    .status-card {
        border: 1px solid #ddd !important;
        background: #f8f9fa !important;
    }
    
    /* Table styles for print */
    .table {
        font-size: 11px !important;
        margin-bottom: 0 !important;
    }
    
    .table th {
        background-color: #e9ecef !important;
        color: #000 !important;
        border: 1px solid #ddd !important;
        padding: 8px !important;
    }
    
    .table td {
        border: 1px solid #ddd !important;
        padding: 6px !important;
    }
    
    /* Badge styles for print */
    .badge {
        background-color: #6c757d !important;
        color: white !important;
        border: 1px solid #000 !important;
    }
    
    /* Icon styles for print */
    .fas {
        font-weight: bold !important;
    }
    
    /* Page break controls */
    .row {
        page-break-inside: avoid;
    }
    
    /* Ensure proper spacing */
    h1, h2, h3, h4, h5, h6 {
        page-break-after: avoid;
        margin-top: 0 !important;
    }
    
    /* Remove gradients and colors for print */
    
}

/* Screen-only styles */
@media screen {
    .print-only {
        display: none !important;
    }
}

/* Additional responsive styles */
.table-responsive {
    overflow-x: auto;
}

@media (max-width: 768px) {
    .summary-card .card-body {
        padding: 20px !important;
    }
    
    .status-card {
        margin-bottom: 10px;
    }
}

/* Backup section specific styles */
.no-print .card-header {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
    color: white !important;
}

.no-print .card-body {
    background-color: #f8f9fa !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>
