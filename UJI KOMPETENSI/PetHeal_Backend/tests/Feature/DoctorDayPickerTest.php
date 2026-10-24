<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pemilih hari kerja pada form dokter (create/edit).
 *
 * Sebelumnya field ini `<select multiple>`: tampilannya berupa daftar teks
 * polos ("Senin Selasa Rabu ...") yang tidak bisa diklik per hari, dan pesan
 * error bawaan "required" tidak menjelaskan apa yang harus dilakukan.
 *
 * Test ini mengunci perilaku yang harus tetap benar setelah UI diganti:
 * - form tetap mengirim `available_days[]` (kontrak controller tidak berubah),
 * - hari milik dokter lama tercentang saat form edit dibuka,
 * - validasi tetap menolak pilihan kosong dengan pesan yang bisa dibaca.
 */
class DoctorDayPickerTest extends TestCase
{
    use DatabaseTransactions;

    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    private Clinic $clinic;
    private User $admin;
    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'DayPicker ' . $suffix,
            'slug' => 'day-picker-' . $suffix,
            'address' => 'Jl. Day Picker',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'daypickadmin' . $suffix,
            'email' => 'daypickadmin' . $suffix . '@daypicker.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr Day ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['tuesday', 'thursday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    /**
     * Ambil markup satu checkbox hari tertentu.
     */
    private function checkboxTag(string $html, string $day): string
    {
        preg_match('/<input[^>]*value="' . $day . '"[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    }

    /**
     * Ambil tag pembuka elemen pesan di bawah pemilih hari.
     */
    private function errorTag(string $html): string
    {
        preg_match('/<p[^>]*data-day-picker-error[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    }

    public function test_create_form_renders_day_picker_for_every_day(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/doctors/create');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('data-day-picker', $html);
        $this->assertStringContainsString('name="available_days[]"', $html);

        foreach (self::DAYS as $day) {
            $this->assertNotSame('', $this->checkboxTag($html, $day), "Checkbox hari [{$day}] tidak dirender.");
        }

        // Preset cepat harus ada: semua / hari kerja / akhir pekan / kosongkan.
        foreach (['all', 'weekdays', 'weekend', 'none'] as $preset) {
            $this->assertStringContainsString('data-day-picker-preset="' . $preset . '"', $html);
        }

        // Select multiple lama sudah diganti.
        $this->assertStringNotContainsString('<select name="available_days[]"', $html);

        // Ringkasan jumlah hari terpilih, dalam bahasa aktif.
        $this->assertStringContainsString('0 dari 7 hari dipilih', $html);
    }

    public function test_create_form_uses_english_copy_in_english_locale(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['app_locale' => 'en'])
            ->get('/admin/doctors/create');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('0 of 7 days selected', $html);
        $this->assertStringContainsString('Monday', $html);
        $this->assertStringContainsString('All days', $html);
    }

    public function test_edit_form_preselects_the_days_already_stored(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/doctors/' . $this->doctor->id . '/edit');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('checked', $this->checkboxTag($html, 'tuesday'));
        $this->assertStringContainsString('checked', $this->checkboxTag($html, 'thursday'));
        $this->assertStringNotContainsString('checked', $this->checkboxTag($html, 'monday'));
        $this->assertStringNotContainsString('checked', $this->checkboxTag($html, 'sunday'));

        $this->assertStringContainsString('2 dari 7 hari dipilih', $html);
    }

    public function test_store_saves_the_selected_days(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/doctors', [
            'name' => 'Dr Baru',
            'specialization' => 'Bedah',
            'available_days' => ['monday', 'wednesday', 'saturday'],
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.doctors.index'));

        $doctor = Doctor::where('name', 'Dr Baru')->firstOrFail();

        $this->assertSame(
            ['monday', 'wednesday', 'saturday'],
            $doctor->available_days
        );
    }

    public function test_update_replaces_the_selected_days(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/doctors/' . $this->doctor->id, [
            'name' => $this->doctor->name,
            'specialization' => $this->doctor->specialization,
            'available_days' => ['friday'],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertRedirect(route('admin.doctors.index'));

        $this->assertSame(['friday'], $this->doctor->fresh()->available_days);
    }

    public function test_store_without_days_returns_a_readable_error(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/doctors', [
            'name' => 'Dr Tanpa Hari',
            'specialization' => 'Umum',
            'available_days' => [],
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('available_days');
        $this->assertSame(
            [__('admin.day_picker_required')],
            $response->getSession()->get('errors')->get('available_days')
        );

        $this->assertDatabaseMissing('doctors', ['name' => 'Dr Tanpa Hari']);

        // Halaman form yang dikembalikan harus menunjukkan pesan itu langsung
        // (bukan hanya lewat validasi klien).
        $form = $this->actingAs($this->admin)->get('/admin/doctors/create');
        $form->assertOk();
        $this->assertStringContainsString(__('admin.day_picker_required'), $form->getContent());
        $this->assertStringNotContainsString('hidden', $this->errorTag($form->getContent()));
    }

    public function test_rejected_day_is_still_blocked_by_validation(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/doctors', [
            'name' => 'Dr Hari Ngawur',
            'specialization' => 'Umum',
            'available_days' => ['funday'],
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('available_days.0');

        $this->assertDatabaseMissing('doctors', ['name' => 'Dr Hari Ngawur']);
    }
}
