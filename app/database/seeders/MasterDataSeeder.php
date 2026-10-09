<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Elektronik', 'slug' => 'elektronik'],
            ['name' => 'Sembako', 'slug' => 'sembako'],
        ])->mapWithKeys(fn (array $attributes) => [
            $attributes['name'] => Category::updateOrCreate(
                ['slug' => $attributes['slug']],
                ['name' => $attributes['name']],
            ),
        ]);

        foreach ([
            [
                'code' => 'GDG-A',
                'name' => 'Gudang Pusat Jakarta',
                'address' => 'Jakarta',
                'is_active' => true,
            ],
            [
                'code' => 'GDG-B',
                'name' => 'Gudang Cabang Surabaya',
                'address' => 'Surabaya',
                'is_active' => true,
            ],
        ] as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse,
            );
        }

        foreach ([
            ['name' => 'Toko Maju Bandung', 'address' => 'Bandung'],
            ['name' => 'Toko Sejahtera Bekasi', 'address' => 'Bekasi'],
        ] as $customer) {
            Customer::updateOrCreate(
                ['name' => $customer['name']],
                ['address' => $customer['address']],
            );
        }

        foreach ([
            [
                'sku' => 'PRD-001',
                'name' => 'Laptop',
                'category_id' => $categories['Elektronik']->id,
                'cost_price' => 5_000_000,
                'selling_price' => 6_000_000,
                'is_active' => true,
            ],
            [
                'sku' => 'PRD-002',
                'name' => 'Beras 5kg',
                'category_id' => $categories['Sembako']->id,
                'cost_price' => 60_000,
                'selling_price' => 75_000,
                'is_active' => true,
            ],
            [
                'sku' => 'PRD-003',
                'name' => 'Smartphone',
                'category_id' => $categories['Elektronik']->id,
                'cost_price' => 3_000_000,
                'selling_price' => 3_800_000,
                'is_active' => true,
            ],
        ] as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                $product,
            );
        }
    }
}
