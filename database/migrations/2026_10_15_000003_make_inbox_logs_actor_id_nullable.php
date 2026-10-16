<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rejected requests are not always addressable to a specific local actor (for
 * example the shared inbox, or a malformed payload that never reached actor
 * binding). The audit row must still be persisted, so `actor_id` becomes
 * nullable instead of forcing a bogus foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbox_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inbox_logs', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable(false)->change();
        });
    }
};
