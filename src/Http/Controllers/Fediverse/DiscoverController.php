<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Enums\ActivityType;
use DanielPetrica\LaravelActivityPub\Models\Activity;
use DanielPetrica\LaravelActivityPub\Models\RemoteActor;
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

    private const DEFAULT_HANDLE = 'danielpetrica@infosec.exchange';

    public function index(): View
    {
        $user = auth()->user();

        $remoteActor = null;
        $remoteActorUrl = null;
        $handle = null;
        $domain = null;
        $isFollowing = false;

        $parts = explode('@', self::DEFAULT_HANDLE);
        $username = $parts[0];
        $domain = $parts[1];
        $resource = 'acct:'.$username.'@'.$domain;
        $webfingerResult = $this->webFingerService->resolve(resource: $resource);

        if ($webfingerResult !== null && isset($webfingerResult['href'])) {
            $actorUrl = $webfingerResult['href'];
            $data = $this->remoteActorResolver->fetchActorData(actorUri: $actorUrl);

            if ($data !== null) {
                $this->remoteActorResolver->upsertFromData(actorUri: $actorUrl, data: $data);

                $localActor = $this->resolveLocalActor();
                $remoteActorModel = RemoteActor::where('actor_url', $actorUrl)->first();

                $remoteActor = $data;
                $remoteActorUrl = $actorUrl;
                $handle = self::DEFAULT_HANDLE;

                if ($localActor && $remoteActorModel) {
                    $isFollowing = Activity::query()
                        ->where('actor_id', '=', $localActor->id)
                        ->where('type', '=', ActivityType::Follow)
                        ->where('remote_actor_id', '=', $remoteActorModel->id)
                        ->where('is_incoming', '=', false)
                        ->exists();
                }
            }
        }

        return view('activitypub::fediverse.discover', [
            'actor' => $user,
            'remoteActor' => $remoteActor,
            'remoteActorUrl' => $remoteActorUrl,
            'handle' => $handle,
            'domain' => $domain,
            'isFollowing' => $isFollowing,
        ]);
    }

    public function resolve(Request $request): RedirectResponse|View
    {
        $request->validate([
            'handle' => ['required', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $handle = ltrim($request->input('handle'), '@');

        if (! str_contains($handle, '@')) {
            return redirect()
                ->route('fediverse.discover')
                ->withErrors(['handle' => 'Enter a Fediverse address like user@domain.com']);
        }

        $parts = explode('@', $handle);
        $username = $parts[0];
        $domain = $parts[1] ?? null;

        if ($domain === null) {
            return redirect()
                ->route('fediverse.discover')
                ->withErrors(['handle' => 'Could not parse domain from the address']);
        }

        $this->activityPubLog('info', 'Resolving handle', ['handle' => $handle, 'username' => $username, 'domain' => $domain]);

        $resource = 'acct:'.$username.'@'.$domain;
        $webfingerResult = $this->webFingerService->resolve(resource: $resource);

        if ($webfingerResult === null || ! isset($webfingerResult['href'])) {
            $this->activityPubLog('warning', 'WebFinger resolution failed', ['handle' => $handle, 'resource' => $resource]);

            return redirect()
                ->route('fediverse.discover')
                ->withErrors(['handle' => 'Could not find that Fediverse account']);
        }

        $actorUrl = $webfingerResult['href'];

        $this->activityPubLog('info', 'WebFinger resolved, fetching actor profile', ['handle' => $handle, 'actorUrl' => $actorUrl]);

        $data = $this->remoteActorResolver->fetchActorData(actorUri: $actorUrl);

        if ($data === null) {
            $this->activityPubLog('warning', 'Could not fetch remote actor profile', ['handle' => $handle, 'actorUrl' => $actorUrl]);

            return redirect()
                ->route('fediverse.discover')
                ->withErrors(['handle' => 'Could not fetch the remote actor profile']);
        }

        $this->activityPubLog('info', 'Remote actor profile fetched', ['handle' => $handle, 'actorUrl' => $actorUrl, 'username' => $data['preferredUsername'] ?? 'unknown']);

        $remoteActorModel = $this->remoteActorResolver->upsertFromData(
            actorUri: $actorUrl,
            data: $data,
        );

        $isFollowing = Activity::query()
            ->where('actor_id', '=', $localActor->id)
            ->where('type', '=', ActivityType::Follow)
            ->where('remote_actor_id', '=', $remoteActorModel->id)
            ->where('is_incoming', '=', false)
            ->exists();

        return view('activitypub::fediverse.discover', [
            'actor' => $user,
            'remoteActor' => $data,
            'remoteActorUrl' => $actorUrl,
            'handle' => $handle,
            'domain' => $domain,
            'isFollowing' => $isFollowing,
        ]);
    }
}
