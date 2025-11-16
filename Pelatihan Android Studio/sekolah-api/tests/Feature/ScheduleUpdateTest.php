<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Classroom;
use App\Models\ClassModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ScheduleUpdateTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $teacher;
    protected $subject;
    protected $classroom;
    protected $class;
    protected $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test data
        $this->subject = Subject::factory()->create();
        $this->teacher = Teacher::factory()->create();
        $this->classroom = Classroom::factory()->create();
        $this->class = ClassModel::factory()->create();

        // Create test schedule
        $this->schedule = Schedule::create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'classroom_id' => $this->classroom->id,
            'day_of_week' => 'monday',
            'period_number' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'academic_year' => '2024/2025',
            'semester' => 'ganjil',
            'status' => 'active',
            'notes' => 'Original notes',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
    }

    /**
     * Test Schedule Update Success
     */
    public function test_schedule_update_success()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 2,
            'notes' => 'Updated notes',
        ];

        $response = $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        // Check redirect
        $response->assertRedirect(route('web-schedules.index'));
        $response->assertSessionHas('success', 'Schedule updated successfully and changes saved to database.');

        // Verify in database
        $this->assertDatabaseHas('schedules', [
            'id' => $this->schedule->id,
            'day_of_week' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 2,
            'notes' => 'Updated notes',
        ]);
    }

    /**
     * Test Schedule Update - End Time Must Be After Start Time
     */
    public function test_schedule_update_end_time_must_be_after_start_time()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => '11:00',
            'end_time' => '10:00', // End time before start time
            'period_number' => 2,
            'notes' => 'Updated notes',
        ];

        $response = $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        // Should fail validation
        $response->assertSessionHasErrors('end_time');

        // Verify data NOT changed in database
        $this->assertDatabaseHas('schedules', [
            'id' => $this->schedule->id,
            'day_of_week' => 'monday', // Still original value
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);
    }

    /**
     * Test Schedule Update - Required Fields Missing
     */
    public function test_schedule_update_missing_required_fields()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            // Missing subject_id
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 2,
        ];

        $response = $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        $response->assertSessionHasErrors('subject_id');
    }

    /**
     * Test Schedule Update - Invalid Day
     */
    public function test_schedule_update_invalid_day()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'invalid_day', // Invalid day
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 2,
        ];

        $response = $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        $response->assertSessionHasErrors('day');
    }

    /**
     * Test Schedule Update - Invalid Period Number
     */
    public function test_schedule_update_invalid_period_number()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 15, // Invalid (max 10)
        ];

        $response = $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        $response->assertSessionHasErrors('period_number');
    }

    /**
     * Test Schedule Update - Invalid Time Format
     */
    public function test_schedule_update_invalid_time_format()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => 'invalid_time', // Invalid format
            'end_time' => '11:00',
            'period_number' => 2,
        ];

        $response = $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        $response->assertSessionHasErrors('start_time');
    }

    /**
     * Test Schedule Update - Schedule Not Found
     */
    public function test_schedule_update_not_found()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 2,
        ];

        $response = $this->put(route('web-schedules.update', 999), $updateData);

        $response->assertNotFound();
    }

    /**
     * Test Schedule Update With Only Notes Change
     */
    public function test_schedule_update_only_notes()
    {
        $this->actingAs($this->user);

        $newNotes = 'This is updated notes only';
        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'monday', // Same as original
            'start_time' => '08:00', // Same as original
            'end_time' => '09:00', // Same as original
            'period_number' => 1, // Same as original
            'notes' => $newNotes,
        ];

        $response = $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        $response->assertRedirect(route('web-schedules.index'));

        $this->assertDatabaseHas('schedules', [
            'id' => $this->schedule->id,
            'notes' => $newNotes,
        ]);
    }

    /**
     * Test Schedule Update Timestamp Updated
     */
    public function test_schedule_update_timestamp_updated()
    {
        $this->actingAs($this->user);

        $originalUpdatedAt = $this->schedule->updated_at;

        sleep(1); // Wait 1 second

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 2,
        ];

        $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        $updatedSchedule = Schedule::find($this->schedule->id);

        $this->assertGreaterThan(
            $originalUpdatedAt,
            $updatedSchedule->updated_at,
            'updated_at timestamp should be newer after update'
        );
    }

    /**
     * Test Schedule Edit Form Load
     */
    public function test_schedule_edit_form_loads()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('web-schedules.edit', $this->schedule->id));

        $response->assertStatus(200);
        $response->assertViewIs('schedules.edit');
        $response->assertViewHas('schedule');
        $response->assertViewHas('dropdownData');
    }

    /**
     * Test Schedule Edit Form Contains Current Data
     */
    public function test_schedule_edit_form_contains_current_data()
    {
        $this->actingAs($this->user);

        $response = $this->get(route('web-schedules.edit', $this->schedule->id));

        $response->assertViewHas('schedule', function ($schedule) {
            return $schedule->id === $this->schedule->id
                && $schedule->day_of_week === 'monday'
                && $schedule->period_number === 1;
        });
    }

    /**
     * Test Updated By User Is Recorded
     */
    public function test_updated_by_user_is_recorded()
    {
        $this->actingAs($this->user);

        $updateData = [
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'classroom_id' => $this->classroom->id,
            'class_id' => $this->class->id,
            'day' => 'tuesday',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'period_number' => 2,
            'notes' => 'Updated notes',
        ];

        $this->put(route('web-schedules.update', $this->schedule->id), $updateData);

        $updatedSchedule = Schedule::find($this->schedule->id);

        $this->assertEquals(
            $this->user->id,
            $updatedSchedule->updated_by,
            'updated_by should be the current user'
        );
    }
}
