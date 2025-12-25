<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CleanDuplicateUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-duplicate-users {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect and remove duplicate user accounts based on username or email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        $this->info('Starting duplicate user cleanup...');
        $this->info($isDryRun ? 'DRY RUN MODE - No changes will be made' : 'LIVE MODE - Duplicates will be removed');
        $this->newLine();

        // Find duplicate usernames
        $duplicateUsernames = User::select('username', DB::raw('COUNT(*) as count'))
            ->whereNotNull('username')
            ->groupBy('username')
            ->having('count', '>', 1)
            ->get();

        // Find duplicate emails
        $duplicateEmails = User::select('email', DB::raw('COUNT(*) as count'))
            ->groupBy('email')
            ->having('count', '>', 1)
            ->get();

        $totalRemoved = 0;

        // Handle duplicate usernames
        if ($duplicateUsernames->count() > 0) {
            $this->warn("Found {$duplicateUsernames->count()} duplicate usernames:");

            foreach ($duplicateUsernames as $duplicate) {
                $users = User::where('username', $duplicate->username)->orderBy('created_at', 'asc')->get();

                $this->line("  Username: {$duplicate->username} ({$duplicate->count} accounts)");

                // Keep the oldest account, remove the rest
                $keepUser = $users->first();
                $removeUsers = $users->skip(1);

                $this->line("    Keeping: User ID {$keepUser->id} (created {$keepUser->created_at})");

                foreach ($removeUsers as $removeUser) {
                    $this->line("    Removing: User ID {$removeUser->id} (created {$removeUser->created_at})");

                    if (!$isDryRun) {
                        // Delete related data
                        $removeUser->videos()->delete();
                        $removeUser->likes()->delete();
                        $removeUser->comments()->delete();
                        $removeUser->bookmarks()->delete();
                        $removeUser->notifications()->delete();
                        $removeUser->following()->detach();
                        $removeUser->followers()->detach();
                        $removeUser->tokens()->delete();

                        // Delete user
                        $removeUser->delete();
                    }

                    $totalRemoved++;
                }

                $this->newLine();
            }
        } else {
            $this->info('No duplicate usernames found.');
        }

        // Handle duplicate emails
        if ($duplicateEmails->count() > 0) {
            $this->warn("Found {$duplicateEmails->count()} duplicate emails:");

            foreach ($duplicateEmails as $duplicate) {
                $users = User::where('email', $duplicate->email)->orderBy('created_at', 'asc')->get();

                $this->line("  Email: {$duplicate->email} ({$duplicate->count} accounts)");

                // Keep the oldest account, remove the rest
                $keepUser = $users->first();
                $removeUsers = $users->skip(1);

                $this->line("    Keeping: User ID {$keepUser->id} (created {$keepUser->created_at})");

                foreach ($removeUsers as $removeUser) {
                    $this->line("    Removing: User ID {$removeUser->id} (created {$removeUser->created_at})");

                    if (!$isDryRun) {
                        // Delete related data
                        $removeUser->videos()->delete();
                        $removeUser->likes()->delete();
                        $removeUser->comments()->delete();
                        $removeUser->bookmarks()->delete();
                        $removeUser->notifications()->delete();
                        $removeUser->following()->detach();
                        $removeUser->followers()->detach();
                        $removeUser->tokens()->delete();

                        // Delete user
                        $removeUser->delete();
                    }

                    $totalRemoved++;
                }

                $this->newLine();
            }
        } else {
            $this->info('No duplicate emails found.');
        }

        $this->newLine();

        if ($isDryRun) {
            $this->info("DRY RUN COMPLETE: Would have removed {$totalRemoved} duplicate accounts.");
            $this->comment('Run without --dry-run to actually remove duplicates.');
        } else {
            $this->success("Cleanup complete! Removed {$totalRemoved} duplicate accounts.");
        }

        return Command::SUCCESS;
    }
}
