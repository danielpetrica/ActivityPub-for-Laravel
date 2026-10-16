<?php

namespace DanielPetrica\LaravelActivityPub\Http\Controllers;

use DanielPetrica\LaravelActivityPub\Contracts\FederatableContentContract;
use DanielPetrica\LaravelActivityPub\Http\Controllers\Concerns\RespondsToAccept;
use DanielPetrica\LaravelActivityPub\Http\Resources\OrderedCollection;
use DanielPetrica\LaravelActivityPub\Models\Actor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class FeaturedController extends Controller
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

        $items = $this->pinnedActivityPubIds(actor: $actor);
        $totalItems = count($items);
        $totalPages = max((int) ceil($totalItems / $perPage), 1);

        $collectionId = $actor->actor_id.'/featured';

        if ($requestedPage === null) {
            return $this->activityPubResponse($request, OrderedCollection::make(
                id: $collectionId,
                totalItems: $totalItems,
                first: $collectionId.'?page=1',
                last: $collectionId.'?page='.$totalPages,
            ));
        }

        $page = min(max((int) $requestedPage, 1), $totalPages);

        $pageItems = array_slice($items, ($page - 1) * $perPage, $perPage);

        return $this->activityPubResponse($request, OrderedCollection::makePage(
            id: $collectionId.'?page='.$page,
            partOf: $collectionId,
            items: $pageItems,
            totalItems: $totalItems,
            next: $page < $totalPages ? $collectionId.'?page='.($page + 1) : null,
            prev: $page > 1 ? $collectionId.'?page='.($page - 1) : null,
        ));
    }

    /**
     * Resolve the ActivityPub IDs of every pinned piece of content owned by the
     * actor, across all configured federatable models.
     *
     * Defensive by design: unknown/misconfigured models are skipped, and an
     * empty `federatable_models` config yields an empty collection.
     *
     * @return array<int, string>
     */
    protected function pinnedActivityPubIds(Actor $actor): array
    {
        $items = [];

        foreach ((array) config('activitypub.federatable_models', []) as $modelClass) {
            if (! is_string($modelClass) || ! class_exists($modelClass)) {
                continue;
            }

            if (! is_subclass_of($modelClass, FederatableContentContract::class)) {
                continue;
            }

            if (! is_subclass_of($modelClass, Model::class)) {
                continue;
            }

            $items = array_merge($items, $this->pinnedActivityPubIdsForModel(
                modelClass: $modelClass,
                actor: $actor,
            ));
        }

        return array_values(array_unique($items));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    protected function pinnedActivityPubIdsForModel(string $modelClass, Actor $actor): array
    {
        try {
            $probe = new $modelClass;
            $table = $probe->getTable();
        } catch (Throwable) {
            return [];
        }

        try {
            $query = $modelClass::query();

            // Narrow the query at the database level where the model supports
            // it, so we never load a whole table just to check the flag.
            if (Schema::hasColumn($table, 'pinned')) {
                $query->where('pinned', '=', true);
            }

            $items = [];

            foreach ($query->get() as $content) {
                if (! $content instanceof FederatableContentContract) {
                    continue;
                }

                if (! $content->shouldFederate() || ! $content->isActivityPubPinned()) {
                    continue;
                }

                if ($content->activityPubActor()->getActorId() !== $actor->actor_id) {
                    continue;
                }

                $items[] = $content->getActivityPubId();
            }

            return $items;
        } catch (Throwable) {
            return [];
        }
    }
}
