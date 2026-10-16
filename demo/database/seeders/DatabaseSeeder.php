<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\EmployeeUser;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Shardevcom\Product\Models\Brand;
use Shardevcom\Product\Models\Product;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the demo so the SPA has real credentials and data to consume.
     *
     * Every platform user shares the password `password`; the client's login
     * screen presets the matching email per platform.
     */
    public function run(): void
    {
        User::factory()->create(['name' => 'Demo Web User', 'email' => 'user@example.com']);
        AdminUser::factory()->create(['name' => 'Demo Admin', 'email' => 'admin@example.com']);
        EmployeeUser::factory()->create(['name' => 'Demo Employee', 'email' => 'employee@example.com']);

        $brands = Brand::factory(3)->create();

        Product::factory(12)
            ->recycle($brands)
            ->create();
    }
}
