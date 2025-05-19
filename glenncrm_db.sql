-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2025 at 01:59 AM
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
-- Database: `glenncrm_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`log_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:16:52'),
(2, 6, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:17:21'),
(3, 6, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:21:27'),
(4, 6, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:21:44'),
(5, 6, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:23:24'),
(6, 6, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:23:45'),
(7, 1, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:26:15'),
(8, 5, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:27:09'),
(9, 5, 'updated', 'customer', 26, NULL, '::1', '2025-05-15 23:35:12'),
(10, 1, 'logged in', 'system', NULL, NULL, '::1', '2025-05-15 23:36:12');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive','prospect') DEFAULT 'active',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `name`, `email`, `phone`, `address`, `notes`, `status`, `assigned_to`, `created_at`, `updated_at`) VALUES
(16, 'Sane Corp', 'contact@acme.com', '555-0101', '123 Elm St', 'Priority client', 'active', 5, '2025-05-15 22:11:42', NULL),
(17, 'Veta', 'info@beta.com', '555-0202', '456 Oak Ave', 'Under review', 'prospect', 6, '2025-05-15 22:11:42', '2025-05-15 23:05:56'),
(18, 'Gamma Inc', 'sales@gamma.com', '555-0303', '789 Pine Rd', 'Key account', 'active', 5, '2025-05-15 22:11:42', NULL),
(19, 'Delta Co', 'hello@delta.co', '555-0404', '321 Maple Dr', 'Low priority', 'inactive', 6, '2025-05-15 22:11:42', NULL),
(20, 'Epsilon Ltd', 'team@epsilon.ltd', '555-0505', '654 Birch Ln', 'Follow up in Q2', 'prospect', 5, '2025-05-15 22:11:42', NULL),
(26, 'Acme Corp', 'contact@acme.com', '555-0101', '123 Elm St', 'Priority client', 'active', 5, '2025-05-15 22:12:14', NULL),
(27, 'Beta LLC', 'info@beta.com', '555-0202', '456 Oak Ave', 'Under review', 'prospect', 6, '2025-05-15 22:12:14', NULL),
(28, 'Rader', 'sales@gamma.com', '555-0303', '789 Pine Rd', 'Key account', 'active', 5, '2025-05-15 22:12:14', '2025-05-15 23:06:14'),
(29, 'Alfa LLC', 'hello@delta.co', '555-0404', '321 Maple Dr', 'Low priority', 'inactive', 6, '2025-05-15 22:12:14', '2025-05-15 23:07:08'),
(30, 'Teason', 'team@epsilon.ltd', '555-0505', '654 Birch Ln', 'Follow up in Q2', 'prospect', 5, '2025-05-15 22:12:14', '2025-05-15 23:06:49');

-- --------------------------------------------------------

--
-- Table structure for table `interactions`
--

CREATE TABLE `interactions` (
  `interaction_id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `interaction_type` enum('call','email','meeting','other') DEFAULT NULL,
  `interaction_date` datetime NOT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in minutes',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interactions`
--

INSERT INTO `interactions` (`interaction_id`, `lead_id`, `customer_id`, `user_id`, `interaction_type`, `interaction_date`, `duration`, `notes`, `created_at`) VALUES
(6, 16, 19, 5, 'call', '2024-06-12 09:30:00', 15, 'Left voicemail re: mockups', '2025-05-15 22:29:16'),
(7, 17, 20, 6, 'email', '2024-06-13 14:15:00', 10, 'Sent pricing sheet PDF', '2025-05-15 22:29:16'),
(8, 20, 28, 5, 'meeting', '2024-06-14 11:00:00', 45, 'Performed kick-off demo', '2025-05-15 22:29:16'),
(9, NULL, 30, 6, 'call', '2024-06-16 16:45:00', 5, 'Client requested more case studies', '2025-05-15 22:29:16'),
(10, NULL, 16, 5, 'call', '2024-06-18 10:00:00', 20, 'Discussed next sprint scope', '2025-05-15 22:29:16');

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `lead_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('new','contacted','qualified','proposal','negotiation','closed_won','closed_lost') DEFAULT 'new',
  `source` varchar(100) DEFAULT NULL,
  `value` decimal(10,2) DEFAULT 0.00,
  `expected_close_date` date DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`lead_id`, `customer_id`, `title`, `description`, `status`, `source`, `value`, `expected_close_date`, `priority`, `assigned_to`, `created_at`, `updated_at`) VALUES
(16, 16, 'New website project', 'Build marketing site', 'negotiation', 'Referral', 12000.00, '2024-08-15', 'high', 5, '2025-05-15 22:13:35', '2025-05-15 23:08:01'),
(17, 17, 'Mobile app', 'iOS + Android', 'negotiation', 'Web', 24000.00, '2024-09-01', 'medium', 6, '2025-05-15 22:13:35', '2025-05-15 22:46:17'),
(18, 20, 'CRM integration', 'Migrate data to CRM', 'qualified', 'Email', 8000.00, '2024-07-30', 'high', 5, '2025-05-15 22:13:35', NULL),
(19, 26, 'Support contract', 'Annual SLA', 'proposal', 'Phone', 5000.00, '2024-07-15', 'low', 6, '2025-05-15 22:13:35', NULL),
(20, 30, 'Custom reporting', 'Dashboard widgets', 'new', 'Web', 6000.00, '2024-08-05', 'medium', 5, '2025-05-15 22:13:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `reminder_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `reminder_date` datetime NOT NULL,
  `message` text DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reminders`
--

INSERT INTO `reminders` (`reminder_id`, `user_id`, `customer_id`, `lead_id`, `title`, `reminder_date`, `message`, `is_completed`, `sent`, `created_at`) VALUES
(5, 6, 16, 16, 'Call about website', '2025-06-18 10:00:00', 'Check mockups', 0, 0, '2025-05-15 22:15:49'),
(6, 6, 20, NULL, 'Send proposal', '2024-10-28 14:00:00', 'Email qualified quote', 0, 0, '2025-05-15 22:15:49'),
(7, 5, NULL, 17, 'Follow up integration', '2024-07-01 09:00:00', 'Confirm data format', 0, 0, '2025-05-15 22:15:49'),
(8, 6, 17, NULL, 'Discuss reporting', '2024-07-05 11:00:00', 'Share initial wireframe', 0, 0, '2025-05-15 22:15:49'),
(9, 1, 26, 19, 'TEST', '2025-05-20 01:10:00', 'Call', 0, 0, '2025-05-15 23:11:11');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sale_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`sale_id`, `lead_id`, `customer_id`, `user_id`, `sale_date`, `amount`, `description`, `created_at`) VALUES
(4, NULL, 16, 5, '2024-12-20', 15000.00, 'Ad-hoc support package', '2025-05-15 22:14:41'),
(5, NULL, 17, 6, '2025-03-20', 12000.00, 'Website build', '2025-05-15 22:14:41'),
(6, NULL, 30, 5, '2025-02-17', 8000.00, 'CRM integration', '2025-05-15 22:14:41');

-- --------------------------------------------------------

--
-- Table structure for table `sales_reports`
--

CREATE TABLE `sales_reports` (
  `report_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_sales` decimal(10,2) DEFAULT NULL,
  `leads_count` int(11) DEFAULT 0,
  `converted_leads` int(11) DEFAULT 0,
  `conversion_rate` decimal(5,2) DEFAULT NULL,
  `report_period` enum('daily','weekly','monthly','yearly') DEFAULT 'monthly',
  `report_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_name`, `setting_value`, `setting_description`, `is_public`, `updated_at`) VALUES
(1, 'company_name', 'Glenn CRM', 'Company name displayed throughout the application', 1, '2025-05-15 21:32:14'),
(2, 'email_notifications', 'true', 'Enable or disable email notifications', 0, '2025-05-15 18:20:54'),
(3, 'items_per_page', '10', 'Number of items to display per page in lists', 1, '2025-05-15 18:20:54'),
(4, 'email_from', 'glenncrmmanager@gmail.com', '', 0, '2025-05-15 21:11:27'),
(5, 'smtp_host', 'smtp.gmail.com', '', 0, '2025-05-15 21:27:01'),
(6, 'smtp_port', '587', '', 0, '2025-05-15 21:11:27'),
(7, 'smtp_user', 'glenncrmmanager@gmail.com', '', 0, '2025-05-15 21:29:11'),
(8, 'smtp_pass', 'qxmn gidp jcdm irpa', '', 0, '2025-05-15 21:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `email`, `first_name`, `last_name`, `role`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$8U7xVWETE1W.UVPyPZm1s.oiMIqQcdLUld4PaZzMXjkCeL0on/9lO', 'admin@example.com', 'Admin', 'User', 'admin', '2025-05-15 23:36:12', '2025-05-15 18:20:54'),
(5, 'jdoe', '$2y$10$39WmZo2w0dbbwXKsiXGzd.l3ZeVzdy.RkE2eYkf3FqD4Y/qFY/DXm', 'jdoe@acme.com', 'John', 'Doe', 'user', '2025-05-15 23:27:09', '2025-05-15 22:07:16'),
(6, 'asmith', '$2y$10$MGcc6xVY3hf3ExuyUMDt0uYiW5hLttzod2menQq95JPFk19ODEGMa', 'asmith@acme.com', 'Anna', 'Smith', 'user', '2025-05-15 23:23:45', '2025-05-15 22:07:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `interactions`
--
ALTER TABLE `interactions`
  ADD PRIMARY KEY (`interaction_id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`lead_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `lead_id` (`lead_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `lead_id` (`lead_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sales_reports`
--
ALTER TABLE `sales_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `interactions`
--
ALTER TABLE `interactions`
  MODIFY `interaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `lead_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `sales_reports`
--
ALTER TABLE `sales_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `interactions`
--
ALTER TABLE `interactions`
  ADD CONSTRAINT `interactions_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`lead_id`),
  ADD CONSTRAINT `interactions_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `interactions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `leads_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `reminders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `reminders_ibfk_3` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`lead_id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`lead_id`),
  ADD CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `sales_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `sales_reports`
--
ALTER TABLE `sales_reports`
  ADD CONSTRAINT `sales_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
