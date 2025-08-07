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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            
            // Source identification from MediaStack API
            $table->string('mediastack_id', 100)->unique()->comment('MediaStack source ID (e.g., cnn, bbc)');
            $table->string('name', 200)->comment('Display name of the news source');
            $table->string('url', 500)->nullable()->comment('Official website URL of the source');
            $table->string('category', 50)->nullable()->comment('Primary category of this source');
            $table->string('country', 5)->nullable()->comment('2-letter country code (ISO 3166-1 alpha-2)');
            $table->string('language', 5)->nullable()->comment('2-letter language code (ISO 639-1)');
            
            // Metadata and status
            $table->boolean('is_active')->default(true)->comment('Whether this source is currently being tracked');
            $table->timestamp('last_fetched_at')->nullable()->comment('Last time we fetched news from this source');
            $table->json('fetch_settings')->nullable()->comment('JSON settings for fetching from this source');
            
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['country', 'is_active']);
            $table->index(['language', 'is_active']);
            $table->index(['category', 'is_active']);
            $table->index('last_fetched_at');
            
            $table->comment('Stores information about news sources from MediaStack API');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};