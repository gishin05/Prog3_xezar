-- =====================================================
-- Store Management System Database Schema
-- Consolidated from: items_db, customer_orders_table, 
--                    purchase_orders_table, product_issues_table
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =====================================================
-- Create Database
-- =====================================================
CREATE DATABASE IF NOT EXISTS `store_db`;
USE `store_db`;

-- =====================================================
-- Table: users
-- Description: User accounts for system login and roles
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('employee','manager') NOT NULL DEFAULT 'employee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Table: items
-- Description: Inventory products and stock information
-- =====================================================
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100),
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `isles` varchar(100) NOT NULL,
  `shelf_position` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- Table: purchase_orders
-- Description: Incoming orders from suppliers to restock inventory
-- =====================================================
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `supplier_name` varchar(255) NOT NULL,
  `item_id` int(11) NOT NULL,
  `order_quantity` int(11) NOT NULL,
  `received_quantity` int(11) DEFAULT NULL,
  `status` enum('ordered','confirmed','processing','in_transit','delayed','received','partially_received','cancelled') NOT NULL DEFAULT 'ordered',
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_by` int(11) DEFAULT NULL,
  `received_date` timestamp NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
  FOREIGN KEY (`received_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_po_status ON purchase_orders(status);
CREATE INDEX idx_po_item ON purchase_orders(item_id);
CREATE INDEX idx_po_created ON purchase_orders(created_at);

-- =====================================================
-- Table: customer_orders
-- Description: Outgoing orders to customers (sales)
-- =====================================================
DROP TABLE IF EXISTS `customer_orders`;
CREATE TABLE `customer_orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `customer_name` varchar(255) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('placed','confirmed','picking','packed','shipped','delivered','cancelled') NOT NULL DEFAULT 'placed',
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `shipped_by` int(11) DEFAULT NULL,
  `shipped_date` timestamp NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
  FOREIGN KEY (`shipped_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_co_status ON customer_orders(status);
CREATE INDEX idx_co_item ON customer_orders(item_id);
CREATE INDEX idx_co_created ON customer_orders(created_at);

-- =====================================================
-- Table: product_issues
-- Description: Issue reports for received products (damaged, qty mismatch, defective, etc.)
-- =====================================================
DROP TABLE IF EXISTS `product_issues`;
CREATE TABLE `product_issues` (
  `issue_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `issue_type` enum('damaged','quantity_mismatch','defective','other') NOT NULL,
  `description` text,
  `quantity_affected` int(11),
  `reported_by` int(11) NOT NULL,
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved` boolean DEFAULT FALSE,
  `resolution_notes` text,
  `resolved_at` timestamp NULL,
  `resolved_by` int(11),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `items`(`item_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reported_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE INDEX idx_pi_order ON product_issues(purchase_order_id);
CREATE INDEX idx_pi_item ON product_issues(item_id);
CREATE INDEX idx_pi_resolved ON product_issues(resolved);
CREATE INDEX idx_pi_reported ON product_issues(reported_at);

-- =====================================================
-- Sample Data
-- =====================================================

-- Sample Users
INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `role`) VALUES
(1, 'manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Store Manager', 'manager'),
(2, 'employee1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Employee', 'employee'),
(3, 'employee2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Employee', 'employee');

-- Sample Items
INSERT INTO `items` (`item_id`, `item_name`, `category`, `price`, `quantity`, `isles`, `shelf_position`) VALUES
(22, 'Apple', 'Fruits', 1.50, 50, 'Aisle-8', 'Shelf-A-6'),
(23, 'Milk', 'Dairy', 1500.00, 5, 'Aisle-9', 'Shelf-D-7');

-- =====================================================
-- Set AUTO_INCREMENT values
-- =====================================================
ALTER TABLE `users` AUTO_INCREMENT = 11;
ALTER TABLE `items` AUTO_INCREMENT = 24;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
