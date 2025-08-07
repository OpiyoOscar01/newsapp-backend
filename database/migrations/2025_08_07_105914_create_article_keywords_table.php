<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_keywords', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('article_id')->constrained('articles')->onDelete('cascade')
                  ->comment('Reference to the article');
            $table->string('keyword', 100)->comment('Extracted or assigned keyword');
            $table->decimal('relevance_score', 4, 3)->default(1.000)
                  ->comment('How relevant this keyword is to the article (0-1)');
            $table->enum('source', ['extracted', 'manual', 'ai', 'mediastack'])
                  ->comment('How this keyword was determined');
            
            $table->timestamps();
            
            // Indexes for search functionality
            $table->index(['keyword', 'relevance_score']);
            $table->index(['article_id', 'relevance_score']);
            $table->unique(['article_id', 'keyword']);
            
            $table->comment('Keywords associated with articles for enhanced search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_keywords');
    }
};