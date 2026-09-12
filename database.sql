-- ========================================================
-- Youshoo E-Commerce Platform - Complete MySQL Database Export
-- Compatible with MySQL 5.7, 8.0+, MariaDB & phpMyAdmin
-- Exported on: 2026-09-12 07:34:22
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `meesho_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `meesho_db`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('customer','supplier','admin') COLLATE utf8mb4_unicode_ci DEFAULT 'customer',
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`
INSERT INTO `users` (`id`, `full_name`, `email`, `phone`, `password_hash`, `role`, `status`, `avatar`, `created_at`, `updated_at`) VALUES
('1', 'Admin User', 'admin@meesho.com', '9876543210', '$2y$10$u0cN7JNFGgSKJ2EVTQWHzezNjItShG/R.oLRzKU80FR0k./bjbcIe', 'admin', 'active', NULL, '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('2', 'Kashvi Textiles', 'supplier@meesho.com', '9820011223', '$2y$10$UBw7mTbGGG25hrU0bClFhue7j1FpKR8FXgyLlPpmHgKElI0HKFPHO', 'supplier', 'active', NULL, '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('3', 'Vaidehi Fashion Hub', 'vaidehi@meesho.com', '9820044556', '$2y$10$UBw7mTbGGG25hrU0bClFhue7j1FpKR8FXgyLlPpmHgKElI0HKFPHO', 'supplier', 'active', NULL, '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('4', 'Urban Kidz Store', 'urbankids@meesho.com', '9820077889', '$2y$10$UBw7mTbGGG25hrU0bClFhue7j1FpKR8FXgyLlPpmHgKElI0HKFPHO', 'supplier', 'active', NULL, '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('5', 'Home Bliss Living', 'homebliss@meesho.com', '9820099001', '$2y$10$UBw7mTbGGG25hrU0bClFhue7j1FpKR8FXgyLlPpmHgKElI0HKFPHO', 'supplier', 'active', NULL, '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('6', 'Priya Sharma', 'customer@meesho.com', '9123456780', '$2y$10$yho0pZLkKHqPCMsDJtC./ORtPWFYsugJBm2YnabGtLHGoSffMh7ES', 'customer', 'active', NULL, '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('7', 'Admin User', 'admin@youshoo.com', '9876543210', '$2y$10$fSh0EHMbu9yf4XpIeK8RlO6UMQP6NJF9eKus8T.WDiNy1tZh89k3O', 'admin', 'active', NULL, '2026-09-12 10:38:07', '2026-09-12 10:38:07'),
('8', 'Kashvi Textiles', 'supplier@youshoo.com', '9820011223', '$2y$10$jpVqTtDBBmNqzT5fWt6s1.LAOYjsGUh.ky0vbeQxIAN2404P7gVe6', 'supplier', 'active', NULL, '2026-09-12 10:38:07', '2026-09-12 10:38:07'),
('9', 'Priya Sharma', 'customer@youshoo.com', '9123456780', '$2y$10$vS0V.V0RFOWp6is96hDJ2OAu.KMSeGeXN2/QRapY89KzYNUkm6ojy', 'customer', 'active', NULL, '2026-09-12 10:38:07', '2026-09-12 10:38:07');

-- --------------------------------------------------------
-- Table structure for table `suppliers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `shop_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gstin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pincode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_account` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_ifsc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT '4.20',
  `followers_count` int DEFAULT '120',
  `verified` tinyint(1) DEFAULT '1',
  `commission_rate` decimal(4,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `suppliers`
INSERT INTO `suppliers` (`id`, `user_id`, `shop_name`, `owner_name`, `gstin`, `phone`, `business_email`, `address`, `city`, `state`, `pincode`, `bank_account`, `bank_ifsc`, `logo`, `banner`, `rating`, `followers_count`, `verified`, `commission_rate`, `created_at`) VALUES
('1', '8', 'Kashvi Sarees & Ethnic', 'Rameshwar Patel', '24ABCDE1234F1Z5', '9820011223', 'supplier@youshoo.com', 'Ring Road Textile Market, Surat', 'Surat', 'Gujarat', '395002', NULL, NULL, NULL, NULL, '4.60', '1420', '1', '0.00', '2026-09-12 10:13:39'),
('2', '3', 'Vaidehi Trends', 'Sunita Agarwal', '27XYZAB9876G2Z1', '9820044556', 'vaidehi@meesho.com', 'Chawri Bazar, Chandni Chowk', 'New Delhi', 'Delhi', '110006', NULL, NULL, NULL, NULL, '4.40', '890', '1', '0.00', '2026-09-12 10:13:39'),
('3', '4', 'Urban Kidz Apparel', 'Amit Verma', '29PQRST5678H3Z8', '9820077889', 'urbankids@meesho.com', 'Chickpet Commercial Area', 'Bengaluru', 'Karnataka', '560053', NULL, NULL, NULL, NULL, '4.50', '610', '1', '0.00', '2026-09-12 10:13:39'),
('4', '5', 'Home Bliss Living', 'Kavita Sengupta', '19LMNOP4321J4Z3', '9820099001', 'homebliss@meesho.com', 'Burrabazar Craft Row', 'Kolkata', 'West Bengal', '700007', NULL, NULL, NULL, NULL, '4.30', '430', '1', '0.00', '2026-09-12 10:13:39');

-- --------------------------------------------------------
-- Table structure for table `categories`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `categories`
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `image`, `parent_id`, `sort_order`, `created_at`) VALUES
('1', 'Women Ethnic', 'women-ethnic', 'fa-female', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=500&auto=format&fit=crop&q=80', NULL, '1', '2026-09-12 10:13:39'),
('2', 'Women Western', 'women-western', 'fa-tshirt', 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=500&auto=format&fit=crop&q=80', NULL, '2', '2026-09-12 10:13:39'),
('3', 'Men', 'men', 'fa-male', 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=500&auto=format&fit=crop&q=80', NULL, '3', '2026-09-12 10:13:39'),
('4', 'Kids', 'kids', 'fa-child', 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=500&auto=format&fit=crop&q=80', NULL, '4', '2026-09-12 10:13:39'),
('5', 'Home & Kitchen', 'home-kitchen', 'fa-home', 'https://images.unsplash.com/photo-1584589167171-541ce45f1eea?w=500&auto=format&fit=crop&q=80', NULL, '5', '2026-09-12 10:13:39'),
('6', 'Beauty & Health', 'beauty-health', 'fa-spa', 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=500&auto=format&fit=crop&q=80', NULL, '6', '2026-09-12 10:13:39'),
('7', 'Jewellery & Accessories', 'jewellery-accessories', 'fa-gem', 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=500&auto=format&fit=crop&q=80', NULL, '7', '2026-09-12 10:13:39'),
('8', 'Bags & Footwear', 'bags-footwear', 'fa-shoe-prints', 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=500&auto=format&fit=crop&q=80', NULL, '8', '2026-09-12 10:13:39'),
('9', 'Electronics', 'electronics', 'fa-headphones', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&auto=format&fit=crop&q=80', NULL, '9', '2026-09-12 10:13:39');

-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supplier_id` int NOT NULL,
  `category_id` int NOT NULL,
  `subcategory_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `mrp` decimal(10,2) NOT NULL,
  `discount_percent` int DEFAULT '0',
  `stock` int NOT NULL DEFAULT '50',
  `sku` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sizes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Free Size',
  `colors` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Multi',
  `fabric` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Cotton Blend',
  `free_delivery` tinyint(1) DEFAULT '1',
  `cod_available` tinyint(1) DEFAULT '1',
  `rating_avg` decimal(3,2) DEFAULT '4.20',
  `rating_count` int DEFAULT '150',
  `status` enum('approved','pending','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'approved',
  `is_featured` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `products`
INSERT INTO `products` (`id`, `supplier_id`, `category_id`, `subcategory_id`, `title`, `slug`, `description`, `price`, `mrp`, `discount_percent`, `stock`, `sku`, `sizes`, `colors`, `fabric`, `free_delivery`, `cod_available`, `rating_avg`, `rating_count`, `status`, `is_featured`, `created_at`, `updated_at`) VALUES
('1', '1', '1', NULL, 'Kashvi Graceful Banarasi Silk Saree with Rich Zari Pallu', 'kashvi-banarasi-silk-saree-zari-pallu', 'Elegance personified! This gorgeous Banarasi Woven Jacquard Silk Saree comes with an opulent embroidered pallu and matching unstitched blouse piece. Perfect for festive gatherings, weddings, and traditional pujas. Soft drape with luminous sheen.', '499.00', '1899.00', '74', '85', 'KAS-SAR-001', 'Free Size', 'Royal Magenta, Bottle Green, Navy Blue', 'Banarasi Art Silk', '1', '1', '4.60', '1248', 'approved', '1', '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('2', '1', '1', NULL, 'Charvi Sensational Embroidered Georgette Anarkali Kurta Set', 'charvi-sensational-georgette-anarkali-kurta-set', 'Step out in style with this flared Georgette Anarkali Kurta featuring intricate threadwork, sequin highlights, matching santoon pants, and a chiffon lace dupatta.', '599.00', '2199.00', '73', '120', 'KAS-KUR-002', 'M, L, XL, XXL', 'Pink, Peach, Sky Blue', 'Heavy Faux Georgette', '1', '1', '4.40', '850', 'approved', '1', '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('3', '1', '1', NULL, 'Aagyeyi Alluring Cotton Printed Straight Kurti', 'aagyeyi-cotton-printed-straight-kurti', 'Daily wear comfortable pure cotton straight kurti featuring elegant Jaipur block prints, wooden button detailing on placket, and 3/4 sleeves. Breathable and easy to wash.', '249.00', '799.00', '69', '240', 'KAS-KUR-003', 'S, M, L, XL, XXL', 'Indigo Blue, Mustard Yellow, Red', '100% Pure Cotton', '1', '1', '4.20', '2340', 'approved', '0', '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('4', '2', '2', NULL, 'Trendy Floral Print Ruffled Fit & Flare Dress', 'trendy-floral-print-ruffled-dress', 'Turn heads with this charming knee-length western floral dress. Features a sweetheart neckline, puffed sleeves, elasticated smocked waist, and tiered ruffled hem. Soft crepe fabric.', '389.00', '1299.00', '70', '60', 'VAI-WES-001', 'XS, S, M, L, XL', 'Floral Peach, Mint Green, Lavender', 'Georgette Crepe', '1', '1', '4.30', '540', 'approved', '1', '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('5', '2', '2', NULL, 'High-Waist Stretchable Wide Leg Denim Jeans', 'high-waist-stretchable-wide-leg-jeans', 'Premium high rise boyfriend wide leg jeans with super stretch denim comfort. Clean finish waistband, 5 pocket styling, and vintage washed blue tone.', '499.00', '1599.00', '69', '95', 'VAI-JNS-002', '28, 30, 32, 34', 'Light Blue, Dark Indigo, Charcoal Black', 'Denim Cotton Spandex', '1', '1', '4.50', '980', 'approved', '1', '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('6', '2', '2', NULL, 'Ribbed Knit High-Neck Long Sleeve Crop Top', 'ribbed-knit-high-neck-crop-top', 'Ultra chic and cozy ribbed knitted bodycon top. Features turtleneck design, fine gauge ribbed structure, and sleek tailored fit that pairs effortlessly with trousers or skirts.', '229.00', '699.00', '67', '150', 'VAI-TOP-003', 'Free Size, S, M, L', 'Black, White, Wine, Beige', 'Ribbed Lycra Cotton', '1', '1', '4.10', '720', 'approved', '0', '2026-09-12 10:13:39', '2026-09-12 10:13:39'),
('7', '2', '3', NULL, 'Urbano Men Solid Cotton Casual Spread Collar Shirt', 'urbano-men-solid-cotton-casual-shirt', 'Tailored fit premium cotton shirt with curved hemline, spread collar, and single patch pocket. Ideal for smart office wear or casual outings.', '349.00', '1199.00', '71', '110', 'MEN-SHT-001', 'M, L, XL, XXL', 'White, Olive Green, Navy Blue, Maroon', 'Pure Combed Cotton', '1', '1', '4.40', '1650', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('8', '2', '3', NULL, 'Men Pack of 3 Bio-Washed Graphic Round Neck T-Shirts', 'men-pack-of-3-graphic-tshirts', 'Pack of 3 super soft bio-washed 180 GSM cotton crewneck t-shirts. Features stylish typography and fade-proof chest prints. Color retention guaranteed for 50+ washes.', '499.00', '1499.00', '67', '180', 'MEN-TSH-002', 'S, M, L, XL', 'Black + White + Grey Melange', '100% Bio-Washed Cotton', '1', '1', '4.50', '3120', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('9', '3', '4', NULL, 'Cute Baby Boys Dungaree Set with Cotton T-Shirt', 'baby-boys-dungaree-set-cotton-tshirt', 'Delightful 2-piece dungaree and inner tee combo crafted from baby-friendly hypoallergenic cotton. Adjustable shoulder straps, front cartoon patch, and press buttons at crotch for easy diaper changes.', '299.00', '899.00', '67', '75', 'KID-DUN-001', '0-6 Months, 6-12 Months, 1-2 Years, 2-3 Years', 'Navy Blue & Yellow, Red & White', 'Organic Soft Cotton', '1', '1', '4.60', '490', 'approved', '0', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('10', '3', '4', NULL, 'Girls Party Wear Flared Net Princess Frock', 'girls-party-wear-flared-net-frock', 'Turn your little angel into a royal princess! Multi-layered bouncy net skirt, satin ribbon waist bow, embroidered bodice, and breathable cotton inner lining for maximum comfort.', '449.00', '1499.00', '70', '65', 'KID-FRK-002', '2-3 Years, 3-4 Years, 4-5 Years, 6-7 Years', 'Baby Pink, Sky Blue, Crimson Red', 'Soft Net with Cotton Inner', '1', '1', '4.70', '810', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('11', '4', '5', NULL, 'All-Season Glace Cotton Double Bed King Size Bedsheet with 2 Pillow Covers', 'glace-cotton-double-bed-king-size-bedsheet', 'Ultra soft wrinkle-free 210 TC glace cotton double bed sheet (90 x 100 inches) with 2 coordinating pillow covers. Fast color printing, machine washable, and shrinkage proof.', '329.00', '999.00', '67', '140', 'HOM-BED-001', 'King Size (90x100 in)', 'Mandala Blue, Geometric Grey, Floral Teal', 'Glace Microfiber Cotton', '1', '1', '4.30', '1950', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('12', '4', '5', NULL, 'Stainless Steel Heavy Bottom Induction Friendly Cookware Set (3 Pcs)', 'stainless-steel-induction-cookware-set', 'Tri-ply encapsulated heavy base cookware set includes Kadhai with glass lid, sauce pan, and fry pan. Uniform heat distribution, rust-proof food grade SS304 steel.', '799.00', '2499.00', '68', '45', 'HOM-KIT-002', 'Set of 3 (1.5L, 2L, 2.5L)', 'Silver Steel', 'Stainless Steel', '1', '1', '4.50', '670', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('13', '2', '6', NULL, 'Matte Waterproof 12-Hour Long Stay Liquid Lipstick Set (Pack of 4)', 'matte-waterproof-liquid-lipstick-set', 'Velvety smooth matte transfer-proof liquid lipsticks enriched with Vitamin E & Jojoba Oil. Lightweight formula that keeps lips moisturized without cracking. Highly pigmented.', '219.00', '799.00', '73', '210', 'BTY-LIP-001', 'Pack of 4 (Nude & Red Shades)', 'Nude Pink, Deep Berry, Crimson Red, Mocha Brown', 'Liquid Matte Formula', '1', '1', '4.40', '2800', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('14', '1', '7', NULL, 'Gold-Plated Kundan & Pearl Choker Necklace Set with Earrings & Maang Tikka', 'gold-plated-kundan-pearl-choker-set', 'Royal Rajasthani Kundan bridal choker set embellished with lustrous faux pearls and colored gemstones. Includes matching drop jhumka earrings and delicate maang tikka.', '299.00', '1299.00', '77', '115', 'JWL-NEK-001', 'Adjustable Dori', 'Gold & Emerald Green, Gold & Ruby Red', 'Brass & Kundan Stone', '1', '1', '4.60', '3420', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('15', '2', '8', NULL, 'Women Structured Handheld Satchel Bag with Sling Strap', 'women-structured-handheld-satchel-bag', 'Trendy textured PU leather tote handbag featuring dual top handles, detachable crossbody sling strap, metallic gold lock buckle, and spacious multi-compartment interior.', '399.00', '1399.00', '71', '80', 'BAG-SAT-001', 'Standard Handbag', 'Tan Brown, Chic Beige, Jet Black', 'Premium PU Leather', '1', '1', '4.50', '920', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('16', '2', '8', NULL, 'Comfortable Memory Foam Slip-On Walking Shoes for Women', 'memory-foam-slip-on-walking-shoes', 'Feather-light breathable mesh casual walking sneakers with ultra soft cushioned insole and non-skid rubber sole. Perfect for jogging, gym, daily walks, or college.', '379.00', '1199.00', '68', '130', 'SHU-SLP-002', 'IND-4, IND-5, IND-6, IND-7, IND-8', 'Blush Pink, Charcoal Grey, All Black', 'Breathable Mesh & EVA', '1', '1', '4.30', '1430', 'approved', '0', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('17', '2', '9', NULL, 'True Wireless Bluetooth 5.3 Earbuds with 40H Playtime & Deep Bass', 'true-wireless-bluetooth-earbuds-40h-playtime', 'Immerse in high-fidelity sound with 13mm dynamic neodymium drivers, environmental noise cancellation (ENC) for crystal clear calls, IPX5 water resistance, and fast Type-C charging.', '549.00', '1999.00', '73', '160', 'ELC-EAR-001', 'Standard Fit', 'Matte Black, Pearl White', 'Polycarbonate Plastic', '1', '1', '4.40', '4150', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40'),
('18', '2', '9', NULL, 'Smart Fitness Band with 1.4-Inch HD Touch Display & SpO2 Heart Rate Monitor', 'smart-fitness-band-hd-touch-display', 'Track your active lifestyle with 20+ sports modes, 24/7 heart rate and blood oxygen monitoring, sleep analysis, call & message notifications, and 7-day battery life.', '699.00', '2499.00', '72', '90', 'ELC-WTC-002', 'Adjustable Strap', 'Midnight Black, Rose Gold, Navy', 'Silicone & Aluminum Case', '1', '1', '4.50', '2200', 'approved', '1', '2026-09-12 10:13:40', '2026-09-12 10:13:40');

-- --------------------------------------------------------
-- Table structure for table `product_images`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `image_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `product_images`
INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `is_primary`, `sort_order`) VALUES
('1', '1', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80', '1', '0'),
('2', '1', 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=600&auto=format&fit=crop&q=80', '0', '1'),
('3', '1', 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&auto=format&fit=crop&q=80', '0', '2'),
('4', '2', 'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80', '1', '0'),
('5', '2', 'https://images.unsplash.com/photo-1583391733975-08149e3549ce?w=600&auto=format&fit=crop&q=80', '0', '1'),
('6', '3', 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=600&auto=format&fit=crop&q=80', '1', '0'),
('7', '3', 'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80', '0', '1'),
('8', '4', 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&auto=format&fit=crop&q=80', '1', '0'),
('9', '4', 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=600&auto=format&fit=crop&q=80', '0', '1'),
('10', '5', 'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=600&auto=format&fit=crop&q=80', '1', '0'),
('11', '5', 'https://images.unsplash.com/photo-1582418702059-97ebafb35d09?w=600&auto=format&fit=crop&q=80', '0', '1'),
('12', '6', 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=600&auto=format&fit=crop&q=80', '1', '0'),
('13', '6', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80', '0', '1'),
('14', '7', 'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=600&auto=format&fit=crop&q=80', '1', '0'),
('15', '7', 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=600&auto=format&fit=crop&q=80', '0', '1'),
('16', '8', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80', '1', '0'),
('17', '8', 'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=600&auto=format&fit=crop&q=80', '0', '1'),
('18', '9', 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=600&auto=format&fit=crop&q=80', '1', '0'),
('19', '9', 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=600&auto=format&fit=crop&q=80', '0', '1'),
('20', '10', 'https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?w=600&auto=format&fit=crop&q=80', '1', '0'),
('21', '10', 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=600&auto=format&fit=crop&q=80', '0', '1'),
('22', '11', 'https://images.unsplash.com/photo-1584589167171-541ce45f1eea?w=600&auto=format&fit=crop&q=80', '1', '0'),
('23', '11', 'https://images.unsplash.com/photo-1616046229478-9901c5536a45?w=600&auto=format&fit=crop&q=80', '0', '1'),
('24', '12', 'https://images.unsplash.com/photo-1584992236310-6edddc08acff?w=600&auto=format&fit=crop&q=80', '1', '0'),
('25', '12', 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=600&auto=format&fit=crop&q=80', '0', '1'),
('26', '13', 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=600&auto=format&fit=crop&q=80', '1', '0'),
('27', '13', 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&auto=format&fit=crop&q=80', '0', '1'),
('28', '14', 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80', '1', '0'),
('29', '14', 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&auto=format&fit=crop&q=80', '0', '1'),
('30', '15', 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=600&auto=format&fit=crop&q=80', '1', '0'),
('31', '15', 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&auto=format&fit=crop&q=80', '0', '1'),
('32', '16', 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600&auto=format&fit=crop&q=80', '1', '0'),
('33', '16', 'https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=600&auto=format&fit=crop&q=80', '0', '1'),
('34', '17', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80', '1', '0'),
('35', '17', 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&auto=format&fit=crop&q=80', '0', '1'),
('36', '18', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80', '1', '0'),
('37', '18', 'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=600&auto=format&fit=crop&q=80', '0', '1');

-- --------------------------------------------------------
-- Table structure for table `cart`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `session_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` int NOT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Free Size',
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Default',
  `quantity` int DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `cart`
-- (No rows in `cart`)

-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `delivery_fee` decimal(10,2) DEFAULT '0.00',
  `final_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cod','upi','card','netbanking') COLLATE utf8mb4_unicode_ci DEFAULT 'cod',
  `payment_status` enum('pending','paid','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `order_status` enum('placed','confirmed','shipped','out_for_delivery','delivered','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'placed',
  `shipping_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_city` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_state` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shipping_pincode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `orders`
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `discount_amount`, `delivery_fee`, `final_amount`, `payment_method`, `payment_status`, `order_status`, `shipping_name`, `shipping_phone`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_pincode`, `created_at`, `updated_at`) VALUES
('1', 'YSH-5FAEC6CB', '6', '499.00', '0.00', '0.00', '499.00', 'cod', 'pending', 'confirmed', 'Priya Sharma', '9123456780', 'Flat 402, Sai Residency, MG Road', 'Mumbai', 'Maharashtra', '400001', '2026-09-12 10:13:40', '2026-09-12 11:04:22'),
('2', 'YSH-4E55064C', '6', '599.00', '0.00', '0.00', '599.00', 'cod', 'paid', 'delivered', 'Priya Sharma', '9123456780', 'Flat 402, Sai Residency, MG Road', 'Mumbai', 'Maharashtra', '400001', '2026-09-12 10:13:40', '2026-09-12 11:04:22'),
('3', 'YSH-FAA53B8A', NULL, '3798.00', '2800.00', '0.00', '998.00', 'cod', 'pending', 'placed', 'Aditi Sharma', '9876543210', 'Flat 302, Galaxy Heights, Linking Road', 'Mumbai', 'Maharashtra', '400050', '2026-09-12 10:23:14', '2026-09-12 11:04:22');

-- --------------------------------------------------------
-- Table structure for table `order_items`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `supplier_id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_image` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Free Size',
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Default',
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `supplier_status` enum('pending','accepted','shipped','delivered','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `order_items`
INSERT INTO `order_items` (`id`, `order_id`, `supplier_id`, `product_id`, `product_title`, `product_image`, `size`, `color`, `quantity`, `unit_price`, `total_price`, `supplier_status`) VALUES
('1', '1', '1', '1', 'Kashvi Graceful Banarasi Silk Saree with Rich Zari Pallu', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80', 'Free Size', 'Default', '1', '499.00', '499.00', 'accepted'),
('2', '2', '1', '2', 'Charvi Sensational Embroidered Georgette Anarkali Kurta Set', 'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80', 'M', 'Default', '1', '599.00', '599.00', 'delivered'),
('3', '3', '1', '1', 'Kashvi Graceful Banarasi Silk Saree with Rich Zari Pallu', 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80', 'Free Size', 'Default', '2', '499.00', '998.00', 'pending');

-- --------------------------------------------------------
-- Table structure for table `reviews`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rating` int NOT NULL,
  `review_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `helpful_count` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `reviews`
INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `user_name`, `rating`, `review_text`, `review_image`, `helpful_count`, `created_at`) VALUES
('1', '1', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:39'),
('2', '1', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:39'),
('3', '1', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:39'),
('4', '2', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:39'),
('5', '2', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:39'),
('6', '2', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:39'),
('7', '3', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:39'),
('8', '3', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:39'),
('9', '3', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:39'),
('10', '4', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:39'),
('11', '4', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:39'),
('12', '4', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:39'),
('13', '5', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:39'),
('14', '5', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:39'),
('15', '5', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:39'),
('16', '6', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('17', '6', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('18', '6', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('19', '7', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('20', '7', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('21', '7', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('22', '8', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('23', '8', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('24', '8', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('25', '9', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('26', '9', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('27', '9', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('28', '10', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('29', '10', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('30', '10', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('31', '11', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('32', '11', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('33', '11', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('34', '12', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('35', '12', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('36', '12', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('37', '13', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('38', '13', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('39', '13', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('40', '14', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('41', '14', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('42', '14', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('43', '15', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('44', '15', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('45', '15', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('46', '16', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('47', '16', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('48', '16', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('49', '17', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('50', '17', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40');
INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `user_name`, `rating`, `review_text`, `review_image`, `helpful_count`, `created_at`) VALUES
('51', '17', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40'),
('52', '18', '6', 'Ananya K.', '5', 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', NULL, '34', '2026-09-12 10:13:40'),
('53', '18', '6', 'Deepak M.', '4', 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', NULL, '19', '2026-09-12 10:13:40'),
('54', '18', '6', 'Komal R.', '5', 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', NULL, '42', '2026-09-12 10:13:40');

-- --------------------------------------------------------
-- Table structure for table `banners`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '#',
  `button_text` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Shop Now',
  `active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `banners`
INSERT INTO `banners` (`id`, `title`, `subtitle`, `badge`, `image_url`, `link_url`, `button_text`, `active`, `sort_order`, `created_at`) VALUES
('1', 'Mega Blockbuster Sale', 'Unbeatable Prices on 50 Lakh+ Products & Lowest Prices Every Day', 'FLAT 70% OFF', 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1200&auto=format&fit=crop&q=80', 'index.php?category=women-ethnic', 'Shop Ethnic Wear', '1', '1', '2026-09-12 10:13:39'),
('2', 'Top Trends in Western Wear', 'Starting at just ₹199 | Free Delivery & Cash on Delivery Available', 'TRENDING NOW', 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=1200&auto=format&fit=crop&q=80', 'index.php?category=women-western', 'Explore Western', '1', '2', '2026-09-12 10:13:39'),
('3', 'Home & Kitchen Fest', 'Upgrade your space with modern essentials, cookware & stylish decor', 'STARTING ₹149', 'https://images.unsplash.com/photo-1616046229478-9901c5536a45?w=1200&auto=format&fit=crop&q=80', 'index.php?category=home-kitchen', 'View Home Decor', '1', '3', '2026-09-12 10:13:39');

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
