/**
 * Perilaku UI bersama untuk konsol admin & portal dokter.
 *
 * Dua masalah yang diselesaikan modul ini (lihat docs/AUDIT_FIXES_REPORT.md
 * UIX-08 dan UIX-19):
 *
 * 1. Setiap halaman punya modal sendiri-sendiri. Tiga di antaranya tidak bisa
 *    ditutup dengan tombol Escape dan lima tidak memindahkan fokus, sehingga
 *    pengguna keyboard/pembaca layar bisa "terjebak" di dalam modal.
 *    Di sini semua modal `[role="dialog"]` diberi perilaku yang sama tanpa
 *    harus menyentuh 11 view satu per satu.
 *
 * 2. Konfirmasi tindakan destruktif ditulis berbeda di setiap halaman. Modul
 *    ini menyediakan satu jalur konfirmasi:
 *      - `data-confirm="pesan"`      → dialog ya/tidak
 *      - `data-confirm-type="HAPUS"` → pengguna wajib mengetik kata tersebut
 *        (dipakai bila server juga memeriksa kata yang sama).
 *
 * Tidak ada dependensi. Modul ini tidak melakukan apa pun kalau elemennya
 * tidak ada di halaman, jadi aman dimuat global lewat app.js.
 */

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([type="hidden"]):not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

/** Modal dianggap terbuka bila kelas `hidden` sudah dilepas dan ia punya kotak. */
function isOpen(dialog) {
    return Boolean(dialog) && !dialog.classList.contains('hidden') && dialog.getClientRects().length > 0;
}

function focusables(root) {
    return Array.from(root.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
        (el) => el.getClientRects().length > 0 && el.getAttribute('aria-hidden') !== 'true'
    );
}

/**
 * Tutup modal dengan cara paling "setia": tekan tombol tutup milik halaman,
 * sehingga pembersihan milik halaman (reset form, buka kunci scroll) tetap
 * berjalan. Kalau tidak ada, sembunyikan langsung.
 */
function dismiss(dialog) {
    if (!isOpen(dialog)) {
        return;
    }

    const explicit = dialog.querySelector('[data-modal-close]');
    if (explicit) {
        explicit.click();
        return;
    }

    const candidates = Array.from(dialog.querySelectorAll('[onclick]'));
    const closer = candidates.find((el) => /close|dismiss|tutup|batal/i.test(el.getAttribute('onclick') || ''));
    if (closer) {
        closer.click();
        return;
    }

    dialog.classList.add('hidden');
    dialog.classList.remove('flex');
    dialog.setAttribute('aria-hidden', 'true');
}

/** Escape menutup modal teratas; Tab berputar di dalam modal yang terbuka. */
function initDialogKeyboard() {
    let lastFocus = null;

    document.addEventListener(
        'keydown',
        (event) => {
            const openDialogs = Array.from(document.querySelectorAll('[role="dialog"]')).filter(isOpen);
            if (openDialogs.length === 0) {
                lastFocus = null;
                return;
            }

            const top = openDialogs[openDialogs.length - 1];

            if (event.key === 'Escape') {
                event.preventDefault();
                dismiss(top);
                if (lastFocus && typeof lastFocus.focus === 'function') {
                    lastFocus.focus();
                }
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            const items = focusables(top);
            if (items.length === 0) {
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        true
    );

    // Fokus awal saat modal dibuka, dan kembalikan saat ditutup.
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            const dialog = mutation.target;
            if (!(dialog instanceof Element) || dialog.getAttribute('role') !== 'dialog') {
                return;
            }

            if (isOpen(dialog)) {
                if (!dialog.dataset.focusInitialised) {
                    dialog.dataset.focusInitialised = '1';
                    lastFocus = document.activeElement;
                    const target = dialog.querySelector('[autofocus]') || focusables(dialog)[0];
                    if (target) {
                        // Tunggu satu frame: modal biasanya baru dilepas dari `hidden`.
                        requestAnimationFrame(() => target.focus());
                    }
                }
            } else if (dialog.dataset.focusInitialised) {
                delete dialog.dataset.focusInitialised;
            }
        });
    });

    document.querySelectorAll('[role="dialog"]').forEach((dialog) => {
        observer.observe(dialog, { attributes: true, attributeFilter: ['class', 'style', 'hidden'] });
    });
}

/** Dialog konfirmasi ketik-dan-lanjut, dibuat sekali lalu dipakai berulang. */
function createTypedConfirm() {
    const host = document.createElement('div');
    host.id = 'admin-typed-confirm';
    host.className = 'fixed inset-0 z-[95] hidden items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm';
    host.setAttribute('role', 'dialog');
    host.setAttribute('aria-modal', 'true');
    host.setAttribute('aria-labelledby', 'admin-typed-confirm-title');
    host.innerHTML = `
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400">
                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">warning</span>
                </span>
                <div class="min-w-0">
                    <h2 id="admin-typed-confirm-title" class="text-base font-bold text-slate-900 dark:text-white"></h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400" data-role="message"></p>
                </div>
            </div>
            <form class="mt-4 space-y-3" data-role="form">
                <div>
                    <label class="admin-label" for="admin-typed-confirm-input" data-role="label"></label>
                    <input id="admin-typed-confirm-input" type="text" autocomplete="off" class="admin-input mt-1 font-mono uppercase tracking-widest" required>
                </div>
                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" class="admin-btn-secondary" data-modal-close data-role="cancel"></button>
                    <button type="submit" class="admin-btn-danger" data-role="submit"></button>
                </div>
            </form>
        </div>`;
    document.body.appendChild(host);

    const form = host.querySelector('[data-role="form"]');
    const input = host.querySelector('#admin-typed-confirm-input');
    let pending = null;

    const close = () => {
        host.classList.add('hidden');
        host.classList.remove('flex');
        host.setAttribute('aria-hidden', 'true');
        pending = null;
        input.value = '';
    };

    host.querySelector('[data-role="cancel"]').addEventListener('click', close);
    host.addEventListener('mousedown', (event) => {
        if (event.target === host) {
            close();
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = pending;
        if (!form) {
            return close();
        }

        const word = (input.value || '').trim();
        const field = form.dataset.confirmField;
        if (field) {
            let hidden = form.querySelector(`input[data-confirm-value="${field}"]`);
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = field;
                hidden.dataset.confirmValue = field;
                form.appendChild(hidden);
            }
            hidden.value = word;
        }

        form.dataset.confirmed = '1';
        close();
        HTMLFormElement.prototype.submit.call(form);
    });

    return {
        open(form, options) {
            pending = form;
            host.querySelector('[data-role="message"]').textContent = options.message || '';
            host.querySelector('[data-role="label"]').textContent = options.label || '';
            host.querySelector('#admin-typed-confirm-title').textContent = options.title || '';
            host.querySelector('[data-role="cancel"]').textContent = options.cancel || '';
            host.querySelector('[data-role="submit"]').textContent = options.submit || '';
            host.classList.remove('hidden');
            host.classList.add('flex');
            host.removeAttribute('aria-hidden');
        },
        isOpen: () => isOpen(host),
    };
}

let typedConfirmHost = null;

function ensureTypedConfirm() {
    if (!typedConfirmHost) {
        typedConfirmHost = createTypedConfirm();
    }
    return typedConfirmHost;
}

function initConfirmations() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        const typedWord = form.dataset.confirmType;
        if (typedWord) {
            event.preventDefault();
            ensureTypedConfirm().open(form, {
                title: form.dataset.confirmTitle || '',
                message: form.dataset.confirmMessage || '',
                label: form.dataset.confirmLabel || typedWord,
                cancel: form.dataset.confirmCancel || 'Batal',
                submit: form.dataset.confirmSubmit || 'Lanjutkan',
            });
            return;
        }

        const message = form.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
}

export function initAdminUI() {
    // Dialog konfirmasi dibuat lebih dulu supaya pemantau fokus/Escape di bawah
    // ikut mencakupnya (dialog itu disisipkan ke DOM saat runtime).
    ensureTypedConfirm();
    initDialogKeyboard();
    initConfirmations();
}
