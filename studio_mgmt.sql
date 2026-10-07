-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2026 at 04:03 PM
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
-- Database: `studio_mgmt`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'soulprint', '$2y$10$q6Z2W6O1hReODtJEmkfdFuZnOBhPx4/Aff.//ul1F.lwHV8rIZCw.', '2026-03-18 16:41:25');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `service_type` varchar(80) DEFAULT NULL,
  `package_key` varchar(80) DEFAULT NULL,
  `package_type` varchar(100) DEFAULT NULL,
  `motif` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `booking_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `slot_period` enum('AM','PM') DEFAULT NULL,
  `color_code` varchar(7) DEFAULT NULL,
  `attendee_count` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `client_notes` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'Confirmed',
  `status_note` text DEFAULT NULL,
  `status_updated_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `client_id`, `title`, `service_type`, `package_key`, `package_type`, `motif`, `price`, `booking_date`, `start_time`, `slot_period`, `color_code`, `attendee_count`, `status`, `status_note`, `status_updated_at`) VALUES
(1, 2, 'Princess portrait session', 'portrait', 'self_solo', 'Self Photography — Solo', 'Princess', 350.00, '2026-03-30', '09:42:00', NULL, '#3b82f6', 1, 'Confirmed', 'Imported appointment', '2026-03-30 09:00:00'),
(2, 2, 'Graduation portrait session', 'graduation', 'self_solo', 'Self Photography — Solo', 'Graduation', 350.00, '2026-03-30', '09:43:00', NULL, '#3b82f6', 1, 'Confirmed', 'Imported appointment', '2026-03-30 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `booking_updates`
--

CREATE TABLE `booking_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `status` varchar(32) NOT NULL,
  `note` text NOT NULL,
  `actor_type` enum('client','admin','system') NOT NULL DEFAULT 'system',
  `actor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_updates_booking_id` (`booking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `booking_updates` (`booking_id`, `status`, `note`, `actor_type`, `created_at`) VALUES
(1, 'Confirmed', 'Imported appointment', 'system', '2026-03-30 09:00:00'),
(2, 'Confirmed', 'Imported appointment', 'system', '2026-03-30 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `daily_tracker`
--

CREATE TABLE `daily_tracker` (
  `id` int(11) NOT NULL,
  `track_date` date NOT NULL,
  `income_today` decimal(10,2) NOT NULL,
  `client_today` int(11) NOT NULL,
  `target` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `liabilities`
--

CREATE TABLE `liabilities` (
  `id` int(11) NOT NULL,
  `creditor` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `username` varchar(150) DEFAULT NULL,
  `phone` varchar(11) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `username`, `phone`, `password`, `created_at`, `profile_image`) VALUES
(1, 'Romano', 'Datinguinoo', 'datinguinooromano@gmail.com', '09703947838', '$2y$10$R38BmED/3vtbX9w1EJPYNe9PUDMJI/LZwYGmHkYVo1nKLqjSHiLzi', '2026-03-28 12:30:06', NULL),
(2, 'Mano', 'Datinguinoo', 'datinguinoomano@gmail.com', '09874564357', '$2y$10$10zOzqEX6I3hPBzhlDbcH.Y32GwHRHFe8fGlmISVNhqhr5eRqWGWK', '2026-03-28 12:49:22', 'profile_69c7ce52956721.50589209.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_day_slot` (`booking_date`,`slot_period`),
  ADD KEY `bookings_client_status` (`client_id`,`status`),
  ADD KEY `bookings_date_status` (`booking_date`,`status`);

--
-- Indexes for table `daily_tracker`
--
ALTER TABLE `daily_tracker`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `track_date` (`track_date`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `liabilities`
--
ALTER TABLE `liabilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `daily_tracker`
--
ALTER TABLE `daily_tracker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `liabilities`
--
ALTER TABLE `liabilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
