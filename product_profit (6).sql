-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3308
-- Generation Time: Apr 24, 2025 at 10:32 AM
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
-- Database: `product_profit`
--
CREATE DATABASE IF NOT EXISTS `product_profit` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `product_profit`;

-- --------------------------------------------------------

--
-- Table structure for table `commission_records`
--

CREATE TABLE `commission_records` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `person_name` varchar(255) NOT NULL,
  `commission_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commission_records`
--

INSERT INTO `commission_records` (`id`, `report_id`, `person_name`, `commission_rate`, `commission_amount`, `created_at`) VALUES
(5, 45, 'azri', 5.00, 4938.35, '2025-04-22 15:50:48');

-- --------------------------------------------------------

--
-- Table structure for table `financial_report`
--

CREATE TABLE `financial_report` (
  `id_financial_report` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `ssm_number` varchar(100) DEFAULT NULL,
  `report_month` date DEFAULT NULL,
  `total_sales` decimal(10,2) DEFAULT NULL,
  `roas` decimal(5,2) DEFAULT NULL,
  `net_revenue` decimal(10,2) DEFAULT NULL,
  `ads_cost` decimal(10,2) DEFAULT NULL,
  `direct_cost_cogs` decimal(10,2) DEFAULT NULL,
  `gross_profit` decimal(10,2) DEFAULT NULL,
  `shipping_fee` decimal(10,2) DEFAULT NULL,
  `web_hosting_domain` decimal(10,2) DEFAULT NULL,
  `operating_cost` decimal(10,2) DEFAULT NULL,
  `operating_profit` decimal(10,2) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `operation_cost` decimal(10,2) DEFAULT NULL,
  `wrap_parcel_cost` decimal(10,2) DEFAULT NULL,
  `commission_parcel` decimal(10,2) DEFAULT NULL,
  `training_cost` decimal(10,2) DEFAULT NULL,
  `internet_cost` decimal(10,2) DEFAULT NULL,
  `postpaid_bill` decimal(10,2) DEFAULT NULL,
  `rent` decimal(10,2) DEFAULT NULL,
  `utilities` decimal(10,2) DEFAULT NULL,
  `maintenance_repair` decimal(10,2) DEFAULT NULL,
  `staff_pay_and_claim` decimal(10,2) DEFAULT NULL,
  `other_expenses` decimal(10,2) DEFAULT NULL,
  `total_expenses` decimal(10,2) DEFAULT NULL,
  `net_profit` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending','Paid') DEFAULT 'Pending',
  `commission_rate` decimal(5,2) DEFAULT 10.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `financial_report`
--

INSERT INTO `financial_report` (`id_financial_report`, `team_id`, `company_name`, `ssm_number`, `report_month`, `total_sales`, `roas`, `net_revenue`, `ads_cost`, `direct_cost_cogs`, `gross_profit`, `shipping_fee`, `web_hosting_domain`, `operating_cost`, `operating_profit`, `salary`, `operation_cost`, `wrap_parcel_cost`, `commission_parcel`, `training_cost`, `internet_cost`, `postpaid_bill`, `rent`, `utilities`, `maintenance_repair`, `staff_pay_and_claim`, `other_expenses`, `total_expenses`, `net_profit`, `created_at`, `payment_status`, `commission_rate`) VALUES
(45, 1, '', '', '2025-04-01', 0.00, NULL, 100000.00, 0.00, 1233.00, 98767.00, NULL, NULL, NULL, 98767.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 98767.00, '2025-04-22 07:50:48', 'Pending', 5.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `ads_spend` decimal(10,2) DEFAULT NULL,
  `purchase` int(11) DEFAULT NULL,
  `cpp` decimal(10,2) DEFAULT NULL,
  `unit_sold` int(11) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `item_cost` decimal(10,2) DEFAULT NULL,
  `cod` decimal(10,2) DEFAULT NULL,
  `sales` decimal(10,2) DEFAULT NULL,
  `profit` decimal(10,2) DEFAULT NULL,
  `cogs` decimal(10,2) DEFAULT 0.00,
  `created_at` date DEFAULT curdate(),
  `pakej` varchar(255) DEFAULT NULL,
  `team_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_dna`
--

CREATE TABLE `product_dna` (
  `id` int(11) NOT NULL,
  `winning_product_id` int(11) NOT NULL,
  `suggested_product_name` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_dna`
--

INSERT INTO `product_dna` (`id`, `winning_product_id`, `suggested_product_name`, `reason`, `added_by`, `created_at`) VALUES
(41, 4, 'makana ayam', 'This product is in the same category and has similar characteristics. ', 15, '2025-04-22 07:47:01');

-- --------------------------------------------------------

--
-- Table structure for table `product_proposals`
--

CREATE TABLE `product_proposals` (
  `id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `product_description` text NOT NULL,
  `tiktok_link` varchar(255) DEFAULT NULL,
  `product_image` varchar(255) DEFAULT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `proposed_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_feedback` text DEFAULT NULL,
  `approved_rejected_date` datetime DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_proposals`
--

INSERT INTO `product_proposals` (`id`, `product_name`, `category`, `cost_price`, `selling_price`, `product_description`, `tiktok_link`, `product_image`, `team_id`, `user_id`, `proposed_date`, `status`, `admin_feedback`, `approved_rejected_date`, `admin_id`) VALUES
(14, 'baju sukan', 'Fashion', 1.00, 2.00, 'baju', 'https://www.tiktok.com/@yana_ariey/video/7267789306046074114\" data-video-id=\"7267789306046074114\" style=\"max-width: 605px;min-width: 325px;\" > <section> <a target=\"_blank\" title=\"@yana_ariey\" href=\"https://www.tiktok.com/@yana_ariey?refer=embed\">@yana_ari', 'uploads/proposals/680749404f23c_lOGO-IASME-TRADING-5.png', 3, 11, '2025-04-22 15:46:08', 'approved', 'ok', '2025-04-22 09:46:44', 15);

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `team_id` int(11) NOT NULL,
  `team_name` varchar(50) NOT NULL,
  `team_description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`team_id`, `team_name`, `team_description`) VALUES
(1, 'Team A', 'TEAM A'),
(2, 'Team B', 'TEAM B'),
(3, 'TEAM C', 'TEAM C'),
(4, 'TEAM D', 'TEAM D'),
(16, 'TEAM B 2', 'TEAM B'),
(21, 'TEAM B 3', 'TEAM B');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','team','super_admin') NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `team_id`, `is_admin`) VALUES
(7, 'admin', '$2y$10$XNyMoiKu7GSVwFVnDzkq3.U/lGhf8o/WV62ADlBrVcviA4x0S1GFC', 'admin', NULL, 1),
(10, 'azri', '$2y$10$5kPSodLF39VL3rDXAfuCaOFgOuTxdmy3R.uWce6ZR62.wKtu5ch5.', 'team', 1, 0),
(11, 'kirin', '$2y$10$urThYrkoaCCBuVGyc1TUbe8sU7Z1YjfaK7LcGpbnkgA5hcBzDULT2', 'team', 3, 0),
(12, 'DR', '$2y$10$tPzYke5uPpIEUQgTVPFPVef3bf5XIzmChfk50tB8CA4BU6K6.eiHC', 'super_admin', NULL, 1),
(13, 'farid', '$2y$10$m01FoF78DeThR46V4/ap4elfyybGCtBHst8FlxlN3wPbyzn/Bk4vW', 'team', 2, 0),
(14, 'SYIDAH', '$2y$10$dxTtqqck79gxWcwjL6hpxOQDxgX7NorNJWWz7V4BTrMeYRENpLU3G', 'team', 4, 0),
(15, 'superadmin', '$2y$10$WZ0ZSQxPjvJ0FXqWbzeKT.EiRpfgqGtEbzOpwWdYBaZJbYaQam/mK', 'super_admin', NULL, 1),
(16, 'TEAM B 2', '$2y$10$m6BdX.TY9BsV4o0FbK8Qu.XRnuzp2IWIQ0x1P2ng7VxkkizQ3ndwy', 'team', 16, 0),
(17, 'TEAM B 3', '$2y$10$ChlKKqYj.EvCtS3nnzGZWuZ/N4yNkjkEj8pWv5a2/cD/BfuLuVKDK', 'team', 21, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `commission_records`
--
ALTER TABLE `commission_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `financial_report`
--
ALTER TABLE `financial_report`
  ADD PRIMARY KEY (`id_financial_report`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_dna`
--
ALTER TABLE `product_dna`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_proposals`
--
ALTER TABLE `product_proposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`team_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `team_id` (`team_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `commission_records`
--
ALTER TABLE `commission_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `financial_report`
--
ALTER TABLE `financial_report`
  MODIFY `id_financial_report` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `product_dna`
--
ALTER TABLE `product_dna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `product_proposals`
--
ALTER TABLE `product_proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `team_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `product_proposals`
--
ALTER TABLE `product_proposals`
  ADD CONSTRAINT `product_proposals_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`team_id`),
  ADD CONSTRAINT `product_proposals_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `product_proposals_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
