<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScheduleOptimizationService
{
    protected $cacheKey = 'schedules_optimized';
    protected $ttl = 300; // 5 minutes (reduced from 1 hour)

    public function getCachedSchedules($withRelations = true, $filters = [])
    {
        $cacheKey = $this->generateCacheKey($withRelations, $filters);

        return Cache::remember($cacheKey, $this->ttl, function () use ($withRelations, $filters) {
            $query = Schedule::query();

            // Use lighter relations for listing to avoid memory issues
            if ($withRelations) {
                $query = $query->with([
                    'class:id,name',
                    'subject:id,name,code',
                    'teacher:id,user_id,teacher_code',
                    'teacher.user:id,nama',
                    'classroom:id,name,code'
                ]);
            }

            // Apply filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['day_of_week'])) {
                $query->where('day_of_week', $filters['day_of_week']);
            }

            if (!empty($filters['class_id'])) {
                $query->where('class_id', $filters['class_id']);
            }

            if (!empty($filters['teacher_id'])) {
                $query->where('teacher_id', $filters['teacher_id']);
            }

            if (!empty($filters['academic_year'])) {
                $query->where('academic_year', $filters['academic_year']);
            }

            if (!empty($filters['semester'])) {
                $query->where('semester', $filters['semester']);
            }

            $schedules = $query->orderBy('hari', 'asc')
                ->orderBy('jam_mulai', 'asc')
                ->get();

            // Transform to array format with proper data structure for views
            return $schedules->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'subject' => [
                        'id' => $schedule->subject?->id,
                        'name' => $schedule->subject?->name,
                        'code' => $schedule->subject?->code
                    ],
                    'teacher' => [
                        'id' => $schedule->teacher?->id,
                        'nama' => $schedule->teacher?->user?->nama,
                        'teacher_code' => $schedule->teacher?->teacher_code
                    ],
                    'classroom' => [
                        'id' => $schedule->classroom?->id,
                        'name' => $schedule->classroom?->name,
                        'code' => $schedule->classroom?->code
                    ],
                    'day' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'period_number' => $schedule->period_number,
                    'notes' => $schedule->notes
                ];
            });
        });
    }

    public function getTodaysSchedules($withRelations = true)
    {
        $cacheKey = $this->cacheKey . '_today_' . strtolower(now()->format('l'));

        return Cache::remember($cacheKey, $this->ttl, function () use ($withRelations) {
            $query = Schedule::withAllRelations();
            $query->where('day_of_week', strtolower(now()->format('l')));
            $query->where('status', 'active');

            return $query->orderBy('period_number', 'asc')->get();
        });
    }

    public function getWeeklySchedule($classId = null, $withRelations = true)
    {
        $cacheKey = $this->cacheKey . '_weekly';
        if ($classId) {
            $cacheKey .= "_class_{$classId}";
        }

        return Cache::remember($cacheKey, $this->ttl, function () use ($classId, $withRelations) {
            $query = Schedule::withAllRelations();
            $query->active();

            if ($classId) {
                $query->where('class_id', $classId);
            }

            return $query->orderBy('hari', 'asc')
                ->orderBy('jam_mulai', 'asc')
                ->get();
        });
    }

    public function clearScheduleCache()
    {
        Cache::forget($this->cacheKey . '_today_' . strtolower(now()->format('l')));
        Cache::forget($this->cacheKey . '_weekly');

        // Clear all schedule-related cache keys
        $this->clearAllScheduleCache();
    }

    public function clearAllScheduleCache()
    {
        // Clear cache tags only if using a driver that supports tags
        $cacheDriver = config('cache.default');
        if (in_array($cacheDriver, ['redis', 'memcached', 'array'])) {
            Cache::tags(['schedules', 'academic', 'timetable'])->flush();
        }
    }

    protected function generateCacheKey($withRelations, $filters)
    {
        $key = $this->cacheKey;
        $key .= $withRelations ? '_with_relations' : '_without_relations';

        foreach ($filters as $filter => $value) {
            $key .= '_' . $filter . '_' . $value;
        }

        return $key;
    }

    public function getScheduleStatistics()
    {
        $cacheKey = $this->cacheKey . '_stats';

        return Cache::remember($cacheKey, $this->ttl * 2, function () {
            return [
                'total' => Schedule::count(),
                'active' => Schedule::where('status', 'active')->count(),
                'inactive' => Schedule::where('status', 'inactive')->count(),
                'by_day' => Schedule::select('day_of_week', DB::raw('count(*) as count'))
                    ->groupBy('day_of_week')
                    ->pluck('count', 'day_of_week')
                    ->toArray(),
                'by_class' => Schedule::select('class_id', DB::raw('count(*) as count'))
                    ->groupBy('class_id')
                    ->pluck('count', 'class_id')
                    ->toArray(),
            ];
        });
    }
}
