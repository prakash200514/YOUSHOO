<?php
/**
 * Meesho E-Commerce Platform - Database Setup & Seeder Script
 * Run this script via browser (http://localhost/MEESHO/config/setup_database.php) or CLI to initialize the database.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Parse database credentials from environment variables or local defaults
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if ($dbUrl) {
    $urlParts = parse_url($dbUrl);
    $host = $urlParts['host'] ?? '127.0.0.1';
    $port = $urlParts['port'] ?? 3306;
    $user = $urlParts['user'] ?? 'root';
    $pass = $urlParts['pass'] ?? '';
    $dbname = ltrim($urlParts['path'] ?? 'youshoo_db', '/');
} else {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'youshoo_db';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'password';
}

echo "<pre style='font-family: Consolas, monospace; background: #1a1a2e; color: #e94560; padding: 20px; border-radius: 8px;'>";
echo "====================================================\n";
echo "  MEESHO E-COMMERCE PLATFORM - DATABASE INITIALIZER \n";
echo "====================================================\n\n";

try {
    // 1. Connect directly to database (works for cloud DBs and existing DBs)
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "[OK] Connected to database `$dbname` on `$host`.\n\n";
    } catch (PDOException $e) {
        // Fallback: connect to server and create database (works on localhost)
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbname`");
        echo "[OK] Created and switched to database `$dbname` on `$host`.\n\n";
    }

    // 3. Create Tables
    $queries = [
        "users" => "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(120) NOT NULL UNIQUE,
            `phone` VARCHAR(20) NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('customer', 'supplier', 'admin') DEFAULT 'customer',
            `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            `avatar` VARCHAR(255) NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "suppliers" => "CREATE TABLE IF NOT EXISTS `suppliers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL UNIQUE,
            `shop_name` VARCHAR(150) NOT NULL,
            `owner_name` VARCHAR(100) NOT NULL,
            `gstin` VARCHAR(30) NULL,
            `phone` VARCHAR(20) NOT NULL,
            `business_email` VARCHAR(120) NULL,
            `address` TEXT NOT NULL,
            `city` VARCHAR(80) NOT NULL,
            `state` VARCHAR(80) NOT NULL,
            `pincode` VARCHAR(10) NOT NULL,
            `bank_account` VARCHAR(40) NULL,
            `bank_ifsc` VARCHAR(20) NULL,
            `logo` VARCHAR(255) NULL,
            `banner` VARCHAR(255) NULL,
            `rating` DECIMAL(3,2) DEFAULT 4.20,
            `followers_count` INT DEFAULT 120,
            `verified` TINYINT(1) DEFAULT 1,
            `commission_rate` DECIMAL(4,2) DEFAULT 0.00,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "categories" => "CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `icon` VARCHAR(100) NULL,
            `image` VARCHAR(255) NULL,
            `parent_id` INT NULL DEFAULT NULL,
            `sort_order` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "products" => "CREATE TABLE IF NOT EXISTS `products` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `supplier_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            `subcategory_id` INT NULL,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `mrp` DECIMAL(10,2) NOT NULL,
            `discount_percent` INT DEFAULT 0,
            `stock` INT NOT NULL DEFAULT 50,
            `sku` VARCHAR(50) NULL,
            `sizes` VARCHAR(255) DEFAULT 'Free Size',
            `size_prices` TEXT NULL,
            `colors` VARCHAR(255) DEFAULT 'Multi',
            `fabric` VARCHAR(100) DEFAULT 'Cotton Blend',
            `free_delivery` TINYINT(1) DEFAULT 1,
            `cod_available` TINYINT(1) DEFAULT 1,
            `rating_avg` DECIMAL(3,2) DEFAULT 4.20,
            `rating_count` INT DEFAULT 150,
            `status` ENUM('approved', 'pending', 'rejected') DEFAULT 'approved',
            `is_featured` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "product_images" => "CREATE TABLE IF NOT EXISTS `product_images` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `image_url` TEXT NOT NULL,
            `is_primary` TINYINT(1) DEFAULT 0,
            `sort_order` INT DEFAULT 0,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "cart" => "CREATE TABLE IF NOT EXISTS `cart` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `session_id` VARCHAR(100) NOT NULL,
            `product_id` INT NOT NULL,
            `size` VARCHAR(50) DEFAULT 'Free Size',
            `color` VARCHAR(50) DEFAULT 'Default',
            `quantity` INT DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "orders" => "CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(50) NOT NULL UNIQUE,
            `user_id` INT NULL,
            `total_amount` DECIMAL(10,2) NOT NULL,
            `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
            `delivery_fee` DECIMAL(10,2) DEFAULT 0.00,
            `final_amount` DECIMAL(10,2) NOT NULL,
            `payment_method` ENUM('cod', 'upi', 'card', 'netbanking') DEFAULT 'cod',
            `payment_status` ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
            `order_status` ENUM('placed', 'confirmed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'placed',
            `shipping_name` VARCHAR(100) NOT NULL,
            `shipping_phone` VARCHAR(20) NOT NULL,
            `shipping_address` TEXT NOT NULL,
            `shipping_city` VARCHAR(80) NOT NULL,
            `shipping_state` VARCHAR(80) NOT NULL,
            `shipping_pincode` VARCHAR(10) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "order_items" => "CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `supplier_id` INT NOT NULL,
            `product_id` INT NOT NULL,
            `product_title` VARCHAR(255) NOT NULL,
            `product_image` TEXT NOT NULL,
            `size` VARCHAR(50) DEFAULT 'Free Size',
            `color` VARCHAR(50) DEFAULT 'Default',
            `quantity` INT NOT NULL DEFAULT 1,
            `unit_price` DECIMAL(10,2) NOT NULL,
            `total_price` DECIMAL(10,2) NOT NULL,
            `supplier_status` ENUM('pending', 'accepted', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "reviews" => "CREATE TABLE IF NOT EXISTS `reviews` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT NOT NULL,
            `user_id` INT NULL,
            `user_name` VARCHAR(100) NOT NULL,
            `rating` INT NOT NULL,
            `review_text` TEXT NOT NULL,
            `review_image` VARCHAR(255) NULL,
            `helpful_count` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "banners" => "CREATE TABLE IF NOT EXISTS `banners` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `subtitle` VARCHAR(255) NULL,
            `badge` VARCHAR(50) NULL,
            `image_url` TEXT NOT NULL,
            `link_url` VARCHAR(255) DEFAULT '#',
            `button_text` VARCHAR(50) DEFAULT 'Shop Now',
            `active` TINYINT(1) DEFAULT 1,
            `sort_order` INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "whatsapp_logs" => "CREATE TABLE IF NOT EXISTS `whatsapp_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT NOT NULL,
            `supplier_id` INT NOT NULL,
            `order_number` VARCHAR(50) NOT NULL,
            `recipient_phone` VARCHAR(25) NOT NULL,
            `recipient_name` VARCHAR(100) NOT NULL,
            `message_text` TEXT NOT NULL,
            `status` ENUM('sent', 'queued', 'failed', 'direct_link') DEFAULT 'direct_link',
            `api_response` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (`order_id`),
            INDEX (`supplier_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

        "whatsapp_settings" => "CREATE TABLE IF NOT EXISTS `whatsapp_settings` (
            `setting_key` VARCHAR(50) PRIMARY KEY,
            `setting_value` TEXT NULL,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    ];

    foreach ($queries as $tableName => $sql) {
        $pdo->exec($sql);
        echo "[OK] Table `$tableName` created/verified.\n";
    }

    echo "\n----------------------------------------------------\n";
    echo "  SEEDING DEMO DATA & REALISTIC CATALOGUE \n";
    echo "----------------------------------------------------\n\n";

    // 4. Seed Users (Admin, Suppliers, Customer)
    $passwordAdmin = password_hash('admin123', PASSWORD_DEFAULT);
    $passwordSeller = password_hash('seller123', PASSWORD_DEFAULT);
    $passwordUser = password_hash('user123', PASSWORD_DEFAULT);

    $usersData = [
        ['Admin User', 'admin@youshoo.com', '9876543210', $passwordAdmin, 'admin'],
        ['Kashvi Textiles', 'supplier@youshoo.com', '9820011223', $passwordSeller, 'supplier'],
        ['Vaidehi Fashion Hub', 'vaidehi@youshoo.com', '9820044556', $passwordSeller, 'supplier'],
        ['Urban Kidz Store', 'urbankids@youshoo.com', '9820077889', $passwordSeller, 'supplier'],
        ['Home Bliss Living', 'homebliss@youshoo.com', '9820099001', $passwordSeller, 'supplier'],
        ['Priya Sharma', 'customer@youshoo.com', '9123456780', $passwordUser, 'customer']
    ];

    $stmtUserCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmtUserInsert = $pdo->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'active')");

    $userIds = [];
    foreach ($usersData as $u) {
        $stmtUserCheck->execute([$u[1]]);
        $existing = $stmtUserCheck->fetch();
        if (!$existing) {
            $stmtUserInsert->execute([$u[0], $u[1], $u[2], $u[3], $u[4]]);
            $userIds[$u[1]] = $pdo->lastInsertId();
            echo "[+] Created User: {$u[0]} ({$u[1]}) as {$u[4]}\n";
        } else {
            $userIds[$u[1]] = $existing['id'];
            echo "[*] User {$u[1]} already exists (ID: {$existing['id']})\n";
        }
    }

    // 5. Seed Suppliers
    $suppliersData = [
        [
            'user_id' => $userIds['supplier@youshoo.com'],
            'shop_name' => 'Kashvi Sarees & Ethnic',
            'owner_name' => 'Rameshwar Patel',
            'gstin' => '24ABCDE1234F1Z5',
            'phone' => '9820011223',
            'business_email' => 'supplier@youshoo.com',
            'address' => 'Ring Road Textile Market, Surat',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'pincode' => '395002',
            'rating' => 4.6,
            'followers_count' => 1420
        ],
        [
            'user_id' => $userIds['vaidehi@youshoo.com'],
            'shop_name' => 'Vaidehi Trends',
            'owner_name' => 'Sunita Agarwal',
            'gstin' => '27XYZAB9876G2Z1',
            'phone' => '9820044556',
            'business_email' => 'vaidehi@youshoo.com',
            'address' => 'Chawri Bazar, Chandni Chowk',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'pincode' => '110006',
            'rating' => 4.4,
            'followers_count' => 890
        ],
        [
            'user_id' => $userIds['urbankids@youshoo.com'],
            'shop_name' => 'Urban Kidz Apparel',
            'owner_name' => 'Amit Verma',
            'gstin' => '29PQRST5678H3Z8',
            'phone' => '9820077889',
            'business_email' => 'urbankids@youshoo.com',
            'address' => 'Chickpet Commercial Area',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560053',
            'rating' => 4.5,
            'followers_count' => 610
        ],
        [
            'user_id' => $userIds['homebliss@youshoo.com'],
            'shop_name' => 'Home Bliss Living',
            'owner_name' => 'Kavita Sengupta',
            'gstin' => '19LMNOP4321J4Z3',
            'phone' => '9820099001',
            'business_email' => 'homebliss@youshoo.com',
            'address' => 'Burrabazar Craft Row',
            'city' => 'Kolkata',
            'state' => 'West Bengal',
            'pincode' => '700007',
            'rating' => 4.3,
            'followers_count' => 430
        ]
    ];

    $stmtSupplierCheck = $pdo->prepare("SELECT id FROM suppliers WHERE user_id = ?");
    $stmtSupplierInsert = $pdo->prepare("INSERT INTO suppliers (user_id, shop_name, owner_name, gstin, phone, business_email, address, city, state, pincode, rating, followers_count, verified, commission_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0.00)");

    $supplierIds = [];
    foreach ($suppliersData as $s) {
        $stmtSupplierCheck->execute([$s['user_id']]);
        $existing = $stmtSupplierCheck->fetch();
        if (!$existing) {
            $stmtSupplierInsert->execute([
                $s['user_id'], $s['shop_name'], $s['owner_name'], $s['gstin'],
                $s['phone'], $s['business_email'], $s['address'], $s['city'],
                $s['state'], $s['pincode'], $s['rating'], $s['followers_count']
            ]);
            $supplierIds[$s['shop_name']] = $pdo->lastInsertId();
            echo "[+] Created Supplier: {$s['shop_name']}\n";
        } else {
            $supplierIds[$s['shop_name']] = $existing['id'];
            echo "[*] Supplier {$s['shop_name']} already exists\n";
        }
    }

    // 6. Seed Categories
    $categories = [
        ['name' => 'Women Ethnic', 'slug' => 'women-ethnic', 'icon' => 'fa-female', 'image' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Women Western', 'slug' => 'women-western', 'icon' => 'fa-tshirt', 'image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Men', 'slug' => 'men', 'icon' => 'fa-male', 'image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Kids', 'slug' => 'kids', 'icon' => 'fa-child', 'image' => 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'icon' => 'fa-home', 'image' => 'https://images.unsplash.com/photo-1584589167171-541ce45f1eea?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Beauty & Health', 'slug' => 'beauty-health', 'icon' => 'fa-spa', 'image' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Jewellery & Accessories', 'slug' => 'jewellery-accessories', 'icon' => 'fa-gem', 'image' => 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Bags & Footwear', 'slug' => 'bags-footwear', 'icon' => 'fa-shoe-prints', 'image' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=500&auto=format&fit=crop&q=80'],
        ['name' => 'Electronics', 'slug' => 'electronics', 'icon' => 'fa-headphones', 'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&auto=format&fit=crop&q=80']
    ];

    $stmtCatCheck = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $stmtCatInsert = $pdo->prepare("INSERT INTO categories (name, slug, icon, image, sort_order) VALUES (?, ?, ?, ?, ?)");

    $catIds = [];
    $order = 1;
    foreach ($categories as $cat) {
        $stmtCatCheck->execute([$cat['slug']]);
        $existing = $stmtCatCheck->fetch();
        if (!$existing) {
            $stmtCatInsert->execute([$cat['name'], $cat['slug'], $cat['icon'], $cat['image'], $order++]);
            $catIds[$cat['slug']] = $pdo->lastInsertId();
            echo "[+] Created Category: {$cat['name']}\n";
        } else {
            $catIds[$cat['slug']] = $existing['id'];
        }
    }

    // 7. Seed Banners (Meesho Promo Carousels)
    $bannersData = [
        [
            'title' => 'Mega Blockbuster Sale',
            'subtitle' => 'Unbeatable Prices on 50 Lakh+ Products & Lowest Prices Every Day',
            'badge' => 'FLAT 70% OFF',
            'image_url' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=1200&auto=format&fit=crop&q=80',
            'link_url' => 'index.php?category=women-ethnic',
            'button_text' => 'Shop Ethnic Wear'
        ],
        [
            'title' => 'Top Trends in Western Wear',
            'subtitle' => 'Starting at just ₹199 | Free Delivery & Cash on Delivery Available',
            'badge' => 'TRENDING NOW',
            'image_url' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?w=1200&auto=format&fit=crop&q=80',
            'link_url' => 'index.php?category=women-western',
            'button_text' => 'Explore Western'
        ],
        [
            'title' => 'Home & Kitchen Fest',
            'subtitle' => 'Upgrade your space with modern essentials, cookware & stylish decor',
            'badge' => 'STARTING ₹149',
            'image_url' => 'https://images.unsplash.com/photo-1616046229478-9901c5536a45?w=1200&auto=format&fit=crop&q=80',
            'link_url' => 'index.php?category=home-kitchen',
            'button_text' => 'View Home Decor'
        ]
    ];

    $pdo->exec("DELETE FROM banners");
    $stmtBanner = $pdo->prepare("INSERT INTO banners (title, subtitle, badge, image_url, link_url, button_text, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)");
    $bOrder = 1;
    foreach ($bannersData as $b) {
        $stmtBanner->execute([$b['title'], $b['subtitle'], $b['badge'], $b['image_url'], $b['link_url'], $b['button_text'], $bOrder++]);
    }
    echo "[OK] Populated Homepage Banners.\n";

    // 8. Seed Rich Meesho Products
    $products = [
        // Women Ethnic
        [
            'supplier_id' => $supplierIds['Kashvi Sarees & Ethnic'],
            'category_id' => $catIds['women-ethnic'],
            'title' => 'Kashvi Graceful Banarasi Silk Saree with Rich Zari Pallu',
            'slug' => 'kashvi-banarasi-silk-saree-zari-pallu',
            'description' => 'Elegance personified! This gorgeous Banarasi Woven Jacquard Silk Saree comes with an opulent embroidered pallu and matching unstitched blouse piece. Perfect for festive gatherings, weddings, and traditional pujas. Soft drape with luminous sheen.',
            'price' => 499.00,
            'mrp' => 1899.00,
            'stock' => 85,
            'sku' => 'KAS-SAR-001',
            'sizes' => 'Free Size',
            'colors' => 'Royal Magenta, Bottle Green, Navy Blue',
            'fabric' => 'Banarasi Art Silk',
            'rating_avg' => 4.6,
            'rating_count' => 1248,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Kashvi Sarees & Ethnic'],
            'category_id' => $catIds['women-ethnic'],
            'title' => 'Charvi Sensational Embroidered Georgette Anarkali Kurta Set',
            'slug' => 'charvi-sensational-georgette-anarkali-kurta-set',
            'description' => 'Step out in style with this flared Georgette Anarkali Kurta featuring intricate threadwork, sequin highlights, matching santoon pants, and a chiffon lace dupatta.',
            'price' => 599.00,
            'mrp' => 2199.00,
            'stock' => 120,
            'sku' => 'KAS-KUR-002',
            'sizes' => 'M, L, XL, XXL',
            'colors' => 'Pink, Peach, Sky Blue',
            'fabric' => 'Heavy Faux Georgette',
            'rating_avg' => 4.4,
            'rating_count' => 850,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1583391733975-08149e3549ce?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Kashvi Sarees & Ethnic'],
            'category_id' => $catIds['women-ethnic'],
            'title' => 'Aagyeyi Alluring Cotton Printed Straight Kurti',
            'slug' => 'aagyeyi-cotton-printed-straight-kurti',
            'description' => 'Daily wear comfortable pure cotton straight kurti featuring elegant Jaipur block prints, wooden button detailing on placket, and 3/4 sleeves. Breathable and easy to wash.',
            'price' => 249.00,
            'mrp' => 799.00,
            'stock' => 240,
            'sku' => 'KAS-KUR-003',
            'sizes' => 'S, M, L, XL, XXL',
            'colors' => 'Indigo Blue, Mustard Yellow, Red',
            'fabric' => '100% Pure Cotton',
            'rating_avg' => 4.2,
            'rating_count' => 2340,
            'is_featured' => 0,
            'images' => [
                'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Women Western
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['women-western'],
            'title' => 'Trendy Floral Print Ruffled Fit & Flare Dress',
            'slug' => 'trendy-floral-print-ruffled-dress',
            'description' => 'Turn heads with this charming knee-length western floral dress. Features a sweetheart neckline, puffed sleeves, elasticated smocked waist, and tiered ruffled hem. Soft crepe fabric.',
            'price' => 389.00,
            'mrp' => 1299.00,
            'stock' => 60,
            'sku' => 'VAI-WES-001',
            'sizes' => 'XS, S, M, L, XL',
            'colors' => 'Floral Peach, Mint Green, Lavender',
            'fabric' => 'Georgette Crepe',
            'rating_avg' => 4.3,
            'rating_count' => 540,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1496747611176-843222e1e57c?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['women-western'],
            'title' => 'High-Waist Stretchable Wide Leg Denim Jeans',
            'slug' => 'high-waist-stretchable-wide-leg-jeans',
            'description' => 'Premium high rise boyfriend wide leg jeans with super stretch denim comfort. Clean finish waistband, 5 pocket styling, and vintage washed blue tone.',
            'price' => 499.00,
            'mrp' => 1599.00,
            'stock' => 95,
            'sku' => 'VAI-JNS-002',
            'sizes' => '28, 30, 32, 34',
            'colors' => 'Light Blue, Dark Indigo, Charcoal Black',
            'fabric' => 'Denim Cotton Spandex',
            'rating_avg' => 4.5,
            'rating_count' => 980,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1582418702059-97ebafb35d09?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['women-western'],
            'title' => 'Ribbed Knit High-Neck Long Sleeve Crop Top',
            'slug' => 'ribbed-knit-high-neck-crop-top',
            'description' => 'Ultra chic and cozy ribbed knitted bodycon top. Features turtleneck design, fine gauge ribbed structure, and sleek tailored fit that pairs effortlessly with trousers or skirts.',
            'price' => 229.00,
            'mrp' => 699.00,
            'stock' => 150,
            'sku' => 'VAI-TOP-003',
            'sizes' => 'Free Size, S, M, L',
            'colors' => 'Black, White, Wine, Beige',
            'fabric' => 'Ribbed Lycra Cotton',
            'rating_avg' => 4.1,
            'rating_count' => 720,
            'is_featured' => 0,
            'images' => [
                'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Men
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['men'],
            'title' => 'Urbano Men Solid Cotton Casual Spread Collar Shirt',
            'slug' => 'urbano-men-solid-cotton-casual-shirt',
            'description' => 'Tailored fit premium cotton shirt with curved hemline, spread collar, and single patch pocket. Ideal for smart office wear or casual outings.',
            'price' => 349.00,
            'mrp' => 1199.00,
            'stock' => 110,
            'sku' => 'MEN-SHT-001',
            'sizes' => 'M, L, XL, XXL',
            'colors' => 'White, Olive Green, Navy Blue, Maroon',
            'fabric' => 'Pure Combed Cotton',
            'rating_avg' => 4.4,
            'rating_count' => 1650,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['men'],
            'title' => 'Men Pack of 3 Bio-Washed Graphic Round Neck T-Shirts',
            'slug' => 'men-pack-of-3-graphic-tshirts',
            'description' => 'Pack of 3 super soft bio-washed 180 GSM cotton crewneck t-shirts. Features stylish typography and fade-proof chest prints. Color retention guaranteed for 50+ washes.',
            'price' => 499.00,
            'mrp' => 1499.00,
            'stock' => 180,
            'sku' => 'MEN-TSH-002',
            'sizes' => 'S, M, L, XL',
            'colors' => 'Black + White + Grey Melange',
            'fabric' => '100% Bio-Washed Cotton',
            'rating_avg' => 4.5,
            'rating_count' => 3120,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1503342217505-b0a15ec3261c?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Kids
        [
            'supplier_id' => $supplierIds['Urban Kidz Apparel'],
            'category_id' => $catIds['kids'],
            'title' => 'Cute Baby Boys Dungaree Set with Cotton T-Shirt',
            'slug' => 'baby-boys-dungaree-set-cotton-tshirt',
            'description' => 'Delightful 2-piece dungaree and inner tee combo crafted from baby-friendly hypoallergenic cotton. Adjustable shoulder straps, front cartoon patch, and press buttons at crotch for easy diaper changes.',
            'price' => 299.00,
            'mrp' => 899.00,
            'stock' => 75,
            'sku' => 'KID-DUN-001',
            'sizes' => '0-6 Months, 6-12 Months, 1-2 Years, 2-3 Years',
            'colors' => 'Navy Blue & Yellow, Red & White',
            'fabric' => 'Organic Soft Cotton',
            'rating_avg' => 4.6,
            'rating_count' => 490,
            'is_featured' => 0,
            'images' => [
                'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Urban Kidz Apparel'],
            'category_id' => $catIds['kids'],
            'title' => 'Girls Party Wear Flared Net Princess Frock',
            'slug' => 'girls-party-wear-flared-net-frock',
            'description' => 'Turn your little angel into a royal princess! Multi-layered bouncy net skirt, satin ribbon waist bow, embroidered bodice, and breathable cotton inner lining for maximum comfort.',
            'price' => 449.00,
            'mrp' => 1499.00,
            'stock' => 65,
            'sku' => 'KID-FRK-002',
            'sizes' => '2-3 Years, 3-4 Years, 4-5 Years, 6-7 Years',
            'colors' => 'Baby Pink, Sky Blue, Crimson Red',
            'fabric' => 'Soft Net with Cotton Inner',
            'rating_avg' => 4.7,
            'rating_count' => 810,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Home & Kitchen
        [
            'supplier_id' => $supplierIds['Home Bliss Living'],
            'category_id' => $catIds['home-kitchen'],
            'title' => 'All-Season Glace Cotton Double Bed King Size Bedsheet with 2 Pillow Covers',
            'slug' => 'glace-cotton-double-bed-king-size-bedsheet',
            'description' => 'Ultra soft wrinkle-free 210 TC glace cotton double bed sheet (90 x 100 inches) with 2 coordinating pillow covers. Fast color printing, machine washable, and shrinkage proof.',
            'price' => 329.00,
            'mrp' => 999.00,
            'stock' => 140,
            'sku' => 'HOM-BED-001',
            'sizes' => 'King Size (90x100 in)',
            'colors' => 'Mandala Blue, Geometric Grey, Floral Teal',
            'fabric' => 'Glace Microfiber Cotton',
            'rating_avg' => 4.3,
            'rating_count' => 1950,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1584589167171-541ce45f1eea?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1616046229478-9901c5536a45?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Home Bliss Living'],
            'category_id' => $catIds['home-kitchen'],
            'title' => 'Stainless Steel Heavy Bottom Induction Friendly Cookware Set (3 Pcs)',
            'slug' => 'stainless-steel-induction-cookware-set',
            'description' => 'Tri-ply encapsulated heavy base cookware set includes Kadhai with glass lid, sauce pan, and fry pan. Uniform heat distribution, rust-proof food grade SS304 steel.',
            'price' => 799.00,
            'mrp' => 2499.00,
            'stock' => 45,
            'sku' => 'HOM-KIT-002',
            'sizes' => 'Set of 3 (1.5L, 2L, 2.5L)',
            'colors' => 'Silver Steel',
            'fabric' => 'Stainless Steel',
            'rating_avg' => 4.5,
            'rating_count' => 670,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1584992236310-6edddc08acff?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Beauty & Health
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['beauty-health'],
            'title' => 'Matte Waterproof 12-Hour Long Stay Liquid Lipstick Set (Pack of 4)',
            'slug' => 'matte-waterproof-liquid-lipstick-set',
            'description' => 'Velvety smooth matte transfer-proof liquid lipsticks enriched with Vitamin E & Jojoba Oil. Lightweight formula that keeps lips moisturized without cracking. Highly pigmented.',
            'price' => 219.00,
            'mrp' => 799.00,
            'stock' => 210,
            'sku' => 'BTY-LIP-001',
            'sizes' => 'Pack of 4 (Nude & Red Shades)',
            'colors' => 'Nude Pink, Deep Berry, Crimson Red, Mocha Brown',
            'fabric' => 'Liquid Matte Formula',
            'rating_avg' => 4.4,
            'rating_count' => 2800,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Jewellery & Accessories
        [
            'supplier_id' => $supplierIds['Kashvi Sarees & Ethnic'],
            'category_id' => $catIds['jewellery-accessories'],
            'title' => 'Gold-Plated Kundan & Pearl Choker Necklace Set with Earrings & Maang Tikka',
            'slug' => 'gold-plated-kundan-pearl-choker-set',
            'description' => 'Royal Rajasthani Kundan bridal choker set embellished with lustrous faux pearls and colored gemstones. Includes matching drop jhumka earrings and delicate maang tikka.',
            'price' => 299.00,
            'mrp' => 1299.00,
            'stock' => 115,
            'sku' => 'JWL-NEK-001',
            'sizes' => 'Adjustable Dori',
            'colors' => 'Gold & Emerald Green, Gold & Ruby Red',
            'fabric' => 'Brass & Kundan Stone',
            'rating_avg' => 4.6,
            'rating_count' => 3420,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Bags & Footwear
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['bags-footwear'],
            'title' => 'Women Structured Handheld Satchel Bag with Sling Strap',
            'slug' => 'women-structured-handheld-satchel-bag',
            'description' => 'Trendy textured PU leather tote handbag featuring dual top handles, detachable crossbody sling strap, metallic gold lock buckle, and spacious multi-compartment interior.',
            'price' => 399.00,
            'mrp' => 1399.00,
            'stock' => 80,
            'sku' => 'BAG-SAT-001',
            'sizes' => 'Standard Handbag',
            'colors' => 'Tan Brown, Chic Beige, Jet Black',
            'fabric' => 'Premium PU Leather',
            'rating_avg' => 4.5,
            'rating_count' => 920,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['bags-footwear'],
            'title' => 'Comfortable Memory Foam Slip-On Walking Shoes for Women',
            'slug' => 'memory-foam-slip-on-walking-shoes',
            'description' => 'Feather-light breathable mesh casual walking sneakers with ultra soft cushioned insole and non-skid rubber sole. Perfect for jogging, gym, daily walks, or college.',
            'price' => 379.00,
            'mrp' => 1199.00,
            'stock' => 130,
            'sku' => 'SHU-SLP-002',
            'sizes' => 'IND-4, IND-5, IND-6, IND-7, IND-8',
            'colors' => 'Blush Pink, Charcoal Grey, All Black',
            'fabric' => 'Breathable Mesh & EVA',
            'rating_avg' => 4.3,
            'rating_count' => 1430,
            'is_featured' => 0,
            'images' => [
                'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1560769629-975ec94e6a86?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        // Electronics
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['electronics'],
            'title' => 'True Wireless Bluetooth 5.3 Earbuds with 40H Playtime & Deep Bass',
            'slug' => 'true-wireless-bluetooth-earbuds-40h-playtime',
            'description' => 'Immerse in high-fidelity sound with 13mm dynamic neodymium drivers, environmental noise cancellation (ENC) for crystal clear calls, IPX5 water resistance, and fast Type-C charging.',
            'price' => 549.00,
            'mrp' => 1999.00,
            'stock' => 160,
            'sku' => 'ELC-EAR-001',
            'sizes' => 'Standard Fit',
            'colors' => 'Matte Black, Pearl White',
            'fabric' => 'Polycarbonate Plastic',
            'rating_avg' => 4.4,
            'rating_count' => 4150,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&auto=format&fit=crop&q=80'
            ]
        ],
        [
            'supplier_id' => $supplierIds['Vaidehi Trends'],
            'category_id' => $catIds['electronics'],
            'title' => 'Smart Fitness Band with 1.4-Inch HD Touch Display & SpO2 Heart Rate Monitor',
            'slug' => 'smart-fitness-band-hd-touch-display',
            'description' => 'Track your active lifestyle with 20+ sports modes, 24/7 heart rate and blood oxygen monitoring, sleep analysis, call & message notifications, and 7-day battery life.',
            'price' => 699.00,
            'mrp' => 2499.00,
            'stock' => 90,
            'sku' => 'ELC-WTC-002',
            'sizes' => 'Adjustable Strap',
            'colors' => 'Midnight Black, Rose Gold, Navy',
            'fabric' => 'Silicone & Aluminum Case',
            'rating_avg' => 4.5,
            'rating_count' => 2200,
            'is_featured' => 1,
            'images' => [
                'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80',
                'https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=600&auto=format&fit=crop&q=80'
            ]
        ]
    ];

    $stmtProdCheck = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
    $stmtProdInsert = $pdo->prepare("INSERT INTO products 
        (supplier_id, category_id, title, slug, description, price, mrp, discount_percent, stock, sku, sizes, colors, fabric, free_delivery, cod_available, rating_avg, rating_count, status, is_featured) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, 'approved', ?)");

    $stmtImgInsert = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)");

    $stmtReviewInsert = $pdo->prepare("INSERT INTO reviews (product_id, user_id, user_name, rating, review_text, helpful_count) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($products as $p) {
        $stmtProdCheck->execute([$p['slug']]);
        $existing = $stmtProdCheck->fetch();
        $discount = round((($p['mrp'] - $p['price']) / $p['mrp']) * 100);
        
        if (!$existing) {
            $stmtProdInsert->execute([
                $p['supplier_id'], $p['category_id'], $p['title'], $p['slug'], $p['description'],
                $p['price'], $p['mrp'], $discount, $p['stock'], $p['sku'], $p['sizes'],
                $p['colors'], $p['fabric'], $p['rating_avg'], $p['rating_count'], $p['is_featured']
            ]);
            $productId = $pdo->lastInsertId();
            echo "[+] Created Product: {$p['title']} (₹{$p['price']})\n";

            // Insert Images
            $sort = 0;
            foreach ($p['images'] as $imgUrl) {
                $isPrimary = ($sort === 0) ? 1 : 0;
                $stmtImgInsert->execute([$productId, $imgUrl, $isPrimary, $sort++]);
            }

            // Insert realistic customer reviews for this product
            $sampleReviews = [
                ['Ananya K.', 5, 'Superb quality! Exactly like shown in pictures. The fabric is very soft and looks grand for the price! Will order again.', 34],
                ['Deepak M.', 4, 'Very fast delivery within 3 days. Fitting is perfect and stitch quality is neat. Value for money.', 19],
                ['Komal R.', 5, 'Youshoo delivered earlier than expected! Awesome fitting and beautiful color shine. 100% recommended!', 42]
            ];
            foreach ($sampleReviews as $rev) {
                $stmtReviewInsert->execute([$productId, null, $rev[0], $rev[1], $rev[2], $rev[3]]);
            }
        } else {
            echo "[*] Product already exists: {$p['title']}\n";
        }
    }

    // 9. Seed Sample Orders for analytics demonstration
    $stmtOrderCheck = $pdo->prepare("SELECT COUNT(*) FROM orders");
    $stmtOrderCheck->execute();
    if ($stmtOrderCheck->fetchColumn() == 0) {
        // Fetch first 2 products
        $prods = $pdo->query("SELECT id, supplier_id, title, price FROM products LIMIT 3")->fetchAll();
        if (count($prods) >= 2) {
            $orderNum1 = "YSH-" . strtoupper(bin2hex(random_bytes(4)));
            $total1 = $prods[0]['price'];
            $stmtOrd = $pdo->prepare("INSERT INTO orders 
                (order_number, user_id, total_amount, discount_amount, delivery_fee, final_amount, payment_method, payment_status, order_status, shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_pincode) 
                VALUES (?, NULL, ?, 0.00, 0.00, ?, 'cod', 'pending', 'confirmed', 'Priya Sharma', '9123456780', 'Flat 402, Sai Residency, MG Road', 'Mumbai', 'Maharashtra', '400001')");
            $stmtOrd->execute([$orderNum1, $total1, $total1]);
            $ordId1 = $pdo->lastInsertId();

            $pImg1 = $pdo->query("SELECT image_url FROM product_images WHERE product_id = {$prods[0]['id']} LIMIT 1")->fetchColumn();
            $pdo->prepare("INSERT INTO order_items (order_id, supplier_id, product_id, product_title, product_image, size, color, quantity, unit_price, total_price, supplier_status) VALUES (?, ?, ?, ?, ?, 'Free Size', 'Default', 1, ?, ?, 'accepted')")
                ->execute([$ordId1, $prods[0]['supplier_id'], $prods[0]['id'], $prods[0]['title'], $pImg1 ?: '', $prods[0]['price'], $prods[0]['price']]);

            // Order 2 (Delivered)
            $orderNum2 = "YSH-" . strtoupper(bin2hex(random_bytes(4)));
            $total2 = $prods[1]['price'];
            $stmtOrd->execute([$orderNum2, $total2, $total2]);
            $ordId2 = $pdo->lastInsertId();
            $pdo->query("UPDATE orders SET order_status = 'delivered', payment_status = 'paid' WHERE id = $ordId2");

            $pImg2 = $pdo->query("SELECT image_url FROM product_images WHERE product_id = {$prods[1]['id']} LIMIT 1")->fetchColumn();
            $pdo->prepare("INSERT INTO order_items (order_id, supplier_id, product_id, product_title, product_image, size, color, quantity, unit_price, total_price, supplier_status) VALUES (?, ?, ?, ?, ?, 'M', 'Default', 1, ?, ?, 'delivered')")
                ->execute([$ordId2, $prods[1]['supplier_id'], $prods[1]['id'], $prods[1]['title'], $pImg2 ?: '', $prods[1]['price'], $prods[1]['price']]);

            echo "[OK] Generated sample demonstration orders for customer & supplier analytics.\n";
        }
    }

    echo "\n====================================================\n";
    echo "  [SUCCESS] YOUSHOO DATABASE SETUP COMPLETED!\n";
    echo "====================================================\n\n";
    echo "DEFAULT DEMO ACCOUNTS:\n";
    echo "1. Admin Portal:     admin@youshoo.com     / admin123\n";
    echo "2. Supplier Hub:     supplier@youshoo.com  / seller123\n";
    echo "Customer Account:    Register a new customer account at /MEESHO/auth.php then login\n\n";
    echo "<a href='../index.php' style='display:inline-block; padding:10px 20px; background:#9f2089; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>Go to Youshoo Storefront &rarr;</a>\n";
    echo "</pre>";

} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "</pre>";
}
