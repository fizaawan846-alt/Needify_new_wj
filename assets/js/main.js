/**
 * NEEDIFY - MAIN JAVASCRIPT
 * Theme toggle (dark/light), font size scaling (A-, A, A+),
 * mobile navigation, scroll-to-top/bottom, and reveal animations.
 */

(function () {
    'use strict';

    // ----------------------------------------------------
    // 1. Theme Management (Dark / Light Mode)
    // ----------------------------------------------------
    const THEME_KEY = 'needify_theme';

    function getPreferredTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === 'dark' || saved === 'light') {
            return saved;
        }
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
        } else {
            document.body.classList.remove('dark-mode');
        }

        const themeBtns = document.querySelectorAll('.theme-toggle-btn');
        themeBtns.forEach(btn => {
            const icon = btn.querySelector('i');
            if (icon) {
                if (theme === 'dark') {
                    icon.className = 'fa-solid fa-sun';
                    btn.setAttribute('title', 'Switch to light mode');
                    btn.setAttribute('aria-label', 'Switch to light mode');
                } else {
                    icon.className = 'fa-solid fa-moon';
                    btn.setAttribute('title', 'Switch to dark mode');
                    btn.setAttribute('aria-label', 'Switch to dark mode');
                }
            }
        });

        const metaTheme = document.getElementById('themeColorMeta');
        if (metaTheme) {
            metaTheme.setAttribute('content', theme === 'dark' ? '#15111E' : '#FAF7FD');
        }
    }

    function toggleTheme() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, next);
        applyTheme(next);
    }

    // Initialize theme immediately
    applyTheme(getPreferredTheme());

    // ----------------------------------------------------
    // 2. Font Size Scaling (A-, A, A+)
    // ----------------------------------------------------
    const FONT_KEY = 'needify_fontsize';

    function applyFontSize(size) {
        const html = document.documentElement;
        html.classList.remove('font-sm', 'font-md', 'font-lg');

        if (size === 'sm') {
            html.classList.add('font-sm');
        } else if (size === 'lg') {
            html.classList.add('font-lg');
        } else {
            size = 'md';
            html.classList.add('font-md');
        }

        localStorage.setItem(FONT_KEY, size);

        document.querySelectorAll('.font-btn').forEach(btn => {
            if (btn.dataset.size === size) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    // ----------------------------------------------------
    // 3. DOM Ready Initialization
    // ----------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        // Apply saved font size
        const savedFont = localStorage.getItem(FONT_KEY) || 'md';
        applyFontSize(savedFont);

        // Bind theme toggles
        document.querySelectorAll('.theme-toggle-btn').forEach(btn => {
            btn.addEventListener('click', toggleTheme);
        });

        // Bind font size buttons
        document.querySelectorAll('.font-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                applyFontSize(this.dataset.size);
            });
        });

        // Mobile Menu Toggle
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const navMenu = document.getElementById('siteNavMenu');

        if (mobileToggle && navMenu) {
            mobileToggle.addEventListener('click', function () {
                const expanded = mobileToggle.getAttribute('aria-expanded') === 'true';
                mobileToggle.setAttribute('aria-expanded', !expanded);
                navMenu.classList.toggle('show');

                const icon = mobileToggle.querySelector('i');
                if (icon) {
                    icon.className = expanded ? 'fa-solid fa-bars' : 'fa-solid fa-xmark';
                }
            });

            // Close on click outside
            document.addEventListener('click', function (e) {
                if (!mobileToggle.contains(e.target) && !navMenu.contains(e.target) && navMenu.classList.contains('show')) {
                    navMenu.classList.remove('show');
                    mobileToggle.setAttribute('aria-expanded', 'false');
                    const icon = mobileToggle.querySelector('i');
                    if (icon) icon.className = 'fa-solid fa-bars';
                }
            });
        }

        // Scroll to Top / Bottom Buttons
        const scrollUpBtn = document.getElementById('pageScrollUp');
        const scrollDownBtn = document.getElementById('pageScrollDown');

        if (scrollUpBtn) {
            scrollUpBtn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        if (scrollDownBtn) {
            scrollDownBtn.addEventListener('click', function () {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            });
        }

        // Scroll Reveal Animations
        if ('IntersectionObserver' in window) {
            const revealObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -40px 0px'
            });

            document.querySelectorAll('.reveal').forEach(el => {
                revealObserver.observe(el);
            });
        } else {
            // Fallback for older browsers
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
        }
    });
})();
