<footer class="border-t border-stone-800/80 bg-[#0e131d]/90 backdrop-blur-md py-6 text-center text-xs text-stone-400 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-stone-400 text-xs">
            <span class="inline-block w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.6)]"></span>
            <span class="font-semibold text-stone-300">Kopi Mahasiswa</span>
            <span class="text-stone-600">&bull;</span>
            <span class="text-stone-400">Terminal Operasional</span>
        </div>
        <div class="text-xs text-stone-400 font-medium tracking-wider app-watermark">
            Built by Sahlan
        </div>
    </div>
</footer>
<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

<?php
// ─── Chatbot+ Widget Injection (role-aware) ───────────────────────────────
// Injeksi widget hanya jika sesi valid dan file komponen tersedia.
// Customer menggunakan widget terpisah di customer/footer.php.
// Session harus dimulai di file induk (misalnya config.php) sebelum output HTML dikirim.
// Jangan memulai session di sini karena footer dipanggil setelah markup HTML.
$__current_role = strtolower($_SESSION['role'] ?? '');
$__widget_file  = __DIR__ . '/chatbot_widget.php';

if (!empty($_SESSION['user_id']) && file_exists($__widget_file)) {
    $chatbot_config = null;
    if (isset($conn) && $conn instanceof mysqli) {
        $identity_stmt = $conn->prepare(
            "SELECT u.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND LOWER(r.name) = ? LIMIT 1"
        );
        if ($identity_stmt) {
            $identity_user_id = (int)$_SESSION['user_id'];
            $identity_stmt->bind_param("is", $identity_user_id, $__current_role);
            if ($identity_stmt->execute()) {
                $identity_row = $identity_stmt->get_result()->fetch_assoc();
                if ($identity_row && trim((string)$identity_row['name']) !== '') {
                    $_SESSION['name'] = trim((string)$identity_row['name']);
                }
            }
            $identity_stmt->close();
        }
    }
    $__current_name = trim((string)($_SESSION['name'] ?? ''));
    if ($__current_name === '') $__current_name = 'Pengguna';

    if ($__current_role === 'admin') {
        $chatbot_config = [
            'role'        => 'admin',
            'api_endpoint'=> 'ai_api.php',
            'title'       => 'AI Operational Assistant',
            'subtitle'    => 'Inventori, stok, PO & analisis restock',
            'welcome'     => 'Halo ' . $__current_name . '! Saya AI Operational Assistant. Tanyakan tentang inventori, stok menipis, Purchase Order, atau analisis restock. 📦',
            'placeholder' => 'Tanya inventori, PO, restock...',
            'accent'      => 'amber',
            'icon'        => 'bot',
            'session_key' => 'admin_ai_chat_history',
        ];
    } elseif ($__current_role === 'owner') {
        $chatbot_config = [
            'role'        => 'owner',
            'api_endpoint'=> 'ai_api.php',
            'title'       => 'AI Executive Advisor',
            'subtitle'    => 'Analisis bisnis, omzet & strategi',
            'welcome'     => 'Halo ' . $__current_name . '! Saya AI Executive Business Advisor Kopi Mahasiswa. Tanyakan tentang omzet, produk terlaris, tren penjualan, atau rekomendasi strategi. 📊',
            'placeholder' => 'Tanya omzet, tren, strategi...',
            'accent'      => 'purple',
            'icon'        => 'sparkles',
            'session_key' => 'owner_ai_chat_history',
        ];
    } elseif ($__current_role === 'kasir') {
        $chatbot_config = [
            'role'        => 'kasir',
            'api_endpoint'=> 'ai_api.php',
            'title'       => 'AI Operational Helper',
            'subtitle'    => 'Panduan POS, status order & shift',
            'welcome'     => 'Halo ' . $__current_name . '! Saya AI Helper. Tanyakan tentang alur POS, status pesanan hari ini, atau produk yang tersedia. 🧾',
            'placeholder' => 'Tanya order, produk, alur POS...',
            'accent'      => 'emerald',
            'icon'        => 'headset',
            'session_key' => 'kasir_ai_chat_history',
        ];
    } elseif ($__current_role === 'supplier') {
        $chatbot_config = [
            'role'        => 'supplier',
            'api_endpoint'=> 'ai_api.php',
            'title'       => 'AI Supplier Assistant',
            'subtitle'    => 'Status PO & panduan pengiriman',
            'welcome'     => 'Halo ' . $__current_name . '! Saya AI Supplier Assistant. Tanyakan tentang status Purchase Order Anda, alur pengiriman, atau informasi PO yang masuk. 🚚',
            'placeholder' => 'Tanya status PO, pengiriman...',
            'accent'      => 'cyan',
            'icon'        => 'truck',
            'session_key' => 'supplier_ai_chat_history',
        ];
    }

    if ($chatbot_config !== null) {
        require $__widget_file;
    }
}
unset($__current_role, $__current_name, $__widget_file, $chatbot_config);
?>
