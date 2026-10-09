<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_sees_only_permitted_navigation_groups(): void
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('PT Sinar Nusantara')
            ->assertSee('Inventory Management')
            ->assertSee('Dashboard')
            ->assertSee('Barang Masuk')
            ->assertSee('Penjualan')
            ->assertSee('Transfer Antar-Gudang')
            ->assertSee('Riwayat Transaksi')
            ->assertSee('Laporan Stok')
            ->assertSee(route('reports.stock'), false)
            ->assertDontSee('Master Data')
            ->assertDontSee(route('categories.index'), false)
            ->assertDontSee(route('reports.stocks'), false);
    }

    public function test_admin_navigation_marks_the_current_report_route_active(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Laporan Stok')
            ->assertSee('href="'.route('reports.stock').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('reports.inbound').'"', false)
            ->assertSee('href="'.route('reports.sales').'"', false);
    }

    public function test_master_navigation_remains_active_on_edit_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Elektronik', 'slug' => 'elektronik']);

        $this->actingAs($admin)
            ->get(route('categories.edit', $category))
            ->assertOk()
            ->assertSee('href="'.route('categories.index').'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_profile_screen_uses_the_shared_sidebar_and_post_logout_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('id="dashboard-sidebar"', false)
            ->assertSee('data-sidebar-open', false)
            ->assertSee('method="POST" action="'.route('logout').'"', false)
            ->assertSee('name="_token"', false);
    }
}
