@php
/** @var \Illuminate\Pagination\LengthAwarePaginator $users */
@endphp
@extends('layouts.admin')

@section('title', 'Users - PetHeal Admin')
@section('header', 'Users Management')

@section('content')
<!-- Breadcrumbs -->
<nav class="mb-6">
    <ol class="flex items-center space-x-2 text-sm">
        <li><a href="{{ route('admin.dashboard') }}" class="text-slate-500 dark:text-slate-400 hover:text-primary transition-colors">Dashboard</a></li>
        <li class="text-slate-400 dark:text-slate-500">/</li>
        <li class="text-slate-900 dark:text-white font-medium">Users</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white mb-1">Users Management</h1>
        <p class="text-slate-500 dark:text-slate-400">Manage all registered users in the system</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.users.create') }}" 
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-emerald-600 text-white font-semibold rounded-xl transition-all shadow-sm">
            <span class="material-symbols-outlined text-[20px]">person_add</span>
            Add User
        </a>
    </div>
</div>

<!-- Search & Filters -->
<div class="glass-card rounded-2xl p-4 mb-6 border border-slate-200/50 dark:border-slate-700">
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex gap-3">
        <div class="flex-1 relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500">search</span>
            <input type="text" 
                   name="search" 
                   value="{{ request('search') }}" 
                   placeholder="Search by name, email, or phone..." 
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all dark:text-slate-200">
        </div>
        <button type="submit" 
                class="px-6 py-2.5 bg-primary hover:bg-emerald-600 text-white font-semibold rounded-xl transition-all">
            Search
        </button>
        @if(request('search'))
            <a href="{{ route('admin.users.index') }}" 
               class="px-4 py-2.5 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-semibold rounded-xl transition-all">
                Clear
            </a>
        @endif
    </form>
</div>

<!-- Users Table -->
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    @if($users->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50/50 dark:bg-slate-800/30 border-b border-slate-100 dark:border-slate-700">
                    <tr>
                        <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500">User</th>
                        <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500">Email</th>
                        <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500">Phone</th>
                        <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500">Role</th>
                        <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 dark:text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                    @foreach($users as $user)
                        <tr class="hover:bg-slate-50/30 dark:hover:bg-slate-800/20 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    @if($user->photo)
                                        <img src="{{ $user->photo }}" 
                                             alt="{{ $user->name }}" 
                                             class="h-10 w-10 rounded-full mr-3 object-cover ring-2 ring-white"
                                             loading="lazy">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center mr-3 text-white font-semibold text-sm">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                    @endif
                                    <div>
                                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $user->name }}</span>
                                        @if($user->firebase_uid)
                                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">UID: {{ Str::limit($user->firebase_uid, 12) }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-slate-600 dark:text-slate-300">{{ $user->email }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-slate-500 dark:text-slate-400">{{ $user->phone ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full
                                    {{ $user->role === 'admin' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' :
                                       ($user->role === 'doctor' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300') }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('admin.users.show', $user->id) }}" 
                                       class="w-8 h-8 rounded-lg text-slate-400 hover:text-primary transition-all inline-flex items-center justify-center" 
                                       title="View User">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    <a href="{{ route('admin.users.edit', $user->id) }}" 
                                       class="w-8 h-8 rounded-lg text-slate-400 hover:text-primary transition-all inline-flex items-center justify-center" 
                                       title="Edit User">
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </a>
                                    <form method="POST" 
                                          action="{{ route('admin.users.destroy', $user->id) }}" 
                                          class="inline" 
                                          onsubmit="return confirm('Are you sure you want to delete {{ $user->name }}? This action cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="w-8 h-8 rounded-lg text-slate-400 hover:text-red-500 transition-all inline-flex items-center justify-center" 
                                                title="Delete User">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="py-16 px-6 text-center">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full mb-4">
                <span class="material-symbols-outlined text-5xl text-slate-400 dark:text-slate-500">group_off</span>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">No users found</h3>
            <p class="text-slate-500 dark:text-slate-400 mb-6 max-w-md mx-auto">
                @if(request('search'))
                    No users match your search "{{ request('search') }}". Try different keywords.
                @else
                    Get started by syncing users from Firebase or manually adding a new user.
                @endif
            </p>
            <div class="flex justify-center gap-3">
                @if(!request('search'))
                    <a href="{{ route('admin.users.sync') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-emerald-600 text-white font-semibold rounded-xl transition-all">
                        <span class="material-symbols-outlined text-[18px]">sync</span>
                        Sync from Firebase
                    </a>
                @else
                    <a href="{{ route('admin.users.index') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold rounded-xl transition-all">
                        <span class="material-symbols-outlined text-[18px]">clear</span>
                        Clear Search
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>

<!-- Stats Cards -->
@if($users->count() > 0)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <div class="glass-card rounded-2xl p-5 border border-slate-200/50 dark:border-slate-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Total Users</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $users->total() }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl text-emerald-600">group</span>
                </div>
            </div>
        </div>
        <div class="glass-card rounded-2xl p-5 border border-slate-200/50 dark:border-slate-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Admins</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $users->where('role', 'admin')->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl text-purple-600">admin_panel_settings</span>
                </div>
            </div>
        </div>
        <div class="glass-card rounded-2xl p-5 border border-slate-200/50 dark:border-slate-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-1">Doctors</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $users->where('role', 'doctor')->count() }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                    <span class="material-symbols-outlined text-2xl text-blue-600">medical_services</span>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection