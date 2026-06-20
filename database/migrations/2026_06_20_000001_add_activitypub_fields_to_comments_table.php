<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'comments', callback: static function (Blueprint $table): void {
            $table->unsignedBigInteger(column: 'remote_actor_id')->nullable()->after(column: 'user_id');
            $table->string(column: 'remote_activity_id')->nullable()->after(column: 'remote_actor_id');

            $table->foreign(columns: 'remote_actor_id')
                ->references(columns: 'id')
                ->on(table: 'remote_actors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(table: 'comments', callback: static function (Blueprint $table): void {
            $table->dropForeign(index: 'comments_remote_actor_id_foreign');
            $table->dropColumn(columns: ['remote_activity_id', 'remote_actor_id']);
        });
    }
};
