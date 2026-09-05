<?php

namespace DanielPetrica\LaravelActivityPub\Traits;

use Illuminate\Support\Facades\Log;

trait LogsActivityPub
{
    protected function activityPubLog(string $level, string $message, array $context = []): void
    {
        if (! config('activitypub.logging.enabled')) {
            return;
        }

        $channel = config('activitypub.logging.channel');
        $logger = $channel ? Log::channel($channel) : Log::channel();

        $logger->{$level}("ActivityPub: {$message}", $context);
    }
}
