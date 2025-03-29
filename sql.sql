-- MySQL version of the above tables
-- This section contains the MySQL equivalent definitions for the tables used in the application.
-- The tables include users, password_resets, user_logs, and trips, each with their respective fields and constraints.

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) DEFAULT NULL,
    country VARCHAR(50) DEFAULT NULL,
    mobile VARCHAR(20) DEFAULT NULL,
    preference VARCHAR(100),
    activated TINYINT(1) DEFAULT 0,
    activation_token VARCHAR(64),
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active',
    tier ENUM('basic', 'premium', 'enterprise') DEFAULT 'basic',
    authlevel ENUM('user', 'editor', 'admin') DEFAULT 'user',
    first_login_ip VARCHAR(45),
    first_login_referer VARCHAR(255),
    first_login_browser VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reset_token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    request_ip VARCHAR(45),
    request_referer VARCHAR(255),
    request_browser VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE user_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    referer VARCHAR(255),
    browser VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    trip_type ENUM('business', 'leisure') NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    visible TINYINT(1) DEFAULT 1,
    trip_source VARCHAR(20) DEFAULT 'web',
    soft_delete TINYINT(1) DEFAULT 0,
    deleted_by INT DEFAULT NULL,
    deleted_on DATETIME DEFAULT NULL,
    trip_sync TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    item_status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
    soft_delete TINYINT(1) DEFAULT 0,
    deleted_on DATETIME DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    item_source VARCHAR(20) DEFAULT 'web',
    item_sync TINYINT(1) DEFAULT 0,
    -- Common fields
    booking_reference VARCHAR(50),
    departure_station VARCHAR(100),
    arrival_station VARCHAR(100),
    departure_gps VARCHAR(100),
    arrival_gps VARCHAR(100),
    transport_duration VARCHAR(50),
    transport_number VARCHAR(20),  
    transport_company VARCHAR(100),  
    transport_details VARCHAR(1000),  
    -- Flight specific
    flight_number VARCHAR(20),
    airline VARCHAR(100),
    scheduled_departure_time DATETIME,
    scheduled_arrival_time DATETIME,
    actual_departure_time DATETIME,
    actual_arrival_time DATETIME,
    -- Hotel specific  
    hotel_name VARCHAR(100),
    room_type VARCHAR(50),
    -- Bus/Train specific
    route VARCHAR(100),
    -- General
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modified_by INT,

    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE airlines (
    aid INT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    alias VARCHAR(100),
    iata CHAR(6),
    icao CHAR(6),
    callsign VARCHAR(100),
    country VARCHAR(100),
    active CHAR(1)
) ENGINE=InnoDB;

CREATE TABLE airports (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    city VARCHAR(255),
    country VARCHAR(255),
    iata_code VARCHAR(8),
    icao_code VARCHAR(8),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    altitude INT,
    timezone_offset INT,
    dst CHAR(2),
    timezone VARCHAR(50),
    type VARCHAR(50),
    source VARCHAR(50)
) ENGINE=InnoDB;

CREATE TABLE emails (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uid INT,
    from_address VARCHAR(255) NOT NULL,
    from_name VARCHAR(255),
    subject VARCHAR(255),
    body TEXT,
    received_date DATETIME NOT NULL,
    read_status BOOLEAN DEFAULT FALSE,
    processed BOOLEAN DEFAULT FALSE,
    attachment_count INT DEFAULT 0,
    message_id VARCHAR(255),
    in_reply_to VARCHAR(255),
    references_emails VARCHAR(255),
    importance VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uid) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_emails_from (from_address),
    INDEX idx_emails_date (received_date),
    INDEX idx_emails_status (read_status, processed)
) ENGINE=InnoDB;

-- Add MySQL indexes
ALTER TABLE users ADD INDEX idx_users_email (email);
ALTER TABLE users ADD INDEX idx_users_activation (activation_token);
ALTER TABLE users ADD INDEX idx_users_status_tier (status, tier);
ALTER TABLE password_resets ADD INDEX idx_password_resets_token (reset_token);
ALTER TABLE items ADD INDEX idx_items_category (category_id);
ALTER TABLE trips ADD INDEX idx_trips_user_dates (user_id, start_date, end_date);
ALTER TABLE trips ADD INDEX idx_trips_visible (visible);

-- Add Dummy Data

-- Insert dummy data into the users table

INSERT INTO `users` ( `username`, `email`, `password`, `first_name`, `last_name`, `country`, `mobile`, `preference`, `activated`, `activation_token`, `status`, `tier`, `authlevel`, `first_login_ip`, `first_login_referer`, `first_login_browser`, `created_at`, `updated_at`) VALUES
('user1', 'user1@example.com', 'password', 'John', 'Doe', 'United Kingdom', '+447123456789', 'leisure', 1, 'token456', 'active', 'basic', 'user', '192.168.1.2', 'https://example.com', 'Firefox', '2025-03-29 16:02:22', '2025-03-29 16:02:22'),
('user2', 'user2@example.com', 'password', 'Jane', 'Smith', 'Australia', '+614123456789', 'adventure', 1, 'token789', 'active', 'premium', 'editor', '192.168.1.3', 'https://example.com', 'Safari', '2025-03-29 16:02:22', '2025-03-29 16:02:22');

-- Insert dummy data into the trips table
INSERT INTO trips (user_id, title, description, start_date, end_date, trip_type, status, visible)   
VALUES                              
(1, 'Business Trip to New York', 'Meeting with clients and attending industry conference', '2025-05-10', '2025-05-15', 'business', 'active', 1),
(2, 'Family Vacation to Paris', 'Exploring the city of love with family', '2025-06-20', '2025-06-25', 'leisure', 'active', 1),
(3, 'Adventure Tour in Nepal', 'Climbing Mount Everest and exploring the Himalayas', '2025-07-05', '2025-07-15', 'adventure', 'active', 1);

-- Insert dummy data into the categories table              
INSERT INTO categories (name, color, icon) VALUES
('Flight', '#0d6efd', 'fas fa-plane'),
('Bus', '#d1dc38', 'fas fa-bus'),
('Train', '#ffc107', 'fas fa-train'),
('Hotel', '#28a745', 'fas fa-hotel');

-- Insert dummy data into the items table       
INSERT INTO items (trip_id, user_id, category_id, title, item_status, booking_reference, departure_station, arrival_station, departure_gps, arrival_gps, transport_duration, transport_number, transport_company, transport_details, flight_number, airline, scheduled_departure_time, scheduled_arrival_time, actual_departure_time, actual_arrival_time, hotel_name, room_type, route)
VALUES
(1, 1, 1, 'Flight to New York', 'planned', 'NYC123', 'JFK', 'LGA', '40.785091,-73.968285', '40.758675,-73.978675', '2:00:00', 'FL123', 'American Airlines', 'Direct flight', 'FL123', 'American Airlines', '2025-05-10 08:00:00', '2025-05-10 10:00:00', NULL, NULL, NULL, NULL, NULL),
(1, 1, 1, 'Return Flight from New York', 'planned', 'NYC124', 'LGA', 'JFK', '40.758675,-73.978675', '40.785091,-73.968285', '2:00:00', 'FL124', 'American Airlines', 'Direct flight', 'FL124', 'American Airlines', '2025-05-15 08:00:00', '2025-05-15 10:00:00', NULL, NULL, NULL, NULL, NULL),
(1, 1, 1, 'Connection Flight', 'planned', 'NYC125', 'JFK', 'BOS', '40.785091,-73.968285', '42.366978,-71.022362', '1:30:00', 'FL125', 'Delta Airlines', 'Direct flight', 'FL125', 'Delta Airlines', '2025-05-12 14:00:00', '2025-05-12 15:30:00', NULL, NULL, NULL, NULL, NULL),
(1, 1, 2, 'Bus to Manhattan', 'planned', 'BUS123', 'Port Authority', 'Times Square', '40.757008,-73.991545', '40.758896,-73.985130', '0:30:00', 'B123', 'NYC Transit', 'Express bus service', NULL, NULL, '2025-05-10 11:00:00', '2025-05-10 11:30:00', NULL, NULL, NULL, NULL, 'Downtown Express'),
(1, 1, 2, 'Airport Shuttle', 'planned', 'SHUT123', 'JFK Airport', 'Hotel Zone', '40.641766,-73.780968', '40.758896,-73.985130', '1:00:00', 'S123', 'SuperShuttle', 'Shared ride service', NULL, NULL, '2025-05-10 10:30:00', '2025-05-10 11:30:00', NULL, NULL, NULL, NULL, 'Airport-Hotel Route'),
(1, 1, 3, 'Train to Conference', 'planned', 'TRN456', 'Penn Station', 'Grand Central', '40.750568,-73.993519', '40.752726,-73.977229', '0:20:00', 'T456', 'MTA', 'Subway service', NULL, NULL, '2025-05-11 08:00:00', '2025-05-11 08:20:00', NULL, NULL, NULL, NULL, 'Midtown Line'),
(1, 1, 3, 'Train to Brooklyn', 'planned', 'TRN457', 'Grand Central', 'Atlantic Ave', '40.752726,-73.977229', '40.684496,-73.976256', '0:45:00', 'T457', 'MTA', 'Subway service', NULL, NULL, '2025-05-12 09:00:00', '2025-05-12 09:45:00', NULL, NULL, NULL, NULL, 'Downtown Line'),
(1, 1, 4, 'Hilton Midtown', 'planned', 'HTL123', NULL, NULL, '40.762188,-73.983708', NULL, NULL, NULL, NULL, 'Luxury business hotel', NULL, NULL, '2025-05-10 15:00:00', '2025-05-15 11:00:00', NULL, NULL, 'Hilton Midtown Manhattan', 'Executive Suite', NULL);
