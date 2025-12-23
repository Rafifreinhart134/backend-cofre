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
        Schema::table('reports', function (Blueprint $table) {
            // Drop the old unique constraint
            $table->dropUnique(['user_id', 'video_id']);
        });

        Schema::table('reports', function (Blueprint $table) {
            // Make video_id nullable to support story reports
            $table->foreignId('video_id')->nullable()->change();

            // Add story_id for story reports
            $table->foreignId('story_id')->nullable()->after('video_id')
                  ->constrained()->onDelete('cascade');

            // Add reportable_type to distinguish between video and story reports
            $table->string('reportable_type')->nullable()->after('story_id');

            // Add details field for additional report information
            $table->text('details')->nullable()->after('reason');
        });

        // Add new unique constraint to prevent duplicate reports
        // Note: This allows null values, so a user can report multiple items with null video_id/story_id
        Schema::table('reports', function (Blueprint $table) {
            $table->index(['user_id', 'video_id', 'story_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Drop the index
            $table->dropIndex(['user_id', 'video_id', 'story_id']);

            // Drop new columns
            $table->dropColumn(['story_id', 'reportable_type', 'details']);
        });

        // Make video_id non-nullable again
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('video_id')->nullable(false)->change();
        });

        // Restore old unique constraint
        Schema::table('reports', function (Blueprint $table) {
            $table->unique(['user_id', 'video_id']);
        });
    }
};
