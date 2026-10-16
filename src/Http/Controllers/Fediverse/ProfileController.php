<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Http\Requests\ProfileUpdateRequest;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use DanielPetrica\LaravelActivityPub\Services\ActivityPubService;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    use ResolvesLocalActor;

    public function edit(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $parsedUrl = parse_url($localActor->actor_id);

        return view('activitypub::fediverse.profile', [
            'actor' => $user,
            'localActor' => $localActor,
            'actorDomain' => $parsedUrl['host'].(isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : ''),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $localActor->update([
            'name' => $request->input('name'),
            'summary' => $request->input('summary'),
            'icon_url' => $request->input('icon_url'),
            'image_url' => $request->input('image_url'),
        ]);

        $this->applyAlsoKnownAs(request: $request, localActor: $localActor);

        // Federate the profile change as an Update(Person) to every follower.
        app(ActivityPubService::class)->updateActorProfile(actor: $localActor);

        return redirect()
            ->route('fediverse.profile')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Persist `also_known_as` when supplied as an array of valid absolute URLs.
     * Values are sanitized; invalid entries are dropped rather than rejected so
     * a partial list never blocks a profile save.
     */
    protected function applyAlsoKnownAs(ProfileUpdateRequest $request, Actor $localActor): void
    {
        $input = $request->input('also_known_as');

        if (! is_array($input)) {
            return;
        }

        $aliases = array_values(array_filter(
            $input,
            static fn (mixed $entry): bool => is_string($entry) && filter_var($entry, FILTER_VALIDATE_URL) !== false,
        ));

        $localActor->update(['also_known_as' => $aliases]);
    }
}
