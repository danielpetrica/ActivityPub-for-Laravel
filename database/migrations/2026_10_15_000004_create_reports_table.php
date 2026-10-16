<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'reports', callback: static function (Blueprint $table): void {
            $table->id();

            // The local actor that received the report.
            $table->foreignId(column: 'actor_id')
                ->nullable()
                ->constrained(table: 'actors')
                ->cascadeOnDelete();

            // The remote actor that filed the report, when resolvable.
            $table->foreignId(column: 'remote_actor_id')
                ->nullable()
                ->constrained(table: 'remote_actors')
                ->nullOnDelete();

            // Reported target (object id/type). Both nullable: a Flag may
            // reference an object or actor we cannot resolve.
            $table->string(column: 'object_type')->nullable();
            $table->string(column: 'object_id')->nullable();

            $table->text(column: 'reason')->nullable();

            // open | resolved (string, not enum, so consumers can extend).
            $table->string(column: 'status')->default(value: 'open');

            $table->string(column: 'activity_uri')->nullable();
            $table->json(column: 'payload')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at'], 'reports_status_created');
            $table->index('object_id', 'reports_object_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'reports');
    }
};
