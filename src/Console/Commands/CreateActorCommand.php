<?php

namespace DanielPetrica\LaravelActivityPub\Console\Commands;

use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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
            $this->info(string: 'ActivityPub version: '.$version);
        }

        $username = $this->option(key: 'username')
            ?? $this->ask(question: 'Enter the actor username (e.g., "daniel")');

        $name = $this->option(key: 'name')
            ?? $this->ask(question: 'Enter the display name for the actor (optional)', default: $username);

        // Validate
        $validator = Validator::make(
            data: ['username' => $username, 'name' => $name],
            rules: [
                'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:actors,username'],
                'name' => ['nullable', 'string', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error(string: $error);
            }

            return self::FAILURE;
        }

        // Generate RSA key pair
        $this->info(string: 'Generating RSA 2048-bit key pair...');

        try {
            $keyPair = $this->generateKeyPair();
        } catch (Throwable $e) {
            $this->error(string: 'Failed to generate RSA key pair: '.$e->getMessage());

            Log::error('ActivityPub: failed to generate RSA key pair', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        // Create actor
        $actor = Actor::query()->create(attributes: [
            'username' => $username,
            'name' => $name,
            'public_key_pem' => $keyPair['public'],
            'private_key_pem' => $keyPair['private'],
            'manually_approves_followers' => false,
        ]);

        $actorUrl = $actor->actor_id;

        $this->info(string: 'Actor created successfully!');
        $this->info(string: '  Username: '.$actor->username);
        $this->info(string: '  Actor ID: '.$actorUrl);
        $this->info(string: '  Inbox: '.$actor->inbox_url);
        $this->info(string: '  Outbox: '.$actor->outbox_url);

        return self::SUCCESS;
    }

    /**
     * @return array{public: string, private: string}
     */
    protected function generateKeyPair(): array
    {
        $keyResource = openssl_pkey_new(options: [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($keyResource === false) {
            throw new \RuntimeException(message: 'Failed to generate RSA key pair.');
        }

        $privateKey = '';

        $exported = openssl_pkey_export(key: $keyResource, output: $privateKey);

        if ($exported === false) {
            throw new \RuntimeException(message: 'Failed to export private key.');
        }

        $publicKey = openssl_pkey_get_details(key: $keyResource)['key'];

        return [
            'public' => $publicKey,
            'private' => $privateKey,
        ];
    }
}
