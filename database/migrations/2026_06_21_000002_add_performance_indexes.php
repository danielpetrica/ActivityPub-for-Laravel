<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->index(['actor_id', 'is_incoming', 'created_at'], 'activities_actor_incoming_created');
            $table->index(['remote_actor_id', 'is_incoming', 'type', 'created_at'], 'activities_feed_index');
        });

        Schema::table('followers', function (Blueprint $table): void {
            $table->index(['remote_actor_id', 'actor_id', 'status'], 'followers_remote_actor_status');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex('activities_actor_incoming_created');
            $table->dropIndex('activities_feed_index');
        });

        Schema::table('followers', function (Blueprint $table): void {
            $table->dropIndex('followers_remote_actor_status');
        });
    }
};
