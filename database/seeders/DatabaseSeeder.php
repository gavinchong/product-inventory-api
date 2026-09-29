<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $suppliers = collect([
            ['name' => 'Tech Supply', 'email' => 'sales@tech.example', 'phone' => '+60123456789'],
            ['name' => 'Office Supply', 'email' => 'sales@office.example', 'phone' => '+60198765432'],
            ['name' => 'General Wholesale', 'email' => 'sales@wholesale.example', 'phone' => null],
        ])->map(fn (array $attributes) => Supplier::factory()->create($attributes));

        foreach (['Electronics', 'Office', 'Accessories'] as $index => $name) {
            $category = Category::factory()->create([
                'name' => $name,
                'description' => $name.' inventory',
            ]);

            foreach (range(1, 6) as $number) {
                $product = Product::factory()->for($category)->create([
                    'name' => $name.' Product '.$number,
                    'sku' => strtoupper(substr($name, 0, 3)).'-00'.$number,
                    'price' => ($index + 1) * $number * 25,
                    'stock' => $number === 1 ? 0 : $number * 5,
                ]);
                $product->suppliers()->sync([
                    $suppliers[$index]->id,
                    $suppliers[($index + 1) % $suppliers->count()]->id,
                ]);
            }
        }
    }
}
