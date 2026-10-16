<?php

namespace DanielPetrica\LaravelActivityPub\Actions\Handlers;

use DanielPetrica\LaravelActivityPub\Models\Actor;

interface ActivityHandler
{
    public function handles(): string;

    public function handle(Actor $actor, array $payload): void;
}
