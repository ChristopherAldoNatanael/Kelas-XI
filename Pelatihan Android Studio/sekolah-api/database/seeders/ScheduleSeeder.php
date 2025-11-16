<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Schedule;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Teacher;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $classrooms = Classroom::all();
        $subjects = Subject::all();
        $teachers = Teacher::all();

        // Jika tidak ada data, buat data dummy
        if ($classrooms->isEmpty()) {
            $classrooms = collect([
                (object)['id' => 1, 'name' => 'Lab Komputer 1', 'code' => 'LAB1'],
                (object)['id' => 2, 'name' => 'Ruang X RA', 'code' => 'XRA'],
                (object)['id' => 3, 'name' => 'Ruang X RB', 'code' => 'XRB'],
            ]);
        }

        if ($subjects->isEmpty()) {
            $subjects = collect([
                (object)['id' => 1, 'name' => 'Bahasa Indonesia'],
                (object)['id' => 2, 'name' => 'Matematika'],
                (object)['id' => 3, 'name' => 'IPA'],
                (object)['id' => 4, 'name' => 'IPS'],
                (object)['id' => 5, 'name' => 'Bahasa Inggris'],
            ]);
        }

        if ($teachers->isEmpty()) {
            $teachers = collect([
                (object)['id' => 1, 'user_id' => 1, 'subject_id' => 1],
                (object)['id' => 2, 'user_id' => 2, 'subject_id' => 2],
                (object)['id' => 3, 'user_id' => 3, 'subject_id' => 3],
                (object)['id' => 4, 'user_id' => 4, 'subject_id' => 4],
            ]);
        }

        // Sample schedule data for Senin to Jumat (use database enum values)
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        // Create schedules for all RPL classes (X, XI, XII)
        $rplClasses = [
            ['id' => 1, 'name' => 'X RPL 1'],
            ['id' => 2, 'name' => 'X RPL 2'],
            ['id' => 3, 'name' => 'XI RPL 1'],
            ['id' => 4, 'name' => 'XI RPL 2'],
            ['id' => 5, 'name' => 'XII RPL 1']
        ];

        foreach ($rplClasses as $classData) {
            foreach ($days as $dayIndex => $day) {
                $periodStart = 1;

                // 8 periods per day (more realistic for SMK)
                for ($period = 0; $period < 8; $period++) {
                    $roomIndex = ($period + $dayIndex + $classData['id']) % $classrooms->count();
                    $subjectIndex = ($period + $dayIndex + $classData['id']) % $subjects->count();
                    $teacherIndex = ($subjectIndex + $period) % $teachers->count();

                    // More realistic time slots for SMK (45 minutes per subject)
                    $startHour = 7 + floor(($period * 45 + 15) / 60);
                    $startMinute = ($period * 45 + 15) % 60;
                    $endHour = 7 + floor(($period * 45 + 60) / 60);
                    $endMinute = ($period * 45 + 60) % 60;

                    $startTime = sprintf('%02d:%02d', $startHour, $startMinute);
                    $endTime = sprintf('%02d:%02d', $endHour, $endMinute);

                    // Add some variety in notes
                    $notes = ['Regular class', 'Practice session', 'Theory class', 'Lab work', 'Group discussion', 'Project work'][rand(0, 5)];

                    DB::table('schedules')->insert([
                        'class_id' => $classData['id'],
                        'subject_id' => $subjects[$subjectIndex]->id,
                        'teacher_id' => $teachers[$teacherIndex]->id,
                        'classroom_id' => $classrooms[$roomIndex]->id,
                        'day_of_week' => $day,
                        'period_number' => $period + 1,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'academic_year' => '2024/2025',
                        'semester' => 'ganjil',
                        'status' => 'active',
                        'notes' => $notes,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'created_by' => 1 // Use admin user ID
                    ]);
                }
            }
        }

        $this->command->info('Schedules seeded successfully!');
        $this->command->info('Created schedules for X RA class, Monday to Friday.');
    }
}
