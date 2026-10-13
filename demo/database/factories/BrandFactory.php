<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shardevcom\Product\Models\Brand;

/**
 * @extends Factory<\Shardevcom\Product\Models\Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => 'Brand '.Str::upper(Str::random(5)),
        ];
    }
}
