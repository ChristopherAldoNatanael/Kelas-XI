@php
    $me = auth()->user();
    $roleLabels = [
        'super_admin' => 'Super Admin',
        'clinic_admin' => 'Admin Klinik',
        'admin' => 'Admin',
    ];
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-100 p-6 dark:border-slate-800">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">Tim klinik</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Akun admin yang bisa masuk ke dashboard {{ $clinic->name }}. Akun baru dibuat lewat persetujuan superadmin.
        </p>
    </div>

    <div class="p-6">
        @forelse($clinicAdmins as $admin)
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 py-3 last:border-0 dark:border-slate-800">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300" aria-hidden="true">
                    {{ \Illuminate\Support\Str::substr($admin->name, 0, 1) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                        {{ $admin->name }}
                        @if($admin->id === $me->id)
                            <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-emerald-700">Anda</span>
                        @endif
                    </p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $admin->email }}</p>
                </div>
                <span class="rounded-full border border-slate-200 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    {{ $roleLabels[$admin->role] ?? ucfirst((string) $admin->role) }}
                </span>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">Belum ada admin lain di klinik ini.</p>
        @endforelse

        <div class="mt-5 flex items-start gap-2 rounded-xl border border-sky-100 bg-sky-50/80 px-4 py-3 text-xs leading-5 text-sky-800 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300">
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">info</span>
            <p>Butuh menambah admin klinik? Minta calon admin mengirim permintaan gabung dari halaman login, lalu superadmin menyetujuinya.</p>
        </div>
    </div>
</section>
