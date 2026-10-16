<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Enums\FollowerStatus;
use DanielPetrica\LaravelActivityPub\Http\Controllers\Concerns\RespondsToAccept;
use DanielPetrica\LaravelActivityPub\Http\Resources\OrderedCollection;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\Following;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class FollowingController extends Controller
{
    use RespondsToAccept;

    public function __invoke(Request $request, Actor $actor): JsonResponse
    {
        if (! $this->wantsJson($request)) {
            return response()->json(
                ['error' => 'This endpoint serves ActivityPub JSON. Use an appropriate Accept header.'],
                406,
            );
        }

        $perPage = min(max((int) $request->query('perPage', 20), 1), 100);
        $requestedPage = $request->query('page');

        $baseQuery = Following::query()
            ->with('remoteActor')
            ->where('actor_id', '=', $actor->id)
            ->where('status', '=', FollowerStatus::Accepted);

        $totalItems = (clone $baseQuery)->count();
        $totalPages = max((int) ceil($totalItems / $perPage), 1);

        $collectionId = $actor->following_url;

        if ($requestedPage === null) {
            return $this->activityPubResponse($request, OrderedCollection::make(
                id: $collectionId,
                totalItems: $totalItems,
                first: $collectionId.'?page=1',
                last: $collectionId.'?page='.$totalPages,
            ));
        }

        $page = min(max((int) $requestedPage, 1), $totalPages);

        $following = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $following->map(fn (Following $f) => $f->remoteActor->actor_url)->values()->toArray();

        return $this->activityPubResponse($request, OrderedCollection::makePage(
            id: $collectionId.'?page='.$page,
            partOf: $collectionId,
            items: $items,
            totalItems: $totalItems,
            next: $page < $totalPages ? $collectionId.'?page='.($page + 1) : null,
            prev: $page > 1 ? $collectionId.'?page='.($page - 1) : null,
        ));
    }
}
