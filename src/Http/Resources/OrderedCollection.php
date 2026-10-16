<?php

namespace DanielPetrica\LaravelActivityPub\Http\Resources;

final class OrderedCollection
{
    /**
     * Build a top-level `OrderedCollection`.
     *
     * Per the ActivityStreams 2.0 spec the collection itself carries metadata
     * only (`totalItems`, `first`, `last`); the actual items live on the
     * `OrderedCollectionPage` documents reachable through `first`/`last`.
     * Embedding `orderedItems` here would duplicate (and, when the collection
     * spans multiple pages, incorrectly truncate) the data.
     *
     * @return array<string, mixed>
     */
    public static function make(
        string $id,
        int $totalItems,
        ?string $first = null,
        ?string $last = null,
    ): array {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $id,
            'type' => 'OrderedCollection',
            'totalItems' => $totalItems,
            'first' => $first ?? $id.'?page=1',
            'last' => $last ?? $id.'?page=1',
        ];
    }

    /**
     * Build an `OrderedCollectionPage` carrying one slice of items.
     *
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    public static function makePage(
        string $id,
        string $partOf,
        array $items,
        int $totalItems,
        ?string $next = null,
        ?string $prev = null,
    ): array {
        $page = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $id,
            'type' => 'OrderedCollectionPage',
            'partOf' => $partOf,
            'totalItems' => $totalItems,
            'orderedItems' => array_values($items),
        ];

        if ($next !== null) {
            $page['next'] = $next;
        }

        if ($prev !== null) {
            $page['prev'] = $prev;
        }

        return $page;
    }
}
