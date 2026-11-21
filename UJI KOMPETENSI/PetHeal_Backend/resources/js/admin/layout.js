/**
 * Kerangka konsol admin: laci sidebar, tombol tema, dan toast.
 *
 * Sebelumnya ketiga perilaku ini ditulis sebagai <script> inline di
 * layouts/admin.blade.php sehingga setiap halaman memuat ulang kode yang sama
 * dan tidak bisa di-cache peramban. Semua label datang dari `boot.labels`.
 */
import { boot, label } from './boot';

const TOAST_ICONS = {
    success: 'check_circle',
    error: 'error',
    warning: 'warning',
    info: 'info',
};

/**
 * Toast tunggal untuk seluruh konsol admin.
 *
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} [type]
 */
export function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');

    if (!container || !message) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('span');
    icon.className = 'material-symbols-outlined text-base flex-shrink-0';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = TOAST_ICONS[type] || TOAST_ICONS.info;

    const text = document.createElement('span');
    text.className = 'flex-1 text-sm';
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-close material-symbols-outlined';
    close.setAttribute('aria-label', label('close_notification', 'Close'));
    close.textContent = 'close';
    close.addEventListener('click', () => toast.remove());

    toast.append(icon, text, close);
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 350);
    }, 4500);
}

// Beberapa halaman lama memanggil showToast() dari skrip inline miliknya.
window.showToast = showToast;

function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const openBtn = document.getElementById('sidebar-open');
    const closeBtn = document.getElementById('sidebar-close');
    const toggleBtn = document.getElementById('sidebar-toggle');

    if (!sidebar) {
        return;
    }

    const desktop = window.matchMedia('(min-width: 1024px)');

    const setDrawer = (open) => {
        sidebar.classList.toggle('is-open', open);
        document.body.classList.toggle('sidebar-open', open);
        if (backdrop) {
            backdrop.classList.toggle('hidden', !open);
        }
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', String(open));
        }
    };

    const setCollapsed = (collapsed) => {
        sidebar.classList.toggle('sidebar-collapsed', collapsed);
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', String(!collapsed));
        }
        try {
            localStorage.setItem('sidebar-collapsed', String(collapsed));
        } catch (error) {
            // localStorage bisa diblokir pada mode privat.
        }
    };

    try {
        setCollapsed(localStorage.getItem('sidebar-collapsed') === 'true');
    } catch (error) {
        // abaikan
    }

    if (openBtn) openBtn.addEventListener('click', () => setDrawer(true));
    if (closeBtn) closeBtn.addEventListener('click', () => setDrawer(false));
    if (backdrop) backdrop.addEventListener('click', () => setDrawer(false));
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            setCollapsed(!sidebar.classList.contains('sidebar-collapsed'));
        });
    }

    sidebar.querySelectorAll('[data-nav-group-toggle]').forEach((button) => {
        const group = button.closest('[data-nav-group]');
        const submenu = group ? group.querySelector('.sidebar-submenu') : null;

        if (!submenu) {
            return;
        }

        button.addEventListener('click', () => {
            // Saat rail diciutkan submenu tidak punya ruang; buka sidebar dulu
            // agar anak menu tetap bisa dijangkau.
            if (sidebar.classList.contains('sidebar-collapsed')) {
                setCollapsed(false);
                submenu.classList.remove('hidden');
            } else {
                submenu.classList.toggle('hidden');
            }

            const open = !submenu.classList.contains('hidden');
            button.setAttribute('aria-expanded', String(open));
            button.querySelector('.nav-group-chevron')?.classList.toggle('is-open', open);
        });
    });

    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            if (!desktop.matches) {
                setDrawer(false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setDrawer(false);
        }
    });

    desktop.addEventListener('change', (event) => {
        if (event.matches) {
            setDrawer(false);
        }
    });
}

function initThemeToggle() {
    const html = document.documentElement;
    const toggle = document.getElementById('darkModeToggle');
    const icon = document.getElementById('darkModeIcon');

    const applyDark = (isDark) => {
        html.classList.toggle('dark', isDark);
        html.classList.toggle('light', !isDark);
        if (icon) {
            icon.textContent = isDark ? 'light_mode' : 'dark_mode';
        }
        try {
            localStorage.setItem('dark-mode', String(isDark));
        } catch (error) {
            // abaikan
        }
        window.dispatchEvent(new CustomEvent('petheal:themechange', { detail: { isDark } }));
    };

    applyDark(html.classList.contains('dark'));

    if (toggle) {
        toggle.addEventListener('click', () => applyDark(!html.classList.contains('dark')));
    }
}

/**
 * Titik masuk kerangka admin.
 */
export function initAdminLayout() {
    initSidebar();
    initThemeToggle();

    boot.flashes.forEach((flash) => showToast(flash.message, flash.type));
}
