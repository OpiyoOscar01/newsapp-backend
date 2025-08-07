<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->index(['created_at', 'status']);
            $table->index(['endpoint', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('execution_time_ms');
            $table->index('started_at');
            
            $table->comment('Logs all API fetch operations for monitoring and debugging');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_fetch_logs');
    }
};