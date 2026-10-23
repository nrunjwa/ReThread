-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2026 at 07:46 AM
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
-- Database: `rethread`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `admin_email` varchar(100) NOT NULL,
  `admin_password` varchar(255) NOT NULL,
  `admin_phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `admin_name`, `admin_email`, `admin_password`, `admin_phone`, `is_active`, `created_at`) VALUES
(1, 'System Administrator', 'admin@example.com', 'DEMO001', '0121112233', 1, '2026-10-20 04:23:57'),
(2, 'Township Manager', 'manager@example.com', 'DEMO002', '0132223344', 1, '2026-10-20 04:23:57');

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `donation_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `org_id` varchar(20) NOT NULL,
  `material` varchar(50) NOT NULL,
  `weight_kg` decimal(5,2) NOT NULL,
  `status` enum('Scheduled','Processing','Completed','Recycled') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `condition` varchar(50) DEFAULT 'Wearable'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donations`
--

INSERT INTO `donations` (`donation_id`, `donor_id`, `org_id`, `material`, `weight_kg`, `status`, `created_at`, `condition`) VALUES
(1, 1, 'ORG001', 'Cotton', 12.50, 'Completed', '2026-10-20 12:41:42', 'Wearable'),
(2, 1, 'ORG002', 'Denim', 8.00, 'Processing', '2026-10-20 12:41:42', 'Wearable'),
(3, 2, 'ORG001', 'Polyester', 6.00, 'Completed', '2026-10-20 12:41:42', 'Wearable'),
(4, 3, 'ORG003', 'Mixed Textiles', 14.00, 'Scheduled', '2026-10-20 12:41:42', 'Wearable'),
(5, 4, 'ORG004', 'Wool', 5.00, 'Completed', '2026-10-20 12:41:42', 'Wearable'),
(6, 5, 'ORG006', 'Linen', 3.00, 'Recycled', '2026-10-20 12:41:42', 'Wearable'),
(7, 1, 'ORG004', 'Denim', 4.00, 'Scheduled', '2026-10-20 18:18:51', 'Wearable'),
(8, 1, 'ORG004', 'Denim', 4.00, 'Scheduled', '2026-10-20 19:31:16', 'Wearable'),
(9, 1, 'ORG007', 'Shoes', 2.00, 'Scheduled', '2026-10-20 19:36:27', 'Lightly Worn'),
(10, 6, 'ORG004', 'Linen', 4.00, 'Scheduled', '2026-10-21 01:47:46', 'Lightly Worn'),
(11, 6, 'ORG001', 'Polyester', 5.00, 'Scheduled', '2026-10-21 01:52:05', 'Lightly Worn');

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `donor_id` int(11) NOT NULL,
  `donor_name` varchar(100) NOT NULL,
  `donor_email` varchar(100) NOT NULL,
  `donor_password` varchar(255) NOT NULL,
  `donor_phone` varchar(20) DEFAULT NULL,
  `donor_address` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`donor_id`, `donor_name`, `donor_email`, `donor_password`, `donor_phone`, `donor_address`, `is_active`, `created_at`) VALUES
(1, 'Aina Rahman', 'aina@example.com', 'DEMO_HASH_001', '0123456789', 'Section 7, Shah Alam, Selangor', 1, '2026-10-20 04:22:59'),
(2, 'Daniel Lim', 'daniel@example.com', 'DEMO_HASH_002', '0134567890', 'Section 13, Shah Alam, Selangor', 1, '2026-10-20 04:22:59'),
(3, 'Nur Izzati', 'izzati@example.com', 'DEMO_HASH_003', '0145678901', 'Setia Alam, Selangor', 1, '2026-10-20 04:22:59'),
(4, 'Amir Hakim', 'amir@example.com', 'DEMO_HASH_004', '0167890123', 'Kota Kemuning, Selangor', 0, '2026-10-20 04:22:59'),
(5, 'Sofia Tan', 'sofia@example.com', 'DEMO_HASH_005', '0178901234', 'Subang Jaya, Selangor', 1, '2026-10-20 04:22:59'),
(6, 'Tung Sahur', 'tungtungsahur@mail.com', 'TungSahur@123', '0123456789', 'Ampang, 41200', 1, '2026-10-20 19:55:13');

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `org_id` varchar(100) NOT NULL,
  `org_name` varchar(100) DEFAULT NULL,
  `org_address` varchar(100) DEFAULT NULL,
  `capacity_kg` decimal(8,2) DEFAULT NULL,
  `occupied_kg` decimal(8,2) DEFAULT NULL,
  `accepted_materials` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `org_email` varchar(100) DEFAULT NULL,
  `org_password` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`org_id`, `org_name`, `org_address`, `capacity_kg`, `occupied_kg`, `accepted_materials`, `is_active`, `org_email`, `org_password`) VALUES
('ORG001', 'ReWear Collection Hub', 'Seksyen 7, Shah Alam, Selangor', 500.00, 325.00, '[\"Cotton\",\"Polyester\",\"Denim\"]', 1, 'rewearcollect@mail.com', 'demo123'),
('ORG002', 'GreenThread Recycling', 'Seksyen 13, Shah Alam, Selangor', 800.00, 560.00, '[\"Cotton\",\"Polyester\",\"Mixed Textiles\"]', 1, 'greenthread@mail.com', 'GreenT321'),
('ORG003', 'SecondLife Charity Centre', 'Seksyen 9, Shah Alam, Selangor', 300.00, 125.00, '[\"Wearable Clothes\",\"Shoes\"]', 0, NULL, NULL),
('ORG004', 'EcoFabric Solutions', 'Glenmarie, Shah Alam, Selangor', 1000.00, 762.00, '[\"Cotton\",\"Denim\",\"Linen\"]', 1, NULL, NULL),
('ORG005', 'Community Textile Bank', 'Kota Kemuning, Shah Alam, Selangor', 400.00, 180.00, '[\"Wearable Clothes\",\"Household Textiles\"]', 1, NULL, NULL),
('ORG006', 'RenewTex Processing Centre', 'Subang Jaya, Selangor', 1200.00, 950.00, '[\"Polyester\",\"Nylon\",\"Mixed Textiles\"]', 1, NULL, NULL),
('ORG007', 'Hope Closet Foundation', 'Setia Alam, Selangor', 250.00, 92.00, '[\"Wearable Clothes\",\"Shoes\",\"Bags\"]', 1, NULL, NULL),
('ORG008', 'Urban Fabric Recovery', 'Puchong, Selangor', 600.00, 600.00, '[\"Cotton\",\"Denim\",\"Polyester\"]', 0, NULL, NULL),
('ORG009', 'ReThread Donation Point A', 'Seksyen 2, Shah Alam, Selangor', 200.00, 65.00, '[\"Wearable Clothes\",\"Cotton\"]', 1, NULL, NULL),
('ORG010', 'Circular Textile Partners', 'Klang, Selangor', 1500.00, 1120.00, '[\"Cotton\",\"Polyester\",\"Linen\",\"Denim\"]', 0, NULL, NULL),
('ORG011', 'Bellerina Capuchina', 'Alam Kayangan, 45000', 200.00, 0.00, '[]', 1, 'bellerinaCap@mail.com', 'Bellerina123');

-- --------------------------------------------------------

--
-- Table structure for table `pickups`
--

CREATE TABLE `pickups` (
  `pickup_id` int(11) NOT NULL,
  `donation_id` int(11) DEFAULT NULL,
  `schedule_at` datetime DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `pickup_type` varchar(20) DEFAULT 'Collection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pickups`
--

INSERT INTO `pickups` (`pickup_id`, `donation_id`, `schedule_at`, `address`, `status`, `completed_at`, `pickup_type`) VALUES
(1, 1, '2026-10-20 10:00:00', '123 Cotton Lane, Section 7', 'Completed', '2026-10-20 11:30:00', 'Collection'),
(2, 2, '2026-10-20 14:00:00', '45 Denim Road, Seksyen 13', 'Completed', '2026-10-20 15:00:00', 'Collection'),
(3, 4, '2026-10-23 09:00:00', '88 Textile Ave, Seksyen 9', 'Pending', NULL, 'Collection'),
(4, 5, '2026-10-20 16:00:00', '77 Wool Street, Glenmarie', 'Completed', '2026-10-20 17:15:00', 'Collection'),
(5, 7, '2026-10-24 10:30:00', '12 Denim Close, Glenmarie', 'Pending', NULL, 'Collection'),
(6, 9, '2026-10-21 03:36:27', 'Drop-off at collection point', 'Pending', NULL, 'Drop-off'),
(7, 10, '2026-10-21 09:47:46', 'Ampang, 41200', 'Pending', NULL, 'Collection'),
(8, 11, '2026-10-21 09:52:05', 'Drop-off at collection point', 'Pending', NULL, 'Drop-off');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL CHECK (`role` in ('donor','organization','admin')),
  `adress` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `admin_email` (`admin_email`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`donation_id`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`donor_id`),
  ADD UNIQUE KEY `donor_email` (`donor_email`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`org_id`);

--
-- Indexes for table `pickups`
--
ALTER TABLE `pickups`
  ADD PRIMARY KEY (`pickup_id`),
  ADD KEY `fk_pickups_donation` (`donation_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `donation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `donor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pickups`
--
ALTER TABLE `pickups`
  MODIFY `pickup_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `pickups`
--
ALTER TABLE `pickups`
  ADD CONSTRAINT `fk_pickups_donation` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`donation_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
