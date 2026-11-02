-- Skema database Dewala HRIS
-- Cara pakai: import file ini terlebih dahulu (membuat & memilih database),
-- lalu jalankan `php database/seed.php` untuk mengisi data contoh.

CREATE DATABASE IF NOT EXISTS dewala_hris CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dewala_hris;

CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    level INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('hr', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL UNIQUE,
    department_id INT NOT NULL,
    position_id INT NOT NULL,
    manager_id INT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    hire_date DATE NOT NULL,
    position_start_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE RESTRICT,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Career Path: mendefinisikan jalur & kriteria minimum dari satu jabatan ke jabatan berikutnya
CREATE TABLE career_paths (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_position_id INT NOT NULL,
    to_position_id INT NOT NULL,
    min_tenure_months INT NOT NULL DEFAULT 12,
    min_score DECIMAL(5,2) NOT NULL DEFAULT 80,
    max_lateness_6m INT NOT NULL DEFAULT 5,
    sp_lockout_months INT NOT NULL DEFAULT 12,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_path (from_position_id, to_position_id),
    FOREIGN KEY (from_position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (to_position_id) REFERENCES positions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE competency_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    technical_score DECIMAL(5,2) NOT NULL,
    softskill_score DECIMAL(5,2) NOT NULL,
    period VARCHAR(20) NOT NULL,
    notes TEXT NULL,
    scored_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (scored_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Rekam jejak: keterlambatan, SP (Surat Peringatan), atau catatan bebas
CREATE TABLE track_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    type ENUM('keterlambatan', 'sp1', 'sp2', 'sp3', 'catatan') NOT NULL,
    record_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    from_position_id INT NOT NULL,
    to_position_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    requested_by INT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    decided_by INT NULL,
    decided_at TIMESTAMP NULL,
    notes VARCHAR(255) NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (from_position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (to_position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (decided_by) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;
