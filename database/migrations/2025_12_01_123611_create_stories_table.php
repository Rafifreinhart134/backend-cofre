<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('media_url'); // URL to video/image in Cloudflare R2
            $table->string('thumbnail_url')->nullable(); // Thumbnail for video stories
            $table->enum('media_type', ['image', 'video'])->default('image');
            $table->integer('duration')->default(5); // Duration in seconds (5 for image, 15 for video)
            $table->text('caption')->nullable();
            $table->json('stickers')->nullable(); // JSON array of stickers (location, mention, hashtag, etc)
            $table->json('text_elements')->nullable(); // JSON array of text overlays
            $table->string('filter')->nullable(); // Applied filter name
            $table->integer('view_count')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->boolean('allow_resharing')->default(true);
            $table->timestamp('expires_at'); // Stories expire after 24 hours
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('created_at');
            $table->index('expires_at');
            $table->index(['user_id', 'is_archived']);
        });

        // Story views tracking table
        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            $table->foreignId('viewer_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('viewed_at');

            // Prevent duplicate views
            $table->unique(['story_id', 'viewer_id']);
            $table->index(['story_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
    }
};
