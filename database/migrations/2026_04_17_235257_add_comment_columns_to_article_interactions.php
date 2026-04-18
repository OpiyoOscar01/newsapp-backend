<?php
// database/migrations/2024_01_01_000001_add_comment_columns_to_article_interactions.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_interactions', function (Blueprint $table) {
            $table->text('comment_content')->nullable()->after('metadata');
            $table->foreignId('parent_comment_id')->nullable()->after('comment_content')
                  ->constrained('article_interactions')->onDelete('cascade');
            $table->boolean('is_edited')->default(false)->after('parent_comment_id');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            
            $table->index(['article_id', 'parent_comment_id']);
            $table->index(['interaction_type', 'interaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('article_interactions', function (Blueprint $table) {
            $table->dropForeign(['parent_comment_id']);
            $table->dropColumn(['comment_content', 'parent_comment_id', 'is_edited', 'edited_at']);
            $table->dropIndex(['article_id', 'parent_comment_id']);
            $table->dropIndex(['interaction_type', 'interaction_date']);
        });
    }
};