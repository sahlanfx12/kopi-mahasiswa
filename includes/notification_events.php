<?php
/** Stage 4 event helpers. All recipients are resolved from role relations. */
require_once __DIR__ . '/notification.php';

function notification_event_role_ids(mysqli $conn, string $roleName, bool $activeOnly = true): ?array
{
    try {
        $sql = 'SELECT DISTINCT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE LOWER(r.name) = LOWER(?)';
        if ($activeOnly) $sql .= " AND u.status = 'active'";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $roleName);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result->fetch_assoc()) $ids[] = (int)$row['id'];
        $stmt->close();
        return $ids;
    } catch (Throwable $e) {
        error_log('Notification recipient lookup failed.');
        return null;
    }
}

function notification_event_supplier_user_id(mysqli $conn, int $supplierId): ?int
{
    try {
        $stmt = $conn->prepare("SELECT u.id FROM suppliers s JOIN users u ON u.id = s.user_id JOIN roles r ON r.id = u.role_id WHERE s.id = ? AND LOWER(r.name) = 'supplier' AND u.status = 'active' LIMIT 2");
        if (!$stmt) return null;
        $stmt->bind_param('i', $supplierId);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $result = $stmt->get_result();
        if ($result->num_rows !== 1) {
            $stmt->close();
            error_log('PO notification skipped: supplier account is missing or ambiguous.');
            return null;
        }
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)$row['id'];
    } catch (Throwable $e) {
        error_log('PO supplier recipient lookup failed.');
        return null;
    }
}

function notification_event_send(mysqli $conn, int $userId, string $title, string $body, string $type = 'info'): bool
{
    try {
        return send_notification($userId, $title, $body, $type);
    } catch (Throwable $e) {
        error_log('Notification insert failed.');
        return false;
    }
}

function notification_event_send_roles(mysqli $conn, array $roles, string $title, string $body, string $type = 'info'): bool
{
    $ids = [];
    foreach ($roles as $role) {
        $roleIds = notification_event_role_ids($conn, $role);
        if ($roleIds === null) return false;
        $ids = array_merge($ids, $roleIds);
    }
    foreach (array_unique($ids) as $userId) {
        if (!notification_event_send($conn, (int)$userId, $title, $body, $type)) return false;
    }
    return true;
}

function notification_event_send_active_customers(mysqli $conn, string $title, string $body, string $type = 'info'): bool
{
    $ids = notification_event_role_ids($conn, 'Customer');
    if ($ids === null) return false;
    foreach ($ids as $userId) {
        if (!notification_event_send($conn, $userId, $title, $body, $type)) return false;
    }
    return true;
}

function notification_event_stock_change(mysqli $conn, string $productName, int $oldStock, int $newStock, int $minimumStock): bool
{
    $events = [];
    if ($oldStock > 0 && $newStock === 0) {
        $events[] = [['Admin'], 'Stok habis', 'Stok ' . $productName . ' habis.', 'danger'];
        $events[] = [['Owner'], 'Stok kritis', 'Stok ' . $productName . ' berubah menjadi nol.', 'danger'];
    } elseif ($oldStock > $minimumStock && $newStock > 0 && $newStock <= $minimumStock) {
        $events[] = [['Admin'], 'Stok rendah', 'Stok ' . $productName . ' mencapai atau melewati batas minimum (' . $newStock . ' tersisa).', 'warning'];
    }
    foreach ($events as [$roles, $title, $body, $type]) {
        if (!notification_event_send_roles($conn, $roles, $title, $body, $type)) return false;
    }
    return true;
}
