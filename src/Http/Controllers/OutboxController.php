<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Http\Resources\ActivityResource;
use DanielPetrica\LaravelActivityPub\Http\Resources\OrderedCollection;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class OutboxController extends Controller
{
    public function __invoke(Request $request, Actor $actor): JsonResponse
    {
        $perPage = 20;
        $page = (int) $request->query(key: 'page', default: 1);
        $page = max($page, 1);

        $baseQuery = Activity::query()
            ->where(column: 'actor_id', operator: '=', value: $actor->id)
            ->where(column: 'is_incoming', operator: '=', value: false);

        $totalItems = (clone $baseQuery)->count();
        $totalPages = (int) ceil(num: $totalItems / $perPage);

        if ($page === 1) {
            $activities = (clone $baseQuery)
                ->orderByDesc(column: 'created_at')
                ->offset(($page - 1) * $perPage)
                ->limit(value: $perPage)
                ->get();

            $items = $activities->map(function (Activity $activity) use ($request) {
                return ActivityResource::make(
                    activity: $activity,
                    request: $request,
                );
            })->toArray();
        } else {
            $items = [];
        }

        if ($totalPages > 1) {
            $collection = OrderedCollection::makePage(
                id: $actor->outbox_url.'?page='.$page,
                partOf: $actor->outbox_url,
                items: $items,
                totalItems: $totalItems,
                next: $page < $totalPages ? $actor->outbox_url.'?page='.($page + 1) : null,
                prev: $page > 1 ? $actor->outbox_url.'?page='.($page - 1) : null,
            );
        } else {
            $collection = OrderedCollection::make(
                id: $actor->outbox_url,
                items: $items,
                totalItems: $totalItems,
                first: $actor->outbox_url.'?page=1',
                last: $actor->outbox_url.'?page=1',
            );
        }

        return response()->json(
            data: $collection,
            headers: ['Content-Type' => 'application/activity+json'],
        );
    }
}
