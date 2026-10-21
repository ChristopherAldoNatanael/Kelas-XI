@extends('layouts.admin')

@section('title', 'Pilih Klinik')
@section('header', 'Pilih Klinik')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 mb-2">Papan jadwal</p>
        <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Pilih klinik terlebih dahulu</h2>
        <p class="text-sm text-slate-500 mt-1">Papan jadwal menampilkan data operasional satu klinik. Pilih klinik yang ingin dibuka; data antar-klinik tidak akan tercampur.</p>
    </div>

    @if($clinics->isEmpty())
        <div class="glass-card rounded-2xl border border-slate-200/60 p-10 text-center">
            <span class="material-symbols-outlined text-slate-300 text-5xl">local_hospital</span>
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200 mt-3">Belum ada klinik aktif</h3>
            <p class="text-sm text-slate-500 mt-1">Aktifkan klinik terlebih dahulu dari menu Klinik.</p>
            <a href="{{ route('admin.clinics.index') }}" class="inline-flex items-center gap-2 mt-5 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-emerald-600 transition-colors">
                <span class="material-symbols-outlined text-[18px]">local_hospital</span>
                Kelola klinik
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($clinics as $clinic)
                <form method="POST" action="{{ route('admin.switch-clinic') }}">
                    @csrf
                    <input type="hidden" name="clinic_id" value="{{ $clinic->id }}">
                    <input type="hidden" name="return_to" value="schedule">
                    <button type="submit" class="w-full text-left rounded-2xl border border-slate-200/70 dark:border-slate-700 bg-white/80 dark:bg-slate-900/60 p-5 hover:border-primary/50 hover:shadow-md transition-all group">
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-primary">local_hospital</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ $clinic->name }}</h3>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">Aktif</span>
                                </div>
                                @if($clinic->address)
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $clinic->address }}</p>
                                @endif
                                @if($clinic->phone || $clinic->email)
                                    <p class="text-[10px] text-slate-400 mt-2">{{ collect([$clinic->phone, $clinic->email])->filter()->join(' · ') }}</p>
                                @endif
                            </div>
                            <span class="material-symbols-outlined text-slate-300 group-hover:text-primary transition-colors">arrow_forward</span>
                        </div>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>
@endsection
