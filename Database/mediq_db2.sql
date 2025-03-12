-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 09, 2025 at 04:38 PM
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
-- Database: `mediq_db2`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('pending','approved','disabled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT 'default.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `nic`, `name`, `email`, `mobile`, `password`, `status`, `created_at`, `profile_picture`) VALUES
(1, '200202202615', 'Malitha', 'malithatishamal@gmail.com', '785530992', '$2y$10$e3yU/.35yCf9ZbkWhUHm8u9IkKvyaO3ZuO/0K2ALLHa/JRWR.5asm', 'approved', '2025-02-10 12:21:20', '67c28f96e5acd-malitha3.jpg'),
(8, '200202226777', 'admin user', 'admin@gmail.com', '710000000', '$2y$10$FWwvXaoYAFTWI0hrO0RpAOV6eN3qN0PX2nGQy9h/qCsDwNiDutcgm', 'approved', '2025-02-15 22:38:05', 'default.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `antibiotics`
--

CREATE TABLE `antibiotics` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `antibiotics`
--

INSERT INTO `antibiotics` (`id`, `name`) VALUES
(1, 'Amikacin'),
(2, 'Amoxicillin'),
(3, 'Amoxicillin/clavulanic-acid (Co-amoxiclav)'),
(4, 'Ampicillin'),
(5, 'Azithromycin'),
(6, 'Benzathine penicillin'),
(7, 'Benzylpenicillin'),
(8, 'Cefalexin'),
(9, 'Cefepime'),
(10, 'Cefixime'),
(11, 'Cefotaxime'),
(12, 'Ceftazidime'),
(13, 'Ceftriaxone'),
(14, 'Cefuroxime'),
(22, 'Flucloxacillin'),
(23, 'Gentamicin'),
(24, 'Imipenem/cilastatin'),
(25, 'Levofloxacin'),
(26, 'Linezolid'),
(27, 'Meropenem'),
(28, 'Metronidazole'),
(29, 'Nitrofurantoin'),
(30, 'Norfloxacin'),
(31, 'Ofloxacin'),
(32, 'Phenoxymethylpenicillin'),
(33, 'Piperacillin/tazobactam'),
(34, 'Sulbactam + Cefoperazone'),
(35, 'Teicoplanin'),
(36, 'Ticarcillin/Clavulan'),
(37, 'Tigecycline'),
(38, 'Vancomycin'),
(39, 'MDT-PB Adult'),
(40, 'MDT-PB Pediatric'),
(41, 'MDT-MB Adult'),
(42, 'MDT-MB Pediatric'),
(43, 'Ciprofloxacin'),
(44, 'Clarithromycin'),
(45, 'Clindamycin'),
(46, 'Clofazimine'),
(47, 'Co-Trimoxazole'),
(48, 'Doxycycline'),
(49, 'Erythromycin');

-- --------------------------------------------------------

--
-- Table structure for table `dosages`
--

CREATE TABLE `dosages` (
  `id` int(11) NOT NULL,
  `antibiotic_id` int(11) DEFAULT NULL,
  `dosage` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dosages`
--

INSERT INTO `dosages` (`id`, `antibiotic_id`, `dosage`) VALUES
(1, 1, '500 mg IV'),
(2, 2, '250 mg Oral'),
(3, 2, '500 mg Oral'),
(4, 2, '125 mg/5 ml Syrup'),
(5, 2, '125 mg Oral'),
(6, 3, '0.51 g IV'),
(7, 3, '1.2 g IV'),
(8, 3, '375 mg Oral'),
(9, 3, '625 mg Oral'),
(10, 3, '125 mg/31 mg/5 ml, 100 ml Syrup'),
(11, 4, '1 g IV'),
(12, 4, '250 mg IV'),
(13, 5, '250 mg Oral'),
(14, 5, '500 mg Oral'),
(15, 5, '200 mg/5 ml, 15 ml Syrup'),
(16, 6, '1.2 million units IV'),
(17, 7, '1 million units IV'),
(18, 8, '250 mg Oral'),
(19, 8, '125 mg (dispersible tab.) Oral'),
(20, 8, '125 mg/5 ml Syrup'),
(21, 9, '1 g IV'),
(22, 10, '200 mg Oral'),
(23, 11, '1 g IV'),
(24, 11, '500 mg IV'),
(25, 12, '1 g IV'),
(26, 13, '1 g IV'),
(27, 14, '500 mg Oral'),
(28, 14, '125 mg/5 ml Syrup'),
(29, 14, '500 mg Oral'),
(30, 14, '750 mg IV'),
(31, 22, '250 mg Oral'),
(32, 22, '500 mg Oral'),
(33, 22, '125 mg/5 ml, 100 ml Syrup'),
(34, 22, '500 mg IV'),
(35, 23, '80 mg/2 ml IV'),
(36, 24, '500 mg/500 mg IV'),
(37, 25, '500 mg Oral'),
(38, 25, '500 mg IV'),
(39, 26, '600 mg Oral'),
(40, 26, '600 mg IV'),
(41, 27, '1 g IV'),
(42, 28, '500 mg IV'),
(43, 28, '400 mg Oral'),
(44, 28, '200 mg Oral'),
(45, 29, '50 mg Oral'),
(46, 30, '400 mg Oral'),
(47, 31, '200 mg Oral'),
(48, 32, '250 mg Oral'),
(49, 33, '4.5 g IV'),
(50, 34, '2 g IV'),
(51, 35, '400 mg IV'),
(52, 36, '3 g IV'),
(53, 37, '500 mg IV'),
(54, 38, '1 g IV'),
(55, 39, ''),
(56, 40, ''),
(57, 41, ''),
(58, 42, ''),
(59, 43, '250 mg  oral'),
(60, 43, '500 mg  oral'),
(61, 43, '200 mg  iv'),
(62, 44, '250 mg  oral'),
(63, 44, '500 mg  oral'),
(64, 44, '125 mg/5 ml, 100 ml  Syrup'),
(65, 44, '500 mg   Iv'),
(66, 45, '150 mg  oral'),
(67, 45, '300 mg  oral'),
(68, 45, '300 mg  Iv'),
(69, 46, '50 mg  oral'),
(70, 46, '100 mg  oral'),
(71, 47, '480 mg  oral'),
(72, 47, '50 mg  Syrup'),
(73, 48, '100 mg  oral'),
(74, 49, '250 mg  oral'),
(75, 49, '125 mg/5 ml, 100 ml (oral suspension)  Syrup');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expire_time` int(11) NOT NULL,
  `role` enum('user','admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset`
--

INSERT INTO `password_reset` (`id`, `user_id`, `token`, `expire_time`, `role`) VALUES
(3, 43, '0f744d12a3df92db5f38f05cf071b111676994b093a24816c6629539685ed94e7c7e5b71063f594ec1c83999264e5d58c89f', 1740762901, 'user');

-- --------------------------------------------------------

--
-- Table structure for table `releases`
--

CREATE TABLE `releases` (
  `id` int(11) NOT NULL,
  `antibiotic_name` varchar(255) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `item_count` int(11) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `release_time` datetime NOT NULL,
  `type` enum('msd','lp') NOT NULL,
  `ant_type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `releases`
--

INSERT INTO `releases` (`id`, `antibiotic_name`, `dosage`, `item_count`, `ward_name`, `release_time`, `type`, `ant_type`) VALUES
(1, 'Amikacin', '500 mg IV', 100, '3 - Surgical Prof - Female', '2025-03-08 13:05:21', 'msd', 'oral'),
(2, 'Co-Trimoxazole', '600 mg Oral', 100, '1 & 2 - Pediatrics - Combined', '2025-03-08 13:42:14', 'msd', 'oral'),
(3, 'Vancomycin', '1 g IV', 12, '5 - Surgical Prof - Male', '2025-03-08 13:42:49', 'msd', 'oral');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nic` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('pending','approved','disabled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT 'default.jpg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nic`, `name`, `email`, `mobile`, `password`, `status`, `created_at`, `profile_picture`) VALUES
(43, '200202202615', 'malitha', 'malithatishamal2002@gmail.com', '713223952', '$2y$10$kfHNLivdLEwJ13NoA2PmaODOHZdeTzXm3lIL8DC./TCVJDUPjnuDK', 'approved', '2025-02-10 18:08:22', 'default.jpg'),
(45, '200202226299', 'user test', 'user@gmail.com', '712222222', '$2y$10$VsfVH9VG3RWyLRBulY6Tr.MKVEMzlUI16kzHTB22LySBaaLuoqJDW', 'approved', '2025-02-15 22:37:20', 'default.jpg'),
(46, '200202222625', 'test', 'demo3@gmail.com', '771000000', '$2y$10$IIjq.h0RCLc2ytCT.MhdpuRFxjhmDFKBeJUzh62/dgZMVbtzi6WgO', 'pending', '2025-03-06 15:26:52', 'default.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `ward`
--

CREATE TABLE `ward` (
  `id` int(11) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `team` varchar(255) NOT NULL,
  `managed_by` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ward`
--

INSERT INTO `ward` (`id`, `ward_name`, `team`, `managed_by`, `description`, `created_at`) VALUES
(1, '1 & 2 - Pediatrics - Combined', 'Team 1', 'Dr. Jayantha', '', '2025-03-08 12:03:36'),
(3, '3 - Surgical Prof - Female', 'Team 2', '', '', '2025-03-08 12:03:36'),
(4, '4 - Surgery - Male', 'Team 3', 'Dr. Nalitha Wijesundara', '', '2025-03-08 12:03:36'),
(5, '5 - Surgical Prof - Male', 'Team 2', '', '', '2025-03-08 12:03:36'),
(6, '6 - Surgery - Combined', 'Team 4', 'Dr. Sudheera Herath', '', '2025-03-08 12:03:36'),
(7, '7 - Surgical Prof - Female', 'Team 2', '', '', '2025-03-08 12:03:36'),
(8, '8 - Neuro-Surgery - Female', 'Team 5', 'Dr. Yohan Koralage, Dr. Nishantha Gunasekara', '', '2025-03-08 12:03:36'),
(9, '9 - Surgery - Combined', 'Team 8', 'Dr. Seewali Thilakarathna', '', '2025-03-08 12:03:36'),
(10, '10 - Surgery', 'Team 6', 'Dr. Lelwala', '', '2025-03-08 12:03:36'),
(11, '11 - Medicine Prof - Female', 'Team 9', '', '', '2025-03-08 12:03:36'),
(12, '12 - Medicine Prof - Male', 'Team 9', '', '', '2025-03-08 12:03:36'),
(14, '14 - Medicine - Male', 'Team 10', 'Dr. P.A Jayasinghe', '', '2025-03-08 12:03:36'),
(15, '15 - Medicine - Female', 'Team 10', '', '', '2025-03-08 12:03:36'),
(16, '16 - Medicine - Male', 'Team 11', 'Dr. Uluwatta', '', '2025-03-08 12:03:36'),
(17, '17 - Medicine - Female', 'Team 11', '', '', '2025-03-08 12:03:36'),
(18, '18 - Psychiatry - Male', 'Team 12', 'Dr. Rubi Ruben', '', '2025-03-08 12:03:36'),
(19, '19 - Medicine - Male', 'Team 13', 'Dr. Arosha Abeywickrama', '', '2025-03-08 12:03:36'),
(20, '20 - Orthopedic - Female', 'Team 14', 'Dr. Harsha Mendis, Dr. Jayasekara', '', '2025-03-08 12:03:36'),
(21, '21 - Medicine - Female', 'Team 13', '', '', '2025-03-08 12:03:36'),
(22, '22 - Orthopedic - Male', 'Team 14', '', '', '2025-03-08 12:03:36'),
(23, '23 - Psychiatry - Female', 'Team 12', '', '', '2025-03-08 12:03:36'),
(24, '24 - Neurology - Combined', 'Team 15', 'Dr. Mohidin', '', '2025-03-08 12:03:36'),
(25, '25 - Dermatology - Female', 'Team 17', 'Dr. Kapila, Dr. Binari', '', '2025-03-08 12:03:36'),
(26, '26 - Oro-Maxillary Facial - Combined', 'Team 16', '', '', '2025-03-08 12:03:36'),
(27, '27 - Dermatology - Male', 'Team 17', '', '', '2025-03-08 12:03:36'),
(28, '28 - Oncology - Male', 'Team 18', 'Dr. Jayamini Horadugoda', '', '2025-03-08 12:03:36'),
(29, '29 - Oncology - Female', 'Team 18', '', '', '2025-03-08 12:03:36'),
(30, '30 - ENT - Male', 'Team 19', 'Dr. Welendawa, Dr. Wickramasinghe', '', '2025-03-08 12:03:36'),
(31, '31 - ENT - Female', 'Team 19', '', '', '2025-03-08 12:03:36'),
(32, '32 - Ophthalmology - Female', 'Team 20', 'Dr. Hemamali, Dr. Lalitha', '', '2025-03-08 12:03:36'),
(33, '33 - Ophthalmology - Male', 'Team 20', '', '', '2025-03-08 12:03:36'),
(34, '34 - Medicine - Male', 'Team 21', 'Dr. Krishantha Jayasekara', '', '2025-03-08 12:03:36'),
(35, '35 - Medicine - Female', 'Team 21', '', '', '2025-03-08 12:03:36'),
(36, '36 - Pediatrics - Combined', 'Team 22', 'Dr. Upeksha Liyanage, Dr. Jagath', '', '2025-03-08 12:03:36'),
(37, '37 - Neuro-Surgery - Male', 'Team 5', 'Dr. Yohan Koralage, Dr. Nishantha Gunasekara', '', '2025-03-08 12:03:36'),
(39, '39 & 40 - Cardiology', 'Team 23', 'Dr. Sadhanandan', '', '2025-03-08 12:03:36'),
(41, '41, 42 & 43 - Maliban Rehabilitation', 'Team 24', '', '', '2025-03-08 12:03:36'),
(44, '44 - Cardio-Thoracic - Female', 'Team 25', 'Dr. Namal', '', '2025-03-08 12:03:36'),
(45, '45 - Cardio-Thoracic - Male', 'Team 25', '', '', '2025-03-08 12:03:36'),
(46, '46 & 47 - GU Surgery - Male', 'Team 26', 'Dr. Sathis, Dr. Dimantha', '', '2025-03-08 12:03:36'),
(48, '48 - Onco-Surgery - Female', 'Team 27', 'Dr. Mahaliyana', '', '2025-03-08 12:03:36'),
(49, '49 - Onco-Surgery - Male', 'Team 27', '', '', '2025-03-08 12:03:36'),
(50, '50 - Pediatric Oncology - Combined', 'Team 28', '', '', '2025-03-08 12:03:36'),
(51, '51 & 52 - Pediatric Surgery', 'Team 29', 'Dr. Janath, Dr. Kasthuri', '', '2025-03-08 12:03:36'),
(53, '53 - Ophthalmology - Male', 'Team 31', 'Dr. Dharmadasa', '', '2025-03-08 12:03:36'),
(54, '54 - Ophthalmology - Female', 'Team 31', '', '', '2025-03-08 12:03:36'),
(55, '55 - Rheumatology - Combined', 'Team 32', 'Dr. S.P Dissanayake, Dr. Kalum Deshapriya', '', '2025-03-08 12:03:36'),
(58, '58 - Emergency/ETC - Male', 'Team 33', '', '', '2025-03-08 12:03:36'),
(59, '59 - Emergency/ETC - Female', 'Team 33', '', '', '2025-03-08 12:03:36'),
(60, '60 - ETC Pead - Combined', 'Team 34', '', '', '2025-03-08 12:03:36'),
(61, '61 & 62 - Bhikku', 'Team 35', '', '', '2025-03-08 12:03:36'),
(65, '65 - Palliative', 'Team 36', 'Dr. Mahaliyana', '', '2025-03-08 12:03:36'),
(67, '67 - Stroke', 'Team 37', 'Dr. Mohondan', '', '2025-03-08 12:03:36'),
(68, '68 & 69 - Respiratory', 'Team 38', '', '', '2025-03-08 12:03:36'),
(70, '70 - Nephrology', 'Team 39', '', '', '2025-03-08 12:03:36'),
(71, '71 - Nephrology - Male', 'Team 39', '', '', '2025-03-08 12:03:36'),
(72, '72 - Vascular Surgery - Combined', 'Team 40', '', '', '2025-03-08 12:03:36'),
(73, '73 - Nephrology - Female', 'Team 39', '', '', '2025-03-08 12:03:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nic` (`nic`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `mobile` (`mobile`);

--
-- Indexes for table `antibiotics`
--
ALTER TABLE `antibiotics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dosages`
--
ALTER TABLE `dosages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `antibiotic_id` (`antibiotic_id`);

--
-- Indexes for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `releases`
--
ALTER TABLE `releases`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nic` (`nic`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `ward`
--
ALTER TABLE `ward`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `antibiotics`
--
ALTER TABLE `antibiotics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `dosages`
--
ALTER TABLE `dosages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `password_reset`
--
ALTER TABLE `password_reset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `releases`
--
ALTER TABLE `releases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `ward`
--
ALTER TABLE `ward`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dosages`
--
ALTER TABLE `dosages`
  ADD CONSTRAINT `dosages_ibfk_1` FOREIGN KEY (`antibiotic_id`) REFERENCES `antibiotics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD CONSTRAINT `password_reset_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
