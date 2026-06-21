<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'remote_actors', callback: static function (Blueprint $table): void {
            $table->index(columns: 'domain');
        });

        Schema::table(table: 'followers', callback: static function (Blueprint $table): void {
            $table->index(columns: ['actor_id', 'status']);
        });

        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            $table->index(columns: ['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table(table: 'remote_actors', callback: static function (Blueprint $table): void {
            $table->dropIndex(index: 'remote_actors_domain_index');
        });

        Schema::table(table: 'followers', callback: static function (Blueprint $table): void {
            $table->dropIndex(index: 'followers_actor_id_status_index');
        });

        Schema::table(table: 'activities', callback: static function (Blueprint $table): void {
            $table->dropIndex(index: 'activities_status_created_at_index');
        });
    }
};
