@php
    $listActive = request()->routeIs('admin.medicines.*')
        && !request()->routeIs('admin.medicines.expiry', 'admin.medicines.mutations.*');

    $inventoryTabs = [
        [
            'route' => route('admin.medicines.index'),
            'active' => $listActive,
            'icon' => 'medication',
            'label' => __('menu.medicines_list'),
        ],
        [
            'route' => route('admin.medicines.expiry'),
            'active' => request()->routeIs('admin.medicines.expiry'),
            'icon' => 'event_busy',
            'label' => __('menu.medicines_expiry'),
        ],
        [
            'route' => route('admin.medicines.mutations.index'),
            'active' => request()->routeIs('admin.medicines.mutations.*'),
            'icon' => 'swap_horiz',
            'label' => __('menu.medicine_mutations'),
        ],
    ];
@endphp

<nav aria-label="{{ __('inventory.nav_label') }}"
     class="inline-flex max-w-full flex-wrap items-center gap-1 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
    @foreach($inventoryTabs as $tab)
        <a href="{{ $tab['route'] }}"
           class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors {{ $tab['active'] ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100' }}"
           @if($tab['active']) aria-current="page" @endif>
            <span class="material-symbols-outlined text-[17px]" aria-hidden="true">{{ $tab['icon'] }}</span>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
