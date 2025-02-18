-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 18, 2025 at 06:15 AM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `nic`, `name`, `email`, `mobile`, `password`, `status`, `created_at`) VALUES
(1, '200202202615', 'Malitha', 'malithatishamal@gmail.com', '785530992', '$2y$10$e3yU/.35yCf9ZbkWhUHm8u9IkKvyaO3ZuO/0K2ALLHa/JRWR.5asm', 'approved', '2025-02-10 12:21:20'),
(8, '200202226777', 'admin user', 'admin@gmail.com', '710000000', '$2y$10$FWwvXaoYAFTWI0hrO0RpAOV6eN3qN0PX2nGQy9h/qCsDwNiDutcgm', 'approved', '2025-02-15 22:38:05');

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
(17, 'Vancomycin_IV'),
(18, 'Tigecycline'),
(19, 'Teicoplanin'),
(20, 'Phenoxymethylpenicillin'),
(21, 'Ofloxacin'),
(22, 'Norfloxacin'),
(23, 'Nitrofurantoin'),
(24, 'Metronidazole_oral'),
(25, 'Doxycycline'),
(26, 'Co- Trimoxazole'),
(27, 'Clofazimine'),
(28, 'Benzathine penicillin'),
(29, 'Benzylpenicillin'),
(30, 'Cefepime'),
(31, 'Cefixime'),
(32, 'Cefotaxime'),
(33, 'Ceftazidime'),
(34, 'Ceftriaxone'),
(35, 'Amikacin'),
(36, 'Amoxicillin'),
(37, 'Amoxicillin/clavulanic-acid (Coamoxiclav)'),
(38, 'Ampicillin'),
(39, 'Azithromycin'),
(40, 'Cefalexin'),
(41, 'Cefuroxime'),
(42, 'Ciprofloxacin'),
(43, 'Clarithromycin'),
(44, 'Clindamycin'),
(45, 'Erythromycin'),
(46, 'Flucloxacillin'),
(47, 'Gentamicin'),
(48, 'Imipenem/cilastatin'),
(49, 'Levofloxacin'),
(50, 'Linezolid'),
(51, 'Piperacillin/tazobactam'),
(52, 'Sulbactam + Cefoperazone'),
(53, 'Ticarcillin/ Clavulan'),
(54, 'MDT-MB Pediatric'),
(55, 'MDT-MB Adult'),
(56, 'MDT-PB Pediatric'),
(57, 'MDT-PB Adult');

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
(28, 17, '500mg'),
(29, 17, '1g'),
(30, 18, '500 mg (injection)'),
(31, 19, '400 mg (injection)'),
(32, 20, '250 mg'),
(33, 21, '200 mg'),
(34, 22, '400 mg'),
(35, 23, '50 mg'),
(36, 24, '200 mg'),
(37, 24, '400 mg'),
(38, 25, '100 mg'),
(39, 26, '50 ml (oral suspension)'),
(40, 26, '400 mg'),
(41, 27, '50 mg'),
(42, 27, '100 mg'),
(43, 28, '1.2 million units (injection)'),
(44, 29, '1 million units (injection)'),
(45, 30, '1 g (injection)'),
(46, 31, '200 mg'),
(47, 31, '1 g (Injection)'),
(48, 32, '1g (injection)'),
(49, 32, '500 mg(injection)'),
(50, 33, '1g (injection)'),
(51, 34, '1g (injection)'),
(52, 35, '500 mg in 2ml (injection)'),
(53, 36, '250mg'),
(54, 36, '500mg'),
(55, 36, '125 mg/ 5 ml (oral suspension)'),
(56, 36, '125 mg tab (solu.)'),
(57, 37, '375 mg'),
(58, 37, '625 mg'),
(59, 37, '125 mg/31 mg/5 ml, 100 ml (oral suspension)'),
(60, 37, '500 mg/100 mg (injection)'),
(61, 37, '1000 mg/200 mg (injection)'),
(62, 38, '1 g (for injection)'),
(63, 38, '250 mg (for injection)'),
(64, 39, '250 mg'),
(65, 39, '500 mg'),
(66, 39, '200 mg/ 5 ml, 15 ml (suspension)'),
(67, 40, '250 mg'),
(68, 40, '125 mg (dispersible tab.)'),
(69, 40, '125 mg/5 ml, 100 ml (Syr.)'),
(70, 41, '500 mg'),
(71, 41, '750 mg (injection)'),
(72, 41, '125 mg/5 ml in 70 (oral suspension)'),
(73, 42, '250 mg'),
(74, 42, '500 mg'),
(75, 42, '200 mg in 100 ml (IV)'),
(76, 43, '250 mg'),
(77, 43, '500 mg'),
(78, 43, '125 mg/5 ml, 100 ml (oral suspension)'),
(79, 43, '500 mg (vial)'),
(80, 44, '150 mg'),
(81, 44, '300 mg'),
(82, 44, '300 mg/2 ml (injection)'),
(83, 45, '250 mg'),
(84, 45, '125 mg/5 ml, 100 ml (oral suspension)'),
(85, 46, '250 mg'),
(86, 46, '500 mg'),
(87, 46, '125 mg/5 ml, 100 ml (Syrup)'),
(88, 46, '500 mg (Injection)'),
(89, 47, '80 mg/ 2 ml (IV)'),
(90, 48, '500 mg/500 mg (IV)'),
(91, 49, '500 mg'),
(92, 49, '500 mg in 100 ml (injection)'),
(93, 50, '600 mg'),
(94, 50, '2 mg in 300 ml (injection)'),
(95, 51, '4.5 g (injection)'),
(96, 51, '500 mg (injection)'),
(97, 52, '1 g (injection)'),
(98, 52, '2 g (injection)'),
(99, 53, '3 g/ 200 mg (injection)'),
(100, 54, ''),
(101, 55, ''),
(102, 56, ''),
(103, 57, '');

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
  `release_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `releases`
--

INSERT INTO `releases` (`id`, `antibiotic_name`, `dosage`, `item_count`, `ward_name`, `release_time`) VALUES
(28, 'Vancomycin_IV', '500mg', 10, '1 & 2 (Pediatric)', '2025-02-14 17:37:32'),
(29, 'Tigecycline', '500 mg (injection)', 20, '3 & 5 (Surgical prof.)', '2025-02-14 18:18:53'),
(30, 'Phenoxymethylpenicillin', '250mg', 20, '1 & 2 (Pediatric)', '2025-02-14 18:20:53'),
(31, 'Phenoxymethylpenicillin', '250mg', 10, '1 & 2 (Pediatric)', '2025-02-14 18:26:55'),
(32, 'Phenoxymethylpenicillin', '250mg', 1, '1 & 2 (Pediatric)', '2025-02-14 18:27:23'),
(33, 'Vancomycin_IV', '500mg', 10, 'E & F (Theater)', '2025-02-14 19:34:03'),
(34, 'Vancomycin_IV', '500mg', 3, '14 & 15 ( Medicos )', '2025-02-15 05:55:28'),
(35, 'Vancomycin_IV', '500mg', 10, '25 & 27 ( Skin )', '2025-02-15 06:09:18'),
(36, 'Ticarcillin/ Clavulan', '3 g/ 200 mg (injection)', 10, 'Neonate ICU', '2025-02-17 17:56:50'),
(37, 'Gentamicin', '80 mg/ 2 ml (IV)', 80, '8 & 10 (Surgical)', '2025-02-17 17:58:36'),
(38, 'Amoxicillin/clavulanic-acid (Coamoxiclav)', '375 mg', 140, '24 & 26 ( Neuro/Dental )', '2025-02-17 18:00:31'),
(39, 'Imipenem/cilastatin', '500 mg/500 mg (IV)', 150, '46 & 47 (GU)', '2025-02-17 18:06:22');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nic`, `name`, `email`, `mobile`, `password`, `status`, `created_at`) VALUES
(43, '200202202615', 'malitha', 'malithatishamal2002@gmail.com', '713223952', '$2y$10$kfHNLivdLEwJ13NoA2PmaODOHZdeTzXm3lIL8DC./TCVJDUPjnuDK', 'approved', '2025-02-10 18:08:22'),
(45, '200202226299', 'user test', 'user@gmail.com', '712222222', '$2y$10$VsfVH9VG3RWyLRBulY6Tr.MKVEMzlUI16kzHTB22LySBaaLuoqJDW', 'approved', '2025-02-15 22:37:20');

-- --------------------------------------------------------

--
-- Table structure for table `ward`
--

CREATE TABLE `ward` (
  `id` int(11) NOT NULL,
  `ward_name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ward`
--

INSERT INTO `ward` (`id`, `ward_name`, `description`, `created_at`) VALUES
(1, '1 & 2 (Pediatric)', '', '2025-02-14 15:58:12'),
(2, '3 & 5 (Surgical prof.)', '', '2025-02-14 15:59:06'),
(3, '4 & 6 (Surgical)', '', '2025-02-14 15:59:36'),
(4, '7 & 9 (Surgical)', '', '2025-02-14 15:59:51'),
(5, '8 & 10 (Surgical)', '', '2025-02-14 15:59:59'),
(6, '11 & 12 ( Prof )', '', '2025-02-14 18:17:00'),
(7, '14 & 15 ( Medicos )', '', '2025-02-14 18:17:42'),
(8, '16 & 17', '', '2025-02-14 18:17:58'),
(9, '18 & 23 (Psychiatric)', '', '2025-02-14 18:18:23'),
(10, '19 & 21 (Medical)', '', '2025-02-14 18:18:54'),
(11, '20 & 22 (Orthopedic)', '', '2025-02-14 18:20:16'),
(12, '24 & 26 ( Neuro/Dental )', '', '2025-02-14 18:20:38'),
(13, '25 & 27 ( Skin )', '', '2025-02-14 18:20:54'),
(14, '28 & 29 ( Cancer )', '', '2025-02-14 18:21:14'),
(15, '30 & 31 ( ENT )', '', '2025-02-14 18:21:32'),
(16, '32 & 33 (Eye)', '', '2025-02-14 18:21:50'),
(17, '34 & 35 (Medical)', '', '2025-02-14 18:22:13'),
(18, '36 (Pediatric)', '', '2025-02-14 18:22:27'),
(19, '37 & 38 (Neuro surgical)', '', '2025-02-14 18:22:43'),
(20, '39 & 40 (Cardiology)', '', '2025-02-14 18:22:59'),
(21, '41, 42 & 43 (Maliban rehabilitation)', '', '2025-02-14 18:23:14'),
(22, '44 & 45 (Cardiothoracic)', '', '2025-02-14 18:23:30'),
(23, '46 & 47 (GU)', '', '2025-02-14 18:23:47'),
(24, '48 & 49 (Onco. surgical)', '', '2025-02-14 18:24:04'),
(25, '50 (Pedi. Onco)', '', '2025-02-14 18:24:20'),
(26, '51 & 52 (Ped. Surgery and GI)', '', '2025-02-14 18:24:39'),
(27, '53, 54 & 55 (Eye and Rheumatology)', '', '2025-02-14 18:24:53'),
(28, '56 & 57 (Onco)', '', '2025-02-14 18:25:13'),
(29, '58, 59 & 60 (ETC, emergency ward)', '', '2025-02-14 18:25:28'),
(30, '61 & 62 (Bikku)', '', '2025-02-14 18:25:41'),
(31, '65 (Palliative)', '', '2025-02-14 18:25:54'),
(32, '67 (stroke)', '', '2025-02-14 18:26:07'),
(33, '68 & 69 (Respiratory)', '', '2025-02-14 18:26:20'),
(34, '68 & 69 (Respiratory)', '', '2025-02-14 18:26:33'),
(35, '72 (vascular)', '', '2025-02-14 18:26:50'),
(36, 'Neonate ICU', '', '2025-02-14 18:27:04'),
(37, 'ETC ICU', '', '2025-02-14 18:27:15'),
(38, 'Main ICU', '', '2025-02-14 18:27:23'),
(39, 'Ped. ICU', '', '2025-02-14 18:27:31'),
(40, 'CTC ICU', '', '2025-02-14 18:27:38'),
(41, 'Onco ICU', '', '2025-02-14 18:27:46'),
(42, 'NSU ICU', '', '2025-02-14 18:27:55'),
(43, 'OT ENF (Theater)', '', '2025-02-14 18:28:20'),
(44, 'ETC (Theater)', '', '2025-02-14 18:28:54'),
(45, 'A & B (Theater)', '', '2025-02-14 18:29:18'),
(46, 'C & D (Theater)', '', '2025-02-14 18:29:34'),
(47, 'E & F (Theater)', '', '2025-02-14 18:29:43'),
(48, 'Onco (Theater)', '', '2025-02-14 18:30:08'),
(49, 'OT CT (Theater)', '', '2025-02-14 18:30:42'),
(54, '70,71,73 & 74 (Nephrology)', '', '2025-02-15 11:07:50');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `dosages`
--
ALTER TABLE `dosages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `releases`
--
ALTER TABLE `releases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `ward`
--
ALTER TABLE `ward`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dosages`
--
ALTER TABLE `dosages`
  ADD CONSTRAINT `dosages_ibfk_1` FOREIGN KEY (`antibiotic_id`) REFERENCES `antibiotics` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
