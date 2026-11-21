@extends('layouts.admin')

@section('title', $request->code . ' — Permintaan Layanan')
@section('header', 'Detail Permintaan Layanan')

@php
    use App\Models\ServiceRequest;
    $closed = in_array($request->status, [ServiceRequest::STATUS_CONVERTED, ServiceRequest::STATUS_REJECTED, ServiceRequest::STATUS_CANCELLED], true);
@endphp

@section('content')
<div class="space-y-6">
    <x-admin.page-header
        :title="$request->code"
        eyebrow="Permintaan Layanan"
        icon="support_agent"
        :description="ServiceRequest::statusLabel($request->status) . ' · diajukan ' . $request->created_at?->format('d M Y H:i')">
        <x-slot:actions>
            <a href="{{ route('admin.service-requests.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                Daftar Permintaan
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @foreach(['success' => 'emerald', 'error' => 'rose', 'warning' => 'amber'] as $flash => $tone)
        @if(session($flash))
            <div class="flex items-center gap-3 rounded-2xl border border-{{ $tone }}-200 bg-{{ $tone }}-50 px-5 py-3.5 text-sm font-medium text-{{ $tone }}-700 dark:border-{{ $tone }}-900/60 dark:bg-{{ $tone }}-900/20">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">
                    {{ $flash === 'success' ? 'check_circle' : ($flash === 'error' ? 'error' : 'warning') }}
                </span>
                <span>{{ session($flash) }}</span>
            </div>
        @endif
    @endforeach

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <x-admin.panel title="Pengajuan Pemilik" subtitle="Kebutuhan yang ditulis pemilik hewan">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pemilik</dt>
                        <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $request->user->name ?? '—' }}</dd>
                        <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $request->user->email ?? '' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Hewan</dt>
                        <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">
                            {{ $request->pet->name ?? '—' }}
                            <span class="text-xs font-normal text-slate-500">· {{ $request->pet->species ?? '' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Jenis kebutuhan</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $request->need_type_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Preferensi jadwal</dt>
                        <dd class="text-sm text-slate-700 dark:text-slate-300">
                            {{ $request->preferred_date ?? 'Fleksibel' }}
                            {{ $request->preferred_time ? '· ' . $request->preferred_time : '' }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Keluhan / penjelasan</dt>
                        <dd class="mt-1 whitespace-pre-line rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">{{ $request->complaint }}</dd>
                    </div>
                    @if($request->medicalRecord)
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Rekam medis rujukan</dt>
                            <dd class="text-sm text-slate-700 dark:text-slate-300">
                                <a href="{{ route('admin.medical-records.show', $request->medicalRecord->id) }}" class="font-semibold text-primary hover:underline">
                                    Rekam medis #{{ $request->medicalRecord->id }}
                                </a>
                                · {{ format_tanggal($request->medicalRecord->created_at) }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-admin.panel>

            @if($request->doctor_recommendation || $request->doctor_notes)
                <x-admin.panel title="Evaluasi Dokter" :subtitle="$request->doctor?->name ?? 'Dokter penanggung jawab'">
                    <p class="whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $request->doctor_recommendation }}</p>
                    @if($request->doctor_notes)
                        <p class="mt-2 whitespace-pre-line border-t border-slate-100 pt-2 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400">{{ $request->doctor_notes }}</p>
                    @endif
                    <p class="mt-3 text-xs font-semibold {{ $request->needs_follow_up ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400' }}">
                        {{ $request->needs_follow_up ? 'Dokter menilai kontrol lanjutan diperlukan.' : 'Kontrol lanjutan tidak diwajibkan.' }}
                    </p>
                </x-admin.panel>
            @endif

            @if($request->offered_at)
                <x-admin.panel title="Penawaran Aktif" subtitle="Dikirim {{ format_tanggal($request->offered_at, 'datetime') }}">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Layanan</dt>
                            <dd class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $request->offer_service_name }}</dd>
                            @if($request->offer_description)
                                <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $request->offer_description }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Harga</dt>
                            <dd class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                {{ format_rupiah((float) $request->offer_price) }}
                                <span class="text-[10px] font-bold uppercase {{ $request->offer_price_type === 'estimate' ? 'text-amber-600' : 'text-emerald-600' }}">
                                    {{ $request->offer_price_type === 'estimate' ? 'Estimasi' : 'Final' }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Dokter & jadwal</dt>
                            <dd class="text-sm text-slate-700 dark:text-slate-300">
                                {{ $request->offerDoctor->name ?? '—' }}<br>
                                {{ $request->offer_schedule_date ?? '—' }} {{ $request->offer_schedule_time ? '· ' . $request->offer_schedule_time : '' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Berlaku sampai</dt>
                            <dd class="text-sm text-slate-700 dark:text-slate-300">{{ $request->offer_valid_until?->format('d M Y H:i') ?? 'Tanpa batas' }}</dd>
                        </div>
                    </dl>
                </x-admin.panel>
            @endif

            @if($request->admin_notes)
                <x-admin.panel title="Catatan Klinik" flush>
                    <p class="whitespace-pre-line px-5 py-4 text-sm leading-6 text-slate-700 dark:text-slate-300">{{ $request->admin_notes }}</p>
                </x-admin.panel>
            @endif
        </div>

        <div class="space-y-6">
            @if(!$closed)
                <x-admin.panel title="Tindakan" subtitle="Setiap tindakan tercatat di audit log">
                    <div class="space-y-5">
                        <form method="POST" action="{{ route('admin.service-requests.update', $request->id) }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="action" value="review">
                            <button class="admin-btn-secondary w-full !py-2">Tandai sedang ditinjau</button>
                        </form>

                        <form method="POST" action="{{ route('admin.service-requests.update', $request->id) }}" class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                            @csrf
                            <input type="hidden" name="action" value="assign_doctor">
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Tugaskan dokter untuk evaluasi</label>
                            <select name="doctor_id" class="admin-input" required>
                                <option value="">Pilih dokter</option>
                                @foreach($doctors as $doctorOption)
                                    <option value="{{ $doctorOption->id }}" @selected($request->doctor_id === $doctorOption->id)>{{ $doctorOption->name }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="admin_notes" value="{{ $request->admin_notes }}" placeholder="Catatan untuk dokter (opsional)" class="admin-input">
                            <button class="admin-btn-primary w-full !py-2">Tugaskan dokter</button>
                        </form>

                        <form method="POST" action="{{ route('admin.service-requests.update', $request->id) }}" class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                            @csrf
                            <input type="hidden" name="action" value="request_info">
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Minta informasi tambahan</label>
                            <textarea name="admin_notes" rows="3" class="admin-input" placeholder="Informasi apa yang dibutuhkan klinik?" required></textarea>
                            <button class="admin-btn-secondary w-full !py-2">Kirim permintaan informasi</button>
                        </form>
                    </div>
                </x-admin.panel>

                <x-admin.panel title="Susun Penawaran" subtitle="Harga ditentukan admin, bukan dokter">
                    <form method="POST" action="{{ route('admin.service-requests.update', $request->id) }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="action" value="offer">

                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Layanan katalog (opsional)</label>
                            <select name="offer_service_id" class="admin-input">
                                <option value="">— Layanan khusus / belum ada di katalog —</option>
                                @foreach($services as $serviceOption)
                                    <option value="{{ $serviceOption->id }}" @selected($request->offer_service_id === $serviceOption->id)>
                                        {{ $serviceOption->name }} — {{ format_rupiah((float) $serviceOption->price) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Nama layanan penawaran</label>
                            <input type="text" name="offer_service_name" value="{{ $request->offer_service_name }}" class="admin-input" placeholder="mis. Pemeriksaan USG abdomen">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Deskripsi</label>
                            <textarea name="offer_description" rows="2" class="admin-input">{{ $request->offer_description }}</textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Harga (Rp)</label>
                                <input type="number" step="1000" min="0" name="offer_price" value="{{ $request->offer_price }}" class="admin-input">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Jenis harga</label>
                                <select name="offer_price_type" class="admin-input">
                                    <option value="final" @selected($request->offer_price_type !== 'estimate')>Final</option>
                                    <option value="estimate" @selected($request->offer_price_type === 'estimate')>Estimasi</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Durasi (menit, opsional)</label>
                            <input type="number" min="0" name="offer_duration_minutes" value="{{ $request->offer_duration_minutes }}" class="admin-input">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Dokter</label>
                            <select name="offer_doctor_id" class="admin-input" required>
                                <option value="">Pilih dokter</option>
                                @foreach($doctors as $doctorOption)
                                    <option value="{{ $doctorOption->id }}" @selected($request->offer_doctor_id === $doctorOption->id)>{{ $doctorOption->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Tanggal</label>
                                <input type="date" name="offer_schedule_date" min="{{ now()->toDateString() }}" value="{{ $request->offer_schedule_date }}" class="admin-input" required>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Jam</label>
                                <input type="time" name="offer_schedule_time" value="{{ $request->offer_schedule_time }}" class="admin-input" required>
                            </div>
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Berlaku sampai (opsional)</label>
                            <input type="datetime-local" name="offer_valid_until" value="{{ $request->offer_valid_until?->format('Y-m-d\TH:i') }}" class="admin-input">
                        </div>

                        <div>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Catatan klinik</label>
                            <textarea name="offer_notes" rows="2" class="admin-input">{{ $request->offer_notes }}</textarea>
                        </div>

                        <button class="admin-btn-primary w-full">{{ $request->offered_at ? 'Kirim ulang penawaran' : 'Kirim penawaran' }}</button>
                    </form>
                </x-admin.panel>

                <x-admin.panel title="Tutup Permintaan">
                    <div class="space-y-4">
                        <form method="POST" action="{{ route('admin.service-requests.update', $request->id) }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="action" value="reject">
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Alasan penolakan (dikirim ke pemilik)</label>
                            <textarea name="admin_notes" rows="2" class="admin-input" required placeholder="mis. Layanan belum tersedia di klinik ini."></textarea>
                            <button class="admin-btn-secondary w-full !py-2 !text-rose-600">Tolak permintaan</button>
                        </form>
                    </div>
                </x-admin.panel>
            @else
                <x-admin.panel title="Permintaan Ditutup" flush>
                    <div class="px-5 py-6 text-sm text-slate-600 dark:text-slate-300">
                        Status akhir: <span class="font-semibold">{{ ServiceRequest::statusLabel($request->status) }}</span>.
                        @if($request->booking)
                            <a href="{{ route('admin.bookings.show', $request->booking->id) }}" class="mt-2 block font-semibold text-primary hover:underline">
                                Buka booking #{{ $request->booking->id }}
                            </a>
                        @endif
                    </div>
                </x-admin.panel>
            @endif
        </div>
    </div>
</div>
@endsection
