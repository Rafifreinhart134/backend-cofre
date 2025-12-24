<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListAdminUsers extends Command
{
    protected $signature = 'admin:list';
    protected $description = 'List all admin users';

    public function handle()
    {
        $this->info('=== Admin Users ===');

        $users = User::where('is_admin', true)
            ->orWhere('email', 'like', '%admin%')
            ->get(['id', 'name', 'email', 'is_admin', 'email_verified_at']);

        if ($users->isEmpty()) {
            $this->error('No admin users found!');
            return 1;
        }

        $tableData = [];
        foreach ($users as $user) {
            $tableData[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->is_admin ? 'YES' : 'NO',
                $user->email_verified_at ? 'YES' : 'NO',
            ];
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Is Admin', 'Email Verified'],
            $tableData
        );

        return 0;
    }
}
