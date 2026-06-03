@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $records */
@endphp
@extends('layouts.admin')

@section('title', 'Medical Records - PetHeal Admin')
@section('header', 'Medical Records Management')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-md font-bold text-slate-900 dark:text-white">All Medical Records</h2>
            <p class="text-xs text-slate-400 mt-0.5">Manage patient medical records</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50/50 dark:bg-slate-800/30">
                <tr>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">Pet</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">Owner</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">Doctor</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">Diagnosis</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">Cost</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">Date</th>
                    <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                @forelse($records as $record)
                    <tr class="hover:bg-slate-50/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                @if($record->pet->photo)
                                    <img class="h-8 w-8 rounded-full object-cover"
                                         src="{{ asset('storage/' . $record->pet->photo) }}"
                                         alt="{{ $record->pet->name }}">
                                @else
                                    <div class="h-8 w-8 rounded-full bg-emerald-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-emerald-600 text-[16px]">pets</span>
                                    </div>
                                @endif
                                <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $record->pet->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $record->booking->user->name ?? 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $record->doctor->name }}</td>
                        <td class="px-6 py-4 text-sm text-slate-500 max-w-xs truncate">{{ $record->diagnosis }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-emerald-600">
                            Rp {{ number_format(($record->cost ?? 0) + ($record->treatment_cost ?? 0) + ($record->medicine_cost ?? 0), 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{{ $record->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.medical-records.show', $record->id) }}" class="text-slate-400 hover:text-primary transition-colors" title="View">
                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                </a>
                                <a href="{{ route('admin.medical-records.edit', $record->id) }}" class="text-slate-400 hover:text-primary transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                </a>
                                <form method="POST" action="{{ route('admin.medical-records.destroy', $record->id) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-red-500 transition-colors" title="Delete">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                                <span class="material-symbols-outlined text-[48px] mb-3">clinical_notes</span>
                                <p class="text-sm text-slate-500">No medical records found</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100">
        {{ $records->links() }}
    </div>
</div>
@endsection
