@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $bookings */
@endphp
@extends('layouts.admin')

@section('title', __('menu.appointments') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.appointments_schedule'))

@section('content')
@include('admin.bookings._tabs')

<div class="glass-card overflow-hidden rounded-2xl border border-slate-200/60 dark:border-slate-800">

    <div class="border-b border-slate-100 p-5 dark:border-slate-800">
        <form method="GET" class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1 sm:max-w-[220px]">
                <label for="filter-status" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Status</label>
                <select id="filter-status" name="status"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-800 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <option value="">Semua status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Menunggu konfirmasi</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Terkonfirmasi</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>

            <div class="flex-1 sm:max-w-[200px]">
                <label for="filter-date" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Tanggal</label>
                <input id="filter-date" type="date" name="date" value="{{ request('date') }}"
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-800 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:ml-auto">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_list</span>
                    Terapkan
                </button>
                @if(request()->hasAny(['status', 'date']))
                    <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">restart_alt</span>
                        Reset
                    </a>
                @endif
                <a href="{{ route('admin.bookings.export', request()->only(['status', 'date'])) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">print</span>
                    Export PDF
                </a>
            </div>
        </form>

        <p class="mt-3 text-[11px] leading-5 text-slate-400">
            Export mengikuti filter yang aktif. Persempit status dan tanggal lebih dulu agar laporan mudah dibaca.
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[1040px]">
            <thead class="bg-slate-50/70 dark:bg-slate-800/40">
                <tr>
                    <th class="px-6 py-4 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Booking</th>
                    <th class="px-6 py-4 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Hewan & pemilik</th>
                    <th class="px-6 py-4 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Dokter</th>
                    <th class="px-6 py-4 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Layanan</th>
                    <th class="px-6 py-4 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Jadwal</th>
                    <th class="px-6 py-4 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Pembayaran</th>
                    <th class="px-6 py-4 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Status</th>
                    <th class="px-6 py-4 text-right text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                @forelse($bookings as $booking)
                    @php
                        $statusStyles = [
                            'pending' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu'],
                            'confirmed' => ['bg-sky-50 text-sky-700 border-sky-200', 'Terkonfirmasi'],
                            'completed' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Selesai'],
                            'cancelled' => ['bg-rose-50 text-rose-700 border-rose-200', 'Dibatalkan'],
                        ];
                        $statusStyle = $statusStyles[$booking->status] ?? ['bg-slate-50 text-slate-600 border-slate-200', ucfirst($booking->status)];
                    @endphp
                    <tr class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/20">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="{{ route('admin.bookings.show', $booking->id) }}" class="text-sm font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                                #{{ $booking->id }}
                            </a>
                            <p class="mt-0.5 text-[10px] text-slate-400">{{ $booking->created_at?->format('d M Y, H:i') }}</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                @if($booking->pet?->photo_url)
                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ $booking->pet->photo_url }}" alt="">
                                @else
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                                        <span class="material-symbols-outlined text-[16px] text-emerald-600" aria-hidden="true">pets</span>
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? 'Hewan' }}</p>
                                    <a href="{{ route('admin.users.show', $booking->user_id) }}" class="truncate text-[11px] text-slate-500 hover:text-primary dark:text-slate-400">
                                        {{ $booking->user->name ?? 'Pemilik' }}
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-300">
                            {{ $booking->doctor->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $booking->service?->name ?? '—' }}</span>
                            @if($booking->service?->price)
                                <span class="block text-[11px] tabular-nums text-slate-400">Rp {{ number_format($booking->service->price, 0, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <p class="text-sm text-slate-700 dark:text-slate-300">{{ \Carbon\Carbon::parse($booking->booking_date)->locale('id')->translatedFormat('d M Y') }}</p>
                            <p class="text-[11px] tabular-nums text-slate-400">{{ \Carbon\Carbon::parse($booking->booking_time)->format('H:i') }} WIB</p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $paymentStyles = [
                                    'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'dp_paid' => 'bg-sky-50 text-sky-700 border-sky-200',
                                    'partial' => 'bg-orange-50 text-orange-700 border-orange-200',
                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'unpaid' => 'bg-slate-50 text-slate-600 border-slate-200',
                                    'failed' => 'bg-rose-50 text-rose-700 border-rose-200',
                                ];
                            @endphp
                            @if($booking->payment_type)
                                <div class="flex flex-col gap-1">
                                    <span class="w-fit rounded border px-2 py-0.5 text-[9px] font-bold {{ $paymentStyles[$booking->payment_status] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">
                                        {{ $booking->payment_status === 'paid' ? 'Lunas' : ($booking->payment_status === 'dp_paid' ? 'DP dibayar' : 'Belum lunas') }}
                                    </span>
                                    @if($booking->total_amount > 0)
                                        <span class="text-[11px] tabular-nums text-slate-500">
                                            Rp {{ number_format($booking->total_amount, 0, ',', '.') }}
                                            @if($booking->remaining_amount > 0)
                                                <span class="text-orange-600">· sisa {{ number_format($booking->remaining_amount, 0, ',', '.') }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="rounded border px-2 py-0.5 text-[9px] font-bold {{ $statusStyle[0] }}">{{ $statusStyle[1] }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-primary dark:hover:bg-slate-800" title="Lihat detail" aria-label="Lihat detail booking #{{ $booking->id }}">
                                    <span class="material-symbols-outlined text-[20px]" aria-hidden="true">visibility</span>
                                </a>
                                @if($booking->status === 'pending')
                                    <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-900/30" title="Konfirmasi" aria-label="Konfirmasi booking #{{ $booking->id }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">check_circle</span>
                                        </button>
                                    </form>
                                @endif
                                @if(in_array($booking->status, ['pending', 'confirmed'], true))
                                    <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-600 dark:hover:bg-emerald-900/30" title="Tandai selesai" aria-label="Tandai selesai booking #{{ $booking->id }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">task_alt</span>
                                        </button>
                                    </form>
                                    <button type="button"
                                            onclick="showCancelModal({{ $booking->id }}, @js($booking->pet->name ?? 'hewan'))"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30"
                                            title="Batalkan" aria-label="Batalkan booking #{{ $booking->id }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">cancel</span>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
                                <span class="material-symbols-outlined text-[44px] text-slate-300" aria-hidden="true">event_none</span>
                                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    {{ request()->hasAny(['status', 'date']) ? 'Tidak ada booking pada filter ini' : 'Belum ada booking masuk' }}
                                </p>
                                <p class="mt-1 max-w-sm text-xs leading-5 text-slate-500 dark:text-slate-400">
                                    {{ request()->hasAny(['status', 'date'])
                                        ? 'Ubah status atau tanggal untuk melihat jadwal lain.'
                                        : 'Booking baru dari aplikasi pet owner akan muncul di sini otomatis.' }}
                                </p>
                                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                                    @if(request()->hasAny(['status', 'date']))
                                        <a href="{{ route('admin.bookings.index') }}" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">Reset filter</a>
                                    @endif
                                    <a href="{{ route('admin.bookings.schedule') }}" class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-600">Buka papan jadwal</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
        {{ $bookings->withQueryString()->links() }}
    </div>
</div>

{{-- Cancel modal --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden bg-slate-950/40 px-4 py-10 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
    <div class="mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-slate-200/60 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
        <div class="flex items-center gap-2 border-b border-slate-100 p-5 dark:border-slate-800">
            <span class="material-symbols-outlined text-rose-500" aria-hidden="true">cancel</span>
            <h3 id="cancelModalTitle" class="text-sm font-bold text-slate-900 dark:text-white">Batalkan booking</h3>
        </div>
        <form id="cancelForm" method="POST" class="p-5">
            @csrf
            <p id="cancelTarget" class="mb-4 text-sm text-slate-500 dark:text-slate-400">Alasan pembatalan akan dikirim ke pemilik hewan.</p>
            <label for="cancel-reason" class="mb-2 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Alasan pembatalan <span class="text-rose-500">*</span></label>
            <textarea id="cancel-reason" name="reason" required rows="3"
                      class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                      placeholder="Contoh: pemilik meminta jadwal ulang"></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="hideCancelModal()" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">Kembali</button>
                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Batalkan booking</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showCancelModal(bookingId, petName) {
        const modal = document.getElementById('cancelModal');
        document.getElementById('cancelForm').action = '{{ url('/admin/bookings') }}/' + bookingId + '/cancel';
        document.getElementById('cancelTarget').textContent = 'Batalkan kunjungan ' + petName + '? Pemilik akan menerima notifikasi pembatalan.';
        modal.classList.remove('hidden');
        document.getElementById('cancel-reason').focus();
    }

    function hideCancelModal() {
        document.getElementById('cancelModal').classList.add('hidden');
    }

    document.getElementById('cancelModal').addEventListener('click', function (event) {
        if (event.target === this) hideCancelModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hideCancelModal();
    });
</script>
@endsection
