-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 02, 2026 at 09:53 AM
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
-- Database: `runeha_erp`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `module`, `details`, `ip_address`, `created_at`) VALUES
(1, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-14 18:41:12'),
(2, 28, 'logout', 'auth', 'User logged out', '::1', '2026-08-14 18:41:49'),
(3, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-14 18:41:54'),
(4, 28, 'logout', 'auth', 'User logged out', '::1', '2026-08-14 18:42:00'),
(5, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-14 18:42:50'),
(6, 28, 'logout', 'auth', 'User logged out', '::1', '2026-08-14 18:43:04'),
(7, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-15 02:42:08'),
(8, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-15 17:01:50'),
(9, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-16 11:26:16'),
(10, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-16 12:22:21'),
(11, 28, 'logout', 'auth', 'User logged out', '::1', '2026-08-16 13:18:32'),
(12, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-16 13:18:41'),
(13, 28, 'Created project', 'Projects', 'Project: PRJ-2026-1.844674407371E+19', '::1', '2026-08-17 06:13:08'),
(14, 28, 'Created purchase request', 'Procurement', 'PR: PR-202608-0001', '::1', '2026-08-17 06:20:36'),
(15, 28, 'Created purchase order', 'Procurement', 'PO: PO-202608-0001', '::1', '2026-08-17 06:23:19'),
(16, 28, 'Created quotation', 'Procurement', 'QT: QT-202608-0001', '::1', '2026-08-17 06:23:46'),
(17, 28, 'Created supplier', 'Procurement', 'Supplier: angel', '::1', '2026-08-17 06:31:42'),
(18, 28, 'Created invoice', 'Accounting', 'INV: INV-202608-0001', '::1', '2026-08-17 06:32:31'),
(19, 28, 'Created expense', 'Accounting', 'EXP: EXP-202608-0001', '::1', '2026-08-17 06:33:03'),
(20, 28, 'Created payroll', 'Accounting', 'PRL: PRL-202608-0001', '::1', '2026-08-17 06:36:19'),
(21, 28, 'Recorded payment', 'Accounting', 'Payment: PAY-202608-0001', '::1', '2026-08-17 06:39:17'),
(22, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-22 15:10:12'),
(23, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-24 08:35:45'),
(24, 28, 'login', 'auth', 'User logged in', '::1', '2026-08-25 15:28:39');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `expense_number` varchar(50) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expense_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `expense_number`, `project_id`, `category`, `description`, `amount`, `expense_date`, `payment_method`, `reference_number`, `status`, `approved_by`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'EXP-202601-0001', 1, 'Materials', 'Initial cement purchase', 140000.00, '2026-01-18', NULL, NULL, 'paid', NULL, 6, '2026-08-14 18:40:03', '2026-08-14 18:40:03'),
(2, 'EXP-202608-0001', 2, 'Marketing', '', 1000000.00, '2026-08-17', 'Cash', '245355', 'pending', NULL, 28, '2026-08-17 06:33:03', '2026-08-17 06:33:03');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `client` varchar(255) NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','sent','partial','paid','overdue','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `project_id`, `client`, `invoice_date`, `due_date`, `amount`, `paid_amount`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-202601-0001', 1, 'Runeha INC.', '2026-01-20', '2026-02-20', 5000000.00, 10000.00, 'partial', NULL, 6, '2026-08-14 18:40:03', '2026-08-17 06:39:17'),
(2, 'INV-202608-0001', 2, 'santos', '2026-08-17', '2026-08-17', 1000000.00, 0.00, 'draft', '', 28, '2026-08-17 06:32:31', '2026-08-17 06:32:31');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `material_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `cost_per_unit` decimal(12,2) DEFAULT 0.00,
  `min_stock` int(11) DEFAULT 0,
  `max_stock` int(11) DEFAULT 0,
  `current_stock` int(11) DEFAULT 0,
  `supplier` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `material_code`, `name`, `description`, `category`, `unit`, `cost_per_unit`, `min_stock`, `max_stock`, `current_stock`, `supplier`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'MAT-001', 'Cement (40kg)', 'Portland cement 40kg bag', 'Construction', 'bags', 280.00, 500, 2000, 1500, 'ABC Cement Supply', 'active', 2, '2026-08-14 18:40:03', '2026-08-14 18:40:03'),
(2, 'MAT-002', 'Steel Bar #16', 'Deformed steel bar 16mm x 6m', 'Construction', 'pcs', 420.00, 300, 1500, 800, 'SteelCo Inc.', 'active', 2, '2026-08-14 18:40:03', '2026-08-14 18:40:03'),
(3, 'MAT-003', 'Plywood 1/2\"', 'Marine plywood 4x8 ft', 'Construction', 'sheets', 850.00, 100, 500, 200, 'Woodland Supply', 'active', 2, '2026-08-14 18:40:03', '2026-08-14 18:40:03'),
(4, 'MAT-004', 'Nails 4\"', 'Common nails 4 inches', 'Hardware', 'kg', 85.00, 50, 300, 150, 'Hardware Express', 'active', 2, '2026-08-14 18:40:03', '2026-08-14 18:40:03'),
(5, 'MAT-005', 'PVC Pipe 2\"', 'PVC pipe 2 inch diameter', 'Plumbing', 'pcs', 320.00, 100, 500, 350, 'PlumbTech', 'active', 2, '2026-08-14 18:40:03', '2026-08-14 18:40:03');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_number`, `invoice_id`, `amount`, `payment_date`, `payment_method`, `reference_number`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PAY-202608-0001', 1, 10000.00, '2026-08-17', 'Credit Card', '245355', NULL, 28, '2026-08-17 06:39:17', '2026-08-17 06:39:17');

-- --------------------------------------------------------

--
-- Table structure for table `payroll`
--

CREATE TABLE `payroll` (
  `id` int(11) NOT NULL,
  `payroll_number` varchar(50) NOT NULL,
  `employee_name` varchar(100) NOT NULL,
  `employee_position` varchar(100) DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `basic_salary` decimal(15,2) DEFAULT 0.00,
  `deductions` decimal(15,2) DEFAULT 0.00,
  `net_pay` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','approved','paid') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll`
--

INSERT INTO `payroll` (`id`, `payroll_number`, `employee_name`, `employee_position`, `period_start`, `period_end`, `basic_salary`, `deductions`, `net_pay`, `status`, `approved_by`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PRL-202608-0001', 'ronald', 'staff', '2026-08-01', '2026-08-31', 5000.00, 0.00, 5000.00, 'approved', NULL, 28, '2026-08-17 06:36:19', '2026-08-17 06:36:19');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `client` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planning','ongoing','completed','on_hold') DEFAULT 'planning',
  `estimated_budget` decimal(15,2) DEFAULT 0.00,
  `actual_cost` decimal(15,2) DEFAULT 0.00,
  `project_manager_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `project_code`, `name`, `description`, `location`, `client`, `start_date`, `end_date`, `status`, `estimated_budget`, `actual_cost`, `project_manager_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PRJ-2026-001', 'Runeha Corporate Building', 'Construction of 10-story corporate headquarters', 'Makati City', 'Runeha INC.', '2026-01-15', '2026-12-30', 'ongoing', 50000000.00, 0.00, 4, 1, '2026-08-14 18:40:03', '2026-08-14 18:40:03'),
(2, 'PRJ-2026-1.844674407371E+19', 'Mall', '', 'caloocan', 'santos', '2026-08-17', '2026-08-31', 'ongoing', 100000.00, 0.00, NULL, 28, '2026-08-17 06:13:08', '2026-08-17 06:13:08');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `po_number` varchar(50) NOT NULL,
  `purchase_request_id` int(11) DEFAULT NULL,
  `supplier` varchar(255) NOT NULL,
  `supplier_contact` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('draft','sent','confirmed','delivered','cancelled') DEFAULT 'draft',
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `purchase_request_id`, `supplier`, `supplier_contact`, `order_date`, `delivery_date`, `payment_terms`, `status`, `total_amount`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PO-202608-0001', 1, 'Hardware Supply', '09264310192', '2026-08-17', '2026-08-20', 'COD', 'sent', 0.00, 28, '2026-08-17 06:23:19', '2026-08-17 06:23:19');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) DEFAULT 0.00,
  `received_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` int(11) NOT NULL,
  `pr_number` varchar(50) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `requestor_id` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('draft','pending','approved','rejected','ordered','received') DEFAULT 'draft',
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_requests`
--

INSERT INTO `purchase_requests` (`id`, `pr_number`, `project_id`, `requestor_id`, `purpose`, `priority`, `status`, `total_amount`, `approved_by`, `approved_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'PR-202601-0001', 1, 4, 'Initial materials for building foundation', 'high', 'approved', 0.00, NULL, NULL, 1, '2026-08-14 18:40:03', '2026-08-14 18:40:03'),
(2, 'PR-202608-0001', 2, 28, 'need materials', 'medium', 'draft', 1600.00, NULL, NULL, 28, '2026-08-17 06:20:36', '2026-08-17 06:20:36');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_request_items`
--

CREATE TABLE `purchase_request_items` (
  `id` int(11) NOT NULL,
  `purchase_request_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit` varchar(50) DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_request_items`
--

INSERT INTO `purchase_request_items` (`id`, `purchase_request_id`, `material_id`, `quantity`, `unit`, `estimated_cost`, `remarks`, `created_at`) VALUES
(1, 1, 1, 500, 'bags', 280.00, NULL, '2026-08-14 18:40:03'),
(2, 1, 2, 200, 'pcs', 420.00, NULL, '2026-08-14 18:40:03'),
(3, 2, 1, 2, 'pcs', 200.00, '', '2026-08-17 06:20:36'),
(4, 2, 4, 2, 'pcs', 600.00, '', '2026-08-17 06:20:36');

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `id` int(11) NOT NULL,
  `quotation_number` varchar(50) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `supplier` varchar(255) NOT NULL,
  `supplier_contact` varchar(255) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('requested','received','accepted','rejected') DEFAULT 'requested',
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `quotation_number`, `project_id`, `supplier`, `supplier_contact`, `valid_until`, `status`, `total_amount`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'QT-202608-0001', 2, 'Hardware Supply', '09264310192', '2026-08-19', 'requested', 0.00, 'Deliver', 28, '2026-08-17 06:23:46', '2026-08-17 06:23:46');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `movement_type` enum('in','out','transfer') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tin_number` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `tin_number`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'angel', '12124143412', '09938202525', 'johnangel.dlsrys@gmail.com', 'Bulacan', '123', 'active', 28, '2026-08-17 06:31:42', '2026-08-17 06:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `department` enum('procurement','projects','accounting','warehouse','admin') NOT NULL,
  `role` enum('manager','staff','admin') DEFAULT 'staff',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `department`, `role`, `status`, `created_at`, `updated_at`) VALUES
(28, 'admin', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'System Administrator', 'admin@runeha.com', 'admin', 'admin', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(29, 'procurement_mgr', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Procurement Manager', 'procurement@runeha.com', 'procurement', 'manager', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(30, 'procurement_staff', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Procurement Staff', 'procurement.staff@runeha.com', 'procurement', 'staff', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(31, 'projects_mgr', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Projects Manager', 'projects@runeha.com', 'projects', 'manager', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(32, 'projects_staff', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Projects Staff', 'projects.staff@runeha.com', 'projects', 'staff', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(33, 'accounting_mgr', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Accounting Manager', 'accounting@runeha.com', 'accounting', 'manager', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(34, 'accounting_staff', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Accounting Staff', 'accounting.staff@runeha.com', 'accounting', 'staff', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(35, 'warehouse_mgr', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Warehouse Manager', 'warehouse@runeha.com', 'warehouse', 'manager', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58'),
(36, 'warehouse_staff', '$2y$10$3AgDPrugW/eiQC9v70yY5.owBhWfQiS/KmpaLI4ORXQKiEpPm4LRa', 'Warehouse Staff', 'warehouse.staff@runeha.com', 'warehouse', 'staff', 'active', '2026-08-14 18:40:58', '2026-08-14 18:40:58');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_stock`
--

CREATE TABLE `warehouse_stock` (
  `id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `location` varchar(100) DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `expense_number` (`expense_number`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `material_code` (`material_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_number` (`payment_number`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_number` (`payroll_number`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_code` (`project_code`),
  ADD KEY `project_manager_id` (`project_manager_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `purchase_request_id` (`purchase_request_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pr_number` (`pr_number`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `requestor_id` (`requestor_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_request_id` (`purchase_request_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_id` (`quotation_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `warehouse_stock`
--
ALTER TABLE `warehouse_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `warehouse_stock`
--
ALTER TABLE `warehouse_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`project_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `purchase_requests_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_requests_ibfk_2` FOREIGN KEY (`requestor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `purchase_requests_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  ADD CONSTRAINT `purchase_request_items_ibfk_1` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_request_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quotations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `quotation_items_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quotation_items_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_stock`
--
ALTER TABLE `warehouse_stock`
  ADD CONSTRAINT `warehouse_stock_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`),
  ADD CONSTRAINT `warehouse_stock_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
