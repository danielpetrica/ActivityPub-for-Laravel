<?php

namespace DanielPetrica\LaravelActivityPub\Handlers;

use DanielPetrica\LaravelActivityPub\Actions\Handlers\ActivityHandler;

/**
 * Registry of inbox activity handlers keyed by the activity type they handle.
 *
 * Supports both additive registration (a consumer adds support for a new
 * activity type) and replacement (a consumer overrides a core handler for an
 * existing type). Higher priority wins; ties fall back to registration order.
 */
final class HandlerRegistry
{
    /**
     * @var array<string, list<array{handler: ActivityHandler, priority: int, sequence: int}>>
     */
    private array $handlers = [];

    private int $sequence = 0;

    public function register(ActivityHandler $handler, int $priority = 0): void
    {
        $this->handlers[$handler->handles()][] = [
            'handler' => $handler,
            'priority' => $priority,
            'sequence' => $this->sequence++,
        ];
    }

    /**
     * Replace every handler registered for the handler's activity type.
     */
    public function replace(ActivityHandler $handler, int $priority = 0): void
    {
        $this->handlers[$handler->handles()] = [
            [
                'handler' => $handler,
                'priority' => $priority,
                'sequence' => $this->sequence++,
            ],
        ];
    }

    /**
     * Register a handler only when nothing is registered for its type yet.
     * Used to bootstrap the container-tagged handlers without clobbering a
     * handler a consumer has already registered or replaced.
     */
    public function registerIfTypeAbsent(ActivityHandler $handler, int $priority = 0): void
    {
        if ($this->hasType($handler->handles())) {
            return;
        }

        $this->register(handler: $handler, priority: $priority);
    }

    public function hasType(string $type): bool
    {
        return ($this->handlers[$type] ?? []) !== [];
    }

    public function firstForType(string $type): ?ActivityHandler
    {
        return $this->forType($type)[0] ?? null;
    }

    /**
     * @return list<ActivityHandler>
     */
    public function forType(string $type): array
    {
        $entries = $this->handlers[$type] ?? [];

        usort(
            $entries,
            static function (array $a, array $b): int {
                return [$b['priority'], $a['sequence']] <=> [$a['priority'], $b['sequence']];
            },
        );

        return array_map(
            static fn (array $entry): ActivityHandler => $entry['handler'],
            $entries,
        );
    }

    /**
     * @return list<ActivityHandler>
     */
    public function all(): array
    {
        $handlers = [];

        foreach (array_keys($this->handlers) as $type) {
            foreach ($this->forType($type) as $handler) {
                $handlers[] = $handler;
            }
        }

        return $handlers;
    }
}
