<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Comprehensive News Management System Migration
     * Features: User Management, Articles, Categories, Tags, Comments, Media, Moderation
     */
    public function up(): void
    {
        // ================================
        // USER MANAGEMENT TABLES
        // ================================
        
        /**
         * User Roles Table
         * Defines system roles (Admin, Editor, Author, Moderator, Subscriber)
         */
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('slug', 50)->unique();
            $table->string('description')->nullable();
            $table->json('permissions')->nullable(); // Store permissions as JSON
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['slug', 'is_active']);
        });

        /**
         * Enhanced Users Table
         * Extended user management with profile and professional information
         */
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            
            // Profile Information
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('display_name', 150)->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            
            // Professional Information
            $table->string('job_title', 150)->nullable();
            $table->string('organization', 200)->nullable();
            $table->string('website_url')->nullable();
            $table->json('social_links')->nullable(); // Twitter, LinkedIn, etc.
            
            // System Fields
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending_verification'])
                  ->default('pending_verification');
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('receives_notifications')->default(true);
            $table->string('timezone', 50)->default('UTC');
            $table->string('locale', 10)->default('en');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['email', 'status']);
            $table->index(['username', 'status']);
            $table->index('last_login_at');
            $table->index('created_at');
            $table->fullText(['first_name', 'last_name', 'display_name', 'bio']);
        });

        /**
         * User Role Assignments (Many-to-Many)
         */
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('expires_at')->nullable();
            
            $table->unique(['user_id', 'role_id']);
            $table->index(['user_id', 'expires_at']);
        });

        // ================================
        // CONTENT CATEGORIZATION
        // ================================
        
        /**
         * Categories Table (Hierarchical)
         * Support for nested categories with unlimited depth
         */
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('meta_title', 200)->nullable();
            $table->text('meta_description')->nullable();
            
            // Hierarchical structure
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('level')->default(0);
            $table->string('path', 500)->nullable(); // Store full category path
            
            // Media and styling
            $table->string('image_url')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable(); // Hex color code
            
            // Configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_in_menu')->default(true);
            $table->json('settings')->nullable(); // Category-specific settings
            
            $table->timestamps();
            
            $table->index(['parent_id', 'is_active', 'sort_order']);
            $table->index(['slug', 'is_active']);
            $table->index('level');
            $table->fullText(['name', 'description']);
        });

        /**
         * Tags Table
         * Flexible tagging system for content
         */
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable(); // Hex color code
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            $table->index(['slug', 'usage_count']);
            $table->index('usage_count');
            $table->fullText(['name', 'description']);
        });

        // ================================
        // ARTICLES AND CONTENT
        // ================================
        
        /**
         * Articles Table
         * Main content table with comprehensive article management
         */
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('title', 300);
            $table->string('slug', 400)->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->longText('content_raw')->nullable(); // For editors like markdown
            
            // SEO and Meta
            $table->string('meta_title', 200)->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            
            // Media
            $table->string('featured_image_url')->nullable();
            $table->string('featured_image_alt', 300)->nullable();
            $table->json('gallery_images')->nullable(); // Array of image URLs
            
            // Publishing Information
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('editor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            
            // Status and Publishing
            $table->enum('status', [
                'draft', 'pending_review', 'scheduled', 'published', 
                'archived', 'rejected', 'private'
            ])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            
            // Engagement Metrics
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('share_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->unsignedInteger('rating_count')->default(0);
            
            // Content Management
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_breaking_news')->default(false);
            $table->boolean('is_premium')->default(false);
            $table->boolean('allow_comments')->default(true);
            $table->boolean('is_searchable')->default(true);
            $table->unsignedInteger('reading_time')->nullable(); // in minutes
            
            // Technical
            $table->string('content_type', 50)->default('article'); // article, news, review, opinion
            $table->string('language', 10)->default('en');
            $table->json('custom_fields')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance Indexes
            $table->index(['status', 'published_at']);
            $table->index(['author_id', 'status']);
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['is_featured', 'status', 'published_at']);
            $table->index(['is_breaking_news', 'published_at']);
            $table->index('view_count');
            $table->index('created_at');
            $table->fullText(['title', 'excerpt', 'content']);
        });

        /**
         * Article Revisions Table
         * Track all changes to articles for audit and rollback
         */
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Revision Content
            $table->string('title', 300);
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->json('metadata')->nullable(); // Store other changed fields
            
            // Revision Information
            $table->text('revision_notes')->nullable();
            $table->enum('revision_type', ['auto_save', 'manual_save', 'published', 'reverted']);
            $table->unsignedInteger('version_number');
            
            $table->timestamps();
            
            $table->index(['article_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        /**
         * Article Tags (Many-to-Many)
         */
        Schema::create('article_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamp('tagged_at')->useCurrent();
            
            $table->unique(['article_id', 'tag_id']);
            $table->index('tagged_at');
        });

        // ================================
        // MEDIA MANAGEMENT
        // ================================
        
        /**
         * Media Library Table
         * Centralized media management for all file types
         */
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            
            // File Information
            $table->string('filename', 300);
            $table->string('original_filename', 300);
            $table->string('mime_type', 100);
            $table->string('file_extension', 10);
            $table->unsignedBigInteger('file_size'); // in bytes
            $table->string('file_path', 500);
            $table->string('file_url', 500);
            
            // Media Metadata
            $table->string('title', 300)->nullable();
            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            
            // Image-specific fields
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('thumbnails')->nullable(); // Different size variations
            
            // Video/Audio-specific fields
            $table->unsignedInteger('duration')->nullable(); // in seconds
            $table->string('video_codec', 50)->nullable();
            $table->string('audio_codec', 50)->nullable();
            $table->unsignedInteger('bitrate')->nullable();
            
            // Organization
            $table->string('folder', 200)->default('/');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->enum('visibility', ['public', 'private', 'restricted'])->default('public');
            $table->json('usage_rights')->nullable(); // Copyright, license info
            
            // System
            $table->enum('status', ['processing', 'available', 'failed', 'deleted'])->default('processing');
            $table->string('storage_disk', 50)->default('public');
            $table->json('metadata')->nullable(); // EXIF, custom metadata
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['mime_type', 'status']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['folder', 'status']);
            $table->index('file_size');
            $table->fullText(['title', 'alt_text', 'caption', 'description']);
        });

        /**
         * Media Usage Tracking
         * Track where media files are used across the system
         */
        Schema::create('media_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->onDelete('cascade');
            $table->string('usable_type', 100); // Polymorphic relation
            $table->unsignedBigInteger('usable_id');
            $table->string('context', 100)->nullable(); // featured_image, gallery, inline, etc.
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            
            $table->index(['usable_type', 'usable_id']);
            $table->index(['media_id', 'context']);
            $table->unique(['media_id', 'usable_type', 'usable_id', 'context'], 'media_usage_unique');
        });

        // ================================
        // COMMENTS SYSTEM
        // ================================
        
        /**
         * Comments Table
         * Hierarchical comment system with moderation
         */
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship (can comment on articles, other comments, etc.)
            $table->string('commentable_type', 100);
            $table->unsignedBigInteger('commentable_id');
            
            // Hierarchical structure
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->unsignedInteger('level')->default(0);
            $table->string('thread_path', 500)->nullable(); // For efficient nested queries
            
            // Comment Content
            $table->longText('content');
            $table->longText('content_raw')->nullable(); // Original markdown/raw content
            $table->json('metadata')->nullable(); // Custom fields, formatting
            
            // Author Information
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('guest_name', 150)->nullable(); // For non-registered users
            $table->string('guest_email')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            // Moderation and Status
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'spam', 'flagged', 'hidden'
            ])->default('pending');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_reason')->nullable();
            
            // Engagement
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('dislike_count')->default(0);
            $table->unsignedInteger('reply_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['commentable_type', 'commentable_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['parent_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->fullText(['content']);
        });

        /**
         * Comment Reactions (Likes/Dislikes)
         */
        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('reaction_type', ['like', 'dislike', 'love', 'laugh', 'angry']);
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            
            $table->unique(['comment_id', 'user_id']);
            $table->index(['comment_id', 'reaction_type']);
        });

        // ================================
        // CONTENT MODERATION
        // ================================
        
        /**
         * Moderation Reports
         * User-generated reports for content moderation
         */
        Schema::create('moderation_reports', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship (can report articles, comments, users, etc.)
            $table->string('reportable_type', 100);
            $table->unsignedBigInteger('reportable_id');
            
            // Reporter Information
            $table->foreignId('reported_by')->nullable()->constrained('users')->onDelete('set null');
            $table->ipAddress('reporter_ip')->nullable();
            
            // Report Details
            $table->enum('reason', [
                'spam', 'inappropriate_content', 'harassment', 'copyright',
                'misinformation', 'hate_speech', 'violence', 'other'
            ]);
            $table->text('description')->nullable();
            $table->json('evidence')->nullable(); // Screenshots, links, etc.
            
            // Moderation Status
            $table->enum('status', ['pending', 'investigating', 'resolved', 'dismissed'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->enum('action_taken', [
                'no_action', 'content_removed', 'content_edited', 'user_warned', 
                'user_suspended', 'user_banned'
            ])->nullable();
            
            $table->timestamps();
            
            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });

        /**
         * Content Moderation Actions
         * Log all moderation actions for audit trail
         */
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            
            // Target content (polymorphic)
            $table->string('actionable_type', 100);
            $table->unsignedBigInteger('actionable_id');
            
            // Action Information
            $table->foreignId('moderator_id')->constrained('users')->onDelete('cascade');
            $table->enum('action_type', [
                'approve', 'reject', 'flag', 'unflag', 'hide', 'unhide',
                'delete', 'restore', 'edit', 'warn_user', 'suspend_user', 'ban_user'
            ]);
            $table->text('reason')->nullable();
            $table->json('details')->nullable(); // Additional action context
            $table->json('previous_state')->nullable(); // Store state before action
            
            // Related Report
            $table->foreignId('report_id')->nullable()->constrained('moderation_reports')->onDelete('set null');
            
            $table->timestamps();
            
            $table->index(['actionable_type', 'actionable_id']);
            $table->index(['moderator_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
        });

        // ================================
        // USER ENGAGEMENT
        // ================================
        
        /**
         * Article Views Tracking
         * Track article views with analytics data
         */
        Schema::create('article_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Session and Device Info
            $table->string('session_id', 200)->nullable();
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->string('device_type', 50)->nullable(); // mobile, tablet, desktop
            $table->string('browser', 100)->nullable();
            $table->string('platform', 100)->nullable();
            
            // Geographic and Referral
            $table->string('country_code', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            
            // Engagement Metrics
            $table->unsignedInteger('time_spent')->nullable(); // seconds
            $table->decimal('scroll_percentage', 5, 2)->default(0.00);
            $table->boolean('is_bounce')->default(true);
            
            $table->timestamps();
            
            $table->index(['article_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->unique(['article_id', 'user_id', 'session_id', 'created_at'], 'unique_view_tracking');
        });

        /**
         * Article Likes/Reactions
         */
        Schema::create('article_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('reaction_type', ['like', 'dislike', 'love', 'laugh', 'wow', 'sad', 'angry']);
            $table->timestamps();
            
            $table->unique(['article_id', 'user_id']);
            $table->index(['article_id', 'reaction_type']);
        });

        /**
         * User Subscriptions
         * Newsletter and content subscriptions
         */
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('email'); // For guest subscriptions
            
            // Subscription Types
            $table->boolean('newsletter')->default(false);
            $table->boolean('breaking_news')->default(false);
            $table->boolean('weekly_digest')->default(false);
            $table->json('category_subscriptions')->nullable(); // Array of category IDs
            $table->json('tag_subscriptions')->nullable(); // Array of tag IDs
            $table->json('author_subscriptions')->nullable(); // Array of author IDs
            
            // Preferences
            $table->enum('frequency', ['immediate', 'daily', 'weekly', 'monthly'])->default('weekly');
            $table->time('preferred_time')->default('09:00');
            $table->string('timezone', 50)->default('UTC');
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_token', 100)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['user_id', 'email']);
            $table->index(['email', 'is_active']);
            $table->index(['is_active', 'frequency']);
        });

        // ================================
        // ADVANCED CONTENT FEATURES
        // ================================
        
        /**
         * Article Series
         * Group related articles into series
         */
        Schema::create('article_series', function (Blueprint $table) {
            $table->id();
            $table->string('title', 300);
            $table->string('slug', 400)->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('article_count')->default(0);
            $table->timestamps();
            
            $table->index(['slug', 'is_active']);
            $table->index(['created_by', 'is_active']);
        });

        /**
         * Series Articles (Many-to-Many with order)
         */
        Schema::create('series_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained('article_series')->onDelete('cascade');
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('order_in_series');
            $table->timestamps();
            
            $table->unique(['series_id', 'article_id']);
            $table->unique(['series_id', 'order_in_series']);
            $table->index(['article_id']);
        });

        /**
         * Content Scheduling
         * Advanced scheduling for articles and social media
         */
        Schema::create('content_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            
            // Scheduling Information
            $table->timestamp('scheduled_for');
            $table->enum('action', ['publish', 'unpublish', 'archive', 'feature', 'unfeature']);
            $table->json('action_data')->nullable(); // Additional parameters for the action
            
            // Social Media Integration
            $table->json('social_platforms')->nullable(); // Facebook, Twitter, LinkedIn, etc.
            $table->text('social_message')->nullable();
            $table->json('social_settings')->nullable(); // Platform-specific settings
            
            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('executed_at')->nullable();
            $table->text('execution_log')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['scheduled_for', 'status']);
            $table->index(['article_id', 'action']);
        });

        // ================================
        // ANALYTICS AND REPORTING
        // ================================
        
        /**
         * Article Analytics (Daily Aggregates)
         * Pre-calculated analytics for performance
         */
        Schema::create('article_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->date('date');
            
            // Traffic Metrics
            $table->unsignedInteger('unique_views')->default(0);
            $table->unsignedInteger('total_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->decimal('avg_time_spent', 8, 2)->default(0.00); // seconds
            $table->decimal('bounce_rate', 5, 2)->default(0.00); // percentage
            
            // Engagement Metrics
            $table->unsignedInteger('comments_added')->default(0);
            $table->unsignedInteger('likes_added')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('social_shares')->default(0);
            
            // Traffic Sources
            $table->json('traffic_sources')->nullable(); // Direct, Search, Social, etc.
            $table->json('top_referrers')->nullable();
            $table->json('geographic_data')->nullable(); // Country/city breakdown
            
            $table->timestamps();
            
            $table->unique(['article_id', 'date']);
            $table->index(['date', 'unique_views']);
            $table->index(['article_id', 'date']);
        });

        // ================================
        // SYSTEM CONFIGURATION
        // ================================
        
        /**
         * System Settings
         * Configurable system-wide settings
         */
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 200)->unique();
            $table->longText('value')->nullable();
            $table->string('type', 50)->default('string'); // string, json, boolean, integer, etc.
            $table->string('group', 100)->default('general');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be accessed by frontend
            $table->boolean('is_editable')->default(true);
            $table->timestamps();
            
            $table->index(['group', 'is_public']);
            $table->index('key');
        });

        /**
         * Activity Logs
         * System-wide activity logging
         */
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            
            // Actor (who performed the action)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('user_type', 100)->nullable(); // For polymorphic users
            
            // Subject (what was acted upon)
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            
            // Action Details
            $table->string('action', 100); // created, updated, deleted, etc.
            $table->string('description')->nullable();
            $table->json('properties')->nullable(); // Old/new values, metadata
            
            // Context
            $table->string('batch_uuid', 36)->nullable(); // Group related actions
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
            $table->index('batch_uuid');
        });

        /**
         * Email Templates
         * Manage system email templates
         */
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->string('subject', 300);
            $table->longText('html_content');
            $table->longText('text_content')->nullable();
            $table->json('variables')->nullable(); // Available template variables
            $table->string('category', 100)->default('general');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index('name');
        });

        // ================================
        // NOTIFICATION SYSTEM
        // ================================
        
        /**
         * Notifications
         * System-wide notification management
         */
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 200);
            $table->string('notifiable_type', 100);
            $table->unsignedBigInteger('notifiable_id');
            $table->longText('data'); // JSON data
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['type', 'read_at']);
        });

        // ================================
        // SEO AND REDIRECTS
        // ================================
        
        /**
         * URL Redirects
         * Manage 301/302 redirects for SEO
         */
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_url', 500);
            $table->string('new_url', 500);
            $table->enum('type', ['301', '302', '307', '308'])->default('301');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique('old_url');
            $table->index(['old_url', 'is_active']);
            $table->index('hit_count');
        });

        // ================================
        // ADDITIONAL INDEXES FOR PERFORMANCE
        // ================================
        
        // Add composite indexes for common queries after table creation
        Schema::table('articles', function (Blueprint $table) {
            $table->index(['category_id', 'is_featured', 'published_at'], 'articles_category_featured_published');
            $table->index(['author_id', 'status', 'published_at'], 'articles_author_status_published');
            $table->index(['is_breaking_news', 'status', 'published_at'], 'articles_breaking_status_published');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->index(['commentable_type', 'commentable_id', 'parent_id', 'status'], 'comments_polymorphic_parent_status');
        });

        // ================================
        // INITIAL DATA SEEDING
        // ================================
        
        // Insert default roles
        DB::table('roles')->insert([
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full system access and control',
                'permissions' => json_encode(['*']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can manage all content and moderate comments',
                'permissions' => json_encode([
                    'articles.create', 'articles.edit', 'articles.delete', 'articles.publish',
                    'comments.moderate', 'users.manage', 'categories.manage', 'tags.manage'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Can create and edit own articles',
                'permissions' => json_encode([
                    'articles.create', 'articles.edit.own', 'articles.view.own'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Moderator',
                'slug' => 'moderator',
                'description' => 'Can moderate comments and handle reports',
                'permissions' => json_encode([
                    'comments.moderate', 'reports.handle', 'users.warn'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Subscriber',
                'slug' => 'subscriber',
                'description' => 'Regular user with commenting privileges',
                'permissions' => json_encode([
                    'comments.create', 'articles.view', 'profile.edit.own'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert default categories
        DB::table('categories')->insert([
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'Latest tech news and innovations',
                'parent_id' => null,
                'level' => 0,
                'path' => 'technology',
                'sort_order' => 1,
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Business news and market updates',
                'parent_id' => null,
                'level' => 0,
                'path' => 'business',
                'sort_order' => 2,
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'description' => 'Sports news and updates',
                'parent_id' => null,
                'level' => 0,
                'path' => 'sports',
                'sort_order' => 3,
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Entertainment',
                'slug' => 'entertainment',
                'description' => 'Entertainment and celebrity news',
                'parent_id' => null,
                'level' => 0,
                'path' => 'entertainment',
                'sort_order' => 4,
                'is_featured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Insert default system settings
        DB::table('settings')->insert([
            [
                'key' => 'site.name',
                'value' => 'News Management System',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Website name',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site.description',
                'value' => 'A comprehensive news management platform',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Website description',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'comments.require_approval',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'moderation',
                'description' => 'Require approval for new comments',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'articles.auto_publish',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'publishing',
                'description' => 'Automatically publish articles without review',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'media.max_upload_size',
                'value' => '10485760',
                'type' => 'integer',
                'group' => 'media',
                'description' => 'Maximum file upload size in bytes (10MB)',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to handle foreign key constraints
        Schema::dropIfExists('series_articles');
        Schema::dropIfExists('article_series');
        Schema::dropIfExists('content_schedules');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('article_analytics');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('article_reactions');
        Schema::dropIfExists('article_views');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('moderation_reports');
        Schema::dropIfExists('comment_reactions');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('media_usage');
        Schema::dropIfExists('media');
        Schema::dropIfExists('article_tags');
        Schema::dropIfExists('article_revisions');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
