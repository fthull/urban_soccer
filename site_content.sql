-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 11, 2025 at 08:53 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
('about_heading1', '\n                    \n                    \n                    \n                    MGD Soccer Field                    \n                        Magelang                    \n                                    \n                        Magelang                    \n                                    \n                        Magel                    \n                        Magelang                    \n                '),
('about_image_path', 'uploads/68998b7c3db01_minn.jpg'),
('about_text1', '\n                    MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan'),
('about_text2', '\n                    Kami yakin bahwa sepak bola bukan hanya tentang mencetak gol, tapi juga tentang menjaga kebersamaan, tawa, dan semangat sportifitas.                '),
('feature_list6_text', 'Tempat Parkir                    '),
('footer_copyright_name', '\n            MGD Soccer Field        '),
('footer_logo_path', 'uploads/6899505c9a98d_6894529f78292_logo2.png'),
('gallery_heading', '\n            Galeri MGD Soccer Field        '),
('gallery_image5_path', 'uploads/689990582fbe3_ban.png'),
('hero_heading', '\n                    \n                    \n                    \n                    \n                    \n                    \n                    \n                    \n                    \n                    \n                    \n                    \n                    MGD Soccer Field Magelang                                                                                                                                                                                                                '),
('hero_subtext', '\n                    \n                    Waktunya main! Segera booking lapangan untuk timmu                                '),
('map_title', '\n        Lokasi Kami    ');

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
