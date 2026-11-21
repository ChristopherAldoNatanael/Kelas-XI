/**
 * Command palette (Ctrl/Cmd + K) konsol admin.
 *
 * Logika sama dengan versi inline lama, tetapi dipindahkan ke modul agar
 * label bahasa tidak lagi ditulis sebagai @json(__('...')) di dalam <script>
 * halaman. Label dibaca dari `boot.labels`, URL dari `boot.routes`.
 */
import { boot, label } from './boot';

const QUICK_ACTIONS = [
    {
        titleKey: 'command_board_title',
        subtitleKey: 'command_board_desc',
        routeKey: 'board',
        icon: 'calendar_view_day',
    },
    {
        titleKey: 'command_list_title',
        subtitleKey: 'command_list_desc',
        routeKey: 'bookings',
        icon: 'calendar_today',
    },
    {
        titleKey: 'command_payments_title',
        subtitleKey: 'command_payments_desc',
        routeKey: 'payments',
        icon: 'receipt_long',
    },
];

const TYPE_ICON = { pet: 'pets', owner: 'person', booking: 'calendar_today' };

export function initCommandPalette() {
    const modal = document.getElementById('commandPalette');
    const input = document.getElementById('commandPaletteInput');
    const body = document.getElementById('commandPaletteBody');
    const trigger = document.getElementById('commandPaletteTrigger');
    const spinner = document.getElementById('commandPaletteSpinner');

    if (!modal || !input || !body || !trigger || !spinner) {
        return;
    }

    const searchUrl = boot.routes.search || '';

    const quickActions = QUICK_ACTIONS.map((action) => ({
        type: 'action',
        title: label(action.titleKey),
        subtitle: label(action.subtitleKey),
        url: boot.routes[action.routeKey] || '',
        icon: action.icon,
    }));

    let results = [];
    let activeIndex = -1;
    let debounceTimer = null;
    let requestSeq = 0;
    let controller = null;

    const isOpen = () => !modal.classList.contains('hidden');

    const navigate = (url) => {
        if (url) {
            window.location.href = url;
        }
    };

    const createHeading = (text) => {
        const heading = document.createElement('p');
        heading.className = 'px-3 pt-3 pb-1 text-[9px] font-bold uppercase tracking-[0.18em] text-slate-400';
        heading.textContent = text;

        return heading;
    };

    const setActive = (index, scroll) => {
        if (!results.length) {
            return;
        }

        activeIndex = Math.max(0, Math.min(index, results.length - 1));
        body.querySelectorAll('[data-command-item]').forEach((node) => {
            const isActive = Number(node.dataset.index) === activeIndex;
            node.classList.toggle('bg-slate-50', isActive);
            node.classList.toggle('dark:bg-slate-800/70', isActive);
            node.classList.toggle('ring-1', isActive);
            node.classList.toggle('ring-primary/30', isActive);
            node.setAttribute('aria-selected', isActive ? 'true' : 'false');
            if (isActive && scroll) {
                node.scrollIntoView({ block: 'nearest' });
            }
        });
    };

    const createResultRow = (item, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.commandItem = 'true';
        button.dataset.index = String(index);
        button.className = 'command-result w-full flex items-start gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/70';
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', 'false');

        const iconWrap = document.createElement('span');
        iconWrap.className = 'w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 flex items-center justify-center flex-shrink-0';
        const icon = document.createElement('span');
        icon.className = 'material-symbols-outlined text-[17px]';
        icon.textContent = item.icon || TYPE_ICON[item.type] || 'calendar_today';
        iconWrap.appendChild(icon);

        const copy = document.createElement('span');
        copy.className = 'min-w-0 flex-1';
        const title = document.createElement('span');
        title.className = 'block text-sm font-semibold text-slate-800 dark:text-slate-100 truncate';
        title.textContent = item.title || label('untitled', '-');
        const subtitle = document.createElement('span');
        subtitle.className = 'block text-[11px] text-slate-500 truncate mt-0.5';
        subtitle.textContent = item.subtitle || '';
        copy.append(title, subtitle);

        const type = document.createElement('span');
        type.className = 'text-[9px] font-bold uppercase tracking-wider text-slate-400 flex-shrink-0 mt-1';
        type.textContent = label(`command_type_${item.type}`, '');

        button.append(iconWrap, copy, type);
        button.addEventListener('mouseenter', () => setActive(index, false));
        button.addEventListener('click', () => navigate(item.url));

        return button;
    };

    const renderQuickActions = () => {
        body.replaceChildren();
        results = quickActions;
        activeIndex = -1;
        body.appendChild(createHeading(label('command_quick_actions')));

        quickActions.forEach((item, index) => body.appendChild(createResultRow(item, index)));

        const hint = document.createElement('p');
        hint.className = 'px-3 pt-3 pb-2 text-[11px] text-slate-400';
        hint.textContent = label('command_min_chars');
        body.appendChild(hint);
    };

    const renderMessage = (title, message, withRetry) => {
        body.replaceChildren();
        results = [];
        activeIndex = -1;

        const wrap = document.createElement('div');
        wrap.className = 'px-4 py-10 text-center';

        const icon = document.createElement('span');
        icon.className = 'material-symbols-outlined text-slate-300 text-4xl';
        icon.textContent = withRetry ? 'cloud_off' : 'search_off';

        const heading = document.createElement('p');
        heading.className = 'text-sm font-semibold text-slate-700 dark:text-slate-200 mt-2';
        heading.textContent = title;

        const copy = document.createElement('p');
        copy.className = 'text-[11px] text-slate-500 mt-1';
        copy.textContent = message;

        wrap.append(icon, heading, copy);

        if (withRetry) {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'mt-4 px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors';
            retry.textContent = label('try_again');
            retry.addEventListener('click', () => runSearch(input.value));
            wrap.appendChild(retry);
        }

        body.appendChild(wrap);
    };

    const renderEmpty = () => {
        renderMessage(label('command_no_results_title'), label('command_no_results_desc'), false);
    };

    const renderGroups = (payload) => {
        const groups = ['booking', 'pet', 'owner'].map((type) => ({
            label: label(`command_type_${type}`, ''),
            items: payload[`${type}s`] || [],
        }));

        const allItems = groups.reduce((items, group) => items.concat(group.items), []);

        if (!allItems.length) {
            renderEmpty();

            return;
        }

        body.replaceChildren();
        results = allItems;
        activeIndex = -1;

        let index = 0;
        groups.forEach((group) => {
            if (!group.items.length) {
                return;
            }
            body.appendChild(createHeading(group.label));
            group.items.forEach((item) => {
                body.appendChild(createResultRow(item, index));
                index += 1;
            });
        });
        setActive(0, false);
    };

    function runSearch(query) {
        const term = (query || '').trim();

        if (term.length < 2) {
            requestSeq += 1;
            if (controller) controller.abort();
            spinner.classList.add('hidden');
            renderQuickActions();

            return;
        }

        const seq = ++requestSeq;
        if (controller) controller.abort();
        controller = new AbortController();
        spinner.classList.remove('hidden');

        fetch(`${searchUrl}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Search failed');
                }

                return response.json();
            })
            .then((payload) => {
                if (seq !== requestSeq) {
                    return;
                }
                if (payload.message) {
                    renderMessage(label('command_select_clinic_title'), payload.message, false);

                    return;
                }
                renderGroups(payload.data || {});
            })
            .catch((error) => {
                if (error.name === 'AbortError' || seq !== requestSeq) {
                    return;
                }
                renderMessage(
                    label('command_search_failed_title'),
                    label('command_search_failed_desc'),
                    true,
                );
            })
            .finally(() => {
                if (seq === requestSeq) {
                    spinner.classList.add('hidden');
                }
            });
    }

    const openPalette = () => {
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        input.value = '';
        renderQuickActions();
        setTimeout(() => input.focus(), 30);
    };

    const closePalette = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        if (controller) controller.abort();
        spinner.classList.add('hidden');
        input.value = '';
        results = [];
        activeIndex = -1;
    };

    trigger.addEventListener('click', () => (isOpen() ? closePalette() : openPalette()));

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const value = input.value;
        debounceTimer = setTimeout(() => runSearch(value), 180);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1, true);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex - 1, true);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (results.length) {
                navigate(results[activeIndex >= 0 ? activeIndex : 0].url);
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        const isShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';

        if (isShortcut) {
            event.preventDefault();
            isOpen() ? closePalette() : openPalette();

            return;
        }

        if (event.key === 'Escape' && isOpen()) {
            closePalette();
        }
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closePalette();
        }
    });
}
