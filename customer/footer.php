    </main>
    <footer class="border-t border-stone-800/80 bg-[#0a0d14] text-stone-400 mt-auto relative z-10 pb-24 sm:pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 text-stone-500">
                <span class="inline-block w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.6)]"></span>
                <span class="font-semibold text-stone-300">Kopi Mahasiswa</span>
                <span class="text-stone-600">&bull;</span>
                <span class="text-stone-400">Portal Layanan Pelanggan</span>
            </div>
            <div class="text-xs text-stone-400 font-medium tracking-wider sm:pr-24 app-watermark">
                Built by Sahlan
            </div>
        </div>
    </footer>

<?php
$chat_display_name = trim((string)($_SESSION['name'] ?? ''));
if (isset($conn) && $conn instanceof mysqli) {
    $identity_stmt = $conn->prepare(
        "SELECT u.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND LOWER(r.name) = 'customer' LIMIT 1"
    );
    if ($identity_stmt) {
        $identity_user_id = (int)($_SESSION['user_id'] ?? 0);
        $identity_stmt->bind_param("i", $identity_user_id);
        if ($identity_stmt->execute()) {
            $identity_row = $identity_stmt->get_result()->fetch_assoc();
            if ($identity_row && trim((string)$identity_row['name']) !== '') {
                $chat_display_name = trim((string)$identity_row['name']);
                $_SESSION['name'] = $chat_display_name;
            }
        }
        $identity_stmt->close();
    }
}
?>
<!-- ========================================================================= -->
<!-- PREMIUM AURA AI ASSISTANT FLOATING LAUNCHER & CHAT PANEL                 -->
<!-- ========================================================================= -->
<div id="chatbot-container" class="fixed bottom-6 right-6 z-50 select-none">
    <!-- Compact AURA AI Floating Launcher with Hover Tooltip -->
    <div class="relative group">
        <!-- Floating Tooltip on Hover (Desktop) -->
        <div class="absolute right-16 top-1/2 -translate-y-1/2 pointer-events-none opacity-0 group-hover:opacity-100 transition-all duration-200 translate-x-2 group-hover:translate-x-0 hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-[#0e131d] text-slate-100 text-xs font-semibold shadow-xl border border-stone-800 whitespace-nowrap z-50" id="chatbot-tooltip">
            <span>AI Barista Assistant</span>
        </div>

        <!-- Compact Launcher Button with AURA AI Orbital Symbol -->
        <button id="chatbot-btn"
                type="button"
                aria-label="Buka Chat AI Barista Assistant"
                class="relative w-14 h-14 rounded-2xl p-0.5 bg-[#0e131d] border border-amber-500/40 shadow-[0_8px_25px_rgba(245,158,11,0.28)] hover:shadow-[0_12px_32px_rgba(245,158,11,0.45)] transition-all duration-200 transform hover:-translate-y-1 hover:scale-105 active:translate-y-0 active:scale-95 focus:outline-none overflow-hidden">
            <img src="../assets/images/aura_ai_dark.png" alt="AURA AI" class="aura-icon-dark w-full h-full object-cover rounded-xl">
            <img src="../assets/images/aura_ai_light.png" alt="AURA AI" class="aura-icon-light w-full h-full object-cover rounded-xl hidden">
        </button>
    </div>

    <!-- Premium Chat Panel Window — Layered Dark Surface -->
    <div id="chatbot-window"
         class="chat-hidden w-[92vw] sm:w-[420px] max-h-[84vh] rounded-2xl flex flex-col absolute bottom-16 right-0 overflow-hidden"
         style="height:580px; background:linear-gradient(160deg,#131926 0%,#0e131d 60%,#090c12 100%); border:1px solid rgba(255,255,255,0.08); box-shadow:0 32px 64px -16px rgba(0,0,0,0.7),0 0 0 1px rgba(245,158,11,0.06),inset 0 1px 0 rgba(255,255,255,0.04);">

        <!-- ── Header ── -->
        <div id="chat-header-bar" class="px-4 py-3 flex items-center justify-between shrink-0"
             style="background:linear-gradient(135deg,#1a2232 0%,#131926 100%); border-bottom:1px solid rgba(255,255,255,0.07); box-shadow:0 1px 0 rgba(0,0,0,0.3);">
            <div class="flex items-center gap-3 min-w-0">
                <!-- AURA AI Header Avatar -->
                <div class="w-11 h-11 rounded-xl overflow-hidden shrink-0"
                     style="box-shadow:0 0 0 1px rgba(245,158,11,0.25),0 4px 12px rgba(0,0,0,0.4);">
                    <img src="../assets/images/aura_ai_dark.png" alt="AURA AI" class="aura-icon-dark w-full h-full object-cover">
                    <img src="../assets/images/aura_ai_light.png" alt="AURA AI" class="aura-icon-light w-full h-full object-cover hidden">
                </div>
                <div class="min-w-0">
                    <h3 id="chat-header-title" class="font-bold text-slate-100 leading-tight truncate" style="font-size:13px;">AI Barista Assistant</h3>
                    <p id="chat-header-subtitle" class="text-stone-400 mt-0.5 truncate" style="font-size:11px;">Katalog, rekomendasi rasa &amp; info menu</p>
                </div>
            </div>
            <div class="flex items-center gap-1 shrink-0 ml-2">
                <button id="chatbot-clear"
                        type="button"
                        title="Bersihkan Percakapan"
                        aria-label="Bersihkan Percakapan"
                        class="w-7 h-7 rounded-lg flex items-center justify-center text-stone-400 hover:text-amber-400 focus:outline-none shrink-0"
                        style="transition:background .15s,color .15s;"
                        onmouseenter="this.style.background='rgba(255,255,255,0.08)'"
                        onmouseleave="this.style.background='transparent'"
                        onclick="clearChatHistory()">
                    <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                </button>
                <button id="chatbot-close"
                        aria-label="Tutup Chat"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-stone-400 hover:text-slate-100 focus:outline-none shrink-0"
                        style="transition:background .15s,color .15s;"
                        onmouseenter="this.style.background='rgba(255,255,255,0.08)'"
                        onmouseleave="this.style.background='transparent'">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

        <!-- ── Conversation Area ── -->
        <div id="chat-messages" class="flex-grow overflow-y-auto"
             style="padding:20px 16px; display:flex; flex-direction:column; gap:16px; background:linear-gradient(180deg,#0d1220 0%,#090c12 100%);">

            <!-- Initial Welcome Bubble -->
            <div class="chat-msg-row flex items-start gap-2.5">
                <div class="chat-avatar shrink-0 mt-0.5 w-8 h-8 rounded-xl overflow-hidden"
                     style="box-shadow:0 0 0 1px rgba(245,158,11,0.2);">
                    <img src="../assets/images/aura_ai_dark.png" alt="AURA AI" class="aura-icon-dark w-full h-full object-cover">
                    <img src="../assets/images/aura_ai_light.png" alt="AURA AI" class="aura-icon-light w-full h-full object-cover hidden">
                </div>
                <div class="chat-assistant-bubble"
                     style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 16px 16px 16px; padding:12px 15px; max-width:calc(100% - 44px); font-size:13px; line-height:1.65; color:#e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.25);">
                    Halo<?= $chat_display_name !== '' ? ' ' . htmlspecialchars($chat_display_name, ENT_QUOTES, 'UTF-8') : '' ?>! Saya AI Barista Kopi Mahasiswa. Ada yang bisa saya bantu terkait rekomendasi menu, racikan rasa, harga, atau cek status pesanan Anda hari ini? &#9749;
                </div>
            </div>

            <!-- Render Previous Session Conversation History if Available -->
            <?php if (!empty($_SESSION['ai_chat_history']) && is_array($_SESSION['ai_chat_history'])): ?>
                <?php foreach ($_SESSION['ai_chat_history'] as $item): ?>
                    <?php if (($item['role'] ?? '') === 'user'): ?>
                        <div class="chat-msg-row flex justify-end">
                            <div class="chat-user-bubble" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); border-radius:16px 4px 16px 16px; padding:11px 15px; max-width:80%; font-size:13px; line-height:1.65; font-weight:500; color:#1c1100; box-shadow:0 3px 10px rgba(245,158,11,0.3); word-break:break-word;">
                                <?= nl2br(htmlspecialchars($item['content'] ?? '')) ?>
                            </div>
                        </div>
                    <?php elseif (($item['role'] ?? '') === 'assistant'): ?>
                        <div class="chat-msg-row flex items-start gap-2.5">
                            <div class="chat-avatar shrink-0 mt-0.5 w-8 h-8 rounded-xl overflow-hidden" style="box-shadow:0 0 0 1px rgba(245,158,11,0.2);">
                                <img src="../assets/images/aura_ai_dark.png" alt="AURA AI" class="aura-icon-dark w-full h-full object-cover">
                                <img src="../assets/images/aura_ai_light.png" alt="AURA AI" class="aura-icon-light w-full h-full object-cover hidden">
                            </div>
                            <div class="chat-assistant-bubble" style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 16px 16px 16px; padding:12px 15px; max-width:calc(100% - 44px); font-size:13px; line-height:1.65; color:#e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.25); word-break:break-word;">
                                <?= nl2br(htmlspecialchars($item['content'] ?? '')) ?>
                                <div class="mt-2 pt-1 border-t border-white/10">
                                    <button type="button" data-copy-answer aria-label="Salin Jawaban"
                                            class="text-[10px] font-semibold text-amber-400 hover:text-amber-300 transition-colors">
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

        <!-- ── Input Composer ── -->
        <div id="chat-footer-form" class="shrink-0 px-3.5 py-3"
             style="background:#0e131d; border-top:1px solid rgba(255,255,255,0.06);">
            <form id="chat-form" class="flex items-center gap-2" onsubmit="event.preventDefault(); sendMessage();">
                <input type="text" id="chat-input"
                       placeholder="Tanya menu kopi, harga, rekomendasi..."
                       autocomplete="off"
                       class="w-full outline-none"
                       style="background:#131926; border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:10px 14px; font-size:13px; color:#e2e8f0; font-family:inherit; transition:border-color .2s,box-shadow .2s;"
                       onfocus="this.style.borderColor='rgba(245,158,11,0.5)';this.style.boxShadow='0 0 0 2px rgba(245,158,11,0.12)';"
                       onblur="this.style.borderColor='rgba(255,255,255,0.08)';this.style.boxShadow='none';"
                       required>
                <button type="submit" id="chat-submit-btn"
                        aria-label="Kirim Pesan"
                        class="shrink-0 flex items-center justify-center w-10 h-10 rounded-xl font-bold text-stone-950 disabled:opacity-40 disabled:pointer-events-none"
                        style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); box-shadow:0 4px 12px rgba(245,158,11,0.3); transition:transform .15s,box-shadow .15s;"
                        onmouseenter="if(!this.disabled){this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 18px rgba(245,158,11,0.4)';}"
                        onmouseleave="this.style.transform='';this.style.boxShadow='0 4px 12px rgba(245,158,11,0.3)';"
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
        const chatBtn       = document.getElementById('chatbot-btn');
        const chatWindow    = document.getElementById('chatbot-window');
        const chatClose     = document.getElementById('chatbot-close');
        const chatInput     = document.getElementById('chat-input');
        const chatSubmitBtn = document.getElementById('chat-submit-btn');
        let isSending = false;

        if (chatBtn && chatWindow) {
            chatBtn.addEventListener('click', () => {
                const isHidden = chatWindow.classList.contains('chat-hidden');
                if (isHidden) {
                    chatWindow.classList.remove('chat-hidden');
                    chatWindow.classList.add('chat-visible');
                    const msgs = document.getElementById('chat-messages');
                    if (msgs) msgs.scrollTop = msgs.scrollHeight;
                    setTimeout(() => chatInput.focus(), 220);
                } else {
                    chatWindow.classList.remove('chat-visible');
                    chatWindow.classList.add('chat-hidden');
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });

            if (chatClose) {
                chatClose.addEventListener('click', () => {
                    chatWindow.classList.remove('chat-visible');
                    chatWindow.classList.add('chat-hidden');
                });
            }
        }

        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.addEventListener('click', async function(event) {
                const button = event.target instanceof Element ? event.target.closest('[data-copy-answer]') : null;
                if (!button) return;
                const bubble = button.closest('.chat-assistant-bubble');
                const source = bubble ? bubble.querySelector('.km-ai-copy-source') : null;
                if (!source || !window.KMUI || typeof window.KMUI.copyText !== 'function') {
                    if (window.KMUI) window.KMUI.toast('error', 'Teks jawaban tidak tersedia untuk disalin.');
                    return;
                }
                const copied = await window.KMUI.copyText(source.textContent);
                window.KMUI.toast(copied ? 'success' : 'error', copied
                    ? 'Jawaban berhasil disalin.'
                    : 'Gagal menyalin jawaban. Silakan salin teks secara manual.');
            });
        }

        window.clearChatHistory = function() {
            if (!window.KMUI || !window.KMUI.confirm) return;
            window.KMUI.confirm({
                title: "Hapus riwayat percakapan",
                message: "Riwayat percakapan dengan AI Barista akan dihapus dari akun ini.",
                confirmLabel: "Hapus riwayat",
                danger: true
            }).then(function(confirmed) {
                if (!confirmed) return;
                return fetch("ai_api.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "clear_history" })
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error("Permintaan hapus riwayat gagal.");
                }
                return res.json();
            })
            .then(data => {
                if (!data || data.status !== "success") {
                    throw new Error("Server tidak mengonfirmasi penghapusan riwayat.");
                }
                const msgs = document.getElementById('chat-messages');
                const welcome = msgs.querySelector('.chat-msg-row');
                msgs.innerHTML = '';
                if (welcome) msgs.appendChild(welcome);
                if (typeof lucide !== 'undefined') lucide.createIcons();
                window.KMUI.toast("success", "Riwayat percakapan berhasil dihapus.");
            })
            .catch(() => {
                window.KMUI.toast("error", "Gagal menghapus riwayat percakapan. Silakan coba lagi.");
            });
            });
        };

        window.sendMessage = function() {
            const input = document.getElementById('chat-input');
            const msg   = input.value.trim();
            if (!msg || isSending) return;
            const msgs = document.getElementById('chat-messages');

            isSending = true;
            // User Bubble
            msgs.innerHTML += `
                <div class="chat-msg-row flex justify-end">
                    <div class="chat-user-bubble" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%); border-radius:16px 4px 16px 16px; padding:11px 15px; max-width:80%; font-size:13px; line-height:1.65; font-weight:500; color:#1c1100; box-shadow:0 3px 10px rgba(245,158,11,0.3); word-break:break-word;">
                        ${escapeHtml(msg)}
                    </div>
                </div>`;
            input.value = '';
            msgs.scrollTop = msgs.scrollHeight;

            // Typing Indicator
            const loadingId = 'ai-loading-' + Date.now();
            msgs.innerHTML += `
                <div id="${loadingId}" class="chat-msg-row flex items-start gap-2.5">
                    <div class="chat-avatar shrink-0 mt-0.5 w-8 h-8 rounded-xl overflow-hidden" style="box-shadow:0 0 0 1px rgba(245,158,11,0.2);">
                        <img src="../assets/images/aura_ai_dark.png" alt="AURA AI" class="aura-icon-dark w-full h-full object-cover">
                        <img src="../assets/images/aura_ai_light.png" alt="AURA AI" class="aura-icon-light w-full h-full object-cover hidden">
                    </div>
                    <div class="chat-assistant-bubble" style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 16px 16px 16px; padding:13px 16px; display:inline-flex; align-items:center; gap:5px; box-shadow:0 2px 8px rgba(0,0,0,0.25);">
                        <span class="typing-dot" style="background:#f59e0b;"></span>
                        <span class="typing-dot" style="background:#f59e0b;"></span>
                        <span class="typing-dot" style="background:#f59e0b;"></span>
                    </div>
                </div>`;
            msgs.scrollTop = msgs.scrollHeight;
            if (typeof lucide !== 'undefined') lucide.createIcons();

            if (chatSubmitBtn) chatSubmitBtn.disabled = true;

            fetch("ai_api.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ message: msg })
            })
            .then(res => res.json())
            .then(data => {
                const loader = document.getElementById(loadingId);
                if (loader) loader.remove();
                if (chatSubmitBtn) chatSubmitBtn.disabled = false;
                isSending = false;

                const rawText = data.reply
                    ? data.reply
                    : (data.error ? data.error : "Maaf, terjadi kendala saat memproses respons dari barista AI.");
                const reply = escapeHtml(rawText).replace(/\n/g, "<br>");
                msgs.innerHTML += `
                    <div class="chat-msg-row flex items-start gap-2.5">
                        <div class="chat-avatar shrink-0 mt-0.5 w-8 h-8 rounded-xl overflow-hidden" style="box-shadow:0 0 0 1px rgba(245,158,11,0.2);">
                            <img src="../assets/images/aura_ai_dark.png" alt="AURA AI" class="aura-icon-dark w-full h-full object-cover">
                            <img src="../assets/images/aura_ai_light.png" alt="AURA AI" class="aura-icon-light w-full h-full object-cover hidden">
                        </div>
                        <div class="chat-assistant-bubble" style="background:linear-gradient(135deg,#1e2a3d 0%,#1a2232 100%); border:1px solid rgba(255,255,255,0.07); border-radius:4px 16px 16px 16px; padding:12px 15px; max-width:calc(100% - 44px); font-size:13px; line-height:1.65; color:#e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.25); word-break:break-word;">
                            ${reply}
                            <div class="mt-2 pt-1 border-t border-white/10">
                                <button type="button" data-copy-answer aria-label="Salin Jawaban"
                                        class="text-[10px] font-semibold text-amber-400 hover:text-amber-300 transition-colors">
                                    Salin Jawaban
                                </button>
                            </div>
                            <span class="km-ai-copy-source" hidden>${escapeHtml(rawText)}</span>
                        </div>
                    </div>`;
                msgs.scrollTop = msgs.scrollHeight;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            })
            .catch(() => {
                const loader = document.getElementById(loadingId);
                if (loader) loader.remove();
                if (chatSubmitBtn) chatSubmitBtn.disabled = false;
                isSending = false;

                msgs.innerHTML += `
                    <div class="chat-msg-row flex items-start gap-2.5">
                        <div class="chat-avatar shrink-0 mt-0.5 w-8 h-8 rounded-xl overflow-hidden" style="box-shadow:0 0 0 1px rgba(239,68,68,0.3);">
                            <img src="../assets/images/aura_ai_dark.png" alt="AURA AI" class="aura-icon-dark w-full h-full object-cover">
                            <img src="../assets/images/aura_ai_light.png" alt="AURA AI" class="aura-icon-light w-full h-full object-cover hidden">
                        </div>
                        <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:4px 16px 16px 16px; padding:11px 15px; font-size:13px; line-height:1.6; color:#fca5a5;">
                            Gagal terhubung ke AI Barista. Silakan coba sesaat lagi.
                        </div>
                    </div>`;
                msgs.scrollTop = msgs.scrollHeight;
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        };

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    })();
</script>
</body>
</html>
