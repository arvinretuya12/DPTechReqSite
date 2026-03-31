-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 31, 2026 at 01:14 AM
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
-- Database: `tech_requirements_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `requirements`
--

CREATE TABLE `requirements` (
  `id` int(11) NOT NULL,
  `merchant_id` varchar(50) NOT NULL,
  `tbo_pay_scrn` varchar(255) DEFAULT NULL,
  `tbo_pay_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `tbo_return_scrn` varchar(255) DEFAULT NULL,
  `tbo_return_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `otc_pay_scrn` varchar(255) DEFAULT NULL,
  `otc_pay_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `otc_return_scrn` varchar(255) DEFAULT NULL,
  `otc_return_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `otc_admin1_scrn` varchar(255) DEFAULT NULL,
  `otc_admin1_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `otc_admin2_scrn` varchar(255) DEFAULT NULL,
  `otc_admin2_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `postback_url` varchar(255) DEFAULT NULL,
  `postback_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `return_url` varchar(255) DEFAULT NULL,
  `return_url_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `website_url` varchar(255) DEFAULT NULL,
  `website_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rsa_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `idempotency_status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requirements`
--

INSERT INTO `requirements` (`id`, `merchant_id`, `tbo_pay_scrn`, `tbo_pay_status`, `tbo_return_scrn`, `tbo_return_status`, `otc_pay_scrn`, `otc_pay_status`, `otc_return_scrn`, `otc_return_status`, `otc_admin1_scrn`, `otc_admin1_status`, `otc_admin2_scrn`, `otc_admin2_status`, `postback_url`, `postback_status`, `return_url`, `return_url_status`, `website_url`, `website_status`, `rsa_status`, `idempotency_status`) VALUES
(2, 'TESTMID', 'uploads/1774879010_1.jpg', 'approved', 'uploads/1774881336_2.jpg', 'approved', 'uploads/1774881373_3.jpg', 'approved', 'uploads/1774879010_4.jpg', 'approved', 'uploads/1774879159_5.jpg', 'approved', 'uploads/1774879159_6.jpg', 'approved', 'https://migration-devopsarvin.onrender.com/RSAPostback.php', 'approved', 'https://migration-devopsarvin.onrender.com/return.php', 'approved', 'https://migration-devopsarvin.onrender.com/test.php', 'approved', 'approved', 'approved');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `merchant_id` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('merchant','devops') NOT NULL,
  `email` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`merchant_id`, `username`, `password`, `role`, `email`) VALUES
('DEVOPS01', 'devops_main', 'password123', 'devops', 'devops@dragonpay.com'),
('TESTMID', 'merchant_test', 'password123', 'merchant', 'merchant@test.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `requirements`
--
ALTER TABLE `requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `merchant_id` (`merchant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`merchant_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `requirements`
--
ALTER TABLE `requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `requirements`
--
ALTER TABLE `requirements`
  ADD CONSTRAINT `requirements_ibfk_1` FOREIGN KEY (`merchant_id`) REFERENCES `users` (`merchant_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
