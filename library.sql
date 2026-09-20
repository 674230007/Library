-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 20, 2026 at 09:04 AM
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
-- Database: `library`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `image_url` varchar(500) DEFAULT 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=500&q=80',
  `status` enum('Available','Borrowed') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `category`, `image_url`, `status`, `created_at`) VALUES
(1, 'Clean Code', 'Software Engineering', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=500&q=80', 'Available', '2026-09-20 05:06:29'),
(2, 'Design Patterns', 'Software Architecture', 'https://images.unsplash.com/photo-1532012197267-da84d127e765?w=500&q=80', 'Available', '2026-09-20 05:06:29'),
(3, 'Introduction to Algorithms', 'Computer Science', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80', 'Available', '2026-09-20 05:06:29'),
(4, 'HTML & CSS Design', 'Web Development', 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=500&q=80', 'Available', '2026-09-20 05:06:29'),
(5, 'Clean Code', 'Software Engineering', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=500&q=80', 'Available', '2026-09-20 05:21:25'),
(6, 'Design Patterns', 'Software Architecture', 'https://images.unsplash.com/photo-1532012197267-da84d127e765?w=500&q=80', 'Available', '2026-09-20 05:21:25'),
(7, 'Introduction to Algorithms', 'Computer Science', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=500&q=80', 'Borrowed', '2026-09-20 05:21:25'),
(8, 'HTML & CSS Design', 'Web Development', 'https://images.unsplash.com/photo-1507842217343-583bb7270b66?w=500&q=80', 'Available', '2026-09-20 05:21:25'),
(9, 'Database System Concepts', 'Database', 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=500&q=80', 'Available', '2026-09-20 05:21:25'),
(10, 'Artificial Intelligence A Modern Approach', 'AI & Data Science', 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=500&q=80', 'Available', '2026-09-20 05:21:25'),
(11, 'หนูนิดไท่ชอบกินผัก', 'นิทาน', 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=500&q=80', 'Available', '2026-09-20 05:23:50');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `borrower_name` varchar(100) NOT NULL,
  `student_id` varchar(20) NOT NULL DEFAULT '654321000',
  `student_card_image` varchar(255) DEFAULT NULL,
  `status` enum('Borrowed','Returned') NOT NULL DEFAULT 'Borrowed',
  `approval_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `borrow_date` date NOT NULL,
  `due_date` date NOT NULL DEFAULT (curdate() + interval 7 day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `book_id`, `book_title`, `borrower_name`, `student_id`, `student_card_image`, `status`, `approval_status`, `borrow_date`, `due_date`) VALUES
(1, 3, 'Introduction to Algorithms', 'สมชาย ใจดี (Borrower)', '654321000', NULL, 'Returned', 'Approved', '2026-06-01', '2026-09-27'),
(2, 2, 'Design Patterns', 'ยศวดี หาญสีภูมิ (Borrower)', '654321000', NULL, 'Returned', 'Approved', '2026-09-20', '2026-09-27'),
(3, 11, 'หนูนิดไท่ชอบกินผัก', 'ยศวดี หาญสีภูมิ (Borrower)', '674230007', NULL, 'Returned', 'Approved', '2026-09-20', '2026-09-27'),
(4, 6, 'Design Patterns', 'นักศึกษา / ผู้ใช้บริการห้องสมุด', '674230009', NULL, 'Returned', 'Approved', '2026-09-20', '2026-09-23'),
(5, 9, 'Database System Concepts', 'นักศึกษา NPRU', '698547123', NULL, 'Returned', 'Approved', '2026-09-20', '2026-09-23'),
(6, 10, 'Artificial Intelligence A Modern Approach', 'นักศึกษา NPRU (ยืนยันบัตรแล้ว)', '674230009', NULL, 'Returned', 'Approved', '2026-09-20', '2026-09-23'),
(7, 11, 'หนูนิดไท่ชอบกินผัก', 'นักศึกษา NPRU (ยืนยันบัตรแล้ว)', '674230007', NULL, 'Returned', 'Approved', '2026-09-20', '2026-09-23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` enum('Borrower','Librarian','Procurement') NOT NULL DEFAULT 'Borrower',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `role`, `created_at`) VALUES
(1, 'borrower1', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'ยศวดี หาญสีภูมิ (Borrower)', 'Borrower', '2026-09-20 05:15:55'),
(2, 'librarian1', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'วราภรณ์ สายสล้าง (Librarian)', 'Librarian', '2026-09-20 05:15:55'),
(3, 'procurement1', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'สมศักดิ์ จัดซื้อ (Procurement)', 'Procurement', '2026-09-20 05:15:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`);

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
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
