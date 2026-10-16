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
use Illuminate\Support\Facades\Log;

final class InboxController extends Controller
{
    use RespondsToAccept;

    private const PER_PAGE = 20;

    public function __construct(
        private InboxProcessor $inboxProcessor,
    ) {}

    public function index(Request $request, Actor $actor): JsonResponse
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
            ->where('is_incoming', '=', true);

        $totalItems = (clone $baseQuery)->count();
        $totalPages = max((int) ceil($totalItems / $perPage), 1);

        $collectionId = $actor->inbox_url;

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

    public function __invoke(InboxRequest $request, Actor $actor): JsonResponse
    {
        if (strlen($request->getContent()) > 1048576) {
            return response()->json(['error' => 'Payload too large.'], 413);
        }

        $payload = $request->all();
        $actorUrl = $payload['actor'];
        $remoteActor = $request->attributes->get('remote_actor');

        if ($remoteActor instanceof RemoteActor && $actorUrl !== $remoteActor->actor_url) {
            return response()->json(
                ['error' => 'Actor in payload does not match signature.'],
                401,
            );
        }

        $this->dispatchInboxProcessing(payload: $payload);

        return response()->json(
            ['status' => 'accepted'],
            202,
        );
    }

    public function sharedInbox(InboxRequest $request): JsonResponse
    {
        if (strlen($request->getContent()) > 1048576) {
            return response()->json(['error' => 'Payload too large.'], 413);
        }

        $payload = $request->all();
        $actorUrl = $payload['actor'];
        $remoteActor = $request->attributes->get('remote_actor');

        if ($remoteActor instanceof RemoteActor && $actorUrl !== $remoteActor->actor_url) {
            return response()->json(
                ['error' => 'Actor in payload does not match signature.'],
                401,
            );
        }

        // The shared inbox fans out to every local actor. Resolution is used to
        // record which local actor (if any) an activity was explicitly
        // addressed to, but an unresolved target must never silently drop the
        // activity: the InboxProcessor performs its own (broader) resolution
        // and logs anything it cannot route.
        $target = $this->resolveTargetActor(payload: $payload);

        if ($target === null) {
            Log::debug(
                'Shared inbox: no local actor explicitly addressed; passing payload to processor.',
                ['type' => $payload['type'] ?? null],
            );
        }

        $this->dispatchInboxProcessing(payload: $payload);

        return response()->json(
            ['status' => 'accepted'],
            202,
        );
    }

    /**
     * Hand the verified payload to the inbox pipeline.
     *
     * By default processing is queued so signature verification stays the only
     * work on the HTTP request and remote resolution runs on a worker. Set
     * `activitypub.queue.process_inbox` to false to process inline.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function dispatchInboxProcessing(array $payload): void
    {
        if (config('activitypub.queue.process_inbox', true)) {
            $this->inboxProcessor->queue(payload: $payload);

            return;
        }

        $this->inboxProcessor->process(payload: $payload);
    }

    /**
     * Resolve the local actor an incoming activity is addressed to.
     *
     * Handles actor URLs, followers/following collection URLs, trailing
     * slashes, `@username` paths as well as `to`/`cc`/`bto`/`bcc`. Values may
     * be plain IRI strings or embedded objects exposing an `id`.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveTargetActor(array $payload): ?Actor
    {
        $domain = parse_url(config('activitypub.domain'), PHP_URL_HOST);

        if ($domain === null) {
            return null;
        }

        foreach ($this->addressingValues($payload) as $url) {
            if (parse_url($url, PHP_URL_HOST) !== $domain) {
                continue;
            }

            $username = $this->extractUsername(url: $url);

            if ($username === null) {
                continue;
            }

            $actor = Actor::query()
                ->where('username', '=', $username)
                ->first();

            if ($actor !== null) {
                return $actor;
            }
        }

        return null;
    }

    /**
     * Collect every IRI that could identify the addressed local actor from the
     * addressing fields and the activity object.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function addressingValues(array $payload): array
    {
        $urls = [];

        foreach (['to', 'cc', 'bto', 'bcc'] as $field) {
            foreach ((array) ($payload[$field] ?? []) as $value) {
                if (is_string($value) && $value !== '') {
                    $urls[] = $value;
                } elseif (is_array($value) && isset($value['id']) && is_string($value['id']) && $value['id'] !== '') {
                    $urls[] = $value['id'];
                }
            }
        }

        $object = $payload['object'] ?? null;

        if (is_string($object) && $object !== '') {
            $urls[] = $object;
        } elseif (is_array($object)) {
            foreach (['id', 'object'] as $key) {
                if (isset($object[$key]) && is_string($object[$key]) && $object[$key] !== '') {
                    $urls[] = $object[$key];
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Extract a local username from an actor or collection URL.
     *
     * Supports `.../users/{name}`, `.../users/{name}/followers`, a trailing
     * slash and `.../@name`.
     */
    protected function extractUsername(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return null;
        }

        $usersIndex = array_search('users', $segments, true);

        if ($usersIndex !== false && isset($segments[$usersIndex + 1])) {
            return ltrim($segments[$usersIndex + 1], '@');
        }

        return ltrim((string) end($segments), '@') ?: null;
    }
}
