@extends('layouts.admin')

@section('title', 'Doctor Details - PetHeal Admin')
@section('header', 'Doctor Profile')

@section('content')
<div class="glass-card rounded-2xl overflow-hidden border border-slate-200/50">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-md font-bold text-slate-900 dark:text-white tracking-tight">Doctor Profile</h3>
                <p class="text-xs text-slate-400 mt-0.5">View doctor details and information</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.doctors.edit', $doctor->id) }}" class="bg-primary hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                    Edit
                </a>
                <a href="{{ route('admin.doctors.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold transition-all">
                    Back
                </a>
            </div>
        </div>
    </div>

    <div class="p-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left - Photo and Basic Info -->
            <div class="lg:w-1/3">
                <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6 text-center">
                    @if($doctor->photo_url)
                        <img class="w-32 h-32 rounded-full object-cover mx-auto mb-4" src="{{ $doctor->photo_url }}?t={{ time() }}" alt="{{ $doctor->name }}">
                    @elseif($doctor->photo)
                        <img class="w-32 h-32 rounded-full object-cover mx-auto mb-4" src="{{ asset('storage/' . $doctor->photo) }}?t={{ time() }}" alt="{{ $doctor->name }}">
                    @else
                        <div class="w-32 h-32 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center mx-auto mb-4">
                            <span class="material-symbols-outlined text-5xl text-slate-400">person</span>
                        </div>
                    @endif

                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $doctor->name }}</h2>
                    <p class="text-sm text-slate-500 mt-1">{{ $doctor->specialization }}</p>

                    <div class="mt-4">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $doctor->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                            {{ $doctor->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6 mt-6">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Contact Information</h4>
                    <div class="space-y-3">
                        @if($doctor->email)
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <span class="material-symbols-outlined text-blue-600 text-sm">email</span>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase">Email</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $doctor->email }}</p>
                            </div>
                        </div>
                        @endif

                        @if($doctor->phone)
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                                <span class="material-symbols-outlined text-emerald-600 text-sm">phone</span>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase">Phone</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300">{{ $doctor->phone }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right - Schedule and Stats -->
            <div class="lg:w-2/3 space-y-6">
                <!-- Schedule -->
                <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Schedule</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Available Days</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300 mt-1">
                                {{ implode(', ', is_array($doctor->available_days) ? $doctor->available_days : json_decode($doctor->available_days, true) ?? []) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Working Hours</p>
                            <p class="text-sm text-slate-700 dark:text-slate-300 mt-1">
                                {{ $doctor->start_time }} - {{ $doctor->end_time }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center">
                                <span class="material-symbols-outlined text-blue-600">event</span>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase">Total Bookings</p>
                                <p class="text-xl font-bold text-slate-900 dark:text-white">{{ $doctor->bookings->count() }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase">Completed</p>
                                <p class="text-xl font-bold text-slate-900 dark:text-white">{{ $doctor->bookings->where('status', 'completed')->count() }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-center">
                                <span class="material-symbols-outlined text-yellow-600">pending</span>
                            </div>
                            <div>
                                <p class="text-[10px] text-slate-400 uppercase">Pending</p>
                                <p class="text-xl font-bold text-slate-900 dark:text-white">{{ $doctor->bookings->where('status', 'pending')->count() }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                @if($doctor->bookings->count() > 0)
                <div class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6">
                    <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-4">Recent Bookings</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-[9px] uppercase tracking-[0.15em] font-bold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                    <th class="pb-3">Patient</th>
                                    <th class="pb-3">Date</th>
                                    <th class="pb-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                                @foreach($doctor->bookings->take(5) as $booking)
                                <tr class="hover:bg-slate-50/30 transition-colors">
                                    <td class="py-3 text-sm text-slate-700 dark:text-slate-300">{{ $booking->pet->name }}</td>
                                    <td class="py-3 text-sm text-slate-500">{{ \Carbon\Carbon::parse($booking->booking_date)->format('M d, Y') }}</td>
                                    <td class="py-3">
                                        @php
                                            $statusColors = [
                                                'pending' => ['bg-yellow-100 text-yellow-700 border-yellow-200', 'Pending'],
                                                'confirmed' => ['bg-blue-100 text-blue-700 border-blue-200', 'Scheduled'],
                                                'completed' => ['bg-emerald-100 text-emerald-700 border-emerald-200', 'Completed'],
                                                'cancelled' => ['bg-red-100 text-red-700 border-red-200', 'Cancelled'],
                                            ];
                                            $statusStyle = $statusColors[$booking->status] ?? ['bg-slate-100 text-slate-700 border-slate-200', ucfirst($booking->status)];
                                        @endphp
                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $statusStyle[0] }}">{{ $statusStyle[1] }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection