<?php
// Global Theme Toggle Button Component — Kopi Mahasiswa
?>
<button type="button"
        onclick="toggleGlobalTheme()"
        aria-label="Ganti tema terang atau gelap"
        title="Ganti Tema (Terang / Gelap)"
        class="theme-toggle-btn w-8 h-8 rounded-xl flex items-center justify-center transition-all bg-[#131926] hover:bg-stone-800/80 border border-stone-800 text-stone-400 hover:text-amber-400 shadow-sm focus:outline-none">
    <i data-lucide="sun" class="theme-icon-sun w-4 h-4 hidden text-amber-500"></i>
    <i data-lucide="moon" class="theme-icon-moon w-4 h-4 text-stone-400"></i>
</button>
