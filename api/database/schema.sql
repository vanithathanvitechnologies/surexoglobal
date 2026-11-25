
-- ----------------------------------------------------------------------------
-- 1. Admins Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(60) NOT NULL DEFAULT 'Super Admin',
  `status` ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
  `auth_token` VARCHAR(128) DEFAULT NULL,
  `token_expires_at` DATETIME DEFAULT NULL,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_token` (`auth_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Categories Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(60) NOT NULL DEFAULT 'grid',
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  `image` VARCHAR(500) NOT NULL DEFAULT '',
  `description` TEXT,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Products Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NOT NULL DEFAULT 'furniture',
  `category_name` VARCHAR(150) NOT NULL DEFAULT 'Furniture',
  `brand` VARCHAR(150) NOT NULL DEFAULT 'Surexoglobal',
  `sku` VARCHAR(100) NOT NULL DEFAULT '',
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `original_price` DECIMAL(12,2) DEFAULT NULL,
  `discount` VARCHAR(60) NOT NULL DEFAULT '',
  `rating` DECIMAL(3,1) NOT NULL DEFAULT 4.5,
  `reviews_count` INT UNSIGNED NOT NULL DEFAULT 100,
  `admin_default_rating` DECIMAL(3,1) NOT NULL DEFAULT 4.5,
  `admin_default_review_count` INT UNSIGNED NOT NULL DEFAULT 100,
  `customer_reviews_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `in_stock` TINYINT(1) NOT NULL DEFAULT 1,
  `is_trending` TINYINT(1) NOT NULL DEFAULT 0,
  `is_best_seller` TINYINT(1) NOT NULL DEFAULT 0,
  `image` VARCHAR(500) NOT NULL DEFAULT '',
  `gallery` LONGTEXT,
  `colors` LONGTEXT,
  `materials` LONGTEXT,
  `sizes` LONGTEXT,
  `description` TEXT,
  `features` LONGTEXT,
  `specifications` LONGTEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category`),
  KEY `idx_products_sku` (`sku`),
  KEY `idx_products_stock` (`in_stock`),
  KEY `idx_products_trending` (`is_trending`),
  KEY `idx_products_bestseller` (`is_best_seller`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Customers Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL,
  `mobile` VARCHAR(40) NOT NULL DEFAULT '',
  `avatar` VARCHAR(500) NOT NULL DEFAULT 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&q=80',
  `city` VARCHAR(100) NOT NULL DEFAULT 'Chennai',
  `state` VARCHAR(100) NOT NULL DEFAULT 'Tamil Nadu',
  `total_orders` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_spent` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `role` VARCHAR(60) NOT NULL DEFAULT 'Customer',
  `status` ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
  `joined_date` VARCHAR(50) NOT NULL DEFAULT '',
  `auth_token` VARCHAR(128) DEFAULT NULL,
  `token_expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_email` (`email`),
  KEY `idx_customers_token` (`auth_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Orders Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` VARCHAR(50) NOT NULL,
  `order_date` VARCHAR(60) NOT NULL,
  `customer_id` VARCHAR(50) DEFAULT NULL,
  `customer_name` VARCHAR(150) NOT NULL,
  `customer_email` VARCHAR(190) NOT NULL,
  `customer_phone` VARCHAR(40) NOT NULL DEFAULT '',
  `product_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(120) NOT NULL DEFAULT 'General',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `coupon_code` VARCHAR(60) DEFAULT NULL,
  `payment_method` VARCHAR(40) NOT NULL DEFAULT 'upi',
  `payment_status` VARCHAR(60) NOT NULL DEFAULT 'Paid',
  `order_status` VARCHAR(60) NOT NULL DEFAULT 'Processing',
  `image` VARCHAR(500) NOT NULL DEFAULT '',
  `tracking_step` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `items` LONGTEXT,
  `address` TEXT,
  `delivery_type` VARCHAR(40) NOT NULL DEFAULT 'standard',
  `order_notes` TEXT,
  `admin_notes` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_customer_email` (`customer_email`),
  KEY `idx_orders_status` (`order_status`),
  KEY `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Reviews Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` VARCHAR(100) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `customer_id` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `rating` DECIMAL(3,1) NOT NULL DEFAULT 5.0,
  `title` VARCHAR(255) NOT NULL,
  `comment` TEXT NOT NULL,
  `avatar` VARCHAR(500) NOT NULL DEFAULT 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&q=80',
  `review_date` VARCHAR(60) NOT NULL DEFAULT '',
  `status` ENUM('Approved','Pending','Hidden') NOT NULL DEFAULT 'Approved',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_product` (`product_id`),
  KEY `idx_reviews_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Coupons Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` VARCHAR(50) NOT NULL,
  `code` VARCHAR(60) NOT NULL,
  `discount_type` ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
  `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `min_order_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `description` TEXT,
  `usage_limit` INT UNSIGNED NOT NULL DEFAULT 100,
  `times_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `expiry_date` DATE DEFAULT NULL,
  `status` ENUM('Active','Expired') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupons_code` (`code`),
  KEY `idx_coupons_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Custom Product Requests Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `custom_requests` (
  `id` VARCHAR(50) NOT NULL,
  `request_date` VARCHAR(60) NOT NULL,
  `customer_id` VARCHAR(50) DEFAULT NULL,
  `full_name` VARCHAR(150) NOT NULL DEFAULT '',
  `email` VARCHAR(190) NOT NULL DEFAULT '',
  `mobile` VARCHAR(40) NOT NULL DEFAULT '',
  `account_id` VARCHAR(80) NOT NULL DEFAULT '',
  `product_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(120) NOT NULL DEFAULT 'General',
  `subcategory` VARCHAR(120) NOT NULL DEFAULT '',
  `brand` VARCHAR(120) NOT NULL DEFAULT '',
  `status` VARCHAR(60) NOT NULL DEFAULT 'Under Review',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `budget` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quotation` DECIMAL(12,2) DEFAULT NULL,
  `requirements` TEXT,
  `location` VARCHAR(255) NOT NULL DEFAULT '',
  `delivery_date` VARCHAR(60) NOT NULL DEFAULT '',
  `contact_preference` VARCHAR(60) NOT NULL DEFAULT '',
  `notes` TEXT,
  `images` LONGTEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_requests_status` (`status`),
  KEY `idx_requests_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Contact Messages Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `mobile` VARCHAR(40) NOT NULL DEFAULT '',
  `subject` VARCHAR(150) NOT NULL DEFAULT 'General Inquiry',
  `message` TEXT NOT NULL,
  `status` ENUM('New','Read','Replied') NOT NULL DEFAULT 'New',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. FAQs Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question` VARCHAR(300) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. Cart & Wishlist Sync Tables (Keyed by Customer ID or Guest Session Key)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cart_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_key` VARCHAR(120) NOT NULL,
  `product_id` VARCHAR(100) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `selected_color` VARCHAR(80) NOT NULL DEFAULT '',
  `selected_material` VARCHAR(120) NOT NULL DEFAULT '',
  `selected_size` VARCHAR(120) NOT NULL DEFAULT '',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_session_item` (`session_key`, `product_id`, `selected_size`),
  KEY `idx_cart_session` (`session_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wishlist_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_key` VARCHAR(120) NOT NULL,
  `product_id` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist_session_product` (`session_key`, `product_id`),
  KEY `idx_wishlist_session` (`session_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. Password Resets Table
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pwreset_email` (`email`),
  KEY `idx_pwreset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Default Admin Account (admin@surexoglobal.com / admin123)
INSERT INTO `admins` (`name`, `email`, `password_hash`, `role`, `status`) VALUES
('System Administrator', 'admin@surexoglobal.com', '$2y$10$aG/UrTxVp5lv6DsH1dHGgOR92klAo7DYfc.9s3LIeLesx7aZtilEK', 'Super Admin', 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Categories (11)
INSERT INTO `categories` (`id`, `name`, `icon`, `count`, `image`, `description`, `sort_order`) VALUES
('all', 'All Products', 'grid', 1024, 'https://images.unsplash.com/photo-1472851294608-062f824d29cc?w=300&q=80', 'Browse our complete global marketplace catalog', 1),
('furniture', 'Furniture', 'sofa', 120, 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=300&q=80', 'Modern sofas, wooden beds, dining tables & lounge chairs', 2),
('vegetables', 'Vegetables', 'carrot', 65, 'https://images.unsplash.com/photo-1597362925123-77861d3fbac7?w=300&q=80', 'Farm-fresh hydroponic and organic vegetables', 3),
('fruits', 'Fruits', 'apple', 72, 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?w=300&q=80', 'Orchard-fresh seasonal fruits delivered daily', 4),
('mens-fashion', 'Men\'s Fashion', 'shirt', 140, 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=300&q=80', 'Casual shirts, pique polos & everyday menswear', 5),
('womens-fashion', 'Women\'s Fashion', 'dress', 210, 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=300&q=80', 'Party dresses, ethnic wear & contemporary styles', 6),
('kids-fashion', 'Kids\' Fashion', 'baby', 85, 'https://images.unsplash.com/photo-1514090458221-65bb69cf63e6?w=300&q=80', 'Soft organic cotton outfits for kids', 7),
('electronics', 'Electronics', 'headphones', 98, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=300&q=80', 'ANC headphones, smartwatches & audio gadgets', 8),
('digital-accessories', 'Digital Accessories', 'smartphone', 74, 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?w=300&q=80', 'Ergonomic stands, cables & mobile accessories', 9),
('home-decor', 'Home Decor', 'lamp', 60, 'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=300&q=80', 'Blackout curtains, lighting & interior accents', 10),
('kitchen-essentials', 'Kitchen Essentials', 'pot', 50, 'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=300&q=80', 'Air fryers, granite cookware sets & kitchen tools', 11)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Products (15)
INSERT INTO `products` (
  `id`, `name`, `category`, `category_name`, `brand`, `sku`, `price`, `original_price`, `discount`,
  `rating`, `reviews_count`, `admin_default_rating`, `admin_default_review_count`, `customer_reviews_count`,
  `in_stock`, `is_trending`, `is_best_seller`, `image`, `gallery`, `colors`, `materials`, `sizes`,
  `description`, `features`, `specifications`
) VALUES
(
  'prod-sofa', 'Modern Fabric Sofa', 'furniture', 'Furniture', 'FurniHome', 'FH-SF-001', 24999.00, 32999.00, '24% OFF',
  4.5, 120, 4.5, 120, 0, 1, 1, 1,
  'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80',
  '["https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80","https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?w=800&q=80","https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80","https://images.unsplash.com/photo-1550581190-9c1c48d21d6c?w=800&q=80"]',
  '["#808080","#e5d3b3","#2b4c7e","#3d405b"]',
  '["Fabric","Leather"]',
  '["2 Seater","3 Seater","L Shape"]',
  'Upgrade your living space with our Modern Fabric Sofa. Designed for comfort, style and durability, this sofa is perfect for modern homes. The soft fabric upholstery and sturdy wooden frame provide long-lasting support, while its elegant design enhances any room decor.',
  '["Premium fabric upholstery","Sturdy wooden frame","Comfortable high-density seating foam","Modern & elegant Scandinavian design","Easy to clean and stain resistant"]',
  '{"Brand":"FurniHome","Model":"FH-SF-001","Material":"High-density Foam & Fabric","Color":"Warm Ash Grey","Dimensions":"180 cm (W) x 85 cm (D) x 75 cm (H)","Weight":"35 kg","Style":"Contemporary Modern","Warranty":"1 Year Comprehensive"}'
),
(
  'prod-dining-table', 'Wooden Dining Table', 'furniture', 'Furniture', 'Home Living', 'HL-DT-012', 14999.00, 19999.00, '25% OFF',
  4.4, 180, 4.4, 180, 0, 1, 1, 0,
  'https://images.unsplash.com/photo-1615066390971-03e4e1c36ddf?w=800&q=80',
  '["https://images.unsplash.com/photo-1615066390971-03e4e1c36ddf?w=800&q=80","https://images.unsplash.com/photo-1533090161767-e6ffed986c88?w=800&q=80"]',
  '["#8b5a2b","#4a2e18"]',
  '["Solid Teak Wood","Sheesham"]',
  '["4 Seater","6 Seater"]',
  'Handcrafted solid wood dining table finished in natural teak polish. Engineered for family gatherings and stylish interior aesthetics.',
  '["100% Solid Sheesham Wood","Scratch-resistant lacquer finish","Reinforced joinery for lifetime stability"]',
  '{"Brand":"Home Living","Model":"HL-DT-012","Material":"Solid Wood","Color":"Honey Brown Teak","Dimensions":"150 cm (L) x 90 cm (W) x 76 cm (H)","Weight":"42 kg","Warranty":"3 Years"}'
),
(
  'prod-tomatoes', 'Fresh Tomatoes', 'vegetables', 'Vegetables', 'Surexoglobal Fresh', 'SM-VG-001', 49.00, 60.00, '18% OFF',
  4.6, 320, 4.6, 320, 0, 1, 1, 0,
  'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&q=80',
  '["https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&q=80"]',
  '["#dc2626"]',
  '["Organic Farm Harvest"]',
  '["1 kg","2 kg","5 kg"]',
  'Farm-fresh hydroponic and open-field vine-ripened red tomatoes. Delivered daily from local farms directly to your doorstep.',
  '["100% Pesticide tested","Plump & juicy red","Rich in Lycopene and Vitamin C"]',
  '{"Brand":"Surexoglobal Fresh","Weight":"1 kg pack","Origin":"Locally Farmed Tamil Nadu","Shelf Life":"4-5 Days at ambient temperature"}'
),
(
  'prod-apples', 'Fresh Apples (Shimla Royal Delicious)', 'fruits', 'Fruits', 'Surexoglobal Fresh', 'SM-FR-004', 99.00, 130.00, '24% OFF',
  4.6, 310, 4.6, 310, 0, 1, 1, 0,
  'https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=800&q=80',
  '["https://images.unsplash.com/photo-1560806887-1e4cd0b6cbd6?w=800&q=80"]',
  '["#b91c1c"]',
  '["Organic Orchard Fresh"]',
  '["1 kg (4-5 pcs)","2 kg Box"]',
  'Crisp, sweet, and naturally orchard-ripened Shimla Royal Delicious apples. Selected by hand for uniform size and sweetness.',
  '["Crisp juicy bite","Wax-free natural skin","Immunity boosting antioxidants"]',
  '{"Brand":"Surexoglobal Fresh","Weight":"1 kg Box","Origin":"Himachal Pradesh, India","Grade":"Grade A Export"}'
),
(
  'prod-dress', 'Women\'s Party Dress', 'womens-fashion', 'Women\'s Fashion', 'Global Style', 'GS-WD-108', 1499.00, 1999.00, '40% OFF',
  4.5, 210, 4.5, 210, 0, 1, 0, 1,
  'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=800&q=80',
  '["https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?w=800&q=80"]',
  '["#ec4899","#3b82f6","#0f172a"]',
  '["Chiffon & Georgette","Silk Blend"]',
  '["S","M","L","XL"]',
  'Chic fit-and-flare party dress with subtle pleating and breathable inner lining. Perfect for evening cocktail parties, festivals, and festive gatherings.',
  '["Flowy breathable drape","Concealed side zipper","Soft non-itchy inner slip"]',
  '{"Brand":"Global Style","Care":"Gentle Machine Wash","Occasion":"Evening / Festive / Party","Fit":"Fit & Flare"}'
),
(
  'prod-shirt', 'Men\'s Casual Shirt', 'mens-fashion', 'Men\'s Fashion', 'Global Tech Style', 'GTS-MS-045', 699.00, 999.00, '30% OFF',
  4.4, 190, 4.4, 190, 0, 1, 1, 0,
  'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=800&q=80',
  '["https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=800&q=80"]',
  '["#0284c7","#ffffff","#475569"]',
  '["100% Breathable Cotton"]',
  '["38","40","42","44"]',
  'Slim fit washed cotton casual button-down shirt. Features curved hemline, spread collar, and mother-of-pearl buttons.',
  '["Pre-shrunk 100% combed cotton","Comfort stretch weave","Wrinkle-resistant wash"]',
  '{"Brand":"Global Tech Style","Sleeve":"Full Sleeve","Collar":"Spread Collar","Pattern":"Solid Oxford"}'
),
(
  'prod-headphones', 'Wireless Headphones ANC', 'electronics', 'Electronics', 'Global Tech', 'GT-WH-300', 1999.00, 2999.00, '33% OFF',
  4.6, 270, 4.6, 270, 0, 1, 1, 1,
  'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80',
  '["https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80"]',
  '["#0f172a","#e2e8f0"]',
  '["Memory Foam & Matte Polymer"]',
  '["Standard Adjustable"]',
  'Immersive sound with 40mm neodymium drivers, active hybrid noise cancellation, and up to 35 hours of battery playback.',
  '["Active Hybrid ANC","35h Quick-Charge Battery","Bluetooth 5.3 Low Latency","Built-in Clear Voice Mic"]',
  '{"Brand":"Global Tech","Battery":"500 mAh Li-ion","Driver":"40 mm Dynamic","Warranty":"1 Year Brand Replacement"}'
),
(
  'prod-smartwatch', 'Smart Watch Series 9', 'electronics', 'Electronics', 'Global Tech', 'GT-SW-900', 2499.00, 3999.00, '38% OFF',
  4.5, 220, 4.5, 220, 0, 1, 0, 1,
  'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&q=80',
  '["https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&q=80"]',
  '["#0f172a","#ca8a04","#475569"]',
  '["Zinc Alloy & Leather Strap"]',
  '["44mm AMOLED"]',
  '1.96-inch high-res AMOLED display with Bluetooth calling, 120+ sports workout modes, SpO2 sensor, and 7-day battery life.',
  '["Always-On AMOLED Display","BT Calling & Notifications","Heart Rate & Sleep Monitor","IP68 Water Resistance"]',
  '{"Brand":"Global Tech","Display":"1.96\\" 410x502 AMOLED","Compatibility":"Android & iOS","Battery":"300 mAh (7 Days)"}'
),
(
  'prod-mobilestand', 'Adjustable Mobile Stand', 'digital-accessories', 'Digital Accessories', 'Surexoglobal Basics', 'SM-MS-009', 299.00, 499.00, '40% OFF',
  4.4, 160, 4.4, 160, 0, 0, 0, 0,
  'https://images.unsplash.com/photo-1586105251261-72a756497a11?w=800&q=80',
  '["https://images.unsplash.com/photo-1586105251261-72a756497a11?w=800&q=80"]',
  '["#1e293b","#cbd5e1"]',
  '["Heavy-Duty Aluminium Alloy"]',
  '["Universal Phone/Tablet"]',
  'Foldable ergonomic aluminum desk cradle compatible with smartphones and tablets up to 11 inches with anti-skid silicone pads.',
  '["Full 270° angle adjustment","Cable passthrough slot","Foldable pocket portable"]',
  '{"Brand":"Surexoglobal Basics","Weight":"180 g","Material":"CNC Aluminum Alloy"}'
),
(
  'prod-airfryer', 'Rapid Air Fryer 4.5L', 'kitchen-essentials', 'Kitchen Essentials', 'Premium Kitchen', 'PK-AF-450', 3999.00, 5999.00, '33% OFF',
  4.6, 200, 4.6, 200, 0, 1, 1, 1,
  'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=800&q=80',
  '["https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=800&q=80"]',
  '["#0f172a"]',
  '["BPA-Free Food Grade Non-Stick"]',
  '["4.5 Litres"]',
  'Crisp food with up to 90% less oil. Digital touchscreen with 8 one-touch cooking presets and auto-shutoff safety timer.',
  '["360° Rapid Air Circulation","Non-stick dishwasher safe basket","1400W Fast heating element"]',
  '{"Brand":"Premium Kitchen","Capacity":"4.5 Liters","Power":"1400 Watts","Warranty":"2 Years Replacement"}'
),
(
  'prod-curtains', 'Blackout Curtains Set (2 Panels)', 'home-decor', 'Home Decor', 'Home Living', 'HL-CT-022', 899.00, 1299.00, '31% OFF',
  4.5, 140, 4.5, 140, 0, 1, 0, 0,
  'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=800&q=80',
  '["https://images.unsplash.com/photo-1513694203232-719a280e022f?w=800&q=80"]',
  '["#e2e8f0","#334155","#92400e"]',
  '["Triple-Weave Thermal Polyester"]',
  '["7 Feet (Door)","9 Feet (Long Door)"]',
  'Triple-weave thermal blackout door curtains. Blocks 95% sunlight and reduces outside noise for calm, cooler room insulation.',
  '["Thermal room cooling barrier","Rust-resistant metal grommets","Machine washable"]',
  '{"Brand":"Home Living","Set":"2 Panels","Dimensions":"4 ft x 7 ft each"}'
),
(
  'prod-wooden-bed', 'King Size Wooden Bed with Storage', 'furniture', 'Furniture', 'FurniHome', 'FH-BD-301', 12999.00, 16999.00, '24% OFF',
  4.4, 120, 4.4, 120, 0, 1, 0, 1,
  'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&q=80',
  '["https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&q=80"]',
  '["#78350f","#292524"]',
  '["Engineered Sheesham Wood"]',
  '["Queen Size","King Size"]',
  'Solid engineered wood king-size cot featuring hydraulic lift underbed storage and cushioned acoustic headboard.',
  '["Spacious hydraulic storage box","Termite & moisture resistant","Quiet slat support system"]',
  '{"Brand":"FurniHome","Size":"King Size (78 x 72 inches)","Storage":"Hydraulic Lift-Up (800 L)","Warranty":"3 Years Warranty"}'
),
(
  'prod-chair', 'Premium Wooden Chair', 'furniture', 'Furniture', 'FurniHome', 'FH-CH-091', 8999.00, 16999.00, '31% OFF',
  4.5, 124, 4.5, 124, 0, 1, 1, 0,
  'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80',
  '["https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=800&q=80"]',
  '["#ca8a04","#451a03"]',
  '["Natural Oak & Linen Cushion"]',
  '["Standard Lounge"]',
  'Mid-century modern accent lounge armchair crafted from seasoned natural white oak with high-resilience linen cushions.',
  '["Ergonomic back support","Solid oak mortise joints","Removable cushion covers"]',
  '{"Brand":"FurniHome","Weight Capacity":"150 kg","Warranty":"2 Years"}'
),
(
  'prod-cookware', 'Non-Stick Cookware 4-Piece Set', 'kitchen-essentials', 'Kitchen Essentials', 'KitchenPro', 'KP-CW-400', 2499.00, 3999.00, '38% OFF',
  4.6, 400, 4.6, 400, 0, 1, 0, 1,
  'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=800&q=80',
  '["https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=800&q=80"]',
  '["#1e293b"]',
  '["Hard Anodized Aluminum with Granite Coating"]',
  '["4 Pcs Combo"]',
  'Includes Dosa Tawa (28cm), Fry Pan (24cm), Kadhai with Glass Lid (24cm), and Sauce Pan. Induction and gas stove compatible.',
  '["5-Layer Granite Non-Stick","PFOA & Lead Free","Cool-touch Bakelite handles"]',
  '{"Brand":"KitchenPro","Coating":"Granite Greblon Non-Stick","Compatibility":"Induction & Gas","Warranty":"2 Years"}'
),
(
  'prod-girls-frock', 'Girls Frock (Velcom Blossom Edition)', 'kids-fashion', 'Kids\' Fashion', 'Kids Club', 'KC-GF-082', 799.00, 1499.00, '47% OFF',
  4.7, 60, 4.7, 60, 0, 1, 0, 1,
  'https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?w=800&q=80',
  '["https://images.unsplash.com/photo-1518831959646-742c3a14ebf7?w=800&q=80"]',
  '["#f43f5e","#ec4899","#fef08a"]',
  '["100% Organic Soft Cotton"]',
  '["2-3 Yrs","4-5 Yrs","6-7 Yrs"]',
  'Pretty summer floral print cotton frock for young girls with delicate lace collar and soft inner lining.',
  '["Skin-friendly baby cotton","Fade-proof natural dyes","Gentle waistband elastic"]',
  '{"Brand":"Kids Club","Fabric":"100% Organic Cotton","Care":"Gentle Hand Wash"}'
),
(
  'prod-polo-shirt', 'Men\'s Polo T-Shirt', 'mens-fashion', 'Men\'s Fashion', 'Surexoglobal Basics', 'SM-PT-103', 599.00, 999.00, '40% OFF',
  4.5, 740, 4.5, 740, 0, 1, 1, 0,
  'https://images.unsplash.com/photo-1581655353564-df123a1eb820?w=800&q=80',
  '["https://images.unsplash.com/photo-1581655353564-df123a1eb820?w=800&q=80"]',
  '["#0284c7","#0f172a","#10b981","#ffffff"]',
  '["Pique Cotton Weave"]',
  '["M","L","XL","XXL"]',
  'Classic ribbed collar polo shirt knitted from 220 GSM breathable pique cotton with reinforced seams.',
  '["220 GSM Pique Cotton","No-curl collar tips","Classic 2-button placket"]',
  '{"Brand":"Surexoglobal Basics","Fit":"Regular Fit","Pattern":"Solid Pique"}'
)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Customers (5) — default password for demo customers is "customer123"
INSERT INTO `customers` (`id`, `name`, `email`, `password_hash`, `mobile`, `avatar`, `city`, `state`, `total_orders`, `total_spent`, `role`, `status`, `joined_date`) VALUES
('CUST-001', 'Nivetha Raman', 'nivetha@gmail.com', '$2y$10$iwMKsbzTjb039MXY3T84c.ox3.FFy5WEkQlat9DA/XqKLag8qKN/G', '+91 98765 43210', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&q=80', 'Chennai', 'Tamil Nadu', 5, 44894.00, 'Customer', 'Active', '15 Jan 2026'),
('CUST-002', 'Rahul Sharma', 'rahul.k@outlook.com', '$2y$10$iwMKsbzTjb039MXY3T84c.ox3.FFy5WEkQlat9DA/XqKLag8qKN/G', '+91 98123 45678', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&q=80', 'Bengaluru', 'Karnataka', 3, 16890.00, 'Customer', 'Active', '02 Feb 2026'),
('CUST-003', 'Priya Sundaram', 'priya.s@yahoo.com', '$2y$10$iwMKsbzTjb039MXY3T84c.ox3.FFy5WEkQlat9DA/XqKLag8qKN/G', '+91 97890 12345', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=300&q=80', 'Coimbatore', 'Tamil Nadu', 8, 62450.00, 'VIP Customer', 'Active', '10 Nov 2025'),
('CUST-004', 'Amit Patel', 'amit.patel@gmail.com', '$2y$10$iwMKsbzTjb039MXY3T84c.ox3.FFy5WEkQlat9DA/XqKLag8qKN/G', '+91 99234 56789', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&q=80', 'Ahmedabad', 'Gujarat', 1, 799.00, 'Customer', 'Active', '22 Mar 2026'),
('CUST-005', 'Ananya Deshmukh', 'ananya.d@gmail.com', '$2y$10$iwMKsbzTjb039MXY3T84c.ox3.FFy5WEkQlat9DA/XqKLag8qKN/G', '+91 96543 21098', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300&q=80', 'Mumbai', 'Maharashtra', 4, 28350.00, 'Customer', 'Active', '05 Apr 2026')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Coupons (4)
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order_amount`, `description`, `usage_limit`, `times_used`, `expiry_date`, `status`) VALUES
('CPN-001', 'SAVE30', 'fixed', 30.00, 299.00, 'Instant ₹30 off on any cart above ₹299', 500, 142, '2026-12-31', 'Active'),
('CPN-002', 'SUREXO10', 'percentage', 10.00, 999.00, '10% instant discount on orders above ₹999', 1000, 388, '2026-12-31', 'Active'),
('CPN-003', 'FREE500', 'fixed', 500.00, 4999.00, 'Flat ₹500 off on furniture and high-ticket orders', 200, 64, '2026-11-30', 'Active'),
('CPN-004', 'FESTIVE20', 'percentage', 20.00, 1999.00, 'Special seasonal festival coupon code', 250, 250, '2026-10-15', 'Expired')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Reviews (5)
INSERT INTO `reviews` (`id`, `product_id`, `product_name`, `name`, `rating`, `title`, `comment`, `avatar`, `review_date`, `status`) VALUES
(1, 'prod-sofa', 'Modern Fabric Sofa', 'Priya S.', 5.0, 'Very comfortable and stylish sofa. Highly recommended!', 'The delivery was very prompt and the technicians assembled it in under 20 minutes. Fabric feels luxurious and the seating support is firm yet comfortable.', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&q=80', 'Nov 10, 2026', 'Approved'),
(2, 'prod-sofa', 'Modern Fabric Sofa', 'Rahul K.', 5.0, 'Good quality and value for money.', 'For ₹24,999, you simply cannot find a better deal in local furniture stores. The grey fabric matches our living room tiles beautifully.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&q=80', 'Oct 28, 2026', 'Approved'),
(3, 'prod-sofa', 'Modern Fabric Sofa', 'Meena R.', 4.0, 'Perfect for my living room. Love it!', 'Excellent build quality, no squeaks, and very deep seating cushions. My kids love lounging here during movie nights.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&q=80', 'Oct 15, 2026', 'Approved'),
(4, 'prod-tomatoes', 'Fresh Tomatoes', 'Sunita V.', 5.0, 'Super fresh and juicy!', 'Received them within 3 hours. Completely fresh farm quality, red and firm. Will definitely order daily vegetables here.', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&q=80', 'Nov 18, 2026', 'Approved'),
(5, 'prod-dining-table', 'Wooden Dining Table', 'Karthik N.', 4.0, 'Solid wood build, elegant finish', 'The Sheesham wood finish is rich and dark. Looks like a table twice its price. Sturdy chairs and smooth tabletop.', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&q=80', 'Nov 12, 2026', 'Approved')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Orders (5)
INSERT INTO `orders` (
  `id`, `order_date`, `customer_id`, `customer_name`, `customer_email`, `customer_phone`,
  `product_name`, `category`, `quantity`, `subtotal`, `total`,
  `payment_method`, `payment_status`, `order_status`, `image`, `tracking_step`, `address`
) VALUES
('#1005', 'Nov 10, 2026', 'CUST-001', 'Nivetha Raman', 'nivetha@gmail.com', '+91 98765 43210', 'Modern Fabric Sofa', 'Furniture', 1, 24999.00, 24999.00, 'upi', 'Paid', 'Delivered', 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&q=80', 5, 'No 42, Green Avenue, Anna Nagar, Chennai - 600040'),
('#1004', 'Oct 28, 2026', 'CUST-002', 'Rahul Sharma', 'rahul.k@outlook.com', '+91 98123 45678', 'Fresh Tomatoes', 'Vegetables', 2, 98.00, 98.00, 'upi', 'Paid', 'Delivered', 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&q=80', 5, 'Flat 302, Palm Heights, Indiranagar, Bengaluru - 560038'),
('#1003', 'Oct 20, 2026', 'CUST-003', 'Priya Sundaram', 'priya.s@yahoo.com', '+91 97890 12345', 'Men\'s Casual Shirt', 'Men\'s Fashion', 1, 799.00, 799.00, 'card', 'Paid', 'Delivered', 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=800&q=80', 5, '15/B Race Course Road, Coimbatore - 641018'),
('#1002', 'Oct 15, 2026', 'CUST-004', 'Amit Patel', 'amit.patel@gmail.com', '+91 99234 56789', 'Air Fryer', 'Kitchen Essentials', 1, 3999.00, 3999.00, 'upi', 'Paid', 'Processing', 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=800&q=80', 2, '88 Shanti Nagar, SG Highway, Ahmedabad - 380015'),
('#1001', 'Oct 10, 2026', 'CUST-005', 'Ananya Deshmukh', 'ananya.d@gmail.com', '+91 96543 21098', 'Wooden Dining Table', 'Furniture', 1, 14999.00, 14999.00, 'netbanking', 'Paid', 'Delivered', 'https://images.unsplash.com/photo-1615066390971-03e4e1c36ddf?w=800&q=80', 5, 'B-12, Sea Breeze Apts, Bandra West, Mumbai - 400050')
ON DUPLICATE KEY UPDATE `order_status` = VALUES(`order_status`);

-- Custom Product Requests (6)
INSERT INTO `custom_requests` (`id`, `request_date`, `product_name`, `category`, `status`, `quantity`, `budget`, `quotation`, `notes`, `images`) VALUES
('PRQ-1001', '08-10-2026', 'Wooden Chair', 'Furniture', 'Pending', 4, 15000.00, NULL, 'Requires dark walnut finish with cushioned seats', '[]'),
('PRQ-1002', '07-10-2026', 'Smart Watch', 'Electronics', 'Under Review', 1, 3500.00, NULL, 'AMOLED display with GPS tracking', '[]'),
('PRQ-1003', '06-10-2026', 'Air Purifier', 'Home & Garden', 'Product Found', 2, 12000.00, 11499.00, 'HEPA 13 filter required', '[]'),
('PRQ-1004', '05-10-2026', 'Running Shoes', 'Fashion', 'Quotation Sent', 1, 2500.00, 2299.00, 'Size 9 UK, lightweight mesh', '[]'),
('PRQ-1005', '04-10-2026', 'Gaming Chair', 'Furniture', 'Approved', 1, 9000.00, 8499.00, 'Ergonomic lumbar support', '[]'),
('PRQ-1006', '03-10-2026', 'Blender', 'Kitchen Essentials', 'Rejected', 1, 800.00, NULL, 'Out of regional supplier range', '[]')
ON DUPLICATE KEY UPDATE `product_name` = VALUES(`product_name`);

-- FAQs (5)
INSERT INTO `faqs` (`id`, `question`, `answer`, `sort_order`, `is_active`) VALUES
(1, '1. How can I place an order?', 'Simply browse our categories, add desired products to your cart, click on "Checkout", enter your delivery address in India, choose your preferred payment method (UPI, Net Banking, Credit/Debit Card or COD), and confirm your order.', 1, 1),
(2, '2. How can I track my order?', 'Once your order is dispatched, you will receive an SMS and WhatsApp update with your live tracking URL and courier AWB number. You can also track anytime by entering your Order ID on our "Track Order" page.', 2, 1),
(3, '3. What payment methods are supported?', 'We support all major payment modes including Google Pay, PhonePe, Paytm, BHIM UPI, Visa, MasterCard, RuPay cards, Net Banking across 50+ banks, and Cash on Delivery (COD) for eligible pin codes.', 3, 1),
(4, '4. Can I request unavailable products?', 'Yes! If you cannot find what you are looking for, click on the "Request a Product" button anywhere on our website, upload a sample reference image, and our procurement team will source it for you at wholesale rates.', 4, 1),
(5, '5. How can I contact customer support?', 'Our dedicated customer service team is available Monday through Saturday from 9:00 AM to 8:00 PM via phone (+91 98765 43210), email (support@surexoglobal.com), and 24/7 instant WhatsApp chat.', 5, 1)
ON DUPLICATE KEY UPDATE `question` = VALUES(`question`);

SET FOREIGN_KEY_CHECKS = 1;
