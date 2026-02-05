CREATE DATABASE IF NOT EXISTS attendance_system DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE attendance_system;

-- เก็บ Log การเช็คชื่อ
CREATE TABLE IF NOT EXISTS checkin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    device_id VARCHAR(64) NOT NULL,
    checkin_date DATE NOT NULL,
    checkin_time TIME NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lon DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- กัน Device ID ซ้ำในวันเดียวกัน
    UNIQUE KEY unique_checkin (device_id, checkin_date)
);

-- เก็บสถานะเปิด/ปิด
CREATE TABLE IF NOT EXISTS system_settings (
    setting_name VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(50) NOT NULL
);

-- Default เปิดไว้ก่อน
INSERT IGNORE INTO system_settings (setting_name, setting_value) VALUES ('checkin_status', 'open');