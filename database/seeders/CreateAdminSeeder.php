<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CreateAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating admin user...');

        // Use database transaction to ensure data is saved
        DB::beginTransaction();

        try {
            // Delete existing admin users to avoid conflicts
            User::where('email', 'admin@covre.com')->forceDelete();

            // Create fresh admin user with explicit database insert
            $user = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@covre.com',
                'password' => Hash::make('admin123'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);

            // Force save to database
            $user->save();

            // Commit transaction
            DB::commit();

            // Verify user was created
            $verifyUser = User::where('email', 'admin@covre.com')->first();

            if ($verifyUser && $verifyUser->is_admin && $verifyUser->email_verified_at) {
                $this->command->info('✓ Admin user created successfully!');
                $this->command->info('  Email: admin@covre.com');
                $this->command->info('  Password: admin123');
                $this->command->warn('  IMPORTANT: Change password after first login!');
            } else {
                $this->command->error('Failed to verify admin user creation!');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error creating admin user: ' . $e->getMessage());
            throw $e;
        }
    }
}
