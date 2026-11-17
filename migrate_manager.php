<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * KOPI MAHASISWA — PHASE B/C/D/E/F/G/H MIGRATION
 * Menambahkan: Manager role, permission system, presence, activity log,
 * messages, notifications, user status — TANPA mengubah tabel existing.
 */

$conn = new mysqli('localhost', 'root', '', 'kopi_mahasiswa');
if ($conn->connect_error) die("DB error: " . $conn->connect_error);

$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$steps = [];

// ======================================================
// 1. Add Manager role (id=6)
// ======================================================
$exists = $conn->query("SELECT id FROM roles WHERE name='Manager'")->fetch_assoc();
if (!$exists) {
    $conn->query("INSERT INTO roles (id, name) VALUES (6, 'Manager')");
    $steps[] = "Role 'Manager' (id=6) created";
} else {
    $steps[] = "Role 'Manager' already exists (id={$exists['id']})";
}

// ======================================================
// 2. Add status and last_active to users (preserve existing)
// ======================================================
$col_status = $conn->query("SHOW COLUMNS FROM users LIKE 'status'")->num_rows;
if (!$col_status) {
    $conn->query("ALTER TABLE users ADD COLUMN status ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER phone");
    $steps[] = "users.status column added";
}

$col_la = $conn->query("SHOW COLUMNS FROM users LIKE 'last_active'")->num_rows;
if (!$col_la) {
    $conn->query("ALTER TABLE users ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL AFTER status");
    $steps[] = "users.last_active column added";
}

// ======================================================
// 3. Permissions table
// ======================================================
$conn->query("CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$steps[] = "permissions table ready";

// ======================================================
// 4. Role Permissions table
// ======================================================
$conn->query("CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY uq_rp (role_id, permission_name)
)");
$steps[] = "role_permissions table ready";

// ======================================================
// 5. Seed permissions
// ======================================================
$perms = [
    // Customer
    ['view_menu', 'View product catalog'],
    ['view_product', 'View product detail'],
    ['manage_cart', 'Add/remove from cart'],
    ['create_order', 'Create order'],
    ['view_own_orders', 'View own order history'],
    ['use_customer_ai', 'Use Customer AI chatbot'],
    // Kasir
    ['access_pos', 'Access POS system'],
    ['create_transaction', 'Create transactions'],
    ['update_transaction_status', 'Update order status'],
    ['view_transactions', 'View transactions'],
    ['use_kasir_ai', 'Use Kasir AI chatbot'],
    // Admin
    ['manage_products', 'Full product CRUD'],
    ['manage_categories', 'Full category CRUD'],
    ['manage_inventory', 'Manage inventory/stock'],
    ['manage_purchase_orders', 'Create and manage POs'],
    ['manage_suppliers', 'Manage supplier relations'],
    ['use_admin_ai', 'Use Admin AI (inventory)'],
    // Owner
    ['view_reports', 'View business reports'],
    ['view_business_dashboard', 'View owner dashboard'],
    ['use_owner_ai', 'Use Owner AI (business insight)'],
    // Supplier
    ['view_own_po', 'View own Purchase Orders'],
    ['update_po_shipped', 'Mark PO as shipped'],
    ['use_supplier_ai', 'Use Supplier AI chatbot'],
    // Manager
    ['monitor_system', 'Monitor entire system'],
    ['manage_employees', 'View and manage employee accounts'],
    ['suspend_user', 'Suspend user accounts'],
    ['activate_user', 'Activate user accounts'],
    ['view_activity_logs', 'View audit/activity logs'],
    ['use_internal_chat', 'Use internal chat'],
    ['send_notifications', 'Send system notifications'],
    ['view_presence', 'View user presence/online status'],
    ['use_manager_ai', 'Use Manager AI agent'],
    ['execute_ai_commands', 'Execute AI action commands'],
];

$stmt = $conn->prepare("INSERT IGNORE INTO permissions (name, description) VALUES (?, ?)");
foreach ($perms as $p) {
    $stmt->bind_param("ss", $p[0], $p[1]);
    $stmt->execute();
}
$steps[] = count($perms) . " permissions seeded";

// ======================================================
// 6. Seed role_permissions
// ======================================================
// role_id: 1=Owner, 2=Admin, 3=Kasir, 4=Customer, 5=Supplier, 6=Manager
$role_perms = [
    4 => ['view_menu', 'view_product', 'manage_cart', 'create_order', 'view_own_orders', 'use_customer_ai'],
    3 => ['access_pos', 'create_transaction', 'update_transaction_status', 'view_transactions', 'view_menu', 'view_product', 'use_kasir_ai'],
    2 => ['manage_products', 'manage_categories', 'manage_inventory', 'manage_purchase_orders', 'manage_suppliers', 'use_admin_ai', 'view_menu', 'view_product'],
    1 => ['view_reports', 'view_business_dashboard', 'use_owner_ai'],
    5 => ['view_own_po', 'update_po_shipped', 'use_supplier_ai'],
    6 => ['monitor_system', 'manage_employees', 'suspend_user', 'activate_user', 'view_activity_logs', 'use_internal_chat', 'send_notifications', 'view_presence', 'use_manager_ai', 'execute_ai_commands', 'view_reports', 'view_business_dashboard', 'view_transactions', 'manage_inventory'],
];

$stmt2 = $conn->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_name) VALUES (?, ?)");
$total_rp = 0;
foreach ($role_perms as $role_id => $pnames) {
    foreach ($pnames as $pname) {
        $stmt2->bind_param("is", $role_id, $pname);
        $stmt2->execute();
        $total_rp++;
    }
}
$steps[] = "$total_rp role_permissions seeded";

// ======================================================
// 7. Activity Logs
// ======================================================
$conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_role VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    status ENUM('SUCCESS','FAILED','PENDING') DEFAULT 'SUCCESS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
)");
$steps[] = "activity_logs table ready";

// ======================================================
// 8. Messages (internal chat)
// ======================================================
$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender (sender_id),
    INDEX idx_receiver (receiver_id),
    INDEX idx_thread (sender_id, receiver_id)
)");
$steps[] = "messages table ready";

// ======================================================
// 9. Notifications
// ======================================================
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT,
    type ENUM('info','warning','danger','success') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_notif (user_id, is_read)
)");
$steps[] = "notifications table ready";

$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "=== MIGRATION COMPLETE ===\n";
foreach ($steps as $i => $s) {
    echo ($i+1) . ". $s\n";
}
echo "\nDone.\n";
?>
