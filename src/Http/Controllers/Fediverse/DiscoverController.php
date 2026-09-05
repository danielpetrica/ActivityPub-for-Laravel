<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Services\RemoteActorResolver;
use DanielPetrica\LaravelActivityPub\Services\WebFingerService;
use DanielPetrica\LaravelActivityPub\Traits\LogsActivityPub;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class DiscoverController extends Controller
{
    use LogsActivityPub;
    use ResolvesLocalActor;

    public function __construct(
        private WebFingerService $webFingerService,
        private RemoteActorResolver $remoteActorResolver,
    ) {}

    public function index(): View
    {
        $user = auth()->user();

        return view(view: 'activitypub::fediverse.discover', data: [
            'actor' => $user,
        ]);
    }

    public function resolve(Request $request): RedirectResponse|View
    {
        $request->validate(rules: [
            'handle' => ['required', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $handle = ltrim(string: $request->input(key: 'handle'), characters: '@');

        if (! str_contains(haystack: $handle, needle: '@')) {
            return redirect()
                ->route(route: 'fediverse.discover')
                ->withErrors(['handle' => 'Enter a Fediverse address like user@domain.com']);
        }

        $parts = explode(separator: '@', string: $handle);
        $username = $parts[0];
        $domain = $parts[1] ?? null;

        if ($domain === null) {
            return redirect()
                ->route(route: 'fediverse.discover')
                ->withErrors(['handle' => 'Could not parse domain from the address']);
        }

        $this->activityPubLog('info', 'Resolving handle', ['handle' => $handle, 'username' => $username, 'domain' => $domain]);

        $resource = 'acct:'.$username.'@'.$domain;
        $webfingerResult = $this->webFingerService->resolve(resource: $resource);

        if ($webfingerResult === null || ! isset($webfingerResult['href'])) {
            $this->activityPubLog('warning', 'WebFinger resolution failed', ['handle' => $handle, 'resource' => $resource]);

            return redirect()
                ->route(route: 'fediverse.discover')
                ->withErrors(['handle' => 'Could not find that Fediverse account']);
        }

        $actorUrl = $webfingerResult['href'];

        $this->activityPubLog('info', 'WebFinger resolved, fetching actor profile', ['handle' => $handle, 'actorUrl' => $actorUrl]);

        $data = $this->remoteActorResolver->fetchActorData(actorUri: $actorUrl);

        if ($data === null) {
            $this->activityPubLog('warning', 'Could not fetch remote actor profile', ['handle' => $handle, 'actorUrl' => $actorUrl]);

            return redirect()
                ->route(route: 'fediverse.discover')
                ->withErrors(['handle' => 'Could not fetch the remote actor profile']);
        }

        $this->activityPubLog('info', 'Remote actor profile fetched', ['handle' => $handle, 'actorUrl' => $actorUrl, 'username' => $data['preferredUsername'] ?? 'unknown']);

        $remoteActorModel = $this->remoteActorResolver->upsertFromData(
            actorUri: $actorUrl,
            data: $data,
        );

        $isFollowing = Activity::query()
            ->where(column: 'actor_id', operator: '=', value: $localActor->id)
            ->where(column: 'type', operator: '=', value: ActivityType::Follow)
            ->where(column: 'remote_actor_id', operator: '=', value: $remoteActorModel->id)
            ->where(column: 'is_incoming', operator: '=', value: false)
            ->exists();

        return view(view: 'activitypub::fediverse.discover', data: [
            'actor' => $user,
            'remoteActor' => $data,
            'remoteActorUrl' => $actorUrl,
            'handle' => $handle,
            'domain' => $domain,
            'isFollowing' => $isFollowing,
        ]);
    }
}
