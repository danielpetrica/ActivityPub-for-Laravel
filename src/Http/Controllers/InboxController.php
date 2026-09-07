<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Actions\InboxProcessor;
use DanielPetrica\LaravelActivityPub\Http\Controllers\Concerns\RespondsToAccept;
use DanielPetrica\LaravelActivityPub\Http\Requests\InboxRequest;
use DanielPetrica\LaravelActivityPub\Http\Resources\ActivityResource;
use DanielPetrica\LaravelActivityPub\Http\Resources\OrderedCollection;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class InboxController extends Controller
{
    use RespondsToAccept;

    public function __construct(
        private InboxProcessor $inboxProcessor,
    ) {}

    public function index(Request $request, Actor $actor): JsonResponse
    {
        if (! $this->wantsJson($request)) {
            return response()->json(
                data: ['error' => 'This endpoint serves ActivityPub JSON. Use an appropriate Accept header.'],
                status: 406,
            );
        }

        $perPage = 20;
        $page = (int) $request->query(key: 'page', default: 1);
        $page = max($page, 1);

        $baseQuery = Activity::query()
            ->where(column: 'actor_id', operator: '=', value: $actor->id)
            ->where(column: 'is_incoming', operator: '=', value: true);

        $totalItems = (clone $baseQuery)->count();
        $totalPages = (int) ceil(num: $totalItems / $perPage);

        if ($page === 1) {
            $activities = (clone $baseQuery)
                ->orderByDesc(column: 'created_at')
                ->offset(($page - 1) * $perPage)
                ->limit(value: $perPage)
                ->get();

            $items = $activities->map(function (Activity $activity) use ($request) {
                return ActivityResource::format(
                    activity: $activity,
                    request: $request,
                );
            })->toArray();
        } else {
            $items = [];
        }

        if ($totalPages > 1) {
            $collection = OrderedCollection::makePage(
                id: $actor->inbox_url.'?page='.$page,
                partOf: $actor->inbox_url,
                items: $items,
                totalItems: $totalItems,
                next: $page < $totalPages ? $actor->inbox_url.'?page='.($page + 1) : null,
                prev: $page > 1 ? $actor->inbox_url.'?page='.($page - 1) : null,
            );
        } else {
            $collection = OrderedCollection::make(
                id: $actor->inbox_url,
                items: $items,
                totalItems: $totalItems,
                first: $actor->inbox_url.'?page=1',
                last: $actor->inbox_url.'?page=1',
            );
        }

        return $this->activityPubResponse($request, $collection);
    }

    public function __invoke(InboxRequest $request, Actor $actor): JsonResponse
    {
        if ($request->header('Content-Length') && (int) $request->header('Content-Length') > 1048576) {
            return response()->json(['error' => 'Payload too large.'], 413);
        }

        $payload = $request->all();
        $actorUrl = $payload['actor'];
        $remoteActor = $request->attributes->get(key: 'remote_actor');

        if ($remoteActor instanceof RemoteActor && $actorUrl !== $remoteActor->actor_url) {
            return response()->json(
                data: ['error' => 'Actor in payload does not match signature.'],
                status: 401,
            );
        }

        $this->inboxProcessor->process(payload: $payload);

        return response()->json(
            data: ['status' => 'accepted'],
            status: 202,
        );
    }

    public function sharedInbox(InboxRequest $request): JsonResponse
    {
        if ($request->header('Content-Length') && (int) $request->header('Content-Length') > 1048576) {
            return response()->json(['error' => 'Payload too large.'], 413);
        }

        $payload = $request->all();
        $actorUrl = $payload['actor'];
        $remoteActor = $request->attributes->get(key: 'remote_actor');

        if ($remoteActor instanceof RemoteActor && $actorUrl !== $remoteActor->actor_url) {
            return response()->json(
                data: ['error' => 'Actor in payload does not match signature.'],
                status: 401,
            );
        }

        $actor = $this->resolveTargetActor(payload: $payload);

        if ($actor === null) {
            return response()->json(
                data: ['status' => 'accepted'],
                status: 202,
            );
        }

        $this->inboxProcessor->process(payload: $payload);

        return response()->json(
            data: ['status' => 'accepted'],
            status: 202,
        );
    }

    protected function resolveTargetActor(array $payload): ?Actor
    {
        $all = array_merge(
            (array) ($payload['to'] ?? []),
            (array) ($payload['cc'] ?? []),
        );

        $domain = parse_url(url: config('activitypub.domain'), component: PHP_URL_HOST);

        foreach ($all as $url) {
            if (! is_string($url)) {
                continue;
            }

            $urlDomain = parse_url(url: $url, component: PHP_URL_HOST);

            if ($urlDomain !== $domain) {
                continue;
            }

            $username = basename($url);

            $actor = Actor::query()
                ->where(column: 'username', operator: '=', value: $username)
                ->first();

            if ($actor !== null) {
                return $actor;
            }
        }

        return null;
    }
}
