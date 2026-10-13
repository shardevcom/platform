<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shardevcom\Product\Models\Brand;
use Shardevcom\Product\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $sku = strtoupper(Str::random(3)).'-'.Str::upper(Str::random(5));

        return [
            'sku' => $sku,
            'ean' => (string) fake()->unique()->numerify('#############'),
            'name' => fake()->words(2, true),
            'brand_id' => Brand::factory(),
            'active' => fake()->boolean(85),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function withoutBrand(): static
    {
        return $this->state(fn (): array => ['brand_id' => null]);
    }
}
