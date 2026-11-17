<?php
/**
 * KOPI MAHASISWA — Shared Chatbot+ Widget Component
 * Digunakan oleh: Admin, Owner, Kasir, Supplier (via theme_footer.php)
 * Customer menggunakan widget terpisah di customer/footer.php
 *
 * Parameter yang diharapkan dari caller (set via $chatbot_config):
 *   $chatbot_config = [
 *       'role'         => 'admin',           // role slug (admin|owner|kasir|supplier)
 *       'api_endpoint' => 'ai_api.php',      // path relatif ke endpoint
 *       'title'        => 'AI Assistant',    // judul header chat
 *       'subtitle'     => 'Sub-judul',       // sub-judul header
 *       'welcome'      => 'Pesan sambutan',  // pesan awal
 *       'placeholder'  => 'Ketik pesan...', // placeholder input
 *       'accent'       => 'amber',           // warna aksen: amber|purple|cyan|emerald
 *       'icon'         => 'bot',             // lucide icon name
 *       'session_key'  => 'admin_ai_chat_history', // session key riwayat
 *   ];
 */

// Hanya render jika $chatbot_config tersedia dan ada sesi yang valid
if (!isset($chatbot_config) || !is_array($chatbot_config)) return;
if (empty($_SESSION['user_id'])) return;

$cb_role     = htmlspecialchars($chatbot_config['role'] ?? 'staff', ENT_QUOTES, 'UTF-8');
$cb_endpoint = htmlspecialchars($chatbot_config['api_endpoint'] ?? 'ai_api.php', ENT_QUOTES, 'UTF-8');
$cb_title    = htmlspecialchars($chatbot_config['title'] ?? 'AI Assistant', ENT_QUOTES, 'UTF-8');
$cb_subtitle = htmlspecialchars($chatbot_config['subtitle'] ?? 'Asisten operasional', ENT_QUOTES, 'UTF-8');
$cb_welcome  = htmlspecialchars($chatbot_config['welcome'] ?? 'Halo! Ada yang bisa saya bantu?', ENT_QUOTES, 'UTF-8');
$cb_placeholder = htmlspecialchars($chatbot_config['placeholder'] ?? 'Ketik pertanyaan Anda...', ENT_QUOTES, 'UTF-8');
$cb_icon     = htmlspecialchars($chatbot_config['icon'] ?? 'bot', ENT_QUOTES, 'UTF-8');
$cb_session_key = $chatbot_config['session_key'] ?? ($cb_role . '_ai_chat_history');

// Palet warna berdasarkan accent
$accent = $chatbot_config['accent'] ?? 'amber';
$accent_colors = [
    'amber'   => ['btn_grad' => 'linear-gradient(135deg,#f59e0b,#d97706)', 'btn_shadow' => 'rgba(245,158,11,0.3)',  'border' => 'rgba(245,158,11,0.4)', 'icon_bg' => 'rgba(245,158,11,0.1)',  'icon_text' => '#f59e0b', 'badge_bg' => 'rgba(245,158,11,0.1)',  'badge_border' => 'rgba(245,158,11,0.25)', 'badge_text' => '#fbbf24', 'dot_color' => '#f59e0b'],
    'purple'  => ['btn_grad' => 'linear-gradient(135deg,#a855f7,#7c3aed)', 'btn_shadow' => 'rgba(168,85,247,0.3)', 'border' => 'rgba(168,85,247,0.4)', 'icon_bg' => 'rgba(168,85,247,0.1)', 'icon_text' => '#a855f7', 'badge_bg' => 'rgba(168,85,247,0.1)', 'badge_border' => 'rgba(168,85,247,0.25)', 'badge_text' => '#c084fc', 'dot_color' => '#a855f7'],
    'cyan'    => ['btn_grad' => 'linear-gradient(135deg,#06b6d4,#0891b2)', 'btn_shadow' => 'rgba(6,182,212,0.3)',  'border' => 'rgba(6,182,212,0.4)',  'icon_bg' => 'rgba(6,182,212,0.1)',  'icon_text' => '#06b6d4', 'badge_bg' => 'rgba(6,182,212,0.1)',  'badge_border' => 'rgba(6,182,212,0.25)',  'badge_text' => '#22d3ee', 'dot_color' => '#06b6d4'],
    'emerald' => ['btn_grad' => 'linear-gradient(135deg,#10b981,#059669)', 'btn_shadow' => 'rgba(16,185,129,0.3)', 'border' => 'rgba(16,185,129,0.4)', 'icon_bg' => 'rgba(16,185,129,0.1)', 'icon_text' => '#10b981', 'badge_bg' => 'rgba(16,185,129,0.1)', 'badge_border' => 'rgba(16,185,129,0.25)', 'badge_text' => '#34d399', 'dot_color' => '#10b981'],
];
$c = $accent_colors[$accent] ?? $accent_colors['amber'];

$unique_id = 'cb_' . $cb_role; // unique prefix for DOM IDs

// Riwayat percakapan dari session
$cb_history = (isset($_SESSION[$cb_session_key]) && is_array($_SESSION[$cb_session_key]))
              ? $_SESSION[$cb_session_key] : [];
?>

<!-- ================================================================= -->
<!-- CHATBOT+ WIDGET — <?= strtoupper($cb_role) ?> (Kopi Mahasiswa)    -->
<!-- ================================================================= -->
<div id="<?= $unique_id ?>_container" class="fixed bottom-6 right-6 z-50 select-none">
    <!-- Floating Launcher Button -->
    <div class="relative group">
        <!-- Tooltip -->
        <div class="absolute right-16 top-1/2 -translate-y-1/2 pointer-events-none opacity-0 group-hover:opacity-100 transition-all duration-200 translate-x-2 group-hover:translate-x-0 hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#0e131d] text-slate-100 text-xs font-semibold shadow-xl border border-stone-800 whitespace-nowrap z-50">
            <?= $cb_title ?>
        </div>

        <button id="<?= $unique_id ?>_btn"
                type="button"
                aria-label="Buka <?= $cb_title ?>"
                class="relative w-14 h-14 rounded-2xl flex items-center justify-center focus:outline-none transition-all duration-200 transform hover:-translate-y-1 hover:scale-105 active:translate-y-0 active:scale-95"
                style="background:#0e131d; border:1px solid <?= $c['border'] ?>; box-shadow:0 8px 25px <?= $c['btn_shadow'] ?>;">
            <div class="w-7 h-7 rounded-xl flex items-center justify-center" style="background:<?= $c['icon_bg'] ?>; color:<?= $c['icon_text'] ?>;">
                <i data-lucide="<?= $cb_icon ?>" class="w-5 h-5"></i>
            </div>
        </button>
    </div>

    <!-- Chat Panel -->
    <div id="<?= $unique_id ?>_window"
         class="chat-hidden w-[92vw] sm:w-[420px] max-h-[84vh] rounded-2xl flex flex-col absolute bottom-16 right-0 overflow-hidden"
         style="height:560px; background:linear-gradient(160deg,#131926 0%,#0e131d 60%,#090c12 100%); border:1px solid rgba(255,255,255,0.08); box-shadow:0 32px 64px -16px rgba(0,0,0,0.7),0 0 0 1px <?= $c['border'] ?>,inset 0 1px 0 rgba(255,255,255,0.04);">

        <!-- Header -->
        <div class="px-4 py-3 flex items-center justify-between shrink-0"
             style="background:linear-gradient(135deg,#1a2232 0%,#131926 100%); border-bottom:1px solid rgba(255,255,255,0.07);">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                     style="background:<?= $c['icon_bg'] ?>; border:1px solid <?= $c['border'] ?>;">
                    <i data-lucide="<?= $cb_icon ?>" class="w-5 h-5" style="color:<?= $c['icon_text'] ?>;"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="font-bold text-slate-100 leading-tight truncate" style="font-size:13px;"><?= $cb_title ?></h3>
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full hidden sm:inline-block"
                              style="background:<?= $c['badge_bg'] ?>; color:<?= $c['badge_text'] ?>; border:1px solid <?= $c['badge_border'] ?>;">AI</span>
                    </div>
                    <p class="text-stone-400 mt-0.5 truncate" style="font-size:11px;"><?= $cb_subtitle ?></p>
                </div>
            </div>
            <div class="flex items-center gap-1 shrink-0 ml-2">
                <button type="button"
                        title="Bersihkan Percakapan"
                        aria-label="Bersihkan Percakapan"
                        class="w-7 h-7 rounded-lg flex items-center justify-center text-stone-400 hover:text-amber-400 focus:outline-none shrink-0"
                        style="transition:background .15s,color .15s;"
                        onmouseenter="this.style.background='rgba(255,255,255,0.08)'"
                        onmouseleave="this.style.background='transparent'"
                        onclick="<?= $unique_id ?>_clearHistory()">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </button>
                <button id="<?= $unique_id ?>_close"
                        aria-label="Tutup Chat"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-stone-400 hover:text-slate-100 focus:outline-none shrink-0"
                        style="transition:background .15s,color .15s;"
                        onmouseenter="this.style.background='rgba(255,255,255,0.08)'"
                        onmouseleave="this.style.background='transparent'">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div id="<?= $unique_id ?>_messages" class="flex-grow overflow-y-auto"
             style="padding:16px; display:flex; flex-direction:column; gap:14px; background:linear-gradient(180deg,#0d1220 0%,#090c12 100%);">

            <!-- Welcome Message -->
            <div class="flex items-start gap-2.5">
                <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                     style="background:<?= $c['icon_bg'] ?>; border:1px solid <?= $c['border'] ?>;">
                    <i data-lucide="<?= $cb_icon ?>" class="w-3.5 h-3.5" style="color:<?= $c['icon_text'] ?>;"></i>
                </div>
                <div style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 14px 14px 14px; padding:10px 14px; max-width:calc(100% - 40px); font-size:12.5px; line-height:1.65; color:#e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.25);">
                    <?= $cb_welcome ?>
                </div>
            </div>

            <!-- Render Session History -->
            <?php if (!empty($cb_history)): ?>
                <?php foreach ($cb_history as $item): ?>
                    <?php if (($item['role'] ?? '') === 'user'): ?>
                        <div class="flex justify-end">
                            <div style="background:<?= $c['btn_grad'] ?>; border-radius:14px 4px 14px 14px; padding:10px 14px; max-width:80%; font-size:12.5px; line-height:1.65; font-weight:500; color:#1c1100; box-shadow:0 3px 10px <?= $c['btn_shadow'] ?>; word-break:break-word;">
                                <?= nl2br(htmlspecialchars($item['content'] ?? '')) ?>
                            </div>
                        </div>
                    <?php elseif (($item['role'] ?? '') === 'assistant'): ?>
                        <div class="flex items-start gap-2.5">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                                 style="background:<?= $c['icon_bg'] ?>; border:1px solid <?= $c['border'] ?>;">
                                <i data-lucide="<?= $cb_icon ?>" class="w-3.5 h-3.5" style="color:<?= $c['icon_text'] ?>;"></i>
                            </div>
                            <div data-ai-reply style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 14px 14px 14px; padding:10px 14px; max-width:calc(100% - 40px); font-size:12.5px; line-height:1.65; color:#e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.25); word-break:break-word;">
                                <?= nl2br(htmlspecialchars($item['content'] ?? '')) ?>
                                <div class="mt-2 pt-1 border-t border-white/10">
                                    <button type="button" data-copy-answer aria-label="Salin Jawaban"
                                            class="text-[10px] font-semibold text-stone-400 hover:text-white transition-colors">
                                        Salin Jawaban
                                    </button>
                                </div>
                                <span class="km-ai-copy-source" hidden><?= htmlspecialchars((string)($item['content'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Input -->
        <div class="shrink-0 px-3.5 py-3" style="background:#0e131d; border-top:1px solid rgba(255,255,255,0.06);">
            <form id="<?= $unique_id ?>_form" class="flex items-center gap-2" onsubmit="event.preventDefault(); <?= $unique_id ?>_sendMessage();">
                <input type="text" id="<?= $unique_id ?>_input"
                       placeholder="<?= $cb_placeholder ?>"
                       autocomplete="off"
                       class="w-full outline-none"
                       style="background:#131926; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:10px 14px; font-size:12.5px; color:#e2e8f0; font-family:inherit; transition:border-color .2s,box-shadow .2s;"
                       onfocus="this.style.borderColor='<?= $c['border'] ?>';this.style.boxShadow='0 0 0 2px <?= str_replace(')', ',0.15)', $c['btn_shadow']) ?>';"
                       onblur="this.style.borderColor='rgba(255,255,255,0.08)';this.style.boxShadow='none';"
                       required>
                <button type="submit" id="<?= $unique_id ?>_submit_btn"
                        aria-label="Kirim Pesan"
                        class="shrink-0 flex items-center justify-center w-10 h-10 rounded-xl font-bold disabled:opacity-40 disabled:pointer-events-none"
                        style="background:<?= $c['btn_grad'] ?>; box-shadow:0 4px 12px <?= $c['btn_shadow'] ?>; color:#0e0e0e; transition:transform .15s,box-shadow .15s;"
                        onmouseenter="if(!this.disabled){this.style.transform='translateY(-1px)';}"
                        onmouseleave="this.style.transform='';"
                        onmousedown="this.style.transform='scale(0.94)';"
                        onmouseup="this.style.transform='';">
                    <i data-lucide="send" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const uid        = '<?= $unique_id ?>';
    const apiUrl     = '<?= $cb_endpoint ?>';
    const iconSvg    = '<?= $cb_icon ?>';
    const iconBg     = '<?= $c['icon_bg'] ?>';
    const iconColor  = '<?= $c['icon_text'] ?>';
    const iconBorder = '<?= $c['border'] ?>';
    const btnGrad    = '<?= $c['btn_grad'] ?>';
    const btnShadow  = '<?= $c['btn_shadow'] ?>';
    const dotColor   = '<?= $c['dot_color'] ?>';

    const btn       = document.getElementById(uid + '_btn');
    const win       = document.getElementById(uid + '_window');
    const closeBtn  = document.getElementById(uid + '_close');
    const input     = document.getElementById(uid + '_input');
    const submitBtn = document.getElementById(uid + '_submit_btn');
    const msgs      = document.getElementById(uid + '_messages');
    let isSending = false;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function renderMarkdown(text) {
        const lines = escapeHtml(text).split(/\r\n?|\n/);
        let html = '';
        let inList = false;

        function renderInline(line) {
            return line.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        }

        lines.forEach((line, index) => {
            const bullet = line.match(/^\s*[-*]\s+(.+)$/);
            if (bullet) {
                if (!inList) {
                    if (html) html += '<br>';
                    html += '<ul class="list-disc pl-4 space-y-1">';
                    inList = true;
                }
                html += `<li>${renderInline(bullet[1])}</li>`;
                return;
            }

            if (inList) {
                html += '</ul>';
                inList = false;
            }
            if (index > 0) html += '<br>';
            html += renderInline(line);
        });

        if (inList) html += '</ul>';
        return html;
    }

    function aiAvatar() {
        return `<div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                     style="background:${iconBg}; border:1px solid ${iconBorder};">
                    <i data-lucide="${iconSvg}" class="w-3.5 h-3.5" style="color:${iconColor};"></i>
                </div>`;
    }

    function aiBubble(htmlContent, rawText) {
        return `<div class="flex items-start gap-2.5">
                    ${aiAvatar()}
                    <div data-ai-reply style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 14px 14px 14px; padding:10px 14px; max-width:calc(100% - 40px); font-size:12.5px; line-height:1.65; color:#e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.25); word-break:break-word;">
                        ${htmlContent}
                        <div class="mt-2 pt-1 border-t border-white/10">
                            <button type="button" data-copy-answer aria-label="Salin Jawaban"
                                    class="text-[10px] font-semibold text-stone-400 hover:text-white transition-colors">
                                Salin Jawaban
                            </button>
                        </div>
                        <span class="km-ai-copy-source" hidden>${escapeHtml(rawText)}</span>
                    </div>
                </div>`;
    }

    function userBubble(text) {
        return `<div class="flex justify-end">
                    <div style="background:${btnGrad}; border-radius:14px 4px 14px 14px; padding:10px 14px; max-width:80%; font-size:12.5px; line-height:1.65; font-weight:500; color:#1c1100; box-shadow:0 3px 10px ${btnShadow}; word-break:break-word;">
                        ${escapeHtml(text)}
                    </div>
                </div>`;
    }

    function typingBubble(id) {
        return `<div id="${id}" class="flex items-start gap-2.5">
                    ${aiAvatar()}
                    <div style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 14px 14px 14px; padding:13px 16px; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(0,0,0,0.25);">
                        <span class="typing-dot" style="background:${dotColor};"></span>
                        <span class="typing-dot" style="background:${dotColor};"></span>
                        <span class="typing-dot" style="background:${dotColor};"></span>
                    </div>
                </div>`;
    }

    function errorBubble() {
        return `<div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5" style="background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3);">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5" style="color:#f87171;"></i>
                    </div>
                    <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:4px 14px 14px 14px; padding:10px 14px; font-size:12.5px; line-height:1.6; color:#fca5a5;">
                        Gagal terhubung ke AI. Silakan coba sesaat lagi.
                    </div>
                </div>`;
    }

    function scrollBottom() {
        if (msgs) msgs.scrollTop = msgs.scrollHeight;
    }

    function reloadIcons() {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    if (msgs) {
        msgs.addEventListener('click', async function(event) {
            const button = event.target instanceof Element ? event.target.closest('[data-copy-answer]') : null;
            if (!button || !window.KMUI || typeof window.KMUI.copyText !== 'function') return;
            const reply = button.closest('[data-ai-reply]');
            const source = reply ? reply.querySelector('.km-ai-copy-source') : null;
            if (!source) {
                window.KMUI.toast('error', 'Teks jawaban tidak tersedia untuk disalin.');
                return;
            }
            const copied = await window.KMUI.copyText(source.textContent);
            window.KMUI.toast(copied ? 'success' : 'error', copied
                ? 'Jawaban berhasil disalin.'
                : 'Gagal menyalin jawaban. Silakan salin teks secara manual.');
        });
    }

    // Toggle open/close
    if (btn && win) {
        btn.addEventListener('click', () => {
            const isHidden = win.classList.contains('chat-hidden');
            if (isHidden) {
                win.classList.remove('chat-hidden');
                win.classList.add('chat-visible');
                scrollBottom();
                setTimeout(() => { if (input) input.focus(); }, 220);
            } else {
                win.classList.remove('chat-visible');
                win.classList.add('chat-hidden');
            }
            reloadIcons();
        });
    }

    if (closeBtn && win) {
        closeBtn.addEventListener('click', () => {
            win.classList.remove('chat-visible');
            win.classList.add('chat-hidden');
        });
    }

    // Clear history
    window[uid + '_clearHistory'] = function() {
        if (!window.KMUI || !window.KMUI.confirm) return;
        window.KMUI.confirm({
            title: 'Hapus riwayat percakapan',
            message: 'Riwayat percakapan AI pada akun ini akan dihapus.',
            confirmLabel: 'Hapus riwayat',
            danger: true
        }).then(function(confirmed) {
            if (!confirmed) return;
            return fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear_history' })
        }).then(response => {
            if (!response.ok) {
                throw new Error('Permintaan hapus riwayat gagal.');
            }
            return response.json();
        }).then(data => {
            if (!data || data.status !== 'success') {
                throw new Error('Server tidak mengonfirmasi penghapusan riwayat.');
            }
            if (!msgs) return;
            const firstMsg = msgs.querySelector('.flex');
            msgs.innerHTML = '';
            if (firstMsg) msgs.appendChild(firstMsg);
            reloadIcons();
            window.KMUI.toast('success', 'Riwayat percakapan berhasil dihapus.');
        }).catch(() => {
            window.KMUI.toast('error', 'Gagal menghapus riwayat percakapan. Silakan coba lagi.');
        });
        });
    };

    // Send message
    window[uid + '_sendMessage'] = function() {
        if (!input || !msgs || isSending) return;
        const msg = input.value.trim();
        if (!msg) return;

        isSending = true;
        msgs.innerHTML += userBubble(msg);
        input.value = '';
        scrollBottom();

        const loadingId = uid + '_load_' + Date.now();
        msgs.innerHTML += typingBubble(loadingId);
        scrollBottom();
        reloadIcons();

        if (submitBtn) submitBtn.disabled = true;

        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        })
        .then(res => res.json())
        .then(data => {
            const loader = document.getElementById(loadingId);
            if (loader) loader.remove();
            if (submitBtn) submitBtn.disabled = false;
            isSending = false;

            const rawText = data.reply
                ? data.reply
                : (data.error ? data.error : 'Maaf, terjadi kendala saat memproses respons AI.');
            msgs.innerHTML += aiBubble(renderMarkdown(rawText), rawText);
            scrollBottom();
            reloadIcons();
        })
        .catch(() => {
            const loader = document.getElementById(loadingId);
            if (loader) loader.remove();
            if (submitBtn) submitBtn.disabled = false;
            isSending = false;
            msgs.innerHTML += errorBubble();
            scrollBottom();
            reloadIcons();
        });
    };

    reloadIcons();
})();
</script>
