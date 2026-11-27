<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.3 — indeks performa dan pagination daftar rekam medis pemilik.
 */
class P43PerformancePaginationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_medical_record_performance_indexes_exist(): void
    {
        $indexes = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'medical_records')
            ->pluck('INDEX_NAME')
            ->unique()
            ->all();

        foreach ([
            'idx_mr_clinic_created',
            'idx_mr_doctor_created',
            'idx_mr_clinic_status_created',
            'idx_mr_clinic_payment_created',
        ] as $index) {
            $this->assertContains($index, $indexes, 'Indeks hilang: ' . $index);
        }
    }

    public function test_owner_list_is_paginated_twenty_per_page(): void
    {
        $suffix = strtolower(Str::random(6));

        $owner = User::create([
            'name' => 'Owner P43',
            'email' => 'owner' . $suffix . '@p43.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P43Pet',
            'species' => 'Cat',
            'gender' => 'female',
        ]);

        $clinic = Clinic::create([
            'name' => 'P43 ' . $suffix,
            'slug' => 'p43-' . $suffix,
            'address' => 'Jl. P43',
            'is_active' => true,
        ]);

        $doctorUser = User::create([
            'name' => 'Dokter P43',
            'email' => 'doctor' . $suffix . '@p43.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $clinic->id,
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'name' => 'Dr P43 ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        foreach (range(1, 21) as $i) {
            MedicalRecord::create([
                'pet_id' => $pet->id,
                'doctor_id' => $doctor->id,
                'clinic_id' => $clinic->id,
                'diagnosis' => 'Diagnosis ' . $i,
                'treatment' => 'Tindakan ' . $i,
                'status' => MedicalRecord::STATUS_FINAL,
            ]);
        }

        $token = $owner->createToken('p43')->plainTextToken;

        $first = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/medical-records');

        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 20)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.total', 21);

        $this->assertCount(20, $first->json('data'));

        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', 'application/json')
            ->getJson('/api/medical-records?page=2');

        $second->assertOk()
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.total', 21);

        $this->assertCount(1, $second->json('data'));
    }
}
