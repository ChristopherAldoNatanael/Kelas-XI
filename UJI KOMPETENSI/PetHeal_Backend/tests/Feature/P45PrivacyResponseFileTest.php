<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\PrivacyResponseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.6 — berkas jawaban PDP: unggah, generate, unduh admin/pemilik, audit.
 */
class P45PrivacyResponseFileTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P45 ' . $suffix,
            'slug' => 'p45-' . $suffix,
            'address' => 'Jl. P45',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin P45',
            'email' => 'admin' . $suffix . '@p45.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->owner = User::create([
            'name' => 'Owner P45',
            'email' => 'owner' . $suffix . '@p45.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);
    }

    private function makeRequest(string $type = 'access'): PrivacyRequest
    {
        return PrivacyRequest::create([
            'user_id' => $this->owner->id,
            'clinic_id' => $this->clinic->id,
            'request_type' => $type,
            'status' => PrivacyRequest::STATUS_VERIFIED,
            'description' => 'Mohon salinan data saya.',
            'requested_at' => now()->subDay(),
            'due_at' => now()->addDays(13),
        ]);
    }

    private function apiAs(User $user): array
    {
        auth()->forgetGuards();

        return [
            'Authorization' => 'Bearer ' . $user->createToken('p45')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_admin_upload_and_owner_download_response_file(): void
    {
        $privacyRequest = $this->makeRequest();
        $pdf = "%PDF-1.4\nSalinan data pribadi P45\n%%EOF";

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.response.attach', $privacyRequest->id), [
                'response_file' => UploadedFile::fake()->createWithContent('salinan-p45.pdf', $pdf),
            ])
            ->assertRedirect(route('admin.privacy-requests.show', $privacyRequest->id))
            ->assertSessionHas('success');

        $privacyRequest->refresh();

        $this->assertNotNull($privacyRequest->response_file_path);
        $this->assertTrue($privacyRequest->response_file_encrypted);
        $this->assertSame(hash('sha256', $pdf), $privacyRequest->response_file_sha256);
        $this->assertSame('salinan-p45.pdf', $privacyRequest->response_file_name);

        $stored = (string) Storage::disk('local')->get($privacyRequest->response_file_path);
        $this->assertStringNotContainsString('Salinan data pribadi', $stored);

        $this->assertTrue(
            AuditLog::where('action', 'privacy_request.response_attached')
                ->where('model_id', $privacyRequest->id)
                ->exists()
        );

        $headers = $this->apiAs($this->owner);

        $this->withHeaders($headers)
            ->getJson('/api/privacy-requests')
            ->assertOk()
            ->assertJsonPath('data.0.response_available', true)
            ->assertJsonPath('data.0.response_file_name', 'salinan-p45.pdf');

        $download = $this->withHeaders($headers)
            ->get('/api/privacy-requests/' . $privacyRequest->id . '/response');

        $download->assertOk();
        $this->assertSame($pdf, $download->getContent());
        $this->assertSame(1, $privacyRequest->fresh()->response_download_count);

        $this->assertTrue(
            AuditLog::where('action', 'privacy_request.response_downloaded')
                ->where('model_id', $privacyRequest->id)
                ->where('user_id', $this->owner->id)
                ->exists()
        );
    }

    public function test_admin_can_generate_pdf_from_ui(): void
    {
        $privacyRequest = $this->makeRequest();

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.response.generate', $privacyRequest->id))
            ->assertRedirect(route('admin.privacy-requests.show', $privacyRequest->id))
            ->assertSessionHas('success');

        $privacyRequest->refresh();

        $this->assertNotNull($privacyRequest->response_file_path);
        $this->assertTrue($privacyRequest->response_file_encrypted);
        $this->assertStringEndsWith('.pdf', $privacyRequest->response_file_name);

        $bytes = app(PrivacyResponseService::class)->read($privacyRequest);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertTrue(
            AuditLog::where('action', 'privacy_request.response_generated')
                ->where('model_id', $privacyRequest->id)
                ->exists()
        );
    }

    public function test_owner_cannot_download_missing_or_foreign_response(): void
    {
        $privacyRequest = $this->makeRequest();

        $headers = $this->apiAs($this->owner);

        $this->withHeaders($headers)
            ->getJson('/api/privacy-requests/' . $privacyRequest->id . '/response')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_response_cannot_change_after_completed(): void
    {
        $privacyRequest = $this->makeRequest();
        $privacyRequest->forceFill([
            'status' => PrivacyRequest::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.response.attach', $privacyRequest->id), [
                'response_file' => UploadedFile::fake()->createWithContent('locked.pdf', "%PDF-1.4 locked"),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNull($privacyRequest->fresh()->response_file_path);
    }
}
