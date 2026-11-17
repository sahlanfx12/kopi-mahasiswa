<?php
/** Shared, owner-scoped Notification Center helpers. */

function notification_center_unread_count(mysqli $conn, int $userId): int
{
    try {
        $stmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0');
        if (!$stmt) return 0;
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) { $stmt->close(); return 0; }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['unread_count'] ?? 0);
    } catch (Throwable $e) {
        error_log('Notification Center unread count query failed.');
        return 0;
    }
}

function notification_center_list(mysqli $conn, int $userId): ?array
{
    try {
        $stmt = $conn->prepare('SELECT id, title, body, type, is_read, link, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100');
        if (!$stmt) return null;
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        error_log('Notification Center list query failed.');
        return null;
    }
}

function notification_center_mark_one_read(mysqli $conn, int $notificationId, int $userId): bool
{
    try {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0');
        if (!$stmt) return false;
        $stmt->bind_param('ii', $notificationId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log('Notification Center mark-read query failed.');
        return false;
    }
}

function notification_center_safe_link(?string $link): ?string
{
    if ($link === null || $link === '' || strlen($link) > 255) return null;
    if (preg_match('/[\\x00-\\x20\\x7f\\\\]/', $link) || str_contains($link, '..')) return null;
    if (!preg_match('/^[A-Za-z0-9_-]+(?:\\/[A-Za-z0-9_-]+)*\\.php(?:\\?[A-Za-z0-9_-]+=[A-Za-z0-9_.-]*(?:&[A-Za-z0-9_-]+=[A-Za-z0-9_.-]*)*)?$/', $link)) return null;
    return $link;
}

function notification_center_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function notification_center_render(mysqli $conn, int $userId, bool $supplierPortal = false): void
{
    $items = notification_center_list($conn, $userId);
    $types = [
        'info' => ['Informasi', 'text-sky-300 bg-sky-500/10 border-sky-500/30'],
        'warning' => ['Peringatan', 'text-amber-300 bg-amber-500/10 border-amber-500/30'],
        'danger' => ['Penting', 'text-rose-300 bg-rose-500/10 border-rose-500/30'],
        'success' => ['Berhasil', 'text-emerald-300 bg-emerald-500/10 border-emerald-500/30'],
    ];
    ?>
    <section class="<?= $supplierPortal ? 'w-full' : 'max-w-4xl mx-auto px-4 sm:px-6 py-8 w-full flex-grow' ?>" aria-label="Daftar notifikasi">
        <?php if (!$supplierPortal): ?>
        <div class="flex items-center justify-between gap-4 mb-6">
            <div><p class="text-xs uppercase tracking-widest text-amber-400 font-bold">Pusat Informasi</p><h1 class="text-2xl font-extrabold text-white">Notifikasi</h1></div>
            <a href="index.php" class="text-sm text-stone-300 hover:text-white">Kembali ke Dashboard</a>
        </div>
        <?php endif; ?>
        <?php if ($items === null): ?>
            <?php if ($supplierPortal): ?>
                <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-5 text-rose-200" role="status">Notifikasi belum dapat dimuat. Silakan coba kembali.</div>
            <?php else: ?>
                <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-rose-200" role="status">Notifikasi belum dapat dimuat. Silakan coba kembali.</div>
            <?php endif; ?>
        <?php elseif (!$items): ?>
            <?php if ($supplierPortal): ?>
                <div class="rounded-2xl border border-stone-800 bg-[#0e131d]/80 px-6 py-12 text-center shadow-lg">
                    <i data-lucide="bell-off" class="mx-auto mb-4 h-10 w-10 text-stone-500"></i>
                    <p class="font-semibold text-white">Belum ada notifikasi</p>
                    <p class="mt-2 text-sm text-stone-400">Pembaruan pesanan dan informasi untuk akun Anda akan muncul di sini.</p>
                </div>
            <?php else: ?>
                <div class="rounded-xl border border-stone-800 bg-[#0e131d] p-8 text-center text-stone-400">Belum ada notifikasi.</div>
            <?php endif; ?>
        <?php else: ?>
            <div class="space-y-3">
            <?php foreach ($items as $item):
                $type = $types[$item['type'] ?? ''] ?? ['Notifikasi', 'text-stone-300 bg-stone-800 border-stone-700'];
                $read = (int)($item['is_read'] ?? 0) === 1;
                $safeLink = notification_center_safe_link(isset($item['link']) ? (string)$item['link'] : null);
                $created = null;
                try { $created = !empty($item['created_at']) ? (new DateTimeImmutable((string)$item['created_at']))->format('d/m/Y H:i') : null; } catch (Throwable $e) { $created = null; }
            ?>
                <article class="<?= $supplierPortal ? 'rounded-2xl' : 'rounded-xl' ?> border <?= $read ? 'border-stone-800 bg-[#0e131d]/80' : 'border-amber-500/30 bg-[#141b29]' ?> p-4 sm:p-5">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="rounded-full border px-2.5 py-1 text-xs font-semibold <?= $type[1] ?>"><?= notification_center_h($type[0]) ?></span>
                        <span class="text-xs <?= $read ? 'text-stone-500' : 'text-amber-300' ?>"><?= $read ? 'Sudah dibaca' : 'Belum dibaca' ?></span>
                        <?php if ($created !== null): ?><time class="ml-auto text-xs text-stone-500"><?= notification_center_h($created) ?></time><?php endif; ?>
                    </div>
                    <h2 class="font-bold text-white"><?= notification_center_h((string)$item['title']) ?></h2>
                    <?php if (!empty($item['body'])): ?><p class="mt-2 text-sm leading-relaxed text-stone-300 whitespace-pre-line"><?= notification_center_h((string)$item['body']) ?></p><?php endif; ?>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <?php if ($safeLink !== null): ?><a class="text-sm font-semibold text-amber-300 hover:text-amber-200" href="<?= notification_center_h($safeLink) ?>">Buka detail</a><?php endif; ?>
                        <?php if (!$read): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?= (int)$item['id'] ?>">
                                <?= csrf_input() ?>
                                <button type="submit" class="<?= $supplierPortal ? 'inline-flex min-h-10 items-center rounded-lg border border-stone-700 px-3 py-2 text-sm font-semibold text-stone-300 hover:text-white hover:border-stone-500' : 'text-sm font-semibold text-stone-300 hover:text-white' ?>">Tandai sudah dibaca</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
}
