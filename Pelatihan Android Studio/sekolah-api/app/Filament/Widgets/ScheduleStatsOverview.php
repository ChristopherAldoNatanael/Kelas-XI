<?php

namespace App\Filament\Widgets;

use App\Models\Schedule;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ScheduleStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Jadwal', Schedule::count())
                ->description('Jumlah total jadwal pelajaran')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart([70, 40, 80, 60, 50, 90, 100])
                ->color('primary'),
                
            Stat::make('Jadwal Aktif', Schedule::where('status', 'active')->count())
                ->description('Jumlah jadwal aktif')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart([30, 50, 70, 60, 80, 90, 100])
                ->color('success'),
                
            Stat::make('Hari Ini', Schedule::where('day_of_week', strtolower(now()->format('l')))->count())
                ->description('Jumlah jadwal hari ini')
                ->descriptionIcon('heroicon-m-calendar')
                ->chart([10, 20, 30, 40, 50, 60, 70])
                ->color('warning'),
        ];
    }
}