<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Insert default clinic if not exists
        $defaultClinicId = DB::table('clinics')->where('slug', 'petheal-pusat')->value('id');

        if (!$defaultClinicId) {
            $defaultClinicId = DB::table('clinics')->insertGetId([
                'name'         => 'Klinik PetHeal Pusat',
                'slug'         => 'petheal-pusat',
                'address'      => 'Jl. Sudirman No. 123, Jakarta Pusat, DKI Jakarta 10220',
                'phone'        => '021-5551234',
                'email'        => 'pusat@petheal.com',
                'logo_path'    => null,
                'primary_color' => '#18C964',
                'description'  => 'Klinik hewan pusat PetHeal — melayani konsultasi, vaksinasi, grooming, dan operasi.',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        // 2. Backfill doctors
        DB::table('doctors')
            ->whereNull('clinic_id')
            ->update(['clinic_id' => $defaultClinicId]);

        // 3. Backfill services
        DB::table('services')
            ->whereNull('clinic_id')
            ->update(['clinic_id' => $defaultClinicId]);

        // 4. Backfill bookings
        DB::table('bookings')
            ->whereNull('clinic_id')
            ->update(['clinic_id' => $defaultClinicId]);

        // 5. Backfill users (non-super_admin get default clinic)
        DB::table('users')
            ->whereNull('clinic_id')
            ->where('role', '!=', 'super_admin')
            ->update(['clinic_id' => $defaultClinicId]);

        // 6. Backfill medical_records from their booking's clinic_id
        DB::statement('
            UPDATE medical_records mr
            INNER JOIN bookings b ON b.id = mr.booking_id
            SET mr.clinic_id = b.clinic_id
            WHERE mr.clinic_id IS NULL
        ');

        // 7. Backfill payment_events from their booking's clinic_id
        DB::statement('
            UPDATE payment_events pe
            INNER JOIN bookings b ON b.id = pe.booking_id
            SET pe.clinic_id = b.clinic_id
            WHERE pe.clinic_id IS NULL
        ');

        // 8. Backfill notifications from their user's clinic_id
        DB::statement('
            UPDATE notifications n
            INNER JOIN users u ON u.id = n.user_id
            SET n.clinic_id = u.clinic_id
            WHERE n.clinic_id IS NULL
        ');

        // 9. Make clinic_id NOT NULL on doctors, services, bookings
        //    Use raw ALTER TABLE for safety on existing data
        DB::statement('ALTER TABLE doctors MODIFY clinic_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE services MODIFY clinic_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE bookings MODIFY clinic_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        // Revert NOT NULL back to nullable
        DB::statement('ALTER TABLE doctors MODIFY clinic_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE services MODIFY clinic_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE bookings MODIFY clinic_id BIGINT UNSIGNED NULL');

        // Clear clinic_id from all tables
        DB::table('notifications')->update(['clinic_id' => null]);
        DB::table('audit_logs')->update(['clinic_id' => null]);
        DB::table('payment_events')->update(['clinic_id' => null]);
        DB::table('medical_records')->update(['clinic_id' => null]);
        DB::table('bookings')->update(['clinic_id' => null]);
        DB::table('services')->update(['clinic_id' => null]);
        DB::table('doctors')->update(['clinic_id' => null]);
        DB::table('users')->where('role', '!=', 'super_admin')->update(['clinic_id' => null]);

        // Remove default clinic
        DB::table('clinics')->where('slug', 'petheal-pusat')->delete();
    }
};