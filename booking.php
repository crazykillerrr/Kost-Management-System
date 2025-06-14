<?php
$page_title = 'Booking Kamar - KostKu';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Require login for booking
requireLogin();

// Admin cannot book rooms
if (isAdmin()) {
    header('Location: admin/dashboard.php');
    exit();
}

// Check if room ID is provided
if (!isset($_GET['room_id']) || !is_numeric($_GET['room_id'])) {
    header('Location: rooms.php');
    exit();
}

$room_id = $_GET['room_id'];
$user_id = getUserId();
$database = new Database();
$db = $database->getConnection();

// Get room details
try {
    $query = "SELECT * FROM rooms WHERE id = ? AND status = 'available'";
    $stmt = $db->prepare($query);
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        $_SESSION['error'] = "Kamar tidak tersedia untuk booking.";
        header('Location: rooms.php');
        exit();
    }
    
    // Get user details
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan sistem. Silakan coba lagi.";
    header('Location: rooms.php');
    exit();
}

// Process booking
$error = '';
$success = '';
$booking_created = false;
$booking_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'];
    $duration = (int)$_POST['duration'];
    $payment_method = $_POST['payment_method'];
    
    // Validation
    if (empty($start_date) || $duration <= 0) {
        $error = "Semua field harus diisi dengan benar.";
    } elseif (strtotime($start_date) < strtotime(date('Y-m-d'))) {
        $error = "Tanggal mulai tidak boleh kurang dari hari ini.";
    } else {
        try {
            $db->beginTransaction();
            
            // Calculate end date
            $end_date = date('Y-m-d', strtotime($start_date . ' + ' . $duration . ' months'));
            $total_amount = $room['price'] * $duration;
            
            // Create booking
            $booking_query = "INSERT INTO bookings (user_id, room_id, start_date, end_date, duration_months, total_amount, status, booking_date, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, 'pending', CURDATE(), NOW())";
            $booking_stmt = $db->prepare($booking_query);
            $booking_stmt->execute([$user_id, $room_id, $start_date, $end_date, $duration, $total_amount]);
            
            $booking_id = $db->lastInsertId();
            
            // Create payment record
            $payment_status = ($payment_method === 'cash') ? 'completed' : 'pending';
            $payment_query = "INSERT INTO payments (booking_id, amount, payment_date, payment_method, status) 
                             VALUES (?, ?, CURDATE(), ?, ?)";
            $payment_stmt = $db->prepare($payment_query);
            $payment_stmt->execute([$booking_id, $total_amount, $payment_method, $payment_status]);
            
            // Update room status
            $room_update = "UPDATE rooms SET status = 'booked' WHERE id = ?";
            $room_stmt = $db->prepare($room_update);
            $room_stmt->execute([$room_id]);
            
            $db->commit();
            
            if ($payment_method === 'cash') {
                $success = "Booking berhasil dibuat! Pembayaran tunai telah dikonfirmasi. Admin akan segera memproses booking Anda.";
            } else {
                $success = "Booking berhasil dibuat! Silakan upload bukti transfer untuk menyelesaikan pembayaran.";
                $booking_created = true;
            }
            
            // Log activity
            try {
                $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                             VALUES (?, 'CREATE_BOOKING', ?, ?)";
                $log_stmt = $db->prepare($log_query);
                $log_stmt->execute([
                    $user_id, 
                    "Created booking for room {$room['room_number']} with {$payment_method} payment", 
                    $_SERVER['REMOTE_ADDR']
                ]);
            } catch (Exception $e) {
                // Log error but don't fail the booking
            }
            
        } catch (PDOException $e) {
            $db->rollback();
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}

// Handle payment proof upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_proof']) && isset($_POST['booking_id'])) {
    $upload_booking_id = $_POST['booking_id'];
    
    // Verify booking belongs to user
    $verify_query = "SELECT id FROM bookings WHERE id = ? AND user_id = ?";
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$upload_booking_id, $user_id]);
    
    if ($verify_stmt->rowCount() > 0) {
        // Handle file upload
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['payment_proof']['name'];
            $file_tmp = $_FILES['payment_proof']['tmp_name'];
            $file_size = $_FILES['payment_proof']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file
            $allowed_ext = array('jpg', 'jpeg', 'png', 'pdf');
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file_ext, $allowed_ext)) {
                $error = "Format file tidak didukung. Gunakan JPG, PNG, atau PDF.";
            } elseif ($file_size > $max_size) {
                $error = "Ukuran file terlalu besar. Maksimal 2MB.";
            } else {
                // Create upload directory
                $upload_dir = 'uploads/payment_proofs/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_filename = 'payment_' . $upload_booking_id . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    try {
                        // Update payment record
                        $update_payment = "UPDATE payments SET receipt_url = ?, status = 'pending' WHERE booking_id = ?";
                        $update_stmt = $db->prepare($update_payment);
                        $update_stmt->execute([$upload_path, $upload_booking_id]);
                        
                        $success = "Bukti pembayaran berhasil diupload! Admin akan memverifikasi pembayaran Anda dalam 1x24 jam.";
                        
                        // Log activity
                        $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                                     VALUES (?, 'UPLOAD_PAYMENT_PROOF', ?, ?)";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([
                            $user_id, 
                            "Uploaded payment proof for booking ID: {$upload_booking_id}", 
                            $_SERVER['REMOTE_ADDR']
                        ]);
                        
                        // Redirect to my bookings after 3 seconds
                        header("refresh:3;url=my-bookings.php");
                        
                    } catch (PDOException $e) {
                        $error = "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.";
                    }
                } else {
                    $error = "Gagal mengupload file. Silakan coba lagi.";
                }
            }
        } else {
            $error = "Silakan pilih file bukti pembayaran.";
        }
    } else {
        $error = "Booking tidak ditemukan.";
    }
}

// Get default values from GET parameters if available
$default_duration = isset($_GET['duration']) ? (int)$_GET['duration'] : 1;
$default_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" style="color: var(--pink-primary);">Beranda</a></li>
            <li class="breadcrumb-item"><a href="rooms.php" style="color: var(--pink-primary);">Kamar</a></li>
            <li class="breadcrumb-item"><a href="room-detail.php?id=<?php echo $room['id']; ?>" style="color: var(--pink-primary);">Kamar <?php echo $room['room_number']; ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Booking</li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="mb-0"><i class="fas fa-calendar-plus me-2"></i>Booking Kamar</h3>
                </div>
                <div class="card-body p-4">
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
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?php echo $room['image_url'] ?: '/placeholder.svg?height=100&width=100'; ?>" 
                                     class="rounded me-3" alt="<?php echo $room['room_number']; ?>" 
                                     style="width: 100px; height: 100px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-1">Kamar <?php echo $room['room_number']; ?></h5>
                                    <span class="badge" style="background-color: var(--pink-primary);">
                                        <?php echo ucfirst($room['room_type']); ?>
                                    </span>
                                    <p class="text-primary mb-0 mt-1">
                                        Rp <?php echo number_format($room['price'], 0, ',', '.'); ?> /bulan
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100" style="background-color: var(--pink-light);">
                                <div class="card-body">
                                    <h6 class="mb-2">Fasilitas:</h6>
                                    <p class="mb-0 small"><?php echo $room['facilities']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <?php if (!$booking_created): ?>
                    <!-- Booking Form -->
                    <form method="POST" id="bookingForm">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">Informasi Penyewa</h5>
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">No. Telepon</label>
                                    <input type="tel" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Detail Booking</h5>
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           min="<?php echo date('Y-m-d'); ?>" 
                                           value="<?php echo $default_start_date; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="duration" class="form-label">Durasi Sewa</label>
                                    <select class="form-select" id="duration" name="duration" required>
                                        <option value="1" <?php echo $default_duration === 1 ? 'selected' : ''; ?>>1 Bulan</option>
                                        <option value="3" <?php echo $default_duration === 3 ? 'selected' : ''; ?>>3 Bulan</option>
                                        <option value="6" <?php echo $default_duration === 6 ? 'selected' : ''; ?>>6 Bulan</option>
                                        <option value="12" <?php echo $default_duration === 12 ? 'selected' : ''; ?>>12 Bulan</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Tanggal Berakhir</label>
                                    <input type="date" class="form-control" id="end_date" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="mb-3">Ringkasan Biaya</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>Harga Kamar</td>
                                                <td class="text-end">Rp <?php echo number_format($room['price'], 0, ',', '.'); ?> /bulan</td>
                                            </tr>
                                            <tr>
                                                <td>Durasi</td>
                                                <td class="text-end"><span id="durationText">1</span> bulan</td>
                                            </tr>
                                            <tr class="table-light">
                                                <th>Total Pembayaran</th>
                                                <th class="text-end" id="totalPayment">
                                                    Rp <?php echo number_format($room['price'], 0, ',', '.'); ?>
                                                </th>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="mb-3">Metode Pembayaran</h5>
                                <div class="mb-3">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="payment_method" id="transfer" value="transfer" checked>
                                        <label class="form-check-label" for="transfer">
                                            <i class="fas fa-university me-2"></i>Transfer Bank
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="cash" value="cash">
                                        <label class="form-check-label" for="cash">
                                            <i class="fas fa-money-bill-wave me-2"></i>Pembayaran Tunai
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Transfer Info -->
                                <div id="transferInfo" class="alert alert-info">
                                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Informasi Transfer:</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card bg-white border-primary">
                                                <div class="card-body p-3">
                                                    <h6 class="text-primary mb-2"><i class="fas fa-university me-2"></i>Bank BCA</h6>
                                                    <p class="mb-1"><strong>No. Rekening:</strong> 1234567890</p>
                                                    <p class="mb-0"><strong>Atas Nama:</strong> KostKu</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card bg-white border-success">
                                                <div class="card-body p-3">
                                                    <h6 class="text-success mb-2"><i class="fas fa-university me-2"></i>Bank Mandiri</h6>
                                                    <p class="mb-1"><strong>No. Rekening:</strong> 0987654321</p>
                                                    <p class="mb-0"><strong>Atas Nama:</strong> KostKu</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <p class="mb-1"><i class="fas fa-exclamation-triangle text-warning me-2"></i><strong>Penting:</strong></p>
                                        <ul class="mb-0 small">
                                            <li>Transfer sesuai dengan total pembayaran yang tertera</li>
                                            <li>Setelah transfer, upload bukti pembayaran pada langkah selanjutnya</li>
                                            <li>Verifikasi pembayaran akan dilakukan dalam 1x24 jam</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Cash Info -->
                                <div id="cashInfo" class="alert alert-success d-none">
                                    <h6 class="mb-3"><i class="fas fa-money-bill-wave me-2"></i>Pembayaran Tunai:</h6>
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p class="mb-2"><i class="fas fa-map-marker-alt me-2"></i><strong>Alamat Kantor:</strong></p>
                                            <p class="mb-2">Jl. Kost Indah No. 123, Jakarta Selatan</p>
                                            <p class="mb-2"><i class="fas fa-clock me-2"></i><strong>Jam Operasional:</strong></p>
                                            <p class="mb-0">Senin - Jumat: 08:00 - 17:00 WIB<br>Sabtu: 08:00 - 12:00 WIB</p>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <i class="fas fa-check-circle text-success fa-3x mb-2"></i>
                                                <p class="small text-success mb-0">Pembayaran tunai akan langsung dikonfirmasi</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" required>
                            <label class="form-check-label" for="terms">
                                Saya menyetujui <a href="#" style="color: var(--pink-primary);">syarat dan ketentuan</a> yang berlaku
                            </label>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-pink btn-lg">
                                <i class="fas fa-calendar-check me-2"></i>Konfirmasi Booking
                            </button>
                        </div>
                    </form>
                    
                    <?php else: ?>
                    <!-- Payment Proof Upload Form -->
                    <div class="text-center mb-4">
                        <i class="fas fa-upload fa-3x text-primary mb-3"></i>
                        <h5>Upload Bukti Pembayaran</h5>
                        <p class="text-muted">Booking ID: #<?php echo $booking_id; ?></p>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-6 mx-auto">
                                <div class="mb-3">
                                    <label for="payment_proof" class="form-label">
                                        <i class="fas fa-file-image me-2"></i>Bukti Pembayaran
                                    </label>
                                    <input type="file" class="form-control" id="payment_proof" name="payment_proof" 
                                           accept=".jpg,.jpeg,.png,.pdf" required>
                                    <div class="form-text">
                                        Format yang didukung: JPG, PNG, PDF. Maksimal 2MB.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Total yang Harus Dibayar</label>
                                    <div class="form-control bg-light text-center fw-bold text-primary">
                                        Rp <?php echo number_format($room['price'] * $default_duration, 0, ',', '.'); ?>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Pastikan:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Nominal transfer sesuai dengan total pembayaran</li>
                                        <li>Bukti transfer jelas dan dapat dibaca</li>
                                        <li>Tanggal transfer tidak lebih dari 3 hari</li>
                                    </ul>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="upload_proof" class="btn btn-success btn-lg">
                                        <i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran
                                    </button>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <a href="my-bookings.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Lihat Booking Saya
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const durationSelect = document.getElementById('duration');
    const durationText = document.getElementById('durationText');
    const totalPayment = document.getElementById('totalPayment');
    const transferInfo = document.getElementById('transferInfo');
    const cashInfo = document.getElementById('cashInfo');
    const transferRadio = document.getElementById('transfer');
    const cashRadio = document.getElementById('cash');
    
    const roomPrice = <?php echo $room['price']; ?>;
    
    // Calculate end date and total payment
    function updateCalculations() {
        if (!startDateInput || !durationSelect) return;
        
        const startDate = new Date(startDateInput.value);
        const duration = parseInt(durationSelect.value);
        
        // Update duration text
        if (durationText) durationText.textContent = duration;
        
        // Calculate end date
        const endDate = new Date(startDate);
        endDate.setMonth(endDate.getMonth() + duration);
        
        // Format end date for input
        const year = endDate.getFullYear();
        const month = String(endDate.getMonth() + 1).padStart(2, '0');
        const day = String(endDate.getDate()).padStart(2, '0');
        if (endDateInput) endDateInput.value = `${year}-${month}-${day}`;
        
        // Calculate total payment
        const total = roomPrice * duration;
        if (totalPayment) totalPayment.textContent = `Rp ${total.toLocaleString('id-ID')}`;
    }
    
    // Toggle payment info
    if (transferRadio) {
        transferRadio.addEventListener('change', function() {
            if (this.checked) {
                transferInfo.classList.remove('d-none');
                cashInfo.classList.add('d-none');
            }
        });
    }
    
    if (cashRadio) {
        cashRadio.addEventListener('change', function() {
            if (this.checked) {
                transferInfo.classList.add('d-none');
                cashInfo.classList.remove('d-none');
            }
        });
    }
    
    // Update calculations when inputs change
    if (startDateInput) startDateInput.addEventListener('change', updateCalculations);
    if (durationSelect) durationSelect.addEventListener('change', updateCalculations);
    
    // Initial calculation
    updateCalculations();
    
    // File upload preview
    const fileInput = document.getElementById('payment_proof');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const fileSize = file.size / 1024 / 1024; // MB
                if (fileSize > 2) {
                    alert('Ukuran file terlalu besar. Maksimal 2MB.');
                    this.value = '';
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
