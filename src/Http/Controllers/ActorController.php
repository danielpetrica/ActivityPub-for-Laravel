<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Http\Controllers\Concerns\RespondsToAccept;
use DanielPetrica\LaravelActivityPub\Http\Resources\ActorResource;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ActorController extends Controller
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

        $actorData = (new ActorResource(resource: $actor))->toArray(request: $request);

        return $this->activityPubResponse($request, $actorData);
    }
}
