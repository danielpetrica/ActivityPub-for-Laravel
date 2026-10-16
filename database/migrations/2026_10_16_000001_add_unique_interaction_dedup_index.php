<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `type` only ever holds a short InteractionType value (Like, Announce,
        // Bookmark, Quote, Comment), so it does not need the column's default
        // 255 characters. Tightening it keeps the composite unique index below
        // InnoDB's 3072-byte index-key limit for utf8mb4
        // (3 x 255 x 4 + 16 > 3072 without this).
        Schema::table(table: 'interactions', callback: static function (Blueprint $table): void {
            $table->string(column: 'type', length: 64)->change();
        });

        Schema::table(table: 'interactions', callback: static function (Blueprint $table): void {
            // Real database-level dedup that matches the semantics used by the
            // inbound Like/Announce handlers (`updateOrCreate`) and the local
            // `ActivityInteractions` service: one interaction per
            // (type, object, local actor, remote actor).
            //
            // `remote_actor_id` is part of the key on purpose. Inbound
            // interactions are attributed to the *local recipient* in
            // `actor_id` while the remote engager lands in `remote_actor_id`,
            // so distinct remote actors engaging the same object must not
            // collapse into a single row. It must not be dropped from the key.
            //
            // NULL semantics: on MySQL, SQLite and PostgreSQL a unique index
            // treats NULL values as distinct, so local interactions (which
            // carry a NULL `remote_actor_id`) are not constrained here and
            // stay deduplicated by `ActivityInteractions`. The inbound path,
            // which is the one the reviewer flagged, always sets a
            // non-null `remote_actor_id`, so it is fully constrained.
            $table->unique(
                ['type', 'object_type', 'object_id', 'actor_id', 'remote_actor_id'],
                'interactions_type_object_actor_remote_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table(table: 'interactions', callback: static function (Blueprint $table): void {
            $table->dropUnique(index: 'interactions_type_object_actor_remote_unique');
        });

        Schema::table(table: 'interactions', callback: static function (Blueprint $table): void {
            $table->string(column: 'type')->change();
        });
    }
};
