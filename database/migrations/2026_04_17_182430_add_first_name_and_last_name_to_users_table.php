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
        Schema::table('users', function (Blueprint $table) {
            // Add first_name and last_name columns
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            
            // Make the existing 'name' column nullable since we're replacing it
            $table->string('name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the columns
            $table->dropColumn(['first_name', 'last_name']);
            
            // Revert name column back to not nullable
            $table->string('name')->nullable(false)->change();
        });
    }
};