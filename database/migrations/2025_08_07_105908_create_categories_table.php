<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            
            // Category identification - matches MediaStack API categories
            $table->string('slug', 50)->unique()->comment('Category slug (e.g., technology, business)');
            $table->string('name', 100)->comment('Display name for the category');
            $table->text('description')->nullable()->comment('Optional description of the category');
            $table->boolean('is_active')->default(true)->comment('Whether this category is currently active');
            
            // Metadata
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['slug', 'is_active']);
            
            $table->comment('Stores news categories that match MediaStack API categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};