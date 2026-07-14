<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Athar Customer',
                'email' => 'customer@kanzza.com',
                'phone' => '081200000001',
                'role' => UserRole::Customer,
            ],
            [
                'name' => 'Kasir Kanzza',
                'email' => 'cashier@kanzza.com',
                'phone' => '081200000002',
                'role' => UserRole::Cashier,
            ],
            [
                'name' => 'Driver Kanzza',
                'email' => 'driver@kanzza.com',
                'phone' => '081200000003',
                'role' => UserRole::Driver,
            ],
            [
                'name' => 'Owner Kanzza',
                'email' => 'owner@kanzza.com',
                'phone' => '081200000004',
                'role' => UserRole::Owner,
            ],
        ];

        foreach ($users as $userData) {
            User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    ...$userData,
                    'password' => Hash::make('123456'),
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                ],
            );
        }

        $categories = collect([
            ['name' => 'Frozen Food', 'description' => 'Produk makanan beku siap masak.'],
            ['name' => 'Snack', 'description' => 'Camilan dan makanan ringan.'],
            ['name' => 'Minuman', 'description' => 'Minuman pelengkap.'],
        ])->mapWithKeys(function (array $data): array {
            $category = Category::query()->updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => true,
                ],
            );

            return [$data['name'] => $category];
        });

        $products = [
            [
                'category' => 'Frozen Food',
                'sku' => 'KZ-NUGGET-001',
                'name' => 'Nugget Ayam Kanzza',
                'cost_price' => 25000,
                'selling_price' => 32000,
                'stock' => 50,
                'minimum_stock' => 10,
                'unit' => 'pack',
            ],
            [
                'category' => 'Frozen Food',
                'sku' => 'KZ-SOSIS-001',
                'name' => 'Sosis Sapi Kanzza',
                'cost_price' => 28000,
                'selling_price' => 35000,
                'stock' => 40,
                'minimum_stock' => 10,
                'unit' => 'pack',
            ],
            [
                'category' => 'Snack',
                'sku' => 'KZ-KERIPIK-001',
                'name' => 'Keripik Singkong Original',
                'cost_price' => 10000,
                'selling_price' => 15000,
                'stock' => 75,
                'minimum_stock' => 15,
                'unit' => 'pack',
            ],
        ];

        foreach ($products as $productData) {
            $categoryName = $productData['category'];
            unset($productData['category']);

            Product::query()->updateOrCreate(
                ['sku' => $productData['sku']],
                [
                    ...$productData,
                    'category_id' => $categories[$categoryName]->id,
                    'slug' => Str::slug($productData['name']),
                    'description' => $productData['name'].' siap dijual.',
                    'is_active' => true,
                ],
            );
        }
    }
}
