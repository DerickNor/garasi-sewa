<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder utama: `php artisan migrate --seed`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);
    }
}
