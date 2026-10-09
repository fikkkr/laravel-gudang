<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_routes_are_limited_to_admins(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($operator)
            ->get(route('categories.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('categories.index'))
            ->assertOk();
    }

    public function test_operator_can_access_inbound_creation_route(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $this->actingAs($operator)
            ->get(route('inbounds.create'))
            ->assertOk();
    }

    public function test_database_seeder_creates_role_users_and_master_data_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('warehouses', 2);
        $this->assertDatabaseCount('customers', 2);
        $this->assertDatabaseCount('products', 3);

        $admin = User::where('email', 'admin@sinar.com')->firstOrFail();
        $operator = User::where('email', 'operator@sinar.com')->firstOrFail();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->is_active);
        $this->assertSame('operator', $operator->role);
        $this->assertTrue($operator->is_active);
        $this->assertTrue(Hash::check('admin123', $admin->password));
        $this->assertTrue(Hash::check('operator123', $operator->password));
    }
}
