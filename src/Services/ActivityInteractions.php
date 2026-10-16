<?php

namespace DanielPetrica\LaravelActivityPub\Services;

use DanielPetrica\LaravelActivityPub\Enums\InteractionStatus;
use DanielPetrica\LaravelActivityPub\Enums\InteractionType;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Interaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class ActivityInteractions
{
    // ── Counting ──

    public static function likeCount(Model $model): int
    {
        return static::countForType(model: $model, type: InteractionType::Like);
    }

    public static function boostCount(Model $model): int
    {
        return static::countForType(model: $model, type: InteractionType::Announce);
    }

    public static function bookmarkCount(Model $model): int
    {
        return static::countForType(model: $model, type: InteractionType::Bookmark);
    }

    public static function commentCount(Model $model): int
    {
        return static::countForType(model: $model, type: InteractionType::Comment);
    }

    public static function quoteCount(Model $model): int
    {
        return static::countForType(model: $model, type: InteractionType::Quote);
    }

    // ── Boolean checks ──

    public static function hasLiked(Model $model, Actor $actor): bool
    {
        return static::existsForType(model: $model, actor: $actor, type: InteractionType::Like);
    }

    public static function hasBoosted(Model $model, Actor $actor): bool
    {
        return static::existsForType(model: $model, actor: $actor, type: InteractionType::Announce);
    }

    public static function hasBookmarked(Model $model, Actor $actor): bool
    {
        return static::existsForType(model: $model, actor: $actor, type: InteractionType::Bookmark);
    }

    // ── Mutations ──

    public static function like(Actor $actor, Model $model): Interaction
    {
        return static::createOrReactivate(actor: $actor, model: $model, type: InteractionType::Like);
    }

    public static function unlike(Actor $actor, Model $model): void
    {
        static::deactivate(actor: $actor, model: $model, type: InteractionType::Like);
    }

    public static function boost(Actor $actor, Model $model): Interaction
    {
        return static::createOrReactivate(actor: $actor, model: $model, type: InteractionType::Announce);
    }

    public static function unboost(Actor $actor, Model $model): void
    {
        static::deactivate(actor: $actor, model: $model, type: InteractionType::Announce);
    }

    public static function bookmark(Actor $actor, Model $model): Interaction
    {
        return static::createOrReactivate(actor: $actor, model: $model, type: InteractionType::Bookmark);
    }

    public static function unbookmark(Actor $actor, Model $model): void
    {
        static::deactivate(actor: $actor, model: $model, type: InteractionType::Bookmark);
    }

    // ── Queries ──

    /** @return \Illuminate\Support\Collection<int, Actor> */
    public static function likers(Model $model): Collection
    {
        return static::actorsForType(model: $model, type: InteractionType::Like);
    }

    /** @return \Illuminate\Support\Collection<int, Actor> */
    public static function boosters(Model $model): Collection
    {
        return static::actorsForType(model: $model, type: InteractionType::Announce);
    }

    /** @return \Illuminate\Support\Collection<int, Interaction> */
    public static function bookmarks(Actor $actor): Collection
    {
        return Interaction::query()
            ->active()
            ->ofType(type: InteractionType::Bookmark)
            ->forActor(actorId: $actor->id)
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, Interaction> */
    public static function recentForModel(Model $model, InteractionType $type, int $limit = 20): Collection
    {
        return Interaction::query()
            ->active()
            ->ofType(type: $type)
            ->forObject(
                objectType: $model->getMorphClass(),
                objectId: (string) $model->getKey(),
            )
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // ── Private helpers ──

    private static function countForType(Model $model, InteractionType $type): int
    {
        return Interaction::query()
            ->active()
            ->ofType(type: $type)
            ->forObject(
                objectType: $model->getMorphClass(),
                objectId: (string) $model->getKey(),
            )
            ->count();
    }

    private static function existsForType(Model $model, Actor $actor, InteractionType $type): bool
    {
        return Interaction::query()
            ->active()
            ->ofType(type: $type)
            ->forObject(
                objectType: $model->getMorphClass(),
                objectId: (string) $model->getKey(),
            )
            ->forActor(actorId: $actor->id)
            ->exists();
    }

    private static function createOrReactivate(Actor $actor, Model $model, InteractionType $type): Interaction
    {
        $existing = Interaction::query()
            ->ofType(type: $type)
            ->forObject(
                objectType: $model->getMorphClass(),
                objectId: (string) $model->getKey(),
            )
            ->forActor(actorId: $actor->id)
            ->first();

        if ($existing !== null) {
            $existing->update(['status' => InteractionStatus::Active]);

            return $existing->refresh();
        }

        return Interaction::create([
            'type' => $type,
            'status' => InteractionStatus::Active,
            'actor_id' => $actor->id,
            'object_type' => $model->getMorphClass(),
            'object_id' => (string) $model->getKey(),
        ]);
    }

    private static function deactivate(Actor $actor, Model $model, InteractionType $type): void
    {
        Interaction::query()
            ->active()
            ->ofType(type: $type)
            ->forObject(
                objectType: $model->getMorphClass(),
                objectId: (string) $model->getKey(),
            )
            ->forActor(actorId: $actor->id)
            ->update(['status' => InteractionStatus::Undone]);
    }

    /** @return \Illuminate\Support\Collection<int, Actor> */
    private static function actorsForType(Model $model, InteractionType $type): Collection
    {
        return Interaction::query()
            ->active()
            ->ofType(type: $type)
            ->forObject(
                objectType: $model->getMorphClass(),
                objectId: (string) $model->getKey(),
            )
            ->with('actor')
            ->get()
            ->map(fn (Interaction $interaction) => $interaction->actor)
            ->filter()
            ->values();
    }
}
