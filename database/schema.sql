
CREATE DATABASE IF NOT EXISTS kopi_mahasiswa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kopi_mahasiswa;

-- 1. roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

-- 3. categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. products
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image VARCHAR(255),
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- 5. orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    order_type ENUM('Dine In', 'Take Away') DEFAULT 'Dine In',
    status ENUM('PENDING', 'CONFIRMED', 'PREPARING', 'READY', 'COMPLETED', 'CANCELLED') DEFAULT 'PENDING',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- 6. order_items
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- 7. payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount_paid DECIMAL(10, 2) NOT NULL,
    change_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) DEFAULT 'CASH',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- 8. stock
CREATE TABLE IF NOT EXISTS stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL UNIQUE,
    current_stock INT NOT NULL DEFAULT 0,
    minimum_stock INT NOT NULL DEFAULT 10,
    unit VARCHAR(20) DEFAULT 'Pcs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 9. stock_transactions
CREATE TABLE IF NOT EXISTS stock_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    type ENUM('IN', 'OUT') NOT NULL,
    quantity INT NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE CASCADE
);

-- 10. suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 11. purchase_orders
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    status ENUM('DRAFT', 'ORDERED', 'SHIPPED', 'RECEIVED', 'CANCELLED') DEFAULT 'DRAFT',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT
);

-- 12. purchase_order_items
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- 13. promotions
CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,
    name VARCHAR(100) NOT NULL,
    discount_type ENUM('PERCENTAGE', 'FIXED') NOT NULL,
    discount_value DECIMAL(10, 2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- =====================================================
-- SEED DATA
-- =====================================================

-- Seed Roles
INSERT IGNORE INTO roles (id, name) VALUES
(1, 'Owner'),
(2, 'Admin'),
(3, 'Kasir'),
(4, 'Customer'),
(5, 'Supplier');

-- Seed Categories
INSERT IGNORE INTO categories (id, name) VALUES
(1, 'Kopi Espresso Based'),
(2, 'Kopi Susu'),
(3, 'Non-Kopi'),
(4, 'Makanan Ringan'),
(5, 'Biji Kopi');

-- Seed Products (10 Produk)
INSERT IGNORE INTO products (id, category_id, name, description, price, image, is_available) VALUES
(1, 1, 'Americano', 'Espresso dengan tambahan air panas. Rasa pahit yang bersih dan menyegarkan, cocok untuk yang suka kopi tanpa susu.', 15000, NULL, 1),
(2, 1, 'Caffe Latte', 'Espresso dengan susu steam yang creamy. Rasanya lembut dengan aroma kopi yang kuat.', 20000, NULL, 1),
(3, 2, 'Kopi Susu Gula Aren', 'Kopi susu kekinian dengan gula aren asli. Manis alami dengan rasa karamel yang khas.', 18000, NULL, 1),
(4, 2, 'Kopi Susu Pandan', 'Kopi susu dengan sirup pandan yang wangi. Perpaduan unik kopi dan pandan yang segar.', 22000, NULL, 1),
(5, 3, 'Matcha Latte', 'Serbuk teh hijau Jepang premium dengan susu segar. Antioksidan tinggi, rasa creamy dan sedikit pahit.', 25000, NULL, 1),
(6, 3, 'Red Velvet Latte', 'Minuman manis dengan rasa seperti kue red velvet yang legendaris. Tidak mengandung kafein.', 25000, NULL, 1),
(7, 3, 'Taro Latte', 'Minuman susu dengan ekstrak ubi ungu asli. Warna cantik, rasa manis dan creamy.', 22000, NULL, 1),
(8, 4, 'Croissant Butter', 'Roti pastry renyah di luar, lembut di dalam dengan aroma mentega yang gurih.', 20000, NULL, 1),
(9, 4, 'French Fries', 'Kentang goreng renyah dengan bumbu BBQ spesial. Teman setia ngopi yang nggak ada habisnya.', 15000, NULL, 1),
(10, 5, 'Biji Kopi Arabica 200g', 'Biji kopi arabica pilihan dari petani lokal, proses full wash. Aroma floral, rasa sedikit asam.', 65000, NULL, 1);

-- Seed Stock (Stok Awal)
INSERT IGNORE INTO stock (product_id, current_stock, minimum_stock, unit) VALUES
(1, 100, 20, 'Cup'),
(2, 100, 20, 'Cup'),
(3, 100, 20, 'Cup'),
(4, 100, 20, 'Cup'),
(5, 50, 10, 'Cup'),
(6, 50, 10, 'Cup'),
(7, 50, 10, 'Cup'),
(8, 30, 10, 'Pcs'),
(9, 40, 10, 'Porsi'),
(10, 15, 5, 'Pack');

-- Seed Suppliers
INSERT IGNORE INTO suppliers (id, user_id, name, contact_name, phone, email, address) VALUES
(1, NULL, 'CV. Kopi Nusantara', 'Bapak Ahmad', '081200001111', 'supplier@kopimahasiswa.com', 'Jl. Kebun Kopi No. 1, Bogor');
