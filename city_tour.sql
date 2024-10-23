-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2024 at 08:37 AM
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
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `company_id` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `phone_number` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`id`, `user_id`, `name`, `company_id`, `type`, `created_at`, `updated_at`, `phone_number`, `email`) VALUES
(5, 32, 'Mamat', '1', 'staff', '2024-09-11 23:45:42', '2024-09-11 23:45:42', 123213131, 'mamat@gmail.com'),
(6, 33, 'Zura', '1', 'staff', '2024-09-11 23:45:43', '2024-09-11 23:45:43', 12313131, 'zura@gmail.com'),
(7, NULL, 'A', '1', 'staff', '2024-09-12 01:23:22', '2024-09-12 01:23:22', 133256126, 'amalina@gmail.com'),
(8, 16, 'MOHD RIZAM BAKAR', '8', 'commission', '2024-09-20 20:01:30', '2024-09-20 20:01:30', 133256126, 'markrizam@gmail.com'),
(10, 41, 'Samira', '8', 'staff', '2024-10-02 00:33:38', '2024-10-02 00:33:38', 123213131, 'samira@maddintravel.com'),
(11, 42, 'Andika', '8', 'staff', '2024-10-02 00:33:38', '2024-10-02 00:33:38', 12313131, 'andika@maddintravel.com'),
(12, 43, 'WeiLing', '6', 'staff', '2024-10-02 00:36:31', '2024-10-02 00:36:31', 1233133, 'weiling@tariqtravel.com'),
(13, 46, 'Kerry Jamal', '8', 'staff', '2024-10-08 21:21:37', '2024-10-08 21:21:37', 123213, 'kerry@maddintravel.com'),
(14, 47, 'Renny Jamal', '8', 'staff', '2024-10-08 22:26:30', '2024-10-08 22:26:30', 123312233, 'renny@maddintravel.com'),
(15, 48, 'kamalia kamal', '8', 'staff', '2024-10-08 22:34:14', '2024-10-08 22:34:14', 1982122, 'kamalia@maddintravel.com');

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
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `agent_id` int(11) DEFAULT NULL,
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
(8, 'Anisah', 5, NULL, '123213', 'active', '2024-09-13 01:00:02', '2024-09-13 01:00:02', 'anisah@gmail.com', NULL, NULL),
(9, 'Amalina', 6, NULL, '123123123', 'active', '2024-09-13 01:00:02', '2024-09-13 01:00:02', 'amalina@gmail.com', NULL, NULL),
(10, 'Kamal', 7, NULL, '123213213', 'active', '2024-09-13 01:00:02', '2024-09-13 01:00:02', 'kamal@gmail.com', NULL, NULL),
(17, 'John', 8, NULL, '112345', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'john@example.com', NULL, NULL),
(18, 'Emily', 8, NULL, '998877665', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'emily@gmail.com', NULL, NULL),
(19, 'Dave', 8, NULL, '887766554', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'dave@example.com', NULL, NULL),
(20, 'Mike', 8, NULL, '776655443', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'mike@example.com', NULL, NULL),
(21, 'Sarah', 12, NULL, '665544332', 'active', '2024-09-24 18:15:22', '2024-10-02 19:41:34', 'sarah@example.com', NULL, NULL),
(22, 'Jack', 8, NULL, '554433221', 'active', '2024-09-24 18:15:22', '2024-09-24 18:15:22', 'jack@example.com', NULL, NULL),
(23, 'Khairul Awani', 12, NULL, '123213131', 'active', '2024-10-02 18:12:55', '2024-10-02 18:12:55', 'khairul@gmail.com', 'Jalan Kangkung', 'AP21321312'),
(24, 'Azurasyafiya', 12, NULL, '12313131', 'active', '2024-10-02 18:12:55', '2024-10-02 18:12:55', 'Azurasyafiya@gmail.com', 'Jalan Madani', 'AL12312414'),
(25, 'AZAHRRA', 11, NULL, '0123213131', '1', '2024-10-02 18:37:07', '2024-10-02 18:37:07', 'azzahra@gmail.com', 'Jalan Mawar Duka, 27000', 'A9823123313');

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `nationality` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `code`, `nationality`, `name`, `created_at`, `updated_at`, `address`, `phone`, `user_id`, `status`, `email`) VALUES
(1, 'AMR200', 'Malaysia', 'Amir Travel', '2024-09-12 18:06:29', '2024-10-01 22:43:21', 'Jalan Mawar Duka, 27000', '0147201172', 12, 1, 'amir@alphia.net'),
(4, 'SUMUA', 'Malaysia', 'SUMUA TRAVEL', '2024-10-01 19:47:18', '2024-10-01 19:47:18', 'Taman Bucida Hijauan', '0321562222', 36, 1, 'sumuatravel@gmail.com'),
(5, 'PAAN', 'Malaysia', 'PAAN TRAVEL', '2024-10-01 19:55:11', '2024-10-01 19:55:11', 'Taman Bucida Hijauan Kuala Lumpur', '60321655631', 37, 1, 'paantravel@gmail.com'),
(6, 'TAR', 'Kuwait', 'TARIQ TRAVEL', '2024-10-01 20:00:07', '2024-10-01 20:00:07', 'Off road side', '0921331332', 38, 1, 'tareqtravel@gmail.com'),
(7, 'PAU', 'Malaysia', 'PAU TRAVEL', '2024-10-01 20:58:49', '2024-10-01 20:58:49', 'Jalan Bukit Bintang, Kuala Lumpur', '123213131', 39, 1, 'admin@pautravel.com'),
(8, 'MADD', 'Malaysia', 'MADDIN TRAVEL', '2024-10-01 21:04:48', '2024-10-01 21:04:48', 'Jalan Bukit Bintang, Kuala Lumpur', '923213213', 40, 1, 'admin@maddintravel.com');

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
  `paid_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `client_id`, `agent_id`, `amount`, `status`, `created_at`, `updated_at`, `currency`, `paid_date`) VALUES
(2, 'INV-2024-00001', 18, 8, 100.00, 'paid', '2024-09-25 17:19:11', '2024-10-01 01:15:21', NULL, '2024-10-01 01:15:21'),
(3, 'INV-2024-00002', 17, 8, 102.00, 'paid', '2024-09-25 17:20:02', '2024-10-01 02:02:32', NULL, '2024-10-01 02:02:32'),
(4, 'INV-2024-00003', 17, 8, NULL, 'unpaid', '2024-09-26 18:56:23', '2024-09-26 18:56:23', NULL, NULL),
(5, 'INV-2024-00004', 10, 8, 234.00, 'unpaid', '2024-09-29 18:55:13', '2024-09-29 18:55:13', 'usd', NULL),
(6, 'INV-2024-00005', 9, 8, 340.00, 'unpaid', '2024-09-29 19:06:32', '2024-09-29 19:06:32', 'usd', NULL),
(7, 'INV-2024-00006', 17, 8, 173.00, 'unpaid', '2024-09-29 19:19:40', '2024-09-29 19:19:40', 'usd', NULL),
(8, 'INV-2024-00007', 18, 8, 280.00, 'unpaid', '2024-09-29 19:36:33', '2024-09-29 19:36:33', 'usd', NULL),
(9, 'INV-2024-00008', 18, 8, 336.00, 'unpaid', '2024-09-29 19:44:39', '2024-09-29 19:44:39', 'usd', NULL),
(10, 'INV-2024-00009', 19, 8, 231.00, 'unpaid', '2024-09-29 19:50:03', '2024-09-29 19:50:03', 'usd', NULL),
(11, 'INV-2024-00010', 18, 8, 333.00, 'unpaid', '2024-09-29 19:53:13', '2024-09-29 19:53:13', 'usd', NULL),
(12, 'INV-2024-00011', 10, 8, 244.00, 'unpaid', '2024-09-29 19:57:22', '2024-09-29 19:57:22', 'usd', NULL),
(13, 'INV-2024-00012', 10, 8, 250.00, 'unpaid', '2024-09-29 20:04:50', '2024-09-29 20:04:50', 'usd', NULL),
(14, 'INV-2024-00013', 21, 8, 350.00, 'paid', '2024-09-29 20:08:41', '2024-10-01 01:08:07', 'usd', '2024-10-01 01:08:07'),
(15, 'INV-2024-00014', 22, 8, 351.00, 'unpaid', '2024-09-29 20:45:53', '2024-09-29 20:45:53', 'usd', NULL),
(16, 'INV-2024-00015', 17, 8, 1320.00, 'unpaid', '2024-09-29 20:58:51', '2024-09-29 20:58:51', 'usd', NULL),
(17, 'INV-2024-00016', 17, 8, 140.00, 'unpaid', '2024-09-29 21:15:08', '2024-09-29 21:15:08', 'usd', NULL),
(18, 'INV-2024-00017', 17, 8, 140.00, 'unpaid', '2024-09-29 21:15:48', '2024-09-29 21:15:48', 'usd', NULL),
(19, 'INV-2024-00018', 10, 8, 300.00, 'unpaid', '2024-09-29 21:23:18', '2024-09-29 21:23:18', 'usd', NULL),
(20, 'INV-2024-00019', 9, 8, 370.00, 'paid', '2024-09-29 22:14:36', '2024-09-30 00:15:14', 'usd', NULL),
(21, 'INV-2024-00020', 22, 8, 123.00, 'paid', '2024-09-30 00:45:14', '2024-09-30 00:45:22', 'usd', NULL),
(22, 'INV-2024-00021', 21, 8, 130.00, 'paid', '2024-09-30 01:06:14', '2024-09-30 18:08:09', 'usd', NULL),
(23, 'INV-2024-00022', 22, 7, 50.00, 'unpaid', '2024-09-30 01:56:57', '2024-09-30 01:56:57', 'myr', NULL),
(24, 'INV-2024-00023', 22, 7, 45.00, 'unpaid', '2024-09-30 01:58:31', '2024-09-30 01:58:31', 'myr', NULL),
(25, 'INV-2024-00024', 21, 7, 40.00, 'unpaid', '2024-09-30 02:03:01', '2024-09-30 02:03:01', 'eur', NULL),
(26, 'INV-2024-00025', 21, 7, 30.00, 'unpaid', '2024-09-30 17:27:34', '2024-09-30 17:27:34', 'myr', NULL),
(27, 'INV-2024-00026', 21, 7, 200.00, 'paid', '2024-09-30 17:31:11', '2024-09-30 17:37:57', 'myr', NULL),
(28, 'INV-2024-00027', 21, 7, 123.00, 'unpaid', '2024-09-30 18:18:33', '2024-09-30 18:18:33', 'myr', NULL),
(29, 'INV-2024-00028', 22, 8, 120.00, 'unpaid', '2024-10-01 00:18:27', '2024-10-01 00:18:27', 'usd', NULL),
(30, 'INV-2024-00029', 21, 8, 123.00, 'unpaid', '2024-10-01 00:28:40', '2024-10-01 00:28:40', 'usd', NULL),
(31, 'INV-2024-00030', 21, 8, 123.00, 'paid', '2024-10-01 00:32:51', '2024-10-01 00:32:54', 'usd', NULL),
(32, 'INV-2024-00031', 21, 7, 120.00, 'unpaid', '2024-10-01 21:30:35', '2024-10-01 21:30:35', 'myr', NULL),
(33, 'INV-2024-00032', 21, 7, 120.00, 'unpaid', '2024-10-01 21:30:38', '2024-10-01 21:30:38', 'myr', NULL),
(34, 'INV-2024-00033', 21, 7, 120.00, 'unpaid', '2024-10-01 21:30:38', '2024-10-01 21:30:38', 'myr', NULL),
(35, 'INV-2024-00034', 21, 7, 120.00, 'unpaid', '2024-10-01 21:30:38', '2024-10-01 21:30:38', 'myr', NULL),
(36, 'INV-2024-00035', 21, 7, 120.00, 'unpaid', '2024-10-01 21:30:39', '2024-10-01 21:30:39', 'myr', NULL),
(37, 'INV-2024-00036', 21, 7, 210.00, 'unpaid', '2024-10-01 21:31:05', '2024-10-01 21:31:05', 'myr', NULL),
(38, 'INV-2024-00037', 21, 8, 320.00, 'paid', '2024-10-01 21:32:59', '2024-10-01 21:33:06', 'usd', '2024-10-01 21:33:06'),
(39, 'INV-2024-00038', 21, 8, 124.00, 'paid', '2024-10-02 01:58:30', '2024-10-02 02:00:26', 'myr', '2024-10-02 02:00:26'),
(40, 'INV-2024-00039', 21, 7, 160.00, 'unpaid', '2024-10-02 02:00:02', '2024-10-02 02:00:02', 'myr', NULL),
(41, 'INV-2024-00040', 17, 8, 250.00, 'paid', '2024-10-06 18:20:53', '2024-10-06 18:23:35', 'usd', '2024-10-06 18:23:35'),
(42, 'INV-2024-00041', 22, 8, 123.00, 'paid', '2024-10-08 01:56:35', '2024-10-08 01:56:38', 'usd', '2024-10-08 01:56:38');

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
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_details`
--

INSERT INTO `invoice_details` (`id`, `invoice_id`, `invoice_number`, `task_id`, `task_description`, `task_remark`, `task_price`, `created_at`, `updated_at`, `quantity`) VALUES
(1, 14, 'INV-2024-00013', 39, 'Book Concert Tickets', 'concert', 150.00, '2024-09-29 20:08:41', '2024-09-29 20:08:41', 1),
(2, 14, 'INV-2024-00013', 35, 'Book Museum Tickets', 'museum', 200.00, '2024-09-29 20:08:41', '2024-09-29 20:08:41', 1),
(3, 15, 'INV-2024-00014', 40, 'Book Travel Van', 'sfsfsdf', 211.00, '2024-09-29 20:45:53', '2024-09-29 20:45:53', 1),
(4, 15, 'INV-2024-00014', 31, 'Book Travel Van', 'dsadad', 140.00, '2024-09-29 20:45:53', '2024-09-29 20:45:53', 1),
(5, 16, 'INV-2024-00015', 39, 'Book Concert Tickets', 'Concert', 120.00, '2024-09-29 20:58:51', '2024-09-29 20:58:51', 1),
(6, 16, 'INV-2024-00015', 40, 'Book Travel Van', 'van', 1200.00, '2024-09-29 20:58:51', '2024-09-29 20:58:51', 1),
(7, 17, 'INV-2024-00016', 34, 'Book Hotel Wangsa', 'wangsa', 140.00, '2024-09-29 21:15:08', '2024-09-29 21:15:08', 1),
(8, 18, 'INV-2024-00017', 34, 'Book Hotel Wangsa', 'wangsa', 140.00, '2024-09-29 21:15:48', '2024-09-29 21:15:48', 1),
(9, 19, 'INV-2024-00018', 35, 'Book Museum Tickets', 'museum', 200.00, '2024-09-29 21:23:18', '2024-09-29 21:23:18', 1),
(10, 19, 'INV-2024-00018', 37, 'Book Safari Tickets', 'safari', 100.00, '2024-09-29 21:23:18', '2024-09-29 21:23:18', 1),
(11, 20, 'INV-2024-00019', 35, 'Book Museum Tickets', 'museum', 250.00, '2024-09-29 22:14:36', '2024-09-29 22:14:36', 1),
(12, 20, 'INV-2024-00019', 40, 'Book Travel Van', 'Van', 120.00, '2024-09-29 22:14:36', '2024-09-29 22:14:36', 1),
(13, 21, 'INV-2024-00020', 40, 'Book Travel Van', 'Travel', 123.00, '2024-09-30 00:45:14', '2024-09-30 00:45:14', 1),
(14, 22, 'INV-2024-00021', 39, 'Book Concert Tickets', 'concert', 130.00, '2024-09-30 01:06:14', '2024-09-30 01:06:14', 1),
(15, 23, 'INV-2024-00022', 40, 'Book Travel Van', 'travel', 50.00, '2024-09-30 01:56:57', '2024-09-30 01:56:57', 1),
(16, 24, 'INV-2024-00023', 40, 'Book Travel Van', 'travel', 45.00, '2024-09-30 01:58:31', '2024-09-30 01:58:31', 1),
(17, 25, 'INV-2024-00024', 39, 'Book Concert Tickets', 'tticket', 40.00, '2024-09-30 02:03:01', '2024-09-30 02:03:01', 1),
(18, 26, 'INV-2024-00025', 39, 'Book Concert Tickets', 'concert', 30.00, '2024-09-30 17:27:34', '2024-09-30 17:27:34', 1),
(19, 27, 'INV-2024-00026', 39, 'Book Concert Tickets', 'concert', 200.00, '2024-09-30 17:31:11', '2024-09-30 17:31:11', 1),
(20, 28, 'INV-2024-00027', 39, 'Book Concert Tickets', 'concert', 123.00, '2024-09-30 18:18:33', '2024-09-30 18:18:33', 1),
(21, 29, 'INV-2024-00028', 40, 'Book Travel Van', 'van', 120.00, '2024-10-01 00:18:27', '2024-10-01 00:18:27', 1),
(22, 30, 'INV-2024-00029', 39, 'Book Concert Tickets', 'Concert', 123.00, '2024-10-01 00:28:40', '2024-10-01 00:28:40', 1),
(23, 31, 'INV-2024-00030', 39, 'Book Concert Tickets', 'concert', 123.00, '2024-10-01 00:32:51', '2024-10-01 00:32:51', 1),
(24, 32, 'INV-2024-00031', 39, 'Book Concert Tickets', 'concert', 120.00, '2024-10-01 21:30:35', '2024-10-01 21:30:35', 1),
(25, 33, 'INV-2024-00032', 39, 'Book Concert Tickets', 'concert', 120.00, '2024-10-01 21:30:38', '2024-10-01 21:30:38', 1),
(26, 34, 'INV-2024-00033', 39, 'Book Concert Tickets', 'concert', 120.00, '2024-10-01 21:30:38', '2024-10-01 21:30:38', 1),
(27, 35, 'INV-2024-00034', 39, 'Book Concert Tickets', 'concert', 120.00, '2024-10-01 21:30:38', '2024-10-01 21:30:38', 1),
(28, 36, 'INV-2024-00035', 39, 'Book Concert Tickets', 'concert', 120.00, '2024-10-01 21:30:39', '2024-10-01 21:30:39', 1),
(29, 37, 'INV-2024-00036', 39, 'Book Concert Tickets', 'concert', 210.00, '2024-10-01 21:31:05', '2024-10-01 21:31:05', 1),
(30, 38, 'INV-2024-00037', 39, 'Book Concert Tickets', 'concert', 320.00, '2024-10-01 21:32:59', '2024-10-01 21:32:59', 1),
(31, 39, 'INV-2024-00038', 39, 'Book Concert Tickets', 'concert', 124.00, '2024-10-02 01:58:30', '2024-10-02 01:58:30', 1),
(32, 40, 'INV-2024-00039', 39, 'Book Concert Tickets', 'concert', 160.00, '2024-10-02 02:00:02', '2024-10-02 02:00:02', 1),
(33, 41, 'INV-2024-00040', 35, 'Book Museum Tickets', 'book museum', 250.00, '2024-10-06 18:20:53', '2024-10-06 18:20:53', 1),
(34, 42, 'INV-2024-00041', 40, 'Book Travel Van', 'mm', 123.00, '2024-10-08 01:56:35', '2024-10-08 01:56:35', 1);

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
(1, 42, '2024-09-26 01:19:02', '2024-10-08 01:56:35');

-- --------------------------------------------------------

--
-- Stand-in structure for view `invoice_transaction_view`
-- (See below for the actual view)
--
CREATE TABLE `invoice_transaction_view` (
`invoice_number` varchar(255)
,`client_id` bigint(20) unsigned
,`agent_id` bigint(20) unsigned
,`amount` decimal(10,2)
,`invoice_status` varchar(255)
,`invoice_created_at` timestamp
,`currency` varchar(50)
,`payment_type` varchar(255)
,`transaction_amount` double
,`transaction_status_id` int(11)
,`transaction_details` text
,`transaction_created_at` timestamp
,`transaction_updated_at` timestamp
,`task_id` int(11)
,`task_description` varchar(255)
,`task_remark` varchar(255)
,`task_price` decimal(10,2)
,`quantity` int(11)
);

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
(16, '2024_09_18_023528_create_roles_table', 4);

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
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('ycfeZNNDw8LliGWNORAzMTYkcOf19RBCKWkPVUok', 34, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoicVZSSk4yT0JhaFNNN3V5Qm1wSk9jZ0Z4aTdHWjZ0SWx0UjVXMWtKNSI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9jb21wYW5pZXMvOCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjM0O30=', 1728455654);

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_id` varchar(100) DEFAULT NULL,
  `reference` varchar(255) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `agent_id` varchar(100) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `agent_email` varchar(100) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_name` varchar(200) DEFAULT NULL,
  `client_phone` varchar(100) DEFAULT NULL,
  `ext_id` varchar(100) DEFAULT NULL,
  `task_type` varchar(100) DEFAULT NULL,
  `contract_id` varchar(100) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `item_id`, `reference`, `description`, `created_at`, `updated_at`, `agent_id`, `status`, `agent_email`, `client_email`, `client_name`, `client_phone`, `ext_id`, `task_type`, `contract_id`, `client_id`) VALUES
(30, '30', NULL, 'Book Hotel Wangsa', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'pending', 'markrizam@gmail.com', 'anisah@gmail.com', 'Anisah', '123213', '2131', 'accommodation', '1221', 8),
(31, '31', NULL, 'Book Travel Van ', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'pending', 'markrizam@gmail.com', 'amalina@gmail.com', 'Amalina', '123123123', '213', 'transportation', '1231', 9),
(32, '31', NULL, 'Book Playground Ticket', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'completed', 'markrizam@gmail.com', 'kamal@gmail.com', 'Kamal', '123213213', '2313', 'entertainment', '1231', 10),
(33, '30', NULL, 'Book Hotel Wangsa', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'pending', 'markrizam@gmail.com', 'anisah@gmail.com', 'Anisah', '123213', '=C4+1', 'accommodation', '1221', 8),
(34, '30', NULL, 'Book Hotel Wangsa', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'pending', 'markrizam@gmail.com', 'anisah@gmail.com', 'Anisah', '123213', '=C5+1', 'accommodation', '1221', 8),
(35, '32', NULL, 'Book Museum Tickets', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'pending', 'markrizam@gmail.com', 'john@example.com', 'John', '112345', '=C6+1', 'entertainment', '1254', 17),
(36, '33', NULL, 'Book River Cruise', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'completed', 'markrizam@gmail.com', 'emily@gmail.com', 'Emily', '998877665', '=C7+1', 'transportation', '2341', 18),
(37, '34', NULL, 'Book Safari Tickets', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'pending', 'markrizam@gmail.com', 'dave@example.com', 'Dave', '887766554', '=C8+1', 'entertainment', '1299', 19),
(38, '35', NULL, 'Book Travel Van', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'completed', 'markrizam@gmail.com', 'mike@example.com', 'Mike', '776655443', '=C9+1', 'transportation', '1234', 20),
(39, '36', NULL, 'Book Concert Tickets', '2024-09-24 18:15:22', '2024-10-02 19:41:34', '12', 'pending', 'weiling@tariqtravel.com', 'sarah@example.com', 'Sarah', '665544332', '=C10+1', 'entertainment', '1567', 21),
(40, '37', NULL, 'Book Travel Van', '2024-09-24 18:15:22', '2024-09-24 18:15:22', '8', 'pending', 'markrizam@gmail.com', 'jack@example.com', 'Jack', '554433221', '=C11+1', 'transportation', '1789', 22);

-- --------------------------------------------------------

--
-- Stand-in structure for view `task_item_agent_view`
-- (See below for the actual view)
--
CREATE TABLE `task_item_agent_view` (
`task_id` bigint(20) unsigned
,`task_item_id` varchar(100)
,`task_reference` varchar(255)
,`task_description` varchar(255)
,`task_created_at` timestamp
,`task_updated_at` timestamp
,`task_agent_id` varchar(100)
,`task_client_id` int(11)
,`task_status` varchar(100)
,`task_agent_email` varchar(100)
,`task_client_email` varchar(100)
,`task_client_name` varchar(200)
,`task_client_phone` varchar(100)
,`task_ext_id` varchar(100)
,`task_type` varchar(100)
,`item_id` bigint(20) unsigned
,`item_reference` varchar(255)
,`item_description` varchar(255)
,`item_type` varchar(255)
,`item_client_id` int(11)
,`item_agent_id` int(11)
,`item_status` varchar(255)
,`item_created_at` timestamp
,`item_updated_at` timestamp
,`contract_id` varchar(100)
,`contract_code` varchar(255)
,`item_time_signed` varchar(100)
,`item_client_email` varchar(100)
,`item_agent_email` varchar(100)
,`item_total_price` decimal(10,2)
,`item_payment_date` varchar(100)
,`item_paid` tinyint(1)
,`item_payment_time` varchar(100)
,`item_payment_amount` decimal(10,2)
,`item_refunded` decimal(10,2)
,`trip_name` varchar(255)
,`trip_code` varchar(100)
,`agent_id` bigint(20) unsigned
,`agent_name` varchar(255)
,`agent_company_id` varchar(255)
,`agent_type` varchar(255)
,`agent_phone_number` int(11)
,`agent_email` varchar(100)
,`agent_created_at` timestamp
,`agent_updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `payment_type` varchar(255) DEFAULT NULL,
  `transaction_amount` double NOT NULL,
  `transaction_method_id` int(11) DEFAULT NULL,
  `transaction_status_id` int(11) DEFAULT NULL,
  `transaction_details` text DEFAULT NULL,
  `agent_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `invoice_id`, `payment_type`, `transaction_amount`, `transaction_method_id`, `transaction_status_id`, `transaction_details`, `agent_id`, `client_id`, `created_at`, `updated_at`) VALUES
(1, 21, 'online', 123, NULL, NULL, NULL, 8, 22, '2024-09-30 00:45:22', '2024-09-30 00:45:22'),
(2, 27, 'online', 200, NULL, NULL, NULL, 7, 21, '2024-09-30 17:37:57', '2024-09-30 17:37:57'),
(3, 22, 'online', 130, NULL, NULL, NULL, 8, 21, '2024-09-30 18:08:09', '2024-09-30 18:08:09'),
(4, 31, 'online', 123, NULL, NULL, NULL, 8, 21, '2024-10-01 00:32:54', '2024-10-01 00:32:54'),
(5, 14, 'online', 350, NULL, NULL, NULL, 8, 21, '2024-10-01 01:08:07', '2024-10-01 01:08:07'),
(6, 2, 'online', 100, NULL, NULL, NULL, 8, 18, '2024-10-01 01:15:21', '2024-10-01 01:15:21'),
(7, 3, 'online', 102, NULL, NULL, NULL, 8, 17, '2024-10-01 02:02:32', '2024-10-01 02:02:32'),
(8, 38, 'online', 320, NULL, NULL, NULL, 8, 21, '2024-10-01 21:33:06', '2024-10-01 21:33:06'),
(9, 39, 'online', 124, NULL, NULL, NULL, 8, 21, '2024-10-02 02:00:26', '2024-10-02 02:00:26'),
(10, 41, 'online', 250, NULL, NULL, NULL, 8, 17, '2024-10-06 18:23:35', '2024-10-06 18:23:35'),
(11, 42, 'online', 123, NULL, NULL, NULL, 8, 22, '2024-10-08 01:56:38', '2024-10-08 01:56:38');

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
  `role` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `first_login` tinyint(1) NOT NULL DEFAULT 1,
  `fa_type_id` int(11) DEFAULT NULL,
  `two_factor_code` varchar(255) DEFAULT NULL,
  `two_factor_expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `remember_token`, `created_at`, `updated_at`, `first_login`, `fa_type_id`, `two_factor_code`, `two_factor_expires_at`) VALUES
(12, 'amir', 'amir@alphia.net', '2024-09-07 00:09:52', '$2y$12$48NrdZa8y2z3cHJuMNwx8.nrHZHxL6dXuk0dcVpBTd42u8Ob7hOwa', 'agent', NULL, '2024-08-29 01:05:52', '2024-09-07 00:09:52', 0, 1, 'eyJpdiI6ImNHam5hNDR3RkFoUkU3emFLcUovTEE9PSIsInZhbHVlIjoidkh5TFZBZFBSU1VPR0FKc1YyT3dYZ3A1L3dZYWVvVUo5ODBCa2ZlT3dWTT0iLCJtYWMiOiJiOGY5YzliNTE1YWFlNWQyNDM2NDUyZTg3N2ZjMzI1MmEwNzNiODk4NjQ5ZTRiZDljYzM3YzI2ZDJhMzE0YmRkIiwidGFnIjoiIn0=', NULL),
(14, 'amir2', 'amirabrashid2112@gmail.com', NULL, '$2y$12$7t0mtNraBJ34yT/4fMBJp.S2dlEHZPibCumddysd3POOxfJvTkRZ2', 'agent', NULL, '2024-09-07 02:00:13', '2024-09-07 02:01:19', 0, 1, 'eyJpdiI6IjRVa2ZlcHNFYldsVTNhenpibCsxVVE9PSIsInZhbHVlIjoiQ1pFMDNHTnp0S29LTGhTQnlrQkxVUjBjUiszc2JZT25qVHZoQ2NpMkVyUT0iLCJtYWMiOiJmZTc3MDJhOWQzYTM4ZTBhMDg0OThhODI1ZmY5MDFiZTYyMGNhNmZiYzNlNGMxODQ1NTQ5YWUyYmY2N2U1M2M0IiwidGFnIjoiIn0=', NULL),
(16, 'MOHD RIZAM BAKAR', 'markrizam@gmail.com', '2024-09-11 00:12:01', '$2y$12$ELhvyp5G49eXk7LYQHF0geSYQa8L4K4CtNEMESl7xlaIuPsoQ0Hg6', 'agent', NULL, '2024-09-11 00:04:26', '2024-09-11 00:13:13', 0, 1, 'eyJpdiI6IngvN3FTREhNd1VjazlBS09KUkNTV0E9PSIsInZhbHVlIjoiV0x4T2g3b3JvTE5uaXFxa0J0ZnI0aHVxUmxtWjgyNUxkT25Ec0JYTGk2bz0iLCJtYWMiOiI1NjI4NDgxYjZlZGNhMDA1YWZhODA4YzAxYjcwNDg1MWQ0MjhiNzc2YWMxNTA2NjlmMTRlODQ3MjI2YjRhZmQxIiwidGFnIjoiIn0=', NULL),
(32, 'Mamat', 'mamat@gmail.com', NULL, '$2y$12$NpUBKuZyArzwHgOn46eSLOQb6YVQDXAw0pPgmw2Do5zomn16Wm9/6', 'agent', NULL, '2024-09-11 23:45:42', '2024-09-11 23:45:42', 1, NULL, NULL, NULL),
(33, 'Zura', 'zura@gmail.com', NULL, '$2y$12$8tfmmJUu5ApJUZ01IGQGC..Qsh1.Fr1XcsOPO0DSzza6bTMxwQ/HW', 'agent', NULL, '2024-09-11 23:45:43', '2024-09-11 23:45:43', 1, NULL, NULL, NULL),
(34, 'MOHD RIZAM BAKAR', 'rizam@alphia.net', NULL, '$2y$12$aeMILCHGMBfYrNBBKpPm4uMmfh20.RJLNrXeFh5ozhdv61ZQeUlhS', 'admin', NULL, '2024-10-01 18:08:53', '2024-10-01 18:08:53', 1, NULL, NULL, NULL),
(35, 'PARK TRAVEL', 'parktravel@gmail.com', NULL, '$2y$12$1hqsvva4f.2CYmbmIwdEZOzJPrrY35yBmvs3QmnJOEHvGstIgJtHa', 'agent', NULL, '2024-10-01 19:12:19', '2024-10-01 19:12:19', 1, NULL, NULL, NULL),
(36, 'SUMUA TRAVEL', 'sumuatravel@gmail.com', NULL, '$2y$12$rRNTmN7uMrjpXLyzFFCz3en3x.CdaTItI2ZplVAW3w/NT1RCiSkZK', 'agent', NULL, '2024-10-01 19:47:18', '2024-10-01 19:47:18', 1, NULL, NULL, NULL),
(37, 'PAAN TRAVEL', 'paantravel@gmail.com', NULL, '$2y$12$mG/Ie5jEh0e5GUUu356TzOiypqC/D6RAbdlhqekNGcrqAgCAHXBKW', 'agent', NULL, '2024-10-01 19:55:11', '2024-10-01 19:55:11', 1, NULL, NULL, NULL),
(38, 'TARIQ TRAVEL', 'tareqtravel@gmail.com', NULL, '$2y$12$P9Yjztiu/VileOlQUe6rbuKqicyXEz.h28./TAyTZdJav5yv7tWLi', 'company', NULL, '2024-10-01 20:00:07', '2024-10-01 20:00:07', 1, NULL, NULL, NULL),
(39, 'PAU TRAVEL', 'admin@pautravel.com', NULL, '$2y$12$tSpY6ncrLsNWWK1dpU3hRu1N24.shZKVPjuq4SwduOAZrsVVAfyGO', 'company', NULL, '2024-10-01 20:58:49', '2024-10-01 20:58:49', 1, NULL, NULL, NULL),
(40, 'MADDIN TRAVEL', 'admin@maddintravel.com', NULL, '$2y$12$wKvBGxeXfWCJ/ZSp4TMTReP/alEvh5TPsXpT/FljF/IUasmxqNWYK', 'company', NULL, '2024-10-01 21:04:48', '2024-10-01 21:04:48', 1, NULL, NULL, NULL),
(41, 'Samira', 'samira@maddintravel.com', NULL, '$2y$12$t1Oy6VxYodnDa9b0sWigC.vosMyIjII7HRvlCASnUHpgFGc0w2Pay', 'agent', NULL, '2024-10-02 00:33:38', '2024-10-02 00:33:38', 1, NULL, NULL, NULL),
(42, 'Andika', 'andika@maddintravel.com', NULL, '$2y$12$QPGJuU.t4u7WCckWsBUEj.YSupxw5GHzJVlJDj.7EI9JgYNMFWZAO', 'agent', NULL, '2024-10-02 00:33:38', '2024-10-02 00:33:38', 1, NULL, NULL, NULL),
(43, 'WeiLing', 'weiling@tariqtravel.com', NULL, '$2y$12$n3nnHG5Rv.SuBAp.zetR0uMOC7pQzOVyA5PA/PrboR4TxO3R3dPW.', 'agent', NULL, '2024-10-02 00:36:31', '2024-10-02 00:36:31', 1, NULL, NULL, NULL),
(44, 'FATTAH AMIN', 'fattah@maddintravel.com', NULL, '$2y$12$kI9WVivfETGrW.xEQXSRp.9cXuL/wMhv/Xs4wfmtdq1vO36lOfL.G', 'agent', NULL, '2024-10-08 21:19:37', '2024-10-08 21:19:37', 1, NULL, NULL, NULL),
(46, 'Kerry Jamal', 'kerry@maddintravel.com', NULL, '$2y$12$pUJxgTTx4oL0uBbrvfFtzO2GoqYA5Qg6lY8f40nbwcFkpHrqFbxJu', 'agent', NULL, '2024-10-08 21:21:37', '2024-10-08 21:21:37', 1, NULL, NULL, NULL),
(47, 'Renny Jamal', 'renny@maddintravel.com', NULL, '$2y$12$G3Wklj.JQZ9b09cV8kpXzeNDyzBsC4YHlgBgWPknHpC5BPuw7cO2K', 'agent', NULL, '2024-10-08 22:26:30', '2024-10-08 22:26:30', 1, NULL, NULL, NULL),
(48, 'kamalia kamal', 'kamalia@maddintravel.com', NULL, '$2y$12$hF5OAAqhzxecBq6QkD0hRuLfzxfDj/il6idF36CslgWGrjWn2Tr.a', 'agent', NULL, '2024-10-08 22:34:14', '2024-10-08 22:34:14', 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure for view `invoice_transaction_view`
--
DROP TABLE IF EXISTS `invoice_transaction_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `invoice_transaction_view`  AS SELECT `i`.`invoice_number` AS `invoice_number`, `i`.`client_id` AS `client_id`, `i`.`agent_id` AS `agent_id`, `i`.`amount` AS `amount`, `i`.`status` AS `invoice_status`, `i`.`created_at` AS `invoice_created_at`, `i`.`currency` AS `currency`, `t`.`payment_type` AS `payment_type`, `t`.`transaction_amount` AS `transaction_amount`, `t`.`transaction_status_id` AS `transaction_status_id`, `t`.`transaction_details` AS `transaction_details`, `t`.`created_at` AS `transaction_created_at`, `t`.`updated_at` AS `transaction_updated_at`, `id`.`task_id` AS `task_id`, `id`.`task_description` AS `task_description`, `id`.`task_remark` AS `task_remark`, `id`.`task_price` AS `task_price`, `id`.`quantity` AS `quantity` FROM ((`invoices` `i` left join `transactions` `t` on(`i`.`id` = `t`.`invoice_id`)) left join `invoice_details` `id` on(`i`.`id` = `id`.`invoice_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `task_item_agent_view`
--
DROP TABLE IF EXISTS `task_item_agent_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `task_item_agent_view`  AS SELECT `tasks`.`id` AS `task_id`, `tasks`.`item_id` AS `task_item_id`, `tasks`.`reference` AS `task_reference`, `tasks`.`description` AS `task_description`, `tasks`.`created_at` AS `task_created_at`, `tasks`.`updated_at` AS `task_updated_at`, `tasks`.`agent_id` AS `task_agent_id`, `tasks`.`client_id` AS `task_client_id`, `tasks`.`status` AS `task_status`, `tasks`.`agent_email` AS `task_agent_email`, `tasks`.`client_email` AS `task_client_email`, `tasks`.`client_name` AS `task_client_name`, `tasks`.`client_phone` AS `task_client_phone`, `tasks`.`ext_id` AS `task_ext_id`, `tasks`.`task_type` AS `task_type`, `items`.`id` AS `item_id`, `items`.`item_ref` AS `item_reference`, `items`.`description` AS `item_description`, `items`.`item_type` AS `item_type`, `items`.`client_id` AS `item_client_id`, `items`.`agent_id` AS `item_agent_id`, `items`.`item_status` AS `item_status`, `items`.`created_at` AS `item_created_at`, `items`.`updated_at` AS `item_updated_at`, `items`.`contract_id` AS `contract_id`, `items`.`contract_code` AS `contract_code`, `items`.`time_signed` AS `item_time_signed`, `items`.`client_email` AS `item_client_email`, `items`.`agent_email` AS `item_agent_email`, `items`.`total_price` AS `item_total_price`, `items`.`payment_date` AS `item_payment_date`, `items`.`paid` AS `item_paid`, `items`.`payment_time` AS `item_payment_time`, `items`.`payment_amount` AS `item_payment_amount`, `items`.`refunded` AS `item_refunded`, `items`.`trip_name` AS `trip_name`, `items`.`trip_code` AS `trip_code`, `agents`.`id` AS `agent_id`, `agents`.`name` AS `agent_name`, `agents`.`company_id` AS `agent_company_id`, `agents`.`type` AS `agent_type`, `agents`.`phone_number` AS `agent_phone_number`, `agents`.`email` AS `agent_email`, `agents`.`created_at` AS `agent_created_at`, `agents`.`updated_at` AS `agent_updated_at` FROM ((`tasks` join `items` on(`tasks`.`item_id` = `items`.`id`)) join `agents` on(`tasks`.`agent_id` = `agents`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `agent_type`
--
ALTER TABLE `agent_type`
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
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `companies_code_unique` (`code`);

--
-- Indexes for table `credit_facility`
--
ALTER TABLE `credit_facility`
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
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_unique` (`name`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`);

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
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agents`
--
ALTER TABLE `agents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `agent_type`
--
ALTER TABLE `agent_type`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `credit_facility`
--
ALTER TABLE `credit_facility`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fa_type`
--
ALTER TABLE `fa_type`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `invoice_details`
--
ALTER TABLE `invoice_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
