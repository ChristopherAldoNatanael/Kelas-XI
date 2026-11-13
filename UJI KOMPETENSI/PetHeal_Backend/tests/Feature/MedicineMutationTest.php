<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineStockTransaction;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\MedicineMutationService;
use App\Services\MedicineStockService;
use App\Services\PrescriptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Mutasi stok berbasis batch & transaksi berstatus.
 *
 * Yang dikunci di sini: monitoring per batch, larangan dispensing batch ED,
 * perpindahan available↔quarantine tanpa double deduction, pembatalan yang
 * tidak merusak saldo, audit trail, upload dokumen, dan isolasi klinik.
 */
class MedicineMutationTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $adminB;
    private MedicalRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Mutasi A ' . $suffix, 'slug' => 'mutasi-a-' . $suffix,
            'address' => 'Jl. A', 'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Mutasi B ' . $suffix, 'slug' => 'mutasi-b-' . $suffix,
            'address' => 'Jl. B', 'is_active' => true,
        ]);

        $makeUser = fn (string $tag, string $role, ?int $clinicId) => User::create([
            'name' => $tag . $suffix,
            'email' => $tag . $suffix . '@mutasi.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'clinic_id' => $clinicId,
        ]);

        $this->adminA = $makeUser('admina', 'clinic_admin', $this->clinicA->id);
        $this->adminB = $makeUser('adminb', 'clinic_admin', $this->clinicB->id);
        $owner = $makeUser('owner', 'user', null);

        $doctor = Doctor::create([
            'name' => 'Dr Mutasi ' . $suffix, 'specialization' => 'Umum', 'is_active' => true,
            'clinic_id' => $this->clinicA->id, 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        ]);
        $service = Service::create([
            'name' => 'Layanan Mutasi ' . $suffix, 'price' => 100000,
            'is_active' => true, 'clinic_id' => $this->clinicA->id,
        ]);
        $pet = Pet::create([
            'user_id' => $owner->id, 'name' => 'MutasiPet', 'species' => 'Cat', 'gender' => 'male',
        ]);
        $booking = Booking::create([
            'user_id' => $owner->id, 'pet_id' => $pet->id, 'doctor_id' => $doctor->id,
            'service_id' => $service->id, 'clinic_id' => $this->clinicA->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00:00', 'status' => 'pending',
            'payment_status' => 'unpaid', 'total_amount' => 100000,
            'paid_amount' => 0, 'remaining_amount' => 100000,
        ]);
        $this->record = MedicalRecord::create([
            'booking_id' => $booking->id, 'pet_id' => $pet->id,
            'doctor_id' => $doctor->id, 'clinic_id' => $this->clinicA->id,
            'diagnosis' => 'Infeksi', 'treatment' => 'Antibiotik',
            'extra_payment_status' => 'not_required',
        ]);
    }

    private function mutationService(): MedicineMutationService
    {
        return app(MedicineMutationService::class);
    }

    private function stockService(): MedicineStockService
    {
        return app(MedicineStockService::class);
    }

    /**
     * @return array{0: Medicine, 1: MedicineBatch}
     */
    private function makeMedicine(
        Clinic $clinic,
        string $name,
        float $quantity,
        ?string $expiry,
        string $batchNumber = 'BATCH-1'
    ): array {
        $medicine = Medicine::create([
            'clinic_id' => $clinic->id,
            'name' => $name . ' ' . Str::random(5),
            'unit' => 'tablet',
            'min_stock' => 2,
            'price' => 2500,
        ]);

        $this->stockService()->stockInBatch(
            $medicine,
            $quantity,
            $batchNumber,
            $expiry,
            'Pembelian supplier'
        );

        return [$medicine->fresh(), $medicine->batches()->firstOrFail()];
    }

    private function quarantine(
        Medicine $medicine,
        MedicineBatch $batch,
        float $quantity,
        int $clinicId
    ): MedicineStockTransaction {
        $transaction = $this->mutationService()->create([
            'type' => MedicineStockTransaction::TYPE_QUARANTINE,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => $quantity,
            'reason' => 'Mendekati kedaluwarsa',
            'quarantine_location' => 'Lemari A',
        ], $clinicId, $this->adminA->id);

        $this->mutationService()->process($transaction, $this->adminA);

        return $this->mutationService()->complete($transaction, $this->adminA);
    }

    // ---------- 1 & 2. Deteksi batch hampir ED dan sudah ED ----------

    public function test_expiring_and_expired_batches_are_detected_per_batch(): void
    {
        $expiringDate = today()->addDays(10)->format('Y-m-d');
        $expiredDate = today()->subDay()->format('Y-m-d');

        [$expiring, $expiringBatch] = $this->makeMedicine($this->clinicA, 'Expiring', 5, $expiringDate, 'EXP-10');
        [$expired, $expiredBatch] = $this->makeMedicine($this->clinicA, 'Expired', 7, $expiredDate, 'EXP-OLD');

        $this->assertSame('expiring', $expiringBatch->expiryStatus());
        $this->assertSame('expired', $expiredBatch->expiryStatus());

        $this->assertTrue(
            MedicineBatch::where('clinic_id', $this->clinicA->id)
                ->expiringSoon(60)->pluck('id')->contains($expiringBatch->id)
        );
        $this->assertTrue(
            MedicineBatch::where('clinic_id', $this->clinicA->id)
                ->expired()->pluck('id')->contains($expiredBatch->id)
        );
        $this->assertFalse(
            MedicineBatch::where('clinic_id', $this->clinicA->id)
                ->expired()->pluck('id')->contains($expiringBatch->id)
        );

        $this->actingAs($this->adminA)
            ->get('/admin/medicines/expiry')
            ->assertOk()
            ->assertSee($expiring->name)
            ->assertSee($expired->name);

        // Filter, pencarian, dan sorting memakai query yang sama dengan tabel.
        $this->actingAs($this->adminA)
            ->get('/admin/medicines/expiry?status=expired&sort=name&q=Expired')
            ->assertOk()
            ->assertSee($expired->name)
            ->assertDontSee($expiring->name);
    }

    // ---------- 3. Batch ED tidak bisa didispensing ----------

    public function test_expired_batch_cannot_be_dispensed(): void
    {
        [$expired] = $this->makeMedicine(
            $this->clinicA,
            'ExpiredDispense',
            10,
            today()->subDay()->format('Y-m-d'),
            'ED-1'
        );

        $this->expectException(RuntimeException::class);

        app(PrescriptionService::class)->syncForRecord($this->record, [
            ['medicine_id' => $expired->id, 'quantity' => 2],
        ]);

        $this->assertSame(10.0, (float) $expired->fresh()->stock);
    }

    // ---------- 4. Karantina: available → quarantine ----------

    public function test_quarantine_moves_stock_without_changing_physical_total(): void
    {
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Karantina', 10, null, 'K-1');

        $transaction = $this->quarantine($medicine, $batch, 4, $this->clinicA->id);

        $medicine->refresh();
        $batch->refresh();

        $this->assertSame(6.0, (float) $medicine->stock);
        $this->assertSame(4.0, (float) $medicine->quarantined_stock);
        $this->assertSame(10.0, $medicine->physicalStock());
        $this->assertSame(6.0, (float) $batch->stock);
        $this->assertSame(4.0, (float) $batch->quarantined_stock);

        $this->assertSame(MedicineStockTransaction::STATUS_COMPLETED, $transaction->status);
        $this->assertSame(2, $transaction->movements()->count());
        $this->assertSame(1, $transaction->movements()->where('stock_state', 'available')->count());
        $this->assertSame(1, $transaction->movements()->where('stock_state', 'quarantine')->count());
        $this->assertTrue($transaction->needsFollowUp());
    }

    // ---------- 5. Retur langsung mengurangi available satu kali ----------

    public function test_direct_supplier_return_reduces_available_stock_exactly_once(): void
    {
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Retur', 10, null, 'R-1');

        $transaction = $this->mutationService()->create([
            'type' => MedicineStockTransaction::TYPE_RETURN_SUPPLIER,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => 3,
            'reason' => 'Kelebihan stok',
            'supplier_name' => 'PT Sehat',
            'return_date' => today()->format('Y-m-d'),
        ], $this->clinicA->id, $this->adminA->id);

        $this->mutationService()->process($transaction, $this->adminA);
        $this->mutationService()->complete($transaction, $this->adminA);

        $this->assertSame(7.0, (float) $medicine->fresh()->stock);
        $this->assertSame(0.0, (float) $medicine->fresh()->quarantined_stock);
        $this->assertSame(-3.0, (float) $transaction->movements()->where('stock_state', 'available')->sum('quantity'));

        // Klik selesai kedua kali tidak boleh memotong lagi.
        $this->expectException(RuntimeException::class);
        try {
            $this->mutationService()->complete($transaction, $this->adminA);
        } finally {
            $this->assertSame(7.0, (float) $medicine->fresh()->stock);
        }
    }

    // ---------- 6. Pemusnahan mengurangi karantina satu kali ----------

    public function test_disposal_reduces_quarantine_stock_exactly_once(): void
    {
        [$medicine, $batch] = $this->makeMedicine(
            $this->clinicA,
            'Musnah',
            10,
            today()->subDay()->format('Y-m-d'),
            'D-1'
        );

        $quarantine = $this->quarantine($medicine, $batch, 4, $this->clinicA->id);

        // Halaman tindak lanjut dari karantina menampilkan sumbernya.
        $this->actingAs($this->adminA)
            ->get('/admin/medicines/mutations/create?source_transaction_id=' . $quarantine->id . '&type=disposal')
            ->assertOk()
            ->assertSee(__('inventory.source_quarantine', ['reference' => $quarantine->reference]));

        $disposal = $this->mutationService()->create([
            'type' => MedicineStockTransaction::TYPE_DISPOSAL,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => 4,
            'reason' => 'Kedaluwarsa',
            'destruction_date' => today()->format('Y-m-d'),
            'destruction_method' => 'incineration',
            'witness_name' => 'Saksi Klinik',
            'source_transaction_id' => $quarantine->id,
        ], $this->clinicA->id, $this->adminA->id);

        $this->mutationService()->process($disposal, $this->adminA);
        $this->mutationService()->complete($disposal, $this->adminA);

        $medicine->refresh();
        $this->assertSame(6.0, (float) $medicine->stock);
        $this->assertSame(0.0, (float) $medicine->quarantined_stock);
        $this->assertSame(6.0, (float) $medicine->physicalStock());
        $this->assertFalse($quarantine->fresh()->needsFollowUp());

        $this->expectException(RuntimeException::class);
        try {
            $this->mutationService()->complete($disposal, $this->adminA);
        } finally {
            $this->assertSame(0.0, (float) $medicine->fresh()->quarantined_stock);
        }
    }

    // ---------- 7, 8, 15. Jumlah melebihi stok & eksekusi bersamaan ----------

    public function test_quantity_cannot_exceed_available_stock_or_go_negative(): void
    {
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Batas', 10, null, 'L-1');

        try {
            $this->mutationService()->create([
                'type' => MedicineStockTransaction::TYPE_QUARANTINE,
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'quantity' => 11,
                'reason' => 'Terlalu banyak',
                'quarantine_location' => 'A',
            ], $this->clinicA->id, $this->adminA->id);
            $this->fail('Mutasi melebihi stok seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('melebihi', $e->getMessage());
        }

        // Dua transaksi bersamaan: keduanya lolos validasi awal (6 + 6 > 10),
        // tetapi hanya satu yang boleh mengubah saldo.
        $first = $this->mutationService()->create([
            'type' => MedicineStockTransaction::TYPE_QUARANTINE,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => 6,
            'reason' => 'Persiapan karantina A',
            'quarantine_location' => 'A',
        ], $this->clinicA->id, $this->adminA->id);

        $second = $this->mutationService()->create([
            'type' => MedicineStockTransaction::TYPE_QUARANTINE,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => 6,
            'reason' => 'Persiapan karantina B',
            'quarantine_location' => 'B',
        ], $this->clinicA->id, $this->adminA->id);

        $this->mutationService()->process($first, $this->adminA);
        $this->mutationService()->complete($first, $this->adminA);

        $this->mutationService()->process($second, $this->adminA);

        try {
            $this->mutationService()->complete($second, $this->adminA);
            $this->fail('Transaksi kedua seharusnya ditolak karena stok tidak cukup.');
        } catch (RuntimeException $e) {
            $this->assertSame(4.0, (float) $medicine->fresh()->stock);
            $this->assertSame(6.0, (float) $medicine->fresh()->quarantined_stock);
            $this->assertSame(MedicineStockTransaction::STATUS_PROCESSING, $second->fresh()->status);
        }

        $this->assertGreaterThanOrEqual(0, (float) $medicine->fresh()->stock);
    }

    // ---------- 9, 10, 11. Batal tanpa mengubah saldo; riwayat tetap ada ----------

    public function test_cancelling_before_completion_keeps_balances_and_audit_trail(): void
    {
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Batal', 10, null, 'C-1');

        $transaction = $this->mutationService()->create([
            'type' => MedicineStockTransaction::TYPE_QUARANTINE,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => 5,
            'reason' => 'Akan dikarantina',
            'quarantine_location' => 'A',
        ], $this->clinicA->id, $this->adminA->id);

        $this->mutationService()->process($transaction, $this->adminA);
        $cancelled = $this->mutationService()->cancel($transaction, $this->adminA, 'Salah pilih batch');

        $medicine->refresh();

        $this->assertSame(MedicineStockTransaction::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('Salah pilih batch', $cancelled->cancel_reason);
        $this->assertSame($this->adminA->id, $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(10.0, (float) $medicine->stock);
        $this->assertSame(0.0, (float) $medicine->quarantined_stock);
        $this->assertSame(0, $transaction->movements()->count());

        // Riwayat tidak dihapus.
        $this->assertDatabaseHas('medicine_stock_transactions', [
            'id' => $transaction->id,
            'status' => MedicineStockTransaction::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('medicine_stock_movements', [
            'medicine_id' => $medicine->id,
            'type' => 'in',
        ]);
    }

    public function test_completed_transaction_cannot_be_cancelled_but_can_be_reversed(): void
    {
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Balik', 10, null, 'V-1');
        $quarantine = $this->quarantine($medicine, $batch, 4, $this->clinicA->id);

        try {
            $this->mutationService()->cancel($quarantine, $this->adminA, 'Batal');
            $this->fail('Transaksi selesai tidak boleh dibatalkan langsung.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('pembalik', $e->getMessage());
        }

        $reversal = $this->mutationService()->reverse($quarantine, $this->adminA, 'Karantina tidak jadi');

        $medicine->refresh();
        $this->assertSame(10.0, (float) $medicine->stock);
        $this->assertSame(0.0, (float) $medicine->quarantined_stock);
        $this->assertSame(MedicineStockTransaction::STATUS_COMPLETED, $reversal->status);
        $this->assertSame($quarantine->id, $reversal->source_transaction_id);
        $this->assertTrue($quarantine->fresh()->isReversed());

        // Pembalik tidak boleh dibuat dua kali.
        $this->expectException(RuntimeException::class);
        $this->mutationService()->reverse($quarantine, $this->adminA, 'Lagi');
    }

    // ---------- 12. Isolasi klinik ----------

    public function test_clinic_cannot_access_another_clinics_mutation(): void
    {
        [$medicineB, $batchB] = $this->makeMedicine($this->clinicB, 'KlinikB', 10, null, 'B-1');
        $transactionB = $this->quarantine($medicineB, $batchB, 2, $this->clinicB->id);

        $this->actingAs($this->adminA)
            ->get('/admin/medicines/mutations/' . $transactionB->id)
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->post('/admin/medicines/mutations/' . $transactionB->id . '/process')
            ->assertNotFound();

        $this->assertSame(MedicineStockTransaction::STATUS_COMPLETED, $transactionB->fresh()->status);
        $this->assertSame(8.0, (float) $medicineB->fresh()->stock);
    }

    // ---------- 13. Role tidak berwenang ----------

    public function test_role_without_admin_access_cannot_process_mutation(): void
    {
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Role', 10, null, 'ROLE-1');

        $transaction = $this->mutationService()->create([
            'type' => MedicineStockTransaction::TYPE_QUARANTINE,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => 2,
            'reason' => 'Uji role',
            'quarantine_location' => 'A',
        ], $this->clinicA->id, $this->adminA->id);

        $ordinaryUser = User::create([
            'name' => 'User Biasa',
            'email' => 'biasa' . Str::random(5) . '@mutasi.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->actingAs($ordinaryUser)
            ->post('/admin/medicines/mutations/' . $transaction->id . '/process')
            ->assertRedirect(route('admin.login'));

        $this->assertSame(MedicineStockTransaction::STATUS_PENDING, $transaction->fresh()->status);
        $this->assertSame(10.0, (float) $medicine->fresh()->stock);
    }

    // ---------- 14. Upload dokumen tervalidasi ----------

    public function test_mutation_document_upload_is_validated_and_stored(): void
    {
        Storage::fake('public');
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Dokumen', 10, null, 'DOC-1');

        $base = [
            'type' => MedicineStockTransaction::TYPE_RETURN_SUPPLIER,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch->id,
            'quantity' => 2,
            'reason' => 'Retur',
            'supplier_name' => 'PT Sehat',
            'return_date' => today()->format('Y-m-d'),
        ];

        // Tipe file berbahaya harus ditolak.
        $this->actingAs($this->adminA)
            ->post('/admin/medicines/mutations', [
                ...$base,
                'document' => UploadedFile::fake()->create('bukti.exe', 20, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('document');

        // PDF valid tersimpan dan transaksi dibuat.
        $response = $this->actingAs($this->adminA)
            ->post('/admin/medicines/mutations', [
                ...$base,
                'document' => UploadedFile::fake()->create('bukti-retur.pdf', 100, 'application/pdf'),
            ]);

        $transaction = MedicineStockTransaction::where('medicine_id', $medicine->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.medicines.mutations.show', $transaction->id));
        $this->assertNotNull($transaction->document_path);
        Storage::disk('public')->assertExists($transaction->document_path);
        $this->assertSame(10.0, (float) $medicine->fresh()->stock, 'Stok belum berubah saat transaksi pending.');
    }

    // ---------- Halaman & alur lengkap ----------

    public function test_mutation_pages_and_full_workflow_are_reachable(): void
    {
        [$medicine, $batch] = $this->makeMedicine($this->clinicA, 'Halaman', 10, null, 'P-1');

        $this->actingAs($this->adminA)->get('/admin/medicines/mutations')->assertOk();
        $this->actingAs($this->adminA)
            ->get('/admin/medicines/mutations?type=quarantine&status=pending&medicine_id=' . $medicine->id)
            ->assertOk();
        $this->actingAs($this->adminA)->get('/admin/medicines/mutations/create')->assertOk();
        $this->actingAs($this->adminA)->get('/admin/medicines/expiry')->assertOk();

        $this->actingAs($this->adminA)
            ->post('/admin/medicines/mutations', [
                'type' => MedicineStockTransaction::TYPE_QUARANTINE,
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'quantity' => 3,
                'reason' => 'ED dekat',
                'quarantine_location' => 'Lemari A',
            ])
            ->assertRedirect();

        $transaction = MedicineStockTransaction::where('medicine_id', $medicine->id)->latest('id')->firstOrFail();

        $this->actingAs($this->adminA)
            ->get('/admin/medicines/mutations/' . $transaction->id)
            ->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('Menunggu Tindakan');

        $this->actingAs($this->adminA)
            ->post('/admin/medicines/mutations/' . $transaction->id . '/process')
            ->assertRedirect();

        $this->actingAs($this->adminA)
            ->post('/admin/medicines/mutations/' . $transaction->id . '/complete')
            ->assertRedirect();

        $this->assertSame(7.0, (float) $medicine->fresh()->stock);
        $this->assertSame(3.0, (float) $medicine->fresh()->quarantined_stock);
    }
}
