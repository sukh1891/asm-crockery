-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 16, 2026 at 04:41 AM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u139684396_asm_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `remember_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `email`, `password`, `created_at`, `remember_token`) VALUES
(1, 'info@asmcrockery.com', '$2y$10$aFl3ls5n5x5r2nLzZO25lO0nveL1xoAIPON2oNIoMOUNCCGg34mAW', '2025-12-18 06:24:36', '61caddfa2cd387d6c0c4d59a5c4d453f0d4f4371bb8100d25f2d9f729b16245e');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `qty` int(11) DEFAULT 1,
  `price_inr` decimal(10,2) NOT NULL,
  `added_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `session_id`, `user_id`, `product_id`, `variation_id`, `qty`, `price_inr`, `added_at`) VALUES
(1, NULL, 1, 2, 10, 2, 18.00, '2026-02-05 09:22:16'),
(2, NULL, 1, 1, NULL, 3, 80.00, '2026-02-05 09:22:41'),
(3, 'ik6cllkssa93l595pc6d4chka9', NULL, 2, 9, 1, 12.00, '2026-02-09 05:26:05'),
(4, '2fco3gvb0rcj1jqbk144dctipo', NULL, 2, 9, 1, 12.00, '2026-02-10 08:00:18'),
(5, NULL, 2, 2, 9, 1, 12.00, '2026-02-11 08:31:56'),
(6, '5oo02k6a82sa0rmae1b3b5o4rq', NULL, 2, 9, 1, 12.00, '2026-02-13 08:27:08');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `slug` varchar(120) DEFAULT NULL,
  `parent` int(11) DEFAULT 0,
  `show_in_menu` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `parent`, `show_in_menu`) VALUES
(1, 'Test-1', 'test-1', 0, 1),
(2, 'Test-2', 'test-2', 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` enum('fixed','percent') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `min_order` decimal(10,2) DEFAULT 0.00,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `user_limit` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates`
--

CREATE TABLE `currency_rates` (
  `id` int(11) NOT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `rate` decimal(15,6) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount_codes`
--

CREATE TABLE `discount_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `value` decimal(10,2) DEFAULT NULL,
  `expiry` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `shipping_amount` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `currency` varchar(5) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `payment_id` varchar(200) DEFAULT NULL,
  `gateway_order_id` varchar(200) DEFAULT NULL,
  `tracking_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `refund_amount` float DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `name`, `phone`, `email`, `address`, `country`, `total_amount`, `shipping_amount`, `amount`, `currency`, `status`, `payment_id`, `gateway_order_id`, `tracking_id`, `created_at`, `refund_amount`) VALUES
(1, 'ASM-000001', NULL, 'Sushant Khurana', '09254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 12.00, 0.00, 12.00, 'INR', 'shipped', NULL, NULL, '', '2026-01-05 10:30:13', 0),
(2, 'ASM-000002', NULL, '', '', '', '', '', 54.00, 6000.00, 6054.00, 'INR', 'pending', NULL, NULL, NULL, '2026-01-07 05:26:47', 0),
(3, 'ASM-000003', NULL, '', '', '', '', '', 218.00, 2000.00, 2218.00, 'INR', 'pending', NULL, NULL, NULL, '2026-01-07 08:16:45', 0),
(4, 'ASM-000004', NULL, '', '', '', '', '', 12.00, 2000.00, 2012.00, 'INR', 'pending', NULL, NULL, NULL, '2026-01-07 08:52:21', 0),
(5, 'ASM-000005', NULL, '', '', '', '', '', 12.00, 2000.00, 2012.00, 'INR', 'pending', NULL, NULL, NULL, '2026-01-07 10:39:56', 0),
(6, 'ASM-000006', NULL, 'Sushant Khurana', '09254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 184.00, 0.00, 184.00, 'INR', 'pending', NULL, NULL, NULL, '2026-01-07 10:54:41', 0),
(7, 'ASM-000007', NULL, 'Sushant Khurana', '09254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 12.00, 0.00, 12.00, 'INR', 'pending', NULL, NULL, NULL, '2026-01-07 11:15:53', 0),
(8, 'ASM-000008', NULL, 'Sushant Khurana', '09254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 80.00, 0.00, 80.00, 'INR', 'pending', NULL, NULL, NULL, '2026-01-08 06:13:12', 0),
(9, 'ASM-000009', NULL, 'Sushant Khurana', '09254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 12.00, 0.00, 12.00, 'INR', 'pending', NULL, NULL, NULL, '2026-02-04 05:16:13', 0),
(10, 'ASM-000010', NULL, 'Sushant Khurana', '09254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 12.00, 0.00, 12.00, 'INR', 'pending', NULL, NULL, NULL, '2026-02-05 06:51:27', 0),
(11, NULL, NULL, 'Sushant Khurana', '9254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 111.00, 99.00, NULL, 'INR', 'pending', NULL, 'order_SEmwwDCfdnjFtS', NULL, '2026-02-11 09:26:10', 0),
(12, NULL, NULL, 'Sushant Khurana', '9254064033', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', 111.00, 99.00, NULL, 'INR', 'pending', NULL, 'order_SEn64B0H8e8zcC', NULL, '2026-02-11 09:34:48', 0);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variation_id`, `qty`, `price`) VALUES
(1, 1, 2, 9, 1, 12.00),
(2, 2, 2, 10, 3, 18.00),
(3, 3, 3, NULL, 1, 200.00),
(4, 3, 2, 10, 1, 18.00),
(5, 4, 2, 9, 1, 12.00),
(6, 5, 2, 9, 1, 12.00),
(7, 6, 1, NULL, 2, 80.00),
(8, 6, 2, 9, 2, 12.00),
(9, 7, 2, 9, 1, 12.00),
(10, 8, 1, NULL, 1, 80.00),
(11, 9, 2, 9, 1, 12.00),
(12, 10, 2, 9, 1, 12.00);

-- --------------------------------------------------------

--
-- Table structure for table `otp_log`
--

CREATE TABLE `otp_log` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_used` tinyint(4) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `product_type` enum('simple','variable') DEFAULT 'simple',
  `price_inr` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `slug` varchar(180) DEFAULT NULL,
  `regular_price` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `title`, `short_description`, `description`, `category_id`, `sku`, `product_type`, `price_inr`, `stock`, `weight`, `images`, `created_at`, `slug`, `regular_price`, `sale_price`) VALUES
(1, 'Test-1', NULL, 'product-add.php', 1, NULL, 'simple', 80.00, 1, NULL, '1766553609_0.webp', '2025-12-22 06:32:33', 'test-1', 100.00, 80.00),
(2, 'Test-2', '<p><br></p>', '<p><br></p>', 2, NULL, 'variable', 0.00, 0, 2.00, '1766387051_0.webp,1766387051_1.webp', '2025-12-22 07:04:11', 'test-2', 0.00, NULL),
(3, 'Test-3', '<p><br></p>', '<p>It does not throw a console error unless MIME type is invalid.\r\n\r\nYour screenshot confirms the CSS request was hitting a WordPress 404 HTML page, not your CSS file.</p>', 1, NULL, 'simple', 200.00, 1, NULL, '', '2025-12-29 04:38:24', 'test-3', 250.00, 200.00),
(4, 'Test-4', NULL, 'New Year Special Offer\r\nStart-up package @ 24,999 only\r\n(Logo + Website + 3 Months Marketing)\r\nValid till 31st December only', 1, NULL, 'simple', 80.00, 0, NULL, '1766983367_0.webp,1766983367_1.webp', '2025-12-29 04:42:47', 'test-4', 100.00, 80.00);

-- --------------------------------------------------------

--
-- Table structure for table `product_attributes`
--

CREATE TABLE `product_attributes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `attribute_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_attribute_values`
--

CREATE TABLE `product_attribute_values` (
  `id` int(11) NOT NULL,
  `attribute_id` int(11) DEFAULT NULL,
  `value_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variations`
--

CREATE TABLE `product_variations` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variation_sku` varchar(150) DEFAULT NULL,
  `regular_price` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `price_inr` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT NULL,
  `weight` decimal(6,2) DEFAULT NULL,
  `attributes_json` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variations`
--

INSERT INTO `product_variations` (`id`, `product_id`, `variation_sku`, `regular_price`, `sale_price`, `price_inr`, `stock`, `weight`, `attributes_json`, `created_at`) VALUES
(9, 2, NULL, 15.00, 12.00, 12.00, 1, NULL, 'M', '2026-01-05 10:21:18'),
(10, 2, NULL, 20.00, 18.00, 18.00, 1, NULL, 'L', '2026-01-05 10:21:18');

-- --------------------------------------------------------

--
-- Table structure for table `synonyms`
--

CREATE TABLE `synonyms` (
  `id` int(11) NOT NULL,
  `word` varchar(100) DEFAULT NULL,
  `synonym` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `remember_token` varchar(64) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `phone`, `name`, `email`, `address`, `country`, `otp`, `created_at`, `remember_token`, `remember_expires`) VALUES
(1, '09254064033', 'Sushant Khurana', 'sushant.khurana.sukh@gmail.com', '', NULL, '758750', '2026-01-07 11:15:53', NULL, NULL),
(2, '9254064033', 'Sushant Khurana', 'sushant.khurana.sukh@gmail.com', '143, Sector-7', 'India', NULL, '2026-02-11 08:58:33', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label` varchar(100) DEFAULT 'Home',
  `name` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `is_default` tinyint(4) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_cart_session_product` (`session_id`,`product_id`,`variation_id`),
  ADD UNIQUE KEY `uniq_cart` (`user_id`,`session_id`,`product_id`,`variation_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_payment_id` (`payment_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_log`
--
ALTER TABLE `otp_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_phone_created` (`phone`,`created_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_attribute_values`
--
ALTER TABLE `product_attribute_values`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_variations`
--
ALTER TABLE `product_variations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `synonyms`
--
ALTER TABLE `synonyms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_user_product` (`user_id`,`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currency_rates`
--
ALTER TABLE `currency_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discount_codes`
--
ALTER TABLE `discount_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `otp_log`
--
ALTER TABLE `otp_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `product_attributes`
--
ALTER TABLE `product_attributes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_attribute_values`
--
ALTER TABLE `product_attribute_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_variations`
--
ALTER TABLE `product_variations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `synonyms`
--
ALTER TABLE `synonyms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
