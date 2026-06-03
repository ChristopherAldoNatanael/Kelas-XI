@extends('layouts.admin')

@section('title', 'User Details - PetHeal Admin')
@section('header', 'User Details')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-md font-bold text-slate-900 dark:text-white">User #{{ $user->id }}</h2>
        <div class="flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>Back
            </a>
            <a href="{{ route('admin.users.edit', $user->id) }}" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">edit</span>Edit
            </a>
        </div>
    </div>

    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- User Info -->
            <div class="bg-slate-50/50 rounded-2xl p-4">
                <h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">User Information</h3>
                <div class="flex items-center gap-4 mb-4">
                    @if($user->photo)
                        <img src="{{ $user->photo }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full">
                    @else
                        <div class="h-16 w-16 rounded-full bg-emerald-100 flex items-center justify-center">
                            <span class="text-emerald-600 text-2xl font-semibold">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                    @endif
                    <div>
                        <p class="text-xl font-semibold text-slate-900 dark:text-white">{{ $user->name }}</p>
                        <span class="px-2 py-0.5 text-[9px] font-bold rounded border inline-block mt-1
                            {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-700 border-purple-200' :
                               ($user->role === 'doctor' ? 'bg-blue-100 text-blue-700 border-blue-200' : 'bg-slate-100 text-slate-700 border-slate-200') }}">
                            {{ ucfirst($user->role) }}
                        </span>
                    </div>
                </div>

                <div class="space-y-2">
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Email</p>
                        <p class="text-sm text-slate-800 dark:text-slate-200">{{ $user->email }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Phone</p>
                        <p class="text-sm text-slate-800 dark:text-slate-200">{{ $user->phone ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Firebase UID</p>
                        <p class="text-sm text-slate-800 dark:text-slate-200 bg-slate-100 px-2 py-1 rounded inline-block">{{ $user->firebase_uid ?? 'Local User' }}</p>
                    </div>
                </div>
            </div>

            <!-- Firebase Data -->
            @if($firebaseData)
            <div class="bg-slate-50/50 rounded-2xl p-4">
                <h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">Firebase Data</h3>
                <div class="space-y-2 text-sm">
                    @foreach($firebaseData as $key => $value)
                        <div>
                            <p class="text-slate-500 text-[10px] font-bold uppercase tracking-wider">{{ $key }}</p>
                            <p class="text-sm text-slate-800 dark:text-slate-200">{{ is_array($value) ? json_encode($value) : $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Pets -->
        @if($user->pets && $user->pets->count() > 0)
        <div class="mt-6">
            <h3 class="text-md font-bold text-slate-900 dark:text-white mb-3">Pets ({{ $user->pets->count() }})</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($user->pets as $pet)
                    <div class="bg-slate-50/50 rounded-2xl p-4">
                        <div class="flex items-center gap-3">
                            @if($pet->photo)
                                <img class="h-10 w-10 rounded-full object-cover"
                                     src="{{ asset('storage/' . $pet->photo) }}"
                                     alt="{{ $pet->name }}">
                            @else
                                <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-emerald-600 text-[20px]">pets</span>
                                </div>
                            @endif
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $pet->name }}</p>
                                <p class="text-sm text-slate-500">{{ $pet->species ?? 'Pet' }} - {{ $pet->breed ?? '' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Bookings -->
        @if($user->bookings && $user->bookings->count() > 0)
        <div class="mt-6">
            <h3 class="text-md font-bold text-slate-900 dark:text-white mb-3">Recent Bookings ({{ $user->bookings->count() }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50/50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Pet</th>
                            <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Doctor</th>
                            <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Date</th>
                            <th class="px-6 py-4 text-left text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100/50">
                        @foreach($user->bookings->take(5) as $booking)
                            <tr class="hover:bg-slate-50/30 transition-colors">
                                <td class="px-6 py-4 text-sm text-slate-800 dark:text-slate-200">{{ $booking->pet->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-500">{{ $booking->doctor->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-500">{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded border inline-block
                                        {{ $booking->status === 'completed' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' :
                                           ($booking->status === 'confirmed' ? 'bg-blue-100 text-blue-700 border-blue-200' :
                                           ($booking->status === 'cancelled' ? 'bg-red-100 text-red-700 border-red-200' : 'bg-yellow-100 text-yellow-700 border-yellow-200')) }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="mt-6 pt-4 border-t border-slate-100">
            <p class="text-xs text-slate-400">
                Created: {{ $user->created_at->format('M d, Y H:i') }}
                @if($user->updated_at->ne($user->created_at))
                    | Updated: {{ $user->updated_at->format('M d, Y H:i') }}
                @endif
            </p>
        </div>
    </div>
</div>
@endsection