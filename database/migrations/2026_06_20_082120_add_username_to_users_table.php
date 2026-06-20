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
        Schema::table(table: 'users', callback: static function (Blueprint $table): void {
            $table->string(column: 'username')->nullable()->unique()->after(column: 'email');
        });
    }

    public function down(): void
    {
        Schema::table(table: 'users', callback: static function (Blueprint $table): void {
            $table->dropColumn(columns: 'username');
        });
    }
};
