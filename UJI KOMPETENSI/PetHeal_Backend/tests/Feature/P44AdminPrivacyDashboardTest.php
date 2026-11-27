<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.4 — dashboard admin permintaan PDP (web).
 */
class P44AdminPrivacyDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'P44 ' . $suffix,
            'slug' => 'p44-' . $suffix,
            'address' => 'Jl. P44',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin P44',
            'email' => 'admin' . $suffix . '@p44.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->owner = User::create([
            'name' => 'Owner P44',
            'email' => 'owner' . $suffix . '@p44.test',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);
    }

    private function request(string $type = 'access', string $status = 'received'): PrivacyRequest
    {
        return PrivacyRequest::create([
            'user_id' => $this->owner->id,
            'clinic_id' => $this->clinic->id,
            'request_type' => $type,
            'status' => $status,
            'description' => 'Mohon salinan rekam medis hewan saya.',
            'requested_at' => now()->subDay(),
            'due_at' => now()->addDays(13),
        ]);
    }

    public function test_admin_sees_and_handles_privacy_request(): void
    {
        $privacyRequest = $this->request();

        $this->actingAs($this->admin)
            ->get(route('admin.privacy-requests.index'))
            ->assertOk()
            ->assertSee('Permintaan PDP')
            ->assertSee($this->owner->name);

        $this->actingAs($this->admin)
            ->get(route('admin.privacy-requests.show', $privacyRequest->id))
            ->assertOk()
            ->assertSee('Mohon salinan rekam medis hewan saya.');

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.verify', $privacyRequest->id))
            ->assertRedirect(route('admin.privacy-requests.show', $privacyRequest->id));

        $this->assertSame('verified', $privacyRequest->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.start', $privacyRequest->id))
            ->assertRedirect(route('admin.privacy-requests.show', $privacyRequest->id));

        $this->assertSame('in_progress', $privacyRequest->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.complete', $privacyRequest->id), [
                'response_path' => 'exports/pdp/p44.pdf',
            ])
            ->assertRedirect(route('admin.privacy-requests.show', $privacyRequest->id));

        $privacyRequest->refresh();
        $this->assertSame('completed', $privacyRequest->status);
        $this->assertSame('exports/pdp/p44.pdf', $privacyRequest->response_path);
        $this->assertSame($this->admin->id, $privacyRequest->handled_by);

        $this->assertTrue(
            AuditLog::where('action', 'privacy_request.completed')
                ->where('model_id', $privacyRequest->id)
                ->where('model_type', PrivacyRequest::class)
                ->exists()
        );
    }

    public function test_reject_requires_reason_and_records_it(): void
    {
        $privacyRequest = $this->request('erasure', 'verified');

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.reject', $privacyRequest->id), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('verified', $privacyRequest->fresh()->status);

        $this->actingAs($this->admin)
            ->post(route('admin.privacy-requests.reject', $privacyRequest->id), [
                'rejection_reason' => 'Rekam medis masih dalam masa retensi.',
            ])
            ->assertRedirect(route('admin.privacy-requests.show', $privacyRequest->id));

        $privacyRequest->refresh();
        $this->assertSame('rejected', $privacyRequest->status);
        $this->assertSame('Rekam medis masih dalam masa retensi.', $privacyRequest->rejection_reason);
    }

    public function test_admin_cannot_open_another_clinics_request(): void
    {
        $otherClinic = Clinic::create([
            'name' => 'P44 Other',
            'slug' => 'p44-other-' . strtolower(Str::random(6)),
            'address' => 'Jl. Lain',
            'is_active' => true,
        ]);

        $foreign = PrivacyRequest::create([
            'user_id' => $this->owner->id,
            'clinic_id' => $otherClinic->id,
            'request_type' => 'access',
            'status' => 'received',
            'description' => 'Permintaan klinik lain.',
            'requested_at' => now(),
            'due_at' => now()->addDays(14),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.privacy-requests.show', $foreign->id))
            ->assertNotFound();
    }
}
