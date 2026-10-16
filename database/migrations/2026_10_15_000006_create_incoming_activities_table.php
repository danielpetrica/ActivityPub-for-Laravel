<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reservation table for actor-scoped inbox idempotency. A row is
        // claimed (insert-or-ignore) *before* the handler runs, so concurrent
        // redeliveries of the same activity cannot double-process.
        Schema::create(table: 'incoming_activities', callback: static function (Blueprint $table): void {
            $table->id();

            $table->foreignId(column: 'actor_id')
                ->constrained(table: 'actors')
                ->cascadeOnDelete();

            // The ActivityStreams `id` of an incoming activity.
            $table->string(column: 'activity_uri');

            $table->timestamps();

            // Dedupe is scoped per local actor, so the same activity addressed
            // to a second local actor is still processed for that actor.
            $table->unique(columns: ['actor_id', 'activity_uri']);
        });

        // Re-scope the activity log's uniqueness the same way: the same
        // activity id may legitimately be recorded once per local actor.
        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            $table->dropUnique(index: 'activities_activity_uri_unique');
            $table->unique(columns: ['actor_id', 'activity_uri']);
        });
    }

    public function down(): void
    {
        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            $table->dropUnique(index: 'activities_actor_id_activity_uri_unique');
            $table->unique(columns: 'activity_uri');
        });

        Schema::dropIfExists(table: 'incoming_activities');
    }
};
