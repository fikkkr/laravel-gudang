<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_user_can_create_customer(): void
    {
        $this->get(route('customers.index'))->assertOk();
        $this->get(route('customers.create'))
            ->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee(route('customers.store'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="address"', false);

        $this->post(route('customers.store'), [
            'name' => 'Test Pelanggan',
            'phone' => '081234567890',
            'address' => 'Bandung',
        ])->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Test Pelanggan',
            'phone' => '081234567890',
            'address' => 'Bandung',
        ]);
    }

    public function test_create_customer_validation_fails_when_name_missing(): void
    {
        $this->post(route('customers.store'), [
            'phone' => '081234567890',
            'address' => 'Bandung',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_admin_can_edit_and_delete_a_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Toko Lama',
            'phone' => '0811111111',
            'address' => 'Bandung',
        ]);

        $this->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee(route('customers.update', $customer->id), false);

        $this->put(route('customers.update', $customer), [
            'name' => 'Toko Baru',
            'phone' => '0822222222',
            'address' => 'Bekasi',
        ])->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Toko Baru',
            'phone' => '0822222222',
            'address' => 'Bekasi',
        ]);

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
