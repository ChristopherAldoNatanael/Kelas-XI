<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Super admin harus bisa menghapus SATU akun admin klinik tanpa menghapus
 * kliniknya, supaya salah approve join request bisa dibatalkan.
 *
 * Aturan yang diuji:
 *  - hanya super admin (admin klinik tidak boleh menghapus sesama admin),
 *  - klinik, dokter, dan data lain tidak tersentuh,
 *  - akun yang juga punya riwayat booking sebagai pasien TIDAK dihapus
 *    (cascade `bookings.user_id` akan menghapus riwayat klinis),
 *  - sesi dan token akun yang dihapus ikut dicabut,
 *  - jejak audit `user.delete_admin` tersimpan dengan clinic_id yang benar.
 */
class ClinicAdminDeletionTest extends TestCase
{
    use DatabaseTransactions;

    private function makeClinic(string $suffix): Clinic
    {
        return Clinic::create([
            'name' => 'Hapus Admin ' . $suffix,
            'slug' => 'hapus-admin-' . $suffix,
            'address' => 'Jl. Uji',
            'is_active' => true,
        ]);
    }

    private function makeUser(string $suffix, string $role, ?int $clinicId = null): User
    {
        return User::create([
            'name' => $role . ' ' . $suffix,
            'email' => $role . $suffix . '@hapus.test',
            'password' => Hash::make('password123'),
            'role' => $role,
            'clinic_id' => $clinicId,
            'email_verified_at' => now(),
        ]);
    }

    public function test_super_admin_can_delete_clinic_admin_and_clinic_survives(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix);
        $super = $this->makeUser('super' . $suffix, 'super_admin');
        $target = $this->makeUser('target' . $suffix, 'clinic_admin', $clinic->id);

        // Token & device token harus ikut hilang supaya akun tidak bisa dipakai lagi.
        $target->createToken('uji-hapus');

        $this->actingAs($super)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', __('admin.clinic_admin_deleted', ['name' => $target->name]));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $target->id,
            'tokenable_type' => User::class,
        ]);

        // Klinik tidak tersentuh.
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);

        // Audit menyebut akun yang hilang, bukan hanya "user.delete".
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.delete_admin',
            'model_id' => $target->id,
            'clinic_id' => $clinic->id,
            'user_id' => $super->id,
        ]);
    }

    public function test_clinic_admin_still_cannot_delete_another_admin(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix);
        $actor = $this->makeUser('aktor' . $suffix, 'clinic_admin', $clinic->id);
        $target = $this->makeUser('rekan' . $suffix, 'clinic_admin', $clinic->id);

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', __('admin.cannot_delete_admin'));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'user.delete_admin']);
    }

    public function test_super_admin_account_cannot_be_deleted(): void
    {
        $suffix = strtolower(Str::random(6));
        $super = $this->makeUser('super' . $suffix, 'super_admin');
        $other = $this->makeUser('other' . $suffix, 'super_admin');

        $this->actingAs($super)
            ->delete(route('admin.users.destroy', $other->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', __('admin.cannot_delete_super_admin'));

        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_admin_with_patient_history_is_kept(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix);
        $super = $this->makeUser('super' . $suffix, 'super_admin');
        $target = $this->makeUser('target' . $suffix, 'clinic_admin', $clinic->id);

        $pet = Pet::create([
            'user_id' => $target->id,
            'name' => 'Kucing ' . $suffix,
            'species' => 'cat',
        ]);

        $doctor = Doctor::create([
            'name' => 'Dr Hapus ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        Booking::create([
            'user_id' => $target->id,
            'pet_id' => $pet->id,
            'doctor_id' => $doctor->id,
            'clinic_id' => $clinic->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_type' => 'full',
            'total_amount' => 50000,
            'paid_amount' => 50000,
            'remaining_amount' => 0,
        ]);

        $this->actingAs($super)
            ->delete(route('admin.users.destroy', $target->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', __('admin.cannot_delete_user_with_history'));

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('bookings', ['user_id' => $target->id]);
    }

    public function test_delete_button_is_rendered_for_super_admin_only(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix);
        $super = $this->makeUser('super' . $suffix, 'super_admin');
        $clinicAdmin = $this->makeUser('aktor' . $suffix, 'clinic_admin', $clinic->id);
        $target = $this->makeUser('target' . $suffix, 'clinic_admin', $clinic->id);

        $deleteUrl = route('admin.users.destroy', $target->id);
        // Aksi hapus memakai path yang sama dengan halaman detail (beda method),
        // jadi yang diperiksa adalah markup form-nya, bukan URL mentah.
        $deleteForm = 'action="' . $deleteUrl . '"';

        // Super admin: tombol hapus muncul dan pembungkusnya memakai konfirmasi
        // khusus admin (bukan pesan hapus data biasa).
        $response = $this->actingAs($super)->get(route('admin.users.index', ['role' => 'admin']));
        $response->assertOk();
        $response->assertSee($deleteForm, false);
        $response->assertSee(__('admin.confirm_delete_admin', ['name' => $target->name]), false);

        // Admin klinik: tidak ada tombol hapus untuk rekan admin.
        $response = $this->actingAs($clinicAdmin)->get(route('admin.users.index', ['role' => 'admin']));
        $response->assertOk();
        $response->assertDontSee($deleteForm, false);
    }
}
