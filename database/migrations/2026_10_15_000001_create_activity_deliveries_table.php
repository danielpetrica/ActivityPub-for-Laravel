<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'activity_deliveries', callback: static function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'activity_id')
                ->constrained(table: 'activities')
                ->cascadeOnDelete();

            $table->string(column: 'inbox_url');
            $table->string(column: 'status')->default(value: 'pending');
            $table->unsignedInteger(column: 'attempts')->default(value: 0);
            $table->integer(column: 'response_code')->nullable();
            $table->text(column: 'last_error')->nullable();
            $table->timestamp(column: 'delivered_at')->nullable();

            $table->timestamps();

            $table->unique(columns: ['activity_id', 'inbox_url']);
            $table->index(columns: 'status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'activity_deliveries');
    }
};
