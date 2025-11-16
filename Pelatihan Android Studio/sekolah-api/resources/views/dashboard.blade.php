@extends('layouts.app')

@section('title', 'Dashboard')

@section('page-header')
<div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white rounded-lg shadow-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold mb-2">Dashboard Overview</h1>
            <p class="text-primary-100">Welcome back! Here's what's happening with your school management system.</p>
        </div>
        <div class="mt-4 md:mt-0">
            <div class="text-sm text-primary-100">Last updated</div>
            <div class="text-lg font-semibold">{{ now()->format('M d, Y H:i') }}</div>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="space-y-6">

    <!-- Modern Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Users -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Users</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_users']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Registered users</p>
                </div>
                <div class="h-12 w-12 bg-primary-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-primary-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <span class="text-green-600 font-medium">+12%</span>
                    <span class="text-gray-500 ml-1">from last month</span>
                </div>
            </div>
        </div>

        <!-- Total Schedules -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Schedules</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_schedules']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Active schedules</p>
                </div>
                <div class="h-12 w-12 bg-success-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar text-success-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <span class="text-green-600 font-medium">+8%</span>
                    <span class="text-gray-500 ml-1">from last week</span>
                </div>
            </div>
        </div>

        <!-- Total Teachers -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Teachers</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_teachers']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Active staff</p>
                </div>
                <div class="h-12 w-12 bg-warning-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-chalkboard-teacher text-warning-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <i class="fas fa-minus text-gray-500 mr-1"></i>
                    <span class="text-gray-600 font-medium">0%</span>
                    <span class="text-gray-500 ml-1">no change</span>
                </div>
            </div>
        </div>

        <!-- Total Subjects -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Subjects</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_subjects']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Available courses</p>
                </div>
                <div class="h-12 w-12 bg-danger-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-book text-danger-600 text-xl"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex items-center text-sm">
                    <i class="fas fa-arrow-up text-green-500 mr-1"></i>
                    <span class="text-green-600 font-medium">+3%</span>
                    <span class="text-gray-500 ml-1">new subjects added</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Classrooms -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Classrooms</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_classrooms']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Available rooms</p>
                </div>
                <div class="h-12 w-12 bg-secondary-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-building text-secondary-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Classes -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Classes</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['total_classes']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Student groups</p>
                </div>
                <div class="h-12 w-12 bg-primary-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-primary-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Active Schedules -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Active Schedules</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['active_schedules']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Currently running</p>
                </div>
                <div class="h-12 w-12 bg-success-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-success-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Today's Schedules -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Today's Schedules</p>
                    <p class="text-3xl font-bold text-gray-900">{{ number_format($stats['today_schedules']) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ now()->format('l') }}</p>
                </div>
                <div class="h-12 w-12 bg-warning-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-day text-warning-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>


</div>


@endsection
