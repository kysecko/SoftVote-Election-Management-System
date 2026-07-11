-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 11, 2026 at 01:17 PM
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
-- Database: `softvote_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `admin_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'DATABASE_BACKUP', 'Super Admin exported a full database backup.', '::1', '2026-04-06 19:47:24'),
(2, 2, 'DATABASE_BACKUP', 'Super Admin exported a full database backup.', '::1', '2026-04-24 09:52:49'),
(3, 2, 'DATABASE_BACKUP_JSON', 'Super Admin exported a JSON database backup.', '::1', '2026-04-24 09:52:50'),
(4, 2, 'DATABASE_BACKUP', 'Super Admin exported a full database backup.', '::1', '2026-04-24 22:52:26'),
(5, 2, 'DATABASE_BACKUP_JSON', 'Super Admin exported a JSON database backup.', '::1', '2026-04-24 22:52:29'),
(6, 2, 'DATABASE_BACKUP_JSON', 'Super Admin exported a JSON database backup.', '::1', '2026-05-23 16:27:24');

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

CREATE TABLE `administrators` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_type` varchar(20) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `is_deleted` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`id`, `username`, `password`, `name`, `created_at`, `user_type`, `is_active`, `is_deleted`, `last_login`, `last_activity`) VALUES
(11, 'arjaytejano', '$2y$10$syZc2n/NCPapq3UCa2lpfe2hJSbp/V9V.AS90X1mYuYx/9yqu8mGG', 'Arjay Tejano', '2026-04-24 01:01:07', 'admin', 1, 0, '2026-05-10 19:56:24', '2026-05-10 19:56:37'),
(13, 'paulamedina', '$2y$10$kUyKggk0m.etmYLFdU6Fm.URcmrahlI2aMlusOE1v7noAB9RoEuwC', 'Paula Medina', '2026-04-24 14:05:45', 'admin', 1, 0, '2026-04-24 22:49:04', '2026-04-24 22:49:04'),
(14, 'aljonmalle', '$2y$10$zdBLQzy..O87LpAyJIixMelSXMFija0W1e6QZ5DF05ciZ8Mthqpuu', 'Aljon Malle', '2026-04-24 14:56:57', 'admin', 0, 0, NULL, NULL),
(15, 'newadmin', '$2y$10$9hh.vA0/.SEbOjgMFfibl.byn6J1o8YNmLSaw7n9hCKFpzHKeBvs6', 'Administrator', '2026-05-10 12:03:40', 'admin', 1, 0, '2026-05-23 16:34:42', '2026-05-23 08:35:13');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `actor_type` enum('superadmin','admin') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `actor_name` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor_type`, `actor_id`, `actor_name`, `action`, `target_type`, `target_id`, `detail`, `ip_address`, `created_at`) VALUES
(469, 'admin', 15, 'Administrator', 'login', NULL, NULL, 'Admin logged in', '::1', '2026-05-18 09:33:12'),
(470, 'admin', 15, 'Administrator', 'login', NULL, NULL, 'Admin logged in', '::1', '2026-05-18 09:49:36'),
(471, 'superadmin', 2, 'Super Administrator', 'login', NULL, NULL, 'Superadmin logged in', '::1', '2026-05-18 09:53:19'),
(472, 'superadmin', 2, 'Super Administrator', 'login', NULL, NULL, 'Superadmin logged in', '::1', '2026-05-18 10:03:15'),
(473, 'admin', 15, 'Administrator', 'login', NULL, NULL, 'Admin logged in', '::1', '2026-05-23 07:43:07'),
(474, 'admin', 15, 'Administrator', 'login', NULL, NULL, 'Admin logged in', '::1', '2026-05-23 08:00:34'),
(475, 'superadmin', 2, 'Super Administrator', 'login', NULL, NULL, 'Superadmin logged in', '::1', '2026-05-23 08:26:25'),
(476, 'superadmin', 2, 'Super Administrator', 'election_status_changed', 'election', 0, '0', '::1', '2026-05-23 08:28:20'),
(477, 'superadmin', 2, 'Super Administrator', 'election_status_changed', 'election', 0, '0', '::1', '2026-05-23 08:28:29'),
(478, 'superadmin', 2, 'Super Administrator', 'election_status_changed', 'election', 0, '0', '::1', '2026-05-23 08:28:33'),
(479, 'superadmin', 2, 'Super Administrator', 'election_status_changed', 'election', 0, '0', '::1', '2026-05-23 08:29:43'),
(480, 'superadmin', 2, 'Super Administrator', 'admin_suspended', 'admin', 15, '0', '::1', '2026-05-23 08:30:50'),
(481, 'superadmin', 2, 'Super Administrator', 'admin_activated', 'admin', 15, '0', '::1', '2026-05-23 08:30:58'),
(482, 'admin', 15, 'Administrator', 'login', NULL, NULL, 'Admin logged in', '::1', '2026-05-23 08:34:42');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `partylist_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `photo_url` varchar(255) DEFAULT 'https://ui-avatars.com/api/?name=Candidate&size=200&background=6366f1&color=fff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `is_winner` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `position_id`, `partylist_id`, `name`, `photo_url`, `created_at`, `first_name`, `last_name`, `middle_name`, `is_winner`) VALUES
(136, 1, 12, 'MANITAY, ALTHEA MONIQUE', 'uploads/candidates/cand_69eac4e8f24f2.jpg', '2026-04-24 01:18:32', 'ALTHEA MONIQUE', 'MANITAY', '', 0),
(137, 1, 13, 'CASTRO, JANDRICK', 'uploads/candidates/cand_69eac591b7bf5.jpg', '2026-04-24 01:21:21', 'JANDRICK', 'CASTRO', '', 0),
(138, 2, 12, 'VENGUA, JOANNA MAE', 'uploads/candidates/cand_69eac5c3bdf95.jpg', '2026-04-24 01:22:11', 'JOANNA MAE', 'VENGUA', '', 0),
(139, 2, 13, 'BACLIG, ELLAYSA', 'uploads/candidates/cand_69eac6098ea49.jpg', '2026-04-24 01:23:21', 'ELLAYSA', 'BACLIG', '', 0),
(140, 3, 12, 'CUNANAN, LYKA MAE Q.', 'uploads/candidates/cand_69eac63fe8ea5.jpg', '2026-04-24 01:24:15', 'LYKA MAE', 'CUNANAN', 'Q', 0),
(141, 3, 13, 'SAGAO, JOSHUA', 'uploads/candidates/cand_69eac67ac715b.jpg', '2026-04-24 01:25:14', 'JOSHUA', 'SAGAO', '', 0),
(142, 5, 12, 'SONGCO, RUSSEL', 'uploads/candidates/cand_69eac6a93686b.jpg', '2026-04-24 01:26:01', 'RUSSEL', 'SONGCO', '', 0),
(143, 5, 13, 'SENDON, RHEANA D.', 'uploads/candidates/cand_69eac6c30df86.jpg', '2026-04-24 01:26:27', 'RHEANA', 'SENDON', 'D', 0),
(144, 4, 12, 'CONSTANTINO, KATHLYN MAE', 'uploads/candidates/cand_69eac6e2510a1.jpg', '2026-04-24 01:26:58', 'KATHLYN MAE', 'CONSTANTINO', '', 0),
(145, 4, 13, 'ESTRADA, PRINCE', 'uploads/candidates/cand_69eac71494228.jpg', '2026-04-24 01:27:48', 'PRINCE', 'ESTRADA', '', 0),
(146, 6, 12, 'VALENZUELA, JEFFREY', 'uploads/candidates/cand_69eac76b9b4e2.jpg', '2026-04-24 01:29:15', 'JEFFREY', 'VALENZUELA', '', 0),
(147, 6, 13, 'BALATBAT, RHYLE', 'uploads/candidates/cand_69eac79e04e1f.jpg', '2026-04-24 01:30:06', 'RHYLE', 'BALATBAT', '', 0),
(148, 7, 12, 'RAYMUNDO, GARNETT JAMES', 'uploads/candidates/cand_69eac7d33120b.jpg', '2026-04-24 01:30:59', 'GARNETT JAMES', 'RAYMUNDO', '', 0),
(149, 7, 13, 'CASTRO, RJ', 'uploads/candidates/cand_69eac7ed4f7e3.jpg', '2026-04-24 01:31:25', 'RJ', 'CASTRO', '', 0),
(150, 8, 12, 'ARCENAL, BIEL ALEXA L.', 'uploads/candidates/cand_69eac80cc90f5.jpg', '2026-04-24 01:31:56', 'BIEL ALEXA', 'ARCENAL', 'L', 0),
(151, 8, 13, 'BORBORAN, ALEKSANDRA', 'uploads/candidates/cand_69eac8264fdb2.jpg', '2026-04-24 01:32:22', 'ALEKSANDRA', 'BORBORAN', '', 0),
(152, 9, 12, 'PINTO, CHLOE R.', 'uploads/candidates/cand_69eac8583f5e2.jpg', '2026-04-24 01:33:12', 'CHLOE', 'PINTO', 'R', 0),
(153, 9, 13, 'DELA ROSA, BABY ANN', 'uploads/candidates/cand_69eac86ebec68.jpg', '2026-04-24 01:33:34', 'BABY ANN', 'DELA ROSA', '', 0),
(154, 10, 12, 'DE LEON, RONALD JAY', 'uploads/candidates/cand_69eac89183491.jpg', '2026-04-24 01:34:09', 'RONALD JAY', 'DE LEON', '', 0),
(155, 10, 13, 'UMPAR, JHON PAUL', 'uploads/candidates/cand_69eac8a94742f.jpg', '2026-04-24 01:34:33', 'JHON PAUL', 'UMPAR', '', 0),
(156, 11, 12, 'GOMEZ, DOMINIC', 'uploads/candidates/cand_69eac8c82316c.jpg', '2026-04-24 01:35:04', 'DOMINIC', 'GOMEZ', '', 0),
(157, 11, 13, 'SALAVER, NADINE', 'uploads/candidates/cand_69eac8e4bfb19.jpg', '2026-04-24 01:35:32', 'NADINE', 'SALAVER', '', 0),
(158, 12, 12, 'BOLANTE, JOHN NIXON', 'uploads/candidates/cand_69eac90933e42.jpg', '2026-04-24 01:36:09', 'JOHN NIXON', 'BOLANTE', '', 0),
(159, 12, 13, 'LORENZO, MERYL', 'uploads/candidates/cand_69eac923d4a3d.jpg', '2026-04-24 01:36:35', 'MERYL', 'LORENZO', '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `election_settings`
--

CREATE TABLE `election_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `election_settings`
--

INSERT INTO `election_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'election_title', 'Student Council Election 2026', '2026-02-10 13:21:57'),
(2, 'election_status', 'ongoing', '2026-05-23 08:35:13'),
(3, 'voting_enabled', '1', '2026-02-10 13:21:57'),
(4, 'results_visible', '0', '2026-02-10 13:21:57');

-- --------------------------------------------------------

--
-- Table structure for table `otp_tokens`
--

CREATE TABLE `otp_tokens` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `token` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partylists`
--

CREATE TABLE `partylists` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partylists`
--

INSERT INTO `partylists` (`id`, `name`, `created_at`) VALUES
(12, 'SIKSIK PARTYLIST', '2026-04-24 01:17:18'),
(13, 'ASTIG PARTYLIST', '2026-04-24 01:17:24');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `position_type` enum('council','board') NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_name`, `position_type`, `display_order`, `created_at`) VALUES
(1, 'Chairman', 'council', 1, '2026-02-10 13:21:57'),
(2, 'Vice Chairman', 'council', 2, '2026-02-10 13:21:57'),
(3, 'Secretary', 'council', 3, '2026-02-10 13:21:57'),
(4, 'Auditor', 'council', 4, '2026-02-10 13:21:57'),
(5, 'Treasurer', 'council', 5, '2026-02-10 13:21:57'),
(6, 'P.R.O', 'council', 6, '2026-02-10 13:21:57'),
(7, 'TVL Representative', 'board', 7, '2026-02-10 13:21:57'),
(8, 'ACADS Representative', 'board', 8, '2026-02-10 13:21:57'),
(9, 'HRMT Representative', 'board', 9, '2026-02-10 13:21:57'),
(10, 'CET Representative', 'board', 10, '2026-02-10 13:21:57'),
(11, 'IT Representative', 'board', 11, '2026-02-10 13:21:57'),
(12, 'TTMT Representative', 'board', 12, '2026-02-10 13:21:57');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `has_voted` tinyint(1) DEFAULT 0,
  `is_new_user` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `password`, `department`, `section`, `email`, `has_voted`, `is_new_user`, `created_at`, `last_login`) VALUES
(151, '23-0100', '$2y$10$uXAPdEV36k9kyuvuEAN5..sLpAkpBK1O.LYhYMxnm2/.L.zYQ1jre', 'IT', '302', '', 0, 1, '2026-05-14 04:31:31', NULL),
(152, '23-0143', '$2y$10$w2HNw54p/04Nl6XSy176seq5SLIxT5Iav3y8AVVvGKuxjEWcPc3Qy', 'IT', '302', '', 0, 1, '2026-05-14 04:31:31', NULL),
(153, '23-0121', '$2y$10$ByR5msAyl.M6bNkb3eLuAezF64q.zhtN2Kw1D1lVNmCLOLXllrfA.', 'IT', '302', '', 0, 1, '2026-05-14 04:31:32', NULL),
(154, '23-0123', '$2y$10$foBf2ecavT43svgmMijKr.ksC14GpMaewjEro1jenBWX.dlwgE.Di', 'IT', '302', '', 0, 1, '2026-05-14 04:31:32', NULL),
(155, '23-0079', '$2y$10$kE5VdR9ZB5X7VRCmG12VEu6AOMBS7f1X04u/N8eNQaJtqFulIruam', 'IT', '302', '', 0, 1, '2026-05-14 04:31:32', NULL),
(156, '23-0093', '$2y$10$0jxY3cDMT/cf2L18NyO.SOCfN6nV62b5jrLESKQwnT9kO12W.DeXC', 'IT', '302', '', 0, 1, '2026-05-14 04:31:32', NULL),
(157, '23-0109', '$2y$10$IJX.joXl2nPl5dqXakYSP.gx8oW1/DVUPRQSOonQqvfYcxmInHFgy', 'IT', '302', '', 0, 1, '2026-05-14 04:31:32', NULL),
(158, '23-0261', '$2y$10$hrenaBBvk/3XskQcppu01uqNySQiHAAq2x8FqHB/yuTacmfEEqJV.', 'IT', '302', '', 0, 1, '2026-05-14 04:31:32', NULL),
(159, '11-1111', '$2y$10$Td68upj9HbFyg6PynCfgSeupWTUc7ye0bxJphr0MFqOXn3gCnih3G', 'IT', '302', 'gecho4546@gmail.com', 0, 0, '2026-05-14 05:05:41', NULL),
(160, '22-2222', '$2y$10$9Ydne3zHk5EnIIrYwMVnbuO3XC8KOWCl6kTtMWvebf7whF.yru.G2', 'CS', 'CS32', 'rusellegarcia4@gmail.com', 0, 0, '2026-05-15 11:21:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `superadmins`
--

CREATE TABLE `superadmins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `superadmins`
--

INSERT INTO `superadmins` (`id`, `username`, `password`, `name`, `email`, `is_active`, `created_at`, `last_login`, `last_activity`) VALUES
(2, 'superadmin', '$2y$10$/gKM6OSAOSDryfXZ4.X8IeI0NlQNFktCNQS7cxeKIOnOM7m5mvrGa', 'Super Administrator', 'superadmin2@softvote.local', 1, '2026-04-18 23:46:08', '2026-05-23 08:26:25', '2026-05-23 16:30:58');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `position_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `vote_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vote_sessions`
--

CREATE TABLE `vote_sessions` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `vote_completed` tinyint(1) DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `action` (`action`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actor` (`actor_type`,`actor_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `fk_candidate_partylist` (`partylist_id`);

--
-- Indexes for table `election_settings`
--
ALTER TABLE `election_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `partylists`
--
ALTER TABLE `partylists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `superadmins`
--
ALTER TABLE `superadmins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vote` (`student_id`,`position_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `vote_sessions`
--
ALTER TABLE `vote_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=483;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `election_settings`
--
ALTER TABLE `election_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `partylists`
--
ALTER TABLE `partylists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `superadmins`
--
ALTER TABLE `superadmins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=435;

--
-- AUTO_INCREMENT for table `vote_sessions`
--
ALTER TABLE `vote_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_tokens`
--
ALTER TABLE `otp_tokens`
  ADD CONSTRAINT `otp_tokens_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vote_sessions`
--
ALTER TABLE `vote_sessions`
  ADD CONSTRAINT `vote_sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
