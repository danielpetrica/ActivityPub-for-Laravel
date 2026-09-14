<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_url')->nullable();
            $table->string('key_id')->nullable();
            $table->string('ip')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->integer('status_code');
            $table->string('reason');
            $table->text('body_summary')->nullable();
            $table->json('headers')->nullable();
            $table->timestamps();

            $table->foreign('actor_id')->references('id')->on('actors')->cascadeOnDelete();
            $table->index('actor_id');
            $table->index('status_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_logs');
    }
};
