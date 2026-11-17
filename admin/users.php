<?php
require "../config.php";
include "header.php";

$users = $conn->query("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.role_id ASC, u.name ASC");
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold text-white tracking-tight">Manajemen Pengguna</h1>
        <p class="text-xs text-stone-400 mt-1">Daftar akun pengguna terdaftar di sistem Kopi Mahasiswa.</p>
    </div>
</div>

<div class="bg-[#0e131d]/90 border border-stone-800/80 rounded-2xl overflow-hidden shadow-xl">
    <div class="p-4 border-b border-stone-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="users" class="w-4 h-4 text-amber-400"></i>
            <h2 class="text-xs font-bold text-white uppercase tracking-wider">Daftar Akun Pengguna</h2>
        </div>
        <span class="text-xs text-stone-500 font-mono"><?= $users->num_rows ?> Akun Terdaftar</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-[#141b29] text-stone-400 uppercase tracking-wider text-[11px]">
                <tr>
                    <th class="p-4">Nama</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Telepon</th>
                    <th class="p-4">Role</th>
                    <th class="p-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-800/60">
                <?php while($u = $users->fetch_assoc()):
                    $role_badge = match(strtolower($u['role_name'])) {
                        'admin' => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
                        'owner' => 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
                        'manager' => 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30',
                        'kasir' => 'bg-blue-500/10 text-blue-400 border border-blue-500/30',
                        'supplier' => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30',
                        default => 'bg-stone-800 text-stone-300 border border-stone-700'
                    };
                    $status = $u['status'] ?? 'active';
                ?>
                <tr class="hover:bg-stone-800/30 transition-colors">
                    <td class="p-4 font-semibold text-white flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-stone-800 border border-stone-700 flex items-center justify-center text-xs font-bold text-amber-400">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <?= htmlspecialchars($u['name']) ?>
                    </td>
                    <td class="p-4 text-stone-400 font-mono"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="p-4 text-stone-400 font-mono"><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                    <td class="p-4">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide <?= $role_badge ?>">
                            <?= htmlspecialchars($u['role_name']) ?>
                        </span>
                    </td>
                    <td class="p-4">
                        <?php if ($status === 'suspended'): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/30">
                                <?= htmlspecialchars(app_status_label($status)) ?>
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <?= htmlspecialchars(app_status_label($status)) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include "footer.php"; ?>