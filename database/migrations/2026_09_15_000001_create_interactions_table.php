<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'interactions', callback: static function (Blueprint $table): void {
            $table->id();

            $table->string(column: 'type');
            $table->foreignId(column: 'actor_id')
                ->nullable()
                ->constrained(table: 'actors')
                ->cascadeOnDelete();
            $table->foreignId(column: 'remote_actor_id')
                ->nullable()
                ->constrained(table: 'remote_actors')
                ->cascadeOnDelete();

            // Polymorphic-style columns (not using Laravel morphs so we can use string IDs)
            $table->string(column: 'object_type');
            $table->string(column: 'object_id');

            $table->string(column: 'status')->default(value: 'Active');

            $table->timestamps();

            // Indexes
            $table->index(['type', 'object_type', 'object_id'], 'interactions_type_object');
            // Unique combo: one interaction per actor per object.
            // Enforced at application level, not as a DB unique constraint,
            // to allow SoftDeletes flexibility later.
            $table->index(['type', 'object_type', 'object_id', 'actor_id'], 'interactions_type_object_actor');
            $table->index(['actor_id'], 'interactions_actor');
            $table->index(['remote_actor_id'], 'interactions_remote_actor');
            $table->index(['type', 'status'], 'interactions_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'interactions');
    }
};
