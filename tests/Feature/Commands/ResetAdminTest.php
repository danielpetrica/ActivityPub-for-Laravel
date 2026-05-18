<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('resets the selected user when users exist and password is provided', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@danielpetrica.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    $this->artisan('app:reset-admin', [
        '--password' => 'new-password',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain("Admin user 'admin@danielpetrica.com' has been reset")
        ->assertSuccessful();

    $user->refresh();

    expect($user->email_verified_at)->toBeNull();
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});

it('creates a new user when no users exist and password is provided', function () {
    $this->artisan('app:reset-admin', [
        '--password' => 'new-password',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('has been reset')
        ->assertSuccessful();

    $user = User::first();

    expect($user)->not->toBeNull();
    expect($user->email)->toBe('admin@danielpetrica.com');
    expect($user->name)->toBe('Admin');
    expect($user->email_verified_at)->toBeNull();
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});
