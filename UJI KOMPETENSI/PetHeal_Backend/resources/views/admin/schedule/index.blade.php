@extends('layouts.admin')

@section('title', __('menu.schedule') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.appointments_schedule'))

@section('content')
@php
    $statusStyles = [
        'pending' => ['bg-amber-50 text-amber-700 border-amber-200', 'Menunggu'],
        'confirmed' => ['bg-sky-50 text-sky-700 border-sky-200', 'Terkonfirmasi'],
        'completed' => ['bg-emerald-50 text-emerald-700 border-emerald-200', 'Selesai'],
        'cancelled' => ['bg-rose-50 text-rose-700 border-rose-200', 'Dibatalkan'],
    ];
    $paymentStyles = [
        'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'dp_paid' => 'bg-blue-50 text-blue-700 border-blue-200',
        'partial' => 'bg-orange-50 text-orange-700 border-orange-200',
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'failed' => 'bg-rose-50 text-rose-700 border-rose-200',
        'unpaid' => 'bg-slate-50 text-slate-600 border-slate-200',
    ];
@endphp

@include('admin.bookings._tabs')

{{-- Date navigator --}}
<div class="glass-card rounded-2xl border border-slate-200/60 p-4 sm:p-5 mb-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.bookings.schedule', ['date' => $previousDate, 'doctor_id' => $selectedDoctorId]) }}"
               class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
               aria-label="Hari sebelumnya">
                <span class="material-symbols-outlined text-[20px]">chevron_left</span>
            </a>

            <form method="GET" action="{{ route('admin.bookings.schedule') }}" class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="schedule-date" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Tanggal</label>
                    <input id="schedule-date" type="date" name="date" value="{{ $selectedDate }}"
                           class="px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div>
                    <label for="schedule-doctor" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Dokter</label>
                    <select id="schedule-doctor" name="doctor_id"
                            class="px-3 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-primary focus:border-transparent">
                        <option value="">Semua dokter</option>
                        @foreach($doctors as $doctor)
                            <option value="{{ $doctor->id }}" {{ (string) $selectedDoctorId === (string) $doctor->id ? 'selected' : '' }}>
                                {{ $doctor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-emerald-600 transition-colors">
                    Tampilkan
                </button>
                <a href="{{ route('admin.bookings.schedule', ['date' => now()->toDateString(), 'doctor_id' => $selectedDoctorId]) }}"
                   class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                    Hari ini
                </a>
            </form>

            <a href="{{ route('admin.bookings.schedule', ['date' => $nextDate, 'doctor_id' => $selectedDoctorId]) }}"
               class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
               aria-label="Hari berikutnya">
                <span class="material-symbols-outlined text-[20px]">chevron_right</span>
            </a>
        </div>

        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-slate-400">{{ $isToday ? 'Hari ini' : 'Tanggal dipilih' }}</p>
                <p class="text-lg font-bold text-slate-900 dark:text-white">
                    {{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }}
                </p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary">calendar_month</span>
            </div>
        </div>
    </div>
</div>

{{-- Daily summary --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="glass-card rounded-xl border border-slate-200/60 p-5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total kunjungan</p>
        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $stats['total'] }}</p>
    </div>
    <div class="glass-card rounded-xl border border-amber-200/60 p-5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Menunggu</p>
        <p class="text-2xl font-bold text-amber-600 mt-1">{{ $stats['pending'] }}</p>
    </div>
    <div class="glass-card rounded-xl border border-sky-200/60 p-5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-sky-600">Terkonfirmasi</p>
        <p class="text-2xl font-bold text-sky-600 mt-1">{{ $stats['confirmed'] }}</p>
    </div>
    <div class="glass-card rounded-xl border border-emerald-200/60 p-5">
        <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Selesai</p>
        <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $stats['completed'] }}</p>
    </div>
</div>

{{-- Schedule board --}}
@if($doctors->isEmpty())
    <div class="glass-card rounded-2xl border border-slate-200/60 p-12 text-center">
        <span class="material-symbols-outlined text-slate-300 text-5xl">event_busy</span>
        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200 mt-4">Belum ada dokter</h3>
        <p class="text-sm text-slate-500 mt-1">Tambahkan dokter terlebih dahulu agar papan jadwal bisa menampilkan kunjungan.</p>
        <a href="{{ route('admin.doctors.index') }}" class="inline-flex items-center gap-2 mt-5 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-emerald-600 transition-colors">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Kelola dokter
        </a>
    </div>
@else
    <div class="overflow-x-auto pb-3 custom-scrollbar">
        <div class="flex items-start gap-4 min-w-max">
            @foreach($doctors as $doctor)
                @php
                    $doctorBookings = $bookingsByDoctor[$doctor->id] ?? collect();
                    $doctorSchedule = $effectiveSchedules[$doctor->id] ?? null;
                    $isClosed = (bool) ($doctorSchedule['is_closed'] ?? false);
                    $isWorking = (bool) ($doctorSchedule['is_working_day'] ?? true);
                    $isSpecial = (bool) ($doctorSchedule['has_exception'] ?? false);
                    $reviewCount = $doctorBookings->filter(fn ($b) => isset($bookingsNeedingReview[$b->id]))->count();
                @endphp
                <section class="w-[310px] flex-shrink-0 rounded-2xl border border-slate-200/70 bg-white/80 dark:bg-slate-900/60 dark:border-slate-800 overflow-hidden" aria-label="Jadwal {{ $doctor->name }}">
                    <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/30">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-primary">medical_services</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $doctor->name }}</h3>
                                    @if(!$doctor->is_active)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-600">Nonaktif</span>
                                    @endif
                                    @if($isClosed)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700 border border-rose-200">Cuti</span>
                                    @elseif($isSpecial)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-200">Jam khusus</span>
                                    @elseif(!$isWorking)
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200">Tidak praktik</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-slate-500 truncate">{{ $doctor->specialization ?? 'Dokter hewan' }}</p>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-1 rounded-lg bg-white dark:bg-slate-800 text-slate-500 border border-slate-200 dark:border-slate-700">
                                {{ $doctorBookings->count() }} janji
                            </span>
                        </div>
                        <div class="mt-3 flex items-start justify-between gap-2">
                            <p class="text-[10px] mt-0.5 flex items-start gap-1 {{ $isClosed ? 'text-rose-600' : 'text-slate-400' }}">
                                <span class="material-symbols-outlined text-[13px]">
                                    {{ $isClosed ? 'event_busy' : 'schedule' }}
                                </span>
                                <span class="leading-snug">
                                    @if($isClosed)
                                        Sehari penuh{{ $doctorSchedule['reason'] ? ' · ' . $doctorSchedule['reason'] : '' }}
                                    @elseif($isWorking)
                                        {{ $doctorSchedule['start_time'] }} - {{ $doctorSchedule['end_time'] }} WIB
                                    @else
                                        Tidak ada jam praktik
                                    @endif
                                </span>
                            </p>
                            <a href="{{ route('admin.doctors.schedule', ['id' => $doctor->id, 'date' => $selectedDate]) }}"
                               class="shrink-0 w-7 h-7 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-primary hover:border-primary/40 transition-colors inline-flex items-center justify-center"
                               title="Atur cuti / jam khusus" aria-label="Atur jadwal {{ $doctor->name }}">
                                <span class="material-symbols-outlined text-[15px]">tune</span>
                            </a>
                        </div>
                        @if($reviewCount > 0)
                            <p class="mt-2 flex items-start gap-1 rounded-lg bg-rose-50 border border-rose-100 px-2 py-1.5 text-[10px] font-medium text-rose-700">
                                <span class="material-symbols-outlined text-[13px]">notification_important</span>
                                {{ $reviewCount }} booking di luar jadwal — tinjau manual
                            </p>
                        @endif
                    </div>

                    <div class="p-3 space-y-3 max-h-[620px] overflow-y-auto custom-scrollbar">
                        @forelse($doctorBookings as $booking)
                            @php
                                $status = $statusStyles[$booking->status] ?? ['bg-slate-50 text-slate-600 border-slate-200', ucfirst($booking->status)];
                                $paymentStyle = $paymentStyles[$booking->payment_status] ?? 'bg-slate-50 text-slate-600 border-slate-200';
                            @endphp
                            <article class="rounded-xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-900 p-3.5 hover:border-primary/40 transition-colors">
                                <div class="flex items-start gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 flex flex-col items-center justify-center flex-shrink-0">
                                        <span class="text-[13px] font-bold leading-none">{{ $booking->booking_time }}</span>
                                        <span class="text-[8px] uppercase tracking-wider opacity-70 mt-0.5">WIB</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $booking->pet->name ?? 'Hewan' }}</p>
                                                <p class="text-[11px] text-slate-500 truncate">{{ $booking->user->name ?? 'Pemilik' }}</p>
                                            </div>
                                            <span class="text-[9px] font-bold px-2 py-1 rounded-md border flex-shrink-0 {{ $status[0] }}">{{ $status[1] }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 mt-2 truncate">{{ $booking->service->name ?? 'Layanan klinik' }}</p>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                            <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded border {{ $paymentStyle }}">
                                                {{ $booking->payment_status === 'paid' ? 'Lunas' : ($booking->remaining_amount > 0 ? 'Sisa Rp ' . number_format($booking->remaining_amount, 0, ',', '.') : 'Belum dibayar') }}
                                            </span>
                                            @if(isset($bookingsNeedingReview[$booking->id]))
                                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded border bg-rose-50 text-rose-700 border-rose-200 inline-flex items-center gap-0.5">
                                                    <span class="material-symbols-outlined text-[11px]">warning</span>
                                                    Di luar jadwal
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                       class="flex-1 text-center px-2 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-[11px] font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                                        Detail
                                    </a>
                                    @if($booking->status === 'pending')
                                        <form method="POST" action="{{ route('admin.bookings.confirm', $booking->id) }}" class="flex-1">
                                            @csrf
                                            <button type="submit" class="w-full px-2 py-1.5 rounded-lg bg-emerald-600 text-white text-[11px] font-semibold hover:bg-emerald-700 transition-colors">
                                                Konfirmasi
                                            </button>
                                        </form>
                                    @elseif($booking->status === 'confirmed')
                                        <form method="POST" action="{{ route('admin.bookings.complete', $booking->id) }}" class="flex-1">
                                            @csrf
                                            <button type="submit" class="w-full px-2 py-1.5 rounded-lg bg-emerald-600 text-white text-[11px] font-semibold hover:bg-emerald-700 transition-colors">
                                                Selesai
                                            </button>
                                        </form>
                                    @endif
                                    @if(in_array($booking->status, ['pending', 'confirmed'], true))
                                        <button type="button"
                                                onclick="openCancelModal({{ $booking->id }}, '{{ addslashes($booking->pet->name ?? 'hewan') }}')"
                                                class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors inline-flex items-center justify-center flex-shrink-0"
                                                title="Batalkan booking" aria-label="Batalkan booking {{ $booking->pet->name ?? '' }}">
                                            <span class="material-symbols-outlined text-[17px]">cancel</span>
                                        </button>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-200 dark:border-slate-700 px-4 py-8 text-center">
                                <span class="material-symbols-outlined text-slate-300 text-3xl">event_available</span>
                                <p class="text-xs font-semibold text-slate-500 mt-2">Tidak ada kunjungan</p>
                                <p class="text-[10px] text-slate-400 mt-1">Slot dokter ini masih kosong.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endif

{{-- Cancel modal --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden bg-slate-950/40 backdrop-blur-sm px-4 py-10 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
    <div class="mx-auto w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-700 shadow-2xl overflow-hidden">
        <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-rose-500">cancel</span>
            <h3 id="cancelModalTitle" class="text-sm font-bold text-slate-900 dark:text-white">Batalkan Booking</h3>
        </div>
        <form id="cancelForm" method="POST" class="p-5">
            @csrf
            <p id="cancelTarget" class="text-sm text-slate-500 mb-4">Berikan alasan pembatalan agar riwayat tetap jelas.</p>
            <label for="cancel-reason" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">Alasan pembatalan <span class="text-rose-500">*</span></label>
            <textarea id="cancel-reason" name="reason" required rows="3"
                      class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                      placeholder="Contoh: pemilik meminta jadwal ulang"></textarea>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeCancelModal()" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white text-sm font-semibold hover:bg-rose-700 transition-colors">
                    Batalkan Booking
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCancelModal(bookingId, petName) {
        const modal = document.getElementById('cancelModal');
        const form = document.getElementById('cancelForm');
        const target = document.getElementById('cancelTarget');
        form.action = '/admin/bookings/' + bookingId + '/cancel';
        target.textContent = 'Batalkan kunjungan ' + petName + '? Tindakan ini akan mengirim notifikasi ke pemilik.';
        modal.classList.remove('hidden');
        document.getElementById('cancel-reason').focus();
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').classList.add('hidden');
    }

    document.getElementById('cancelModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeCancelModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeCancelModal();
    });
</script>
@endsection
