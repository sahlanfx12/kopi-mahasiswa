<?php
require_once __DIR__ . '/interaction_ui.php';
app_render_interaction_ui();
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    // Immediate Theme Initialization (Zero Flash / FOUC Prevention)
    (function() {
        try {
            const savedTheme = localStorage.getItem('km_theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.add('theme-light');
            } else {
                document.documentElement.classList.remove('theme-light');
            }
        } catch(e) {}
    })();

    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: {
                    sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    mono: ['"JetBrains Mono"', 'monospace'],
                },
                colors: {
                    dark: {
                        950: '#090c12',
                        900: '#0e131d',
                        850: '#131926',
                        800: '#1a2232',
                        750: '#212a3d',
                        700: '#2b364d',
                        600: '#3a4863',
                    },
                    amber: {
                        450: '#f59e0b',
                        550: '#d97706',
                    }
                }
            }
        }
    }

    // Global Theme Switcher Controller
    window.toggleGlobalTheme = function() {
        const isLight = document.documentElement.classList.contains('theme-light');
        if (isLight) {
            document.documentElement.classList.remove('theme-light');
            try { localStorage.setItem('km_theme', 'dark'); } catch(e) {}
        } else {
            document.documentElement.classList.add('theme-light');
            try { localStorage.setItem('km_theme', 'light'); } catch(e) {}
        }
        window.updateThemeToggleIcons();
    };

    window.updateThemeToggleIcons = function() {
        const isLight = document.documentElement.classList.contains('theme-light');
        const sunIcons = document.querySelectorAll('.theme-icon-sun');
        const moonIcons = document.querySelectorAll('.theme-icon-moon');

        sunIcons.forEach(function(el) {
            if (isLight) el.classList.remove('hidden');
            else el.classList.add('hidden');
        });
        moonIcons.forEach(function(el) {
            if (isLight) el.classList.add('hidden');
            else el.classList.remove('hidden');
        });
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        window.updateThemeToggleIcons();
    });
</script>
<style>
    /* =========================================================================
       BASE DARK MODE (PRESERVED EXISTING BYBIT TRADING SYSTEM)
       ========================================================================= */
    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #090c12;
        color: #e2e8f0;
    }
    .font-mono { font-family: 'JetBrains Mono', monospace; }

    /* Custom scrollbars (Dark) */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #090c12; }
    ::-webkit-scrollbar-thumb { background: #1a2232; border-radius: 9999px; }
    ::-webkit-scrollbar-thumb:hover { background: #2b364d; }

    /* Subtle depth card (Dark) */
    .trade-card {
        background: radial-gradient(120% 120% at 50% 0%, rgba(26, 34, 50, 0.45) 0%, rgba(14, 19, 29, 0.85) 100%);
        border: 1px solid rgba(255, 255, 255, 0.07);
        box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
    }
    .trade-card:hover {
        border-color: rgba(245, 158, 11, 0.35);
    }

    /* Bybit-style badge */
    .badge-glow {
        box-shadow: 0 0 12px rgba(245, 158, 11, 0.15);
    }

    /* =========================================================================
       PREMIUM LIGHT MODE DESIGN SYSTEM (WARM LAYERED SAAS PALETTE)
       Background #EEF1F5 / Surface #FFFFFF / Secondary #E7EBF1 / Border #CBD3DE
       ========================================================================= */
    .theme-light body,
    .theme-light {
        background-color: #eef1f5 !important;
        color: #202938 !important;
    }

    /* Background Surfacing */
    .theme-light .bg-\[\#090c12\],
    .theme-light [class*="bg-[#090c12]"] {
        background-color: #eef1f5 !important;
    }

    .theme-light .bg-\[\#0e131d\],
    .theme-light [class*="bg-[#0e131d]"] {
        background-color: #ffffff !important;
    }

    .theme-light .bg-\[\#131926\],
    .theme-light [class*="bg-[#131926]"] {
        background-color: #e7ebf1 !important;
    }

    .theme-light .bg-\[\#141b29\],
    .theme-light [class*="bg-[#141b29]"] {
        background-color: #e7ebf1 !important;
    }

    .theme-light .bg-\[\#0a0d14\] {
        background-color: #ffffff !important;
    }

    /* Trade Card (Light Mode: Warm White with Crisp 1px Outline & Layered Depth) */
    .theme-light .trade-card {
        background: #ffffff !important;
        border: 1px solid #cbd3de !important;
        box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.08), 0 2px 6px -1px rgba(15, 23, 42, 0.04) !important;
    }
    .theme-light .trade-card:hover {
        border-color: #d97706 !important;
        box-shadow: 0 10px 25px -4px rgba(217, 119, 6, 0.14), 0 4px 10px -2px rgba(15, 23, 42, 0.06) !important;
    }

    /* Borders & Dividers (More defined outlines) */
    .theme-light [class*="border-stone-800"],
    .theme-light [class*="border-slate-800"],
    .theme-light [class*="border-stone-700"],
    .theme-light [class*="border-slate-700"] {
        border-color: #cbd3de !important;
    }

    .theme-light [class*="divide-stone-800"],
    .theme-light [class*="divide-slate-800"],
    .theme-light [class*="divide-stone-700"],
    .theme-light [class*="divide-slate-700"] {
        border-color: #d6dde6 !important;
    }

    /* Typography Hierarchy */
    .theme-light .text-white,
    .theme-light .text-stone-100,
    .theme-light .text-slate-100,
    .theme-light .text-stone-200,
    .theme-light .text-slate-200 {
        color: #202938 !important;
    }

    .theme-light .text-stone-300,
    .theme-light .text-slate-300,
    .theme-light .text-stone-400,
    .theme-light .text-slate-400 {
        color: #526174 !important;
    }

    .theme-light .text-stone-500,
    .theme-light .text-slate-500 {
        color: #8091a5 !important;
    }

    /* Navigation Bar */
    .theme-light nav {
        background-color: rgba(255, 255, 255, 0.98) !important;
        border-bottom: 1px solid #cbd3de !important;
        box-shadow: 0 2px 10px -2px rgba(15, 23, 42, 0.06) !important;
    }

    .theme-light nav .text-stone-400:hover,
    .theme-light nav .text-slate-400:hover {
        color: #1e293b !important;
        background-color: #e7ebf1 !important;
    }

    /* Form Inputs, Selects, and Textareas */
    .theme-light input:not([type="submit"]):not([type="button"]):not([type="checkbox"]):not([type="radio"]),
    .theme-light select,
    .theme-light textarea {
        background-color: #ffffff !important;
        color: #202938 !important;
        border: 1px solid #cbd3de !important;
    }
    .theme-light input:focus,
    .theme-light select:focus,
    .theme-light textarea:focus {
        border-color: #d97706 !important;
        background-color: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.18) !important;
    }

    /* Table Headers & Rows */
    .theme-light thead,
    .theme-light thead th {
        background-color: #e7ebf1 !important;
        color: #334155 !important;
        border-bottom: 1px solid #cbd3de !important;
        font-weight: 700 !important;
    }
    .theme-light tbody tr:hover {
        background-color: #f6f7f9 !important;
    }

    /* Footer */
    .theme-light footer:not(.footer-transparent) {
        background-color: #ffffff !important;
        border-top: 1px solid #cbd3de !important;
    }

    /* Amber Accents in Light Mode */
    .theme-light .text-amber-400 {
        color: #d97706 !important;
    }
    .theme-light .text-amber-500 {
        color: #b45309 !important;
    }
    .theme-light .bg-amber-500\/10 {
        background-color: rgba(217, 119, 6, 0.12) !important;
        color: #b45309 !important;
        border-color: rgba(217, 119, 6, 0.4) !important;
    }
    .theme-light .border-amber-500\/30,
    .theme-light .border-amber-500\/20 {
        border-color: rgba(217, 119, 6, 0.45) !important;
    }

    /* Status Badges in Light Mode */
    .theme-light .bg-emerald-500\/10 {
        background-color: rgba(16, 185, 129, 0.12) !important;
        color: #047857 !important;
        border-color: rgba(16, 185, 129, 0.35) !important;
    }
    .theme-light .bg-rose-500\/10 {
        background-color: rgba(244, 63, 94, 0.12) !important;
        color: #be123c !important;
        border-color: rgba(244, 63, 94, 0.35) !important;
    }
    .theme-light .bg-sky-500\/10,
    .theme-light .bg-cyan-500\/10 {
        background-color: rgba(14, 165, 233, 0.12) !important;
        color: #0369a1 !important;
        border-color: rgba(14, 165, 233, 0.35) !important;
    }
    .theme-light .bg-purple-500\/10,
    .theme-light .bg-indigo-500\/10 {
        background-color: rgba(139, 92, 246, 0.12) !important;
        color: #6d28d9 !important;
        border-color: rgba(139, 92, 246, 0.35) !important;
    }

    /* Theme Toggle Button in Light Mode */
    .theme-light .theme-toggle-btn {
        background-color: #ffffff !important;
        border: 1px solid #cbd3de !important;
        color: #526174 !important;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
    }
    .theme-light .theme-toggle-btn:hover {
        background-color: #e7ebf1 !important;
        color: #d97706 !important;
        border-color: #b45309 !important;
    }

    /* Scrollbars in Light Mode */
    .theme-light ::-webkit-scrollbar-track { background: #eef1f5; }
    .theme-light ::-webkit-scrollbar-thumb { background: #cbd3de; }
    .theme-light ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    /* Pull Cord Lamp Room Overlay in Light Mode */
    .theme-light #room-ambient-dark {
        background-color: rgba(238, 241, 245, 0.88) !important;
    }
    .theme-light #lamp-glow-cone {
        background: radial-gradient(circle at 35% 25%, rgba(245, 158, 11, 0.24) 0%, rgba(251, 191, 36, 0.12) 35%, transparent 75%) !important;
    }

    /* =========================================================================
       GLOBAL MICRO-INTERACTIONS & MOTION POLISH
       Smooth 60fps GPU-accelerated feedback across buttons, cards, and navigation
       ========================================================================= */
    button:not(:disabled),
    a.btn,
    .btn-interactive,
    input[type="submit"]:not(:disabled) {
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                    box-shadow 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                    border-color 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                    background-color 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                    color 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }

    /* Primary button hover lift (1.5px - 2px) & press feedback */
    button:not(:disabled):hover,
    input[type="submit"]:not(:disabled):hover {
        transform: translateY(-1.8px);
    }
    button:not(:disabled):active,
    input[type="submit"]:not(:disabled):active {
        transform: translateY(0.5px) scale(0.98);
    }

    /* Interactive Card Micro-Lift */
    .trade-card {
        transition: transform 0.24s cubic-bezier(0.16, 1, 0.3, 1),
                    box-shadow 0.24s cubic-bezier(0.16, 1, 0.3, 1),
                    border-color 0.24s cubic-bezier(0.16, 1, 0.3, 1);
    }
    a.trade-card:hover,
    .card-interactive:hover,
    .trade-card.hover-lift:hover {
        transform: translateY(-3.5px);
    }

    /* Navigation links hover micro-shift */
    nav a {
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    nav a:hover i[data-lucide] {
        transform: translateY(-1px) translateX(1px);
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    /* Table row micro-transition */
    tbody tr {
        transition: background-color 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }

    /* Form input focus micro-transition */
    input, select, textarea {
        transition: border-color 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                    box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1),
                    background-color 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    /* =========================================================================
       CHATBOT ANIMATION & LAYER STYLING
       Mendukung Customer (#chatbot-window) dan Staf (#cb_admin_window, #cb_owner_window,
       #cb_kasir_window, #cb_supplier_window)
       ========================================================================= */
    #chatbot-window,
    #cb_admin_window,
    #cb_owner_window,
    #cb_kasir_window,
    #cb_supplier_window {
        transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                    transform 0.28s cubic-bezier(0.16, 1, 0.3, 1),
                    visibility 0.28s;
        transform-origin: bottom right;
    }
    #chatbot-window.chat-hidden,
    #cb_admin_window.chat-hidden,
    #cb_owner_window.chat-hidden,
    #cb_kasir_window.chat-hidden,
    #cb_supplier_window.chat-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: scale(0.92) translateY(14px);
    }
    #chatbot-window.chat-visible,
    #cb_admin_window.chat-visible,
    #cb_owner_window.chat-visible,
    #cb_kasir_window.chat-visible,
    #cb_supplier_window.chat-visible {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: scale(1) translateY(0);
    }

    /* Custom thin scrollbar for chat-messages */
    #chat-messages::-webkit-scrollbar { width: 4px; }
    #chat-messages::-webkit-scrollbar-track { background: transparent; }
    #chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 9999px; }
    #chat-messages::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.15); }

    /* Input placeholder color (inline style doesn't cover ::placeholder) */
    #chat-input::placeholder { color: #526174; }
    .theme-light #chat-input::placeholder { color: #8091a5; }

    /* 3-Dot Typing Pulse Animation */
    @keyframes typingBounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.35; }
        40% { transform: translateY(-4px); opacity: 1; }
    }
    .typing-dot {
        display: inline-block;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        animation: typingBounce 1.2s infinite ease-in-out both;
    }
    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }

    /* AURA AI Dual Icon Theme Switching */
    .aura-icon-dark {
        display: block !important;
    }
    .aura-icon-light {
        display: none !important;
    }
    .theme-light .aura-icon-dark {
        display: none !important;
    }
    .theme-light .aura-icon-light {
        display: block !important;
    }

    /* =========================================================================
       CHATBOT — LIGHT MODE OVERRIDES
       Panel uses inline styles; we override via !important on specific IDs/classes.
       Scoped strictly within #chatbot-container to prevent bleed.
       ========================================================================= */
    .theme-light #chatbot-btn {
        background-color: #ffffff !important;
        border: 1.5px solid rgba(217, 119, 6, 0.45) !important;
        box-shadow: 0 10px 28px -4px rgba(217, 119, 6, 0.28), 0 4px 10px -2px rgba(15, 23, 42, 0.08) !important;
    }
    .theme-light #chatbot-btn:hover {
        border-color: #d97706 !important;
        box-shadow: 0 14px 35px -2px rgba(217, 119, 6, 0.42), 0 6px 14px -2px rgba(15, 23, 42, 0.12) !important;
    }
    .theme-light #chatbot-tooltip {
        background-color: #ffffff !important;
        border: 1px solid #cbd3de !important;
        color: #202938 !important;
        box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.12) !important;
    }
    /* Panel window */
    .theme-light #chatbot-window {
        background: #f0f3f7 !important;
        border: 1px solid #cbd3de !important;
        box-shadow: 0 20px 45px -12px rgba(15, 23, 42, 0.16), 0 4px 14px -2px rgba(15, 23, 42, 0.08) !important;
    }
    /* Header bar */
    .theme-light #chat-header-bar {
        background: #ffffff !important;
        border-bottom: 1px solid #cbd3de !important;
        box-shadow: 0 1px 0 #cbd3de !important;
    }
    .theme-light #chat-header-title {
        color: #202938 !important;
    }
    .theme-light #chat-header-subtitle {
        color: #526174 !important;
    }
    .theme-light #chatbot-close {
        color: #526174 !important;
    }
    /* Conversation area */
    .theme-light #chat-messages {
        background: #eef1f5 !important;
    }
    .theme-light #chat-messages::-webkit-scrollbar-thumb {
        background: #cbd3de !important;
    }
    /* Composer */
    .theme-light #chat-footer-form {
        background-color: #ffffff !important;
        border-top: 1px solid #cbd3de !important;
    }
    .theme-light #chat-input {
        background-color: #f0f3f7 !important;
        border: 1px solid #cbd3de !important;
        color: #202938 !important;
    }
    .theme-light #chat-input:focus {
        background-color: #ffffff !important;
        border-color: #d97706 !important;
        box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2) !important;
    }
    /* Bubbles */
    .theme-light #chat-messages .chat-assistant-bubble {
        background: #ffffff !important;
        border: 1px solid #cbd3de !important;
        color: #202938 !important;
        box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.05) !important;
    }
    .theme-light #chat-messages .chat-user-bubble {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 2px 8px -1px rgba(217, 119, 6, 0.3) !important;
    }
    .theme-light #chat-messages .typing-dot {
        background-color: #d97706 !important;
    }

    /* =========================================================================
       OFFICIAL DEVELOPER WATERMARK STYLES
       Subtle, elegant credit with guaranteed cross-theme contrast & legibility
       ========================================================================= */
    .app-watermark {
        font-size: 0.75rem; /* text-xs */
        font-weight: 500;
        letter-spacing: 0.05em;
        color: #a8a29e; /* stone-400 */
        display: inline-block;
        user-select: none;
        transition: color 0.2s ease;
    }
    .app-watermark:hover {
        color: #d6d3d1; /* stone-300 */
    }
    .theme-light .app-watermark {
        color: #64748b !important; /* slate-500: crisp & subtle on light backgrounds */
    }
    .theme-light .app-watermark:hover {
        color: #334155 !important;
    }

    /* Thermal receipt printer styles isolation */
    @media print {
        .theme-light body, body {
            background-color: #ffffff !important;
            color: #000000 !important;
        }
    }

    /* Respect prefers-reduced-motion */
    @media (prefers-reduced-motion: reduce) {
        *, ::before, ::after {
            animation-delay: -1ms !important;
            animation-duration: 1ms !important;
            animation-iteration-count: 1 !important;
            background-attachment: initial !important;
            scroll-behavior: auto !important;
            transition-delay: 0s !important;
            transition-duration: 0s !important;
            transform: none !important;
        }
    }
</style>
