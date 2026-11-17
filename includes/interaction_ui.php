<?php
if (!function_exists('app_status_labels')) {
    function app_status_labels(): array {
        return [
            'PENDING' => 'Menunggu',
            'CONFIRMED' => 'Dikonfirmasi',
            'PREPARING' => 'Sedang Disiapkan',
            'READY' => 'Siap Disajikan',
            'COMPLETED' => 'Selesai',
            'CANCELLED' => 'Dibatalkan',
            'DRAFT' => 'Draf',
            'ORDERED' => 'Dipesan',
            'SHIPPED' => 'Dikirim',
            'RECEIVED' => 'Diterima',
            'ACTIVE' => 'Aktif',
            'INACTIVE' => 'Tidak Aktif',
            'SUSPENDED' => 'Ditangguhkan',
            'IN' => 'Stok Masuk',
            'OUT' => 'Stok Keluar',
        ];
    }
}

if (!function_exists('app_status_label')) {
    function app_status_label(?string $status): string {
        $status = strtoupper(trim((string)$status));
        $labels = app_status_labels();
        return $labels[$status] ?? ($status !== '' ? $status : 'Tidak diketahui');
    }
}

if (!function_exists('app_render_interaction_ui')) {
    function app_render_interaction_ui(): void {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $notification = $_SESSION['ui_notification'] ?? null;
        unset($_SESSION['ui_notification']);
        if (!is_array($notification)
            || !in_array($notification['type'] ?? '', ['success', 'error', 'warning', 'info'], true)
            || !is_string($notification['message'] ?? null)
        ) {
            $notification = null;
        }
        ?>
        <style>
            #km-toast-region { position: fixed; top: 1rem; right: 1rem; z-index: 100; display: grid; gap: .65rem; width: min(24rem, calc(100vw - 2rem)); pointer-events: none; }
            .km-toast { display: flex; align-items: flex-start; gap: .75rem; padding: .9rem 1rem; border: 1px solid #334155; border-radius: 1rem; background: rgba(14,19,29,.97); color: #e2e8f0; box-shadow: 0 14px 38px rgba(0,0,0,.3); pointer-events: auto; animation: km-toast-in .2s ease-out both; }
            .km-toast[data-type="success"] { border-color: rgba(16,185,129,.45); }
            .km-toast[data-type="error"] { border-color: rgba(244,63,94,.5); }
            .km-toast[data-type="warning"] { border-color: rgba(245,158,11,.5); }
            .km-toast-icon { flex: 0 0 auto; font-weight: 800; }
            .km-toast[data-type="success"] .km-toast-icon { color: #34d399; }
            .km-toast[data-type="error"] .km-toast-icon { color: #fb7185; }
            .km-toast[data-type="warning"] .km-toast-icon { color: #fbbf24; }
            .km-toast[data-type="info"] .km-toast-icon { color: #7dd3fc; }
            .km-toast-message { flex: 1; font-size: .85rem; line-height: 1.5; }
            .km-toast-close { border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 1.1rem; line-height: 1; }
            #km-modal-backdrop { position: fixed; inset: 0; z-index: 110; display: grid; place-items: center; padding: 1rem; background: rgba(2,6,13,.72); backdrop-filter: blur(6px); opacity: 0; transition: opacity .18s ease; }
            #km-modal-backdrop.km-open { opacity: 1; }
            .km-modal { width: min(100%, 28rem); border: 1px solid #334155; border-radius: 1.4rem; padding: 1.5rem; background: #0e131d; color: #e2e8f0; box-shadow: 0 24px 70px rgba(0,0,0,.45); transform: translateY(8px) scale(.99); transition: transform .18s ease; }
            .km-open .km-modal { transform: translateY(0) scale(1); }
            .km-modal-icon { width: 2.75rem; height: 2.75rem; display: grid; place-items: center; border-radius: 1rem; margin-bottom: 1rem; background: rgba(245,158,11,.12); color: #fbbf24; font-size: 1.3rem; }
            .km-modal[data-danger="true"] .km-modal-icon { background: rgba(244,63,94,.12); color: #fb7185; }
            .km-modal-title { margin: 0; font-size: 1.1rem; font-weight: 800; color: #f8fafc; }
            .km-modal-message { margin: .55rem 0 1.35rem; color: #a8b3c5; font-size: .9rem; line-height: 1.6; white-space: pre-line; }
            .km-modal-actions { display: flex; justify-content: flex-end; gap: .65rem; }
            .km-modal-button { min-height: 2.6rem; border: 1px solid #334155; border-radius: .8rem; padding: .6rem 1rem; color: #cbd5e1; background: #131926; font-weight: 700; cursor: pointer; }
            .km-modal-button-primary { border-color: transparent; color: #111827; background: linear-gradient(135deg,#fbbf24,#f59e0b); }
            .km-modal[data-danger="true"] .km-modal-button-primary { color: white; background: linear-gradient(135deg,#f43f5e,#e11d48); }
            .km-submit-loading { display: inline-flex; align-items: center; gap: .45rem; }
            .km-submit-spinner { width: .85rem; height: .85rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: km-spin .65s linear infinite; }
            @keyframes km-spin { to { transform: rotate(360deg); } }
            @keyframes km-toast-in { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
            @media (prefers-reduced-motion: reduce) { .km-toast, #km-modal-backdrop, .km-modal { animation: none; transition: none; } }
            @media (max-width: 480px) { #km-toast-region { top: .6rem; right: .6rem; width: calc(100vw - 1.2rem); } .km-modal { padding: 1.2rem; } .km-modal-actions { flex-direction: column-reverse; } .km-modal-button { width: 100%; } }
        </style>
        <script>
            (function () {
                const statusLabels = <?= json_encode(app_status_labels(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
                let activeDialog = null;
                let lastFocused = null;

                function ensureToastRegion() {
                    let region = document.getElementById('km-toast-region');
                    if (!region) {
                        region = document.createElement('div');
                        region.id = 'km-toast-region';
                        region.setAttribute('aria-live', 'polite');
                        region.setAttribute('aria-relevant', 'additions');
                        document.body.appendChild(region);
                    }
                    return region;
                }

                function toast(type, message) {
                    if (!['success', 'error', 'warning', 'info'].includes(type)) type = 'info';
                    const region = ensureToastRegion();
                    const item = document.createElement('div');
                    const icon = document.createElement('span');
                    const text = document.createElement('span');
                    const close = document.createElement('button');
                    item.className = 'km-toast';
                    item.dataset.type = type;
                    item.setAttribute('role', type === 'error' ? 'alert' : 'status');
                    icon.className = 'km-toast-icon';
                    icon.setAttribute('aria-hidden', 'true');
                    icon.textContent = type === 'success' ? '✓' : (type === 'error' ? '!' : (type === 'warning' ? '⚠' : 'i'));
                    text.className = 'km-toast-message';
                    text.textContent = String(message);
                    close.className = 'km-toast-close';
                    close.type = 'button';
                    close.setAttribute('aria-label', 'Tutup notifikasi');
                    close.textContent = '×';
                    close.addEventListener('click', function () { item.remove(); });
                    item.append(icon, text, close);
                    region.appendChild(item);
                    window.setTimeout(function () { item.remove(); }, 5000);
                }

                async function copyText(text) {
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        try {
                            await navigator.clipboard.writeText(String(text));
                            return true;
                        } catch (error) {
                            // Continue with the legacy clipboard fallback.
                        }
                    }

                    const textarea = document.createElement('textarea');
                    textarea.value = String(text);
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();

                    let copied = false;
                    try {
                        copied = document.execCommand('copy');
                    } catch (error) {
                        copied = false;
                    }
                    textarea.remove();
                    return copied;
                }

                function closeDialog() {
                    if (!activeDialog) return;
                    const dialog = activeDialog;
                    dialog.classList.remove('km-open');
                    window.setTimeout(function () { dialog.remove(); }, 180);
                    activeDialog = null;
                    document.body.style.overflow = '';
                    if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
                }

                function confirmDialog(options) {
                    if (activeDialog) return Promise.resolve(false);
                    lastFocused = document.activeElement;
                    return new Promise(function (resolve) {
                        const backdrop = document.createElement('div');
                        const panel = document.createElement('section');
                        const icon = document.createElement('div');
                        const title = document.createElement('h2');
                        const message = document.createElement('p');
                        const actions = document.createElement('div');
                        const cancel = document.createElement('button');
                        const accept = document.createElement('button');
                        backdrop.id = 'km-modal-backdrop';
                        panel.className = 'km-modal';
                        panel.dataset.danger = options.danger ? 'true' : 'false';
                        panel.setAttribute('role', 'alertdialog');
                        panel.setAttribute('aria-modal', 'true');
                        panel.setAttribute('aria-labelledby', 'km-modal-title');
                        panel.setAttribute('aria-describedby', 'km-modal-message');
                        icon.className = 'km-modal-icon';
                        icon.setAttribute('aria-hidden', 'true');
                        icon.textContent = options.danger ? '!' : '⚠';
                        title.className = 'km-modal-title';
                        title.id = 'km-modal-title';
                        title.textContent = options.title || 'Konfirmasi tindakan';
                        message.className = 'km-modal-message';
                        message.id = 'km-modal-message';
                        message.textContent = options.message || 'Apakah Anda yakin ingin melanjutkan?';
                        actions.className = 'km-modal-actions';
                        cancel.className = 'km-modal-button';
                        cancel.type = 'button';
                        cancel.textContent = options.cancelLabel || 'Kembali';
                        accept.className = 'km-modal-button km-modal-button-primary';
                        accept.type = 'button';
                        accept.textContent = options.confirmLabel || 'Lanjutkan';
                        actions.append(cancel, accept);
                        panel.append(icon, title, message, actions);
                        backdrop.appendChild(panel);
                        document.body.appendChild(backdrop);
                        activeDialog = backdrop;
                        document.body.style.overflow = 'hidden';
                        function finish(result) { closeDialog(); resolve(result); }
                        cancel.addEventListener('click', function () { finish(false); });
                        accept.addEventListener('click', function () { finish(true); });
                        backdrop.addEventListener('click', function (event) {
                            if (event.target === backdrop) finish(false);
                        });
                        backdrop.addEventListener('keydown', function (event) {
                            if (event.key === 'Escape') { event.preventDefault(); finish(false); }
                            if (event.key === 'Tab') {
                                event.preventDefault();
                                if (event.shiftKey) {
                                    (document.activeElement === cancel ? accept : cancel).focus();
                                } else {
                                    (document.activeElement === accept ? cancel : accept).focus();
                                }
                            }
                        });
                        window.requestAnimationFrame(function () {
                            backdrop.classList.add('km-open');
                            cancel.focus();
                        });
                    });
                }

                function labelStatus(value) {
                    const key = String(value || '').trim().toUpperCase();
                    return statusLabels[key] || value;
                }

                function initInteractions() {
                    document.querySelectorAll('[data-status-label]').forEach(function (node) {
                        node.textContent = labelStatus(node.dataset.statusLabel);
                    });

                    document.addEventListener('submit', function (event) {
                        const form = event.target;
                        if (!(form instanceof HTMLFormElement)) return;
                        if (form.dataset.confirmed === 'true') {
                            delete form.dataset.confirmed;
                        } else if (form.hasAttribute('data-confirm')) {
                            event.preventDefault();
                            const select = form.querySelector('[data-confirm-status]');
                            const selectedStatus = select ? labelStatus(select.value) : '';
                            const message = (form.dataset.confirmMessage || 'Konfirmasi tindakan ini?')
                                .replace('{status}', selectedStatus)
                                .replace('{object}', form.dataset.confirmObject || 'data');
                            confirmDialog({
                                title: form.dataset.confirmTitle,
                                message: message,
                                confirmLabel: form.dataset.confirmLabel,
                                danger: form.dataset.confirmDanger === 'true' || (select && ['CANCELLED', 'OUT'].includes(select.value))
                            }).then(function (confirmed) {
                                if (!confirmed) return;
                                form.dataset.confirmed = 'true';
                                if (event.submitter && typeof form.requestSubmit === 'function') form.requestSubmit(event.submitter);
                                else form.requestSubmit();
                            });
                            return;
                        }

                        if (!form.hasAttribute('data-loading')) return;
                        if (form.dataset.submitting === 'true') {
                            event.preventDefault();
                            return;
                        }
                        form.dataset.submitting = 'true';
                        const submitter = event.submitter;
                        if (submitter && !submitter.disabled) {
                            submitter.disabled = true;
                            submitter.setAttribute('aria-busy', 'true');
                            const original = submitter.innerHTML;
                            submitter.dataset.originalContent = original;
                            submitter.innerHTML = '<span class="km-submit-loading"><span class="km-submit-spinner" aria-hidden="true"></span><span>Memproses…</span></span>';
                        }
                    });
                }

                window.KMUI = { toast: toast, confirm: confirmDialog, statusLabel: labelStatus, copyText: copyText };
                document.addEventListener('DOMContentLoaded', initInteractions, { once: true });
                <?php if ($notification !== null): ?>
                document.addEventListener('DOMContentLoaded', function () {
                    window.KMUI.toast(
                        <?= json_encode($notification['type'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                        <?= json_encode($notification['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
                    );
                }, { once: true });
                <?php endif; ?>
            })();
        </script>
        <?php
    }
}
