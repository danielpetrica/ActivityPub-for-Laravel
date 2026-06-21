<?php

namespace DanielPetrica\LaravelActivityPub\Http\Resources;

final class OrderedCollection
{
    /**
     * @param  array<int, mixed>  $items
     * @return array<string, mixed>
     */
    public static function make(
        string $id,
        array $items,
        int $totalItems,
        ?string $first = null,
        ?string $last = null,
    ): array {
        $collection = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $id,
            'type' => 'OrderedCollection',
            'totalItems' => $totalItems,
        ];

        if ($items !== []) {
            $collection['orderedItems'] = $items;
        }

        $collection['first'] = $first ?? $id.'?page=1';
        $collection['last'] = $last ?? $id.'?page=1';

        return $collection;
    }

    /**
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
        ];

        if ($items !== []) {
            $page['orderedItems'] = $items;
        }

        if ($next !== null) {
            $page['next'] = $next;
        }

        if ($prev !== null) {
            $page['prev'] = $prev;
        }

        return $page;
    }
}
