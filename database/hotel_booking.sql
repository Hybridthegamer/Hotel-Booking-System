-- ============================================================
-- Hotel Booking System with Intelligent Reservation Queue
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS hotel_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_booking;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address VARCHAR(255),
    gender ENUM('Male','Female','Other') DEFAULT 'Male',
    state VARCHAR(50),
    status ENUM('active','suspended') DEFAULT 'active',
    role ENUM('customer','admin') DEFAULT 'customer',
    trust_score DECIMAL(5,2) DEFAULT 100.00,
    total_bookings INT DEFAULT 0,
    completed_stays INT DEFAULT 0,
    cancellations INT DEFAULT 0,
    failed_bookings INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL UNIQUE,
    room_type ENUM('Commercial','Business','Executive','Double','Suite') NOT NULL,
    floor INT DEFAULT 1,
    capacity INT DEFAULT 2,
    rate DECIMAL(10,2) NOT NULL,
    description TEXT,
    amenities TEXT,
    status ENUM('available','occupied','reserved','maintenance') DEFAULT 'available',
    image VARCHAR(255) DEFAULT 'default-room.jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    nights INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    special_requests TEXT,
    status ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending',
    payment_status ENUM('unpaid','paid','refunded') DEFAULT 'unpaid',
    payment_method ENUM('card','bank_transfer','cash') DEFAULT 'card',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reservation Queue — holds users waiting for a room type
CREATE TABLE IF NOT EXISTS reservation_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_type ENUM('Commercial','Business','Executive','Double','Suite') NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    priority_score DECIMAL(8,2) DEFAULT 0.00,
    queue_position INT DEFAULT 0,
    status ENUM('waiting','allocated','expired','cancelled') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    allocated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Temporary reservations — room held while user completes payment
CREATE TABLE IF NOT EXISTS temp_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status ENUM('active','expired','converted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Trust score log — audit trail for trust changes
CREATE TABLE IF NOT EXISTS trust_score_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type ENUM('booking_completed','stay_completed','cancellation','failed_payment','no_show','long_stay_completed') NOT NULL,
    score_change DECIMAL(5,2) NOT NULL,
    score_after DECIMAL(5,2) NOT NULL,
    booking_id INT,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Payment transactions
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    transaction_ref VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('card','bank_transfer','cash') DEFAULT 'card',
    status ENUM('pending','success','failed','refunded') DEFAULT 'pending',
    gateway_response TEXT,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admin activity log
CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Seed Data
-- ============================================================

-- Default admin account (password: Admin@1234)
INSERT INTO users (full_name, username, email, password, role, trust_score) VALUES
('Hotel Administrator', 'admin', 'admin@hotelbooking.com', '$2y$12$LQv3c1yqBWVHxkd0LQ4fbuDovJpOKNfHGXf4FHIDsN4OU9BcUoHM2', 'admin', 100.00);

-- Sample rooms
INSERT INTO rooms (room_number, room_type, floor, capacity, rate, description, amenities, status) VALUES
('101', 'Commercial', 1, 2, 7000.00, 'Comfortable standard room with city view and essential amenities for budget-conscious travellers.', 'WiFi,TV,Air Conditioning,Hot Water,Wardrobe', 'available'),
('102', 'Commercial', 1, 2, 7000.00, 'Standard room with garden view, ideal for short stays.', 'WiFi,TV,Air Conditioning,Hot Water,Wardrobe', 'available'),
('103', 'Commercial', 1, 2, 8000.00, 'Spacious commercial room with enhanced furnishings.', 'WiFi,TV,Air Conditioning,Hot Water,Wardrobe,Mini Fridge', 'available'),
('201', 'Business', 2, 2, 12000.00, 'Well-appointed business room with work desk, fast WiFi, and premium bedding.', 'WiFi,TV,Air Conditioning,Hot Water,Work Desk,Mini Bar,Safe', 'available'),
('202', 'Business', 2, 2, 12000.00, 'Business room with pool view, perfect for corporate travellers.', 'WiFi,TV,Air Conditioning,Hot Water,Work Desk,Mini Bar,Safe', 'available'),
('203', 'Business', 2, 3, 14000.00, 'Larger business room accommodating up to 3 guests.', 'WiFi,TV,Air Conditioning,Hot Water,Work Desk,Mini Bar,Safe,Sofa', 'available'),
('301', 'Executive', 3, 2, 11000.00, 'Elegant executive room with panoramic views and premium finishes.', 'WiFi,TV,Air Conditioning,Hot Water,Jacuzzi,Mini Bar,Safe,Lounge Chair', 'available'),
('302', 'Executive', 3, 2, 11000.00, 'Executive room with king-size bed and luxury bathroom.', 'WiFi,TV,Air Conditioning,Hot Water,Jacuzzi,Mini Bar,Safe,Lounge Chair', 'available'),
('401', 'Double', 4, 4, 56000.00, 'Spacious double room with two large beds, ideal for families or groups.', 'WiFi,TV,Air Conditioning,Hot Water,Double Beds,Kitchen,Living Area,Safe', 'available'),
('402', 'Double', 4, 4, 56000.00, 'Double room with private balcony and stunning views.', 'WiFi,TV,Air Conditioning,Hot Water,Double Beds,Kitchen,Living Area,Balcony', 'available'),
('501', 'Suite', 5, 4, 85000.00, 'Luxury presidential suite with separate living room, dining area, and butler service.', 'WiFi,TV,Air Conditioning,Hot Water,Living Room,Dining Area,Jacuzzi,Bar,Butler Service', 'available'),
('502', 'Suite', 5, 2, 75000.00, 'Honeymoon suite with romantic décor and premium spa bath.', 'WiFi,TV,Air Conditioning,Hot Water,Spa Bath,Living Room,Bar,Room Service', 'available');
