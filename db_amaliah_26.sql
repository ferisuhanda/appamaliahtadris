-- =====================================================================
-- Database: db_amaliah_26
-- Aplikasi: Amaliah Tadris - Pondok Pesantren Daar el-Qolam 4
-- Tahun Pelajaran: 2026-2027
-- =====================================================================
-- CATATAN:
-- - Jalankan script ini di phpMyAdmin / MySQL CLI
-- - Script ini akan DROP DATABASE jika ada, lalu CREATE ulang (fresh)
-- - Password default: admin123
-- =====================================================================

-- =====================================================================
-- SIMPAN SETTING LAMA (agar bisa di-restore di akhir script)
-- =====================================================================
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40101 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40101 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- FRESH DATABASE
-- =====================================================================
DROP DATABASE IF EXISTS `db_amaliah_26`;
CREATE DATABASE `db_amaliah_26`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `db_amaliah_26`;

-- =====================================================================
-- Tabel: `users`
-- =====================================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(50)  NOT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(100) DEFAULT NULL,
  `role`         ENUM('admin','user') DEFAULT 'admin',
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Password default: admin123
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- =====================================================================
-- Tabel: `guru`
-- Kriteria: Supervisor 1, Supervisor 2, Assessor
-- =====================================================================
DROP TABLE IF EXISTS `guru`;
CREATE TABLE `guru` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `nama_guru`      VARCHAR(100) NOT NULL,
  `mata_pelajaran` VARCHAR(100) NOT NULL,
  `kriteria`       ENUM('Supervisor 1','Supervisor 2','Assessor')
                   NOT NULL DEFAULT 'Assessor',
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nama_guru` (`nama_guru`),
  KEY `idx_kriteria`  (`kriteria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `guru` (`id`, `nama_guru`, `mata_pelajaran`, `kriteria`) VALUES
(1, 'Ahmad Fauzi',   'Matematika',   'Supervisor 1'),
(2, 'Siti Aminah',   'Bahasa Arab',  'Assessor'),
(3, 'Budi Santoso',  'Fisika',       'Supervisor 2'),
(4, 'Dewi Lestari',  'Kimia',        'Assessor');

-- =====================================================================
-- Tabel: `santri`
-- =====================================================================
DROP TABLE IF EXISTS `santri`;
CREATE TABLE `santri` (
  `id`            INT(11)       NOT NULL AUTO_INCREMENT,
  `induk`         VARCHAR(30)   NOT NULL,
  `siswa`         VARCHAR(100)  NOT NULL,
  `kelas`         VARCHAR(10)   NOT NULL,
  `gender`        ENUM('L','P') NOT NULL DEFAULT 'L',
  `pelajaran`     VARCHAR(100)  DEFAULT NULL,
  `kelompok`      INT(2)        DEFAULT NULL,
  `kelompok_naqd` INT(2)        DEFAULT NULL,
  `created_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_induk` (`induk`),
  KEY `idx_kelas` (`kelas`),
  KEY `idx_kelompok` (`kelompok`),
  KEY `idx_kelompok_naqd` (`kelompok_naqd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `santri` 
  (`induk`, `siswa`, `kelas`, `gender`, `pelajaran`, `kelompok`, `kelompok_naqd`) VALUES
('2024001', 'Ahmad Fauzi',      '1A',    'L', 'Bahasa Arab',   1, 1),
('2024002', 'Siti Aminah',      '2B',    'P', 'Bahasa Inggris',2, 2),
('2024003', 'Muhammad Rizki',   '3A',    'L', 'Nahwu',         3, 3),
('2024004', 'Fatimah Az-Zahra', '5 IPA', 'P', 'Hadits',        4, 4);

-- =====================================================================
-- Tabel: `teaching_practice`
-- =====================================================================
DROP TABLE IF EXISTS `teaching_practice`;
CREATE TABLE `teaching_practice` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `santri_id`      INT(11)      NOT NULL,
  `kelas_tp`       VARCHAR(10)  NOT NULL,
  `kelompok`       INT(2)       NOT NULL,
  `mata_pelajaran` VARCHAR(100) NOT NULL,
  `judul`          VARCHAR(255) NOT NULL,
  `supervisor1`    VARCHAR(100) DEFAULT NULL,
  `supervisor2`    VARCHAR(100) DEFAULT NULL,
  `assessor`       VARCHAR(100) DEFAULT NULL,
  `tanggal`        DATE         NOT NULL,
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_santri_id` (`santri_id`),
  KEY `idx_kelas_tp` (`kelas_tp`),
  KEY `idx_kelompok` (`kelompok`),
  KEY `idx_tanggal` (`tanggal`),
  CONSTRAINT `fk_tp_santri` FOREIGN KEY (`santri_id`)
    REFERENCES `santri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teaching_practice`
  (`santri_id`, `kelas_tp`, `kelompok`, `mata_pelajaran`, `judul`,
   `supervisor1`, `supervisor2`, `assessor`, `tanggal`) VALUES
(1, '1A', 1, 'Bahasa Arab',   'Latihan Mubtada',  'Ahmad Fauzi',  'Budi Santoso', 'Siti Aminah',  '2026-09-01'),
(2, '2B', 2, 'Bahasa Inggris','Simple Present',   'Ahmad Fauzi',  'Budi Santoso', 'Siti Aminah',  '2026-09-05'),
(3, '3A', 3, 'Nahwu',         'Bab Kalam',        'Budi Santoso', 'Ahmad Fauzi',  'Dewi Lestari', '2026-09-10');

-- =====================================================================
-- Tabel: `penilaian`
-- =====================================================================
DROP TABLE IF EXISTS `penilaian`;
CREATE TABLE `penilaian` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `guru_id`    INT(11)      NOT NULL,
  `tanggal`    DATE         NOT NULL,
  `nilai`      DECIMAL(5,2) DEFAULT NULL,
  `catatan`    TEXT         DEFAULT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_guru_id` (`guru_id`),
  CONSTRAINT `fk_penilaian_guru` FOREIGN KEY (`guru_id`)
    REFERENCES `guru` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Tabel: `nilai`
-- =====================================================================
DROP TABLE IF EXISTS `nilai`;
CREATE TABLE `nilai` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `santri_id`  INT(11)      NOT NULL,
  `kelas_tp`   VARCHAR(10)  NOT NULL,
  `kelompok`   INT(2)       NOT NULL,
  `kriteria`   ENUM('Supervisor 1','Supervisor 2','Assessor') NOT NULL,
  `penilai`    VARCHAR(100) NOT NULL,
  `n1`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n2`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n3`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n4`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n5`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n6`         TINYINT(1)   NOT NULL DEFAULT 0,
  `hasil`      DECIMAL(5,2) NOT NULL DEFAULT 0,
  `tanggal`    DATE         NOT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nilai` (`santri_id`,`kelas_tp`,`kelompok`,`kriteria`),
  KEY `idx_santri` (`santri_id`),
  KEY `idx_kelas` (`kelas_tp`),
  KEY `idx_kriteria` (`kriteria`),
  CONSTRAINT `fk_nilai_santri` FOREIGN KEY (`santri_id`)
    REFERENCES `santri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Tabel: `nilai_naqd`
-- Deskripsi: Menyimpan nilai 6 kriteria Naqd per siswa
-- =====================================================================
DROP TABLE IF EXISTS `nilai_naqd`;
CREATE TABLE `nilai_naqd` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `santri_id`  INT(11)      NOT NULL,
  `kelas_tp`   VARCHAR(10)  NOT NULL,
  `kelompok`   INT(2)       NOT NULL,
  `kriteria`   ENUM('Supervisor 1','Supervisor 2','Assessor') NOT NULL,
  `penilai`    VARCHAR(100) NOT NULL,
  `n1`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n2`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n3`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n4`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n5`         TINYINT(1)   NOT NULL DEFAULT 0,
  `n6`         TINYINT(1)   NOT NULL DEFAULT 0,
  `hasil`      DECIMAL(5,2) NOT NULL DEFAULT 0,
  `tanggal`    DATE         NOT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nilai_naqd` (`santri_id`,`kelas_tp`,`kelompok`,`kriteria`),
  KEY `idx_santri` (`santri_id`),
  KEY `idx_kelas` (`kelas_tp`),
  KEY `idx_kriteria` (`kriteria`),
  CONSTRAINT `fk_nilai_naqd_santri` FOREIGN KEY (`santri_id`)
    REFERENCES `santri` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================================
-- Tabel: `kelompok_amaliah`
-- =====================================================================
DROP TABLE IF EXISTS `kelompok_amaliah`;
CREATE TABLE `kelompok_amaliah` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `kelompok`     INT(2)       NOT NULL,
  `supervisor1`  VARCHAR(100) DEFAULT NULL,
  `supervisor2`  VARCHAR(100) DEFAULT NULL,
  `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_kelompok` (`kelompok`),
  KEY `idx_supervisor1` (`supervisor1`),
  KEY `idx_supervisor2` (`supervisor2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `kelompok_amaliah` (`kelompok`, `supervisor1`, `supervisor2`) VALUES
(1, 'Ahmad Fauzi',  'Budi Santoso'),
(2, 'Budi Santoso', 'Ahmad Fauzi'),
(3, 'Ahmad Fauzi',  'Dewi Lestari');

-- =====================================================================
-- RESTORE SETTING LAMA
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40101 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;

-- =====================================================================
-- SELESAI
-- =====================================================================