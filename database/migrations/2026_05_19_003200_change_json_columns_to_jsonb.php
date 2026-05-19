<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->jsonb('seo_metadata')->nullable()->change();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->jsonb('seo_metadata')->nullable()->change();
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->jsonb('seo_metadata')->nullable()->change();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->jsonb('content')->change();
            $table->jsonb('seo_metadata')->nullable()->change();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->jsonb('content')->change();
            $table->jsonb('seo_metadata')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('seo_metadata')->nullable()->change();
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->json('seo_metadata')->nullable()->change();
        });

        Schema::table('tools', function (Blueprint $table) {
            $table->json('seo_metadata')->nullable()->change();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->json('content')->change();
            $table->json('seo_metadata')->nullable()->change();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->json('content')->change();
            $table->json('seo_metadata')->nullable()->change();
        });
    }
};
