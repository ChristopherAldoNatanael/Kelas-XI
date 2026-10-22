@extends('layouts.admin')

@section('title', 'Ubah pengguna — ' . ($clinicName ?? 'PetHeal') . ' Admin')
@section('header', 'Ubah pengguna')

@section('content')
<div class="mx-auto max-w-xl">
    <div class="mb-5 flex items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $user->name }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Perbarui data kontak atau reset password pengguna.
            </p>
        </div>
        <a href="{{ route('admin.users.show', $user->id) }}"
           class="inline-flex flex-shrink-0 items-center gap-1 rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_back</span>
            Kembali
        </a>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $user->id) }}"
          class="space-y-4 rounded-2xl border border-slate-200/70 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Nama lengkap <span class="text-rose-500">*</span></label>
            <input id="name" type="text" name="name" required value="{{ old('name', $user->name) }}"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Email <span class="text-rose-500">*</span></label>
            <input id="email" type="email" name="email" required value="{{ old('email', $user->email) }}"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Nomor telepon</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            @error('phone') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Password baru</label>
            <input id="password" type="password" name="password" autocomplete="new-password" placeholder="Kosongkan jika tidak diubah"
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            <p class="mt-1 text-xs text-slate-400">Isi hanya jika ingin mengganti password pengguna (minimal 6 karakter).</p>
            @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
            <a href="{{ route('admin.users.show', $user->id) }}" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">Batal</a>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-600">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
                Simpan perubahan
            </button>
        </div>
    </form>
</div>
@endsection
