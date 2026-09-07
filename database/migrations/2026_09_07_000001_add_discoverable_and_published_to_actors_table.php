<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'actors', callback: static function (Blueprint $table): void {
            $table->boolean(column: 'discoverable')->default(value: true);
            $table->timestamp(column: 'published')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(table: 'actors', callback: static function (Blueprint $table): void {
            $table->dropColumn(columns: ['discoverable', 'published']);
        });
    }
};
