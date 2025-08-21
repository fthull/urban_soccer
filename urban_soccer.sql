-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 19, 2025 at 06:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `urban_soccer`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$afl4sUxVXj/f62hbziBFjebljchcHYVHJ5GMHTyylg4EOkq8s7JqS', '2025-08-12 12:44:02');

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `waktu` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Menunggu',
  `sewa_sepatu` int(11) NOT NULL DEFAULT 0,
  `sewa_rompi` int(11) NOT NULL DEFAULT 0,
  `total_harga` int(11) DEFAULT 0,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `waktu_booking` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`id`, `nama`, `no_hp`, `tanggal`, `waktu`, `status`, `sewa_sepatu`, `sewa_rompi`, `total_harga`, `bukti_pembayaran`, `waktu_booking`) VALUES
(39, 'emsit', '087998006995', '2025-08-14', '2025-08-14 22:30:00', 'booked', 3, 1, 735000, NULL, NULL),
(40, 'Citra', '081234432234', '2025-08-15', '2025-08-15 21:00:00', 'Menunggu', 10, 0, 800000, NULL, NULL),
(41, 'Citra', '089098765422', '2025-08-15', '2025-08-15 22:30:00', 'booked', 3, 0, 730000, NULL, NULL),
(42, 'Irvan', '08765432109', '2025-08-16', '2025-08-16 06:00:00', 'Menunggu', 7, 0, 770000, NULL, NULL),
(43, 'fadillah', '081234234234', '2025-08-19', '2025-08-19 21:00:00', 'Booked', 1, 1, 715000, '68a3e5e590678.jpg', NULL),
(44, 'fadillah', '081234234234', '2025-08-19', '2025-08-19 15:00:00', 'Menunggu', 1, 0, 30, NULL, '2025-08-19 09:46:57'),
(45, 'fathull', '081234432234', '2025-08-19', '2025-08-19 22:30:00', 'Booked', 4, 3, 15120, '68a3f111b50ee.jpg', '2025-08-19 10:35:06'),
(46, 'indah', '082888999777', '2025-08-20', '2025-08-20 15:00:00', 'Booked', 5, 4, 20150, '68a3f96bd28d1.jpg', '2025-08-19 11:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `site_content`
--

CREATE TABLE `site_content` (
  `content_key` varchar(255) NOT NULL,
  `content_value` longtext DEFAULT NULL,
  `key` varchar(255) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_content`
--

INSERT INTO `site_content` (`content_key`, `content_value`, `key`, `value`) VALUES
('', NULL, 'modal_form_title', 'Detail Booking'),
('about_heading', 'MGD Soccer Field  MagelangMagelang', '', ''),
('about_image_path', 'uploads/689edd8fdfb99_min.jpg', '', ''),
('about_text1', 'MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan.', '', ''),
('abou_head', 'MGD Soccer Field Magelang', '', ''),
('ab_head', 'MGD Soccer Field Magelang', '', ''),
('booking_cancel_button', 'Batal', '', ''),
('booking_field_rent', 'Biaya Sewa Lapangan', '', ''),
('booking_notes_label', 'Catatan Tambahan:', '', ''),
('booking_shoes_label', 'Sewa Sepatu (Rp30.000/pasang):', '', ''),
('booking_shoes_label_text', 'Sewa Sepatu', '', ''),
('booking_shoes_price', '30.000', '', ''),
('booking_summary_title', 'Rincian Biaya', '', ''),
('booking_total_title', 'Total Bayar', '', ''),
('book_heading', 'Booking Lapangan Anda', '', ''),
('confirmation_cancel_btn', 'Batal', '', ''),
('confirmation_submit_btn', 'Ya, Pesanan sudah benar', '', ''),
('confirmation_subtitle', 'Pastikan data yang Anda isi sudah benar sebelum melanjutkan.', '', ''),
('confirmation_title', 'Pesanan Anda sudah benar?', '', ''),
('feature_list6_text', 'Parkir', '', ''),
('field_rent_price', 'Rp800.000', '', ''),
('footer_list_1', 'Booking Lapangan', '', ''),
('form_cancel_btn', 'Batal', '', ''),
('form_nama_label', 'Nama :', '', ''),
('form_nama_placeholder', 'Nama Lengkap', '', ''),
('form_phone_label', 'Nomor :', '', ''),
('form_phone_placeholder', 'Nomor Telepon', '', ''),
('form_submit_btn', 'Booking Sekarang', '', ''),
('gallery_heading', '\n            Galeri MGD Soccer Field        ', '', ''),
('gallery_image1_path', 'uploads/68a3e891dc5c1_gal2.png', '', ''),
('gallery_image2_path', 'uploads/689ede6e1dd72_gal1.png', '', ''),
('gallery_image7_path', 'uploads/68a3e87e54e7b_gal7.png', '', ''),
('gallery_marquee_text', '\n                Lebih dari sekadar lapangan, Urban Soccer Field adalah ruang untuk mencipta kenangan. Galeri ini memperlihatkan momen kebersamaan, kerja tim, dan semangat sportivitas dari para pecinta bola.            ', '', ''),
('hero_book_button_text', '\n                    Book Sekarang                ', '', ''),
('hero_heading', '\n                \n                \n                \nMGD Soccer Field Magelang                                    ', '', ''),
('hero_subtext', '\n                \n                \nWaktunya main! Segera booking lapangan untuk timmu                        ', '', ''),
('hero_subtitle', 'Pesan sekarang dan rasakan pengalaman terbaik!', '', ''),
('hero_title', 'Selamat Datang di Lapangan Kami', '', ''),
('home_bg_image', 'uploads/689e962b6c5dd_bn1.png', '', ''),
('home_subtitle', 'Waktunya main! Booking lapangan untuk timmu', '', ''),
('home_title', 'MGD SOCCER MAGELANG', '', ''),
('logo_image', 'uploads/689b6107ca7fa_bn1.png', '', ''),
('modal_detail_tanggal_label', 'Tanggal:', '', ''),
('modal_detail_waktu_label', 'Waktu:', '', ''),
('modal_form_subtitle', 'Isi data untuk konfirmasi pemesanan.', '', ''),
('modal_form_title', 'Detail Booking', '', ''),
('modal_summary_title', 'Rincian Biaya', '', ''),
('modal_summary_total_label', 'Total Bayar', '', ''),
('price_lainnya', '7500', '', ''),
('price_lapangan', 'Rp800.000', '', ''),
('price_lapangan_label', 'Biaya Sewa Lapangan', '', ''),
('price_rompi', '5000', '', ''),
('price_sepatu', '10000', '', ''),
('sewa_lainnya_label', 'Sewa Lainnya', '', ''),
('sewa_rompi_label', 'Sewa Rompi', '', ''),
('sewa_sepatu_label', 'Sewa Sepatu', '', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_content`
--
ALTER TABLE `site_content`
  ADD PRIMARY KEY (`content_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
