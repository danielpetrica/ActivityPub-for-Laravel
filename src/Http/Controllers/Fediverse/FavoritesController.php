<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers\Fediverse;

use DanielPetrica\LaravelActivityPub\Services\ActivityInteractions;
use DanielPetrica\LaravelActivityPub\Traits\ResolvesLocalActor;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class FavoritesController extends Controller
{
    use ResolvesLocalActor;

    public function __invoke(): View
    {
        $user = auth()->user();
        $localActor = $this->resolveLocalActor();

        $bookmarks = ActivityInteractions::bookmarks(actor: $localActor);

        return view(view: 'activitypub::fediverse.favorites', data: [
            'bookmarks' => $bookmarks,
            'actor' => $user,
        ]);
    }
}
