<?php
$page_title = 'Profil Saya - KostKu';
require_once 'config/database.php';
require_once 'config/session.php';

// Require login
requireLogin();

$user_id = getUserId();
$database = new Database();
$db = $database->getConnection();

// Get user data
try {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: logout.php');
        exit();
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$error = '';
$success = '';

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Validation
    if (empty($full_name)) {
        $error = "Nama lengkap harus diisi!";
    } elseif (empty($email)) {
        $error = "Email harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } else {
        try {
            // Check if email already exists (except current user)
            $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$email, $user_id]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = "Email sudah digunakan oleh pengguna lain!";
            } else {
                // Update user data
                $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$full_name, $email, $phone, $user_id])) {
                    // Update session data
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    
                    $success = "Profil berhasil diperbarui!";
                    
                    // Refresh user data
                    $stmt = $db->prepare($query);
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                                 VALUES (?, 'UPDATE_PROFILE', 'User updated their profile', ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([$user_id, $_SERVER['REMOTE_ADDR']]);
                } else {
                    $error = "Gagal memperbarui profil. Silakan coba lagi.";
                }
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Semua field password harus diisi!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Konfirmasi password baru tidak cocok!";
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter!";
    } else {
        try {
            // Verify current password
            if (password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password = ? WHERE id = ?";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([$hashed_password, $user_id])) {
                    $success = "Password berhasil diubah!";
                    
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
                                 VALUES (?, 'CHANGE_PASSWORD', 'User changed their password', ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([$user_id, $_SERVER['REMOTE_ADDR']]);
                } else {
                    $error = "Gagal mengubah password. Silakan coba lagi.";
                }
            } else {
                $error = "Password saat ini tidak valid!";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <h2 class="mb-4">Profil <span style="color: var(--pink-primary);">Saya</span></h2>
    
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
    
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto" 
                             style="width: 120px; height: 120px; background-color: var(--pink-light); font-size: 3rem;">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                    </div>
                    
                    <h4 class="mb-1"><?php echo $user['full_name']; ?></h4>
                    <p class="text-muted mb-3"><?php echo $user['email']; ?></p>
                    
                    <ul class="list-group list-group-flush text-start">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user me-2"></i>Username</span>
                            <span class="text-muted"><?php echo $user['username']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-phone me-2"></i>Telepon</span>
                            <span class="text-muted"><?php echo $user['phone'] ?: '-'; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-user-tag me-2"></i>Role</span>
                            <span class="badge" style="background-color: var(--pink-primary);">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-calendar-alt me-2"></i>Bergabung</span>
                            <span class="text-muted"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Menu Cepat</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt me-2" style="color: var(--pink-primary);"></i>Dashboard
                        </a>
                        <a href="my-bookings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-check me-2" style="color: var(--pink-primary);"></i>Booking Saya
                        </a>
                        <a href="rooms.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-bed me-2" style="color: var(--pink-primary);"></i>Kamar
                        </a>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Edit Profile Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profil</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" readonly>
                            <div class="form-text">Username tidak dapat diubah.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo $user['full_name']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $user['email']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">No. Telepon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo $user['phone']; ?>">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-pink">
                            <i class="fas fa-save me-2"></i>Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Ubah Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Password Saat Ini</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye" id="current_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password Baru</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye" id="new_password_icon"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimal 6 karakter.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-pink">
                            <i class="fas fa-key me-2"></i>Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(id) {
    const passwordField = document.getElementById(id);
    const toggleIcon = document.getElementById(id + '_icon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
