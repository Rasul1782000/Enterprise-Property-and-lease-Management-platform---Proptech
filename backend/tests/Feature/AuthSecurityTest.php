<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_routes_reject_requests_without_a_valid_sanctum_token(): void
    {
        $this->getJson('/api/properties')->assertUnauthorized();
        $this->withToken('hardcoded-admin-token-forged')
            ->getJson('/api/properties')
            ->assertUnauthorized();
    }

    public function test_tenant_cannot_access_staff_management_routes(): void
    {
        $tenant = User::create([
            'name' => 'Test Tenant',
            'email' => 'tenant@example.test',
            'password' => 'secret-password',
            'role' => 'tenant',
        ]);

        Sanctum::actingAs($tenant);

        $this->getJson('/api/properties')->assertForbidden();
    }

    public function test_public_registration_cannot_assign_a_privileged_role(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Unprivileged User',
            'email' => 'new-user@example.test',
            'password' => 'secure-password-123',
            'password_confirmation' => 'secure-password-123',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'new-user@example.test',
            'role' => 'tenant',
        ]);
    }

    public function test_legacy_demo_password_is_not_a_valid_login(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'admin@propertylease.test',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonMissingPath('token');
    }
}
