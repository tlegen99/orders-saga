<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // фиксированные товары - удобно дёргать API руками: id и цены известны заранее
        foreach ([
            ['name' => 'Клавиатура', 'price' => 450000, 'stock' => 20],
            ['name' => 'Мышь', 'price' => 150000, 'stock' => 50],
            ['name' => 'Монитор', 'price' => 2500000, 'stock' => 5],
            ['name' => 'Наушники', 'price' => 800000, 'stock' => 10],
            ['name' => 'Коврик', 'price' => 50000, 'stock' => 100],
        ] as $product) {
            Product::create($product);
        }
    }
}
