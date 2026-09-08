<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'blocked_domains', callback: static function (Blueprint $table): void {
            $table->id();
            $table->string(column: 'domain')->unique();
            $table->text(column: 'reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'blocked_domains');
    }
};
