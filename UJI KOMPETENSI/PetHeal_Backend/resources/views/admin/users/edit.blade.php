@extends('layouts.admin')

@section('title', 'Edit User - PetHeal Admin')
@section('header', 'Edit User')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-md font-bold text-slate-900 dark:text-white">Edit User #{{ $user->id }}</h2>
        <a href="{{ route('admin.users.show', $user->id) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>Back
        </a>
    </div>

    <div class="p-6">
        <form method="POST" action="{{ route('admin.users.update', $user->id) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Name *</label>
                    <input type="text" name="name" required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all"
                        placeholder="Enter user name" value="{{ old('name', $user->name) }}">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Email *</label>
                    <input type="email" name="email" required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all"
                        placeholder="Enter email address" value="{{ old('email', $user->email) }}">
                    @error('email')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Phone</label>
                    <input type="text" name="phone"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all"
                        placeholder="Enter phone number" value="{{ old('phone', $user->phone) }}">
                </div>

                <div class="form-group">
                    <label for="role" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Role</label>
                    <select id="role" name="role" class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all">
                        <option value="user" {{ old('role', $user->role ?? '') == 'user' ? 'selected' : '' }}>User</option>
                        <option value="admin" {{ old('role', $user->role ?? '') == 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">New Password</label>
                    <input type="password" name="password"
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 focus:ring-2 focus:ring-primary focus:border-transparent text-sm outline-none transition-all"
                        placeholder="Leave blank to keep current password">
                    <p class="text-xs text-slate-400 mt-1">Leave blank to keep the current password</p>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Firebase UID</label>
                    <input type="text" disabled
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 cursor-not-allowed"
                        value="{{ $user->firebase_uid ?? 'Local User' }}">
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('admin.users.show', $user->id) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold transition-all">
                    Cancel
                </a>
                <button type="submit" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-1">
                    <span class="material-symbols-outlined text-[18px]">save</span>Update User
                </button>
            </div>
        </form>
    </div>
</div>
@endsection