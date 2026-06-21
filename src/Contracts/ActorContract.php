<?php

namespace DanielPetrica\LaravelActivityPub\Contracts;

interface ActorContract
{
    public function getPreferredUsername(): string;

    public function getDisplayName(): string;

    public function getSummary(): ?string;

    public function getIconUrl(): ?string;

    public function getHeaderImageUrl(): ?string;

    public function getActorId(): string;

    public function getInboxUrl(): string;

    public function getOutboxUrl(): string;

    public function getFollowersUrl(): string;

    public function getFollowingUrl(): string;

    public function getPublicKey(): string;

    public function getKeyId(): string;

    public function getPrivateKeyPem(): ?string;
}
