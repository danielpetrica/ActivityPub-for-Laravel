<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Http\Controllers\Concerns\RespondsToAccept;
use DanielPetrica\LaravelActivityPub\Http\Resources\ActivityResource;
use DanielPetrica\LaravelActivityPub\Http\Resources\OrderedCollection;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class OutboxController extends Controller
{
    use RespondsToAccept;

    private const PER_PAGE = 20;

    public function __invoke(Request $request, Actor $actor): JsonResponse
    {
        if (! $this->wantsJson($request)) {
            return response()->json(
                ['error' => 'This endpoint serves ActivityPub JSON. Use an appropriate Accept header.'],
                406,
            );
        }

        $perPage = self::PER_PAGE;
        $requestedPage = $request->query('page');

        $baseQuery = Activity::query()
            ->where('actor_id', '=', $actor->id)
            ->where('is_incoming', '=', false);

        $totalItems = (clone $baseQuery)->count();
        $totalPages = max((int) ceil($totalItems / $perPage), 1);

        $collectionId = $actor->outbox_url;

        // No ?page= parameter: serve the top-level collection. It only carries
        // metadata and the first/last page links; the items live on the pages.
        if ($requestedPage === null) {
            return $this->activityPubResponse($request, OrderedCollection::make(
                id: $collectionId,
                totalItems: $totalItems,
                first: $collectionId.'?page=1',
                last: $collectionId.'?page='.$totalPages,
            ));
        }

        $page = min(max((int) $requestedPage, 1), $totalPages);

        $activities = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $activities->map(function (Activity $activity) use ($request): array {
            return ActivityResource::format(
                activity: $activity,
                request: $request,
            );
        })->toArray();

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
