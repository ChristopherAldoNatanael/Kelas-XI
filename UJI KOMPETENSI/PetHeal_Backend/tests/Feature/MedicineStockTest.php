<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\MedicineStockService;
use App\Services\PrescriptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Stok obat.
 *
 * Yang dikunci: stok tidak boleh minus, setiap perubahan punya baris mutasi,
 * koreksi resep tidak memotong stok dua kali, obat klinik lain tidak bisa
 * dipakai, dan obat yang pernah dipakai resep tidak terhapus.
 */
class MedicineStockTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private Medicine $medicine;
    private Medicine $medicineB;
    private MedicalRecord $record;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Stok A ' . $suffix, 'slug' => 'stok-a-' . $suffix,
            'address' => 'Jl. A', 'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Stok B ' . $suffix, 'slug' => 'stok-b-' . $suffix,
            'address' => 'Jl. B', 'is_active' => true,
        ]);

        $mk = fn (string $tag, string $role, ?int $clinicId) => User::create([
            'name' => $tag, 'email' => $tag . $suffix . '@stok.test',
            'password' => Hash::make('password123'), 'role' => $role, 'clinic_id' => $clinicId,
        ]);

        $this->adminA = $mk('sadmin', 'clinic_admin', $this->clinicA->id);
        $owner = $mk('sowner', 'user', null);

        $doctor = Doctor::create([
            'name' => 'Dr Stok ' . $suffix, 'specialization' => 'Umum', 'is_active' => true,
            'clinic_id' => $this->clinicA->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        $service = Service::create([
            'name' => 'Layanan Stok ' . $suffix, 'price' => 100000,
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);
        $pet = Pet::create([
            'user_id' => $owner->id, 'name' => 'StokPet', 'species' => 'Cat', 'gender' => 'male',
        ]);

        $this->booking = Booking::create([
            'user_id' => $owner->id, 'pet_id' => $pet->id, 'doctor_id' => $doctor->id,
            'service_id' => $service->id, 'clinic_id' => $this->clinicA->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00', 'status' => 'pending',
            'payment_status' => 'unpaid', 'total_amount' => 100000,
            'paid_amount' => 0, 'remaining_amount' => 100000,
        ]);

        $this->record = MedicalRecord::create([
            'booking_id' => $this->booking->id, 'pet_id' => $pet->id,
            'doctor_id' => $doctor->id, 'clinic_id' => $this->clinicA->id,
            'diagnosis' => 'Infeksi', 'treatment' => 'Antibiotik',
            'extra_payment_status' => 'not_required',
        ]);

        $this->medicine = Medicine::create([
            'clinic_id' => $this->clinicA->id, 'name' => 'Amoxicillin ' . $suffix,
            'unit' => 'tablet', 'min_stock' => 5, 'price' => 2500,
        ]);

        // Nama sama di klinik lain: harus boleh, dan tidak boleh tercampur.
        $this->medicineB = Medicine::create([
            'clinic_id' => $this->clinicB->id, 'name' => 'Amoxicillin ' . $suffix,
            'unit' => 'tablet', 'min_stock' => 5, 'price' => 2500,
        ]);

        // Stok awal selalu lewat service — `stock` memang tidak mass-assignable
        // supaya angka stok tidak pernah datang dari input form.
        app(MedicineStockService::class)->setOpeningStock($this->medicine, 10);
        app(MedicineStockService::class)->setOpeningStock($this->medicineB, 99);
    }

    private function stockService(): MedicineStockService
    {
        return app(MedicineStockService::class);
    }

    private function prescriptions(): PrescriptionService
    {
        return app(PrescriptionService::class);
    }

    // ---------- Mutasi stok ----------

    public function test_stock_in_creates_a_signed_ledger_row(): void
    {
        $movement = $this->stockService()->stockIn($this->medicine, 15, 'Pembelian supplier');

        $this->assertSame(15.0, (float) $movement->quantity);
        $this->assertSame(25.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(25.0, (float) $movement->stock_after);
        // Dua baris: stok awal (fixture) + stok masuk yang baru dicatat.
        $this->assertSame(2, $this->medicine->movements()->count());
    }

    public function test_stock_cannot_go_below_zero(): void
    {
        $this->expectException(RuntimeException::class);

        // 10 stok, minta keluar 50.
        $this->stockService()->record($this->medicine, -50, 'out', 'uji');
    }

    public function test_adjustment_records_the_difference_not_the_target(): void
    {
        // 10 → 7 harus dicatat -3, bukan +7.
        $movement = $this->stockService()->adjustTo($this->medicine, 7, 'Stok opname');
        $target = $this->medicine->fresh();          // baca dulu, sebelum objek di-reload
        $movement = $this->medicine->movements()->latest('id')->first();
        $this->assertSame(7.0, (float) $target->stock);
        $this->assertSame(-3.0, (float) $movement->quantity);
    }

    // ---------- Integrasi resep ----------

    public function test_prescription_deducts_stock_once(): void
    {
        $total = $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $this->medicine->id, 'quantity' => 4, 'dose' => '2x1'],
        ]);

        $this->assertSame(10000.0, $total);              // 4 × 2500
        $this->assertSame(6.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(1, $this->record->prescriptionItems()->count());
        $this->assertSame(1, $this->medicine->movements()->where('type', 'out')->count());
    }

    public function test_editing_the_prescription_does_not_double_deduct(): void
    {
        // Simpan 4, lalu koreksi menjadi 6: stok akhir harus 10 - 6 = 4.
        $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $this->medicine->id, 'quantity' => 4],
        ]);
        $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $this->medicine->id, 'quantity' => 6],
        ]);

        $this->assertSame(4.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(1, $this->record->prescriptionItems()->count());
    }

    /**
     * Regresi: pengembalian stok dulu menjumlahkan SEMUA baris `out`, bukan
     * net `out + return`. Edit kedua mengembalikan 2× qty, edit ketiga 3×,
     * sehingga stok membengkak tanpa disadari.
     */
    public function test_repeated_prescription_edits_keep_stock_in_sync(): void
    {
        foreach ([4, 6, 3, 8, 2] as $quantity) {
            $this->prescriptions()->syncForRecord($this->record, [
                ['medicine_id' => $this->medicine->id, 'quantity' => $quantity],
            ]);

            // Setelah SETIAP simpan, saldo harus 10 - qty terakhir.
            $this->assertSame(
                10.0 - $quantity,
                (float) $this->medicine->fresh()->stock,
                "Stok salah setelah menyimpan qty {$quantity}"
            );
        }
    }

    /**
     * Regresi: `syncForRecord()` dulu dipanggil sebelum record disimpan,
     * sehingga item resep ditulis dengan `medical_record_id = null` dan
     * form create + resep berakhir 500.
     */
    public function test_admin_create_with_prescription_saves_record_and_deducts_stock(): void
    {
        // Booking di fixture sudah punya record; create butuh booking kosong.
        $this->record->delete();

        $this->actingAs($this->adminA)
            ->post('/admin/medical-records', [
                'booking_id' => $this->booking->id,
                'diagnosis' => 'Infeksi',
                'treatment' => 'Antibiotik',
                'prescription' => [
                    ['medicine_id' => $this->medicine->id, 'quantity' => 4],
                ],
            ])
            ->assertRedirect(route('admin.bookings.index'));

        $record = MedicalRecord::where('booking_id', $this->booking->id)->firstOrFail();

        $this->assertSame(1, $record->prescriptionItems()->count());
        $this->assertSame(6.0, (float) $this->medicine->fresh()->stock);
        $this->assertSame(10000.0, (float) $record->medicine_cost);
    }

    public function test_medicine_from_another_clinic_is_ignored(): void
    {
        // Obat klinik B tidak boleh dipakai untuk rekam medis klinik A.
        $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $this->medicineB->id, 'quantity' => 5],
        ]);

        $this->assertSame(0, $this->record->prescriptionItems()->count());
        $this->assertSame(99.0, (float) $this->medicineB->fresh()->stock);
    }

    public function test_deleting_record_returns_the_consumed_stock(): void
    {
        $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $this->medicine->id, 'quantity' => 4],
        ]);

        $this->prescriptions()->restoreOnDelete($this->record);

        $this->assertSame(10.0, (float) $this->medicine->fresh()->stock);
    }

    // ---------- Isolasi klinik & guard hapus ----------

    public function test_admin_only_sees_own_clinic_medicine(): void
    {
        (new \App\Http\Middleware\AdminAuth())->handle(
            \Illuminate\Http\Request::create('/admin/medicines', 'GET'),
            fn () => null
        );

        $response = $this->actingAs($this->adminA)->get('/admin/medicines');

        $response->assertOk();
        $response->assertSee($this->medicine->name);
        // Obat klinik lain tidak boleh muncul di daftar.
        $this->assertSame(
            0,
            Medicine::where('clinic_id', $this->clinicA->id)->count() - 1
        );
    }

    public function test_medicine_used_in_prescription_is_deactivated_not_deleted(): void
    {
        $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $this->medicine->id, 'quantity' => 1],
        ]);

        $this->actingAs($this->adminA)
            ->delete('/admin/medicines/' . $this->medicine->id);

        $this->assertDatabaseHas('medicines', [
            'id' => $this->medicine->id,
            'is_active' => false,
        ]);
    }

    public function test_unused_medicine_is_deleted(): void
    {
        $spare = Medicine::create([
            'clinic_id' => $this->clinicA->id, 'name' => 'Spare ' . Str::random(5),
            'unit' => 'botol', 'min_stock' => 0, 'price' => 1000,
        ]);

        $this->actingAs($this->adminA)
            ->delete('/admin/medicines/' . $spare->id);

        $this->assertDatabaseMissing('medicines', ['id' => $spare->id]);
    }

    // ---------- Penanganan obat kedaluwarsa ----------

    /**
     * Label "kedaluwarsa" saja tidak boleh jadi akhir alur: stok harus
     * keluar lewat mutasi `disposal`, dan obat harus nonaktif.
     */
    public function test_disposal_zeroes_stock_records_ledger_and_deactivates(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Expired ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);
        $this->stockService()->setOpeningStock($expired, 12);

        $movement = $this->stockService()->dispose(
            $expired,
            null,
            Medicine::DISPOSAL_DESTROYED,
            'Kedaluwarsa, ditemukan saat opname'
        );

        $fresh = $expired->fresh();

        $this->assertSame(0.0, (float) $fresh->stock);
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->disposed_at);
        $this->assertSame(Medicine::DISPOSAL_DESTROYED, $fresh->disposal_method);
        $this->assertSame(12.0, (float) $fresh->disposed_quantity);
        $this->assertSame(-12.0, (float) $movement->quantity);
        $this->assertSame('disposal', $movement->type);
        $this->assertSame('Pemusnahan', $movement->type_label);
    }

    /** Sebagian batch rusak: hanya sebagian yang dikeluarkan. */
    public function test_partial_disposal_keeps_the_rest_of_the_stock(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Partial ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);
        $this->stockService()->setOpeningStock($expired, 10);

        $this->stockService()->dispose($expired, 4, Medicine::DISPOSAL_DESTROYED, 'Sebagian rusak');

        $this->assertSame(6.0, (float) $expired->fresh()->stock);
        $this->assertSame(4.0, (float) $expired->fresh()->disposed_quantity);
    }

    /** Musnahkan melebihi stok harus ditolak, bukan dipotong diam-diam. */
    public function test_disposal_above_stock_is_rejected(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Over ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);
        $this->stockService()->setOpeningStock($expired, 3);

        $this->expectException(RuntimeException::class);
        $this->stockService()->dispose($expired, 10, Medicine::DISPOSAL_DESTROYED, 'Salah input');
    }

    /**
     * Obat kedaluwarsa TIDAK boleh memotong stok lewat resep. Tanpa guard
     * ini, penanganan kedaluwarsa hanya kosmetik.
     */
    public function test_expired_medicine_cannot_be_prescribed(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'NoPrescribe ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);
        $this->stockService()->setOpeningStock($expired, 10);

        $this->expectException(RuntimeException::class);

        $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $expired->id, 'quantity' => 2],
        ]);

        $this->assertSame(10.0, (float) $expired->fresh()->stock);
    }

    /** Obat kedaluwarsa tidak muncul di form resep (filter server-side). */
    public function test_expired_medicine_is_hidden_from_prescription_list(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Hidden ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);

        $options = Medicine::where('clinic_id', $this->clinicA->id)
            ->where('is_active', true)
            ->notExpired()
            ->fefo()
            ->get();

        $this->assertFalse($options->contains('id', $expired->id));
        $this->assertTrue($options->contains('id', $this->medicine->id));
    }

    /** Stok masuk baru setelah dimusnahkan = batch baru, penanda direset. */
    public function test_stock_in_after_disposal_revives_the_medicine(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Revive ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);
        $this->stockService()->setOpeningStock($expired, 5);
        $this->stockService()->dispose($expired, null, Medicine::DISPOSAL_DESTROYED, 'Kedaluwarsa');

        $this->stockService()->stockIn($expired, 8, 'Batch baru', null);

        $fresh = $expired->fresh();
        $this->assertSame(8.0, (float) $fresh->stock);
        $this->assertFalse($fresh->isDisposed());
        $this->assertTrue($fresh->is_active);
    }

    /** Hapus terpilih: yang punya riwayat nonaktif, yang baru hilang. */
    public function test_bulk_delete_deactivates_used_and_deletes_unused(): void
    {
        $spare = Medicine::create([
            'clinic_id' => $this->clinicA->id, 'name' => 'BulkSpare ' . Str::random(5),
            'unit' => 'botol', 'min_stock' => 0, 'price' => 1000,
        ]);

        $this->prescriptions()->syncForRecord($this->record, [
            ['medicine_id' => $this->medicine->id, 'quantity' => 1],
        ]);

        $this->actingAs($this->adminA)
            ->post('/admin/medicines/bulk-destroy', [
                'ids' => [$this->medicine->id, $spare->id],
            ])
            ->assertRedirect(route('admin.medicines.index'));

        $this->assertDatabaseHas('medicines', ['id' => $this->medicine->id, 'is_active' => false]);
        $this->assertDatabaseMissing('medicines', ['id' => $spare->id]);
    }

    /** Bulk delete tanpa pilihan harus ditolak dengan pesan, bukan 500. */
    public function test_bulk_delete_requires_at_least_one_id(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/medicines/bulk-destroy', ['ids' => []])
            ->assertSessionHasErrors('ids');
    }

    /** Bulk delete tidak boleh menyentuh obat klinik lain. */
    public function test_bulk_delete_is_clinic_scoped(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/medicines/bulk-destroy', [
                'ids' => [$this->medicineB->id],
            ])
            ->assertRedirect(route('admin.medicines.index'));

        $this->assertDatabaseHas('medicines', ['id' => $this->medicineB->id]);
    }

    /** "Hapus semua" wajib konfirmasi ketik HAPUS. */
    public function test_delete_all_requires_confirmation_word(): void
    {
        $this->actingAs($this->adminA)
            ->delete('/admin/medicines/destroy-all', ['confirmation' => 'ya'])
            ->assertRedirect(route('admin.medicines.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('medicines', ['id' => $this->medicine->id]);
    }

    /** "Hapus semua" dengan konfirmasi benar menghapus/nonaktifkan seluruh klinik. */
    public function test_delete_all_handles_every_medicine_in_the_clinic(): void
    {
        $spare = Medicine::create([
            'clinic_id' => $this->clinicA->id, 'name' => 'AllSpare ' . Str::random(5),
            'unit' => 'botol', 'min_stock' => 0, 'price' => 1000,
        ]);

        $this->actingAs($this->adminA)
            ->delete('/admin/medicines/destroy-all', ['confirmation' => 'HAPUS'])
            ->assertRedirect(route('admin.medicines.index'));

        // Punya riwayat stok (stok awal) → nonaktif, bukan dihapus.
        $this->assertDatabaseHas('medicines', ['id' => $this->medicine->id, 'is_active' => false]);
        // Tanpa riwayat → benar-benar hilang.
        $this->assertDatabaseMissing('medicines', ['id' => $spare->id]);
        // Klinik lain tidak tersentuh.
        $this->assertDatabaseHas('medicines', ['id' => $this->medicineB->id]);
    }

    /**
     * Berita acara lama sudah menyatu ke riwayat mutasi: tautan lama
     * diarahkan ke daftar mutasi berfilter retur/pemusnahan, dan catatan
     * pemusnahan lama tetap bisa ditemukan di sana.
     */
    public function test_legacy_disposal_report_redirects_to_unified_mutation_history(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Reported ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);
        $this->stockService()->setOpeningStock($expired, 6);
        $this->stockService()->dispose($expired, null, Medicine::DISPOSAL_RETURNED, 'Retur ke distributor');

        $this->actingAs($this->adminA)
            ->get('/admin/medicines/disposals')
            ->assertRedirect(route('admin.medicines.mutations.index'));

        $this->actingAs($this->adminA)
            ->get('/admin/medicines/mutations')
            ->assertOk()
            ->assertSee($expired->name)
            ->assertSee('Retur supplier');
    }

    /** Filter "kedaluwarsa" di daftar hanya menampilkan obat yang sudah lewat. */
    public function test_expired_filter_only_lists_past_expiry_medicines(): void
    {
        $expired = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Filtered ' . Str::random(5),
            'unit' => 'tablet', 'min_stock' => 0, 'price' => 1000,
            'expiry_date' => Carbon::yesterday()->format('Y-m-d'),
        ]);

        $this->actingAs($this->adminA)
            ->get('/admin/medicines?expired=1')
            ->assertOk()
            ->assertSee($expired->name)
            ->assertDontSee($this->medicine->name);
    }
}
