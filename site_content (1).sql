-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 13, 2025 at 06:10 AM
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
-- Table structure for table `site_content`
--

CREATE TABLE `site_content` (
  `content_key` varchar(255) NOT NULL,
  `content_value` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_content`
--

INSERT INTO `site_content` (`content_key`, `content_value`) VALUES
('about_heading', 'MGD Soccer Field  Magelang Magelang'),
('about_image_path', 'uploads/689af65f14483_min.jpg'),
('about_text1', '\n          MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan.        '),
('book_heading', 'Booking Lapangan Anda'),
('feature_list6_text', 'Parkir'),
('footer_list_1', 'Booking Lapangan'),
('gallery_heading', '\n            Galeri MGD Soccer Field        '),
('gallery_marquee_text', '\n                Lebih dari sekadar lapangan, Urban Soccer Field adalah ruang untuk mencipta kenangan. Galeri ini memperlihatkan momen kebersamaan, kerja tim, dan semangat sportivitas dari para pecinta bola.            '),
('hero_book_button_text', '\n                    Book Sekarang                '),
('hero_heading', '\n                \n                \n                \nMGD Soccer Field Magelang                                    '),
('hero_subtext', '\n                \n                \nWaktunya main! Segera booking lapangan untuk timmu                        '),
('hero_subtitle', 'Pesan sekarang dan rasakan pengalaman terbaik!'),
('hero_title', 'Selamat Datang di Lapangan Kami'),
('home_bg_image', 'uploads/689c02b833268_bann.jpeg'),
('home_subtitle', 'Waktunya main! Booking lapangan untuk timmu'),
('home_title', 'MGD SOCCER MAGELANG'),
('logo_image', 'uploads/689b6107ca7fa_bn1.png');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `site_content`
--
ALTER TABLE `site_content`
  ADD PRIMARY KEY (`content_key`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
