<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'blocked_remote_actors', callback: static function (Blueprint $table): void {
            $table->id();
            $table->foreignId(column: 'remote_actor_id')->constrained('remote_actors')->cascadeOnDelete();
            $table->text(column: 'reason')->nullable();
            $table->timestamps();
            $table->unique('remote_actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'blocked_remote_actors');
    }
};
