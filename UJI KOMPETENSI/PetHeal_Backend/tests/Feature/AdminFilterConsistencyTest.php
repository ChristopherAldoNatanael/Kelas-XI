<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kontrak laporan admin: kartu ringkasan, daftar, dan export harus membaca
 * filter yang sama.
 *
 * Tiga perilaku yang dikunci di sini pernah menjadi sumber laporan yang
 * menyesatkan:
 * - export pembayaran selalu menambahkan `paid_amount > 0`, sehingga filter
 *   `unpaid`/`dp_pending` menghasilkan PDF kosong;
 * - daftar "terbaru" dan saldo tertunggak di dashboard mengabaikan rentang
 *   tanggal yang dipakai kartu statistik;
 * - dashboard berdate-range memakai cache all-time, jadi pembayaran baru bisa
 *   tidak terlihat sampai lima menit.
 */
class AdminFilterConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinic;
    private User $admin;
    private Doctor $doctor;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Filter ' . $this->suffix,
            'slug' => 'filter-' . $this->suffix,
            'address' => 'Jl. Filter',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'filteradmin' . $this->suffix,
            'email' => 'filteradmin' . $this->suffix . '@filter.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr Filter ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $this->service = Service::create([
            'name' => 'Konsultasi Filter ' . $this->suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
        ]);
    }

    private function makePet(string $name): Pet
    {
        $owner = User::create([
            'name' => 'Owner ' . $name,
            'email' => strtolower($name) . '-' . $this->suffix . '@filter.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinic->id,
        ]);

        return Pet::create([
            'user_id' => $owner->id,
            'name' => $name,
            'species' => 'Cat',
            'gender' => 'male',
        ]);
    }

    private function makeBooking(Pet $pet, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $pet->user_id,
            'pet_id' => $pet->id,
            'doctor_id' => $this->doctor->id,
            'service_id' => $this->service->id,
            'booking_date' => Carbon::today()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'confirmed',
            'payment_type' => 'full',
            'payment_status' => 'unpaid',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'clinic_id' => $this->clinic->id,
        ], $attributes));
    }

    /**
     * created_at tidak fillable; paksa tanggalnya tanpa menyentuh updated_at.
     */
    private function backdate(Booking $booking, Carbon $at): void
    {
        $booking->timestamps = false;
        $booking->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }

    /**
     * Dompdf mengompres content stream. Test membaca teks dari stream yang
     * sudah didekompres supaya isi PDF benar-benar diperiksa, bukan hanya
     * status 200-nya.
     */
    private function pdfText(string $contents): string
    {
        $text = '';

        preg_match_all('/stream\r?\n(.*?)endstream/s', $contents, $streams);
        foreach ($streams[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded !== false) {
                $text .= $decoded;
            }
        }

        return $text !== '' ? $text : $contents;
    }

    public function test_payment_export_contains_rows_for_a_filter_without_paid_amount(): void
    {
        $unpaid = $this->makeBooking($this->makePet('UnpaidExportPet'), [
            'payment_status' => 'unpaid',
            'total_amount' => 450000,
            'paid_amount' => 0,
            'remaining_amount' => 450000,
        ]);

        $paid = $this->makeBooking($this->makePet('PaidExportPet'), [
            'payment_status' => 'paid',
            'total_amount' => 500000,
            'paid_amount' => 500000,
            'remaining_amount' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.payments.export', ['status' => 'unpaid']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $text = $this->pdfText($response->getContent());
        $this->assertStringContainsString('UnpaidExportPet', $text);
        $this->assertStringNotContainsString('PaidExportPet', $text);
    }

    public function test_payment_summary_cards_follow_the_active_filter(): void
    {
        $this->makeBooking($this->makePet('StatsUnpaidPet'), [
            'payment_status' => 'unpaid',
            'total_amount' => 987654,
            'paid_amount' => 0,
            'remaining_amount' => 987654,
        ]);

        $this->makeBooking($this->makePet('StatsPaidPet'), [
            'payment_status' => 'paid',
            'total_amount' => 500000,
            'paid_amount' => 500000,
            'remaining_amount' => 0,
        ]);

        $filtered = $this->actingAs($this->admin)
            ->get('/admin/payments?status=unpaid')
            ->assertOk();

        $stats = $filtered->viewData('stats');
        $this->assertSame(1, (int) $stats['total_transactions']);
        $this->assertEquals(987654, (float) $stats['total_outstanding']);
        $this->assertEquals(0, (float) $stats['total_collected']);
        $this->assertSame(1, $filtered->viewData('payments')->total());

        $all = $this->actingAs($this->admin)
            ->get('/admin/payments')
            ->assertOk();

        $allStats = $all->viewData('stats');
        $this->assertSame(2, (int) $allStats['total_transactions']);
        $this->assertEquals(500000, (float) $allStats['total_collected']);
    }

    public function test_unknown_filter_values_fall_back_instead_of_redirecting(): void
    {
        $this->makeBooking($this->makePet('StaleLinkPet'));

        // Bookmark lama / link yang dimanipulasi tidak boleh melempar 302.
        $response = $this->actingAs($this->admin)
            ->get('/admin/payments?status=unknown&type=refund&sort=id&direction=sideways');

        $response->assertOk();
        $this->assertSame(1, $response->viewData('payments')->total());
    }

    public function test_dashboard_date_range_applies_to_cards_and_recent_lists(): void
    {
        $old = $this->makeBooking($this->makePet('OldRangePet'), [
            'payment_status' => 'partial',
            'total_amount' => 1000000,
            'paid_amount' => 400000,
            'remaining_amount' => 600000,
        ]);
        $this->backdate($old, Carbon::today()->subDays(20));

        $recent = $this->makeBooking($this->makePet('RecentRangePet'), [
            'payment_status' => 'partial',
            'total_amount' => 1000000,
            'paid_amount' => 250000,
            'remaining_amount' => 750000,
        ]);

        $today = Carbon::today()->format('Y-m-d');
        $filtered = $this->actingAs($this->admin)
            ->get('/admin?start_date=' . $today . '&end_date=' . $today)
            ->assertOk();

        $this->assertSame([$recent->id], $filtered->viewData('recentBookings')->pluck('id')->all());
        $this->assertSame([$recent->id], $filtered->viewData('recentPayments')->pluck('id')->all());
        $this->assertSame([$recent->id], $filtered->viewData('outstandingBookings')->pluck('id')->all());
        $this->assertSame(1, $filtered->viewData('outstandingCount'));
        $this->assertEquals(750000, (float) $filtered->viewData('totalOutstanding'));
        $this->assertEquals(250000, (float) $filtered->viewData('totalCollected'));

        $all = $this->actingAs($this->admin)->get('/admin')->assertOk();

        $this->assertCount(2, $all->viewData('recentBookings'));
        $this->assertCount(2, $all->viewData('recentPayments'));
        $this->assertSame(2, $all->viewData('outstandingCount'));
        $this->assertEquals(1350000, (float) $all->viewData('totalOutstanding'));
    }

    public function test_date_range_dashboard_reflects_a_payment_immediately(): void
    {
        $booking = $this->makeBooking($this->makePet('FreshRangePet'), [
            'total_amount' => 500000,
            'paid_amount' => 0,
            'remaining_amount' => 500000,
        ]);

        $today = Carbon::today()->format('Y-m-d');
        $url = '/admin?start_date=' . $today . '&end_date=' . $today;

        $first = $this->actingAs($this->admin)->get($url)->assertOk();
        $this->assertEquals(0, (float) $first->viewData('totalCollected'));

        // Pembayaran tercatat di luar PaymentStatusService (mis. koreksi data),
        // jadi tidak ada cache invalidation yang bisa diandalkan.
        $booking->forceFill([
            'paid_amount' => 200000,
            'remaining_amount' => 300000,
            'payment_status' => 'partial',
        ])->save();

        $second = $this->actingAs($this->admin)->get($url)->assertOk();
        $this->assertEquals(200000, (float) $second->viewData('totalCollected'));
        $this->assertEquals(300000, (float) $second->viewData('totalOutstanding'));
    }

    public function test_medical_record_csv_streams_past_the_pdf_row_cap(): void
    {
        $booking = $this->makeBooking($this->makePet('CsvPet'));

        for ($i = 1; $i <= 205; $i++) {
            MedicalRecord::create([
                'booking_id' => $booking->id,
                'pet_id' => $booking->pet_id,
                'doctor_id' => $this->doctor->id,
                'clinic_id' => $this->clinic->id,
                'diagnosis' => 'CSVROW' . $i,
                'treatment' => 'Observasi',
                'cost' => 50000,
                'treatment_cost' => 0,
                'medicine_cost' => 0,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get(route('admin.medical-records.export.csv'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        // Satu baris header + 205 baris data. PDF tetap dibatasi 200 baris.
        $this->assertCount(206, $lines);
        $this->assertSame(205, substr_count($csv, 'CSVROW'));
        $this->assertStringContainsString('Booking ID', $lines[0]);
    }
}
