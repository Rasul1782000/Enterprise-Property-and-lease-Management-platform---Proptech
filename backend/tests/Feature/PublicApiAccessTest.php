<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_management_endpoints_are_public(): void
    {
        $this->getJson('/api/properties')->assertOk();
        $this->getJson('/api/dashboard/stats')->assertOk();
    }

    public function test_local_dashboard_preview_origins_can_call_the_api(): void
    {
        $this->getJson('/api/properties', [
            'Origin' => 'http://localhost:55508',
        ])->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:55508');
    }

    public function test_management_changes_are_allowed_without_a_login(): void
    {
        $this->postJson('/api/properties', [
            'name' => 'Public API Test',
            'code' => 'PUBLIC-001',
            'type' => 'commercial',
            'address' => '1 Test Street',
            'city' => 'Test City',
        ])->assertCreated();
    }

    public function test_authentication_endpoints_are_not_registered(): void
    {
        $this->postJson('/api/auth/login')->assertNotFound();
    }
}
