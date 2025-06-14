<?php
$page_title = 'Edit Kamar - Admin KostKu';
require_once '../config/database.php';
require_once '../config/session.php';

// Require admin login
requireAdmin();

// Check if room ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: rooms.php');
    exit();
}

$room_id = $_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Get room data
try {
    $query = "SELECT * FROM rooms WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$room_id]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        header('Location: rooms.php');
        exit();
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = trim($_POST['room_number']);
    $room_type = $_POST['room_type'];
    $price = $_POST['price'];
    $description = trim($_POST['description']);
    $facilities = trim($_POST['facilities']);
    $status = $_POST['status'];
    
    // Validation
    if (empty($room_number) || empty($room_type) || empty($price) || empty($description)) {
        $error = "Semua field wajib harus diisi!";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Harga harus berupa angka positif!";
    } else {
        try {
            // Check if room number already exists (except current room)
            $check_query = "SELECT id FROM rooms WHERE room_number = ? AND id != ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$room_number, $room_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = "Nomor kamar sudah digunakan!";
            } else {
                // Handle image upload
                $image_url = $room['image_url']; // Keep existing image by default
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['image']['name'];
                    $file_tmp = $_FILES['image']['tmp_name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    // Check file extension
                    $allowed_ext = array('jpg', 'jpeg', 'png');
                    if (in_array($file_ext, $allowed_ext)) {
                        // Create unique filename
                        $new_file_name = 'room_' . $room_number . '_' . time() . '.' . $file_ext;
                        $upload_path = '../uploads/rooms/' . $new_file_name;
                        
                        // Create directory if not exists
                        if (!file_exists('../uploads/rooms/')) {
                            mkdir('../uploads/rooms/', 0777, true);
                        }
                        
                        // Upload file
                        if (move_uploaded_file($file_tmp, $upload_path)) {
                            // Delete old image if exists
                            if ($room['image_url'] && file_exists('../' . $room['image_url'])) {
                                unlink('../' . $room['image_url']);
                            }
                            $image_url = 'uploads/rooms/' . $new_file_name;
                        }
                    }
                }
                
                // Update room
                $update_query = "UPDATE rooms SET room_number = ?, room_type = ?, price = ?, description = ?, facilities = ?, status = ?, image_url = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$room_number, $room_type, $price, $description, $facilities, $status, $image_url, $room_id])) {
                    $success = "Kamar berhasil diperbarui!";
                    
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                                 VALUES (?, 'UPDATE_ROOM', ?, ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([
                        getUserId(), 
                        "Updated room: {$room_number}", 
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    // Refresh room data
                    $stmt = $db->prepare($query);
                    $stmt->execute([$room_id]);
                    $room = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Gagal memperbarui kamar. Silakan coba lagi.";
                }
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<div class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" style="color: var(--pink-primary);">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="rooms.php" style="color: var(--pink-primary);">Kelola Kamar</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Kamar <?php echo $room['room_number']; ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Kamar <?php echo $room['room_number']; ?></h3>
                </div>
                <div class="card-body">
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
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="room_number" class="form-label">Nomor Kamar *</label>
                                <input type="text" class="form-control" id="room_number" name="room_number" 
                                       value="<?php echo htmlspecialchars($room['room_number']); ?>" 
                                       placeholder="Contoh: A01, B02" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="room_type" class="form-label">Tipe Kamar *</label>
                                <select class="form-select" id="room_type" name="room_type" required>
                                    <option value="">Pilih Tipe Kamar</option>
                                    <option value="single" <?php echo $room['room_type'] === 'single' ? 'selected' : ''; ?>>Single</option>
                                    <option value="double" <?php echo $room['room_type'] === 'double' ? 'selected' : ''; ?>>Double</option>
                                    <option value="suite" <?php echo $room['room_type'] === 'suite' ? 'selected' : ''; ?>>Suite</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Harga per Bulan *</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo $room['price']; ?>" 
                                           min="0" step="1000" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="available" <?php echo $room['status'] === 'available' ? 'selected' : ''; ?>>Tersedia</option>
                                    <option value="occupied" <?php echo $room['status'] === 'occupied' ? 'selected' : ''; ?>>Terisi</option>
                                    <option value="maintenance" <?php echo $room['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Deskripsi *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Deskripsi kamar yang menarik..." required><?php echo htmlspecialchars($room['description']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="facilities" class="form-label">Fasilitas</label>
                            <textarea class="form-control" id="facilities" name="facilities" rows="2" 
                                      placeholder="AC, WiFi, Lemari, Meja Belajar, dll (pisahkan dengan koma)"><?php echo htmlspecialchars($room['facilities']); ?></textarea>
                            <div class="form-text">Pisahkan setiap fasilitas dengan koma (,)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Foto Kamar</label>
                            <?php if ($room['image_url']): ?>
                                <div class="mb-2">
                                    <img src="../<?php echo $room['image_url']; ?>" class="img-thumbnail" 
                                         style="max-width: 200px; max-height: 150px;" alt="Current Image">
                                    <p class="small text-muted mt-1">Foto saat ini</p>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="image" name="image" accept=".jpg,.jpeg,.png">
                            <div class="form-text">Format yang didukung: JPG, PNG. Maksimal 2MB. Kosongkan jika tidak ingin mengubah foto.</div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="rooms.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                            <button type="submit" class="btn btn-pink">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Format price input
document.getElementById('price').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    e.target.value = value;
});

// Preview new image
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Create or update preview
            let preview = document.getElementById('new-image-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.id = 'new-image-preview';
                preview.className = 'mt-2';
                document.getElementById('image').parentNode.appendChild(preview);
            }
            
            preview.innerHTML = `
                <img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                <p class="small text-muted mt-1">Preview foto baru</p>
            `;
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
