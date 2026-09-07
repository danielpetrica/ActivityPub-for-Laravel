<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            $table->text(column: 'debug')->nullable()->after(column: 'status');
        });
    }

    public function down(): void
    {
        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            $table->dropColumn(columns: 'debug');
        });
    }
};
