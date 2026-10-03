CREATE DATABASE IF NOT EXISTS sahti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sahti;

-- Safe to re-import: removes and recreates ONLY the SAHTI tables below.
-- (This erases appointments made in the new app. Back them up first if you need them.)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS users, appointments, clinics, feedback, doctors;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE doctors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  image VARCHAR(50) NOT NULL,
  speciality VARCHAR(60) NOT NULL,
  fees_da INT UNSIGNED NOT NULL,
  INDEX (speciality), INDEX (name)
) ENGINE=InnoDB;

CREATE TABLE clinics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  address VARCHAR(200) NOT NULL,
  city VARCHAR(60) NOT NULL DEFAULT 'Annaba',
  phone VARCHAR(20) NOT NULL,
  open_time TIME NOT NULL,
  close_time TIME NOT NULL,
  INDEX (address),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT UNSIGNED NOT NULL,
  name VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL,
  phone VARCHAR(16) NOT NULL,
  appt_date DATE NOT NULL,
  appt_time TIME NOT NULL,
  message VARCHAR(500) NOT NULL DEFAULT '',
  status ENUM('booked','cancelled','visited') NOT NULL DEFAULT 'booked',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- non-NULL only while booked: the UNIQUE key blocks double-booking, cancelled slots become free again
  active_slot VARCHAR(20) GENERATED ALWAYS AS (IF(status='booked', CONCAT(appt_date,' ',appt_time), NULL)) STORED,
  UNIQUE KEY uq_doctor_slot (doctor_id, active_slot),
  INDEX (phone),
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doctor_id INT UNSIGNED NULL,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('doctor','admin') NOT NULL DEFAULT 'doctor',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE feedback (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL, phone VARCHAR(15), email VARCHAR(100) NOT NULL,
  subject VARCHAR(150), message VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO doctors (id,name,image,speciality,fees_da) VALUES
(1,'Dr. BENSALAH MOHAMED ABDENOUR','01.jpg','Interniste',2000),
(2,'Dr. BERREZAG ABIDA','02.jpg','Généraliste',2500),
(3,'Dr. DRID NOUREDDINE','03.jpg','Généraliste',3000),
(4,'Dr. NACER CHERIF','04.jpg','Dentiste',1500),
(5,'Dr. Hacene Cherkeski Naïma','05.jpg','Psychologue',3000),
(6,'Dr. Hanachi Nafaa Nesrine','06.jpg','Psychologue',3500);

INSERT INTO clinics (doctor_id,name,address,phone,open_time,close_time) VALUES
(1,'Cabinet médecine interne BENSALAH MOHAMED ABDENOUR','Boulevard Bouzerad Hocine 2, Annaba 23000','038405615','08:00','17:00'),
(2,'Cabinet médical BERREZAG ABIDA','5 Rue Zenine Larbi, Annaba','038451660','08:00','16:00'),
(3,'Cabinet médical DRID NOUREDDINE','29 Rue du C.N.R.A., Annaba','038863522','08:00','15:00'),
(4,'Cabinet dentaire Dr NACER CHERIF','Bd Souidani Boudjemaa, Annaba','0557406322','09:00','18:00'),
(5,'Cabinet de psychologie Dr. Hacene Cherkeski Naïma','Cité, Béni Mhaffer, Annaba','0659009008','07:00','16:00'),
(6,'Cabinet de psychologie Hanachi Nafaa Nesrine','56 Av. Abdelhamid Ben Badis, Annaba','0555751969','08:00','16:00');

-- Demo accounts. Password for ALL of them: Sahti2026!  (change it from "Mon compte" after the first login)
INSERT INTO users (doctor_id,name,email,password_hash,role) VALUES
(NULL,'Administrateur','admin@sahti.dz','$2y$10$m0bxSP/yGsRXijd00ETJHu5.suatm1YZcB69jOa64/7xo33.bruQK','admin'),
(1,'Dr. Bensalah','doctor1@sahti.dz','$2y$10$m0bxSP/yGsRXijd00ETJHu5.suatm1YZcB69jOa64/7xo33.bruQK','doctor'),
(2,'Dr. Berrezag','doctor2@sahti.dz','$2y$10$m0bxSP/yGsRXijd00ETJHu5.suatm1YZcB69jOa64/7xo33.bruQK','doctor'),
(3,'Dr. Drid','doctor3@sahti.dz','$2y$10$m0bxSP/yGsRXijd00ETJHu5.suatm1YZcB69jOa64/7xo33.bruQK','doctor'),
(4,'Dr. Nacer','doctor4@sahti.dz','$2y$10$m0bxSP/yGsRXijd00ETJHu5.suatm1YZcB69jOa64/7xo33.bruQK','doctor'),
(5,'Dr. Cherkeski','doctor5@sahti.dz','$2y$10$m0bxSP/yGsRXijd00ETJHu5.suatm1YZcB69jOa64/7xo33.bruQK','doctor'),
(6,'Dr. Hanachi','doctor6@sahti.dz','$2y$10$m0bxSP/yGsRXijd00ETJHu5.suatm1YZcB69jOa64/7xo33.bruQK','doctor');
