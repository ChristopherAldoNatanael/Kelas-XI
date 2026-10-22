@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $users */
$role = $role ?? 'all';
$stats = $stats ?? ['total' => 0, 'patients' => 0, 'admins' => 0, 'new_this_month' => 0];

$roleTabs = [
    'all' => ['label' => 'Semua', 'count' => $stats['total']],
    'patient' => ['label' => 'Pasien', 'count' => $stats['patients']],
    'admin' => ['label' => 'Admin klinik', 'count' => $stats['admins']],
];

$roleBadges = [
    'user' => ['Pasien', 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'],
    'doctor' => ['Dokter', 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:border-sky-900'],
    'clinic_admin' => ['Admin klinik', 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-900'],
    'admin' => ['Admin', 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-300 dark:border-purple-900'],
    'super_admin' => ['Super Admin', 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-900'],
];
@endphp
@extends('layouts.admin')

@section('title', __('menu.patients') . ' — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', __('menu.patients'))

@section('content')
<div class="mx-auto max-w-6xl space-y-5">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Data klinik</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">Pasien & pengguna</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Pemilik hewan yang terdaftar di klinik ini, lengkap dengan akun admin yang punya akses.
            </p>
        </div>
        <a href="{{ route('admin.users.create') }}"
           class="inline-flex items-center gap-2 self-start rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
            Tambah pasien
        </a>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total pengguna</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $stats['total'] }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200/60 bg-emerald-50/50 p-4 dark:border-emerald-900/40 dark:bg-emerald-900/10">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Pasien</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ $stats['patients'] }}</p>
        </div>
        <div class="rounded-xl border border-sky-200/60 bg-sky-50/50 p-4 dark:border-sky-900/40 dark:bg-sky-900/10">
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-600">Pasien baru bulan ini</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-400">{{ $stats['new_this_month'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200/70 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Admin klinik</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $stats['admins'] }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900">

        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
            <div class="inline-flex self-start rounded-xl border border-slate-200/80 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800/60" role="tablist" aria-label="Filter peran">
                @foreach($roleTabs as $key => $tab)
                    @php $isActive = $role === $key; @endphp
                    <a href="{{ route('admin.users.index', array_filter(['role' => $key === 'all' ? null : $key, 'search' => request('search')])) }}"
                       role="tab" @if($isActive) aria-selected="true" @endif
                       class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors
                              {{ $isActive ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200' }}">
                        {{ $tab['label'] }}
                        <span class="rounded bg-slate-100 px-1 text-[10px] font-bold tabular-nums text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $tab['count'] }}</span>
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-1 items-center gap-2 lg:max-w-md">
                @if($role !== 'all')
                    <input type="hidden" name="role" value="{{ $role }}">
                @endif
                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-slate-400" aria-hidden="true">search</span>
                    <label for="user-search" class="sr-only">Cari pasien</label>
                    <input id="user-search" type="text" name="search" value="{{ request('search') }}"
                           placeholder="Nama, email, telepon, atau ID"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
                </div>
                <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-white">
                    Cari
                </button>
                @if(request('search'))
                    <a href="{{ route('admin.users.index', $role !== 'all' ? ['role' => $role] : []) }}"
                       class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        @if($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px]">
                    <thead class="bg-slate-50/70 dark:bg-slate-800/40">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Pengguna</th>
                            <th class="px-6 py-3.5 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Kontak</th>
                            <th class="px-6 py-3.5 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Hewan</th>
                            <th class="px-6 py-3.5 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Peran</th>
                            <th class="px-6 py-3.5 text-left text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Bergabung</th>
                            <th class="px-6 py-3.5 text-right text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/60 dark:divide-slate-800/60">
                        @foreach($users as $user)
                            @php $badge = $roleBadges[$user->role] ?? [ucfirst((string) $user->role), 'bg-slate-100 text-slate-600 border-slate-200']; @endphp
                            <tr class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/20">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        @if($user->photo)
                                            <img src="{{ $user->photo }}" alt="" class="h-9 w-9 rounded-full object-cover" loading="lazy">
                                        @else
                                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" aria-hidden="true">
                                                {{ mb_substr($user->name, 0, 1) }}
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.users.show', $user->id) }}" class="block truncate text-sm font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                                                {{ $user->name }}
                                            </a>
                                            <p class="text-[10px] text-slate-400">ID #{{ $user->id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ $user->email }}</p>
                                    <p class="text-[11px] text-slate-400">{{ $user->phone ?? 'Tanpa nomor telepon' }}</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user->pets_count > 0)
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">pets</span>
                                            {{ $user->pets_count }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="rounded border px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $badge[1] }}">{{ $badge[0] }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    {{ $user->created_at?->locale('id')->translatedFormat('d M Y') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.users.show', $user->id) }}"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-primary dark:hover:bg-slate-800"
                                           title="Lihat profil" aria-label="Lihat profil {{ $user->name }}">
                                            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">visibility</span>
                                        </a>
                                        <a href="{{ route('admin.users.edit', $user->id) }}"
                                           class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-primary dark:hover:bg-slate-800"
                                           title="Ubah" aria-label="Ubah {{ $user->name }}">
                                            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">edit</span>
                                        </a>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="inline"
                                              onsubmit="return confirm('Hapus {{ $user->name }}? Tindakan ini tidak bisa dibatalkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/30"
                                                    title="Hapus" aria-label="Hapus {{ $user->name }}">
                                                <span class="material-symbols-outlined text-[19px]" aria-hidden="true">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                    {{ $users->links() }}
                </div>
            @endif
        @else
            <div class="px-6 py-16 text-center">
                <span class="material-symbols-outlined text-[44px] text-slate-300" aria-hidden="true">group_off</span>
                <h3 class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
                    {{ request('search') ? 'Tidak ada pengguna yang cocok' : 'Belum ada pengguna terdaftar' }}
                </h3>
                <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-slate-500 dark:text-slate-400">
                    @if(request('search'))
                        Tidak ada hasil untuk "{{ request('search') }}". Coba kata kunci lain atau reset pencarian.
                    @else
                        Pasien akan muncul otomatis setelah mendaftar dari aplikasi, atau tambahkan manual di sini.
                    @endif
                </p>
                <div class="mt-4 flex justify-center gap-2">
                    @if(request('search'))
                        <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">Reset pencarian</a>
                    @else
                        <a href="{{ route('admin.users.create') }}" class="rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-600">Tambah pasien</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
