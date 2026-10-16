<?php

namespace DanielPetrica\LaravelActivityPub\Contracts;

interface ActorProfileContract
{
    public function getPreferredUsername(): string;

    public function getDisplayName(): string;

    public function getSummary(): ?string;

    public function getIconUrl(): ?string;

    public function getHeaderImageUrl(): ?string;

    /**
     * Get the public profile URL for this actor.
     *
     * The bundled `Actor` returns its ActivityPub actor IRI. That IRI is also
     * the canonical, content-negotiated profile document: a request with an
     * HTML `Accept` header is served the human-readable profile page, so the
     * value is valid for both the ActivityStreams `url` field and the
     * WebFinger `profile-page` link. Override this method to return a
     * separate, purely-HTML profile page URL when your app has one.
     */
    public function getProfileUrl(): string;
}
