-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2025 at 08:40 AM
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
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `taxpayer_name` varchar(255) NOT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `tax_type` varchar(50) DEFAULT NULL,
  `rdo_code` varchar(100) DEFAULT NULL,
  `client_address` text DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `street` varchar(100) DEFAULT NULL,
  `building_no` varchar(100) DEFAULT NULL,
  `floor_no` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `contact_person` varchar(100) NOT NULL,
  `contact_number` varchar(50) NOT NULL,
  `client_by` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `client_name`, `taxpayer_name`, `tin`, `tax_type`, `rdo_code`, `client_address`, `province`, `city`, `barangay`, `street`, `building_no`, `floor_no`, `zip_code`, `contact_person`, `contact_number`, `client_by`, `created_at`) VALUES
(2, 'JFL Agri-Ventures Supplies', 'Jaime K. Crisostomo ', '', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lucero St., Lucero St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mabolo', 'Lucero St.', 'Lucero St.', '', '3000', 'Maam Hannah Omilan', '09923318932', 'Owned', '2025-07-30 01:53:01'),
(3, 'Evo Riders Club Philippines', 'Third Hernandez', '', 'NONVAT', '', 'Bayugan, Agusan del Sur', 'Agusan del Sur', 'Bayugan', '', '', '', '', '', ' Third da Man/ Tristan', '09774533014', 'Online', '2025-07-31 04:36:00'),
(4, 'Perci\'s Battery and General Merchandise', 'Percival C. Clemente', '244-210-101-00000', 'NON-VAT EXEMPT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mabini St. Purok 3, Brgy. Mojon, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mojon', 'Mabini St. Purok 3', '', '', '3000', 'Percival Clemente', '09233129288', 'WALK-IN', '2025-08-01 02:27:24'),
(5, 'Hailey\'s Haven Nail Salon and Spa - Robinson Malolos', 'MARVIN D. V. VALERIANO', '284-264-259-00001', 'NON-VAT EXEMPT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '3-03317 Robinsons Place, Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Sumapang Matanda', '', '', '3-03317 Robinsons Place', '3000', 'Marvin De Vera', '0448167243', 'Mhel', '2025-08-02 01:15:25'),
(6, 'Athens LPG Trading', 'Michelle Anne R. Vicente', '239-855-555-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '118 M. Crisostomo, RVC Bldg. Fausta Rd., Brgy. San Vicente, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'San Vicente', 'RVC Bldg. Fausta Rd.', '', '118 M. Crisostomo', '3000', 'Michelle Anne Vicente', '0448167243', 'Online', '2025-08-02 01:56:31'),
(7, 'Force Central Field Dist. Inc. - Valenzuela', 'Force Central Field Dist. Inc', '', 'VAT', '', 'Brgy. Bagbaguin, Valenzuela, Metro Manila', 'Metro Manila', 'Valenzuela', 'Bagbaguin', '', '', '', '', 'Ms. Annibeth Espino', '09453879265', 'Online', '2025-08-02 07:21:24'),
(8, 'MZ Pangilinan Wood and Metal Casket', 'Mariella Pangilinan', '447-663-563-00000', 'NON-VAT EXEMPT', '21A - North Pampanga', 'Brgy. Sto.Tomas, Mabalacat, Pampanga, 2000', 'Pampanga', 'Mabalacat', 'Sto.Tomas', '', '', '', '2000', 'Mariella Pangilinan', '09929975410', 'Online', '2025-08-02 07:31:47'),
(9, 'Florida Villas II Longos Homeowners Association Inc.', 'Florida Villas II Longos Homeowners Association Inc.', '416-130-953-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Florida Village, Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', 'Florida Village', '', '', '3000', 'Hector Crisostomo', '9164858933', 'Walk-In', '2025-08-04 01:34:36'),
(10, 'Francisco Gardening Services', 'Gerome G. Francisco', '388-911-896-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Purok 2, Brgy. Kapitangan, Paombong, Bulacan, 3001', 'Bulacan', 'Paombong', 'Kapitangan', 'Purok 2', '', '', '3001', 'Gerome G. Francisco', 'Francisco Gardening Services', 'Walk-In', '2025-08-04 01:42:57'),
(11, 'Family Affair Event Specialist', 'Gilbert E. De Jesus', '106-017-952-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '176 Gorund Floor, Essen Bldg., Brgy. Liang, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Liang', '', 'Essen Bldg.', '176 Gorund Floor ', '3000', '.', '.', 'Walk-In', '2025-08-04 01:49:24'),
(12, 'Seadragon Outdoor Products Trading', 'Rosemarie M. Palacio', '749-298-412-00000', 'NON-VAT EXEMPT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Blas Ople Road, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'Blas Ople Road', '', '', '3000', 'Rosemarie M. Palacio', '0448167243', 'Mhel', '2025-08-04 01:53:07'),
(13, 'F.C. Ladia Diagnostic and Clinical Laboratory', 'Femar C. Ladia', '267-660-920-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mabini St., Brgy. Mojon, Malolos City, Bulacan', 'Bulacan', 'Malolos City', 'Mojon', 'Mabini St.', '', '', '', 'F.C. Ladia Diagnostic and Clinical Laboratory', '09209784264', 'Online', '2025-08-04 01:53:10'),
(14, 'Filipinos in the Former Soviet Republics Inc.', 'Filipinos in the Former Soviet Republics Inc.', '660-908-580-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. Matimbo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Matimbo', '', '', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-04 01:57:14'),
(15, 'Fit-Sports Outlet OPC', 'Fit Sports Outlet OPC', '010-686-663-00001', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lucero St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mabolo', 'Lucero St.', '', '', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-04 02:00:35'),
(16, 'Flamers Realty Inc.', 'Flamers Realty Inc.', '005-315-820-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. Binang 2nd, Bocaue, Bulacan, 3018', 'Bulacan', 'Bocaue', 'Binang 2nd', '', '', '', '3018', 'Osjas', '0448167243', 'Online', '2025-08-04 02:07:19'),
(17, 'F-Stuff Images OPC', 'F-Stuff Images OPC', '759-905-761-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '1934 UP Bliss, Brgy. San Vicente, Quezon City, Metro Manila', 'Metro Manila', 'Quezon City', 'San Vicente', '1934 UP Bliss', '', '', '', 'Rotap ', 'Messenger', 'Online', '2025-08-04 02:12:09'),
(18, 'Fritz Gerald P. Kalaw M.D.', 'Fritz Gerald P. Kalaw ', '479-644-758-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Primero De Junio St., Brgy. Liang, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Liang', 'Primero De Junio St. ', '', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-04 02:18:16'),
(19, '3J Water Haus', 'Erik Joseph P. Gaspar', '220-278-591-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. Tikay, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Tikay', '', '', '', '3000', '.', '.', 'Online', '2025-08-04 02:24:54'),
(20, '360 Degrees Systems Corp.', '360 Degrees Systems Corp.', '007-624-965-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'DRT Highway, Brgy. Tarcan, Baliuag, Bulacan, 3006', 'Bulacan', 'Baliuag', 'Tarcan', 'DRT Highway', '', '', '3006', '.', '.', 'Online', '2025-08-04 02:33:24'),
(21, '168 Global Construction Development Corp.', '168 Global Construction Development Corp.', '010-503-441-00000', 'VAT', '', 'Unit C, Lot 1, Camaro St., Brgy. Fairview, Quezon City, Metro Manila', 'Metro Manila', 'Quezon City', 'Fairview', 'Camaro St.', 'Lot 1', 'Unit C', '', '.', '.', 'Online', '2025-08-04 02:41:05'),
(22, '1st Choice Office Depot', 'Mary Amalou T. Rodriguez', '903-293-324-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Ground Floor, Alumni Bldg, BSU Compound, Brgy. Guinhawa, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Guinhawa', 'BSU Compound', 'Alumni Bldg', 'Ground Floor', '3000', '.', '.', 'Online', '2025-08-04 02:46:48'),
(23, 'A. Dela Cruz Battery Shop', 'Artie G. Dela Cruz', '236-929-413-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'F. Estrella St., Brgy. SanJuan, Malolos City, Bulacan', 'Bulacan', 'Malolos City', 'SanJuan', 'F. Estrella St.', '', '', '', '.', '.', 'Walk-In', '2025-08-04 02:53:26'),
(24, 'Adventures Hub Travel & Tours', 'Nerissa S. Caparas', '267-325-865-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '1/F, Sto. Rosario Arba Multi Purpose Coop, Brgy. San Isidro 1, Paombong, Bulacan', 'Bulacan', 'Paombong', 'San Isidro 1', '', 'Sto. Rosario Arba Multi Purpose Coop', '1/F', '', '.', '.', 'Walk-In', '2025-08-04 02:59:31'),
(25, 'A27 BJBS Commercial Unit Leasing', 'Lazaro D. Domingo', '158-526-225-00006', 'NONVAT', '', 'Unit 27, Don A. Roces Ave., Brgy. Laging Handa, Quezon City, Metro Manila', 'Metro Manila', 'Quezon City', 'Laging Handa', 'Don A. Roces Ave.', '', 'Unit 27', '', 'Osjas', '0448167243', 'Online', '2025-08-04 03:09:32'),
(26, 'Active Media Designs and Printing', 'Wizermina C. Lumbad', '188-744-661-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '30-C, RVC Bldg., Fausta Rd., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mabolo', 'Fausta Rd.', 'RVC Bldg.', '30-C', '3000', 'Active Media Designs and Printing', '09987916018', 'Owned', '2025-08-04 03:17:55'),
(27, 'Agnes & James Private Resort', 'Agustina C. Coholan', '209-155-596-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Purok 5 Tuklas Rd., Brgy. Santor, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Santor', 'Purok 5 Tuklas Rd.', '', '', '3000', 'Agustina C. Coholan', '09171328659', 'Walk-In', '2025-08-04 03:22:28'),
(28, 'Atty. Julian Marvin Viola Duba', 'Julian Marvin Viola Duba', '323-941-530-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Dr. Peralta St., Brgy. Guinhawa, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Guinhawa', 'Dr. Peralta St.', '', '', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-04 03:31:36'),
(29, 'AU1 Water Refilling Station', 'Ferdinand C. Trajano', '199-749-689-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Soliman St., Brgy. Caingin, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Caingin', 'Soliman St.', '', '', '3000', 'Ferdinand C. Trajano', '\'09692685139', 'Online', '2025-08-04 03:36:28'),
(30, 'Auditsap OPC', 'Auditsap OPC', '612-044-344-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '6th St. Desta Homes Subd., Brgy. Atlag, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Atlag', '6th St. Desta Homes Subd.', '', '', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-04 03:45:41'),
(31, 'Autumn\'s Laundry Shop', 'Maria Luisa D.L. Badua', '152-249-566-00001', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Corner Lambingan St., Brgy. Dampol, Pulilan, Bulacan', 'Bulacan', 'Pulilan', 'Dampol', 'Corner Lambingan St. ', '', '', '', 'Maria Luisa D.L. Badua', '09994220786', 'Walk-In', '2025-08-04 03:50:30'),
(32, 'Azares Car Care Center', 'Alvin S. Azares', '257-482-991-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Km 51 Mc Arthur Highway, Brgy. Gatbuca, Calumpit, Bulacan, 3003', 'Bulacan', 'Calumpit', 'Gatbuca', 'Km 51 Mc Arthur Highway', '', '', '3003', 'Alvin S. Azares', '09998805097', 'Walk-In', '2025-08-04 03:53:57'),
(33, 'Arvil T. Meña-Software Engineer', 'Arvil T. Mena', '336-920-443-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Gitna St., Brgy. Matimbo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Matimbo', 'Gitna St.', '', '', '3000', 'Arvil T. Mena', '09273836459', 'Walk-In', '2025-08-04 04:01:09'),
(34, 'Apolinar R. Santiago Leasing', 'Apolinar R. Santiago', '251-065-027-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mc Arthur Highway, Brgy. Binang, Bocaue, Bulacan', 'Bulacan', 'Bocaue', 'Binang', 'Mc Arthur Highway', '', '', '', 'Osjas', '0448167243', 'Online', '2025-08-04 05:15:15'),
(35, 'Arctic-Forest Products Inc.', 'Arctic-Forest Products Inc.', '007-050-982-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Warehouse 11A, Ilang-Ilang St., Brgy. Tabang, Guiguinto, Bulacan', 'Bulacan', 'Guiguinto', 'Tabang', 'Ilang-Ilang St.', 'Warehouse 11A', '', '', 'Beng Treyes', 'beng.treyes@arcticfp.com', 'Online', '2025-08-04 05:22:24'),
(36, 'Adeling\'s Homemade Native Delicacies Store', 'Archie P. Bianes', '495-296-780-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mc Arthur Highway, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'Mc Arthur Highway', '', '', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-04 05:26:10'),
(37, 'Adeling\'s Homemade Native Delicacies Store', 'Archie P. Bianes', '495-296-780-00001', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Ground Floor, Waltermart Mall, Brgy. Ilang-Ilang, Guiguinto, Bulacan, 3000', 'Bulacan', 'Guiguinto', 'Ilang-Ilang', '', 'Waltermart Mall', 'Ground Floor', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-04 05:33:28'),
(38, 'Adeling\'s Homemade Native Delicacies Store', 'Archie P. Bianes', '495-296-780-00002', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Ground Floor Kiosk 19, Waltermart Mall, Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', '', 'Waltermart Mall', 'Ground Floor Kiosk 19', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-04 05:35:28'),
(39, 'Sta. Isabel Trading', 'Ivan Nikolai C. Reyes', '403-062-433-00000', 'NONVAT', '015 - Naguilian, Isabela', 'Purok 6, Brgy. Sillawit, Cauayan, Isabela, 3305', 'Isabela', 'Cauayan', 'Sillawit', 'Purok 6', '', '', '3305', 'Maam Hannah', '09923318932', 'Online', '2025-08-04 05:48:20'),
(40, 'AI & IT Solutions Inc.', 'AI & IT Solutions Inc.', '646-525-768-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Purok 3 Campupot St., Brgy. Dakila, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Dakila', 'Purok 3 Campupot St.', '', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-04 05:50:33'),
(41, 'A.L. Music Store & Sports Merchandising Incorporated', 'A.L. Music Store & Sports Merchandising Incorporated', '010-836-124-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lipana Bldg., Brgy. Sta. Rita, Guiguinto, Bulacan', 'Bulacan', 'Guiguinto', 'Sta. Rita', '', 'Lipana Bldg.', '', '', 'Maam Aida', 'Messenger', 'Online', '2025-08-04 05:56:46'),
(42, 'Sta. Isabel Trading', 'Ivan Nikolai C. Reyes', '403-062-433-000', 'VAT', '', 'Brgy. SanFermin, Cauayan, Isabela, 3305', 'Isabela', 'Cauayan', 'SanFermin', '', '', '', '3305', 'Maam Hannah', '0923318932', 'Online', '2025-08-04 06:06:54'),
(43, 'Alano\'s Enterprises Inc.', 'Alano\'s Enterprises Inc.', '670-733-954-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Block 1 Lot 3, Stanley Ville Subd., Brgy. San Agustin, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'San Agustin', 'Stanley Ville Subd.', 'Block 1 Lot 3', '', '3000', 'Maam Aida', 'Messenger', 'Online', '2025-08-04 06:16:39'),
(44, 'Alano\'s Enterprises', 'Antonina R. Alano', '236-265-138-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Block 1 Lot 3, Stanley Ville Subd., Brgy. San Agustin, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'San Agustin', 'Stanley Ville Subd.', 'Block 1 Lot 3', '', '3000', 'Maam Aida', 'Messenger', 'Online', '2025-08-04 08:28:52'),
(45, 'AMA Petron Gas Station', 'Ramon R. Cruz', '147-414-440-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. San Juan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'San Juan', '', '', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-04 08:33:23'),
(46, 'Alpha Architects', 'Casper A. Payongayong', '249-298-205-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mc Arthur Highway Lot 2 A3, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'Mc Arthur Highway Lot 2 A3', '', '', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-04 08:40:20'),
(47, 'Baratillo Tools Plumbing & Electrical Supplies', 'Maria Liza B. Cajucom', '257-585-169-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lucero St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mabolo', 'Lucero St.', '', '', '3000', 'Margie Parohinog', 'Messenger', 'Online', '2025-08-05 01:54:29'),
(48, 'Bon Bon Units for Rent', 'Romualdo A. Pagsibigan', '124-117-817-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'KM 45 Mc Arthur Highway, Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', 'KM 45 Mc Arthur Highway', '', '', '3000', 'Romualdo A. Pagsibigan', '09954014510', 'Walk-In', '2025-08-05 01:57:41'),
(49, 'Bulacan State University Multipurpose Cooperative', 'Bulacan State University Multipurpose Cooperative', '298-609-644-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'BSU Compound, Brgy. Guinhawa, Malolos City, Bulacan', 'Bulacan', 'Malolos City', 'Guinhawa', 'BSU Compound', '', '', '', 'Margie Parohinog', 'Messenger', 'Online', '2025-08-05 02:10:41'),
(50, 'Badua Apartment', 'Marcelo P. Badua', '207-081-616-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lambingan St., Brgy. Dampol, Pulilan, Bulacan', 'Bulacan', 'Pulilan', 'Dampol', 'Lambingan St.', '', '', '', 'Marcelo P. Badua', '09994220786', 'Walk-In', '2025-08-05 02:16:43'),
(51, 'Balithai Spa', 'Milette R. Natividad', '488-481-240-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '3/F, MCF Lifestyle Hub, Mc Arthur Highway, Brgy. Tikay, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Tikay', 'Mc Arthur Highway', 'MCF Lifestyle Hub', '3/F', '3000', 'Jenneffer Todorov', 'Messenger', 'Walk-In', '2025-08-05 02:20:11'),
(52, 'Barley Daily Health & Beauty Products Shop', 'Cherille M. Menor', '470-136-540-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Blk 39 Lot 27, Deca Homes Dinar St., Brgy. Saluysoy, Meycauayan City, Bulacan', 'Bulacan', 'Meycauayan City', 'Saluysoy', 'Deca Homes Dinar St.', 'Blk 39 Lot 27', '', '', 'Cherille M. Menor', 'Messenger', 'Online', '2025-08-05 02:25:47'),
(53, 'Basilevy Electrical & Plumbing Materials Trading', 'Christallyn Ann T. Santos', '241-658-602-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Blas Ople Road Pulo St., Brgy. Bulihan, Malolos City, Bulacan', 'Bulacan', 'Malolos City', 'Bulihan', 'Blas Ople Road Pulo St.', '', '', '', 'Christallyn Ann T. Santos', '\'09051232036', 'Online', '2025-08-05 02:43:59'),
(54, 'BJBC Pharmacy', 'Mary Jane B. Tapang', '209-014-835-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'M. Crisostomo St., Brgy. San Vicente, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'San Vicente', 'M. Crisostomo St.', '', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-05 02:48:54'),
(55, 'Billy S.J. Enterprises', 'Manuelito M. Serpa Juan', '147-266-598-00000', 'VAT', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', 'Brgy. Niugan, Angat, Bulacan', 'Bulacan', 'Angat', 'Niugan', '', '', '', '', 'Manuelito M. Serpa Juan', 'Messenger', 'Online', '2025-08-05 03:43:45'),
(56, 'Brethren In Christ Christian Ministries Inc.', 'Brethren In Christ Christian Ministries Inc.', '223-482-318-00000', 'NONVAT', '', 'Brgy. Apalit Balucuc, San Fernando, Pampanga', 'Pampanga', 'San Fernando', 'Apalit Balucuc', '', '', '', '', 'Angie Tomas', 'Messenger', 'Online', '2025-08-05 03:49:03'),
(57, 'BJBC Pharmacy ', 'Mary Jane B. Tapang', '209-014-835-00002', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'M.H. Del Pilar St. COR J.P. Rizal St., Brgy. Poblacion Sto. Nino, Hagonoy, Bulacan', 'Bulacan', 'Hagonoy', 'Poblacion Sto. Nino', 'M.H. Del Pilar St. COR J.P. Rizal St.', '', '', '', 'Osjas', '0448167243', 'Online', '2025-08-05 05:26:38'),
(58, 'BJBC Pharmacy - 1', 'Mary Jane B. Tapang', '209-014-835-00001', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Valencia COR Crisostomo STS, Brgy. San Vicente, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'San Vicente', 'Valencia COR Crisostomo STS', '', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-05 05:29:46'),
(59, 'Cyber Dreams Computer Center', 'Jun Sebastian Tumalad', '228-916-090-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'New Public Market, Brgy. Sto. Nino, Hagonoy, Bulacan', 'Bulacan', 'Hagonoy', 'Sto. Nino', '', 'New Public Market', '', '', 'Jun Sebastian Tumalad', '\'09333297989', 'Walk-In', '2025-08-05 05:52:07'),
(60, 'Capili Auto Electrical Shop', 'Edgardo T. Capili', '406-048-977-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Purok 1, Brgy. Santisima Trinidad, Malolos City, Bulacan', 'Bulacan', 'Malolos City', 'Santisima Trinidad', 'Purok 1', '', '', '', 'Edgardo T. Capili', 'edgiecapili@gmail.com', 'Walk-In', '2025-08-05 05:58:50'),
(61, 'Castillo Farm Supply - 03', 'Salome Vidanes Castillo', '233-876-669-00003', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mc Arthur Highway, Brgy. Corazon, Calumpit, Bulacan, 3003', 'Bulacan', 'Calumpit', 'Corazon', 'Mc Arthur Highway', '', '', '3003', 'Salome Vidanes Castillo', 'Messenger', 'Walk-In', '2025-08-05 06:02:47'),
(62, 'Malolos Industrial Tools Supply', 'Maria Liza B. Cajucom', '257-585-169-00001', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. SanPablo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'SanPablo', '', '', '', '3000', 'Ma\'am Liza', 'MESSENGER', 'Online', '2025-08-05 06:07:12'),
(63, 'Cedric\'s Agri Supply', 'Ricardo T. Marcelino', '279-328-468-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Purok 4, Brgy. San Pedro, Malolos City, Bulacan', 'Bulacan', 'Malolos City', 'San Pedro', 'Purok 4', '', '', '', 'Maam Aida ', 'Messenger', 'Online', '2025-08-05 06:08:34'),
(64, 'Cherith Spa', 'Cheryl S. Cruz', '417-411-564-00001', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'No. 2, Juliana Square Commercial Bldg., Brgy. Sto. Nino, Paombong, Bulacan', 'Bulacan', 'Paombong', 'Sto. Nino', 'Juliana Square Commercial Bldg.', 'No. 2', '', '', '.', '.', 'Walk-In', '2025-08-05 06:13:48'),
(65, 'Childine Spa', 'Geraldine L. Niog', '162-248-749-00002', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Gov. Halili St., Brgy. Binang 2nd, Bocaue, Bulacan', 'Bulacan', 'Bocaue', 'Binang 2nd', 'Gov. Halili St.', '', '', '', 'Maam Dina', 'Messenger', 'Online', '2025-08-05 06:22:55'),
(66, 'Colored Pixels Printing Services', 'John Benedict A. Alejo', '428-315-746-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '264 A, Maaasahan St., Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Sumapang Matanda', 'Maaasahan St.', '264 A', '', '3000', 'John Benedict A. Alejo', '09950342946', 'Walk-In', '2025-08-05 06:27:40'),
(67, 'CN & J Fast-Foofchain Fast-food chain', 'Cindy P. Abut', '912-599-623-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'L2 A3 Builders Warehouse, Mc Arthur Highway, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'Mc Arthur Highway', 'L2 A3 Builders Warehouse', '', '3000', 'Cindy P. Abut', '09178334303', 'Walk-In', '2025-08-05 06:38:15'),
(68, 'Caniogan Credit & Development Cooperative - Calumpit', 'Caniogan Credit & Development Cooperative ', '001-648-627-00002', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'J.P. Rizal St., Brgy. Poblacion, Calumpit, Bulacan, 3003', 'Bulacan', 'Calumpit', 'Poblacion', 'J.P. Rizal St.', '', '', '3003', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 06:42:53'),
(69, 'Caniogan Credit & Development Cooperative - Guiguinto', 'Caniogan Credit & Development Cooperative ', '001-648-627-00003', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Cagayan Valley Rd., Brgy. Sta. Rita, Guiguinto, Bulacan, 3015', 'Bulacan', 'Guiguinto', 'Sta. Rita', 'Cagayan Valley Rd.', '', '', '3015', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 06:53:20'),
(70, 'Caniogan Credit & Development Cooperative - San Ildefonso', 'Caniogan Credit & Development Cooperative ', '001-648-627-00016', 'NONVAT', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', 'Brgy. San Juan, San Ildefonso, Bulacan, 3010', 'Bulacan', 'San Ildefonso', 'San Juan', '', '', '', '3010', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 06:56:30'),
(71, 'Caniogan Credit & Development Cooperative - SJDM', 'Caniogan Credit & Development Cooperative ', '001-648-627-00014', 'NONVAT', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', 'Brgy. Tungkong Mangga, San Jose del Monte City, Bulacan, 3023', 'Bulacan', 'San Jose del Monte City', 'Tungkong Mangga', '', '', '', '3023', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 06:58:14'),
(72, 'Caniogan Credit & Development Cooperative - Sta. Maria', 'Caniogan Credit & Development Cooperative ', '001-648-627-00013', 'NONVAT', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', 'Corner C. De Jesus St., Brgy. Poblacion, Santa Maria, Bulacan, 3022', 'Bulacan', 'Santa Maria', 'Poblacion', 'Corner C. De Jesus St.', '', '', '3022', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 06:59:51'),
(73, 'Caniogan Credit & Development Cooperative - Plaridel', 'Caniogan Credit & Development Cooperative ', '001-648-627-00005', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '311, Brgy. Banga 1st, Plaridel, Bulacan, 3004', 'Bulacan', 'Plaridel', 'Banga 1st', '', '311', '', '3004', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:07:36'),
(74, 'Caniogan Credit & Development Cooperative - Baliuag', 'Caniogan Credit & Development Cooperative ', '001-648-627-00006', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'J.P. Rizal St., Brgy. San Jose, Baliuag, Bulacan, 3006', 'Bulacan', 'Baliuag', 'San Jose', 'J.P. Rizal St.', '', '', '3006', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:09:13'),
(75, 'Caniogan Credit & Development Cooperative - Main', 'Caniogan Credit & Development Cooperative ', '001-648-627-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lucero St., Brgy. Caniogan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Caniogan', 'Lucero St.', '', '', '3000', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:13:27'),
(76, 'Caniogan Credit & Development Cooperative - Hagonoy', 'Caniogan Credit & Development Cooperative ', '001-648-627-00001', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Purok 8, Brgy. Sto. Nino, Hagonoy, Bulacan, 3002', 'Bulacan', 'Hagonoy', 'Sto. Nino', 'Purok 8', '', '', '3002', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:27:19'),
(77, 'Caniogan Credit & Development Cooperative - Meycauayan', 'Caniogan Credit & Development Cooperative ', '001-648-627-00015', 'NONVAT', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', 'Contreras St., Brgy. Calvario, Meycauayan City, Bulacan, 3020', 'Bulacan', 'Meycauayan City', 'Calvario', 'Contreras St.', '', '', '3020', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:29:40'),
(78, 'Caniogan Credit & Development Cooperative - Apalit', 'Caniogan Credit & Development Cooperative ', '001-648-627-00012', 'NONVAT', '', 'Brgy. San Vicente Apalit, San Fernando, Pampanga, 2016', 'Pampanga', 'San Fernando', 'San Vicente Apalit', '', '', '', '2016', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:32:14'),
(79, 'Caniogan Credit & Development Cooperative - Tuktukan', 'Caniogan Credit & Development Cooperative ', '001-648-627-00004', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mc Arthur Highway, Brgy. Tuktukan, Guiguinto, Bulacan, 3015', 'Bulacan', 'Guiguinto', 'Tuktukan', 'Mc Arthur Highway', '', '', '3015', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:48:37'),
(80, 'Caniogan Creditt & Development Cooperative - Pulilan', 'Caniogan Creditt & Development Cooperative', '001-648-627-00009', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Pulilan Commercial Apt., Brgy. Cutcot, Pulilan, Bulacan, 3003', 'Bulacan', 'Pulilan', 'Cutcot', '', 'Pulilan Commercial Apt.', '', '3003', 'Cecille Dooma', 'Messenger', 'Online', '2025-08-05 07:50:49'),
(81, 'Jocelyn Evangelista Tamayo M.D.', 'Jocelyn E. Tamayo', '216-018-863-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Unit 1, Prime Medical Laboratory Builders Warehouse, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', '', 'Prime Medical Laboratory Builders Warehouse', 'Unit 1', '3000', 'Tina', 'Messenger', 'Online', '2025-08-06 00:24:15'),
(82, 'Daily-Smiles Dental Clinic', 'Gella Jane D. Serenilla', '489-266-283-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Unit 1 G/F, Rocka Drive, Brgy. Pio Cruzcosa, Calumpit, Bulacan, 3003', 'Bulacan', 'Calumpit', 'Pio Cruzcosa', 'Rocka Drive', '', 'Unit 1 G/F', '3003', 'Osjas', '0448167243', 'Online', '2025-08-06 00:34:27'),
(83, 'Danlled P. Javier - Virtual Assistant', 'Danlled P. Javier', '651-210-660-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '1090, Purok 5, Brgy. Look 1st, Malolos City, Bulacan', 'Bulacan', 'Malolos City', 'Look 1st', 'Purok 5', '1090', '', '', ' danlled.javier@gmail.com', '\'09608142226 ', 'Online', '2025-08-06 00:38:46'),
(84, 'DDD Pharmacy Co.', 'DDD Pharmacy Co.', '764-680-887-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Purok 1, Brgy. San Pedro, Hagonoy, Bulacan, 3003', 'Bulacan', 'Hagonoy', 'San Pedro', 'Purok 1', '', '', '3003', 'Osjas', '09256246649', 'Online', '2025-08-06 00:48:20'),
(85, 'Dela Cruz-Badua Dental Clinic', 'Maria Luisa D.L. Badua', '152-249-566-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'COR Lambingan St., Brgy. Dampol, Pulilan, Bulacan, 3005', 'Bulacan', 'Pulilan', 'Dampol', 'COR Lambingan St. ', '', '', '3005', 'marialbadua@gmail.com', '09994220786', 'Walk-In', '2025-08-06 00:56:34'),
(86, 'Doble88 Food Stall', 'Caroline D. Cruz', '477-664-656-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '1 Plaza Building, Mc Arthur Highway, Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Sumapang Matanda', 'Mc Arthur Highway', '1 Plaza Building', '', '3000', 'Ma\'am Aida', 'Messenger', 'Online', '2025-08-06 01:01:54'),
(87, 'Doxa Dave S. Rotap M.D.', 'Doxa Dave S. Rotap', '318-062-375-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Pagsibigan St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mabolo', 'Pagsibigan St.', '', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-06 01:17:14'),
(88, 'Eagle Auto Care Center', 'Alvin C. Manio', '460-957-610-00003', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. Dampol, Pulilan, Bulacan, 3005', 'Bulacan', 'Pulilan', 'Dampol', '', '', '', '3005', '.', '.', 'Walk-In', '2025-08-06 01:30:28'),
(89, 'Eagle Food And Beverage Station ', 'Alvin C. Manio', '460-957-610-00001', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mc Arthur Highway, Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', 'Mc Arthur Highway', '', '', '3000', '.', '.', 'Walk-In', '2025-08-06 01:33:14'),
(90, 'Eamor Charmaine V. Zuñiga - Healthcare Virtual Assistant', 'Eamor Charmaine V. Zuniga', '311-444-952-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '182, Bantayan 2nd, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'Bantayan 2nd', '182', '', '3000', 'Eamor Charmaine V. Zuniga', '09351054280', 'Online', '2025-08-06 01:40:16'),
(91, 'Edcon Cargotrans Inc.', 'Edcon Cargotrans Inc.', '006-821-590-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '1327, Alido Subd. Rosal St., Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'Alido Subd. Rosal St.', '1327', '', '3000', 'Edcon Cargotrans Inc.', '.', 'Online', '2025-08-06 01:42:42'),
(92, 'Edsan Online Store', 'Eduardo S. Santiago', '403-552-100-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '539, Hipolito St., Brgy. Caingin, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Caingin', 'Hipolito St. ', '539', '', '3000', 'Eduardo S. Santiago', '\'09453042577', 'Online', '2025-08-06 01:46:20'),
(93, 'Eldrin Arbe A. Quiñones M.D.', 'Eldrin Arbe A. Quiñones', '725-716-469-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '66, Toribio Vinta St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mabolo', 'Toribio Vinta St.', '66', '', '3000', 'Osjas', '0448167243', 'Online', '2025-08-06 01:51:41'),
(94, 'Eli DC Hardware', 'Kari Ann H. Dela Cruz', '430-488-158-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '115, Camia St., Brgy. San Jose, Plaridel, Bulacan, 3004', 'Bulacan', 'Plaridel', 'San Jose', 'Camia St.', '115', '', '3004', 'Rikmar', 'Messenger', 'Online', '2025-08-06 02:07:47'),
(95, 'Eilamore Ekea Movers OPC', 'Eilamore Ekea Movers OPC', '626-612-616-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '90, Purok 2, Brgy. Iba O\'Este, Calumpit, Bulacan, 3003', 'Bulacan', 'Calumpit', 'Iba O\'Este', 'Purok 2', '90', '', '3003', '.', '.', 'Walk-In', '2025-08-06 02:25:26'),
(96, 'Eglin Realty Corporation', 'Eglin Realty Corporation', '001-335-877-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '346, Cagayan Valley Rd., Brgy. Sta. Rita, Guiguinto, Bulacan, 3015', 'Bulacan', 'Guiguinto', 'Sta. Rita', 'Cagayan Valley Rd.', '346', '', '3015', 'Christian Alvin Ong ', '09175721743 ', 'Online', '2025-08-06 02:27:42'),
(97, 'Einemar Warehousing And Real Estate Property Leasing', 'Malome Bawaan Gomez', '755-108-891-00001', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '673, R.E. Chico St., Brgy. Sto. Cristo, Baliuag, Bulacan, 3006', 'Bulacan', 'Baliuag', 'Sto. Cristo', 'R.E. Chico St.', '673', '', '3006', 'Osjas', '0448167243', 'Online', '2025-08-06 02:31:21'),
(98, 'Einemar Construction Services', 'Ireneo JR. A Escote', '240-274-183-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '673, R.E. Chico St., Baliuag, Bulacan, 3006', 'Bulacan', 'Baliuag', '', 'R.E. Chico St.', '673', '', '3006', 'Osjas', '0448167243', 'Online', '2025-08-06 02:34:06'),
(99, 'Emmanuel R. Santiago Leasing', 'Emmanuel R. Santiago', '251-065-411-00000', 'NONVAT', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', '095, Mac Arthur Highway, Brgy. BiñAng 2nd, Bocaue, Bulacan, 3018', 'Bulacan', 'Bocaue', 'BiñAng 2nd', 'Mac Arthur Highway', '095', '', '3018', 'Osjas', '0448167243', 'Online', '2025-08-06 02:46:34'),
(100, 'EEE/GEE 222 Plumbing Maintenance and Services', 'Edgardo E. Enriquez', '243-134-548-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '59, Kabihasnan St., Brgy. Caniogan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Caniogan', 'Kabihasnan St.', '59', '', '3000', 'Nita', '09772308886', 'Walk-In', '2025-08-06 02:49:31'),
(101, 'ESC SwimmingPool-Design&Build Construction Services', 'Emilio S. Clemente', '276-331-232-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '24, Calizon St., Brgy. Sto. Rosario, Paombong, Bulacan', 'Bulacan', 'Paombong', 'Sto. Rosario', 'Calizon St.', '24', '', '', 'Osjas', '0448167243', 'Online', '2025-08-06 02:53:14'),
(102, 'Easy Steps Learning School of Malolos Inc.', 'Easy Steps Learning School of Malolos Inc.', '274-172-282-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '565, Buendia St., Brgy. Sto. Rosario, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Sto. Rosario', 'Buendia St. ', '565', '', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-06 03:05:59'),
(103, 'E3M Elite Resources OPC', 'E3M Elite Resources OPC', '651-785-982-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '673, RE Chico St., Brgy. Sto. Cristo, Baliuag, Bulacan, 3006', 'Bulacan', 'Baliuag', 'Sto. Cristo', 'RE Chico St.', '673', '', '3006', 'Osjas', '0448167243', 'Online', '2025-08-06 03:12:07'),
(104, 'Espiritu\'s Dry Goods Store', 'Lucita S. Espiritu', '114-250-452-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '2F, #13, New Hagonoy Public Market, Brgy. San Sebastian, Hagonoy, Bulacan, 3002', 'Bulacan', 'Hagonoy', 'San Sebastian', 'New Hagonoy Public Market ', '#13', '2F', '3002', 'ese261957@gmail.com', '9098438032', 'Online', '2025-08-06 03:17:19'),
(105, 'Ezsipnayan Learning Center', 'Ellain A. Mansilungan', '306-059-609-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Unit-B, Socorro St., Brgy. San Pablo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'San Pablo', 'Socorro St.', 'Unit-B', '', '3000', 'ezsipnayan@gmail.com', '09275901745', 'Walk-In', '2025-08-06 03:21:44'),
(106, 'Evelyn Santiago Pagsibigan D.M.D', 'Evelyn Santiago Pagsibigan', '414-708-298-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '642, KM 44 McArthur Highway, Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', 'KM 44 McArthur Highway', '642', '', '3000', '.', '.', 'Walk-In', '2025-08-06 03:30:48'),
(107, 'Family Affair Event Specialist', 'Gilbert E. De Jesus', '106-017-952-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Ground Floor, 176 Essen Bldg., Brgy. Liang, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Liang', '', '176 Essen Bldg.', 'Ground Floor', '3000', '.', '.', 'Walk-In', '2025-08-06 03:43:11'),
(108, 'F.C. Ladia Diagnostic and Clinical Laboratory', 'Femar C. Ladia', '267-660-920-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Mabini St., Brgy. Mojon, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mojon', 'Mabini St.', '', '', '3000', 'F.C. Ladia Diagnostic and Clinical Laboratory', '09209784264', 'Online', '2025-08-06 03:45:33'),
(109, 'Force Central Field Dist. Inc.', 'Force Central Field Dist. Inc.', '', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Bldg. A 9020, Mc-Arthur Highway, Brgy. Tikay, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Tikay', 'Mc-Arthur Highway', 'Bldg. A 9020', '', '3000', 'Ms. Annibeth Espino', '09453879265', 'Online', '2025-08-06 03:47:53'),
(110, 'Flamers Realty Inc.', 'Flamers Realty Inc.', '005-315-820-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '157, Brgy. Binang 2nd, Bocaue, Bulacan, 3018', 'Bulacan', 'Bocaue', 'Binang 2nd', '', '157', '', '3018', 'Osjas', '0448167243', 'Online', '2025-08-06 03:51:04'),
(111, 'Gmak Construction Corp.', 'Gmak Construction Corporation', '622-677-672-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. Paltok Bunsuran III, Pandi, Bulacan, 3014', 'Bulacan', 'Pandi', 'Paltok Bunsuran III', '', '', '', '3014', '.', '.', 'Online', '2025-08-06 05:08:59'),
(112, 'GMT Engineering Services', 'Melanie P. Capuz', '207-781-106-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '8888, Masagana 3rd St. Phase 3, Brgy. Tabang, Guiguinto, Bulacan, 3015', 'Bulacan', 'Guiguinto', 'Tabang', 'Masagana 3rd St. Phase 3', '8888', '', '3015', 'Melanie P. Capuz', '09437094697', 'Walk-In', '2025-08-06 05:22:05'),
(113, 'Goldenhorse Dry Goods Trading ', 'Mary Joy M. Briones', '300-083-041-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lot 2 Phase 5 Block 111, Dream Crest Subd. Sherwood St., Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', 'Dream Crest Subd. Sherwood St. ', 'Lot 2 Phase 5 Block 111', '', '3000', 'Mary Joy M. Briones', '\'09663200305', 'Walk-In', '2025-08-06 05:31:35'),
(114, 'Goldbridge Property Leasing OPC', 'Goldbridge Property Leasing OPC', '643-349-823-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Brgy. Bulihan, Plaridel, Bulacan, 3004', 'Bulacan', 'Plaridel', 'Bulihan', '', '', '', '3004', 'Osjas', '0448167243', 'Online', '2025-08-06 05:35:34'),
(115, 'Goldentaste Food Kiosk', 'Mary Joy M. Briones', '300-083-041-00001', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'South Supermarket, McArthur Highway, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'McArthur Highway', 'South Supermarket ', '', '3000', 'Mary Joy M. Briones', 'Messenger', 'Online', '2025-08-06 05:44:31'),
(116, 'Goldendoble 88 Corporation ', 'Goldendoble 88 Corporation ', '778-707-297-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Unit IIA, Donmar Bldg., Paseo Del Congreso, Brgy. Catmon, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Catmon', 'Paseo Del Congreso ', 'Donmar Bldg.', 'Unit IIA', '3000', '.', '.', 'Walk-In', '2025-08-06 05:54:16'),
(117, 'Golden Tatsu Auction Products Trading', 'Yveta L. Paz', '655-787-324-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Warehouse #4, Tip 2 CMPD, Brgy. Sta. Cruz, Guiguinto, Bulacan, 3015', 'Bulacan', 'Guiguinto', 'Sta. Cruz', 'Tip 2 CMPD ', 'Warehouse #4', '', '3015', 'Ma\'am Aida', 'Messenger', 'Online', '2025-08-06 05:57:33'),
(118, 'Mind Dragon Philippines OPC', 'Mind Dragon Philippines OPC', '769-378-969-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '4F, N4 Bldg., KM 44-45 McArthur Highway Cabanas, Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', 'KM 44-45 McArthur Highway Cabanas ', 'N4 Bldg. ', '4F', '3000', 'Osjas', '0448167243', 'Online', '2025-08-06 06:03:16'),
(119, 'V.B. COLUMNA CONSTRUCTION CORPORATION', 'V.B. COLUMNA CONSTRUCTION CORPORATION', '', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '33 VBC Bldg., Violeta Village Azucena St., Brgy. Sta.Cruz, Guiguinto, Bulacan, 3015', 'Bulacan', 'Guiguinto', 'Sta.Cruz', 'Violeta Village Azucena St.', '33 VBC Bldg.', '', '3015', 'Mica', '+639470220693', 'AMDP', '2025-08-06 06:11:44'),
(120, 'GR8 Lending Corporation', 'GR8 Lending Corporation', '010-788-073-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '2nd Floor, Graman Bldg., Camia St., Brgy. Dakila, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Dakila', 'Camia St.', 'Graman Bldg.', '2nd Floor', '3000', 'Rotap', 'Messenger', 'Online', '2025-08-06 06:12:22'),
(121, 'Gray\'s Clothing Retailing Stall', 'Aicelle Joyce V. Cruz', '341-747-463-00000', 'NONVAT', '', '178, Daan Estacion, Brgy. San Jose, Bulacan, Bulacan', 'Bulacan', 'Bulacan', 'San Jose', 'Daan Estacion', '178', '', '', '.', 'Messenger', 'Online', '2025-08-06 06:15:43'),
(122, 'Grand Royale Subd. Homeowners Association Inc.', 'Grand Royale Subd. Homeowners Association Inc.', '468-862-906-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Phase 2 Block 41, Grand Royale Subd. Magnolia St., Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Bulihan', 'Grand Royale Subd. Magnolia St.', 'Phase 2 Block 41', '', '3000', 'Grand Royale Subd. Homeowners Association Inc.', '09329725095', 'Online', '2025-08-06 06:24:58'),
(123, 'Hailey\'s Haven Nail Salon and Spa - Robinsons Malolos', 'Marvin D.V. Valeriano', '284-264-259-00006', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Lvl 3, Robinson\'s Place Malolos, Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Sumapang Matanda', '', 'Robinson\'s Place Malolos', 'Lvl 3', '3000', 'Osjas ', '0448167243', 'Online', '2025-08-06 06:33:56'),
(124, 'HDS Nail Salon - Main', 'Shane DJ. Enriquez', '903-495-296-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'SM Center Pulilan, Plaridel-Pulilan Diversion Road, Brgy. Sto. Cristo, Pulilan, Bulacan, 3005', 'Bulacan', 'Pulilan', 'Sto. Cristo', 'Plaridel-Pulilan Diversion Road', 'SM Center Pulilan', '', '3005', 'ate Mhel', '0448167243', 'Online', '2025-08-06 06:47:11'),
(125, 'Hailey\'s Haven Nail Salon and Spa - Sta. Maria', 'Marvin D.V. Valeriano', '284-264-259-00003', 'NONVAT', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', '2F, Waltermart Mall, Brgy. Sta. Clara, Santa Maria, Bulacan, 3022', 'Bulacan', 'Santa Maria', 'Sta. Clara', '', 'Waltermart Mall', '2F', '3022', 'Osjas', '0448167243', 'Online', '2025-08-06 06:49:42'),
(126, 'Hailey\'s Haven Nail Salon and Spa - Angeles Pampanga', 'Marvin D.V. Valeriano', '284-264-259-00004', 'NONVAT', '', 'Robinson\'s Place, McArthur Highway, Brgy. Balibago, Angeles, Pampanga, 2009', 'Pampanga', 'Angeles', 'Balibago', 'McArthur Highway', 'Robinson\'s Place ', '', '2009', 'Osjas', '0448167243', 'Online', '2025-08-06 06:53:12'),
(127, 'Hailey\'s Haven Nail Salon and Spa - San Fernando Pampanga', 'Marvin D.V. Valeriano', '284-264-259-00005', 'NONVAT', '', 'Level 1 RS-01129, Robinsons Starmills, Olongapo-Gapan Road, Brgy. San Jose, San Fernando, Pampanga, 3000', 'Pampanga', 'San Fernando', 'San Jose', 'Olongapo-Gapan Road', 'Robinsons Starmills', 'Level 1 RS-01129', '3000', 'Osjas', '0448167243', 'Online', '2025-08-06 06:59:29'),
(128, 'Hailey\'s Haven Nail Salon and Spa - Main', 'Marvin D.V. Valeriano', '284-264-259-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Waltermart Mall, Brgy. Ilang-Ilang, Guiguinto, Bulacan, 3015', 'Bulacan', 'Guiguinto', 'Ilang-Ilang', '', 'Waltermart Mall', '', '3015', 'Osjas', '0448167243', 'Online', '2025-08-06 07:05:15'),
(129, 'Handy Paper Products Inc.', 'Handy Paper Products Inc.', '768-307-647-00000', 'VAT', '', '29-A, Earth St. E & E Compound, Brgy. Parada Dist., Valenzuela, Metro Manila, 1440', 'Metro Manila', 'Valenzuela', 'Parada Dist.', 'Earth St. E & E Compound', '29-A', '', '1440', 'Handy Paper Products Inc.', '09302703918', 'Walk-In', '2025-08-06 07:21:59'),
(130, 'Hukl Consultants OPC', 'Hukl Consultants OPC', '637-139-578-00000', 'VAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '4F, Cabanas N4 Bldg., KMS 44-45 Mc Arthur Highway, Brgy. Longos, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Longos', 'KMS 44-45 Mc Arthur Highway', 'Cabanas N4 Bldg. ', '4F', '3000', '.', '.', 'Walk-In', '2025-08-06 07:27:22'),
(131, 'Bulacan Provincial Hospital (BMC) Multi Purpose Coop', 'Bulacan Provincial Hospital (BMC) Multi Purpose Coop', '005-314-464-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', 'Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', '', '', '', '', '3000', 'Anthony Dan Villegas', '9673682820 ', 'AMDP', '2025-08-06 07:31:34'),
(132, 'Holy Trinity Funeral Services', 'Maria Avegale C. Flores', '903-329-903-00000', 'NONVAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '248, Lucero St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Bulacan', 'Malolos City', 'Mabolo', 'Lucero St.', '248', '', '3000', '.', '.', 'Online', '2025-08-06 07:42:09');

-- --------------------------------------------------------

--
-- Table structure for table `company_customers`
--

CREATE TABLE `company_customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `taxpayer_name` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `barangay` varchar(50) DEFAULT NULL,
  `subd_or_street` varchar(100) DEFAULT NULL,
  `building_or_block` varchar(100) DEFAULT NULL,
  `lot_or_room_no` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_customers`
--

INSERT INTO `company_customers` (`id`, `user_id`, `company_name`, `taxpayer_name`, `contact_person`, `contact_number`, `province`, `city`, `barangay`, `subd_or_street`, `building_or_block`, `lot_or_room_no`, `zip_code`) VALUES
(1, 20, 'Erine Enterprises', '', 'WIZERMINA MENDOZA CRUZ', '09987916018', 'Bulacan', 'Malolos City', '', '30 Fausta Rd., Lucero St, Mabolo', '', '', '3000');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_logs`
--

CREATE TABLE `delivery_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `delivery_date` date DEFAULT curdate(),
  `delivered_reams` decimal(10,2) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `supplier_name` varchar(50) NOT NULL,
  `amount_per_ream` int(11) NOT NULL,
  `delivery_note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_logs`
--

INSERT INTO `delivery_logs` (`id`, `product_id`, `delivery_date`, `delivered_reams`, `unit`, `supplier_name`, `amount_per_ream`, `delivery_note`, `created_by`) VALUES
(31, 29, '2025-01-03', 40.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(32, 29, '2025-01-23', 64.00, NULL, 'International Fine Paper Exchange INC.', 275, '', 12),
(33, 29, '2025-05-10', 40.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(34, 29, '2025-05-08', 40.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(35, 30, '2025-01-23', 40.00, NULL, 'International Fine Paper Exchange INC.', 293, '', 12),
(36, 30, '2025-01-03', 24.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(37, 34, '2025-01-03', 49.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(38, 34, '2025-05-10', 32.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(39, 31, '2025-01-03', 50.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(40, 35, '2025-01-03', 32.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(41, 35, '2025-01-23', 16.00, NULL, 'International Fine Paper Exchange INC.', 274, '', 12),
(43, 35, '2025-05-08', 16.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(44, 32, '2025-01-03', 33.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(45, 32, '2025-01-23', 16.00, NULL, 'International Fine Paper Exchange INC.', 293, '', 12),
(46, 36, '2025-01-03', 34.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(47, 33, '2025-01-03', 44.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(48, 37, '2025-01-03', 12.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(49, 37, '2025-01-23', 16.00, NULL, 'International Fine Paper Exchange INC.', 274, '', 12),
(50, 37, '2025-05-08', 8.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(51, 38, '2025-01-03', 70.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(52, 38, '2025-01-21', 80.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(53, 39, '2025-01-03', 59.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(54, 39, '2025-01-23', 50.00, NULL, 'International Fine Paper Exchange INC.', 237, '', 12),
(55, 44, '2025-01-03', 35.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(56, 41, '2025-01-03', 29.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(57, 41, '2025-01-23', 20.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(58, 43, '2025-01-23', 20.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(59, 42, '2025-01-03', 30.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(60, 45, '2025-01-03', 57.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(61, 40, '2025-01-03', 20.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(62, 40, '2025-01-23', 30.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(63, 46, '2025-01-03', 28.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(64, 46, '2025-01-23', 20.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(65, 47, '2025-01-23', 30.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(66, 47, '2025-02-21', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(67, 47, '2025-05-10', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(68, 48, '2025-01-23', 30.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(69, 48, '2025-02-21', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(70, 48, '2025-05-10', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(71, 51, '2025-01-05', 30.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(72, 51, '2025-02-21', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(73, 51, '2025-05-10', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(74, 49, '2025-01-23', 30.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(75, 49, '2025-05-10', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(76, 50, '2025-05-10', 25.00, NULL, 'International Fine Paper Exchange INC.', 1, '', 12),
(77, 101, '2025-07-09', 40.00, NULL, 'Star Paper Corporation', 276, '', 11),
(78, 96, '2025-07-09', 40.00, NULL, 'Star Paper Corporation', 276, '', 11),
(79, 94, '2025-07-09', 84.00, NULL, 'Star Paper Corporation', 198, '', 11),
(80, 103, '2025-07-09', 16.00, NULL, 'Star Paper Corporation', 352, '', 11),
(81, 104, '2025-07-09', 24.00, NULL, 'Star Paper Corporation', 352, '', 11),
(82, 102, '2025-07-09', 24.00, NULL, 'Star Paper Corporation', 240, '', 11),
(83, 105, '2025-01-10', 2000.00, NULL, 'Star Paper Corporation', 7, '', 11),
(84, 106, '2025-01-10', 6.00, '', 'Star Paper Corporation', 9, '', 11),
(85, 53, '2025-07-04', 10.00, NULL, 'International Fine Paper Exchange INC.', 428, '', 11),
(86, 61, '2025-06-13', 25.00, '', 'Star Paper Corporation', 167, '', 13),
(87, 63, '2025-06-13', 15.00, '', 'Star Paper Corporation', 168, '', 13),
(88, 110, '2025-06-04', 2.00, '', 'Star Paper Corporation', 13, '', 13),
(89, 111, '2025-06-04', 5.00, '', 'Star Paper Corporation', 12, '', 13),
(90, 75, '2025-04-04', 50.00, '', 'Star Paper Corporation', 139, '', 13),
(91, 74, '2025-04-04', 50.00, '', 'Star Paper Corporation', 139, '', 13),
(92, 115, '2025-04-04', 200.00, '', 'Star Paper Corporation', 10, '', 13),
(93, 116, '2025-04-04', 200.00, '', 'Star Paper Corporation', 11, '', 13),
(94, 117, '2025-04-04', 200.00, '', 'Star Paper Corporation', 15, '', 13),
(95, 105, '2025-04-04', 1000.00, 'Sheet', 'Star Paper Corporation', 18, '', 13),
(96, 112, '2025-04-04', 200.00, 'Sheet', 'Star Paper Corporation', 12, '', 13),
(97, 118, '2025-04-04', 100.00, '', 'Star Paper Corporation', 145, '', 13),
(98, 79, '2025-04-04', 25.00, 'Ream', 'Star Paper Corporation', 162, '', 13),
(99, 113, '2025-05-08', 400.00, '', 'Star Paper Corporation', 4, '', 15),
(100, 114, '2025-05-08', 100.00, '', 'Star Paper Corporation', 8, '', 15),
(101, 105, '2025-05-08', 35.00, '', 'Star Paper Corporation', 7, '', 15),
(102, 61, '2025-03-20', 25.00, '', 'Star Paper Corporation', 168, '', 15),
(103, 60, '2025-03-20', 25.00, '', 'Star Paper Corporation', 168, '', 15),
(104, 116, '2025-03-20', 500.00, 'Sheet', 'Star Paper Corporation', 9, '', 15),
(105, 119, '2025-02-20', 200.00, 'Sheet', 'Star Paper Corporation', 8, '', 15),
(106, 120, '2025-02-20', 200.00, 'Sheet', 'Star Paper Corporation', 8, '', 15),
(107, 57, '2025-02-20', 100.00, 'Ream', 'Star Paper Corporation', 182, '', 15),
(108, 68, '2025-02-20', 100.00, 'Ream', 'Star Paper Corporation', 151, '', 15),
(109, 121, '2025-02-20', 1000.00, 'Sheets', 'Star Paper Corporation', 4, '', 15),
(110, 71, '2025-03-07', 150.00, 'Ream', 'Star Paper Corporation', 95, '', 15),
(111, 122, '2025-01-23', 50.00, '', 'Star Paper Corporation', 122, '', 15),
(112, 123, '2025-01-23', 1.00, '', 'Star Paper Corporation', 9, '', 15),
(113, 74, '2025-01-23', 20.00, '', 'Star Paper Corporation', 140, '', 15),
(114, 76, '2025-01-23', 10.00, '', 'Star Paper Corporation', 140, '', 15),
(115, 77, '2025-01-23', 10.00, '', 'Star Paper Corporation', 140, '', 15),
(116, 124, '2025-01-23', 50.00, '', 'Star Paper Corporation', 145, '', 15),
(117, 60, '2025-01-23', 25.00, '', 'Star Paper Corporation', 168, '', 15),
(118, 61, '2025-01-23', 10.00, '', 'Star Paper Corporation', 168, '', 15),
(119, 62, '2025-01-23', 10.00, '', 'Star Paper Corporation', 168, '', 15),
(120, 125, '2025-01-10', 0.60, '', 'Star Paper Corporation', 13, '', 15),
(121, 29, '2025-07-11', 40.00, 'Ream', 'International Fine Paper Exchange INC.', 274, '', 13),
(122, 30, '2025-07-11', 24.00, 'Ream', 'International Fine Paper Exchange INC.', 292, '', 13),
(123, 31, '2025-07-11', 16.00, 'Ream', 'International Fine Paper Exchange INC.', 292, '', 13),
(124, 34, '2025-07-11', 24.00, 'Ream', 'International Fine Paper Exchange INC.', 273, '', 13),
(125, 35, '2025-07-11', 16.00, 'Ream', 'International Fine Paper Exchange INC.', 273, '', 13),
(127, 38, '2025-01-23', 10.00, 'Ream', 'International Fine Paper Exchange INC.', 223, '', 13),
(128, 43, '2025-01-23', 10.00, 'Ream', 'International Fine Paper Exchange INC.', 237, '', 13),
(129, 101, '2025-07-17', 10.00, 'Reams', 'Star Paper Corporation', 283, '', 15);

-- --------------------------------------------------------

--
-- Table structure for table `insuances`
--

CREATE TABLE `insuances` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `date_issued` date DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `insuances`
--

INSERT INTO `insuances` (`id`, `item_name`, `description`, `stock`, `date_issued`, `issued_by`, `created_at`) VALUES
(2, 'Ink Warm Red', 'Offset Warm Red - 1kg', 0, '2025-08-04', 11, '2025-08-04 02:12:01');

-- --------------------------------------------------------

--
-- Table structure for table `insuance_delivery_logs`
--

CREATE TABLE `insuance_delivery_logs` (
  `id` int(11) NOT NULL,
  `insuance_name` varchar(255) NOT NULL,
  `delivered_quantity` float NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `delivery_note` text DEFAULT NULL,
  `delivery_date` date DEFAULT curdate(),
  `supplier_name` varchar(255) DEFAULT NULL,
  `amount_per_unit` decimal(10,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `insuance_delivery_logs`
--

INSERT INTO `insuance_delivery_logs` (`id`, `insuance_name`, `delivered_quantity`, `unit`, `delivery_note`, `delivery_date`, `supplier_name`, `amount_per_unit`, `created_by`, `created_at`) VALUES
(1, 'Ink Warm Red', 1, 'Piece', '', '2025-08-01', 'Star Paper', 453.42, 11, '2025-08-04 02:49:39');

-- --------------------------------------------------------

--
-- Table structure for table `insuance_usages`
--

CREATE TABLE `insuance_usages` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity_used` float NOT NULL,
  `description` text DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `date_issued` date DEFAULT curdate(),
  `used_by_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `insuance_usages`
--

INSERT INTO `insuance_usages` (`id`, `item_id`, `quantity_used`, `description`, `issued_by`, `date_issued`, `used_by_name`) VALUES
(1, 2, 1, 'For Numbering', 11, '2025-08-04', NULL);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `product_id` int(11) DEFAULT NULL,
  `status` enum('pending','unpaid','for_delivery','completed') DEFAULT 'pending',
  `completed_date` datetime DEFAULT NULL,
  `taxpayer_name` varchar(255) DEFAULT NULL,
  `rdo_code` varchar(100) DEFAULT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `client_by` varchar(100) DEFAULT NULL,
  `tax_type` varchar(50) DEFAULT NULL,
  `ocn_number` varchar(100) DEFAULT NULL,
  `date_issued` date DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `street` varchar(100) DEFAULT NULL,
  `building_no` varchar(50) DEFAULT NULL,
  `floor_no` varchar(50) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_orders`
--

INSERT INTO `job_orders` (`id`, `log_date`, `client_name`, `client_address`, `contact_person`, `contact_number`, `project_name`, `quantity`, `number_of_sets`, `product_size`, `paper_size`, `custom_paper_size`, `paper_type`, `copies_per_set`, `serial_range`, `binding_type`, `custom_binding`, `paper_sequence`, `special_instructions`, `created_by`, `created_at`, `product_id`, `status`, `completed_date`, `taxpayer_name`, `rdo_code`, `tin`, `client_by`, `tax_type`, `ocn_number`, `date_issued`, `province`, `city`, `barangay`, `street`, `building_no`, `floor_no`, `zip_code`) VALUES
(129, '2025-01-02', 'RaxNet Trucking Services', 'n/a, 125, Purok 2 Calumpang, Calumpit, Bulacan, 3003', 'n/a', '0923-602-4354', 'Waybill (No Permit)', 20, 50, '1/4', 'LONG', '', 'Carbonless', 2, '001-1000', 'Booklet', '', 'Top White, Bottom Blue', '', 15, '2025-07-11 08:40:34', NULL, 'completed', '2025-07-11 10:39:57', 'RaxNet Trucking Services', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(130, '2025-01-06', 'Oak Tree Marketing', 'N/A, LITES MTKJ BLDG, PASEO DEL CONGRESO, CATMON, Malolos City, Bulacan, 3000', 'Oak Tree Marketing', '0926-959-0360', 'Delivery Receipt 2025', 100, 50, '1/2', 'LONG', '', 'Carbonless', 3, '120251-125250', 'Pad', '', 'Top White, Middle Yellow, Bottom Pink', '', 13, '2025-07-14 00:28:24', NULL, 'completed', '2025-07-15 03:00:05', 'ANGELICA LUZ T. DE LEON', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(131, '2025-01-06', 'Oak Tree Marketing', 'LITES MTKJ BLDG, PASEO DEL CONGRESO, CATMON, Malolos City, Bulacan, 3000', 'Oak Tree Marketing', '09269590360', 'Provisional Receipt', 30, 50, '1/3', 'LONG', '', 'Carbonless', 2, '5601-7100', 'Booklet', '', 'Top White, Bottom Blue', '', 13, '2025-07-14 00:31:29', NULL, 'completed', '2025-07-15 03:00:14', 'ANGELICA LUZ T. DE LEON', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(132, '2025-01-06', 'Oak Tree Marketing', 'LITES MTKJ BLDG, PASEO DEL CONGRESO, CATMON, Malolos City, Bulacan, 3000', 'Oak Tree Marketing', '09269590360', 'Sales Invoice', 20, 50, '1/2', 'LONG', '', 'Carbonless', 2, '1501 - 2500', 'Booklet', '', 'Top White, Bottom Blue', '', 13, '2025-07-14 00:34:00', NULL, 'completed', '2025-07-15 03:00:23', 'ANGELICA LUZ T. DE LEON', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(133, '2025-01-13', 'St. John The Baptist Catholic School Inc.', 'N/A, N/A, J.P. RIZAL ST., POBLACION, Calumpit, Bulacan, 3003', 'St. John The Baptist Catholic School Inc.', '+63 44 792 5738', 'A.R.', 100, 50, '1/2', 'LONG', '', 'Carbonless', 2, '10000-15000', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-14 00:42:41', NULL, 'completed', '2025-07-30 06:59:48', 'St. John The Baptist Catholic School Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(134, '2025-01-15', 'LaPresa Bar and Restaurant', ' ASHDOD COMMERCIAL BUILDING, MAC ARTHUR HIGHWAY, PIO CRUZCOSA, Calumpit, Bulacan, 3003', 'ALEXANDER A. CACO', 'N/A', 'Order Slip', 100, 50, '1/8', 'LONG', '', 'Carbonless', 2, '0001-5000', 'Pad', '', 'Top White, Bottom Green', '', 13, '2025-07-14 00:50:59', NULL, 'completed', '2025-07-15 02:58:04', 'ALEXANDER A. CACO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(135, '2025-01-15', 'Pearl Orient Fruits and Vegetables Trading', 'B6, L8, PH7-B ARUM ST., GRAND ROYALE SUBD., PINAGBAKAHAN, Malolos City, Bulacan, 3000', 'MARJORIE LUISA C. JABAT', 'N/A', 'Sales Invoice', 10, 50, '1/2', 'LONG', '', 'Carbonless', 3, '4501-5000', 'Booklet', '', 'Top White, Middle Pink, Bottom Yellow', '', 13, '2025-07-14 01:56:21', NULL, 'completed', '2025-07-15 03:01:05', 'MARJORIE LUISA C. JABAT', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(136, '2025-01-15', 'Santos Dental Care Center', '2ND FLOOR, 207C, VICTORIA STATION CONDOMINIUM EDSA SOUTH TRIANGLE, Quezon City, Metro Manila, 1103', 'Santos Dental Care Center', '0939 235 7530', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 02:17:04', NULL, 'completed', '2025-07-30 06:58:27', 'MA. SHIELA A. SANTOS', '039 - South Quezon City', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(137, '2025-01-15', 'Tita Lev\'s Water Refilling Station', 'N/A, 179, CAMIA ST., DAKILA, Malolos City, Bulacan, 3000', 'RAMON V. MANALAD JR.', 'N/A', 'Service Invoice', 10, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 02:35:48', NULL, 'completed', '2025-07-14 10:58:42', 'RAMON V. MANALAD JR.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(138, '2025-01-15', 'Eldrin Arbe A. Quiñones, M.D.', 'N/A, 66, TORIBIO VINTA ST. MABOLO, Malolos City, Bulacan, 3000', 'ELDRIN ARBE A. QUIÑONES', 'N/A', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 02:50:47', NULL, 'completed', '2025-07-15 02:27:13', 'ELDRIN ARBE A. QUIÑONES', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(139, '2025-01-15', 'Maricopa KTV ', 'MOLINO 1, Bacoor, Cavite, 4102', 'ALEXANDER A. CACO', 'N/A', 'Order Slip', 100, 50, '1/8', 'LONG', '', 'Carbonless', 2, '0001-5000', 'Pad', '', 'Top White, Bottom Green', '', 13, '2025-07-14 03:09:58', NULL, 'completed', '2025-07-15 02:58:14', 'ALEXANDER A. CACO', '54B - Kawit, West Cavite', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(140, '2025-01-20', 'Juan Vicente F. Aclan, M.D.', '222, JEFF-LITES BLDG., TANJECO ST., SAN VICENTE, Malolos City, Bulacan, 3000', 'Edith Ramos', 'N/A', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Blue', '', 13, '2025-07-14 03:12:52', NULL, 'completed', '2025-07-15 02:36:00', 'JUAN VICENTE F. ACLAN', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(141, '2025-01-23', 'Hailey\'s Haven Nail Salon and Spa - 4', 'LEVEL 2, ROBINSONS PLACE, MAC ARTHUR HIGHWAY, BALIBAGO, Angeles, Pampanga, 2009', 'Nails.Glow Robinsons Angeles ', '09542410998', 'Service Invoice', 50, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-2500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 03:27:23', NULL, 'completed', '2025-07-15 02:35:07', 'MARVIN D. V. VALERIANO', '21A - North Pampanga', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(142, '2025-02-07', 'Medhaus Pharma and Medical Supplies Training', 'N/A, #0926, REYES ST., PUROK 5 CALAPACUAN, Iba, Zambales, 2209', 'ALEX/ROTAP', 'MESSENGER', 'Sales Invoice', 20, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '2501-3500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 03:45:42', NULL, 'completed', '2025-07-15 02:59:17', 'REMIGIO JR. U. TAN', '019 - Subic Bay Freeport Zone', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(143, '2025-02-08', 'MER LOT LEASING', 'N/A, N/A, LUCERO STREET, MABOLO, Malolos City, Bulacan, 3000', 'CHONA M. SANTOS', 'N/A', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 03:50:27', NULL, 'completed', '2025-07-15 02:59:10', 'CHONA M. SANTOS', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(144, '2025-02-12', 'Selah Musical InstrumentsTrading', '2/F, A&P BUILDING, MCARTHUR HIGHWAY, GUINHAWA, Malolos City, Bulacan, 3000', 'ATE MHEL ', '816-7243', 'Sales Invoice', 10, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 05:15:21', NULL, 'completed', '2025-07-30 06:58:56', 'STEPHEN D. CABURNIDA', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(145, '2025-02-14', 'Hailey\'s Haven Nail Salon and Spa - 0', '1ST FLOOR, WALTERMART, ILANG-ILANG, Guiguinto, Bulacan, 3015', 'ATE MHEL ', '816-7243', 'Service Invoice', 50, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-2500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 05:21:34', NULL, 'completed', '2025-07-15 02:33:46', 'MARVIN D. V. VALERIANO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(146, '2025-02-14', 'Caniogan Credit & Development Cooperative - CBO', 'LUCERO ST., 021, CANIOGAN, Malolos City, Bulacan, 3000', 'Cecille Dooma', 'Messenger', 'Provisional Receipt', 240, 50, '1/6', 'LONG', '', 'Carbonless', 2, '1456651-1468650', 'Booklet', '', 'Top White,Bottom Yellow', '', 13, '2025-07-14 05:30:24', NULL, 'completed', '2025-07-15 02:23:59', 'Caniogan Creditt & Development Cooperative', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '001-648-627-00000', 'AMDP', 'EXEMPT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(147, '2025-02-14', 'Malolos Women\'s Ultrasound Center Inc.', 'A MABINI ST., #802, MOJON, Malolos City, Bulacan, 3000', 'Ate Mhel', '816-7243', 'Service Invoice', 30, 50, '1/3', 'LONG', '', 'Carbonless', 3, '001-1500', 'Booklet', '', 'Top White, Middle Pink, Bottom Yellow', '', 13, '2025-07-14 05:49:23', NULL, 'completed', '2025-07-15 02:58:59', 'Malolos Women\'s Ultrasound Center Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(148, '2025-03-04', 'ESC SWIMMINGPOOL-DESIGN&BUILD CONSTRUCTION SERVICES ', '24, CALIZON STREET, SANTO ROSARIO, Paombong, Bulacan, 3001', 'ATE MHEL', '816-7243', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 05:55:52', NULL, 'completed', '2025-07-15 02:27:30', 'EMILIO S. CLEMENTE', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(149, '2025-03-05', 'NVX Enterprises', '34, PUROK 4, ABULALAS, Hagonoy, Bulacan, 3002', 'ATE MHEL ', '816-7243', 'Service Invoice', 10, 50, '1/2', 'LONG', '', 'Carbonless', 3, '001-500', 'Booklet', '', 'Top White, Middle Green, Bottom Yellow', '', 13, '2025-07-14 06:04:43', NULL, 'completed', '2025-07-15 03:00:45', 'MARY JANE M. FAUSTINO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(150, '2025-03-07', 'EZSIPNAYAN LEARNING CENTER ', '2/F, LYSA QUEEN BLDG., MCARTHUR HIGHWAY, SAN PABLO, Malolos City, Bulacan, 3000', 'ELLAINE A. MANSILUNGAN', '09275901745', 'Service Invoice', 20, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-1000', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 06:09:21', NULL, 'completed', '2025-07-15 02:36:05', 'ELLAINE A. MANSILUNGAN', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(151, '2025-03-07', 'NICAT METAL BUILDERS OPC', 'LOT 4 BLOCK 3, BULACAN AGRO-INDUSTRIAL SUBDIVISION, PIO CRUZCOSA, Calumpit, Bulacan, 3003', 'Edmar (VIBER)', '09177148487 ', 'Collection Receipt', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 06:14:01', NULL, 'completed', '2025-07-15 02:59:28', 'NICAT METAL BUILDERS OPC', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(152, '2025-03-07', 'RFH Lot for Lease', '46, TANJECO ST, SAN VICENTE, Malolos City, Bulacan, 3000', 'ATE MHEL', '816-7243', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Green', '', 13, '2025-07-14 06:19:42', NULL, 'completed', '2025-07-15 03:01:11', 'ROSEMARIE F. HIGUERA', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(153, '2025-03-07', 'Marizel Photo - Photo and Coverage', '33, FAUSTA RD., MABOLO, Malolos City, Bulacan, 3000', 'Marizel Photo - Photo and Coverage', '09226825706', 'Receipt', 20, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '0001-1000', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 06:26:23', NULL, 'completed', '2025-07-15 02:59:04', 'Marizel Photo - Photo and Coverage', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(154, '2025-03-13', 'ROUTE 95 DINER - Guiguinto', 'Sta Cruz, Brgy Tabang, Malolos City, Bulacan, 3015', 'Judith', '0992 321 7360', 'Service Invoice', 50, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '1001-3500', 'Booklet', '', 'Top White,Bottom Yellow', '', 13, '2025-07-14 06:38:20', NULL, 'completed', '2025-07-30 04:41:33', 'EFAPRODITO DELA CRUZ CORPORATION', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Owned', 'VAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(155, '2025-03-13', 'TECHCONEK PHILIPPINES INC. ', '2ND FLR., N2, THE CABANAS KM. 44/45 LONGOS, Malolos City, Bulacan, 3000', 'Sab Carpio', 'Messenger', 'Billing Statement', 10, 50, 'whole', 'SHORT', '', 'Carbonless', 3, '1001-1500', 'Booklet', '', 'Top White, Middle Yellow, Bottom Pink', '', 13, '2025-07-14 06:49:31', NULL, 'completed', '2025-07-30 07:01:38', 'TECHCONEK PHILIPPINES INC.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(156, '2025-03-13', 'ROUTE 95 DINER', '4120, 2360 C, Calamba Road, Brgy. San Jose, Tagaytay, Cavite, 4120', 'N/A', 'R95Diner@gmail.com', 'Service Invoice', 20, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '1001-2000', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 07:01:42', NULL, 'completed', '2025-07-30 06:55:30', 'EFAPRODITO DELA CRUZ CORPORATION', '54A - Trece Martirez City, East Cavite', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(157, '2025-03-13', 'ROUTE 95 DINER', 'LOT 12, BLK 2A, PH1, NORTHVILLE EXEC. VILLAGE, LONGOS, Malolos City, Bulacan, 3000', 'EFAPRODITO DELA CRUZ CORPORATION', ' 966-480-5518  ', 'Service Invoice', 20, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '1001-2000', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 07:14:00', NULL, 'completed', '2025-07-30 06:55:44', 'ROUTE 95 DINER - MAIN', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(158, '2025-03-13', 'ROUTE 95 DINER ', 'C5 EXTENSION ROAD, EAST ARCADE, MOONWALK, Parañaque, Metro Manila, 1701', 'ROUTE 95 DINER PARAÑAQUE', '0915-981-4292', 'Service Invoice', 20, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '1001-2000', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 07:29:39', NULL, 'completed', '2025-07-30 06:56:00', 'EFAPRODITO DELA CRUZ CORPORATION', '052', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(159, '2025-03-15', 'Atty. Julian Marvin V. Duba', 'N/A, 245, Dr. Peralta St., Maguinhawa, Malolos City, Bulacan, 3000', 'Rotap (Sweetheart)', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 07:35:42', NULL, 'completed', '2025-07-15 02:11:25', 'Julian Marvin V. Duba', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(160, '2025-03-15', 'RMU Commercial Stall Leasing', 'Block 6, Lot 32, Capitol View Park Subdivision, Bulihan, Malolos City, Bulacan, 3000', 'Rotap ', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 08:02:25', NULL, 'completed', '2025-07-15 02:04:04', 'Ricardo M. Uychoco', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(161, '2025-03-21', '360 DEGREES SYSTEMS CORPORATION ', 'N/A, 9005, Doña Remedios Trinidad Hwy, Baliuag, Bulacan, 3006', 'ATE MHEL', '816-7243', 'Check Voucher', 25, 50, '1/2', 'LONG', '', 'Carbonless', 2, '4501-5750', 'Pad', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 08:10:20', NULL, 'completed', '2025-08-05 08:23:16', '360 SYSTEMS CORPORATION', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(162, '2025-03-21', 'ALANO’S ENTERPRISES INCORPORATED ', 'N/A, BLOCK 1 LOT 3, STANLEY VILLE SUBDIVISION, SAN AGUSTIN, Malolos City, Bulacan, 3000', 'MAAM AIDA', 'MESSENGER', 'INVOICE', 10, 50, 'whole', 'SHORT', '', 'Carbonless', 3, '00001-00500', 'Booklet', '', 'Top White, Middle Yellow, Bottom Blue', '', 13, '2025-07-14 08:14:19', NULL, 'completed', '2025-07-15 02:04:19', 'ALANO’S ENTERPRISES INCORPORATED', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(163, '2025-03-22', 'Oak Tree Marketing', 'N/A, LITES MTKJ BLDG, PASEO DEL CONGRESO, CATMON, Malolos City, Bulacan, 3000', 'Oak Tree Marketing', '0926-959-0360', 'Delivery Receipt ', 100, 50, '1/2', 'LONG', '', 'Carbonless', 3, '125251-130250', 'Pad', '', 'Top White, Middle Yellow, Bottom Pink', '', 13, '2025-07-14 08:20:10', NULL, 'completed', '2025-07-15 02:59:44', 'ANGELICA LUZ T. DE LEON', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(164, '2025-03-21', 'ALANO’S ENTERPRISES INCORPORATED ', 'N/A, BLOCK 1 LOT 3, STANLEY VILLE SUBDIVISION, SAN AGUSTIN, Malolos City, Bulacan, 3000', 'MAAM AIDA', 'MESSENGER', 'Collection Receipt', 10, 50, '1/4', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 08:28:27', NULL, 'completed', '2025-07-15 02:04:13', 'ALANO’S ENTERPRISES INCORPORATED', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(165, '2025-03-22', 'SCD MARKETING & PRODUCT SOLUTIONS INC. ', '8th, #2, St., Golden Mile Business Park, Maduya, Bacoor, Cavite, 4116', 'INA DELA VICTORIA', 'MESSENGER', 'D.R. - SHORT', 150, 50, '1/2', 'LONG', '', 'Carbonless', 5, '162501-170000', 'Pad', '', 'Top White, Middle Pink, Middle Yellow, Middle Green, Bottom Blue', '', 13, '2025-07-14 08:39:57', NULL, 'completed', '2025-07-30 06:58:45', 'SCD MARKETING SOLUTIONS INC', '54A - Trece Martirez City, East Cavite', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(166, '2025-03-25', 'ADVENTURES HUB TRAVEL AND TOURS ', '1/F STO. ROSARIO, ARBA MULTI-PURPOSE COOP., SAN ISIDRO I, Paombong, Bulacan, 3001', 'NERISSA S. CAPARAS', 'N/A', 'A.R.', 20, 50, '1/3', 'LONG', '', 'Carbonless', 2, '7001-8000', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-14 08:51:40', NULL, 'completed', '2025-07-15 02:03:24', 'NERISSA S. CAPARAS', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(167, '2025-03-27', 'Malolos Credit & Development Cooperative', 'No. 34, MCDC Bldg., Fausta Rd., Mabolo, Malolos City, Bulacan, 3000', 'Apple', 'Messenger', 'Collector\'s Receipt', 200, 100, '1/6', 'LONG', '', 'Carbonless', 2, '527601-547600', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-15 00:16:10', NULL, 'completed', '2025-07-15 02:47:25', 'MCDC', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(168, '2025-03-25', 'Packetswork Corporation', '88, F. ESTRELLA ST.,, ANIAG, Malolos City, Bulacan, 3000', 'Packetswork Corporation', 'packetswork.corporation@gmail.com', 'Delivery Receipt ', 50, 50, '1/2', 'LONG', '', 'Carbonless', 3, '25001-27500', 'Booklet', '', 'Top White, Middle Pink, Bottom Yellow', '', 13, '2025-07-15 00:22:10', NULL, 'completed', '2025-07-15 03:01:00', 'Packetswork Corporation', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(169, '2025-03-28', 'Packetswork Corporation', '88, F. ESTRELLA ST.,, ANIAG, Malolos City, Bulacan, 3000', 'Packetswork Corporation', 'packetswork.corporation@gmail.com', 'Collection Receipt', 10, 50, '1/3', 'LONG', '', 'Carbonless', 3, '3001-3500', 'Booklet', '', 'Top White, Middle Pink, Bottom Yellow', '', 13, '2025-07-15 00:25:21', NULL, 'completed', '2025-07-15 03:00:54', 'Packetswork Corporation', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(170, '2025-04-01', 'LaPresa Bar and Restaurant', 'ASHDOD COMMERCIAL BUILDING,  MAC ARTHUR HIWAY, PIO CRUZCOSA, Calumpit, Bulacan, 3003', 'ALEXANDER A. CACO', '09423175159', 'Order Slip', 100, 50, '1/8', 'LONG', '', 'Carbonless', 2, '5001-10000', 'Pad', '', 'Top White, Bottom Green', '', 13, '2025-07-15 00:31:39', NULL, 'completed', '2025-07-15 02:57:56', 'ALEXANDER A. CACO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(171, '2025-04-03', 'JFL Agri-Ventures Supplies', '., 555, Lucero St., Mabolo, Malolos City, Bulacan, 3000', 'Maam Hannah ', '0992-3318-932', 'Provisional Receipt', 102, 50, '1/3', 'LONG', '', 'Carbonless', 3, '174701-179800', 'Booklet', '', 'Top White, Middle Yellow, Bottom Pink', '', 13, '2025-07-15 00:52:57', NULL, 'completed', '2025-07-15 02:36:21', 'JFL Agri-Ventures Supplies', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(172, '2025-04-03', 'Goldentaste Food Kiosk', '., South Supermarket, Mc Arthur Highway, Bulihan, Malolos City, Bulacan, 3000', 'Mary Joy M. Briones', 'Messenger', 'Sales Invoice', 100, 50, '1/4', 'LONG', '', 'Carbonless', 2, '501-5500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-15 01:15:01', NULL, 'completed', '2025-07-15 02:36:11', 'Mary Joy M. Briones', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(173, '2025-04-04', 'Square Space MNL Inc.', '., 336, Pandi-Angat Rd., Siling Matanda, Pandi, Bulacan, 3014', 'SSMI Office', '0918-9367-002 ', 'Delivery Receipt ', 10, 50, '1/2', 'LONG', '', 'Carbonless', 3, '501-1000', 'Booklet', '', 'Top White, Middle Pink, Bottom Yellow', '', 13, '2025-07-15 01:27:27', NULL, 'completed', '2025-07-30 06:59:07', 'Square Space MNL Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(174, '2025-04-04', 'Noah\'s Arc Trading', '., The Cabanas, Mac Arthur Highway, Longos, Malolos City, Bulacan, 3000', 'Caren Silao', '0920-9759-108', 'Delivery Receipt ', 10, 50, '1/2', 'SHORT', '', 'Carbonless', 2, '501-1000', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-15 01:41:05', NULL, 'completed', '2025-07-15 02:59:36', 'Caren Silao', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(175, '2025-07-15', 'Active Media Designs & Printing', 'RVC Bldg. 30-C, 30 Fausta Rd., Lucero St, Mabolo, Malolos City, Bulacan, 3000', 'Margie parohinog', '09987916018', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '0001-0500', 'Booklet', '', 'Top White, Bottom Yellow', '', 11, '2025-07-15 01:48:22', NULL, 'completed', '2025-07-15 02:03:04', 'Wizermina C. Lumbad', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(176, '2025-04-04', 'Tigerux Global Management Inc.', '110 2ND FLOOR, FELIZA JAZZ BUILDING, MC ARTHUR HIGHWAY SUMAPANG MATANDA, Malolos City, Bulacan, 3000', 'Rotap', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', 'LONG', 'Carbonless', 2, '00001-0050', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-15 02:41:42', NULL, 'completed', '2025-07-30 07:02:01', 'Tigerux Global Management Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(177, '2025-04-10', 'JMPrints Souvenir Shop', '., PUROK 2, PALIMBANG, Calumpit, Bulacan, 3003', 'Julius Faigmani', 'Messenger', 'Sales Invoice', 40, 50, '1/2', 'LONG', '', 'Carbonless', 3, '001-2000', 'Pad', '', 'Top White, Middle Pink, Bottom Green', '', 13, '2025-07-15 02:59:38', NULL, 'completed', '2025-07-15 03:00:31', 'Marjorie M. Faigmani', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(178, '2025-04-10', 'The Glam Room Salon & Spa', '., Builders Warehouse, Mc Arthur Highway, Bulihan, Malolos City, Bulacan, 3000', 'Caren Silao', '0920-9759-108', 'A.R.', 20, 50, '1/4', 'LONG', '', 'Carbonless', 2, '1001-2000', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-15 03:08:14', NULL, 'completed', '2025-07-30 07:01:51', 'Caren Silao', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(179, '2025-04-14', 'Reina Readymix Construction OPC', '., Purok Cardona, Barangay Ilog, Lucena, Quezon, 4336', 'Edmar (VIBER)', '09177148487', 'Delivery Receipt ', 20, 50, '1/4', 'LONG', '', 'Carbonless', 3, '21-21500', 'Booklet', '', 'Top White, Middle Blue, Bottom Pink', '', 13, '2025-07-15 03:32:29', NULL, 'completed', '2025-07-30 04:38:41', 'Reina Readymix Construction OPC', '061 - Gumaca, Quezon', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(180, '2025-04-21', 'Oak Tree Marketing', '2nd Floor, LITES MTKJ Bldg., Paseo del Congreso, Brgy. Catmon, Malolos City, Bulacan, 3000', 'Rose Anne Gamos (HR Officer)', 'ragamos@oaktree.ph', 'Sales Invoice', 20, 50, '1/2', 'LONG', '', 'Carbonless', 2, '2501-3500', 'Booklet', '', 'Top White, Bottom Blue', '', 13, '2025-07-15 03:45:07', NULL, 'completed', '2025-07-30 04:38:00', 'ANGELICA LUZ T. DE LEON', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(181, '2025-04-25', 'Sunoria Realty OPC', '., 4620, Mc Arthur Highway, Poblacion, Guiguinto, Bulacan, 3015', 'Ate Mhel', '816-7243', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-15 05:09:27', NULL, 'completed', '2025-07-30 07:01:18', 'Sunoria Realty OPC', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(182, '2025-04-25', 'Hailey\'s Haven Nail Salon and Spa - 6', 'Level 3, 03323 Robinsons Place Malolos, Sumapang Matanda, Malolos City, Bulacan, 3000', 'Ate Mhel', '(044) 816-7243', 'Service Invoice', 50, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-02500', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-15 05:19:09', NULL, 'completed', '2025-07-29 03:22:55', 'MARVIN D. V. VALERIANO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(183, '2025-04-30', 'Remarsan Food House', 'Unit 1, Swiss Gerald Blg., Sitio Bagong Kalsada, San Isidro, Paombong, Bulacan, 3001', 'Maam Aida', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 3, '00001-00500', 'Booklet', '', 'Top White, Middle Blue, Bottom Yellow', '', 13, '2025-07-15 05:31:53', NULL, 'completed', '2025-07-30 06:55:22', 'Mark Lester R. Errazo', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(184, '2025-04-30', 'Swiss Gerald Commercial Building', '., Swiss Gerald Blg., Sitio Bagong Kalsada, San Isidro, Paombong, Bulacan, 3001', 'Maam Aida', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-15 05:41:26', NULL, 'completed', '2025-07-30 07:01:28', 'Gerald Allan R. Macabalo', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(185, '2025-05-03', 'Arctic-Forest Products Inc.', '11A, Warehouse, Ilang-Ilang St., Tabang, Guiguinto, Bulacan, 3015', 'Beng Treyes', 'beng.treyes@arcticfp.com', 'Material Requisition ', 10, 50, '1/2', 'LONG', '', 'Carbonless', 3, '15901-10400', 'Booklet', '', 'Top White, Middle Yellow, Bottom Blue', '', 13, '2025-07-15 05:53:48', NULL, 'completed', '2025-07-29 03:29:27', 'Arctic-Forest Products Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(186, '2025-05-03', 'Arctic-Forest Products Inc.', '11A, Warehouse, Ilang-Ilang St., Tabang, Guiguinto, Bulacan, 3015', 'Beng Treyes', 'beng.treyes@arcticfp.com', 'Gate Pass', 10, 50, '1/2', 'LONG', '', 'Carbonless', 3, '10701-11200', 'Booklet', '', 'Top White, Middle Blue, Bottom Yellow', '', 13, '2025-07-15 06:07:59', NULL, 'completed', '2025-07-18 07:48:31', 'Arctic-Forest Products Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(187, '2025-05-03', 'Arctic-Forest Products Inc.', '11A, Warehouse, Ilang-Ilang St., Tabang, Guiguinto, Bulacan, 3015', 'Beng Treyes', 'beng.treyes@arcticfp.com', 'Stock Transfer Form', 10, 50, '1/2', 'LONG', '', 'Carbonless', 4, '1501-2000', 'Booklet', '', 'Top White, Middle Yellow, Middle Green, Bottom Pink', '', 13, '2025-07-15 06:11:50', NULL, 'completed', '2025-07-29 03:29:37', 'Arctic-Forest Products Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(188, '2025-05-06', 'JFJ Movers Enterprise', '., 552, Lucero St., Mabolo, Malolos City, Bulacan, 3000', 'Maam Leng', 'Messenger', 'Provisional Receipt', 100, 50, '1/3', 'LONG', '', 'Carbonless', 3, '7651-12650', 'Booklet', '', 'Top White, Middle Pink, Bottom Blue', '', 13, '2025-07-15 06:23:24', NULL, 'completed', '2025-07-30 00:52:56', 'Jaim Mari C. Fernandez', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(189, '2025-05-07', 'Arctic-Forest Products Inc.', '11A, Warehouse, Ilang-Ilang St., Tabang, Guiguinto, Bulacan, 3015', 'Beng Treyes', 'beng.treyes@arcticfp.com', 'Delivery Note', 10, 50, '1/2', 'LONG', '', 'Carbonless', 3, '2751-3250', 'Booklet', '', 'Top White, Middle Blue, Bottom Yellow', '', 13, '2025-07-15 06:29:05', NULL, 'completed', '2025-07-29 03:29:11', 'Arctic-Forest Products Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(190, '2025-05-09', 'Suisse Chicken House', 'Unit 11, Swiss Gerald Blg., Sitio Bagong Kalsada, San Isidro, Paombong, Bulacan, 3001', 'Maam Aida', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 3, '00001-00500', 'Booklet', '', 'Top White, Middle Blue, Bottom Yellow', '', 13, '2025-07-15 07:05:29', NULL, 'completed', '2025-07-30 07:01:07', 'Mark Lester R. Errazo', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(191, '2025-05-09', 'St. James Realty Development', '., ., Igulot, Bocaue, Bulacan, 3018', 'Osjas', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-15 07:17:04', NULL, 'completed', '2025-07-30 06:59:38', 'Jacob M. Santiago', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(192, '2025-07-14', 'Barley Daily Health & Beauty Products Shop', 'Block 39, Lot 27, Dinar St., Deca Homes, Meycauayan City, Bulacan, 3020', 'Cherille M. Menor', 'Messenger', 'Sales Invoice', 10, 50, '1/4', 'SHORT', '', 'Carbonless', 2, '0001-0500', 'Booklet', '', 'Top White, Bottom Green', '', 13, '2025-07-15 07:42:06', NULL, 'completed', '2025-07-30 04:36:16', 'Cherille M. Menor', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(193, '2025-05-20', 'RTM8 Rice Wholesaling ', '., ., Denorado Intercity, San Juan, Balagtas, Bulacan, 3016', 'Ate Mhel', '0448167243', 'Collection Receipt', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-15 07:49:49', NULL, 'completed', '2025-07-30 06:57:40', 'Jerome Isaac S. Maniquis', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(194, '2025-05-20', 'RTM8 Rice Wholesaling ', '., ., Denorado Intercity, San Juan, Balagtas, Bulacan, 3016', 'Ate Mhel', '0448167243', 'Sales Invoice', 10, 50, '1/4', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-15 07:52:21', NULL, 'completed', '2025-07-30 06:58:00', 'Jerome Isaac S. Maniquis', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(195, '2025-05-28', 'Oak Tree Marketing', '2nd Floor, Lites MTKJ Bldg., Paseo Del Congreso, Malolos City, Bulacan, 3000', 'Oak Tree Marketing', '09338275560', 'Delivery Receipt ', 100, 50, '1/2', 'LONG', '', 'Carbonless', 3, '130251-135250', 'Pad', '', 'Top White, Middle Yellow, Bottom Pink', '', 13, '2025-07-15 08:00:32', NULL, 'completed', '2025-07-30 04:38:17', 'ANGELICA LUZ T. DE LEON', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(196, '2025-05-29', 'Eilamore Ekea Movers OPC', '., 90 Purok 2, Iba, O\'este, Calumpit, Bulacan, 3003', 'Maam Lai', 'Messenger', 'Delivery Receipt ', 300, 50, '1/4', 'LONG', '', 'Carbonless', 3, '45001-60000', 'Booklet', '', 'Top White, Middle Yellow, Bottom Pink', '', 13, '2025-07-15 08:06:52', NULL, 'completed', '2025-07-30 06:54:03', 'Eilamore Ekea Movers OPC', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(197, '2025-06-02', 'Lili\'s Pharmacy ', 'Stall 2, ., 92 Lucero St., Mabolo, Malolos City, Bulacan, 3000', 'Lilinette A. Pagdilao', '.', 'Sales Invoice', 20, 50, '1/4', 'LONG', '', 'Carbonless', 2, '501-1500', 'Booklet', '', 'Top White, Bottom Yellow', '', 13, '2025-07-15 08:12:36', NULL, 'completed', '2025-07-29 03:26:08', 'Lilinette A. Pagdilao', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(198, '2025-06-03', 'Catholic Women\'s League, Philippines, Inc.', '., Santiago Apostol Unit, Santiago Apostol Parish, Paombong, Bulacan, 3001', 'Catholic Women\'s League, Philippines, Inc.', '9623817789', 'Donation Receipt', 10, 50, '1/6', 'SHORT', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Pink', '', 13, '2025-07-15 08:39:11', NULL, 'completed', '2025-07-29 03:29:55', 'Catholic Women\'s League, Philippines, Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(199, '2025-06-04', 'JMV Nail Salon', 'Wara 014, Waltermart Mall, Jose Abad Santos Avenue, San Jose Mesulo, Arayat, Angeles, Pampanga, 2012', 'Ate Mhel ', '(044) 816-7243', 'Service Invoice', 50, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-2500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-16 01:17:37', NULL, 'completed', '2025-07-29 03:25:53', 'Joana Marie D.J. Valeriano', '21A - North Pampanga', '263-027-244-00005', 'Osjas', 'NONVAT', '21AAU20250000005912', '2025-06-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(200, '2025-06-04', 'Arctic-Forest Products Inc.', '11A, Warehouse, Ilang-Ilang St., Tabang, Guiguinto, Bulacan, 3015', 'Beng Treyes', 'beng.treyes@arcticfp.com', 'Invoice', 20, 50, '1/2', 'LONG', '', 'Carbonless', 3, '001-1000', 'Booklet', '', 'Top White, Middle Blue, Bottom Yellow', 'None', 13, '2025-07-16 01:28:29', NULL, 'completed', '2025-07-29 03:29:00', 'Arctic-Forest Products Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '007-050-982-00000', 'Online', 'VAT', '25AAU20250000007425', '2025-06-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(201, '2025-06-04', 'Arctic-Forest Products Inc.', '11A, Warehouse, Ilang-Ilang St., Tabang, Guiguinto, Bulacan, 3015', 'Beng Treyes', 'beng.treyes@arcticfp.com', 'Collection Receipt', 10, 50, '1/3', 'LONG', '', 'Carbonless', 3, '4001A-4500A', 'Booklet', '', 'Top White, Middle Yellow, Bottom Pink', 'None', 13, '2025-07-16 01:58:31', NULL, 'completed', '2025-07-29 03:28:49', 'Arctic-Forest Products Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '007-050-982-00000', 'Online', 'VAT', '25AAU20250000007425', '2025-06-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(202, '2025-02-22', 'Megale Security Services Corp.', '., ., KM. 43 Mc Arthur Highway, Bulihan, Malolos City, Bulacan, 3000', 'Megale', '0923-1055-807', 'Acknowledgement Receipt', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '501-1000', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-16 02:26:27', NULL, 'completed', '2025-07-30 04:37:07', 'Megale Security Services Corp.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '010-417-480-00000', 'Viber', 'VAT', '25AAU20250000002961', '2025-02-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(203, '2025-01-09', 'Caniogan Creditt & Development Cooperative - Calumpit', '., CCDCoop Cal Bldg.,, Pulilan-Calumpit Rd., Caniogan, Calumpit, Bulacan, 3003', 'Cecille Dooma', 'Messenger', 'Provisional Receipt', 240, 50, '1/6', 'LONG', '', 'Carbonless', 2, '4143101-4155100', 'Booklet', '', 'Top White,Bottom Yellow', 'None', 13, '2025-07-16 02:40:11', NULL, 'completed', '2025-07-29 03:22:05', 'Caniogan Creditt & Development Cooperative', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '001-648-627-00002', 'Online', 'NONVAT', '25AAU2025000000XXXX', '2025-01-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(204, '2025-01-15', 'Caniogan Creditt & Development Cooperative - Hagonoy', 'Purok 8, Hagonoy, Bulacan, 3002', 'Cecille Dooma', 'Messenger | 0933-813-2857', 'Provisional Receipt', 240, 50, '1/6', 'LONG', '', 'Carbonless', 2, '615701-6127700', 'Booklet', '', 'Top White,Bottom Yellow', 'None', 13, '2025-07-16 02:48:33', NULL, 'completed', '2025-07-29 03:20:01', 'Caniogan Creditt & Development Cooperative', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '001-648-627-00001', 'Online', 'EXEMPT', '25AAU2025000000XXXX', '2025-01-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(205, '2025-01-22', 'JFL Agri Pa-In-Pro', '550, Lucero St., Mabolo, Malolos City, Bulacan, 3000', 'Maam Hannah', '0992-3318-932', 'Provisional Receipt', 100, 50, '1/3', 'LONG', '', 'Carbonless', 3, '0001-5000', 'Booklet', '', 'Top White, Middle Yellow, Bottom Pink', 'None', 13, '2025-07-16 02:56:23', NULL, 'completed', '2025-07-30 04:36:54', 'Pa-In-Pro Business Venture Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '010-761-215-00000', 'Online', 'VAT', '25AAU2025000000XXXX', '2025-01-22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(206, '2025-07-16', 'Yolanda S. Rotap, CPA', 'Unit C9-10, SRCDC Bldg., Sumapang Matanda, Malolos City, Bulacan, 3000', 'Rotap', 'Messenger', 'Service Invoice', 20, 50, '1/2', 'LONG', '', 'Carbonless', 2, '501-1500', 'Booklet', '', 'Top White, Bottom Pink', 'None', 13, '2025-07-16 03:03:09', NULL, 'completed', '2025-07-30 07:02:30', 'Yolanda S. Rotap', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '107-200-855-00000', 'Online', 'VAT', '25AAU20250000007655', '2025-06-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(207, '2025-06-16', 'Active Media Designs and Printing', '., 30-C, Fausta Rd., Lucero St., Mabolo, Malolos City, Bulacan, 3000', 'Wizermina C. Lumbad', '09987916018', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '251-500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-16 07:07:25', NULL, 'completed', '2025-07-29 03:27:53', 'Wizermina C. Lumbad', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '188-744-661-00000', 'Owned', 'NONVAT', '25AAU20240000008639', '2024-06-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(208, '2025-06-17', 'EZSIPNAYAN LEARNING CENTER - 1', '2/F, LYSA QUEEN BLDG., MC ARTHUR HIGHWAY, SAN PABLO, Malolos City, Bulacan, 3000', 'ELLAINE A. MANSILUNGAN', '09275901745', 'Service Invoice', 20, 50, '1/3', 'LONG', '', 'Carbonless', 2, '501-1500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-16 07:30:12', NULL, 'completed', '2025-07-29 03:30:33', 'ELLAINE A. MANSILUNGAN', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '306-059-609-00001', 'WALK-IN', 'NONVAT', '25AAU20250000007756', '2025-06-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(209, '2025-07-12', 'Rach Aircondition Services', 'Malolos City, Bulacan, 3000', 'Rachel', '09202682435', 'Billing Statement', 30, 50, 'whole', 'SHORT', '', 'Carbonless', 3, '3001 - 4500', 'Booklet', '', 'Top White, Middle Yellow, Bottom Green', 'None', 15, '2025-07-16 07:36:38', NULL, 'completed', '2025-07-16 08:01:08', 'Rachel', '', '', 'WALK-IN', 'VAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(210, '2025-06-19', 'ROUTE 95 DINER - 4', 'MC ARTHUR HIGHWAY, STA. CRUZ, Guiguinto, Bulacan, 3015', 'JUDITH CARIGO', '09974221107', 'Service Invoice', 40, 50, '1/4', 'LONG', '', 'Carbonless', 2, '3501-5500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-16 07:50:05', NULL, 'completed', '2025-07-30 06:56:17', 'EFAPRODITO DELA CRUZ CORPORATION', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '010-751-600-00004', 'Online', 'VAT', '25AAU20250000008043', '2025-06-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(211, '2025-06-17', 'Natalia De Guzman Sali PH. D - Consultant', '034, Zone 6, Abulalas, Hagonoy, Bulacan, 3002', 'Natalia De Guzman', '09166238343', 'Service Invoice', 10, 50, '1/4', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Pink', 'None', 13, '2025-07-16 08:20:57', NULL, 'completed', '2025-07-30 06:54:45', 'Natalia De Guzman', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '182-788-994-00000', 'WALK-IN', 'NONVAT', '25AAU20250000006681', '2025-05-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(212, '2025-07-16', 'DDD Pharmacy Co.', 'Purok 1, San Pedro, Hagonoy, Bulacan, 3002', 'jundomingo524@gmail.com', '09175776355', 'Sales Invoice', 5, 50, '1/4', 'LONG', '', 'Carbonless', 2, '3501-3750', 'Booklet', '', 'Top White, Bottom Green', 'None', 13, '2025-07-16 08:32:18', NULL, 'completed', '2025-07-24 05:49:54', 'DDD Pharmacy Co.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '764-680-887-00000', 'WALK-IN', 'NONVAT', '25AAU20250000008342', '2025-06-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(213, '2025-06-25', 'DDD Pharmacy Co.', 'Purok 1, Brgy. SanPedro, Hagonoy, Bulacan, 3002', 'jundomingo524@gmail.com', '09175776355', 'Sales Invoice', 5, 50, '1/4', 'LONG', '', 'Carbonless', 2, '3251-3500', 'Booklet', '', 'Top White, Bottom Green', 'None', 13, '2025-07-16 08:39:05', NULL, 'completed', '2025-07-30 04:36:31', 'DDD Pharmacy Co.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '764-680-887-00000', 'WALK-IN', 'NONVAT', '25AAU20250000008045', '2025-06-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(214, '2025-05-08', 'Sta. Isabel Trading', 'Purok 6, Brgy. Sillawit, Cauayan, Isabela, 3305', 'Maam Hannah', 'Messenger', 'S.O.A.', 100, 50, '1/2', 'SHORT', '', 'Carbonless', 4, '40801-45800', 'Pad', '', 'Top White, Middle Green, Middle Blue, Bottom Pink', 'None', 13, '2025-07-17 01:02:42', NULL, 'completed', '2025-07-30 07:00:08', 'Ivan Nikolai C. Reyes', '015 - Naguilian, Isabela', '403-062-433-00000', 'Online', 'NONVAT', '', '2025-05-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(215, '2025-02-14', 'Sta. Isabel Trading', 'Purok 6, Brgy. Sillawit, Cauayan, Isabela, 3305', 'Maam Hannah', 'Messenger', 'S.O.A.', 100, 50, '1/2', 'SHORT', '', 'Carbonless', 4, '35801-40800', 'Pad', '', 'Top White, Middle Green, Middle Blue, Bottom Pink', 'None', 13, '2025-07-17 01:11:04', NULL, 'completed', '2025-07-30 07:00:37', 'Ivan Nikolai C. Reyes', '015 - Naguilian, Isabela', '403-062-433-00000', 'Online', 'NONVAT', '', '2025-02-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(216, '2025-01-21', 'Sto. Rosario Credit and Development Coop.', 'Ricardo O. Santos Bldg., A. Mabini St., Brgy. Mojon, Malolos City, Bulacan, 3000', 'Assistant', 'sto.rosario_credit@yahoo.com/(044)791-6750', 'Check Voucher', 100, 50, '1/2', 'SHORT', '', 'Carbonless', 2, '45501-50500', 'Pad', '', 'Top White, Bottom Pink', 'None', 13, '2025-07-17 01:46:06', NULL, 'completed', '2025-07-30 07:00:49', 'Sto. Rosario Credit and Development Coop.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '000-582-878-00000', 'Online', 'VAT', '', '2025-01-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(217, '2025-05-20', 'Razcal Interior Supplies Trading ', '#50 Uli Uli, Brgy. Iba, Hagonoy, Bulacan, 3002', 'Messenger', 'Messenger', 'D.R. (No Permit)', 30, 50, 'whole', 'SHORT', '', 'Carbonless', 3, '1401-2900', 'Booklet', '', 'Top White, Middle Blue, Bottom Yellow', 'None', 13, '2025-07-17 02:08:08', NULL, 'completed', '2025-07-30 04:38:31', 'Razcal Interior Supplies Trading', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', '2025-05-20', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(218, '2025-04-12', 'JFL Agri-Ventures Supplies', '550, Lucero St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Maam Hannah Omilan', '09923318932', 'Sales Invoice', 200, 50, '1/2', 'SHORT', '', 'Carbonless', 4, '60001A-70000A', 'Pad', '', 'Top White,Middle Pink,Middle Yellow,Bottom Blue', 'None', 13, '2025-07-17 02:48:14', NULL, 'completed', '2025-07-29 03:25:15', 'Jaime K. Crisostomo', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '157-339-724-00000', 'Online', 'VAT', '25AAU20250000005632', '2025-04-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(219, '2025-03-28', 'Packetswork Corporation', '88, F. Estrella St., Brgy. Aniag, Malolos City, Bulacan, 3000', 'Packetswork Corporation', 'packetswork.corporation@gmail.com', 'Sales Invoice', 10, 50, 'whole', 'SHORT', '', 'Carbonless', 3, '4501-5000', 'Booklet', '', 'Top White, Middle Yellow, Bottom Blue', 'None', 13, '2025-07-17 03:04:39', NULL, 'completed', '2025-07-30 04:37:46', 'Packetswork Corporation', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '008-974-665-00000', 'Online', 'VAT', '25AAU20250000004831', '2025-03-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(220, '2025-03-25', 'Edcon Cargotrans, Inc.', '1327, Alido Subd., Rosal St., Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Edcon Cargotrans, Inc.', 'Messenger', 'Waybill', 30, 50, '1/2', 'SHORT', '', 'Carbonless', 2, '17501-19000', 'Booklet', '', 'Top White, Bottom Blue', 'None', 13, '2025-07-17 05:15:38', NULL, 'completed', '2025-07-30 06:53:49', 'Edcon Cargotrans, Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '006-821-590-00000', 'Online', 'VAT', '', '2025-03-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(221, '2025-01-21', 'Sto. Rosario Credit and Development Coop.', 'Ricardo O. Santos Bldg., A. Mabini St., Brgy. Mojon, Malolos City, Bulacan, 3000', 'Joriza', 'Messenger', 'Passbook Insert (Blue)', 30, 50, '1/2', '38X25', '', 'Special Paper', 1, '81001-84000', 'Pad', '', 'Matt C2s 80#', 'None', 13, '2025-07-17 05:29:54', NULL, 'completed', '2025-07-30 07:00:58', 'Sto. Rosario Credit and Development Coop.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '000-582-878-00000', 'Online', 'VAT', '', '0025-01-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(222, '2025-03-04', 'Malolos Credit & Development Cooperative', 'Unit 103-104, Phoenix Bldg., Fausta Rd., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Maam Apple', 'Messenger', 'Cavan Rice Stub Associate', 200, 1, 'whole', '22.5X28.5', '', 'Special Paper', 1, '001-200', 'Custom', '', 'Imp Bristol 100# Pink', 'None', 15, '2025-07-17 06:29:15', NULL, 'completed', '2025-07-29 03:26:17', 'Malolos Credit & Development Cooperative', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(223, '2025-07-15', 'Aw Electrical Services', '613, Enriquez St., Brgy. Tabe, Guiguinto, Bulacan, 3015', 'Jen Dela Cruz', 'Messenger', 'A.R.', 20, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-1000', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 15, '2025-07-17 07:25:23', NULL, 'completed', '2025-07-30 06:53:36', 'Reymart A. Dela Cruz', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '462-972-616-00000', 'Online', 'NONVAT', '25AAU20250000009237', '2025-07-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(224, '2025-07-10', 'Maricopa KTV ', 'Brgy. Molino 1, Bacoor, Cavite, 4102', 'ALEXANDER A. CACO', 'caco_alexanderoffice@yahoo.com', 'Order Slip', 100, 50, '1/8', 'LONG', '', 'Carbonless', 2, '5001-10000', 'Pad', '', 'Top White, Bottom Green', 'None', 15, '2025-07-17 07:32:09', NULL, 'completed', '2025-07-29 03:25:43', 'ALEXANDER A. CACO', '54B - Kawit, West Cavite', '', 'Online', 'NONVAT', '', '2025-07-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `job_orders` (`id`, `log_date`, `client_name`, `client_address`, `contact_person`, `contact_number`, `project_name`, `quantity`, `number_of_sets`, `product_size`, `paper_size`, `custom_paper_size`, `paper_type`, `copies_per_set`, `serial_range`, `binding_type`, `custom_binding`, `paper_sequence`, `special_instructions`, `created_by`, `created_at`, `product_id`, `status`, `completed_date`, `taxpayer_name`, `rdo_code`, `tin`, `client_by`, `tax_type`, `ocn_number`, `date_issued`, `province`, `city`, `barangay`, `street`, `building_no`, `floor_no`, `zip_code`) VALUES
(225, '2025-07-10', 'LaPresa Bar and Restaurant', 'Ashdod Commercial Building, Mac Arthur Highway, Brgy. Pio Cruzcosa, Calumpit, Bulacan, 3003', 'ALEXANDER A. CACO', 'caco_alexanderoffice@yahoo.com', 'Order Slip', 100, 50, '1/8', 'LONG', '', 'Carbonless', 2, '10001-15000', 'Pad', '', 'Top White, Bottom Green', 'None', 15, '2025-07-17 07:38:03', NULL, 'completed', '2025-07-29 03:26:01', 'ALEXANDER A. CACO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', '2025-07-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(226, '2025-07-18', 'Osjas Management Services Inc.', '30-B, RVC Bldg., Fausta Rd., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'ate Mhel ', '0448167243', 'A.R.', 5, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-250', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-18 02:43:23', NULL, 'completed', '2025-07-30 04:37:31', 'Osjas Management Services Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '009-528-508-00000', 'WALK-IN', 'NONVAT', '25AAU20250000008607', '2025-07-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(227, '2025-07-08', 'Visionaries Property Management Corp.', 'Lot 7 Block 2, Bulacan Agro Industrial Subd., Brgy. San Marcos, Calumpit, Bulacan, 3003', 'Rotap', 'Messenger', 'Service Invoice', 10, 50, '1/3', 'LONG', '', 'Carbonless', 2, '00001-00500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-18 03:03:46', NULL, 'completed', '2025-08-02 07:24:30', 'Visionaries Property Management Corp.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '678-809-311-00000', 'Online', 'VAT', '25AAU20250000008594', '2025-06-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(228, '2025-07-07', 'Osjas Management Services Inc.', '30B, RVC Bldg., Fausta Rd., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'ate Mhel', '0448167243', 'Service Invoice', 5, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-250', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 13, '2025-07-18 03:22:32', NULL, 'completed', '2025-07-30 04:37:18', 'Osjas Management Services Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '009-528-508-00000', 'WALK-IN', 'NONVAT', '25AAU20250000008607', '2025-07-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(229, '2025-07-07', 'Malolos Credit & Development Cooperative', 'No. 34, MCDC Bldg., Fausta Rd., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Maam Apple', 'Messenger', 'Request for Loan Application', 10, 100, '1/2', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 1, '.', 'Pad', '', 'White - Book Paper #50 (70gsm)', 'None', 13, '2025-07-18 03:47:56', NULL, 'completed', '2025-07-29 03:26:33', 'Malolos Credit & Development Cooperative', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', '2025-07-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(230, '2025-07-07', 'Malolos Credit & Development Cooperative', 'No. 34, MCDC Bldg., Fausta Rd., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Maam Apple', 'Messenger', 'Remittance Slip (Riso)', 20, 100, '1/4', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 1, '.', 'Pad', '', 'White - Book Paper #50 (70gsm)', 'None', 13, '2025-07-18 05:23:14', NULL, 'completed', '2025-07-29 03:26:25', 'Malolos Credit & Development Cooperative', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', '2025-07-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(231, '2025-07-18', 'Oak Tree Marketing', 'LITES MTKJ BLDG, PASEO DEL CONGRESO, Brgy. CATMON, Malolos City, Bulacan, 3000', 'Oak Tree Marketing', '0926-959-0360', 'Sales Invoice', 20, 50, '1/2', 'LONG', '', 'Carbonless', 2, '3501-4500', 'Booklet', '', 'Top White, Bottom Blue', 'None', 11, '2025-07-18 05:40:45', NULL, 'completed', '2025-07-30 06:54:57', 'ANGELICA LUZ T. DE LEON', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '321-365-054-00000', 'AMDP', 'VAT', '25AAU20250000009238', '2025-12-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(232, '2025-06-28', 'Force Central Field Dist. Inc. - Cavite', 'Warehouse 5, New Cavite Industrial City, Gov. Drive, Stateland Hills, Carmelita St., Brgy. Manggahan, Gen. Trias, Dasmariñas, Cavite, 4105', 'Ms. Annibeth Espino', '09453879265', 'SDR - Good Stocks', 200, 50, '1/2', 'QTO/SHORT (8.5X11)', '', 'Ordinary Paper', 3, '00001-10000', 'Pad', '', 'White - Newsprint Lmn 48.8, Yellow - Star Onion Skin, Pink - Star Onion Skin', 'None', 13, '2025-07-18 05:59:49', NULL, 'completed', '2025-07-29 03:22:44', 'Force Central Field Dist. Inc.', '54B - Kawit, West Cavite', '', 'Online', 'NONVAT', '', '2025-06-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(233, '2025-07-03', 'JFL Agri-Ventures Supplies', '550, Lucero St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Maam Hannah Omilan', '09923318932', 'Delivery Receipt ', 100, 50, '1/2', '11X17', '', 'Carbonless', 4, '100001A-105000A', 'Pad', '', 'Top White, Middle Yellow, Middle Blue, Bottom Pink', 'None', 13, '2025-07-18 06:09:03', NULL, 'completed', '2025-07-29 03:23:25', 'JFL Agri-Ventures Supplies', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', '2025-07-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(234, '2025-07-18', 'JFL Agri Pa-In-Pro', '550, Lucero St., Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Maam Hannah', '0992-3318-932', 'Sales Invoice', 100, 50, '1/2', 'SHORT', '', 'Carbonless', 4, '0001-5000', 'Pad', '', 'Top White, Middle Pink, Middle Yellow, Bottom Blue', 'None', 15, '2025-07-18 06:09:43', NULL, 'completed', '2025-07-30 06:54:31', 'Pa-In-Pro Business Venture Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '010-761-215-00000', 'Online', 'VAT', '25AAU20250000008489', '2025-06-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(235, '2025-06-23', 'Practical Tools General Merchandise', 'Km 43 Mc Arthur Highway, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Christopher C. Cajucom', 'Messenger', 'Sales Invoice (Re-Print)', 42, 50, '1/2', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 3, '15001A-20000A', 'Booklet', '', 'White - Bond Paper, Pink - Star Onion Skin, Green - Star Onion Skin', 'None', 13, '2025-07-18 06:59:49', NULL, 'completed', '2025-07-30 04:35:50', 'Christopher C. Cajucom', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '261-351-978-00000', 'Online', 'VAT', '25AAU20250000001589', '2023-02-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(236, '2025-07-09', 'Force Central Field Dist. Inc. ', 'Bldg. A, Mc Arthur Highway, Brgy. Tikay, Malolos City, Bulacan', 'Ms. Annibeth Espino', '09453879265', 'PSS Form', 3, 500, 'whole', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 1, '.', 'Custom', '', 'White - Book Paper #50 (70gsm)', 'None', 13, '2025-07-18 07:11:24', NULL, 'completed', '2025-07-29 03:30:55', 'Force Central Field Dist. Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(237, '2025-07-09', 'Force Central Field Dist. Inc.', '9020, Bldg. A, Mc Arthur Highway, Brgy. Tikay, Malolos City, Bulacan, 3000', 'Ms. Annibeth Espino', '09453879265', 'CKAS Sales Order', 2, 500, 'whole', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 1, '.', 'Custom', '', 'White - Book Paper #50 (70gsm)', 'None', 13, '2025-07-18 07:19:07', NULL, 'completed', '2025-07-29 03:28:07', 'Force Central Field Dist. Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'Online', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(238, '2025-07-01', 'Caniogan Creditt & Development Cooperative - Sta Maria', 'Corner C De Jesus St., Brgy. Poblacion, Santa Maria, Bulacan, 3003', 'Cecille Dooma', 'Messenger', 'Provisional Receipt', 120, 50, '1/6', 'LONG', '', 'Carbonless', 2, '2549901-255900', 'Booklet', '', 'Top White,Bottom Yellow', 'None', 13, '2025-07-18 07:33:25', NULL, 'completed', '2025-07-29 03:21:18', 'Caniogan Creditt & Development Cooperative', '25B - Sta. Maria, Bulacan (now RDO East Bulacan)', '001-648-627-00013', 'Online', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(239, '2025-07-18', 'ABC Roof Master', 'Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Alexander Bagangan', '0936-737-2819', 'Delivery Receipt ', 10, 50, '1/3', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 2, '0001-0500', 'Booklet', '', 'White - Bond Paper Gn050,Blue - Star Onion Skin', 'None', 11, '2025-07-18 07:47:53', NULL, 'completed', '2025-08-01 02:28:31', 'Alexander Bagangan', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'WALK-IN', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(240, '2025-06-28', 'Ria Farms Aqua Inc.', 'Brgy. Carillo, Hagonoy, Bulacan, 3002', 'Ria Farms Aqua Inc.', '9189306006', 'Sales Invoice', 20, 50, '1/2', 'LONG', '', 'Carbonless', 3, '00001-1000', 'Booklet', '', 'Top White, Middle Yellow, Bottom Green', 'None', 13, '2025-07-18 08:08:05', NULL, 'completed', '2025-07-30 06:53:05', 'Ria Farms Aqua Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '678-631-425-00000', 'Online', 'NONVAT', '25AAU20250000008464', '2025-06-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(241, '2025-07-18', 'HDS Nail Salon - Main', 'SM Center Pulilan, Plaridel-Pulilan Diversion Road, Brgy. Sto. Cristo, Pulilan, Bulacan, 3005', 'ate Mhel', '0448167243', 'Service Invoice', 50, 50, '1/3', 'LONG', '', 'Carbonless', 2, '0001-2500', 'Booklet', '', 'Top White, Bottom Pink', 'None', 13, '2025-07-18 08:18:42', NULL, 'completed', '2025-07-30 06:54:17', 'Shane DJ. Enriquez', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '903-495-296-00000', 'Online', 'NONVAT', '25AAU20250000008376', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(242, '2025-07-02', 'ProAce International Philippines Inc.', 'Unit 23 A, LVN TownHomes P. Tuazon Blvd., Brgy. Kaunlaran, Quezon City, Metro Manila, 1114', 'Ate Mhel', '(044)8167243', 'Collection Receipt', 10, 50, '1/3', 'LONG', '', 'Carbonless', 3, '001-500', 'Booklet', '', 'Top White, Middle Blue, Bottom Yellow', 'None', 13, '2025-07-18 08:31:59', NULL, 'completed', '2025-07-30 06:55:09', 'ProAce International Philippines Inc.', '038 - North Quezon City', '610-406-921-00000', 'Online', 'VAT', '25AAU20250000004252', '2025-06-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(243, '2025-07-03', 'Force Central Field Dist. Inc. - Valenzuela', 'Gen. Luis St., Brgy. Bagbaguin, Valenzuela, Metro Manila, 3000', 'Ms. Annibeth Espino', '09453879265', 'SO PSS', 4, 500, 'whole', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 1, '.', 'Custom', '', 'White - Book Paper #50 (70gsm)', 'None', 13, '2025-07-18 08:39:00', NULL, 'pending', NULL, 'Force Central Field Dist. Inc.', '', '', 'Online', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(244, '2025-07-18', 'Jekotel Food Hub -Branch 1', 'Level 4, ROBINSONS PLACE, Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Jester Maverick Pingol', '09066249222', 'Sales Invoice', 20, 50, '1/4', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 2, '2001-3000', 'Booklet', '', 'White - Bond Paper,Yellow - Star Onion Skin', 'None', 15, '2025-07-18 08:41:41', NULL, 'completed', '2025-07-18 08:44:34', 'Jester Maverick T. Pingol', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '312-302-793-00001', 'WALK-IN', 'NONVAT', '25AAU20250000009239', '2025-07-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(245, '2025-08-01', 'Force Central Field Dist. Inc. - Valenzuela', 'Gen. Luis St., Brgy. Bagbaguin, Valenzuela, Metro Manila, 1114', 'Ms. Annibeth Espino', '09453879265', 'MTAS & KAS', 3, 500, 'whole', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 1, '.', 'Custom', '', 'White - Book Paper #50 (70gsm)', 'None', 13, '2025-07-18 08:45:55', NULL, 'completed', '2025-08-02 07:24:17', 'Force Central Field Dist. Inc.', '', '', 'Online', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(246, '2025-07-18', 'Jekotel Food Hub - Main', 'Level 4, ROBINSONS PLACE, Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Jester Maverick Pingol', '09066249222', 'Sales Invoice', 20, 50, '1/4', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 2, '001-1000', 'Booklet', '', 'White - Bond Paper,Yellow - Star Onion Skin', 'None', 15, '2025-07-18 08:49:51', NULL, 'completed', '2025-07-18 08:56:42', 'Jester Maverick T. Pingol', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '312-302-793-00000', 'WALK-IN', 'NON-VAT EXEMPT', '25AAU20250000009262', '2025-07-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(247, '2025-07-19', 'RRS ROOFING MATERIALS MATERIALS - BRANCH 1', 'Purok 4, Diversion Road, Brgy. Mabolo, Malolos City, Bulacan, 3000', 'Ricardo T. Marcelino', '09338527421', 'Delivery Receipt', 50, 50, '1/4', 'LONG', '', 'Carbonless', 2, '6501-9000', 'Booklet', '', 'Top White,Bottom Yellow', 'None', 15, '2025-07-19 07:35:51', NULL, 'completed', '2025-07-30 06:57:29', 'RICARDO T. MARCELINO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '279-328-468-00001', 'Aida', 'NON-VAT EXEMPT', '25AAU20250000009399', '2025-07-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(248, '2025-07-21', 'JFL Agri-Ventures Supplies', '550, Lucero St., Brgy. MABOLO, Malolos City, Bulacan, 3000', 'Maam Hannah Omilan', '09923318932', 'Proof of Delivery', 100, 50, 'whole', '11X17', '', 'Carbonless', 4, 'S - 000001', 'Pad', '', 'Top White, Middle Yellow, Middle Blue, Bottom Pink', 'None', 15, '2025-07-21 03:27:22', NULL, 'completed', '2025-07-24 05:49:38', 'Jaime K. Crisostomo', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '157-339-724-00000', 'Online', 'VAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(249, '2025-07-21', 'Evo Riders Club Philippines', 'Brgy. Mabolo, Malolos City, Bulacan, 3000', ' Third da Man/ Tristan', '09774533014', 'Raffle Ticket', 100, 50, '1/6', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 2, '00001-10000', 'Booklet', '', 'White - Book Paper #50 (70gsm),White - Book Paper #50 (70gsm)', '1 Staple Only\r\n100 sets per booklet\r\nBook 50', 15, '2025-07-24 05:36:10', NULL, 'completed', '2025-08-04 01:53:28', 'Third Hernandez', '', '', 'Online', 'NONVAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(253, '2025-07-04', 'Zoilo Teresita Trading Inc.', 'Mc Arthur Highway, Brgy. Longos, Malolos City, Bulacan, 3000', 'Wennie', 'Messenger', 'Purchase Order', 300, 50, '1/4', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 2, '80001-95000', 'Booklet', '', 'White - Bond Paper, Green - Star Onion Skin', 'None', 13, '2025-07-30 01:09:42', NULL, 'completed', '2025-07-31 02:49:26', 'Zoilo Teresita Trading Inc.', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '603-515-451-00001', 'Online', 'VAT', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(255, '2025-08-01', 'Perci\'s Battery and General Merchandise', 'Mabini St. Purok 3, Brgy. Mojon, Malolos City, Bulacan, 3000', 'Percival Clemente', '09233129288', 'Sales Invoice', 20, 50, '1/4', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 2, '3501-4500', 'Booklet', '', 'White - Bond Paper, Yellow - Star Onion Skin', 'None', 15, '2025-08-01 02:27:24', NULL, 'pending', NULL, 'Percival C. Clemente', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '244-210-101-00000', 'WALK-IN', 'NON-VAT EXEMPT', '25AAU20250000009609', '2025-07-30', 'Bulacan', 'Malolos City', 'Mojon', 'Mabini St. Purok 3', '', '', '3000'),
(256, '2025-08-01', 'Hailey\'s Haven Nail Salon and Spa - Robinson_00001', '3-03317 Robinsons Place, Brgy. Sumapang Matanda, Malolos City, Bulacan, 3000', 'Marvin De Vera', '0448167243', 'Service Invoice', 50, 50, '1/3', 'LONG', '', 'Carbonless', 2, '001-2500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 15, '2025-08-02 01:15:25', NULL, 'pending', NULL, 'MARVIN D. V. VALERIANO', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '284-264-259-00001', 'Mhel', 'NON-VAT EXEMPT', '25AAU20250000009727', '2025-07-31', 'Bulacan', 'Malolos City', 'Sumapang Matanda', '', '', '3-03317 Robinsons Place', '3000'),
(257, '2025-08-02', 'Athens LPG Trading', '118 M. Crisostomo, RVC Bldg. Fausta Rd., Brgy. San Vicente, Malolos City, Bulacan, 3000', 'Michelle Anne Vicente', '0448167243', 'Sales Invoice', 20, 50, '1/4', 'LONG', '', 'Carbonless', 2, '501-1500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 15, '2025-08-02 01:56:31', NULL, 'for_delivery', NULL, 'Michelle Anne R. Vicente', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '239-855-555-00000', 'Online', 'VAT', '25AAU20250000009606', '2025-07-30', 'Bulacan', 'Malolos City', 'San Vicente', 'RVC Bldg. Fausta Rd.', '', '118 M. Crisostomo', '3000'),
(258, '2025-08-02', 'Force Central Field Dist. Inc. - Valenzuela', 'Brgy. Bagbaguin, Valenzuela, Metro Manila', 'Ms. Annibeth Espino', '09453879265', 'Employee Record', 1, 1, 'whole', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 1, '.', 'Custom', '', 'White - Book Paper #50 (70gsm)', 'None', 15, '2025-08-02 07:21:24', NULL, 'for_delivery', NULL, 'Force Central Field Dist. Inc', '', '', 'Online', 'VAT', '', NULL, 'Metro Manila', 'Valenzuela', 'Bagbaguin', '', '', '', ''),
(259, '2025-08-02', 'MZ Pangilinan Wood and Metal Casket', 'Brgy. Sto.Tomas, Mabalacat, Pampanga, 2000', 'Mariella Pangilinan', '09929975410', 'Sales Invoice_w/out Permit', 10, 50, '1/2', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 2, '0001-0500', 'Booklet', '', 'White - Bond Paper, Yellow - Star Onion Skin', 'None', 15, '2025-08-02 07:31:47', NULL, 'pending', NULL, 'Mariella Pangilinan', '21A - North Pampanga', '447-663-563-00000', 'Online', 'NON-VAT EXEMPT', '', NULL, 'Pampanga', 'Mabalacat', 'Sto.Tomas', '', '', '', '2000'),
(260, '2025-08-04', 'Seadragon Outdoor Products Trading', 'Blas Ople Road, Brgy. Bulihan, Malolos City, Bulacan, 3000', 'Rosemarie M. Palacio', '0448167243', 'Sales Invoice', 10, 50, '1/4', 'LONG', '', 'Carbonless', 2, '001-500', 'Booklet', '', 'Top White, Bottom Yellow', 'None', 15, '2025-08-04 01:53:07', NULL, 'pending', NULL, 'Rosemarie M. Palacio', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '749-298-412-00000', 'Mhel', 'NON-VAT EXEMPT', '25AAU20250000009612', '2025-07-30', 'Bulacan', 'Malolos City', 'Bulihan', 'Blas Ople Road', '', '', '3000'),
(261, '2025-08-04', 'Sta. Isabel Trading', 'Purok 6, Brgy. Sillawit, Cauayan, Isabela, 3305', 'Maam Hannah', '09923318932', 'S.O.A.', 100, 50, '1/2', 'SHORT', '', 'Carbonless', 4, '45801-50800', 'Pad', '', 'Top White,Middle Green,Middle Blue,Bottom Pink', 'None', 13, '2025-08-04 05:48:20', NULL, 'pending', NULL, 'Ivan Nikolai C. Reyes', '015 - Naguilian, Isabela', '403-062-433-00000', 'Online', 'NONVAT', '', NULL, 'Isabela', 'Cauayan', 'Sillawit', 'Purok 6', '', '', '3305'),
(263, '2025-08-06', 'V.B. COLUMNA CONSTRUCTION CORPORATION', '33 VBC Bldg., Violeta Village Azucena St., Brgy. Sta.Cruz, Guiguinto, Bulacan, 3015', 'Mica', '+639470220693', 'Delivery Receipt ', 5, 50, 'whole', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 3, '23751 - 24000', 'Pad', '', 'White - Bond Paper Gn050, Blue - Star Onion Skin, Yellow - Star Onion Skin', 'None', 11, '2025-08-06 06:50:18', NULL, 'pending', NULL, 'V.B. COLUMNA CONSTRUCTION CORPORATION', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'AMDP', 'VAT', '', NULL, 'Bulacan', 'Guiguinto', 'Sta.Cruz', 'Violeta Village Azucena St.', '33 VBC Bldg.', '', '3015'),
(264, '2025-08-06', 'Bulacan Provincial Hospital (BMC) Multi Purpose Coop', 'BMC, Brgy. Mojon, Malolos City, Bulacan, 3000', 'Anthony Dan Villegas', '9673682820 ', 'Loan Ledger', 100, 1, '1/6', '22.5X28.5', '8.25X5.5', 'Special Paper', 1, '0', 'Custom', 'bind by 100', 'Imp Bristol 120#', 'None', 11, '2025-08-06 08:12:06', NULL, 'pending', NULL, 'Bulacan Provincial Hospital (BMC) Multi Purpose Coop', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '005-314-464-000', 'AMDP', 'NONVAT', '', NULL, 'Bulacan', 'Malolos City', 'Mojon', '', 'BMC', '', '3000'),
(265, '2025-08-06', 'V.B. COLUMNA CONSTRUCTION CORPORATION', '33 VBC Bldg., Violeta Village Azucena St., Brgy. Sta.Cruz, Guiguinto, Bulacan, 3015', 'Mica', '+639470220693', 'Delivery Receipt ', 5, 50, 'whole', 'F4/LONG (8.5X14)', '', 'Ordinary Paper', 3, '23751 - 24000', 'Pad', '', 'White - Bond Paper Gn050, Blue - Star Onion Skin, Yellow - Star Onion Skin', 'None', 16, '2025-08-16 01:57:06', NULL, 'pending', NULL, 'V.B. COLUMNA CONSTRUCTION CORPORATION', '25A - Plaridel, Bulacan (now RDO West Bulacan)', '', 'AMDP', 'VAT', '', NULL, 'Bulacan', 'Guiguinto', 'Sta.Cruz', 'Violeta Village Azucena St.', '33 VBC Bldg.', '', '3015');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `province` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `province`, `city`) VALUES
(1, 'Ilocos Norte', 'Laoag'),
(2, 'Ilocos Norte', 'Batac'),
(3, 'Ilocos Norte', 'Paoay'),
(4, 'Ilocos Norte', 'Bacarra'),
(5, 'Ilocos Sur', 'Vigan'),
(6, 'Ilocos Sur', 'Candon'),
(7, 'Ilocos Sur', 'Narvacan'),
(8, 'La Union', 'San Fernando'),
(9, 'La Union', 'Bauang'),
(10, 'La Union', 'Agoo'),
(11, 'Pangasinan', 'Dagupan'),
(12, 'Pangasinan', 'Urdaneta'),
(13, 'Pangasinan', 'San Carlos'),
(14, 'Pangasinan', 'Alaminos'),
(15, 'Cagayan', 'Tuguegarao'),
(16, 'Cagayan', 'Aparri'),
(17, 'Isabela', 'Ilagan'),
(18, 'Isabela', 'Cauayan'),
(19, 'Isabela', 'Santiago'),
(20, 'Nueva Vizcaya', 'Bayombong'),
(21, 'Nueva Vizcaya', 'Solano'),
(22, 'Quirino', 'Cabarroguis'),
(23, 'Batanes', 'Basco'),
(24, 'Nueva Ecija', 'Cabanatuan'),
(25, 'Nueva Ecija', 'Gapan'),
(26, 'Nueva Ecija', 'San Jose'),
(27, 'Tarlac', 'Tarlac City'),
(28, 'Tarlac', 'Capas'),
(29, 'Tarlac', 'Concepcion'),
(30, 'Pampanga', 'San Fernando'),
(31, 'Pampanga', 'Angeles'),
(32, 'Pampanga', 'Mabalacat'),
(33, 'Zambales', 'Olongapo'),
(34, 'Zambales', 'Iba'),
(40, 'Bataan', 'Balanga'),
(41, 'Bataan', 'Orani'),
(42, 'Aurora', 'Baler'),
(43, 'Metro Manila', 'Manila'),
(44, 'Metro Manila', 'Quezon City'),
(45, 'Metro Manila', 'Makati'),
(46, 'Metro Manila', 'Pasig'),
(47, 'Metro Manila', 'Taguig'),
(48, 'Metro Manila', 'Caloocan'),
(49, 'Metro Manila', 'Parañaque'),
(50, 'Metro Manila', 'Mandaluyong'),
(51, 'Metro Manila', 'Pasay'),
(52, 'Metro Manila', 'Las Piñas'),
(53, 'Metro Manila', 'Marikina'),
(54, 'Metro Manila', 'Muntinlupa'),
(55, 'Metro Manila', 'Navotas'),
(56, 'Metro Manila', 'San Juan'),
(57, 'Metro Manila', 'Valenzuela'),
(58, 'Cavite', 'Dasmariñas'),
(59, 'Cavite', 'Bacoor'),
(60, 'Cavite', 'Imus'),
(61, 'Cavite', 'Tagaytay'),
(62, 'Laguna', 'San Pablo'),
(63, 'Laguna', 'Santa Rosa'),
(64, 'Laguna', 'Calamba'),
(65, 'Laguna', 'Biñan'),
(66, 'Batangas', 'Batangas City'),
(67, 'Batangas', 'Lipa'),
(68, 'Batangas', 'Tanauan'),
(69, 'Rizal', 'Antipolo'),
(70, 'Rizal', 'Cainta'),
(71, 'Rizal', 'Taytay'),
(72, 'Quezon', 'Lucena'),
(73, 'Quezon', 'Tayabas'),
(74, 'Palawan', 'Puerto Princesa'),
(75, 'Occidental Mindoro', 'Mamburao'),
(76, 'Oriental Mindoro', 'Calapan'),
(77, 'Romblon', 'Romblon'),
(78, 'Romblon', 'Odiongan'),
(79, 'Marinduque', 'Boac'),
(80, 'Camarines Norte', 'Daet'),
(81, 'Camarines Sur', 'Naga'),
(82, 'Camarines Sur', 'Iriga'),
(83, 'Albay', 'Legazpi'),
(84, 'Albay', 'Tabaco'),
(85, 'Albay', 'Ligao'),
(86, 'Sorsogon', 'Sorsogon City'),
(87, 'Catanduanes', 'Virac'),
(88, 'Masbate', 'Masbate City'),
(89, 'Aklan', 'Kalibo'),
(90, 'Aklan', 'Malay'),
(91, 'Antique', 'San Jose de Buenavista'),
(92, 'Capiz', 'Roxas'),
(93, 'Iloilo', 'Iloilo City'),
(94, 'Iloilo', 'Passi'),
(95, 'Negros Occidental', 'Bacolod'),
(96, 'Negros Occidental', 'Talisay'),
(97, 'Negros Occidental', 'Sagay'),
(98, 'Cebu', 'Cebu City'),
(99, 'Cebu', 'Mandaue'),
(100, 'Cebu', 'Lapu-Lapu'),
(101, 'Cebu', 'Talisay'),
(102, 'Bohol', 'Tagbilaran'),
(103, 'Negros Oriental', 'Dumaguete'),
(104, 'Negros Oriental', 'Bayawan'),
(105, 'Leyte', 'Tacloban'),
(106, 'Leyte', 'Ormoc'),
(107, 'Southern Leyte', 'Maasin'),
(108, 'Eastern Samar', 'Borongan'),
(109, 'Northern Samar', 'Catarman'),
(110, 'Samar', 'Calbayog'),
(111, 'Samar', 'Catbalogan'),
(112, 'Zamboanga del Norte', 'Dipolog'),
(113, 'Zamboanga del Norte', 'Dapitan'),
(114, 'Zamboanga del Sur', 'Pagadian'),
(115, 'Zamboanga Sibugay', 'Ipil'),
(116, 'Misamis Occidental', 'Oroquieta'),
(117, 'Misamis Occidental', 'Ozamiz'),
(118, 'Misamis Oriental', 'Cagayan de Oro'),
(119, 'Misamis Oriental', 'Gingoog'),
(120, 'Bukidnon', 'Malaybalay'),
(121, 'Bukidnon', 'Valencia'),
(122, 'Lanao del Norte', 'Iligan'),
(123, 'Lanao del Sur', 'Marawi'),
(124, 'Maguindanao', 'Buluan'),
(125, 'Cotabato', 'Kidapawan'),
(126, 'Sultan Kudarat', 'Tacurong'),
(127, 'South Cotabato', 'Koronadal'),
(128, 'South Cotabato', 'General Santos'),
(129, 'Sarangani', 'Alabel'),
(130, 'Agusan del Norte', 'Butuan'),
(131, 'Agusan del Sur', 'Bayugan'),
(132, 'Surigao del Norte', 'Surigao City'),
(133, 'Surigao del Sur', 'Tandag'),
(134, 'Dinagat Islands', 'San Jose'),
(135, 'Basilan', 'Isabela City'),
(136, 'Sulu', 'Jolo'),
(137, 'Tawi-Tawi', 'Bongao'),
(138, 'Bulacan', 'Angat'),
(139, 'Bulacan', 'Balagtas'),
(140, 'Bulacan', 'Baliuag'),
(141, 'Bulacan', 'Bocaue'),
(142, 'Bulacan', 'Bulacan'),
(143, 'Bulacan', 'Bustos'),
(144, 'Bulacan', 'Calumpit'),
(145, 'Bulacan', 'Doña Remedios Trinidad'),
(146, 'Bulacan', 'Guiguinto'),
(147, 'Bulacan', 'Hagonoy'),
(148, 'Bulacan', 'Malolos City'),
(149, 'Bulacan', 'Marilao'),
(150, 'Bulacan', 'Meycauayan City'),
(151, 'Bulacan', 'Norzagaray'),
(152, 'Bulacan', 'Obando'),
(153, 'Bulacan', 'Pandi'),
(154, 'Bulacan', 'Paombong'),
(155, 'Bulacan', 'Plaridel'),
(156, 'Bulacan', 'Pulilan'),
(157, 'Bulacan', 'San Ildefonso'),
(158, 'Bulacan', 'San Jose del Monte City'),
(159, 'Bulacan', 'San Miguel'),
(160, 'Bulacan', 'San Rafael'),
(161, 'Bulacan', 'Santa Maria');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `created_at`, `used`) VALUES
(16, 20, 'd39b93bda943f6a498685ef97e409e577a3d0faeb4568c8a2ff87d7c3d4df827', '2025-08-16 15:18:45', '2025-08-16 06:18:45', 0),
(23, 19, '8a207e3c93f2f28ea91fa65e80778cae8c9b6aee36e216f06eab8d0ed138fc29', '2025-08-16 15:35:01', '2025-08-16 06:35:01', 0);

-- --------------------------------------------------------

--
-- Table structure for table `personal_customers`
--

CREATE TABLE `personal_customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address_line1` varchar(100) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal_customers`
--

INSERT INTO `personal_customers` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `age`, `gender`, `birthdate`, `contact_number`, `address_line1`, `city`, `province`, `zip_code`) VALUES
(2, 18, 'Erine George', 'C.', 'Lumbad', 21, 'Male', '2003-10-23', '09281248185', 'PHASE 2 BLK 95 LOT 6 METROPOLIS AVENUE', 'CALUMPIT', 'BULACAN', '3003'),
(3, 19, 'Asha', '', 'Lumbad', 21, 'Female', '2004-06-20', '09281248185', '', 'Malolos', 'Bulacan', '');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_type` varchar(100) DEFAULT NULL,
  `product_group` varchar(100) DEFAULT NULL,
  `product_name` varchar(100) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_type`, `product_group`, `product_name`, `unit_price`, `created_by`) VALUES
(29, 'Carbonless', 'LONG', 'Top White', 274.03, 12),
(30, 'Carbonless', 'LONG', 'Middle Yellow', 291.53, 12),
(31, 'Carbonless', 'LONG', 'Middle Pink', 291.53, 12),
(32, 'Carbonless', 'LONG', 'Middle Green', 288.54, 12),
(33, 'Carbonless', 'LONG', 'Middle Blue', 288.54, 12),
(34, 'Carbonless', 'LONG', 'Bottom Yellow', 272.78, 12),
(35, 'Carbonless', 'LONG', 'Bottom Pink', 272.78, 12),
(36, 'Carbonless', 'LONG', 'Bottom Green', 269.79, 12),
(37, 'Carbonless', 'LONG', 'Bottom Blue', 269.79, 12),
(38, 'Carbonless', 'SHORT', 'Top White', 222.62, 12),
(39, 'Carbonless', 'SHORT', 'Middle Yellow', 232.59, 12),
(40, 'Carbonless', 'SHORT', 'Middle Blue', 232.59, 12),
(41, 'Carbonless', 'SHORT', 'Middle Pink', 232.59, 12),
(42, 'Carbonless', 'SHORT', 'Middle Green', 232.59, 12),
(43, 'Carbonless', 'SHORT', 'Bottom Pink', 236.62, 12),
(44, 'Carbonless', 'SHORT', 'Bottom Yellow', 217.59, 12),
(45, 'Carbonless', 'SHORT', 'Bottom Green', 217.59, 12),
(46, 'Carbonless', 'SHORT', 'Bottom Blue', 217.59, 12),
(47, 'Carbonless', '11X17', 'Top White', 428.39, 12),
(48, 'Carbonless', '11X17', 'Middle Yellow', 456.39, 12),
(49, 'Carbonless', '11X17', 'Middle Blue', 456.39, 12),
(50, 'Carbonless', '11X17', 'Bottom Pink', 426.39, 12),
(51, 'Carbonless', '11X17', 'Middle Pink', 456.39, 12),
(52, 'Carbonless', '11X17', 'Middle Green', 456.39, 12),
(53, 'Carbonless', '11X17', 'Bottom Yellow', 427.79, 12),
(54, 'Carbonless', '11X17', 'Bottom Green', 426.39, 12),
(55, 'Carbonless', '11X17', 'Bottom Blue', 426.39, 12),
(56, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Book Paper #60 (80gsm)', 1.00, 12),
(57, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Book Paper #50 (70gsm)', 181.97, 12),
(58, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Book Paper #40 (60gsm)', 1.00, 12),
(59, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Starbond (50gsm)', 1.00, 12),
(60, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'Yellow - Star Onion Skin', 168.30, 12),
(61, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'Pink - Star Onion Skin', 168.30, 12),
(62, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'Green - Star Onion Skin', 168.30, 12),
(63, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'Blue - Star Onion Skin', 167.56, 12),
(64, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Newsprint Lmn 48.8', 1.00, 12),
(65, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Newsprint Jn048.8', 1.00, 12),
(66, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Newsprint Jn052', 144.84, 12),
(67, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Book Paper #60 (80gsm)', 1.00, 12),
(68, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Book Paper #50 (70gsm)', 151.37, 12),
(69, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Book Paper #40 (60gsm)', 1.00, 12),
(70, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Starbond (50gsm)', 1.00, 12),
(71, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Newsprint Lmn 48.8', 94.96, 12),
(72, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Newsprint Jn048.8', 1.00, 12),
(73, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Newsprint Jn052', 1.00, 12),
(74, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'Yellow - Star Onion Skin', 139.74, 12),
(75, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'Pink - Star Onion Skin', 138.72, 12),
(76, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'Blue - Star Onion Skin', 139.74, 12),
(77, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'Green - Star Onion Skin', 139.74, 12),
(78, 'Ordinary Paper', 'A4 (8.25X11.65)', 'White - Book Paper #60 (80gsm)', 1.00, 12),
(79, 'Ordinary Paper', 'A4 (8.25X11.65)', 'White - Book Paper #50 (70gsm)', 794.68, 12),
(80, 'Ordinary Paper', 'A4 (8.25X11.65)', 'White - Book Paper #40 (60gsm)', 1.00, 12),
(81, 'Ordinary Paper', 'A4 (8.25X11.65)', 'White - Starbond (50gsm)', 1.00, 12),
(82, 'Ordinary Paper', 'A4 (8.25X11.65)', 'White - Newsprint Lmn 48.8', 1.00, 12),
(83, 'Ordinary Paper', 'A4 (8.25X11.65)', 'White - Newsprint Jn048.8', 1.00, 12),
(84, 'Ordinary Paper', 'A4 (8.25X11.65)', 'White - Newsprint Jn052', 1.00, 12),
(85, 'Ordinary Paper', 'A4 (8.25X11.65)', 'Yellow - Star Onion Skin', 1.00, 12),
(86, 'Ordinary Paper', 'A4 (8.25X11.65)', 'Pink - Star Onion Skin', 1.00, 12),
(87, 'Ordinary Paper', 'A4 (8.25X11.65)', 'Green - Star Onion Skin', 1.00, 12),
(88, 'Ordinary Paper', 'A4 (8.25X11.65)', 'Blue - Star Onion Skin', 1.00, 12),
(89, 'Ordinary Paper', '11X17', 'White - Book Paper #60 (80gsm)', 1.00, 12),
(90, 'Ordinary Paper', '11X17', 'White - Book Paper #50 (70gsm)', 1.00, 12),
(91, 'Ordinary Paper', '11X17', 'White - Book Paper #40 (60gsm)', 1.00, 12),
(92, 'Ordinary Paper', '11X17', 'White - Starbond (50gsm)', 1.00, 12),
(93, 'Ordinary Paper', '11X17', 'White - Newsprint Jn048.8', 1.00, 12),
(94, 'Ordinary Paper', '11X17', 'White - Newsprint Jn052', 197.70, 12),
(95, 'Ordinary Paper', '11X17', 'White - Newsprint Lmn 48.8', 1.00, 12),
(96, 'Ordinary Paper', '11X17', 'Yellow - Star Onion Skin', 276.26, 12),
(98, 'Ordinary Paper', '11X17', 'Green - Star Onion Skin', 1.00, 12),
(99, 'Ordinary Paper', '11X17', 'Blue - Star Onion Skin', 1.00, 12),
(101, 'Ordinary Paper', '11X17', 'Pink - Star Onion Skin', 283.40, 11),
(102, 'Ordinary Paper', '14X17', 'White - Newsprint Jn052', 240.05, 11),
(103, 'Ordinary Paper', '14X17', 'Pink - Star Onion Skin', 352.25, 11),
(104, 'Ordinary Paper', '14X17', 'Yellow - Star Onion Skin', 352.25, 11),
(105, 'Special Paper', '22.5X28.5', 'Imp Bristol 100#', 7.24, 11),
(106, 'Special Paper', '22.5X28.5', 'Imp Bristol 120#', 9.39, 11),
(107, 'Consumables', 'INK OFFSET', 'Reflex Blue 1kg Tokyo', 406.27, 11),
(108, 'Consumables', 'INK OFFSET', 'Black - Process Fd 2kg. Toyo', 666.06, 11),
(109, 'Consumables', 'INK OFFSET', 'Red - Fire 1kg Alisk', 507.96, 11),
(110, 'Special Paper', '31X43', 'Foldcote Sw Cal 15', 13.04, 11),
(111, 'Special Paper', '26X38', 'Coated Board C2S 220#', 12.20, 11),
(112, 'Special Paper', '36X24', 'High Gloss Sticker', 12.25, 11),
(113, 'Special Paper', '22.5X28.5', 'Imp Bristol 74# White', 3.60, 11),
(114, 'Special Paper', '38X26', 'Chipboard #120', 8.33, 11),
(115, 'Special Paper', '25X38', 'Coated Paper C2S 70#', 7.64, 13),
(116, 'Special Paper', '25X38', 'Coated Paper C2S 80#', 8.80, 13),
(117, 'Special Paper', '25X38', 'Coated Paper C2S 120#', 12.24, 13),
(118, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Bond Paper', 144.84, 13),
(119, 'Special Paper', '22.5X28.5', 'Imp Bristol 100# Pink', 7.75, 15),
(120, 'Special Paper', '22.5X28.5', 'Imp Bristol 100# Blue', 7.75, 15),
(121, 'Special Paper', '38X25', 'Imp Natural Kraft Paper (se)', 4.03, 15),
(122, 'Ordinary Paper', 'QTO/SHORT (8.5X11)', 'White - Bond Paper Gn050', 122.40, 15),
(123, 'Special Paper', '38X25', 'Matt C2S 80#', 9.04, 15),
(124, 'Ordinary Paper', 'F4/LONG (8.5X14)', 'White - Bond Paper Gn050', 144.84, 15),
(125, 'Special Paper', '31X43', 'Imp Claycoated Cal 10', 39.78, 15);

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
  `usage_note` text DEFAULT NULL,
  `job_order_id` int(11) DEFAULT NULL,
  `spoilage_sheets` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usage_logs`
--

INSERT INTO `usage_logs` (`id`, `product_id`, `log_date`, `used_sheets`, `used_reams`, `usage_note`, `job_order_id`, `spoilage_sheets`) VALUES
(172, 29, '2025-01-02', 250.00, NULL, 'Auto-deducted from job order for RaxNet Trucking Services', 129, 0),
(173, 37, '2025-01-02', 250.00, NULL, 'Auto-deducted from job order for RaxNet Trucking Services', 129, 0),
(174, 29, '2025-01-06', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 130, 0),
(175, 30, '2025-01-06', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 130, 0),
(176, 35, '2025-01-06', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 130, 0),
(177, 29, '2025-01-06', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 131, 0),
(178, 37, '2025-01-06', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 131, 0),
(179, 29, '2025-01-06', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 132, 0),
(180, 37, '2025-01-06', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 132, 0),
(181, 29, '2025-01-13', 2500.00, NULL, 'Auto-deducted from job order for St. John The Baptist Catholic School Inc.', 133, 0),
(182, 35, '2025-01-13', 2500.00, NULL, 'Auto-deducted from job order for St. John The Baptist Catholic School Inc.', 133, 0),
(185, 29, '2025-01-15', 250.00, NULL, 'Auto-deducted from job order for Pearl Orient Fruits and Vegetables Trading', 135, 0),
(186, 31, '2025-01-15', 250.00, NULL, 'Auto-deducted from job order for Pearl Orient Fruits and Vegetables Trading', 135, 0),
(187, 34, '2025-01-15', 250.00, NULL, 'Auto-deducted from job order for Pearl Orient Fruits and Vegetables Trading', 135, 0),
(188, 29, '2025-01-15', 166.00, NULL, 'Auto-deducted from job order for Santos Dental Care Center', 136, 0),
(189, 34, '2025-01-15', 166.00, NULL, 'Auto-deducted from job order for Santos Dental Care Center', 136, 0),
(190, 38, '2025-01-15', 125.00, NULL, 'Auto-deducted from job order for Tita Lev\'s Water Refilling Station', 137, 0),
(191, 44, '2025-01-15', 125.00, NULL, 'Auto-deducted from job order for Tita Lev\'s Water Refilling Station', 137, 0),
(192, 29, '2025-01-15', 166.00, NULL, 'Auto-deducted from job order for Eldrin Arbe A. Quiñones, M.D.', 138, 0),
(193, 34, '2025-01-15', 166.00, NULL, 'Auto-deducted from job order for Eldrin Arbe A. Quiñones, M.D.', 138, 0),
(200, 38, '2025-02-07', 250.00, NULL, 'Auto-deducted from job order for Medhaus Pharma and Medical Supplies Training', 142, 0),
(201, 44, '2025-02-07', 250.00, NULL, 'Auto-deducted from job order for Medhaus Pharma and Medical Supplies Training', 142, 0),
(202, 29, '2025-02-08', 166.00, NULL, 'Auto-deducted from job order for MER LOT LEASING', 143, 0),
(203, 34, '2025-02-08', 166.00, NULL, 'Auto-deducted from job order for MER LOT LEASING', 143, 0),
(204, 38, '2025-02-12', 125.00, NULL, 'Auto-deducted from job order for Selah Musical InstrumentsTrading', 144, 0),
(205, 44, '2025-02-12', 125.00, NULL, 'Auto-deducted from job order for Selah Musical InstrumentsTrading', 144, 0),
(213, 29, '2025-03-04', 166.00, NULL, 'Auto-deducted from job order for ESC SWIMMINGPOOL-DESIGN&BUILD CONSTRUCTION SERVICES ', 148, 0),
(214, 34, '2025-03-04', 166.00, NULL, 'Auto-deducted from job order for ESC SWIMMINGPOOL-DESIGN&BUILD CONSTRUCTION SERVICES ', 148, 0),
(215, 29, '2025-03-05', 250.00, NULL, 'Auto-deducted from job order for NVX Enterprises', 149, 0),
(216, 32, '2025-03-05', 250.00, NULL, 'Auto-deducted from job order for NVX Enterprises', 149, 0),
(217, 34, '2025-03-05', 250.00, NULL, 'Auto-deducted from job order for NVX Enterprises', 149, 0),
(218, 29, '2025-03-07', 333.00, NULL, 'Auto-deducted from job order for EZSIPNAYAN LEARNING CENTER ', 150, 0),
(219, 34, '2025-03-07', 333.00, NULL, 'Auto-deducted from job order for EZSIPNAYAN LEARNING CENTER ', 150, 0),
(220, 29, '2025-03-07', 166.00, NULL, 'Auto-deducted from job order for NICAT METAL BUILDERS OPC', 151, 0),
(221, 34, '2025-03-07', 166.00, NULL, 'Auto-deducted from job order for NICAT METAL BUILDERS OPC', 151, 0),
(222, 29, '2025-03-07', 166.00, NULL, 'Auto-deducted from job order for RFH Lot for Lease', 152, 0),
(223, 36, '2025-03-07', 166.00, NULL, 'Auto-deducted from job order for RFH Lot for Lease', 152, 0),
(224, 38, '2025-03-07', 250.00, NULL, 'Auto-deducted from job order for Marizel Photo - Photo and Coverage', 153, 0),
(225, 44, '2025-03-07', 250.00, NULL, 'Auto-deducted from job order for Marizel Photo - Photo and Coverage', 153, 0),
(228, 38, '2025-03-13', 500.00, NULL, 'Auto-deducted from job order for TECHCONEK PHILIPPINES INC. ', 155, 0),
(229, 39, '2025-03-13', 500.00, NULL, 'Auto-deducted from job order for TECHCONEK PHILIPPINES INC. ', 155, 0),
(230, 43, '2025-03-13', 500.00, NULL, 'Auto-deducted from job order for TECHCONEK PHILIPPINES INC. ', 155, 0),
(235, 38, '2025-03-13', 250.00, NULL, 'Auto-deducted from job order for ROUTE 95 DINER ', 158, 0),
(236, 44, '2025-03-13', 250.00, NULL, 'Auto-deducted from job order for ROUTE 95 DINER ', 158, 0),
(237, 29, '2025-03-15', 166.00, NULL, 'Auto-deducted from job order for Atty. Julian Marvin V. Duba', 159, 0),
(238, 34, '2025-03-15', 166.00, NULL, 'Auto-deducted from job order for Atty. Julian Marvin V. Duba', 159, 0),
(239, 29, '2025-03-15', 166.00, NULL, 'Auto-deducted from job order for RMU Commercial Stall Leasing', 160, 0),
(240, 34, '2025-03-15', 166.00, NULL, 'Auto-deducted from job order for RMU Commercial Stall Leasing', 160, 0),
(241, 29, '2025-03-21', 625.00, NULL, 'Auto-deducted from job order for 360 DEGREES SYSTEMS CORPORATION ', 161, 0),
(242, 34, '2025-03-21', 625.00, NULL, 'Auto-deducted from job order for 360 DEGREES SYSTEMS CORPORATION ', 161, 0),
(243, 38, '2025-03-21', 500.00, NULL, 'Auto-deducted from job order for ALANO’S ENTERPRISES INCORPORATED ', 162, 0),
(244, 39, '2025-03-21', 500.00, NULL, 'Auto-deducted from job order for ALANO’S ENTERPRISES INCORPORATED ', 162, 0),
(245, 46, '2025-03-21', 500.00, NULL, 'Auto-deducted from job order for ALANO’S ENTERPRISES INCORPORATED ', 162, 0),
(246, 29, '2025-03-22', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 163, 0),
(247, 30, '2025-03-22', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 163, 0),
(248, 35, '2025-03-22', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 163, 0),
(249, 29, '2025-03-21', 125.00, NULL, 'Auto-deducted from job order for ALANO’S ENTERPRISES INCORPORATED ', 164, 0),
(250, 34, '2025-03-21', 125.00, NULL, 'Auto-deducted from job order for ALANO’S ENTERPRISES INCORPORATED ', 164, 0),
(251, 29, '2025-03-22', 3750.00, NULL, 'Auto-deducted from job order for SCD MARKETING & PRODUCT SOLUTIONS INC. ', 165, 0),
(252, 31, '2025-03-22', 3750.00, NULL, 'Auto-deducted from job order for SCD MARKETING & PRODUCT SOLUTIONS INC. ', 165, 0),
(253, 30, '2025-03-22', 3750.00, NULL, 'Auto-deducted from job order for SCD MARKETING & PRODUCT SOLUTIONS INC. ', 165, 0),
(254, 32, '2025-03-22', 3750.00, NULL, 'Auto-deducted from job order for SCD MARKETING & PRODUCT SOLUTIONS INC. ', 165, 0),
(255, 37, '2025-03-22', 3750.00, NULL, 'Auto-deducted from job order for SCD MARKETING & PRODUCT SOLUTIONS INC. ', 165, 0),
(256, 29, '2025-03-25', 333.00, NULL, 'Auto-deducted from job order for ADVENTURES HUB TRAVEL AND TOURS ', 166, 0),
(257, 34, '2025-03-25', 333.00, NULL, 'Auto-deducted from job order for ADVENTURES HUB TRAVEL AND TOURS ', 166, 0),
(260, 29, '2025-03-25', 1250.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 168, 0),
(261, 31, '2025-03-25', 1250.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 168, 0),
(262, 34, '2025-03-25', 1250.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 168, 0),
(263, 29, '2025-03-28', 166.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 169, 0),
(264, 31, '2025-03-28', 166.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 169, 0),
(265, 34, '2025-03-28', 166.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 169, 0),
(268, 29, '2025-04-03', 1700.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 171, 0),
(269, 30, '2025-04-03', 1700.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 171, 0),
(270, 35, '2025-04-03', 1700.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 171, 0),
(271, 29, '2025-04-03', 1250.00, NULL, 'Auto-deducted from job order for Goldentaste Food Kiosk', 172, 0),
(272, 34, '2025-04-03', 1250.00, NULL, 'Auto-deducted from job order for Goldentaste Food Kiosk', 172, 0),
(273, 29, '2025-04-04', 250.00, NULL, 'Auto-deducted from job order for Square Space MNL Inc.', 173, 0),
(274, 31, '2025-04-04', 250.00, NULL, 'Auto-deducted from job order for Square Space MNL Inc.', 173, 0),
(275, 34, '2025-04-04', 250.00, NULL, 'Auto-deducted from job order for Square Space MNL Inc.', 173, 0),
(276, 38, '2025-03-13', 250.00, NULL, 'Updated job order for ROUTE 95 DINER', 156, 0),
(277, 44, '2025-03-13', 250.00, NULL, 'Updated job order for ROUTE 95 DINER', 156, 0),
(280, 38, '2025-04-04', 250.00, NULL, 'Auto-deducted from job order for Noah\'s Arc Trading', 174, 0),
(281, 43, '2025-04-04', 250.00, NULL, 'Auto-deducted from job order for Noah\'s Arc Trading', 174, 0),
(282, 38, '2025-03-13', 250.00, NULL, 'Updated job order for ROUTE 95 DINER', 157, 0),
(283, 44, '2025-03-13', 250.00, NULL, 'Updated job order for ROUTE 95 DINER', 157, 0),
(284, 29, '2025-07-15', 166.00, NULL, 'Auto-deducted from job order for Active Media Designs & Printing', 175, 0),
(285, 34, '2025-07-15', 166.00, NULL, 'Auto-deducted from job order for Active Media Designs & Printing', 175, 0),
(290, 29, '2025-01-20', 166.00, NULL, 'Updated job order for Juan Vicente F. Aclan, M.D.', 140, 0),
(291, 37, '2025-01-20', 166.00, NULL, 'Updated job order for Juan Vicente F. Aclan, M.D.', 140, 0),
(296, 29, '2025-02-14', 833.00, NULL, 'Updated job order for Hailey\'s Haven Nail Salon and Spa - 0', 145, 0),
(297, 34, '2025-02-14', 833.00, NULL, 'Updated job order for Hailey\'s Haven Nail Salon and Spa - 0', 145, 0),
(298, 29, '2025-01-23', 833.00, NULL, 'Updated job order for Hailey\'s Haven Nail Salon and Spa - 4', 141, 0),
(299, 34, '2025-01-23', 833.00, NULL, 'Updated job order for Hailey\'s Haven Nail Salon and Spa - 4', 141, 0),
(302, 29, '2025-04-04', 166.00, NULL, 'Auto-deducted from job order for Tigerux Global Management Inc.', 176, 0),
(303, 34, '2025-04-04', 166.00, NULL, 'Auto-deducted from job order for Tigerux Global Management Inc.', 176, 0),
(304, 29, '2025-03-27', 3333.00, NULL, 'Updated job order for Malolos Credit & Development Cooperative', 167, 0),
(305, 35, '2025-03-27', 3333.00, NULL, 'Updated job order for Malolos Credit & Development Cooperative', 167, 0),
(308, 29, '2025-01-15', 625.00, NULL, 'Updated job order for LaPresa Bar and Restaurant', 134, 0),
(309, 36, '2025-01-15', 625.00, NULL, 'Updated job order for LaPresa Bar and Restaurant', 134, 0),
(310, 29, '2025-01-15', 625.00, NULL, 'Updated job order for Maricopa KTV ', 139, 0),
(311, 36, '2025-01-15', 625.00, NULL, 'Updated job order for Maricopa KTV ', 139, 0),
(312, 29, '2025-04-01', 625.00, NULL, 'Updated job order for LaPresa Bar and Restaurant', 170, 0),
(313, 36, '2025-04-01', 625.00, NULL, 'Updated job order for LaPresa Bar and Restaurant', 170, 0),
(314, 29, '2025-02-14', 500.00, NULL, 'Updated job order for Malolos Women\'s Ultrasound Center Inc.', 147, 0),
(315, 31, '2025-02-14', 500.00, NULL, 'Updated job order for Malolos Women\'s Ultrasound Center Inc.', 147, 0),
(316, 34, '2025-02-14', 500.00, NULL, 'Updated job order for Malolos Women\'s Ultrasound Center Inc.', 147, 0),
(317, 29, '2025-04-10', 1000.00, NULL, 'Auto-deducted from job order for JMPrints Souvenir Shop', 177, 0),
(318, 31, '2025-04-10', 1000.00, NULL, 'Auto-deducted from job order for JMPrints Souvenir Shop', 177, 0),
(319, 36, '2025-04-10', 1000.00, NULL, 'Auto-deducted from job order for JMPrints Souvenir Shop', 177, 0),
(320, 29, '2025-04-10', 250.00, NULL, 'Auto-deducted from job order for The Glam Room Salon & Spa', 178, 0),
(321, 35, '2025-04-10', 250.00, NULL, 'Auto-deducted from job order for The Glam Room Salon & Spa', 178, 0),
(322, 29, '2025-04-14', 250.00, NULL, 'Auto-deducted from job order for Reina Readymix Construction OPC', 179, 0),
(323, 33, '2025-04-14', 250.00, NULL, 'Auto-deducted from job order for Reina Readymix Construction OPC', 179, 0),
(324, 35, '2025-04-14', 250.00, NULL, 'Auto-deducted from job order for Reina Readymix Construction OPC', 179, 0),
(325, 29, '2025-04-21', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 180, 0),
(326, 37, '2025-04-21', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 180, 0),
(327, 29, '2025-04-25', 166.00, NULL, 'Auto-deducted from job order for Sunoria Realty OPC', 181, 0),
(328, 34, '2025-04-25', 166.00, NULL, 'Auto-deducted from job order for Sunoria Realty OPC', 181, 0),
(329, 29, '2025-04-25', 833.00, NULL, 'Auto-deducted from job order for Hailey\'s Haven Nail Salon and Spa - 6', 182, 0),
(330, 35, '2025-04-25', 833.00, NULL, 'Auto-deducted from job order for Hailey\'s Haven Nail Salon and Spa - 6', 182, 0),
(331, 29, '2025-04-30', 166.00, NULL, 'Auto-deducted from job order for Remarsan Food House', 183, 0),
(332, 33, '2025-04-30', 166.00, NULL, 'Auto-deducted from job order for Remarsan Food House', 183, 0),
(333, 34, '2025-04-30', 166.00, NULL, 'Auto-deducted from job order for Remarsan Food House', 183, 0),
(334, 29, '2025-04-30', 166.00, NULL, 'Auto-deducted from job order for Swiss Gerald Commercial Building', 184, 0),
(335, 35, '2025-04-30', 166.00, NULL, 'Auto-deducted from job order for Swiss Gerald Commercial Building', 184, 0),
(336, 29, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 185, 0),
(337, 30, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 185, 0),
(338, 37, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 185, 0),
(339, 29, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 186, 0),
(340, 33, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 186, 0),
(341, 34, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 186, 0),
(342, 29, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 187, 0),
(343, 30, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 187, 0),
(344, 32, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 187, 0),
(345, 35, '2025-05-03', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 187, 0),
(346, 29, '2025-05-06', 1666.00, NULL, 'Auto-deducted from job order for JFJ Movers Enterprise', 188, 0),
(347, 31, '2025-05-06', 1666.00, NULL, 'Auto-deducted from job order for JFJ Movers Enterprise', 188, 0),
(348, 37, '2025-05-06', 1666.00, NULL, 'Auto-deducted from job order for JFJ Movers Enterprise', 188, 0),
(349, 29, '2025-05-07', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 189, 0),
(350, 33, '2025-05-07', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 189, 0),
(351, 34, '2025-05-07', 250.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 189, 0),
(352, 29, '2025-05-09', 166.00, NULL, 'Auto-deducted from job order for Suisse Chicken House', 190, 0),
(353, 33, '2025-05-09', 166.00, NULL, 'Auto-deducted from job order for Suisse Chicken House', 190, 0),
(354, 34, '2025-05-09', 166.00, NULL, 'Auto-deducted from job order for Suisse Chicken House', 190, 0),
(355, 29, '2025-05-09', 166.00, NULL, 'Auto-deducted from job order for St. James Realty Development', 191, 0),
(356, 35, '2025-05-09', 166.00, NULL, 'Auto-deducted from job order for St. James Realty Development', 191, 0),
(357, 38, '2025-07-14', 125.00, NULL, 'Auto-deducted from job order for Barley Daily Health & Beauty Products Shop', 192, 0),
(358, 45, '2025-07-14', 125.00, NULL, 'Auto-deducted from job order for Barley Daily Health & Beauty Products Shop', 192, 0),
(359, 29, '2025-05-20', 166.00, NULL, 'Auto-deducted from job order for RTM8 Rice Wholesaling ', 193, 0),
(360, 34, '2025-05-20', 166.00, NULL, 'Auto-deducted from job order for RTM8 Rice Wholesaling ', 193, 0),
(361, 29, '2025-05-20', 125.00, NULL, 'Auto-deducted from job order for RTM8 Rice Wholesaling ', 194, 0),
(362, 34, '2025-05-20', 125.00, NULL, 'Auto-deducted from job order for RTM8 Rice Wholesaling ', 194, 0),
(363, 29, '2025-05-28', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 195, 0),
(364, 30, '2025-05-28', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 195, 0),
(365, 35, '2025-05-28', 2500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 195, 0),
(366, 29, '2025-05-29', 3750.00, NULL, 'Auto-deducted from job order for Eilamore Ekea Movers OPC', 196, 0),
(367, 30, '2025-05-29', 3750.00, NULL, 'Auto-deducted from job order for Eilamore Ekea Movers OPC', 196, 0),
(368, 35, '2025-05-29', 3750.00, NULL, 'Auto-deducted from job order for Eilamore Ekea Movers OPC', 196, 0),
(369, 29, '2025-06-02', 250.00, NULL, 'Auto-deducted from job order for Lili\'s Pharmacy ', 197, 0),
(370, 34, '2025-06-02', 250.00, NULL, 'Auto-deducted from job order for Lili\'s Pharmacy ', 197, 0),
(371, 38, '2025-06-03', 83.00, NULL, 'Auto-deducted from job order for Catholic Women\'s League, Philippines, Inc.', 198, 0),
(372, 43, '2025-06-03', 83.00, NULL, 'Auto-deducted from job order for Catholic Women\'s League, Philippines, Inc.', 198, 0),
(373, 29, '2025-06-04', 833.00, NULL, 'Auto-deducted from job order for JMV Nail Salon', 199, 0),
(374, 34, '2025-06-04', 833.00, NULL, 'Auto-deducted from job order for JMV Nail Salon', 199, 0),
(375, 29, '2025-06-04', 500.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 200, 0),
(376, 33, '2025-06-04', 500.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 200, 0),
(377, 34, '2025-06-04', 500.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 200, 0),
(378, 29, '2025-06-04', 166.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 201, 0),
(379, 30, '2025-06-04', 166.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 201, 0),
(380, 35, '2025-06-04', 166.00, NULL, 'Auto-deducted from job order for Arctic-Forest Products Inc.', 201, 0),
(381, 29, '2025-02-22', 166.00, NULL, 'Auto-deducted from job order for Megale Security Services Corp.', 202, 0),
(382, 34, '2025-02-22', 166.00, NULL, 'Auto-deducted from job order for Megale Security Services Corp.', 202, 0),
(387, 29, '2025-01-22', 1666.00, NULL, 'Auto-deducted from job order for JFL Agri Pa-In-Pro', 205, 0),
(388, 30, '2025-01-22', 1666.00, NULL, 'Auto-deducted from job order for JFL Agri Pa-In-Pro', 205, 0),
(389, 35, '2025-01-22', 1666.00, NULL, 'Auto-deducted from job order for JFL Agri Pa-In-Pro', 205, 0),
(390, 29, '2025-07-16', 500.00, NULL, 'Auto-deducted from job order for Yolanda S. Rotap, CPA', 206, 0),
(391, 35, '2025-07-16', 500.00, NULL, 'Auto-deducted from job order for Yolanda S. Rotap, CPA', 206, 0),
(392, 29, '2025-06-16', 166.00, NULL, 'Auto-deducted from job order for Active Media Designs and Printing', 207, 0),
(393, 34, '2025-06-16', 166.00, NULL, 'Auto-deducted from job order for Active Media Designs and Printing', 207, 0),
(394, 29, '2025-06-17', 333.00, NULL, 'Auto-deducted from job order for EZSIPNAYAN LEARNING CENTER - 1', 208, 0),
(395, 34, '2025-06-17', 333.00, NULL, 'Auto-deducted from job order for EZSIPNAYAN LEARNING CENTER - 1', 208, 0),
(396, 38, '2025-07-12', 1500.00, NULL, 'Auto-deducted from job order for Rach Aircondition Services', 209, 0),
(397, 39, '2025-07-12', 1500.00, NULL, 'Auto-deducted from job order for Rach Aircondition Services', 209, 0),
(398, 45, '2025-07-12', 1500.00, NULL, 'Auto-deducted from job order for Rach Aircondition Services', 209, 0),
(399, 29, '2025-06-19', 500.00, NULL, 'Auto-deducted from job order for ROUTE 95 DINER - 4', 210, 0),
(400, 34, '2025-06-19', 500.00, NULL, 'Auto-deducted from job order for ROUTE 95 DINER - 4', 210, 0),
(401, 29, '2025-06-17', 125.00, NULL, 'Auto-deducted from job order for Natalia De Guzman Sali PH. D - Consultant', 211, 0),
(402, 35, '2025-06-17', 125.00, NULL, 'Auto-deducted from job order for Natalia De Guzman Sali PH. D - Consultant', 211, 0),
(403, 29, '2025-07-16', 62.00, NULL, 'Auto-deducted from job order for DDD Pharmacy Co.', 212, 0),
(404, 36, '2025-07-16', 62.00, NULL, 'Auto-deducted from job order for DDD Pharmacy Co.', 212, 0),
(405, 29, '2025-06-25', 62.00, NULL, 'Auto-deducted from job order for DDD Pharmacy Co.', 213, 0),
(406, 36, '2025-06-25', 62.00, NULL, 'Auto-deducted from job order for DDD Pharmacy Co.', 213, 0),
(407, 38, '2025-05-08', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 214, 0),
(408, 42, '2025-05-08', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 214, 0),
(409, 40, '2025-05-08', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 214, 0),
(410, 43, '2025-05-08', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 214, 0),
(411, 38, '2025-02-14', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 215, 0),
(412, 42, '2025-02-14', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 215, 0),
(413, 40, '2025-02-14', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 215, 0),
(414, 43, '2025-02-14', 2500.00, NULL, 'Auto-deducted from job order for Sta. Isabel Trading', 215, 0),
(415, 38, '2025-01-21', 2500.00, NULL, 'Auto-deducted from job order for Sto. Rosario Credit and Development Coop.', 216, 0),
(416, 43, '2025-01-21', 2500.00, NULL, 'Auto-deducted from job order for Sto. Rosario Credit and Development Coop.', 216, 0),
(417, 38, '2025-05-20', 1500.00, NULL, 'Auto-deducted from job order for Razcal Interior Supplies Trading ', 217, 0),
(418, 40, '2025-05-20', 1500.00, NULL, 'Auto-deducted from job order for Razcal Interior Supplies Trading ', 217, 0),
(419, 44, '2025-05-20', 1500.00, NULL, 'Auto-deducted from job order for Razcal Interior Supplies Trading ', 217, 0),
(424, 38, '2025-04-12', 5000.00, NULL, 'Updated job order for JFL Agri-Ventures Supplies', 218, 0),
(425, 41, '2025-04-12', 5000.00, NULL, 'Updated job order for JFL Agri-Ventures Supplies', 218, 0),
(426, 39, '2025-04-12', 5000.00, NULL, 'Updated job order for JFL Agri-Ventures Supplies', 218, 0),
(427, 46, '2025-04-12', 5000.00, NULL, 'Updated job order for JFL Agri-Ventures Supplies', 218, 0),
(428, 38, '2025-03-28', 500.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 219, 0),
(429, 39, '2025-03-28', 500.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 219, 0),
(430, 46, '2025-03-28', 500.00, NULL, 'Auto-deducted from job order for Packetswork Corporation', 219, 0),
(431, 38, '2025-03-25', 750.00, NULL, 'Auto-deducted from job order for Edcon Cargotrans, Inc.', 220, 0),
(432, 46, '2025-03-25', 750.00, NULL, 'Auto-deducted from job order for Edcon Cargotrans, Inc.', 220, 0),
(434, 123, '2025-01-21', 750.00, NULL, 'Updated job order for Sto. Rosario Credit and Development Coop.', 221, 0),
(435, 119, '2025-03-04', 200.00, NULL, 'Auto-deducted from job order for Malolos Credit & Development Cooperative', 222, 0),
(436, 29, '2025-07-15', 333.00, NULL, 'Auto-deducted from job order for Aw Electrical Services', 223, 0),
(437, 34, '2025-07-15', 333.00, NULL, 'Auto-deducted from job order for Aw Electrical Services', 223, 0),
(438, 29, '2025-07-10', 625.00, NULL, 'Auto-deducted from job order for Maricopa KTV ', 224, 0),
(439, 36, '2025-07-10', 625.00, NULL, 'Auto-deducted from job order for Maricopa KTV ', 224, 0),
(440, 29, '2025-07-10', 625.00, NULL, 'Auto-deducted from job order for LaPresa Bar and Restaurant', 225, 0),
(441, 36, '2025-07-10', 625.00, NULL, 'Auto-deducted from job order for LaPresa Bar and Restaurant', 225, 0),
(442, 29, '2025-07-18', 83.00, NULL, 'Auto-deducted from job order for Osjas Management Services Inc.', 226, 0),
(443, 34, '2025-07-18', 83.00, NULL, 'Auto-deducted from job order for Osjas Management Services Inc.', 226, 0),
(444, 29, '2025-07-08', 166.00, NULL, 'Auto-deducted from job order for Visionaries Property Management Corp.', 227, 0),
(445, 34, '2025-07-08', 166.00, NULL, 'Auto-deducted from job order for Visionaries Property Management Corp.', 227, 0),
(446, 29, '2025-07-07', 83.00, NULL, 'Auto-deducted from job order for Osjas Management Services Inc.', 228, 0),
(447, 34, '2025-07-07', 83.00, NULL, 'Auto-deducted from job order for Osjas Management Services Inc.', 228, 0),
(448, 57, '2025-07-07', 500.00, NULL, 'Auto-deducted from job order for Malolos Credit & Development Cooperative', 229, 0),
(449, 57, '2025-07-07', 500.00, NULL, 'Auto-deducted from job order for Malolos Credit & Development Cooperative', 230, 0),
(450, 29, '2025-07-18', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 231, 0),
(451, 37, '2025-07-18', 500.00, NULL, 'Auto-deducted from job order for Oak Tree Marketing', 231, 0),
(452, 71, '2025-06-28', 5000.00, NULL, 'Auto-deducted from job order for Force Central Field Dist. Inc. - Cavite', 232, 0),
(453, 74, '2025-06-28', 5000.00, NULL, 'Auto-deducted from job order for Force Central Field Dist. Inc. - Cavite', 232, 0),
(454, 75, '2025-06-28', 5000.00, NULL, 'Auto-deducted from job order for Force Central Field Dist. Inc. - Cavite', 232, 0),
(455, 47, '2025-07-03', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 233, 0),
(456, 48, '2025-07-03', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 233, 0),
(457, 49, '2025-07-03', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 233, 0),
(458, 50, '2025-07-03', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 233, 0),
(459, 38, '2025-07-18', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri Pa-In-Pro', 234, 0),
(460, 41, '2025-07-18', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri Pa-In-Pro', 234, 0),
(461, 39, '2025-07-18', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri Pa-In-Pro', 234, 0),
(462, 46, '2025-07-18', 2500.00, NULL, 'Auto-deducted from job order for JFL Agri Pa-In-Pro', 234, 0),
(463, 118, '2025-06-23', 1050.00, NULL, 'Auto-deducted from job order for Practical Tools General Merchandise', 235, 0),
(464, 61, '2025-06-23', 1050.00, NULL, 'Auto-deducted from job order for Practical Tools General Merchandise', 235, 0),
(465, 62, '2025-06-23', 1050.00, NULL, 'Auto-deducted from job order for Practical Tools General Merchandise', 235, 0),
(466, 57, '2025-07-09', 1500.00, NULL, 'Auto-deducted from job order for Force Central Field Dist. Inc. ', 236, 0),
(467, 57, '2025-07-09', 1000.00, NULL, 'Auto-deducted from job order for Force Central Field Dist. Inc.', 237, 0),
(472, 29, '2025-06-28', 500.00, NULL, 'Auto-deducted from job order for Ria Farms Aqua Inc.', 240, 0),
(473, 30, '2025-06-28', 500.00, NULL, 'Auto-deducted from job order for Ria Farms Aqua Inc.', 240, 0),
(474, 36, '2025-06-28', 500.00, NULL, 'Auto-deducted from job order for Ria Farms Aqua Inc.', 240, 0),
(475, 29, '2025-07-18', 833.00, NULL, 'Auto-deducted from job order for HDS Nail Salon - Main', 241, 0),
(476, 35, '2025-07-18', 833.00, NULL, 'Auto-deducted from job order for HDS Nail Salon - Main', 241, 0),
(477, 29, '2025-07-02', 166.00, NULL, 'Auto-deducted from job order for ProAce International Philippines Inc.', 242, 0),
(478, 33, '2025-07-02', 166.00, NULL, 'Auto-deducted from job order for ProAce International Philippines Inc.', 242, 0),
(479, 34, '2025-07-02', 166.00, NULL, 'Auto-deducted from job order for ProAce International Philippines Inc.', 242, 0),
(480, 124, '2025-07-18', 166.00, NULL, 'Updated job order for ABC Roof Master', 239, 20),
(481, 63, '2025-07-18', 166.00, NULL, 'Updated job order for ABC Roof Master', 239, 20),
(486, 57, '2025-07-03', 2000.00, NULL, 'Updated job order for Force Central Field Dist. Inc. - Valenzuela', 243, 0),
(492, 118, '2025-07-18', 250.00, NULL, 'Updated job order for Jekotel Food Hub - Main', 246, 0),
(493, 60, '2025-07-18', 250.00, NULL, 'Updated job order for Jekotel Food Hub - Main', 246, 0),
(494, 118, '2025-07-18', 250.00, NULL, 'Updated job order for Jekotel Food Hub -Branch 1', 244, 0),
(495, 60, '2025-07-18', 250.00, NULL, 'Updated job order for Jekotel Food Hub -Branch 1', 244, 0),
(496, 29, '2025-01-15', 2000.00, NULL, 'Updated job order for Caniogan Creditt & Development Cooperative - Hagonoy', 204, 0),
(497, 34, '2025-01-15', 2000.00, NULL, 'Updated job order for Caniogan Creditt & Development Cooperative - Hagonoy', 204, 0),
(500, 29, '2025-02-14', 2000.00, NULL, 'Updated job order for Caniogan Credit & Development Cooperative - CBO', 146, 0),
(501, 34, '2025-02-14', 2000.00, NULL, 'Updated job order for Caniogan Credit & Development Cooperative - CBO', 146, 0),
(504, 29, '2025-07-19', 625.00, NULL, 'Updated job order for RRS ROOFING MATERIALS MATERIALS - BRANCH 1', 247, 0),
(505, 34, '2025-07-19', 625.00, NULL, 'Updated job order for RRS ROOFING MATERIALS MATERIALS - BRANCH 1', 247, 0),
(506, 47, '2025-07-21', 5000.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 248, 0),
(507, 48, '2025-07-21', 5000.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 248, 0),
(508, 49, '2025-07-21', 5000.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 248, 0),
(509, 50, '2025-07-21', 5000.00, NULL, 'Auto-deducted from job order for JFL Agri-Ventures Supplies', 248, 0),
(512, 57, '2025-07-21', 833.00, NULL, 'Updated job order for Evo Riders Club Philippines', 249, 0),
(513, 57, '2025-07-21', 833.00, NULL, 'Updated job order for Evo Riders Club Philippines', 249, 0),
(517, 29, '2025-07-01', 1000.00, NULL, 'Updated job order for Caniogan Creditt & Development Cooperative - Sta Maria', 238, 0),
(518, 34, '2025-07-01', 1000.00, NULL, 'Updated job order for Caniogan Creditt & Development Cooperative - Sta Maria', 238, 0),
(519, 29, '2025-01-09', 2000.00, NULL, 'Updated job order for Caniogan Creditt & Development Cooperative - Calumpit', 203, 0),
(520, 34, '2025-01-09', 2000.00, NULL, 'Updated job order for Caniogan Creditt & Development Cooperative - Calumpit', 203, 0),
(521, 118, '2025-07-04', 3750.00, NULL, 'Auto-deducted from job order for Zoilo Teresita Trading Inc.', 253, 0),
(522, 62, '2025-07-04', 3750.00, NULL, 'Auto-deducted from job order for Zoilo Teresita Trading Inc.', 253, 0),
(523, 38, '2025-03-13', 625.00, NULL, 'Updated job order for ROUTE 95 DINER - Guiguinto', 154, 0),
(524, 44, '2025-03-13', 625.00, NULL, 'Updated job order for ROUTE 95 DINER - Guiguinto', 154, 0),
(527, 118, '2025-08-01', 250.00, NULL, 'Auto-deducted from job order for Perci\'s Battery and General Merchandise', 255, 0),
(528, 60, '2025-08-01', 250.00, NULL, 'Auto-deducted from job order for Perci\'s Battery and General Merchandise', 255, 0),
(531, 29, '2025-08-01', 833.00, NULL, 'Auto-deducted from job order for Hailey\'s Haven Nail Salon and Spa - Robinson_00001', 256, 0),
(532, 34, '2025-08-01', 833.00, NULL, 'Auto-deducted from job order for Hailey\'s Haven Nail Salon and Spa - Robinson_00001', 256, 0),
(533, 29, '2025-08-02', 250.00, NULL, 'Auto-deducted from job order for Athens LPG Trading', 257, 0),
(534, 34, '2025-08-02', 250.00, NULL, 'Auto-deducted from job order for Athens LPG Trading', 257, 0),
(535, 57, '2025-08-02', 1.00, NULL, 'Auto-deducted from job order for Force Central Field Dist. Inc. - Valenzuela', 258, 0),
(536, 57, '2025-08-01', 1500.00, NULL, 'Updated job order for Force Central Field Dist. Inc. - Valenzuela', 245, 0),
(537, 118, '2025-08-02', 250.00, NULL, 'Auto-deducted from job order for MZ Pangilinan Wood and Metal Casket', 259, 0),
(538, 60, '2025-08-02', 250.00, NULL, 'Auto-deducted from job order for MZ Pangilinan Wood and Metal Casket', 259, 0),
(539, 29, '2025-08-04', 125.00, NULL, 'Auto-deducted from job order for Seadragon Outdoor Products Trading', 260, 0),
(540, 34, '2025-08-04', 125.00, NULL, 'Auto-deducted from job order for Seadragon Outdoor Products Trading', 260, 0),
(549, 38, '2025-08-04', 2500.00, NULL, 'Updated job order for Sta. Isabel Trading', 261, 0),
(550, 42, '2025-08-04', 2500.00, NULL, 'Updated job order for Sta. Isabel Trading', 261, 0),
(551, 40, '2025-08-04', 2500.00, NULL, 'Updated job order for Sta. Isabel Trading', 261, 0),
(552, 43, '2025-08-04', 2500.00, NULL, 'Updated job order for Sta. Isabel Trading', 261, 0),
(553, 124, '2025-08-06', 250.00, NULL, 'Auto-deducted from job order for V.B. COLUMNA CONSTRUCTION CORPORATION', 263, 0),
(554, 63, '2025-08-06', 250.00, NULL, 'Auto-deducted from job order for V.B. COLUMNA CONSTRUCTION CORPORATION', 263, 0),
(555, 60, '2025-08-06', 250.00, NULL, 'Auto-deducted from job order for V.B. COLUMNA CONSTRUCTION CORPORATION', 263, 0),
(557, 106, '2025-08-06', 16.00, NULL, 'Updated job order for Bulacan Provincial Hospital (BMC) Multi Purpose Coop', 264, 0),
(558, 124, '2025-08-06', 250.00, NULL, 'Auto-deducted from job order for V.B. COLUMNA CONSTRUCTION CORPORATION', 265, 0),
(559, 63, '2025-08-06', 250.00, NULL, 'Auto-deducted from job order for V.B. COLUMNA CONSTRUCTION CORPORATION', 265, 0),
(560, 60, '2025-08-06', 250.00, NULL, 'Auto-deducted from job order for V.B. COLUMNA CONSTRUCTION CORPORATION', 265, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee','customer') NOT NULL DEFAULT 'employee'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(10, 'Erine George', '$2y$10$J1bjgeiKlvYX3UWA0Q6/WuXzi7FWspbq5tH/OJLl2uS9xMHfcDoqe', 'admin'),
(11, 'Wizermina', '$2y$10$jAF/ulEXnTfkR/CT.gjEkOkRsy0q.2LIzgyCTrWUboYsW5GNUa5iC', 'admin'),
(12, 'Renren', '$2y$10$a9fZW8pu2Zck9P8aKXrAmeDEcPQXEGeAf26RlsCv9UXhiejb45dYK', 'admin'),
(13, 'Alyssa', '$2y$10$WEwvndsntwsXGltKDdGCeuXY6pFy6ajEPtUuonvQtzMe5ywIbh5c.', 'employee'),
(14, 'alicia', '$2y$10$pMhXz88yJeAvBxoFvIy7PeUY7XK8e1iEUS7wAAfqZhqZ/0hqqH0gq', 'employee'),
(15, 'margie', '$2y$10$ersJBM.HyMWj3urYLRTbIOxeCb3KafyG9CEKfuXva5HRI41cWAc4u', 'admin'),
(16, 'admin', '$2y$10$Gi.rcdf3bZypZq6UasEomOrpMMzgqEL14qTIYKfKQS81/vM2rO5aO', 'admin'),
(18, 'uppercasbone@gmail.com', '$2y$10$Psh5Ygugu259AVgQtTLbLeba3MGCby7lwIkNZ0wz3r/23p6hee9CG', 'customer'),
(19, 'lumbaderinegeorgec@gmail.com', '$2y$10$ijacImzrQdzR/SkJqtsQDOTrhLfpCoC39MhU0APqUVkuNw8/FA53S', 'customer'),
(20, 'activemediaprint@gmail.com', '$2y$10$tvTcAuBktNIn6SdqGyOT7urFfZNB7p3PtTtquxQTiOcmIWozy/t12', 'customer');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_customers`
--
ALTER TABLE `company_customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_created_by` (`created_by`);

--
-- Indexes for table `insuances`
--
ALTER TABLE `insuances`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `insuance_delivery_logs`
--
ALTER TABLE `insuance_delivery_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `insuance_usages`
--
ALTER TABLE `insuance_usages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item` (`item_id`),
  ADD KEY `fk_user` (`issued_by`);

--
-- Indexes for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_job_orders_product` (`product_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `personal_customers`
--
ALTER TABLE `personal_customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_products_created_by` (`created_by`);

--
-- Indexes for table `usage_logs`
--
ALTER TABLE `usage_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_usage_job_order` (`job_order_id`);

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
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `company_customers`
--
ALTER TABLE `company_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `insuances`
--
ALTER TABLE `insuances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `insuance_delivery_logs`
--
ALTER TABLE `insuance_delivery_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `insuance_usages`
--
ALTER TABLE `insuance_usages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `job_orders`
--
ALTER TABLE `job_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=266;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `personal_customers`
--
ALTER TABLE `personal_customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `usage_logs`
--
ALTER TABLE `usage_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=561;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `company_customers`
--
ALTER TABLE `company_customers`
  ADD CONSTRAINT `company_customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD CONSTRAINT `delivery_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `insuance_usages`
--
ALTER TABLE `insuance_usages`
  ADD CONSTRAINT `fk_item` FOREIGN KEY (`item_id`) REFERENCES `insuances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD CONSTRAINT `fk_job_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `job_orders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `personal_customers`
--
ALTER TABLE `personal_customers`
  ADD CONSTRAINT `personal_customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `usage_logs`
--
ALTER TABLE `usage_logs`
  ADD CONSTRAINT `fk_usage_job_order` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `usage_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
