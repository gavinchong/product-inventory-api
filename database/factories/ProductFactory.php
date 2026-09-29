<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-########-????'),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0, 10000),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
