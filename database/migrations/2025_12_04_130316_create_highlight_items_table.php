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
        Schema::create('highlight_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('highlight_id')->constrained('story_highlights')->onDelete('cascade');
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->timestamp('added_at')->useCurrent();

            // Prevent duplicate stories in same highlight
            $table->unique(['highlight_id', 'story_id']);

            // Indexes for better performance
            $table->index(['highlight_id', 'order']);
            $table->index('story_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('highlight_items');
    }
};
