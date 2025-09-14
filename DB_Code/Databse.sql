-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 14, 2025 at 11:25 AM
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
  `status` varchar(50) DEFAULT NULL,
  `Manual` text DEFAULT NULL,
  `Author_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 'อ.สมศรี ', 'จารุผดุง', 'somsri.1', 'somsri11', 'somsri.ja@psu.ac.th', '091111111', 'อาจารย์', 'วิทยาการคอมพิวเตอร์', '1', 'img/somsri.jpg');

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
  MODIFY `Login_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Notification`
--
ALTER TABLE `Notification`
  MODIFY `Noti_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Publication`
--
ALTER TABLE `Publication`
  MODIFY `Pub_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `PublicationHistory`
--
ALTER TABLE `PublicationHistory`
  MODIFY `History_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `User`
--
ALTER TABLE `User`
  MODIFY `User_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
