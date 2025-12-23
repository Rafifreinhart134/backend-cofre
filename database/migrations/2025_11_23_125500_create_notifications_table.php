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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Recipient
            $table->foreignId('from_user_id')->nullable()->constrained('users')->onDelete('cascade'); // Sender
            $table->enum('type', ['follow', 'like', 'comment', 'mention', 'repost', 'system']); // Notification type
            $table->string('title')->nullable();
            $table->text('message');
            $table->foreignId('video_id')->nullable()->constrained()->onDelete('cascade'); // Related video (optional)
            $table->foreignId('comment_id')->nullable()->constrained()->onDelete('cascade'); // Related comment (optional)
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('is_read');
            $table->index(['user_id', 'is_read']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
