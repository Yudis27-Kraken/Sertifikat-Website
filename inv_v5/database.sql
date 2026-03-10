-- ============================================================
-- DATABASE  : inv_v5
-- Sistem     : Inventaris Kantor v5
-- Versi      : 1.0  |  2025
-- Catatan    : Database baru, tidak menabrak versi sebelumnya.
--              Satu user saja (admin), ada kolom kategori & kode.
-- ============================================================

CREATE DATABASE IF NOT EXISTS inv_v5
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE inv_v5;

-- ------------------------------------------------------------
-- TABEL: users  —  satu akun admin
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id        INT           AUTO_INCREMENT PRIMARY KEY,
  nama      VARCHAR(100)  NOT NULL,
  username  VARCHAR(50)   NOT NULL UNIQUE,
  password  VARCHAR(255)  NOT NULL,          -- bcrypt hash
  aktif     TINYINT(1)    NOT NULL DEFAULT 1,
  dibuat    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: kategori  —  master kategori barang
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kategori (
  id    INT          AUTO_INCREMENT PRIMARY KEY,
  nama  VARCHAR(60)  NOT NULL UNIQUE,
  ikon  VARCHAR(10)  NOT NULL DEFAULT '📦'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: barang  —  data inventaris utama
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS barang (
  id           INT           AUTO_INCREMENT PRIMARY KEY,
  kode         VARCHAR(20)   NOT NULL UNIQUE,    -- format: ELK-001
  nama         VARCHAR(150)  NOT NULL,
  id_kategori  INT           NOT NULL,
  lokasi       VARCHAR(100)  NOT NULL,
  jumlah       INT           NOT NULL DEFAULT 1,
  kondisi      ENUM('Baik','Rusak','Dalam Servis') NOT NULL DEFAULT 'Baik',
  keterangan   TEXT,
  tanggal      DATE          NOT NULL,
  dibuat       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  diperbarui   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (id_kategori) REFERENCES kategori(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABEL: log_aktivitas  —  audit trail
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS log_aktivitas (
  id         INT  AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50),
  aksi       ENUM('LOGIN','LOGOUT','TAMBAH','EDIT','HAPUS') NOT NULL,
  keterangan TEXT,
  waktu      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATA AWAL
-- Password: admin123
-- Hash bcrypt: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================

-- Satu user saja
INSERT INTO users (nama, username, password) VALUES
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Kategori master
INSERT INTO kategori (nama, ikon) VALUES
('Elektronik',  '💻'),
('Furniture',   '🪑'),
('Alat Tulis',  '✏️'),
('Kendaraan',   '🚗'),
('Lainnya',     '📦');

-- Data barang awal
INSERT INTO barang (kode, nama, id_kategori, lokasi, jumlah, kondisi, keterangan, tanggal) VALUES
('ELK-001', 'Laptop Dell Latitude 5520',   1, 'Ruang IT',         3,  'Baik',         'RAM 16GB, SSD 512GB, i5 Gen 11',  '2024-01-10'),
('ELK-002', 'Monitor LG 24 inch',          1, 'Ruang IT',         5,  'Baik',         'Full HD IPS Panel',               '2024-01-15'),
('ELK-003', 'Proyektor Epson EB-X51',      1, 'Ruang Rapat A',    1,  'Baik',         '3600 lumens, HDMI + VGA',         '2024-02-01'),
('ELK-004', 'Printer Canon PIXMA G3020',   1, 'Ruang Admin',      2,  'Baik',         'Ink tank system, WiFi',           '2024-02-10'),
('ELK-005', 'AC Daikin 1.5 PK',            1, 'Ruang Server',     2,  'Baik',         'Inverter, hemat listrik',         '2024-04-01'),
('ELK-006', 'UPS APC 650VA',               1, 'Ruang Server',     3,  'Baik',         'Backup power 15 menit',           '2024-05-01'),
('ELK-007', 'Telepon Panasonic',           1, 'Resepsionis',      4,  'Dalam Servis', 'Kabel putus, sedang diperbaiki',  '2024-01-05'),
('FRN-001', 'Meja Kerja Kayu',             2, 'Lantai 2',        12,  'Baik',         'Ukuran 120x60cm, laci 3 susun',   '2024-03-01'),
('FRN-002', 'Kursi Ergonomis Mesh',        2, 'Open Space',      20,  'Baik',         'Adjustable lumbar support',       '2024-03-15'),
('FRN-003', 'Lemari Arsip Besi 4 Pintu',   2, 'Lantai 3',         5,  'Rusak',        'Kunci hilang, perlu penggantian', '2023-12-01'),
('FRN-004', 'Whiteboard 120x90 cm',        2, 'Ruang Rapat B',    3,  'Baik',         'Magnetik, tersedia tray spidol',  '2024-04-10'),
('ATK-001', 'Mesin Penghancur Kertas',     3, 'Ruang Admin',      1,  'Baik',         'Cross cut, kapasitas 8 lembar',   '2024-05-10'),
('ATK-002', 'Printer Label Brother',       3, 'Gudang',           1,  'Baik',         'Mendukung pita 12mm dan 18mm',    '2024-05-20'),
('LAN-001', 'CCTV Hikvision 4 Kamera',    5, 'Lobby',            1,  'Baik',         'NVR + 4 kamera outdoor',          '2024-06-01');
