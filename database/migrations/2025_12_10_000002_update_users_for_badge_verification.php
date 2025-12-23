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
        // Add website field for user profiles
        Schema::table('users', function (Blueprint $table) {
            $table->string('website', 500)->nullable();
        });

        // Note: SQLite doesn't support MODIFY COLUMN or ENUM types
        // For production MySQL, you would run:
        // DB::statement("ALTER TABLE users MODIFY COLUMN account_type ENUM('regular', 'creator') DEFAULT 'regular'");
        // For SQLite, the account_type is just a string with application-level validation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove website field
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('website');
        });

        // Note: For MySQL production, you would restore:
        // DB::statement("ALTER TABLE users MODIFY COLUMN account_type ENUM('regular', 'umkm', 'creator') DEFAULT 'regular'");
    }
};
