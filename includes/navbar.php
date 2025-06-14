<?php
// Determine if we're in admin directory or root
$is_admin_dir = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$base_path = $is_admin_dir ? '../' : '';

// Include session if not already included
if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../config/session.php';
}

?>

<nav class="navbar navbar-expand-lg navbar-light" style="background-color: var(--pink-light);">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo $base_path; ?>index.php" style="color: white;">
            <i class="fas fa-home me-2"></i>KostQ
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>index.php">
                        <i class="fas fa-home me-1"></i>Beranda
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <!-- Admin Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-cog me-1"></i>Admin
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/rooms.php">
                                    <i class="fas fa-bed me-2"></i>Kelola Kamar
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/approve-booking.php">
                                    <i class="fas fa-bed me-2"></i>Approve Booking
                                </a></li>
                                 <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/payment-verification.php">
                                    <i class="fas fa-bed me-2"></i>Verifikasi Pembayaran
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/bookings.php">
                                    <i class="fas fa-calendar-alt me-2"></i>Kelola Booking
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/payments.php">
                                    <i class="fas fa-credit-card me-2"></i>Kelola Pembayaran
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/users.php">
                                    <i class="fas fa-users me-2"></i>Kelola Pengguna
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo $base_path; ?>admin/reports.php">
                                    <i class="fas fa-chart-bar me-2"></i>Laporan
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- User Menu -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>rooms.php">
                                <i class="fas fa-bed me-1"></i>Kamar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $base_path; ?>my-bookings.php">
                                <i class="fas fa-calendar-check me-1"></i>Booking Saya
                            </a>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>rooms.php">
                            <i class="fas fa-bed me-1"></i>Kamar
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo getUserName(); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>profile.php">
                                <i class="fas fa-user-edit me-2"></i>Profil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $base_path; ?>register.php">
                            <i class="fas fa-user-plus me-1"></i>Daftar
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>