<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Filament\Resources\UserResource;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user(): void
    {
        $user = User::factory()->create();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_can_render_user_list_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user, 'web');

        $this->withoutMiddleware();
        $this->get('/admin/users')->assertSuccessful();
    }
}
