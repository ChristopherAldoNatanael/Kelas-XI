<x-admin.panel title="Susun Penawaran" subtitle="Harga ditentukan admin, bukan dokter">
    <script type="application/json" id="offerScheduleData">{!! json_encode($scheduleMeta ?? ['doctors' => [], 'clinic' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    @if($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            <p class="font-semibold">Penawaran belum bisa dikirim:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-4">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.service-requests.update', $request->id) }}" class="space-y-5">
        @csrf
        <input type="hidden" name="action" value="offer">

        {{-- 1. Layanan & harga --}}
        <section class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] font-bold text-white dark:bg-slate-100 dark:text-slate-900">1</span>
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Layanan &amp; harga</h3>
            </div>

            <div>
                <label for="offer_service_id" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Layanan katalog <span class="font-normal text-slate-400">(opsional)</span>
                </label>
                <select name="offer_service_id" id="offer_service_id" class="admin-input">
                    <option value="">— Layanan khusus / belum ada di katalog —</option>
                    @foreach($services as $serviceOption)
                        <option value="{{ $serviceOption->id }}"
                                data-name="{{ $serviceOption->name }}"
                                data-description="{{ $serviceOption->description }}"
                                data-price="{{ (float) $serviceOption->price }}"
                                data-duration="{{ $serviceOption->duration }}"
                                @selected((string) old('offer_service_id', $request->offer_service_id) === (string) $serviceOption->id)>
                            {{ $serviceOption->name }} — {{ format_rupiah((float) $serviceOption->price) }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                    Pilih katalog untuk mengisi otomatis nama, deskripsi, durasi, dan harga awal. Semua tetap bisa Anda ubah.
                </p>
            </div>

            <div>
                <label for="offer_service_name" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Nama layanan penawaran <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="offer_service_name" id="offer_service_name" maxlength="150" required
                       value="{{ old('offer_service_name', $request->offer_service_name) }}"
                       class="admin-input" placeholder="mis. Pemeriksaan USG abdomen">
            </div>

            <div>
                <label for="offer_description" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Deskripsi</label>
                <textarea name="offer_description" id="offer_description" rows="2" maxlength="2000"
                          class="admin-input" placeholder="Cakupan pemeriksaan, persiapan, atau hal yang perlu diketahui pemilik">{{ old('offer_description', $request->offer_description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="offer_price" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Harga (Rp) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 flex items-center text-xs font-medium text-slate-400" style="top: 50%; transform: translateY(-50%);">Rp</span>
                        <input type="number" step="1000" min="0" max="1000000000" name="offer_price" id="offer_price" required
                               value="{{ old('offer_price', $request->offer_price) }}"
                               class="admin-input" style="padding-left: 2.25rem;" placeholder="0">
                    </div>
                    <p id="offer_price_hint" class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400"></p>
                </div>
                <div>
                    <label for="offer_price_type" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Jenis harga</label>
                    <select name="offer_price_type" id="offer_price_type" class="admin-input">
                        <option value="final" @selected(old('offer_price_type', $request->offer_price_type) !== 'estimate')>Final</option>
                        <option value="estimate" @selected(old('offer_price_type', $request->offer_price_type) === 'estimate')>Estimasi</option>
                    </select>
                    <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">Final = tidak berubah; estimasi = bisa menyesuaikan.</p>
                </div>
                <div class="col-span-2">
                    <label for="offer_duration_minutes" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Durasi <span class="font-normal text-slate-400">(menit, opsional)</span>
                    </label>
                    <input type="number" min="5" max="1440" step="5" name="offer_duration_minutes" id="offer_duration_minutes"
                           value="{{ old('offer_duration_minutes', $request->offer_duration_minutes) }}"
                           class="admin-input" placeholder="mis. 30">
                </div>
            </div>
        </section>

        <hr class="border-slate-100 dark:border-slate-800">

        {{-- 2. Dokter & jadwal --}}
        <section class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] font-bold text-white dark:bg-slate-100 dark:text-slate-900">2</span>
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Dokter &amp; jadwal</h3>
            </div>

            <div>
                <label for="offer_doctor_id" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Dokter <span class="text-rose-500">*</span>
                </label>
                <select name="offer_doctor_id" id="offer_doctor_id" class="admin-input" required>
                    <option value="">Pilih dokter</option>
                    @foreach($doctors as $doctorOption)
                        <option value="{{ $doctorOption->id }}" @selected((string) old('offer_doctor_id', $request->offer_doctor_id) === (string) $doctorOption->id)>
                            {{ $doctorOption->name }}{{ $doctorOption->specialization ? ' — ' . $doctorOption->specialization : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div id="offer_doctor_schedule" class="hidden rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-[11.5px] leading-5 text-slate-600 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-300">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] text-slate-400" aria-hidden="true">calendar_month</span>
                        <span id="offer_doctor_days">—</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] text-slate-400" aria-hidden="true">schedule</span>
                        <span id="offer_doctor_hours">—</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px] text-slate-400" aria-hidden="true">local_hospital</span>
                        <span id="offer_clinic_info">—</span>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="offer_schedule_date" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Tanggal <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="offer_schedule_date" id="offer_schedule_date" min="{{ now()->toDateString() }}"
                           value="{{ old('offer_schedule_date', $request->offer_schedule_date?->format('Y-m-d')) }}"
                           class="admin-input" required>
                    <p id="offer_date_hint" class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                        Pilih dokter dulu agar tanggal yang tersedia menyesuaikan hari praktiknya.
                    </p>
                    <button type="button" id="offer_use_suggested_date"
                            class="mt-1.5 hidden items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        <span class="material-symbols-outlined text-[13px]" aria-hidden="true">event_available</span>
                        <span id="offer_suggested_date_label">Pakai tanggal ini</span>
                    </button>
                </div>
                <div>
                    <label for="offer_schedule_time" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Jam <span class="text-rose-500">*</span>
                    </label>
                    <select name="offer_schedule_time" id="offer_schedule_time" class="admin-input" required disabled>
                        <option value="">Pilih tanggal dulu</option>
                    </select>
                    <p id="offer_slot_note" class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400"></p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/40">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ringkasan penawaran</p>
                <dl class="mt-2 space-y-1.5 text-[12px] leading-5">
                    <div class="flex gap-2">
                        <dt class="w-16 shrink-0 text-slate-500 dark:text-slate-400">Layanan</dt>
                        <dd id="offer_summary_service" class="font-medium text-slate-800 dark:text-slate-200">—</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-16 shrink-0 text-slate-500 dark:text-slate-400">Dokter</dt>
                        <dd id="offer_summary_doctor" class="font-medium text-slate-800 dark:text-slate-200">—</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-16 shrink-0 text-slate-500 dark:text-slate-400">Jadwal</dt>
                        <dd id="offer_summary_schedule" class="font-medium text-slate-800 dark:text-slate-200">—</dd>
                    </div>
                    <div class="flex gap-2">
                        <dt class="w-16 shrink-0 text-slate-500 dark:text-slate-400">Biaya</dt>
                        <dd id="offer_summary_price" class="font-semibold text-slate-900 dark:text-slate-100">—</dd>
                    </div>
                </dl>
            </div>
        </section>

        <hr class="border-slate-100 dark:border-slate-800">

        {{-- 3. Masa berlaku & catatan --}}
        <section class="space-y-3">
            <div class="flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] font-bold text-white dark:bg-slate-100 dark:text-slate-900">3</span>
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Masa berlaku &amp; catatan</h3>
            </div>

            <div>
                <label for="offer_valid_until" class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Berlaku sampai <span class="font-normal text-slate-400">(opsional)</span>
                </label>
                <input type="datetime-local" name="offer_valid_until" id="offer_valid_until"
                       value="{{ old('offer_valid_until', $request->offer_valid_until?->format('Y-m-d\TH:i')) }}"
                       class="admin-input">
                <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                    Kosongkan bila penawaran tidak memiliki batas waktu. Pemilik melihat tanggal ini sebelum menyetujui.
                </p>
            </div>

            <div>
                <label for="offer_notes" class="text-xs font-semibold text-slate-600 dark:text-slate-300">Catatan klinik</label>
                <textarea name="offer_notes" id="offer_notes" rows="2" maxlength="2000" class="admin-input"
                          placeholder="mis. Puasakan hewan 6 jam sebelum USG.">{{ old('offer_notes', $request->offer_notes) }}</textarea>
            </div>
        </section>

        <button class="admin-btn-primary w-full">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>
            {{ $request->offered_at ? 'Kirim ulang penawaran' : 'Kirim penawaran' }}
        </button>
    </form>
</x-admin.panel>
