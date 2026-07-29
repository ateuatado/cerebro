/* Cerebro — app.js — Comportamento global: tema, sidebar, mobile */
/* Assets locais, sem inline, sem CDN — AGENTS.md */

'use strict';

// ─── Theme Toggle ──────────────────────────────────────────────────────
const ThemeManager = (() => {
    const KEY = 'cbr-theme';
    const root = document.documentElement;

    function get() {
        return localStorage.getItem(KEY) || 'dark';
    }

    function set(theme) {
        root.setAttribute('data-theme', theme);
        localStorage.setItem(KEY, theme);
        updateToggleIcon(theme);
    }

    function updateToggleIcon(theme) {
        document.querySelectorAll('#theme-toggle, #login-theme-toggle').forEach(btn => {
            const icon = btn.querySelector('i');
            if (!icon) return;
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            btn.setAttribute('aria-label', theme === 'dark' ? 'Mudar para modo claro' : 'Mudar para modo escuro');
        });
    }

    function toggle() {
        const next = get() === 'dark' ? 'light' : 'dark';
        set(next);
    }

    function init() {
        set(get());
        document.querySelectorAll('#theme-toggle, #login-theme-toggle').forEach(btn => {
            btn.addEventListener('click', toggle);
        });
    }

    return { init, get, set, toggle };
})();

// ─── Sidebar Mobile ────────────────────────────────────────────────────
const SidebarManager = (() => {
    let sidebar, overlay, toggle;

    function open() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        toggle && toggle.setAttribute('aria-expanded', 'true');
    }

    function close() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        toggle && toggle.setAttribute('aria-expanded', 'false');
    }

    function init() {
        sidebar  = document.querySelector('.cbr-sidebar');
        overlay  = document.querySelector('.cbr-sidebar-overlay');
        toggle   = document.querySelector('.cbr-menu-toggle');

        if (!sidebar) return;

        toggle && toggle.addEventListener('click', () => {
            sidebar.classList.contains('open') ? close() : open();
        });

        overlay && overlay.addEventListener('click', close);

        // Fechar ao clicar em nav link no mobile
        sidebar.querySelectorAll('.cbr-nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) close();
            });
        });

        // Fechar com ESC
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') close();
        });
    }

    return { init, open, close };
})();

// ─── Active nav link ───────────────────────────────────────────────────
function setActiveNavLinks() {
    const currentPath = window.location.pathname;
    document.querySelectorAll('.cbr-nav-link, .cbr-bottom-item').forEach(link => {
        const href = link.getAttribute('href') || '';
        // Extrai o path sem base_url
        const linkPath = href.replace(/^https?:\/\/[^/]+/, '');
        link.classList.remove('active');
        if (linkPath && currentPath.startsWith(linkPath) && linkPath !== '/') {
            link.classList.add('active');
        } else if (linkPath && linkPath !== '/' && currentPath === linkPath) {
            link.classList.add('active');
        }
    });
    // Dashboard: exact match
    document.querySelectorAll('[data-nav="dashboard"]').forEach(el => {
        if (currentPath.endsWith('/public') || currentPath.endsWith('/public/') ||
            currentPath === '/' || currentPath.endsWith('/cerebro/public/')) {
            el.classList.add('active');
        }
    });
}

// ─── Flash messages auto-dismiss ──────────────────────────────────────
function initFlashDismiss() {
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(alert => {
        const delay = parseInt(alert.dataset.autoDismiss || '4000');
        setTimeout(() => {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, delay);
    });
}

// ─── Tooltips Bootstrap ────────────────────────────────────────────────
function initTooltips() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el, { trigger: 'hover' });
        });
    }
}

// ─── Touch feedback (ripple) ───────────────────────────────────────────
function initRipple() {
    document.querySelectorAll('.cbr-nav-link, .cbr-bottom-item, .cbr-entity-card').forEach(el => {
        el.addEventListener('pointerdown', function(e) {
            const rect = this.getBoundingClientRect();
            const ripple = document.createElement('span');
            const size = Math.max(rect.width, rect.height);
            ripple.style.cssText = `
                position:absolute;width:${size}px;height:${size}px;
                left:${e.clientX - rect.left - size/2}px;
                top:${e.clientY - rect.top - size/2}px;
                border-radius:50%;background:rgba(124,106,247,0.15);
                transform:scale(0);transition:transform 0.5s ease,opacity 0.5s ease;
                opacity:1;pointer-events:none;
            `;
            if (getComputedStyle(this).position === 'static') {
                this.style.position = 'relative';
            }
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            requestAnimationFrame(() => {
                ripple.style.transform = 'scale(2.5)';
                ripple.style.opacity = '0';
            });
            ripple.addEventListener('transitionend', () => ripple.remove());
        });
    });
}

// ─── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
    SidebarManager.init();
    setActiveNavLinks();
    initFlashDismiss();
    initTooltips();
    initRipple();
});
