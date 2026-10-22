@php
    $isBoardView = request()->routeIs('admin.bookings.schedule');
    $boardQuery = array_filter([
        'date' => request('date'),
        'doctor_id' => request('doctor_id'),
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="mb-6">
    <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">
        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">calendar_month</span>
        Operasional klinik
    </p>

    <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('menu.appointments') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                @if($isBoardView)
                    Pantau urutan kunjungan per dokter dan jam, lalu konfirmasi atau selesaikan langsung dari sini.
                @else
                    Cari, filter, dan kelola seluruh janji temu klinik. Untuk alur hari ini, buka papan jadwal.
                @endif
            </p>
        </div>

        <div class="inline-flex self-start rounded-xl border border-slate-200/80 bg-white p-1 dark:border-slate-700 dark:bg-slate-900" role="tablist" aria-label="Tampilan booking">
            <a href="{{ route('admin.bookings.index') }}" role="tab"
               @if(!$isBoardView) aria-selected="true" @endif
               class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors
                      {{ !$isBoardView
                            ? 'bg-primary text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">format_list_bulleted</span>
                Daftar Booking
            </a>
            <a href="{{ route('admin.bookings.schedule', $boardQuery) }}" role="tab"
               @if($isBoardView) aria-selected="true" @endif
               class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition-colors
                      {{ $isBoardView
                            ? 'bg-primary text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">view_timeline</span>
                Papan Jadwal
            </a>
        </div>
    </div>
</div>
