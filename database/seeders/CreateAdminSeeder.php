<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Delete existing admin users to avoid conflicts
        User::where('email', 'admin@covre.id')->delete();

        // Create fresh admin user
        User::create([
            'name' => 'Admin Covre',
            'email' => 'admin@covre.id',
            'password' => Hash::make('admin123'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user created: admin@covre.id / admin123');
    }
}
