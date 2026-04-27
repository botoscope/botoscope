'use strict';
const isDarkMode = window.Telegram?.WebApp?.themeParams?.is_dark;

if (isDarkMode) {
    document.body.style = 'background: #000; color: #fff;';
    const existing = document.querySelector('meta[name="color-scheme"]');
    const meta = existing || document.createElement('meta');
    meta.name = 'color-scheme';
    meta.content = 'dark';
    if (!existing) {
        document.head.appendChild(meta);
    }

    document.body.classList.add('botoscope-dark-mode');
}

if (typeof botoscope_filter_data !== 'undefined' && botoscope_filter_data.should_close) {
    window.Telegram.WebApp.close();
}
