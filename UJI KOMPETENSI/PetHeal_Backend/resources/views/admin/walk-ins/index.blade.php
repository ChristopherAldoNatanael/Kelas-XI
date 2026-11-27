@extends('layouts.admin')

@section('title', 'Walk-in & Darurat — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', 'Walk-in & Darurat')

@php
$triageLabels = [
    'red' => 'Merah — mengancam nyawa',
    'orange' => 'Oranye — urgent',
    'yellow' => 'Kuning — perlu segera',
    'green' => 'Hijau — stabil',
];
$triageTones = ['red' => 'rose', 'orange' => 'amber', 'yellow' => 'sky', 'green' => 'green'];
@endphp

@section('content')
<div class="space-y-6">

    <x-admin.page-header
        title="Pendaftaran Walk-in & Darurat"
        eyebrow="Pasien tanpa aplikasi"
        icon="emergency"
        description="Daftarkan pasien yang datang langsung, beri nomor antrian, lalu lanjutkan ke rekam medis dokter.">
        <x-slot:actions>
            <a href="{{ route('admin.queue.index') }}" class="admin-btn-secondary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">groups</span>
                Papan Antrian
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div class="xl:col-span-3">
            <x-admin.panel title="Kunjungan baru" subtitle="Nomor antrian dibuat otomatis setelah disimpan">
                <form method="POST" action="{{ route('admin.walk-ins.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="visit_type" class="admin-label">Jenis kunjungan</label>
                            <select id="visit_type" name="visit_type" class="admin-input" required>
                                <option value="walk_in" @selected(old('visit_type', 'walk_in') === 'walk_in')>Datang langsung (walk-in)</option>
                                <option value="emergency" @selected(old('visit_type') === 'emergency')>Darurat</option>
                            </select>
                        </div>
                        <div>
                            <label for="doctor_id" class="admin-label">Dokter penanggung jawab</label>
                            <select id="doctor_id" name="doctor_id" class="admin-input" required>
                                <option value="">Pilih dokter</option>
                                @foreach($doctors as $doctor)
                                    <option value="{{ $doctor->id }}" @selected((string) old('doctor_id') === (string) $doctor->id)>{{ $doctor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="triage-box" class="rounded-xl border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-900/40 dark:bg-rose-950/20">
                        <label for="triage_level" class="admin-label">Triage darurat</label>
                        <select id="triage_level" name="triage_level" class="admin-input">
                            <option value="">Pilih triage</option>
                            @foreach($triageLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('triage_level') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">Wajib untuk kunjungan darurat. Triage dicatat pada booking.</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="admin-label mb-0">Pemilik hewan</label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="owner_mode" value="existing" @checked(old('owner_mode', $owner ? 'existing' : 'existing') === 'existing')>
                                Sudah terdaftar
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="radio" name="owner_mode" value="new" @checked(old('owner_mode') === 'new')>
                                Pemilik baru
                            </label>
                        </div>

                        <div id="existing-owner-box" class="mt-4 space-y-3">
                            <div>
                                <label for="owner-search" class="admin-label">Cari pemilik</label>
                                <div class="relative">
                                    <input id="owner-search" type="text" autocomplete="off" class="admin-input" placeholder="Ketik nama, email, atau telepon pemilik">
                                    <div id="owner-search-results" class="absolute left-0 right-0 z-30 mt-1 hidden max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"></div>
                                </div>
                                <p id="owner-search-status" class="mt-1 text-xs text-slate-500">Hasil muncul otomatis saat mengetik minimal 2 karakter.</p>
                            </div>

                            <input id="existing-owner-id" type="hidden" name="existing_owner_id" value="{{ old('existing_owner_id', $owner->id ?? '') }}">

                            <div id="selected-owner" class="rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-900 {{ $owner ? '' : 'hidden' }}">
                                <span id="selected-owner-name" class="font-semibold">{{ $owner->name ?? '' }}</span>
                                <span id="selected-owner-contact" class="text-slate-500"> {{ $owner ? '· ' . $owner->email : '' }}</span>
                                <button type="button" id="clear-owner" class="ml-2 text-xs text-emerald-700 hover:underline dark:text-emerald-400">Ganti</button>
                            </div>

                            <div id="pet-box" class="{{ $owner ? '' : 'hidden' }}">
                                <label for="pet_id" class="admin-label">Hewan</label>
                                <select id="pet_id" name="pet_id" class="admin-input" {{ $owner ? 'required' : '' }}>
                                    <option value="">Pilih hewan</option>
                                    @if($owner)
                                        @foreach($owner->pets as $pet)
                                            <option value="{{ $pet->id }}" @selected((string) old('pet_id') === (string) $pet->id)>{{ $pet->name }} — {{ $pet->species }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <p id="pet-empty-hint" class="mt-1 hidden text-xs text-amber-700 dark:text-amber-300">Pemilik ini belum punya data hewan. Gunakan mode “Pemilik baru” untuk membuat hewan.</p>
                            </div>
                        </div>

                        <div id="new-owner-box" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="owner_name" class="admin-label">Nama pemilik</label>
                                <input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" class="admin-input">
                            </div>
                            <div>
                                <label for="owner_phone" class="admin-label">Telepon</label>
                                <input id="owner_phone" name="owner_phone" value="{{ old('owner_phone') }}" class="admin-input">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="owner_email" class="admin-label">Email (opsional)</label>
                                <input id="owner_email" type="email" name="owner_email" value="{{ old('owner_email') }}" class="admin-input">
                            </div>
                            <div>
                                <label for="pet_name" class="admin-label">Nama hewan</label>
                                <input id="pet_name" name="pet_name" value="{{ old('pet_name') }}" class="admin-input">
                            </div>
                            <div>
                                <label for="species" class="admin-label">Spesies</label>
                                <input id="species" name="species" value="{{ old('species') }}" class="admin-input" placeholder="Kucing, anjing, ...">
                            </div>
                            <div>
                                <label for="breed" class="admin-label">Ras (opsional)</label>
                                <input id="breed" name="breed" value="{{ old('breed') }}" class="admin-input">
                            </div>
                            <div>
                                <label for="gender" class="admin-label">Kelamin</label>
                                <select id="gender" name="gender" class="admin-input">
                                    <option value="unknown">Tidak diketahui</option>
                                    <option value="male" @selected(old('gender') === 'male')>Jantan</option>
                                    <option value="female" @selected(old('gender') === 'female')>Betina</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="complaint" class="admin-label">Keluhan / catatan awal</label>
                        <textarea id="complaint" name="complaint" rows="3" maxlength="2000" class="admin-input" placeholder="Keluhan utama, kondisi saat tiba, atau catatan triage.">{{ old('complaint') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="needs_payment" value="1" @checked(old('needs_payment'))>
                            Perlu pembayaran
                        </label>
                        <div class="sm:col-span-2">
                            <label for="amount" class="admin-label">Nominal (opsional)</label>
                            <input id="amount" type="number" min="0" step="1000" name="amount" value="{{ old('amount') }}" class="admin-input" placeholder="0">
                        </div>
                    </div>

                    <button type="submit" class="admin-btn-primary w-full">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
                        Daftarkan & beri nomor antrian
                    </button>
                </form>
            </x-admin.panel>
        </div>

        <div class="xl:col-span-2">
            <x-admin.panel title="Kunjungan hari ini" subtitle="Walk-in dan darurat" flush>
                <x-slot:actions>
                    <x-admin.badge :label="'Total · ' . $visits->count()" tone="slate" />
                </x-slot:actions>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($visits as $visit)
                        <div class="space-y-2 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $visit->pet->name ?? 'Hewan' }}</div>
                                    <div class="text-xs text-slate-500">{{ $visit->user->name ?? 'Tanpa pemilik' }} · {{ $visit->doctor->name ?? 'Tanpa dokter' }}</div>
                                </div>
                                <div class="text-right">
                                    @if($visit->queue_number)
                                        <div class="text-lg font-bold text-emerald-700 dark:text-emerald-400">{{ \App\Services\QueueService::label($visit->queue_number) }}</div>
                                        <div class="text-xs text-slate-500">{{ \App\Services\QueueService::statusLabel($visit->queue_status) }}</div>
                                    @else
                                        <x-admin.badge label="Belum antri" tone="amber" />
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <x-admin.badge :label="$visit->service_type === 'emergency' ? 'Darurat' : 'Walk-in'" :tone="$visit->service_type === 'emergency' ? 'rose' : 'sky'" />
                                @if($visit->triage_level)
                                    <x-admin.badge :label="$triageLabels[$visit->triage_level] ?? $visit->triage_level" :tone="$triageTones[$visit->triage_level] ?? 'slate'" />
                                @endif
                                <span class="text-slate-500">{{ \Illuminate\Support\Carbon::parse($visit->booking_time)->format('H:i') }}</span>
                            </div>

                            @if($visit->notes)
                                <p class="text-xs text-slate-500 line-clamp-2">{{ $visit->notes }}</p>
                            @endif

                            <div class="flex flex-wrap gap-2">
                                @if($visit->medical_record_id)
                                    <a href="{{ route('admin.medical-records.show', $visit->medical_record_id) }}" class="admin-btn-secondary">Buka rekam medis</a>
                                @else
                                    <a href="{{ route('admin.medical-records.create', $visit->id) }}" class="admin-btn-primary">Buat rekam medis</a>
                                @endif
                                <a href="{{ route('admin.bookings.show', $visit->id) }}" class="admin-btn-secondary">Detail kunjungan</a>
                            </div>
                        </div>
                    @empty
                        <div class="p-4">
                            <x-admin.empty-state
                                title="Belum ada kunjungan langsung"
                                description="Kunjungan walk-in dan darurat hari ini akan muncul di sini."
                                icon="emergency"
                                tone="slate" />
                        </div>
                    @endforelse
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const visitType = document.getElementById('visit_type');
    const triageBox = document.getElementById('triage-box');
    const triageSelect = document.getElementById('triage_level');
    const existingBox = document.getElementById('existing-owner-box');
    const newBox = document.getElementById('new-owner-box');
    const ownerModeInputs = document.querySelectorAll('input[name="owner_mode"]');
    const ownerSearchUrl = @json(route('admin.walk-ins.owners'));
    const ownerSearch = document.getElementById('owner-search');
    const ownerResults = document.getElementById('owner-search-results');
    const ownerStatus = document.getElementById('owner-search-status');
    const ownerIdInput = document.getElementById('existing-owner-id');
    const selectedOwner = document.getElementById('selected-owner');
    const selectedOwnerName = document.getElementById('selected-owner-name');
    const selectedOwnerContact = document.getElementById('selected-owner-contact');
    const petBox = document.getElementById('pet-box');
    const petSelect = document.getElementById('pet_id');
    const petEmptyHint = document.getElementById('pet-empty-hint');
    const clearOwner = document.getElementById('clear-owner');
    let searchTimer = null;
    let searchController = null;

    function resetPetOptions() {
        petSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Pilih hewan';
        petSelect.appendChild(placeholder);
    }

    function selectOwner(owner) {
        ownerIdInput.value = owner.id;
        selectedOwnerName.textContent = owner.name || 'Pemilik';
        selectedOwnerContact.textContent = ' · ' + (owner.email || owner.phone || '');
        selectedOwner.classList.remove('hidden');
        petBox.classList.remove('hidden');
        petSelect.required = true;
        resetPetOptions();

        (owner.pets || []).forEach((pet) => {
            const option = document.createElement('option');
            option.value = pet.id;
            option.textContent = `${pet.name || 'Hewan'} — ${pet.species || '-'}`;
            petSelect.appendChild(option);
        });

        petEmptyHint.classList.toggle('hidden', (owner.pets || []).length > 0);
        ownerResults.classList.add('hidden');
        ownerResults.innerHTML = '';
        ownerSearch.value = '';
        ownerStatus.textContent = 'Pemilik dipilih.';
    }

    function clearSelectedOwner() {
        ownerIdInput.value = '';
        selectedOwner.classList.add('hidden');
        petBox.classList.add('hidden');
        petSelect.required = false;
        petSelect.value = '';
        ownerStatus.textContent = 'Hasil muncul otomatis saat mengetik minimal 2 karakter.';
        ownerSearch.focus();
    }

    async function searchOwners(query) {
        if (searchController) searchController.abort();
        searchController = new AbortController();
        ownerStatus.textContent = 'Mencari pemilik...';

        try {
            const response = await fetch(`${ownerSearchUrl}?q=${encodeURIComponent(query)}`, {
                headers: { 'Accept': 'application/json' },
                signal: searchController.signal,
            });
            if (!response.ok) throw new Error('request failed');
            const payload = await response.json();
            const owners = payload.data || [];
            ownerResults.innerHTML = '';

            if (owners.length === 0) {
                ownerStatus.textContent = 'Pemilik tidak ditemukan. Gunakan mode “Pemilik baru”.';
                ownerResults.classList.add('hidden');
                return;
            }

            owners.forEach((owner) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'block w-full border-b border-slate-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800';
                const name = document.createElement('span');
                name.className = 'font-medium';
                name.textContent = owner.name || 'Pemilik';
                const detail = document.createElement('span');
                detail.className = 'mt-0.5 block text-xs text-slate-500';
                detail.textContent = [owner.email, owner.phone].filter(Boolean).join(' · ');
                button.append(name, detail);
                button.addEventListener('click', () => selectOwner(owner));
                ownerResults.appendChild(button);
            });

            ownerResults.classList.remove('hidden');
            ownerStatus.textContent = `${owners.length} pemilik ditemukan.`;
        } catch (error) {
            if (error.name !== 'AbortError') {
                ownerStatus.textContent = 'Pencarian gagal. Coba lagi.';
            }
        }
    }

    ownerSearch.addEventListener('input', () => {
        const query = ownerSearch.value.trim();
        window.clearTimeout(searchTimer);

        if (query.length < 2) {
            ownerResults.classList.add('hidden');
            ownerResults.innerHTML = '';
            ownerStatus.textContent = 'Hasil muncul otomatis saat mengetik minimal 2 karakter.';
            return;
        }

        searchTimer = window.setTimeout(() => searchOwners(query), 250);
    });

    clearOwner.addEventListener('click', clearSelectedOwner);

    document.addEventListener('click', (event) => {
        if (!ownerResults.contains(event.target) && event.target !== ownerSearch) {
            ownerResults.classList.add('hidden');
        }
    });

    function syncVisitType() {
        const emergency = visitType.value === 'emergency';
        triageBox.style.display = emergency ? '' : 'none';
        triageSelect.required = emergency;
    }

    function syncOwnerMode() {
        const mode = document.querySelector('input[name="owner_mode"]:checked')?.value || 'existing';
        existingBox.style.display = mode === 'existing' ? '' : 'none';
        newBox.style.display = mode === 'new' ? '' : 'none';
    }

    visitType.addEventListener('change', syncVisitType);
    ownerModeInputs.forEach((input) => input.addEventListener('change', syncOwnerMode));
    syncVisitType();
    syncOwnerMode();
})();
</script>
@endpush
