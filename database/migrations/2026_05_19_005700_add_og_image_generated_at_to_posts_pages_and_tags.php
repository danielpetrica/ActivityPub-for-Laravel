<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('og_image_generated_at')->nullable()->after('og_image');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->timestamp('og_image_generated_at')->nullable()->after('og_image');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->timestamp('og_image_generated_at')->nullable()->after('og_image');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('og_image_generated_at');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('og_image_generated_at');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('og_image_generated_at');
        });
    }
};
