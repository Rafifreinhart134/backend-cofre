<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the new columns that we'll need for badge system
        // These need to exist before we can populate them
        Schema::table('users', function (Blueprint $table) {
            $table->enum('badge_status', ['pending', 'approved', 'rejected'])->nullable()->after('account_type');
            $table->text('badge_application_reason')->nullable()->after('badge_status');
            $table->boolean('badge_is_culinary_creator')->default(false)->after('badge_application_reason');
            $table->timestamp('badge_applied_at')->nullable()->after('badge_is_culinary_creator');
            $table->text('badge_rejection_reason')->nullable()->after('badge_applied_at');
            $table->boolean('show_badge')->default(true)->after('badge_rejection_reason');
        });

        // Now migrate existing UMKM users to creator with auto-approved badge
        DB::table('users')
            ->where('account_type', 'umkm')
            ->update([
                'account_type' => 'creator',
                'badge_status' => 'approved',
                'badge_application_reason' => 'Migrated from UMKM account type',
                'badge_is_culinary_creator' => true,
                'badge_applied_at' => now(),
                'show_badge' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert creator users back to umkm if they were migrated
        DB::table('users')
            ->where('badge_application_reason', 'Migrated from UMKM account type')
            ->update([
                'account_type' => 'umkm',
                'badge_status' => null,
                'badge_application_reason' => null,
                'badge_is_culinary_creator' => false,
                'badge_applied_at' => null,
                'show_badge' => true,
            ]);

        // Drop the badge columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'badge_status',
                'badge_application_reason',
                'badge_is_culinary_creator',
                'badge_applied_at',
                'badge_rejection_reason',
                'show_badge',
            ]);
        });
    }
};
