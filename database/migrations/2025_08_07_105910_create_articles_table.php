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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            
            // Core article data from MediaStack API response
            $table->string('title', 500)->comment('Article headline/title from MediaStack API');
            $table->text('description')->nullable()->comment('Article description/summary from MediaStack API');
            $table->longText('content')->nullable()->comment('Full article content (may be limited by MediaStack)');
            $table->string('author', 255)->nullable()->comment('Article author name from MediaStack API');
            $table->string('url', 191)->unique()->comment('Original article URL - used as unique identifier');
            $table->string('source', 100)->nullable()->comment('Source name from MediaStack API (e.g., CNN, BBC)');
            $table->string('image_url', 2048)->nullable()->comment('Featured image URL from MediaStack API');
            
            // Classification and metadata from MediaStack API
            $table->string('category', 50)->nullable()->comment('Article category from MediaStack API');
            $table->string('language', 5)->nullable()->comment('Article language (2-letter ISO code)');
            $table->string('country', 5)->nullable()->comment('Source country (2-letter ISO code)');
            $table->timestamp('published_at')->comment('Original publication timestamp from MediaStack API');
            
            // Internal application fields
            $table->boolean('is_active')->default(true)->comment('Whether article is visible to users');
            $table->boolean('is_featured')->default(false)->comment('Whether article should be featured');
            $table->integer('view_count')->default(0)->comment('Number of times article has been viewed');
            $table->decimal('sentiment_score', 3, 2)->nullable()->comment('Sentiment analysis score (-1 to 1)');
            $table->json('tags')->nullable()->comment('Additional tags for the article (JSON array)');
            $table->json('keywords')->nullable()->comment('Extracted keywords from the article (JSON array)');
            
            // SEO and caching fields
            $table->string('slug', 500)->nullable()->comment('SEO-friendly URL slug');
            $table->text('meta_description')->nullable()->comment('SEO meta description');
            $table->string('cached_image_path', 500)->nullable()->comment('Local cached version of the image');
            
            // Processing status
            $table->enum('processing_status', ['pending', 'processed', 'failed'])
                  ->default('pending')
                  ->comment('Status of article processing (image caching, keyword extraction, etc.)');
            $table->timestamp('last_processed_at')->nullable()->comment('Last time article was processed');
            
            $table->timestamps();
            
            // Essential indexes for performance
            $table->index(['published_at', 'is_active']); // Main listing queries
            $table->index(['category', 'is_active', 'published_at']); // Category-based queries
            $table->index(['source', 'is_active']); // Source-based queries
            $table->index(['country', 'language', 'is_active']); // Location/language filtering
            $table->index(['is_featured', 'published_at']); // Featured articles
            $table->index('view_count'); // Popular articles
            $table->index('processing_status'); // Processing queue
            $table->index('created_at'); // Admin queries
            
            // Full-text search index for title and description
            $table->fullText(['title', 'description'], 'articles_search_index');
            
            $table->comment('Main table storing news articles fetched from MediaStack API');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};