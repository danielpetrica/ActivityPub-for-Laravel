<?php

namespace DanielPetrica\LaravelActivityPub\Console\Commands;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\KeyPairService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

final class CreateActorCommand extends Command
{
    protected $signature = 'activitypub:create-actor
        {--username= : The actor username (e.g., "daniel")}
        {--name= : The display name for the actor (optional)}';

    protected $description = 'Create a new ActivityPub actor with an RSA key pair.';

    public function handle(): int
    {
        $version = config('activitypub.version');

        if ($version) {
            $this->info('ActivityPub version: '.$version);
        }

        $username = $this->option('username')
            ?? $this->ask(question: 'Enter the actor username (e.g., "daniel")');

        $name = $this->option('name')
            ?? $this->ask(question: 'Enter the display name for the actor (optional)', default: $username);

        // Validate
        $validator = Validator::make(
            ['username' => $username, 'name' => $name],
            [
                'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:actors,username'],
                'name' => ['nullable', 'string', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Verify a matching user exists in the application
        $userModel = config('auth.providers.users.model');

        if ($userModel !== null && class_exists($userModel) && Schema::hasTable((new $userModel)->getTable())) {
            $user = $userModel::query()
                ->where('username', '=', $username)
                ->orWhere('email', 'like', $username.'@%')
                ->first();

            if ($user === null) {
                $this->error("Cannot create actor: no user found with username '{$username}'.");

                return self::FAILURE;
            }
        }

        // Generate RSA key pair
        $this->info('Generating RSA 2048-bit key pair...');

        try {
            $keyPair = app(KeyPairService::class)->generate();
        } catch (Throwable $e) {
            $this->error('Failed to generate RSA key pair: '.$e->getMessage());

            Log::error('ActivityPub: failed to generate RSA key pair', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        // Create actor
        $actor = Actor::query()->create([
            'username' => $username,
            'name' => $name,
            'public_key_pem' => $keyPair['public'],
            'private_key_pem' => $keyPair['private'],
            'manually_approves_followers' => false,
        ]);

        $actorUrl = $actor->actor_id;

        $this->info('Actor created successfully!');
        $this->info('  Username: '.$actor->username);
        $this->info('  Actor ID: '.$actorUrl);
        $this->info('  Inbox: '.$actor->inbox_url);
        $this->info('  Outbox: '.$actor->outbox_url);

        return self::SUCCESS;
    }
}
