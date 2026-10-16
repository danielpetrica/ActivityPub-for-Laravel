<?php

namespace DanielPetrica\LaravelActivityPub\Helpers;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use Illuminate\Support\Facades\DB;

final class PostStats
{
    /**
     * Get engagement stats for a post by its ActivityPub object ID.
     *
     * @return array{likes: int, reblogs: int, replies: int}
     */
    public static function forObject(string $objectId): array
    {
        $counts = Activity::query()
            ->select('type', DB::raw('count(*) as total'))
            ->where('object_id', '=', $objectId)
            ->whereIn('type', [ActivityType::Like, ActivityType::Announce, ActivityType::Create])
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return [
            'likes' => $counts[ActivityType::Like->value] ?? 0,
            'reblogs' => $counts[ActivityType::Announce->value] ?? 0,
            'replies' => $counts[ActivityType::Create->value] ?? 0,
        ];
    }

    /**
     * Get total follower count for a local actor.
     */
    public static function followerCount(int $actorId): int
    {
        return \DanielPetrica\LaravelActivityPub\Models\Follower::query()
            ->where('actor_id', '=', $actorId)
            ->where('status', '=', 'accepted')
            ->count();
    }

    /**
     * Get total following count for a local actor.
     */
    public static function followingCount(int $actorId): int
    {
        return Activity::query()
            ->where('actor_id', '=', $actorId)
            ->where('type', '=', ActivityType::Follow)
            ->where('is_incoming', '=', false)
            ->distinct()
            ->count('remote_actor_id');
    }

    /**
     * Get counts for incoming activities of each type for a local actor.
     *
     * @return array<string, int>
     */
    public static function incomingCounts(int $actorId): array
    {
        return Activity::query()
            ->select('type', DB::raw('count(*) as total'))
            ->where('actor_id', '=', $actorId)
            ->where('is_incoming', '=', true)
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();
    }

    /**
     * Get counts for outgoing activities of each type for a local actor.
     *
     * @return array<string, int>
     */
    public static function outgoingCounts(int $actorId): array
    {
        return Activity::query()
            ->select('type', DB::raw('count(*) as total'))
            ->where('actor_id', '=', $actorId)
            ->where('is_incoming', '=', false)
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();
    }
}
