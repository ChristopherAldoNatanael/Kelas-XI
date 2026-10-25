<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kontrak `remove_photo` pada PUT /api/pets/{id}:
 * - field baru adalah aditif; klien lama (tanpa field) tidak berubah perilaku;
 * - file lama hanya dihapus dari disk public (path relatif), foto http eksternal
 *   sekadar dilepas referensinya;
 * - file upload baru tetap menang atas remove_photo.
 */
class PetPhotoRemovalTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinic;
    private User $owner;
    private Pet $pet;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'PP ' . $this->suffix,
            'slug' => 'pp-' . $this->suffix,
            'address' => 'Jl. PP',
            'is_active' => true,
        ]);
        $this->owner = User::create([
            'name' => 'ppowner',
            'email' => 'pp' . $this->suffix . '@pp.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
            'clinic_id' => $this->clinic->id,
        ]);
        $this->pet = Pet::create([
            'user_id' => $this->owner->id,
            'name' => 'PP Pet',
            'species' => 'Dog',
            'gender' => 'male',
            'photo' => 'pets/original_' . $this->suffix . '.jpg',
        ]);
        Storage::disk('public')->put($this->pet->photo, 'fake-image-bytes');
    }

    private function putApi(array $payload)
    {
        // Sama seperti test API lain di repo ini: guard Sanctum perlu Bearer
        // token nyata, actingAs(...,'sanctum') tidak melewati ApiAuthenticate.
        auth()->forgetGuards();
        $token = $this->owner->createToken('pet-photo-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->putJson('/api/pets/' . $this->pet->id, $payload);
    }

    public function test_remove_photo_nulls_field_and_deletes_stored_file(): void
    {
        $response = $this->putApi(['remove_photo' => true]);
        $response->assertOk()->assertJsonPath('success', true);

        $this->assertNull($this->pet->fresh()->photo);
        Storage::disk('public')->assertMissing('pets/original_' . $this->suffix . '.jpg');
    }

    public function test_request_without_remove_photo_keeps_existing_photo(): void
    {
        $response = $this->putApi(['name' => 'Nama Baru']);
        $response->assertOk();

        $fresh = $this->pet->fresh();
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame('pets/original_' . $this->suffix . '.jpg', $fresh->photo);
        Storage::disk('public')->assertExists('pets/original_' . $this->suffix . '.jpg');
    }

    public function test_remove_photo_false_is_accepted_and_keeps_photo(): void
    {
        $this->putApi(['remove_photo' => false])
            ->assertOk();

        $this->assertNotNull($this->pet->fresh()->photo);
        Storage::disk('public')->assertExists('pets/original_' . $this->suffix . '.jpg');
    }

    public function test_new_upload_takes_precedence_over_remove_photo(): void
    {
        auth()->forgetGuards();
        $token = $this->owner->createToken('pet-photo-test')->plainTextToken;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->put(
                '/api/pets/' . $this->pet->id,
                [
                    'name' => 'PP Pet',
                    'species' => 'Dog',
                    'remove_photo' => true,
                    'photo' => UploadedFile::fake()->image('new.jpg'),
                ]
            );

        $response->assertOk();
        $fresh = $this->pet->fresh();
        $this->assertNotNull($fresh->photo);
        $this->assertNotSame('pets/original_' . $this->suffix . '.jpg', $fresh->photo);
        // File lama dibersihkan saat diganti.
        Storage::disk('public')->assertMissing('pets/original_' . $this->suffix . '.jpg');
    }
}
