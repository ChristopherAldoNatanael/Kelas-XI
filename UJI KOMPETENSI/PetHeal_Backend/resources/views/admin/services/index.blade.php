@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $services */
@endphp
@extends('layouts.admin')

@section('title', 'Services - PetHeal Admin')
@section('header', 'Services & Pricelist')

@section('content')

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
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">All Services</h2>
            <p class="text-xs text-slate-400 mt-0.5">Manage your clinic's service catalog and pricing</p>
        </div>
        <a href="{{ route('admin.services.create') }}" class="bg-primary text-white px-4 py-2 rounded-xl hover:bg-emerald-600 flex items-center gap-2 text-sm font-medium">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Add Service
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Service</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Category</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($services as $service)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $service->name }}</p>
                                @if($service->description)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-xs truncate">{{ $service->description }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($service->category)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ $service->category }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900 dark:text-white">
                            Rp {{ number_format($service->price, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $service->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' }}">
                                {{ $service->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.services.edit', $service->id) }}" class="p-2 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-400 dark:hover:bg-emerald-900/50 transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </a>
                                <form method="POST" action="{{ route('admin.services.destroy', $service->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this service?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 transition-colors" title="Delete">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300 dark:text-slate-600 mb-3">medical_services</span>
                                <p class="text-slate-500 dark:text-slate-400 font-medium">No services found</p>
                                <p class="text-xs text-slate-400 mt-1">Add your first service to get started</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($services->hasPages())
    <div class="p-6 border-t border-slate-100 dark:border-slate-800">
        {{ $services->links() }}
    </div>
    @endif
</div>
@endsection
