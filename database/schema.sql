-- Database Schema untuk Sistem Kost
CREATE DATABASE IF NOT EXISTS kost_management;
USE kost_management;

-- Table Users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    role ENUM('penyewa', 'admin') DEFAULT 'penyewa',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table Rooms
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(10) UNIQUE NOT NULL,
    room_type ENUM('single', 'double', 'suite') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    facilities TEXT,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table Bookings
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    duration_months INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'active', 'completed') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Table Payments
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'transfer', 'card') NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    receipt_url VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- Table Activity Logs
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role) 
VALUES ('admin', 'admin@kost.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert sample rooms
INSERT INTO rooms (room_number, room_type, price, description, facilities, image_url) VALUES
('A01', 'single', 800000, 'Kamar single nyaman dengan fasilitas lengkap', 'AC, WiFi, Lemari, Meja Belajar', '/images/room1.jpg'),
('A02', 'single', 800000, 'Kamar single dengan pemandangan taman', 'AC, WiFi, Lemari, Meja Belajar', '/images/room2.jpg'),
('B01', 'double', 1200000, 'Kamar double untuk 2 orang', 'AC, WiFi, Lemari, 2 Kasur, Meja Belajar', '/images/room3.jpg'),
('C01', 'suite', 1800000, 'Suite mewah dengan kamar mandi dalam', 'AC, WiFi, Lemari, Sofa, Kulkas, Kamar Mandi Dalam', '/images/room4.jpg');
