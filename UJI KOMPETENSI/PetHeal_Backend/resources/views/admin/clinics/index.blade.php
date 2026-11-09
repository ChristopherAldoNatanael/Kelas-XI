@extends('layouts.admin')

@section('title', __('clinics.clinic_management') . ' — PetHeal Admin')
@section('header', __('clinics.clinic_management'))

@section('content')
<div class="space-y-6">

    {{-- Header halaman --}}
    <x-admin.page-header
        :title="__('clinics.clinic_management')"
        :eyebrow="__('admin.eyebrow_management')"
        icon="local_hospital"
        :description="__('clinics.manage_all_clinics')">
        <x-slot:actions>
            <a href="{{ route('admin.clinics.create') }}"
               class="admin-btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                {{ __('clinics.add_clinic') }}
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3.5 text-sm font-medium text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
            {{ session('error') }}
        </div>
    @endif

    {{-- Kredensial akun admin klinik yang baru dibuat --}}
    @if(session('new_clinic_credentials'))
        @php $credentials = session('new_clinic_credentials'); @endphp
        <div class="relative rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-blue-800 dark:border-blue-900/60 dark:bg-blue-900/20 dark:text-blue-200" id="cred-alert">
            <button type="button" onclick="document.getElementById('cred-alert').remove()"
                    class="absolute right-3 top-2 text-lg text-blue-400 transition hover:text-blue-600 dark:hover:text-blue-300"
                    aria-label="{{ __('common.close') }}">
                &times;
            </button>

            <p class="mb-3 font-bold">{{ __('clinics.admin_account') }} {{ $credentials['clinic'] }}</p>

            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-blue-600 dark:text-blue-300">{{ __('clinics.cred_email') }}:</span>
                    <code class="rounded bg-blue-100 px-2 py-0.5 font-mono text-xs text-blue-900 dark:bg-blue-900/60 dark:text-blue-100">{{ $credentials['email'] }}</code>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-blue-600 dark:text-blue-300">{{ __('clinics.cred_password') }}:</span>
                    <code class="rounded bg-blue-100 px-2 py-0.5 font-mono text-xs text-blue-900 dark:bg-blue-900/60 dark:text-blue-100">{{ $credentials['password'] }}</code>
                </div>
            </div>

            <p class="mt-3 text-[11px] text-blue-500 dark:text-blue-300/80">{{ __('clinics.save_credentials') }}</p>
        </div>
    @endif

    {{-- Tabel klinik --}}
    <x-admin.panel :title="__('clinics.th_clinic')" :subtitle="__('clinics.manage_all_clinics')" flush>
        <x-slot:actions>
            <x-admin.badge :label="__('admin.total') . ' · ' . $clinics->total()" tone="slate" />
        </x-slot:actions>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="admin-table-head">
                    <tr>
                        <th class="px-5 py-3">{{ __('clinics.th_clinic') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.contact') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.stats') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.color') }}</th>
                        <th class="px-5 py-3">{{ __('clinics.th_status') }}</th>
                        <th class="px-5 py-3 text-center">{{ __('clinics.th_actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                    @forelse($clinics as $clinic)
                        <tr class="admin-table-row">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    @if($clinic->logo_url)
                                        <img src="{{ $clinic->logo_url }}" alt="{{ $clinic->name }}"
                                             class="h-9 w-9 rounded-lg border border-slate-200 object-cover dark:border-slate-700">
                                    @else
                                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-[11px] font-bold text-white"
                                              style="background-color: {{ $clinic->primary_color }}">
                                            {{ \Illuminate\Support\Str::substr($clinic->name, 0, 2) }}
                                        </span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $clinic->name }}</p>
                                        <p class="truncate text-[11px] text-slate-400">{{ $clinic->slug }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-xs text-slate-600 dark:text-slate-400">{{ $clinic->phone ?: '—' }}</p>
                                <p class="text-[11px] text-slate-400">{{ $clinic->email ?: '—' }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <x-admin.badge :label="$clinic->doctors_count . ' ' . __('dashboard.doctors')" tone="blue" />
                                    <x-admin.badge :label="$clinic->services_count . ' ' . __('dashboard.services_count')" tone="violet" />
                                    <x-admin.badge :label="$clinic->users_count . ' ' . __('dashboard.users')" tone="slate" />
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="h-5 w-5 rounded-full border border-slate-200 dark:border-slate-700"
                                          style="background-color: {{ $clinic->primary_color }}"></span>
                                    <span class="font-mono text-[10px] text-slate-500">{{ $clinic->primary_color }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <x-admin.badge
                                    :label="$clinic->is_active ? __('clinics.status_active') : __('clinics.status_inactive')"
                                    :tone="$clinic->is_active ? 'primary' : 'slate'"
                                    dot />
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('admin.clinics.edit', $clinic) }}"
                                       class="admin-icon-btn"
                                       title="{{ __('admin.edit') }}"
                                       aria-label="{{ __('admin.edit') }} {{ $clinic->name }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">edit</span>
                                    </a>

                                    <form method="POST" action="{{ route('admin.clinics.toggle-active', $clinic) }}"
                                          class="inline"
                                          onsubmit="return confirm('{{ __('clinics.toggle_confirm', ['name' => $clinic->name]) }}')">
                                        @csrf
                                        <button type="submit"
                                                class="admin-icon-btn hover:!text-orange-500"
                                                title="{{ $clinic->is_active ? __('common.inactive') : __('common.active') }}"
                                                aria-label="{{ $clinic->is_active ? __('common.inactive') : __('common.active') }} {{ $clinic->name }}">
                                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">
                                                {{ $clinic->is_active ? 'block' : 'check_circle' }}
                                            </span>
                                        </button>
                                    </form>

                                    <button type="button"
                                            class="admin-icon-btn hover:!text-rose-600"
                                            title="{{ __('clinics.delete') }}"
                                            aria-label="{{ __('clinics.delete') }} {{ $clinic->name }}"
                                            onclick="openDeleteClinicModal(this)"
                                            data-clinic-name="{{ $clinic->name }}"
                                            data-clinic-slug="{{ $clinic->slug }}"
                                            data-clinic-action="{{ route('admin.clinics.destroy', $clinic) }}"
                                            data-counts="{{ json_encode([
                                                'users' => $clinic->users_count,
                                                'doctors' => $clinic->doctors_count,
                                                'services' => $clinic->services_count,
                                                'bookings' => $clinic->bookings_count,
                                                'medical_records' => $clinic->medical_records_count,
                                                'notifications' => $clinic->notifications_count,
                                                'join_requests' => $clinic->join_requests_count,
                                                'schedule_exceptions' => $clinic->schedule_exceptions_count,
                                            ]) }}">
                                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-admin.empty-state
                                    :title="__('admin.clinics_empty_title')"
                                    :description="__('admin.clinics_empty_desc')"
                                    icon="local_hospital"
                                    tone="primary">
                                    <x-slot:actions>
                                        <a href="{{ route('admin.clinics.create') }}" class="admin-btn-primary">
                                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
                                            {{ __('clinics.add_clinic') }}
                                        </a>
                                    </x-slot:actions>
                                </x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            {{ $clinics->links() }}
        </x-slot:footer>
    </x-admin.panel>
</div>

{{-- Modal hapus klinik permanen --}}
<div id="delete-clinic-modal"
     class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-950/60 px-4 py-6 backdrop-blur-sm"
     role="dialog" aria-modal="true" aria-labelledby="delete-clinic-title" aria-hidden="true">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start gap-3 border-b border-slate-100 px-6 py-5 dark:border-slate-800">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">delete_forever</span>
            </span>
            <div class="min-w-0 flex-1">
                <h3 id="delete-clinic-title" class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ __('clinics.delete_title') }}
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('clinics.delete_modal_desc') }}
                    <strong id="delete-clinic-name" class="font-semibold text-slate-700 dark:text-slate-200"></strong>
                </p>
            </div>
            <button type="button" onclick="closeDeleteClinicModal()"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    aria-label="{{ __('common.close') }}">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">close</span>
            </button>
        </div>

        <form id="delete-clinic-form" method="POST" action="">
            @csrf
            @method('DELETE')

            <div class="space-y-4 px-6 py-5">
                {{-- Ringkasan data yang ikut terhapus (angka dari query index). --}}
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach([
                        'users' => __('clinics.delete_summary_users'),
                        'doctors' => __('clinics.delete_summary_doctors'),
                        'services' => __('clinics.delete_summary_services'),
                        'bookings' => __('clinics.delete_summary_bookings'),
                        'medical_records' => __('clinics.delete_summary_records'),
                        'notifications' => __('clinics.delete_summary_notifications'),
                        'join_requests' => __('clinics.delete_summary_join_requests'),
                        'schedule_exceptions' => __('clinics.delete_summary_schedule'),
                    ] as $key => $label)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-950/30">
                            <p class="text-base font-bold text-slate-800 dark:text-slate-100" data-delete-count="{{ $key }}">0</p>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs leading-relaxed text-rose-700 dark:border-rose-900/60 dark:bg-rose-900/20 dark:text-rose-300">
                    <p class="font-semibold">{{ __('clinics.delete_irreversible') }}</p>
                    <p class="mt-1">{{ __('clinics.delete_warning') }}</p>
                </div>

                <div>
                    <label for="delete-clinic-slug" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                        {{ __('clinics.delete_type_slug') }}
                        <code id="delete-clinic-slug-label" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700 dark:bg-slate-800 dark:text-slate-200"></code>
                    </label>
                    <input id="delete-clinic-slug" name="confirm_slug" type="text" required
                           autocomplete="off" spellcheck="false"
                           class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-slate-800 outline-none transition focus:border-rose-400 focus:ring-2 focus:ring-rose-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-rose-500 dark:focus:ring-rose-900/40">
                    <p id="delete-clinic-slug-hint" class="mt-1.5 hidden text-[11px] font-medium text-rose-600 dark:text-rose-400">
                        {{ __('clinics.delete_slug_hint') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4 dark:border-slate-800 dark:bg-slate-950/30">
                <button type="button" onclick="closeDeleteClinicModal()" class="admin-btn-secondary">
                    {{ __('clinics.cancel') }}
                </button>
                <button type="submit" id="delete-clinic-submit" disabled
                        class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete_forever</span>
                    {{ __('clinics.delete_confirm') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const modal = document.getElementById('delete-clinic-modal');
    if (!modal) return;

    const form = document.getElementById('delete-clinic-form');
    const input = document.getElementById('delete-clinic-slug');
    const submit = document.getElementById('delete-clinic-submit');
    const nameEl = document.getElementById('delete-clinic-name');
    const slugLabel = document.getElementById('delete-clinic-slug-label');
    const hint = document.getElementById('delete-clinic-slug-hint');
    let expectedSlug = '';

    function syncSubmit() {
        const matches = input.value.trim() === expectedSlug;
        submit.disabled = !matches;
        hint.classList.toggle('hidden', input.value === '' || matches);
    }

    window.openDeleteClinicModal = function (button) {
        const data = button.dataset;
        expectedSlug = data.clinicSlug;

        nameEl.textContent = data.clinicName;
        slugLabel.textContent = expectedSlug;
        form.action = data.clinicAction;
        input.value = '';

        let counts = {};
        try { counts = JSON.parse(data.counts || '{}'); } catch (e) { counts = {}; }
        modal.querySelectorAll('[data-delete-count]').forEach(function (el) {
            el.textContent = counts[el.dataset.deleteCount] ?? '0';
        });

        syncSubmit();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { input.focus(); }, 50);
    };

    window.closeDeleteClinicModal = function () {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        modal.querySelectorAll('[data-delete-count]').forEach(function (el) { el.textContent = '0'; });
        document.body.style.overflow = '';
    };

    input.addEventListener('input', syncSubmit);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) window.closeDeleteClinicModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            window.closeDeleteClinicModal();
        }
    });
})();
</script>
@endpush
