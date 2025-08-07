<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            
            $table->index(['is_active', 'next_run_at']);
            $table->index('last_run_at');
            
            $table->comment('Manages scheduled fetching of news from MediaStack API');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fetch_schedules');
    }
};