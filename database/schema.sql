CREATE DATABASE IF NOT EXISTS library_attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_attendance_db;

CREATE TABLE users (
 user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(150) NOT NULL,
 username VARCHAR(50) NOT NULL UNIQUE,
 password VARCHAR(255) NOT NULL,
 role ENUM('super_admin','moderator') NOT NULL DEFAULT 'moderator',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE departments (
 department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 department_name VARCHAR(100) NOT NULL UNIQUE,
 category_type ENUM('program','faculty','other') NOT NULL DEFAULT 'program',
 status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE persons (
 person_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 school_id VARCHAR(50) NOT NULL UNIQUE,
 first_name VARCHAR(100) NOT NULL,
 last_name VARCHAR(100) NOT NULL,
 person_type ENUM('student','faculty') NOT NULL,
 department_id INT UNSIGNED NOT NULL,
 qr_token_hash CHAR(64) NULL UNIQUE,
 status ENUM('active','inactive') NOT NULL DEFAULT 'active',
 is_demo TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_person_department FOREIGN KEY(department_id) REFERENCES departments(department_id)
) ENGINE=InnoDB;

CREATE TABLE attendance_records (
 attendance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 person_id INT UNSIGNED NOT NULL,
 attendance_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 attendance_method ENUM('manual','qr') NOT NULL,
 recorded_by INT UNSIGNED NOT NULL,
 is_demo TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_att_person FOREIGN KEY(person_id) REFERENCES persons(person_id),
 CONSTRAINT fk_att_user FOREIGN KEY(recorded_by) REFERENCES users(user_id),
 INDEX idx_att_datetime(attendance_datetime),
 INDEX idx_person_datetime(person_id,attendance_datetime),
 INDEX idx_att_demo_datetime(is_demo,attendance_datetime)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
 log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 action VARCHAR(100) NOT NULL,
 description TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_log_user FOREIGN KEY(user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO departments(department_name,category_type) VALUES
('BLIS','program'),('BLIS FACULTY','faculty'),('BSMID','program'),('BSMID FACULTY','faculty'),
('BSIT','program'),('BSIT FACULTY','faculty'),('BSBA','program'),('BSBA FACULTY','faculty'),
('BSHM','program'),('HM FACULTY','faculty'),('BSCD','program'),('AB SOCIO','program'),
('ARTS & SCIENCES FACULTY','faculty'),('BEED','program'),('BSED ENGLISH','program'),
('BSED FIL','program'),('BSED SOCSTUD','program'),('BPED','program'),
('EDUCATION FACULTY','faculty'),('COE AUTOMOTIVE','program'),('COE ELECTRONICS','program'),
('ENGINEERING FACULTY','faculty'),('BSCRIM','program'),('CRIM FACULTY','faculty'),('PROFED','other');
