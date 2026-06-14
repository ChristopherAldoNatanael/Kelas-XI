@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $doctors */
@endphp
@extends('layouts.admin')

@section('title', 'Doctors - PetHeal Admin')
@section('header', 'Veterinarians Management')

@section('content')

{{-- Success / Error Notification --}}
@if(session('success'))
<div id="flash-msg" class="mb-4 flex items-center gap-3 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 px-5 py-3 rounded-xl text-sm font-medium">
    <span class="material-symbols-outlined text-[18px]">check_circle</span>
    {{ session('success') }}
</div>
<script>setTimeout(()=>{ const el=document.getElementById('flash-msg'); if(el) el.style.display='none'; }, 3000);</script>
@endif

@if(session('error'))
<div class="mb-4 flex items-center gap-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-400 px-5 py-3 rounded-xl text-sm font-medium">
    <span class="material-symbols-outlined text-[18px]">error</span>
    {{ session('error') }}
</div>
@endif

<div class="glass-card rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
        <div>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Daftar Dokter Hewan</h2>
            <p class="text-xs text-slate-400 mt-0.5">Kelola profil, jadwal, dan performa tim dokter</p>
        </div>
        <a href="{{ route('admin.doctors.create') }}" class="bg-primary text-white px-4 py-2 rounded-xl hover:bg-emerald-600 flex items-center gap-2 text-sm font-medium">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah Dokter
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Doctor</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Specialization</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Rating</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Schedule</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($doctors as $doctor)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                @if($doctor->photo_url)
                                    <img class="h-12 w-12 rounded-full object-cover mr-4 ring-2 ring-slate-100 dark:ring-slate-700" src="{{ $doctor->photo_url }}?t={{ time() }}" alt="{{ $doctor->name }}">
                                @elseif($doctor->photo)
                                    <img class="h-12 w-12 rounded-full object-cover mr-4 ring-2 ring-slate-100 dark:ring-slate-700" src="{{ asset('storage/' . $doctor->photo) }}?t={{ time() }}" alt="{{ $doctor->name }}">
                                @else
                                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-emerald-100 to-blue-100 dark:from-emerald-900/30 dark:to-blue-900/30 flex items-center justify-center mr-4 ring-2 ring-slate-100 dark:ring-slate-700">
                                        <span class="material-symbols-outlined text-emerald-600 dark:text-emerald-400">person</span>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $doctor->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $doctor->email ?? 'Email belum diisi' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                {{ $doctor->specialization }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <div class="inline-flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-amber-500">star</span>
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ $doctor->reviews_count > 0 ? number_format((float) $doctor->reviews_avg_rating, 1) : '0.0' }}
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                        ({{ $doctor->reviews_count }} review{{ $doctor->reviews_count === 1 ? '' : 's' }})
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400">
                                    {{ $doctor->completed_bookings_count }} booking selesai
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600 dark:text-slate-400">
                            <div class="flex flex-col">
                                <span>{{ implode(', ', array_slice($doctor->available_days, 0, 3)) }}</span>
                                <span class="text-xs text-slate-400">{{ $doctor->start_time }} - {{ $doctor->end_time }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $doctor->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $doctor->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.doctors.show', $doctor->id) }}" class="p-2 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 transition-colors" title="Lihat detail">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </a>
                                <a href="{{ route('admin.doctors.edit', $doctor->id) }}" class="p-2 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 transition-colors" title="Ubah">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form method="POST" action="{{ route('admin.doctors.destroy', $doctor->id) }}" class="inline" onsubmit="return confirm('Yakin ingin menghapus dokter ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-600 mb-3">medical_services</span>
                                <p class="text-slate-600 dark:text-slate-300 font-medium">Belum ada dokter yang tampil</p>
                                <p class="text-xs text-slate-400 mt-1">Tambahkan dokter pertama agar user bisa mulai membuat booking konsultasi.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($doctors->hasPages())
    <div class="p-6 border-t border-slate-100 dark:border-slate-800">
        {{ $doctors->links() }}
    </div>
    @endif
</div>
@endsection
