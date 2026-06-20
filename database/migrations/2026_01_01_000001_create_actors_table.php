<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'actors', callback: static function (Blueprint $table): void {
            $table->id();

            $table->string(column: 'username')->unique();
            $table->string(column: 'name')->nullable();
            $table->text(column: 'summary')->nullable();
            $table->string(column: 'icon_url')->nullable();
            $table->string(column: 'image_url')->nullable();

            // Computed URLs. If null, derived from domain + username at runtime.
            $table->string(column: 'inbox_url')->nullable();
            $table->string(column: 'outbox_url')->nullable();
            $table->string(column: 'followers_url')->nullable();
            $table->string(column: 'following_url')->nullable();

            // RSA key pair for HTTP Signatures
            $table->text(column: 'public_key_pem');
            $table->text(column: 'private_key_pem');

            $table->boolean(column: 'manually_approves_followers')->default(value: false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(table: 'actors');
    }
};
