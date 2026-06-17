<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Product::insert([
            ['name' => 'Laptop', 'stock' => 50, 'price' => 999.99, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Smartphone', 'stock' => 100, 'price' => 499.99, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Headphones', 'stock' => 200, 'price' => 79.99, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
