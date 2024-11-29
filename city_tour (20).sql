-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2024 at 10:25 AM
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
-- Database: `city_tour`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `level` int(11) DEFAULT NULL,
  `actual_balance` decimal(10,0) DEFAULT NULL,
  `budget_balance` decimal(10,0) DEFAULT NULL,
  `variance` decimal(10,0) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `company_id` bigint(20) DEFAULT NULL,
  `parent_id` bigint(20) DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `code` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `name`, `level`, `actual_balance`, `budget_balance`, `variance`, `created_at`, `updated_at`, `company_id`, `parent_id`, `reference_id`, `code`) VALUES
(1, 'TravelCorp', 4, 0, NULL, NULL, '2024-11-05 04:53:37', '2024-11-05 07:45:37', 15, 50, 1, 'T13222'),
(29, 'Assets', 1, 0, 0, 0, '2024-10-24 18:59:15', '2024-11-05 07:45:37', 15, NULL, NULL, NULL),
(30, 'Liabilities', 1, 0, 0, 0, '2024-10-24 18:59:15', '2024-11-05 07:45:37', 15, NULL, NULL, NULL),
(31, 'Income', 1, 0, 0, 0, '2024-10-24 18:59:15', '2024-11-05 07:45:37', 15, NULL, NULL, NULL),
(32, 'Expenses', 1, 0, 0, 0, '2024-10-24 18:59:15', '2024-11-05 07:45:37', 15, NULL, NULL, NULL),
(33, 'Current Assets', 2, 0, 0, 0, '2024-10-24 19:02:09', '2024-11-05 07:45:37', 15, 29, NULL, NULL),
(34, 'Fixed Assets', 2, 0, 0, 0, '2024-10-24 19:02:09', '2024-11-05 07:45:37', 15, 29, NULL, NULL),
(35, 'Current Liabilities', 2, 0, 0, 0, '2024-10-24 19:02:09', '2024-11-05 07:45:37', 15, 30, NULL, NULL),
(36, 'Long-Term Liabilities', 2, 0, 0, 0, '2024-10-24 19:02:09', '2024-11-05 07:45:37', 15, 30, NULL, NULL),
(37, 'Operating Income', 2, 0, 0, 0, '2024-10-24 19:02:10', '2024-11-05 07:45:37', 15, 31, NULL, NULL),
(38, 'Non-Operating Income', 2, 0, 0, 0, '2024-10-24 19:02:10', '2024-11-05 07:45:37', 15, 31, NULL, NULL),
(39, 'Fixed Expenses', 2, 0, 0, 0, '2024-10-24 19:02:10', '2024-11-05 07:45:37', 15, 32, NULL, NULL),
(40, 'Variable Expenses', 2, 0, 0, 0, '2024-10-24 19:02:10', '2024-11-05 07:45:37', 15, 32, NULL, NULL),
(41, 'Deposits', 2, 0, 0, 0, '2024-10-24 19:04:26', '2024-11-05 07:45:37', 15, 29, NULL, NULL),
(42, 'Investments', 2, 0, 0, 0, '2024-10-24 19:04:26', '2024-11-05 07:45:37', 15, 29, NULL, NULL),
(43, 'Provisions', 2, 0, 0, 0, '2024-10-24 19:04:26', '2024-11-05 07:45:37', 15, 30, NULL, NULL),
(44, 'Cash', 3, 0, 0, 0, '2024-10-24 19:07:59', '2024-11-14 03:48:15', 15, 33, NULL, NULL),
(45, 'Accounts Receivable', 3, -618, 0, 0, '2024-10-24 19:07:59', '2024-11-22 00:37:10', 15, 33, NULL, NULL),
(46, 'Inventory', 3, 0, 0, 0, '2024-10-24 19:07:59', '2024-11-05 07:45:37', 15, 33, NULL, NULL),
(47, 'Property, Plant, and Equipment', 3, 0, 0, 0, '2024-10-24 19:07:59', '2024-11-05 07:45:37', 15, 34, NULL, NULL),
(48, 'Investments in Subsidiaries', 3, 0, 0, 0, '2024-10-24 19:07:59', '2024-11-05 07:45:37', 15, 42, NULL, NULL),
(49, 'Long-Term Deposits', 3, 0, 0, 0, '2024-10-24 19:13:15', '2024-11-05 07:45:37', 15, 41, NULL, NULL),
(50, 'Accounts Payable', 3, 2193, 0, 0, '2024-10-24 19:13:15', '2024-11-22 00:37:10', 15, 35, NULL, NULL),
(51, 'Short-Term Debt', 3, 0, 0, 0, '2024-10-24 19:13:15', '2024-11-05 07:45:37', 15, 35, NULL, NULL),
(52, 'Long-Term Debt', 3, 0, 0, 0, '2024-10-24 19:13:15', '2024-11-05 07:45:37', 15, 36, NULL, NULL),
(53, 'Salary Expense', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 39, NULL, NULL),
(54, 'Rent Expense', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 39, NULL, NULL),
(55, 'Utilities Expense', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 40, NULL, NULL),
(56, 'Depreciation Expense', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 39, NULL, NULL),
(57, 'Business Trip Expense', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 40, NULL, NULL),
(58, 'Agent Sales Commission', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 40, NULL, NULL),
(59, 'Sponsorship Fee', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 40, NULL, NULL),
(60, 'Legal & Professional Fees', 3, 0, 0, 0, '2024-10-24 19:16:30', '2024-11-05 07:45:37', 15, 40, NULL, NULL),
(62, 'petty cash', 4, 700, 200, -100, '2024-10-24 15:42:48', '2024-11-12 22:39:05', 15, 44, 8, '3222222'),
(63, 'Office Laptop', 4, 0, 0, 0, '2024-10-24 15:44:48', '2024-11-05 07:45:37', 15, 47, NULL, 'vcfdyy'),
(64, 'Office Rental', 4, 4000, 0, 4000, '2024-10-24 15:53:19', '2024-11-05 07:45:37', 15, 54, NULL, NULL),
(65, 'Anisah', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-06 02:08:19', 15, 45, 8, '12222'),
(66, 'Amalina', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-06 02:08:28', 15, 45, 9, 'A1113'),
(67, 'Kamal', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 10, 'K1231'),
(68, 'John', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 17, 'J12321'),
(69, 'Emily', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 18, 'E32311'),
(70, 'Dave', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-06 02:08:36', 15, 45, 19, 'D32321'),
(71, 'Mike', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 20, 'M45221'),
(72, 'Sarah', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 21, 'S13111'),
(73, 'Jack', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 22, 'J14211'),
(74, 'Khairul Awani', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 23, 'K31311'),
(75, 'Azurasyafiya', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-05 07:45:37', 15, 45, 24, 'A12311'),
(76, 'AZAHRRA', 4, 0, 0, 0, '2024-10-29 03:46:09', '2024-11-21 04:51:03', 15, 45, 25, 'A43222'),
(123, 'TravelCorp', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-05 07:45:37', 15, 50, 1, 'T13222'),
(124, 'Global Tours', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-05 07:45:37', 15, 50, 2, 'G21313'),
(125, 'Holiday Makers', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-05 07:45:37', 15, 50, 3, 'H32424'),
(126, 'World Travel Network', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-05 07:45:37', 15, 50, 4, 'W31232'),
(127, 'Jetsetter Services', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-06 02:08:48', 15, 50, 5, 'J12312'),
(128, 'Kuwait Travel Agency', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-06 02:08:58', 15, 50, 6, 'K82311'),
(129, 'Kuwait Air Tours', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-05 07:45:37', 15, 50, 7, 'K12332'),
(130, 'Desert Adventures', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-06 02:09:06', 15, 50, 8, 'D24321'),
(131, 'Kuwait Luxury Travel', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-05 07:45:37', 15, 50, 9, 'K73433'),
(132, 'Kuwait Excursions', 4, 0, NULL, NULL, '2024-11-05 04:54:29', '2024-11-05 07:45:37', 15, 50, 10, 'K15422'),
(133, 'Income On Sales', 3, 357, NULL, NULL, '2024-11-05 04:57:10', '2024-11-22 00:37:10', 15, 37, NULL, NULL),
(134, 'MOHD RIZAM BAKAR', 4, 0, NULL, NULL, '2024-11-05 04:57:46', '2024-11-05 07:45:37', 15, 133, 8, 'M12313'),
(135, 'Samira', 4, 0, NULL, NULL, '2024-11-05 04:57:46', '2024-11-05 07:45:37', 15, 133, 10, 'S123131'),
(136, 'Andika', 4, 0, NULL, NULL, '2024-11-05 04:57:46', '2024-11-05 07:45:37', 15, 133, 11, 'A213123'),
(137, 'WeiLing', 4, 0, NULL, NULL, '2024-11-05 04:57:46', '2024-11-05 07:45:37', 15, 133, 12, 'W21441'),
(138, 'Kerry Jamal', 4, 0, NULL, NULL, '2024-11-05 04:57:46', '2024-11-05 07:45:37', 15, 133, 13, 'K213231'),
(139, 'Renny Jamal', 4, 0, NULL, NULL, '2024-11-05 04:57:46', '2024-11-05 07:45:37', 15, 133, 14, 'R213123'),
(140, 'kamalia kamal', 4, 0, NULL, NULL, '2024-11-05 04:57:46', '2024-11-05 07:45:37', 15, 133, 15, 'K131444'),
(141, 'Soud Shoja', 4, 357, NULL, NULL, '2024-11-05 08:24:59', '2024-11-22 00:37:10', 15, 133, 16, 'K131445'),
(142, 'Saeid Shoja', 4, 0, NULL, NULL, '2024-11-05 08:24:59', '2024-11-05 08:24:59', 15, 133, 17, 'K131446'),
(143, 'Mohammad Alhashmi', 4, 0, NULL, NULL, '2024-11-05 08:24:59', '2024-11-05 08:24:59', 15, 133, 18, 'K131447'),
(144, 'Bank', 3, 0, NULL, NULL, '2024-11-05 09:36:22', '2024-11-05 09:36:22', 15, 33, NULL, NULL),
(145, 'Payment Gateway', 4, 1800, NULL, NULL, '2024-11-05 09:55:03', '2024-11-22 08:29:43', 15, 144, NULL, 'INV1232'),
(146, 'Bank Charges', 3, 0, NULL, NULL, '2024-11-05 10:06:28', '2024-11-05 10:06:28', 15, 40, NULL, NULL),
(147, 'Tap Charges', 4, 4, NULL, NULL, '2024-11-05 10:07:23', '2024-11-21 23:59:09', 15, 146, NULL, 'TAP1011'),
(148, 'Amadeus', 4, 2193, NULL, NULL, '2024-11-06 05:25:23', '2024-11-22 00:37:10', 15, 50, 11, 'A12309'),
(149, 'Alaiwi Soud', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 26, 'A43223'),
(150, 'Mohammad Alfailakawi', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 27, 'A43224'),
(151, 'Abdulaziz Alhouti', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 28, 'A43225'),
(152, 'Ahmad Almahmeed', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 29, 'A43226'),
(153, 'Abdulaziz ALmesbah', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 30, 'A43227'),
(154, 'Alaa Bensalamah', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 31, 'A43228'),
(155, 'Noor Esmail', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 32, 'A43229'),
(156, 'Khaled Alajmi', 4, -1155, NULL, NULL, '2024-11-06 06:51:14', '2024-11-21 23:59:09', 15, 45, 33, 'A43230'),
(157, 'Khalid Alazmi', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 34, 'A43231'),
(158, 'Khaled ALrashidi', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 35, 'A43232'),
(159, 'Mohammed Alhashimi', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 36, 'A43233'),
(160, 'Faisal Alshammari', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 37, 'A43234'),
(161, 'Abdalrahman Alazemi', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 38, 'A43235'),
(162, 'Nourah Alazmi', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 39, 'A43236'),
(163, 'Ahmad Shamsher', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-06 06:51:14', 15, 45, 40, 'A43237'),
(164, 'Naser Alajmi', 4, 537, NULL, NULL, '2024-11-06 06:51:14', '2024-11-22 00:37:10', 15, 45, 41, 'A43238'),
(165, 'Fahad Alajmi', 4, 0, NULL, NULL, '2024-11-06 06:51:14', '2024-11-21 04:52:14', 15, 45, 42, 'A43239');

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`id`, `user_id`, `name`, `company_id`, `type`, `created_at`, `updated_at`, `phone_number`, `email`, `branch_id`) VALUES
(5, 32, 'Mamat', 1, 'staff', '2024-09-11 23:45:42', '2024-09-11 23:45:42', '123213131', 'mamat@gmail.com', 1),
(6, 33, 'Zura', 1, 'staff', '2024-09-11 23:45:43', '2024-09-11 23:45:43', '12313131', 'zura@gmail.com', 1),
(7, NULL, 'A', 1, 'staff', '2024-09-12 01:23:22', '2024-09-12 01:23:22', '133256126', 'amalina@gmail.com', 1),
(8, 16, 'MOHD RIZAM BAKAR', 8, 'commission', '2024-09-20 20:01:30', '2024-09-20 20:01:30', '+60193058463', 'markrizam@gmail.com', 1),
(10, 41, 'Samira', 8, 'staff', '2024-10-02 00:33:38', '2024-10-02 00:33:38', '123213131', 'samira@maddintravel.com', 1),
(11, 42, 'Andika', 8, 'staff', '2024-10-02 00:33:38', '2024-10-02 00:33:38', '12313131', 'andika@maddintravel.com', 1),
(12, 43, 'WeiLing', 6, 'staff', '2024-10-02 00:36:31', '2024-10-02 00:36:31', '1233133', 'weiling@tariqtravel.com', 1),
(13, 46, 'Kerry Jamal', 8, 'staff', '2024-10-08 21:21:37', '2024-10-08 21:21:37', '123213', 'kerry@maddintravel.com', 1),
(14, 47, 'Renny Jamal', 8, 'staff', '2024-10-08 22:26:30', '2024-10-08 22:26:30', '123312233', 'renny@maddintravel.com', 1),
(15, 48, 'kamalia kamal', 8, 'staff', '2024-10-08 22:34:14', '2024-10-08 22:34:14', '1982122', 'kamalia@maddintravel.com', 1),
(16, 52, 'Soud Shoja', 15, 'commission', '2024-10-15 04:34:16', '2024-10-15 04:34:16', '+96555524870', 'soud@citytravelers.co', 1),
(17, 51, 'Saeid Shoja', 15, 'commission', '2024-10-15 04:34:16', '2024-10-15 04:34:16', '+96555524870', 'soud@citytravelers.co', 1),
(18, 50, 'Mohammad Alhashmi', 15, 'commission', '2024-10-15 04:34:16', '2024-10-15 04:34:16', '+96522210017', 'msh@citytravelers.co', 1);

-- --------------------------------------------------------

--
-- Table structure for table `agent_type`
--

CREATE TABLE `agent_type` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `airlines`
--

CREATE TABLE `airlines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `iata_designator` varchar(8) DEFAULT NULL,
  `code` varchar(8) DEFAULT NULL,
  `icao_designator` varchar(8) DEFAULT NULL,
  `country_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `airlines_old`
--

CREATE TABLE `airlines_old` (
  `id` int(11) NOT NULL,
  `airline_name` varchar(255) DEFAULT NULL,
  `code` varchar(10) DEFAULT NULL,
  `cost_category` enum('Low Cost','Normal') DEFAULT NULL,
  `avg_price` decimal(10,2) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `fleet_size` int(11) DEFAULT NULL,
  `special_services` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `airlines_old`
--

INSERT INTO `airlines_old` (`id`, `airline_name`, `code`, `cost_category`, `avg_price`, `rating`, `country`, `fleet_size`, `special_services`, `website`, `created_at`, `updated_at`) VALUES
(1, 'American Airlines', 'AA', 'Normal', 250.00, 4.5, 'USA', 950, 'In-flight meals', 'https://www.aa.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(2, 'United Airlines', 'UA', 'Normal', 300.00, 4.0, 'USA', 800, 'Extra legroom available', 'https://www.united.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(3, 'Delta Airlines', 'DL', 'Normal', 280.00, 4.7, 'USA', 700, 'Wi-Fi on flights', 'https://www.delta.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(4, 'Southwest Airlines', 'SW', 'Low Cost', 150.00, 4.3, 'USA', 500, 'No change fees', 'https://www.southwest.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(5, 'JetBlue Airways', 'B6', 'Low Cost', 180.00, 4.2, 'USA', 300, 'Free snacks', 'https://www.jetblue.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(6, 'Alaska Airlines', 'AS', 'Normal', 220.00, 4.4, 'USA', 150, 'First-class seating', 'https://www.alaskaair.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(7, 'Spirit Airlines', 'NK', 'Low Cost', 90.00, 3.5, 'USA', 200, 'Pay for extras', 'https://www.spirit.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(8, 'Frontier Airlines', 'F9', 'Low Cost', 75.00, 3.8, 'USA', 150, 'Only pay for what you use', 'https://www.flyfrontier.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(9, 'Hawaiian Airlines', 'HA', 'Normal', 400.00, 4.6, 'USA', 40, 'Island hopping services', 'https://www.hawaiianairlines.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(10, 'Allegiant Air', 'G4', 'Low Cost', 60.00, 3.9, 'USA', 130, 'Seasonal flights', 'https://www.allegiantair.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(11, 'Air Canada', 'AC', 'Normal', 350.00, 4.5, 'Canada', 400, 'Lounge access', 'https://www.aircanada.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(12, 'British Airways', 'BA', 'Normal', 500.00, 4.5, 'UK', 250, 'In-flight entertainment', 'https://www.britishairways.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(13, 'Lufthansa', 'LH', 'Normal', 450.00, 4.6, 'Germany', 300, 'Fine dining options', 'https://www.lufthansa.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(14, 'Qatar Airways', 'QR', 'Normal', 550.00, 4.8, 'Qatar', 150, 'Luxury services', 'https://www.qatarairways.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10'),
(15, 'Emirates', 'EK', 'Normal', 600.00, 4.9, 'UAE', 250, 'Private suites', 'https://www.emirates.com', '2024-10-17 05:48:10', '2024-10-17 05:48:10');

-- --------------------------------------------------------

--
-- Table structure for table `airports`
--

CREATE TABLE `airports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `city_code` varchar(8) DEFAULT NULL,
  `aiport` varchar(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `asset` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `company_id` int(11) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `user_id`, `name`, `email`, `phone`, `address`, `company_id`, `created_at`, `updated_at`) VALUES
(1, 55, 'City Travel East', 'amir@kuwaittravelexperts.com', '0193058463', 'C. Falsa 445', 15, '2024-11-08 00:10:40', '2024-11-08 00:10:40');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('saeid@citytravelers.c|127.0.0.1', 'i:1;', 1730790231),
('saeid@citytravelers.c|127.0.0.1:timer', 'i:1730790231;', 1730790231);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `level` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `parent_id`, `created_at`, `updated_at`, `level`) VALUES
(32, 'Assets', 'Resources owned by the company', NULL, '2024-10-25 02:14:30', '2024-10-25 02:14:30', 1),
(33, 'Liabilities', 'Obligations or debts owed to others', NULL, '2024-10-25 02:14:30', '2024-10-25 02:14:30', 1),
(34, 'Income', 'Revenue generated from business activities', NULL, '2024-10-25 02:14:30', '2024-10-25 02:14:30', 1),
(35, 'Expenses', 'Costs incurred in the process of earning revenue', NULL, '2024-10-25 02:14:30', '2024-10-25 02:14:30', 1),
(36, 'Current Assets', 'Assets that are expected to be converted to cash within a year', 32, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(37, 'Fixed Assets', 'Assets that will last longer than one year', 32, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(38, 'Current Liabilities', 'Obligations due within one year', 33, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(39, 'Long-Term Liabilities', 'Obligations due after one year', 33, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(40, 'Operating Income', 'Revenue from primary business operations', 34, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(41, 'Non-Operating Income', 'Revenue from non-core business activities', 34, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(42, 'Fixed Expenses', 'Regular costs incurred regardless of business activity', 35, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(43, 'Variable Expenses', 'Costs that vary based on business activity', 35, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(44, 'Deposits', 'Money held in deposit accounts', 32, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(45, 'Investments', 'Funds put into financial schemes for earning returns', 32, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(46, 'Provisions', 'Funds set aside for potential liabilities', 33, '2024-10-25 02:19:57', '2024-10-25 02:19:57', 2),
(47, 'Cash', 'Available cash on hand', 36, '2024-10-25 02:24:21', '2024-10-25 02:24:21', 3),
(48, 'Accounts Receivable', 'Money owed by customers for sales made', 36, '2024-10-25 02:24:21', '2024-10-25 02:24:21', 3),
(49, 'Inventory', 'Goods available for sale', 36, '2024-10-25 02:24:21', '2024-10-25 02:24:21', 3),
(50, 'Property, Plant, and Equipment', 'Long-term physical assets used in operations', 37, '2024-10-25 02:24:21', '2024-10-25 02:24:21', 3),
(51, 'Cash', 'Available cash on hand', 36, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(52, 'Accounts Receivable', 'Money owed by customers for sales made', 36, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(53, 'Inventory', 'Goods available for sale', 36, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(54, 'Property, Plant, and Equipment', 'Long-term physical assets used in operations', 37, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(55, 'Investments in Subsidiaries', 'Equity investments in subsidiary companies', 45, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(56, 'Long-Term Deposits', 'Deposits held for long terms', 44, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(57, 'Accounts Payable', 'Money owed to suppliers ', 38, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(58, 'Short-Term Debt', 'Debt that is due within one year', 38, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(59, 'Long-Term Debt', 'Debt that is due after one year', 39, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(60, 'Salary Expense', 'Regular payments made to employees', 42, '2024-10-25 02:27:25', '2024-10-25 02:27:25', 3),
(61, 'Rent Expense', 'Costs incurred for leasing premises', 42, '2024-10-25 02:27:26', '2024-10-25 02:27:26', 3),
(62, 'Utilities Expense', 'Costs incurred for utilities such as electricity and water', 43, '2024-10-25 02:27:26', '2024-10-25 02:27:26', 3),
(63, 'Depreciation Expense', 'Reduction in the value of fixed assets', 42, '2024-10-25 02:27:26', '2024-10-25 02:27:26', 3),
(64, 'Business Trip Expense', 'Costs incurred during business travel', 43, '2024-10-25 02:27:26', '2024-10-25 02:27:26', 3),
(65, 'Agent Sales Commission', 'Commissions paid to agents for sales made', 43, '2024-10-25 02:27:26', '2024-10-25 02:27:26', 3),
(66, 'Sponsorship Fee', 'Costs incurred for sponsoring events or organizations', 43, '2024-10-25 02:27:26', '2024-10-25 02:27:26', 3),
(67, 'Legal & Professional Fees', 'Payments made for legal and professional services', 43, '2024-10-25 02:27:26', '2024-10-25 02:27:26', 3);

-- --------------------------------------------------------

--
-- Table structure for table `charges`
--

CREATE TABLE `charges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `charges`
--

INSERT INTO `charges` (`id`, `name`, `type`, `description`, `amount`, `created_at`, `updated_at`) VALUES
(1, 'TAP', 'Payment Gateway', 'Payment charges from TAP', 0.035, '2024-11-04 18:18:25', '2024-11-06 04:50:12'),
(2, 'Lea Parisian', 'paypal', 'Tempore inventore itaque ut sed. Necessitatibus laudantium saepe corrupti et. Fugiat aliquid sit architecto magni temporibus.', 236.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25'),
(3, 'Diego Schmitt', 'mastercard', 'Eius non architecto consequuntur quis voluptatum reprehenderit. Ut non totam vel explicabo qui voluptate aut. Velit cum praesentium officia reprehenderit aut.', 122.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25'),
(4, 'Dr. Marquise Dibbert II', 'paypal', 'Eveniet quibusdam ab dolorum esse illum. Perferendis molestiae sed et. Illo facere fuga expedita. Qui neque atque nisi rerum qui perferendis. Illum tenetur quisquam libero.', 106.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25'),
(5, 'Prof. Hattie Rempel', 'paypal', 'Doloremque non quas tempora neque a. Repudiandae perferendis maiores expedita. In voluptatibus officiis tempore eveniet omnis.', 375.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25'),
(6, 'Dr. Anais Lehner', 'mastercard', 'Odio consequuntur commodi earum distinctio. Incidunt omnis minus fugit expedita rerum voluptas dicta. Veritatis at necessitatibus aut quam laborum non. Ipsa qui debitis ut molestiae quis enim quo.', 74.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25'),
(7, 'Pablo Haag', 'paypal', 'Quisquam dolor eligendi fuga voluptatibus veritatis dolor tenetur doloremque. Qui commodi voluptates quisquam non aut quia voluptate enim. Incidunt quo et aut eius.', 671.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25'),
(8, 'Beatrice Bosco', 'paypal', 'Blanditiis quas rem et harum. Qui accusantium culpa est non aperiam. Tempora explicabo non rerum et debitis perspiciatis. Asperiores aliquid dolores fuga deserunt dolor.', 500.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25'),
(9, 'Tom Dooley', 'visa', 'Consequatur in consequatur voluptate. Et laboriosam et corrupti eos dolorem odio officia.', 655.000, '2024-11-04 18:18:25', '2024-11-04 18:18:25');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `agent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `passport_no` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `agent_id`, `note`, `phone`, `status`, `created_at`, `updated_at`, `email`, `address`, `passport_no`) VALUES
(8, 'Anisah', 16, NULL, '193058463', 'active', '2024-09-13 01:00:02', '2024-09-13 01:00:02', 'anisah@gmail.com', NULL, NULL),
(9, 'Amalina', 16, NULL, '193058463', 'active', '2024-09-13 01:00:02', '2024-09-13 01:00:02', 'amalina@gmail.com', NULL, NULL),
(10, 'Kamal', 16, NULL, '193058463', 'active', '2024-09-13 01:00:02', '2024-09-13 01:00:02', 'kamal@gmail.com', NULL, NULL),
(17, 'John', 16, NULL, '193058463', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'john@example.com', NULL, NULL),
(18, 'Emily', 16, NULL, '193058463', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'emily@gmail.com', NULL, NULL),
(19, 'Dave', 16, NULL, '193058463', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'dave@example.com', NULL, NULL),
(20, 'Mike', 16, NULL, '193058463', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'mike@example.com', NULL, NULL),
(21, 'Sarah', 16, NULL, '193058463', 'active', '2024-09-24 18:15:22', '2024-10-02 19:41:34', 'sarah@example.com', NULL, NULL),
(22, 'Jack', 16, NULL, '193058463', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'jack@example.com', NULL, NULL),
(23, 'Khairul Awani', 16, NULL, '193058463', 'active', '2024-10-02 18:12:55', '2024-10-02 18:12:55', 'khairul@gmail.com', 'Jalan Kangkung', 'AP21321312'),
(24, 'Azurasyafiya', 16, NULL, '193058463', 'active', '2024-10-02 18:12:55', '2024-10-02 18:12:55', 'Azurasyafiya@gmail.com', 'Jalan Madani', 'AL12312414'),
(25, 'AZAHRRA', 16, NULL, '193058463', 'active', '2024-10-02 18:37:07', '2024-10-02 18:37:07', 'azzahra@gmail.com', 'Jalan Mawar Duka, 27000', 'A9823123313'),
(26, 'Alaiwi Soud', 16, NULL, '193058463', 'active', NULL, NULL, 'alaiwi@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(27, 'Mohammad Alfailakawi', 16, NULL, '193058463', 'active', NULL, NULL, 'alfailakawi@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(28, 'Abdulaziz ALhouti', 16, NULL, '193058463', 'active', NULL, NULL, 'abdulaziz@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(29, 'Ahmad Almahmeed', 16, NULL, '193058463', 'active', NULL, NULL, 'ahmad@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(30, 'Abdulaziz ALmesbah', 16, NULL, '193058463', 'active', NULL, NULL, 'mesbah@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(31, 'Alaa Bensalamah', 16, NULL, '193058463', 'active', NULL, NULL, 'bensalamah@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(32, 'Noor Esmail', 16, NULL, '193058463', 'active', NULL, NULL, 'esmaiel@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(33, 'Khaled Alajmi', 16, NULL, '193058463', 'active', NULL, NULL, 'khaled@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(34, 'Khalid Alazmi', 16, NULL, '193058463', 'active', NULL, NULL, 'khalid@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(35, 'Khaled ALrashidi', 16, NULL, '193058463', 'active', NULL, NULL, 'alrashidi@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(36, 'Mohammed Alhashimi', 16, NULL, '193058463', 'active', NULL, NULL, 'alhashimi@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(37, 'Faisal Alshammari', 16, NULL, '193058463', 'active', NULL, NULL, 'faisal@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(38, 'Abdalrahman Alazemi', 16, NULL, '193058463', 'active', NULL, NULL, 'alazemi@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(39, 'Nourah Alazmi', 16, NULL, '193058463', 'active', NULL, NULL, 'nourah@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(40, 'Ahmad Shamsher', 16, NULL, '193058463', 'active', NULL, NULL, 'shamsher@gmail.com', 'AL AHMADI - KUWAIT', NULL),
(41, 'Naser Alajmi', 16, NULL, '0193058463', 'active', '2024-11-06 04:11:57', '2024-11-06 04:11:57', 'naser@gmail.com', 'kuwait', NULL),
(42, 'Fahad Alajmi', 16, NULL, '0193058463', 'active', '2024-11-06 04:12:20', '2024-11-06 04:12:20', 'fahad@gmail.com', 'kuwait', NULL),
(43, 'Jomari', 16, NULL, '0147201172', '1', '2024-11-20 00:19:14', '2024-11-20 00:19:14', 'jomari@gmail.com', 'Jalan Mawar Duka, 27000', 'A98231233121'),
(44, 'KHALED MR AHMADI', NULL, NULL, NULL, 'active', '2024-11-20 18:15:59', '2024-11-20 18:15:59', NULL, NULL, NULL),
(45, 'ALAZMI/NOURAH MRS AHMADI', NULL, NULL, NULL, 'active', '2024-11-20 18:18:21', '2024-11-20 18:18:21', NULL, NULL, NULL),
(46, 'Abdalkarim Alazemi', NULL, NULL, NULL, 'active', '2024-11-20 18:26:00', '2024-11-20 18:26:00', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `coa_categories`
--

CREATE TABLE `coa_categories` (
  `id` int(11) NOT NULL,
  `accountDescription` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `accountType` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coa_categories`
--

INSERT INTO `coa_categories` (`id`, `accountDescription`, `active`, `accountType`, `created_at`, `updated_at`) VALUES
(1, 'Bank charges', 1, 'Expenses', '2024-10-10 19:55:06', '2024-10-10 19:55:06'),
(2, 'Task Payment', 1, 'Accounts Receivable', '2024-10-10 20:05:31', '2024-10-10 20:05:31'),
(3, 'Agents Commission', 1, 'Expenses', '2024-10-10 20:05:53', '2024-10-10 20:05:53'),
(4, 'Refund', 1, 'Expenses', '2024-10-10 20:06:22', '2024-10-10 20:06:22'),
(5, 'Incentives', 1, 'Income', '2024-10-10 20:06:37', '2024-10-10 20:06:37'),
(6, 'Hosting Fee', 1, 'Expenses', '2024-10-10 20:09:39', '2024-10-10 20:09:39'),
(7, 'Domain Fee', 1, 'Expenses', '2024-10-10 20:09:54', '2024-10-10 20:09:54'),
(8, 'Computer equipment', 1, 'Fixed Assets', '2024-10-10 20:10:15', '2024-10-10 20:10:15'),
(9, 'Furniture & fixtures', 1, 'Fixed Assets', '2024-10-10 20:10:35', '2024-10-10 20:10:35'),
(10, 'Accounts payable', 1, 'Accounts Payable', '2024-10-10 20:10:55', '2024-10-10 20:10:55'),
(11, 'Rent', 1, 'Expenses', '2024-10-10 20:11:21', '2024-10-10 20:11:21'),
(12, 'Advertising', 1, 'Expenses', '2024-10-10 20:11:31', '2024-10-10 20:11:31'),
(13, 'Payroll', 1, 'Expenses', '2024-10-10 20:11:41', '2024-10-10 20:11:41'),
(14, 'Payroll taxes', 1, 'Expenses', '2024-10-10 20:11:52', '2024-10-10 20:11:52'),
(15, 'Employee benefits', 1, 'Expenses', '2024-10-10 20:12:03', '2024-10-10 20:12:03'),
(16, 'Insurance-liability', 1, 'Expenses', '2024-10-10 20:12:24', '2024-10-10 20:12:24'),
(17, 'Office supplies', 1, 'Expenses', '2024-10-10 20:12:40', '2024-10-10 20:12:40'),
(18, 'Other meetings & travel', 1, 'Expenses', '2024-10-10 20:12:51', '2024-10-10 20:12:51'),
(19, 'Miscellaneous expenses', 1, 'Expenses', '2024-10-10 20:13:36', '2024-10-10 20:13:36'),
(20, 'Entertainment', 1, 'Expenses', '2024-10-10 20:13:48', '2024-10-10 20:13:48'),
(21, 'Utilities', 1, 'Expenses', '2024-10-10 20:13:58', '2024-10-10 20:13:58'),
(22, 'Telephone/network', 1, 'Expenses', '2024-10-10 20:14:09', '2024-10-10 20:14:09'),
(23, 'Travel - General', 1, 'Expenses', '2024-10-10 20:14:19', '2024-10-10 20:14:19'),
(24, 'Meals', 1, 'Expenses', '2024-10-10 20:14:29', '2024-10-10 20:14:29'),
(25, 'Hotel', 1, 'Expenses', '2024-10-10 20:14:41', '2024-10-10 20:14:41');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `nationality_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `code`, `nationality_id`, `name`, `created_at`, `updated_at`, `address`, `phone`, `user_id`, `status`, `email`) VALUES
(1, 'AMR200', 1, 'Amir Travel', '2024-09-12 18:06:29', '2024-10-01 22:43:21', 'Jalan Mawar Duka, 27000', '0147201172', 2, 1, 'amir@alphia.net'),
(4, 'SUMUA', 1, 'SUMUA TRAVEL', '2024-10-01 19:47:18', '2024-10-01 19:47:18', 'Taman Bucida Hijauan', '0321562222', 3, 1, 'sumuatravel@gmail.com'),
(5, 'PAAN', 1, 'PAAN TRAVEL', '2024-10-01 19:55:11', '2024-10-01 19:55:11', 'Taman Bucida Hijauan Kuala Lumpur', '60321655631', 4, 1, 'paantravel@gmail.com'),
(6, 'TAR', 1, 'TARIQ TRAVEL', '2024-10-01 20:00:07', '2024-10-01 20:00:07', 'Off road side', '0921331332', 5, 1, 'tareqtravel@gmail.com'),
(7, 'PAU', 1, 'PAU TRAVEL', '2024-10-01 20:58:49', '2024-10-01 20:58:49', 'Jalan Bukit Bintang, Kuala Lumpur', '123213131', 12, 1, 'admin@pautravel.com'),
(8, 'MADD', 1, 'MADDIN TRAVEL', '2024-10-01 21:04:48', '2024-10-01 21:04:48', 'Jalan Bukit Bintang, Kuala Lumpur', '923213213', 40, 1, 'admin@maddintravel.com'),
(9, 'KW001', 1, 'Kuwait Travel Experts', '2024-10-18 04:07:30', '2024-10-18 04:07:30', '123 Al-Mubarak St, Kuwait City', '965-2222-1111', 32, 0, 'info@kuwaittravelexperts.com'),
(10, 'KW002', 1, 'Desert Adventures', '2024-10-18 04:07:30', '2024-10-18 04:07:30', '45 Salmiya St, Salmiya', '965-2222-2222', 33, 0, 'contact@desertadventures.com'),
(11, 'KW003', 1, 'Luxury Voyages', '2024-10-18 04:07:30', '2024-10-18 04:07:30', '789 Fahaheel Rd, Fahaheel', '965-2222-3333', 34, 0, 'support@luxuryvoyages.com'),
(12, 'KW004', 1, 'Kuwait Air Tours', '2024-10-18 04:07:30', '2024-10-18 04:07:30', '25 Arabian Gulf St, Hawalli', '965-2222-4444', 35, 0, 'hello@kuwaitairtours.com'),
(13, 'KW005', 1, 'Explore Kuwait', '2024-10-18 04:07:30', '2024-10-18 04:07:30', '60 Al-Jahra St, Al-Jahra', '965-2222-5555', 36, 0, 'info@explorekuwait.com'),
(14, 'CM1234', 89, 'COMO TRAVEL AND TOURISM', '2024-10-30 07:14:17', '2024-10-30 07:14:17', 'COMO office', '+965 1811008', 37, 1, 'como@agency.co'),
(15, 'CT4578', 89, 'CITY TRAVELERS', '2024-10-30 07:21:23', '2024-10-30 07:21:23', 'AL AHMADI - KUWAIT\nALSALEM ALSEBAH ST.\nABU HALI - BLOCK 3', '+965 22210017', 1, 1, 'citytravelers@agency.co');

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `iso_code` char(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name`, `iso_code`) VALUES
(1, 'Afghanistan', 'AF'),
(2, 'Albania', 'AL'),
(3, 'Algeria', 'DZ'),
(4, 'Andorra', 'AD'),
(5, 'Angola', 'AO'),
(6, 'Antigua and Barbuda', 'AG'),
(7, 'Argentina', 'AR'),
(8, 'Armenia', 'AM'),
(9, 'Australia', 'AU'),
(10, 'Austria', 'AT'),
(11, 'Azerbaijan', 'AZ'),
(12, 'Bahamas', 'BS'),
(13, 'Bahrain', 'BH'),
(14, 'Bangladesh', 'BD'),
(15, 'Barbados', 'BB'),
(16, 'Belarus', 'BY'),
(17, 'Belgium', 'BE'),
(18, 'Belize', 'BZ'),
(19, 'Benin', 'BJ'),
(20, 'Bhutan', 'BT'),
(21, 'Bolivia', 'BO'),
(22, 'Bosnia and Herzegovina', 'BA'),
(23, 'Botswana', 'BW'),
(24, 'Brazil', 'BR'),
(25, 'Brunei', 'BN'),
(26, 'Bulgaria', 'BG'),
(27, 'Burkina Faso', 'BF'),
(28, 'Burundi', 'BI'),
(29, 'Cabo Verde', 'CV'),
(30, 'Cambodia', 'KH'),
(31, 'Cameroon', 'CM'),
(32, 'Canada', 'CA'),
(33, 'Central African Republic', 'CF'),
(34, 'Chad', 'TD'),
(35, 'Chile', 'CL'),
(36, 'China', 'CN'),
(37, 'Colombia', 'CO'),
(38, 'Comoros', 'KM'),
(39, 'Congo (Congo-Brazzaville)', 'CG'),
(40, 'Congo (Democratic Republic)', 'CD'),
(41, 'Costa Rica', 'CR'),
(42, 'Croatia', 'HR'),
(43, 'Cuba', 'CU'),
(44, 'Cyprus', 'CY'),
(45, 'Czech Republic', 'CZ'),
(46, 'Denmark', 'DK'),
(47, 'Djibouti', 'DJ'),
(48, 'Dominica', 'DM'),
(49, 'Dominican Republic', 'DO'),
(50, 'Ecuador', 'EC'),
(51, 'Egypt', 'EG'),
(52, 'El Salvador', 'SV'),
(53, 'Equatorial Guinea', 'GQ'),
(54, 'Eritrea', 'ER'),
(55, 'Estonia', 'EE'),
(56, 'Eswatini', 'SZ'),
(57, 'Ethiopia', 'ET'),
(58, 'Fiji', 'FJ'),
(59, 'Finland', 'FI'),
(60, 'France', 'FR'),
(61, 'Gabon', 'GA'),
(62, 'Gambia', 'GM'),
(63, 'Georgia', 'GE'),
(64, 'Germany', 'DE'),
(65, 'Ghana', 'GH'),
(66, 'Greece', 'GR'),
(67, 'Grenada', 'GD'),
(68, 'Guatemala', 'GT'),
(69, 'Guinea', 'GN'),
(70, 'Guinea-Bissau', 'GW'),
(71, 'Guyana', 'GY'),
(72, 'Haiti', 'HT'),
(73, 'Honduras', 'HN'),
(74, 'Hungary', 'HU'),
(75, 'Iceland', 'IS'),
(76, 'India', 'IN'),
(77, 'Indonesia', 'ID'),
(78, 'Iran', 'IR'),
(79, 'Iraq', 'IQ'),
(80, 'Ireland', 'IE'),
(81, 'Israel', 'IL'),
(82, 'Italy', 'IT'),
(83, 'Jamaica', 'JM'),
(84, 'Japan', 'JP'),
(85, 'Jordan', 'JO'),
(86, 'Kazakhstan', 'KZ'),
(87, 'Kenya', 'KE'),
(88, 'Kiribati', 'KI'),
(89, 'Kuwait', 'KW'),
(90, 'Kyrgyzstan', 'KG'),
(91, 'Laos', 'LA'),
(92, 'Latvia', 'LV'),
(93, 'Lebanon', 'LB'),
(94, 'Lesotho', 'LS'),
(95, 'Liberia', 'LR'),
(96, 'Libya', 'LY'),
(97, 'Liechtenstein', 'LI'),
(98, 'Lithuania', 'LT'),
(99, 'Luxembourg', 'LU'),
(100, 'Madagascar', 'MG'),
(101, 'Malawi', 'MW'),
(102, 'Malaysia', 'MY'),
(103, 'Maldives', 'MV'),
(104, 'Mali', 'ML'),
(105, 'Malta', 'MT'),
(106, 'Marshall Islands', 'MH'),
(107, 'Mauritania', 'MR'),
(108, 'Mauritius', 'MU'),
(109, 'Mexico', 'MX'),
(110, 'Micronesia', 'FM'),
(111, 'Moldova', 'MD'),
(112, 'Monaco', 'MC'),
(113, 'Mongolia', 'MN'),
(114, 'Montenegro', 'ME'),
(115, 'Morocco', 'MA'),
(116, 'Mozambique', 'MZ'),
(117, 'Myanmar (Burma)', 'MM'),
(118, 'Namibia', 'NA'),
(119, 'Nauru', 'NR'),
(120, 'Nepal', 'NP'),
(121, 'Netherlands', 'NL'),
(122, 'New Zealand', 'NZ'),
(123, 'Nicaragua', 'NI'),
(124, 'Niger', 'NE'),
(125, 'Nigeria', 'NG'),
(126, 'North Korea', 'KP'),
(127, 'North Macedonia', 'MK'),
(128, 'Norway', 'NO'),
(129, 'Oman', 'OM'),
(130, 'Pakistan', 'PK'),
(131, 'Palau', 'PW'),
(132, 'Palestine', 'PS'),
(133, 'Panama', 'PA'),
(134, 'Papua New Guinea', 'PG'),
(135, 'Paraguay', 'PY'),
(136, 'Peru', 'PE'),
(137, 'Philippines', 'PH'),
(138, 'Poland', 'PL'),
(139, 'Portugal', 'PT'),
(140, 'Qatar', 'QA'),
(141, 'Romania', 'RO'),
(142, 'Russia', 'RU'),
(143, 'Rwanda', 'RW'),
(144, 'Saint Kitts and Nevis', 'KN'),
(145, 'Saint Lucia', 'LC'),
(146, 'Saint Vincent and the Grenadines', 'VC'),
(147, 'Samoa', 'WS'),
(148, 'San Marino', 'SM'),
(149, 'Sao Tome and Principe', 'ST'),
(150, 'Saudi Arabia', 'SA'),
(151, 'Senegal', 'SN'),
(152, 'Serbia', 'RS'),
(153, 'Seychelles', 'SC'),
(154, 'Sierra Leone', 'SL'),
(155, 'Singapore', 'SG'),
(156, 'Slovakia', 'SK'),
(157, 'Slovenia', 'SI'),
(158, 'Solomon Islands', 'SB'),
(159, 'Somalia', 'SO'),
(160, 'South Africa', 'ZA'),
(161, 'South Korea', 'KR'),
(162, 'South Sudan', 'SS'),
(163, 'Spain', 'ES'),
(164, 'Sri Lanka', 'LK'),
(165, 'Sudan', 'SD'),
(166, 'Suriname', 'SR'),
(167, 'Sweden', 'SE'),
(168, 'Switzerland', 'CH'),
(169, 'Syria', 'SY'),
(170, 'Taiwan', 'TW'),
(171, 'Tajikistan', 'TJ'),
(172, 'Tanzania', 'TZ'),
(173, 'Thailand', 'TH'),
(174, 'Timor-Leste', 'TL'),
(175, 'Togo', 'TG'),
(176, 'Tonga', 'TO'),
(177, 'Trinidad and Tobago', 'TT'),
(178, 'Tunisia', 'TN'),
(179, 'Turkey', 'TR'),
(180, 'Turkmenistan', 'TM'),
(181, 'Tuvalu', 'TV'),
(182, 'Uganda', 'UG'),
(183, 'Ukraine', 'UA'),
(184, 'United Arab Emirates', 'AE'),
(185, 'United Kingdom', 'GB'),
(186, 'United States', 'US'),
(187, 'Uruguay', 'UY'),
(188, 'Uzbekistan', 'UZ'),
(189, 'Vanuatu', 'VU'),
(190, 'Vatican City', 'VA'),
(191, 'Venezuela', 'VE'),
(192, 'Vietnam', 'VN'),
(193, 'Yemen', 'YE'),
(194, 'Zambia', 'ZM'),
(195, 'Zimbabwe', 'ZW');

-- --------------------------------------------------------

--
-- Table structure for table `country_currencies`
--

CREATE TABLE `country_currencies` (
  `country_id` int(11) NOT NULL,
  `currency_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `country_currencies`
--

INSERT INTO `country_currencies` (`country_id`, `currency_id`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 14),
(5, 4),
(6, 131),
(7, 5),
(8, 6),
(9, 7),
(10, 14),
(11, 8),
(12, 9),
(13, 10),
(14, 11),
(15, 12),
(16, 13),
(17, 14),
(18, 15),
(19, 16),
(20, 17),
(21, 18),
(22, 19),
(23, 20),
(24, 21),
(25, 22),
(26, 23),
(27, 16),
(28, 24),
(29, 25),
(30, 26),
(31, 27),
(32, 28),
(33, 27),
(34, 27),
(35, 29),
(36, 30),
(37, 31),
(38, 32),
(39, 27),
(40, 33),
(41, 34),
(42, 35),
(43, 36),
(44, 14),
(45, 37),
(46, 38),
(47, 39),
(48, 40),
(49, 41),
(50, 42),
(51, 43),
(52, 44),
(53, 27),
(54, 45),
(55, 46),
(56, 47),
(57, 48),
(58, 49),
(59, 50),
(60, 51),
(61, 52),
(62, 53),
(63, 54),
(64, 55),
(65, 56),
(66, 57),
(67, 58),
(68, 59),
(69, 60),
(70, 16),
(71, 61),
(72, 62),
(73, 63),
(74, 64),
(75, 65),
(76, 66),
(77, 67),
(78, 68),
(79, 69),
(80, 70),
(81, 71),
(82, 72),
(83, 73),
(84, 74),
(85, 75),
(86, 76),
(87, 77),
(88, 78),
(89, 79),
(90, 80),
(91, 81),
(92, 82),
(93, 83),
(94, 84),
(95, 85),
(96, 86),
(97, 87),
(98, 88),
(99, 89),
(100, 90),
(101, 91),
(102, 92),
(103, 93),
(104, 94),
(105, 95),
(106, 96),
(107, 97),
(108, 98),
(109, 99),
(110, 100),
(111, 101),
(112, 102),
(113, 103),
(114, 104),
(115, 105),
(116, 106),
(117, 107),
(118, 108),
(119, 109),
(120, 110),
(121, 111),
(122, 112),
(123, 113),
(124, 16),
(125, 114),
(126, 115),
(127, 116),
(128, 117),
(129, 118),
(130, 119),
(131, 120),
(132, 121),
(133, 122),
(134, 123),
(135, 124),
(136, 125),
(137, 126),
(138, 127),
(139, 128),
(140, 129),
(141, 130),
(142, 131),
(143, 132),
(144, 133),
(145, 134),
(146, 135),
(147, 136),
(148, 137),
(149, 138),
(150, 139),
(151, 140),
(152, 141),
(153, 142),
(154, 143);

-- --------------------------------------------------------

--
-- Table structure for table `credit_facility`
--

CREATE TABLE `credit_facility` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `agent_id` int(10) UNSIGNED NOT NULL,
  `balance` double NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `code` varchar(3) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`id`, `code`, `name`, `symbol`) VALUES
(1, 'AED', 'United Arab Emirates Dirham', 'د.إ'),
(2, 'AFN', 'Afghan Afghani', '؋'),
(3, 'ALL', 'Albanian Lek', 'L'),
(4, 'AMD', 'Armenian Dram', '֏'),
(5, 'ANG', 'Netherlands Antillean Guilder', 'ƒ'),
(6, 'AOA', 'Angolan Kwanza', 'Kz'),
(7, 'ARS', 'Argentine Peso', '$'),
(8, 'AUD', 'Australian Dollar', 'A$'),
(9, 'AWG', 'Aruban Florin', 'ƒ'),
(10, 'AZN', 'Azerbaijani Manat', '₼'),
(11, 'BAM', 'Bosnia-Herzegovina Convertible Mark', 'KM'),
(12, 'BBD', 'Barbadian Dollar', '$'),
(13, 'BDT', 'Bangladeshi Taka', '৳'),
(14, 'BGN', 'Bulgarian Lev', 'лв'),
(15, 'BHD', 'Bahraini Dinar', '.د.ب'),
(16, 'BIF', 'Burundian Franc', 'FBu'),
(17, 'BMD', 'Bermudian Dollar', '$'),
(18, 'BND', 'Brunei Dollar', '$'),
(19, 'BOB', 'Bolivian Boliviano', 'Bs'),
(20, 'BRL', 'Brazilian Real', 'R$'),
(21, 'BSD', 'Bahamian Dollar', '$'),
(22, 'BTN', 'Bhutanese Ngultrum', 'Nu.'),
(23, 'BWP', 'Botswana Pula', 'P'),
(24, 'BYN', 'Belarusian Ruble', 'Br'),
(25, 'BZD', 'Belize Dollar', '$'),
(26, 'CAD', 'Canadian Dollar', 'C$'),
(27, 'CDF', 'Congolese Franc', 'FC'),
(28, 'CHF', 'Swiss Franc', 'CHF'),
(29, 'CLP', 'Chilean Peso', '$'),
(30, 'CNY', 'Chinese Yuan', '¥'),
(31, 'COP', 'Colombian Peso', '$'),
(32, 'CRC', 'Costa Rican Colón', '₡'),
(33, 'CUP', 'Cuban Peso', '$'),
(34, 'CVE', 'Cape Verdean Escudo', 'Esc'),
(35, 'CZK', 'Czech Koruna', 'Kč'),
(36, 'DJF', 'Djiboutian Franc', 'Fdj'),
(37, 'DKK', 'Danish Krone', 'kr'),
(38, 'DOP', 'Dominican Peso', 'RD$'),
(39, 'DZD', 'Algerian Dinar', 'دج'),
(40, 'EGP', 'Egyptian Pound', '£'),
(41, 'ERN', 'Eritrean Nakfa', 'Nfk'),
(42, 'ETB', 'Ethiopian Birr', 'Br'),
(43, 'EUR', 'Euro', '€'),
(44, 'FJD', 'Fijian Dollar', '$'),
(45, 'FKP', 'Falkland Islands Pound', '£'),
(46, 'FOK', 'Faroese Króna', 'kr'),
(47, 'GBP', 'British Pound Sterling', '£'),
(48, 'GEL', 'Georgian Lari', '₾'),
(49, 'GGP', 'Guernsey Pound', '£'),
(50, 'GHS', 'Ghanaian Cedi', '₵'),
(51, 'GIP', 'Gibraltar Pound', '£'),
(52, 'GMD', 'Gambian Dalasi', 'D'),
(53, 'GNF', 'Guinean Franc', 'FG'),
(54, 'GTQ', 'Guatemalan Quetzal', 'Q'),
(55, 'GYD', 'Guyanese Dollar', '$'),
(56, 'HKD', 'Hong Kong Dollar', 'HK$'),
(57, 'HNL', 'Honduran Lempira', 'L'),
(58, 'HRK', 'Croatian Kuna', 'kn'),
(59, 'HTG', 'Haitian Gourde', 'G'),
(60, 'HUF', 'Hungarian Forint', 'Ft'),
(61, 'IDR', 'Indonesian Rupiah', 'Rp'),
(62, 'ILS', 'Israeli New Shekel', '₪'),
(63, 'IMP', 'Isle of Man Pound', '£'),
(64, 'INR', 'Indian Rupee', '₹'),
(65, 'IQD', 'Iraqi Dinar', 'ع.د'),
(66, 'IRR', 'Iranian Rial', '﷼'),
(67, 'ISK', 'Icelandic Króna', 'kr'),
(68, 'JEP', 'Jersey Pound', '£'),
(69, 'JMD', 'Jamaican Dollar', 'J$'),
(70, 'JOD', 'Jordanian Dinar', 'د.ا'),
(71, 'JPY', 'Japanese Yen', '¥'),
(72, 'KES', 'Kenyan Shilling', 'Sh'),
(73, 'KGS', 'Kyrgyzstani Som', 'с'),
(74, 'KHR', 'Cambodian Riel', '៛'),
(75, 'KID', 'Kiribati Dollar', '$'),
(76, 'KMF', 'Comorian Franc', 'CF'),
(77, 'KRW', 'South Korean Won', '₩'),
(78, 'KWD', 'Kuwaiti Dinar', 'د.ك'),
(79, 'KYD', 'Cayman Islands Dollar', '$'),
(80, 'KZT', 'Kazakhstani Tenge', '₸'),
(81, 'LAK', 'Lao Kip', '₭'),
(82, 'LBP', 'Lebanese Pound', 'ل.ل'),
(83, 'LKR', 'Sri Lankan Rupee', 'Rs'),
(84, 'LRD', 'Liberian Dollar', '$'),
(85, 'LSL', 'Lesotho Loti', 'L'),
(86, 'LYD', 'Libyan Dinar', 'ل.د'),
(87, 'MAD', 'Moroccan Dirham', 'د.م.'),
(88, 'MDL', 'Moldovan Leu', 'L'),
(89, 'MGA', 'Malagasy Ariary', 'Ar'),
(90, 'MKD', 'Macedonian Denar', 'ден'),
(91, 'MMK', 'Burmese Kyat', 'Ks'),
(92, 'MNT', 'Mongolian Tögrög', '₮'),
(93, 'MOP', 'Macanese Pataca', 'MOP$'),
(94, 'MRU', 'Mauritanian Ouguiya', 'UM'),
(95, 'MUR', 'Mauritian Rupee', '₨'),
(96, 'MVR', 'Maldivian Rufiyaa', 'Rf'),
(97, 'MWK', 'Malawian Kwacha', 'MK'),
(98, 'MXN', 'Mexican Peso', '$'),
(99, 'MYR', 'Malaysian Ringgit', 'RM'),
(100, 'MZN', 'Mozambican Metical', 'MT'),
(101, 'NAD', 'Namibian Dollar', '$'),
(102, 'NGN', 'Nigerian Naira', '₦'),
(103, 'NIO', 'Nicaraguan Córdoba', 'C$'),
(104, 'NOK', 'Norwegian Krone', 'kr'),
(105, 'NPR', 'Nepalese Rupee', 'Rs'),
(106, 'NZD', 'New Zealand Dollar', '$'),
(107, 'OMR', 'Omani Rial', '﷼'),
(108, 'PAB', 'Panamanian Balboa', 'B/.'),
(109, 'PEN', 'Peruvian Sol', 'S/'),
(110, 'PGK', 'Papua New Guinean Kina', 'K'),
(111, 'PHP', 'Philippine Peso', '₱'),
(112, 'PKR', 'Pakistani Rupee', '₨'),
(113, 'PLN', 'Polish Złoty', 'zł'),
(114, 'PYG', 'Paraguayan Guaraní', '₲'),
(115, 'QAR', 'Qatari Riyal', 'ر.ق'),
(116, 'RON', 'Romanian Leu', 'lei'),
(117, 'RSD', 'Serbian Dinar', 'дин'),
(118, 'RUB', 'Russian Ruble', '₽'),
(119, 'RWF', 'Rwandan Franc', 'FRw'),
(120, 'SAR', 'Saudi Riyal', 'ر.س'),
(121, 'SBD', 'Solomon Islands Dollar', '$'),
(122, 'SCR', 'Seychellois Rupee', '₨'),
(123, 'SDG', 'Sudanese Pound', 'ج.س.'),
(124, 'SEK', 'Swedish Krona', 'kr'),
(125, 'SGD', 'Singapore Dollar', '$'),
(126, 'SHP', 'Saint Helena Pound', '£'),
(127, 'SLL', 'Sierra Leonean Leone', 'Le'),
(128, 'SOS', 'Somali Shilling', 'Sh'),
(129, 'SRD', 'Surinamese Dollar', '$'),
(130, 'SSP', 'South Sudanese Pound', '£'),
(131, 'STN', 'São Tomé and Príncipe Dobra', 'Db'),
(132, 'SYP', 'Syrian Pound', '£'),
(133, 'SZL', 'Eswatini Lilangeni', 'L'),
(134, 'THB', 'Thai Baht', '฿'),
(135, 'TJS', 'Tajikistani Somoni', 'SM'),
(136, 'TMT', 'Turkmenistani Manat', 'T'),
(137, 'TND', 'Tunisian Dinar', 'د.ت'),
(138, 'TOP', 'Tongan Paʻanga', 'T$'),
(139, 'TRY', 'Turkish Lira', '₺'),
(140, 'TTD', 'Trinidad and Tobago Dollar', '$'),
(141, 'TVD', 'Tuvaluan Dollar', '$'),
(142, 'TWD', 'New Taiwan Dollar', 'NT$'),
(143, 'TZS', 'Tanzanian Shilling', 'Sh'),
(144, 'UAH', 'Ukrainian Hryvnia', '₴'),
(145, 'UGX', 'Ugandan Shilling', 'Sh'),
(146, 'USD', 'United States Dollar', '$'),
(147, 'UYU', 'Uruguayan Peso', '$'),
(148, 'UZS', 'Uzbekistani Soʻm', 'сўм'),
(149, 'VES', 'Venezuelan Bolívar', 'Bs.'),
(150, 'VND', 'Vietnamese Đồng', '₫'),
(151, 'VUV', 'Vanuatu Vatu', 'Vt'),
(152, 'WST', 'Samoan Tālā', 'T'),
(153, 'XAF', 'Central African CFA Franc', 'FCFA'),
(154, 'XCD', 'East Caribbean Dollar', '$'),
(155, 'XOF', 'West African CFA Franc', 'CFA'),
(156, 'XPF', 'CFP Franc', '₣'),
(157, 'YER', 'Yemeni Rial', '﷼'),
(158, 'ZAR', 'South African Rand', 'R'),
(159, 'ZMW', 'Zambian Kwacha', 'ZK'),
(160, 'ZWL', 'Zimbabwean Dollar', '$');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fa_type`
--

CREATE TABLE `fa_type` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fa_type`
--

INSERT INTO `fa_type` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'authenticator', '2024-10-30 07:24:14', '2024-10-30 07:24:14'),
(2, 'email', '2024-10-30 07:24:14', '2024-10-30 07:24:14');

-- --------------------------------------------------------

--
-- Table structure for table `general_ledgers`
--

CREATE TABLE `general_ledgers` (
  `id` int(11) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `account_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `debit` decimal(10,2) DEFAULT 0.00,
  `credit` decimal(10,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `invoice_id` int(11) DEFAULT NULL,
  `voucher_number` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `invoice_detail_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `general_ledgers`
--

INSERT INTO `general_ledgers` (`id`, `transaction_date`, `account_id`, `company_id`, `transaction_id`, `description`, `debit`, `credit`, `balance`, `created_at`, `updated_at`, `invoice_id`, `voucher_number`, `name`, `type`, `invoice_detail_id`, `invoice_number`) VALUES
(104, '2024-11-22 07:53:45', 148, 15, 33, 'Payment need to be made to: Amadeus', 250.00, 0.00, 250.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 40, NULL, 'Amadeus', 'payable', 29, NULL),
(105, '2024-11-22 07:53:45', 156, 15, 33, 'Payment need to be received from: Khaled Alajmi', 0.00, 300.00, 300.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 40, NULL, 'Khaled Alajmi', 'receivable', 29, NULL),
(106, '2024-11-22 07:53:45', 141, 15, 33, 'Price markup by Agent: Soud Shoja', 0.00, 50.00, 50.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 40, NULL, 'Soud Shoja', 'income', 29, NULL),
(107, '2024-11-22 07:53:45', 148, 15, 34, 'Payment need to be made to: Amadeus', 565.00, 0.00, 565.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 40, NULL, 'Amadeus', 'payable', 30, NULL),
(108, '2024-11-22 07:53:45', 156, 15, 34, 'Payment need to be received from: Khaled Alajmi', 0.00, 600.00, 600.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 40, NULL, 'Khaled Alajmi', 'receivable', 30, NULL),
(109, '2024-11-22 07:53:45', 141, 15, 34, 'Price markup by Agent: Soud Shoja', 0.00, 35.00, 35.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 40, NULL, 'Soud Shoja', 'income', 30, NULL),
(110, '2024-11-22 07:59:09', 145, 15, 35, 'Payment transfered to: Invoice Payments', 300.00, 0.00, 300.00, '2024-11-21 23:59:09', '2024-11-21 23:59:09', 40, 'VOU-2024-00068', 'Invoice Payments', 'bank', 29, NULL),
(111, '2024-11-22 07:59:09', 147, 15, 8, 'Payment Charged For:Tap Charges', 0.00, 0.35, 3.35, '2024-11-21 23:59:09', '2024-11-21 23:59:09', 40, 'VOU-2024-00068', 'Tap Charges', 'charges', 29, NULL),
(112, '2024-11-22 07:59:09', 145, 15, 36, 'Payment transfered to: Invoice Payments', 600.00, 0.00, 600.00, '2024-11-21 23:59:09', '2024-11-21 23:59:09', 40, 'VOU-2024-00068', 'Invoice Payments', 'bank', 30, NULL),
(113, '2024-11-22 07:59:09', 147, 15, 8, 'Payment Charged For:Tap Charges', 0.00, 0.35, 4.05, '2024-11-21 23:59:09', '2024-11-21 23:59:09', 40, 'VOU-2024-00068', 'Tap Charges', 'charges', 30, NULL),
(114, '2024-11-22 08:37:10', 148, 15, 37, 'Payment need to be made to: Amadeus', 657.75, 0.00, 657.75, '2024-11-22 00:37:10', '2024-11-22 00:37:10', 41, NULL, 'Amadeus', 'payable', 31, NULL),
(115, '2024-11-22 08:37:10', 164, 15, 37, 'Payment need to be received from: Naser Alajmi', 0.00, 750.00, 750.00, '2024-11-22 00:37:10', '2024-11-22 00:37:10', 41, NULL, 'Naser Alajmi', 'receivable', 31, NULL),
(116, '2024-11-22 08:37:10', 141, 15, 37, 'Price markup by Agent: Soud Shoja', 0.00, 92.25, 92.25, '2024-11-22 00:37:10', '2024-11-22 00:37:10', 41, NULL, 'Soud Shoja', 'income', 31, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hotels`
--

CREATE TABLE `hotels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL,
  `zip_code` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `website` varchar(255) NOT NULL,
  `rating` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hotels`
--

INSERT INTO `hotels` (`id`, `name`, `address`, `city`, `state`, `country`, `zip_code`, `phone`, `email`, `website`, `rating`, `image`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Hotel California', '123 Sunset Blvd', 'Los Angeles', 'CA', 'USA', '90001', '123-456-7890', 'info@hotelcalifornia.com', 'www.hotelcalifornia.com', '4.5', 'hotel_california.jpg', 'A lovely place.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(2, 'The Grand Budapest Hotel', '456 Mountain Rd', 'Zubrowka', '', 'Fictional', '12345', '987-654-3210', 'info@grandbudapest.com', 'www.grandbudapest.com', '5.0', 'grand_budapest.jpg', 'A grand hotel.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(3, 'The Overlook Hotel', '789 Snowy Ln', 'Sidewinder', 'CO', 'USA', '80483', '555-123-4567', 'info@overlookhotel.com', 'www.overlookhotel.com', '4.0', 'overlook_hotel.jpg', 'A haunted hotel.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(4, 'Bates Motel', '101 Psycho St', 'Fairvale', 'CA', 'USA', '90002', '555-987-6543', 'info@batesmotel.com', 'www.batesmotel.com', '3.5', 'bates_motel.jpg', 'A creepy motel.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(5, 'The Continental', '202 Assassin Ave', 'New York', 'NY', 'USA', '10001', '555-111-2222', 'info@thecontinental.com', 'www.thecontinental.com', '4.8', 'the_continental.jpg', 'A hotel for assassins.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(6, 'Hotel Transylvania', '303 Monster Blvd', 'Transylvania', '', 'Romania', '67890', '555-333-4444', 'info@hoteltransylvania.com', 'www.hoteltransylvania.com', '4.2', 'hotel_transylvania.jpg', 'A hotel for monsters.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(7, 'The Ritz-Carlton', '404 Luxury Ln', 'Paris', '', 'France', '75001', '555-555-6666', 'info@ritzcarlton.com', 'www.ritzcarlton.com', '5.0', 'ritz_carlton.jpg', 'A luxury hotel.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(8, 'The Plaza Hotel', '505 Central Park S', 'New York', 'NY', 'USA', '10019', '555-777-8888', 'info@theplaza.com', 'www.theplaza.com', '4.7', 'plaza_hotel.jpg', 'A historic hotel.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(9, 'The Shining Hotel', '606 Horror Rd', 'Sidewinder', 'CO', 'USA', '80483', '555-999-0000', 'info@shininghotel.com', 'www.shininghotel.com', '4.3', 'shining_hotel.jpg', 'A horror hotel.', '2024-10-16 19:17:28', '2024-10-16 19:17:28'),
(10, 'The Great Northern Hotel', '707 Twin Peaks Dr', 'Twin Peaks', 'WA', 'USA', '98201', '555-222-3333', 'info@greatnorthern.com', 'www.greatnorthern.com', '4.6', 'great_northern.jpg', 'A mysterious hotel.', '2024-10-16 19:17:28', '2024-10-16 19:17:28');

-- --------------------------------------------------------

--
-- Table structure for table `incomes`
--

CREATE TABLE `incomes` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `client_id` bigint(20) UNSIGNED DEFAULT NULL,
  `agent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `currency` varchar(50) DEFAULT NULL,
  `paid_date` timestamp NULL DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `swift_no` varchar(255) DEFAULT NULL,
  `iban_no` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `shipping` decimal(10,2) DEFAULT NULL,
  `accept_payment` varchar(255) DEFAULT NULL,
  `sub_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `client_id`, `agent_id`, `amount`, `status`, `created_at`, `updated_at`, `currency`, `paid_date`, `invoice_date`, `due_date`, `label`, `account_number`, `bank_name`, `swift_no`, `iban_no`, `country`, `tax`, `discount`, `shipping`, `accept_payment`, `sub_amount`) VALUES
(40, 'INV-2024-00463', 33, 16, 900.00, 'paid', '2024-11-21 23:53:45', '2024-11-21 23:59:09', 'KWD', '2024-11-21 23:59:09', '2024-11-22', '2024-11-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 900.00),
(41, 'INV-2024-00466', 41, 16, 750.00, 'unpaid', '2024-11-22 00:37:10', '2024-11-22 00:37:10', 'KWD', NULL, '2024-11-22', '2024-11-30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 750.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_details`
--

CREATE TABLE `invoice_details` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `task_id` int(11) NOT NULL,
  `task_description` varchar(255) DEFAULT NULL,
  `task_remark` varchar(255) DEFAULT NULL,
  `task_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `quantity` int(11) DEFAULT 1,
  `markup_price` decimal(10,2) DEFAULT NULL,
  `supplier_price` decimal(10,2) DEFAULT NULL,
  `paid` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_details`
--

INSERT INTO `invoice_details` (`id`, `invoice_id`, `invoice_number`, `task_id`, `task_description`, `task_remark`, `task_price`, `created_at`, `updated_at`, `quantity`, `markup_price`, `supplier_price`, `paid`) VALUES
(29, 40, 'INV-2024-00463', 20, 'T12347 - Flight First Class (Kuwait Airways)', NULL, 300.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 1, 50.00, 250.00, 0),
(30, 40, 'INV-2024-00463', 21, '912316582 - Hotel Double Bed (Adagio Premium The Palm, Dubai, United Arab Emirates)', NULL, 600.00, '2024-11-21 23:53:45', '2024-11-21 23:53:45', 1, 35.00, 565.00, 0),
(31, 41, 'INV-2024-00466', 22, 'MW8621716 - Hotel King Bed Deluxe High Floor (2408 Oaks Liwa Heights, Jumeirah Lake Towers)', NULL, 750.00, '2024-11-22 00:37:10', '2024-11-22 00:37:10', 1, 92.25, 657.75, 0);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_sequences`
--

CREATE TABLE `invoice_sequences` (
  `id` int(11) NOT NULL,
  `current_sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_sequences`
--

INSERT INTO `invoice_sequences` (`id`, `current_sequence`, `created_at`, `updated_at`) VALUES
(1, 467, '2024-11-06 04:50:51', '2024-11-22 00:36:53');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_ref` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `item_type` varchar(255) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `item_status` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `contract_id` varchar(100) DEFAULT NULL,
  `contract_code` varchar(255) DEFAULT NULL,
  `time_signed` varchar(100) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `agent_email` varchar(100) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `payment_date` varchar(100) DEFAULT NULL,
  `paid` tinyint(1) DEFAULT NULL,
  `payment_time` varchar(100) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `refunded` decimal(10,2) DEFAULT NULL,
  `trip_name` varchar(255) DEFAULT NULL,
  `trip_code` varchar(100) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `item_ref`, `description`, `item_type`, `client_id`, `item_status`, `created_at`, `updated_at`, `contract_id`, `contract_code`, `time_signed`, `client_email`, `agent_email`, `total_price`, `payment_date`, `paid`, `payment_time`, `payment_amount`, `refunded`, `trip_name`, `trip_code`, `agent_id`) VALUES
(19, NULL, 'Book flight tickets', NULL, NULL, 'active', '2024-09-18 18:03:06', '2024-09-18 18:03:06', '122', '0', NULL, 'anisah@gmail.com', 'mamat@gmail.com', 1230.00, NULL, 0, NULL, NULL, NULL, 'walk in Jakarta', NULL, NULL),
(20, NULL, 'Arrange accommodation', NULL, NULL, 'active', '2024-09-18 18:03:06', '2024-09-18 18:03:06', '123', '0', NULL, 'amalina@gmail.com', 'zura@gmail.com', 4500.00, NULL, 0, NULL, NULL, NULL, 'Paris wait for me', NULL, NULL),
(21, NULL, 'Submit visa application', NULL, NULL, 'active', '2024-09-18 18:03:06', '2024-09-18 18:03:06', '124', '0', NULL, 'kamal@gmail.com', 'zura@gmail.com', 2500.00, NULL, 1, NULL, 2500.00, NULL, 'Kembara Tioman', NULL, NULL),
(30, NULL, 'Book Hotel Wangsa', NULL, 8, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '1221', 'ODDO', NULL, 'anisah@gmail.com', 'markrizam@gmail.com', 1230.00, NULL, 0, NULL, NULL, NULL, 'walk in Jakarta', NULL, 8),
(31, NULL, 'Book Travel Van ', NULL, 9, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '1231', 'DOOO', NULL, 'amalina@gmail.com', 'markrizam@gmail.com', 4500.00, NULL, 0, NULL, NULL, NULL, 'Paris wait for me', NULL, 8),
(32, NULL, 'Book Museum Tickets', NULL, 17, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '1254', 'TEEE', NULL, 'john@example.com', 'markrizam@gmail.com', 2100.00, NULL, 0, NULL, NULL, NULL, 'Cultural Walk', NULL, 8),
(33, NULL, 'Book River Cruise', NULL, 18, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '2341', 'BLLL', NULL, 'emily@gmail.com', 'markrizam@gmail.com', 5000.00, NULL, 1, '1500', 5000.00, NULL, 'River Adventure', NULL, 8),
(34, NULL, 'Book Safari Tickets', NULL, 19, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '1299', 'GFEE', NULL, 'dave@example.com', 'markrizam@gmail.com', 3600.00, NULL, 0, NULL, NULL, NULL, 'Wild Safari Trip', NULL, 8),
(35, NULL, 'Book Travel Van', NULL, 20, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '1234', 'HHHH', NULL, 'mike@example.com', 'markrizam@gmail.com', 4000.00, NULL, 1, '1400', 4000.00, NULL, 'Urban Exploration', NULL, 8),
(36, NULL, 'Book Concert Tickets', NULL, 21, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '1567', 'IUUU', NULL, 'sarah@example.com', 'markrizam@gmail.com', 1800.00, NULL, 0, NULL, NULL, NULL, 'Music Under the Stars', NULL, 8),
(37, NULL, 'Book Travel Van', NULL, 22, 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '1789', 'JJJJ', NULL, 'jack@example.com', 'markrizam@gmail.com', 3000.00, NULL, 0, NULL, NULL, NULL, 'Scenic Drive through City', NULL, 8);

-- --------------------------------------------------------

--
-- Table structure for table `items_status`
--

CREATE TABLE `items_status` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_status_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2024_08_22_083438_create_companies_table', 1),
(5, '2024_08_22_093322_create_agents_table', 1),
(6, '2024_08_22_093937_create_credit_facility_table', 1),
(7, '2024_08_22_095323_create_agent_type_table', 1),
(8, '2024_08_23_021722_create_clients_table', 2),
(9, '2024_08_23_022024_create_tasks_table', 2),
(10, '2024_08_23_022232_create_items_table', 2),
(11, '2024_08_23_022517_create_items_status_table', 2),
(12, '2024_08_23_022618_create_transactions_table', 2),
(13, '2024_08_26_041829_add_first_login_to_users_table', 2),
(14, '2024_08_27_030943_add_two_factor_to_users_table', 3),
(15, '2024_08_27_064222_create_fa_type_table', 3),
(16, '2024_09_18_023528_create_roles_table', 4),
(17, '2024_10_29_032151_create_charges_table', 5);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `voucher_number` varchar(255) DEFAULT NULL,
  `from` varchar(255) DEFAULT NULL,
  `pay_to` varchar(255) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(50) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` enum('credit_card','debit_card','cash','bank_transfer','paypal','other') DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `account_number` varchar(255) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `swift_no` varchar(255) DEFAULT NULL,
  `iban_no` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT NULL,
  `shipping` decimal(10,2) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `voucher_number`, `from`, `pay_to`, `account_id`, `amount`, `currency`, `payment_date`, `payment_method`, `status`, `created_at`, `updated_at`, `account_number`, `bank_name`, `swift_no`, `iban_no`, `country`, `tax`, `discount`, `shipping`, `payment_reference`, `invoice_id`) VALUES
(8, 'VOU-2024-00068', 'Khaled Alajmi', 'CITY TRAVELERS', 156, 900.00, 'KWD', '2024-11-22 07:58:19', 'credit_card', 'completed', '2024-11-21 23:58:19', '2024-11-21 23:59:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'chg_TS04A4420241057b2R92211427', 40);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', '2024-10-30 07:33:41', '2024-10-30 07:33:41'),
(2, 'company', '2024-10-30 07:33:41', '2024-10-30 07:33:41'),
(3, 'agent', '2024-10-30 07:33:41', '2024-10-30 07:33:41'),
(4, 'client', '2024-10-30 07:33:41', '2024-10-30 07:33:41'),
(5, 'accountant', '2024-10-30 07:33:41', '2024-10-30 07:33:41');

-- --------------------------------------------------------

--
-- Table structure for table `sequences`
--

CREATE TABLE `sequences` (
  `id` int(11) NOT NULL,
  `sequence_for` varchar(255) DEFAULT NULL,
  `current_sequence` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sequences`
--

INSERT INTO `sequences` (`id`, `sequence_for`, `current_sequence`, `created_at`, `updated_at`) VALUES
(1, 'VOUCHER', 69, '2024-11-11 04:00:31', '2024-11-21 23:58:19'),
(2, 'INVOICE', 1, '2024-11-11 04:00:55', '2024-11-11 04:00:55');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('lESTv6QmxKoCRqk5iU73UDcpf9bIsXWslF9ZRVSt', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiOWFtMjFsOFROaUt0Q09YanlmTk8xOTR3bk1kSlNxODhJZHhmWmJMNCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjMzOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvdHJhbnNhY3Rpb24iO31zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=', 1732267444),
('YghU5MJMhPccirEBYUIdRFdDqcpF8Nz52Al9TfGR', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiR0thcjR1T3BXQXlnRDkxT2hlV2YzZllaYkNzOWVhbGEwTjVSZGxVQyI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjMzOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvdHJhbnNhY3Rpb24iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=', 1732250896);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country_id` int(11) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `email`, `phone`, `address`, `city`, `state`, `postal_code`, `country_id`, `website`, `payment_terms`, `created_at`, `updated_at`) VALUES
(1, 'TravelCorp', 'Alice Johnson', 'alice@travelcorp.com', '123-456-7890', '123 Travel St', 'New York', 'NY', '10001', 163, 'www.travelcorp.com', 'Net 30', '2024-10-18 04:04:09', '2024-11-06 04:38:28'),
(2, 'Global Tours', 'Bob Smith', 'bob@globaltours.com', '234-567-8901', '456 Adventure Blvd', 'Los Angeles', 'CA', '90001', 163, 'www.globaltours.com', 'Net 45', '2024-10-18 04:04:09', '2024-11-06 04:38:28'),
(3, 'Holiday Makers', 'Charlie Brown', 'charlie@holidaymakers.com', '345-678-9012', '789 Vacation Rd', 'Chicago', 'IL', '60601', 163, 'www.holidaymakers.com', 'Net 60', '2024-10-18 04:04:09', '2024-11-06 04:38:28'),
(4, 'World Travel Network', 'Diana Prince', 'diana@worldtravel.com', '456-789-0123', '321 Globe Ave', 'Miami', 'FL', '33101', 163, 'www.worldtravel.com', 'Due on Receipt', '2024-10-18 04:04:09', '2024-11-06 04:38:28'),
(5, 'Jetsetter Services', 'Ethan Hunt', 'ethan@jetsetter.com', '567-890-1234', '654 Jet Rd', 'Seattle', 'WA', '98101', 163, 'www.jetsetter.com', 'Net 30', '2024-10-18 04:04:09', '2024-11-06 04:38:28'),
(6, 'Kuwait Travel Agency', 'Fatima Al-Sabah', 'fatima@kuwaittravel.com', '965-2222-1111', '10 Souq Al-Mubarakiya', 'Kuwait City', 'Kuwait', '13001', 163, 'www.kuwaittravel.com', 'Net 30', '2024-10-18 04:05:06', '2024-11-06 04:38:28'),
(7, 'Kuwait Air Tours', 'Ahmed Al-Fahad', 'ahmed@kuwaitairtours.com', '965-2222-2222', '15 Salmiya St', 'Salmiya', 'Kuwait', '22003', 163, 'www.kuwaitairtours.com', 'Net 45', '2024-10-18 04:05:06', '2024-11-06 04:38:28'),
(8, 'Desert Adventures', 'Sara Al-Hassan', 'sara@desertadventures.com', '965-2222-3333', '25 Arabian Gulf St', 'Hawalli', 'Kuwait', '32001', 163, 'www.desertadventures.com', 'Due on Receipt', '2024-10-18 04:05:06', '2024-11-06 04:38:28'),
(9, 'Kuwait Luxury Travel', 'Omar Al-Mansour', 'omar@kuwaitluxury.com', '965-2222-4444', '5 Fahaheel Rd', 'Fahaheel', 'Kuwait', '64001', 163, 'www.kuwaitluxury.com', 'Net 30', '2024-10-18 04:05:06', '2024-11-06 04:38:28'),
(10, 'Kuwait Excursions', 'Layla Al-Rashid', 'layla@kuwaitexcursions.com', '965-2222-5555', '8 Al-Jahra St', 'Al-Jahra', 'Kuwait', '47001', 163, 'www.kuwaiexursions.com', 'Net 60', '2024-10-18 04:05:06', '2024-11-06 04:38:28'),
(11, 'Amadeus', 'john', 'hospitality.support@amadeus.com', NULL, 'madrid, france', 'madrid', 'madrid', '28001', 163, NULL, NULL, '2024-11-06 04:43:13', '2024-11-06 04:43:13');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED DEFAULT NULL,
  `agent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `payment_type` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `tax` decimal(10,2) DEFAULT NULL,
  `surcharge` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `cancellation_policy` text DEFAULT NULL,
  `additional_info` text DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `invoice_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `client_id`, `agent_id`, `type`, `status`, `client_name`, `reference`, `duration`, `payment_type`, `price`, `tax`, `surcharge`, `total`, `cancellation_policy`, `additional_info`, `supplier_id`, `venue`, `invoice_price`) VALUES
(19, 33, 16, 'Flight', 'Completed', 'Khaled Alajmi', 'T12346', 5, 'Credit Card', 125.00, 20.00, 10.00, 155.00, 'Non-refundable', 'Economy class', 11, ' Kuwait Airways', NULL),
(20, 33, 16, 'Flight', 'Completed', 'Khaled Alajmi', 'T12347', 2, 'Credit Card', 200.00, 30.00, 20.00, 250.00, 'Non-refundable', 'First Class', 11, 'Kuwait Airways', NULL),
(21, 33, 16, 'Hotel', 'Completed', 'Khaled Alajmi', '912316582', 4, 'Cash', 160.00, 112.00, 112.00, 565.00, ' Cancellation of reservation or no-show may result in penalties according to rate and contract terms.', 'Double Bed', 11, 'Adagio Premium The Palm, Dubai, United Arab Emirates', NULL),
(22, 41, 16, 'Hotel', 'Assigned', 'Naser Alajmi', 'MW8621716', 5, 'Wallet', 536.95, 120.80, 0.00, 657.75, ' Cancellation of reservation or no-show may result in penalties according to rate and contract terms.', 'King Bed Deluxe High Floor', 11, '2408 Oaks Liwa Heights, Jumeirah Lake Towers', NULL),
(23, 42, 16, 'Hotel', 'Confirmed', 'Fahad Alajmi', 'MW8621716', 5, 'Wallet', 536.95, 120.80, 0.00, 657.75, ' Cancellation of reservation or no-show may result in penalties according to rate and contract terms.', 'King Bed Deluxe High Floor', 11, '2408 Oaks Liwa Heights, Jumeirah Lake Towers', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `task_flight_details`
--

CREATE TABLE `task_flight_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `departure_time` timestamp NULL DEFAULT NULL,
  `departure_from` varchar(255) NOT NULL,
  `arrival_time` timestamp NULL DEFAULT NULL,
  `arrive_to` varchar(255) NOT NULL,
  `terminal` varchar(255) DEFAULT NULL,
  `airline_id` bigint(20) UNSIGNED NOT NULL,
  `flight_number` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `baggage_allowed` varchar(255) NOT NULL,
  `equipment` varchar(255) DEFAULT NULL,
  `flight_meal` varchar(255) DEFAULT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_flight_details`
--

INSERT INTO `task_flight_details` (`id`, `departure_time`, `departure_from`, `arrival_time`, `arrive_to`, `terminal`, `airline_id`, `flight_number`, `class`, `baggage_allowed`, `equipment`, `flight_meal`, `task_id`, `created_at`, `updated_at`) VALUES
(41, '2024-10-15 16:00:00', 'New York', '2024-10-15 20:00:00', 'Los Angeles', 'T1', 1, 'AA100', 'Economy', '2 bags', 'Boeing 737', 'Standard', 1, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(42, '2024-10-16 17:00:00', 'Chicago', '2024-10-16 21:00:00', 'Miami', 'T2', 2, 'UA200', 'Business', '3 bags', 'Airbus A320', 'Vegetarian', 2, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(43, '2024-10-17 18:00:00', 'San Francisco', '2024-10-17 22:00:00', 'Seattle', 'T3', 3, 'DL300', 'First Class', '2 bags', 'Boeing 747', 'Kosher', 3, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(44, '2024-10-18 19:00:00', 'Houston', '2024-10-18 23:00:00', 'Denver', 'T4', 4, 'SW400', 'Economy', '1 bag', 'Boeing 737', 'Halal', 4, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(45, '2024-10-19 20:00:00', 'Boston', '2024-10-20 00:00:00', 'Atlanta', 'T5', 5, 'AA500', 'Business', '2 bags', 'Airbus A320', 'Standard', 5, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(46, '2024-10-20 21:00:00', 'Dallas', '2024-10-21 01:00:00', 'Orlando', 'T6', 6, 'UA600', 'First Class', '3 bags', 'Boeing 747', 'Vegetarian', 6, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(47, '2024-10-21 22:00:00', 'Philadelphia', '2024-10-22 02:00:00', 'Phoenix', 'T7', 7, 'DL700', 'Economy', '2 bags', 'Boeing 737', 'Kosher', 7, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(48, '2024-10-22 23:00:00', 'Las Vegas', '2024-10-23 03:00:00', 'San Diego', 'T8', 8, 'SW800', 'Business', '1 bag', 'Airbus A320', 'Halal', 8, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(49, '2024-10-24 00:00:00', 'Detroit', '2024-10-24 04:00:00', 'Charlotte', 'T9', 9, 'AA900', 'First Class', '2 bags', 'Boeing 747', 'Standard', 9, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(50, '2024-10-25 01:00:00', 'San Jose', '2024-10-25 05:00:00', 'Austin', 'T10', 10, 'UA1000', 'Economy', '3 bags', 'Boeing 737', 'Vegetarian', 10, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(51, '2024-10-26 02:00:00', 'Indianapolis', '2024-10-26 06:00:00', 'Columbus', 'T11', 11, 'DL1100', 'Business', '2 bags', 'Airbus A320', 'Kosher', 11, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(52, '2024-10-27 03:00:00', 'Fort Worth', '2024-10-27 07:00:00', 'San Antonio', 'T12', 12, 'SW1200', 'First Class', '1 bag', 'Boeing 747', 'Halal', 12, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(53, '2024-10-28 04:00:00', 'El Paso', '2024-10-28 08:00:00', 'Nashville', 'T13', 13, 'AA1300', 'Economy', '2 bags', 'Boeing 737', 'Standard', 13, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(54, '2024-10-29 05:00:00', 'Memphis', '2024-10-29 09:00:00', 'Baltimore', 'T14', 14, 'UA1400', 'Business', '3 bags', 'Airbus A320', 'Vegetarian', 14, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(55, '2024-10-30 06:00:00', 'Louisville', '2024-10-30 10:00:00', 'Milwaukee', 'T15', 15, 'DL1500', 'First Class', '2 bags', 'Boeing 747', 'Kosher', 15, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(56, '2024-10-31 07:00:00', 'Albuquerque', '2024-10-31 11:00:00', 'Tucson', 'T16', 16, 'SW1600', 'Economy', '1 bag', 'Boeing 737', 'Halal', 16, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(57, '2024-10-31 08:00:00', 'Fresno', '2024-10-31 12:00:00', 'Sacramento', 'T17', 17, 'AA1700', 'Business', '2 bags', 'Airbus A320', 'Standard', 17, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(58, '2024-11-01 09:00:00', 'Kansas City', '2024-11-01 13:00:00', 'Long Beach', 'T18', 18, 'UA1800', 'First Class', '3 bags', 'Boeing 747', 'Vegetarian', 14, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(59, '2024-11-02 10:00:00', 'Mesa', '2024-11-02 14:00:00', 'Omaha', 'T19', 19, 'DL1900', 'Economy', '2 bags', 'Boeing 737', 'Kosher', 13, '2024-10-16 19:12:54', '2024-10-16 19:12:54'),
(60, '2024-11-03 11:00:00', 'Virginia Beach', '2024-11-03 15:00:00', 'Colorado Springs', 'T20', 20, 'SW2000', 'Business', '1 bag', 'Airbus A320', 'Halal', 12, '2024-10-16 19:12:54', '2024-10-16 19:12:54');

-- --------------------------------------------------------

--
-- Table structure for table `task_hotel_details`
--

CREATE TABLE `task_hotel_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `hotel_id` bigint(20) UNSIGNED NOT NULL,
  `booking_time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `room_number` varchar(255) NOT NULL,
  `room_type` varchar(255) NOT NULL,
  `room_amount` decimal(8,2) NOT NULL,
  `room_details` text DEFAULT NULL,
  `rate` decimal(8,2) NOT NULL,
  `task_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_hotel_details`
--

INSERT INTO `task_hotel_details` (`id`, `hotel_id`, `booking_time`, `check_in`, `check_out`, `room_number`, `room_type`, `room_amount`, `room_details`, `rate`, `task_id`, `created_at`, `updated_at`) VALUES
(41, 1, '2024-10-15 16:00:00', '2024-10-17', '2024-10-20', '101', 'Single', 100.00, 'Sea view', 4.50, 1, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(42, 2, '2024-10-16 17:00:00', '2024-10-18', '2024-10-21', '102', 'Double', 150.00, 'Mountain view', 4.00, 2, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(43, 3, '2024-10-17 18:00:00', '2024-10-19', '2024-10-22', '103', 'Suite', 200.00, 'City view', 5.00, 3, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(44, 4, '2024-10-18 19:00:00', '2024-10-20', '2024-10-23', '104', 'Single', 100.00, 'Garden view', 4.20, 4, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(45, 5, '2024-10-19 20:00:00', '2024-10-21', '2024-10-24', '105', 'Double', 150.00, 'Pool view', 4.80, 5, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(46, 6, '2024-10-20 21:00:00', '2024-10-22', '2024-10-25', '106', 'Suite', 200.00, 'Sea view', 4.90, 6, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(47, 7, '2024-10-21 22:00:00', '2024-10-23', '2024-10-26', '107', 'Single', 100.00, 'Mountain view', 4.30, 7, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(48, 8, '2024-10-22 23:00:00', '2024-10-24', '2024-10-27', '108', 'Double', 150.00, 'City view', 4.70, 8, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(49, 9, '2024-10-24 00:00:00', '2024-10-25', '2024-10-28', '109', 'Suite', 200.00, 'Garden view', 5.00, 9, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(50, 10, '2024-10-25 01:00:00', '2024-10-26', '2024-10-29', '110', 'Single', 100.00, 'Pool view', 4.10, 10, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(51, 1, '2024-10-26 02:00:00', '2024-10-27', '2024-10-30', '111', 'Double', 150.00, 'Sea view', 4.60, 11, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(52, 2, '2024-10-27 03:00:00', '2024-10-28', '2024-10-31', '112', 'Suite', 200.00, 'Mountain view', 4.90, 12, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(53, 3, '2024-10-28 04:00:00', '2024-10-29', '2024-11-01', '113', 'Single', 100.00, 'City view', 4.40, 13, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(54, 4, '2024-10-29 05:00:00', '2024-10-30', '2024-11-02', '114', 'Double', 150.00, 'Garden view', 4.80, 14, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(55, 5, '2024-10-30 06:00:00', '2024-10-31', '2024-11-03', '115', 'Suite', 200.00, 'Pool view', 5.00, 15, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(56, 6, '2024-10-31 07:00:00', '2024-11-01', '2024-11-04', '116', 'Single', 100.00, 'Sea view', 4.20, 16, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(57, 7, '2024-10-31 08:00:00', '2024-11-02', '2024-11-05', '117', 'Double', 150.00, 'Mountain view', 4.70, 17, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(58, 8, '2024-11-01 09:00:00', '2024-11-03', '2024-11-06', '118', 'Suite', 200.00, 'City view', 4.90, 1, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(59, 9, '2024-11-02 10:00:00', '2024-11-04', '2024-11-07', '119', 'Single', 100.00, 'Garden view', 4.30, 2, '2024-10-16 19:17:51', '2024-10-16 19:17:51'),
(60, 10, '2024-11-03 11:00:00', '2024-11-05', '2024-11-08', '120', 'Double', 150.00, 'Pool view', 4.60, 3, '2024-10-16 19:17:51', '2024-10-16 19:17:51');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_type` enum('company','branch','agent','client') NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date` datetime NOT NULL,
  `description` text DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `reference_type` enum('Invoice','Payment') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `entity_id`, `entity_type`, `transaction_type`, `amount`, `date`, `description`, `invoice_id`, `reference_type`, `created_at`, `updated_at`) VALUES
(33, 15, 'company', 'credit', 300.00, '2024-11-22 07:53:45', 'Invoice:INV-2024-00463 Generated', 40, 'Invoice', '2024-11-21 23:53:45', '2024-11-21 23:53:45'),
(34, 15, 'company', 'credit', 600.00, '2024-11-22 07:53:45', 'Invoice:INV-2024-00463 Generated', 40, 'Invoice', '2024-11-21 23:53:45', '2024-11-21 23:53:45'),
(35, 15, 'company', 'debit', 300.00, '2024-11-22 07:59:09', 'pay to Invoice:INV-2024-00463', 40, 'Invoice', '2024-11-21 23:59:09', '2024-11-21 23:59:09'),
(36, 15, 'company', 'debit', 600.00, '2024-11-22 07:59:09', 'pay to Invoice:INV-2024-00463', 40, 'Invoice', '2024-11-21 23:59:09', '2024-11-21 23:59:09'),
(37, 15, 'company', 'credit', 750.00, '2024-11-22 08:37:10', 'Invoice:INV-2024-00466 Generated', 41, 'Invoice', '2024-11-22 00:37:10', '2024-11-22 00:37:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `first_login` tinyint(1) NOT NULL DEFAULT 1,
  `fa_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `two_factor_code` varchar(255) DEFAULT NULL,
  `two_factor_expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role_id`, `remember_token`, `created_at`, `updated_at`, `first_login`, `fa_type_id`, `two_factor_code`, `two_factor_expires_at`) VALUES
(1, 'Ahmad Al-Sabah', 'ahmad@kuwaittravelexperts.com', '2024-10-18 04:17:08', '$2y$12$iydgzysVAheUJ3guwZYJtecC6/9je43WL/q.HfgkHS5iFPeDyNFGi', 2, NULL, NULL, '2024-11-21 23:50:40', 0, 1, NULL, NULL),
(2, 'Fatima Al-Hassan', 'fatima@desertadventures.com', '2024-10-18 04:17:08', '$2y$12$wKvBGxeXfWCJ/ZSp4TMTReP/alEvh5TPsXpT/FljF/IUasmxqNWYK', 2, NULL, NULL, NULL, 1, 1, NULL, NULL),
(3, 'Mohammed Al-Fahad', 'mohammed@luxuryvoyages.com', '2024-10-18 04:17:08', '$2y$12$wKvBGxeXfWCJ/ZSp4TMTReP/alEvh5TPsXpT/FljF/IUasmxqNWYK', 2, NULL, NULL, NULL, 1, 1, NULL, NULL),
(4, 'Sara Al-Otaibi', 'sara@kuwaitairtours.com', '2024-10-18 04:17:08', '$2y$12$wKvBGxeXfWCJ/ZSp4TMTReP/alEvh5TPsXpT/FljF/IUasmxqNWYK', 2, NULL, NULL, NULL, 1, 1, NULL, NULL),
(5, 'Nasser Al-Rashed', 'nasser@explorekuwait.com', '2024-10-18 04:17:08', '$2y$12$wKvBGxeXfWCJ/ZSp4TMTReP/alEvh5TPsXpT/FljF/IUasmxqNWYK', 2, NULL, NULL, NULL, 1, 1, NULL, NULL),
(12, 'amir', 'amir@alphia.net', '2024-09-07 00:09:52', '$2y$12$48NrdZa8y2z3cHJuMNwx8.nrHZHxL6dXuk0dcVpBTd42u8Ob7hOwa', 3, NULL, '2024-08-29 01:05:52', '2024-09-07 00:09:52', 0, 1, 'eyJpdiI6ImNHam5hNDR3RkFoUkU3emFLcUovTEE9PSIsInZhbHVlIjoidkh5TFZBZFBSU1VPR0FKc1YyT3dYZ3A1L3dZYWVvVUo5ODBCa2ZlT3dWTT0iLCJtYWMiOiJiOGY5YzliNTE1YWFlNWQyNDM2NDUyZTg3N2ZjMzI1MmEwNzNiODk4NjQ5ZTRiZDljYzM3YzI2ZDJhMzE0YmRkIiwidGFnIjoiIn0=', NULL),
(14, 'amir2', 'amirabrashid2112@gmail.com', NULL, '$2y$12$7t0mtNraBJ34yT/4fMBJp.S2dlEHZPibCumddysd3POOxfJvTkRZ2', 3, NULL, '2024-09-07 02:00:13', '2024-09-07 02:01:19', 0, 1, 'eyJpdiI6IjRVa2ZlcHNFYldsVTNhenpibCsxVVE9PSIsInZhbHVlIjoiQ1pFMDNHTnp0S29LTGhTQnlrQkxVUjBjUiszc2JZT25qVHZoQ2NpMkVyUT0iLCJtYWMiOiJmZTc3MDJhOWQzYTM4ZTBhMDg0OThhODI1ZmY5MDFiZTYyMGNhNmZiYzNlNGMxODQ1NTQ5YWUyYmY2N2U1M2M0IiwidGFnIjoiIn0=', NULL),
(16, 'MOHD RIZAM BAKAR', 'markrizam@gmail.com', '2024-09-11 00:12:01', '$2y$12$ELhvyp5G49eXk7LYQHF0geSYQa8L4K4CtNEMESl7xlaIuPsoQ0Hg6', 3, NULL, '2024-09-11 00:04:26', '2024-09-11 00:13:13', 0, 1, 'eyJpdiI6IngvN3FTREhNd1VjazlBS09KUkNTV0E9PSIsInZhbHVlIjoiV0x4T2g3b3JvTE5uaXFxa0J0ZnI0aHVxUmxtWjgyNUxkT25Ec0JYTGk2bz0iLCJtYWMiOiI1NjI4NDgxYjZlZGNhMDA1YWZhODA4YzAxYjcwNDg1MWQ0MjhiNzc2YWMxNTA2NjlmMTRlODQ3MjI2YjRhZmQxIiwidGFnIjoiIn0=', NULL),
(32, 'Mamat', 'mamat@gmail.com', NULL, '$2y$12$NpUBKuZyArzwHgOn46eSLOQb6YVQDXAw0pPgmw2Do5zomn16Wm9/6', 3, NULL, '2024-09-11 23:45:42', '2024-09-11 23:45:42', 1, 1, NULL, NULL),
(33, 'Zura', 'zura@gmail.com', NULL, '$2y$12$8tfmmJUu5ApJUZ01IGQGC..Qsh1.Fr1XcsOPO0DSzza6bTMxwQ/HW', 3, NULL, '2024-09-11 23:45:43', '2024-09-11 23:45:43', 1, 1, NULL, NULL),
(34, 'MOHD RIZAM BAKAR', 'rizam@alphia.net', NULL, '$2y$12$aeMILCHGMBfYrNBBKpPm4uMmfh20.RJLNrXeFh5ozhdv61ZQeUlhS', 1, NULL, '2024-10-01 18:08:53', '2024-10-01 18:08:53', 1, 1, NULL, NULL),
(35, 'PARK TRAVEL', 'parktravel@gmail.com', NULL, '$2y$12$1hqsvva4f.2CYmbmIwdEZOzJPrrY35yBmvs3QmnJOEHvGstIgJtHa', 3, NULL, '2024-10-01 19:12:19', '2024-10-01 19:12:19', 1, 1, NULL, NULL),
(36, 'SUMUA TRAVEL', 'sumuatravel@gmail.com', NULL, '$2y$12$rRNTmN7uMrjpXLyzFFCz3en3x.CdaTItI2ZplVAW3w/NT1RCiSkZK', 3, NULL, '2024-10-01 19:47:18', '2024-10-01 19:47:18', 1, 1, NULL, NULL),
(37, 'PAAN TRAVEL', 'paantravel@gmail.com', NULL, '$2y$12$mG/Ie5jEh0e5GUUu356TzOiypqC/D6RAbdlhqekNGcrqAgCAHXBKW', 3, NULL, '2024-10-01 19:55:11', '2024-10-01 19:55:11', 1, 1, NULL, NULL),
(38, 'TARIQ TRAVEL', 'tareqtravel@gmail.com', NULL, '$2y$12$P9Yjztiu/VileOlQUe6rbuKqicyXEz.h28./TAyTZdJav5yv7tWLi', 2, NULL, '2024-10-01 20:00:07', '2024-10-01 20:00:07', 1, 1, NULL, NULL),
(39, 'PAU TRAVEL', 'admin@pautravel.com', NULL, '$2y$12$tSpY6ncrLsNWWK1dpU3hRu1N24.shZKVPjuq4SwduOAZrsVVAfyGO', 2, NULL, '2024-10-01 20:58:49', '2024-10-01 20:58:49', 1, 1, NULL, NULL),
(40, 'MADDIN TRAVEL', 'admin@maddintravel.com', NULL, '$2y$12$wKvBGxeXfWCJ/ZSp4TMTReP/alEvh5TPsXpT/FljF/IUasmxqNWYK', 2, NULL, '2024-10-01 21:04:48', '2024-10-01 21:04:48', 1, 1, NULL, NULL),
(41, 'Samira', 'samira@maddintravel.com', NULL, '$2y$12$t1Oy6VxYodnDa9b0sWigC.vosMyIjII7HRvlCASnUHpgFGc0w2Pay', 3, NULL, '2024-10-02 00:33:38', '2024-10-02 00:33:38', 1, 1, NULL, NULL),
(42, 'Andika', 'andika@maddintravel.com', NULL, '$2y$12$QPGJuU.t4u7WCckWsBUEj.YSupxw5GHzJVlJDj.7EI9JgYNMFWZAO', 3, NULL, '2024-10-02 00:33:38', '2024-10-02 00:33:38', 1, 1, NULL, NULL),
(43, 'WeiLing', 'weiling@tariqtravel.com', NULL, '$2y$12$n3nnHG5Rv.SuBAp.zetR0uMOC7pQzOVyA5PA/PrboR4TxO3R3dPW.', 3, NULL, '2024-10-02 00:36:31', '2024-10-02 00:36:31', 1, 1, NULL, NULL),
(44, 'FATTAH AMIN', 'fattah@maddintravel.com', NULL, '$2y$12$kI9WVivfETGrW.xEQXSRp.9cXuL/wMhv/Xs4wfmtdq1vO36lOfL.G', 3, NULL, '2024-10-08 21:19:37', '2024-10-08 21:19:37', 1, 1, NULL, NULL),
(46, 'Kerry Jamal', 'kerry@maddintravel.com', NULL, '$2y$12$pUJxgTTx4oL0uBbrvfFtzO2GoqYA5Qg6lY8f40nbwcFkpHrqFbxJu', 3, NULL, '2024-10-08 21:21:37', '2024-10-08 21:21:37', 1, 1, NULL, NULL),
(47, 'Renny Jamal', 'renny@maddintravel.com', NULL, '$2y$12$G3Wklj.JQZ9b09cV8kpXzeNDyzBsC4YHlgBgWPknHpC5BPuw7cO2K', 3, NULL, '2024-10-08 22:26:30', '2024-10-08 22:26:30', 1, 1, NULL, NULL),
(48, 'kamalia kamal', 'kamalia@maddintravel.com', NULL, '$2y$12$hF5OAAqhzxecBq6QkD0hRuLfzxfDj/il6idF36CslgWGrjWn2Tr.a', 3, NULL, '2024-10-08 22:34:14', '2024-10-08 22:34:14', 1, 1, NULL, NULL),
(49, 'manager city travelers', 'manager@citytravelers.co', '2024-10-30 07:28:25', '$2y$12$iydgzysVAheUJ3guwZYJtecC6/9je43WL/q.HfgkHS5iFPeDyNFGi', 2, NULL, '2024-10-30 07:28:25', '2024-10-30 07:28:25', 1, 1, NULL, NULL),
(50, 'Mohammad Alhashmi', 'msh@citytravelers.co', '2024-10-30 07:28:25', '$2y$12$iydgzysVAheUJ3guwZYJtecC6/9je43WL/q.HfgkHS5iFPeDyNFGi', 3, NULL, '2024-10-30 07:34:35', '2024-10-30 07:34:35', 1, 1, NULL, NULL),
(51, 'Saeid Shoja', 'saeid@citytravelers.co', '2024-10-30 07:28:25', '$2y$12$iydgzysVAheUJ3guwZYJtecC6/9je43WL/q.HfgkHS5iFPeDyNFGi', 3, NULL, '2024-10-30 07:34:35', '2024-10-30 07:34:35', 1, 1, NULL, NULL),
(52, 'Soud Shoja', 'soud@citytravelers.co', '2024-10-30 07:28:25', '$2y$12$iydgzysVAheUJ3guwZYJtecC6/9je43WL/q.HfgkHS5iFPeDyNFGi', 3, 'isDQiYBo7meyt0Krq1YhPUCJnmG20a3sW8xdTfw0q0o89dLOEVIe95if28zR', '2024-10-30 07:34:35', '2024-11-13 19:50:36', 0, 1, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agents_company_id_foreign` (`company_id`),
  ADD KEY `agents_user_id_foreign` (`user_id`);

--
-- Indexes for table `agent_type`
--
ALTER TABLE `agent_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `airlines`
--
ALTER TABLE `airlines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `iata_designator` (`iata_designator`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `icao_designator` (`icao_designator`),
  ADD KEY `country_id` (`country_id`);

--
-- Indexes for table `airlines_old`
--
ALTER TABLE `airlines_old`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `airports`
--
ALTER TABLE `airports`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_no` (`serial_no`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `charges`
--
ALTER TABLE `charges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_agent_id_foreign` (`agent_id`);

--
-- Indexes for table `coa_categories`
--
ALTER TABLE `coa_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `companies_code_unique` (`code`),
  ADD KEY `companies_nationality_id_foreign` (`nationality_id`),
  ADD KEY `companies_user_id_foreign` (`user_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `country_currencies`
--
ALTER TABLE `country_currencies`
  ADD PRIMARY KEY (`country_id`,`currency_id`),
  ADD KEY `fk_currency` (`currency_id`);

--
-- Indexes for table `credit_facility`
--
ALTER TABLE `credit_facility`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `fa_type`
--
ALTER TABLE `fa_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `general_ledgers`
--
ALTER TABLE `general_ledgers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incomes`
--
ALTER TABLE `incomes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`);

--
-- Indexes for table `invoice_details`
--
ALTER TABLE `invoice_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoice_sequences`
--
ALTER TABLE `invoice_sequences`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items_status`
--
ALTER TABLE `items_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_unique` (`name`);

--
-- Indexes for table `sequences`
--
ALTER TABLE `sequences`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suppliers_country_id_foreign` (`country_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasks_client_id_foreign` (`client_id`),
  ADD KEY `tasks_agent_id_foreign` (`agent_id`),
  ADD KEY `tasks_supplier_id_foreign` (`supplier_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_fa_type_id_foreign` (`fa_type_id`),
  ADD KEY `users_role_id_foreign` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `agents`
--
ALTER TABLE `agents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `agent_type`
--
ALTER TABLE `agent_type`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `airlines_old`
--
ALTER TABLE `airlines_old`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `charges`
--
ALTER TABLE `charges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `coa_categories`
--
ALTER TABLE `coa_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT for table `credit_facility`
--
ALTER TABLE `credit_facility`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fa_type`
--
ALTER TABLE `fa_type`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `general_ledgers`
--
ALTER TABLE `general_ledgers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `incomes`
--
ALTER TABLE `incomes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `invoice_details`
--
ALTER TABLE `invoice_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `invoice_sequences`
--
ALTER TABLE `invoice_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `items_status`
--
ALTER TABLE `items_status`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sequences`
--
ALTER TABLE `sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `agents`
--
ALTER TABLE `agents`
  ADD CONSTRAINT `agents_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `agents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `airlines`
--
ALTER TABLE `airlines`
  ADD CONSTRAINT `airlines_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`);

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `client_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`);

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_nationality_id_foreign` FOREIGN KEY (`nationality_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `companies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `country_currencies`
--
ALTER TABLE `country_currencies`
  ADD CONSTRAINT `fk_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `fk_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`);

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_agent_id_foreign` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`),
  ADD CONSTRAINT `tasks_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `tasks_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_fa_type_id_foreign` FOREIGN KEY (`fa_type_id`) REFERENCES `fa_type` (`id`),
  ADD CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
