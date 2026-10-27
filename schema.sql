CREATE DATABASE IF NOT EXISTS medstore CHARACTER SET utf8mb4;
USE medstore;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  note VARCHAR(120) NOT NULL DEFAULT '',
  category VARCHAR(60) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  requires_rx TINYINT(1) NOT NULL DEFAULT 0,
  controlled TINYINT(1) NOT NULL DEFAULT 0,   -- narcotic/psychotropic: never sold online
  active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_token CHAR(32) NOT NULL UNIQUE,         -- customer tracking link
  customer_name VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  address TEXT NOT NULL,
  lat DOUBLE NULL, lng DOUBLE NULL,
  delivery_type ENUM('normal','emergency') NOT NULL DEFAULT 'normal',
  delivery_fee DECIMAL(10,2) NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  needs_rx TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('rx_review','need_better_photo','approved','packed','out_for_delivery',
              'delivered','rejected','cancelled') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status, delivery_type, created_at),
  INDEX (phone, created_at)
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL, product_id INT NOT NULL,
  qty INT NOT NULL, unit_price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE staff (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','pharmacist','worker','dispatcher') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE prescriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  file_name VARCHAR(80) NOT NULL,               -- stored outside the web root
  status ENUM('pending','approved','rejected','need_better_photo') NOT NULL DEFAULT 'pending',
  prescriber_name VARCHAR(120) NULL,
  prescriber_reg_no VARCHAR(60) NULL,
  verification_method ENUM('document_checked','registry_checked','prescriber_phoned') NULL,
  reviewed_by INT NULL, reviewed_at DATETIME NULL, note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (reviewed_by) REFERENCES staff(id)
);

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NULL, order_id INT NULL,
  action VARCHAR(60) NOT NULL, detail VARCHAR(255) NULL, ip VARCHAR(45) NULL,
  at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL, username VARCHAR(60) NOT NULL,
  at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, at), INDEX (username, at)
);

-- Emergency SOS and ambulance booking
CREATE TABLE providers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type ENUM('ambulance','hospital') NOT NULL,
  phone VARCHAR(20) NOT NULL,
  lat DOUBLE NOT NULL, lng DOUBLE NOT NULL,
  api_token CHAR(64) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX (active, lat, lng)
);

-- One row per ambulance. The driver signs in at driver.php with a private code (stored hashed).
CREATE TABLE ambulances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider_id INT NULL,
  plate VARCHAR(20) NOT NULL UNIQUE,
  driver_name VARCHAR(120) NOT NULL,
  driver_phone VARCHAR(20) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  status ENUM('offline','available','busy') NOT NULL DEFAULT 'offline',
  lat DOUBLE NULL, lng DOUBLE NULL, accuracy_m INT NULL,
  last_seen DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (provider_id) REFERENCES providers(id),
  INDEX (status, last_seen)
);

CREATE TABLE incidents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  public_token CHAR(32) NOT NULL UNIQUE,      -- the requester's private tracking/cancel key
  share_token  CHAR(32) NOT NULL UNIQUE,      -- read-only link for family
  phone VARCHAR(20) NOT NULL,                 -- who is asking (contact)
  for_other TINYINT(1) NOT NULL DEFAULT 0,
  patient_name VARCHAR(120) NULL, patient_phone VARCHAR(20) NULL,
  address_text VARCHAR(500) NULL,
  lat DOUBLE NULL, lng DOUBLE NULL,           -- NULL when the requester typed an address instead of sharing GPS
  emergency_type VARCHAR(40) NOT NULL DEFAULT 'general',
  status ENUM('open','needs_staff','acknowledged','arrived','completed','cancelled','call_fallback') NOT NULL DEFAULT 'open',
  ack_deadline DATETIME NOT NULL,
  acknowledged_by INT NULL,                   -- provider that took it (phone flow)
  ambulance_id INT NULL,                      -- ambulance that took it (live-tracking flow)
  destination_id INT NULL,                    -- hospital chosen by the patient
  accepted_at DATETIME NULL, arrived_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ambulance_id) REFERENCES ambulances(id),
  FOREIGN KEY (destination_id) REFERENCES providers(id),
  INDEX (status, ack_deadline)
);

CREATE TABLE incident_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  incident_id INT NOT NULL,
  provider_id INT NULL, ambulance_id INT NULL,
  notified_at DATETIME NOT NULL,
  outcome ENUM('pending','accepted','declined','expired') NOT NULL DEFAULT 'pending',
  FOREIGN KEY (incident_id) REFERENCES incidents(id),
  INDEX (incident_id, ambulance_id)
);

-- Sample data. REPLACE providers with real, verified partners before any real use.
INSERT INTO products (id,name,note,category,price,stock,requires_rx) VALUES
 (1,'Paracetamol 500 mg','Strip of 10 tablets','Pain & fever',20,200,0),
 (2,'ORS sachets','Pack of 5','First aid',60,100,0),
 (3,'Cetirizine 10 mg','Strip of 10 tablets','Cold & allergy',30,150,0),
 (4,'Antiseptic solution','100 ml bottle','First aid',110,80,0),
 (5,'Crepe bandage roll','10 cm','First aid',90,80,0),
 (6,'Digital thermometer','Fast-read','Devices',250,40,0),
 (7,'Blood pressure monitor','Upper-arm, automatic','Devices',3200,10,0),
 (8,'Ibuprofen 400 mg','Strip of 10 tablets','Pain & fever',45,120,0),
 (9,'Amoxicillin 500 mg','Strip of 10 capsules','Prescription',140,60,1),
 (10,'Atorvastatin 20 mg','Strip of 10 tablets','Prescription',180,60,1),
 (11,'Salbutamol inhaler','200 doses','Prescription',350,25,1),
 (12,'Cough syrup','100 ml','Cold & allergy',95,70,0);
INSERT INTO providers (name,type,phone,lat,lng,api_token) VALUES
 ('Test Ambulance A','ambulance','+9779800000001',27.7172,85.3240,SHA2('tokenA',256)),
 ('Test Hospital B','hospital','+9779800000002',27.7050,85.3300,SHA2('tokenB',256));
