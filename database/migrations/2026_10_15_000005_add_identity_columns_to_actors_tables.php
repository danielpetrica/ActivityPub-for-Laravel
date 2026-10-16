<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'actors', callback: static function (Blueprint $table): void {
            // ActivityStreams `alsoKnownAs`: additional account IRIs the actor
            // is known by. Stored as a JSON list of absolute IRIs.
            $table->json(column: 'also_known_as')->nullable()->after(column: 'published');

            // ActivityStreams `movedTo`: the new actor IRI after an account
            // migration. Stored on the (local) actor for completeness.
            $table->string(column: 'moved_to')->nullable()->after(column: 'also_known_as');
        });

        Schema::table(table: 'remote_actors', callback: static function (Blueprint $table): void {
            // Recorded from an inbound Move so operators can follow migrations.
            $table->string(column: 'moved_to')->nullable()->after(column: 'icon_url');
            $table->json(column: 'also_known_as')->nullable()->after(column: 'moved_to');
        });
    }

    public function down(): void
    {
        Schema::table(table: 'actors', callback: static function (Blueprint $table): void {
            $table->dropColumn(columns: ['also_known_as', 'moved_to']);
        });

        Schema::table(table: 'remote_actors', callback: static function (Blueprint $table): void {
            $table->dropColumn(columns: ['moved_to', 'also_known_as']);
        });
    }
};
