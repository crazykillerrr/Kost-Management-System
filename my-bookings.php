<?php
$page_title = 'Booking Saya - KostKu';
require_once 'config/database.php';
require_once 'config/session.php';

// Require login
requireLogin();

// Admin cannot access this page
if (isAdmin()) {
    header('Location: admin/bookings.php');
    exit();
}

$user_id = getUserId();
$database = new Database();
$db = $database->getConnection();

// Get user's bookings
try {
    $query = "SELECT b.*, r.room_number, r.room_type, r.price, r.image_url, 
              p.id as payment_id, p.status as payment_status, p.receipt_url
              FROM bookings b
              JOIN rooms r ON b.room_id = r.id
              LEFT JOIN payments p ON b.id = p.booking_id
              WHERE b.user_id = ?
              ORDER BY b.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Process payment upload
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_payment'])) {
    $booking_id = $_POST['booking_id'];
    $payment_id = $_POST['payment_id'];
    
    // Check if file was uploaded
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $file_name = $_FILES['receipt']['name'];
        $file_tmp = $_FILES['receipt']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file extension
        $allowed_ext = array('jpg', 'jpeg', 'png', 'pdf');
        if (in_array($file_ext, $allowed_ext)) {
            // Create unique filename
            $new_file_name = 'receipt_' . $booking_id . '_' . time() . '.' . $file_ext;
            $upload_path = 'uploads/receipts/' . $new_file_name;
            
            // Create directory if not exists
            if (!file_exists('uploads/receipts/')) {
                mkdir('uploads/receipts/', 0777, true);
            }
            
            // Upload file
            if (move_uploaded_file($file_tmp, $upload_path)) {
                try {
                    // Update payment record
                    $update_query = "UPDATE payments SET receipt_url = ?, status = 'pending' WHERE id = ?";
                    $update_stmt = $db->prepare($update_query);
                    $update_stmt->execute([$upload_path, $payment_id]);
                    
                    $success = "Bukti pembayaran berhasil diupload. Admin akan memverifikasi pembayaran Anda.";
                    
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                                 VALUES (?, 'UPLOAD_RECEIPT', ?, ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        $user_id, 
                        "Uploaded payment receipt for booking ID: {$booking_id}", 
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    // Refresh bookings data
                    $stmt = $db->prepare($query);
                    $stmt->execute([$user_id]);
                    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                } catch (PDOException $e) {
                    $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
                }
            } else {
                $error = "Gagal mengupload file. Silakan coba lagi.";
            }
        } else {
            $error = "Format file tidak didukung. Gunakan JPG, PNG, atau PDF.";
        }
    } else {
        $error = "Silakan pilih file bukti pembayaran.";
    }
}

// Cancel booking
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $booking_id = $_GET['id'];
    
    try {
        // Check if booking belongs to user and is in pending status
        $check_query = "SELECT id FROM bookings WHERE id = ? AND user_id = ? AND status = 'pending'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$booking_id, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            // Update booking status
            $update_query = "UPDATE bookings SET status = 'rejected' WHERE id = ?";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([$booking_id]);
            
            // Log activity
            $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                         VALUES (?, 'CANCEL_BOOKING', ?, ?)";
            $log_stmt = $db->prepare($log_query);
            $log_stmt->execute([
                $user_id, 
                "Cancelled booking ID: {$booking_id}", 
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $success = "Booking berhasil dibatalkan.";
            
            // Refresh bookings data
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            $error = "Booking tidak ditemukan atau tidak dapat dibatalkan.";
        }
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <h2 class="mb-4">Booking <span style="color: var(--pink-primary);">Saya</span></h2>
    
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
    
    <?php if (empty($bookings)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Anda belum memiliki booking.
            <div class="mt-3">
                <a href="rooms.php" class="btn btn-pink">
                    <i class="fas fa-search me-2"></i>Cari Kamar
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-3 mb-4">
                <!-- Booking Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Statistik Booking</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $pending_count = 0;
                        $active_count = 0;
                        $completed_count = 0;
                        
                        foreach ($bookings as $booking) {
                            if ($booking['status'] === 'pending') $pending_count++;
                            if ($booking['status'] === 'active') $active_count++;
                            if ($booking['status'] === 'completed') $completed_count++;
                        }
                        ?>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Pending</span>
                                <span class="badge bg-warning"><?php echo $pending_count; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo ($pending_count / count($bookings)) * 100; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Aktif</span>
                                <span class="badge bg-success"><?php echo $active_count; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?php echo ($active_count / count($bookings)) * 100; ?>%"></div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span>Selesai</span>
                                <span class="badge bg-info"><?php echo $completed_count; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" style="width: <?php echo ($completed_count / count($bookings)) * 100; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Filter</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check">
                                <input class="form-check-input filter-status" type="checkbox" value="all" id="filter-all" checked>
                                <label class="form-check-label" for="filter-all">
                                    Semua
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input filter-status" type="checkbox" value="pending" id="filter-pending">
                                <label class="form-check-label" for="filter-pending">
                                    Pending
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input filter-status" type="checkbox" value="approved" id="filter-approved">
                                <label class="form-check-label" for="filter-approved">
                                    Approved
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input filter-status" type="checkbox" value="active" id="filter-active">
                                <label class="form-check-label" for="filter-active">
                                    Aktif
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input filter-status" type="checkbox" value="completed" id="filter-completed">
                                <label class="form-check-label" for="filter-completed">
                                    Selesai
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input filter-status" type="checkbox" value="rejected" id="filter-rejected">
                                <label class="form-check-label" for="filter-rejected">
                                    Dibatalkan
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <!-- Bookings List -->
                <div class="row g-4" id="bookings-container">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="col-12 booking-item" data-status="<?php echo $booking['status']; ?>">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3 mb-md-0">
                                            <img src="<?php echo $booking['image_url'] ?: '/placeholder.svg?height=150&width=250'; ?>" 
                                                 class="img-fluid rounded" alt="<?php echo $booking['room_number']; ?>" 
                                                 style="height: 150px; width: 100%; object-fit: cover;">
                                        </div>
                                        
                                        <div class="col-md-8">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5>Kamar <?php echo $booking['room_number']; ?></h5>
                                                <span class="badge <?php 
                                                    echo $booking['status'] === 'pending' ? 'bg-warning' : 
                                                         ($booking['status'] === 'approved' ? 'bg-primary' : 
                                                         ($booking['status'] === 'active' ? 'bg-success' : 
                                                         ($booking['status'] === 'completed' ? 'bg-info' : 'bg-danger')));
                                                ?>">
                                                    <?php 
                                                    echo $booking['status'] === 'pending' ? 'Pending' : 
                                                         ($booking['status'] === 'approved' ? 'Approved' : 
                                                         ($booking['status'] === 'active' ? 'Aktif' : 
                                                         ($booking['status'] === 'completed' ? 'Selesai' : 'Dibatalkan')));
                                                    ?>
                                                </span>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-sm-6">
                                                    <p class="mb-1"><strong>Tipe:</strong> <?php echo ucfirst($booking['room_type']); ?></p>
                                                    <p class="mb-1"><strong>Tanggal Booking:</strong> <?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></p>
                                                    <p class="mb-1"><strong>Durasi:</strong> <?php echo $booking['duration_months']; ?> bulan</p>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-1"><strong>Mulai:</strong> <?php echo date('d/m/Y', strtotime($booking['start_date'])); ?></p>
                                                    <p class="mb-1"><strong>Selesai:</strong> <?php echo date('d/m/Y', strtotime($booking['end_date'])); ?></p>
                                                    <p class="mb-1"><strong>Total:</strong> Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex flex-wrap justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge <?php 
                                                        echo $booking['payment_status'] === 'pending' ? 'bg-warning' : 
                                                             ($booking['payment_status'] === 'completed' ? 'bg-success' : 'bg-danger');
                                                    ?> me-2">
                                                        <i class="fas fa-money-bill-wave me-1"></i>
                                                        <?php 
                                                        echo $booking['payment_status'] === 'pending' ? 'Pembayaran Pending' : 
                                                             ($booking['payment_status'] === 'completed' ? 'Pembayaran Selesai' : 'Pembayaran Gagal');
                                                        ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="mt-2 mt-sm-0">
                                                    <a href="room-detail.php?id=<?php echo $booking['room_id']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                                        <i class="fas fa-info-circle"></i>
                                                    </a>
                                                    
                                                    <?php if ($booking['status'] === 'pending'): ?>
                                                        <a href="my-bookings.php?action=cancel&id=<?php echo $booking['id']; ?>" 
                                                           class="btn btn-sm btn-danger me-1 btn-delete">
                                                            <i class="fas fa-times"></i> Batalkan
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (($booking['status'] === 'approved' || $booking['status'] === 'pending') && 
                                                              $booking['payment_status'] === 'pending' && empty($booking['receipt_url'])): ?>
                                                        <button type="button" class="btn btn-sm btn-pink" 
                                                                data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-upload"></i> Upload Bukti
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Modal -->
                            <?php if (($booking['status'] === 'approved' || $booking['status'] === 'pending') && 
                                      $booking['payment_status'] === 'pending'): ?>
                                <div class="modal fade" id="paymentModal<?php echo $booking['id']; ?>" tabindex="-1" 
                                     aria-labelledby="paymentModalLabel<?php echo $booking['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="paymentModalLabel<?php echo $booking['id']; ?>">
                                                    Upload Bukti Pembayaran
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                    <input type="hidden" name="payment_id" value="<?php echo $booking['payment_id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="receipt<?php echo $booking['id']; ?>" class="form-label">
                                                            Bukti Pembayaran
                                                        </label>
                                                        <input type="file" class="form-control" id="receipt<?php echo $booking['id']; ?>" 
                                                               name="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                                                        <div class="form-text">
                                                            Format yang didukung: JPG, PNG, PDF. Maksimal 2MB.
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Total Pembayaran</label>
                                                        <div class="form-control bg-light">
                                                            Rp <?php echo number_format($booking['total_amount'], 0, ',', '.'); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="alert alert-info">
                                                        <p class="mb-2"><strong>Informasi Transfer:</strong></p>
                                                        <p class="mb-1">Bank BCA: 1234567890 a/n KostKu</p>
                                                        <p class="mb-0">Pastikan nominal transfer sesuai dengan total pembayaran.</p>
                                                    </div>
                                                    
                                                    <div class="d-grid">
                                                        <button type="submit" name="upload_payment" class="btn btn-pink">
                                                            <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter bookings by status
    const filterAll = document.getElementById('filter-all');
    const filterStatus = document.querySelectorAll('.filter-status:not(#filter-all)');
    const bookingItems = document.querySelectorAll('.booking-item');
    
    // Filter function
    function filterBookings() {
        const selectedStatuses = [];
        
        if (filterAll.checked) {
            bookingItems.forEach(item => {
                item.style.display = 'block';
            });
            
            // Uncheck other filters
            filterStatus.forEach(filter => {
                filter.checked = false;
            });
            
            return;
        }
        
        // Get selected statuses
        filterStatus.forEach(filter => {
            if (filter.checked) {
                selectedStatuses.push(filter.value);
            }
        });
        
        // Show/hide bookings based on selected statuses
        bookingItems.forEach(item => {
            const status = item.dataset.status;
            
            if (selectedStatuses.length === 0 || selectedStatuses.includes(status)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Add event listeners
    filterAll.addEventListener('change', filterBookings);
    
    filterStatus.forEach(filter => {
        filter.addEventListener('change', function() {
            if (this.checked) {
                filterAll.checked = false;
            } else if (!document.querySelector('.filter-status:not(#filter-all):checked')) {
                filterAll.checked = true;
            }
            
            filterBookings();
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
