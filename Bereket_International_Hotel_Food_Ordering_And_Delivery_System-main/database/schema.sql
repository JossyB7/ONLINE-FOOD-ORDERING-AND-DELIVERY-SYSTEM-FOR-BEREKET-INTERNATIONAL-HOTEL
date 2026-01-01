CREATE DATABASE IF NOT EXISTS bereket_hotel_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
 USE bereket_hotel_db;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    category ENUM('appetizers', 'main-course', 'desserts', 'beverages') NOT NULL,
    image VARCHAR(255),
    stock INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    delivery_address TEXT NOT NULL,
    order_notes TEXT,
    subtotal DECIMAL(10, 2) NOT NULL,
    delivery_fee DECIMAL(10, 2) NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    payment_screenshot VARCHAR(255),
    status ENUM('pending', 'verified', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    estimated_delivery_minutes INT DEFAULT 45,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_number (order_number),
    INDEX idx_status (status),
    INDEX idx_customer_email (customer_email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_id INT,
    item_name VARCHAR(255) NOT NULL,
    item_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_item_id (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin Users Table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Insert menu items (using INSERT IGNORE to prevent errors if items already exist)
INSERT IGNORE INTO menu_items (name, description, price, category, image, stock) VALUES
('Doro Wat', 'Spicy chicken stew with injera', 285.00, 'main-course', 'asset/image/doro.jpg', 50),
('Tibs', 'Sautéed beef with vegetables', 320.00, 'main-course', 'asset/image/tibs.jpg', 50),
('Kitfo', 'Minced raw beef with spices', 340.00, 'main-course', 'asset/image/kitfo.jpg', 30),
('Shiro', 'Chickpea stew with injera', 205.00, 'main-course', 'asset/image/shero.jpg', 50),
('Firfir', 'Shredded injera with sauce', 170.00, 'main-course', 'asset/image/firfir.jpg', 50),
('Vegetable Samosa', 'Crispy pastry with vegetables', 58.00, 'appetizers', 'asset/image/samosa.jpg', 100),
('Ethiopian Salad', 'Fresh mixed vegetables', 138.00, 'appetizers', 'asset/image/salad.jpg', 50),
('Baklava', 'Sweet pastry with honey', 115.00, 'desserts', 'asset/image/baklava.jpg', 50),
('Tiramisu', 'Italian coffee dessert', 170.00, 'desserts', 'asset/image/tiramisu.jpg', 30),
('Fresh Juice', 'Seasonal fruit juice', 70.00, 'beverages', 'asset/image/fresh.jpg', 100);


CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    reset_token VARCHAR(100),
    reset_token_expires TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Insert admin user (using INSERT IGNORE to prevent errors if user already exists)
INSERT IGNORE INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin123', 'yosefbelachew41@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');



