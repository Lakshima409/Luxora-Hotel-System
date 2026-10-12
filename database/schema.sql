-- Luxora v2 PART 1/3: core tables
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NULL,
  role ENUM('guest','clerk','manager','admin') NOT NULL DEFAULT 'clerk',
  email VARCHAR(120) NULL, phone VARCHAR(30) NULL,
  active TINYINT(1) DEFAULT 1, failed_attempts INT DEFAULT 0,
  locked_until DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_role (`role`)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS customers (
  customer_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL, email VARCHAR(120) NULL,
  phone VARCHAR(30) NULL, id_passport VARCHAR(50) NULL,
  card_token VARCHAR(64) NULL, is_vip TINYINT(1) DEFAULT 0,
  user_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rooms (
  room_id INT AUTO_INCREMENT PRIMARY KEY, id INT NULL,
  room_number VARCHAR(10) UNIQUE NOT NULL,
  room_type VARCHAR(50) NOT NULL DEFAULT 'Standard',
  rate DECIMAL(10,2) NOT NULL DEFAULT 0,
  price DECIMAL(10,2) NULL, capacity INT DEFAULT 2, beds INT DEFAULT 1,
  room_condition ENUM('Available','Reserved','Occupied','Under Maintenance','Cleaning In Progress') DEFAULT 'Available',
  status VARCHAR(30) DEFAULT 'available', image_path VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_room_status (status)
) ENGINE=InnoDB;
-- PART 2/3: reservations + services + billing
CREATE TABLE IF NOT EXISTS reservations (
  reservation_id INT AUTO_INCREMENT PRIMARY KEY, id INT NULL,
  customer_id INT NULL, guest_id INT NULL, room_id INT NULL,
  room_number VARCHAR(10) NULL, room_type VARCHAR(50) NULL,
  guest_name VARCHAR(100) NULL, email VARCHAR(120) NULL, phone VARCHAR(30) NULL,
  check_in DATETIME NULL, check_out DATETIME NULL,
  checkin_date DATE NULL, checkout_date DATE NULL,
  check_in_date DATETIME NULL, check_out_date DATETIME NULL,
  occupants INT DEFAULT 1, num_guests INT DEFAULT 1,
  booking_status ENUM('Pending','Confirmed','Checked-In','Checked-Out','Cancelled','No-Show') DEFAULT 'Pending',
  status VARCHAR(30) DEFAULT 'booked', reservation_status VARCHAR(30) DEFAULT 'booked',
  has_card TINYINT(1) DEFAULT 0, card_last4 VARCHAR(4) NULL,
  credit_card_number VARCHAR(20) NULL,
  is_walk_in TINYINT(1) DEFAULT 0, is_walkin TINYINT(1) DEFAULT 0,
  is_group TINYINT(1) DEFAULT 0, group_id VARCHAR(36) NULL,
  booked_by ENUM('guest','clerk','travel_agency') DEFAULT 'guest',
  company_name VARCHAR(120) NULL, is_travel_agency_guest TINYINT(1) DEFAULT 0,
  special_requests TEXT NULL, notes TEXT NULL, payment_method VARCHAR(30) NULL,
  reservation_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by INT NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL,
  FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL,
  INDEX idx_res_room (room_id), INDEX idx_res_dates (check_in, check_out)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS services_catalog (
  service_id INT AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(100) UNIQUE NOT NULL,
  default_price DECIMAL(10,2) NOT NULL DEFAULT 0, active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY, service_id INT NULL,
  reservation_id INT NOT NULL, service_name VARCHAR(100) NOT NULL,
  cost DECIMAL(10,2) NOT NULL DEFAULT 0, qty INT DEFAULT 1,
  added_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
  INDEX idx_svc_res (reservation_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bills (
  bill_id INT AUTO_INCREMENT PRIMARY KEY, id INT NULL,
  reservation_id INT NOT NULL, subtotal DECIMAL(10,2) DEFAULT 0,
  tax DECIMAL(10,2) DEFAULT 0, late_fee DECIMAL(10,2) DEFAULT 0,
  discount DECIMAL(10,2) DEFAULT 0, total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  payment_status ENUM('Unpaid','Partial','Paid','Refunded') DEFAULT 'Unpaid',
  payment_method VARCHAR(30) NULL, snapshot JSON NULL,
  issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bill_items (
  item_id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NOT NULL,
  description VARCHAR(255) NOT NULL, amount DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NOT NULL,
  method VARCHAR(30) DEFAULT 'cash', amount DECIMAL(10,2) NOT NULL,
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- PART 3/3: reports, logs, settings, seeds
CREATE TABLE IF NOT EXISTS reports (
  report_id INT AUTO_INCREMENT PRIMARY KEY, report_date DATE NOT NULL UNIQUE,
  occupancy_pct DECIMAL(5,2) DEFAULT 0, revenue DECIMAL(12,2) DEFAULT 0,
  total_rooms INT DEFAULT 0, occupied_rooms INT DEFAULT 0, no_shows INT DEFAULT 0,
  payload JSON NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS action_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, role VARCHAR(20) NULL,
  action_type VARCHAR(50) NOT NULL, action VARCHAR(50) NULL,
  message TEXT NULL, description TEXT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_user (user_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS activity_logs (
  alog_id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NULL, user_id INT NULL,
  action_type VARCHAR(50) DEFAULT 'note', action TEXT NULL, description TEXT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cron_log (
  clog_id INT AUTO_INCREMENT PRIMARY KEY, id INT NULL,
  script VARCHAR(120) NOT NULL, run_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(20) DEFAULT 'success', records_affected INT DEFAULT 0, message TEXT NULL,
  INDEX idx_cron_script (script)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(80) PRIMARY KEY, svalue VARCHAR(255) NOT NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO settings (skey,svalue) VALUES
 ('tax_rate','0.10'),('late_fee','50.00'),('timezone','Asia/Colombo'),
 ('session_timeout','1800'),('currency','LKR');
INSERT IGNORE INTO services_catalog (service_name,default_price) VALUES
 ('Room Service',25.00),('Laundry',15.00),('Mini Bar',20.00),
 ('Restaurant',30.00),('Club Facility',30.00),('Telephone',10.00);
INSERT IGNORE INTO users (username,password_hash,`password`,role,email) VALUES
 ('clerk1','$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy','$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy','clerk','clerk@luxora.local'),
 ('manager1','$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy','$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy','manager','manager@luxora.local'),
 ('admin1','$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy','$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy','admin','admin@luxora.local');
INSERT IGNORE INTO rooms (room_number,room_type,rate,price,capacity,beds,room_condition,status) VALUES
 ('A101','Standard',100.00,100.00,2,1,'Available','available'),
 ('A102','Deluxe',150.00,150.00,2,1,'Available','available'),
 ('B201','Suite',250.00,250.00,4,2,'Available','available'),
 ('B202','Family Room',200.00,200.00,4,2,'Available','available');
SET FOREIGN_KEY_CHECKS=1;


