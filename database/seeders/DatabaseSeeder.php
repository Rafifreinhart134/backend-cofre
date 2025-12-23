<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create super admin user
        $this->call([
            AdminSeeder::class,
        ]);

        // DISABLED: FoodContentSeeder creates fake videos with S3 URLs
        // Using local storage only - videos will be uploaded manually
        // $this->call([
        //     FoodContentSeeder::class,
        // ]);

        // Database is now empty - ready for real uploads!
    }
}
