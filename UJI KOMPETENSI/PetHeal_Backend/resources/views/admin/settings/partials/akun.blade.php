@php
    $me = auth()->user();
    $initial = \Illuminate\Support\Str::substr($me->name ?? 'A', 0, 1);
    $roleLabel = match ($me->role ?? '') {
        'super_admin' => 'Super Admin',
        'clinic_admin', 'admin' => 'Admin Klinik',
        default => ucfirst((string) ($me->role ?? 'Admin')),
    };
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-100 p-6 dark:border-slate-800">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">Informasi akun</h3>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
            Nama dan email ini yang tampil di riwayat aktivitas serta notifikasi tim.
        </p>
    </div>

    <div class="p-6">
        <div class="mb-6 flex items-center gap-4 rounded-xl border border-slate-200/70 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
            <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-primary text-lg font-semibold text-white" aria-hidden="true">
                {{ $initial }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $me->name }}</p>
                <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $me->email }}</p>
            </div>
            <span class="ml-auto flex-shrink-0 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                {{ $roleLabel }}
            </span>
        </div>

        <form method="POST" action="{{ route('admin.settings.profile') }}" class="space-y-5">
            @csrf
            <div>
                <label for="settings-name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Nama lengkap</label>
                <input id="settings-name" type="text" name="name" value="{{ old('name', $me->name) }}" required autocomplete="name"
                       class="w-full rounded-xl border @error('name') border-rose-300 @else border-slate-200 dark:border-slate-700 @enderror bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-slate-950 dark:text-slate-100">
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="settings-email" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input id="settings-email" type="email" name="email" value="{{ old('email', $me->email) }}" required autocomplete="email"
                       class="w-full rounded-xl border @error('email') border-rose-300 @else border-slate-200 dark:border-slate-700 @enderror bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:bg-slate-950 dark:text-slate-100">
                <p class="mt-1 text-xs text-slate-400">Dipakai untuk login. Perubahan langsung berlaku setelah disimpan.</p>
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                    Simpan perubahan
                </button>
                <a href="{{ route('admin.settings', ['tab' => 'keamanan']) }}" class="text-sm font-medium text-slate-500 hover:text-primary dark:text-slate-400">
                    Ganti password
                </a>
            </div>
        </form>
    </div>
</section>
