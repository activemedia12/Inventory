-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 23, 2025 at 09:04 AM
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
-- Database: `inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `delivery_logs`
--

CREATE TABLE `delivery_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `delivery_date` date DEFAULT curdate(),
  `delivered_reams` decimal(10,2) NOT NULL,
  `supplier_name` varchar(50) NOT NULL,
  `amount_per_ream` int(11) NOT NULL,
  `delivery_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_logs`
--

INSERT INTO `delivery_logs` (`id`, `product_id`, `delivery_date`, `delivered_reams`, `supplier_name`, `amount_per_ream`, `delivery_note`) VALUES
(11, 11, '2025-06-23', 100.00, '', 0, ''),
(12, 11, '2025-06-23', 52.00, 'Erine', 150, '');

-- --------------------------------------------------------

--
-- Table structure for table `job_orders`
--

CREATE TABLE `job_orders` (
  `id` int(11) NOT NULL,
  `log_date` date DEFAULT curdate(),
  `client_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `client_address` text NOT NULL,
  `contact_person` varchar(100) NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `project_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `number_of_sets` int(11) DEFAULT NULL,
  `product_size` varchar(10) DEFAULT NULL,
  `paper_size` varchar(50) NOT NULL,
  `custom_paper_size` varchar(50) DEFAULT NULL,
  `paper_type` varchar(50) NOT NULL,
  `copies_per_set` int(11) NOT NULL,
  `serial_range` varchar(255) DEFAULT NULL,
  `binding_type` varchar(50) NOT NULL,
  `custom_binding` varchar(100) DEFAULT NULL,
  `paper_sequence` text NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_orders`
--

INSERT INTO `job_orders` (`id`, `log_date`, `client_name`, `client_address`, `contact_person`, `contact_number`, `project_name`, `quantity`, `number_of_sets`, `product_size`, `paper_size`, `custom_paper_size`, `paper_type`, `copies_per_set`, `serial_range`, `binding_type`, `custom_binding`, `paper_sequence`, `special_instructions`, `created_by`, `created_at`) VALUES
(81, '2025-06-23', 'Erine Enterprises', 'METROPOLIS NORTH SUBD', 'Active Media Designs and Printing', '0447963370', 'Official Receipt', 40, 50, '1/4', 'LONG', '', 'Carbonless', 1, '1501 - 5500', 'Booklet', '', 'Top White', '', 1, '2025-06-23 06:43:50'),
(82, '2025-06-23', 'Erine Enterprises', 'METROPOLIS NORTH SUBD', 'WIZERMINA MENDOZA CRUZ', '0447963370', 'Official Receipt', 40, 50, '1/4', 'LONG', '', 'Carbonless', 1, '1501 - 5500', 'Booklet', '', 'Top White', '', 1, '2025-06-23 06:44:51'),
(83, '2025-06-23', 'Route 95 Diner', 'Guiguinto', 'Mark', '0912345678', 'Official Receipt', 40, 50, '1/2', 'LONG', '', 'Carbonless', 1, '1501 - 5500', 'Booklet', '', 'Top White', '', 1, '2025-06-23 06:55:38');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_type` varchar(100) DEFAULT NULL,
  `product_group` varchar(100) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_type`, `product_group`, `product_name`, `unit_price`) VALUES
(11, 'Carbonless', 'LONG', 'Top White', 150.00);

-- --------------------------------------------------------

--
-- Table structure for table `usage_logs`
--

CREATE TABLE `usage_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `log_date` date DEFAULT curdate(),
  `used_sheets` decimal(10,2) NOT NULL,
  `used_reams` decimal(10,2) DEFAULT NULL,
  `usage_note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usage_logs`
--

INSERT INTO `usage_logs` (`id`, `product_id`, `log_date`, `used_sheets`, `used_reams`, `usage_note`) VALUES
(98, 11, '2025-06-23', 500.00, NULL, 'Auto-deducted from job order for Erine Enterprises'),
(99, 11, '2025-06-23', 500.00, NULL, 'Auto-deducted from job order for Erine Enterprises'),
(100, 11, '2025-06-23', 1000.00, NULL, 'Auto-deducted from job order for Route 95 Diner');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'erine', '$2y$10$J8YMB9Ep8wkx2BFKAmQybuT42CMuqROjCALyOMUCW2UEmz64bRk0q', 'employee');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `usage_logs`
--
ALTER TABLE `usage_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `job_orders`
--
ALTER TABLE `job_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `usage_logs`
--
ALTER TABLE `usage_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD CONSTRAINT `delivery_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD CONSTRAINT `job_orders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `usage_logs`
--
ALTER TABLE `usage_logs`
  ADD CONSTRAINT `usage_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
