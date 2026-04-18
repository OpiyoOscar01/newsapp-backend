<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Drops existing table if it exists, then creates a fresh one
     */
    public function up(): void
    {
        // Drop the table if it exists
        Schema::dropIfExists('visitor_logs');
        
        // Create the table fresh
        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('session_id');
            $table->string('unique_visitor_id')->nullable();

            $table->string('page');
            $table->enum('page_type', ['landing', 'category', 'article', 'other']);

            $table->string('referrer')->nullable();
            $table->enum('referrer_type', ['direct', 'search', 'social', 'external', 'internal']);

            $table->text('user_agent')->nullable();
            $table->string('screen_resolution')->nullable();
            $table->enum('device_type', ['mobile', 'tablet', 'desktop']);

            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->default('UTC');

            $table->string('category_slug')->nullable();
            $table->string('article_id')->nullable();
            $table->string('ip_address')->nullable();

            $table->json('additional_data')->nullable();

            $table->timestamps();

            $table->index('created_at');
            $table->index(['session_id', 'created_at']);
            $table->index(['unique_visitor_id', 'created_at']);
            $table->index(['page_type', 'created_at']);
            $table->index(['device_type', 'created_at']);
            $table->index(['referrer_type', 'created_at']);
            $table->index(['category_slug', 'created_at']);
            $table->index(['article_id', 'created_at']);
            $table->index(['page', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
    }
};