<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_interactions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('article_id')->constrained('articles')->onDelete('cascade')
                  ->comment('Reference to the article being interacted with');
            $table->unsignedBigInteger('user_id')->nullable()->comment('User ID if logged in, null for anonymous');
            
            // Interaction details
            $table->enum('interaction_type', ['view', 'like', 'share', 'bookmark', 'comment'])
                  ->comment('Type of user interaction');
            $table->string('session_id', 100)->nullable()->comment('Session ID for anonymous users');
            $table->string('ip_address', 45)->nullable()->comment('User IP address (supports IPv6)');
            $table->string('user_agent', 500)->nullable()->comment('User browser information');
            $table->string('referrer', 500)->nullable()->comment('Where user came from');
            
            // Interaction metadata
            $table->json('metadata')->nullable()->comment('Additional interaction data (time spent, scroll depth, etc.)');
            $table->timestamp('interaction_date')->comment('When the interaction occurred');
            
            $table->timestamps();
            
            // Indexes for analytics
            $table->index(['article_id', 'interaction_type']);
            $table->index(['interaction_date', 'interaction_type']);
            $table->index(['user_id', 'interaction_type']);
            $table->index('session_id');
            
            // Composite index for unique constraints on some interactions
            $table->unique(['article_id', 'user_id', 'interaction_type', 'session_id'], 'unique_user_article_interaction');
            
            $table->comment('Tracks user interactions with articles for analytics');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_interactions');
    }
};