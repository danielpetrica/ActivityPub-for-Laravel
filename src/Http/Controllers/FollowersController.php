<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Http\Resources\OrderedCollection;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Follower;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FollowersController extends Controller
{
    public function __invoke(Request $request, Actor $actor): JsonResponse
    {
        $perPage = min(max((int) $request->query('perPage', 20), 1), 100);
        $page = max((int) $request->query('page', 1), 1);

        $baseQuery = Follower::query()
            ->with('remoteActor')
            ->where('actor_id', '=', $actor->id)
            ->where('status', '=', FollowerStatus::Accepted);

        $totalItems = (clone $baseQuery)->count();
        $totalPages = (int) ceil($totalItems / $perPage);

        if ($page === 1) {
            $firstItems = (clone $baseQuery)
                ->orderBy('created_at')
                ->offset(0)
                ->limit($perPage)
                ->get();

            $items = $firstItems->map(fn (Follower $f) => $f->remoteActor->actor_url)->toArray();
        } else {
            $items = [];
        }

        if ($totalPages > 1) {
            $collection = OrderedCollection::makePage(
                id: $actor->followers_url.'?page='.$page,
                partOf: $actor->followers_url,
                items: $items,
                totalItems: $totalItems,
                next: $page < $totalPages ? $actor->followers_url.'?page='.($page + 1) : null,
                prev: $page > 1 ? $actor->followers_url.'?page='.($page - 1) : null,
            );
        } else {
            $collection = OrderedCollection::make(
                id: $actor->followers_url,
                items: $items,
                totalItems: $totalItems,
                first: $actor->followers_url.'?page=1',
                last: $actor->followers_url.'?page=1',
            );
        }

        return response()->json($collection, headers: ['Content-Type' => 'application/activity+json']);
    }
}
