-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 28, 2025 at 03:04 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `group10`
--

-- --------------------------------------------------------

--
-- Table structure for table `LoginHistory`
--

CREATE TABLE `LoginHistory` (
  `Login_id` int(11) NOT NULL,
  `User_id` int(11) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `success` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `LoginHistory`
--

INSERT INTO `LoginHistory` (`Login_id`, `User_id`, `time`, `success`) VALUES
(1, 1, '2025-09-19 20:15:12', 1),
(2, 2, '2025-09-19 20:28:25', 1),
(3, 2, '2025-09-19 20:29:05', 1),
(4, 3, '2025-09-19 23:27:01', 1),
(5, 3, '2025-09-19 23:30:53', 1),
(6, 3, '2025-09-19 23:31:10', 1),
(7, 3, '2025-09-19 23:32:39', 1),
(8, 3, '2025-09-19 23:34:31', 1),
(9, 3, '2025-09-19 23:38:40', 1),
(10, 1, '2025-09-19 23:38:58', 1),
(11, 3, '2025-09-19 23:40:56', 1),
(12, 2, '2025-09-19 23:52:51', 1),
(13, 1, '2025-09-20 00:04:13', 1),
(14, 1, '2025-09-27 13:27:56', 1),
(15, 2, '2025-09-27 13:29:07', 1),
(16, 3, '2025-09-27 13:29:48', 1),
(17, 2, '2025-09-27 13:52:26', 1),
(18, 4, '2025-09-27 15:16:32', 1),
(19, 3, '2025-09-27 15:36:54', 1),
(20, 4, '2025-09-27 16:36:18', 1),
(21, 4, '2025-09-27 16:40:11', 1),
(22, 2, '2025-09-27 16:41:30', 1),
(23, 4, '2025-09-27 16:41:41', 1),
(24, 3, '2025-09-27 16:42:49', 1),
(25, 4, '2025-09-27 16:44:16', 1),
(26, 4, '2025-09-27 16:47:03', 1),
(27, 3, '2025-09-27 16:47:25', 1),
(28, 4, '2025-09-27 16:48:58', 1),
(29, 3, '2025-09-27 16:53:15', 1),
(30, 4, '2025-09-27 16:53:33', 1),
(31, 2, '2025-09-27 17:01:52', 1),
(32, 4, '2025-09-27 17:39:42', 1),
(33, 4, '2025-09-27 17:41:57', 1),
(34, 4, '2025-09-27 17:53:59', 1),
(35, 4, '2025-09-27 17:58:18', 1),
(36, 4, '2025-09-27 18:06:47', 1),
(37, 4, '2025-09-27 18:09:10', 1),
(38, 4, '2025-09-27 18:47:42', 1),
(39, 3, '2025-09-27 20:16:49', 1),
(40, 4, '2025-09-28 10:39:36', 1),
(41, 2, '2025-09-28 11:21:54', 1),
(42, 4, '2025-09-28 11:25:08', 1),
(43, 1, '2025-09-28 12:03:36', 1),
(44, 4, '2025-09-28 12:05:43', 1),
(45, 2, '2025-09-28 12:45:58', 1),
(46, 4, '2025-09-28 12:46:32', 1),
(47, 3, '2025-09-28 13:22:56', 1),
(48, 4, '2025-09-28 13:23:23', 1),
(49, 2, '2025-09-28 13:25:57', 1),
(50, 4, '2025-09-28 15:00:44', 1),
(51, 1, '2025-09-28 15:49:42', 1),
(52, 3, '2025-09-28 16:10:15', 1),
(53, 4, '2025-09-28 16:19:11', 1),
(54, 3, '2025-09-28 16:30:39', 1),
(55, 4, '2025-09-28 16:42:40', 1),
(56, 4, '2025-09-28 16:43:51', 1),
(57, 3, '2025-09-28 18:01:52', 1),
(58, 3, '2025-09-28 18:16:26', 1),
(59, 3, '2025-09-28 18:23:38', 1),
(60, 4, '2025-09-28 18:40:55', 1),
(61, 2, '2025-09-28 18:51:05', 1),
(62, 3, '2025-09-28 19:21:31', 1),
(63, 2, '2025-09-28 19:26:42', 1),
(64, 4, '2025-09-28 19:40:12', 1),
(65, 3, '2025-09-28 19:40:25', 1),
(66, 4, '2025-09-28 19:42:56', 1),
(67, 4, '2025-09-28 19:50:21', 1),
(68, 3, '2025-09-28 19:50:43', 1),
(69, 4, '2025-09-28 19:51:17', 1),
(70, 2, '2025-09-28 19:57:00', 1),
(71, 4, '2025-09-28 19:57:52', 1),
(72, 2, '2025-09-28 19:58:59', 1);

-- --------------------------------------------------------

--
-- Table structure for table `Notification`
--

CREATE TABLE `Notification` (
  `Noti_id` int(11) NOT NULL,
  `User_id` int(11) DEFAULT NULL,
  `Pub_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Notification`
--

INSERT INTO `Notification` (`Noti_id`, `User_id`, `Pub_id`, `message`, `date_time`, `status`) VALUES
(2, 3, NULL, 'เจ้าหน้าที่ **Tanakon Panapong** ได้ส่งข้อความ: \'แจ้งผลงานได้รับการอนุมัติสำเร็จ\' ถึงคุณ', '2025-09-28 13:01:00', 'unread');

-- --------------------------------------------------------

--
-- Table structure for table `Publication`
--

CREATE TABLE `Publication` (
  `Pub_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `publish_year` year(4) DEFAULT NULL,
  `journal` varchar(255) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `visibility` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Waiting',
  `Manual` text DEFAULT NULL,
  `Author_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Publication`
--

INSERT INTO `Publication` (`Pub_id`, `title`, `publish_year`, `journal`, `type`, `file_path`, `visibility`, `status`, `Manual`, `Author_id`) VALUES
(3, 'การจำแนกภาพจอประสาทตาของผู้ป่วยเบาหวานขึ้นตา แต่ละระยะ ด้วย Deep Learning', '2023', 'Thesis', 'Journal', 'uploads/68d7af51e4a25.pdf', NULL, 'approved', NULL, 3),
(4, 'วิจัยของ อ.สมศรี', '2025', NULL, 'Conference', 'uploads/68d8c20eb3e6a.pdf', NULL, 'approved', NULL, 1),
(5, 'การออกแบบระบบบันทึกยอดขายแบบเรียลไทม์', '2025', 'Thesis', 'Journal', NULL, NULL, 'waiting', NULL, 3),
(6, 'การพัฒนาระบบแนะนำการลงทุนด้วย AI', '2022', 'Journal', 'Thesis', 'uploads/68d902fe4f6c1.pdf', NULL, 'approved', NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `PublicationHistory`
--

CREATE TABLE `PublicationHistory` (
  `History_id` int(11) NOT NULL,
  `Pub_id` int(11) DEFAULT NULL,
  `Edited_by` int(11) DEFAULT NULL,
  `change_detail` text DEFAULT NULL,
  `edit_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `PublicationHistory`
--

INSERT INTO `PublicationHistory` (`History_id`, `Pub_id`, `Edited_by`, `change_detail`, `edit_date`) VALUES
(2, 3, 3, 'Added new publication', '2025-09-27 16:33:05'),
(3, 4, 1, 'Added new publication', '2025-09-28 12:05:18'),
(4, 5, 3, 'Added new publication', '2025-09-28 16:31:45'),
(5, 6, 3, 'Added new publication', '2025-09-28 16:42:22');

-- --------------------------------------------------------

--
-- Table structure for table `User`
--

CREATE TABLE `User` (
  `User_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `Username` varchar(50) DEFAULT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `Department` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `User`
--

INSERT INTO `User` (`User_id`, `first_name`, `last_name`, `Username`, `Password`, `email`, `tel`, `role`, `Department`, `status`, `avatar`) VALUES
(1, 'อ.สมศรี', 'จารุผดุง', 'somsri11', '11', 'somsri.ja@psu.ac.th', '091111111', 'normal', 'วิทยาการคอมพิวเตอร์', '1', 'somsri.jpg'),
(2, 'Tarathep', 'Madmun', 'admin', '11111', 'tarathep11@gmail.com', '0812345678', 'admin', 'วิทยาการคอมพิวเตอร์', '1', NULL),
(3, 'ผศ.จรรยา', 'สายนุ้ย', 'janya', '11111', 'janya.s@psu.ac.th', '0987343210', 'normal', 'วิทยาการคอมพิวเตอร์', '1', 'janya.jpg'),
(4, 'Tanakon', 'Panapong', 'staff11', '11111', 'staff@gmail.com', '234234234', 'staff', 'วิทยาการคอมพิวเตอร์', '1', NULL),
(6, 'รศ.ดร.สาธิต', 'อินทจักร์', 'sathit11', '11', 'sathit.i@psu.ac.th', '123456789', 'normal', 'วิทยาการคอมพิวเตอร์', NULL, 'avatar_68d92b094871f.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `LoginHistory`
--
ALTER TABLE `LoginHistory`
  ADD PRIMARY KEY (`Login_id`),
  ADD KEY `User_id` (`User_id`);

--
-- Indexes for table `Notification`
--
ALTER TABLE `Notification`
  ADD PRIMARY KEY (`Noti_id`),
  ADD KEY `User_id` (`User_id`),
  ADD KEY `Pub_id` (`Pub_id`);

--
-- Indexes for table `Publication`
--
ALTER TABLE `Publication`
  ADD PRIMARY KEY (`Pub_id`),
  ADD KEY `Author_id` (`Author_id`);

--
-- Indexes for table `PublicationHistory`
--
ALTER TABLE `PublicationHistory`
  ADD PRIMARY KEY (`History_id`),
  ADD KEY `Pub_id` (`Pub_id`),
  ADD KEY `Edited_by` (`Edited_by`);

--
-- Indexes for table `User`
--
ALTER TABLE `User`
  ADD PRIMARY KEY (`User_id`),
  ADD UNIQUE KEY `Username` (`Username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `LoginHistory`
--
ALTER TABLE `LoginHistory`
  MODIFY `Login_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `Notification`
--
ALTER TABLE `Notification`
  MODIFY `Noti_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `Publication`
--
ALTER TABLE `Publication`
  MODIFY `Pub_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `PublicationHistory`
--
ALTER TABLE `PublicationHistory`
  MODIFY `History_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `User`
--
ALTER TABLE `User`
  MODIFY `User_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `LoginHistory`
--
ALTER TABLE `LoginHistory`
  ADD CONSTRAINT `loginhistory_ibfk_1` FOREIGN KEY (`User_id`) REFERENCES `User` (`User_id`);

--
-- Constraints for table `Notification`
--
ALTER TABLE `Notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`User_id`) REFERENCES `User` (`User_id`),
  ADD CONSTRAINT `notification_ibfk_2` FOREIGN KEY (`Pub_id`) REFERENCES `Publication` (`Pub_id`);

--
-- Constraints for table `Publication`
--
ALTER TABLE `Publication`
  ADD CONSTRAINT `publication_ibfk_1` FOREIGN KEY (`Author_id`) REFERENCES `User` (`User_id`);

--
-- Constraints for table `PublicationHistory`
--
ALTER TABLE `PublicationHistory`
  ADD CONSTRAINT `publicationhistory_ibfk_1` FOREIGN KEY (`Pub_id`) REFERENCES `Publication` (`Pub_id`),
  ADD CONSTRAINT `publicationhistory_ibfk_2` FOREIGN KEY (`Edited_by`) REFERENCES `User` (`User_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
