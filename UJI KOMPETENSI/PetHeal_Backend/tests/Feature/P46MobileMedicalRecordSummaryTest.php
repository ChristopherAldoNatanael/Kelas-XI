<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.7 — endpoint daftar ringan untuk Android + pagination.
 */
class P46MobileMedicalRecordSummaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_summary_is_lightweight_and_paginated(): void
    {
        $suffix = strtolower(Str::random(6));

        $owner = User::create([
            'name' => 'Owner P46',
            'email' => 'owner' . $suffix . '@p46.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $pet = Pet::create([
            'user_id' => $owner->id,
            'name' => 'P46Pet',
            'species' => 'Cat',
            'photo' => 'pets/p46.jpg',
        ]);

        $clinic = Clinic::create([
            'name' => 'P46 ' . $suffix,
            'slug' => 'p46-' . $suffix,
            'address' => 'Jl. P46',
            'is_active' => true,
        ]);

        $doctorUser = User::create([
            'name' => 'Dokter P46',
            'email' => 'doctor' . $suffix . '@p46.test',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $clinic->id,
        ]);

        $doctor = Doctor::create([
            'user_id' => $doctorUser->id,
            'name' => 'Dr P46',
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

        $headers = [
            'Authorization' => 'Bearer ' . $owner->createToken('p46')->plainTextToken,
            'Accept' => 'application/json',
        ];

        $first = $this->withHeaders($headers)->getJson('/api/medical-records/summary');

        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.per_page', 20)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.total', 21);

        $this->assertCount(20, $first->json('data'));

        $record = $first->json('data.0');

        $this->assertArrayHasKey('id', $record);
        $this->assertArrayHasKey('pet', $record);
        $this->assertArrayHasKey('doctor', $record);
        $this->assertArrayNotHasKey('booking', $record);
        $this->assertArrayNotHasKey('vitals', $record);
        $this->assertArrayNotHasKey('attachments', $record);
        $this->assertArrayNotHasKey('prescription', $record);

        $second = $this->withHeaders($headers)->getJson('/api/medical-records/summary?page=2');

        $second->assertOk()->assertJsonPath('pagination.current_page', 2);
        $this->assertCount(1, $second->json('data'));

        $compressed = $this->withHeaders($headers + ['Accept-Encoding' => 'gzip'])
            ->get('/api/medical-records/summary');

        $compressed->assertOk();
        $this->assertSame('gzip', $compressed->headers->get('Content-Encoding'));

        $decoded = gzdecode($compressed->getContent());
        $this->assertNotFalse($decoded);
        $this->assertSame(20, count(json_decode($decoded, true)['data']));
    }
}
