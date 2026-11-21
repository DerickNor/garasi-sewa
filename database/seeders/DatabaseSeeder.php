<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder utama: `php artisan migrate:fresh --seed` membuat akun admin dan data demo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AdminSeeder::class, DemoSeeder::class]);
    }
}
