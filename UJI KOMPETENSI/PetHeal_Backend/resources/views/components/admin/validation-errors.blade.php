@props(['title' => null])

{{--
    Blok error validasi global untuk konsol admin & portal dokter.

    Layout admin merender komponen ini sekali, tepat di atas @yield('content'),
    sehingga setiap halaman otomatis menampilkan penolakan validasi dari server
    tanpa harus menulis bloknya sendiri (lihat docs/AUDIT_FIXES_REPORT.md UIX-07).

    @example hanya dipakai lewat layout
    <x-admin.validation-errors />
--}}
@if($errors->any())
    <div {{ $attributes->merge(['class' => 'mb-5 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200', 'role' => 'alert']) }}>
        <span class="material-symbols-outlined mt-px text-[20px] flex-shrink-0" aria-hidden="true">error</span>
        <div class="min-w-0">
            <p class="font-semibold">{{ $title ?? __('admin.validation_failed') }}</p>
            <ul class="mt-1 list-inside list-disc space-y-1 text-[13px] leading-6">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
