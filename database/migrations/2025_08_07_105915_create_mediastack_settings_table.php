<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('mediastack_settings');
    }
};