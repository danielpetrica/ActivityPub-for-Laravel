<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            // The ActivityStreams `id` of an incoming activity. Incoming
            // activities are stamped with it after handling so that
            // redeliveries can be detected and ignored (idempotency).
            $table->string(column: 'activity_uri')->nullable()->after(column: 'object_id');
            $table->unique('activity_uri');
        });
    }

    public function down(): void
    {
        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            $table->dropUnique(['activity_uri']);
            $table->dropColumn(columns: 'activity_uri');
        });
    }
};
