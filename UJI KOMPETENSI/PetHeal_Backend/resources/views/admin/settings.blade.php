@extends('layouts.admin')
@section('title', 'Settings - PetHeal Admin')
@section('header', 'Admin Settings')

@section('content')
<div class="max-w-2xl">
    <div class="glass-card rounded-2xl p-8 border border-slate-200/50">
        <h3 class="text-lg font-bold text-slate-900 mb-6">Change Password</h3>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 text-sm">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.password') }}">
            @csrf
            <div class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Current Password</label>
                    <input type="password" name="current_password" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">New Password</label>
                    <input type="password" name="new_password" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm New Password</label>
                    <input type="password" name="new_password_confirmation" required class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition">
                </div>
                <button type="submit" class="px-6 py-2.5 bg-primary hover:bg-emerald-600 text-white font-semibold rounded-xl transition-all text-sm">Update Password</button>
            </div>
        </form>
    </div>
</div>
@endsection