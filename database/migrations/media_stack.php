<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration creates a comprehensive database structure for a news application
     * that integrates with the MediaStack API. It includes all necessary tables to
     * store news articles, manage API fetching logs, handle categories and sources,
     * and support user interactions.
     * 
     */
    /**
     * Key Features:
     * Complete MediaStack API Mapping: All tables map directly to the MediaStack API response structure
     * Performance Optimized: Strategic indexes on frequently queried columns
     * Comprehensive Logging: Detailed API fetch logs for monitoring and debugging
     * User Interaction Ready: Tables ready for user features like likes, shares, bookmarks
     * SEO Friendly: Fields for slugs, meta descriptions, and cached images
     * Monitoring & Analytics: Built-in performance tracking and scheduling management
     * Extensible: Designed to grow with your application needs
     * Tables Created:
     *categories - MediaStack news categories
     *sources - News source information
     *articles - Main news articles table
     *api_fetch_logs - API call monitoring
     *article_interactions - User engagement tracking
     *article_keywords - Enhanced search capabilities
     *mediastack_settings - Configuration management
     *fetch_schedules - Cron job management
     *Usage:

     *Copy# Create and run the migration
     *php artisan make:migration create_mediastack_news_database
     *# Copy the above code into the generated migration file
     *php artisan migrate
     *This migration provides a solid foundation for your news application with comprehensive logging, monitoring, and extensibility built-in from th *start.
     */
    public function up(): void
    {
        // Create categories table first (referenced by other tables)
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

        // Create sources table to store news source information
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

        // Main articles table - core entity storing news articles from MediaStack API
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            
            // Core article data from MediaStack API response
            $table->string('title', 500)->comment('Article headline/title from MediaStack API');
            $table->text('description')->nullable()->comment('Article description/summary from MediaStack API');
            $table->longText('content')->nullable()->comment('Full article content (may be limited by MediaStack)');
            $table->string('author', 255)->nullable()->comment('Article author name from MediaStack API');
            $table->string('url', 2048)->unique()->comment('Original article URL - used as unique identifier');
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

        // API fetch logs table for monitoring and debugging
        Schema::create('api_fetch_logs', function (Blueprint $table) {
            $table->id();
            
            // API call information
            $table->string('endpoint', 100)->comment('MediaStack API endpoint called (e.g., news, sources)');
            $table->json('parameters')->comment('JSON of parameters sent to the API');
            $table->string('request_type', 50)->default('scheduled')->comment('Type of request: scheduled, manual, retry');
            
            // Response information
            $table->integer('total_results')->default(0)->comment('Total results available from API response');
            $table->integer('fetched_results')->default(0)->comment('Number of results actually fetched');
            $table->integer('new_articles')->default(0)->comment('Number of new articles added to database');
            $table->integer('updated_articles')->default(0)->comment('Number of existing articles updated');
            $table->integer('duplicate_articles')->default(0)->comment('Number of duplicate articles skipped');
            
            // Performance metrics
            $table->integer('execution_time_ms')->comment('Total execution time in milliseconds');
            $table->integer('api_response_time_ms')->nullable()->comment('API response time in milliseconds');
            $table->integer('db_processing_time_ms')->nullable()->comment('Database processing time in milliseconds');
            
            // Status and error handling
            $table->enum('status', ['success', 'partial_success', 'failed', 'rate_limited'])
                  ->comment('Overall status of the fetch operation');
            $table->text('error_message')->nullable()->comment('Error message if fetch failed');
            $table->json('error_details')->nullable()->comment('Detailed error information');
            $table->integer('http_status_code')->nullable()->comment('HTTP status code from API response');
            
            // Rate limiting information
            $table->integer('rate_limit_remaining')->nullable()->comment('Remaining API calls for current period');
            $table->timestamp('rate_limit_reset_at')->nullable()->comment('When rate limit resets');
            
            // Metadata
            $table->string('triggered_by', 100)->default('cron')->comment('What triggered this fetch (cron, manual, webhook)');
            $table->timestamp('started_at')->comment('When the fetch operation started');
            $table->timestamp('completed_at')->nullable()->comment('When the fetch operation completed');
            
            $table->timestamps();
            
            // Indexes for monitoring and analytics
            $table->index(['created_at', 'status']); // Status monitoring over time
            $table->index(['endpoint', 'created_at']); // Endpoint-specific logs
            $table->index(['status', 'created_at']); // Error analysis
            $table->index('execution_time_ms'); // Performance monitoring
            $table->index('started_at'); // Chronological queries
            
            $table->comment('Logs all API fetch operations for monitoring and debugging');
        });

        // User interactions with articles (if you plan to add user features)
        Schema::create('article_interactions', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys (assumes you have a users table or will create one)
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
            $table->index(['article_id', 'interaction_type']); // Article analytics
            $table->index(['interaction_date', 'interaction_type']); // Time-based analytics
            $table->index(['user_id', 'interaction_type']); // User behavior analysis
            $table->index('session_id'); // Session tracking
            
            // Composite index for unique constraints on some interactions
            $table->unique(['article_id', 'user_id', 'interaction_type', 'session_id'], 'unique_user_article_interaction');
            
            $table->comment('Tracks user interactions with articles for analytics');
        });

        // Article keywords table for better search and categorization
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
            $table->index(['keyword', 'relevance_score']); // Keyword search
            $table->index(['article_id', 'relevance_score']); // Article keywords
            $table->unique(['article_id', 'keyword']); // Prevent duplicate keywords per article
            
            $table->comment('Keywords associated with articles for enhanced search');
        });

        // Configuration table for MediaStack API settings
        Schema::create('mediastack_settings', function (Blueprint $table) {
            $table->id();
            
            $table->string('key', 100)->unique()->comment('Configuration key');
            $table->text('value')->comment('Configuration value (JSON for complex values)');
            $table->string('type', 20)->default('string')->comment('Data type: string, json, integer, boolean');
            $table->text('description')->nullable()->comment('Description of this setting');
            $table->boolean('is_encrypted')->default(false)->comment('Whether this value is encrypted');
            
            $table->timestamps();
            
            $table->index('key');
            
            $table->comment('Configuration settings for MediaStack API integration');
        });

        // Table to track fetch schedules and their performance
        Schema::create('fetch_schedules', function (Blueprint $table) {
            $table->id();
            
            $table->string('name', 100)->unique()->comment('Unique name for this fetch schedule');
            $table->string('description', 255)->comment('Description of what this schedule does');
            $table->json('api_parameters')->comment('JSON parameters to send to MediaStack API');
            $table->string('cron_expression', 50)->comment('Cron expression for scheduling');
            $table->boolean('is_active')->default(true)->comment('Whether this schedule is currently active');
            
            // Performance tracking
            $table->timestamp('last_run_at')->nullable()->comment('When this schedule last ran');
            $table->timestamp('next_run_at')->nullable()->comment('When this schedule will next run');
            $table->integer('total_runs')->default(0)->comment('Total number of times this schedule has run');
            $table->integer('successful_runs')->default(0)->comment('Number of successful runs');
            $table->integer('failed_runs')->default(0)->comment('Number of failed runs');
            $table->decimal('average_execution_time', 8, 2)->nullable()->comment('Average execution time in seconds');
            
            // Alert settings
            $table->integer('max_execution_time')->default(300)->comment('Maximum allowed execution time in seconds');
            $table->boolean('alert_on_failure')->default(true)->comment('Whether to send alerts on failure');
            $table->string('alert_email', 255)->nullable()->comment('Email to send alerts to');
            
            $table->timestamps();
            
            $table->index(['is_active', 'next_run_at']); // Scheduler queries
            $table->index('last_run_at'); // Performance tracking
            
            $table->comment('Manages scheduled fetching of news from MediaStack API');
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Drops all tables in reverse order of creation to maintain referential integrity.
     */
    public function down(): void
    {
        // Drop tables in reverse order to handle foreign key constraints
        Schema::dropIfExists('fetch_schedules');
        Schema::dropIfExists('mediastack_settings');
        Schema::dropIfExists('article_keywords');
        Schema::dropIfExists('article_interactions');
        Schema::dropIfExists('api_fetch_logs');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('categories');
    }
};
